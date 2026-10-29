<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Adrian Dymorz <dev@adrian.dymorz.ch>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Appreciation\Controller;

use OCA\Appreciation\ResponseDefinitions;
use OCA\Appreciation\Service\AccessService;
use OCA\Appreciation\Service\EventService;
use OCA\Appreciation\Service\Presenter;
use OCA\Appreciation\Service\UpcomingService;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\ApiRoute;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\OCS\OCSForbiddenException;
use OCP\AppFramework\OCS\OCSNotFoundException;
use OCP\IRequest;
use OCP\IUserSession;

/**
 * Upcoming birthdays and jubilees.
 *
 * @psalm-import-type AppreciationEvent from ResponseDefinitions
 */
class EventController extends BaseOCSController {
	public function __construct(
		IRequest $request,
		IUserSession $userSession,
		AccessService $access,
		private UpcomingService $upcoming,
		private Presenter $presenter,
	) {
		parent::__construct($request, $userSession, $access);
	}

	/**
	 * List the upcoming events of the current user
	 *
	 * Responsible and interested users get the events of their groups,
	 * managers all events. Titles are in the language of the user. Birthdays
	 * and jubilees of the period come first, followed by the yearly gifts of
	 * every calendar year the period touches, including overdue ones of this
	 * year.
	 *
	 * @param int $days Number of days from today, 1 to 400
	 * @return DataResponse<Http::STATUS_OK, list<AppreciationEvent>, array{}>
	 * @throws OCSForbiddenException No access to the app
	 *
	 * 200: Events returned, each part ordered by date
	 */
	#[NoAdminRequired]
	#[ApiRoute(verb: 'GET', url: '/api/v1/events')]
	public function index(int $days = 60): DataResponse {
		$userId = $this->currentUserId();
		$days = max(1, min(EventService::WINDOW_DAYS, $days));
		$events = $this->call(function () use ($userId, $days): array {
			$this->access->requireAccess($userId);
			return $this->upcoming->nextDays($userId, $days);
		});
		return new DataResponse(array_map(
			fn (array $entry): array => $this->presenter->event($entry[0], $entry[1], $userId),
			$events,
		));
	}

	/**
	 * Mark an event as greeted or not greeted
	 *
	 * For yearly gifts this marks the gift as presented, also when the
	 * "greeted" checklist is disabled.
	 *
	 * @param int $id ID of the event
	 * @param bool $greeted Whether the person has been greeted (gifts: the gift was presented)
	 * @return DataResponse<Http::STATUS_OK, AppreciationEvent, array{}>
	 * @throws OCSForbiddenException Only the responsible person and managers may do this
	 * @throws OCSNotFoundException Event not found or not visible
	 *
	 * 200: Event updated
	 */
	#[NoAdminRequired]
	#[ApiRoute(verb: 'PUT', url: '/api/v1/events/{id}/greeted', requirements: ['id' => '\d+'])]
	public function greet(int $id, bool $greeted): DataResponse {
		$userId = $this->currentUserId();
		[$event, $role] = $this->call(fn (): array => $this->upcoming->setGreeted($userId, $id, $greeted));
		return new DataResponse($this->presenter->event($event, $role, $userId));
	}
}
