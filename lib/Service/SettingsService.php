<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Adrian Dymorz <dev@adrian.dymorz.ch>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Laudatio\Service;

use OCA\Laudatio\AppInfo\Application;
use OCA\Laudatio\Exception\ValidationException;
use OCP\Config\IUserConfig;
use OCP\IAppConfig;
use OCP\IL10N;

/**
 * Typed access to the admin settings and the per-user settings of the app.
 */
class SettingsService {
	public const MANAGER_GROUP = 'manager_group';
	public const JUBILEE_YEARS = 'jubilee_years';
	public const LEAD_TIME_DAYS = 'lead_time_days';
	public const BIRTHDAY_EVENTS = 'birthday_events';
	public const JUBILEE_EVENTS = 'jubilee_events';
	public const GREETING_REMINDER = 'greeting_reminder';
	public const EMAIL_DEFAULT = 'email_default';
	public const REGULATIONS_URL = 'regulations_url';
	public const SHEET_NOTE = 'sheet_note';
	/** Per-user setting */
	public const EMAIL_NOTIFICATIONS = 'email_notifications';

	/** @var list<int> */
	public const DEFAULT_JUBILEE_YEARS = [1, 5, 10, 15, 20, 25, 40];
	public const DEFAULT_LEAD_TIME_DAYS = 14;
	public const MAX_LEAD_TIME_DAYS = 365;
	public const MAX_JUBILEE_YEAR = 100;
	public const MAX_URL_LENGTH = 2000;
	public const MAX_SHEET_NOTE_LENGTH = 2000;

	public function __construct(
		private IAppConfig $appConfig,
		private IUserConfig $userConfig,
		private IL10N $l10n,
	) {
	}

	/**
	 * Nextcloud group whose members may edit master data ('' = admins only).
	 */
	public function getManagerGroup(): string {
		return $this->appConfig->getValueString(Application::APP_ID, self::MANAGER_GROUP);
	}

	public function setManagerGroup(string $groupId): void {
		$this->appConfig->setValueString(Application::APP_ID, self::MANAGER_GROUP, $groupId);
	}

	/**
	 * @return list<int>
	 */
	public function getJubileeYears(): array {
		$value = $this->appConfig->getValueString(Application::APP_ID, self::JUBILEE_YEARS, self::formatJubileeYears(self::DEFAULT_JUBILEE_YEARS));
		try {
			return $this->parseJubileeYears($value);
		} catch (ValidationException) {
			return self::DEFAULT_JUBILEE_YEARS;
		}
	}

	/**
	 * @throws ValidationException
	 */
	public function setJubileeYears(string $value): void {
		$years = $this->parseJubileeYears($value);
		$this->appConfig->setValueString(Application::APP_ID, self::JUBILEE_YEARS, self::formatJubileeYears($years));
	}

	public function getLeadTimeDays(): int {
		return $this->appConfig->getValueInt(Application::APP_ID, self::LEAD_TIME_DAYS, self::DEFAULT_LEAD_TIME_DAYS);
	}

	/**
	 * @throws ValidationException
	 */
	public function setLeadTimeDays(int $days): void {
		if ($days < 0 || $days > self::MAX_LEAD_TIME_DAYS) {
			throw new ValidationException($this->l10n->t('The lead time must be between 0 and %d days.', [self::MAX_LEAD_TIME_DAYS]));
		}
		$this->appConfig->setValueInt(Application::APP_ID, self::LEAD_TIME_DAYS, $days);
	}

	public function isBirthdayEventsEnabled(): bool {
		return $this->appConfig->getValueBool(Application::APP_ID, self::BIRTHDAY_EVENTS, true);
	}

	public function isJubileeEventsEnabled(): bool {
		return $this->appConfig->getValueBool(Application::APP_ID, self::JUBILEE_EVENTS, true);
	}

	public function isGreetingReminderEnabled(): bool {
		return $this->appConfig->getValueBool(Application::APP_ID, self::GREETING_REMINDER, true);
	}

