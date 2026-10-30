<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Adrian Dymorz <dev@adrian.dymorz.ch>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Laudatio\Tests\Unit\Service;

use OCA\Laudatio\Db\GroupMapper;
use OCA\Laudatio\Db\InterestedMapper;
use OCA\Laudatio\Db\OrgFunctionMapper;
use OCA\Laudatio\Db\PersonMapper;
use OCA\Laudatio\Db\SliceMapper;
use OCA\Laudatio\Exception\ValidationException;
use OCA\Laudatio\Service\AccessService;
use OCA\Laudatio\Service\AuditService;
use OCA\Laudatio\Service\Clock;
use OCA\Laudatio\Service\Dates;
use OCA\Laudatio\Service\EventService;
use OCA\Laudatio\Service\GroupService;
use OCA\Laudatio\Service\ImportValidator;
use OCA\Laudatio\Service\NotificationService;
use OCA\Laudatio\Service\PersonService;
use OCA\Laudatio\Service\RecipientService;
use OCA\Laudatio\Service\SettingsService;
use OCA\Laudatio\Service\SliceValidator;
use OCP\IDBConnection;
use OCP\IL10N;
use OCP\IUserManager;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Runs against the real validation of the services, which are only cut off from
 * the database.
 */
final class ImportValidatorTest extends TestCase {
	/**
	 * A file of format version 1 as it was written by the app. Don't change it
	 * when the format changes: old backups must stay readable.
	 */
	private const VERSION_1 = <<<'JSON'
		{
			"format": "laudatio-export",
			"version": 1,
			"exportedAt": "2026-10-30",
			"groups": [
				{
					"name": "Board",
					"jubileeYears": "5, 10",
					"functions": [
						{ "id": 3, "name": "Treasurer", "yearlyGift": true },
						{ "id": 4, "name": "President", "yearlyGift": false }
					]
				},
				{ "name": "Empty", "jubileeYears": null, "functions": [] }
			],
			"persons": [
				{
					"firstName": "Anna",
					"lastName": "Muster",
					"dateOfBirth": "1986-11-01",
					"notes": "Prefers flowers",
					"street": "Bahnhofstrasse",
					"streetNumber": "12a",
					"postalCode": "8001",
					"city": "Zürich",
					"country": "Switzerland",
					"mobilePhone": "+41 79 123 45 67",
					"otherPhone": null,
					"terms": [
						{ "functionId": 3, "startDate": "2015-01-01", "endDate": "2018-12-31" },
						{ "functionId": 3, "startDate": "2019-01-01", "endDate": null },
						{ "functionId": 4, "startDate": "2019-01-01", "endDate": null }
					]
				},
				{
					"firstName": "Beat",
					"lastName": "Beispiel",
					"dateOfBirth": "1970-02-03",
					"notes": null,
					"street": null,
					"streetNumber": null,
					"postalCode": null,
					"city": null,
					"country": null,
					"mobilePhone": null,
					"otherPhone": null,
					"terms": []
				}
			]
		}
		JSON;

	private ImportValidator $validator;

	#[\Override]
	protected function setUp(): void {
		$l10n = $this->createMock(IL10N::class);
		$l10n->method('t')->willReturnCallback(static fn (string $text, array $params = []): string => vsprintf($text, $params));
		$clock = $this->createMock(Clock::class);
		$clock->method('today')->willReturn(Dates::parse('2026-10-30'));
		$settings = $this->createMock(SettingsService::class);
		$settings->method('parseJubileeYears')->willReturnCallback(static function (string $years): array {
			if ($years === 'many') {
				throw new ValidationException('Invalid years');
			}
			return [5, 10];
		});

		$sliceValidator = new SliceValidator(
			$this->createMock(SliceMapper::class),
			$this->createMock(OrgFunctionMapper::class),
			$l10n,
		);
		$groups = new GroupService(
			$this->createMock(GroupMapper::class),
			$this->createMock(InterestedMapper::class),
			$this->createMock(OrgFunctionMapper::class),
			$this->createMock(SliceMapper::class),
			$settings,
			$this->createMock(EventService::class),
			$this->createMock(AuditService::class),
			$this->createMock(RecipientService::class),
			$this->createMock(AccessService::class),
			$this->createMock(NotificationService::class),
			$clock,
			$this->createMock(IUserManager::class),
			$this->createMock(IDBConnection::class),
			$l10n,
		);
		$persons = new PersonService(
			$this->createMock(PersonMapper::class),
			$this->createMock(SliceMapper::class),
			$sliceValidator,
			$this->createMock(EventService::class),
			$this->createMock(AuditService::class),
			$this->createMock(RecipientService::class),
			$this->createMock(AccessService::class),
			$clock,
			$this->createMock(IDBConnection::class),
			$l10n,
		);
		$this->validator = new ImportValidator($groups, $persons, $sliceValidator, $l10n);
	}

