<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Adrian Dymorz <dev@adrian.dymorz.ch>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Appreciation\Service;

use DateTimeImmutable;
use OCA\Appreciation\Db\OrgFunctionMapper;
use OCA\Appreciation\Db\SliceMapper;
use OCA\Appreciation\Exception\ConflictException;
use OCA\Appreciation\Exception\ValidationException;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\IL10N;

class SliceValidator {
	public function __construct(
		private SliceMapper $sliceMapper,
		private OrgFunctionMapper $functionMapper,
		private IL10N $l10n,
	) {
	}

	/**
	 * @throws ValidationException when the dates or the function are invalid
	 * @throws ConflictException when the slice overlaps another slice of the same person and function
	 */
	public function validate(int $personId, int $functionId, DateTimeImmutable $start, ?DateTimeImmutable $end, ?int $ignoreSliceId = null): void {
		if ($end !== null && Dates::compare($end, $start) < 0) {
			throw new ValidationException($this->l10n->t('The end date must not be before the start date.'));
		}

		try {
			$this->functionMapper->find($functionId);
		} catch (DoesNotExistException) {
			throw new ValidationException($this->l10n->t('The function does not exist.'));
		}

		foreach ($this->sliceMapper->findByPersonAndFunction($personId, $functionId) as $other) {
			if ($other->getId() === $ignoreSliceId) {
				continue;
			}
			if (self::overlaps($start, $end, $other->getStartDate(), $other->getEndDate())) {
				throw new ConflictException($this->l10n->t('The time slice overlaps another time slice of this person in the same function.'));
			}
		}
	}

	/**
	 * Whether two inclusive date ranges overlap. A null end means open-ended.
	 */
	public static function overlaps(DateTimeImmutable $startA, ?DateTimeImmutable $endA, DateTimeImmutable $startB, ?DateTimeImmutable $endB): bool {
		$endAKey = $endA === null ? '9999-12-31' : Dates::format($endA);
		$endBKey = $endB === null ? '9999-12-31' : Dates::format($endB);
		return Dates::format($startA) <= $endBKey && Dates::format($startB) <= $endAKey;
	}
}
