<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Adrian Dymorz <dev@adrian.dymorz.ch>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Appreciation\Db;

use OCP\AppFramework\Db\QBMapper;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;

/**
 * @template-extends QBMapper<Interested>
 */
class InterestedMapper extends QBMapper {
	public function __construct(IDBConnection $db) {
		parent::__construct($db, 'appreciation_interested', Interested::class);
	}

	/**
	 * @param list<int> $groupIds
	 * @return array<int, list<string>> user IDs by group ID
	 */
	public function findUserIdsByGroups(array $groupIds): array {
		$result = [];
		foreach (array_chunk(array_values(array_unique($groupIds)), 1000) as $chunk) {
			$qb = $this->db->getQueryBuilder();
			$qb->select('group_id', 'user_id')
				->from($this->getTableName())
				->where($qb->expr()->in('group_id', $qb->createNamedParameter($chunk, IQueryBuilder::PARAM_INT_ARRAY)))
				->orderBy('user_id');
			$cursor = $qb->executeQuery();
			while (($row = $cursor->fetch()) !== false) {
				$result[(int)$row['group_id']][] = (string)$row['user_id'];
			}
			$cursor->closeCursor();
		}
		return $result;
	}

	/**
	 * @return list<int>
	 */
	public function findGroupIdsByUser(string $userId): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('group_id')
			->from($this->getTableName())
			->where($qb->expr()->eq('user_id', $qb->createNamedParameter($userId)));
		$cursor = $qb->executeQuery();
		$ids = [];
		while (($row = $cursor->fetch()) !== false) {
			$ids[] = (int)$row['group_id'];
		}
		$cursor->closeCursor();
		return $ids;
	}

	/**
	 * Replaces the interested users of a group.
	 *
	 * @param list<string> $userIds
	 */
	public function setForGroup(int $groupId, array $userIds): void {
		$this->deleteByGroup($groupId);
		foreach (array_values(array_unique($userIds)) as $userId) {
			$entry = new Interested();
			$entry->setGroupId($groupId);
			$entry->setUserId($userId);
			$this->insert($entry);
		}
	}

	public function deleteByGroup(int $groupId): void {
		$qb = $this->db->getQueryBuilder();
		$qb->delete($this->getTableName())
			->where($qb->expr()->eq('group_id', $qb->createNamedParameter($groupId, IQueryBuilder::PARAM_INT)));
		$qb->executeStatement();
	}

	public function deleteByUser(string $userId): void {
		$qb = $this->db->getQueryBuilder();
		$qb->delete($this->getTableName())
			->where($qb->expr()->eq('user_id', $qb->createNamedParameter($userId)));
		$qb->executeStatement();
	}
}
