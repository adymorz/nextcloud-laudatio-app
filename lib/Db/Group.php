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
 * An organizational group (not a Nextcloud group) with one responsible user,
 * or none (empty UID) after that user was deleted or the group was imported.
 *
 * @method string getName()
 * @method void setName(string $name)
 * @method string getResponsibleUid()
 * @method void setResponsibleUid(string $responsibleUid)
 * @method string|null getJubileeYears()
 * @method void setJubileeYears(?string $jubileeYears)
 * @method int getCreatedAt()
 * @method void setCreatedAt(int $createdAt)
 * @method int getUpdatedAt()
 * @method void setUpdatedAt(int $updatedAt)
 */
class Group extends Entity {
	protected string $name = '';
	protected string $responsibleUid = '';
	/** Comma separated override of the global jubilee years, null = use the global setting */
	protected ?string $jubileeYears = null;
	protected int $createdAt = 0;
	protected int $updatedAt = 0;

	public function __construct() {
		$this->addType('name', Types::STRING);
		$this->addType('responsibleUid', Types::STRING);
		$this->addType('jubileeYears', Types::STRING);
		$this->addType('createdAt', Types::BIGINT);
		$this->addType('updatedAt', Types::BIGINT);
	}

	/**
	 * Leaves the group without a responsible user. The setter ignores unchanged
	 * values, so on a new group it would leave the column out of the insert.
	 */
	public function clearResponsible(): void {
		$this->responsibleUid = '';
		$this->markFieldUpdated('responsibleUid');
	}
}
