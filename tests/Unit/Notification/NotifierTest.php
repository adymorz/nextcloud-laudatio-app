<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Adrian Dymorz <dev@adrian.dymorz.ch>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Appreciation\Tests\Unit\Notification;

use OCA\Appreciation\Db\Event;
use OCA\Appreciation\Db\EventMapper;
use OCA\Appreciation\Notification\Notifier;
use OCA\Appreciation\Service\Dates;
use OCA\Appreciation\Service\NotificationService;
use OCA\Appreciation\Service\RecipientService;
use OCA\Appreciation\Service\TitleBuilder;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\IDateTimeFormatter;
use OCP\IL10N;
use OCP\IURLGenerator;
use OCP\L10N\IFactory;
use OCP\Notification\AlreadyProcessedException;
use OCP\Notification\INotification;
use OCP\Notification\UnknownNotificationException;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class NotifierTest extends TestCase {
	private EventMapper&MockObject $eventMapper;
	private RecipientService&MockObject $recipients;
	private Notifier $notifier;

	#[\Override]
	protected function setUp(): void {
		$l10n = $this->createMock(IL10N::class);
		$l10n->method('t')->willReturnCallback(static fn (string $text, array $params = []): string => vsprintf($text, $params));
		$factory = $this->createMock(IFactory::class);
		$factory->method('get')->willReturn($l10n);
		$this->eventMapper = $this->createMock(EventMapper::class);
		$this->recipients = $this->createMock(RecipientService::class);
		$titles = $this->createMock(TitleBuilder::class);
		$titles->method('build')->willReturnCallback(static fn (Event $e, ?string $role): string => '40th Birthday of Anna Muster' . ($role === 'interested' ? ' (for information)' : ''));
		$formatter = $this->createMock(IDateTimeFormatter::class);
		$formatter->method('formatDate')->willReturn('October 30, 2026');
		$urls = $this->createMock(IURLGenerator::class);
		$urls->method('linkToRouteAbsolute')->willReturn('https://cloud.example/apps/appreciation/');
		$urls->method('getAbsoluteURL')->willReturnArgument(0);

		$this->notifier = new Notifier($factory, $this->eventMapper, $this->recipients, $titles, $formatter, $urls);
	}

	public function testRendersTitleAndDateForTheRole(): void {
		$this->eventMapper->method('find')->with(7)->willReturn($this->event());
		$this->recipients->method('roleOf')->willReturn(RecipientService::ROLE_INTERESTED);

		$notification = $this->notification('appreciation', NotificationService::SUBJECT_UPCOMING);
		$notification->expects($this->once())->method('setParsedSubject')->with('40th Birthday of Anna Muster (for information) on October 30, 2026')->willReturnSelf();
		$notification->expects($this->never())->method('setParsedMessage');
		$this->notifier->prepare($notification, 'en');
	}

	public function testResponsibleGetsAHint(): void {
		$this->eventMapper->method('find')->willReturn($this->event());
		$this->recipients->method('roleOf')->willReturn(RecipientService::ROLE_RESPONSIBLE);

		$notification = $this->notification('appreciation', NotificationService::SUBJECT_UPCOMING);
		$notification->expects($this->once())->method('setParsedMessage')->with('Remember to congratulate in time.')->willReturnSelf();
		$this->notifier->prepare($notification, 'en');
	}

	public function testOtherAppsAreRejected(): void {
		$this->expectException(UnknownNotificationException::class);
		$this->notifier->prepare($this->notification('files', 'whatever'), 'en');
	}

	public function testDeletedEventRemovesTheNotification(): void {
		$this->eventMapper->method('find')->willThrowException(new DoesNotExistException('gone'));
		$this->expectException(AlreadyProcessedException::class);
		$this->notifier->prepare($this->notification('appreciation', NotificationService::SUBJECT_UPCOMING), 'en');
	}

	public function testUserNoLongerRecipientRemovesTheNotification(): void {
		$this->eventMapper->method('find')->willReturn($this->event());
		$this->recipients->method('roleOf')->willReturn(null);
		$this->expectException(AlreadyProcessedException::class);
		$this->notifier->prepare($this->notification('appreciation', NotificationService::SUBJECT_UPCOMING), 'en');
	}

	private function notification(string $app, string $subject): INotification&MockObject {
		$notification = $this->createMock(INotification::class);
		$notification->method('getApp')->willReturn($app);
		$notification->method('getSubject')->willReturn($subject);
		$notification->method('getObjectId')->willReturn('7');
		$notification->method('getUser')->willReturn('bob');
		$notification->method('setParsedSubject')->willReturnSelf();
		$notification->method('setParsedMessage')->willReturnSelf();
		$notification->method('setLink')->willReturnSelf();
		$notification->method('setIcon')->willReturnSelf();
		return $notification;
	}

	private function event(): Event {
		$event = new Event();
		$event->setId(7);
		$event->setType(Event::TYPE_BIRTHDAY);
		$event->setEventDate(Dates::parse('2026-10-30') ?? throw new \LogicException());
		return $event;
	}
}
