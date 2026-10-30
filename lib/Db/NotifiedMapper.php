<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Adrian Dymorz <dev@adrian.dymorz.ch>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Laudatio\Db;

use DateTimeImmutable;
use OCP\AppFramework\Db\QBMapper;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;

/**
 * @template-extends QBMapper<Notified>
 */
class NotifiedMapper extends QBMapper {
	public function __construct(IDBConnection $db) {
		parent::__construct($db, 'laudatio_notified', Notified::class);
	}

	/**
	 * @return list<string>
	 */
	public function findUserIdsByEvent(int $eventId): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('user_id')
			->from($this->getTableName())
			->where($qb->expr()->eq('event_id', $qb->createNamedParameter($eventId, IQueryBuilder::PARAM_INT)));
		$cursor = $qb->executeQuery();
		$userIds = [];
		while (($row = $cursor->fetch()) !== false) {
			$userIds[] = (string)$row['user_id'];
		}
		$cursor->closeCursor();
		return $userIds;
	}

	/**
	 * IDs of events before the given day that users were notified about.
	 *
	 * @return list<int>
	 */
	public function findEventIdsBefore(DateTimeImmutable $day): array {
		$qb = $this->db->getQueryBuilder();
		$qb->selectDistinct('n.event_id')
			->from($this->getTableName(), 'n')
			->innerJoin('n', 'laudatio_events', 'e', $qb->expr()->eq('e.id', 'n.event_id'))
			->where($qb->expr()->lt('e.event_date', $qb->createNamedParameter($day, IQueryBuilder::PARAM_DATE_IMMUTABLE)));
		$cursor = $qb->executeQuery();
		$ids = [];
		while (($row = $cursor->fetch()) !== false) {
			$ids[] = (int)$row['event_id'];
		}
		$cursor->closeCursor();
		return $ids;
	}

	public function add(int $eventId, string $userId, int $time): void {
		$entry = new Notified();
		$entry->setEventId($eventId);
		$entry->setUserId($userId);
		$entry->setNotifiedAt($time);
		$this->insert($entry);
	}

	/**
	 * @param list<int> $eventIds
	 */
	public function deleteByEventIds(array $eventIds): void {
		foreach (array_chunk(array_values(array_unique($eventIds)), 1000) as $chunk) {
			$qb = $this->db->getQueryBuilder();
			$qb->delete($this->getTableName())
				->where($qb->expr()->in('event_id', $qb->createNamedParameter($chunk, IQueryBuilder::PARAM_INT_ARRAY)));
			$qb->executeStatement();
		}
	}
}
