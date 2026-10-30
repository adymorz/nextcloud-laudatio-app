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
	 * @throws ValidationException
	 */
	public function create(?string $actor, PersonInput $input): Person {
		$person = new Person();
		$this->apply($person, $input);
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
	 * @throws NotFoundException|ValidationException|ConflictException
	 */
	public function addSlice(?string $actor, int $personId, int $functionId, string $startDate, ?string $endDate): Slice {
		$this->get($personId);
		[$start, $end] = $this->parseRange($startDate, $endDate);
		$this->sliceValidator->validate($personId, $functionId, $start, $end);

		$slice = new Slice();
		$slice->setPersonId($personId);
		$slice->setFunctionId($functionId);
		$slice->setStartDate($start);
		$slice->setEndDate($end);
		$now = $this->clock->time();
		$slice->setCreatedAt($now);
		$slice->setUpdatedAt($now);

		$this->atomic(function () use ($actor, $slice): void {
			$this->sliceMapper->insert($slice);
			$this->audit->log($actor, AuditEntry::ACTION_CREATE, AuditService::ENTITY_SLICE, $slice->getId(), ['person_id', 'function_id', 'start_date', 'end_date']);
		}, $this->db);
		$this->afterChange($personId);
		return $slice;
	}

	/**
	 * @throws NotFoundException|ValidationException|ConflictException
	 */
	public function updateSlice(?string $actor, int $sliceId, int $functionId, string $startDate, ?string $endDate): Slice {
		$slice = $this->getSlice($sliceId);
		[$start, $end] = $this->parseRange($startDate, $endDate);
		$this->sliceValidator->validate($slice->getPersonId(), $functionId, $start, $end, $slice->getId());

		$changed = [];
		if ($slice->getFunctionId() !== $functionId) {
			$slice->setFunctionId($functionId);
			$changed[] = 'function_id';
		}
		if (Dates::compare($slice->getStartDate(), $start) !== 0) {
			$slice->setStartDate($start);
			$changed[] = 'start_date';
		}
		$oldEnd = $slice->getEndDate();
		if (($oldEnd === null) !== ($end === null) || ($oldEnd !== null && $end !== null && Dates::compare($oldEnd, $end) !== 0)) {
			$slice->setEndDate($end);
			$changed[] = 'end_date';
		}
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

		$notes = $input->notes === null ? null : trim($input->notes);
		if ($notes === '') {
			$notes = null;
		}
		if ($notes !== null && mb_strlen($notes) > self::MAX_NOTES_LENGTH) {
			throw new ValidationException($this->l10n->t('Notes must not be longer than %d characters.', [self::MAX_NOTES_LENGTH]));
		}

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
	private function fieldNames(Person $person): array {
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
