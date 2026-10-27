<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Adrian Dymorz <dev@adrian.dymorz.ch>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Appreciation\Controller;

use OCA\Appreciation\Db\OrgFunction;
use OCA\Appreciation\Exception\ConflictException;
use OCA\Appreciation\ResponseDefinitions;
use OCA\Appreciation\Service\AccessService;
use OCA\Appreciation\Service\GroupService;
use OCA\Appreciation\Service\Presenter;
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
 * Functions (roles) within a group.
 *
 * @psalm-import-type AppreciationFunction from ResponseDefinitions
 * @psalm-import-type AppreciationError from ResponseDefinitions
 */
class FunctionController extends BaseOCSController {
	public function __construct(
		IRequest $request,
		IUserSession $userSession,
		AccessService $access,
		private GroupService $groups,
		private Presenter $presenter,
	) {
		parent::__construct($request, $userSession, $access);
	}

	/**
	 * Add a function to a group
	 *
	 * @param int $groupId ID of the group
	 * @param string $name Name of the function, e.g. "Treasurer"
	 * @return DataResponse<Http::STATUS_CREATED, AppreciationFunction, array{}>
	 * @throws OCSBadRequestException Invalid name
	 * @throws OCSForbiddenException Not a manager
	 * @throws OCSNotFoundException Group not found
	 *
	 * 201: Function created
	 */
	#[NoAdminRequired]
	#[ApiRoute(verb: 'POST', url: '/api/v1/groups/{groupId}/functions', requirements: ['groupId' => '\d+'])]
	public function create(int $groupId, string $name): DataResponse {
		$userId = $this->currentUserId();
		$function = $this->call(function () use ($userId, $groupId, $name): OrgFunction {
			$this->access->requireManager($userId);
			return $this->groups->createFunction($userId, $groupId, $name);
		});
		return new DataResponse($this->presenter->function($function), Http::STATUS_CREATED);
	}

	/**
	 * Rename a function
	 *
	 * @param int $id ID of the function
	 * @param string $name Name of the function
	 * @return DataResponse<Http::STATUS_OK, AppreciationFunction, array{}>
	 * @throws OCSBadRequestException Invalid name
	 * @throws OCSForbiddenException Not a manager
	 * @throws OCSNotFoundException Function not found
	 *
	 * 200: Function updated
	 */
	#[NoAdminRequired]
	#[ApiRoute(verb: 'PUT', url: '/api/v1/functions/{id}', requirements: ['id' => '\d+'])]
	public function update(int $id, string $name): DataResponse {
		$userId = $this->currentUserId();
		$function = $this->call(function () use ($userId, $id, $name): OrgFunction {
			$this->access->requireManager($userId);
			return $this->groups->updateFunction($userId, $id, $name);
		});
		return new DataResponse($this->presenter->function($function));
	}

	/**
	 * Delete a function without time slices
	 *
	 * @param int $id ID of the function
	 * @return DataResponse<Http::STATUS_OK, list<empty>, array{}>|DataResponse<Http::STATUS_CONFLICT, AppreciationError, array{}>
	 * @throws OCSForbiddenException Not a manager
	 * @throws OCSNotFoundException Function not found
	 *
	 * 200: Function deleted
	 * 409: The function still has time slices
	 */
	#[NoAdminRequired]
	#[ApiRoute(verb: 'DELETE', url: '/api/v1/functions/{id}', requirements: ['id' => '\d+'])]
	public function destroy(int $id): DataResponse {
		$userId = $this->currentUserId();
		try {
			$this->call(function () use ($userId, $id): void {
				$this->access->requireManager($userId);
				$this->groups->deleteFunction($userId, $id);
			});
		} catch (ConflictException $e) {
			return $this->conflict($e);
		}
		return new DataResponse([]);
	}
}
