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
 * A generated birthday, jubilee or yearly gift. The ordinal is the age
 * (birthdays), the number of years of service (jubilees) or the calendar year
 * (gifts). For gifts, the event date is the due date and "greeted" means
 * presented.
 *
 * @method string getUid()
 * @method void setUid(string $uid)
 * @method string getType()
 * @method void setType(string $type)
 * @method int getPersonId()
 * @method void setPersonId(int $personId)
 * @method int|null getFunctionId()
 * @method void setFunctionId(?int $functionId)
 * @method DateTimeImmutable getEventDate()
 * @method void setEventDate(DateTimeImmutable $eventDate)
 * @method int getOrdinal()
 * @method void setOrdinal(int $ordinal)
 * @method int|null getGreetedAt()
 * @method void setGreetedAt(?int $greetedAt)
 * @method string|null getGreetedBy()
 * @method void setGreetedBy(?string $greetedBy)
 * @method int getCreatedAt()
 * @method void setCreatedAt(int $createdAt)
 */
class Event extends Entity {
	public const TYPE_BIRTHDAY = 'birthday';
	public const TYPE_JUBILEE = 'jubilee';
	public const TYPE_GIFT = 'gift';

	protected string $uid = '';
	protected string $type = '';
	protected int $personId = 0;
	protected ?int $functionId = null;
	protected ?DateTimeImmutable $eventDate = null;
	protected int $ordinal = 0;
	protected ?int $greetedAt = null;
	protected ?string $greetedBy = null;
	protected int $createdAt = 0;

	public function __construct() {
		$this->addType('uid', Types::STRING);
		$this->addType('type', Types::STRING);
		$this->addType('personId', Types::BIGINT);
		$this->addType('functionId', Types::BIGINT);
		$this->addType('eventDate', Types::DATE_IMMUTABLE);
		$this->addType('ordinal', Types::INTEGER);
		$this->addType('greetedAt', Types::BIGINT);
		$this->addType('greetedBy', Types::STRING);
		$this->addType('createdAt', Types::BIGINT);
	}
}
