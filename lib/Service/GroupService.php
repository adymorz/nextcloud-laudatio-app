<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Adrian Dymorz <dev@adrian.dymorz.ch>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Laudatio\Service;

use OCA\Laudatio\Db\AuditEntry;
use OCA\Laudatio\Db\Group;
use OCA\Laudatio\Db\GroupMapper;
use OCA\Laudatio\Db\InterestedMapper;
use OCA\Laudatio\Db\OrgFunction;
use OCA\Laudatio\Db\OrgFunctionMapper;
use OCA\Laudatio\Db\SliceMapper;
use OCA\Laudatio\Exception\ConflictException;
use OCA\Laudatio\Exception\NotFoundException;
use OCA\Laudatio\Exception\ValidationException;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\TTransactional;
use OCP\IDBConnection;
use OCP\IL10N;
use OCP\IUserManager;

/**
 * Groups with their responsible and interested users, and their functions.
 */
class GroupService {
	use TTransactional;

	public const MAX_NAME_LENGTH = 255;

	public function __construct(
		private GroupMapper $groupMapper,
		private InterestedMapper $interestedMapper,
		private OrgFunctionMapper $functionMapper,
		private SliceMapper $sliceMapper,
		private SettingsService $settings,
		private EventService $eventService,
		private AuditService $audit,
		private RecipientService $recipients,
		private AccessService $access,
		private Clock $clock,
		private IUserManager $userManager,
		private IDBConnection $db,
		private IL10N $l10n,
	) {
	}

	/**
	 * @throws NotFoundException
	 */
	public function get(int $id): Group {
		try {
			return $this->groupMapper->find($id);
		} catch (DoesNotExistException) {
			throw new NotFoundException($this->l10n->t('The group does not exist.'));
		}
	}

	/**
	 * @param list<int>|null $ids null for all groups
	 * @return list<Group>
	 */
	public function findAll(?array $ids = null): array {
		if ($ids === null) {
			return $this->groupMapper->findAll();
		}
		return $ids === [] ? [] : $this->groupMapper->findByIds($ids);
	}

	/**
	 * @param list<int> $groupIds
	 * @return array<int, list<string>> interested user IDs by group ID
	 */
	public function getInterested(array $groupIds): array {
		return $groupIds === [] ? [] : $this->interestedMapper->findUserIdsByGroups($groupIds);
	}

	/**
	 * @param list<string> $interested
	 * @throws ValidationException
	 */
	public function create(?string $actor, string $name, string $responsibleUid, array $interested, ?string $jubileeYears): Group {
		$group = new Group();
		[$name, $interested, $jubileeYears] = $this->validate($name, $responsibleUid, $interested, $jubileeYears);
		$group->setName($name);
		$group->setResponsibleUid($responsibleUid);
		$group->setJubileeYears($jubileeYears);
		$now = $this->clock->time();
		$group->setCreatedAt($now);
		$group->setUpdatedAt($now);

		$this->atomic(function () use ($actor, $group, $interested): void {
			$this->groupMapper->insert($group);
			$this->interestedMapper->setForGroup($group->getId(), $interested);
			$this->audit->log($actor, AuditEntry::ACTION_CREATE, AuditService::ENTITY_GROUP, $group->getId(), ['name', 'responsible_uid', 'interested', 'jubilee_years']);
		}, $this->db);
		$this->afterChange(false);
		return $group;
	}

	/**
	 * @param list<string> $interested
	 * @throws NotFoundException|ValidationException
	 */
	public function update(?string $actor, int $id, string $name, string $responsibleUid, array $interested, ?string $jubileeYears): Group {
		$group = $this->get($id);
		[$name, $interested, $jubileeYears] = $this->validate($name, $responsibleUid, $interested, $jubileeYears);

		$changed = [];
		if ($group->getName() !== $name) {
			$group->setName($name);
			$changed[] = 'name';
		}
		if ($group->getResponsibleUid() !== $responsibleUid) {
			$group->setResponsibleUid($responsibleUid);
			$changed[] = 'responsible_uid';
		}
		$yearsChanged = $group->getJubileeYears() !== $jubileeYears;
		if ($yearsChanged) {
			$group->setJubileeYears($jubileeYears);
			$changed[] = 'jubilee_years';
		}
		$oldInterested = $this->getInterested([$id])[$id] ?? [];
		$interestedChanged = !$this->sameSet($oldInterested, $interested);
		if ($interestedChanged) {
			$changed[] = 'interested';
		}
		if ($changed === []) {
			return $group;
		}
		$group->setUpdatedAt($this->clock->time());

		$this->atomic(function () use ($actor, $group, $interested, $interestedChanged, $changed): void {
			$this->groupMapper->update($group);
			if ($interestedChanged) {
				$this->interestedMapper->setForGroup($group->getId(), $interested);
			}
			$this->audit->log($actor, AuditEntry::ACTION_UPDATE, AuditService::ENTITY_GROUP, $group->getId(), $changed);
		}, $this->db);
		$this->afterChange($yearsChanged);
		return $group;
	}

	/**
	 * @throws NotFoundException|ConflictException when the group still has functions
	 */
	public function delete(?string $actor, int $id): void {
		$group = $this->get($id);
		if ($this->functionMapper->countByGroup($id) > 0) {
			throw new ConflictException($this->l10n->t('Delete the functions of the group first.'));
		}
		$this->atomic(function () use ($actor, $group): void {
			$this->interestedMapper->deleteByGroup($group->getId());
			$this->groupMapper->delete($group);
			$this->audit->log($actor, AuditEntry::ACTION_DELETE, AuditService::ENTITY_GROUP, $group->getId());
		}, $this->db);
		$this->afterChange(false);
	}

