<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Adrian Dymorz <dev@adrian.dymorz.ch>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Appreciation\Db;

use DateTimeImmutable;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\QBMapper;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;

/**
 * @template-extends QBMapper<Slice>
 */
class SliceMapper extends QBMapper {
	public function __construct(IDBConnection $db) {
		parent::__construct($db, 'appreciation_slices', Slice::class);
	}

	/**
	 * @throws DoesNotExistException
	 */
	public function find(int $id): Slice {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from($this->getTableName())
			->where($qb->expr()->eq('id', $qb->createNamedParameter($id, IQueryBuilder::PARAM_INT)));
		return $this->findEntity($qb);
	}

	/**
	 * @return list<Slice> ordered by start date
	 */
	public function findByPerson(int $personId): array {
		return $this->findByPersons([$personId]);
	}

	/**
	 * @param list<int> $personIds
	 * @return list<Slice> ordered by start date
	 */
	public function findByPersons(array $personIds): array {
		$result = [];
		foreach (array_chunk(array_values(array_unique($personIds)), 1000) as $chunk) {
			$qb = $this->db->getQueryBuilder();
			$qb->select('*')
				->from($this->getTableName())
				->where($qb->expr()->in('person_id', $qb->createNamedParameter($chunk, IQueryBuilder::PARAM_INT_ARRAY)));
			array_push($result, ...$this->findEntities($qb));
		}
		usort($result, static fn (Slice $a, Slice $b): int => [$a->getStartDate(), $a->getId()] <=> [$b->getStartDate(), $b->getId()]);
		return $result;
	}

	/**
	 * @return list<Slice>
	 */
	public function findByPersonAndFunction(int $personId, int $functionId): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from($this->getTableName())
			->where($qb->expr()->eq('person_id', $qb->createNamedParameter($personId, IQueryBuilder::PARAM_INT)))
			->andWhere($qb->expr()->eq('function_id', $qb->createNamedParameter($functionId, IQueryBuilder::PARAM_INT)))
			->orderBy('start_date')
			->addOrderBy('id');
		return $this->findEntities($qb);
	}

	public function countByFunction(int $functionId): int {
		$qb = $this->db->getQueryBuilder();
		$qb->select($qb->func()->count('*', 'cnt'))
			->from($this->getTableName())
			->where($qb->expr()->eq('function_id', $qb->createNamedParameter($functionId, IQueryBuilder::PARAM_INT)));
		$cursor = $qb->executeQuery();
		$count = (int)$cursor->fetchOne();
		$cursor->closeCursor();
		return $count;
	}

	/**
	 * IDs of persons with a slice in one of the groups that has not ended
	 * before the given day ("current members", including future slices).
	 *
	 * @param list<int> $groupIds
	 * @return list<int>
	 */
	public function findCurrentPersonIdsByGroups(array $groupIds, DateTimeImmutable $today): array {
		$ids = [];
		foreach (array_chunk(array_values(array_unique($groupIds)), 1000) as $chunk) {
			$qb = $this->db->getQueryBuilder();
			$qb->selectDistinct('s.person_id')
				->from($this->getTableName(), 's')
				->innerJoin('s', 'appreciation_functions', 'f', $qb->expr()->eq('f.id', 's.function_id'))
				->where($qb->expr()->in('f.group_id', $qb->createNamedParameter($chunk, IQueryBuilder::PARAM_INT_ARRAY)))
				->andWhere($qb->expr()->orX(
					$qb->expr()->isNull('s.end_date'),
					$qb->expr()->gte('s.end_date', $qb->createNamedParameter($today, IQueryBuilder::PARAM_DATE_IMMUTABLE)),
				));
			$cursor = $qb->executeQuery();
			while (($row = $cursor->fetch()) !== false) {
				$ids[] = (int)$row['person_id'];
			}
			$cursor->closeCursor();
		}
		return array_values(array_unique($ids));
	}

	/**
	 * @return list<int> IDs of all persons that have at least one slice
	 */
	public function findAllPersonIds(): array {
		$qb = $this->db->getQueryBuilder();
		$qb->selectDistinct('person_id')->from($this->getTableName());
		$cursor = $qb->executeQuery();
		$ids = [];
		while (($row = $cursor->fetch()) !== false) {
			$ids[] = (int)$row['person_id'];
		}
		$cursor->closeCursor();
		return $ids;
	}

	public function deleteByPerson(int $personId): void {
		$qb = $this->db->getQueryBuilder();
		$qb->delete($this->getTableName())
			->where($qb->expr()->eq('person_id', $qb->createNamedParameter($personId, IQueryBuilder::PARAM_INT)));
		$qb->executeStatement();
	}
}
