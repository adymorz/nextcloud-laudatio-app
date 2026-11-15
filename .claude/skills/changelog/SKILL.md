---
name: changelog
description: Add or update the CHANGELOG.md entry for a change to the Laudatio app. Use for every change to the app (feature, fix, UI or behaviour change, translation, settings, occ command, export format, supported versions) as part of making the change, before it is committed, so each branch brings its own entry.
---

<!--
  - SPDX-FileCopyrightText: 2026 Adrian Dymorz <dev@adrian.dymorz.ch>
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

# Changelog entry

[CHANGELOG.md](../../../CHANGELOG.md) follows [Keep a Changelog](https://keepachangelog.com/en/1.1.0/). Its sections become the release notes in the Nextcloud App Store and on the GitHub and GitLab releases ([scripts/release-notes.sh](../../../scripts/release-notes.sh)), so they are written for the people who use and run the app.

## Where

- Add the entry under `## [Unreleased]`, directly below the introduction and above the latest release. If that heading is missing, create it there. Keep the brackets: [RELEASING.md](../../../RELEASING.md) renames it to `## X.Y.Z – YYYY-MM-DD` at release time, which is not part of this skill.
- Group entries under `### Added`, `### Changed`, `### Deprecated`, `### Removed`, `### Fixed` and `### Security`, in this order, and create only the subsections that are needed.
- Never change a released section (`## X.Y.Z – …`); it has been published.

## What

- Every change that users, admins or people updating the app notice gets an entry: features, changes to the UI or behaviour, bug fixes, translations and new languages, settings, occ commands, the export format, migrations, supported Nextcloud and PHP versions, security fixes.
- Changes that never reach an installed app get none: CI, release scripts, the DDEV environment, tests, refactoring without a visible effect, developer documentation.
- If the change builds on something that is still under `[Unreleased]`, update that entry instead of adding one. A bug that was never released is not a `Fixed` entry.

## How

- One line per change, starting with `- `, without a trailing period, in English.
- Describe the effect from the user's point of view and say where in the app it shows (data sheet, function history, term dialog, admin settings, …). Use the English UI terms (person, group, function, term, responsible user, interested user, manager). Leave out file, class and API names.
- Match the existing entries in tone and length; one sentence, a semicolon at most.

## Commit

- Put the entry in the same commit as the change. If the change is already pushed, add a follow-up commit; don't rewrite pushed history for it.
- On a merge conflict in CHANGELOG.md, keep the entries of both sides.

## Example

```markdown
## [Unreleased]

### Changed

- The function history on the data sheet shows each date next to its source, in the columns "Function / Group", "Start date / Source" and "End date / Source"
```
