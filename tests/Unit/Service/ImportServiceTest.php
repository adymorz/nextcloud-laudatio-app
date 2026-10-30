<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Adrian Dymorz <dev@adrian.dymorz.ch>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Laudatio\Tests\Unit\Service;

use OCA\Laudatio\Db\Group;
use OCA\Laudatio\Db\GroupMapper;
use OCA\Laudatio\Db\OrgFunction;
use OCA\Laudatio\Db\OrgFunctionMapper;
use OCA\Laudatio\Db\Person;
use OCA\Laudatio\Db\PersonMapper;
use OCA\Laudatio\Db\Slice;
use OCA\Laudatio\Db\SliceMapper;
use OCA\Laudatio\Exception\ConflictException;
use OCA\Laudatio\Exception\ValidationException;
use OCA\Laudatio\Model\ImportData;
use OCA\Laudatio\Service\AccessService;
use OCA\Laudatio\Service\AuditService;
use OCA\Laudatio\Service\Clock;
use OCA\Laudatio\Service\Dates;
use OCA\Laudatio\Service\EventService;
use OCA\Laudatio\Service\ImportService;
use OCA\Laudatio\Service\ImportValidator;
use OCA\Laudatio\Service\PersonService;
use OCA\Laudatio\Service\RecipientService;
use OCP\AppFramework\Db\Entity;
use OCP\IDBConnection;
use OCP\IL10N;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class ImportServiceTest extends TestCase {
	private ImportValidator&MockObject $validator;
	private GroupMapper&MockObject $groupMapper;
	private OrgFunctionMapper&MockObject $functionMapper;
	private PersonMapper&MockObject $personMapper;
	private SliceMapper&MockObject $sliceMapper;
	private PersonService&MockObject $persons;
	private EventService&MockObject $eventService;
	private AuditService&MockObject $audit;
	private IDBConnection&MockObject $db;
	private ImportService $service;
	/** @var list<string> names of the written tables and the sync, in order */
	private array $writes = [];

	#[\Override]
	protected function setUp(): void {
		$this->validator = $this->createMock(ImportValidator::class);
		$this->groupMapper = $this->createMock(GroupMapper::class);
		$this->functionMapper = $this->createMock(OrgFunctionMapper::class);
		$this->personMapper = $this->createMock(PersonMapper::class);
		$this->sliceMapper = $this->createMock(SliceMapper::class);
		$this->persons = $this->createMock(PersonService::class);
		$this->eventService = $this->createMock(EventService::class);
		$this->audit = $this->createMock(AuditService::class);
		$this->db = $this->createMock(IDBConnection::class);
		$clock = $this->createMock(Clock::class);
		$clock->method('time')->willReturn(1000);
		$l10n = $this->createMock(IL10N::class);
		$l10n->method('t')->willReturnArgument(0);

		$this->service = new ImportService(
			$this->validator,
			$this->groupMapper,
			$this->functionMapper,
			$this->personMapper,
			$this->sliceMapper,
			$this->persons,
			$this->eventService,
			$this->audit,
			$this->createMock(RecipientService::class),
			$this->createMock(AccessService::class),
			$clock,
			$this->db,
			$l10n,
		);

		$nextId = 100;
		$insert = function (string $name) use (&$nextId): \Closure {
			return function (Entity $entity) use ($name, &$nextId): Entity {
				$this->writes[] = $name;
				$entity->setId(++$nextId);
				return $entity;
			};
		};
		$this->groupMapper->method('insert')->willReturnCallback($insert('group'));
		$this->functionMapper->method('insert')->willReturnCallback($insert('function'));
		$this->personMapper->method('insert')->willReturnCallback($insert('person'));
		$this->sliceMapper->method('insert')->willReturnCallback($insert('slice'));
		$this->eventService->method('syncAll')->willReturnCallback(function (): array {
			$this->writes[] = 'sync';
			return ['inserted' => 0, 'updated' => 0, 'deleted' => 0];
		});
	}

	public function testRefusesAnAppWithGroups(): void {
		$this->groupMapper->method('findAll')->willReturn([new Group()]);
		$this->assertRefused();
	}

	public function testRefusesAnAppWithPersons(): void {
		$this->personMapper->method('findAll')->willReturn([new Person()]);
		$this->assertRefused();
	}

	public function testInvalidFileWritesNothing(): void {
		$this->validator->method('validate')->willThrowException(new ValidationException('Invalid'));
		$this->db->expects($this->never())->method('beginTransaction');
		$this->eventService->expects($this->never())->method('syncAll');

		$this->expectException(ValidationException::class);
		$this->service->import('manager', []);
	}

	public function testWritesEverythingInOneTransactionAndSyncsAfterwards(): void {
		$this->validator->method('validate')->with(['file'])->willReturn($this->data());
		$this->db->expects($this->once())->method('beginTransaction');
		$this->db->expects($this->once())->method('commit');
		$this->persons->method('fieldNames')->willReturn(['first_name', 'last_name', 'date_of_birth']);
		$this->persons->method('sliceFieldNames')->willReturn(['person_id', 'function_id', 'start_date']);
		$audit = [];
		$this->audit->method('log')->willReturnCallback(static function (?string $actor, string $action, string $type, int $id, array $fields) use (&$audit): void {
			$audit[] = [$actor, $action, $type, $id, $fields];
		});

		$result = $this->service->import('manager', ['file']);

		$this->assertSame(['groups' => 1, 'functions' => 2, 'persons' => 1, 'terms' => 2], $result);
		$this->assertSame(['group', 'function', 'function', 'person', 'slice', 'slice', 'sync'], $this->writes);
		$this->assertSame([
			['manager', 'create', 'group', 101, ['name', 'jubilee_years']],
			['manager', 'create', 'function', 102, ['group_id', 'name', 'yearly_gift']],
			['manager', 'create', 'function', 103, ['group_id', 'name', 'yearly_gift']],
			['manager', 'create', 'person', 104, ['first_name', 'last_name', 'date_of_birth']],
			['manager', 'create', 'slice', 105, ['person_id', 'function_id', 'start_date']],
			['manager', 'create', 'slice', 106, ['person_id', 'function_id', 'start_date']],
		], $audit);
	}

	public function testTranslatesTheIdsOfTheFile(): void {
		$data = $this->data();
		$this->service->write('manager', $data);

		$group = $data->groups[0]['group'];
		$this->assertSame(1000, $group->getCreatedAt());
		$this->assertSame(101, $data->groups[0]['functions'][7]->getGroupId());
		$this->assertSame(101, $data->groups[0]['functions'][8]->getGroupId());

		[$first, $second] = $data->persons[0]['terms'];
		$this->assertSame(104, $first->getPersonId());
		$this->assertSame(103, $first->getFunctionId());
		$this->assertSame(102, $second->getFunctionId());
		$this->assertSame(1000, $second->getCreatedAt());
	}

	public function testRollsBackWhenAWriteFails(): void {
		$this->personMapper->method('insert')->willThrowException(new \RuntimeException('Database gone'));
		$this->db->expects($this->once())->method('rollBack');
		$this->db->expects($this->never())->method('commit');
		$this->eventService->expects($this->never())->method('syncAll');

		$this->expectException(\RuntimeException::class);
		$this->service->write('manager', $this->data());
	}

	private function assertRefused(): void {
		$this->validator->expects($this->never())->method('validate');
		$this->db->expects($this->never())->method('beginTransaction');
		$this->eventService->expects($this->never())->method('syncAll');

		$this->expectException(ConflictException::class);
		$this->service->import('manager', []);
	}

	private function data(): ImportData {
		$group = new Group();
		$group->setName('Board');
		$group->clearResponsible();
		$treasurer = new OrgFunction();
		$treasurer->setName('Treasurer');
		$president = new OrgFunction();
		$president->setName('President');

		$person = new Person();
		$person->setFirstName('Anna');
		$person->setLastName('Muster');
		$person->setDateOfBirth(Dates::parse('1986-11-01'));

		$first = new Slice();
		$first->setFunctionId(8);
		$first->setStartDate(Dates::parse('2020-01-01'));
		$second = new Slice();
		$second->setFunctionId(7);
		$second->setStartDate(Dates::parse('2021-01-01'));

		return new ImportData(
			[['group' => $group, 'functions' => [7 => $treasurer, 8 => $president]]],
			[['person' => $person, 'terms' => [$first, $second]]],
		);
	}
}
