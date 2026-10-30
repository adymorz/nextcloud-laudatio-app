<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Adrian Dymorz <dev@adrian.dymorz.ch>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Laudatio\Tests\Unit\Listener;

use OCA\Laudatio\Db\AuditEntry;
use OCA\Laudatio\Db\Group;
use OCA\Laudatio\Db\GroupMapper;
use OCA\Laudatio\Db\InterestedMapper;
use OCA\Laudatio\Listener\UserDeletedListener;
use OCA\Laudatio\Service\AuditService;
use OCA\Laudatio\Service\Clock;
use OCA\Laudatio\Service\NotificationService;
use OCP\IUser;
use OCP\User\Events\UserDeletedEvent;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

final class UserDeletedListenerTest extends TestCase {
	private InterestedMapper&MockObject $interestedMapper;
	private GroupMapper&MockObject $groupMapper;
	private AuditService&MockObject $audit;
	private NotificationService&MockObject $notifications;
	private LoggerInterface&MockObject $logger;
	private UserDeletedListener $listener;

	#[\Override]
	protected function setUp(): void {
		$this->interestedMapper = $this->createMock(InterestedMapper::class);
		$this->groupMapper = $this->createMock(GroupMapper::class);
		$this->audit = $this->createMock(AuditService::class);
		$this->notifications = $this->createMock(NotificationService::class);
		$this->logger = $this->createMock(LoggerInterface::class);
		$clock = $this->createMock(Clock::class);
		$clock->method('time')->willReturn(1000);
		$this->listener = new UserDeletedListener($this->interestedMapper, $this->groupMapper, $this->audit, $this->notifications, $clock, $this->logger);
	}

	public function testClearsTheResponsibleUserAndNotifiesTheManagers(): void {
		$group = new Group();
		$group->setId(3);
		$group->setResponsibleUid('carol');
		$this->interestedMapper->expects($this->once())->method('deleteByUser')->with('carol');
		$this->groupMapper->method('findIdsByResponsible')->with('carol')->willReturn([3]);
		$this->groupMapper->method('findByIds')->with([3])->willReturn([$group]);
		$this->groupMapper->expects($this->once())->method('update')->with($group);
		$this->audit->expects($this->once())->method('log')->with(null, AuditEntry::ACTION_UPDATE, AuditService::ENTITY_GROUP, 3, ['responsible_uid']);
		$this->notifications->expects($this->once())->method('notifyResponsibleDeleted')->with(3);
		$this->logger->expects($this->once())->method('warning');

		$this->listener->handle(new UserDeletedEvent($this->user('carol')));
		$this->assertSame('', $group->getResponsibleUid());
		$this->assertSame(1000, $group->getUpdatedAt());
	}

	public function testUsersWithoutGroupsOnlyLeaveTheInterestedLists(): void {
		$this->interestedMapper->expects($this->once())->method('deleteByUser')->with('dave');
		$this->groupMapper->method('findIdsByResponsible')->willReturn([]);
		$this->groupMapper->expects($this->never())->method('update');
		$this->notifications->expects($this->never())->method('notifyResponsibleDeleted');

		$this->listener->handle(new UserDeletedEvent($this->user('dave')));
	}

	private function user(string $uid): IUser {
		$user = $this->createMock(IUser::class);
		$user->method('getUID')->willReturn($uid);
		return $user;
	}
}
