<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Adrian Dymorz <dev@adrian.dymorz.ch>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Appreciation\Service;

use OCA\Appreciation\Db\Event;
use OCA\Appreciation\Db\Group;
use OCA\Appreciation\Db\GroupMapper;
use OCA\Appreciation\Db\InterestedMapper;
use OCA\Appreciation\Db\OrgFunction;
use OCA\Appreciation\Db\OrgFunctionMapper;
use OCA\Appreciation\Db\Slice;
use OCA\Appreciation\Db\SliceMapper;

/**
 * Determines who receives an event and in which role.
 *
 * - Jubilee and yearly gift: the responsible and interested users of the
 *   function's group.
 * - Birthday: those of all groups the person holds an active slice in on the
 *   day of the birthday.
 *
 * Responsible wins over interested when a user has both roles.
 * Groups, functions and interested users are loaded once and cached; call
 * reset() after changing them.
 */
class RecipientService {
	public const ROLE_RESPONSIBLE = 'responsible';
	public const ROLE_INTERESTED = 'interested';

	/** @var array<int, Group>|null */
	private ?array $groups = null;
	/** @var array<int, OrgFunction>|null */
	private ?array $functions = null;
	/** @var array<int, list<string>>|null */
	private ?array $interested = null;
	/** @var array<int, list<Slice>> */
	private array $slicesByPerson = [];

	public function __construct(
		private GroupMapper $groupMapper,
		private OrgFunctionMapper $functionMapper,
		private InterestedMapper $interestedMapper,
		private SliceMapper $sliceMapper,
	) {
	}

	/**
	 * @return array<string, self::ROLE_*> role by user ID
	 */
	public function recipients(Event $event): array {
		$recipients = [];
		foreach ($this->groupIdsFor($event) as $groupId) {
			$group = $this->groups()[$groupId] ?? null;
			if ($group === null) {
				continue;
			}
			foreach ($this->interested()[$groupId] ?? [] as $userId) {
				$recipients[$userId] ??= self::ROLE_INTERESTED;
			}
			$recipients[$group->getResponsibleUid()] = self::ROLE_RESPONSIBLE;
		}
		return $recipients;
	}

	/**
	 * @return self::ROLE_*|null
	 */
	public function roleOf(Event $event, string $userId): ?string {
		return $this->recipients($event)[$userId] ?? null;
	}

	/**
	 * IDs of the groups an event belongs to.
	 *
	 * @return list<int>
	 */
	public function groupIdsFor(Event $event): array {
		$functionId = $event->getFunctionId();
		if (in_array($event->getType(), [Event::TYPE_JUBILEE, Event::TYPE_GIFT], true) && $functionId !== null) {
			$function = $this->functions()[$functionId] ?? null;
			return $function === null ? [] : [$function->getGroupId()];
		}

		$groupIds = [];
		foreach ($this->slicesOf($event->getPersonId()) as $slice) {
			if (!$slice->isActiveOn($event->getEventDate())) {
				continue;
			}
			$function = $this->functions()[$slice->getFunctionId()] ?? null;
			if ($function !== null) {
				$groupIds[] = $function->getGroupId();
			}
		}
		return array_values(array_unique($groupIds));
	}

	public function reset(): void {
		$this->groups = null;
		$this->functions = null;
		$this->interested = null;
		$this->slicesByPerson = [];
	}

	/**
	 * @return array<int, Group>
	 */
	private function groups(): array {
		if ($this->groups === null) {
			$this->groups = [];
			foreach ($this->groupMapper->findAll() as $group) {
				$this->groups[$group->getId()] = $group;
			}
		}
		return $this->groups;
	}

	/**
	 * @return array<int, OrgFunction>
	 */
	private function functions(): array {
		if ($this->functions === null) {
			$this->functions = [];
			foreach ($this->functionMapper->findAll() as $function) {
				$this->functions[$function->getId()] = $function;
			}
		}
		return $this->functions;
	}

	/**
	 * @return array<int, list<string>>
	 */
	private function interested(): array {
		return $this->interested ??= $this->interestedMapper->findUserIdsByGroups(array_keys($this->groups()));
	}

	/**
	 * @return list<Slice>
	 */
	private function slicesOf(int $personId): array {
		return $this->slicesByPerson[$personId] ??= $this->sliceMapper->findByPerson($personId);
	}
}
