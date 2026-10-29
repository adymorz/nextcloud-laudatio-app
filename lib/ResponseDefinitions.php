<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Adrian Dymorz <dev@adrian.dymorz.ch>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Appreciation;

/**
 * Response types of the OCS API, used by psalm and the OpenAPI extractor.
 * Dates are formatted as YYYY-MM-DD, timestamps are unix seconds.
 *
 * @psalm-type AppreciationUser = array{
 *     id: string,
 *     displayName: string,
 * }
 *
 * @psalm-type AppreciationMe = array{
 *     userId: string,
 *     isManager: bool,
 *     responsibleGroupIds: list<int>,
 *     interestedGroupIds: list<int>,
 *     greetingReminder: bool,
 *     leadTimeDays: int,
 *     emailNotifications: bool,
 * }
 *
 * @psalm-type AppreciationFunction = array{
 *     id: int,
 *     groupId: int,
 *     name: string,
 * }
 *
 * @psalm-type AppreciationGroup = array{
 *     id: int,
 *     name: string,
 *     responsible: AppreciationUser,
 *     interested: list<AppreciationUser>,
 *     jubileeYears: ?string,
 *     effectiveJubileeYears: list<int>,
 *     functions: list<AppreciationFunction>,
 *     role: 'manager'|'responsible'|'interested',
 * }
 *
 * @psalm-type AppreciationSlice = array{
 *     id: int,
 *     personId: int,
 *     functionId: int,
 *     functionName: string,
 *     groupId: int,
 *     groupName: string,
 *     startDate: string,
 *     endDate: ?string,
 * }
 *
 * @psalm-type AppreciationPerson = array{
 *     id: int,
 *     firstName: string,
 *     lastName: string,
 *     displayName: string,
 *     dateOfBirth: ?string,
 *     notes: ?string,
 *     street: ?string,
 *     streetNumber: ?string,
 *     postalCode: ?string,
 *     city: ?string,
 *     country: ?string,
 *     mobilePhone: ?string,
 *     otherPhone: ?string,
 *     sensitiveVisible: bool,
 *     currentFunctions: list<AppreciationSlice>,
 * }
 *
 * @psalm-type AppreciationPersonDetail = array{
 *     id: int,
 *     firstName: string,
 *     lastName: string,
 *     displayName: string,
 *     dateOfBirth: ?string,
 *     notes: ?string,
 *     street: ?string,
 *     streetNumber: ?string,
 *     postalCode: ?string,
 *     city: ?string,
 *     country: ?string,
 *     mobilePhone: ?string,
 *     otherPhone: ?string,
 *     sensitiveVisible: bool,
 *     currentFunctions: list<AppreciationSlice>,
 *     slices: list<AppreciationSlice>,
 * }
 *
 * @psalm-type AppreciationEvent = array{
 *     id: int,
 *     type: 'birthday'|'jubilee',
 *     date: string,
 *     ordinal: int,
 *     title: string,
 *     role: 'responsible'|'interested'|null,
 *     personId: int,
 *     personName: string,
 *     functionId: ?int,
 *     functionName: ?string,
 *     greeted: bool,
 *     greetedAt: ?int,
 *     greetedBy: ?AppreciationUser,
 *     canGreet: bool,
 * }
 *
 * @psalm-type AppreciationAuditEntry = array{
 *     id: int,
 *     actor: ?AppreciationUser,
 *     action: string,
 *     entityType: string,
 *     entityId: int,
 *     entityLabel: ?string,
 *     changedFields: list<string>,
 *     createdAt: int,
 * }
 *
 * @psalm-type AppreciationAuditPage = array{
 *     entries: list<AppreciationAuditEntry>,
 *     total: int,
 * }
 *
 * @psalm-type AppreciationError = array{
 *     message: string,
 * }
 *
 * @psalm-suppress UnusedClass only holds type definitions
 */
class ResponseDefinitions {
}
