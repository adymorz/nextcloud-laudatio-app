<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Adrian Dymorz <dev@adrian.dymorz.ch>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Laudatio\Command;

use OCA\Laudatio\Service\SyncService;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class SyncCommand extends Command {
	public function __construct(
		private SyncService $syncService,
	) {
		parent::__construct();
	}

	#[\Override]
	protected function configure(): void {
		$this->setName('laudatio:sync')
			->setDescription('Generate upcoming birthday and jubilee events and send due notifications now')
			->setHelp('Does the same as the hourly background job.');
	}

	#[\Override]
	protected function execute(InputInterface $input, OutputInterface $output): int {
		$result = $this->syncService->run();
		$output->writeln(sprintf(
			'Events: %d inserted, %d updated, %d deleted. Notifications sent: %d',
			$result['inserted'],
			$result['updated'],
			$result['deleted'],
			$result['notified'],
		));
		return self::SUCCESS;
	}
}
