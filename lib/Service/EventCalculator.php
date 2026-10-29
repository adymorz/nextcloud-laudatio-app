<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Adrian Dymorz <dev@adrian.dymorz.ch>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Appreciation\Service;

use DateTimeImmutable;
use OCA\Appreciation\Db\Event;
use OCA\Appreciation\Db\Person;
use OCA\Appreciation\Db\Slice;
use OCA\Appreciation\Model\PlannedEvent;

/**
 * Calculates which events a person should have within a date window. Pure
 * logic, no database access.
 *
 * - Birthdays: only on days the person holds at least one active time slice,
 *   i.e. while they belong to the organization.
 * - Jubilees: per continuous service period in a function, on the
 *   anniversaries of its start that are in the jubilee years. Generated while
 *   the period is open or if it ended on or after the jubilee date.
 * - Yearly gifts: one per function with a yearly gift and calendar year in
 *   which the person holds it on at least one day, for every year the window
 *   touches (also days before it). Due at the end of the year, or on the last
 *   day in the function if the person leaves it during the year.
 */
class EventCalculator {
	public function __construct(
		private PeriodCalculator $periodCalculator,
	) {
	}

	/**
	 * @param list<Slice> $slices all slices of the person
	 * @param array<int, list<int>> $jubileeYearsByFunction jubilee years per function ID
	 * @param list<int> $giftFunctionIds IDs of the functions with a yearly gift
	 * @return list<PlannedEvent> ordered by date
	 */
	public function calculate(
		Person $person,
		array $slices,
		array $jubileeYearsByFunction,
		DateTimeImmutable $from,
		DateTimeImmutable $to,
		bool $withBirthdays,
		bool $withJubilees,
		array $giftFunctionIds = [],
	): array {
		$events = [];
		if ($withBirthdays) {
			array_push($events, ...$this->birthdays($person, $slices, $from, $to));
		}
		if ($withJubilees) {
			array_push($events, ...$this->jubilees($person, $slices, $jubileeYearsByFunction, $from, $to));
		}
		if ($giftFunctionIds !== []) {
			array_push($events, ...$this->gifts($person, $slices, $giftFunctionIds, $from, $to));
		}
		usort($events, static fn (PlannedEvent $a, PlannedEvent $b): int => [Dates::format($a->date), $a->uid] <=> [Dates::format($b->date), $b->uid]);
		return $events;
	}

	/**
	 * @param list<Slice> $slices
	 * @return list<PlannedEvent>
	 */
	private function birthdays(Person $person, array $slices, DateTimeImmutable $from, DateTimeImmutable $to): array {
		$birth = $person->getDateOfBirth();
		$birthYear = (int)$birth->format('Y');
		$events = [];
		for ($year = (int)$from->format('Y'); $year <= (int)$to->format('Y'); $year++) {
			$age = $year - $birthYear;
			if ($age < 1) {
				continue;
			}
			$date = Dates::anniversary($birth, $age);
			if (!$this->inWindow($date, $from, $to) || !$this->isMemberOn($slices, $date)) {
				continue;
			}
			$events[] = new PlannedEvent(
				sprintf('appreciation-birthday-%d-%d', $person->getId(), $year),
				Event::TYPE_BIRTHDAY,
				$person->getId(),
				null,
				$date,
				$age,
			);
		}
		return $events;
	}

	/**
	 * @param list<Slice> $slices
	 * @param array<int, list<int>> $jubileeYearsByFunction
	 * @return list<PlannedEvent>
	 */
	private function jubilees(Person $person, array $slices, array $jubileeYearsByFunction, DateTimeImmutable $from, DateTimeImmutable $to): array {
		$events = [];
		foreach ($this->periodCalculator->periods($slices) as $period) {
			foreach ($jubileeYearsByFunction[$period->functionId] ?? [] as $years) {
				$date = Dates::anniversary($period->start, $years);
				if (!$this->inWindow($date, $from, $to)) {
					continue;
				}
				if ($period->end !== null && Dates::compare($period->end, $date) < 0) {
					continue;
				}
				$events[] = new PlannedEvent(
					sprintf('appreciation-jubilee-%d-%d-%s-%d', $person->getId(), $period->functionId, $period->start->format('Ymd'), $years),
					Event::TYPE_JUBILEE,
					$person->getId(),
					$period->functionId,
					$date,
					$years,
				);
			}
		}
		return $events;
	}

	/**
	 * @param list<Slice> $slices
	 * @param list<int> $giftFunctionIds
	 * @return list<PlannedEvent>
	 */
	private function gifts(Person $person, array $slices, array $giftFunctionIds, DateTimeImmutable $from, DateTimeImmutable $to): array {
		$lastYear = (int)$to->format('Y');
		/** @var array<string, PlannedEvent> $events by UID */
		$events = [];
		foreach ($this->periodCalculator->periods($slices) as $period) {
			if (!in_array($period->functionId, $giftFunctionIds, true)) {
				continue;
			}
			$endYear = $period->end === null ? $lastYear : min($lastYear, (int)$period->end->format('Y'));
			for ($year = max((int)$from->format('Y'), (int)$period->start->format('Y')); $year <= $endYear; $year++) {
				$due = $period->end !== null && (int)$period->end->format('Y') === $year ? $period->end : Dates::yearEnd($year);
				$uid = sprintf('appreciation-gift-%d-%d-%d', $person->getId(), $period->functionId, $year);
				// Several periods in the same function and year: one gift, due after the last one
				if (isset($events[$uid]) && Dates::compare($events[$uid]->date, $due) >= 0) {
					continue;
				}
				$events[$uid] = new PlannedEvent($uid, Event::TYPE_GIFT, $person->getId(), $period->functionId, $due, $year);
			}
		}
		return array_values($events);
	}

	private function inWindow(DateTimeImmutable $date, DateTimeImmutable $from, DateTimeImmutable $to): bool {
		return Dates::compare($date, $from) >= 0 && Dates::compare($date, $to) <= 0;
	}

	/**
	 * @param list<Slice> $slices
	 */
	private function isMemberOn(array $slices, DateTimeImmutable $date): bool {
		foreach ($slices as $slice) {
			if ($slice->isActiveOn($date)) {
				return true;
			}
		}
		return false;
	}
}
