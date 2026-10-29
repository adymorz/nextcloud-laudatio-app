<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Adrian Dymorz <dev@adrian.dymorz.ch>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Appreciation\Command;

use DateTimeImmutable;
use OCA\Appreciation\Service\Clock;
use OCA\Appreciation\Service\Dates;
use OCA\Appreciation\Service\GroupService;
use OCA\Appreciation\Service\PersonService;
use OCP\IUserManager;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Seeds demo data for development: two groups with the DDEV test users as
 * responsible and interested persons, and persons whose birthdays and
 * jubilees fall within the next weeks (relative to today).
 */
class DemoDataCommand extends Command {
	private const BOARD = 'Demo: Board';
	private const VOLUNTEERS = 'Demo: Volunteers';

	public function __construct(
		private GroupService $groups,
		private PersonService $persons,
		private IUserManager $userManager,
		private Clock $clock,
	) {
		parent::__construct();
	}

	#[\Override]
	protected function configure(): void {
		$this->setName('appreciation:demo-data')
			->setDescription('Create demo groups, functions and persons (for development)')
			->setHelp('Requires the users "alice" and "bob". Alice is responsible for "Demo: Board" and interested in "Demo: Volunteers", Bob the other way round.')
			->addOption('force', null, InputOption::VALUE_NONE, 'Create the demo data even if demo groups already exist');
	}

	#[\Override]
	protected function execute(InputInterface $input, OutputInterface $output): int {
		foreach (['alice', 'bob'] as $userId) {
			if (!$this->userManager->userExists($userId)) {
				$output->writeln('<error>The user "' . $userId . '" does not exist. It is created by the DDEV setup.</error>');
				return self::FAILURE;
			}
		}
		foreach ($this->groups->findAll() as $group) {
			if (in_array($group->getName(), [self::BOARD, self::VOLUNTEERS], true) && $input->getOption('force') !== true) {
				$output->writeln('<comment>Demo data already exists, use --force to add it again.</comment>');
				return self::SUCCESS;
			}
		}

		$today = $this->clock->today();
		$board = $this->groups->create(null, self::BOARD, 'alice', ['bob'], null);
		$volunteers = $this->groups->create(null, self::VOLUNTEERS, 'bob', ['alice'], '1, 3, 5, 10');
		$president = $this->groups->createFunction(null, $board->getId(), 'President');
		$treasurer = $this->groups->createFunction(null, $board->getId(), 'Treasurer');
		$helper = $this->groups->createFunction(null, $volunteers->getId(), 'Event helper');

		// 40th birthday in 3 days, 10th anniversary as president in 10 days
		$anna = $this->persons->create(null, 'Anna', 'Muster', $this->ago($today, 3, 40), 'Prefers flowers over chocolate.');
		$this->persons->addSlice(null, $anna->getId(), $president->getId(), $this->ago($today, 10, 10), null);

		// Re-elected treasurer: two back-to-back slices count as one period, 5th anniversary in 20 days
		$beat = $this->persons->create(null, 'Beat', 'Beispiel', $this->ago($today, 30, 55), null);
		$reElection = Dates::anniversary(Dates::addDays($today, 20), -3);
		$this->persons->addSlice(null, $beat->getId(), $treasurer->getId(), $this->ago($today, 20, 5), Dates::format(Dates::addDays($reElection, -1)));
		$this->persons->addSlice(null, $beat->getId(), $treasurer->getId(), Dates::format($reElection), null);

		// 28th birthday in 5 days, volunteer for 3 years in 45 days
		$claudia = $this->persons->create(null, 'Claudia', 'Test', $this->ago($today, 5, 28), null);
		$this->persons->addSlice(null, $claudia->getId(), $helper->getId(), $this->ago($today, 45, 3), null);

		// Former treasurer: no events, only visible to managers
		$dario = $this->persons->create(null, 'Dario', 'Former', $this->ago($today, 8, 61), null);
		$this->persons->addSlice(null, $dario->getId(), $treasurer->getId(), '2015-01-01', Dates::format(Dates::addDays($today, -400)));

		// Birthday in two months, outside the default lead time
		$eva = $this->persons->create(null, 'Eva', 'Zukunft', $this->ago($today, 60, 33), null);
		$this->persons->addSlice(null, $eva->getId(), $helper->getId(), $this->ago($today, 100, 1), null);

		$output->writeln('Created 2 groups, 3 functions and 5 persons.');
		return self::SUCCESS;
	}

	/**
	 * The date $years years before the day that is $inDays days from today.
	 */
	private function ago(DateTimeImmutable $today, int $inDays, int $years): string {
		return Dates::format(Dates::anniversary(Dates::addDays($today, $inDays), -$years));
	}
}
