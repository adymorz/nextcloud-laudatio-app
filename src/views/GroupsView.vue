<!--
  - SPDX-FileCopyrightText: 2026 Adrian Dymorz <dev@adrian.dymorz.ch>
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<script setup lang="ts">
import type { Group, OrgFunction } from '../types'

import { mdiAccountGroup, mdiDelete, mdiPencil, mdiPlus } from '@mdi/js'
import { showConfirmation, showError } from '@nextcloud/dialogs'
import { t } from '@nextcloud/l10n'
import NcActionButton from '@nextcloud/vue/components/NcActionButton'
import NcActions from '@nextcloud/vue/components/NcActions'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcEmptyContent from '@nextcloud/vue/components/NcEmptyContent'
import NcIconSvgWrapper from '@nextcloud/vue/components/NcIconSvgWrapper'
import NcLoadingIcon from '@nextcloud/vue/components/NcLoadingIcon'
import NcUserBubble from '@nextcloud/vue/components/NcUserBubble'
import { onMounted, ref } from 'vue'
import { api, errorMessage } from '../api'
import GroupDialog from '../components/GroupDialog.vue'
import NameDialog from '../components/NameDialog.vue'
import { me } from '../store'

const groups = ref<Group[]>([])
const loading = ref(true)
const groupDialogOpen = ref(false)
const editedGroup = ref<Group | null>(null)
const nameDialogOpen = ref(false)
const nameDialog = ref<{ title: string, value: string, save: (name: string) => Promise<unknown> }>({ title: '', value: '', save: async () => {} })

const roleLabels: Record<Group['role'], string> = {
	manager: t('appreciation', 'Manager'),
	responsible: t('appreciation', 'Responsible'),
	interested: t('appreciation', 'For information'),
}

/**
 * Loads all visible groups.
 */
async function load() {
	try {
		groups.value = await api.groups()
	} catch (error) {
		showError(errorMessage(error))
	} finally {
		loading.value = false
	}
}

/**
 * @param group Group to edit, null for a new group
 */
function openGroup(group: Group | null) {
	editedGroup.value = group
	groupDialogOpen.value = true
}

/**
 * @param group The group
 */
async function deleteGroup(group: Group) {
	const confirmed = await showConfirmation({
		name: t('appreciation', 'Delete group'),
		text: t('appreciation', 'Delete the group "{name}"?', { name: group.name }),
		labelConfirm: t('appreciation', 'Delete'),
		labelReject: t('appreciation', 'Cancel'),
		severity: 'warning',
	})
	if (confirmed) {
		await run(() => api.deleteGroup(group.id))
	}
}

/**
 * @param group The group
 */
function addFunction(group: Group) {
	nameDialog.value = {
		title: t('appreciation', 'New function in {group}', { group: group.name }),
		value: '',
		save: (name) => api.createFunction(group.id, name),
	}
	nameDialogOpen.value = true
}

/**
 * @param fn The function
 */
function renameFunction(fn: OrgFunction) {
	nameDialog.value = {
		title: t('appreciation', 'Rename function'),
		value: fn.name,
		save: (name) => api.updateFunction(fn.id, name),
	}
	nameDialogOpen.value = true
}

/**
 * @param fn The function
 */
async function deleteFunction(fn: OrgFunction) {
	const confirmed = await showConfirmation({
		name: t('appreciation', 'Delete function'),
		text: t('appreciation', 'Delete the function "{name}"?', { name: fn.name }),
		labelConfirm: t('appreciation', 'Delete'),
		labelReject: t('appreciation', 'Cancel'),
		severity: 'warning',
	})
	if (confirmed) {
		await run(() => api.deleteFunction(fn.id))
	}
}

/**
 * Runs a change and reloads the groups, or shows the error.
 *
 * @param change The API call
 */
async function run(change: () => Promise<unknown>) {
	try {
		await change()
		await load()
	} catch (error) {
		showError(errorMessage(error))
	}
}

onMounted(load)
</script>

