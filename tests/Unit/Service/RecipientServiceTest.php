<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Adrian Dymorz <dev@adrian.dymorz.ch>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Laudatio\Tests\Unit\Service;

use OCA\Laudatio\Db\Event;
use OCA\Laudatio\Db\Group;
use OCA\Laudatio\Db\GroupMapper;
use OCA\Laudatio\Db\InterestedMapper;
use OCA\Laudatio\Db\OrgFunction;
use OCA\Laudatio\Db\OrgFunctionMapper;
use OCA\Laudatio\Db\Slice;
use OCA\Laudatio\Db\SliceMapper;
use OCA\Laudatio\Service\Dates;
use OCA\Laudatio\Service\RecipientService;
use PHPUnit\Framework\TestCase;

final class RecipientServiceTest extends TestCase {
	private RecipientService $service;

	#[\Override]
	protected function setUp(): void {
		$groupMapper = $this->createMock(GroupMapper::class);
		// Group 3 lost its responsible user
		$groupMapper->method('findAll')->willReturn([$this->group(1, 'alice', 'Group 1'), $this->group(2, 'bob', 'Group 2'), $this->group(3, '', 'Group 3')]);
		$functionMapper = $this->createMock(OrgFunctionMapper::class);
		$functionMapper->method('findAll')->willReturn([$this->function(10, 1), $this->function(20, 2), $this->function(30, 3)]);
		$interestedMapper = $this->createMock(InterestedMapper::class);
		$interestedMapper->method('findUserIdsByGroups')->willReturn([1 => ['bob', 'carol'], 2 => ['alice'], 3 => ['dave']]);
		$sliceMapper = $this->createMock(SliceMapper::class);
		$sliceMapper->method('findByPerson')->willReturn([
			$this->slice(10, '2020-01-01', null),
			$this->slice(20, '2020-01-01', '2026-06-30'),
		]);
		$this->service = new RecipientService($groupMapper, $functionMapper, $interestedMapper, $sliceMapper);
	}

	public function testJubileeGoesToTheFunctionsGroup(): void {
		$recipients = $this->service->recipients($this->event(Event::TYPE_JUBILEE, 10, '2026-11-05'));
		$this->assertSame(['bob' => 'interested', 'carol' => 'interested', 'alice' => 'responsible'], $recipients);
	}

	public function testYearlyGiftGoesToTheFunctionsGroup(): void {
		$recipients = $this->service->recipients($this->event(Event::TYPE_GIFT, 20, '2026-12-31'));
		$this->assertSame(['alice' => 'interested', 'bob' => 'responsible'], $recipients);
	}

	public function testBirthdayGoesToAllGroupsActiveOnTheDayAndResponsibleWins(): void {
		$recipients = $this->service->recipients($this->event(Event::TYPE_BIRTHDAY, null, '2026-03-01'));
		ksort($recipients);
		// alice: responsible in group 1, interested in group 2; bob: interested in 1, responsible in 2
		$this->assertSame(['alice' => 'responsible', 'bob' => 'responsible', 'carol' => 'interested'], $recipients);
	}

	public function testBirthdayAfterASliceEnded(): void {
		$recipients = $this->service->recipients($this->event(Event::TYPE_BIRTHDAY, null, '2026-08-01'));
		ksort($recipients);
		$this->assertSame(['alice' => 'responsible', 'bob' => 'interested', 'carol' => 'interested'], $recipients);
		$this->assertSame(RecipientService::ROLE_INTERESTED, $this->service->roleOf($this->event(Event::TYPE_BIRTHDAY, null, '2026-08-01'), 'bob'));
		$this->assertNull($this->service->roleOf($this->event(Event::TYPE_BIRTHDAY, null, '2026-08-01'), 'dave'));
	}

	public function testGroupWithoutResponsibleUserHasOnlyInterestedRecipients(): void {
		$recipients = $this->service->recipients($this->event(Event::TYPE_JUBILEE, 30, '2026-11-05'));
		$this->assertSame(['dave' => 'interested'], $recipients);
	}

	public function testGroupNamesFollowTheRole(): void {
		$birthday = $this->event(Event::TYPE_BIRTHDAY, null, '2026-03-01');
		// alice is responsible in group 1 and only interested in group 2
		$this->assertSame(['Group 1'], $this->service->groupNamesFor($birthday, 'alice'));
		$this->assertSame(['Group 2'], $this->service->groupNamesFor($birthday, 'bob'));
		$this->assertSame(['Group 1'], $this->service->groupNamesFor($birthday, 'carol'));
		$this->assertSame([], $this->service->groupNamesFor($birthday, 'dave'));
	}

	private function group(int $id, string $responsible, string $name): Group {
		$group = new Group();
		$group->setId($id);
		$group->setName($name);
		$group->setResponsibleUid($responsible);
		return $group;
	}

	private function function(int $id, int $groupId): OrgFunction {
		$function = new OrgFunction();
		$function->setId($id);
		$function->setGroupId($groupId);
		return $function;
	}

	private function slice(int $functionId, string $start, ?string $end): Slice {
		$slice = new Slice();
		$slice->setFunctionId($functionId);
		$slice->setStartDate(Dates::parse($start) ?? throw new \LogicException());
		$slice->setEndDate($end === null ? null : Dates::parse($end));
		return $slice;
	}

	private function event(string $type, ?int $functionId, string $date): Event {
		$event = new Event();
		$event->setType($type);
		$event->setPersonId(5);
		$event->setFunctionId($functionId);
		$event->setEventDate(Dates::parse($date) ?? throw new \LogicException());
		return $event;
	}
}
