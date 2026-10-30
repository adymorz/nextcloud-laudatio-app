<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Adrian Dymorz <dev@adrian.dymorz.ch>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Laudatio\Command;

use DateTimeImmutable;
use OCA\Laudatio\Model\PersonInput;
use OCA\Laudatio\Model\SliceInput;
use OCA\Laudatio\Service\Clock;
use OCA\Laudatio\Service\Dates;
use OCA\Laudatio\Service\GroupService;
use OCA\Laudatio\Service\PersonService;
use OCP\IUserManager;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Seeds demo data on a test instance: two groups with the DDEV test users as
 * responsible and interested persons, and persons whose birthdays and
 * anniversaries fall within the next weeks (relative to today). Refuses to mix
 * the demo data into existing data unless forced.
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
		$this->setName('laudatio:demo-data')
			->setDescription('Create demo groups, functions and persons on a test instance')
			->setHelp('Requires the users "alice" and "bob". Alice is responsible for "Demo: Board" and interested in "Demo: Volunteers", Bob the other way round.')
			->addOption('force', null, InputOption::VALUE_NONE, 'Add the demo data even if Laudatio already contains data');
	}

	#[\Override]
	protected function execute(InputInterface $input, OutputInterface $output): int {
		foreach (['alice', 'bob'] as $userId) {
			if (!$this->userManager->userExists($userId)) {
				$output->writeln('<error>The user "' . $userId . '" does not exist. It is created by the DDEV setup.</error>');
				return self::FAILURE;
			}
		}
		if (($this->groups->findAll() !== [] || $this->persons->findAll() !== []) && $input->getOption('force') !== true) {
			$output->writeln('<error>Laudatio already contains data. The demo data is meant for an empty test instance, use --force to add it anyway.</error>');
			return self::FAILURE;
		}

		$today = $this->clock->today();
		$board = $this->groups->create(null, self::BOARD, 'alice', ['bob'], null);
		$volunteers = $this->groups->create(null, self::VOLUNTEERS, 'bob', ['alice'], '1, 3, 5, 10');
		$president = $this->groups->createFunction(null, $board->getId(), 'President');
		$treasurer = $this->groups->createFunction(null, $board->getId(), 'Treasurer');
		$helper = $this->groups->createFunction(null, $volunteers->getId(), 'Event helper', yearlyGift: true);

		// 40th birthday in 3 days, 10th anniversary as president in 10 days
		$anna = $this->persons->create(null, new PersonInput(
			'Anna',
			'Muster',
			$this->ago($today, 3, 40),
			notes: 'Prefers flowers over chocolate.',
			street: 'Bahnhofstrasse',
			streetNumber: '12a',
			postalCode: '8001',
			city: 'Zürich',
			country: 'Switzerland',
			mobilePhone: '+41 79 123 45 67',
		));
		$this->persons->addSlice(null, $anna->getId(), new SliceInput($president->getId(), $this->ago($today, 10, 10)));

		// Re-elected treasurer: two back-to-back slices count as one period, 5th anniversary in 20 days
		$beat = $this->persons->create(null, new PersonInput('Beat', 'Beispiel', $this->ago($today, 30, 55)));
		$reElection = Dates::anniversary(Dates::addDays($today, 20), -3);
		$this->persons->addSlice(null, $beat->getId(), new SliceInput($treasurer->getId(), $this->ago($today, 20, 5), Dates::format(Dates::addDays($reElection, -1))));
		$this->persons->addSlice(null, $beat->getId(), new SliceInput(
			$treasurer->getId(),
			Dates::format($reElection),
			startDateSource: 'Minutes of the general meeting',
			notes: 'Re-elected unanimously.',
		));

		// 28th birthday in 5 days, volunteer for 3 years in 45 days
		$claudia = $this->persons->create(null, new PersonInput('Claudia', 'Test', $this->ago($today, 5, 28)));
		$this->persons->addSlice(null, $claudia->getId(), new SliceInput($helper->getId(), $this->ago($today, 45, 3)));

		// Former treasurer: no events, only visible to managers
		$dario = $this->persons->create(null, new PersonInput('Dario', 'Former', $this->ago($today, 8, 61)));
		$this->persons->addSlice(null, $dario->getId(), new SliceInput($treasurer->getId(), '2015-01-01', Dates::format(Dates::addDays($today, -400))));

		// Birthday in two months, outside the default lead time
		$eva = $this->persons->create(null, new PersonInput('Eva', 'Zukunft', $this->ago($today, 60, 33)));
		$this->persons->addSlice(null, $eva->getId(), new SliceInput($helper->getId(), $this->ago($today, 100, 1)));

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