	public function isEmailDefaultEnabled(): bool {
		return $this->appConfig->getValueBool(Application::APP_ID, self::EMAIL_DEFAULT, false);
	}

	/**
	 * Link to the gift regulations: an external page or the internal link of a
	 * file in Nextcloud. Null when not set.
	 */
	public function getRegulationsUrl(): ?string {
		$url = $this->appConfig->getValueString(Application::APP_ID, self::REGULATIONS_URL);
		return $url === '' ? null : $url;
	}

	/**
	 * @param string $url absolute http(s) URL, '' to remove the link
	 * @throws ValidationException
	 */
	public function setRegulationsUrl(string $url): void {
		$url = trim($url);
		if ($url !== '') {
			if (mb_strlen($url) > self::MAX_URL_LENGTH) {
				throw new ValidationException($this->l10n->t('The link must not be longer than %d characters.', [self::MAX_URL_LENGTH]));
			}
			// Only web links: rules out javascript: and other schemes in the navigation
			$parts = parse_url($url);
			$scheme = strtolower($parts['scheme'] ?? '');
			$host = $parts === false ? '' : ($parts['host'] ?? '');
			if (!in_array($scheme, ['http', 'https'], true) || $host === '' || preg_match('/[\s\x00-\x1F\x7F]/u', $url) === 1) {
				throw new ValidationException($this->l10n->t('The link must be a complete web address starting with https:// or http://.'));
			}
		}
		$this->appConfig->setValueString(Application::APP_ID, self::REGULATIONS_URL, $url);
	}

	/**
	 * Text of the organization printed on every personal data sheet, e.g. who
	 * stores the data and why. Null when not set.
	 */
	public function getSheetNote(): ?string {
		$note = $this->appConfig->getValueString(Application::APP_ID, self::SHEET_NOTE);
		return $note === '' ? null : $note;
	}

	/**
	 * @throws ValidationException
	 */
	public function setSheetNote(string $note): void {
		$note = trim($note);
		if (mb_strlen($note) > self::MAX_SHEET_NOTE_LENGTH) {
			throw new ValidationException($this->l10n->t('The note must not be longer than %d characters.', [self::MAX_SHEET_NOTE_LENGTH]));
		}
		$this->appConfig->setValueString(Application::APP_ID, self::SHEET_NOTE, $note);
	}

	/**
	 * @param self::BIRTHDAY_EVENTS|self::JUBILEE_EVENTS|self::GREETING_REMINDER|self::EMAIL_DEFAULT $key
	 */
	public function setFlag(string $key, bool $value): void {
		$this->appConfig->setValueBool(Application::APP_ID, $key, $value);
	}

	public function isEmailEnabledForUser(string $userId): bool {
		return $this->userConfig->getValueBool($userId, Application::APP_ID, self::EMAIL_NOTIFICATIONS, $this->isEmailDefaultEnabled());
	}

	public function setEmailEnabledForUser(string $userId, bool $enabled): void {
		$this->userConfig->setValueBool($userId, Application::APP_ID, self::EMAIL_NOTIFICATIONS, $enabled);
	}

	/**
	 * Parses a list like "1, 5,10" into sorted unique years. An empty string
	 * yields an empty list (no jubilees).
	 *
	 * @return list<int>
	 * @throws ValidationException
	 */
	public function parseJubileeYears(string $value): array {
		$years = [];
		$tokens = preg_split('/[\s,;]+/', trim($value), -1, PREG_SPLIT_NO_EMPTY);
		foreach ($tokens === false ? [] : $tokens as $token) {
			if (!ctype_digit($token) || (int)$token < 1 || (int)$token > self::MAX_JUBILEE_YEAR) {
				throw new ValidationException($this->l10n->t('Anniversary years must be whole numbers between 1 and %d, separated by commas.', [self::MAX_JUBILEE_YEAR]));
			}
			$years[] = (int)$token;
		}
		$years = array_values(array_unique($years));
		sort($years);
		return $years;
	}

	/**
	 * @param list<int> $years
	 */
	public static function formatJubileeYears(array $years): string {
		return implode(', ', $years);
	}
}
