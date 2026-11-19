/**
 * SPDX-FileCopyrightText: 2026 Adrian Dymorz <dev@adrian.dymorz.ch>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { onBeforeUnmount, onMounted } from 'vue'

import './print.css'

/**
 * Lets a printable view print alone, without the Nextcloud header and
 * navigation, across pages: enables the print styles in print.css while the
 * view is shown.
 *
 * @return print() to open the browser's print dialog
 */
export function usePrintLayout(): { print: () => void } {
	onMounted(() => document.documentElement.classList.add('laudatio-print'))
	onBeforeUnmount(() => document.documentElement.classList.remove('laudatio-print'))
	return { print: () => window.print() }
}
