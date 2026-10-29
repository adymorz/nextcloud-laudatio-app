<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Adrian Dymorz <dev@adrian.dymorz.ch>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Appreciation\Model;

/**
 * The editable data of a person as entered, not yet validated.
 */
final class PersonInput {
	public function __construct(
		public readonly string $firstName,
		public readonly string $lastName,
		public readonly string $dateOfBirth,
		public readonly ?string $notes = null,
		public readonly ?string $street = null,
		public readonly ?string $streetNumber = null,
		public readonly ?string $postalCode = null,
		public readonly ?string $city = null,
		public readonly ?string $country = null,
		public readonly ?string $mobilePhone = null,
		public readonly ?string $otherPhone = null,
	) {
	}
}
