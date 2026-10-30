<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Adrian Dymorz <dev@adrian.dymorz.ch>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Laudatio\Db;

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
 * @method string|null getStreet()
 * @method void setStreet(?string $street)
 * @method string|null getStreetNumber()
 * @method void setStreetNumber(?string $streetNumber)
 * @method string|null getPostalCode()
 * @method void setPostalCode(?string $postalCode)
 * @method string|null getCity()
 * @method void setCity(?string $city)
 * @method string|null getCountry()
 * @method void setCountry(?string $country)
 * @method string|null getMobilePhone()
 * @method void setMobilePhone(?string $mobilePhone)
 * @method string|null getOtherPhone()
 * @method void setOtherPhone(?string $otherPhone)
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
	protected ?string $street = null;
	protected ?string $streetNumber = null;
	protected ?string $postalCode = null;
	protected ?string $city = null;
	protected ?string $country = null;
	protected ?string $mobilePhone = null;
	protected ?string $otherPhone = null;
	protected int $createdAt = 0;
	protected int $updatedAt = 0;

	public function __construct() {
		$this->addType('firstName', Types::STRING);
		$this->addType('lastName', Types::STRING);
		$this->addType('dateOfBirth', Types::DATE_IMMUTABLE);
		$this->addType('notes', Types::TEXT);
		$this->addType('street', Types::STRING);
		$this->addType('streetNumber', Types::STRING);
		$this->addType('postalCode', Types::STRING);
		$this->addType('city', Types::STRING);
		$this->addType('country', Types::STRING);
		$this->addType('mobilePhone', Types::STRING);
		$this->addType('otherPhone', Types::STRING);
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
