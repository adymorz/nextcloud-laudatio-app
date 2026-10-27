<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Adrian Dymorz <dev@adrian.dymorz.ch>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Appreciation\Calendar;

use OCA\Appreciation\Db\Event;
use OCA\Appreciation\Service\Dates;
use OCA\Appreciation\Service\RecipientService;
use OCA\Appreciation\Service\TitleBuilder;
use OCA\Appreciation\Service\UpcomingService;
use OCP\Calendar\ICalendar;
use OCP\Constants;
use OCP\IL10N;
use Sabre\VObject\Component\VCalendar;
use Sabre\VObject\Component\VEvent;
use Sabre\VObject\Property;

/**
 * The birthdays and anniversaries of one user as a read-only calendar.
 *
 * Events are all-day, transparent (they do not make the user busy), titled in
 * the user's language and role, and carry a reminder at the configured lead
 * time.
 *
 * About search(): the DAV app's AppCalendar, which exposes this calendar via
 * CalDAV, calls search() without options and expects Sabre VEVENT components.
 * Other consumers of the calendar manager (upcoming events, calendar based
 * user status) pass options like a time range and expect the array format of
 * the DAV backend instead. To serve CalDAV without breaking those consumers,
 * searches with options return no results.
 */
class AppreciationCalendar implements ICalendar {
	public const string URI = 'events';

	public function __construct(
		private string $userId,
		private IL10N $l10n,
		private string $language,
		private UpcomingService $upcoming,
		private TitleBuilder $titles,
		private int $leadTimeDays,
		private string $appUrl,
	) {
	}

	#[\Override]
	public function getKey(): string {
		return 'appreciation-' . self::URI;
	}

	#[\Override]
	public function getUri(): string {
		return self::URI;
	}

	#[\Override]
	public function getDisplayName(): ?string {
		return $this->l10n->t('Birthdays & anniversaries');
	}

	#[\Override]
	public function getDisplayColor(): ?string {
		return '#C98A1E';
	}

	/**
	 * @return list<VEvent>
	 */
	#[\Override]
	public function search(string $pattern, array $searchProperties = [], array $options = [], ?int $limit = null, ?int $offset = null): array {
		if ($options !== []) {
			return [];
		}

		$results = [];
		foreach ($this->upcoming->allAsRecipient($this->userId) as [$event, $role]) {
			$vEvent = $this->toVEvent($event, $role);
			if ($pattern === '' || $this->matches($vEvent, $pattern, $searchProperties)) {
				$results[] = $vEvent;
			}
		}
		return array_slice($results, $offset ?? 0, $limit);
	}

	#[\Override]
	public function getPermissions(): int {
		return Constants::PERMISSION_READ;
	}

	#[\Override]
	public function isDeleted(): bool {
		return false;
	}

	/**
	 * @param RecipientService::ROLE_* $role
	 */
	private function toVEvent(Event $event, string $role): VEvent {
		$title = $this->titles->build($event, $role, $this->language);
		$date = $event->getEventDate();
		$calendar = new VCalendar();
		/** @var VEvent $vEvent */
		$vEvent = $calendar->add('VEVENT', [
			'UID' => $event->getUid(),
			'X-FILENAME' => $event->getUid() . '.ics',
			'SUMMARY' => $title,
			'DESCRIPTION' => $this->appUrl,
			'URL' => $this->appUrl,
			'CATEGORIES' => $event->getType() === Event::TYPE_JUBILEE ? $this->l10n->t('Anniversary') : $this->l10n->t('Birthday'),
			'TRANSP' => 'TRANSPARENT',
			'CLASS' => 'PRIVATE',
			'DTSTAMP' => (new \DateTimeImmutable('@' . $event->getCreatedAt())),
		]);
		$vEvent->add('DTSTART', $date->format('Ymd'), ['VALUE' => 'DATE']);
		$vEvent->add('DTEND', Dates::addDays($date, 1)->format('Ymd'), ['VALUE' => 'DATE']);
		if ($this->leadTimeDays > 0) {
			$vEvent->add('VALARM', [
				'ACTION' => 'DISPLAY',
				'DESCRIPTION' => $title,
				'TRIGGER' => '-P' . $this->leadTimeDays . 'D',
			]);
		}
		return $vEvent;
	}

	/**
	 * UID and X-FILENAME (used by CalDAV to look up single objects) match
	 * exactly, other properties case-insensitively by substring.
	 *
	 * @param array<array-key, mixed> $searchProperties
	 */
	private function matches(VEvent $vEvent, string $pattern, array $searchProperties): bool {
		$names = array_map('strtoupper', array_filter($searchProperties, 'is_string'));
		foreach ($names === [] ? ['SUMMARY'] : $names as $name) {
			/** @var list<mixed> $nodes */
			$nodes = $vEvent->select($name);
			foreach ($nodes as $node) {
				if (!$node instanceof Property) {
					continue;
				}
				$value = (string)$node;
				$exact = in_array($name, ['UID', 'X-FILENAME'], true);
				if (($exact && $value === $pattern) || (!$exact && mb_stripos($value, $pattern) !== false)) {
					return true;
				}
			}
		}
		return false;
	}
}
