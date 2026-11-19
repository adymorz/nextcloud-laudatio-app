<!--
  - SPDX-FileCopyrightText: 2026 Adrian Dymorz <dev@adrian.dymorz.ch>
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<script setup lang="ts">
import type { Group, OrgFunction, Person } from '../types'

import { mdiAccountGroup, mdiPrinter } from '@mdi/js'
import { t } from '@nextcloud/l10n'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcEmptyContent from '@nextcloud/vue/components/NcEmptyContent'
import NcIconSvgWrapper from '@nextcloud/vue/components/NcIconSvgWrapper'
import NcLoadingIcon from '@nextcloud/vue/components/NcLoadingIcon'
import { computed, onMounted, ref } from 'vue'
import { api, errorMessage } from '../api'
import LoadError from '../components/LoadError.vue'
import { formatDate, toIsoDate } from '../dates'
import { PLAIN } from '../l10n'
import { usePrintLayout } from '../print'
import { showErrorText } from '../toast'

const groups = ref<Group[]>([])
const persons = ref<Person[]>([])
const loading = ref(true)
const loadError = ref<string | null>(null)
const today = toIsoDate(new Date())
const printedOn = formatDate(today)
const { print } = usePrintLayout()

/**
 * The function as it is listed for the person: the name if they hold it
 * today, with the start date if all their terms in it start later (e.g. after
 * an election), null if they have no current or later term in it.
 *
 * @param person The person
 * @param fn Function of the group
 */
function functionLabel(person: Person, fn: OrgFunction): string | null {
	const starts = person.currentFunctions
		.filter((slice) => slice.functionId === fn.id)
		.map((slice) => slice.startDate)
		.sort()
	if (starts.length === 0) {
		return null
	}
	// Dates as YYYY-MM-DD compare as strings; a re-election starts after the current term
	if (starts[0]! <= today) {
		return fn.name
	}
	return t('laudatio', '{function} from {date}', { function: fn.name, date: formatDate(starts[0]!) }, PLAIN)
}

// Names and functions only: the date of birth and contact data are in the
// payload for managers and responsible persons, but never shown here
const sections = computed(() => groups.value.map((group) => {
	const members = persons.value
		.map((person) => ({
			person,
			functions: group.functions
				.map((fn) => functionLabel(person, fn))
				.filter((label) => label !== null),
		}))
		.filter((member) => member.functions.length > 0)
		.sort((a, b) => a.person.lastName.localeCompare(b.person.lastName) || a.person.firstName.localeCompare(b.person.firstName))
	return { group, members }
}))

/**
 * Loads the visible groups and persons.
 */
async function load() {
	loading.value = true
	loadError.value = null
	try {
		[groups.value, persons.value] = await Promise.all([api.groups(), api.persons()])
	} catch (error) {
		loadError.value = errorMessage(error)
		showErrorText(loadError.value)
	} finally {
		loading.value = false
	}
}

onMounted(load)
</script>

<template>
	<div class="overview">
		<div class="overview__toolbar">
			<NcButton variant="primary" :disabled="sections.length === 0" @click="print">
				<template #icon>
					<NcIconSvgWrapper :path="mdiPrinter" />
				</template>
				{{ t('laudatio', 'Print') }}
			</NcButton>
			<span class="muted">{{ t('laudatio', 'To get a PDF, choose "Save as PDF" in the print dialog.') }}</span>
		</div>

		<NcLoadingIcon v-if="loading" :size="32" />
		<LoadError v-else-if="loadError !== null" :message="loadError" @retry="load" />
		<NcEmptyContent v-else-if="sections.length === 0" :name="t('laudatio', 'No groups yet')">
			<template #icon>
				<NcIconSvgWrapper :path="mdiAccountGroup" />
			</template>
		</NcEmptyContent>
		<article v-else class="overview__page">
			<h2 class="overview__title">
				{{ t('laudatio', 'Persons by group') }}
			</h2>
			<p class="overview__meta muted">
				{{ t('laudatio', 'Printed on {date}', { date: printedOn }) }}
			</p>

			<section v-for="{ group, members } in sections" :key="group.id" class="overview__group">
				<h3>
					{{ group.name }}
					<span class="muted">({{ group.responsible?.displayName ?? t('laudatio', 'no responsible person') }})</span>
				</h3>
				<ul v-if="members.length > 0">
					<li v-for="member in members" :key="member.person.id">
						{{ member.person.displayName }}
						<span class="muted">({{ member.functions.join(', ') }})</span>
					</li>
				</ul>
				<p v-else class="muted">
					{{ t('laudatio', 'No current members') }}
				</p>
			</section>
		</article>
	</div>
</template>

<style scoped>
.overview {
	padding: calc(var(--default-grid-baseline) * 4);
	padding-inline-start: calc(var(--default-clickable-area) + var(--default-grid-baseline) * 4);
	max-width: 900px;
}

.overview__toolbar {
	display: flex;
	flex-wrap: wrap;
	align-items: center;
	gap: calc(var(--default-grid-baseline) * 2);
	margin-block-end: calc(var(--default-grid-baseline) * 4);
}

.overview__title {
	margin-block: 0;
}

.overview__meta {
	margin-block-end: calc(var(--default-grid-baseline) * 4);
}

.overview__group h3 {
	margin-block: calc(var(--default-grid-baseline) * 4) var(--default-grid-baseline);
}

.overview__group ul {
	padding-inline-start: calc(var(--default-grid-baseline) * 6);
	list-style: disc;
}

.overview__group li {
	padding-block: calc(var(--default-grid-baseline) / 2);
}

.muted {
	color: var(--color-text-maxcontrast);
}

@media print {
	.overview {
		max-width: none;
		padding: 0;
	}

	.overview__toolbar {
		display: none;
	}

	/* Black on white, also with a dark theme */
	.overview__page,
	.overview__page * {
		color: #000 !important;
		background: #fff !important;
	}

	.overview__group h3 {
		break-after: avoid;
	}

	.overview__group li {
		break-inside: avoid;
	}
}
</style>
