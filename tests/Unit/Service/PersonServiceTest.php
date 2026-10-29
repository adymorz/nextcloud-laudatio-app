<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Adrian Dymorz <dev@adrian.dymorz.ch>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Appreciation\Tests\Unit\Service;

use OCA\Appreciation\Db\Person;
use OCA\Appreciation\Db\PersonMapper;
use OCA\Appreciation\Db\SliceMapper;
use OCA\Appreciation\Exception\ValidationException;
use OCA\Appreciation\Model\PersonInput;
use OCA\Appreciation\Service\AccessService;
use OCA\Appreciation\Service\AuditService;
use OCA\Appreciation\Service\Clock;
use OCA\Appreciation\Service\Dates;
use OCA\Appreciation\Service\EventService;
use OCA\Appreciation\Service\PersonService;
use OCA\Appreciation\Service\RecipientService;
use OCA\Appreciation\Service\SliceValidator;
use OCP\IDBConnection;
use OCP\IL10N;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class PersonServiceTest extends TestCase {
	private PersonMapper&MockObject $personMapper;
	private PersonService $service;

	#[\Override]
	protected function setUp(): void {
		$this->personMapper = $this->createMock(PersonMapper::class);
		$clock = $this->createMock(Clock::class);
		$clock->method('today')->willReturn(Dates::parse('2026-10-29'));
		$clock->method('time')->willReturn(1000);
		$l10n = $this->createMock(IL10N::class);
		$l10n->method('t')->willReturnCallback(static fn (string $text, array $params = []): string => vsprintf($text, $params));
		$this->service = new PersonService(
			$this->personMapper,
			$this->createMock(SliceMapper::class),
			$this->createMock(SliceValidator::class),
			$this->createMock(EventService::class),
			$this->createMock(AuditService::class),
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
}
