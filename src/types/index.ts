/**
 * SPDX-FileCopyrightText: 2026 Adrian Dymorz <dev@adrian.dymorz.ch>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import type { components } from './openapi'

type Schemas = components['schemas']

export type User = Schemas['User']
export type Me = Schemas['Me']
export type Person = Schemas['Person']
export type PersonDetail = Schemas['PersonDetail']
export type Slice = Schemas['Slice']
export type Group = Schemas['Group']
export type OrgFunction = Schemas['Function']
export type AppreciationEvent = Schemas['Event']
export type AuditEntry = Schemas['AuditEntry']
export type AuditPage = Schemas['AuditPage']

export interface PersonInput {
	firstName: string
	lastName: string
	dateOfBirth: string
	notes: string | null
}

export interface SliceInput {
	functionId: number
	startDate: string
	endDate: string | null
}

export interface GroupInput {
	name: string
	responsible: string
	interested: string[]
	jubileeYears: string | null
}
