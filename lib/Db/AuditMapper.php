<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Adrian Dymorz <dev@adrian.dymorz.ch>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Appreciation\Db;

use OCP\AppFramework\Db\QBMapper;
use OCP\IDBConnection;

/**
 * @template-extends QBMapper<AuditEntry>
 */
class AuditMapper extends QBMapper {
	public function __construct(IDBConnection $db) {
		parent::__construct($db, 'appreciation_audit', AuditEntry::class);
	}

	/**
	 * @return list<AuditEntry> newest first
	 */
	public function findPage(int $limit, int $offset): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from($this->getTableName())
			->orderBy('created_at', 'DESC')
			->addOrderBy('id', 'DESC')
			->setMaxResults($limit)
			->setFirstResult($offset);
		return $this->findEntities($qb);
	}

	public function countAll(): int {
		$qb = $this->db->getQueryBuilder();
		$qb->select($qb->func()->count('*', 'cnt'))->from($this->getTableName());
		$cursor = $qb->executeQuery();
		$count = (int)$cursor->fetchOne();
		$cursor->closeCursor();
		return $count;
	}
}
