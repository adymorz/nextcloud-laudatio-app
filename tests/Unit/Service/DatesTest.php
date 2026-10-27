<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Adrian Dymorz <dev@adrian.dymorz.ch>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Appreciation\Tests\Unit\Service;

use DateTimeImmutable;
use DateTimeZone;
use OCA\Appreciation\Service\Dates;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class DatesTest extends TestCase {
	public function testParseAcceptsStrictDates(): void {
		$date = Dates::parse('2024-02-29');
		$this->assertNotNull($date);
		$this->assertSame('2024-02-29', Dates::format($date));
		$this->assertSame('UTC', $date->getTimezone()->getName());
		$this->assertSame('00:00:00', $date->format('H:i:s'));
	}

	/**
	 * @return list<array{string}>
	 */
	public static function invalidDates(): array {
		return [['2023-02-29'], ['2024-13-01'], ['2024-1-01'], ['01.02.2024'], [''], ['2024-01-01T00:00:00']];
	}

	#[DataProvider('invalidDates')]
	public function testParseRejectsInvalidDates(string $value): void {
		$this->assertNull(Dates::parse($value));
	}

	public function testNormalizeKeepsTheLocalDay(): void {
		$lateEvening = new DateTimeImmutable('2026-10-27 23:30:00', new DateTimeZone('Europe/Zurich'));
		$this->assertSame('2026-10-27', Dates::format(Dates::normalize($lateEvening)));
	}

	/**
	 * @return list<array{string, int, string}>
	 */
	public static function anniversaries(): array {
		return [
			['2020-06-15', 5, '2025-06-15'],
			['2000-02-29', 1, '2001-02-28'],
			['2000-02-29', 4, '2004-02-29'],
			['1999-12-31', 1, '2000-12-31'],
		];
	}

	#[DataProvider('anniversaries')]
	public function testAnniversary(string $start, int $years, string $expected): void {
		$this->assertSame($expected, Dates::format(Dates::anniversary($this->date($start), $years)));
	}

	public function testAddDays(): void {
		$this->assertSame('2026-11-10', Dates::format(Dates::addDays($this->date('2026-10-27'), 14)));
		$this->assertSame('2026-10-20', Dates::format(Dates::addDays($this->date('2026-10-27'), -7)));
	}

	public function testYearsBetween(): void {
		$this->assertSame(31, Dates::yearsBetween($this->date('1994-10-28'), $this->date('2026-10-27')));
		$this->assertSame(32, Dates::yearsBetween($this->date('1994-10-27'), $this->date('2026-10-27')));
	}

	private function date(string $value): DateTimeImmutable {
		$date = Dates::parse($value);
		$this->assertNotNull($date);
		return $date;
	}
}
