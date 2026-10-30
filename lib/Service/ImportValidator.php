<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Adrian Dymorz <dev@adrian.dymorz.ch>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Laudatio\Service;

use OCA\Laudatio\Db\Group;
use OCA\Laudatio\Db\OrgFunction;
use OCA\Laudatio\Db\Person;
use OCA\Laudatio\Db\Slice;
use OCA\Laudatio\Exception\ValidationException;
use OCA\Laudatio\Model\ImportData;
use OCA\Laudatio\Model\PersonInput;
use OCA\Laudatio\Model\SliceInput;
use OCP\IL10N;

/**
 * Checks a decoded export file with the same rules as the regular input and
 * turns it into unsaved entities, without touching the database. Keys that are
 * not part of the format are ignored, so a file can never bring in references
 * to Nextcloud users.
 */
class ImportValidator {
	public function __construct(
		private GroupService $groups,
		private PersonService $persons,
		private IL10N $l10n,
	) {
	}

	/**
	 * @param array<array-key, mixed> $data decoded content of the export file
	 * @throws ValidationException
	 */
	public function validate(array $data): ImportData {
		$this->checkFormat($data);

		$groups = [];
		$functionIds = [];
		foreach ($this->entries($data, 'groups') as $position => $entry) {
			try {
				$group = $this->group($entry);
				$functions = [];
				foreach ($this->entries($entry, 'functions') as $function) {
					$id = $function['id'] ?? null;
					if (!is_int($id) || isset($functionIds[$id])) {
						throw new ValidationException($this->l10n->t('Every function needs its own ID.'));
					}
					$functionIds[$id] = true;
					$functions[$id] = $this->function($function);
				}
				$groups[] = ['group' => $group, 'functions' => $functions];
			} catch (ValidationException $e) {
				throw new ValidationException($this->l10n->t('Group %1$d "%2$s"', [$position + 1, $this->text($entry, 'name')]) . ': ' . $e->getMessage());
			}
		}

		$persons = [];
		foreach ($this->entries($data, 'persons') as $position => $entry) {
			try {
				$persons[] = $this->person($entry, $functionIds);
			} catch (ValidationException $e) {
				$name = trim($this->text($entry, 'firstName') . ' ' . $this->text($entry, 'lastName'));
				throw new ValidationException($this->l10n->t('Person %1$d "%2$s"', [$position + 1, $name]) . ': ' . $e->getMessage());
			}
		}

		return new ImportData($groups, $persons);
	}

	/**
	 * @param array<array-key, mixed> $data
	 * @throws ValidationException
	 */
	private function checkFormat(array $data): void {
		$version = $data['version'] ?? null;
		if (($data['format'] ?? null) !== ExportService::FORMAT || !is_int($version) || $version < 1) {
			throw new ValidationException($this->l10n->t('This is not a Laudatio export file.'));
		}
		if ($version < ExportService::MIN_VERSION) {
			throw new ValidationException($this->l10n->t('This file has the old format version %1$d. This version of Laudatio only reads version %2$d and newer.', [$version, ExportService::MIN_VERSION]));
		}
		if ($version > ExportService::VERSION) {
			throw new ValidationException($this->l10n->t('This file has format version %1$d, but this version of Laudatio only reads up to version %2$d. Please update the app first.', [$version, ExportService::VERSION]));
		}
	}

	/**
	 * @param array<array-key, mixed> $entry
	 * @throws ValidationException
	 */
	private function group(array $entry): Group {
		$group = new Group();
		$group->setName($this->groups->validateName($this->string($entry, 'name')));
		$group->setJubileeYears($this->groups->normalizeJubileeYears($this->optionalString($entry, 'jubileeYears')));
		$group->clearResponsible();
		return $group;
	}

