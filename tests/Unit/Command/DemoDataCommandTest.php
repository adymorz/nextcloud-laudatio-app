<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Adrian Dymorz <dev@adrian.dymorz.ch>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Laudatio\Tests\Unit\Command;

use OCA\Laudatio\Command\DemoDataCommand;
use OCA\Laudatio\Db\Group;
use OCA\Laudatio\Service\Clock;
use OCA\Laudatio\Service\GroupService;
use OCA\Laudatio\Service\PersonService;
use OCP\IUserManager;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

final class DemoDataCommandTest extends TestCase {
	public function testRefusesToMixDemoDataIntoExistingData(): void {
		$groups = $this->createMock(GroupService::class);
		$groups->method('findAll')->willReturn([new Group()]);
		$groups->expects($this->never())->method('create');
		$persons = $this->createMock(PersonService::class);
		$persons->expects($this->never())->method('create');
		$userManager = $this->createMock(IUserManager::class);
		$userManager->method('userExists')->willReturn(true);

		$tester = new CommandTester(new DemoDataCommand($groups, $persons, $userManager, $this->createMock(Clock::class)));
		$this->assertSame(Command::FAILURE, $tester->execute([]));
		$this->assertStringContainsString('already contains data', $tester->getDisplay());
	}

	public function testRequiresTheTestUsers(): void {
		$userManager = $this->createMock(IUserManager::class);
		$userManager->method('userExists')->willReturn(false);

		$tester = new CommandTester(new DemoDataCommand(
			$this->createMock(GroupService::class),
			$this->createMock(PersonService::class),
			$userManager,
			$this->createMock(Clock::class),
		));
		$this->assertSame(Command::FAILURE, $tester->execute([]));
		$this->assertStringContainsString('"alice" does not exist', $tester->getDisplay());
	}
}
