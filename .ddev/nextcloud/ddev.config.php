<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Adrian Dymorz <dev@adrian.dymorz.ch>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

/*
 * Development-only Nextcloud system configuration for the DDEV environment.
 *
 * setup.sh symlinks this file to /var/www/nextcloud/config/ddev.config.php.
 * Nextcloud merges every config/*.config.php on top of config.php, so the
 * values below win. Changes take effect on the next request.
 */
$CONFIG = [
	'debug' => true,

	'trusted_domains' => array_merge(['localhost'], explode(',', getenv('DDEV_HOSTNAME') ?: 'appreciation.ddev.site')),
	'overwrite.cli.url' => getenv('DDEV_PRIMARY_URL') ?: 'https://appreciation.ddev.site',
	// TLS is terminated by the DDEV router; Apache in the container speaks plain HTTP.
	'overwriteprotocol' => 'https',
	// Pretty URLs (no /index.php/) via Nextcloud's .htaccess.
	'htaccess.RewriteBase' => '/',

	'apps_paths' => [
		[
			'path' => '/var/www/nextcloud/apps',
			'url' => '/apps',
			'writable' => false,
		],
		[
			// Apps installed from the App Store end up here.
			'path' => '/var/www/nextcloud/custom_apps',
			'url' => '/custom_apps',
			'writable' => true,
		],
		[
			// Contains a symlink to this repository (/var/www/html).
			'path' => '/var/www/nextcloud/apps-extra',
			'url' => '/apps-extra',
			'writable' => false,
		],
	],

	'memcache.local' => '\\OC\\Memcache\\APCu',

	// All outgoing mail is caught by Mailpit (`ddev mailpit`).
	'mail_smtpmode' => 'smtp',
	'mail_smtphost' => '127.0.0.1',
	'mail_smtpport' => 1025,
	'mail_from_address' => 'nextcloud',
	'mail_domain' => 'ddev.site',
];
