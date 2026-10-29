<!--
  - SPDX-FileCopyrightText: 2026 Adrian Dymorz <dev@adrian.dymorz.ch>
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<script setup lang="ts">
import type { PersonDetail } from '../types'

import { mdiArrowLeft, mdiPrinter } from '@mdi/js'
import { showError } from '@nextcloud/dialogs'
import { t } from '@nextcloud/l10n'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcEmptyContent from '@nextcloud/vue/components/NcEmptyContent'
import NcIconSvgWrapper from '@nextcloud/vue/components/NcIconSvgWrapper'
import NcLoadingIcon from '@nextcloud/vue/components/NcLoadingIcon'
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import { api, errorMessage } from '../api'
import { formatDate, toIsoDate } from '../dates'

const props = defineProps<{
	id: number
}>()

const person = ref<PersonDetail | null>(null)
const loading = ref(true)
const notFound = ref(false)
const printedOn = formatDate(toIsoDate(new Date()))

// The sheet is handed to the person: the internal notes are deliberately left out
const rows = computed(() => {
	const p = person.value
	if (p === null) {
		return []
	}
	return [
		{ label: t('appreciation', 'First name'), value: p.firstName },
		{ label: t('appreciation', 'Last name'), value: p.lastName },
		{ label: t('appreciation', 'Date of birth'), value: p.dateOfBirth ? formatDate(p.dateOfBirth) : null },
		{ label: t('appreciation', 'Street'), value: p.street },
		{ label: t('appreciation', 'House number'), value: p.streetNumber },
		{ label: t('appreciation', 'ZIP code'), value: p.postalCode },
		{ label: t('appreciation', 'City'), value: p.city },
		{ label: t('appreciation', 'Country'), value: p.country },
		{ label: t('appreciation', 'Mobile phone'), value: p.mobilePhone },
		{ label: t('appreciation', 'Additional phone number'), value: p.otherPhone },
	]
})

const functions = computed(() => [...(person.value?.currentFunctions ?? [])]
	.sort((a, b) => a.groupName.localeCompare(b.groupName) || a.functionName.localeCompare(b.functionName)))

/**
 * Loads the person.
 */
async function load() {
	loading.value = true
	notFound.value = false
	try {
		person.value = await api.person(props.id)
	} catch (error) {
		notFound.value = true
		showError(errorMessage(error))
	} finally {
		loading.value = false
	}
}

/**
 * Opens the browser's print dialog.
 */
function print() {
	window.print()
}

// Enables the print styles below that hide the Nextcloud header and navigation
onMounted(() => document.documentElement.classList.add('appreciation-print'))
onBeforeUnmount(() => document.documentElement.classList.remove('appreciation-print'))

watch(() => props.id, load, { immediate: true })
</script>

<template>
	<div class="sheet">
		<div class="sheet__toolbar">
			<NcButton variant="tertiary" :to="{ name: 'person', params: { id } }">
				<template #icon>
					<NcIconSvgWrapper :path="mdiArrowLeft" />
				</template>
				{{ t('appreciation', 'Back to the person') }}
			</NcButton>
			<template v-if="person?.sensitiveVisible">
				<NcButton variant="primary" @click="print">
					<template #icon>
						<NcIconSvgWrapper :path="mdiPrinter" />
					</template>
					{{ t('appreciation', 'Print') }}
				</NcButton>
				<span class="sheet__hint">{{ t('appreciation', 'To get a PDF, choose "Save as PDF" in the print dialog.') }}</span>
			</template>
		</div>

		<NcLoadingIcon v-if="loading && person === null" :size="32" />
		<NcEmptyContent v-else-if="notFound || person === null" :name="t('appreciation', 'Person not found')" />
		<NcEmptyContent
			v-else-if="!person.sensitiveVisible"
			:name="t('appreciation', 'Data sheet not available')"
			:description="t('appreciation', 'Only managers and the responsible person can print the data sheet.')" />
		<article v-else class="sheet__page">
			<h2 class="sheet__title">
				{{ t('appreciation', 'Personal data sheet') }}
			</h2>
			<p class="sheet__meta">
				{{ person.displayName }} · {{ t('appreciation', 'Printed on {date}', { date: printedOn }) }}
			</p>
			<p>{{ t('appreciation', 'Please check your data. Correct wrong entries and add missing ones in the right column, then sign the sheet and return it.') }}</p>

			<table class="sheet__table">
				<thead>
					<tr>
						<th>{{ t('appreciation', 'Field') }}</th>
						<th>{{ t('appreciation', 'Current entry') }}</th>
						<th>{{ t('appreciation', 'Correction') }}</th>
					</tr>
				</thead>
				<tbody>
					<tr v-for="row in rows" :key="row.label">
						<th scope="row">
							{{ row.label }}
						</th>
						<td>{{ row.value }}</td>
						<td class="sheet__blank" />
					</tr>
				</tbody>
			</table>

			<h3>{{ t('appreciation', 'Current functions') }}</h3>
			<table v-if="functions.length > 0" class="sheet__table">
				<thead>
					<tr>
						<th>{{ t('appreciation', 'Function') }}</th>
						<th>{{ t('appreciation', 'Group') }}</th>
						<th>{{ t('appreciation', 'Start') }}</th>
					</tr>
				</thead>
				<tbody>
					<tr v-for="slice in functions" :key="slice.id">
						<td>{{ slice.functionName }}</td>
						<td>{{ slice.groupName }}</td>
						<td>{{ formatDate(slice.startDate) }}</td>
					</tr>
				</tbody>
			</table>
			<p v-else>
				{{ t('appreciation', 'No current functions.') }}
			</p>

			<h3>{{ t('appreciation', 'Remarks') }}</h3>
			<div class="sheet__lines">
				<span v-for="line in 3" :key="line" />
			</div>

			<div class="sheet__signature">
				<div>{{ t('appreciation', 'Place and date') }}</div>
				<div>{{ t('appreciation', 'Signature') }}</div>
			</div>
		</article>
	</div>
