<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Adrian Dymorz <dev@adrian.dymorz.ch>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Laudatio\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\DB\Types;
use OCP\Migration\Attributes\AddColumn;
use OCP\Migration\Attributes\ColumnType;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

/**
 * Address and phone numbers of persons, all optional.
 */
#[AddColumn(table: 'laudatio_persons', name: 'street', type: ColumnType::STRING)]
#[AddColumn(table: 'laudatio_persons', name: 'street_number', type: ColumnType::STRING)]
#[AddColumn(table: 'laudatio_persons', name: 'postal_code', type: ColumnType::STRING)]
#[AddColumn(table: 'laudatio_persons', name: 'city', type: ColumnType::STRING)]
#[AddColumn(table: 'laudatio_persons', name: 'country', type: ColumnType::STRING)]
#[AddColumn(table: 'laudatio_persons', name: 'mobile_phone', type: ColumnType::STRING)]
#[AddColumn(table: 'laudatio_persons', name: 'other_phone', type: ColumnType::STRING)]
class Version000300Date20261029120000 extends SimpleMigrationStep {
	/** column => length */
	private const COLUMNS = [
		'street' => 255,
		'street_number' => 32,
		'postal_code' => 32,
		'city' => 255,
		'country' => 255,
		'mobile_phone' => 32,
		'other_phone' => 32,
	];

	/**
	 * @param Closure(): ISchemaWrapper $schemaClosure
	 */
	#[\Override]
	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
		$schema = $schemaClosure();
		$table = $schema->getTable('laudatio_persons');
		$changed = false;
		foreach (self::COLUMNS as $column => $length) {
			if (!$table->hasColumn($column)) {
				$table->addColumn($column, Types::STRING, ['notnull' => false, 'length' => $length]);
				$changed = true;
			}
		}
		return $changed ? $schema : null;
	}
}
