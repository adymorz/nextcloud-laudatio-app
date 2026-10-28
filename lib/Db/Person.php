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
 * @method string getFirstName()
 * @method void setFirstName(string $firstName)
 * @method string getLastName()
 * @method void setLastName(string $lastName)
 * @method DateTimeImmutable getDateOfBirth()
 * @method void setDateOfBirth(DateTimeImmutable $dateOfBirth)
 * @method string|null getNotes()
 * @method void setNotes(?string $notes)
 * @method int getCreatedAt()
 * @method void setCreatedAt(int $createdAt)
 * @method int getUpdatedAt()
 * @method void setUpdatedAt(int $updatedAt)
 */
class Person extends Entity {
	protected string $firstName = '';
	protected string $lastName = '';
	protected ?DateTimeImmutable $dateOfBirth = null;
	protected ?string $notes = null;
	protected int $createdAt = 0;
	protected int $updatedAt = 0;

	public function __construct() {
		$this->addType('firstName', Types::STRING);
		$this->addType('lastName', Types::STRING);
		$this->addType('dateOfBirth', Types::DATE_IMMUTABLE);
		$this->addType('notes', Types::TEXT);
		$this->addType('createdAt', Types::BIGINT);
		$this->addType('updatedAt', Types::BIGINT);
	}

	public function getDisplayName(): string {
		return trim($this->firstName . ' ' . $this->lastName);
	}

	public function hasDateOfBirth(): bool {
		return $this->dateOfBirth !== null;
	}
}
