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
 * @template-extends QBMapper<Event>
 */
class EventMapper extends QBMapper {
	public function __construct(IDBConnection $db) {
		parent::__construct($db, 'appreciation_events', Event::class);
	}

	/**
	 * @throws DoesNotExistException
	 */
	public function find(int $id): Event {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from($this->getTableName())
			->where($qb->expr()->eq('id', $qb->createNamedParameter($id, IQueryBuilder::PARAM_INT)));
		return $this->findEntity($qb);
	}

	/**
	 * Events between two days (both inclusive), ordered by date.
	 *
	 * @return list<Event>
	 */
	public function findBetween(DateTimeImmutable $from, ?DateTimeImmutable $to = null): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from($this->getTableName())
			->where($qb->expr()->gte('event_date', $qb->createNamedParameter($from, IQueryBuilder::PARAM_DATE_IMMUTABLE)));
		if ($to !== null) {
			$qb->andWhere($qb->expr()->lte('event_date', $qb->createNamedParameter($to, IQueryBuilder::PARAM_DATE_IMMUTABLE)));
		}
		$qb->orderBy('event_date')->addOrderBy('id');
		return $this->findEntities($qb);
	}

	/**
	 * @return list<Event>
	 */
	public function findAll(): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from($this->getTableName())
			->orderBy('event_date')
			->addOrderBy('id');
		return $this->findEntities($qb);
	}

	/**
	 * @return list<Event>
	 */
	public function findByPerson(int $personId, ?DateTimeImmutable $from = null): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from($this->getTableName())
			->where($qb->expr()->eq('person_id', $qb->createNamedParameter($personId, IQueryBuilder::PARAM_INT)));
		if ($from !== null) {
			$qb->andWhere($qb->expr()->gte('event_date', $qb->createNamedParameter($from, IQueryBuilder::PARAM_DATE_IMMUTABLE)));
		}
		$qb->orderBy('event_date')->addOrderBy('id');
		return $this->findEntities($qb);
	}
}
