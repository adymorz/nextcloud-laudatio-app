<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Adrian Dymorz <dev@adrian.dymorz.ch>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Laudatio\Tests\Unit\Service;

use OCA\Laudatio\Db\Person;
use OCA\Laudatio\Db\PersonMapper;
use OCA\Laudatio\Db\Slice;
use OCA\Laudatio\Db\SliceMapper;
use OCA\Laudatio\Exception\ValidationException;
use OCA\Laudatio\Model\PersonInput;
use OCA\Laudatio\Model\SliceInput;
use OCA\Laudatio\Service\AccessService;
use OCA\Laudatio\Service\AuditService;
use OCA\Laudatio\Service\Clock;
use OCA\Laudatio\Service\Dates;
use OCA\Laudatio\Service\EventService;
use OCA\Laudatio\Service\PersonService;
use OCA\Laudatio\Service\RecipientService;
use OCA\Laudatio\Service\SliceValidator;
use OCP\IDBConnection;
use OCP\IL10N;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class PersonServiceTest extends TestCase {
	private PersonMapper&MockObject $personMapper;
	private SliceMapper&MockObject $sliceMapper;
	private AuditService&MockObject $audit;
	private PersonService $service;

	#[\Override]
	protected function setUp(): void {
		$this->personMapper = $this->createMock(PersonMapper::class);
		$this->sliceMapper = $this->createMock(SliceMapper::class);
		$this->audit = $this->createMock(AuditService::class);
		$clock = $this->createMock(Clock::class);
		$clock->method('today')->willReturn(Dates::parse('2026-10-29'));
		$clock->method('time')->willReturn(1000);
		$l10n = $this->createMock(IL10N::class);
		$l10n->method('t')->willReturnCallback(static fn (string $text, array $params = []): string => vsprintf($text, $params));
		$this->service = new PersonService(
			$this->personMapper,
			$this->sliceMapper,
			$this->createMock(SliceValidator::class),
			$this->createMock(EventService::class),
			$this->audit,
			$this->createMock(RecipientService::class),
			$this->createMock(AccessService::class),
			$clock,
			$this->createMock(IDBConnection::class),
			$l10n,
		);
	}

	public function testContactDataIsTrimmedAndEmptyBecomesNull(): void {
		$this->personMapper->method('insert')->willReturnCallback(static function (Person $person): Person {
			$person->setId(1);
			return $person;
		});
		$person = $this->service->create(null, new PersonInput(
			'Anna',
			'Muster',
			'1986-11-01',
			street: ' Bahnhofstrasse ',
			streetNumber: '12a',
			postalCode: ' ',
			country: '',
			mobilePhone: '+41 (0)79 123 45-67',
			otherPhone: '044/123.45.67',
		));
		$this->assertSame('Bahnhofstrasse', $person->getStreet());
		$this->assertSame('12a', $person->getStreetNumber());
		$this->assertNull($person->getPostalCode());
		$this->assertNull($person->getCity());
		$this->assertNull($person->getCountry());
		$this->assertSame('+41 (0)79 123 45-67', $person->getMobilePhone());
		$this->assertSame('044/123.45.67', $person->getOtherPhone());
	}

	/**
	 * @return array<string, array{PersonInput}>
	 */
	public static function invalidInput(): array {
		$person = static fn (mixed ...$contact): PersonInput => new PersonInput('Anna', 'Muster', '1986-11-01', ...$contact);
		return [
			'letters in the mobile phone' => [$person(mobilePhone: '079 CALL ME')],
			'no digit in the other phone' => [$person(otherPhone: '+ ( )')],
			'phone too long' => [$person(mobilePhone: str_repeat('1', 33))],
			'street too long' => [$person(street: str_repeat('a', 256))],
			'ZIP code too long' => [$person(postalCode: str_repeat('1', 33))],
			'country too long' => [$person(country: str_repeat('a', 256))],
		];
	}

	#[DataProvider('invalidInput')]
	public function testInvalidContactDataIsRejected(PersonInput $input): void {
		$this->personMapper->expects($this->never())->method('insert');
		$this->expectException(ValidationException::class);
		$this->service->create(null, $input);
	}

	/**
	 * @return array<string, array{string, ?string}>
	 */
	public static function termsOutOfRange(): array {
		return [
			'start before 1900' => ['1899-12-31', null],
			'end after 2200' => ['2020-01-01', '2201-01-01'],
		];
	}

	#[DataProvider('termsOutOfRange')]
	public function testTermsMustBeWithinPlausibleYears(string $start, ?string $end): void {
		$this->personMapper->method('find')->willReturn(new Person());
		$this->expectException(ValidationException::class);
		$this->expectExceptionMessage('between 1900 and 2200');
		$this->service->addSlice(null, 1, new SliceInput(10, $start, $end));
	}

	public function testTermTextsAreTrimmedAndEmptyBecomesNull(): void {
		$slice = $this->service->buildSlice(new SliceInput(
			10,
			'2020-01-01',
			startDateSource: ' Minutes ',
			endDateSource: ' ',
			notes: "\nFirst line\nSecond line\n",
		));
		$this->assertSame('Minutes', $slice->getStartDateSource());
		$this->assertNull($slice->getEndDateSource());
		$this->assertSame("First line\nSecond line", $slice->getNotes());
	}

	/**
	 * @return array<string, array{SliceInput}>
	 */
	public static function invalidTermTexts(): array {
		return [
			'start source too long' => [new SliceInput(10, '2020-01-01', startDateSource: str_repeat('a', 256))],
			'end source too long' => [new SliceInput(10, '2020-01-01', endDateSource: str_repeat('a', 256))],
			'notes too long' => [new SliceInput(10, '2020-01-01', notes: str_repeat('a', 10001))],
		];
	}

	#[DataProvider('invalidTermTexts')]
	public function testInvalidTermTextsAreRejected(SliceInput $input): void {
		$this->personMapper->method('find')->willReturn(new Person());
		$this->sliceMapper->expects($this->never())->method('insert');
		$this->expectException(ValidationException::class);
		$this->service->addSlice(null, 1, $input);
	}

	public function testNewTermLogsTheFilledFields(): void {
		$this->personMapper->method('find')->willReturn(new Person());
		$this->sliceMapper->method('insert')->willReturnCallback(static function (Slice $slice): Slice {
			$slice->setId(5);
			return $slice;
		});
		$this->audit->expects($this->once())->method('log')
			->with(null, 'create', 'slice', 5, ['person_id', 'function_id', 'start_date', 'notes']);
		$this->service->addSlice(null, 1, new SliceInput(10, '2020-01-01', notes: 'Interim'));
	}

	public function testUpdateLogsOnlyTheChangedFields(): void {
		$this->sliceMapper->method('find')->willReturn($this->storedSlice());
		$this->sliceMapper->expects($this->once())->method('update');
		$this->audit->expects($this->once())->method('log')
			->with(null, 'update', 'slice', 5, ['end_date_source', 'notes']);
		$this->service->updateSlice(null, 5, new SliceInput(10, '2020-01-01', startDateSource: 'Minutes', endDateSource: 'Letter', notes: 'Interim'));
	}

	public function testUpdateClearsTheTextsThatAreLeftOut(): void {
		$this->sliceMapper->method('find')->willReturn($this->storedSlice());
		$this->audit->expects($this->once())->method('log')
			->with(null, 'update', 'slice', 5, ['start_date_source']);
		$slice = $this->service->updateSlice(null, 5, new SliceInput(10, '2020-01-01'));
		$this->assertNull($slice->getStartDateSource());
	}

	public function testUnchangedUpdateWritesNothing(): void {
		$this->sliceMapper->method('find')->willReturn($this->storedSlice());
		$this->sliceMapper->expects($this->never())->method('update');
		$this->audit->expects($this->never())->method('log');
		$this->service->updateSlice(null, 5, new SliceInput(10, '2020-01-01', startDateSource: 'Minutes'));
	}

	private function storedSlice(): Slice {
		$slice = new Slice();
		$slice->setId(5);
		$slice->setPersonId(1);
		$slice->setFunctionId(10);
		$slice->setStartDate(Dates::parse('2020-01-01') ?? throw new \LogicException());
		$slice->setStartDateSource('Minutes');
		$slice->resetUpdatedFields();
		return $slice;
	}
}
