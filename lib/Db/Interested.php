<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Adrian Dymorz <dev@adrian.dymorz.ch>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Appreciation\Db;

use OCP\AppFramework\Db\Entity;
use OCP\DB\Types;

/**
 * @method int getGroupId()
 * @method void setGroupId(int $groupId)
 * @method string getUserId()
 * @method void setUserId(string $userId)
 */
class Interested extends Entity {
	protected int $groupId = 0;
	protected string $userId = '';

	public function __construct() {
		$this->addType('groupId', Types::BIGINT);
		$this->addType('userId', Types::STRING);
	}
}
