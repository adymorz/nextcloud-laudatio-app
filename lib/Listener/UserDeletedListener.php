<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Adrian Dymorz <dev@adrian.dymorz.ch>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Laudatio\Listener;

use OCA\Laudatio\Db\AuditEntry;
use OCA\Laudatio\Db\GroupMapper;
use OCA\Laudatio\Db\InterestedMapper;
use OCA\Laudatio\Service\AuditService;
use OCA\Laudatio\Service\Clock;
use OCA\Laudatio\Service\NotificationService;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;
use OCP\User\Events\UserDeletedEvent;
use Psr\Log\LoggerInterface;

/**
 * Removes a deleted user from the interested users of all groups. Groups the
 * user was responsible for are left without a responsible person, so a later
 * account with the same ID inherits nothing, and the managers are notified
 * to assign a new one.
 *
 * @template-implements IEventListener<UserDeletedEvent>
 */
class UserDeletedListener implements IEventListener {
	public function __construct(
		private InterestedMapper $interestedMapper,
		private GroupMapper $groupMapper,
		private AuditService $audit,
		private NotificationService $notifications,
		private Clock $clock,
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

		$groupIds = $this->groupMapper->findIdsByResponsible($userId);
		foreach ($groupIds === [] ? [] : $this->groupMapper->findByIds($groupIds) as $group) {
			$group->setResponsibleUid('');
			$group->setUpdatedAt($this->clock->time());
			$this->groupMapper->update($group);
			$this->audit->log(null, AuditEntry::ACTION_UPDATE, AuditService::ENTITY_GROUP, $group->getId(), ['responsible_uid']);
			$this->notifications->notifyResponsibleDeleted($group->getId());
			$this->logger->warning('The deleted user {user} was responsible for group {group}, the managers were asked to assign a new responsible person', [
				'user' => $userId,
				'group' => $group->getId(),
			]);
		}
	}
}
