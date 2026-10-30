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
import LoadError from '../components/LoadError.vue'
import { formatDateTime } from '../dates'

const PAGE_SIZE = 50

const entries = ref<AuditEntry[]>([])
const total = ref(0)
const loading = ref(false)
const loadError = ref<string | null>(null)

const actions: Record<string, string> = {
	create: t('laudatio', 'created'),
	update: t('laudatio', 'changed'),
	delete: t('laudatio', 'deleted'),
	export: t('laudatio', 'exported'),
}

const entityTypes: Record<string, string> = {
	person: t('laudatio', 'Person'),
	slice: t('laudatio', 'Term'),
	group: t('laudatio', 'Group'),
	function: t('laudatio', 'Function'),
	data: t('laudatio', 'All data'),
}

// The server records the database names of the changed fields
const fieldLabels: Record<string, string> = {
	first_name: t('laudatio', 'First name'),
	last_name: t('laudatio', 'Last name'),
	date_of_birth: t('laudatio', 'Date of birth'),
	notes: t('laudatio', 'Notes'),
	street: t('laudatio', 'Street'),
	street_number: t('laudatio', 'House number'),
	postal_code: t('laudatio', 'Postal code'),
	city: t('laudatio', 'City'),
	country: t('laudatio', 'Country'),
	mobile_phone: t('laudatio', 'Mobile phone'),
	other_phone: t('laudatio', 'Additional phone number'),
	person_id: t('laudatio', 'Person'),
	function_id: t('laudatio', 'Function'),
	start_date: t('laudatio', 'Start date'),
	end_date: t('laudatio', 'End date'),
	name: t('laudatio', 'Name'),
	responsible_uid: t('laudatio', 'Responsible person'),
	interested: t('laudatio', 'Interested persons'),
	jubilee_years: t('laudatio', 'Anniversary years'),
	group_id: t('laudatio', 'Group'),
	yearly_gift: t('laudatio', 'One gift per year'),
}

/**
 * @param fields Database names of the changed fields
 */
function fieldList(fields: string[]): string {
	return fields.map((field) => fieldLabels[field] ?? field).join(', ')
}

/**
 * Loads the next page of entries.
 */
async function loadMore() {
	loading.value = true
	loadError.value = null
	try {
		const page = await api.audit(PAGE_SIZE, entries.value.length)
		entries.value = [...entries.value, ...page.entries]
		total.value = page.total
	} catch (error) {
		loadError.value = errorMessage(error)
		showError(loadError.value)
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
			{{ t('laudatio', 'Who created, changed or deleted persons, groups, functions and terms, and who exported them. Only the names of changed fields are recorded, not their values.') }}
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
						<span v-else-if="entry.entityType !== 'data'" class="muted">{{ t('laudatio', '(deleted, ID {id})', { id: entry.entityId }) }}</span>
					</td>
					<td class="muted">
						{{ fieldList(entry.changedFields) }}
					</td>
				</tr>
			</tbody>
		</table>
		<LoadError v-else-if="loadError !== null" :message="loadError" @retry="loadMore" />
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
