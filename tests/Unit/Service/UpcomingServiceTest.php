<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Adrian Dymorz <dev@adrian.dymorz.ch>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Laudatio\Tests\Unit\Service;

use DateTimeImmutable;
use OCA\Laudatio\Db\Event;
use OCA\Laudatio\Db\EventMapper;
use OCA\Laudatio\Exception\ForbiddenException;
use OCA\Laudatio\Service\AccessService;
use OCA\Laudatio\Service\Clock;
use OCA\Laudatio\Service\Dates;
use OCA\Laudatio\Service\RecipientService;
use OCA\Laudatio\Service\SettingsService;
use OCA\Laudatio\Service\UpcomingService;
use OCP\IL10N;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class UpcomingServiceTest extends TestCase {
	private EventMapper&MockObject $eventMapper;
	private RecipientService&MockObject $recipients;
	private AccessService&MockObject $access;
	private SettingsService&MockObject $settings;
	private UpcomingService $service;

	#[\Override]
	protected function setUp(): void {
		$this->eventMapper = $this->createMock(EventMapper::class);
		$this->recipients = $this->createMock(RecipientService::class);
		$this->access = $this->createMock(AccessService::class);
		$this->settings = $this->createMock(SettingsService::class);
		$clock = $this->createMock(Clock::class);
		$clock->method('today')->willReturn(Dates::parse('2026-11-20'));
		$clock->method('time')->willReturn(1000);
		$l10n = $this->createMock(IL10N::class);
		$l10n->method('t')->willReturnArgument(0);
		$this->service = new UpcomingService($this->eventMapper, $this->recipients, $this->access, $this->settings, $clock, $l10n);
	}

	public function testNextDaysAddsTheGiftsOfEveryYearThePeriodTouches(): void {
		$birthday = $this->event(1, Event::TYPE_BIRTHDAY, '2026-12-01');
		$gift = $this->event(2, Event::TYPE_GIFT, '2026-12-31');
		$queries = [];
		$this->eventMapper->method('findBetween')->willReturnCallback(static function (DateTimeImmutable $from, ?DateTimeImmutable $to, array $types) use (&$queries, $birthday, $gift): array {
			$queries[] = [Dates::format($from), $to === null ? null : Dates::format($to), $types];
			return $types === [Event::TYPE_GIFT] ? [$gift] : [$birthday];
		});
		$this->recipients->method('roleOf')->willReturn(RecipientService::ROLE_RESPONSIBLE);

		$result = $this->service->nextDays('alice', 60);
		$this->assertSame([
			['2026-11-20', '2027-01-19', [Event::TYPE_BIRTHDAY, Event::TYPE_JUBILEE]],
			['2026-01-01', '2027-12-31', [Event::TYPE_GIFT]],
		], $queries);
		$this->assertSame([$birthday, $gift], array_map(static fn (array $entry): Event => $entry[0], $result));
	}

	public function testCalendarHasNoGifts(): void {
		$this->eventMapper->expects($this->once())->method('findAll')->with([Event::TYPE_BIRTHDAY, Event::TYPE_JUBILEE])->willReturn([]);
		$this->assertSame([], $this->service->allAsRecipient('alice'));
	}

	public function testGiftsCanBeMarkedWithoutTheGreetingChecklist(): void {
		$this->settings->method('isGreetingReminderEnabled')->willReturn(false);
		$this->access->method('isManager')->willReturnMap([['manager', true], ['alice', false], ['bob', false]]);
		$gift = $this->event(2, Event::TYPE_GIFT, '2026-12-31');
		$birthday = $this->event(1, Event::TYPE_BIRTHDAY, '2026-12-01');

		$this->assertTrue($this->service->canMark($gift, RecipientService::ROLE_RESPONSIBLE, 'alice'));
		$this->assertTrue($this->service->canMark($gift, null, 'manager'));
		$this->assertFalse($this->service->canMark($gift, RecipientService::ROLE_INTERESTED, 'bob'));
		$this->assertFalse($this->service->canMark($birthday, RecipientService::ROLE_RESPONSIBLE, 'alice'));
	}

	public function testMarkGiftAsPresented(): void {
		$this->settings->method('isGreetingReminderEnabled')->willReturn(false);
		$gift = $this->event(2, Event::TYPE_GIFT, '2026-12-31');
		$this->eventMapper->method('find')->willReturn($gift);
		$this->recipients->method('roleOf')->willReturn(RecipientService::ROLE_RESPONSIBLE);
		$this->eventMapper->expects($this->once())->method('update')->with($gift);

		$this->service->setGreeted('alice', 2, true);
		$this->assertSame(1000, $gift->getGreetedAt());
		$this->assertSame('alice', $gift->getGreetedBy());
	}

	public function testInterestedUserCannotMarkGift(): void {
		$this->eventMapper->method('find')->willReturn($this->event(2, Event::TYPE_GIFT, '2026-12-31'));
		$this->recipients->method('roleOf')->willReturn(RecipientService::ROLE_INTERESTED);
		$this->eventMapper->expects($this->never())->method('update');

		$this->expectException(ForbiddenException::class);
		$this->expectExceptionMessage('Only the responsible person can mark a gift as presented.');
		$this->service->setGreeted('bob', 2, true);
	}

	private function event(int $id, string $type, string $date): Event {
		$event = new Event();
		$event->setId($id);
		$event->setType($type);
		$event->setPersonId(5);
		$event->setFunctionId($type === Event::TYPE_BIRTHDAY ? null : 10);
		$event->setEventDate(Dates::parse($date) ?? throw new \LogicException());
		return $event;
	}
}
