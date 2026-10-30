<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Adrian Dymorz <dev@adrian.dymorz.ch>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Laudatio\Listener;

use OCA\Laudatio\AppInfo\Application;
use OCA\Laudatio\Service\AccessService;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;
use OCP\INavigationManager;
use OCP\IURLGenerator;
use OCP\IUserSession;
use OCP\L10N\IFactory;
use OCP\Navigation\Events\LoadAdditionalEntriesEvent;

/**
 * Adds the navigation entry only for users with access to the app. The event
 * is dispatched once per request when the navigation is built, so the access
 * check does not run on other requests (DAV, OCS, …).
 *
 * @template-implements IEventListener<LoadAdditionalEntriesEvent>
 */
class NavigationListener implements IEventListener {
	public function __construct(
		private IUserSession $userSession,
		private AccessService $access,
		private INavigationManager $navigationManager,
		private IURLGenerator $urlGenerator,
		private IFactory $l10nFactory,
	) {
	}

	#[\Override]
	public function handle(Event $event): void {
		if (!$event instanceof LoadAdditionalEntriesEvent) {
			return;
		}
		$user = $this->userSession->getUser();
		if ($user === null || !$this->access->hasAccess($user->getUID())) {
			return;
		}

		$this->navigationManager->add([
			'id' => Application::APP_ID,
			'order' => 50,
			'href' => $this->urlGenerator->linkToRoute(Application::APP_ID . '.page.index'),
			'icon' => $this->urlGenerator->imagePath(Application::APP_ID, 'app.svg'),
			'name' => $this->l10nFactory->get(Application::APP_ID)->t('Laudatio'),
			'app' => Application::APP_ID,
			'type' => 'link',
		]);
	}
}
