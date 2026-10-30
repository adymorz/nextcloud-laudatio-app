/**
 * SPDX-FileCopyrightText: 2026 Adrian Dymorz <dev@adrian.dymorz.ch>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import type { Me } from './types'

import { loadState } from '@nextcloud/initial-state'
import { reactive } from 'vue'

/**
 * Permissions and settings of the current user, provided by the page.
 */
export const me = reactive<Me>(loadState<Me>('laudatio', 'me'))
