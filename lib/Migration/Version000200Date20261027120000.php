<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Adrian Dymorz <dev@adrian.dymorz.ch>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Appreciation\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\DB\Schema\ITable;
use OCP\DB\Types;
use OCP\Migration\Attributes\CreateTable;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

/**
 * Initial schema. Relations are enforced by the services (no DB foreign keys),
 * timestamps are unix seconds, calendar dates use DATE columns.
 */
#[CreateTable(table: 'appreciation_persons', description: 'Tracked persons')]
#[CreateTable(table: 'appreciation_groups', description: 'Groups with their responsible user')]
#[CreateTable(table: 'appreciation_interested', description: 'Interested users per group')]
#[CreateTable(table: 'appreciation_functions', description: 'Functions within a group')]
#[CreateTable(table: 'appreciation_slices', description: 'Time slices of a person in a function')]
#[CreateTable(table: 'appreciation_events', description: 'Generated birthday and jubilee events')]
#[CreateTable(table: 'appreciation_notified', description: 'Users already notified about an event')]
#[CreateTable(table: 'appreciation_audit', description: 'Audit log of master data changes')]
class Version000200Date20261027120000 extends SimpleMigrationStep {
	/**
	 * @param Closure(): ISchemaWrapper $schemaClosure
	 */
	#[\Override]
	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
		$schema = $schemaClosure();

		if (!$schema->hasTable('appreciation_persons')) {
			$table = $this->createTableWithId($schema, 'appreciation_persons');
			$table->addColumn('first_name', Types::STRING, ['notnull' => true, 'length' => 255]);
			$table->addColumn('last_name', Types::STRING, ['notnull' => true, 'length' => 255]);
			$table->addColumn('date_of_birth', Types::DATE, ['notnull' => true]);
			$table->addColumn('notes', Types::TEXT, ['notnull' => false]);
			$this->addTimestamps($table);
			$table->addIndex(['last_name', 'first_name'], 'appr_persons_name');
		}

		if (!$schema->hasTable('appreciation_groups')) {
			$table = $this->createTableWithId($schema, 'appreciation_groups');
			$table->addColumn('name', Types::STRING, ['notnull' => true, 'length' => 255]);
			$table->addColumn('responsible_uid', Types::STRING, ['notnull' => true, 'length' => 64]);
			$table->addColumn('jubilee_years', Types::STRING, ['notnull' => false, 'length' => 255]);
			$this->addTimestamps($table);
			$table->addIndex(['responsible_uid'], 'appr_groups_resp');
		}

		if (!$schema->hasTable('appreciation_interested')) {
			$table = $this->createTableWithId($schema, 'appreciation_interested');
			$table->addColumn('group_id', Types::BIGINT, ['notnull' => true, 'unsigned' => true]);
			$table->addColumn('user_id', Types::STRING, ['notnull' => true, 'length' => 64]);
			$table->addUniqueIndex(['group_id', 'user_id'], 'appr_interested_uniq');
			$table->addIndex(['user_id'], 'appr_interested_user');
		}

		if (!$schema->hasTable('appreciation_functions')) {
			$table = $this->createTableWithId($schema, 'appreciation_functions');
			$table->addColumn('group_id', Types::BIGINT, ['notnull' => true, 'unsigned' => true]);
			$table->addColumn('name', Types::STRING, ['notnull' => true, 'length' => 255]);
			$this->addTimestamps($table);
			$table->addIndex(['group_id'], 'appr_functions_group');
		}

		if (!$schema->hasTable('appreciation_slices')) {
			$table = $this->createTableWithId($schema, 'appreciation_slices');
			$table->addColumn('person_id', Types::BIGINT, ['notnull' => true, 'unsigned' => true]);
			$table->addColumn('function_id', Types::BIGINT, ['notnull' => true, 'unsigned' => true]);
			$table->addColumn('start_date', Types::DATE, ['notnull' => true]);
			$table->addColumn('end_date', Types::DATE, ['notnull' => false]);
			$this->addTimestamps($table);
			$table->addIndex(['person_id'], 'appr_slices_person');
			$table->addIndex(['function_id'], 'appr_slices_function');
		}

		if (!$schema->hasTable('appreciation_events')) {
			$table = $this->createTableWithId($schema, 'appreciation_events');
			$table->addColumn('uid', Types::STRING, ['notnull' => true, 'length' => 255]);
			$table->addColumn('type', Types::STRING, ['notnull' => true, 'length' => 16]);
			$table->addColumn('person_id', Types::BIGINT, ['notnull' => true, 'unsigned' => true]);
			$table->addColumn('function_id', Types::BIGINT, ['notnull' => false, 'unsigned' => true]);
			$table->addColumn('event_date', Types::DATE, ['notnull' => true]);
			$table->addColumn('ordinal', Types::INTEGER, ['notnull' => true, 'unsigned' => true]);
			$table->addColumn('greeted_at', Types::BIGINT, ['notnull' => false, 'unsigned' => true]);
			$table->addColumn('greeted_by', Types::STRING, ['notnull' => false, 'length' => 64]);
			$table->addColumn('created_at', Types::BIGINT, ['notnull' => true, 'unsigned' => true]);
			$table->addUniqueIndex(['uid'], 'appr_events_uid');
			$table->addIndex(['event_date'], 'appr_events_date');
			$table->addIndex(['person_id'], 'appr_events_person');
		}

		if (!$schema->hasTable('appreciation_notified')) {
			$table = $this->createTableWithId($schema, 'appreciation_notified');
			$table->addColumn('event_id', Types::BIGINT, ['notnull' => true, 'unsigned' => true]);
			$table->addColumn('user_id', Types::STRING, ['notnull' => true, 'length' => 64]);
			$table->addColumn('notified_at', Types::BIGINT, ['notnull' => true, 'unsigned' => true]);
			$table->addUniqueIndex(['event_id', 'user_id'], 'appr_notified_uniq');
		}

		if (!$schema->hasTable('appreciation_audit')) {
			$table = $this->createTableWithId($schema, 'appreciation_audit');
			$table->addColumn('actor_uid', Types::STRING, ['notnull' => false, 'length' => 64]);
			$table->addColumn('action', Types::STRING, ['notnull' => true, 'length' => 16]);
			$table->addColumn('entity_type', Types::STRING, ['notnull' => true, 'length' => 32]);
			$table->addColumn('entity_id', Types::BIGINT, ['notnull' => true, 'unsigned' => true]);
			$table->addColumn('changed_fields', Types::TEXT, ['notnull' => false]);
			$table->addColumn('created_at', Types::BIGINT, ['notnull' => true, 'unsigned' => true]);
			$table->addIndex(['created_at'], 'appr_audit_created');
		}

		return $schema;
	}

	private function createTableWithId(ISchemaWrapper $schema, string $name): ITable {
		$table = $schema->createTable($name);
		$table->addColumn('id', Types::BIGINT, ['autoincrement' => true, 'notnull' => true, 'unsigned' => true]);
		$table->setPrimaryKey(['id']);
		return $table;
	}

	private function addTimestamps(ITable $table): void {
		$table->addColumn('created_at', Types::BIGINT, ['notnull' => true, 'unsigned' => true]);
		$table->addColumn('updated_at', Types::BIGINT, ['notnull' => true, 'unsigned' => true]);
	}
}