	/**
	 * @param array<array-key, mixed> $entry
	 * @throws ValidationException
	 */
	private function function(array $entry): OrgFunction {
		$yearlyGift = $entry['yearlyGift'] ?? null;
		if (!is_bool($yearlyGift)) {
			throw $this->invalid('yearlyGift');
		}
		$function = new OrgFunction();
		$function->setName($this->groups->validateName($this->string($entry, 'name')));
		$function->setYearlyGift($yearlyGift);
		return $function;
	}

	/**
	 * @param array<array-key, mixed> $entry
	 * @param array<int, true> $functionIds IDs of the functions in the file
	 * @return array{person: Person, terms: list<Slice>}
	 * @throws ValidationException
	 */
	private function person(array $entry, array $functionIds): array {
		$person = $this->persons->build(new PersonInput(
			$this->string($entry, 'firstName'),
			$this->string($entry, 'lastName'),
			$this->string($entry, 'dateOfBirth'),
			$this->optionalString($entry, 'notes'),
			$this->optionalString($entry, 'street'),
			$this->optionalString($entry, 'streetNumber'),
			$this->optionalString($entry, 'postalCode'),
			$this->optionalString($entry, 'city'),
			$this->optionalString($entry, 'country'),
			$this->optionalString($entry, 'mobilePhone'),
			$this->optionalString($entry, 'otherPhone'),
		));

		$terms = [];
		foreach ($this->entries($entry, 'terms') as $term) {
			$functionId = $term['functionId'] ?? null;
			if (!is_int($functionId) || !isset($functionIds[$functionId])) {
				throw new ValidationException($this->l10n->t('A term refers to a function that is not in the file.'));
			}
			$slice = $this->persons->buildSlice(new SliceInput(
				$functionId,
				$this->string($term, 'startDate'),
				$this->optionalString($term, 'endDate'),
				$this->optionalString($term, 'startDateSource'),
				$this->optionalString($term, 'endDateSource'),
				$this->optionalString($term, 'notes'),
			));
			foreach ($terms as $other) {
				if ($other->getFunctionId() === $functionId && SliceValidator::overlaps($slice->getStartDate(), $slice->getEndDate(), $other->getStartDate(), $other->getEndDate())) {
					throw new ValidationException($this->l10n->t('The term overlaps another term of this person in the same function.'));
				}
			}
			$terms[] = $slice;
		}
		return ['person' => $person, 'terms' => $terms];
	}

	/**
	 * @param array<array-key, mixed> $data
	 * @return list<array<array-key, mixed>>
	 * @throws ValidationException when the key holds no list of objects
	 */
	private function entries(array $data, string $key): array {
		$entries = $data[$key] ?? null;
		if (!is_array($entries) || !array_is_list($entries)) {
			throw $this->invalid($key);
		}
		$result = [];
		foreach ($entries as $entry) {
			if (!is_array($entry)) {
				throw $this->invalid($key);
			}
			$result[] = $entry;
		}
		return $result;
	}

	/**
	 * @param array<array-key, mixed> $entry
	 * @throws ValidationException
	 */
	private function string(array $entry, string $key): string {
		$value = $entry[$key] ?? null;
		if (!is_string($value)) {
			throw $this->invalid($key);
		}
		return $value;
	}

	/**
	 * @param array<array-key, mixed> $entry
	 * @throws ValidationException
	 */
	private function optionalString(array $entry, string $key): ?string {
		$value = $entry[$key] ?? null;
		if ($value !== null && !is_string($value)) {
			throw $this->invalid($key);
		}
		return $value;
	}

	/**
	 * The value for an error message, empty if it is not a text.
	 *
	 * @param array<array-key, mixed> $entry
	 */
	private function text(array $entry, string $key): string {
		/** @var mixed $value */
		$value = $entry[$key] ?? null;
		return is_string($value) ? $value : '';
	}

	private function invalid(string $key): ValidationException {
		return new ValidationException($this->l10n->t('The value of "%s" is missing or invalid.', [$key]));
	}
}
