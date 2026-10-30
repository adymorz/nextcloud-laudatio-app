<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Adrian Dymorz <dev@adrian.dymorz.ch>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Laudatio\Db;

use OCP\AppFramework\Db\Entity;
use OCP\DB\Types;

/**
 * Audit log entry. Only stores IDs and names of changed fields, never values,
 * so no personal data survives the deletion of a person.
 *
 * @method string|null getActorUid()
 * @method void setActorUid(?string $actorUid)
 * @method string getAction()
 * @method void setAction(string $action)
 * @method string getEntityType()
 * @method void setEntityType(string $entityType)
 * @method int getEntityId()
 * @method void setEntityId(int $entityId)
 * @method string|null getChangedFields()
 * @method void setChangedFields(?string $changedFields)
 * @method int getCreatedAt()
 * @method void setCreatedAt(int $createdAt)
 */
class AuditEntry extends Entity {
	public const ACTION_CREATE = 'create';
	public const ACTION_UPDATE = 'update';
	public const ACTION_DELETE = 'delete';
	public const ACTION_EXPORT = 'export';

	protected ?string $actorUid = null;
	protected string $action = '';
	protected string $entityType = '';
	/** Null until set, so that the ID 0 of AuditService::ENTITY_DATA counts as a change for the insert */
	protected ?int $entityId = null;
	/** JSON encoded list of field names */
	protected ?string $changedFields = null;
	protected int $createdAt = 0;

	public function __construct() {
		$this->addType('actorUid', Types::STRING);
		$this->addType('action', Types::STRING);
		$this->addType('entityType', Types::STRING);
		$this->addType('entityId', Types::BIGINT);
		$this->addType('changedFields', Types::TEXT);
		$this->addType('createdAt', Types::BIGINT);
	}

	/**
	 * @return list<string>
	 */
	public function getChangedFieldList(): array {
		if ($this->changedFields === null || $this->changedFields === '') {
			return [];
		}
		/** @var mixed $decoded */
		$decoded = json_decode($this->changedFields, true);
		return is_array($decoded) ? array_values(array_filter($decoded, 'is_string')) : [];
	}
}
