<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Adrian Dymorz <dev@adrian.dymorz.ch>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Laudatio\Controller;

use OCA\Laudatio\ResponseDefinitions;
use OCA\Laudatio\Service\AccessService;
use OCA\Laudatio\Service\Presenter;
use OCA\Laudatio\Service\SettingsService;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\ApiRoute;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\OCS\OCSForbiddenException;
use OCP\IRequest;
use OCP\IUserSession;

/**
 * @psalm-import-type LaudatioMe from ResponseDefinitions
 */
class MeController extends BaseOCSController {
	public function __construct(
		IRequest $request,
		IUserSession $userSession,
		AccessService $access,
		private Presenter $presenter,
		private SettingsService $settings,
	) {
		parent::__construct($request, $userSession, $access);
	}

	/**
	 * Get the permissions of the current user
	 *
	 * @return DataResponse<Http::STATUS_OK, LaudatioMe, array{}>
	 * @throws OCSForbiddenException No access to the app
	 *
	 * 200: Permissions returned
	 */
	#[NoAdminRequired]
	#[ApiRoute(verb: 'GET', url: '/api/v1/me')]
	public function show(): DataResponse {
		$userId = $this->currentUserId();
		$this->call(fn () => $this->access->requireAccess($userId));
		return new DataResponse($this->presenter->me($userId));
	}

	/**
	 * Change the personal settings of the current user
	 *
	 * @param bool $emailNotifications Whether reminders are also sent by email
	 * @return DataResponse<Http::STATUS_OK, LaudatioMe, array{}>
	 * @throws OCSForbiddenException No access to the app
	 *
	 * 200: Settings saved
	 */
	#[NoAdminRequired]
	#[ApiRoute(verb: 'PUT', url: '/api/v1/me/settings')]
	public function updateSettings(bool $emailNotifications): DataResponse {
		$userId = $this->currentUserId();
		$this->call(fn () => $this->access->requireAccess($userId));
		$this->settings->setEmailEnabledForUser($userId, $emailNotifications);
		return new DataResponse($this->presenter->me($userId));
	}
}
