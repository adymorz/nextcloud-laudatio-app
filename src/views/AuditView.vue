<!--
  - SPDX-FileCopyrightText: 2026 Adrian Dymorz <dev@adrian.dymorz.ch>
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<script setup lang="ts">
import type { AuditEntry } from '../types'

import { showError } from '@nextcloud/dialogs'
import { t } from '@nextcloud/l10n'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcLoadingIcon from '@nextcloud/vue/components/NcLoadingIcon'
import { onMounted, ref } from 'vue'
import { api, errorMessage } from '../api'
import { formatDateTime } from '../dates'

const PAGE_SIZE = 50

const entries = ref<AuditEntry[]>([])
const total = ref(0)
const loading = ref(false)

const actions: Record<string, string> = {
	create: t('laudatio', 'created'),
	update: t('laudatio', 'changed'),
	delete: t('laudatio', 'deleted'),
}

const entityTypes: Record<string, string> = {
	person: t('laudatio', 'Person'),
	slice: t('laudatio', 'Time slice'),
	group: t('laudatio', 'Group'),
	function: t('laudatio', 'Function'),
}

/**
 * Loads the next page of entries.
 */
async function loadMore() {
	loading.value = true
	try {
		const page = await api.audit(PAGE_SIZE, entries.value.length)
		entries.value = [...entries.value, ...page.entries]
		total.value = page.total
	} catch (error) {
		showError(errorMessage(error))
	} finally {
		loading.value = false
	}
}

onMounted(loadMore)
</script>

<template>
	<div class="view">
		<h2>{{ t('laudatio', 'Audit log') }}</h2>
		<p class="muted">
			{{ t('laudatio', 'Who created, changed or deleted master data. Only the names of changed fields are recorded, not their values.') }}
		</p>

		<table v-if="entries.length > 0" class="audit">
			<thead>
				<tr>
					<th>{{ t('laudatio', 'Time') }}</th>
					<th>{{ t('laudatio', 'User') }}</th>
					<th>{{ t('laudatio', 'Action') }}</th>
					<th>{{ t('laudatio', 'Object') }}</th>
					<th>{{ t('laudatio', 'Fields') }}</th>
				</tr>
			</thead>
			<tbody>
				<tr v-for="entry in entries" :key="entry.id">
					<td>{{ formatDateTime(entry.createdAt) }}</td>
					<td>{{ entry.actor?.displayName ?? t('laudatio', 'System') }}</td>
					<td>{{ actions[entry.action] ?? entry.action }}</td>
					<td>
						{{ entityTypes[entry.entityType] ?? entry.entityType }}
						<span v-if="entry.entityLabel">{{ entry.entityLabel }}</span>
						<span v-else class="muted">#{{ entry.entityId }}</span>
					</td>
					<td class="muted">
						{{ entry.changedFields.join(', ') }}
					</td>
				</tr>
			</tbody>
		</table>
		<p v-else-if="!loading" class="muted">
			{{ t('laudatio', 'No entries yet.') }}
		</p>

		<NcLoadingIcon v-if="loading" :size="32" />
		<NcButton v-else-if="entries.length < total" class="audit__more" @click="loadMore">
			{{ t('laudatio', 'Show more') }}
		</NcButton>
	</div>
</template>

<style scoped>
.view {
	padding: calc(var(--default-grid-baseline) * 4);
	padding-inline-start: calc(var(--default-clickable-area) + var(--default-grid-baseline) * 4);
	max-width: 1100px;
}

.audit {
	width: 100%;
	margin-block: calc(var(--default-grid-baseline) * 4);
	border-collapse: collapse;
}

.audit th {
	text-align: start;
	color: var(--color-text-maxcontrast);
	font-weight: normal;
}

.audit th,
.audit td {
	padding: calc(var(--default-grid-baseline) * 2);
	border-block-end: 1px solid var(--color-border);
}

.muted {
	color: var(--color-text-maxcontrast);
}

.audit__more {
	margin-block: calc(var(--default-grid-baseline) * 4);
}
</style>
