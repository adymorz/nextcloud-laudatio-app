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
 * @method int getEventId()
 * @method void setEventId(int $eventId)
 * @method string getUserId()
 * @method void setUserId(string $userId)
 * @method int getNotifiedAt()
 * @method void setNotifiedAt(int $notifiedAt)
 */
class Notified extends Entity {
	protected int $eventId = 0;
	protected string $userId = '';
	protected int $notifiedAt = 0;

	public function __construct() {
		$this->addType('eventId', Types::BIGINT);
		$this->addType('userId', Types::STRING);
		$this->addType('notifiedAt', Types::BIGINT);
	}
}
