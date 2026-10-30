<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Adrian Dymorz <dev@adrian.dymorz.ch>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Laudatio\Calendar;

use OCA\Laudatio\AppInfo\Application;
use OCA\Laudatio\Service\AccessService;
use OCA\Laudatio\Service\SettingsService;
use OCA\Laudatio\Service\TitleBuilder;
use OCA\Laudatio\Service\UpcomingService;
use OCP\Calendar\ICalendarProvider;
use OCP\IURLGenerator;
use OCP\IUserManager;
use OCP\L10N\IFactory;

/**
 * Provides one read-only calendar per user that is responsible for or
 * interested in at least one group. Nextcloud exposes it via CalDAV, so it
 * shows up in the Calendar app and in CalDAV clients as
 * "app-generated--laudatio--events".
 */
class CalendarProvider implements ICalendarProvider {
	private const PRINCIPAL_PREFIX = 'principals/users/';

	public function __construct(
		private AccessService $access,
		private UpcomingService $upcoming,
		private TitleBuilder $titles,
		private SettingsService $settings,
		private IUserManager $userManager,
		private IFactory $l10nFactory,
		private IURLGenerator $urlGenerator,
	) {
	}

	#[\Override]
	public function getCalendars(string $principalUri, array $calendarUris = []): array {
		if (!str_starts_with($principalUri, self::PRINCIPAL_PREFIX)) {
			return [];
		}
		if ($calendarUris !== [] && !in_array(LaudatioCalendar::URI, $calendarUris, true)) {
			return [];
		}

		$userId = substr($principalUri, strlen(self::PRINCIPAL_PREFIX));
		$user = $this->userManager->get($userId);
		if ($user === null) {
			return [];
		}
		if ($this->access->getResponsibleGroupIds($userId) === [] && $this->access->getInterestedGroupIds($userId) === []) {
			return [];
		}

		$language = $this->l10nFactory->getUserLanguage($user);
		return [new LaudatioCalendar(
			$userId,
			$this->l10nFactory->get(Application::APP_ID, $language),
			$language,
			$this->upcoming,
			$this->titles,
			$this->settings->getLeadTimeDays(),
			$this->urlGenerator->linkToRouteAbsolute(Application::APP_ID . '.page.index'),
		)];
	}
}
