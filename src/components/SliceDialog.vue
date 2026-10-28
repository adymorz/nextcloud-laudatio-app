<!--
  - SPDX-FileCopyrightText: 2026 Adrian Dymorz <dev@adrian.dymorz.ch>
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<script setup lang="ts">
import type { Group, Slice } from '../types'

import { showError } from '@nextcloud/dialogs'
import { t } from '@nextcloud/l10n'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcDateTimePickerNative from '@nextcloud/vue/components/NcDateTimePickerNative'
import NcDialog from '@nextcloud/vue/components/NcDialog'
import NcSelect from '@nextcloud/vue/components/NcSelect'
import { computed, ref, watch } from 'vue'
import { api, errorMessage } from '../api'
import { parseIsoDate, toIsoDate } from '../dates'

interface FunctionOption {
	id: number
	label: string
}

const props = defineProps<{
	personId: number
	/** Slice to edit, null to add a new one */
	slice: Slice | null
	groups: Group[]
}>()

const emit = defineEmits<{
	saved: [slice: Slice]
}>()

const open = defineModel<boolean>('open', { required: true })

const options = computed<FunctionOption[]>(() => props.groups.flatMap((group) => group.functions.map((fn) => ({
	id: fn.id,
	label: `${fn.name} (${group.name})`,
}))))

const fn = ref<FunctionOption | null>(null)
const startDate = ref<Date | null>(null)
const endDate = ref<Date | null>(null)
const saving = ref(false)

const valid = computed(() => fn.value !== null && startDate.value !== null)

watch(open, (isOpen) => {
	if (isOpen) {
		fn.value = options.value.find((option) => option.id === props.slice?.functionId) ?? null
		startDate.value = props.slice ? parseIsoDate(props.slice.startDate) : null
		endDate.value = props.slice?.endDate ? parseIsoDate(props.slice.endDate) : null
	}
}, { immediate: true })

/**
 * Adds or updates the time slice.
 */
async function save() {
	if (fn.value === null || startDate.value === null) {
		return
	}
	saving.value = true
	const input = {
		functionId: fn.value.id,
		startDate: toIsoDate(startDate.value),
		endDate: endDate.value === null ? null : toIsoDate(endDate.value),
	}
	try {
		const saved = props.slice === null
			? await api.createSlice(props.personId, input)
			: await api.updateSlice(props.slice.id, input)
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
		:name="slice === null ? t('appreciation', 'Add function') : t('appreciation', 'Edit time slice')"
		size="normal">
		<form class="form" @submit.prevent="save">
			<NcSelect
				v-model="fn"
				:options="options"
				:input-label="t('appreciation', 'Function')"
				:clearable="false"
				label="label" />
			<NcDateTimePickerNative
				id="appreciation-slice-start"
				v-model="startDate"
				type="date"
				:label="t('appreciation', 'Start date')" />
			<NcDateTimePickerNative
				id="appreciation-slice-end"
				v-model="endDate"
				type="date"
				:min="startDate"
				:label="t('appreciation', 'End date (leave empty while active)')" />
			<NcButton v-if="endDate !== null" variant="tertiary" @click="endDate = null">
				{{ t('appreciation', 'Remove end date') }}
			</NcButton>
			<button type="submit" hidden />
		</form>
		<template #actions>
			<NcButton @click="open = false">
				{{ t('appreciation', 'Cancel') }}
			</NcButton>
			<NcButton variant="primary" :disabled="!valid || saving" @click="save">
				{{ t('appreciation', 'Save') }}
			</NcButton>
		</template>
	</NcDialog>
</template>

<style scoped>
.form {
	display: flex;
	flex-direction: column;
	gap: calc(var(--default-grid-baseline) * 3);
}
</style>