<template>
	<div class="view">
		<div class="view__header">
			<h2>{{ t('appreciation', 'Groups & functions') }}</h2>
			<NcButton v-if="me.isManager" variant="primary" @click="openGroup(null)">
				<template #icon>
					<NcIconSvgWrapper :path="mdiPlus" />
				</template>
				{{ t('appreciation', 'New group') }}
			</NcButton>
		</div>

		<NcLoadingIcon v-if="loading" :size="32" />
		<NcEmptyContent v-else-if="groups.length === 0" :name="t('appreciation', 'No groups yet')">
			<template #icon>
				<NcIconSvgWrapper :path="mdiAccountGroup" />
			</template>
		</NcEmptyContent>
		<section v-for="group in groups" :key="group.id" class="group">
			<div class="group__header">
				<h3>{{ group.name }}</h3>
				<span class="group__role">{{ roleLabels[group.role] }}</span>
				<NcActions v-if="me.isManager">
					<NcActionButton close-after-click @click="openGroup(group)">
						<template #icon>
							<NcIconSvgWrapper :path="mdiPencil" />
						</template>
						{{ t('appreciation', 'Edit group') }}
					</NcActionButton>
					<NcActionButton close-after-click @click="addFunction(group)">
						<template #icon>
							<NcIconSvgWrapper :path="mdiPlus" />
						</template>
						{{ t('appreciation', 'Add function') }}
					</NcActionButton>
					<NcActionButton close-after-click @click="deleteGroup(group)">
						<template #icon>
							<NcIconSvgWrapper :path="mdiDelete" />
						</template>
						{{ t('appreciation', 'Delete group') }}
					</NcActionButton>
				</NcActions>
			</div>

			<dl class="group__details">
				<dt>{{ t('appreciation', 'Responsible person') }}</dt>
				<dd>
					<NcUserBubble :user="group.responsible.id" :display-name="group.responsible.displayName" />
				</dd>
				<dt>{{ t('appreciation', 'Interested persons') }}</dt>
				<dd>
					<span v-if="group.interested.length === 0" class="muted">{{ t('appreciation', 'none') }}</span>
					<NcUserBubble
						v-for="user in group.interested"
						:key="user.id"
						:user="user.id"
						:display-name="user.displayName" />
				</dd>
				<dt>{{ t('appreciation', 'Jubilee years') }}</dt>
				<dd>
					{{ group.effectiveJubileeYears.join(', ') || t('appreciation', 'none') }}
					<span class="muted">
						{{ group.jubileeYears === null ? t('appreciation', '(global setting)') : t('appreciation', '(group setting)') }}
					</span>
				</dd>
				<dt>{{ t('appreciation', 'Functions') }}</dt>
				<dd>
					<span v-if="group.functions.length === 0" class="muted">{{ t('appreciation', 'none') }}</span>
					<ul class="functions">
						<li v-for="fn in group.functions" :key="fn.id" class="function">
							{{ fn.name }}
							<NcActions v-if="me.isManager" :aria-label="t('appreciation', 'Actions for {name}', { name: fn.name })">
								<NcActionButton close-after-click @click="renameFunction(fn)">
									<template #icon>
										<NcIconSvgWrapper :path="mdiPencil" />
									</template>
									{{ t('appreciation', 'Rename') }}
								</NcActionButton>
								<NcActionButton close-after-click @click="deleteFunction(fn)">
									<template #icon>
										<NcIconSvgWrapper :path="mdiDelete" />
									</template>
									{{ t('appreciation', 'Delete') }}
								</NcActionButton>
							</NcActions>
						</li>
					</ul>
				</dd>
			</dl>
		</section>

		<GroupDialog v-model:open="groupDialogOpen" :group="editedGroup" @saved="load" />
		<NameDialog
			v-model:open="nameDialogOpen"
			:title="nameDialog.title"
			:label="t('appreciation', 'Name of the function')"
			:initial-value="nameDialog.value"
			@submit="(name) => run(() => nameDialog.save(name))" />
	</div>
</template>

<style scoped>
.view {
	padding: calc(var(--default-grid-baseline) * 4);
	padding-inline-start: calc(var(--default-clickable-area) + var(--default-grid-baseline) * 4);
	max-width: 900px;
}

.view__header {
	display: flex;
	align-items: center;
	justify-content: space-between;
	gap: calc(var(--default-grid-baseline) * 4);
	margin-block-end: calc(var(--default-grid-baseline) * 4);
}

.group {
	padding: calc(var(--default-grid-baseline) * 3) calc(var(--default-grid-baseline) * 4);
	margin-block-end: calc(var(--default-grid-baseline) * 4);
	border: 1px solid var(--color-border);
	border-radius: var(--border-radius-large);
}

.group__header {
	display: flex;
	align-items: center;
	gap: calc(var(--default-grid-baseline) * 3);
}

.group__header h3 {
	flex: 1;
	margin: 0;
}

.group__role,
.muted {
	color: var(--color-text-maxcontrast);
}

.group__details {
	display: grid;
	grid-template-columns: max-content 1fr;
	gap: calc(var(--default-grid-baseline) * 2) calc(var(--default-grid-baseline) * 6);
	margin-block-start: calc(var(--default-grid-baseline) * 3);
}

.group__details dt {
	text-align: start;
	color: var(--color-text-maxcontrast);
}

.function {
	display: flex;
	align-items: center;
	gap: var(--default-grid-baseline);
}
</style>
