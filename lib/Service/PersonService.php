<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Adrian Dymorz <dev@adrian.dymorz.ch>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Laudatio\Service;

use DateTimeImmutable;
use OCA\Laudatio\Db\AuditEntry;
use OCA\Laudatio\Db\Person;
use OCA\Laudatio\Db\PersonMapper;
use OCA\Laudatio\Db\Slice;
use OCA\Laudatio\Db\SliceMapper;
use OCA\Laudatio\Exception\ConflictException;
use OCA\Laudatio\Exception\NotFoundException;
use OCA\Laudatio\Exception\ValidationException;
use OCA\Laudatio\Model\PersonInput;
use OCA\Laudatio\Model\SliceInput;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\TTransactional;
use OCP\IDBConnection;
use OCP\IL10N;

/**
 * Persons and their terms. Every change is written to the audit log and
 * the person's events are synchronized afterwards.
 */
class PersonService {
	use TTransactional;

	public const MAX_NAME_LENGTH = 255;
	public const MAX_NOTES_LENGTH = 10000;
	public const MAX_TEXT_LENGTH = 255;
	public const MAX_SHORT_LENGTH = 32;
	public const MAX_PHONE_LENGTH = 32;
	/** Earliest year of a date of birth or a term */
	public const MIN_YEAR = 1900;
	/** Latest year of a term, keeps typos like 0202 out of the database */
	public const MAX_YEAR = 2200;

