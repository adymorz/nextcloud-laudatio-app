<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Adrian Dymorz <dev@adrian.dymorz.ch>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Laudatio\Service;

use OCA\Laudatio\AppInfo\Application;
use OCA\Laudatio\Db\Event;
use OCA\Laudatio\Db\EventMapper;
use OCA\Laudatio\Db\NotifiedMapper;
use OCP\IDateTimeFormatter;
use OCP\IURLGenerator;
use OCP\IUser;
use OCP\IUserManager;
use OCP\L10N\IFactory;
use OCP\Mail\IMailer;
use OCP\Notification\IManager as INotificationManager;
use Psr\Log\LoggerInterface;

/**
 * Sends one reminder per event and recipient when the event (for yearly gifts:
 * the due date) enters the lead time: a Nextcloud notification and, if the
 * user opted in, an email. Gifts that are already presented get no reminder,
 * disabled accounts get none at all. Notifications of past events are removed.
 * Also tells the managers when a group lost its responsible person.
 */
class NotificationService {
	public const SUBJECT_UPCOMING = 'upcoming';
	public const SUBJECT_RESPONSIBLE_DELETED = 'responsible_deleted';

	public function __construct(
		private EventMapper $eventMapper,
		private NotifiedMapper $notifiedMapper,
		private RecipientService $recipients,
		private TitleBuilder $titles,
		private SettingsService $settings,
		private EventService $eventService,
		private AccessService $access,
		private INotificationManager $notificationManager,
		private IMailer $mailer,
		private IUserManager $userManager,
		private IFactory $l10nFactory,
		private IDateTimeFormatter $dateFormatter,
		private IURLGenerator $urlGenerator,
		private Clock $clock,
		private LoggerInterface $logger,
	) {
	}

	/**
	 * @return int number of sent notifications
	 */
	public function sendDue(): int {
		$today = $this->clock->today();
		$until = Dates::addDays($today, $this->settings->getLeadTimeDays());
		$sent = 0;
		foreach ($this->eventMapper->findBetween($today, $until) as $event) {
			if ($event->getType() === Event::TYPE_GIFT && $event->getGreetedAt() !== null) {
				// Presented before the reminder was due
				continue;
			}
			$alreadyNotified = $this->notifiedMapper->findUserIdsByEvent($event->getId());
			foreach ($this->recipients->recipients($event) as $userId => $role) {
				if (in_array($userId, $alreadyNotified, true)) {
					continue;
				}
				$user = $this->userManager->get($userId);
				if ($user === null || !$user->isEnabled()) {
					// Deleted and disabled accounts get no reminders
					continue;
				}
				$this->notify($event, $user, $role);
				$sent++;
			}
		}
		return $sent;
	}

	/**
	 * Removes the notifications of events that are over.
	 */
	public function cleanup(): void {
		$eventIds = $this->notifiedMapper->findEventIdsBefore($this->clock->today());
		if ($eventIds !== []) {
			$this->eventService->dismissNotifications($eventIds);
			$this->notifiedMapper->deleteByEventIds($eventIds);
		}
	}

	/**
	 * Tells every manager that the group has no responsible person any more.
	 */
	public function notifyResponsibleDeleted(int $groupId): void {
		foreach ($this->access->getManagerUserIds() as $userId) {
			$notification = $this->notificationManager->createNotification();
			$notification->setApp(Application::APP_ID)
				->setUser($userId)
				->setDateTime(new \DateTime('@' . $this->clock->time()))
				->setObject('group', (string)$groupId)
				->setSubject(self::SUBJECT_RESPONSIBLE_DELETED);
			$this->notificationManager->notify($notification);
		}
	}

	/**
	 * Removes the hints about the missing responsible person of the group for
	 * all users, once a new one is assigned or the group is deleted.
	 */
	public function dismissResponsibleDeleted(int $groupId): void {
		$notification = $this->notificationManager->createNotification();
		$notification->setApp(Application::APP_ID)
			->setObject('group', (string)$groupId)
			->setSubject(self::SUBJECT_RESPONSIBLE_DELETED);
		$this->notificationManager->markProcessed($notification);
	}

	/**
	 * @param RecipientService::ROLE_* $role
	 */
	private function notify(Event $event, IUser $user, string $role): void {
		$userId = $user->getUID();
		$notification = $this->notificationManager->createNotification();
		$notification->setApp(Application::APP_ID)
			->setUser($userId)
			->setDateTime(new \DateTime('@' . $this->clock->time()))
			->setObject('event', (string)$event->getId())
			->setSubject(self::SUBJECT_UPCOMING);
		$this->notificationManager->notify($notification);
		$this->notifiedMapper->add($event->getId(), $userId, $this->clock->time());

		if ($this->settings->isEmailEnabledForUser($userId)) {
			$this->sendEmail($event, $user, $role);
		}
	}

	/**
	 * @param RecipientService::ROLE_* $role
	 */
	private function sendEmail(Event $event, IUser $user, string $role): void {
		$address = $user->getEMailAddress();
		if ($address === null || $address === '') {
			return;
		}

		$language = $this->l10nFactory->getUserLanguage($user);
		$l = $this->l10nFactory->get(Application::APP_ID, $language);
		$title = $this->titles->build($event, $role, $language);
		$date = $this->dateFormatter->formatDate($event->getEventDate()->getTimestamp(), 'long', new \DateTimeZone('UTC'), $l);

		$template = $this->mailer->createEMailTemplate('laudatio.reminder', ['title' => $title, 'date' => $date]);
		$template->setSubject($title);
		$template->addHeader();
		$template->addHeading($title);
		if ($event->getType() === Event::TYPE_GIFT) {
			$template->addBodyText($l->t('Due date: %s', [$date]));
			if ($role === RecipientService::ROLE_RESPONSIBLE) {
				$template->addBodyText($l->t('Remember to present the gift in time.'));
			}
		} else {
			$template->addBodyText($l->t('Date: %s', [$date]));
			if ($role === RecipientService::ROLE_RESPONSIBLE) {
				$template->addBodyText($l->t('Remember to congratulate in time.'));
			}
		}
		// Say why the user gets this email and how to stop it
		$groups = implode('", "', $this->recipients->groupNamesFor($event, $user->getUID()));
		$template->addBodyText($role === RecipientService::ROLE_RESPONSIBLE
			? $l->t('You receive this reminder because you are the responsible person of the group "%s" in Laudatio.', [$groups])
			: $l->t('You receive this reminder because you are interested in the group "%s" in Laudatio.', [$groups]));
		$template->addBodyText($l->t('You can turn reminder emails off in the settings of Laudatio.'));
		$template->addBodyButton($l->t('Open Laudatio'), $this->urlGenerator->linkToRouteAbsolute(Application::APP_ID . '.page.index'));
		$template->addFooter();

		try {
			$message = $this->mailer->createMessage();
			$message->setTo([$address => $user->getDisplayName()]);
			$message->useTemplate($template);
			$this->mailer->send($message);
		} catch (\Exception $e) {
			$this->logger->error('Could not send the reminder email to ' . $user->getUID(), ['exception' => $e]);
		}
	}
}
