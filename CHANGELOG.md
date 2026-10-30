<!--
  - SPDX-FileCopyrightText: 2026 Adrian Dymorz <dev@adrian.dymorz.ch>
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Added

- Persons, groups with responsible and interested users, functions and terms
- Address, mobile and additional phone number of persons, visible like date of birth and notes
- Printable data sheet per person to check and complete their data, without notes, with an optional note of the organization (admin setting)
- Access control for managers, responsible and interested users; interested users get birthdays without the age
- Birthday and anniversary events with continuous service periods and per-group anniversary years
- Read-only calendar per user in the Calendar app and via CalDAV
- Reminder notifications and optional emails that say why you receive them and how to turn them off; disabled accounts get no reminders
- Upcoming view with "congratulated" checklist
- Yearly gift per function: one open item per holder and calendar year, with reminder and "presented" checklist
- Audit log of changes to persons, groups, functions and terms
- Admin settings and personal email setting
- Link to the gift regulations (external page or internal link of a Nextcloud file) in the app navigation
- Managers are notified when the responsible user of a group is deleted, so they can assign a new one
- `occ laudatio:purge` deletes all data of the app; `occ laudatio:demo-data` only runs on an empty app
- English and German (informal and formal)
- Support for Nextcloud 34 and 35, PHP 8.2 to 8.5
