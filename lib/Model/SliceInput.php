<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Adrian Dymorz <dev@adrian.dymorz.ch>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Laudatio\Model;

/**
 * The editable data of a term as entered, not yet validated.
 */
final class SliceInput {
	public function __construct(
		public readonly int $functionId,
		public readonly string $startDate,
		public readonly ?string $endDate = null,
		public readonly ?string $startDateSource = null,
		public readonly ?string $endDateSource = null,
		public readonly ?string $notes = null,
	) {
	}
}
