<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Adrian Dymorz <dev@adrian.dymorz.ch>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Laudatio\Tests\Unit\Command;

use OCA\Laudatio\BackgroundJob\SyncJob;
use OCA\Laudatio\Command\PurgeCommand;
use OCP\BackgroundJob\IJobList;
use OCP\Config\IUserConfig;
use OCP\DB\QueryBuilder\IExpressionBuilder;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IAppConfig;
use OCP\IDBConnection;
use OCP\Notification\IManager as INotificationManager;
use OCP\Notification\INotification;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

final class PurgeCommandTest extends TestCase {
	private IDBConnection&MockObject $db;
	private IAppConfig&MockObject $appConfig;
	private IUserConfig&MockObject $userConfig;
	private INotificationManager&MockObject $notificationManager;
	private IJobList&MockObject $jobList;
	private CommandTester $tester;

	#[\Override]
	protected function setUp(): void {
		$this->db = $this->createMock(IDBConnection::class);
		$this->appConfig = $this->createMock(IAppConfig::class);
		$this->userConfig = $this->createMock(IUserConfig::class);
		$this->notificationManager = $this->createMock(INotificationManager::class);
		$this->jobList = $this->createMock(IJobList::class);

		$command = new PurgeCommand($this->db, $this->appConfig, $this->userConfig, $this->notificationManager, $this->jobList);
		// The application provides the question helper
		$application = new Application();
		$application->add($command);
		$this->tester = new CommandTester($command);
	}

	public function testRefusesWithoutForceWhenNotInteractive(): void {
		$this->db->expects($this->never())->method('dropTable');
		$this->assertSame(Command::FAILURE, $this->tester->execute([], ['interactive' => false]));
		$this->assertStringContainsString('--force', $this->tester->getDisplay());
	}

	public function testAbortsWhenNotConfirmed(): void {
		$this->db->expects($this->never())->method('dropTable');
		$this->appConfig->expects($this->never())->method('deleteApp');
		$this->tester->setInputs(['n']);
		$this->assertSame(Command::FAILURE, $this->tester->execute([]));
		$this->assertStringContainsString('nothing was deleted', $this->tester->getDisplay());
	}

	public function testPurgesEverythingWithForce(): void {
		$dropped = [];
		$this->db->method('dropTable')->willReturnCallback(static function (string $table) use (&$dropped): void {
			$dropped[] = $table;
		});
		$qb = $this->createMock(IQueryBuilder::class);
		$qb->expects($this->once())->method('delete')->with('migrations')->willReturnSelf();
		$qb->method('where')->willReturnSelf();
		$qb->method('expr')->willReturn($this->createMock(IExpressionBuilder::class));
		$qb->expects($this->once())->method('executeStatement')->willReturn(2);
		$this->db->method('getQueryBuilder')->willReturn($qb);
		$this->appConfig->expects($this->once())->method('deleteApp')->with('laudatio');
		$this->userConfig->expects($this->once())->method('deleteApp')->with('laudatio');
		$notification = $this->createMock(INotification::class);
		$notification->expects($this->once())->method('setApp')->with('laudatio')->willReturnSelf();
		$this->notificationManager->method('createNotification')->willReturn($notification);
		$this->notificationManager->expects($this->once())->method('markProcessed')->with($notification);
		$this->jobList->expects($this->once())->method('remove')->with(SyncJob::class);

		$this->assertSame(Command::SUCCESS, $this->tester->execute(['--force' => true]));
		$this->assertSame([
			'laudatio_persons', 'laudatio_groups', 'laudatio_interested', 'laudatio_functions',
			'laudatio_slices', 'laudatio_events', 'laudatio_notified', 'laudatio_audit',
		], $dropped);
		$this->assertStringContainsString('app:enable laudatio', $this->tester->getDisplay());
	}
}
