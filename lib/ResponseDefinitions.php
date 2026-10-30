<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Adrian Dymorz <dev@adrian.dymorz.ch>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Laudatio;

/**
 * Response types of the OCS API, used by psalm and the OpenAPI extractor.
 * Dates are formatted as YYYY-MM-DD, timestamps are unix seconds.
 *
 * @psalm-type LaudatioUser = array{
 *     id: string,
 *     displayName: string,
 * }
 *
 * @psalm-type LaudatioMe = array{
 *     userId: string,
 *     isManager: bool,
 *     responsibleGroupIds: list<int>,
 *     interestedGroupIds: list<int>,
 *     greetingReminder: bool,
 *     leadTimeDays: int,
 *     emailNotifications: bool,
 *     regulationsUrl: ?string,
 * }
 *
 * @psalm-type LaudatioFunction = array{
 *     id: int,
 *     groupId: int,
 *     name: string,
 *     yearlyGift: bool,
 * }
 *
 * @psalm-type LaudatioGroup = array{
 *     id: int,
 *     name: string,
 *     responsible: LaudatioUser,
 *     interested: list<LaudatioUser>,
 *     jubileeYears: ?string,
 *     effectiveJubileeYears: list<int>,
 *     functions: list<LaudatioFunction>,
 *     role: 'manager'|'responsible'|'interested',
 * }
 *
 * @psalm-type LaudatioSlice = array{
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
 * @psalm-type LaudatioPerson = array{
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
 *     currentFunctions: list<LaudatioSlice>,
 * }
 *
 * @psalm-type LaudatioPersonDetail = array{
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
 *     currentFunctions: list<LaudatioSlice>,
 *     slices: list<LaudatioSlice>,
 * }
 *
 * @psalm-type LaudatioEvent = array{
 *     id: int,
 *     type: 'birthday'|'jubilee'|'gift',
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
 *     greetedBy: ?LaudatioUser,
 *     canGreet: bool,
 * }
 *
 * @psalm-type LaudatioAuditEntry = array{
 *     id: int,
 *     actor: ?LaudatioUser,
 *     action: string,
 *     entityType: string,
 *     entityId: int,
 *     entityLabel: ?string,
 *     changedFields: list<string>,
 *     createdAt: int,
 * }
 *
 * @psalm-type LaudatioAuditPage = array{
 *     entries: list<LaudatioAuditEntry>,
 *     total: int,
 * }
 *
 * @psalm-type LaudatioError = array{
 *     message: string,
 * }
 *
 * @psalm-suppress UnusedClass only holds type definitions
 */
class ResponseDefinitions {
}
