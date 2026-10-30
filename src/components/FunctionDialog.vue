<!--
  - SPDX-FileCopyrightText: 2026 Adrian Dymorz <dev@adrian.dymorz.ch>
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<script setup lang="ts">
import type { FunctionInput, Group, OrgFunction } from '../types'

import { showError } from '@nextcloud/dialogs'
import { t } from '@nextcloud/l10n'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcCheckboxRadioSwitch from '@nextcloud/vue/components/NcCheckboxRadioSwitch'
import NcDialog from '@nextcloud/vue/components/NcDialog'
import NcNoteCard from '@nextcloud/vue/components/NcNoteCard'
import NcTextField from '@nextcloud/vue/components/NcTextField'
import { computed, ref, watch } from 'vue'
import { api, errorMessage } from '../api'

const props = defineProps<{
	/** Group of the function */
	group: Group
	/** Function to edit, null to create a new one in the group */
	orgFunction: OrgFunction | null
}>()

const emit = defineEmits<{
	saved: [orgFunction: OrgFunction]
}>()

const open = defineModel<boolean>('open', { required: true })

const name = ref('')
const yearlyGift = ref(false)
const saving = ref(false)

const valid = computed(() => name.value.trim() !== '')
// Switching off deletes the gifts of the current and later years (see EventService)
const switchingOff = computed(() => props.orgFunction?.yearlyGift === true && !yearlyGift.value)

watch(open, (isOpen) => {
	if (isOpen) {
		name.value = props.orgFunction?.name ?? ''
		yearlyGift.value = props.orgFunction?.yearlyGift ?? false
	}
}, { immediate: true })

/**
 * Creates or updates the function.
 */
async function save() {
	if (!valid.value) {
		return
	}
	saving.value = true
	const input: FunctionInput = { name: name.value.trim(), yearlyGift: yearlyGift.value }
	try {
		const saved = props.orgFunction === null
			? await api.createFunction(props.group.id, input)
			: await api.updateFunction(props.orgFunction.id, input)
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
		:name="orgFunction === null ? t('laudatio', 'New function in {group}', { group: group.name }) : t('laudatio', 'Edit function')"
		size="small">
		<form class="form" @submit.prevent="save">
			<NcTextField v-model="name" :label="t('laudatio', 'Name of the function')" required />
			<NcCheckboxRadioSwitch v-model="yearlyGift" type="switch">
				{{ t('laudatio', 'One gift per year') }}
			</NcCheckboxRadioSwitch>
			<p class="form__hint">
				{{ t('laudatio', 'The responsible person is reminded to present one gift per calendar year to everybody holding this function.') }}
			</p>
			<NcNoteCard
				v-if="switchingOff"
				type="warning"
				:text="t('laudatio', 'Saving removes this year\'s and future yearly gifts of everybody holding this function, including gifts already marked as presented. Gifts of past years are kept.')" />
			<button type="submit" hidden />
		</form>
		<template #actions>
			<NcButton @click="open = false">
				{{ t('laudatio', 'Cancel') }}
			</NcButton>
			<NcButton variant="primary" :disabled="!valid || saving" @click="save">
				{{ t('laudatio', 'Save') }}
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

.form__hint {
	color: var(--color-text-maxcontrast);
}
</style>
