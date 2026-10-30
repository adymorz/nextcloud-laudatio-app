<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Adrian Dymorz <dev@adrian.dymorz.ch>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Laudatio\Controller;

use OCA\Laudatio\AppInfo\Application;
use OCA\Laudatio\Exception\ConflictException;
use OCA\Laudatio\Exception\ForbiddenException;
use OCA\Laudatio\Exception\NotFoundException;
use OCA\Laudatio\Exception\ValidationException;
use OCA\Laudatio\ResponseDefinitions;
use OCA\Laudatio\Service\AccessService;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\OCS\OCSBadRequestException;
use OCP\AppFramework\OCS\OCSForbiddenException;
use OCP\AppFramework\OCS\OCSNotFoundException;
use OCP\AppFramework\OCSController;
use OCP\IRequest;
use OCP\IUserSession;

/**
 * Common helpers of the OCS controllers: the current user and the mapping of
 * service errors to OCS errors (400, 403, 404) or a 409 response.
 *
 * @psalm-import-type LaudatioError from ResponseDefinitions
 */
abstract class BaseOCSController extends OCSController {
	public function __construct(
		IRequest $request,
		protected IUserSession $userSession,
		protected AccessService $access,
	) {
		parent::__construct(Application::APP_ID, $request);
	}

	/**
	 * @throws OCSForbiddenException
	 */
	protected function currentUserId(): string {
		$user = $this->userSession->getUser();
		if ($user === null) {
			throw new OCSForbiddenException();
		}
		return $user->getUID();
	}

	/**
	 * Runs the callback and converts service exceptions into OCS exceptions.
	 * A ConflictException is passed on, see conflict().
	 *
	 * @template T
	 * @param \Closure(): T $callback
	 * @return T
	 * @throws OCSBadRequestException
	 * @throws OCSForbiddenException
	 * @throws OCSNotFoundException
	 * @throws ConflictException
	 */
	protected function call(\Closure $callback): mixed {
		try {
			return $callback();
		} catch (ValidationException $e) {
			throw new OCSBadRequestException($e->getMessage(), $e);
		} catch (ForbiddenException $e) {
			throw new OCSForbiddenException($e->getMessage(), $e);
		} catch (NotFoundException $e) {
			throw new OCSNotFoundException($e->getMessage(), $e);
		}
	}

	/**
	 * @return DataResponse<Http::STATUS_CONFLICT, LaudatioError, array{}>
	 */
	protected function conflict(ConflictException $e): DataResponse {
		return new DataResponse(['message' => $e->getMessage()], Http::STATUS_CONFLICT);
	}
}
