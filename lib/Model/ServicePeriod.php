<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Adrian Dymorz <dev@adrian.dymorz.ch>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Appreciation\Model;

use DateTimeImmutable;

/**
 * Continuous service of a person in one function: back-to-back time slices
 * merged into one period. An end of null means the period is still open.
 */
final class ServicePeriod {
	public function __construct(
		public readonly int $functionId,
		public readonly DateTimeImmutable $start,
		public readonly ?DateTimeImmutable $end,
	) {
	}
}
