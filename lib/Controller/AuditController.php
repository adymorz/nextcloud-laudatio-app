<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Adrian Dymorz <dev@adrian.dymorz.ch>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Appreciation\Controller;

use OCA\Appreciation\ResponseDefinitions;
use OCA\Appreciation\Service\AccessService;
use OCA\Appreciation\Service\AuditService;
use OCA\Appreciation\Service\Presenter;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\ApiRoute;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\OCS\OCSForbiddenException;
use OCP\IRequest;
use OCP\IUserSession;

/**
 * @psalm-import-type AppreciationAuditPage from ResponseDefinitions
 */
class AuditController extends BaseOCSController {
	public function __construct(
		IRequest $request,
		IUserSession $userSession,
		AccessService $access,
		private AuditService $audit,
		private Presenter $presenter,
	) {
		parent::__construct($request, $userSession, $access);
	}

	/**
	 * Get the audit log, newest first
	 *
	 * @param int $limit Number of entries, 1 to 200
	 * @param int $offset Number of entries to skip
	 * @return DataResponse<Http::STATUS_OK, AppreciationAuditPage, array{}>
	 * @throws OCSForbiddenException Not a manager
	 *
	 * 200: Audit log entries returned
	 */
	#[NoAdminRequired]
	#[ApiRoute(verb: 'GET', url: '/api/v1/audit')]
	public function index(int $limit = 50, int $offset = 0): DataResponse {
		$userId = $this->currentUserId();
		$this->call(fn () => $this->access->requireManager($userId));
		$entries = $this->audit->findPage(max(1, min(200, $limit)), max(0, $offset));
		return new DataResponse([
			'entries' => array_map($this->presenter->auditEntry(...), $entries),
			'total' => $this->audit->count(),
		]);
	}
}
