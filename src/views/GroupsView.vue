<!--
  - SPDX-FileCopyrightText: 2026 Adrian Dymorz <dev@adrian.dymorz.ch>
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<script setup lang="ts">
import type { Group, OrgFunction } from '../types'

import { mdiAccountGroup, mdiAlert, mdiDelete, mdiGift, mdiPencil, mdiPlus } from '@mdi/js'
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
import FunctionDialog from '../components/FunctionDialog.vue'
import GroupDialog from '../components/GroupDialog.vue'
import LoadError from '../components/LoadError.vue'
import { me } from '../store'

const groups = ref<Group[]>([])
const loading = ref(true)
const loadError = ref<string | null>(null)
const groupDialogOpen = ref(false)
const editedGroup = ref<Group | null>(null)
const functionDialogOpen = ref(false)
const functionGroup = ref<Group | null>(null)
const editedFunction = ref<OrgFunction | null>(null)

const roleLabels: Record<Group['role'], string> = {
	manager: t('laudatio', 'Manager'),
	responsible: t('laudatio', 'Responsible'),
	interested: t('laudatio', 'Interested'),
}

// First steps for a manager of an empty app
const emptyHint = me.isManager
	? t('laudatio', 'Create a group, e.g. "Board", with its functions, e.g. "President" and "Treasurer", and choose the responsible person who gets the reminders.')
	: ''

/**
 * Loads all visible groups.
 */
async function load() {
	loadError.value = null
	try {
		groups.value = await api.groups()
	} catch (error) {
		loadError.value = errorMessage(error)
		showError(loadError.value)
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
		name: t('laudatio', 'Delete group'),
		text: t('laudatio', 'Delete the group "{name}"?', { name: group.name }),
		labelConfirm: t('laudatio', 'Delete'),
		labelReject: t('laudatio', 'Cancel'),
		severity: 'warning',
	})
	if (confirmed) {
		await run(() => api.deleteGroup(group.id))
	}
}

/**
 * @param group Group of the function
 * @param fn Function to edit, null to add one
 */
function openFunction(group: Group, fn: OrgFunction | null) {
	functionGroup.value = group
	editedFunction.value = fn
	functionDialogOpen.value = true
}

/**
 * @param fn The function
 */
async function deleteFunction(fn: OrgFunction) {
	const confirmed = await showConfirmation({
		name: t('laudatio', 'Delete function'),
		text: t('laudatio', 'Delete the function "{name}"?', { name: fn.name }),
		labelConfirm: t('laudatio', 'Delete'),
		labelReject: t('laudatio', 'Cancel'),
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
			<h2>{{ t('laudatio', 'Groups & functions') }}</h2>
			<NcButton v-if="me.isManager" variant="primary" @click="openGroup(null)">
				<template #icon>
					<NcIconSvgWrapper :path="mdiPlus" />
				</template>
				{{ t('laudatio', 'New group') }}
			</NcButton>
		</div>

		<NcLoadingIcon v-if="loading" :size="32" />
		<LoadError v-else-if="loadError !== null" :message="loadError" @retry="load" />
		<NcEmptyContent
			v-else-if="groups.length === 0"
			:name="t('laudatio', 'No groups yet')"
			:description="emptyHint">
			<template #icon>
				<NcIconSvgWrapper :path="mdiAccountGroup" />
			</template>
		</NcEmptyContent>
		<section v-for="group in groups" :key="group.id" class="group">
			<div class="group__header">
				<h3>{{ group.name }}</h3>
				<span class="group__role">{{ roleLabels[group.role] }}</span>
				<NcActions v-if="me.isManager" :aria-label="t('laudatio', 'Actions for {name}', { name: group.name })">
					<NcActionButton close-after-click @click="openGroup(group)">
						<template #icon>
							<NcIconSvgWrapper :path="mdiPencil" />
						</template>
						{{ t('laudatio', 'Edit group') }}
					</NcActionButton>
					<NcActionButton close-after-click @click="openFunction(group, null)">
						<template #icon>
							<NcIconSvgWrapper :path="mdiPlus" />
						</template>
						{{ t('laudatio', 'Add function') }}
					</NcActionButton>
					<NcActionButton close-after-click @click="deleteGroup(group)">
						<template #icon>
							<NcIconSvgWrapper :path="mdiDelete" />
						</template>
						{{ t('laudatio', 'Delete group') }}
					</NcActionButton>
				</NcActions>
			</div>

			<dl class="group__details">
				<dt>{{ t('laudatio', 'Responsible person') }}</dt>
				<dd>
					<NcUserBubble v-if="group.responsible" :user="group.responsible.id" :display-name="group.responsible.displayName" />
					<span v-else class="group__warning">
						<NcIconSvgWrapper :path="mdiAlert" :size="20" inline />
						{{ me.isManager
							? t('laudatio', 'No responsible person: the account was deleted. Edit the group to assign a new one.')
							: t('laudatio', 'No responsible person at the moment.') }}
					</span>
				</dd>
				<dt>{{ t('laudatio', 'Interested persons') }}</dt>
				<dd>
					<span v-if="group.interested.length === 0" class="muted">{{ t('laudatio', 'none') }}</span>
					<NcUserBubble
						v-for="user in group.interested"
						:key="user.id"
						:user="user.id"
						:display-name="user.displayName" />
				</dd>
				<dt>{{ t('laudatio', 'Anniversary years') }}</dt>
				<dd>
					{{ group.effectiveJubileeYears.join(', ') || t('laudatio', 'none') }}
					<span class="muted">
						{{ group.jubileeYears === null ? t('laudatio', '(global setting)') : t('laudatio', '(group setting)') }}
					</span>
				</dd>
				<dt>{{ t('laudatio', 'Functions') }}</dt>
				<dd>
					<span v-if="group.functions.length === 0" class="muted">{{ t('laudatio', 'none') }}</span>
					<ul class="functions">
						<li v-for="fn in group.functions" :key="fn.id" class="function">
							{{ fn.name }}
							<NcIconSvgWrapper
								v-if="fn.yearlyGift"
								class="function__gift"
								:path="mdiGift"
								:size="16"
								:name="t('laudatio', 'One gift per year')"
								:title="t('laudatio', 'One gift per year')" />
							<NcActions v-if="me.isManager" :aria-label="t('laudatio', 'Actions for {name}', { name: fn.name })">
								<NcActionButton close-after-click @click="openFunction(group, fn)">
									<template #icon>
										<NcIconSvgWrapper :path="mdiPencil" />
									</template>
									{{ t('laudatio', 'Edit') }}
								</NcActionButton>
								<NcActionButton close-after-click @click="deleteFunction(fn)">
									<template #icon>
										<NcIconSvgWrapper :path="mdiDelete" />
									</template>
									{{ t('laudatio', 'Delete') }}
								</NcActionButton>
							</NcActions>
						</li>
					</ul>
				</dd>
			</dl>
		</section>

		<GroupDialog v-model:open="groupDialogOpen" :group="editedGroup" @saved="load" />
		<FunctionDialog
			v-if="functionGroup"
			v-model:open="functionDialogOpen"
			:group="functionGroup"
			:org-function="editedFunction"
			@saved="load" />
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

.group__warning {
	display: inline-flex;
	align-items: center;
	gap: var(--default-grid-baseline);
	color: var(--color-warning-text);
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

.function__gift {
	color: var(--color-primary-element);
}
</style>
