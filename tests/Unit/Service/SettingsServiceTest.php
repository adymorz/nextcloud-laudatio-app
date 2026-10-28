<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Adrian Dymorz <dev@adrian.dymorz.ch>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Appreciation\Tests\Unit\Service;

use OCA\Appreciation\Exception\ValidationException;
use OCA\Appreciation\Service\SettingsService;
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

	public function testUserEmailSettingDefaultsToAdminDefault(): void {
		$this->appConfig->method('getValueBool')->willReturnMap([
			['appreciation', SettingsService::EMAIL_DEFAULT, false, false, true],
		]);
		$this->userConfig->expects($this->once())
			->method('getValueBool')
			->with('alice', 'appreciation', SettingsService::EMAIL_NOTIFICATIONS, true)
			->willReturn(true);
		$this->assertTrue($this->settings->isEmailEnabledForUser('alice'));
	}
}
