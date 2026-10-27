<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Adrian Dymorz <dev@adrian.dymorz.ch>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Appreciation\Service;

use DateTimeImmutable;
use OCA\Appreciation\Db\Event;
use OCA\Appreciation\Db\EventMapper;
use OCA\Appreciation\Exception\ForbiddenException;
use OCA\Appreciation\Exception\NotFoundException;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\IL10N;

/**
 * The events a user sees: recipients see the events of their groups with
 * their role, managers additionally see all other events (without role).
 */
class UpcomingService {
	public function __construct(
		private EventMapper $eventMapper,
		private RecipientService $recipients,
		private AccessService $access,
		private SettingsService $settings,
		private Clock $clock,
		private IL10N $l10n,
	) {
	}

	/**
	 * @param bool $recipientsOnly true to leave out events the user is no recipient of, even for managers
	 * @return list<array{Event, RecipientService::ROLE_*|null}>
	 */
	public function forUser(string $userId, DateTimeImmutable $from, ?DateTimeImmutable $to, bool $recipientsOnly = false): array {
		$includeAll = !$recipientsOnly && $this->access->isManager($userId);
		$result = [];
		foreach ($this->eventMapper->findBetween($from, $to) as $event) {
			$role = $this->recipients->roleOf($event, $userId);
			if ($role !== null || $includeAll) {
				$result[] = [$event, $role];
			}
		}
		return $result;
	}

	/**
	 * @return list<array{Event, RecipientService::ROLE_*|null}>
	 */
	public function nextDays(string $userId, int $days): array {
		$today = $this->clock->today();
		return $this->forUser($userId, $today, Dates::addDays($today, $days));
	}

	/**
	 * Marks an event as greeted (or not) by the user.
	 *
	 * @return array{Event, RecipientService::ROLE_*|null}
	 * @throws NotFoundException when the event does not exist or is not visible to the user
	 * @throws ForbiddenException when the user may not mark the event
	 */
	public function setGreeted(string $userId, int $eventId, bool $greeted): array {
		try {
			$event = $this->eventMapper->find($eventId);
		} catch (DoesNotExistException) {
			throw new NotFoundException($this->l10n->t('The event does not exist.'));
		}
		$role = $this->recipients->roleOf($event, $userId);
		$isManager = $this->access->isManager($userId);
		if ($role === null && !$isManager) {
			throw new NotFoundException($this->l10n->t('The event does not exist.'));
		}
		if (!$this->settings->isGreetingReminderEnabled() || ($role !== RecipientService::ROLE_RESPONSIBLE && !$isManager)) {
			throw new ForbiddenException($this->l10n->t('Only the responsible person can mark an event as greeted.'));
		}

		if ($greeted && $event->getGreetedAt() === null) {
			$event->setGreetedAt($this->clock->time());
			$event->setGreetedBy($userId);
			$this->eventMapper->update($event);
		} elseif (!$greeted && $event->getGreetedAt() !== null) {
			$event->setGreetedAt(null);
			$event->setGreetedBy(null);
			$this->eventMapper->update($event);
		}
		return [$event, $role];
	}
}
