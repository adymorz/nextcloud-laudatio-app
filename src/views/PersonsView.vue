<!--
  - SPDX-FileCopyrightText: 2026 Adrian Dymorz <dev@adrian.dymorz.ch>
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<script setup lang="ts">
import type { Person, PersonDetail } from '../types'

import { mdiAccountMultiple, mdiMagnify, mdiPlus } from '@mdi/js'
import { showError } from '@nextcloud/dialogs'
import { t } from '@nextcloud/l10n'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcEmptyContent from '@nextcloud/vue/components/NcEmptyContent'
import NcIconSvgWrapper from '@nextcloud/vue/components/NcIconSvgWrapper'
import NcLoadingIcon from '@nextcloud/vue/components/NcLoadingIcon'
import NcTextField from '@nextcloud/vue/components/NcTextField'
import { computed, onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import { api, errorMessage } from '../api'
import LoadError from '../components/LoadError.vue'
import PersonDialog from '../components/PersonDialog.vue'
import { formatDate } from '../dates'
import { me } from '../store'

const router = useRouter()
const persons = ref<Person[]>([])
const loading = ref(true)
const loadError = ref<string | null>(null)
const search = ref('')
const dialogOpen = ref(false)

const filtered = computed(() => {
	const query = search.value.trim().toLocaleLowerCase()
	if (query === '') {
		return persons.value
	}
	return persons.value.filter((person) => person.displayName.toLocaleLowerCase().includes(query)
		|| person.currentFunctions.some((slice) => slice.functionName.toLocaleLowerCase().includes(query) || slice.groupName.toLocaleLowerCase().includes(query)))
})

const showDateOfBirth = computed(() => persons.value.some((person) => person.sensitiveVisible))

/**
 * Loads all visible persons.
 */
async function load() {
	loading.value = true
	loadError.value = null
	try {
		persons.value = await api.persons()
	} catch (error) {
		loadError.value = errorMessage(error)
		showError(loadError.value)
	} finally {
		loading.value = false
	}
}

/**
 * @param person The new person
 */
function onCreated(person: PersonDetail) {
	router.push({ name: 'person', params: { id: person.id } })
}

onMounted(load)
</script>

<template>
	<div class="view">
		<div class="view__header">
			<h2>{{ t('laudatio', 'Persons') }}</h2>
			<NcButton v-if="me.isManager" variant="primary" @click="dialogOpen = true">
				<template #icon>
					<NcIconSvgWrapper :path="mdiPlus" />
				</template>
				{{ t('laudatio', 'New person') }}
			</NcButton>
		</div>

		<NcTextField
			v-model="search"
			class="view__search"
			:label="t('laudatio', 'Search by name, function or group')"
			trailing-button-icon="close"
			:show-trailing-button="search !== ''"
			@trailing-button-click="search = ''">
			<template #icon>
				<NcIconSvgWrapper :path="mdiMagnify" />
			</template>
		</NcTextField>

		<NcLoadingIcon v-if="loading" :size="32" />
		<LoadError v-else-if="loadError !== null" :message="loadError" @retry="load" />
		<NcEmptyContent
			v-else-if="persons.length === 0"
			:name="t('laudatio', 'No persons yet')"
			:description="me.isManager
				? t('laudatio', 'Add the persons of your organization and assign them their functions.')
				: t('laudatio', 'The persons of your groups appear here.')">
			<template #icon>
				<NcIconSvgWrapper :path="mdiAccountMultiple" />
			</template>
		</NcEmptyContent>
		<NcEmptyContent v-else-if="filtered.length === 0" :name="t('laudatio', 'No persons match your search')">
			<template #icon>
				<NcIconSvgWrapper :path="mdiMagnify" />
			</template>
		</NcEmptyContent>
		<table v-else class="persons">
			<thead>
				<tr>
					<th>{{ t('laudatio', 'Name') }}</th>
					<th>{{ t('laudatio', 'Current functions') }}</th>
					<th v-if="showDateOfBirth">
						{{ t('laudatio', 'Date of birth') }}
					</th>
				</tr>
			</thead>
			<tbody>
				<tr v-for="person in filtered" :key="person.id">
					<td>
						<RouterLink :to="{ name: 'person', params: { id: person.id } }">
							{{ person.displayName }}
						</RouterLink>
					</td>
					<td>
						<span v-if="person.currentFunctions.length === 0" class="muted">{{ t('laudatio', 'none') }}</span>
						<span v-for="slice in person.currentFunctions" :key="slice.id" class="function">
							{{ slice.functionName }} <span class="muted">({{ slice.groupName }})</span>
						</span>
					</td>
					<td v-if="showDateOfBirth">
						{{ person.dateOfBirth ? formatDate(person.dateOfBirth) : '' }}
					</td>
				</tr>
			</tbody>
		</table>

		<PersonDialog v-model:open="dialogOpen" :person="null" @saved="onCreated" />
	</div>
</template>

<style scoped>
.view {
	padding: calc(var(--default-grid-baseline) * 4);
	padding-inline-start: calc(var(--default-clickable-area) + var(--default-grid-baseline) * 4);
	max-width: 1100px;
}

.view__header {
	display: flex;
	align-items: center;
	justify-content: space-between;
	gap: calc(var(--default-grid-baseline) * 4);
	margin-block-end: calc(var(--default-grid-baseline) * 4);
}

.view__search {
	max-width: 400px;
	margin-block-end: calc(var(--default-grid-baseline) * 4);
}

.persons {
	width: 100%;
	border-collapse: collapse;
}

.persons th {
	text-align: start;
	color: var(--color-text-maxcontrast);
	font-weight: normal;
}

.persons th,
.persons td {
	padding: calc(var(--default-grid-baseline) * 2);
	border-block-end: 1px solid var(--color-border);
	vertical-align: top;
}

.persons a {
	font-weight: bold;
}

.function {
	display: block;
}

.muted {
	color: var(--color-text-maxcontrast);
}
</style>
