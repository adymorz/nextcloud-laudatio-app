<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Adrian Dymorz <dev@adrian.dymorz.ch>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Laudatio\Service;

use OCA\Laudatio\Db\Slice;
use OCA\Laudatio\Model\ServicePeriod;

/**
 * Merges the time slices of a person into continuous service periods per
 * function. A slice that starts on the day after the previous one ended (e.g.
 * a re-election) continues the period; a gap starts a new period.
 */
class PeriodCalculator {
	/**
	 * @param list<Slice> $slices slices of one person
	 * @return list<ServicePeriod> ordered by function and start date
	 */
	public function periods(array $slices): array {
		$byFunction = [];
		foreach ($slices as $slice) {
			$byFunction[$slice->getFunctionId()][] = $slice;
		}
		ksort($byFunction);

		$periods = [];
		foreach ($byFunction as $functionId => $functionSlices) {
			usort($functionSlices, static fn (Slice $a, Slice $b): int => Dates::compare($a->getStartDate(), $b->getStartDate()));

			$start = null;
			$end = null;
			$open = false;
			foreach ($functionSlices as $slice) {
				if ($start === null) {
					$start = $slice->getStartDate();
					$end = $slice->getEndDate();
					$open = $end === null;
					continue;
				}
				$continues = $open
					|| ($end !== null && Dates::compare($slice->getStartDate(), Dates::addDays($end, 1)) <= 0);
				if ($continues) {
					if (!$open) {
						$sliceEnd = $slice->getEndDate();
						$open = $sliceEnd === null;
						if ($sliceEnd !== null && Dates::compare($sliceEnd, $end) > 0) {
							$end = $sliceEnd;
						}
					}
					continue;
				}
				$periods[] = new ServicePeriod($functionId, $start, $open ? null : $end);
				$start = $slice->getStartDate();
				$end = $slice->getEndDate();
				$open = $end === null;
			}
			if ($start !== null) {
				$periods[] = new ServicePeriod($functionId, $start, $open ? null : $end);
			}
		}
		return $periods;
	}
}
