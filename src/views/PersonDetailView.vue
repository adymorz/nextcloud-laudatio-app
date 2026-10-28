<!--
  - SPDX-FileCopyrightText: 2026 Adrian Dymorz <dev@adrian.dymorz.ch>
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<script setup lang="ts">
import type { Group, PersonDetail, Slice } from '../types'

import { mdiArrowLeft, mdiDelete, mdiPencil, mdiPlus } from '@mdi/js'
import { showConfirmation, showError } from '@nextcloud/dialogs'
import { n, t } from '@nextcloud/l10n'
import NcActionButton from '@nextcloud/vue/components/NcActionButton'
import NcActions from '@nextcloud/vue/components/NcActions'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcEmptyContent from '@nextcloud/vue/components/NcEmptyContent'
import NcIconSvgWrapper from '@nextcloud/vue/components/NcIconSvgWrapper'
import NcLoadingIcon from '@nextcloud/vue/components/NcLoadingIcon'
import { computed, ref, watch } from 'vue'
import { useRouter } from 'vue-router'
import { api, errorMessage } from '../api'
import PersonDialog from '../components/PersonDialog.vue'
import SliceDialog from '../components/SliceDialog.vue'
import { age, daysUntil, formatDate } from '../dates'
import { me } from '../store'

const props = defineProps<{
	id: number
}>()

const router = useRouter()
const person = ref<PersonDetail | null>(null)
const groups = ref<Group[]>([])
const loading = ref(true)
const notFound = ref(false)
const personDialogOpen = ref(false)
const sliceDialogOpen = ref(false)
const editedSlice = ref<Slice | null>(null)

const slices = computed(() => [...(person.value?.slices ?? [])].sort((a, b) => b.startDate.localeCompare(a.startDate)))

/**
 * Loads the person (and, for managers, the groups for the slice dialog).
 */
async function load() {
	loading.value = true
	notFound.value = false
	try {
		person.value = await api.person(props.id)
		if (me.isManager) {
			groups.value = await api.groups()
		}
	} catch (error) {
		notFound.value = true
		showError(errorMessage(error))
	} finally {
		loading.value = false
	}
}

/**
 * @param slice Time slice
 */
function isActive(slice: Slice): boolean {
	return slice.endDate === null || daysUntil(slice.endDate) >= 0
}

/**
 * @param slice Time slice, null to add one
 */
function openSlice(slice: Slice | null) {
	editedSlice.value = slice
	sliceDialogOpen.value = true
}

/**
 * Deletes the person after confirmation.
 */
async function deletePerson() {
	if (person.value === null) {
		return
	}
	const confirmed = await showConfirmation({
		name: t('appreciation', 'Delete person'),
		text: t('appreciation', 'Delete {name} with all functions and events? This cannot be undone.', { name: person.value.displayName }),
		labelConfirm: t('appreciation', 'Delete'),
		labelReject: t('appreciation', 'Cancel'),
		severity: 'warning',
	})
	if (!confirmed) {
		return
	}
	try {
		await api.deletePerson(person.value.id)
		router.push({ name: 'persons' })
	} catch (error) {
		showError(errorMessage(error))
	}
}

/**
 * @param slice Time slice to delete
 */
async function deleteSlice(slice: Slice) {
	const confirmed = await showConfirmation({
		name: t('appreciation', 'Delete time slice'),
		text: t('appreciation', 'Delete the time slice "{function}" from {start}?', { function: slice.functionName, start: formatDate(slice.startDate) }),
		labelConfirm: t('appreciation', 'Delete'),
		labelReject: t('appreciation', 'Cancel'),
		severity: 'warning',
	})
	if (!confirmed) {
		return
	}
	try {
		await api.deleteSlice(slice.id)
		await load()
	} catch (error) {
		showError(errorMessage(error))
	}
}

watch(() => props.id, load, { immediate: true })
</script>

