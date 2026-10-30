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
 * A function (role) within a group, e.g. "Treasurer". Named OrgFunction because
 * "function" is a reserved word in PHP.
 *
 * @method int getGroupId()
 * @method void setGroupId(int $groupId)
 * @method string getName()
 * @method void setName(string $name)
 * @method void setYearlyGift(bool $yearlyGift)
 * @method int getCreatedAt()
 * @method void setCreatedAt(int $createdAt)
 * @method int getUpdatedAt()
 * @method void setUpdatedAt(int $updatedAt)
 */
class OrgFunction extends Entity {
	protected int $groupId = 0;
	protected string $name = '';
	/** Nullable column, see isYearlyGift() */
	protected ?bool $yearlyGift = false;
	protected int $createdAt = 0;
	protected int $updatedAt = 0;

	public function __construct() {
		$this->addType('groupId', Types::BIGINT);
		$this->addType('name', Types::STRING);
		$this->addType('yearlyGift', Types::BOOLEAN);
		$this->addType('createdAt', Types::BIGINT);
		$this->addType('updatedAt', Types::BIGINT);
	}

	/**
	 * Whether the holders get one gift per calendar year.
	 */
	public function isYearlyGift(): bool {
		return $this->yearlyGift === true;
	}
}
