<!--
  - SPDX-FileCopyrightText: 2026 Adrian Dymorz <dev@adrian.dymorz.ch>
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<script setup lang="ts">
import type { User } from '../types'

import { showError } from '@nextcloud/dialogs'
import NcSelectUsers from '@nextcloud/vue/components/NcSelectUsers'
import { computed, ref } from 'vue'
import { errorMessage, searchUsers } from '../api'

interface UserOption {
	id: string
	displayName: string
	user: string
}

const props = defineProps<{
	id: string
	inputLabel: string
	multiple?: boolean
}>()

const model = defineModel<User[]>({ required: true })

const options = ref<UserOption[]>([])
const loading = ref(false)

const toOption = (user: User): UserOption => ({ id: user.id, displayName: user.displayName, user: user.id })

const selected = computed({
	get: () => props.multiple ? model.value.map(toOption) : (model.value[0] ? toOption(model.value[0]) : undefined),
	set: (value: UserOption | UserOption[] | undefined) => {
		const list = value === undefined ? [] : (Array.isArray(value) ? value : [value])
		model.value = list.map((option) => ({ id: option.id, displayName: option.displayName }))
	},
})

/**
 * @param query Search term
 */
async function search(query: string) {
	loading.value = true
	try {
		options.value = (await searchUsers(query)).map(toOption)
	} catch (error) {
		showError(errorMessage(error))
	} finally {
		loading.value = false
	}
}
</script>

<template>
	<NcSelectUsers
		:id="id"
		v-model="selected"
		:options="options"
		:input-label="inputLabel"
		:multiple="multiple"
		:loading="loading"
		@search="search" />
</template>
