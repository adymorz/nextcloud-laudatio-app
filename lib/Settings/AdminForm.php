<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Adrian Dymorz <dev@adrian.dymorz.ch>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Laudatio\Settings;

use OCA\Laudatio\Exception\ValidationException;
use OCA\Laudatio\Service\EventService;
use OCA\Laudatio\Service\SettingsService;
use OCP\IGroup;
use OCP\IGroupManager;
use OCP\IL10N;
use OCP\IUser;
use OCP\Settings\DeclarativeSettingsTypes;
use OCP\Settings\IDeclarativeSettingsFormWithHandlers;

/**
 * Admin settings in the "Groupware" section. Values are stored through the
 * SettingsService so they are validated and typed.
 */
class AdminForm implements IDeclarativeSettingsFormWithHandlers {
	public function __construct(
		private SettingsService $settings,
		private EventService $eventService,
		private IGroupManager $groupManager,
		private IL10N $l10n,
	) {
	}

	#[\Override]
	public function getSchema(): array {
		$groupOptions = [['name' => $this->l10n->t('Administrators only'), 'value' => '']];
		$groups = $this->groupManager->search('');
		usort($groups, static fn (IGroup $a, IGroup $b): int => strcasecmp($a->getDisplayName(), $b->getDisplayName()));
		foreach ($groups as $group) {
			if ($group->getGID() !== 'admin') {
				$groupOptions[] = ['name' => $group->getDisplayName(), 'value' => $group->getGID()];
			}
		}

		return [
			'id' => 'laudatio-admin',
			'priority' => 50,
			'section_type' => DeclarativeSettingsTypes::SECTION_TYPE_ADMIN,
			'section_id' => 'groupware',
			'storage_type' => DeclarativeSettingsTypes::STORAGE_TYPE_EXTERNAL,
			'title' => $this->l10n->t('Laudatio'),
			'description' => $this->l10n->t('Birthdays, anniversaries and yearly gifts of the persons engaged in your organization.'),
			'fields' => [
				[
					'id' => SettingsService::MANAGER_GROUP,
					'title' => $this->l10n->t('Managers'),
					'description' => $this->l10n->t('Members of this group can see and edit all persons, groups and functions, in addition to administrators.'),
					'type' => DeclarativeSettingsTypes::SELECT,
					'options' => $groupOptions,
					'default' => '',
				],
				[
					'id' => SettingsService::JUBILEE_YEARS,
					'title' => $this->l10n->t('Anniversary years'),
					'description' => $this->l10n->t('Years of service that are celebrated, separated by commas. Groups can override this list.'),
					'type' => DeclarativeSettingsTypes::TEXT,
					'placeholder' => SettingsService::formatJubileeYears(SettingsService::DEFAULT_JUBILEE_YEARS),
					'default' => SettingsService::formatJubileeYears(SettingsService::DEFAULT_JUBILEE_YEARS),
				],
				[
					'id' => SettingsService::LEAD_TIME_DAYS,
					'title' => $this->l10n->t('Reminder lead time (days)'),
					'description' => $this->l10n->t('How many days before a birthday, an anniversary or the due date of a yearly gift the reminder is sent.'),
					'type' => DeclarativeSettingsTypes::NUMBER,
					'default' => SettingsService::DEFAULT_LEAD_TIME_DAYS,
				],
				[
					'id' => SettingsService::BIRTHDAY_EVENTS,
					'title' => $this->l10n->t('Events'),
					'label' => $this->l10n->t('Generate birthday events'),
					'description' => $this->l10n->t('Turning this off deletes the upcoming birthday events, including their congratulated marks.'),
					'type' => DeclarativeSettingsTypes::CHECKBOX,
					'default' => true,
				],
				[
					'id' => SettingsService::JUBILEE_EVENTS,
					'title' => '',
					'label' => $this->l10n->t('Generate anniversary events'),
					'description' => $this->l10n->t('Turning this off deletes the upcoming anniversary events, including their congratulated marks.'),
					'type' => DeclarativeSettingsTypes::CHECKBOX,
					'default' => true,
				],
				[
					'id' => SettingsService::GREETING_REMINDER,
					'title' => '',
					'label' => $this->l10n->t('Let responsible persons mark events as congratulated'),
					'type' => DeclarativeSettingsTypes::CHECKBOX,
					'default' => true,
				],
				[
					'id' => SettingsService::EMAIL_DEFAULT,
					'title' => $this->l10n->t('Email'),
					'label' => $this->l10n->t('Send reminder emails by default (users can change this in the settings of the app)'),
					'type' => DeclarativeSettingsTypes::CHECKBOX,
					'default' => false,
				],
				[
					'id' => SettingsService::REGULATIONS_URL,
					'title' => $this->l10n->t('Gift regulations'),
					'description' => $this->l10n->t('Link shown to all users of the app, starting with https://. For a document in Nextcloud, use "Copy internal link" in the sharing tab of the file and share the file with the users of the app.'),
					'type' => DeclarativeSettingsTypes::URL,
					'placeholder' => 'https://',
					'default' => '',
				],
				[
					'id' => SettingsService::SHEET_NOTE,
					'title' => $this->l10n->t('Note on the data sheet'),
					'description' => $this->l10n->t('Printed under the heading of every personal data sheet, e.g. who stores the data and why, who can see it and whom to contact.'),
					'type' => DeclarativeSettingsTypes::TEXT,
					'default' => '',
				],
			],
		];
	}

