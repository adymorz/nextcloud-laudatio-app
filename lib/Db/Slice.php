<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Adrian Dymorz <dev@adrian.dymorz.ch>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Appreciation\Db;

use DateTimeImmutable;
use OCP\AppFramework\Db\Entity;
use OCP\DB\Types;

/**
 * A time slice of a person holding a function. An end date of null means the
 * slice is still active. Both dates are inclusive.
 *
 * @method int getPersonId()
 * @method void setPersonId(int $personId)
 * @method int getFunctionId()
 * @method void setFunctionId(int $functionId)
 * @method DateTimeImmutable getStartDate()
 * @method void setStartDate(DateTimeImmutable $startDate)
 * @method DateTimeImmutable|null getEndDate()
 * @method void setEndDate(?DateTimeImmutable $endDate)
 * @method int getCreatedAt()
 * @method void setCreatedAt(int $createdAt)
 * @method int getUpdatedAt()
 * @method void setUpdatedAt(int $updatedAt)
 */
class Slice extends Entity {
	protected int $personId = 0;
	protected int $functionId = 0;
	protected ?DateTimeImmutable $startDate = null;
	protected ?DateTimeImmutable $endDate = null;
	protected int $createdAt = 0;
	protected int $updatedAt = 0;

	public function __construct() {
		$this->addType('personId', Types::BIGINT);
		$this->addType('functionId', Types::BIGINT);
		$this->addType('startDate', Types::DATE_IMMUTABLE);
		$this->addType('endDate', Types::DATE_IMMUTABLE);
		$this->addType('createdAt', Types::BIGINT);
		$this->addType('updatedAt', Types::BIGINT);
	}

	public function isActiveOn(DateTimeImmutable $date): bool {
		$day = $date->format('Y-m-d');
		return $this->getStartDate()->format('Y-m-d') <= $day
			&& ($this->endDate === null || $this->endDate->format('Y-m-d') >= $day);
	}
}
