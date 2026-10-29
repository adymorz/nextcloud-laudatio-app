<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Adrian Dymorz <dev@adrian.dymorz.ch>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Appreciation\Tests\Unit\Service;

use OCA\Appreciation\Db\Event;
use OCA\Appreciation\Db\OrgFunction;
use OCA\Appreciation\Db\OrgFunctionMapper;
use OCA\Appreciation\Db\Person;
use OCA\Appreciation\Db\PersonMapper;
use OCA\Appreciation\Service\OrdinalFormatter;
use OCA\Appreciation\Service\RecipientService;
use OCA\Appreciation\Service\TitleBuilder;
use OCP\IL10N;
use OCP\L10N\IFactory;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class TitleBuilderTest extends TestCase {
	/**
	 * @return list<array{int, string}>
	 */
	public static function englishOrdinals(): array {
		return [
			[1, '1st'], [2, '2nd'], [3, '3rd'], [4, '4th'], [10, '10th'],
			[11, '11th'], [12, '12th'], [13, '13th'], [21, '21st'], [22, '22nd'],
			[23, '23rd'], [31, '31st'], [32, '32nd'], [33, '33rd'], [101, '101st'],
			[111, '111th'], [112, '112th'], [113, '113th'],
		];
	}

	#[DataProvider('englishOrdinals')]
	public function testEnglishOrdinals(int $number, string $expected): void {
		$this->assertSame($expected, OrdinalFormatter::format($number, 'en'));
		// Languages without translation fall back to English titles
		$this->assertSame($expected, OrdinalFormatter::format($number, 'fr'));
	}

	public function testGermanOrdinals(): void {
		$this->assertSame('5.', OrdinalFormatter::format(5, 'de'));
		$this->assertSame('32.', OrdinalFormatter::format(32, 'de_CH'));
	}

	public function testTitlesPerRole(): void {
		$builder = $this->builder('en');
		$birthday = $this->event(Event::TYPE_BIRTHDAY, null, 32);
		$jubilee = $this->event(Event::TYPE_JUBILEE, 9, 5);

		$this->assertSame('32nd Birthday of Anna Muster', $builder->build($birthday, RecipientService::ROLE_RESPONSIBLE, 'en'));
		$this->assertSame('32nd Birthday of Anna Muster (for information)', $builder->build($birthday, RecipientService::ROLE_INTERESTED, 'en'));
		$this->assertSame('5th Anniversary for Anna Muster as Treasurer', $builder->build($jubilee, RecipientService::ROLE_RESPONSIBLE, 'en'));
		$this->assertSame('5th Anniversary for Anna Muster as Treasurer (for information)', $builder->build($jubilee, RecipientService::ROLE_INTERESTED, 'en'));
		$this->assertSame('5th Anniversary for Anna Muster as Treasurer', $builder->build($jubilee, null, 'en'));
	}

	public function testYearlyGiftTitle(): void {
		$builder = $this->builder('en');
		$gift = $this->event(Event::TYPE_GIFT, 9, 2026);

		$this->assertSame('Yearly gift for Anna Muster (Treasurer)', $builder->build($gift, RecipientService::ROLE_RESPONSIBLE, 'en'));
		$this->assertSame('Yearly gift for Anna Muster (Treasurer) (for information)', $builder->build($gift, RecipientService::ROLE_INTERESTED, 'en'));
	}

	public function testGermanTitlesUseGermanOrdinals(): void {
		$translations = [
			'%1$s Birthday of %2$s' => '%1$s Geburtstag von %2$s',
			'%s (for information)' => '%s (zur Information)',
		];
		$builder = $this->builder('de', $translations);
		$this->assertSame('32. Geburtstag von Anna Muster (zur Information)', $builder->build($this->event(Event::TYPE_BIRTHDAY, null, 32), RecipientService::ROLE_INTERESTED, 'de'));
	}

	/**
	 * @param array<string, string> $translations
	 */
	private function builder(string $language, array $translations = []): TitleBuilder {
		$l10n = $this->createMock(IL10N::class);
		$l10n->method('getLanguageCode')->willReturn($language);
		$l10n->method('t')->willReturnCallback(static fn (string $text, array $params = []): string => vsprintf($translations[$text] ?? $text, $params));
		$factory = $this->createMock(IFactory::class);
		$factory->method('get')->with('appreciation', $language)->willReturn($l10n);

		$person = new Person();
		$person->setFirstName('Anna');
		$person->setLastName('Muster');
		$personMapper = $this->createMock(PersonMapper::class);
		$personMapper->method('find')->willReturn($person);

		$function = new OrgFunction();
		$function->setName('Treasurer');
		$functionMapper = $this->createMock(OrgFunctionMapper::class);
		$functionMapper->method('find')->with(9)->willReturn($function);

		return new TitleBuilder($factory, $personMapper, $functionMapper);
	}

	private function event(string $type, ?int $functionId, int $ordinal): Event {
		$event = new Event();
		$event->setType($type);
		$event->setPersonId(1);
		$event->setFunctionId($functionId);
		$event->setOrdinal($ordinal);
		return $event;
	}
}
