/**
 * SPDX-FileCopyrightText: 2026 Adrian Dymorz <dev@adrian.dymorz.ch>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { showError } from '@nextcloud/dialogs'

/**
 * Escapes plain text for use as HTML.
 *
 * Toasts of @nextcloud/dialogs read their text as HTML and show only its
 * text content: plain text like "Team <Nord>" would show as "Team ", and
 * markup in it would become elements. Default t() output is escaped already;
 * other text, e.g. messages of the server, must be escaped with this first.
 *
 * @param text Plain text
 */
export function escapeHtml(text: string): string {
	const element = document.createElement('div')
	element.textContent = text
	return element.innerHTML
}

/**
 * Shows a plain-text error message as a toast, e.g. errorMessage(error).
 *
 * @param message Plain text
 */
export function showErrorText(message: string): void {
	showError(escapeHtml(message))
}
