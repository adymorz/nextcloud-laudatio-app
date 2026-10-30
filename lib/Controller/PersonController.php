<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Adrian Dymorz <dev@adrian.dymorz.ch>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Laudatio\Controller;

use OCA\Laudatio\Exception\NotFoundException;
use OCA\Laudatio\Model\PersonInput;
use OCA\Laudatio\ResponseDefinitions;
use OCA\Laudatio\Service\AccessService;
use OCA\Laudatio\Service\PersonService;
use OCA\Laudatio\Service\Presenter;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\ApiRoute;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\OCS\OCSBadRequestException;
use OCP\AppFramework\OCS\OCSForbiddenException;
use OCP\AppFramework\OCS\OCSNotFoundException;
use OCP\IL10N;
use OCP\IRequest;
use OCP\IUserSession;

/**
 * @psalm-import-type LaudatioPerson from ResponseDefinitions
 * @psalm-import-type LaudatioPersonDetail from ResponseDefinitions
 */
class PersonController extends BaseOCSController {
	public function __construct(
		IRequest $request,
		IUserSession $userSession,
		AccessService $access,
		private PersonService $persons,
		private Presenter $presenter,
		private IL10N $l10n,
	) {
		parent::__construct($request, $userSession, $access);
	}

	/**
	 * List the persons visible to the current user
	 *
	 * Managers see all persons, responsible and interested users the current
	 * members of their groups. Date of birth, contact data and notes are only
	 * included when the user may see them.
	 *
	 * @return DataResponse<Http::STATUS_OK, list<LaudatioPerson>, array{}>
	 * @throws OCSForbiddenException No access to the app
	 *
	 * 200: Persons returned
	 */
	#[NoAdminRequired]
	#[ApiRoute(verb: 'GET', url: '/api/v1/persons')]
	public function index(): DataResponse {
		$userId = $this->currentUserId();
		$persons = $this->call(function () use ($userId): array {
			$this->access->requireAccess($userId);
			return $this->persons->findAll($this->access->getVisiblePersonIds($userId));
		});
		$slices = $this->persons->getSlicesByPerson(array_map(static fn ($p): int => $p->getId(), $persons));
		return new DataResponse(array_map(
			fn ($person): array => $this->presenter->person($person, $slices[$person->getId()] ?? [], $userId),
			$persons,
		));
	}

	/**
	 * Get a person with the history of time slices
	 *
	 * @param int $id ID of the person
	 * @return DataResponse<Http::STATUS_OK, LaudatioPersonDetail, array{}>
	 * @throws OCSForbiddenException No access to the app
	 * @throws OCSNotFoundException Person not found or not visible
	 *
	 * 200: Person returned
	 */
	#[NoAdminRequired]
	#[ApiRoute(verb: 'GET', url: '/api/v1/persons/{id}', requirements: ['id' => '\d+'])]
	public function show(int $id): DataResponse {
		$userId = $this->currentUserId();
		$person = $this->call(function () use ($userId, $id) {
			$this->access->requireAccess($userId);
			if (!$this->access->canSeePerson($userId, $id)) {
				throw new NotFoundException($this->l10n->t('The person does not exist.'));
			}
			return $this->persons->get($id);
		});
		return new DataResponse($this->presenter->personDetail($person, $this->persons->getSlices($id), $userId));
	}

	/**
	 * Create a person
	 *
	 * @param string $firstName First name
	 * @param string $lastName Last name
	 * @param string $dateOfBirth Date of birth (YYYY-MM-DD)
	 * @param string|null $notes Free text notes
	 * @param string|null $street Street
	 * @param string|null $streetNumber House number
	 * @param string|null $postalCode ZIP code
	 * @param string|null $city City
	 * @param string|null $country Country
	 * @param string|null $mobilePhone Mobile phone number
	 * @param string|null $otherPhone Additional phone number
	 * @return DataResponse<Http::STATUS_CREATED, LaudatioPersonDetail, array{}>
	 * @throws OCSBadRequestException Invalid input
	 * @throws OCSForbiddenException Not a manager
	 *
	 * 201: Person created
	 */
	#[NoAdminRequired]
	#[ApiRoute(verb: 'POST', url: '/api/v1/persons')]
	public function create(
		string $firstName,
		string $lastName,
		string $dateOfBirth,
		?string $notes = null,
		?string $street = null,
		?string $streetNumber = null,
		?string $postalCode = null,
		?string $city = null,
		?string $country = null,
		?string $mobilePhone = null,
		?string $otherPhone = null,
	): DataResponse {
		$userId = $this->currentUserId();
		$input = new PersonInput($firstName, $lastName, $dateOfBirth, $notes, $street, $streetNumber, $postalCode, $city, $country, $mobilePhone, $otherPhone);
		$person = $this->call(function () use ($userId, $input) {
			$this->access->requireManager($userId);
			return $this->persons->create($userId, $input);
		});
		return new DataResponse($this->presenter->personDetail($person, [], $userId), Http::STATUS_CREATED);
	}

	/**
	 * Update a person
	 *
	 * All fields are replaced: optional fields that are left out are cleared.
	 *
	 * @param int $id ID of the person
	 * @param string $firstName First name
	 * @param string $lastName Last name
	 * @param string $dateOfBirth Date of birth (YYYY-MM-DD)
	 * @param string|null $notes Free text notes
	 * @param string|null $street Street
	 * @param string|null $streetNumber House number
	 * @param string|null $postalCode ZIP code
	 * @param string|null $city City
	 * @param string|null $country Country
	 * @param string|null $mobilePhone Mobile phone number
	 * @param string|null $otherPhone Additional phone number
	 * @return DataResponse<Http::STATUS_OK, LaudatioPersonDetail, array{}>
	 * @throws OCSBadRequestException Invalid input
	 * @throws OCSForbiddenException Not a manager
	 * @throws OCSNotFoundException Person not found
	 *
	 * 200: Person updated
	 */
	#[NoAdminRequired]
	#[ApiRoute(verb: 'PUT', url: '/api/v1/persons/{id}', requirements: ['id' => '\d+'])]
	public function update(
		int $id,
		string $firstName,
		string $lastName,
		string $dateOfBirth,
		?string $notes = null,
		?string $street = null,
		?string $streetNumber = null,
		?string $postalCode = null,
		?string $city = null,
		?string $country = null,
		?string $mobilePhone = null,
		?string $otherPhone = null,
	): DataResponse {
		$userId = $this->currentUserId();
		$input = new PersonInput($firstName, $lastName, $dateOfBirth, $notes, $street, $streetNumber, $postalCode, $city, $country, $mobilePhone, $otherPhone);
		$person = $this->call(function () use ($userId, $id, $input) {
			$this->access->requireManager($userId);
			return $this->persons->update($userId, $id, $input);
		});
		return new DataResponse($this->presenter->personDetail($person, $this->persons->getSlices($id), $userId));
	}

	/**
	 * Delete a person with all time slices and events
	 *
	 * @param int $id ID of the person
	 * @return DataResponse<Http::STATUS_OK, list<empty>, array{}>
	 * @throws OCSForbiddenException Not a manager
	 * @throws OCSNotFoundException Person not found
	 *
	 * 200: Person deleted
	 */
	#[NoAdminRequired]
	#[ApiRoute(verb: 'DELETE', url: '/api/v1/persons/{id}', requirements: ['id' => '\d+'])]
	public function destroy(int $id): DataResponse {
		$userId = $this->currentUserId();
		$this->call(function () use ($userId, $id): void {
			$this->access->requireManager($userId);
			$this->persons->delete($userId, $id);
		});
		return new DataResponse([]);
	}
}
