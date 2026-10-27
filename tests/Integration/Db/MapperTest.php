<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Adrian Dymorz <dev@adrian.dymorz.ch>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Appreciation\Tests\Integration\Db;

use OCA\Appreciation\Db\Event;
use OCA\Appreciation\Db\EventMapper;
use OCA\Appreciation\Db\Group;
use OCA\Appreciation\Db\GroupMapper;
use OCA\Appreciation\Db\InterestedMapper;
use OCA\Appreciation\Db\OrgFunction;
use OCA\Appreciation\Db\OrgFunctionMapper;
use OCA\Appreciation\Db\Person;
use OCA\Appreciation\Db\PersonMapper;
use OCA\Appreciation\Db\Slice;
use OCA\Appreciation\Db\SliceMapper;
use OCA\Appreciation\Service\Dates;
use OCA\Appreciation\Tests\Integration\IntegrationTestCase;

final class MapperTest extends IntegrationTestCase {
	private PersonMapper $persons;
	private GroupMapper $groups;
	private InterestedMapper $interested;
	private OrgFunctionMapper $functions;
	private SliceMapper $slices;
	private EventMapper $events;

	#[\Override]
	protected function setUp(): void {
		parent::setUp();
		$this->persons = $this->service(PersonMapper::class);
		$this->groups = $this->service(GroupMapper::class);
		$this->interested = $this->service(InterestedMapper::class);
		$this->functions = $this->service(OrgFunctionMapper::class);
		$this->slices = $this->service(SliceMapper::class);
		$this->events = $this->service(EventMapper::class);
	}

	public function testDatesRoundTrip(): void {
		$person = $this->createPerson('2000-02-29');
		$loaded = $this->persons->find($person->getId());
		$this->assertSame('2000-02-29', Dates::format($loaded->getDateOfBirth()));
		$this->assertNull($loaded->getNotes());

		$group = $this->createGroup('responsible');
		$function = $this->createFunction($group);
		$slice = $this->createSlice($person, $function, '2020-01-01', null);
		$loadedSlice = $this->slices->find($slice->getId());
		$this->assertSame('2020-01-01', Dates::format($loadedSlice->getStartDate()));
		$this->assertNull($loadedSlice->getEndDate());
	}

	public function testCurrentMembersOfGroups(): void {
		$responsible = $this->uniqueName('resp');
		$group = $this->createGroup($responsible);
		$otherGroup = $this->createGroup($this->uniqueName('resp'));
		$function = $this->createFunction($group);
		$otherFunction = $this->createFunction($otherGroup);

		$active = $this->createPerson('1980-01-01');
		$endingToday = $this->createPerson('1981-01-01');
		$former = $this->createPerson('1982-01-01');
		$future = $this->createPerson('1983-01-01');
		$elsewhere = $this->createPerson('1984-01-01');
		$this->createSlice($active, $function, '2010-01-01', null);
		$this->createSlice($endingToday, $function, '2010-01-01', '2026-10-27');
		$this->createSlice($former, $function, '2010-01-01', '2026-10-26');
		$this->createSlice($future, $function, '2027-01-01', null);
		$this->createSlice($elsewhere, $otherFunction, '2010-01-01', null);

		$today = Dates::parse('2026-10-27');
		$this->assertNotNull($today);
		$ids = $this->slices->findCurrentPersonIdsByGroups([$group->getId()], $today);
		$this->assertEqualsCanonicalizing([$active->getId(), $endingToday->getId(), $future->getId()], $ids);

		$this->assertSame([$group->getId()], $this->groups->findIdsByResponsible($responsible));
		$this->assertSame(1, $this->functions->countByGroup($group->getId()));
		$this->assertSame(1, $this->slices->countByFunction($otherFunction->getId()));
	}

	public function testInterestedUsers(): void {
		$group = $this->createGroup($this->uniqueName('resp'));
		$userA = $this->uniqueName('a');
		$userB = $this->uniqueName('b');
		$this->interested->setForGroup($group->getId(), [$userA, $userB, $userA]);
		$this->onTearDown(fn () => $this->interested->deleteByGroup($group->getId()));

		$this->assertEqualsCanonicalizing([$userA, $userB], $this->interested->findUserIdsByGroups([$group->getId()])[$group->getId()]);
		$this->assertSame([$group->getId()], $this->interested->findGroupIdsByUser($userB));

		$this->interested->setForGroup($group->getId(), [$userB]);
		$this->assertSame([], $this->interested->findGroupIdsByUser($userA));
	}

	public function testEventsBetween(): void {
		$person = $this->createPerson('1990-11-05');
		$event = new Event();
		$event->setUid($this->uniqueName('test-event'));
		$event->setType(Event::TYPE_BIRTHDAY);
		$event->setPersonId($person->getId());
		$event->setEventDate(Dates::parse('2026-11-05') ?? throw new \LogicException());
		$event->setOrdinal(36);
		$event->setCreatedAt(time());
		$this->events->insert($event);
		$this->onTearDown(fn () => $this->events->delete($event));

		$ids = static fn (array $events): array => array_map(static fn (Event $e): int => $e->getId(), $events);
		$from = Dates::parse('2026-11-01') ?? throw new \LogicException();
		$this->assertContains($event->getId(), $ids($this->events->findBetween($from, Dates::parse('2026-11-05'))));
		$this->assertNotContains($event->getId(), $ids($this->events->findBetween($from, Dates::parse('2026-11-04'))));
		$this->assertSame([$event->getId()], $ids($this->events->findByPerson($person->getId())));
	}

	private function createPerson(string $dateOfBirth): Person {
		$person = new Person();
		$person->setFirstName('Test');
		$person->setLastName($this->uniqueName('Person'));
		$person->setDateOfBirth(Dates::parse($dateOfBirth) ?? throw new \LogicException());
		$person->setCreatedAt(time());
		$person->setUpdatedAt(time());
		$this->persons->insert($person);
		$this->onTearDown(fn () => $this->persons->delete($person));
		return $person;
	}

	private function createGroup(string $responsible): Group {
		$group = new Group();
		$group->setName($this->uniqueName('Group'));
		$group->setResponsibleUid($responsible);
		$group->setCreatedAt(time());
		$group->setUpdatedAt(time());
		$this->groups->insert($group);
		$this->onTearDown(fn () => $this->groups->delete($group));
		return $group;
	}

	private function createFunction(Group $group): OrgFunction {
		$function = new OrgFunction();
		$function->setGroupId($group->getId());
		$function->setName($this->uniqueName('Function'));
		$function->setCreatedAt(time());
		$function->setUpdatedAt(time());
		$this->functions->insert($function);
		$this->onTearDown(fn () => $this->functions->delete($function));
		return $function;
	}

	private function createSlice(Person $person, OrgFunction $function, string $start, ?string $end): Slice {
		$slice = new Slice();
		$slice->setPersonId($person->getId());
		$slice->setFunctionId($function->getId());
		$slice->setStartDate(Dates::parse($start) ?? throw new \LogicException());
		$slice->setEndDate($end === null ? null : Dates::parse($end));
		$slice->setCreatedAt(time());
		$slice->setUpdatedAt(time());
		$this->slices->insert($slice);
		$this->onTearDown(fn () => $this->slices->delete($slice));
		return $slice;
	}
}
