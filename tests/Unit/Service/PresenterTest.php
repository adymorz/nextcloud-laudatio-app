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
use OCA\Laudatio\Db\OrgFunctionMapper;
use OCA\Laudatio\Db\Person;
use OCA\Laudatio\Db\PersonMapper;
use OCA\Laudatio\Db\Slice;
use OCA\Laudatio\Db\SliceMapper;
use OCA\Laudatio\Service\AccessService;
use OCA\Laudatio\Service\Clock;
use OCA\Laudatio\Service\Dates;
use OCA\Laudatio\Service\EventService;
use OCA\Laudatio\Service\Presenter;
use OCA\Laudatio\Service\RecipientService;
use OCA\Laudatio\Service\SettingsService;
use OCA\Laudatio\Service\TitleBuilder;
use OCA\Laudatio\Service\UpcomingService;
use OCP\IUser;
use OCP\IUserManager;
use OCP\L10N\IFactory;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class PresenterTest extends TestCase {
	private IUserManager&MockObject $userManager;
	private AccessService&MockObject $access;
	private SettingsService&MockObject $settings;
	private Presenter $presenter;

	#[\Override]
	protected function setUp(): void {
		$this->userManager = $this->createMock(IUserManager::class);
		$this->userManager->method('getDisplayName')->willReturnCallback(static fn (string $uid): string => ucfirst($uid));
		$factory = $this->createMock(IFactory::class);
		$factory->method('getUserLanguage')->willReturn('en');
		$this->access = $this->createMock(AccessService::class);
		$this->settings = $this->createMock(SettingsService::class);
		$eventService = $this->createMock(EventService::class);
		$eventService->method('jubileeYearsForGroup')->willReturn([5, 10]);
		$clock = $this->createMock(Clock::class);
		$clock->method('today')->willReturn(Dates::parse('2026-10-30'));

		$this->presenter = new Presenter(
			$this->userManager,
			$factory,
			$this->createMock(PersonMapper::class),
			$this->createMock(GroupMapper::class),
			$this->createMock(OrgFunctionMapper::class),
			$this->createMock(SliceMapper::class),
			$this->access,
			$this->settings,
			$eventService,
			$this->createMock(UpcomingService::class),
			$this->createMock(TitleBuilder::class),
			$clock,
		);
	}

	public function testSensitiveFieldsOnlyForViewersAllowedToSeeThem(): void {
		$this->access->method('canSeeSensitive')->willReturnMap([['alice', 1, true], ['bob', 1, false]]);
		$person = $this->person();

		$forAlice = $this->presenter->person($person, [], 'alice');
		$this->assertTrue($forAlice['sensitiveVisible']);
		$this->assertSame('1986-11-01', $forAlice['dateOfBirth']);
		$this->assertSame('Prefers flowers.', $forAlice['notes']);
		$this->assertSame('+41 79 123 45 67', $forAlice['mobilePhone']);

		$forBob = $this->presenter->person($person, [], 'bob');
		$this->assertFalse($forBob['sensitiveVisible']);
		$this->assertSame('Anna Muster', $forBob['displayName']);
		foreach (['dateOfBirth', 'notes', 'street', 'streetNumber', 'postalCode', 'city', 'country', 'mobilePhone', 'otherPhone'] as $field) {
			$this->assertNull($forBob[$field], $field);
		}
	}

	public function testSourcesAndNotesOfTermsOnlyForViewersAllowedToSeeThem(): void {
		$this->access->method('canSeeSensitive')->willReturnMap([['alice', 1, true], ['bob', 1, false]]);
		$slice = new Slice();
		$slice->setPersonId(1);
		$slice->setFunctionId(9);
		$slice->setStartDate(Dates::parse('2020-01-01') ?? throw new \LogicException());
		$slice->setStartDateSource('Minutes 2020');
		$slice->setEndDateSource('Resignation letter');
		$slice->setNotes('Interim at first.');

		$forAlice = $this->presenter->slice($slice, 'alice');
		$this->assertSame('Minutes 2020', $forAlice['startDateSource']);
		$this->assertSame('Resignation letter', $forAlice['endDateSource']);
		$this->assertSame('Interim at first.', $forAlice['notes']);

		$forBob = $this->presenter->slice($slice, 'bob');
		$this->assertSame('2020-01-01', $forBob['startDate']);
		foreach (['startDateSource', 'endDateSource', 'notes'] as $field) {
			$this->assertNull($forBob[$field], $field);
		}
	}

	public function testInterestedUsersGetBirthdaysWithoutTheAge(): void {
		$birthday = $this->event(Event::TYPE_BIRTHDAY, 40);
		$jubilee = $this->event(Event::TYPE_JUBILEE, 5);

		$this->assertNull($this->presenter->event($birthday, RecipientService::ROLE_INTERESTED, 'bob')['ordinal']);
		$this->assertSame(40, $this->presenter->event($birthday, RecipientService::ROLE_RESPONSIBLE, 'alice')['ordinal']);
		$this->assertSame(40, $this->presenter->event($birthday, null, 'admin')['ordinal']);
		$this->assertSame(5, $this->presenter->event($jubilee, RecipientService::ROLE_INTERESTED, 'bob')['ordinal']);
	}

	public function testGroupWithoutResponsibleUser(): void {
		$group = new Group();
		$group->setId(3);
		$group->setName('Board');
		$group->setResponsibleUid('');
		$this->assertNull($this->presenter->group($group, ['bob'], [], 'bob')['responsible']);

		$group->setResponsibleUid('alice');
		$this->assertSame(['id' => 'alice', 'displayName' => 'Alice'], $this->presenter->group($group, [], [], 'alice')['responsible']);
	}

	public function testMeTellsWhetherEmailsCanBeSent(): void {
		$user = $this->createMock(IUser::class);
		$user->method('getEMailAddress')->willReturn('alice@example.com');
		$this->userManager->method('get')->willReturnMap([['alice', $user], ['bob', null]]);
		$this->settings->method('getSheetNote')->willReturn('Stored by the Example Club.');

		$this->assertTrue($this->presenter->me('alice')['hasEmail']);
		$this->assertFalse($this->presenter->me('bob')['hasEmail']);
		$this->assertSame('Stored by the Example Club.', $this->presenter->me('bob')['sheetNote']);
	}

	private function person(): Person {
		$person = new Person();
		$person->setId(1);
		$person->setFirstName('Anna');
		$person->setLastName('Muster');
		$person->setDateOfBirth(Dates::parse('1986-11-01') ?? throw new \LogicException());
		$person->setNotes('Prefers flowers.');
		$person->setMobilePhone('+41 79 123 45 67');
		return $person;
	}

	private function event(string $type, int $ordinal): Event {
		$event = new Event();
		$event->setId(7);
		$event->setType($type);
		$event->setPersonId(1);
		$event->setFunctionId($type === Event::TYPE_BIRTHDAY ? null : 9);
		$event->setOrdinal($ordinal);
		$event->setEventDate(Dates::parse('2026-11-03') ?? throw new \LogicException());
		return $event;
	}
}
