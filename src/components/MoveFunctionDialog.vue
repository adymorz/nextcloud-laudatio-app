<!--
  - SPDX-FileCopyrightText: 2026 Adrian Dymorz <dev@adrian.dymorz.ch>
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<script setup lang="ts">
import type { Group, OrgFunction } from '../types'

import { showError } from '@nextcloud/dialogs'
import { t } from '@nextcloud/l10n'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcDialog from '@nextcloud/vue/components/NcDialog'
import NcNoteCard from '@nextcloud/vue/components/NcNoteCard'
import NcSelect from '@nextcloud/vue/components/NcSelect'
import { computed, ref, watch } from 'vue'
import { api, errorMessage } from '../api'

interface GroupOption {
	id: number
	label: string
}

const props = defineProps<{
	/** Current group of the function */
	group: Group
	/** Function to move */
	orgFunction: OrgFunction
	/** All groups, the targets */
	groups: Group[]
}>()

const emit = defineEmits<{
	saved: [orgFunction: OrgFunction]
}>()

const open = defineModel<boolean>('open', { required: true })

const options = computed<GroupOption[]>(() => props.groups
	.filter((group) => group.id !== props.group.id)
	.map((group) => ({ id: group.id, label: group.name })))

const target = ref<GroupOption | null>(null)
const saving = ref(false)

const title = computed(() => t('laudatio', 'Move function "{name}"', { name: props.orgFunction.name }))

const targetGroup = computed(() => props.groups.find((group) => group.id === target.value?.id) ?? null)
// The jubilee years come from the group, so the anniversaries may change (see EventService)
const targetYears = computed(() => {
	if (targetGroup.value === null
		|| targetGroup.value.effectiveJubileeYears.join(',') === props.group.effectiveJubileeYears.join(',')) {
		return null
	}
	return targetGroup.value.effectiveJubileeYears.join(', ') || t('laudatio', 'none')
})

watch(open, (isOpen) => {
	if (isOpen) {
		target.value = null
	}
}, { immediate: true })

/**
 * Moves the function to the selected group.
 */
async function save() {
	if (target.value === null) {
		return
	}
	saving.value = true
	try {
		const saved = await api.moveFunction(props.orgFunction.id, target.value.id)
		emit('saved', saved)
		open.value = false
	} catch (error) {
		showError(errorMessage(error))
	} finally {
		saving.value = false
	}
}
</script>

<template>
	<NcDialog
		v-model:open="open"
		:name="title"
		size="small">
		<form class="form" @submit.prevent="save">
			<NcSelect
				v-model="target"
				:options="options"
				:input-label="t('laudatio', 'Group')"
				:clearable="false"
				label="label" />
			<NcNoteCard
				type="info"
				:text="t('laudatio', 'All terms of this function, also past ones, then belong to the new group. Its responsible person gets the reminders, and its responsible and interested persons see everybody holding this function.')" />
			<NcNoteCard
				v-if="targetYears !== null"
				type="warning"
				:text="t('laudatio', 'The anniversary years of the new group apply: {years}. The upcoming anniversaries of everybody holding this function are recalculated.', { years: targetYears })" />
			<button type="submit" hidden />
		</form>
		<template #actions>
			<NcButton @click="open = false">
				{{ t('laudatio', 'Cancel') }}
			</NcButton>
			<NcButton variant="primary" :disabled="target === null || saving" @click="save">
				{{ t('laudatio', 'Move') }}
			</NcButton>
		</template>
	</NcDialog>
</template>

<style scoped>
.form {
	display: flex;
	flex-direction: column;
	gap: calc(var(--default-grid-baseline) * 2);
}
</style>
