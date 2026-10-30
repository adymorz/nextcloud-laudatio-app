<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Adrian Dymorz <dev@adrian.dymorz.ch>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Laudatio\Tests\Unit\Service;

use OCA\Laudatio\Db\Slice;
use OCA\Laudatio\Model\ServicePeriod;
use OCA\Laudatio\Service\Dates;
use OCA\Laudatio\Service\PeriodCalculator;
use PHPUnit\Framework\TestCase;

final class PeriodCalculatorTest extends TestCase {
	private PeriodCalculator $calculator;

	#[\Override]
	protected function setUp(): void {
		$this->calculator = new PeriodCalculator();
	}

	public function testMergesBackToBackSlices(): void {
		$periods = $this->calculator->periods([
			$this->slice(1, '2018-01-01', '2020-12-31'),
			$this->slice(1, '2021-01-01', '2023-12-31'),
			$this->slice(1, '2024-01-01', null),
		]);
		$this->assertSame([['1', '2018-01-01', null]], $this->describe($periods));
	}

	public function testGapStartsNewPeriod(): void {
		$periods = $this->calculator->periods([
			$this->slice(1, '2021-01-02', null),
			$this->slice(1, '2018-01-01', '2020-12-31'),
		]);
		$this->assertSame([['1', '2018-01-01', '2020-12-31'], ['1', '2021-01-02', null]], $this->describe($periods));
	}

	public function testFunctionsAreSeparate(): void {
		$periods = $this->calculator->periods([
			$this->slice(2, '2019-01-01', null),
			$this->slice(1, '2018-01-01', '2018-12-31'),
		]);
		$this->assertSame([['1', '2018-01-01', '2018-12-31'], ['2', '2019-01-01', null]], $this->describe($periods));
	}

	public function testContainedSliceDoesNotShortenThePeriod(): void {
		$periods = $this->calculator->periods([
			$this->slice(1, '2018-01-01', '2022-12-31'),
			$this->slice(1, '2019-01-01', '2019-06-30'),
		]);
		$this->assertSame([['1', '2018-01-01', '2022-12-31']], $this->describe($periods));
	}

	/**
	 * @param list<ServicePeriod> $periods
	 * @return list<array{string, string, ?string}>
	 */
	private function describe(array $periods): array {
		return array_map(static fn (ServicePeriod $p): array => [
			(string)$p->functionId,
			Dates::format($p->start),
			$p->end === null ? null : Dates::format($p->end),
		], $periods);
	}

	private function slice(int $functionId, string $start, ?string $end): Slice {
		$slice = new Slice();
		$slice->setFunctionId($functionId);
		$slice->setStartDate(Dates::parse($start) ?? throw new \LogicException());
		$slice->setEndDate($end === null ? null : Dates::parse($end));
		return $slice;
	}
}
