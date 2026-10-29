<!--
  - SPDX-FileCopyrightText: 2026 Adrian Dymorz <dev@adrian.dymorz.ch>
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<script setup lang="ts">
import { mdiAccountGroup, mdiAccountMultiple, mdiCakeVariant, mdiCog, mdiHistory, mdiScaleBalance } from '@mdi/js'
import { t } from '@nextcloud/l10n'
import NcAppContent from '@nextcloud/vue/components/NcAppContent'
import NcAppNavigation from '@nextcloud/vue/components/NcAppNavigation'
import NcAppNavigationItem from '@nextcloud/vue/components/NcAppNavigationItem'
import NcContent from '@nextcloud/vue/components/NcContent'
import NcIconSvgWrapper from '@nextcloud/vue/components/NcIconSvgWrapper'
import { ref } from 'vue'
import SettingsDialog from './components/SettingsDialog.vue'
import { me } from './store'

const settingsOpen = ref(false)
</script>

<template>
	<NcContent app-name="appreciation">
		<NcAppNavigation :aria-label="t('appreciation', 'Appreciation')">
			<template #list>
				<NcAppNavigationItem :name="t('appreciation', 'Upcoming')" :to="{ name: 'upcoming' }">
					<template #icon>
						<NcIconSvgWrapper :path="mdiCakeVariant" />
					</template>
				</NcAppNavigationItem>
				<NcAppNavigationItem :name="t('appreciation', 'Persons')" :to="{ name: 'persons' }">
					<template #icon>
						<NcIconSvgWrapper :path="mdiAccountMultiple" />
					</template>
				</NcAppNavigationItem>
				<NcAppNavigationItem :name="t('appreciation', 'Groups & functions')" :to="{ name: 'groups' }">
					<template #icon>
						<NcIconSvgWrapper :path="mdiAccountGroup" />
					</template>
				</NcAppNavigationItem>
				<NcAppNavigationItem v-if="me.isManager" :name="t('appreciation', 'Audit log')" :to="{ name: 'audit' }">
					<template #icon>
						<NcIconSvgWrapper :path="mdiHistory" />
					</template>
				</NcAppNavigationItem>
			</template>
			<template #footer>
				<NcAppNavigationItem v-if="me.regulationsUrl" :name="t('appreciation', 'Gift regulations')" :href="me.regulationsUrl">
					<template #icon>
						<NcIconSvgWrapper :path="mdiScaleBalance" />
					</template>
				</NcAppNavigationItem>
				<NcAppNavigationItem :name="t('appreciation', 'Settings')" @click="settingsOpen = true">
					<template #icon>
						<NcIconSvgWrapper :path="mdiCog" />
					</template>
				</NcAppNavigationItem>
			</template>
		</NcAppNavigation>
		<NcAppContent>
			<RouterView />
		</NcAppContent>
		<SettingsDialog v-model:open="settingsOpen" />
	</NcContent>
</template>
