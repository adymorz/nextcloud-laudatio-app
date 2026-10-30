<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Adrian Dymorz <dev@adrian.dymorz.ch>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Laudatio\AppInfo;

use OCA\Laudatio\Calendar\CalendarProvider;
use OCA\Laudatio\Listener\NavigationListener;
use OCA\Laudatio\Listener\UserDeletedListener;
use OCA\Laudatio\Notification\Notifier;
use OCA\Laudatio\Settings\AdminForm;
use OCP\AppFramework\App;
use OCP\AppFramework\Bootstrap\IBootContext;
use OCP\AppFramework\Bootstrap\IBootstrap;
use OCP\AppFramework\Bootstrap\IRegistrationContext;
use OCP\Navigation\Events\LoadAdditionalEntriesEvent;
use OCP\User\Events\UserDeletedEvent;

class Application extends App implements IBootstrap {
	public const APP_ID = 'laudatio';

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
		$context->registerEventListener(LoadAdditionalEntriesEvent::class, NavigationListener::class);
	}

	#[\Override]
	public function boot(IBootContext $context): void {
	}
}
