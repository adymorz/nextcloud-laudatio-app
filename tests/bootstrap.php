<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Adrian Dymorz <dev@adrian.dymorz.ch>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

use OCA\Laudatio\AppInfo\Application;

// Nextcloud server root: $NEXTCLOUD_ROOT (set in DDEV and CI), otherwise the
// classic layout with this app checked out to <server>/apps/laudatio.
$serverRoot = getenv('NEXTCLOUD_ROOT') ?: dirname(__DIR__, 3);

if (file_exists($serverRoot . '/tests/bootstrap.php')) {
	// Server git checkout: also provides \Test\TestCase and other test helpers.
	require_once $serverRoot . '/tests/bootstrap.php';
} else {
	// Release tarball (has no tests/ folder): just boot Nextcloud.
	require_once $serverRoot . '/lib/base.php';
}

require_once __DIR__ . '/../vendor/autoload.php';

\OC_App::loadApp(Application::APP_ID);
OC_Hook::clear();
