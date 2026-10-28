/**
 * SPDX-FileCopyrightText: 2026 Adrian Dymorz <dev@adrian.dymorz.ch>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { getCanonicalLocale, n, t } from '@nextcloud/l10n'

/**
 * Calendar dates are exchanged as YYYY-MM-DD and shown in the local timezone.
 *
 * @param value Date as YYYY-MM-DD
 */
export function parseIsoDate(value: string): Date {
	const [year, month, day] = value.split('-').map(Number)
	return new Date(year!, month! - 1, day!)
}

/**
 * @param date Local date
 */
export function toIsoDate(date: Date): string {
	const pad = (value: number) => String(value).padStart(2, '0')
	return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}`
}

/**
 * @param value Date as YYYY-MM-DD
 */
export function formatDate(value: string): string {
	return new Intl.DateTimeFormat(getCanonicalLocale(), { dateStyle: 'long' }).format(parseIsoDate(value))
}

/**
 * @param timestamp Unix timestamp in seconds
 */
export function formatDateTime(timestamp: number): string {
	return new Intl.DateTimeFormat(getCanonicalLocale(), { dateStyle: 'medium', timeStyle: 'short' }).format(new Date(timestamp * 1000))
}

/**
 * Whole days from today until the date (negative for past dates).
 *
 * @param value Date as YYYY-MM-DD
 */
export function daysUntil(value: string): number {
	const today = new Date()
	today.setHours(0, 0, 0, 0)
	return Math.round((parseIsoDate(value).getTime() - today.getTime()) / 86400000)
}

/**
 * E.g. "today", "tomorrow" or "in 5 days".
 *
 * @param value Date as YYYY-MM-DD
 */
export function relativeDay(value: string): string {
	const days = daysUntil(value)
	if (days === 0) {
		return t('appreciation', 'today')
	}
	if (days === 1) {
		return t('appreciation', 'tomorrow')
	}
	if (days > 1) {
		return n('appreciation', 'in %n day', 'in %n days', days)
	}
	return n('appreciation', '%n day ago', '%n days ago', -days)
}

/**
 * Age in full years today.
 *
 * @param dateOfBirth Date as YYYY-MM-DD
 */
export function age(dateOfBirth: string): number {
	const birth = parseIsoDate(dateOfBirth)
	const today = new Date()
	let years = today.getFullYear() - birth.getFullYear()
	if (today.getMonth() < birth.getMonth() || (today.getMonth() === birth.getMonth() && today.getDate() < birth.getDate())) {
		years--
	}
	return years
}
