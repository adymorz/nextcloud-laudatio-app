<!--
  - SPDX-FileCopyrightText: 2026 Adrian Dymorz <dev@adrian.dymorz.ch>
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<script setup lang="ts">
import type { Slice } from '../types'

import { t } from '@nextcloud/l10n'
import { computed } from 'vue'

const props = defineProps<{
	slice: Slice
}>()

// Null for viewers who may not see them, so nothing is shown then
const details = computed(() => [
	{ label: t('laudatio', 'Source of start date'), value: props.slice.startDateSource },
	{ label: t('laudatio', 'Source of end date'), value: props.slice.endDateSource },
	{ label: t('laudatio', 'Notes'), value: props.slice.notes },
].filter((detail) => detail.value))
</script>

<template>
	<dl v-if="details.length > 0" class="details">
		<template v-for="detail in details" :key="detail.label">
			<dt>{{ detail.label }}</dt>
			<dd>{{ detail.value }}</dd>
		</template>
	</dl>
</template>

<style scoped>
.details {
	display: grid;
	grid-template-columns: max-content 1fr;
	gap: calc(var(--default-grid-baseline) / 2) calc(var(--default-grid-baseline) * 4);
	margin-block-start: var(--default-grid-baseline);
	padding: 0;
}

/* Nextcloud gives definition lists a fixed width, padding and right alignment */
.details dt,
.details dd {
	width: auto;
	padding: 0;
}

.details dt {
	text-align: start;
	color: var(--color-text-maxcontrast);
}

.details dd {
	white-space: pre-wrap;
}
</style>
