<!--
  - SPDX-FileCopyrightText: 2026 Adrian Dymorz <dev@adrian.dymorz.ch>
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<script setup lang="ts">
import { mdiDownload } from '@mdi/js'
import { showError, showSuccess } from '@nextcloud/dialogs'
import { t } from '@nextcloud/l10n'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcFilePicker from '@nextcloud/vue/components/NcFilePicker'
import NcIconSvgWrapper from '@nextcloud/vue/components/NcIconSvgWrapper'
import NcNoteCard from '@nextcloud/vue/components/NcNoteCard'
import { ref } from 'vue'
import { api, errorMessage } from '../api'
import { toIsoDate } from '../dates'

const exporting = ref(false)
const importing = ref(false)
const imported = ref(false)
const picker = ref<InstanceType<typeof NcFilePicker> | null>(null)

/**
 * Downloads the export as a file.
 */
async function download() {
	exporting.value = true
	try {
		const data = await api.exportData()
		const url = URL.createObjectURL(new Blob([JSON.stringify(data, null, '\t')], { type: 'application/json' }))
		const link = document.createElement('a')
		link.href = url
		link.download = `laudatio-export-${toIsoDate(new Date())}.json`
		link.click()
		URL.revokeObjectURL(url)
	} catch (error) {
		showError(errorMessage(error))
	} finally {
		exporting.value = false
	}
}

/**
 * @param file The picked file
 * @return The decoded file, null if it holds no JSON object
 */
async function readFile(file: File): Promise<object | null> {
	try {
		const data: unknown = JSON.parse(await file.text())
		return typeof data === 'object' ? data : null
	} catch {
		return null
	}
}

/**
 * Imports the picked file.
 *
 * @param files The picked files, only the first one counts
 */
async function importFile(files: File[]) {
	const file = files[0]
	if (file === undefined) {
		return
	}
	importing.value = true
	imported.value = false
	try {
		const data = await readFile(file)
		if (data === null) {
			showError(t('laudatio', 'This is not a Laudatio export file.'))
			return
		}
		const result = await api.importData(data)
		imported.value = true
		showSuccess(t('laudatio', 'Imported groups: {groups}, functions: {functions}, persons: {persons}, terms: {terms}', result))
	} catch (error) {
		showError(errorMessage(error))
	} finally {
		importing.value = false
		// Allows picking the same file again after correcting it
		picker.value?.reset()
	}
}
</script>

<template>
	<div class="view">
		<h2>{{ t('laudatio', 'Import & export') }}</h2>

		<section class="transfer">
			<h3>{{ t('laudatio', 'Export') }}</h3>
			<p>
				{{ t('laudatio', 'Download all groups, functions, persons and their terms as a file, as a backup or to move them to another Nextcloud.') }}
			</p>
			<p class="muted">
				{{ t('laudatio', 'Not included: responsible and interested users, events with their "congratulated" and "presented" marks, the audit log and the settings.') }}
			</p>
			<NcNoteCard type="warning">
				{{ t('laudatio', 'The file contains personal data, including dates of birth, contact data and notes, in plain text. Keep it safe. Exports are recorded in the audit log.') }}
			</NcNoteCard>
			<NcButton :disabled="exporting" @click="download">
				<template #icon>
					<NcIconSvgWrapper :path="mdiDownload" />
				</template>
				{{ t('laudatio', 'Download export') }}
			</NcButton>
		</section>

		<section class="transfer">
			<h3>{{ t('laudatio', 'Import') }}</h3>
			<p>
				{{ t('laudatio', 'Load an export file into this Nextcloud. This only works while the app contains no groups and no persons. The file is imported completely or not at all.') }}
			</p>
			<p class="muted">
				{{ t('laudatio', 'To replace existing data, an administrator first empties the app with "occ laudatio:purge", which also deletes the audit log and the settings, and enables it again.') }}
			</p>
			<NcFilePicker
				ref="picker"
				:accept="['.json']"
				:label="t('laudatio', 'Choose export file')"
				:loading="importing"
				@pick="importFile" />
			<NcNoteCard v-if="imported" type="success">
				{{ t('laudatio', 'The data was imported.') }}
				<RouterLink :to="{ name: 'groups' }">
					{{ t('laudatio', 'Assign a responsible person to every group: until then, nobody gets reminders.') }}
				</RouterLink>
			</NcNoteCard>
		</section>
	</div>
</template>

<style scoped>
.view {
	padding: calc(var(--default-grid-baseline) * 4);
	padding-inline-start: calc(var(--default-clickable-area) + var(--default-grid-baseline) * 4);
	max-width: 900px;
}

.transfer {
	margin-block-start: calc(var(--default-grid-baseline) * 8);
}

.transfer h3 {
	margin-block: 0 calc(var(--default-grid-baseline) * 2);
}

.transfer p {
	margin-block-end: calc(var(--default-grid-baseline) * 2);
}

.transfer a {
	text-decoration: underline;
}

.muted {
	color: var(--color-text-maxcontrast);
}
</style>