	/**
	 * @param list<int>|null $groupIds null for the functions of all groups
	 * @return list<OrgFunction>
	 */
	public function getFunctions(?array $groupIds = null): array {
		if ($groupIds === null) {
			return $this->functionMapper->findAll();
		}
		return $groupIds === [] ? [] : $this->functionMapper->findByGroups($groupIds);
	}

	/**
	 * @throws NotFoundException
	 */
	public function getFunction(int $id): OrgFunction {
		try {
			return $this->functionMapper->find($id);
		} catch (DoesNotExistException) {
			throw new NotFoundException($this->l10n->t('The function does not exist.'));
		}
	}

	/**
	 * @throws NotFoundException|ValidationException
	 */
	/**
	 * @param bool $yearlyGift whether the holders get one gift per calendar year
	 * @throws NotFoundException|ValidationException
	 */
	public function createFunction(?string $actor, int $groupId, string $name, bool $yearlyGift = false): OrgFunction {
		$this->get($groupId);
		$function = new OrgFunction();
		$function->setGroupId($groupId);
		$function->setName($this->validateName($name));
		$function->setYearlyGift($yearlyGift);
		$now = $this->clock->time();
		$function->setCreatedAt($now);
		$function->setUpdatedAt($now);

		$this->atomic(function () use ($actor, $function): void {
			$this->functionMapper->insert($function);
			$this->audit->log($actor, AuditEntry::ACTION_CREATE, AuditService::ENTITY_FUNCTION, $function->getId(), ['group_id', 'name', 'yearly_gift']);
		}, $this->db);
		// A new function has no time slices yet, so no events
		$this->afterChange(false);
		return $function;
	}

	/**
	 * @param bool $yearlyGift whether the holders get one gift per calendar year
	 * @throws NotFoundException|ValidationException
	 */
	public function updateFunction(?string $actor, int $id, string $name, bool $yearlyGift): OrgFunction {
		$function = $this->getFunction($id);
		$name = $this->validateName($name);

		$changed = [];
		if ($function->getName() !== $name) {
			$function->setName($name);
			$changed[] = 'name';
		}
		$giftChanged = $function->isYearlyGift() !== $yearlyGift;
		if ($giftChanged) {
			$function->setYearlyGift($yearlyGift);
			$changed[] = 'yearly_gift';
		}
		if ($changed === []) {
			return $function;
		}
		$function->setUpdatedAt($this->clock->time());

		$this->atomic(function () use ($actor, $function, $changed): void {
			$this->functionMapper->update($function);
			$this->audit->log($actor, AuditEntry::ACTION_UPDATE, AuditService::ENTITY_FUNCTION, $function->getId(), $changed);
		}, $this->db);
		$this->afterChange($giftChanged);
		return $function;
	}

	/**
	 * @throws NotFoundException|ConflictException when the function still has time slices
	 */
	public function deleteFunction(?string $actor, int $id): void {
		$function = $this->getFunction($id);
		if ($this->sliceMapper->countByFunction($id) > 0) {
			throw new ConflictException($this->l10n->t('The function still has time slices. Delete them first.'));
		}
		$this->atomic(function () use ($actor, $function): void {
			$this->functionMapper->delete($function);
			$this->audit->log($actor, AuditEntry::ACTION_DELETE, AuditService::ENTITY_FUNCTION, $function->getId());
		}, $this->db);
		$this->afterChange(false);
	}

	/**
	 * @param list<string> $interested
	 * @return array{string, list<string>, ?string} normalized name, interested users and jubilee years
	 * @throws ValidationException
	 */
	private function validate(string $name, string $responsibleUid, array $interested, ?string $jubileeYears): array {
		$name = $this->validateName($name);

		if ($responsibleUid === '' || !$this->userManager->userExists($responsibleUid)) {
			throw new ValidationException($this->l10n->t('The responsible person must be an existing user.'));
		}

		$interested = array_values(array_unique(array_filter($interested, static fn (string $uid): bool => $uid !== $responsibleUid)));
		foreach ($interested as $userId) {
			if (!$this->userManager->userExists($userId)) {
				throw new ValidationException($this->l10n->t('The user "%s" does not exist.', [$userId]));
			}
		}

		if ($jubileeYears !== null && trim($jubileeYears) === '') {
			$jubileeYears = null;
		}
		if ($jubileeYears !== null) {
			$jubileeYears = SettingsService::formatJubileeYears($this->settings->parseJubileeYears($jubileeYears));
		}

		return [$name, $interested, $jubileeYears];
	}

	/**
	 * @throws ValidationException
	 */
	private function validateName(string $name): string {
		$name = trim($name);
		if ($name === '') {
			throw new ValidationException($this->l10n->t('The name is required.'));
		}
		if (mb_strlen($name) > self::MAX_NAME_LENGTH) {
			throw new ValidationException($this->l10n->t('Names must not be longer than %d characters.', [self::MAX_NAME_LENGTH]));
		}
		return $name;
	}

	/**
	 * @param list<string> $a
	 * @param list<string> $b
	 */
	private function sameSet(array $a, array $b): bool {
		sort($a);
		sort($b);
		return $a === $b;
	}

	/**
	 * @param bool $resync whether the events of all persons must be recalculated
	 *                     (jubilee years or yearly gift changed)
	 */
	private function afterChange(bool $resync): void {
		$this->recipients->reset();
		$this->access->clearCache();
		if ($resync) {
			$this->eventService->syncAll();
		}
	}
}
