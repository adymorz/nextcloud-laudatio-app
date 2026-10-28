<!--
  - SPDX-FileCopyrightText: 2026 Adrian Dymorz <dev@adrian.dymorz.ch>
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<script setup lang="ts">
import type { AppreciationEvent } from '../types'

import { mdiCakeVariant, mdiTrophyAward } from '@mdi/js'
import { showError } from '@nextcloud/dialogs'
import { n, t } from '@nextcloud/l10n'
import NcCheckboxRadioSwitch from '@nextcloud/vue/components/NcCheckboxRadioSwitch'
import NcEmptyContent from '@nextcloud/vue/components/NcEmptyContent'
import NcIconSvgWrapper from '@nextcloud/vue/components/NcIconSvgWrapper'
import NcLoadingIcon from '@nextcloud/vue/components/NcLoadingIcon'
import NcSelect from '@nextcloud/vue/components/NcSelect'
import { onMounted, ref, watch } from 'vue'
import { api, errorMessage } from '../api'
import { formatDate, relativeDay } from '../dates'
import { me } from '../store'

interface RangeOption {
	label: string
	days: number
}

const ranges: RangeOption[] = [30, 60, 90, 365].map((days) => ({ days, label: n('appreciation', 'Next %n day', 'Next %n days', days) }))
const range = ref<RangeOption>(ranges[1]!)
const events = ref<AppreciationEvent[]>([])
const loading = ref(true)

/**
 * Loads the events of the selected range.
 */
async function load() {
	loading.value = true
	try {
		events.value = await api.events(range.value.days)
	} catch (error) {
		showError(errorMessage(error))
	} finally {
		loading.value = false
	}
}

/**
 * @param event The event
 * @param greeted New state
 */
async function setGreeted(event: AppreciationEvent, greeted: boolean) {
	try {
		const updated = await api.setGreeted(event.id, greeted)
		events.value = events.value.map((e) => e.id === updated.id ? updated : e)
	} catch (error) {
		showError(errorMessage(error))
	}
}

onMounted(load)
watch(range, load)
</script>

<template>
	<div class="view">
		<div class="view__header">
			<h2>{{ t('appreciation', 'Upcoming birthdays and anniversaries') }}</h2>
			<NcSelect
				v-model="range"
				class="view__range"
				:options="ranges"
				:clearable="false"
				:input-label="t('appreciation', 'Period')"
				label="label" />
		</div>

		<NcLoadingIcon v-if="loading" :size="32" />
		<NcEmptyContent
			v-else-if="events.length === 0"
			:name="t('appreciation', 'Nothing coming up')"
			:description="t('appreciation', 'There are no birthdays or anniversaries in your groups in this period.')">
			<template #icon>
				<NcIconSvgWrapper :path="mdiCakeVariant" />
			</template>
		</NcEmptyContent>
		<ul v-else class="events">
			<li
				v-for="event in events"
				:key="event.id"
				class="event"
				:class="{ 'event--info': event.role === 'interested', 'event--greeted': event.greeted }">
				<NcIconSvgWrapper class="event__icon" :path="event.type === 'jubilee' ? mdiTrophyAward : mdiCakeVariant" />
				<div class="event__text">
					<RouterLink class="event__title" :to="{ name: 'person', params: { id: event.personId } }">
						{{ event.title }}
					</RouterLink>
					<span class="event__date">
						{{ formatDate(event.date) }} · {{ relativeDay(event.date) }}
						<template v-if="event.greeted && event.greetedBy">
							· {{ t('appreciation', 'greeted by {name}', { name: event.greetedBy.displayName }) }}
						</template>
					</span>
				</div>
				<NcCheckboxRadioSwitch
					v-if="me.greetingReminder && event.canGreet"
					:model-value="event.greeted"
					@update:model-value="setGreeted(event, $event)">
					{{ t('appreciation', 'Greeted') }}
				</NcCheckboxRadioSwitch>
			</li>
		</ul>
	</div>
</template>

<style scoped>
.view {
	padding: calc(var(--default-grid-baseline) * 4);
	padding-inline-start: calc(var(--default-clickable-area) + var(--default-grid-baseline) * 4);
	max-width: 900px;
}

.view__header {
	display: flex;
	flex-wrap: wrap;
	align-items: flex-end;
	justify-content: space-between;
	gap: calc(var(--default-grid-baseline) * 4);
	margin-block-end: calc(var(--default-grid-baseline) * 4);
}

.view__range {
	min-width: 200px;
}

.events {
	display: flex;
	flex-direction: column;
	gap: calc(var(--default-grid-baseline) * 2);
}

.event {
	display: flex;
	align-items: center;
	gap: calc(var(--default-grid-baseline) * 3);
	padding: calc(var(--default-grid-baseline) * 2) calc(var(--default-grid-baseline) * 3);
	border-radius: var(--border-radius-large);
	background-color: var(--color-background-hover);
}

.event--info {
	background-color: transparent;
	border: 1px solid var(--color-border);
}

.event__icon {
	color: var(--color-primary-element);
}

.event--info .event__icon {
	color: var(--color-text-maxcontrast);
}

.event__text {
	display: flex;
	flex: 1;
	flex-direction: column;
	min-width: 0;
}

.event__title {
	font-weight: bold;
}

.event--greeted .event__title {
	font-weight: normal;
}

.event__date {
	color: var(--color-text-maxcontrast);
}
</style>
