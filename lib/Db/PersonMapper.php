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
 * @template-extends QBMapper<Person>
 */
class PersonMapper extends QBMapper {
	public function __construct(IDBConnection $db) {
		parent::__construct($db, 'appreciation_persons', Person::class);
	}

	/**
	 * @throws DoesNotExistException
	 */
	public function find(int $id): Person {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from($this->getTableName())
			->where($qb->expr()->eq('id', $qb->createNamedParameter($id, IQueryBuilder::PARAM_INT)));
		return $this->findEntity($qb);
	}

	/**
	 * @return list<Person>
	 */
	public function findAll(): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from($this->getTableName())
			->orderBy('last_name')
			->addOrderBy('first_name')
			->addOrderBy('id');
		return $this->findEntities($qb);
	}

	/**
	 * @param list<int> $ids
	 * @return list<Person>
	 */
	public function findByIds(array $ids): array {
		$result = [];
		foreach (array_chunk(array_values(array_unique($ids)), 1000) as $chunk) {
			$qb = $this->db->getQueryBuilder();
			$qb->select('*')
				->from($this->getTableName())
				->where($qb->expr()->in('id', $qb->createNamedParameter($chunk, IQueryBuilder::PARAM_INT_ARRAY)));
			array_push($result, ...$this->findEntities($qb));
		}
		usort($result, static fn (Person $a, Person $b): int => [$a->getLastName(), $a->getFirstName(), $a->getId()] <=> [$b->getLastName(), $b->getFirstName(), $b->getId()]);
		return $result;
	}
}
