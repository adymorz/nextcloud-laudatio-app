<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Adrian Dymorz <dev@adrian.dymorz.ch>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Laudatio\Service;

use OCA\Laudatio\Db\Event;
use OCA\Laudatio\Db\Group;
use OCA\Laudatio\Db\GroupMapper;
use OCA\Laudatio\Db\InterestedMapper;
use OCA\Laudatio\Db\OrgFunction;
use OCA\Laudatio\Db\OrgFunctionMapper;
use OCA\Laudatio\Db\Slice;
use OCA\Laudatio\Db\SliceMapper;

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
			$responsible = $group->getResponsibleUid();
			if ($responsible !== '') {
				// Empty after the responsible user was deleted or the group was imported
				$recipients[$responsible] = self::ROLE_RESPONSIBLE;
			}
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
	 * Names of the groups that give the user their role for the event, e.g.
	 * for the reason line of a reminder email.
	 *
	 * @return list<string>
	 */
	public function groupNamesFor(Event $event, string $userId): array {
		$role = $this->roleOf($event, $userId);
		$names = [];
		foreach ($this->groupIdsFor($event) as $groupId) {
			$group = $this->groups()[$groupId] ?? null;
			if ($group === null) {
				continue;
			}
			$matches = $role === self::ROLE_RESPONSIBLE
				? $group->getResponsibleUid() === $userId
				: ($role === self::ROLE_INTERESTED && in_array($userId, $this->interested()[$groupId] ?? [], true));
			if ($matches) {
				$names[] = $group->getName();
			}
		}
		return $names;
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
