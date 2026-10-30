<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Adrian Dymorz <dev@adrian.dymorz.ch>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Laudatio\Service;

use OCA\Laudatio\Db\AuditEntry;
use OCA\Laudatio\Db\Event;
use OCA\Laudatio\Db\Group;
use OCA\Laudatio\Db\GroupMapper;
use OCA\Laudatio\Db\OrgFunction;
use OCA\Laudatio\Db\OrgFunctionMapper;
use OCA\Laudatio\Db\Person;
use OCA\Laudatio\Db\PersonMapper;
use OCA\Laudatio\Db\Slice;
use OCA\Laudatio\Db\SliceMapper;
use OCA\Laudatio\ResponseDefinitions;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\IUserManager;
use OCP\L10N\IFactory;

/**
 * Turns entities into API payloads and applies the visibility rules: date of
 * birth, contact data and notes only for viewers allowed to see them, and only
 * slices of groups the viewer can see.
 *
 * @psalm-import-type LaudatioUser from ResponseDefinitions
 * @psalm-import-type LaudatioMe from ResponseDefinitions
 * @psalm-import-type LaudatioFunction from ResponseDefinitions
 * @psalm-import-type LaudatioGroup from ResponseDefinitions
 * @psalm-import-type LaudatioSlice from ResponseDefinitions
 * @psalm-import-type LaudatioPerson from ResponseDefinitions
 * @psalm-import-type LaudatioPersonDetail from ResponseDefinitions
 * @psalm-import-type LaudatioEvent from ResponseDefinitions
 * @psalm-import-type LaudatioAuditEntry from ResponseDefinitions
 */
class Presenter {
	/** @var array<int, OrgFunction>|null */
	private ?array $functions = null;
	/** @var array<int, Group>|null */
	private ?array $groups = null;
	/** @var array<string, string> */
	private array $displayNames = [];

	public function __construct(
		private IUserManager $userManager,
		private IFactory $l10nFactory,
		private PersonMapper $personMapper,
		private GroupMapper $groupMapper,
		private OrgFunctionMapper $functionMapper,
		private SliceMapper $sliceMapper,
		private AccessService $access,
		private SettingsService $settings,
		private EventService $eventService,
		private UpcomingService $upcoming,
		private TitleBuilder $titles,
		private Clock $clock,
	) {
	}

	/**
	 * @return LaudatioUser
	 */
	public function user(string $userId): array {
		$this->displayNames[$userId] ??= $this->userManager->getDisplayName($userId) ?? $userId;
		return ['id' => $userId, 'displayName' => $this->displayNames[$userId]];
	}

	/**
	 * @return LaudatioMe
	 */
	public function me(string $userId): array {
		return [
			'userId' => $userId,
			'isManager' => $this->access->isManager($userId),
			'responsibleGroupIds' => $this->access->getResponsibleGroupIds($userId),
			'interestedGroupIds' => $this->access->getInterestedGroupIds($userId),
			'greetingReminder' => $this->settings->isGreetingReminderEnabled(),
			'leadTimeDays' => $this->settings->getLeadTimeDays(),
			'emailNotifications' => $this->settings->isEmailEnabledForUser($userId),
			'hasEmail' => ($this->userManager->get($userId)?->getEMailAddress() ?? '') !== '',
			'regulationsUrl' => $this->settings->getRegulationsUrl(),
			'sheetNote' => $this->settings->getSheetNote(),
		];
	}

	/**
	 * @return LaudatioFunction
	 */
	public function function(OrgFunction $function): array {
		return [
			'id' => $function->getId(),
			'groupId' => $function->getGroupId(),
			'name' => $function->getName(),
			'yearlyGift' => $function->isYearlyGift(),
		];
	}

	/**
	 * @param list<string> $interested
	 * @param list<OrgFunction> $functions
	 * @return LaudatioGroup
	 */
	public function group(Group $group, array $interested, array $functions, string $viewer): array {
		$role = 'manager';
		if ($group->getResponsibleUid() === $viewer) {
			$role = 'responsible';
		} elseif (in_array($viewer, $interested, true)) {
			$role = 'interested';
		}
		return [
			'id' => $group->getId(),
			'name' => $group->getName(),
			// Empty after the responsible user was deleted
			'responsible' => $group->getResponsibleUid() === '' ? null : $this->user($group->getResponsibleUid()),
			'interested' => array_map($this->user(...), $interested),
			'jubileeYears' => $group->getJubileeYears(),
			'effectiveJubileeYears' => $this->eventService->jubileeYearsForGroup($group),
			'functions' => array_map($this->function(...), array_values(array_filter($functions, static fn (OrgFunction $f): bool => $f->getGroupId() === $group->getId()))),
			'role' => $role,
		];
	}

	/**
	 * @return LaudatioSlice
	 */
	public function slice(Slice $slice): array {
		$function = $this->functions()[$slice->getFunctionId()] ?? null;
		$group = $function === null ? null : ($this->groups()[$function->getGroupId()] ?? null);
		$end = $slice->getEndDate();
		return [
			'id' => $slice->getId(),
			'personId' => $slice->getPersonId(),
			'functionId' => $slice->getFunctionId(),
			'functionName' => $function?->getName() ?? '',
			'groupId' => $function?->getGroupId() ?? 0,
			'groupName' => $group?->getName() ?? '',
			'startDate' => Dates::format($slice->getStartDate()),
			'endDate' => $end === null ? null : Dates::format($end),
		];
	}

