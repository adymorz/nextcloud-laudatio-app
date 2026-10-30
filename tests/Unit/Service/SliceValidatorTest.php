<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Adrian Dymorz <dev@adrian.dymorz.ch>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Laudatio\Tests\Unit\Service;

use DateTimeImmutable;
use OCA\Laudatio\Db\OrgFunction;
use OCA\Laudatio\Db\OrgFunctionMapper;
use OCA\Laudatio\Db\Slice;
use OCA\Laudatio\Db\SliceMapper;
use OCA\Laudatio\Exception\ConflictException;
use OCA\Laudatio\Exception\ValidationException;
use OCA\Laudatio\Service\Dates;
use OCA\Laudatio\Service\SliceValidator;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\IL10N;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class SliceValidatorTest extends TestCase {
	private SliceMapper&MockObject $sliceMapper;
	private OrgFunctionMapper&MockObject $functionMapper;
	private SliceValidator $validator;

	#[\Override]
	protected function setUp(): void {
		$this->sliceMapper = $this->createMock(SliceMapper::class);
		$this->functionMapper = $this->createMock(OrgFunctionMapper::class);
		$l10n = $this->createMock(IL10N::class);
		$l10n->method('t')->willReturnCallback(static fn (string $text, array $params = []): string => vsprintf($text, $params));
		$this->validator = new SliceValidator($this->sliceMapper, $this->functionMapper, $l10n);
	}

	/**
	 * @return array<string, array{string, ?string, string, ?string, bool}>
	 */
	public static function ranges(): array {
		return [
			'disjoint' => ['2020-01-01', '2020-12-31', '2021-01-01', '2021-12-31', false],
			'adjacent is not overlapping' => ['2020-01-01', '2020-12-31', '2021-01-01', null, false],
			'same last and first day' => ['2020-01-01', '2020-12-31', '2020-12-31', null, true],
			'contained' => ['2020-01-01', '2020-12-31', '2020-03-01', '2020-04-01', true],
			'both open' => ['2020-01-01', null, '2024-01-01', null, true],
			'open before closed' => ['2025-01-01', null, '2020-01-01', '2024-12-31', false],
			'open overlapping closed' => ['2024-06-01', null, '2020-01-01', '2024-12-31', true],
		];
	}

	#[DataProvider('ranges')]
	public function testOverlaps(string $startA, ?string $endA, string $startB, ?string $endB, bool $expected): void {
		$this->assertSame($expected, SliceValidator::overlaps($this->date($startA), $this->dateOrNull($endA), $this->date($startB), $this->dateOrNull($endB)));
		$this->assertSame($expected, SliceValidator::overlaps($this->date($startB), $this->dateOrNull($endB), $this->date($startA), $this->dateOrNull($endA)));
	}

	public function testRejectsEndBeforeStart(): void {
		$this->expectException(ValidationException::class);
		$this->validator->validate(1, 2, $this->date('2024-05-01'), $this->date('2024-04-30'));
	}

	public function testRejectsUnknownFunction(): void {
		$this->functionMapper->method('find')->willThrowException(new DoesNotExistException('nope'));
		$this->expectException(ValidationException::class);
		$this->validator->validate(1, 2, $this->date('2024-05-01'), null);
	}

	public function testRejectsOverlap(): void {
		$this->functionMapper->method('find')->willReturn(new OrgFunction());
		$this->sliceMapper->method('findByPersonAndFunction')->with(1, 2)->willReturn([$this->slice(10, '2020-01-01', null)]);
		$this->expectException(ConflictException::class);
		$this->validator->validate(1, 2, $this->date('2024-05-01'), null);
	}

	public function testIgnoresTheSliceBeingEdited(): void {
		$this->functionMapper->method('find')->willReturn(new OrgFunction());
		$this->sliceMapper->method('findByPersonAndFunction')->willReturn([$this->slice(10, '2020-01-01', null)]);
		$this->validator->validate(1, 2, $this->date('2019-01-01'), null, 10);
		$this->addToAssertionCount(1);
	}

	public function testAcceptsAdjacentSlice(): void {
		$this->functionMapper->method('find')->willReturn(new OrgFunction());
		$this->sliceMapper->method('findByPersonAndFunction')->willReturn([$this->slice(10, '2020-01-01', '2023-12-31')]);
		$this->validator->validate(1, 2, $this->date('2024-01-01'), null);
		$this->addToAssertionCount(1);
	}

	private function slice(int $id, string $start, ?string $end): Slice {
		$slice = new Slice();
		$slice->setId($id);
		$slice->setStartDate($this->date($start));
		$slice->setEndDate($this->dateOrNull($end));
		return $slice;
	}

	private function date(string $value): DateTimeImmutable {
		$date = Dates::parse($value);
		$this->assertNotNull($date);
		return $date;
	}

	private function dateOrNull(?string $value): ?DateTimeImmutable {
		return $value === null ? null : $this->date($value);
	}
}
