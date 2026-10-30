/**
 * SPDX-FileCopyrightText: 2026 Adrian Dymorz <dev@adrian.dymorz.ch>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { generateUrl } from '@nextcloud/router'
import { createRouter, createWebHistory } from 'vue-router'
import { me } from './store'
import AuditView from './views/AuditView.vue'
import GroupsView from './views/GroupsView.vue'
import ImportExportView from './views/ImportExportView.vue'
import PersonDetailView from './views/PersonDetailView.vue'
import PersonSheetView from './views/PersonSheetView.vue'
import PersonsView from './views/PersonsView.vue'
import UpcomingView from './views/UpcomingView.vue'

export const router = createRouter({
	history: createWebHistory(generateUrl('/apps/laudatio')),
	linkActiveClass: 'active',
	routes: [
		{ path: '/', redirect: { name: 'upcoming' } },
		{ path: '/upcoming', name: 'upcoming', component: UpcomingView },
		{ path: '/persons', name: 'persons', component: PersonsView },
		{ path: '/persons/:id(\\d+)', name: 'person', component: PersonDetailView, props: (route) => ({ id: Number(route.params.id) }) },
		{ path: '/persons/:id(\\d+)/sheet', name: 'person-sheet', component: PersonSheetView, props: (route) => ({ id: Number(route.params.id) }) },
		{ path: '/groups', name: 'groups', component: GroupsView },
		{ path: '/audit', name: 'audit', component: AuditView, beforeEnter: () => me.isManager || { name: 'upcoming' } },
		{ path: '/import-export', name: 'import-export', component: ImportExportView, beforeEnter: () => me.isManager || { name: 'upcoming' } },
		{ path: '/:pathMatch(.*)*', redirect: { name: 'upcoming' } },
	],
})
