<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Adrian Dymorz <dev@adrian.dymorz.ch>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Laudatio\Controller;

use OCA\Laudatio\Db\Group;
use OCA\Laudatio\Exception\ConflictException;
use OCA\Laudatio\ResponseDefinitions;
use OCA\Laudatio\Service\AccessService;
use OCA\Laudatio\Service\GroupService;
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
 * @psalm-import-type LaudatioGroup from ResponseDefinitions
 * @psalm-import-type LaudatioError from ResponseDefinitions
 */
class GroupController extends BaseOCSController {
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
	 * List the groups visible to the current user with their functions
	 *
	 * @return DataResponse<Http::STATUS_OK, list<LaudatioGroup>, array{}>
	 * @throws OCSForbiddenException No access to the app
	 *
	 * 200: Groups returned
	 */
	#[NoAdminRequired]
	#[ApiRoute(verb: 'GET', url: '/api/v1/groups')]
	public function index(): DataResponse {
		$userId = $this->currentUserId();
		$groups = $this->call(function () use ($userId): array {
			$this->access->requireAccess($userId);
			return $this->groups->findAll($this->access->getVisibleGroupIds($userId));
		});
		$groupIds = array_map(static fn (Group $group): int => $group->getId(), $groups);
		$interested = $this->groups->getInterested($groupIds);
		$functions = $this->groups->getFunctions($groupIds);
		return new DataResponse(array_map(
			fn (Group $group): array => $this->presenter->group($group, $interested[$group->getId()] ?? [], $functions, $userId),
			$groups,
		));
	}

	/**
	 * Create a group
	 *
	 * @param string $name Name of the group
	 * @param string $responsible User ID of the responsible person
	 * @param list<string> $interested User IDs of the interested persons
	 * @param string|null $jubileeYears Comma separated jubilee years, null to use the global setting
	 * @return DataResponse<Http::STATUS_CREATED, LaudatioGroup, array{}>
	 * @throws OCSBadRequestException Invalid input
	 * @throws OCSForbiddenException Not a manager
	 *
	 * 201: Group created
	 */
	#[NoAdminRequired]
	#[ApiRoute(verb: 'POST', url: '/api/v1/groups')]
	public function create(string $name, string $responsible, array $interested = [], ?string $jubileeYears = null): DataResponse {
		$userId = $this->currentUserId();
		$group = $this->call(function () use ($userId, $name, $responsible, $interested, $jubileeYears): Group {
			$this->access->requireManager($userId);
			return $this->groups->create($userId, $name, $responsible, $interested, $jubileeYears);
		});
		return new DataResponse($this->present($group, $userId), Http::STATUS_CREATED);
	}

	/**
	 * Update a group
	 *
	 * @param int $id ID of the group
	 * @param string $name Name of the group
	 * @param string $responsible User ID of the responsible person
	 * @param list<string> $interested User IDs of the interested persons
	 * @param string|null $jubileeYears Comma separated jubilee years, null to use the global setting
	 * @return DataResponse<Http::STATUS_OK, LaudatioGroup, array{}>
	 * @throws OCSBadRequestException Invalid input
	 * @throws OCSForbiddenException Not a manager
	 * @throws OCSNotFoundException Group not found
	 *
	 * 200: Group updated
	 */
	#[NoAdminRequired]
	#[ApiRoute(verb: 'PUT', url: '/api/v1/groups/{id}', requirements: ['id' => '\d+'])]
	public function update(int $id, string $name, string $responsible, array $interested = [], ?string $jubileeYears = null): DataResponse {
		$userId = $this->currentUserId();
		$group = $this->call(function () use ($userId, $id, $name, $responsible, $interested, $jubileeYears): Group {
			$this->access->requireManager($userId);
			return $this->groups->update($userId, $id, $name, $responsible, $interested, $jubileeYears);
		});
		return new DataResponse($this->present($group, $userId));
	}

	/**
	 * Delete a group without functions
	 *
	 * @param int $id ID of the group
	 * @return DataResponse<Http::STATUS_OK, list<empty>, array{}>|DataResponse<Http::STATUS_CONFLICT, LaudatioError, array{}>
	 * @throws OCSForbiddenException Not a manager
	 * @throws OCSNotFoundException Group not found
	 *
	 * 200: Group deleted
	 * 409: The group still has functions
	 */
	#[NoAdminRequired]
	#[ApiRoute(verb: 'DELETE', url: '/api/v1/groups/{id}', requirements: ['id' => '\d+'])]
	public function destroy(int $id): DataResponse {
		$userId = $this->currentUserId();
		try {
			$this->call(function () use ($userId, $id): void {
				$this->access->requireManager($userId);
				$this->groups->delete($userId, $id);
			});
		} catch (ConflictException $e) {
			return $this->conflict($e);
		}
		return new DataResponse([]);
	}

	/**
	 * @return LaudatioGroup
	 */
	private function present(Group $group, string $userId): array {
		$this->presenter->reset();
		$id = $group->getId();
		return $this->presenter->group($group, $this->groups->getInterested([$id])[$id] ?? [], $this->groups->getFunctions([$id]), $userId);
	}
}
