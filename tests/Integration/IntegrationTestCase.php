<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Adrian Dymorz <dev@adrian.dymorz.ch>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Appreciation\Tests\Integration;

use OCP\Server;
use PHPUnit\Framework\TestCase;

/**
 * Base class for tests against the real database of the Nextcloud instance.
 *
 * The tests run against the developer's instance in DDEV, so they must never
 * truncate tables: every test registers cleanups for exactly the rows it
 * created, which run in reverse order after the test.
 */
abstract class IntegrationTestCase extends TestCase {
	/** @var list<\Closure(): void> */
	private array $cleanups = [];

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
		parent::tearDown();
	}
}
