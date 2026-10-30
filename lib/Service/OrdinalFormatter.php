<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Adrian Dymorz <dev@adrian.dymorz.ch>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Laudatio\Service;

/**
 * Ordinal numbers for event titles. German uses "5.", every other language
 * gets English ordinals because the titles fall back to English for them.
 */
final class OrdinalFormatter {
	public static function format(int $number, string $languageCode): string {
		if (self::isGerman($languageCode)) {
			return $number . '.';
		}

		$lastTwoDigits = $number % 100;
		if ($lastTwoDigits >= 11 && $lastTwoDigits <= 13) {
			return $number . 'th';
		}
		return $number . match ($number % 10) {
			1 => 'st',
			2 => 'nd',
			3 => 'rd',
			default => 'th',
		};
	}

	private static function isGerman(string $languageCode): bool {
		return $languageCode === 'de' || str_starts_with($languageCode, 'de_');
	}
}
