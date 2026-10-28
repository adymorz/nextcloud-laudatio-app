<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Adrian Dymorz <dev@adrian.dymorz.ch>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Appreciation\Tests\Unit\Service;

use OCA\Appreciation\Db\Person;
use OCA\Appreciation\Db\Slice;
use OCA\Appreciation\Model\PlannedEvent;
use OCA\Appreciation\Service\Dates;
use OCA\Appreciation\Service\EventCalculator;
use OCA\Appreciation\Service\PeriodCalculator;
use PHPUnit\Framework\TestCase;

final class EventCalculatorTest extends TestCase {
	private EventCalculator $calculator;

	#[\Override]
	protected function setUp(): void {
		$this->calculator = new EventCalculator(new PeriodCalculator());
	}

	public function testBirthdaysOnlyWhileMember(): void {
		$person = $this->person(7, '1990-03-15');
		$slices = [$this->slice(1, '2020-01-01', '2026-12-31')];

		$events = $this->calculate($person, $slices, [], '2026-01-01', '2027-12-31', true, false);
		$this->assertSame([['appreciation-birthday-7-2026', 'birthday', null, '2026-03-15', 36]], $this->describe($events));
	}

	public function testLeapDayBirthdayFallsOnFebruary28(): void {
		$person = $this->person(1, '2000-02-29');
		$events = $this->calculate($person, [$this->slice(1, '2020-01-01', null)], [], '2027-01-01', '2028-12-31', true, false);
		$this->assertSame(['2027-02-28', '2028-02-29'], array_map(static fn (PlannedEvent $e): string => Dates::format($e->date), $events));
		$this->assertSame([27, 28], array_map(static fn (PlannedEvent $e): int => $e->ordinal, $events));
	}

	public function testJubileesOfContinuousPeriod(): void {
		$person = $this->person(3, '1970-01-01');
		$slices = [
			// Re-election: the second slice continues the period started in 2021
			$this->slice(4, '2021-11-05', '2024-11-04'),
			$this->slice(4, '2024-11-05', null),
		];
		$events = $this->calculate($person, $slices, [4 => [1, 5, 10]], '2026-01-01', '2027-01-31', false, true);
		$this->assertSame([['appreciation-jubilee-3-4-20211105-5', 'jubilee', 4, '2026-11-05', 5]], $this->describe($events));
	}

	public function testJubileeOnlyIfPeriodLastsUntilTheDate(): void {
		$person = $this->person(3, '1970-01-01');
		$jubileeYears = [4 => [5]];
		$endsTheDayBefore = [$this->slice(4, '2021-11-05', '2026-11-04')];
		$endsOnTheDay = [$this->slice(4, '2021-11-05', '2026-11-05')];

		$this->assertSame([], $this->calculate($person, $endsTheDayBefore, $jubileeYears, '2026-01-01', '2026-12-31', false, true));
		$this->assertCount(1, $this->calculate($person, $endsOnTheDay, $jubileeYears, '2026-01-01', '2026-12-31', false, true));
	}

	public function testJubileeYearsPerFunction(): void {
		$person = $this->person(3, '1970-01-01');
		$slices = [$this->slice(4, '2023-06-01', null), $this->slice(5, '2023-06-01', null)];
		$events = $this->calculate($person, $slices, [4 => [3], 5 => [5]], '2026-01-01', '2026-12-31', false, true);
		$this->assertSame([['appreciation-jubilee-3-4-20230601-3', 'jubilee', 4, '2026-06-01', 3]], $this->describe($events));
	}

	public function testWindowBoundariesAreInclusive(): void {
		$person = $this->person(2, '1980-05-10');
		$slices = [$this->slice(1, '2000-05-10', null)];
		$events = $this->calculate($person, $slices, [1 => [26]], '2026-05-10', '2026-05-10', true, true);
		$this->assertSame(['birthday', 'jubilee'], array_map(static fn (PlannedEvent $e): string => $e->type, $events));
	}

	/**
	 * @param list<Slice> $slices
	 * @param array<int, list<int>> $years
	 * @return list<PlannedEvent>
	 */
	private function calculate(Person $person, array $slices, array $years, string $from, string $to, bool $birthdays, bool $jubilees): array {
		return $this->calculator->calculate(
			$person,
			$slices,
			$years,
			Dates::parse($from) ?? throw new \LogicException(),
			Dates::parse($to) ?? throw new \LogicException(),
			$birthdays,
			$jubilees,
		);
	}

	/**
	 * @param list<PlannedEvent> $events
	 * @return list<array{string, string, ?int, string, int}>
	 */
	private function describe(array $events): array {
		return array_map(static fn (PlannedEvent $e): array => [$e->uid, $e->type, $e->functionId, Dates::format($e->date), $e->ordinal], $events);
	}

	private function person(int $id, string $dateOfBirth): Person {
		$person = new Person();
		$person->setId($id);
		$person->setDateOfBirth(Dates::parse($dateOfBirth) ?? throw new \LogicException());
		return $person;
	}

	private function slice(int $functionId, string $start, ?string $end): Slice {
		$slice = new Slice();
		$slice->setFunctionId($functionId);
		$slice->setStartDate(Dates::parse($start) ?? throw new \LogicException());
		$slice->setEndDate($end === null ? null : Dates::parse($end));
		return $slice;
	}
}
