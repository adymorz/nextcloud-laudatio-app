<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Adrian Dymorz <dev@adrian.dymorz.ch>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Laudatio\Tests\Integration\Service;

use OCA\Laudatio\Db\AuditEntry;
use OCA\Laudatio\Db\AuditMapper;
use OCA\Laudatio\Db\Event;
use OCA\Laudatio\Db\EventMapper;
use OCA\Laudatio\Db\Group;
use OCA\Laudatio\Db\Person;
use OCA\Laudatio\Exception\ConflictException;
use OCA\Laudatio\Model\PersonInput;
use OCA\Laudatio\Service\AuditService;
use OCA\Laudatio\Service\Clock;
use OCA\Laudatio\Service\Dates;
use OCA\Laudatio\Service\ExportService;
use OCA\Laudatio\Service\GroupService;
use OCA\Laudatio\Service\ImportService;
use OCA\Laudatio\Service\ImportValidator;
use OCA\Laudatio\Service\PersonService;
use OCA\Laudatio\Tests\Integration\IntegrationTestCase;

/**
 * Export and import through the services and the real database. The instance
 * may contain other data, so the test works on the rows it created: it imports
 * them as renamed copies through write(), which skips the check for an empty
 * app.
 */
final class ExportImportTest extends IntegrationTestCase {
	private GroupService $groups;
	private PersonService $persons;

	#[\Override]
	protected function setUp(): void {
		parent::setUp();
		$this->groups = $this->service(GroupService::class);
		$this->persons = $this->service(PersonService::class);
	}

	public function testRoundTrip(): void {
		$today = $this->service(Clock::class)->today();
		$groupName = $this->uniqueName('Group');
		$lastName = $this->uniqueName('Person');
		$copySuffix = '-copy';

		$group = $this->groups->create('admin', $groupName, 'admin', [], '1, 2');
		$this->onTearDown(fn () => $this->groups->delete('admin', $group->getId()));
		$treasurer = $this->groups->createFunction('admin', $group->getId(), 'Treasurer', true);
		$this->onTearDown(fn () => $this->groups->deleteFunction('admin', $treasurer->getId()));
		$president = $this->groups->createFunction('admin', $group->getId(), 'President');
		$this->onTearDown(fn () => $this->groups->deleteFunction('admin', $president->getId()));
		$person = $this->persons->create('admin', new PersonInput('Test', $lastName, '1980-01-01', 'Some notes', city: 'Bern', mobilePhone: '+41 79 123 45 67'));
		$this->onTearDown(fn () => $this->persons->delete('admin', $person->getId()));
		$this->persons->addSlice('admin', $person->getId(), $treasurer->getId(), '2015-01-01', '2018-12-31');
		$this->persons->addSlice('admin', $person->getId(), $treasurer->getId(), '2019-01-01', null);
		$this->persons->addSlice('admin', $person->getId(), $president->getId(), Dates::format($today), null);

		$this->onTearDown(fn () => $this->deleteCopies($groupName . $copySuffix, $lastName . $copySuffix));

		$export = $this->exportOf($groupName, $lastName);
		$this->assertSame(1, $export['version']);
		$this->assertCount(1, $export['groups']);
		$this->assertCount(1, $export['persons']);
		$this->assertCount(3, $export['persons'][0]['terms']);

		$export['groups'][0]['name'] .= $copySuffix;
		$export['persons'][0]['lastName'] .= $copySuffix;
		$result = $this->service(ImportService::class)->write('admin', $this->service(ImportValidator::class)->validate($export));
		$this->assertSame(['groups' => 1, 'functions' => 2, 'persons' => 1, 'terms' => 3], $result);

		$copy = $this->exportOf($groupName . $copySuffix, $lastName . $copySuffix);
		$this->assertSame($this->withoutFunctionIds($export), $this->withoutFunctionIds($copy));

		// Only the user references are lost
		$newGroup = $this->findGroup($groupName . $copySuffix);
		$this->assertSame('', $newGroup->getResponsibleUid());
		$this->assertSame([], $this->groups->getInterested([$newGroup->getId()])[$newGroup->getId()] ?? []);

		// The events are generated from the imported terms
		$newPerson = $this->findPerson($lastName . $copySuffix);
		$types = array_map(static fn (Event $event): string => $event->getType(), $this->service(EventMapper::class)->findByPerson($newPerson->getId()));
		$this->assertContains(Event::TYPE_BIRTHDAY, $types);
		$this->assertContains(Event::TYPE_GIFT, $types);

		// The newest entry comes from the second export above
		[$entry] = $this->service(AuditMapper::class)->findPage(1, 0);
		$this->assertSame('admin', $entry->getActorUid());
		$this->assertSame(AuditEntry::ACTION_EXPORT, $entry->getAction());
		$this->assertSame(AuditService::ENTITY_DATA, $entry->getEntityType());
		$this->assertSame(0, $entry->getEntityId());
	}

	public function testRefusesAnAppWithData(): void {
		$group = $this->groups->create('admin', $this->uniqueName('Group'), 'admin', [], null);
		$this->onTearDown(fn () => $this->groups->delete('admin', $group->getId()));

		$this->expectException(ConflictException::class);
		$this->service(ImportService::class)->import('admin', []);
	}

	/**
	 * The export reduced to one group and one person, with both functions of
	 * the group in a stable order.
	 *
	 * @return array<string, mixed>
	 */
	private function exportOf(string $groupName, string $lastName): array {
		$export = $this->service(ExportService::class)->export('admin');
		$export['groups'] = array_values(array_filter($export['groups'], static fn (array $group): bool => $group['name'] === $groupName));
		$export['persons'] = array_values(array_filter($export['persons'], static fn (array $person): bool => $person['lastName'] === $lastName));
		return $export;
	}

	/**
	 * The function names instead of the IDs, which differ between both exports.
	 *
	 * @param array<string, mixed> $export
	 * @return array<string, mixed>
	 */
	private function withoutFunctionIds(array $export): array {
		$names = [];
		foreach ($export['groups'] as &$group) {
			foreach ($group['functions'] as &$function) {
				$names[$function['id']] = $function['name'];
				unset($function['id']);
			}
			unset($function);
		}
		unset($group);
		foreach ($export['persons'] as &$person) {
			foreach ($person['terms'] as &$term) {
				$term['functionId'] = $names[$term['functionId']];
			}
			unset($term);
		}
		unset($person);
		return $export;
	}

	private function findGroup(string $name): Group {
		foreach ($this->groups->findAll() as $group) {
			if ($group->getName() === $name) {
				return $group;
			}
		}
		$this->fail('Group not found: ' . $name);
	}

	private function findPerson(string $lastName): Person {
		foreach ($this->persons->findAll() as $person) {
			if ($person->getLastName() === $lastName) {
				return $person;
			}
		}
		$this->fail('Person not found: ' . $lastName);
	}

	private function deleteCopies(string $groupName, string $lastName): void {
		foreach ($this->persons->findAll() as $person) {
			if ($person->getLastName() === $lastName) {
				$this->persons->delete('admin', $person->getId());
			}
		}
		foreach ($this->groups->findAll() as $group) {
			if ($group->getName() === $groupName) {
				foreach ($this->groups->getFunctions([$group->getId()]) as $function) {
					$this->groups->deleteFunction('admin', $function->getId());
				}
				$this->groups->delete('admin', $group->getId());
			}
		}
	}
}
