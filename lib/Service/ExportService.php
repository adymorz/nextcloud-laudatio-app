<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Adrian Dymorz <dev@adrian.dymorz.ch>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Laudatio\Service;

use OCA\Laudatio\Db\AuditEntry;
use OCA\Laudatio\Db\OrgFunction;
use OCA\Laudatio\Db\Person;
use OCA\Laudatio\Db\Slice;
use OCA\Laudatio\ResponseDefinitions;

/**
 * Exports all groups, functions, persons and terms for a backup or the transfer
 * to another instance, see ImportService for the way back. The file holds no
 * references to Nextcloud users, and no events: they are generated again.
 * The format is documented in the README.
 *
 * @psalm-import-type LaudatioExport from ResponseDefinitions
 * @psalm-import-type LaudatioExportFunction from ResponseDefinitions
 * @psalm-import-type LaudatioExportPerson from ResponseDefinitions
 */
class ExportService {
	public const FORMAT = 'laudatio-export';
	/** Increase on every change of the format and keep ImportValidator reading the older versions */
	public const VERSION = 1;

	public function __construct(
		private GroupService $groups,
		private PersonService $persons,
		private AuditService $audit,
		private Clock $clock,
	) {
	}

	/**
	 * @return LaudatioExport
	 */
	public function export(string $actor): array {
		$functionsByGroup = [];
		foreach ($this->groups->getFunctions() as $function) {
			$functionsByGroup[$function->getGroupId()][] = $this->function($function);
		}
		$groups = [];
		foreach ($this->groups->findAll() as $group) {
			$groups[] = [
				'name' => $group->getName(),
				'jubileeYears' => $group->getJubileeYears(),
				'functions' => $functionsByGroup[$group->getId()] ?? [],
			];
		}

		$persons = $this->persons->findAll();
		$slices = $this->persons->getSlicesByPerson(array_map(static fn (Person $person): int => $person->getId(), $persons));

		$this->audit->log($actor, AuditEntry::ACTION_EXPORT, AuditService::ENTITY_DATA, 0);
		return [
			'format' => self::FORMAT,
			'version' => self::VERSION,
			'exportedAt' => Dates::format($this->clock->today()),
			'groups' => $groups,
			'persons' => array_map(fn (Person $person): array => $this->person($person, $slices[$person->getId()] ?? []), $persons),
		];
	}

	/**
	 * @return LaudatioExportFunction
	 */
	private function function(OrgFunction $function): array {
		return [
			'id' => $function->getId(),
			'name' => $function->getName(),
			'yearlyGift' => $function->isYearlyGift(),
		];
	}

	/**
	 * @param list<Slice> $slices
	 * @return LaudatioExportPerson
	 */
	private function person(Person $person, array $slices): array {
		return [
			'firstName' => $person->getFirstName(),
			'lastName' => $person->getLastName(),
			'dateOfBirth' => Dates::format($person->getDateOfBirth()),
			'notes' => $person->getNotes(),
			'street' => $person->getStreet(),
			'streetNumber' => $person->getStreetNumber(),
			'postalCode' => $person->getPostalCode(),
			'city' => $person->getCity(),
			'country' => $person->getCountry(),
			'mobilePhone' => $person->getMobilePhone(),
			'otherPhone' => $person->getOtherPhone(),
			'terms' => array_map(static function (Slice $slice): array {
				$end = $slice->getEndDate();
				return [
					'functionId' => $slice->getFunctionId(),
					'startDate' => Dates::format($slice->getStartDate()),
					'endDate' => $end === null ? null : Dates::format($end),
				];
			}, $slices),
		];
	}
}
