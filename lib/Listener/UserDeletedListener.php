<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Adrian Dymorz <dev@adrian.dymorz.ch>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Laudatio\Listener;

use OCA\Laudatio\Db\GroupMapper;
use OCA\Laudatio\Db\InterestedMapper;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;
use OCP\User\Events\UserDeletedEvent;
use Psr\Log\LoggerInterface;

/**
 * Removes a deleted user from the interested users of all groups. Groups the
 * user was responsible for keep the user ID until a manager assigns a new
 * responsible person; this is logged.
 *
 * @template-implements IEventListener<UserDeletedEvent>
 */
class UserDeletedListener implements IEventListener {
	public function __construct(
		private InterestedMapper $interestedMapper,
		private GroupMapper $groupMapper,
		private LoggerInterface $logger,
	) {
	}

	#[\Override]
	public function handle(Event $event): void {
		if (!$event instanceof UserDeletedEvent) {
			return;
		}
		$userId = $event->getUser()->getUID();
		$this->interestedMapper->deleteByUser($userId);
		foreach ($this->groupMapper->findIdsByResponsible($userId) as $groupId) {
			$this->logger->warning('The deleted user {user} was responsible for group {group}, please assign a new responsible person', [
				'user' => $userId,
				'group' => $groupId,
			]);
		}
	}
}
