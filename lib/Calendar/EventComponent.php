<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Adrian Dymorz <dev@adrian.dymorz.ch>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Laudatio\Calendar;

use Sabre\VObject\Component\VEvent;

/**
 * A VEVENT that also returns its properties through array access.
 *
 * The DAV app's AppCalendar groups search results with $object['UID'] and
 * then wraps them with new VCalendar($objects). The latter requires Sabre
 * components, but array access on a Sabre component goes through its
 * iterator and triggers an "Undefined array key" warning for every event on
 * every CalDAV request. Returning properties for string offsets serves both.
 *
 * @psalm-suppress PropertyNotSetInConstructor $parent is set by Sabre when the component is added to a document
 */
class EventComponent extends VEvent {
	/**
	 * @param int|string $offset
	 */
	#[\Override]
	public function offsetGet($offset): mixed {
		if (is_string($offset)) {
			return $this->__get($offset);
		}
		return parent::offsetGet($offset);
	}
}
