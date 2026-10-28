<!--
  - SPDX-FileCopyrightText: 2026 Adrian Dymorz <dev@adrian.dymorz.ch>
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

# Appreciation

Manage the people engaged in your organization (members, employees, elected officials, volunteers) and never miss their birthdays and work anniversaries (jubilees).

Requires Nextcloud 35. Available in English and German.

## Features

- **Persons** with date of birth and notes, and their **functions** over time (e.g. "Treasurer" from 2021 to 2024) in **groups** (e.g. "Board").
- **Birthdays and jubilees** are generated automatically for the next 400 days, for every person who currently holds a function.
- **Calendar**: everybody involved in a group gets a read-only calendar "Birthdays & anniversaries" in the Calendar app and on CalDAV devices, with a reminder before each event.
- **Reminders**: a Nextcloud notification when an event comes within the lead time (default 14 days), optionally also by email.
- **Upcoming** view with a "greeted" checklist for the responsible persons.
- **Audit log** of who created, changed or deleted master data.

### Roles

| Role | Who | Can |
| --- | --- | --- |
| Manager | Nextcloud admins and members of the manager group (admin settings) | See and edit everything, see the audit log |
| Responsible person | Exactly one Nextcloud user per group | See the current members of the group including date of birth and notes, get their events, mark events as greeted |
| Interested person | Any number of Nextcloud users per group | See the current members of the group (name and functions) and get their events "(for information)" |
| Everybody else | | No access; the app is not shown in the navigation |

Access is checked on the server for every request. Persons themselves need no Nextcloud account.

### Birthdays and jubilees

- A **birthday** is generated when the person holds an active function on that day. It goes to all groups of those functions.
- A **jubilee** is counted per function from the start of the continuous service period: time slices that follow each other without a gap (e.g. a re-election) count as one period. It is generated in the jubilee years (global list, can be overridden per group) while the period is open, or if it ended on or after the jubilee date.
- Birthdays and period starts on February 29 fall on February 28 in other years.
- Titles follow the language of each user, e.g. "5th Anniversary for Anna Muster as Treasurer" or "40. Geburtstag von Anna Muster (zur Information)".

### Settings

- **Admin** (Administration settings → Groupware → Appreciation): manager group, jubilee years, reminder lead time, which events are generated, the "greeted" checklist, and whether reminder emails are on by default.
- **Personal** (Settings at the bottom of the app navigation): reminder emails on or off.

## Development

