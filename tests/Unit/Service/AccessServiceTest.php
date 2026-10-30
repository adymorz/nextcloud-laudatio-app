<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Adrian Dymorz <dev@adrian.dymorz.ch>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Laudatio\Tests\Unit\Service;

use OCA\Laudatio\Db\GroupMapper;
use OCA\Laudatio\Db\InterestedMapper;
use OCA\Laudatio\Db\SliceMapper;
use OCA\Laudatio\Exception\ForbiddenException;
use OCA\Laudatio\Service\AccessService;
use OCA\Laudatio\Service\Clock;
use OCA\Laudatio\Service\Dates;
use OCA\Laudatio\Service\SettingsService;
use OCP\IGroup;
use OCP\IGroupManager;
use OCP\IL10N;
use OCP\IUser;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class AccessServiceTest extends TestCase {
	private IGroupManager&MockObject $groupManager;
	private SettingsService&MockObject $settings;
	private GroupMapper&MockObject $groupMapper;
	private InterestedMapper&MockObject $interestedMapper;
	private SliceMapper&MockObject $sliceMapper;
	private AccessService $access;

	#[\Override]
	protected function setUp(): void {
		$this->groupManager = $this->createMock(IGroupManager::class);
		$this->settings = $this->createMock(SettingsService::class);
		$this->groupMapper = $this->createMock(GroupMapper::class);
		$this->interestedMapper = $this->createMock(InterestedMapper::class);
		$this->sliceMapper = $this->createMock(SliceMapper::class);
		$clock = $this->createMock(Clock::class);
		$clock->method('today')->willReturn(Dates::parse('2026-10-27'));
		$l10n = $this->createMock(IL10N::class);
		$l10n->method('t')->willReturnArgument(0);

		$this->groupManager->method('isAdmin')->willReturnCallback(static fn (string $uid): bool => $uid === 'admin');
		$this->groupManager->method('isInGroup')->willReturnCallback(static fn (string $uid, string $gid): bool => $uid === 'manager' && $gid === 'hr');
		$this->groupManager->method('get')->willReturnCallback(fn (string $gid): ?IGroup => match ($gid) {
			'admin' => $this->group([['admin', true], ['locked', false]]),
			'hr' => $this->group([['manager', true], ['admin', true]]),
			default => null,
		});
		$this->settings->method('getManagerGroup')->willReturn('hr');
		$this->groupMapper->method('findIdsByResponsible')->willReturnMap([['alice', [1]], ['bob', []], ['carol', []], ['admin', []], ['manager', []]]);
		$this->interestedMapper->method('findGroupIdsByUser')->willReturnMap([['alice', [2]], ['bob', [1]], ['carol', []], ['admin', []], ['manager', []]]);
		$this->sliceMapper->method('findCurrentPersonIdsByGroups')->willReturnCallback(static function (array $groupIds): array {
			$members = [1 => [10, 11], 2 => [20]];
			$ids = [];
			foreach ($groupIds as $groupId) {
				$ids = array_merge($ids, $members[$groupId] ?? []);
			}
			return $ids;
		});

		$this->access = new AccessService($this->groupManager, $this->settings, $this->groupMapper, $this->interestedMapper, $this->sliceMapper, $clock, $l10n);
	}

	public function testManagers(): void {
		$this->assertTrue($this->access->isManager('admin'));
		$this->assertTrue($this->access->isManager('manager'));
		$this->assertFalse($this->access->isManager('alice'));
		$this->assertNull($this->access->getVisibleGroupIds('manager'));
		$this->assertNull($this->access->getVisiblePersonIds('admin'));
		$this->assertTrue($this->access->canSeeSensitive('manager', 999));
	}

	public function testResponsibleSeesSensitiveDataOfOwnGroupOnly(): void {
		$this->assertTrue($this->access->hasAccess('alice'));
		$this->assertEqualsCanonicalizing([1, 2], $this->access->getVisibleGroupIds('alice'));
		$this->assertEqualsCanonicalizing([10, 11, 20], $this->access->getVisiblePersonIds('alice'));
		$this->assertTrue($this->access->canSeeSensitive('alice', 10));
		// Person 20 is in group 2, where alice is only interested
		$this->assertTrue($this->access->canSeePerson('alice', 20));
		$this->assertFalse($this->access->canSeeSensitive('alice', 20));
	}

	public function testInterestedSeesPersonsButNoSensitiveData(): void {
		$this->assertTrue($this->access->hasAccess('bob'));
		$this->assertTrue($this->access->canSeePerson('bob', 10));
		$this->assertFalse($this->access->canSeePerson('bob', 20));
		$this->assertFalse($this->access->canSeeSensitive('bob', 10));
		$this->assertTrue($this->access->canSeeGroup('bob', 1));
		$this->assertFalse($this->access->canSeeGroup('bob', 2));
	}

	public function testOthersHaveNoAccess(): void {
		$this->assertFalse($this->access->hasAccess('carol'));
		$this->assertSame([], $this->access->getVisiblePersonIds('carol'));
		$this->expectException(ForbiddenException::class);
		$this->access->requireAccess('carol');
	}

	public function testOnlyManagersMayEdit(): void {
		$this->access->requireManager('manager');
		$this->expectException(ForbiddenException::class);
		$this->access->requireManager('alice');
	}

	public function testManagerUserIdsAreTheEnabledAdminsAndManagerGroupMembers(): void {
		$this->assertSame(['admin', 'manager'], $this->access->getManagerUserIds());
	}

	public function testEmptyUserIdMatchesNoGroup(): void {
		// Groups whose responsible user was deleted store an empty UID
		$this->groupMapper->expects($this->never())->method('findIdsByResponsible');
		$this->assertSame([], $this->access->getResponsibleGroupIds(''));
	}

	/**
	 * @param list<array{string, bool}> $users UID and whether the account is enabled
	 */
	private function group(array $users): IGroup {
		$group = $this->createMock(IGroup::class);
		$group->method('getUsers')->willReturn(array_map(function (array $user): IUser {
			$mock = $this->createMock(IUser::class);
			$mock->method('getUID')->willReturn($user[0]);
			$mock->method('isEnabled')->willReturn($user[1]);
			return $mock;
		}, $users));
		return $group;
	}
}
