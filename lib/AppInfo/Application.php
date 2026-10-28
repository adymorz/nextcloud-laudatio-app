<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Adrian Dymorz <dev@adrian.dymorz.ch>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Appreciation\AppInfo;

use OCA\Appreciation\Calendar\CalendarProvider;
use OCA\Appreciation\Listener\UserDeletedListener;
use OCA\Appreciation\Notification\Notifier;
use OCA\Appreciation\Service\AccessService;
use OCA\Appreciation\Settings\AdminForm;
use OCP\AppFramework\App;
use OCP\AppFramework\Bootstrap\IBootContext;
use OCP\AppFramework\Bootstrap\IBootstrap;
use OCP\AppFramework\Bootstrap\IRegistrationContext;
use OCP\INavigationManager;
use OCP\IURLGenerator;
use OCP\IUserSession;
use OCP\L10N\IFactory;
use OCP\User\Events\UserDeletedEvent;
use Psr\Container\ContainerInterface;

class Application extends App implements IBootstrap {
	public const string APP_ID = 'appreciation';

	/** @psalm-suppress PossiblyUnusedMethod */
	public function __construct() {
		parent::__construct(self::APP_ID);
	}

	#[\Override]
	public function register(IRegistrationContext $context): void {
		$context->registerCalendarProvider(CalendarProvider::class);
		$context->registerNotifierService(Notifier::class);
		$context->registerDeclarativeSettings(AdminForm::class);
		$context->registerEventListener(UserDeletedEvent::class, UserDeletedListener::class);
	}

	#[\Override]
	public function boot(IBootContext $context): void {
		$appContainer = $context->getAppContainer();
		$context->injectFn(static function (
			INavigationManager $navigationManager,
			IUserSession $userSession,
			IURLGenerator $urlGenerator,
			IFactory $l10nFactory,
		) use ($appContainer): void {
			// Evaluated lazily when the navigation is rendered; users without
			// access to the app do not get an entry.
			$navigationManager->add(static function () use ($appContainer, $userSession, $urlGenerator, $l10nFactory): ?array {
				$user = $userSession->getUser();
				if ($user === null || !self::accessService($appContainer)->hasAccess($user->getUID())) {
					return null;
				}
				return [
					'id' => self::APP_ID,
					'order' => 50,
					'href' => $urlGenerator->linkToRoute(self::APP_ID . '.page.index'),
					'icon' => $urlGenerator->imagePath(self::APP_ID, 'app.svg'),
					'name' => $l10nFactory->get(self::APP_ID)->t('Appreciation'),
					'app' => self::APP_ID,
					'type' => 'link',
				];
			});
		});
	}

	private static function accessService(ContainerInterface $container): AccessService {
		$service = $container->get(AccessService::class);
		if (!$service instanceof AccessService) {
			throw new \LogicException('AccessService could not be resolved');
		}
		return $service;
	}
}
