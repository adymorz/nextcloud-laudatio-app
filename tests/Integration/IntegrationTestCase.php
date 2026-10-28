<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Adrian Dymorz <dev@adrian.dymorz.ch>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Appreciation\Tests\Integration;

use OCP\IDBConnection;
use OCP\Server;
use PHPUnit\Framework\TestCase;

/**
 * Base class for tests against the real database of the Nextcloud instance.
 *
 * The tests run against the developer's instance in DDEV, so they must never
 * truncate tables: every test registers cleanups for exactly the rows it
 * created, which run in reverse order after the test. Audit log entries
 * written during the test are removed as well (assuming nobody edits data in
 * the instance during the milliseconds a test runs).
 */
abstract class IntegrationTestCase extends TestCase {
	/** @var list<\Closure(): void> */
	private array $cleanups = [];
	private int $lastAuditId = 0;

	#[\Override]
	protected function setUp(): void {
		parent::setUp();
		$qb = $this->service(IDBConnection::class)->getQueryBuilder();
		$qb->select($qb->func()->max('id'))->from('appreciation_audit');
		$cursor = $qb->executeQuery();
		$this->lastAuditId = (int)$cursor->fetchOne();
		$cursor->closeCursor();
	}

	/**
	 * @template T
	 * @param class-string<T> $class
	 * @return T
	 */
	protected function service(string $class) {
		return Server::get($class);
	}

	/**
	 * @param \Closure(): void $cleanup
	 */
	protected function onTearDown(\Closure $cleanup): void {
		$this->cleanups[] = $cleanup;
	}

	/**
	 * A unique prefix, so concurrent or leftover data never collides.
	 */
	protected function uniqueName(string $prefix): string {
		return $prefix . '-' . bin2hex(random_bytes(4));
	}

	#[\Override]
	protected function tearDown(): void {
		foreach (array_reverse($this->cleanups) as $cleanup) {
			try {
				$cleanup();
			} catch (\Throwable) {
				// Already gone, e.g. deleted by the code under test
			}
		}
		$this->cleanups = [];

		$qb = $this->service(IDBConnection::class)->getQueryBuilder();
		$qb->delete('appreciation_audit')
			->where($qb->expr()->gt('id', $qb->createNamedParameter($this->lastAuditId)));
		$qb->executeStatement();

		parent::tearDown();
	}
}