	public function testReadsVersion1(): void {
		$data = $this->validator->validate(self::sample());

		$this->assertCount(2, $data->groups);
		$group = $data->groups[0]['group'];
		$this->assertSame('Board', $group->getName());
		$this->assertSame('5, 10', $group->getJubileeYears());
		$this->assertSame([3, 4], array_keys($data->groups[0]['functions']));
		$this->assertSame('Treasurer', $data->groups[0]['functions'][3]->getName());
		$this->assertTrue($data->groups[0]['functions'][3]->isYearlyGift());
		$this->assertFalse($data->groups[0]['functions'][4]->isYearlyGift());
		$this->assertNull($data->groups[1]['group']->getJubileeYears());
		$this->assertSame([], $data->groups[1]['functions']);

		$this->assertCount(2, $data->persons);
		$person = $data->persons[0]['person'];
		$this->assertSame('Anna Muster', $person->getDisplayName());
		$this->assertSame('1986-11-01', Dates::format($person->getDateOfBirth()));
		$this->assertSame('Prefers flowers', $person->getNotes());
		$this->assertSame('Bahnhofstrasse', $person->getStreet());
		$this->assertSame('12a', $person->getStreetNumber());
		$this->assertSame('8001', $person->getPostalCode());
		$this->assertSame('Zürich', $person->getCity());
		$this->assertSame('Switzerland', $person->getCountry());
		$this->assertSame('+41 79 123 45 67', $person->getMobilePhone());
		$this->assertNull($person->getOtherPhone());

		$terms = $data->persons[0]['terms'];
		$this->assertCount(3, $terms);
		$this->assertSame(3, $terms[0]->getFunctionId());
		$this->assertSame('2015-01-01', Dates::format($terms[0]->getStartDate()));
		$this->assertSame('2018-12-31', Dates::format($terms[0]->getEndDate() ?? throw new \LogicException()));
		$this->assertNull($terms[1]->getEndDate());
		$this->assertSame([], $data->persons[1]['terms']);
	}

	public function testGroupsHaveNoResponsibleUser(): void {
		$sample = self::sample();
		$sample['groups'][0]['responsible'] = 'alice';
		$sample['groups'][0]['responsibleUid'] = 'alice';
		$sample['groups'][0]['interested'] = ['bob'];

		$group = $this->validator->validate($sample)->groups[0]['group'];

		$this->assertSame('', $group->getResponsibleUid());
		$this->assertArrayHasKey('responsibleUid', $group->getUpdatedFields(), 'The NOT NULL column must be part of the insert');
	}

	public function testRefusesNewerVersionsWithAHint(): void {
		$sample = self::sample();
		$sample['version'] = 2;
		$this->expectException(ValidationException::class);
		$this->expectExceptionMessage('format version 2, but this version of Laudatio only reads up to version 1');
		$this->validator->validate($sample);
	}

	/**
	 * @param \Closure(array<array-key, mixed>): array<array-key, mixed> $change
	 */
	#[DataProvider('invalidFiles')]
	public function testRejectsInvalidFiles(\Closure $change, string $message): void {
		$this->expectException(ValidationException::class);
		$this->expectExceptionMessage($message);
		$this->validator->validate($change(self::sample()));
	}

