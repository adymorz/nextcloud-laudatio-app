<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Adrian Dymorz <dev@adrian.dymorz.ch>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Laudatio\Controller;

use OCA\Laudatio\AppInfo\Application;
use OCA\Laudatio\Service\AccessService;
use OCA\Laudatio\Service\Presenter;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\FrontpageRoute;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\Attribute\NoCSRFRequired;
use OCP\AppFramework\Http\Attribute\OpenAPI;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\AppFramework\Services\IInitialState;
use OCP\IL10N;
use OCP\IRequest;
use OCP\IUserSession;

class PageController extends Controller {
	public function __construct(
		IRequest $request,
		private IUserSession $userSession,
		private AccessService $access,
		private Presenter $presenter,
		private IInitialState $initialState,
		private IL10N $l10n,
	) {
		parent::__construct(Application::APP_ID, $request);
	}

	#[NoCSRFRequired]
	#[NoAdminRequired]
	#[OpenAPI(OpenAPI::SCOPE_IGNORE)]
	#[FrontpageRoute(verb: 'GET', url: '/')]
	public function index(): TemplateResponse {
		$userId = $this->userSession->getUser()?->getUID();
		if ($userId === null || !$this->access->hasAccess($userId)) {
			$response = new TemplateResponse('core', '403', ['message' => $this->l10n->t('You do not have access to this app.')], TemplateResponse::RENDER_AS_ERROR);
			$response->setStatus(Http::STATUS_FORBIDDEN);
			return $response;
		}

		$this->initialState->provideInitialState('me', $this->presenter->me($userId));
		return new TemplateResponse(Application::APP_ID, 'index');
	}

	/**
	 * Deep links of the frontend router (e.g. /apps/laudatio/persons/12).
	 * The path is resolved by the router in the browser.
	 *
	 * @psalm-suppress PossiblyUnusedParam
	 */
	#[NoCSRFRequired]
	#[NoAdminRequired]
	#[OpenAPI(OpenAPI::SCOPE_IGNORE)]
	#[FrontpageRoute(verb: 'GET', url: '/{path}', requirements: ['path' => '.+'])]
	public function catchAll(string $path): TemplateResponse {
		return $this->index();
	}
}
