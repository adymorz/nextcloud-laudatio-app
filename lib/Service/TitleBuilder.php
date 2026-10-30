<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Adrian Dymorz <dev@adrian.dymorz.ch>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Laudatio\Service;

use OCA\Laudatio\AppInfo\Application;
use OCA\Laudatio\Db\Event;
use OCA\Laudatio\Db\OrgFunctionMapper;
use OCA\Laudatio\Db\PersonMapper;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\L10N\IFactory;

/**
 * Builds the event titles in the language of the recipient, e.g.
 * "5th Anniversary for Anna Muster as Treasurer", "Yearly gift for Anna Muster
 * (Treasurer)" or, for interested users, "32nd Birthday of Anna Muster (for
 * information)".
 */
class TitleBuilder {
	/** @var array<int, string> */
	private array $personNames = [];
	/** @var array<int, string> */
	private array $functionNames = [];

	public function __construct(
		private IFactory $l10nFactory,
		private PersonMapper $personMapper,
		private OrgFunctionMapper $functionMapper,
	) {
	}

	/**
	 * @param RecipientService::ROLE_*|null $role null for managers that are no recipient
	 */
	public function build(Event $event, ?string $role, string $languageCode): string {
		$l = $this->l10nFactory->get(Application::APP_ID, $languageCode);
		$personName = $this->personName($event->getPersonId());

		$functionId = $event->getFunctionId();
		if ($event->getType() === Event::TYPE_GIFT && $functionId !== null) {
			// TRANSLATORS: e.g. "Yearly gift for Anna Muster (Treasurer)"
			$title = $l->t('Yearly gift for %1$s (%2$s)', [$personName, $this->functionName($functionId)]);
		} elseif ($event->getType() === Event::TYPE_JUBILEE && $functionId !== null) {
			$ordinal = OrdinalFormatter::format($event->getOrdinal(), $l->getLanguageCode());
			// TRANSLATORS: e.g. "5th Anniversary for Anna Muster as Treasurer"
			$title = $l->t('%1$s Anniversary for %2$s as %3$s', [$ordinal, $personName, $this->functionName($functionId)]);
		} else {
			$ordinal = OrdinalFormatter::format($event->getOrdinal(), $l->getLanguageCode());
			// TRANSLATORS: e.g. "32nd Birthday of Anna Muster"
			$title = $l->t('%1$s Birthday of %2$s', [$ordinal, $personName]);
		}

		if ($role === RecipientService::ROLE_INTERESTED) {
			// TRANSLATORS: suffix for users that only follow a group, e.g. "32nd Birthday of Anna Muster (for information)"
			$title = $l->t('%s (for information)', [$title]);
		}
		return $title;
	}

	public function personName(int $personId): string {
		if (!isset($this->personNames[$personId])) {
			try {
				$this->personNames[$personId] = $this->personMapper->find($personId)->getDisplayName();
			} catch (DoesNotExistException) {
				$this->personNames[$personId] = '?';
			}
		}
		return $this->personNames[$personId];
	}

	public function functionName(int $functionId): string {
		if (!isset($this->functionNames[$functionId])) {
			try {
				$this->functionNames[$functionId] = $this->functionMapper->find($functionId)->getName();
			} catch (DoesNotExistException) {
				$this->functionNames[$functionId] = '?';
			}
		}
		return $this->functionNames[$functionId];
	}
}