	public function __construct(
		private PersonMapper $personMapper,
		private SliceMapper $sliceMapper,
		private SliceValidator $sliceValidator,
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
	 * @throws NotFoundException
	 */
	public function get(int $id): Person {
		try {
			return $this->personMapper->find($id);
		} catch (DoesNotExistException) {
			throw new NotFoundException($this->l10n->t('The person does not exist.'));
		}
	}

	/**
	 * @param list<int>|null $ids null for all persons
	 * @return list<Person>
	 */
	public function findAll(?array $ids = null): array {
		if ($ids === null) {
			return $this->personMapper->findAll();
		}
		return $ids === [] ? [] : $this->personMapper->findByIds($ids);
	}

	/**
	 * Validates the input and returns a new, unsaved person.
	 *
	 * @throws ValidationException
	 */
	public function build(PersonInput $input): Person {
		$person = new Person();
		$this->apply($person, $input);
		return $person;
	}

	/**
	 * @throws ValidationException
	 */
	public function create(?string $actor, PersonInput $input): Person {
		$person = $this->build($input);
		$now = $this->clock->time();
		$person->setCreatedAt($now);
		$person->setUpdatedAt($now);

		$this->atomic(function () use ($actor, $person): void {
			$this->personMapper->insert($person);
			$this->audit->log($actor, AuditEntry::ACTION_CREATE, AuditService::ENTITY_PERSON, $person->getId(), $this->fieldNames($person));
		}, $this->db);
		$this->afterChange($person->getId());
		return $person;
	}

	/**
	 * @throws NotFoundException|ValidationException
	 */
	public function update(?string $actor, int $id, PersonInput $input): Person {
		$person = $this->get($id);
		$this->apply($person, $input);
		$changed = $this->fieldNames($person);
		if ($changed === []) {
			return $person;
		}
		$person->setUpdatedAt($this->clock->time());

		$this->atomic(function () use ($actor, $person, $changed): void {
			$this->personMapper->update($person);
			$this->audit->log($actor, AuditEntry::ACTION_UPDATE, AuditService::ENTITY_PERSON, $person->getId(), $changed);
		}, $this->db);
		$this->afterChange($person->getId());
		return $person;
	}

	/**
	 * Deletes the person with all slices, events and notifications.
	 *
	 * @throws NotFoundException
	 */
	public function delete(?string $actor, int $id): void {
		$person = $this->get($id);
		$this->atomic(function () use ($actor, $person): void {
			$this->eventService->deleteAllForPerson($person->getId());
			$this->sliceMapper->deleteByPerson($person->getId());
			$this->personMapper->delete($person);
			$this->audit->log($actor, AuditEntry::ACTION_DELETE, AuditService::ENTITY_PERSON, $person->getId());
		}, $this->db);
		$this->recipients->reset();
		$this->access->clearCache();
	}

	/**
	 * @return list<Slice> ordered by start date
	 */
	public function getSlices(int $personId): array {
		return $this->sliceMapper->findByPerson($personId);
	}

	/**
	 * @param list<int> $personIds
	 * @return array<int, list<Slice>> slices by person ID
	 */
	public function getSlicesByPerson(array $personIds): array {
		$result = [];
		foreach ($personIds === [] ? [] : $this->sliceMapper->findByPersons($personIds) as $slice) {
			$result[$slice->getPersonId()][] = $slice;
		}
		return $result;
	}

	/**
	 * @throws NotFoundException
	 */
	public function getSlice(int $sliceId): Slice {
		try {
			return $this->sliceMapper->find($sliceId);
		} catch (DoesNotExistException) {
			throw new NotFoundException($this->l10n->t('The term does not exist.'));
		}
	}

	/**
	 * Validates the input, except for the rules that need the database, and
	 * returns a new, unsaved slice.
	 *
	 * @throws ValidationException
	 */
	public function buildSlice(SliceInput $input): Slice {
		[$start, $end] = $this->parseRange($input->startDate, $input->endDate);
		$this->sliceValidator->validateRange($start, $end);

		$slice = new Slice();
		$slice->setFunctionId($input->functionId);
		$slice->setStartDate($start);
		$slice->setEndDate($end);
		$this->applySliceTexts($slice, $input);
		return $slice;
	}

	/**
	 * @throws NotFoundException|ValidationException|ConflictException
	 */
	public function addSlice(?string $actor, int $personId, SliceInput $input): Slice {
		$this->get($personId);
		$slice = $this->buildSlice($input);
		$this->sliceValidator->validate($personId, $input->functionId, $slice->getStartDate(), $slice->getEndDate());

		$slice->setPersonId($personId);
		$now = $this->clock->time();
		$slice->setCreatedAt($now);
		$slice->setUpdatedAt($now);

		$this->atomic(function () use ($actor, $slice): void {
			$this->sliceMapper->insert($slice);
			$this->audit->log($actor, AuditEntry::ACTION_CREATE, AuditService::ENTITY_SLICE, $slice->getId(), $this->sliceFieldNames($slice));
		}, $this->db);
		$this->afterChange($personId);
		return $slice;
	}

	/**
	 * @throws NotFoundException|ValidationException|ConflictException
	 */
	public function updateSlice(?string $actor, int $sliceId, SliceInput $input): Slice {
		$slice = $this->getSlice($sliceId);
		[$start, $end] = $this->parseRange($input->startDate, $input->endDate);
		$this->sliceValidator->validate($slice->getPersonId(), $input->functionId, $start, $end, $slice->getId());

		// Entity setters ignore unchanged values, dates are objects and need a comparison
		$slice->setFunctionId($input->functionId);
		if (Dates::compare($slice->getStartDate(), $start) !== 0) {
			$slice->setStartDate($start);
		}
		$oldEnd = $slice->getEndDate();
		if (($oldEnd === null) !== ($end === null) || ($oldEnd !== null && $end !== null && Dates::compare($oldEnd, $end) !== 0)) {
			$slice->setEndDate($end);
		}
		$this->applySliceTexts($slice, $input);
		$changed = $this->sliceFieldNames($slice);
		if ($changed === []) {
			return $slice;
		}
		$slice->setUpdatedAt($this->clock->time());

		$this->atomic(function () use ($actor, $slice, $changed): void {
			$this->sliceMapper->update($slice);
			$this->audit->log($actor, AuditEntry::ACTION_UPDATE, AuditService::ENTITY_SLICE, $slice->getId(), $changed);
		}, $this->db);
		$this->afterChange($slice->getPersonId());
		return $slice;
	}

	/**
	 * @throws NotFoundException
	 */
	public function deleteSlice(?string $actor, int $sliceId): void {
		$slice = $this->getSlice($sliceId);
		$this->atomic(function () use ($actor, $slice): void {
			$this->sliceMapper->delete($slice);
			$this->audit->log($actor, AuditEntry::ACTION_DELETE, AuditService::ENTITY_SLICE, $slice->getId());
		}, $this->db);
		$this->afterChange($slice->getPersonId());
	}

	/**
	 * Validates the input and sets it on the person.
	 *
	 * @throws ValidationException
	 */
	private function apply(Person $person, PersonInput $input): void {
		$firstName = trim($input->firstName);
		$lastName = trim($input->lastName);
		if ($firstName === '' || $lastName === '') {
			throw new ValidationException($this->l10n->t('First and last name are required.'));
		}
		if (mb_strlen($firstName) > self::MAX_NAME_LENGTH || mb_strlen($lastName) > self::MAX_NAME_LENGTH) {
			throw new ValidationException($this->l10n->t('Names must not be longer than %d characters.', [self::MAX_NAME_LENGTH]));
		}

		$birth = Dates::parse($input->dateOfBirth);
		if ($birth === null) {
			throw new ValidationException($this->l10n->t('The date of birth must be a valid date (YYYY-MM-DD).'));
		}
		if ((int)$birth->format('Y') < self::MIN_YEAR || Dates::compare($birth, $this->clock->today()) > 0) {
			throw new ValidationException($this->l10n->t('The date of birth must be between 1900 and today.'));
		}

		$notes = $this->notes($input->notes);
		$street = $this->optionalText($input->street, self::MAX_TEXT_LENGTH, $this->l10n->t('Street'));
		$streetNumber = $this->optionalText($input->streetNumber, self::MAX_SHORT_LENGTH, $this->l10n->t('House number'));
		$postalCode = $this->optionalText($input->postalCode, self::MAX_SHORT_LENGTH, $this->l10n->t('ZIP code'));
		$city = $this->optionalText($input->city, self::MAX_TEXT_LENGTH, $this->l10n->t('City'));
		$country = $this->optionalText($input->country, self::MAX_TEXT_LENGTH, $this->l10n->t('Country'));
		$mobilePhone = $this->phone($input->mobilePhone, $this->l10n->t('Mobile phone'));
		$otherPhone = $this->phone($input->otherPhone, $this->l10n->t('Additional phone number'));

		// Entity setters ignore unchanged values, dates are objects and need a comparison
		$person->setFirstName($firstName);
		$person->setLastName($lastName);
		if (!$person->hasDateOfBirth() || Dates::compare($person->getDateOfBirth(), $birth) !== 0) {
			$person->setDateOfBirth($birth);
		}
		$person->setNotes($notes);
		$person->setStreet($street);
		$person->setStreetNumber($streetNumber);
		$person->setPostalCode($postalCode);
		$person->setCity($city);
		$person->setCountry($country);
		$person->setMobilePhone($mobilePhone);
		$person->setOtherPhone($otherPhone);
	}

	/**
	 * Validates the optional texts and sets them on the slice.
	 *
	 * @throws ValidationException
	 */
	private function applySliceTexts(Slice $slice, SliceInput $input): void {
		$startDateSource = $this->optionalText($input->startDateSource, self::MAX_TEXT_LENGTH, $this->l10n->t('Source of start date'));
		$endDateSource = $this->optionalText($input->endDateSource, self::MAX_TEXT_LENGTH, $this->l10n->t('Source of end date'));
		$notes = $this->notes($input->notes);

		$slice->setStartDateSource($startDateSource);
		$slice->setEndDateSource($endDateSource);
		$slice->setNotes($notes);
	}

	/**
	 * Trims notes, empty becomes null.
	 *
	 * @throws ValidationException when the notes are too long
	 */
	private function notes(?string $value): ?string {
		$value = $value === null ? '' : trim($value);
		if ($value === '') {
			return null;
		}
		if (mb_strlen($value) > self::MAX_NOTES_LENGTH) {
			throw new ValidationException($this->l10n->t('Notes must not be longer than %d characters.', [self::MAX_NOTES_LENGTH]));
		}
		return $value;
	}

	/**
	 * Trims an optional text, empty becomes null.
	 *
	 * @throws ValidationException when the text is too long
	 */
	private function optionalText(?string $value, int $maxLength, string $label): ?string {
		$value = $value === null ? '' : trim($value);
		if ($value === '') {
			return null;
		}
		if (mb_strlen($value) > $maxLength) {
			throw new ValidationException($this->l10n->t('%1$s must not be longer than %2$d characters.', [$label, $maxLength]));
		}
		return $value;
	}

	/**
	 * @throws ValidationException when the phone number contains other characters
	 */
	private function phone(?string $value, string $label): ?string {
		$value = $this->optionalText($value, self::MAX_PHONE_LENGTH, $label);
		if ($value !== null && (preg_match('#^[0-9+()/. -]+$#', $value) !== 1 || preg_match('/\d/', $value) !== 1)) {
			throw new ValidationException($this->l10n->t('%s may only contain digits, spaces and + ( ) / . -', [$label]));
		}
		return $value;
	}

	/**
	 * Names of the updated fields in database notation.
	 *
	 * @return list<string>
	 */
	public function fieldNames(Person $person): array {
		$map = [
			'firstName' => 'first_name',
			'lastName' => 'last_name',
			'dateOfBirth' => 'date_of_birth',
			'notes' => 'notes',
			'street' => 'street',
			'streetNumber' => 'street_number',
			'postalCode' => 'postal_code',
			'city' => 'city',
			'country' => 'country',
			'mobilePhone' => 'mobile_phone',
			'otherPhone' => 'other_phone',
		];
		$fields = [];
		foreach (array_keys($person->getUpdatedFields()) as $property) {
			if (isset($map[$property])) {
				$fields[] = $map[$property];
			}
		}
		return $fields;
	}

	/**
	 * Names of the updated fields in database notation.
	 *
	 * @return list<string>
	 */
	public function sliceFieldNames(Slice $slice): array {
		$map = [
			'personId' => 'person_id',
			'functionId' => 'function_id',
			'startDate' => 'start_date',
			'endDate' => 'end_date',
			'startDateSource' => 'start_date_source',
			'endDateSource' => 'end_date_source',
			'notes' => 'notes',
		];
		return array_values(array_intersect_key($map, $slice->getUpdatedFields()));
	}

	/**
	 * @return array{DateTimeImmutable, ?DateTimeImmutable}
	 * @throws ValidationException
	 */
	private function parseRange(string $startDate, ?string $endDate): array {
		$start = Dates::parse($startDate);
		$end = $endDate === null || $endDate === '' ? null : Dates::parse($endDate);
		if ($start === null || ($endDate !== null && $endDate !== '' && $end === null)) {
			throw new ValidationException($this->l10n->t('Start and end date must be valid dates (YYYY-MM-DD).'));
		}
		foreach ([$start, $end] as $date) {
			$year = $date === null ? self::MIN_YEAR : (int)$date->format('Y');
			if ($year < self::MIN_YEAR || $year > self::MAX_YEAR) {
				throw new ValidationException($this->l10n->t('Start and end date must be between %1$d and %2$d.', [self::MIN_YEAR, self::MAX_YEAR]));
			}
		}
		return [$start, $end];
	}

	private function afterChange(int $personId): void {
		$this->recipients->reset();
		$this->access->clearCache();
		$this->eventService->syncPerson($personId);
	}
}