</template>

<style scoped>
.sheet {
	padding: calc(var(--default-grid-baseline) * 4);
	padding-inline-start: calc(var(--default-clickable-area) + var(--default-grid-baseline) * 4);
}

.sheet__toolbar {
	display: flex;
	flex-wrap: wrap;
	align-items: center;
	gap: calc(var(--default-grid-baseline) * 2);
	margin-block-end: calc(var(--default-grid-baseline) * 4);
}

.sheet__hint,
.sheet__meta {
	color: var(--color-text-maxcontrast);
}

.sheet__page {
	box-sizing: border-box;
	max-width: 210mm;
	padding: 15mm;
	border: 1px solid var(--color-border);
	border-radius: var(--border-radius-large);
	background-color: var(--color-main-background);
}

.sheet__title {
	margin-block: 0;
}

.sheet__meta {
	margin-block-end: 6mm;
}

.sheet__page h3 {
	margin-block: 8mm 2mm;
}

.sheet__table {
	width: 100%;
	margin-block-start: 4mm;
	border-collapse: collapse;
}

.sheet__table tr {
	break-inside: avoid;
}

.sheet__table th,
.sheet__table td {
	padding: 2mm 3mm;
	border: 1px solid var(--color-border-dark);
	text-align: start;
	vertical-align: top;
}

.sheet__table tbody th {
	width: 30%;
	font-weight: normal;
}

.sheet__blank {
	width: 40%;
	height: 10mm;
}

.sheet__lines span {
	display: block;
	height: 9mm;
	border-block-end: 1px solid var(--color-border-dark);
}

.sheet__signature {
	display: grid;
	grid-template-columns: 1fr 1fr;
	gap: 10mm;
	margin-block-start: 18mm;
	break-inside: avoid;
}

.sheet__signature div {
	padding-block-start: 2mm;
	border-block-start: 1px solid var(--color-border-dark);
}

@media print {
	.sheet {
		padding: 0;
	}

	.sheet__toolbar {
		display: none;
	}

	.sheet__page {
		max-width: none;
		padding: 0;
		border: none;
		border-radius: 0;
	}

	/* Black on white, also with a dark theme */
	.sheet__page,
	.sheet__page * {
		color: #000 !important;
		background: #fff !important;
	}

	.sheet__table th,
	.sheet__table td,
	.sheet__lines span,
	.sheet__signature div {
		border-color: #000 !important;
	}
}
</style>

<style>
/* Nextcloud's layout is fixed to the viewport and clips its content: print the sheet only, across pages */
@media print {
	@page {
		margin: 15mm;
	}

	html.appreciation-print,
	html.appreciation-print body {
		position: static !important;
		height: auto !important;
		overflow: visible !important;
		background: none !important;
	}

	html.appreciation-print #header,
	html.appreciation-print #skip-actions,
	html.appreciation-print #profiler-toolbar,
	html.appreciation-print .app-navigation {
		display: none !important;
	}

	html.appreciation-print #content,
	html.appreciation-print #content-vue,
	html.appreciation-print #app-content-vue {
		position: static !important;
		width: auto !important;
		height: auto !important;
		margin: 0 !important;
		padding: 0 !important;
		overflow: visible !important;
		border-radius: 0 !important;
		background: none !important;
		backdrop-filter: none !important;
	}
}
</style>
