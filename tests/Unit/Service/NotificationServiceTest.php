<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Adrian Dymorz <dev@adrian.dymorz.ch>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Laudatio\Tests\Unit\Service;

use OCA\Laudatio\Db\Event;
use OCA\Laudatio\Db\EventMapper;
use OCA\Laudatio\Db\NotifiedMapper;
use OCA\Laudatio\Service\AccessService;
use OCA\Laudatio\Service\Clock;
use OCA\Laudatio\Service\Dates;
use OCA\Laudatio\Service\EventService;
use OCA\Laudatio\Service\NotificationService;
use OCA\Laudatio\Service\RecipientService;
use OCA\Laudatio\Service\SettingsService;
use OCA\Laudatio\Service\TitleBuilder;
use OCP\IDateTimeFormatter;
use OCP\IL10N;
use OCP\IURLGenerator;
use OCP\IUser;
use OCP\IUserManager;
use OCP\L10N\IFactory;
use OCP\Mail\IEMailTemplate;
use OCP\Mail\IMailer;
use OCP\Mail\IMessage;
use OCP\Notification\IManager as INotificationManager;
use OCP\Notification\INotification;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

final class NotificationServiceTest extends TestCase {
	private EventMapper&MockObject $eventMapper;
	private NotifiedMapper&MockObject $notifiedMapper;
	private RecipientService&MockObject $recipients;
	private SettingsService&MockObject $settings;
	private AccessService&MockObject $access;
	private INotificationManager&MockObject $notificationManager;
	private IMailer&MockObject $mailer;
	private IUserManager&MockObject $userManager;
	private NotificationService $service;

	#[\Override]
	protected function setUp(): void {
		$l10n = $this->createMock(IL10N::class);
		$l10n->method('t')->willReturnCallback(static fn (string $text, array $params = []): string => vsprintf($text, $params));
		$factory = $this->createMock(IFactory::class);
		$factory->method('get')->willReturn($l10n);
		$factory->method('getUserLanguage')->willReturn('en');
		$titles = $this->createMock(TitleBuilder::class);
		$titles->method('build')->willReturn('40th birthday of Anna Muster');
		$formatter = $this->createMock(IDateTimeFormatter::class);
		$formatter->method('formatDate')->willReturn('November 3, 2026');
		$urls = $this->createMock(IURLGenerator::class);
		$urls->method('linkToRouteAbsolute')->willReturn('https://cloud.example/apps/laudatio/');
		$clock = $this->createMock(Clock::class);
		$clock->method('today')->willReturn(Dates::parse('2026-10-30'));
		$clock->method('time')->willReturn(1000);

		$this->eventMapper = $this->createMock(EventMapper::class);
		$this->notifiedMapper = $this->createMock(NotifiedMapper::class);
		$this->recipients = $this->createMock(RecipientService::class);
		$this->settings = $this->createMock(SettingsService::class);
		$this->settings->method('getLeadTimeDays')->willReturn(14);
		$this->access = $this->createMock(AccessService::class);
		$this->notificationManager = $this->createMock(INotificationManager::class);
		$this->mailer = $this->createMock(IMailer::class);
		$this->userManager = $this->createMock(IUserManager::class);

		$this->service = new NotificationService(
			$this->eventMapper,
			$this->notifiedMapper,
			$this->recipients,
			$titles,
			$this->settings,
			$this->createMock(EventService::class),
			$this->access,
			$this->notificationManager,
			$this->mailer,
			$this->userManager,
			$factory,
			$formatter,
			$urls,
			$clock,
			$this->createMock(LoggerInterface::class),
		);
	}

	public function testDisabledAndDeletedAccountsGetNoReminder(): void {
		$this->eventMapper->method('findBetween')->willReturn([$this->event()]);
		$this->notifiedMapper->method('findUserIdsByEvent')->willReturn([]);
		$this->recipients->method('recipients')->willReturn([
			'alice' => RecipientService::ROLE_RESPONSIBLE,
			'bob' => RecipientService::ROLE_INTERESTED,
			'gone' => RecipientService::ROLE_INTERESTED,
		]);
		$this->userManager->method('get')->willReturnMap([
			['alice', $this->user('alice', false)],
			['bob', $this->user('bob', true)],
			['gone', null],
		]);
		$this->settings->method('isEmailEnabledForUser')->willReturn(false);
		$notification = $this->notification();
		$notification->expects($this->once())->method('setUser')->with('bob')->willReturnSelf();
		$this->notificationManager->method('createNotification')->willReturn($notification);
		$this->notificationManager->expects($this->once())->method('notify')->with($notification);
		$this->notifiedMapper->expects($this->once())->method('add')->with(7, 'bob', 1000);
		$this->mailer->expects($this->never())->method('send');

		$this->assertSame(1, $this->service->sendDue());
	}

