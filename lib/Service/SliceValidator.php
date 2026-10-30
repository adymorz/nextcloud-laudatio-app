<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Adrian Dymorz <dev@adrian.dymorz.ch>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Laudatio\Service;

use DateTimeImmutable;
use OCA\Laudatio\Db\OrgFunctionMapper;
use OCA\Laudatio\Db\SliceMapper;
use OCA\Laudatio\Exception\ConflictException;
use OCA\Laudatio\Exception\ValidationException;
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
		$this->validateRange($start, $end);

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
				throw new ConflictException($this->l10n->t('The term overlaps another term of this person in the same function.'));
			}
		}
	}

	/**
	 * @throws ValidationException when the end date is before the start date
	 */
	public function validateRange(DateTimeImmutable $start, ?DateTimeImmutable $end): void {
		if ($end !== null && Dates::compare($end, $start) < 0) {
			throw new ValidationException($this->l10n->t('The end date must not be before the start date.'));
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
