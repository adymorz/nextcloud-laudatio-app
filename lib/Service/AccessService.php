<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Adrian Dymorz <dev@adrian.dymorz.ch>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Laudatio\Service;

use OCA\Laudatio\Db\GroupMapper;
use OCA\Laudatio\Db\InterestedMapper;
use OCA\Laudatio\Db\SliceMapper;
use OCA\Laudatio\Exception\ForbiddenException;
use OCP\IGroupManager;
use OCP\IL10N;

/**
 * App-defined access control, checked server-side for every request.
 *
 * - Managers (admins and members of the manager group) see and edit everything.
 * - The responsible user of a group sees the current members of the group,
 *   including date of birth, contact data and notes.
 * - Interested users of a group see the current members without date of
 *   birth, contact data and notes.
 * - Everybody else has no access.
 *
 * Results are cached for the lifetime of the request.
 */
class AccessService {
	/** @var array<string, bool> */
	private array $managerCache = [];
	/** @var array<string, list<int>> */
	private array $responsibleCache = [];
	/** @var array<string, list<int>> */
	private array $interestedCache = [];
	/** @var array<string, list<int>> */
	private array $personCache = [];
	/** @var array<string, list<int>> */
	private array $sensitivePersonCache = [];

	public function __construct(
		private IGroupManager $groupManager,
		private SettingsService $settings,
		private GroupMapper $groupMapper,
		private InterestedMapper $interestedMapper,
		private SliceMapper $sliceMapper,
		private Clock $clock,
		private IL10N $l10n,
	) {
	}

	public function isManager(string $userId): bool {
		if (!isset($this->managerCache[$userId])) {
			$managerGroup = $this->settings->getManagerGroup();
			$this->managerCache[$userId] = $this->groupManager->isAdmin($userId)
				|| ($managerGroup !== '' && $this->groupManager->isInGroup($userId, $managerGroup));
		}
		return $this->managerCache[$userId];
	}

	/**
	 * Enabled users that are managers: the members of the admin group and of
	 * the manager group.
	 *
	 * @return list<string>
	 */
	public function getManagerUserIds(): array {
		$groupIds = ['admin'];
		$managerGroup = $this->settings->getManagerGroup();
		if ($managerGroup !== '' && $managerGroup !== 'admin') {
			$groupIds[] = $managerGroup;
		}
		$userIds = [];
		foreach ($groupIds as $groupId) {
			foreach ($this->groupManager->get($groupId)?->getUsers() ?? [] as $user) {
				if ($user->isEnabled()) {
					$userIds[] = $user->getUID();
				}
			}
		}
		return array_values(array_unique($userIds));
	}

	/**
	 * @return list<int>
	 */
	public function getResponsibleGroupIds(string $userId): array {
		if ($userId === '') {
			// Groups without a responsible user have an empty UID
			return [];
		}
		return $this->responsibleCache[$userId] ??= $this->groupMapper->findIdsByResponsible($userId);
	}

	/**
	 * @return list<int>
	 */
	public function getInterestedGroupIds(string $userId): array {
		return $this->interestedCache[$userId] ??= $this->interestedMapper->findGroupIdsByUser($userId);
	}

	public function hasAccess(string $userId): bool {
		return $this->isManager($userId)
			|| $this->getResponsibleGroupIds($userId) !== []
			|| $this->getInterestedGroupIds($userId) !== [];
	}

	/**
	 * @return list<int>|null group IDs, null means all groups
	 */
	public function getVisibleGroupIds(string $userId): ?array {
		if ($this->isManager($userId)) {
			return null;
		}
		return array_values(array_unique(array_merge(
			$this->getResponsibleGroupIds($userId),
			$this->getInterestedGroupIds($userId),
		)));
	}

	public function canSeeGroup(string $userId, int $groupId): bool {
		$visible = $this->getVisibleGroupIds($userId);
		return $visible === null || in_array($groupId, $visible, true);
	}

	/**
	 * @return list<int>|null person IDs, null means all persons
	 */
	public function getVisiblePersonIds(string $userId): ?array {
		$groupIds = $this->getVisibleGroupIds($userId);
		if ($groupIds === null) {
			return null;
		}
		return $this->personCache[$userId] ??= $groupIds === []
			? []
			: $this->sliceMapper->findCurrentPersonIdsByGroups($groupIds, $this->clock->today());
	}

	public function canSeePerson(string $userId, int $personId): bool {
		$visible = $this->getVisiblePersonIds($userId);
		return $visible === null || in_array($personId, $visible, true);
	}

	/**
	 * Date of birth and notes are visible to managers and to the responsible
	 * user of a group the person currently belongs to.
	 */
	public function canSeeSensitive(string $userId, int $personId): bool {
		if ($this->isManager($userId)) {
			return true;
		}
		$groupIds = $this->getResponsibleGroupIds($userId);
		if ($groupIds === []) {
			return false;
		}
		$personIds = $this->sensitivePersonCache[$userId] ??= $this->sliceMapper->findCurrentPersonIdsByGroups($groupIds, $this->clock->today());
		return in_array($personId, $personIds, true);
	}

	/**
	 * @throws ForbiddenException
	 */
	public function requireAccess(string $userId): void {
		if (!$this->hasAccess($userId)) {
			throw new ForbiddenException($this->l10n->t('You do not have access to this app.'));
		}
	}

	/**
	 * @throws ForbiddenException
	 */
	public function requireManager(string $userId): void {
		if (!$this->isManager($userId)) {
			throw new ForbiddenException($this->l10n->t('This is only available to managers.'));
		}
	}

	/**
	 * Forget cached results, e.g. after groups or slices changed.
	 */
	public function clearCache(): void {
		$this->managerCache = [];
		$this->responsibleCache = [];
		$this->interestedCache = [];
		$this->personCache = [];
		$this->sensitivePersonCache = [];
	}
}
