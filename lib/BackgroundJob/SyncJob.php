<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Adrian Dymorz <dev@adrian.dymorz.ch>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Appreciation\BackgroundJob;

use OCA\Appreciation\Service\SyncService;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\BackgroundJob\TimedJob;

/**
 * Hourly: generates upcoming events and sends due notifications. Running
 * hourly instead of daily lets notifications follow changes quickly; every
 * step is idempotent.
 */
class SyncJob extends TimedJob {
	public function __construct(
		ITimeFactory $time,
		private SyncService $syncService,
	) {
		parent::__construct($time);
		$this->setInterval(3600);
		$this->setTimeSensitivity(self::TIME_INSENSITIVE);
	}

	#[\Override]
	protected function run(mixed $argument): void {
		$this->syncService->run();
	}
}
