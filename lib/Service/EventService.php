<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Adrian Dymorz <dev@adrian.dymorz.ch>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Appreciation\Service;

use DateTimeImmutable;
use OCA\Appreciation\AppInfo\Application;
use OCA\Appreciation\Db\Event;
use OCA\Appreciation\Db\EventMapper;
use OCA\Appreciation\Db\Group;
use OCA\Appreciation\Db\GroupMapper;
use OCA\Appreciation\Db\NotifiedMapper;
use OCA\Appreciation\Db\OrgFunctionMapper;
use OCA\Appreciation\Db\PersonMapper;
use OCA\Appreciation\Db\SliceMapper;
use OCA\Appreciation\Model\PlannedEvent;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\DB\Exception as DbException;
use OCP\Notification\IManager as INotificationManager;
use Psr\Log\LoggerInterface;

/**
 * Keeps the stored events in line with the master data.
 *
 * Events are generated for [today, today + WINDOW_DAYS]. Synchronizing inserts
 * missing events, updates changed ones (keeping the greeted state) and removes
 * future events that are no longer valid. Past events are kept as history.
 */
class EventService {
	public const int WINDOW_DAYS = 400;

	public function __construct(
		private PersonMapper $personMapper,
		private SliceMapper $sliceMapper,
		private OrgFunctionMapper $functionMapper,
		private GroupMapper $groupMapper,
		private EventMapper $eventMapper,
		private NotifiedMapper $notifiedMapper,
		private EventCalculator $calculator,
		private SettingsService $settings,
		private Clock $clock,
		private INotificationManager $notificationManager,
		private LoggerInterface $logger,
	) {
	}

	/**
	 * @return array{inserted: int, updated: int, deleted: int}
	 */
	public function syncAll(): array {
		$totals = ['inserted' => 0, 'updated' => 0, 'deleted' => 0];
		$yearsByFunction = $this->jubileeYearsByFunction();
		foreach ($this->personMapper->findAll() as $person) {
			$result = $this->syncPerson($person->getId(), $yearsByFunction);
			foreach ($totals as $key => $value) {
				$totals[$key] = $value + $result[$key];
			}
		}
		return $totals;
	}

	/**
	 * @param array<int, list<int>>|null $yearsByFunction precomputed jubilee years per function
	 * @return array{inserted: int, updated: int, deleted: int}
	 */
	public function syncPerson(int $personId, ?array $yearsByFunction = null): array {
		$result = ['inserted' => 0, 'updated' => 0, 'deleted' => 0];
		try {
			$person = $this->personMapper->find($personId);
		} catch (DoesNotExistException) {
			return $result;
		}

		$from = $this->clock->today();
		$to = Dates::addDays($from, self::WINDOW_DAYS);
		$planned = $this->calculator->calculate(
			$person,
			$this->sliceMapper->findByPerson($personId),
			$yearsByFunction ?? $this->jubileeYearsByFunction(),
			$from,
			$to,
			$this->settings->isBirthdayEventsEnabled(),
			$this->settings->isJubileeEventsEnabled(),
		);

		$existing = [];
		foreach ($this->eventMapper->findByPerson($personId, $from) as $event) {
			$existing[$event->getUid()] = $event;
		}

		foreach ($planned as $plannedEvent) {
			$event = $existing[$plannedEvent->uid] ?? null;
			unset($existing[$plannedEvent->uid]);
			if ($event === null) {
				if ($this->insert($plannedEvent)) {
					$result['inserted']++;
				}
			} elseif ($this->applyChanges($event, $plannedEvent)) {
				$this->eventMapper->update($event);
				$result['updated']++;
			}
		}

		if ($existing !== []) {
			$this->deleteEvents(array_values($existing));
			$result['deleted'] = count($existing);
		}
		return $result;
	}

	/**
	 * Deletes all events of a person, including past ones.
	 */
	public function deleteAllForPerson(int $personId): void {
		$this->deleteEvents($this->eventMapper->findByPerson($personId));
	}

	/**
	 * @param list<Event> $events
	 */
	public function deleteEvents(array $events): void {
		if ($events === []) {
			return;
		}
		$ids = array_map(static fn (Event $event): int => $event->getId(), $events);
		$this->dismissNotifications($ids);
		$this->notifiedMapper->deleteByEventIds($ids);
		foreach ($events as $event) {
			$this->eventMapper->delete($event);
		}
	}

	/**
	 * Removes the Nextcloud notifications of the given events for all users.
	 *
	 * @param list<int> $eventIds
	 */
	public function dismissNotifications(array $eventIds): void {
		foreach ($eventIds as $eventId) {
			$notification = $this->notificationManager->createNotification();
			$notification->setApp(Application::APP_ID)
				->setObject('event', (string)$eventId);
			$this->notificationManager->markProcessed($notification);
		}
	}

	/**
	 * Jubilee years for every function: the group override or the global list.
	 *
	 * @return array<int, list<int>>
	 */
	public function jubileeYearsByFunction(): array {
		$global = $this->settings->getJubileeYears();
		$yearsByGroup = [];
		foreach ($this->groupMapper->findAll() as $group) {
			$yearsByGroup[$group->getId()] = $this->jubileeYearsForGroup($group, $global);
		}
		$result = [];
		foreach ($this->functionMapper->findAll() as $function) {
			$result[$function->getId()] = $yearsByGroup[$function->getGroupId()] ?? $global;
		}
		return $result;
	}

	/**
	 * @param list<int>|null $global
	 * @return list<int>
	 */
	public function jubileeYearsForGroup(Group $group, ?array $global = null): array {
		$override = $group->getJubileeYears();
		if ($override !== null) {
			try {
				return $this->settings->parseJubileeYears($override);
			} catch (\Exception $e) {
				$this->logger->warning('Invalid jubilee years for group ' . $group->getId() . ', using the global setting', ['exception' => $e]);
			}
		}
		return $global ?? $this->settings->getJubileeYears();
	}

	private function insert(PlannedEvent $planned): bool {
		$event = new Event();
		$event->setUid($planned->uid);
		$event->setType($planned->type);
		$event->setPersonId($planned->personId);
		$event->setFunctionId($planned->functionId);
		$event->setEventDate($planned->date);
		$event->setOrdinal($planned->ordinal);
		$event->setCreatedAt($this->clock->time());
		try {
			$this->eventMapper->insert($event);
			return true;
		} catch (DbException $e) {
			// Inserted concurrently by another request or the background job
			if ($e->getReason() === DbException::REASON_UNIQUE_CONSTRAINT_VIOLATION) {
				return false;
			}
			throw $e;
		}
	}

	private function applyChanges(Event $event, PlannedEvent $planned): bool {
		$changed = false;
		if (Dates::compare($event->getEventDate(), $planned->date) !== 0) {
			$event->setEventDate($planned->date);
			$changed = true;
		}
		if ($event->getOrdinal() !== $planned->ordinal) {
			$event->setOrdinal($planned->ordinal);
			$changed = true;
		}
		return $changed;
	}

	/**
	 * Events in a date range (both inclusive), ordered by date.
	 *
	 * @return list<Event>
	 */
	public function findBetween(DateTimeImmutable $from, ?DateTimeImmutable $to): array {
		return $this->eventMapper->findBetween($from, $to);
	}
}
