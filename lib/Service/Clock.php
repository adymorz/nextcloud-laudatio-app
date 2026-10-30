<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Adrian Dymorz <dev@adrian.dymorz.ch>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Laudatio\Service;

use DateTimeImmutable;
use DateTimeZone;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\IConfig;

class Clock {
	public function __construct(
		private ITimeFactory $timeFactory,
		private IConfig $config,
	) {
	}

	/**
	 * The current day in the server's default timezone ("default_timezone").
	 */
	public function today(): DateTimeImmutable {
		$timezone = $this->config->getSystemValueString('default_timezone', 'UTC');
		try {
			$zone = new DateTimeZone($timezone === '' ? 'UTC' : $timezone);
		} catch (\Exception) {
			$zone = new DateTimeZone('UTC');
		}
		return Dates::normalize($this->timeFactory->now()->setTimezone($zone));
	}

	public function time(): int {
		return $this->timeFactory->getTime();
	}
}
