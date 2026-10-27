<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Adrian Dymorz <dev@adrian.dymorz.ch>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Appreciation\Service;

use DateTimeImmutable;
use DateTimeZone;

/**
 * Helpers for calendar dates (no time of day). All dates are represented as
 * DateTimeImmutable at midnight UTC and compared by their Y-m-d value.
 */
final class Dates {
	private const string FORMAT = 'Y-m-d';

	/**
	 * Parses a strict Y-m-d string, returns null for anything else.
	 */
	public static function parse(string $value): ?DateTimeImmutable {
		$date = DateTimeImmutable::createFromFormat('!' . self::FORMAT, $value, new DateTimeZone('UTC'));
		if ($date === false || $date->format(self::FORMAT) !== $value) {
			return null;
		}
		return $date;
	}

	public static function format(DateTimeImmutable $date): string {
		return $date->format(self::FORMAT);
	}

	/**
	 * Same day in UTC midnight representation, whatever the input timezone.
	 */
	public static function normalize(DateTimeImmutable $date): DateTimeImmutable {
		/** @var DateTimeImmutable $normalized format() output always parses */
		$normalized = self::parse($date->format(self::FORMAT));
		return $normalized;
	}

	public static function compare(DateTimeImmutable $a, DateTimeImmutable $b): int {
		return self::format($a) <=> self::format($b);
	}

	public static function addDays(DateTimeImmutable $date, int $days): DateTimeImmutable {
		$interval = new \DateInterval('P' . abs($days) . 'D');
		$normalized = self::normalize($date);
		return $days >= 0 ? $normalized->add($interval) : $normalized->sub($interval);
	}

	/**
	 * The date $years years after $date. February 29 falls on February 28 in
	 * years that are not leap years.
	 */
	public static function anniversary(DateTimeImmutable $date, int $years): DateTimeImmutable {
		$year = (int)$date->format('Y') + $years;
		$month = (int)$date->format('n');
		$day = (int)$date->format('j');
		if ($month === 2 && $day === 29 && !checkdate(2, 29, $year)) {
			$day = 28;
		}
		/** @var DateTimeImmutable $result always a valid date */
		$result = self::parse(sprintf('%04d-%02d-%02d', $year, $month, $day));
		return $result;
	}

	/**
	 * Full years between $from and $to (e.g. the age on a day).
	 */
	public static function yearsBetween(DateTimeImmutable $from, DateTimeImmutable $to): int {
		$years = (int)$to->format('Y') - (int)$from->format('Y');
		if ($to->format('md') < $from->format('md')) {
			$years--;
		}
		return $years;
	}
}
