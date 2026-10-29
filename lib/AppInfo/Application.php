<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Adrian Dymorz <dev@adrian.dymorz.ch>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Appreciation\AppInfo;

use OCA\Appreciation\Calendar\CalendarProvider;
use OCA\Appreciation\Listener\NavigationListener;
use OCA\Appreciation\Listener\UserDeletedListener;
use OCA\Appreciation\Notification\Notifier;
use OCA\Appreciation\Settings\AdminForm;
use OCP\AppFramework\App;
use OCP\AppFramework\Bootstrap\IBootContext;
use OCP\AppFramework\Bootstrap\IBootstrap;
use OCP\AppFramework\Bootstrap\IRegistrationContext;
use OCP\Navigation\Events\LoadAdditionalEntriesEvent;
use OCP\User\Events\UserDeletedEvent;

class Application extends App implements IBootstrap {
	public const APP_ID = 'appreciation';

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
