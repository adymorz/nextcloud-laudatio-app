/**
 * SPDX-FileCopyrightText: 2026 Adrian Dymorz <dev@adrian.dymorz.ch>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

/**
 * Options for t() when the result is shown as plain text: in templates, bound
 * attributes, dialogs and toasts, which escape it themselves. By default t()
 * escapes the placeholders and sanitizes the result as HTML, so a name like
 * "Kids' Choir & Band" would show as "Kids&#39; Choir &amp; Band".
 *
 * Pass it as the argument after the placeholders when they hold names.
 */
export const PLAIN = { escape: false, sanitize: false } as const