	#[\Override]
	public function getValue(string $fieldId, IUser $user): mixed {
		return match ($fieldId) {
			SettingsService::MANAGER_GROUP => $this->settings->getManagerGroup(),
			SettingsService::JUBILEE_YEARS => SettingsService::formatJubileeYears($this->settings->getJubileeYears()),
			SettingsService::LEAD_TIME_DAYS => $this->settings->getLeadTimeDays(),
			SettingsService::BIRTHDAY_EVENTS => $this->settings->isBirthdayEventsEnabled(),
			SettingsService::JUBILEE_EVENTS => $this->settings->isJubileeEventsEnabled(),
			SettingsService::GREETING_REMINDER => $this->settings->isGreetingReminderEnabled(),
			SettingsService::EMAIL_DEFAULT => $this->settings->isEmailDefaultEnabled(),
			SettingsService::REGULATIONS_URL => $this->settings->getRegulationsUrl() ?? '',
			SettingsService::SHEET_NOTE => $this->settings->getSheetNote() ?? '',
			default => null,
		};
	}

	/**
	 * @throws ValidationException
	 */
	#[\Override]
	public function setValue(string $fieldId, mixed $value, IUser $user): void {
		switch ($fieldId) {
			case SettingsService::MANAGER_GROUP:
				$groupId = is_string($value) ? $value : '';
				if ($groupId !== '' && !$this->groupManager->groupExists($groupId)) {
					throw new ValidationException($this->l10n->t('The group does not exist.'));
				}
				$this->settings->setManagerGroup($groupId);
				return;
			case SettingsService::JUBILEE_YEARS:
				$this->settings->setJubileeYears(is_scalar($value) ? (string)$value : '');
				$this->eventService->syncAll();
				return;
			case SettingsService::LEAD_TIME_DAYS:
				if (!is_numeric($value)) {
					throw new ValidationException($this->l10n->t('The lead time must be a number.'));
				}
				$this->settings->setLeadTimeDays((int)$value);
				return;
			case SettingsService::BIRTHDAY_EVENTS:
			case SettingsService::JUBILEE_EVENTS:
				$this->settings->setFlag($fieldId, $this->toBool($value));
				$this->eventService->syncAll();
				return;
			case SettingsService::GREETING_REMINDER:
			case SettingsService::EMAIL_DEFAULT:
				$this->settings->setFlag($fieldId, $this->toBool($value));
				return;
			case SettingsService::REGULATIONS_URL:
				$this->settings->setRegulationsUrl(is_string($value) ? $value : '');
				return;
			case SettingsService::SHEET_NOTE:
				$this->settings->setSheetNote(is_string($value) ? $value : '');
				return;
		}
	}

	private function toBool(mixed $value): bool {
		return $value === true || $value === 1 || $value === '1' || $value === 'true';
	}
}
