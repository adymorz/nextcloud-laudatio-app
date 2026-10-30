/**
 * SPDX-FileCopyrightText: 2026 Adrian Dymorz <dev@adrian.dymorz.ch>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import type {
	LaudatioEvent,
	AuditPage,
	ExportFile,
	FunctionInput,
	Group,
	GroupInput,
	ImportResult,
	Me,
	OrgFunction,
	Person,
	PersonDetail,
	PersonInput,
	Slice,
	SliceInput,
	User,
} from './types'

import axios, { isAxiosError } from '@nextcloud/axios'
import { t } from '@nextcloud/l10n'
import { generateOcsUrl } from '@nextcloud/router'

interface OcsResponse<T> {
	ocs: {
		meta: { message?: string }
		data: T
	}
}

const headers = { 'OCS-APIRequest': 'true', Accept: 'application/json' }

function url(path: string): string {
	return generateOcsUrl('/apps/laudatio/api/v1' + path)
}

async function get<T>(path: string, params?: Record<string, unknown>): Promise<T> {
	const response = await axios.get<OcsResponse<T>>(url(path), { headers, params })
	return response.data.ocs.data
}

async function post<T>(path: string, body: unknown): Promise<T> {
	const response = await axios.post<OcsResponse<T>>(url(path), body, { headers })
	return response.data.ocs.data
}

async function put<T>(path: string, body: unknown): Promise<T> {
	const response = await axios.put<OcsResponse<T>>(url(path), body, { headers })
	return response.data.ocs.data
}

async function del(path: string): Promise<void> {
	await axios.delete(url(path), { headers })
}

export const api = {
	saveSettings: (emailNotifications: boolean) => put<Me>('/me/settings', { emailNotifications }),

	persons: () => get<Person[]>('/persons'),
	person: (id: number) => get<PersonDetail>(`/persons/${id}`),
	createPerson: (input: PersonInput) => post<PersonDetail>('/persons', input),
	updatePerson: (id: number, input: PersonInput) => put<PersonDetail>(`/persons/${id}`, input),
	deletePerson: (id: number) => del(`/persons/${id}`),

	createSlice: (personId: number, input: SliceInput) => post<Slice>(`/persons/${personId}/slices`, input),
	updateSlice: (id: number, input: SliceInput) => put<Slice>(`/slices/${id}`, input),
	deleteSlice: (id: number) => del(`/slices/${id}`),

	groups: () => get<Group[]>('/groups'),
	createGroup: (input: GroupInput) => post<Group>('/groups', input),
	updateGroup: (id: number, input: GroupInput) => put<Group>(`/groups/${id}`, input),
	deleteGroup: (id: number) => del(`/groups/${id}`),

	createFunction: (groupId: number, input: FunctionInput) => post<OrgFunction>(`/groups/${groupId}/functions`, input),
	updateFunction: (id: number, input: FunctionInput) => put<OrgFunction>(`/functions/${id}`, input),
	deleteFunction: (id: number) => del(`/functions/${id}`),

	events: (days: number) => get<LaudatioEvent[]>('/events', { days }),
	setGreeted: (id: number, greeted: boolean) => put<LaudatioEvent>(`/events/${id}/greeted`, { greeted }),

	audit: (limit: number, offset: number) => get<AuditPage>('/audit', { limit, offset }),

	exportData: () => get<ExportFile>('/export'),
	importData: (data: unknown) => post<ImportResult>('/import', { data }),
}

/**
 * Searches Nextcloud users for user pickers.
 *
 * @param search Search term
 */
export async function searchUsers(search: string): Promise<User[]> {
	const response = await axios.get<OcsResponse<{ id: string, label: string }[]>>(generateOcsUrl('/core/autocomplete/get'), {
		headers,
		params: { search, itemType: 'laudatio', itemId: 'group', shareTypes: [0], limit: 20 },
	})
	return response.data.ocs.data.map((entry) => ({ id: entry.id, displayName: entry.label }))
}

/**
 * Whether the server answered a request with 404, e.g. for a person the user
 * may not see.
 *
 * @param error Error thrown by an API call
 */
export function isNotFound(error: unknown): boolean {
	return isAxiosError(error) && error.response?.status === 404
}

/**
 * The message to show for a failed request: the (translated) message of the
 * server if there is one.
 *
 * @param error Error thrown by an API call
 */
export function errorMessage(error: unknown): string {
	if (isAxiosError<OcsResponse<{ message?: string }>>(error)) {
		const ocs = error.response?.data?.ocs
		const message = ocs?.data?.message || ocs?.meta?.message
		if (message) {
			return message
		}
	}
	return t('laudatio', 'Something went wrong. Please try again.')
}
