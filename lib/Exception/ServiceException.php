<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Adrian Dymorz <dev@adrian.dymorz.ch>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Appreciation\Exception;

/**
 * Base class for expected errors whose (translated) message may be shown to the user.
 */
abstract class ServiceException extends \Exception {
	/**
	 * HTTP status code the API responds with.
	 */
	abstract public function getStatus(): int;
}
