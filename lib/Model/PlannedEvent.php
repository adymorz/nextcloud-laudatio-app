<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Adrian Dymorz <dev@adrian.dymorz.ch>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Appreciation\Model;

use DateTimeImmutable;
use OCA\Appreciation\Db\Event;

/**
 * An event as it should exist according to the current master data.
 */
final class PlannedEvent {
	/**
	 * @param Event::TYPE_* $type
	 */
	public function __construct(
		public readonly string $uid,
		public readonly string $type,
		public readonly int $personId,
		public readonly ?int $functionId,
		public readonly DateTimeImmutable $date,
		public readonly int $ordinal,
	) {
	}
}
