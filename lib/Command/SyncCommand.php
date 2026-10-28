<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Adrian Dymorz <dev@adrian.dymorz.ch>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Appreciation\Command;

use OCA\Appreciation\Service\SyncService;
use OCP\Console\Attribute\AsCommand;
use OCP\Console\ExitCode;
use OCP\Console\IOutput;

#[AsCommand(
	name: 'appreciation:sync',
	description: 'Generate upcoming birthday and jubilee events and send due notifications now',
	help: 'Does the same as the hourly background job.',
)]
class SyncCommand {
	public function __construct(
		private SyncService $syncService,
	) {
	}

	public function __invoke(IOutput $output): ExitCode {
		$result = $this->syncService->run();
		$output->writeln(sprintf(
			'Events: %d inserted, %d updated, %d deleted. Notifications sent: %d',
			$result['inserted'],
			$result['updated'],
			$result['deleted'],
			$result['notified'],
		));
		return ExitCode::Success;
	}
}
