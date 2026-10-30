<!--
  - SPDX-FileCopyrightText: 2026 Adrian Dymorz <dev@adrian.dymorz.ch>
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<script setup lang="ts">
import { showError } from '@nextcloud/dialogs'
import { n, t } from '@nextcloud/l10n'
import NcAppSettingsDialog from '@nextcloud/vue/components/NcAppSettingsDialog'
import NcAppSettingsSection from '@nextcloud/vue/components/NcAppSettingsSection'
import NcCheckboxRadioSwitch from '@nextcloud/vue/components/NcCheckboxRadioSwitch'
import NcNoteCard from '@nextcloud/vue/components/NcNoteCard'
import { ref } from 'vue'
import { api, errorMessage } from '../api'
import { me } from '../store'

const open = defineModel<boolean>('open', { required: true })
const saving = ref(false)

/**
 * @param enabled Whether reminders are also sent by email
 */
async function setEmail(enabled: boolean) {
	saving.value = true
	try {
		Object.assign(me, await api.saveSettings(enabled))
	} catch (error) {
		showError(errorMessage(error))
	} finally {
		saving.value = false
	}
}
</script>

<template>
	<NcAppSettingsDialog v-model:open="open" :name="t('laudatio', 'Laudatio settings')">
		<NcAppSettingsSection id="laudatio-reminders" :name="t('laudatio', 'Reminders')">
			<p class="hint">
				<template v-if="me.leadTimeDays === 0">
					{{ t('laudatio', 'You get a notification on the day of a birthday, an anniversary or the due date of a yearly gift in your groups.') }}
				</template>
				<template v-else>
					{{ n('laudatio', 'You get a notification %n day before a birthday, an anniversary or the due date of a yearly gift in your groups.', 'You get a notification %n days before a birthday, an anniversary or the due date of a yearly gift in your groups.', me.leadTimeDays) }}
				</template>
			</p>
			<NcCheckboxRadioSwitch
				:model-value="me.emailNotifications"
				:loading="saving"
				type="switch"
				@update:model-value="setEmail">
				{{ t('laudatio', 'Also send reminders by email') }}
			</NcCheckboxRadioSwitch>
			<NcNoteCard v-if="!me.hasEmail" type="warning">
				{{ t('laudatio', 'Your account has no email address, so no emails can be sent to you.') }}
			</NcNoteCard>
		</NcAppSettingsSection>
	</NcAppSettingsDialog>
</template>

<style scoped>
.hint {
	color: var(--color-text-maxcontrast);
	margin-block-end: calc(var(--default-grid-baseline) * 2);
}
</style>
