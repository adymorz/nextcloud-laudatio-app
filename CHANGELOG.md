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

- "Overview" in the app navigation lists the current persons of every group with their functions and the responsible person, to print or save as PDF; it shows no dates of birth or contact data

## 0.5.0 – 2026-11-18

### Added

- Managers can move a function to another group with "Move to another group" on the groups page, e.g. after a reorganization; all its terms, also past ones, then belong to the new group

### Changed

- The responsible person gets no reminder for a birthday or anniversary that is already marked as congratulated, and an earlier reminder disappears once it is marked; interested persons still get it

### Fixed

- Names containing &, ' or " show correctly in dialogs, confirmations and the "congratulated by" and "presented by" lines of the upcoming events instead of as HTML codes
- A user who loses a role in a group and gets it back, e.g. when the responsible person is changed back, is reminded of upcoming events again
- Error messages show names containing < or >, e.g. from an import file, in full instead of cutting them off

## 0.4.1 – 2026-11-16

### Fixed

- The manager group in the admin settings lists the names of the groups instead of "undefined" and saves the selected group

## 0.4.0 – 2026-11-15

### Added

- Optional email address of persons, visible like the phone numbers and printed on the data sheet; export files now include it and can only be imported by this version or newer

### Changed

- The function history on the data sheet shows each date next to its source, in the columns "Function / Group", "Start date / Source" and "End date / Source"

## 0.3.0 – 2026-11-15

### Added

- Persons, groups with responsible and interested users, functions and terms
- Source of the start and end date and notes per term, visible like the notes of a person and printed on the data sheet
- Address, mobile and additional phone number of persons, visible like date of birth and notes
- Printable data sheet per person to check and complete their data, with the function history, without the notes of the person, with an optional note of the organization (admin setting)
- Access control for managers, responsible and interested users; interested users get birthdays without the age
- Birthday and anniversary events with continuous service periods and per-group anniversary years
- Read-only calendar per user in the Calendar app and via CalDAV
- Reminder notifications and optional emails that say why you receive them and how to turn them off; disabled accounts get no reminders
- Upcoming view with "congratulated" checklist
- Yearly gift per function: one open item per holder and calendar year, with reminder and "presented" checklist
- Audit log of changes to persons, groups, functions and terms, and of exports
- Import and export of groups, functions, persons and terms as a versioned file, for backups and the move to another Nextcloud, without references to Nextcloud users
- Admin settings and personal email setting
- Link to the gift regulations (external page or internal link of a Nextcloud file) in the app navigation
- Managers are notified when the responsible user of a group is deleted, so they can assign a new one
- `occ laudatio:purge` deletes all data of the app; `occ laudatio:demo-data` only runs on an empty app
- English and German (informal and formal)
- Support for Nextcloud 34 and 35, PHP 8.2 to 8.5
