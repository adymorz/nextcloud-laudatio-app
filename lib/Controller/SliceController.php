<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Adrian Dymorz <dev@adrian.dymorz.ch>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Laudatio\Controller;

use OCA\Laudatio\Exception\ConflictException;
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
use OCP\IRequest;
use OCP\IUserSession;

/**
 * Time slices of a person in a function.
 *
 * @psalm-import-type LaudatioSlice from ResponseDefinitions
 * @psalm-import-type LaudatioError from ResponseDefinitions
 */
class SliceController extends BaseOCSController {
	public function __construct(
		IRequest $request,
		IUserSession $userSession,
		AccessService $access,
		private PersonService $persons,
		private Presenter $presenter,
	) {
		parent::__construct($request, $userSession, $access);
	}

	/**
	 * Add a time slice to a person
	 *
	 * @param int $personId ID of the person
	 * @param int $functionId ID of the function
	 * @param string $startDate First day (YYYY-MM-DD)
	 * @param string|null $endDate Last day (YYYY-MM-DD), null while active
	 * @return DataResponse<Http::STATUS_CREATED, LaudatioSlice, array{}>|DataResponse<Http::STATUS_CONFLICT, LaudatioError, array{}>
	 * @throws OCSBadRequestException Invalid dates or function
	 * @throws OCSForbiddenException Not a manager
	 * @throws OCSNotFoundException Person not found
	 *
	 * 201: Time slice created
	 * 409: Overlaps another time slice of the person in the same function
	 */
	#[NoAdminRequired]
	#[ApiRoute(verb: 'POST', url: '/api/v1/persons/{personId}/slices', requirements: ['personId' => '\d+'])]
	public function create(int $personId, int $functionId, string $startDate, ?string $endDate = null): DataResponse {
		$userId = $this->currentUserId();
		try {
			$slice = $this->call(function () use ($userId, $personId, $functionId, $startDate, $endDate) {
				$this->access->requireManager($userId);
				return $this->persons->addSlice($userId, $personId, $functionId, $startDate, $endDate);
			});
		} catch (ConflictException $e) {
			return $this->conflict($e);
		}
		return new DataResponse($this->presenter->slice($slice), Http::STATUS_CREATED);
	}

	/**
	 * Update a time slice
	 *
	 * @param int $id ID of the time slice
	 * @param int $functionId ID of the function
	 * @param string $startDate First day (YYYY-MM-DD)
	 * @param string|null $endDate Last day (YYYY-MM-DD), null while active
	 * @return DataResponse<Http::STATUS_OK, LaudatioSlice, array{}>|DataResponse<Http::STATUS_CONFLICT, LaudatioError, array{}>
	 * @throws OCSBadRequestException Invalid dates or function
	 * @throws OCSForbiddenException Not a manager
	 * @throws OCSNotFoundException Time slice not found
	 *
	 * 200: Time slice updated
	 * 409: Overlaps another time slice of the person in the same function
	 */
	#[NoAdminRequired]
	#[ApiRoute(verb: 'PUT', url: '/api/v1/slices/{id}', requirements: ['id' => '\d+'])]
	public function update(int $id, int $functionId, string $startDate, ?string $endDate = null): DataResponse {
		$userId = $this->currentUserId();
		try {
			$slice = $this->call(function () use ($userId, $id, $functionId, $startDate, $endDate) {
				$this->access->requireManager($userId);
				return $this->persons->updateSlice($userId, $id, $functionId, $startDate, $endDate);
			});
		} catch (ConflictException $e) {
			return $this->conflict($e);
		}
		return new DataResponse($this->presenter->slice($slice));
	}

	/**
	 * Delete a time slice
	 *
	 * @param int $id ID of the time slice
	 * @return DataResponse<Http::STATUS_OK, list<empty>, array{}>
	 * @throws OCSForbiddenException Not a manager
	 * @throws OCSNotFoundException Time slice not found
	 *
	 * 200: Time slice deleted
	 */
	#[NoAdminRequired]
	#[ApiRoute(verb: 'DELETE', url: '/api/v1/slices/{id}', requirements: ['id' => '\d+'])]
	public function destroy(int $id): DataResponse {
		$userId = $this->currentUserId();
		$this->call(function () use ($userId, $id): void {
			$this->access->requireManager($userId);
			$this->persons->deleteSlice($userId, $id);
		});
		return new DataResponse([]);
	}
}
