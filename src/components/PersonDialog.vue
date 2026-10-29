<!--
  - SPDX-FileCopyrightText: 2026 Adrian Dymorz <dev@adrian.dymorz.ch>
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<script setup lang="ts">
import type { PersonDetail, PersonInput } from '../types'

import { showError } from '@nextcloud/dialogs'
import { t } from '@nextcloud/l10n'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcDateTimePickerNative from '@nextcloud/vue/components/NcDateTimePickerNative'
import NcDialog from '@nextcloud/vue/components/NcDialog'
import NcTextArea from '@nextcloud/vue/components/NcTextArea'
import NcTextField from '@nextcloud/vue/components/NcTextField'
import { computed, reactive, ref, watch } from 'vue'
import { api, errorMessage } from '../api'
import { parseIsoDate, toIsoDate } from '../dates'

const props = defineProps<{
	/** Person to edit, null to create a new one */
	person: PersonDetail | null
}>()

const emit = defineEmits<{
	saved: [person: PersonDetail]
}>()

const open = defineModel<boolean>('open', { required: true })

const firstName = ref('')
const lastName = ref('')
const dateOfBirth = ref<Date | null>(null)
const notes = ref('')
const contact = reactive({
	street: '',
	streetNumber: '',
	postalCode: '',
	city: '',
	country: '',
	mobilePhone: '',
	otherPhone: '',
})
type ContactField = keyof typeof contact
const contactFields = Object.keys(contact) as ContactField[]
const saving = ref(false)

const valid = computed(() => firstName.value.trim() !== '' && lastName.value.trim() !== '' && dateOfBirth.value !== null)

watch(open, (isOpen) => {
	if (isOpen) {
		firstName.value = props.person?.firstName ?? ''
		lastName.value = props.person?.lastName ?? ''
		dateOfBirth.value = props.person?.dateOfBirth ? parseIsoDate(props.person.dateOfBirth) : null
		notes.value = props.person?.notes ?? ''
		for (const field of contactFields) {
			contact[field] = props.person?.[field] ?? ''
		}
	}
}, { immediate: true })

/**
 * @param value Entered text
 */
function optional(value: string): string | null {
	return value.trim() === '' ? null : value
}

/**
 * Creates or updates the person.
 */
async function save() {
	if (!valid.value || dateOfBirth.value === null) {
		return
	}
	saving.value = true
	const input: PersonInput = {
		firstName: firstName.value,
		lastName: lastName.value,
		dateOfBirth: toIsoDate(dateOfBirth.value),
		notes: optional(notes.value),
		street: optional(contact.street),
		streetNumber: optional(contact.streetNumber),
		postalCode: optional(contact.postalCode),
		city: optional(contact.city),
		country: optional(contact.country),
		mobilePhone: optional(contact.mobilePhone),
		otherPhone: optional(contact.otherPhone),
	}
	try {
		const saved = props.person === null
			? await api.createPerson(input)
			: await api.updatePerson(props.person.id, input)
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
		:name="person === null ? t('appreciation', 'New person') : t('appreciation', 'Edit person')"
		size="normal">
		<form class="form" @submit.prevent="save">
			<NcTextField v-model="firstName" :label="t('appreciation', 'First name')" required />
			<NcTextField v-model="lastName" :label="t('appreciation', 'Last name')" required />
			<NcDateTimePickerNative
				id="appreciation-date-of-birth"
				v-model="dateOfBirth"
				type="date"
				:max="new Date()"
				:label="t('appreciation', 'Date of birth')" />
			<div class="form__row form__row--street">
				<NcTextField v-model="contact.street" :label="t('appreciation', 'Street')" autocomplete="off" />
				<NcTextField v-model="contact.streetNumber" :label="t('appreciation', 'House number')" autocomplete="off" />
			</div>
			<div class="form__row form__row--city">
				<NcTextField v-model="contact.postalCode" :label="t('appreciation', 'ZIP code')" autocomplete="off" />
				<NcTextField v-model="contact.city" :label="t('appreciation', 'City')" autocomplete="off" />
			</div>
			<NcTextField v-model="contact.country" :label="t('appreciation', 'Country')" autocomplete="off" />
			<NcTextField
				v-model="contact.mobilePhone"
				type="tel"
				:label="t('appreciation', 'Mobile phone')"
				autocomplete="off" />
			<NcTextField
				v-model="contact.otherPhone"
				type="tel"
				:label="t('appreciation', 'Additional phone number')"
				autocomplete="off" />
			<NcTextArea
				v-model="notes"
				:label="t('appreciation', 'Notes')"
				:helper-text="t('appreciation', 'Date of birth, contact data and notes are only visible to managers and the responsible person of the person\'s groups.')"
				resize="vertical" />
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

.form__row {
	display: grid;
	gap: calc(var(--default-grid-baseline) * 2);
}

.form__row--street {
	grid-template-columns: 1fr 9em;
}

.form__row--city {
	grid-template-columns: 9em 1fr;
}
</style>
