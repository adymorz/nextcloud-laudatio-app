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
use OCA\Laudatio\Service\ExportService;
use OCA\Laudatio\Service\ImportService;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\ApiRoute;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\OCS\OCSBadRequestException;
use OCP\AppFramework\OCS\OCSForbiddenException;
use OCP\IRequest;
use OCP\IUserSession;

/**
 * @psalm-import-type LaudatioExport from ResponseDefinitions
 * @psalm-import-type LaudatioImportResult from ResponseDefinitions
 * @psalm-import-type LaudatioError from ResponseDefinitions
 */
class ImportExportController extends BaseOCSController {
	public function __construct(
		IRequest $request,
		IUserSession $userSession,
		AccessService $access,
		private ExportService $exportService,
		private ImportService $importService,
	) {
		parent::__construct($request, $userSession, $access);
	}

	/**
	 * Export all groups, functions, persons and terms, without references to Nextcloud users
	 *
	 * @return DataResponse<Http::STATUS_OK, LaudatioExport, array{}>
	 * @throws OCSForbiddenException Not a manager
	 *
	 * 200: Export returned
	 */
	#[NoAdminRequired]
	#[ApiRoute(verb: 'GET', url: '/api/v1/export')]
	public function export(): DataResponse {
		$userId = $this->currentUserId();
		$export = $this->call(function () use ($userId): array {
			$this->access->requireManager($userId);
			return $this->exportService->export($userId);
		});
		return new DataResponse($export);
	}

	/**
	 * Import an export file into an empty app
	 *
	 * The groups have no responsible or interested users afterwards.
	 *
	 * @param array<string, mixed> $data Content of the export file
	 * @return DataResponse<Http::STATUS_CREATED, LaudatioImportResult, array{}>|DataResponse<Http::STATUS_CONFLICT, LaudatioError, array{}>
	 * @throws OCSBadRequestException Invalid file
	 * @throws OCSForbiddenException Not a manager
	 *
	 * 201: Data imported
	 * 409: The app already contains data
	 */
	#[NoAdminRequired]
	#[ApiRoute(verb: 'POST', url: '/api/v1/import')]
	public function import(array $data): DataResponse {
		$userId = $this->currentUserId();
		try {
			$result = $this->call(function () use ($userId, $data): array {
				$this->access->requireManager($userId);
				return $this->importService->import($userId, $data);
			});
		} catch (ConflictException $e) {
			return $this->conflict($e);
		}
		return new DataResponse($result, Http::STATUS_CREATED);
	}
}
