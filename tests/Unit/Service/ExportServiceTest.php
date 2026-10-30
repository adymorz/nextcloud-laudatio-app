<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Adrian Dymorz <dev@adrian.dymorz.ch>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Laudatio\Tests\Unit\Service;

use OCA\Laudatio\Db\Group;
use OCA\Laudatio\Db\OrgFunction;
use OCA\Laudatio\Db\Person;
use OCA\Laudatio\Db\Slice;
use OCA\Laudatio\Service\AuditService;
use OCA\Laudatio\Service\Clock;
use OCA\Laudatio\Service\Dates;
use OCA\Laudatio\Service\ExportService;
use OCA\Laudatio\Service\GroupService;
use OCA\Laudatio\Service\PersonService;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class ExportServiceTest extends TestCase {
	private GroupService&MockObject $groups;
	private PersonService&MockObject $persons;
	private AuditService&MockObject $audit;
	private ExportService $service;

	#[\Override]
	protected function setUp(): void {
		$this->groups = $this->createMock(GroupService::class);
		$this->persons = $this->createMock(PersonService::class);
		$this->audit = $this->createMock(AuditService::class);
		$clock = $this->createMock(Clock::class);
		$clock->method('today')->willReturn(Dates::parse('2026-10-30'));
		$this->service = new ExportService($this->groups, $this->persons, $this->audit, $clock);

		$group = new Group();
		$group->setId(7);
		$group->setName('Board');
		$group->setResponsibleUid('secret-user');
		$group->setJubileeYears('5, 10');
		$empty = new Group();
		$empty->setId(8);
		$empty->setName('Empty');

		$function = new OrgFunction();
		$function->setId(3);
		$function->setGroupId(7);
		$function->setName('Treasurer');
		$function->setYearlyGift(true);

		$person = new Person();
		$person->setId(12);
		$person->setFirstName('Anna');
		$person->setLastName('Muster');
		$person->setDateOfBirth(Dates::parse('1986-11-01'));
		$person->setNotes('Prefers flowers');
		$person->setCity('Bern');

		$open = new Slice();
		$open->setPersonId(12);
		$open->setFunctionId(3);
		$open->setStartDate(Dates::parse('2021-01-01'));
		$closed = new Slice();
		$closed->setPersonId(12);
		$closed->setFunctionId(3);
		$closed->setStartDate(Dates::parse('2015-01-01'));
		$closed->setEndDate(Dates::parse('2018-12-31'));
		$closed->setStartDateSource('Minutes 2014');
		$closed->setEndDateSource('Resignation letter');
		$closed->setNotes('Interim at first');

		$this->groups->method('findAll')->willReturn([$group, $empty]);
		$this->groups->method('getFunctions')->willReturn([$function]);
		$this->persons->method('findAll')->willReturn([$person]);
		$this->persons->method('getSlicesByPerson')->with([12])->willReturn([12 => [$closed, $open]]);
	}

	public function testExportsAllDataWithVersion(): void {
		$this->assertSame([
			'format' => 'laudatio-export',
			'version' => 2,
			'exportedAt' => '2026-10-30',
			'groups' => [
				[
					'name' => 'Board',
					'jubileeYears' => '5, 10',
					'functions' => [['id' => 3, 'name' => 'Treasurer', 'yearlyGift' => true]],
				],
				['name' => 'Empty', 'jubileeYears' => null, 'functions' => []],
			],
			'persons' => [
				[
					'firstName' => 'Anna',
					'lastName' => 'Muster',
					'dateOfBirth' => '1986-11-01',
					'notes' => 'Prefers flowers',
					'street' => null,
					'streetNumber' => null,
					'postalCode' => null,
					'city' => 'Bern',
					'country' => null,
					'mobilePhone' => null,
					'otherPhone' => null,
					'terms' => [
						[
							'functionId' => 3,
							'startDate' => '2015-01-01',
							'endDate' => '2018-12-31',
							'startDateSource' => 'Minutes 2014',
							'endDateSource' => 'Resignation letter',
							'notes' => 'Interim at first',
						],
						[
							'functionId' => 3,
							'startDate' => '2021-01-01',
							'endDate' => null,
							'startDateSource' => null,
							'endDateSource' => null,
							'notes' => null,
						],
					],
				],
			],
		], $this->service->export('manager'));
	}

	public function testContainsNoReferencesToUsers(): void {
		$this->groups->expects($this->never())->method('getInterested');
		$json = json_encode($this->service->export('manager'), JSON_THROW_ON_ERROR);
		$this->assertStringNotContainsString('secret-user', $json);
		$this->assertStringNotContainsString('responsible', $json);
		$this->assertStringNotContainsString('interested', $json);
		$this->assertStringNotContainsString('manager', $json);
	}

	public function testExportIsAudited(): void {
		$this->audit->expects($this->once())->method('log')->with('manager', 'export', 'data', 0);
		$this->service->export('manager');
	}
}
