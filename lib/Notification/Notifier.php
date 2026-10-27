<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Adrian Dymorz <dev@adrian.dymorz.ch>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Appreciation\Notification;

use OCA\Appreciation\AppInfo\Application;
use OCA\Appreciation\Db\EventMapper;
use OCA\Appreciation\Service\NotificationService;
use OCA\Appreciation\Service\RecipientService;
use OCA\Appreciation\Service\TitleBuilder;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\IDateTimeFormatter;
use OCP\IURLGenerator;
use OCP\L10N\IFactory;
use OCP\Notification\AlreadyProcessedException;
use OCP\Notification\INotification;
use OCP\Notification\INotifier;
use OCP\Notification\UnknownNotificationException;

/**
 * Renders the reminder notifications at display time, so titles follow the
 * current language and role of the user, and notifications of deleted events
 * disappear.
 */
class Notifier implements INotifier {
	public function __construct(
		private IFactory $l10nFactory,
		private EventMapper $eventMapper,
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
		return $this->l10nFactory->get(Application::APP_ID)->t('Appreciation');
	}

	#[\Override]
	public function prepare(INotification $notification, string $languageCode): INotification {
		if ($notification->getApp() !== Application::APP_ID || $notification->getSubject() !== NotificationService::SUBJECT_UPCOMING) {
			throw new UnknownNotificationException();
		}

		try {
			$event = $this->eventMapper->find((int)$notification->getObjectId());
		} catch (DoesNotExistException) {
			throw new AlreadyProcessedException();
		}
		$role = $this->recipients->roleOf($event, $notification->getUser());
		if ($role === null) {
			throw new AlreadyProcessedException();
		}

		$l = $this->l10nFactory->get(Application::APP_ID, $languageCode);
		$title = $this->titles->build($event, $role, $languageCode);
		$date = $this->dateFormatter->formatDate($event->getEventDate()->getTimestamp(), 'long', new \DateTimeZone('UTC'), $l);

		// TRANSLATORS: e.g. "40th Birthday of Anna Muster on November 3, 2026"
		$notification->setParsedSubject($l->t('%1$s on %2$s', [$title, $date]))
			->setLink($this->urlGenerator->linkToRouteAbsolute(Application::APP_ID . '.page.index'))
			->setIcon($this->urlGenerator->getAbsoluteURL($this->urlGenerator->imagePath(Application::APP_ID, 'app-dark.svg')));
		if ($role === RecipientService::ROLE_RESPONSIBLE) {
			$notification->setParsedMessage($l->t('Remember to congratulate in time.'));
		}
		return $notification;
	}
}
