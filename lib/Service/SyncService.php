<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Adrian Dymorz <dev@adrian.dymorz.ch>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Appreciation\Service;

/**
 * The periodic work of the app, shared by the background job and the
 * appreciation:sync command.
 */
class SyncService {
	public function __construct(
		private EventService $eventService,
		private NotificationService $notifications,
	) {
	}

	/**
	 * @return array{inserted: int, updated: int, deleted: int, notified: int}
	 */
	public function run(): array {
		$result = $this->eventService->syncAll();
		$result['notified'] = $this->notifications->sendDue();
		$this->notifications->cleanup();
		return $result;
	}
}