	/**
	 * @return array<string, array{\Closure(array<array-key, mixed>): array<array-key, mixed>, string}>
	 */
	public static function invalidFiles(): array {
		$set = static fn (array $path, mixed $value): \Closure => static function (array $data) use ($path, $value): array {
			$target = &$data;
			foreach ($path as $key) {
				$target = &$target[$key];
			}
			$target = $value;
			return $data;
		};
		$person = 'Person 1 "Anna Muster": ';

		return [
			'other JSON' => [static fn (array $data): array => ['foo' => 'bar'], 'This is not a Laudatio export file.'],
			'wrong format' => [$set(['format'], 'other'), 'This is not a Laudatio export file.'],
			'version missing' => [static function (array $data): array {
				unset($data['version']);
				return $data;
			}, 'This is not a Laudatio export file.'],
			'version as text' => [$set(['version'], '1'), 'This is not a Laudatio export file.'],
			'version 0' => [$set(['version'], 0), 'This is not a Laudatio export file.'],
			'groups missing' => [static function (array $data): array {
				unset($data['groups']);
				return $data;
			}, 'The value of "groups" is missing or invalid.'],
			'persons not a list' => [$set(['persons'], ['a' => []]), 'The value of "persons" is missing or invalid.'],
			'group not an object' => [$set(['groups', 1], 'Board'), 'The value of "groups" is missing or invalid.'],
			'group name empty' => [$set(['groups', 1, 'name'], ' '), 'Group 2 " ": The name is required.'],
			'group name no text' => [$set(['groups', 1, 'name'], 5), 'Group 2 "": The value of "name" is missing or invalid.'],
			'jubilee years' => [$set(['groups', 0, 'jubileeYears'], 'many'), 'Group 1 "Board": Invalid years'],
			'function without ID' => [$set(['groups', 0, 'functions', 0, 'id'], '3'), 'Group 1 "Board": Every function needs its own ID.'],
			'function ID twice' => [$set(['groups', 1, 'functions'], [['id' => 3, 'name' => 'Other', 'yearlyGift' => false]]), 'Group 2 "Empty": Every function needs its own ID.'],
			'function name too long' => [$set(['groups', 0, 'functions', 0, 'name'], str_repeat('x', 256)), 'Group 1 "Board": Names must not be longer than 255 characters.'],
			'yearly gift no bool' => [$set(['groups', 0, 'functions', 0, 'yearlyGift'], 'yes'), 'Group 1 "Board": The value of "yearlyGift" is missing or invalid.'],
			'person without name' => [$set(['persons', 1, 'firstName'], null), 'Person 2 "Beispiel": The value of "firstName" is missing or invalid.'],
			'date of birth' => [$set(['persons', 0, 'dateOfBirth'], '1986-13-01'), $person . 'The date of birth must be a valid date (YYYY-MM-DD).'],
			'phone' => [$set(['persons', 0, 'otherPhone'], 'call me'), $person . 'Additional phone number may only contain digits'],
			'notes no text' => [$set(['persons', 0, 'notes'], 5), $person . 'The value of "notes" is missing or invalid.'],
			'terms missing' => [$set(['persons', 0, 'terms'], null), $person . 'The value of "terms" is missing or invalid.'],
			'unknown function' => [$set(['persons', 0, 'terms', 0, 'functionId'], 99), $person . 'A term refers to a function that is not in the file.'],
			'term dates' => [$set(['persons', 0, 'terms', 0, 'startDate'], '01.01.2015'), $person . 'Start and end date must be valid dates (YYYY-MM-DD).'],
			'end before start' => [$set(['persons', 0, 'terms', 0, 'endDate'], '2014-12-31'), $person . 'The end date must not be before the start date.'],
			'overlapping terms' => [$set(['persons', 0, 'terms', 1, 'startDate'], '2018-12-31'), $person . 'The term overlaps another term of this person in the same function.'],
		];
	}

	/**
	 * @return array<array-key, mixed>
	 */
	private static function sample(): array {
		/** @var array<array-key, mixed> */
		return json_decode(self::VERSION_1, true, 512, JSON_THROW_ON_ERROR);
	}
}
