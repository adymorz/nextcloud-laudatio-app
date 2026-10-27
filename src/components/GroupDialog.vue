<!--
  - SPDX-FileCopyrightText: 2026 Adrian Dymorz <dev@adrian.dymorz.ch>
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<script setup lang="ts">
import type { Group, User } from '../types'

import { showError } from '@nextcloud/dialogs'
import { t } from '@nextcloud/l10n'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcDialog from '@nextcloud/vue/components/NcDialog'
import NcTextField from '@nextcloud/vue/components/NcTextField'
import { computed, ref, watch } from 'vue'
import { api, errorMessage } from '../api'
import UserSelect from './UserSelect.vue'

const props = defineProps<{
	/** Group to edit, null to create a new one */
	group: Group | null
}>()

const emit = defineEmits<{
	saved: [group: Group]
}>()

const open = defineModel<boolean>('open', { required: true })

const name = ref('')
const responsible = ref<User[]>([])
const interested = ref<User[]>([])
const jubileeYears = ref('')
const saving = ref(false)

const valid = computed(() => name.value.trim() !== '' && responsible.value.length === 1)

watch(open, (isOpen) => {
	if (isOpen) {
		name.value = props.group?.name ?? ''
		responsible.value = props.group ? [props.group.responsible] : []
		interested.value = props.group?.interested ?? []
		jubileeYears.value = props.group?.jubileeYears ?? ''
	}
}, { immediate: true })

/**
 * Creates or updates the group.
 */
async function save() {
	const responsibleUser = responsible.value[0]
	if (!valid.value || responsibleUser === undefined) {
		return
	}
	saving.value = true
	const input = {
		name: name.value,
		responsible: responsibleUser.id,
		interested: interested.value.map((user) => user.id),
		jubileeYears: jubileeYears.value.trim() === '' ? null : jubileeYears.value,
	}
	try {
		const saved = props.group === null
			? await api.createGroup(input)
			: await api.updateGroup(props.group.id, input)
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
		:name="group === null ? t('appreciation', 'New group') : t('appreciation', 'Edit group')"
		size="normal">
		<form class="form" @submit.prevent="save">
			<NcTextField v-model="name" :label="t('appreciation', 'Name')" required />
			<UserSelect
				id="appreciation-group-responsible"
				v-model="responsible"
				:input-label="t('appreciation', 'Responsible person')" />
			<UserSelect
				id="appreciation-group-interested"
				v-model="interested"
				:input-label="t('appreciation', 'Interested persons (get the events for information)')"
				multiple />
			<NcTextField
				v-model="jubileeYears"
				:label="t('appreciation', 'Jubilee years')"
				:helper-text="t('appreciation', 'Comma separated, e.g. 5, 10, 25. Leave empty to use the global setting.')" />
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
