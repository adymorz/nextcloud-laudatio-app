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
 * Sources of the dates and notes of a term, all optional.
 */
#[AddColumn(table: 'laudatio_slices', name: 'start_date_source', type: ColumnType::STRING)]
#[AddColumn(table: 'laudatio_slices', name: 'end_date_source', type: ColumnType::STRING)]
#[AddColumn(table: 'laudatio_slices', name: 'notes', type: ColumnType::TEXT)]
class Version000300Date20261030120000 extends SimpleMigrationStep {
	/**
	 * @param Closure(): ISchemaWrapper $schemaClosure
	 */
	#[\Override]
	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
		$schema = $schemaClosure();
		$table = $schema->getTable('laudatio_slices');
		$changed = false;
		foreach (['start_date_source', 'end_date_source'] as $column) {
			if (!$table->hasColumn($column)) {
				$table->addColumn($column, Types::STRING, ['notnull' => false, 'length' => 255]);
				$changed = true;
			}
		}
		if (!$table->hasColumn('notes')) {
			$table->addColumn('notes', Types::TEXT, ['notnull' => false]);
			$changed = true;
		}
		return $changed ? $schema : null;
	}
}
