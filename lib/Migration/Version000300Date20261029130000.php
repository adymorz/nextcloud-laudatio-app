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
 * Functions whose holders get one gift per calendar year.
 */
#[AddColumn(table: 'laudatio_functions', name: 'yearly_gift', type: ColumnType::BOOLEAN)]
class Version000300Date20261029130000 extends SimpleMigrationStep {
	/**
	 * @param Closure(): ISchemaWrapper $schemaClosure
	 */
	#[\Override]
	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
		$schema = $schemaClosure();
		$table = $schema->getTable('laudatio_functions');
		if ($table->hasColumn('yearly_gift')) {
			return null;
		}
		// Boolean columns must be nullable for Oracle
		$table->addColumn('yearly_gift', Types::BOOLEAN, ['notnull' => false, 'default' => false]);
		return $schema;
	}
}
