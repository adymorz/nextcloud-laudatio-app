<!--
  - SPDX-FileCopyrightText: 2026 Adrian Dymorz <dev@adrian.dymorz.ch>
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<script setup lang="ts">
import { mdiAlertCircleOutline } from '@mdi/js'
import { t } from '@nextcloud/l10n'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcEmptyContent from '@nextcloud/vue/components/NcEmptyContent'
import NcIconSvgWrapper from '@nextcloud/vue/components/NcIconSvgWrapper'

defineProps<{
	/** Message of the server, or a generic one */
	message: string
}>()

const emit = defineEmits<{
	retry: []
}>()
</script>

<template>
	<NcEmptyContent :name="t('laudatio', 'Could not load the data')" :description="message">
		<template #icon>
			<NcIconSvgWrapper :path="mdiAlertCircleOutline" />
		</template>
		<template #action>
			<NcButton @click="emit('retry')">
				{{ t('laudatio', 'Retry') }}
			</NcButton>
		</template>
	</NcEmptyContent>
</template>
