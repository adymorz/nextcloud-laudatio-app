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
use OCP\Migration\Attributes\CreateTable;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

/**
 * Initial schema. Relations are enforced by the services (no DB foreign keys),
 * timestamps are unix seconds, calendar dates use DATE columns.
 *
 * The table objects are only used inside createTable(): Nextcloud 34 returns
 * Doctrine tables, Nextcloud 35 its own ITable, both with the same methods.
 */
#[CreateTable(table: 'laudatio_persons', description: 'Tracked persons')]
#[CreateTable(table: 'laudatio_groups', description: 'Groups with their responsible user')]
#[CreateTable(table: 'laudatio_interested', description: 'Interested users per group')]
#[CreateTable(table: 'laudatio_functions', description: 'Functions within a group')]
#[CreateTable(table: 'laudatio_slices', description: 'Time slices of a person in a function')]
#[CreateTable(table: 'laudatio_events', description: 'Generated birthday and jubilee events')]
#[CreateTable(table: 'laudatio_notified', description: 'Users already notified about an event')]
#[CreateTable(table: 'laudatio_audit', description: 'Audit log of master data changes')]
class Version000200Date20261027120000 extends SimpleMigrationStep {
	private const NOT_NULL = ['notnull' => true];
	private const NULLABLE = ['notnull' => false];
	private const ID = ['notnull' => true, 'unsigned' => true];
	private const ID_NULLABLE = ['notnull' => false, 'unsigned' => true];

	/**
	 * @param Closure(): ISchemaWrapper $schemaClosure
	 */
	#[\Override]
	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
		$schema = $schemaClosure();

		$this->createTable($schema, 'laudatio_persons', [
			'first_name' => [Types::STRING, ['notnull' => true, 'length' => 255]],
			'last_name' => [Types::STRING, ['notnull' => true, 'length' => 255]],
			'date_of_birth' => [Types::DATE, self::NOT_NULL],
			'notes' => [Types::TEXT, self::NULLABLE],
		], true, ['appr_persons_name' => ['last_name', 'first_name']]);

		$this->createTable($schema, 'laudatio_groups', [
			'name' => [Types::STRING, ['notnull' => true, 'length' => 255]],
			'responsible_uid' => [Types::STRING, ['notnull' => true, 'length' => 64]],
			'jubilee_years' => [Types::STRING, ['notnull' => false, 'length' => 255]],
		], true, ['appr_groups_resp' => ['responsible_uid']]);

		$this->createTable($schema, 'laudatio_interested', [
			'group_id' => [Types::BIGINT, self::ID],
			'user_id' => [Types::STRING, ['notnull' => true, 'length' => 64]],
		], false, ['appr_interested_user' => ['user_id']], ['appr_interested_uniq' => ['group_id', 'user_id']]);

		$this->createTable($schema, 'laudatio_functions', [
			'group_id' => [Types::BIGINT, self::ID],
			'name' => [Types::STRING, ['notnull' => true, 'length' => 255]],
		], true, ['appr_functions_group' => ['group_id']]);

		$this->createTable($schema, 'laudatio_slices', [
			'person_id' => [Types::BIGINT, self::ID],
			'function_id' => [Types::BIGINT, self::ID],
			'start_date' => [Types::DATE, self::NOT_NULL],
			'end_date' => [Types::DATE, self::NULLABLE],
		], true, ['appr_slices_person' => ['person_id'], 'appr_slices_function' => ['function_id']]);

		$this->createTable($schema, 'laudatio_events', [
			'uid' => [Types::STRING, ['notnull' => true, 'length' => 255]],
			'type' => [Types::STRING, ['notnull' => true, 'length' => 16]],
			'person_id' => [Types::BIGINT, self::ID],
			'function_id' => [Types::BIGINT, self::ID_NULLABLE],
			'event_date' => [Types::DATE, self::NOT_NULL],
			'ordinal' => [Types::INTEGER, self::ID],
			'greeted_at' => [Types::BIGINT, self::ID_NULLABLE],
			'greeted_by' => [Types::STRING, ['notnull' => false, 'length' => 64]],
			'created_at' => [Types::BIGINT, self::ID],
		], false, ['appr_events_date' => ['event_date'], 'appr_events_person' => ['person_id']], ['appr_events_uid' => ['uid']]);

		$this->createTable($schema, 'laudatio_notified', [
			'event_id' => [Types::BIGINT, self::ID],
			'user_id' => [Types::STRING, ['notnull' => true, 'length' => 64]],
			'notified_at' => [Types::BIGINT, self::ID],
		], false, [], ['appr_notified_uniq' => ['event_id', 'user_id']]);

		$this->createTable($schema, 'laudatio_audit', [
			'actor_uid' => [Types::STRING, ['notnull' => false, 'length' => 64]],
			'action' => [Types::STRING, ['notnull' => true, 'length' => 16]],
			'entity_type' => [Types::STRING, ['notnull' => true, 'length' => 32]],
			'entity_id' => [Types::BIGINT, self::ID],
			'changed_fields' => [Types::TEXT, self::NULLABLE],
			'created_at' => [Types::BIGINT, self::ID],
		], false, ['appr_audit_created' => ['created_at']]);

		return $schema;
	}

	/**
	 * Creates a table with an autoincrement "id" primary key, unless it exists.
	 *
	 * @param non-empty-string $name
	 * @param array<non-empty-string, array{Types::*, array{notnull?: bool, length?: int, unsigned?: bool}}> $columns name => [type, options]
	 * @param bool $timestamps whether to add created_at and updated_at
	 * @param array<non-empty-string, list<non-empty-string>> $indexes index name => columns
	 * @param array<non-empty-string, list<non-empty-string>> $uniqueIndexes index name => columns
	 */
	private function createTable(ISchemaWrapper $schema, string $name, array $columns, bool $timestamps, array $indexes = [], array $uniqueIndexes = []): void {
		if ($schema->hasTable($name)) {
			return;
		}

		$table = $schema->createTable($name);
		$table->addColumn('id', Types::BIGINT, ['autoincrement' => true, 'notnull' => true, 'unsigned' => true]);
		foreach ($columns as $column => [$type, $options]) {
			$table->addColumn($column, $type, $options);
		}
		if ($timestamps) {
			$table->addColumn('created_at', Types::BIGINT, self::ID);
			$table->addColumn('updated_at', Types::BIGINT, self::ID);
		}
		$table->setPrimaryKey(['id']);
		foreach ($uniqueIndexes as $indexName => $indexColumns) {
			$table->addUniqueIndex($indexColumns, $indexName);
		}
		foreach ($indexes as $indexName => $indexColumns) {
			$table->addIndex($indexColumns, $indexName);
		}
	}
}
