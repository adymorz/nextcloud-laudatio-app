<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Adrian Dymorz <dev@adrian.dymorz.ch>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Appreciation\Tests\Integration\Service;

use OCA\Appreciation\Db\AuditEntry;
use OCA\Appreciation\Db\AuditMapper;
use OCA\Appreciation\Db\Event;
use OCA\Appreciation\Db\EventMapper;
use OCA\Appreciation\Exception\ConflictException;
use OCA\Appreciation\Exception\ValidationException;
use OCA\Appreciation\Model\PersonInput;
use OCA\Appreciation\Service\Clock;
use OCA\Appreciation\Service\Dates;
use OCA\Appreciation\Service\EventService;
use OCA\Appreciation\Service\GroupService;
use OCA\Appreciation\Service\PersonService;
use OCA\Appreciation\Service\UpcomingService;
use OCA\Appreciation\Tests\Integration\IntegrationTestCase;

/**
 * Persons, slices, groups and functions through the services, including the
 * generated events and the audit log. Uses the "admin" user that exists in
 * every test instance.
 */
final class LifecycleTest extends IntegrationTestCase {
	private GroupService $groups;
	private PersonService $persons;
	private EventMapper $events;

	#[\Override]
	protected function setUp(): void {
		parent::setUp();
		$this->groups = $this->service(GroupService::class);
		$this->persons = $this->service(PersonService::class);
		$this->events = $this->service(EventMapper::class);
	}

	public function testEventsFollowTheMasterData(): void {
		$today = $this->service(Clock::class)->today();
		$in = static fn (int $days, int $yearsBack = 0): string => Dates::format(Dates::anniversary(Dates::addDays($today, $days), -$yearsBack));

		$group = $this->groups->create('admin', $this->uniqueName('Group'), 'admin', [], '1, 2');
		$this->onTearDown(fn () => $this->groups->delete('admin', $group->getId()));
		$function = $this->groups->createFunction('admin', $group->getId(), 'Tester');
		$this->onTearDown(fn () => $this->groups->deleteFunction('admin', $function->getId()));

		$person = $this->persons->create('admin', new PersonInput('Test', $this->uniqueName('Person'), $in(10, 30), ' notes ', street: ' Main street ', mobilePhone: ''));
		$this->onTearDown(fn () => $this->persons->delete('admin', $person->getId()));
		$this->assertSame('notes', $person->getNotes());
		$this->assertSame('Main street', $person->getStreet());
		$this->assertNull($person->getMobilePhone());

		// No slice yet: not a member, so no birthday
		$this->assertSame([], $this->events->findByPerson($person->getId()));

		$nextBirthday = static fn (string $birth): string => Dates::format(Dates::anniversary(Dates::parse($birth) ?? throw new \LogicException(), 31));

		$this->persons->addSlice('admin', $person->getId(), $function->getId(), $in(20, 2), null);
		$this->assertSame([
			['birthday', $in(10), 30],
			['jubilee', $in(20), 2],
			['birthday', $nextBirthday($in(10, 30)), 31],
		], $this->describe($this->events->findByPerson($person->getId())));

		try {
			$this->persons->addSlice('admin', $person->getId(), $function->getId(), $in(0), null);
			$this->fail('Overlapping slice was accepted');
		} catch (ConflictException) {
		}

		// Moving the birthday updates the existing events
		$this->persons->update('admin', $person->getId(), new PersonInput('Test', $person->getLastName(), $in(12, 30), street: 'Main street', city: 'Bern'));
		$this->assertSame([
			['birthday', $in(12), 30],
			['jubilee', $in(20), 2],
			['birthday', $nextBirthday($in(12, 30)), 31],
		], $this->describe($this->events->findByPerson($person->getId())));

		try {
			$this->groups->deleteFunction('admin', $function->getId());
			$this->fail('Function with slices was deleted');
		} catch (ConflictException) {
		}

		$this->persons->delete('admin', $person->getId());
		$this->assertSame([], $this->events->findByPerson($person->getId()));

		$audit = array_filter(
			$this->service(AuditMapper::class)->findPage(50, 0),
			static fn (AuditEntry $entry): bool => $entry->getEntityType() === 'person' && $entry->getEntityId() === $person->getId(),
		);
		$this->assertSame(['delete', 'update', 'create'], array_values(array_map(static fn (AuditEntry $e): string => $e->getAction(), $audit)));
		$update = array_values($audit)[1];
		$this->assertSame(['date_of_birth', 'notes', 'city'], $update->getChangedFieldList());
	}

	public function testYearlyGifts(): void {
		$today = $this->service(Clock::class)->today();
		$thisYear = (int)$today->format('Y');
		$lastYear = (int)Dates::addDays($today, EventService::WINDOW_DAYS)->format('Y');
		$start = sprintf('%d-01-01', $thisYear - 1);

		$group = $this->groups->create('admin', $this->uniqueName('Group'), 'admin', [], null);
		$this->onTearDown(fn () => $this->groups->delete('admin', $group->getId()));
		$function = $this->groups->createFunction('admin', $group->getId(), 'Gift tester', true);
		$this->onTearDown(fn () => $this->groups->deleteFunction('admin', $function->getId()));
		$person = $this->persons->create('admin', new PersonInput('Test', $this->uniqueName('Person'), '1980-01-01'));
		$this->onTearDown(fn () => $this->persons->delete('admin', $person->getId()));
		$slice = $this->persons->addSlice('admin', $person->getId(), $function->getId(), $start, null);

		$gifts = fn (): array => array_values(array_filter(
			$this->events->findByPerson($person->getId()),
			static fn (Event $e): bool => $e->getType() === Event::TYPE_GIFT,
		));
		$this->assertSame(
			array_map(static fn (int $year): array => ['gift', $year . '-12-31', $year], range($thisYear, $lastYear)),
			$this->describe($gifts()),
		);

		// Leaving today: this year's gift is due today, no later ones
		$this->persons->updateSlice('admin', $slice->getId(), $function->getId(), $start, Dates::format($today));
		$this->assertSame([['gift', Dates::format($today), $thisYear]], $this->describe($gifts()));

		// Presented stays presented when the events are synchronized again
		$gift = $gifts()[0];
		$this->service(UpcomingService::class)->setGreeted('admin', $gift->getId(), true);
		$this->service(EventService::class)->syncPerson($person->getId());
		$this->assertNotNull($this->events->find($gift->getId())->getGreetedAt());

		// Switching the yearly gift off removes this year's gift
		$this->groups->updateFunction('admin', $function->getId(), 'Gift tester', false);
		$this->assertSame([], $gifts());
	}

	public function testValidation(): void {
		$this->expectException(ValidationException::class);
		$this->groups->create('admin', 'Group', $this->uniqueName('no-such-user'), [], null);
	}

	/**
	 * @param list<Event> $events
	 * @return list<array{string, string, int}>
	 */
	private function describe(array $events): array {
		return array_map(static fn (Event $e): array => [$e->getType(), Dates::format($e->getEventDate()), $e->getOrdinal()], $events);
	}
}
