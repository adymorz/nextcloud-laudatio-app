<!--
  - SPDX-FileCopyrightText: 2026 Adrian Dymorz <dev@adrian.dymorz.ch>
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<script setup lang="ts">
import { t } from '@nextcloud/l10n'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcDialog from '@nextcloud/vue/components/NcDialog'
import NcTextField from '@nextcloud/vue/components/NcTextField'
import { ref, watch } from 'vue'

const props = defineProps<{
	title: string
	label: string
	initialValue: string
}>()

const emit = defineEmits<{
	submit: [name: string]
}>()

const open = defineModel<boolean>('open', { required: true })
const name = ref('')

watch(open, (isOpen) => {
	if (isOpen) {
		name.value = props.initialValue
	}
}, { immediate: true })

/**
 * Hands the name to the parent and closes the dialog.
 */
function submit() {
	if (name.value.trim() === '') {
		return
	}
	emit('submit', name.value.trim())
	open.value = false
}
</script>

<template>
	<NcDialog v-model:open="open" :name="title" size="small">
		<form @submit.prevent="submit">
			<NcTextField v-model="name" :label="label" required />
		</form>
		<template #actions>
			<NcButton @click="open = false">
				{{ t('appreciation', 'Cancel') }}
			</NcButton>
			<NcButton variant="primary" :disabled="name.trim() === ''" @click="submit">
				{{ t('appreciation', 'Save') }}
			</NcButton>
		</template>
	</NcDialog>
</template>
