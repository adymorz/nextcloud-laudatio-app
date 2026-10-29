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
	/** Events on a fixed day, unlike yearly gifts that are open for a whole year */
	private const DATED_TYPES = [Event::TYPE_BIRTHDAY, Event::TYPE_JUBILEE];

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
	 * @param list<Event::TYPE_*> $types only these types, all if empty
	 * @return list<array{Event, RecipientService::ROLE_*|null}>
	 */
	public function forUser(string $userId, DateTimeImmutable $from, ?DateTimeImmutable $to, bool $recipientsOnly = false, array $types = []): array {
		$includeAll = !$recipientsOnly && $this->access->isManager($userId);
		$result = [];
		foreach ($this->eventMapper->findBetween($from, $to, $types) as $event) {
			$role = $this->recipients->roleOf($event, $userId);
			if ($role !== null || $includeAll) {
				$result[] = [$event, $role];
			}
		}
		return $result;
	}

	/**
	 * All stored birthdays and jubilees, past and upcoming, the user receives
	 * (for the calendar). Yearly gifts have no fixed day and are left out.
	 *
	 * @return list<array{Event, RecipientService::ROLE_*}>
	 */
	public function allAsRecipient(string $userId): array {
		$result = [];
		foreach ($this->eventMapper->findAll(self::DATED_TYPES) as $event) {
			$role = $this->recipients->roleOf($event, $userId);
			if ($role !== null) {
				$result[] = [$event, $role];
			}
		}
		return $result;
	}

	/**
	 * Birthdays and jubilees of the next days, followed by the yearly gifts of
	 * every calendar year the period touches, including overdue ones of this
	 * year.
	 *
	 * @return list<array{Event, RecipientService::ROLE_*|null}>
	 */
	public function nextDays(string $userId, int $days): array {
		$today = $this->clock->today();
		$to = Dates::addDays($today, $days);
		return [
			...$this->forUser($userId, $today, $to, false, self::DATED_TYPES),
			...$this->forUser($userId, Dates::yearStart((int)$today->format('Y')), Dates::yearEnd((int)$to->format('Y')), false, [Event::TYPE_GIFT]),
		];
	}

	/**
	 * Whether the user may mark the event as greeted, or a gift as presented:
	 * the responsible person and managers. Birthdays and jubilees only when
	 * the "greeted" checklist is enabled.
	 *
	 * @param RecipientService::ROLE_*|null $role role of the user for the event
	 */
	public function canMark(Event $event, ?string $role, string $userId): bool {
		if ($role !== RecipientService::ROLE_RESPONSIBLE && !$this->access->isManager($userId)) {
			return false;
		}
		return $event->getType() === Event::TYPE_GIFT || $this->settings->isGreetingReminderEnabled();
	}

	/**
	 * Marks an event as greeted, or a gift as presented (or not) by the user.
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
		if ($role === null && !$this->access->isManager($userId)) {
			throw new NotFoundException($this->l10n->t('The event does not exist.'));
		}
		if (!$this->canMark($event, $role, $userId)) {
			throw new ForbiddenException($event->getType() === Event::TYPE_GIFT
				? $this->l10n->t('Only the responsible person can mark a gift as presented.')
				: $this->l10n->t('Only the responsible person can mark an event as greeted.'));
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