	public function testEmailSaysWhyTheUserGetsItAndHowToStopIt(): void {
		$event = $this->event();
		$this->eventMapper->method('findBetween')->willReturn([$event]);
		$this->notifiedMapper->method('findUserIdsByEvent')->willReturn([]);
		$this->recipients->method('recipients')->willReturn(['alice' => RecipientService::ROLE_RESPONSIBLE]);
		$this->recipients->method('groupNamesFor')->with($event, 'alice')->willReturn(['Board']);
		$this->userManager->method('get')->willReturn($this->user('alice', true, 'alice@example.com'));
		$this->settings->method('isEmailEnabledForUser')->willReturn(true);
		$this->notificationManager->method('createNotification')->willReturn($this->notification());

		$texts = [];
		$template = $this->createMock(IEMailTemplate::class);
		$template->method('addBodyText')->willReturnCallback(static function (string $text) use (&$texts): void {
			$texts[] = $text;
		});
		$template->expects($this->once())->method('setSubject')->with('40th birthday of Anna Muster');
		$this->mailer->method('createEMailTemplate')->willReturn($template);
		$message = $this->createMock(IMessage::class);
		$message->expects($this->once())->method('setTo')->with(['alice@example.com' => 'Alice'])->willReturnSelf();
		$this->mailer->method('createMessage')->willReturn($message);
		$this->mailer->expects($this->once())->method('send')->with($message);

		$this->assertSame(1, $this->service->sendDue());
		$this->assertSame([
			'Date: November 3, 2026',
			'Remember to congratulate in time.',
			'You receive this reminder because you are the responsible person of the group "Board" in Laudatio.',
			'You can turn reminder emails off in the settings of Laudatio.',
		], $texts);
	}

	public function testUsersWithoutEmailAddressGetTheNotificationOnly(): void {
		$this->eventMapper->method('findBetween')->willReturn([$this->event()]);
		$this->notifiedMapper->method('findUserIdsByEvent')->willReturn([]);
		$this->recipients->method('recipients')->willReturn(['bob' => RecipientService::ROLE_INTERESTED]);
		$this->userManager->method('get')->willReturn($this->user('bob', true));
		$this->settings->method('isEmailEnabledForUser')->willReturn(true);
		$this->notificationManager->method('createNotification')->willReturn($this->notification());
		$this->notificationManager->expects($this->once())->method('notify');
		$this->mailer->expects($this->never())->method('createMessage');

		$this->assertSame(1, $this->service->sendDue());
	}

	public function testEveryManagerIsToldAboutAMissingResponsiblePerson(): void {
		$this->access->method('getManagerUserIds')->willReturn(['admin', 'hr']);
		$users = [];
		$notification = $this->notification();
		$notification->method('setUser')->willReturnCallback(static function (string $uid) use (&$users): void {
			$users[] = $uid;
		});
		$notification->expects($this->exactly(2))->method('setObject')->with('group', '3')->willReturnSelf();
		$notification->expects($this->exactly(2))->method('setSubject')->with(NotificationService::SUBJECT_RESPONSIBLE_DELETED)->willReturnSelf();
		$this->notificationManager->method('createNotification')->willReturn($notification);
		$this->notificationManager->expects($this->exactly(2))->method('notify')->with($notification);

		$this->service->notifyResponsibleDeleted(3);
		$this->assertSame(['admin', 'hr'], $users);
	}

	public function testDismissingAddressesAllUsersAtOnce(): void {
		$notification = $this->notification();
		$notification->expects($this->once())->method('setObject')->with('group', '3')->willReturnSelf();
		$notification->expects($this->once())->method('setSubject')->with(NotificationService::SUBJECT_RESPONSIBLE_DELETED)->willReturnSelf();
		$notification->expects($this->never())->method('setUser');
		$this->notificationManager->method('createNotification')->willReturn($notification);
		$this->notificationManager->expects($this->once())->method('markProcessed')->with($notification);

		$this->service->dismissResponsibleDeleted(3);
	}

	private function notification(): INotification&MockObject {
		$notification = $this->createMock(INotification::class);
		foreach (['setApp', 'setUser', 'setDateTime', 'setObject', 'setSubject'] as $method) {
			$notification->method($method)->willReturnSelf();
		}
		return $notification;
	}

	private function user(string $uid, bool $enabled, ?string $email = null): IUser {
		$user = $this->createMock(IUser::class);
		$user->method('getUID')->willReturn($uid);
		$user->method('isEnabled')->willReturn($enabled);
		$user->method('getEMailAddress')->willReturn($email);
		$user->method('getDisplayName')->willReturn(ucfirst($uid));
		return $user;
	}

	private function event(): Event {
		$event = new Event();
		$event->setId(7);
		$event->setType(Event::TYPE_BIRTHDAY);
		$event->setPersonId(1);
		$event->setEventDate(Dates::parse('2026-11-03') ?? throw new \LogicException());
		return $event;
	}
}
