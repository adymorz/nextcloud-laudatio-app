<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Adrian Dymorz <dev@adrian.dymorz.ch>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Laudatio\Service;

use OCA\Laudatio\Db\AuditEntry;
use OCA\Laudatio\Db\AuditMapper;
use OCP\EventDispatcher\IEventDispatcher;
use OCP\Log\Audit\CriticalActionPerformedEvent;

/**
 * Records who created, changed, deleted or exported master data. Entries hold
 * IDs and field names only. Each entry is also dispatched as a critical action,
 * so the admin_audit app writes it to the Nextcloud audit log when it is enabled.
 */
class AuditService {
	public const ENTITY_PERSON = 'person';
	public const ENTITY_SLICE = 'slice';
	public const ENTITY_GROUP = 'group';
	public const ENTITY_FUNCTION = 'function';
	/** All master data at once, with entity ID 0 */
	public const ENTITY_DATA = 'data';

	public function __construct(
		private AuditMapper $mapper,
		private IEventDispatcher $dispatcher,
		private Clock $clock,
	) {
	}

	/**
	 * @param AuditEntry::ACTION_* $action
	 * @param self::ENTITY_* $entityType
	 * @param list<string> $changedFields
	 */
	public function log(?string $actorUid, string $action, string $entityType, int $entityId, array $changedFields = []): void {
		$entry = new AuditEntry();
		$entry->setActorUid($actorUid);
		$entry->setAction($action);
		$entry->setEntityType($entityType);
		$entry->setEntityId($entityId);
		$entry->setChangedFields($changedFields === [] ? null : json_encode($changedFields, JSON_THROW_ON_ERROR));
		$entry->setCreatedAt($this->clock->time());
		$this->mapper->insert($entry);

		$this->dispatcher->dispatchTyped(new CriticalActionPerformedEvent(
			'Laudatio: "%s" performed %s on %s #%s (fields: %s)',
			[
				'actor' => $actorUid ?? 'system',
				'action' => $action,
				'entityType' => $entityType,
				'entityId' => (string)$entityId,
				'fields' => $changedFields === [] ? '-' : implode(', ', $changedFields),
			],
		));
	}

	/**
	 * @return list<AuditEntry>
	 */
	public function findPage(int $limit, int $offset): array {
		return $this->mapper->findPage($limit, $offset);
	}

	public function count(): int {
		return $this->mapper->countAll();
	}
}
