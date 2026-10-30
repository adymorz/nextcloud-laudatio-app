<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Adrian Dymorz <dev@adrian.dymorz.ch>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Laudatio\Notification;

use OCA\Laudatio\AppInfo\Application;
use OCA\Laudatio\Db\Event;
use OCA\Laudatio\Db\EventMapper;
use OCA\Laudatio\Db\GroupMapper;
use OCA\Laudatio\Service\NotificationService;
use OCA\Laudatio\Service\RecipientService;
use OCA\Laudatio\Service\TitleBuilder;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\IDateTimeFormatter;
use OCP\IL10N;
use OCP\IURLGenerator;
use OCP\L10N\IFactory;
use OCP\Notification\AlreadyProcessedException;
use OCP\Notification\INotification;
use OCP\Notification\INotifier;
use OCP\Notification\UnknownNotificationException;

/**
 * Renders the notifications at display time, so titles follow the current
 * language and role of the user, and notifications that are no longer
 * relevant disappear: reminders of deleted events, and the hint about a
 * missing responsible person once the group has one again.
 */
class Notifier implements INotifier {
	public function __construct(
		private IFactory $l10nFactory,
		private EventMapper $eventMapper,
		private GroupMapper $groupMapper,
		private RecipientService $recipients,
		private TitleBuilder $titles,
		private IDateTimeFormatter $dateFormatter,
		private IURLGenerator $urlGenerator,
	) {
	}

	#[\Override]
	public function getID(): string {
		return Application::APP_ID;
	}

	#[\Override]
	public function getName(): string {
		return $this->l10nFactory->get(Application::APP_ID)->t('Laudatio');
	}

	#[\Override]
	public function prepare(INotification $notification, string $languageCode): INotification {
		if ($notification->getApp() !== Application::APP_ID) {
			throw new UnknownNotificationException();
		}
		$l = $this->l10nFactory->get(Application::APP_ID, $languageCode);
		$notification = match ($notification->getSubject()) {
			NotificationService::SUBJECT_UPCOMING => $this->prepareUpcoming($notification, $l, $languageCode),
			NotificationService::SUBJECT_RESPONSIBLE_DELETED => $this->prepareResponsibleDeleted($notification, $l),
			default => throw new UnknownNotificationException(),
		};
		return $notification->setIcon($this->urlGenerator->getAbsoluteURL($this->urlGenerator->imagePath(Application::APP_ID, 'app-dark.svg')));
	}

	/**
	 * @throws AlreadyProcessedException when the event is gone, the user no longer receives it or the gift was presented
	 */
	private function prepareUpcoming(INotification $notification, IL10N $l, string $languageCode): INotification {
		try {
			$event = $this->eventMapper->find((int)$notification->getObjectId());
		} catch (DoesNotExistException) {
			throw new AlreadyProcessedException();
		}
		$role = $this->recipients->roleOf($event, $notification->getUser());
		$isGift = $event->getType() === Event::TYPE_GIFT;
		// A presented gift needs no reminder any more
		if ($role === null || ($isGift && $event->getGreetedAt() !== null)) {
			throw new AlreadyProcessedException();
		}

		$title = $this->titles->build($event, $role, $languageCode);
		$date = $this->dateFormatter->formatDate($event->getEventDate()->getTimestamp(), 'long', new \DateTimeZone('UTC'), $l);

		$subject = $isGift
			// TRANSLATORS: e.g. "Yearly gift for Anna Muster (Treasurer) due by December 31, 2026"
			? $l->t('%1$s due by %2$s', [$title, $date])
			// TRANSLATORS: e.g. "40th birthday of Anna Muster on November 3, 2026"
			: $l->t('%1$s on %2$s', [$title, $date]);
		$notification->setParsedSubject($subject)
			->setLink($this->urlGenerator->linkToRouteAbsolute(Application::APP_ID . '.page.index'));
		if ($role === RecipientService::ROLE_RESPONSIBLE) {
			$notification->setParsedMessage($isGift
				? $l->t('Remember to present the gift in time.')
				: $l->t('Remember to congratulate in time.'));
		}
		return $notification;
	}

	/**
	 * @throws AlreadyProcessedException when the group is gone or has a responsible person again
	 */
	private function prepareResponsibleDeleted(INotification $notification, IL10N $l): INotification {
		try {
			$group = $this->groupMapper->find((int)$notification->getObjectId());
		} catch (DoesNotExistException) {
			throw new AlreadyProcessedException();
		}
		if ($group->getResponsibleUid() !== '') {
			throw new AlreadyProcessedException();
		}
		return $notification
			->setParsedSubject($l->t('The responsible person of the group "%s" was deleted. Please assign a new one.', [$group->getName()]))
			->setLink($this->urlGenerator->linkToRouteAbsolute(Application::APP_ID . '.page.catchAll', ['path' => 'groups']));
	}
}