The development environment is based on [DDEV](https://ddev.com). Docker and DDEV (v1.25 or newer) are the only requirements on your machine; PHP, Composer and Node.js run inside the DDEV containers.

### Getting started

```sh
git clone https://gitlab.home.dymorz.net/nextcloud-apps/nextcloud-appreciation-app.git
cd nextcloud-appreciation-app
ddev start             # the first start downloads and installs Nextcloud 35
ddev composer install  # PHP dev tools and the OCP API for IDE autocompletion
ddev npm ci
ddev npm run build     # or keep `ddev npm run watch` running while you work
ddev launch
```

Log in as `admin` / `admin`. The users `alice` / `alice` and `bob` / `bob` are created as well.

To get some data to work with, run `ddev occ appreciation:demo-data`. It creates two groups, where alice is responsible for "Demo: Board" and interested in "Demo: Volunteers" and bob the other way round, with persons whose birthdays and jubilees fall in the next weeks. Install the Calendar app (`ddev occ app:install calendar`) to see their calendars.

### How it works

- Nextcloud (release tarball, version set by `NEXTCLOUD_VERSION` in [.ddev/config.yaml](.ddev/config.yaml)) lives in a Docker volume at `/var/www/nextcloud` in the web container. Your working copy is at `/var/www/html` and is symlinked to `/var/www/nextcloud/apps-extra/appreciation`, so changes are live immediately.
- [.ddev/nextcloud/setup.sh](.ddev/nextcloud/setup.sh) runs on every `ddev start`. It downloads and installs Nextcloud on the first run, and afterwards only re-links the app, applies pending upgrades and enables the app.
- Development settings (debug mode, trusted domains, app paths, mail) are in [.ddev/nextcloud/ddev.config.php](.ddev/nextcloud/ddev.config.php).
- Background jobs run every 5 minutes (`nextcloud-cron` daemon in the web container).
- All outgoing mail is caught by Mailpit.

### Commands

| Command | Purpose |
| --- | --- |
| `ddev npm run watch` | Rebuild the frontend on every change |
| `ddev npm run lint` / `ddev npm run stylelint` | Lint JavaScript/TypeScript/Vue and styles |
| `ddev npm run typecheck` | Type check the frontend |
| `ddev composer lint` | Check PHP syntax |
| `ddev composer cs:check` / `ddev composer cs:fix` | Check / fix the PHP coding style |
| `ddev composer psalm` | Static analysis |
| `ddev composer rector` | Automated refactoring (then runs `cs:fix`) |
| `ddev composer test:unit` | PHPUnit tests: unit tests and integration tests against the instance database (they only remove the rows they created) |
| `ddev composer openapi` | Regenerate [openapi.json](openapi.json) after API changes, then `ddev npm run typescript:generate` for the frontend types |
| `ddev npm run l10n` | Check translations and compile `l10n/*.js`, see below |
| `ddev occ appreciation:sync` | Generate events and send due reminders now (otherwise hourly) |
| `ddev occ appreciation:demo-data` | Create demo data |
| `ddev occ <command>` | Run Nextcloud's `occ`, e.g. `ddev occ app:list` |
| `ddev occ log:watch` | Follow the Nextcloud log |
| `ddev mailpit` | Open Mailpit to see sent mails |
| `ddev xdebug on` / `ddev xdebug off` | Toggle Xdebug |
| `ddev nextcloud-reset` | Reinstall Nextcloud from scratch (database, users and files are lost) |
| `ddev nextcloud-reset --full` | Same, and download the server code again |

### Debugging with Xdebug

Run `ddev xdebug on` and listen on port 9003 in your IDE. For VS Code with the PHP Debug extension, add this to `.vscode/launch.json`:

```json
{
	"version": "0.2.0",
	"configurations": [
		{
			"name": "Listen for Xdebug (DDEV)",
			"type": "php",
			"request": "launch",
			"port": 9003,
			"pathMappings": {
				"/var/www/html": "${workspaceFolder}"
			}
		}
	]
}
```

Breakpoints work in the app's code. The Nextcloud server code is only available inside the container (`ddev ssh`, `/var/www/nextcloud`); the public API is available in `vendor/nextcloud/ocp` for reference.

### Updating Nextcloud

Change `NEXTCLOUD_VERSION` in [.ddev/config.yaml](.ddev/config.yaml) and run `ddev restart`. The server code is replaced and `occ upgrade` runs; your data is kept. Downgrades are not supported, use `ddev nextcloud-reset --full` instead.

### Translations

Source strings are English: `$l->t('…')` in PHP and `t('appreciation', '…')` in the frontend. Translations live in [l10n/de.json](l10n/de.json) (German, informal) and [l10n/de_DE.json](l10n/de_DE.json) (German, formal). After adding or changing strings, add them to both files and run `ddev npm run l10n`: it lists missing and unused strings and writes the `l10n/*.js` files the frontend loads. CI fails if a translation is missing.

### Architecture

- `lib/Service`: domain logic. `EventCalculator` and `PeriodCalculator` compute the events, `EventService` keeps the `appreciation_events` table in sync, `AccessService` holds all permission rules, `Presenter` applies them to API responses.
- `lib/Controller`: OCS API (`/ocs/v2.php/apps/appreciation/api/v1/…`, documented in [openapi.json](openapi.json)) and the page.
- `lib/Calendar`: the calendar provider (public `ICalendarProvider` API).
- `lib/Notification`, `lib/BackgroundJob`, `lib/Command`, `lib/Settings`: notifier, hourly job, occ commands, admin settings form.
- `src`: Vue 3 frontend with `@nextcloud/vue`.

## Known issues

- Nextcloud 35.0.2 rejects saving *personal* declarative settings for users that are neither admin nor subadmin (the settings app's `DeclarativeSettingsController::setValue` lacks `#[NoSubAdminRequired]`). The personal setting of this app is therefore in the app itself.

## License

[AGPL-3.0-or-later](LICENSE)
