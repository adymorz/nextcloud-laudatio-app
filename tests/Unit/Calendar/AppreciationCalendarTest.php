<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Adrian Dymorz <dev@adrian.dymorz.ch>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Appreciation\Tests\Unit\Calendar;

use OCA\Appreciation\Calendar\AppreciationCalendar;
use OCA\Appreciation\Db\Event;
use OCA\Appreciation\Service\Dates;
use OCA\Appreciation\Service\RecipientService;
use OCA\Appreciation\Service\TitleBuilder;
use OCA\Appreciation\Service\UpcomingService;
use OCP\Constants;
use OCP\IL10N;
use PHPUnit\Framework\TestCase;
use Sabre\VObject\Component\VCalendar;
use Sabre\VObject\Component\VEvent;

final class AppreciationCalendarTest extends TestCase {
	private AppreciationCalendar $calendar;

	#[\Override]
	protected function setUp(): void {
		$upcoming = $this->createMock(UpcomingService::class);
		$upcoming->method('allAsRecipient')->with('alice')->willReturn([
			[$this->event('appreciation-birthday-1-2026', Event::TYPE_BIRTHDAY, '2026-10-30'), RecipientService::ROLE_RESPONSIBLE],
			[$this->event('appreciation-jubilee-1-4-20161106-10', Event::TYPE_JUBILEE, '2026-11-06'), RecipientService::ROLE_INTERESTED],
		]);
		$titles = $this->createMock(TitleBuilder::class);
		$titles->method('build')->willReturnCallback(static fn (Event $e, ?string $role): string => $e->getUid() . ' as ' . (string)$role);
		$l10n = $this->createMock(IL10N::class);
		$l10n->method('t')->willReturnArgument(0);

		$this->calendar = new AppreciationCalendar('alice', $l10n, 'en', $upcoming, $titles, 14, 'https://cloud.example/apps/appreciation/');
	}

	public function testMetadata(): void {
		$this->assertSame('events', $this->calendar->getUri());
		$this->assertSame(Constants::PERMISSION_READ, $this->calendar->getPermissions());
		$this->assertFalse($this->calendar->isDeleted());
	}

	public function testAllDayTransparentEventsWithReminder(): void {
		$events = $this->calendar->search('');
		$this->assertCount(2, $events);
		$this->assertContainsOnlyInstancesOf(VEvent::class, $events);

		$vEvent = $events[0];
		$this->assertSame('appreciation-birthday-1-2026', (string)$vEvent->UID);
		$this->assertSame('appreciation-birthday-1-2026.ics', (string)$vEvent->{'X-FILENAME'});
		$this->assertSame('appreciation-birthday-1-2026 as responsible', (string)$vEvent->SUMMARY);
		$this->assertSame('20261030', (string)$vEvent->DTSTART);
		$this->assertSame('DATE', (string)$vEvent->DTSTART['VALUE']);
		$this->assertSame('20261031', (string)$vEvent->DTEND);
		$this->assertSame('TRANSPARENT', (string)$vEvent->TRANSP);
		$this->assertSame('-P14D', (string)$vEvent->VALARM?->TRIGGER);

		// The DAV app groups by $object['UID'] and wraps the components like this
		$this->assertSame('appreciation-birthday-1-2026', (string)$vEvent['UID']);
		$serialized = (new VCalendar([$vEvent]))->serialize();
		$this->assertStringContainsString('DTSTART;VALUE=DATE:20261030', $serialized);
	}

	public function testLookupByFilenameAndUidIsExact(): void {
		$this->assertCount(1, $this->calendar->search('appreciation-birthday-1-2026.ics', ['X-FILENAME']));
		$this->assertCount(1, $this->calendar->search('appreciation-jubilee-1-4-20161106-10', ['UID']));
		$this->assertCount(0, $this->calendar->search('appreciation-birthday-1', ['UID']));
	}

	public function testSearchWithOptionsReturnsNothing(): void {
		$range = ['timerange' => ['start' => new \DateTimeImmutable('2026-01-01'), 'end' => new \DateTimeImmutable('2027-01-01')]];
		$this->assertSame([], $this->calendar->search('', [], $range));
		$this->assertSame([], $this->calendar->search('', [], ['types' => ['VEVENT']]));
	}

	public function testLimitAndOffset(): void {
		$events = $this->calendar->search('', [], [], 1, 1);
		$this->assertCount(1, $events);
		$this->assertSame('appreciation-jubilee-1-4-20161106-10', (string)$events[0]->UID);
	}

	private function event(string $uid, string $type, string $date): Event {
		$event = new Event();
		$event->setUid($uid);
		$event->setType($type);
		$event->setEventDate(Dates::parse($date) ?? throw new \LogicException());
		$event->setCreatedAt(1793126400);
		return $event;
	}
}
