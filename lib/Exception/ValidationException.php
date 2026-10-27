<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Adrian Dymorz <dev@adrian.dymorz.ch>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Appreciation\Exception;

use OCP\AppFramework\Http;

class ValidationException extends ServiceException {
	#[\Override]
	public function getStatus(): int {
		return Http::STATUS_BAD_REQUEST;
	}
}