	/**
	 * @param list<Slice> $slices all slices of the person
	 * @return LaudatioPerson
	 */
	public function person(Person $person, array $slices, string $viewer): array {
		$sensitive = $this->access->canSeeSensitive($viewer, $person->getId());
		$today = $this->clock->today();
		$current = array_filter(
			$this->visibleSlices($slices, $viewer),
			static function (Slice $slice) use ($today): bool {
				$end = $slice->getEndDate();
				return $end === null || Dates::compare($end, $today) >= 0;
			},
		);
		return [
			'id' => $person->getId(),
			'firstName' => $person->getFirstName(),
			'lastName' => $person->getLastName(),
			'displayName' => $person->getDisplayName(),
			'dateOfBirth' => $sensitive ? Dates::format($person->getDateOfBirth()) : null,
			'notes' => $sensitive ? $person->getNotes() : null,
			'street' => $sensitive ? $person->getStreet() : null,
			'streetNumber' => $sensitive ? $person->getStreetNumber() : null,
			'postalCode' => $sensitive ? $person->getPostalCode() : null,
			'city' => $sensitive ? $person->getCity() : null,
			'country' => $sensitive ? $person->getCountry() : null,
			'mobilePhone' => $sensitive ? $person->getMobilePhone() : null,
			'otherPhone' => $sensitive ? $person->getOtherPhone() : null,
			'sensitiveVisible' => $sensitive,
			'currentFunctions' => array_map($this->slice(...), array_values($current)),
		];
	}

	/**
	 * @param list<Slice> $slices all slices of the person
	 * @return LaudatioPersonDetail
	 */
	public function personDetail(Person $person, array $slices, string $viewer): array {
		$detail = $this->person($person, $slices, $viewer);
		$detail['slices'] = array_map($this->slice(...), $this->visibleSlices($slices, $viewer));
		return $detail;
	}

	/**
	 * @param RecipientService::ROLE_*|null $role
	 * @return LaudatioEvent
	 */
	public function event(Event $event, ?string $role, string $viewer): array {
		$language = $this->l10nFactory->getUserLanguage($this->userManager->get($viewer));
		$functionId = $event->getFunctionId();
		$greetedBy = $event->getGreetedBy();
		$type = match ($event->getType()) {
			Event::TYPE_JUBILEE => 'jubilee',
			Event::TYPE_GIFT => 'gift',
			default => 'birthday',
		};
		return [
			'id' => $event->getId(),
			'type' => $type,
			'date' => Dates::format($event->getEventDate()),
			// Interested users may not see the date of birth, so no age for them
			'ordinal' => $event->getType() === Event::TYPE_BIRTHDAY && $role === RecipientService::ROLE_INTERESTED ? null : $event->getOrdinal(),
			'title' => $this->titles->build($event, $role, $language),
			'role' => $role,
			'personId' => $event->getPersonId(),
			'personName' => $this->titles->personName($event->getPersonId()),
			'functionId' => $functionId,
			'functionName' => $functionId === null ? null : $this->titles->functionName($functionId),
			'greeted' => $event->getGreetedAt() !== null,
			'greetedAt' => $event->getGreetedAt(),
			'greetedBy' => $greetedBy === null ? null : $this->user($greetedBy),
			'canGreet' => $this->upcoming->canMark($event, $role, $viewer),
		];
	}

	/**
	 * @return LaudatioAuditEntry
	 */
	public function auditEntry(AuditEntry $entry): array {
		$actor = $entry->getActorUid();
		return [
			'id' => $entry->getId(),
			'actor' => $actor === null ? null : $this->user($actor),
			'action' => $entry->getAction(),
			'entityType' => $entry->getEntityType(),
			'entityId' => $entry->getEntityId(),
			'entityLabel' => $this->entityLabel($entry->getEntityType(), $entry->getEntityId()),
			'changedFields' => $entry->getChangedFieldList(),
			'createdAt' => $entry->getCreatedAt(),
		];
	}

	/**
	 * Current name of the entity, null if it no longer exists.
	 */
	private function entityLabel(string $type, int $id): ?string {
		try {
			return match ($type) {
				AuditService::ENTITY_PERSON => $this->personMapper->find($id)->getDisplayName(),
				AuditService::ENTITY_GROUP => $this->groupMapper->find($id)->getName(),
				AuditService::ENTITY_FUNCTION => $this->functionMapper->find($id)->getName(),
				AuditService::ENTITY_SLICE => $this->sliceLabel($this->sliceMapper->find($id)),
				default => null,
			};
		} catch (DoesNotExistException) {
			return null;
		}
	}

	private function sliceLabel(Slice $slice): string {
		$function = $this->functions()[$slice->getFunctionId()] ?? null;
		return $this->titles->personName($slice->getPersonId()) . ': ' . ($function?->getName() ?? '?');
	}

	/**
	 * @param list<Slice> $slices
	 * @return list<Slice>
	 */
	private function visibleSlices(array $slices, string $viewer): array {
		return array_values(array_filter($slices, function (Slice $slice) use ($viewer): bool {
			$function = $this->functions()[$slice->getFunctionId()] ?? null;
			return $function !== null && $this->access->canSeeGroup($viewer, $function->getGroupId());
		}));
	}

	/**
	 * @return array<int, OrgFunction>
	 */
	private function functions(): array {
		if ($this->functions === null) {
			$this->functions = [];
			foreach ($this->functionMapper->findAll() as $function) {
				$this->functions[$function->getId()] = $function;
			}
		}
		return $this->functions;
	}

	/**
	 * @return array<int, Group>
	 */
	private function groups(): array {
		if ($this->groups === null) {
			$this->groups = [];
			foreach ($this->groupMapper->findAll() as $group) {
				$this->groups[$group->getId()] = $group;
			}
		}
		return $this->groups;
	}

	/**
	 * Forget cached functions and groups after they changed.
	 */
	public function reset(): void {
		$this->functions = null;
		$this->groups = null;
	}
}
