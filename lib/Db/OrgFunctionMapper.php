<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Adrian Dymorz <dev@adrian.dymorz.ch>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Appreciation\Db;

use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\QBMapper;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;

/**
 * @template-extends QBMapper<OrgFunction>
 */
class OrgFunctionMapper extends QBMapper {
	public function __construct(IDBConnection $db) {
		parent::__construct($db, 'appreciation_functions', OrgFunction::class);
	}

	/**
	 * @throws DoesNotExistException
	 */
	public function find(int $id): OrgFunction {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from($this->getTableName())
			->where($qb->expr()->eq('id', $qb->createNamedParameter($id, IQueryBuilder::PARAM_INT)));
		return $this->findEntity($qb);
	}

	/**
	 * @return list<OrgFunction>
	 */
	public function findAll(): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from($this->getTableName())
			->orderBy('name')
			->addOrderBy('id');
		return $this->findEntities($qb);
	}

	/**
	 * @param list<int> $groupIds
	 * @return list<OrgFunction>
	 */
	public function findByGroups(array $groupIds): array {
		$result = [];
		foreach (array_chunk(array_values(array_unique($groupIds)), 1000) as $chunk) {
			$qb = $this->db->getQueryBuilder();
			$qb->select('*')
				->from($this->getTableName())
				->where($qb->expr()->in('group_id', $qb->createNamedParameter($chunk, IQueryBuilder::PARAM_INT_ARRAY)));
			array_push($result, ...$this->findEntities($qb));
		}
		usort($result, static fn (OrgFunction $a, OrgFunction $b): int => [$a->getName(), $a->getId()] <=> [$b->getName(), $b->getId()]);
		return $result;
	}

	public function countByGroup(int $groupId): int {
		$qb = $this->db->getQueryBuilder();
		$qb->select($qb->func()->count('*', 'cnt'))
			->from($this->getTableName())
			->where($qb->expr()->eq('group_id', $qb->createNamedParameter($groupId, IQueryBuilder::PARAM_INT)));
		$cursor = $qb->executeQuery();
		$count = (int)$cursor->fetchOne();
		$cursor->closeCursor();
		return $count;
	}
}
