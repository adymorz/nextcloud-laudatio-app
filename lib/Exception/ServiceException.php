<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Adrian Dymorz <dev@adrian.dymorz.ch>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Laudatio\Exception;

/**
 * Base class for expected errors whose (translated) message may be shown to
 * the user. The controllers map the subclasses to HTTP status codes.
 */
abstract class ServiceException extends \Exception {
}