<template>
	<div class="view">
		<NcButton variant="tertiary" :to="{ name: 'persons' }">
			<template #icon>
				<NcIconSvgWrapper :path="mdiArrowLeft" />
			</template>
			{{ t('appreciation', 'All persons') }}
		</NcButton>

		<NcLoadingIcon v-if="loading && person === null" :size="32" />
		<NcEmptyContent v-else-if="notFound || person === null" :name="t('appreciation', 'Person not found')" />
		<template v-else>
			<div class="view__header">
				<h2>{{ person.displayName }}</h2>
				<div v-if="me.isManager" class="view__actions">
					<NcButton @click="personDialogOpen = true">
						<template #icon>
							<NcIconSvgWrapper :path="mdiPencil" />
						</template>
						{{ t('appreciation', 'Edit') }}
					</NcButton>
					<NcButton variant="error" @click="deletePerson">
						<template #icon>
							<NcIconSvgWrapper :path="mdiDelete" />
						</template>
						{{ t('appreciation', 'Delete') }}
					</NcButton>
				</div>
			</div>

			<dl v-if="person.sensitiveVisible" class="profile">
				<dt>{{ t('appreciation', 'Date of birth') }}</dt>
				<dd v-if="person.dateOfBirth">
					{{ formatDate(person.dateOfBirth) }}
					<span class="muted">({{ n('appreciation', '%n year old', '%n years old', age(person.dateOfBirth)) }})</span>
				</dd>
				<dt>{{ t('appreciation', 'Notes') }}</dt>
				<dd class="profile__notes">
					{{ person.notes ?? t('appreciation', 'No notes') }}
				</dd>
			</dl>

			<div class="section__header">
				<h3>{{ t('appreciation', 'Function history') }}</h3>
				<NcButton v-if="me.isManager" @click="openSlice(null)">
					<template #icon>
						<NcIconSvgWrapper :path="mdiPlus" />
					</template>
					{{ t('appreciation', 'Add function') }}
				</NcButton>
			</div>
			<p v-if="slices.length === 0" class="muted">
				{{ t('appreciation', 'No functions yet.') }}
			</p>
			<ol v-else class="timeline">
				<li v-for="slice in slices"
					:key="slice.id"
					class="timeline__item"
					:class="{ 'timeline__item--active': isActive(slice) }">
					<div class="timeline__text">
						<strong>{{ slice.functionName }}</strong> <span class="muted">{{ slice.groupName }}</span>
						<span class="timeline__dates">
							<template v-if="slice.endDate === null">
								{{ t('appreciation', 'since {date}', { date: formatDate(slice.startDate) }) }}
							</template>
							<template v-else>
								{{ formatDate(slice.startDate) }} – {{ formatDate(slice.endDate) }}
							</template>
						</span>
					</div>
					<NcActions v-if="me.isManager">
						<NcActionButton close-after-click @click="openSlice(slice)">
							<template #icon>
								<NcIconSvgWrapper :path="mdiPencil" />
							</template>
							{{ t('appreciation', 'Edit') }}
						</NcActionButton>
						<NcActionButton close-after-click @click="deleteSlice(slice)">
							<template #icon>
								<NcIconSvgWrapper :path="mdiDelete" />
							</template>
							{{ t('appreciation', 'Delete') }}
						</NcActionButton>
					</NcActions>
				</li>
			</ol>

			<PersonDialog v-model:open="personDialogOpen" :person="person" @saved="load" />
			<SliceDialog
				v-model:open="sliceDialogOpen"
				:person-id="person.id"
				:slice="editedSlice"
				:groups="groups"
				@saved="load" />
		</template>
	</div>
</template>

<style scoped>
.view {
	padding: calc(var(--default-grid-baseline) * 4);
	padding-inline-start: calc(var(--default-clickable-area) + var(--default-grid-baseline) * 4);
	max-width: 900px;
}

.view__header,
.section__header {
	display: flex;
	flex-wrap: wrap;
	align-items: center;
	justify-content: space-between;
	gap: calc(var(--default-grid-baseline) * 2);
	margin-block: calc(var(--default-grid-baseline) * 4);
}

.view__actions {
	display: flex;
	gap: calc(var(--default-grid-baseline) * 2);
}

.profile {
	display: grid;
	grid-template-columns: max-content 1fr;
	gap: calc(var(--default-grid-baseline) * 2) calc(var(--default-grid-baseline) * 6);
}

.profile dt {
	text-align: start;
	color: var(--color-text-maxcontrast);
}

.profile__notes {
	white-space: pre-wrap;
}

.timeline {
	border-inline-start: 2px solid var(--color-border-dark);
	margin-inline-start: calc(var(--default-grid-baseline) * 2);
}

.timeline__item {
	position: relative;
	display: flex;
	align-items: center;
	justify-content: space-between;
	padding: calc(var(--default-grid-baseline) * 2) calc(var(--default-grid-baseline) * 4);
}

.timeline__item::before {
	content: '';
	position: absolute;
	inset-inline-start: -7px;
	width: 12px;
	height: 12px;
	border-radius: 50%;
	background-color: var(--color-border-dark);
}

.timeline__item--active::before {
	background-color: var(--color-primary-element);
}

.timeline__text {
	display: flex;
	flex-direction: column;
}

.timeline__dates,
.muted {
	color: var(--color-text-maxcontrast);
}
</style>
