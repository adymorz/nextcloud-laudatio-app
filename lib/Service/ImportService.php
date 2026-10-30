<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Adrian Dymorz <dev@adrian.dymorz.ch>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Laudatio\Service;

use OCA\Laudatio\Db\AuditEntry;
use OCA\Laudatio\Db\GroupMapper;
use OCA\Laudatio\Db\OrgFunctionMapper;
use OCA\Laudatio\Db\PersonMapper;
use OCA\Laudatio\Db\SliceMapper;
use OCA\Laudatio\Exception\ConflictException;
use OCA\Laudatio\Exception\ValidationException;
use OCA\Laudatio\Model\ImportData;
use OCA\Laudatio\ResponseDefinitions;
use OCP\AppFramework\Db\TTransactional;
use OCP\IDBConnection;
use OCP\IL10N;

/**
 * Imports an export file into an empty app, all or nothing. The groups have no
 * responsible or interested users afterwards, so nobody gets reminders until a
 * manager assigns them. Every created row is written to the audit log.
 *
 * @psalm-import-type LaudatioImportResult from ResponseDefinitions
 */
class ImportService {
	use TTransactional;

	public function __construct(
		private ImportValidator $validator,
		private GroupMapper $groupMapper,
		private OrgFunctionMapper $functionMapper,
		private PersonMapper $personMapper,
		private SliceMapper $sliceMapper,
		private PersonService $persons,
		private EventService $eventService,
		private AuditService $audit,
		private RecipientService $recipients,
		private AccessService $access,
		private Clock $clock,
		private IDBConnection $db,
		private IL10N $l10n,
	) {
	}

	/**
	 * @param array<array-key, mixed> $data decoded content of the export file
	 * @return LaudatioImportResult
	 * @throws ConflictException when the app already contains data
	 * @throws ValidationException when the file is not valid
	 */
	public function import(string $actor, array $data): array {
		if ($this->groupMapper->findAll() !== [] || $this->personMapper->findAll() !== []) {
			throw new ConflictException($this->l10n->t('Laudatio already contains data. You can only import into an empty app, so nothing was changed.'));
		}
		return $this->write($actor, $this->validator->validate($data));
	}

	/**
	 * Separate from import() because the integration tests run on an instance
	 * that may contain data.
	 *
	 * @return LaudatioImportResult
	 */
	public function write(string $actor, ImportData $data): array {
		$counts = ['groups' => 0, 'functions' => 0, 'persons' => 0, 'terms' => 0];
		$now = $this->clock->time();

		$this->atomic(function () use ($actor, $data, $now, &$counts): void {
			$functionIds = [];
			foreach ($data->groups as ['group' => $group, 'functions' => $functions]) {
				$group->setCreatedAt($now);
				$group->setUpdatedAt($now);
				$this->groupMapper->insert($group);
				$this->audit->log($actor, AuditEntry::ACTION_CREATE, AuditService::ENTITY_GROUP, $group->getId(), ['name', 'jubilee_years']);
				$counts['groups']++;

				foreach ($functions as $fileId => $function) {
					$function->setGroupId($group->getId());
					$function->setCreatedAt($now);
					$function->setUpdatedAt($now);
					$this->functionMapper->insert($function);
					$this->audit->log($actor, AuditEntry::ACTION_CREATE, AuditService::ENTITY_FUNCTION, $function->getId(), ['group_id', 'name', 'yearly_gift']);
					$functionIds[$fileId] = $function->getId();
					$counts['functions']++;
				}
			}

			foreach ($data->persons as ['person' => $person, 'terms' => $terms]) {
				$person->setCreatedAt($now);
				$person->setUpdatedAt($now);
				$this->personMapper->insert($person);
				$this->audit->log($actor, AuditEntry::ACTION_CREATE, AuditService::ENTITY_PERSON, $person->getId(), $this->persons->fieldNames($person));
				$counts['persons']++;

				foreach ($terms as $slice) {
					$slice->setPersonId($person->getId());
					$slice->setFunctionId($functionIds[$slice->getFunctionId()]);
					$slice->setCreatedAt($now);
					$slice->setUpdatedAt($now);
					$this->sliceMapper->insert($slice);
					$this->audit->log($actor, AuditEntry::ACTION_CREATE, AuditService::ENTITY_SLICE, $slice->getId(), ['person_id', 'function_id', 'start_date', 'end_date']);
					$counts['terms']++;
				}
			}
		}, $this->db);

		$this->recipients->reset();
		$this->access->clearCache();
		$this->eventService->syncAll();
		return $counts;
	}
}
