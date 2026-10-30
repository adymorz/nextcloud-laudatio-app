<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Adrian Dymorz <dev@adrian.dymorz.ch>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Laudatio\Model;

use OCA\Laudatio\Db\Group;
use OCA\Laudatio\Db\OrgFunction;
use OCA\Laudatio\Db\Person;
use OCA\Laudatio\Db\Slice;

/**
 * The content of a validated export file as unsaved entities.
 */
final class ImportData {
	/**
	 * @param list<array{group: Group, functions: array<int, OrgFunction>}> $groups functions by their ID in the file
	 * @param list<array{person: Person, terms: list<Slice>}> $persons the terms hold the function IDs of the file
	 */
	public function __construct(
		public readonly array $groups,
		public readonly array $persons,
	) {
	}
}
