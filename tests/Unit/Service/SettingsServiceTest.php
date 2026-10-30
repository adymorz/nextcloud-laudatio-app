<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Adrian Dymorz <dev@adrian.dymorz.ch>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Laudatio\Tests\Unit\Service;

use OCA\Laudatio\Exception\ValidationException;
use OCA\Laudatio\Service\SettingsService;
use OCP\Config\IUserConfig;
use OCP\IAppConfig;
use OCP\IL10N;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class SettingsServiceTest extends TestCase {
	private IAppConfig&MockObject $appConfig;
	private IUserConfig&MockObject $userConfig;
	private SettingsService $settings;

	#[\Override]
	protected function setUp(): void {
		$this->appConfig = $this->createMock(IAppConfig::class);
		$this->userConfig = $this->createMock(IUserConfig::class);
		$l10n = $this->createMock(IL10N::class);
		$l10n->method('t')->willReturnCallback(static fn (string $text, array $params = []): string => vsprintf($text, $params));
		$this->settings = new SettingsService($this->appConfig, $this->userConfig, $l10n);
	}

	/**
	 * @return array<string, array{string, list<int>}>
	 */
	public static function validYears(): array {
		return [
			'default format' => ['1, 5, 10, 15, 20, 25, 40', [1, 5, 10, 15, 20, 25, 40]],
			'unsorted with duplicates' => ['25,5 5;10', [5, 10, 25]],
			'empty' => ['  ', []],
		];
	}

	/**
	 * @param list<int> $expected
	 */
	#[DataProvider('validYears')]
	public function testParseJubileeYears(string $value, array $expected): void {
		$this->assertSame($expected, $this->settings->parseJubileeYears($value));
	}

	/**
	 * @return list<array{string}>
	 */
	public static function invalidYears(): array {
		return [['0'], ['5, ten'], ['101'], ['-5'], ['2.5']];
	}

	#[DataProvider('invalidYears')]
	public function testParseJubileeYearsRejectsInvalidValues(string $value): void {
		$this->expectException(ValidationException::class);
		$this->settings->parseJubileeYears($value);
	}

	public function testBrokenStoredYearsFallBackToDefault(): void {
		$this->appConfig->method('getValueString')->willReturn('garbage');
		$this->assertSame(SettingsService::DEFAULT_JUBILEE_YEARS, $this->settings->getJubileeYears());
	}

	public function testLeadTimeIsValidated(): void {
		$this->appConfig->expects($this->never())->method('setValueInt');
		$this->expectException(ValidationException::class);
		$this->settings->setLeadTimeDays(366);
	}

	/**
	 * @return array<string, array{string, string}>
	 */
	public static function validRegulationsUrls(): array {
		return [
			'internal link' => [' https://cloud.example.com/f/123 ', 'https://cloud.example.com/f/123'],
			'external page' => ['http://intranet/regulations.pdf?v=2#gifts', 'http://intranet/regulations.pdf?v=2#gifts'],
			'upper case scheme' => ['HTTPS://example.com', 'HTTPS://example.com'],
			'removed' => ['  ', ''],
		];
	}

	#[DataProvider('validRegulationsUrls')]
	public function testRegulationsUrlIsStored(string $value, string $stored): void {
		$this->appConfig->expects($this->once())->method('setValueString')->with('laudatio', SettingsService::REGULATIONS_URL, $stored);
		$this->settings->setRegulationsUrl($value);
	}

	/**
	 * @return array<string, array{string}>
	 */
	public static function invalidRegulationsUrls(): array {
		return [
			'script' => ['javascript:alert(1)'],
			'other scheme' => ['ftp://example.com/regulations.pdf'],
			'no scheme' => ['cloud.example.com/f/123'],
			'relative' => ['/f/123'],
			'no host' => ['https://'],
			'space' => ['https://example.com/a b'],
			'too long' => ['https://example.com/' . str_repeat('a', 2000)],
		];
	}

	#[DataProvider('invalidRegulationsUrls')]
	public function testInvalidRegulationsUrlIsRejected(string $value): void {
		$this->appConfig->expects($this->never())->method('setValueString');
		$this->expectException(ValidationException::class);
		$this->settings->setRegulationsUrl($value);
	}

	public function testEmptyRegulationsUrlIsNull(): void {
		$this->appConfig->method('getValueString')->willReturn('');
		$this->assertNull($this->settings->getRegulationsUrl());
	}

	public function testSheetNoteIsTrimmedAndStored(): void {
		$this->appConfig->expects($this->once())->method('setValueString')->with('laudatio', SettingsService::SHEET_NOTE, 'Stored by the Example Club.');
		$this->settings->setSheetNote("  Stored by the Example Club. \n");
	}

	public function testTooLongSheetNoteIsRejected(): void {
		$this->appConfig->expects($this->never())->method('setValueString');
		$this->expectException(ValidationException::class);
		$this->settings->setSheetNote(str_repeat('a', 2001));
	}

	public function testEmptySheetNoteIsNull(): void {
		$this->appConfig->method('getValueString')->willReturn('');
		$this->assertNull($this->settings->getSheetNote());
	}

	public function testUserEmailSettingDefaultsToAdminDefault(): void {
		$this->appConfig->method('getValueBool')->willReturnMap([
			['laudatio', SettingsService::EMAIL_DEFAULT, false, false, true],
		]);
		$this->userConfig->expects($this->once())
			->method('getValueBool')
			->with('alice', 'laudatio', SettingsService::EMAIL_NOTIFICATIONS, true)
			->willReturn(true);
		$this->assertTrue($this->settings->isEmailEnabledForUser('alice'));
	}
}
