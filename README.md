<!--
  - SPDX-FileCopyrightText: 2026 Adrian Dymorz <dev@adrian.dymorz.ch>
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

# Laudatio

Manage the people engaged in your organization (members, employees, elected officials, volunteers) and never miss their birthdays, anniversaries and yearly gifts.

Requires Nextcloud 34 or 35 (PHP 8.2 or newer). Available in English and German. Report bugs and ideas in the [issue tracker](https://github.com/adymorz/nextcloud-laudatio-app/issues), see [Contributing and support](#contributing-and-support).

![Upcoming birthdays and anniversaries](screenshots/upcoming.png)

## Features

- **Persons** with date of birth, address, phone numbers and notes, and their **functions** over time (e.g. "Treasurer" from 2021 to 2024, a *term*) in **groups** (e.g. "Board").
- **Birthdays and anniversaries** are generated automatically for the next 400 days, for every person who currently holds a function.
- **Yearly gifts**: functions can be marked "One gift per year", so the responsible person is reminded to present one gift per calendar year to everybody holding them.
- **Calendar**: everybody involved in a group gets a read-only calendar "Birthdays & anniversaries" in the Calendar app and on CalDAV devices, with a reminder before each event (yearly gifts have no fixed day and are not in the calendar).
- **Reminders**: a Nextcloud notification when an event comes within the lead time (default 14 days), optionally also by email. Every email says why you receive it and how to turn it off.
- **Upcoming** view with a "congratulated" checklist for the responsible persons, and the yearly gifts of the year with a "presented" checklist.
- **Data sheet** per person to print (or save as PDF) and hand over, so the person can check, correct and complete their data. Notes are never printed; an optional note of your organization on who stores the data and why is.
- **Audit log** of who created, changed or deleted persons, groups, functions and terms, and who exported them.
- **Import and export**: managers download all groups, functions, persons and terms as a versioned file, as a backup or to move them to another Nextcloud.

### Roles

| Role | Who | Can |
| --- | --- | --- |
| Manager | Nextcloud admins and members of the manager group (admin settings) | See and edit everything, see the audit log, import and export |
| Responsible person | Exactly one Nextcloud user per group | See the current members of the group including date of birth, contact data and notes, print their data sheets, get their events, mark events as congratulated and yearly gifts as presented |
| Interested person | Any number of Nextcloud users per group | See the current members of the group (name and functions) and get their events "(for information)": birthdays without the age, anniversaries with the years of service |
| Everybody else | | No access; the app is not shown in the navigation |

Access is checked on the server for every request. Persons themselves need no Nextcloud account. When a Nextcloud account is deleted, it is removed from all interested lists; groups it was responsible for are left without a responsible person and the managers are notified to assign a new one. Disabled accounts get no reminders.

### Birthdays and anniversaries

- A **birthday** is generated when the person holds an active function on that day. It goes to all groups of those functions.
- An **anniversary** is counted per function from the start of the continuous service period: terms that follow each other without a gap (e.g. a re-election) count as one period. It is generated in the anniversary years (global list, can be overridden per group) while the period is open, or if it ended on or after the anniversary date.
- Birthdays and period starts on February 29 fall on February 28 in other years.
- Titles follow the language and role of each user, e.g. "5th anniversary of Anna Muster as Treasurer", "40th birthday of Anna Muster" or, for interested persons, "Geburtstag von Anna Muster (zur Information)".

### Yearly gifts

- For a function marked "One gift per year", every person holding it on at least one day of a calendar year gets one **yearly gift** for that year, an open item for the function's group, e.g. "Yearly gift for Anna Muster (Treasurer)". Holding the function twice in the same year still gives one gift.
- It is due at the end of the year, or on the last day in the function if the person leaves it during the year. The reminder comes when the due date is within the lead time, unless the gift is already presented.
- The Upcoming view lists the gifts of every year in the selected period, including overdue ones of the current year. The responsible person and managers mark them as presented, also when the "congratulated" checklist is turned off.
- Gifts are generated for the current and the next calendar year. Switching the function's yearly gift off removes the gifts of the current year, including presented ones; those of past years stay.

### Import and export

Managers find both under **Import & export** in the app navigation.

- The **export** is a JSON file with all groups, functions, persons and terms, including dates of birth, contact data and notes. It holds no references to Nextcloud users, so it fits any Nextcloud. See [Privacy and data protection](#privacy-and-data-protection) before you store or pass on the file.
- **Not exported**: responsible and interested users, events with their "congratulated" and "presented" marks, the audit log and the settings. The events are generated again from the terms.
- The **import** only works while the app contains no groups and no persons. The whole file is checked first, then it is imported completely or not at all. Every created group, function, person and term appears in the audit log.
- **After an import**, assign a responsible person to every group, because nobody gets reminders until then. Check the admin settings too: groups without their own anniversary years use the global list of this Nextcloud. Yearly gifts of the current year appear as open again.

To restore a backup over existing data, an administrator first empties the app. This also deletes the audit log and all settings, including the manager group:

```sh
occ laudatio:purge
occ app:enable laudatio
```

Then the administrator imports the file in the app and sets the admin settings again.

### Settings

- **Admin** (Administration settings → Groupware → Laudatio): manager group, anniversary years, reminder lead time, which events are generated, the "congratulated" checklist, whether reminder emails are on by default, the link to the gift regulations (an external page or the internal link of a file in Nextcloud) shown to all users in the app navigation, and the note printed on every data sheet.
- **Personal** (Settings at the bottom of the app navigation): reminder emails on or off.

## Privacy and data protection

Laudatio stores personal data of people who usually have no Nextcloud account. What you should know as the organization responsible for that data:

- **What is stored**: first and last name, date of birth, address, phone numbers and free-text notes per person; their terms (function, group, start and end date); the generated events with their "congratulated" marks; who was already notified about which event; and the audit log (IDs and field names only).
- **Where**: only in the app's own tables of your Nextcloud database (`laudatio_*`). Nothing is sent to any other server.
- **Who sees what**: see [Roles](#roles). Date of birth, contact data and notes are shown to managers and the responsible persons only, in the app and in its API.
- **Where data leaves the database**: Nextcloud notifications hold only the event ID and render the title when shown. Reminder emails carry the title (name, age or years of service, function) and the date. The CalDAV calendar carries the title and the date to the devices of the responsible and interested users. The printed data sheet carries everything but the notes. Interested users never get the age. An export carries all persons with their dates of birth, contact data and notes as an unencrypted file to the device of the manager who downloads it; storing that file safely is up to you.
- **Logging**: the audit log records who created, changed or deleted what and which fields, never the values, and who exported the data. The same entries go to the Nextcloud audit log (admin_audit app) as critical actions. The Nextcloud log contains no personal data of persons.
- **Deletion**: deleting a person removes their terms, events, notifications and reminders; the audit log keeps the person's ID only. Deleting a function or group requires removing its terms first. Persons stay until a manager deletes them, there is no automatic retention. Export files are outside the app: a deleted person stays in earlier exports until you delete those files.
- **Everything at once**: `occ laudatio:purge` deletes all tables, settings, notifications and the background job of the app, see [Uninstalling](#uninstalling).

## Installation

As an administrator, open **Apps**, search for "Laudatio" (category "Organization") and click **Download and enable**, or run `occ app:install laudatio`.

Then choose the manager group and adjust the anniversary years and reminders in Administration settings → Groupware → Laudatio. Background jobs (cron) must run: the job of the app generates the events and sends the reminders hourly. Install the Calendar app to see the birthdays and anniversaries in a calendar.

### Uninstalling

Disabling the app (`occ app:disable laudatio`) keeps all data. This is also what happens automatically during a server upgrade when the app does not support the new version yet. Nextcloud never deletes the tables of an app, so removing the app alone leaves the personal data in your database. To delete it:

```sh
occ laudatio:purge        # asks for confirmation, --force skips the question
occ app:remove laudatio
```

`occ laudatio:purge` drops the tables of the app and deletes its settings, the personal settings of all users, its notifications and its background job. Afterwards, `occ app:enable laudatio` starts over with an empty app.

## Development

The app is developed on a self-hosted GitLab, [GitHub](https://github.com/adymorz/nextcloud-laudatio-app) is a public mirror. The development environment is based on [DDEV](https://ddev.com). Docker and DDEV (v1.25 or newer) are the only requirements on your machine; PHP, Composer and Node.js run inside the DDEV containers.

### Getting started

```sh
git clone https://github.com/adymorz/nextcloud-laudatio-app.git
cd nextcloud-laudatio-app
ddev start             # the first start downloads and installs Nextcloud 35
ddev composer install  # PHP dev tools and the OCP API for IDE autocompletion
ddev npm ci
ddev npm run build     # or keep `ddev npm run watch` running while you work
ddev launch
```

Log in as `admin` / `admin`. The users `alice` / `alice` and `bob` / `bob` are created as well.

To get some data to work with, run `ddev occ laudatio:demo-data`. It creates two groups, where alice is responsible for "Demo: Board" and interested in "Demo: Volunteers" and bob the other way round, with persons whose birthdays and anniversaries fall in the next weeks. Install the Calendar app (`ddev occ app:install calendar`) to see their calendars.

### How it works

- Nextcloud (release tarball, version set by `NEXTCLOUD_VERSION` in [.ddev/config.yaml](.ddev/config.yaml)) lives in a Docker volume at `/var/www/nextcloud` in the web container. Your working copy is at `/var/www/html` and is symlinked to `/var/www/nextcloud/apps-extra/laudatio`, so changes are live immediately. Never run `occ app:remove laudatio` there, it would follow the symlink and delete the working copy.
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
| `ddev composer test` | PHPUnit, see [Testing](#testing); `test:unit` and `test:integration` run one suite |
| `ddev composer openapi` | Regenerate [openapi.json](openapi.json) after API changes, then `ddev npm run typescript:generate` for the frontend types |
| `ddev npm run l10n` | Check translations and compile `l10n/*.js`, see below |
| `ddev occ laudatio:sync` | Generate events and send due reminders now (otherwise hourly) |
| `ddev occ laudatio:demo-data` | Create demo data on an empty app (`--force` adds it to existing data) |
| `ddev occ laudatio:purge` | Delete all data of the app, then `ddev occ app:enable laudatio` starts over |
| `ddev exec scripts/appstore-package.sh` | Build the unsigned App Store archive in `build/appstore/` (after `ddev npm run build`), see [RELEASING.md](RELEASING.md) |
| `ddev exec node scripts/screenshots.mjs` | Retake the App Store screenshots with headless Chromium, see the preparation in [scripts/screenshots.mjs](scripts/screenshots.mjs) |
| `ddev occ <command>` | Run Nextcloud's `occ`, e.g. `ddev occ app:list` |
| `ddev occ log:watch` | Follow the Nextcloud log |
| `ddev mailpit` | Open Mailpit to see sent mails |
| `ddev xdebug on` / `ddev xdebug off` | Toggle Xdebug |
| `ddev nextcloud-reset` | Reinstall Nextcloud from scratch (database, users and files are lost) |
| `ddev nextcloud-reset --full` | Same, and download the server code again |

### Testing

All tests boot Nextcloud ([tests/bootstrap.php](tests/bootstrap.php)), so they run inside DDEV or in CI. The `unit` suite uses mocks only. The `integration` suite writes to the database of the instance and removes the rows it created afterwards; run it on a development instance only. CI runs both suites on Nextcloud 34 and 35 with SQLite.

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

Change `NEXTCLOUD_VERSION` in [.ddev/config.yaml](.ddev/config.yaml) and run `ddev restart`. The server code is replaced and `occ upgrade` runs; your data is kept. Downgrades are not possible: `ddev restart` then only prints a warning, and `ddev nextcloud-reset --full` installs the configured version from scratch.

### Supporting Nextcloud 34 and 35

The app supports Nextcloud 34 and 35 and PHP 8.2 to 8.5. The development instance runs Nextcloud 35.

- `composer.json` pins `nextcloud/ocp` to the **lowest** supported version (`dev-stable34`), so autocompletion and psalm only offer API that exists in both. CI also runs psalm against `dev-stable35` and the PHP tests in Nextcloud 34 and 35.
- Avoid PHP 8.3 syntax (e.g. typed class constants); CI lints on PHP 8.2.
- To try the app on Nextcloud 34 locally, run `ddev exec 'NEXTCLOUD_VERSION=34.0.5 bash /mnt/ddev_config/commands/web/nextcloud-reset --full'`. A later `ddev restart` upgrades the instance back to the configured Nextcloud 35, which also tests the upgrade path.

### Translations

Source strings are English: `$l->t('…')` in PHP and `t('laudatio', '…')` in the frontend. Translations live in [l10n/de.json](l10n/de.json) (German, informal) and [l10n/de_DE.json](l10n/de_DE.json) (German, formal). After adding or changing strings, add them to both files and run `ddev npm run l10n`: it lists missing and unused strings and writes the `l10n/*.js` files the frontend loads. CI fails if a translation is missing. See [CONTRIBUTING.md](CONTRIBUTING.md) for adding a language.

### Architecture

- `lib/Service`: domain logic. `EventCalculator` and `PeriodCalculator` compute the events, `EventService` keeps the `laudatio_events` table in sync, `AccessService` holds all permission rules, `Presenter` applies them to API responses.
- `lib/Controller`: OCS API (`/ocs/v2.php/apps/laudatio/api/v1/…`, documented in [openapi.json](openapi.json)) and the page.
- `lib/Calendar`: the calendar provider (public `ICalendarProvider` API).
- `lib/Notification`, `lib/BackgroundJob`, `lib/Command`, `lib/Settings`, `lib/Listener`: notifier, hourly job, occ commands, admin settings form, cleanup when a user is deleted.
- `src`: Vue 3 frontend with `@nextcloud/vue`.

### Export file format

The export is a JSON file, written by `ExportService` and read by `ImportValidator` and `ImportService`:

```json
{
	"format": "laudatio-export",
	"version": 1,
	"exportedAt": "2026-10-30",
	"groups": [
		{
			"name": "Board",
			"jubileeYears": "5, 10",
			"functions": [
				{ "id": 3, "name": "Treasurer", "yearlyGift": true }
			]
		}
	],
	"persons": [
		{
			"firstName": "Anna",
			"lastName": "Muster",
			"dateOfBirth": "1986-11-01",
			"notes": null,
			"street": null,
			"streetNumber": null,
			"postalCode": null,
			"city": null,
			"country": null,
			"mobilePhone": null,
			"otherPhone": null,
			"terms": [
				{ "functionId": 3, "startDate": "2021-01-01", "endDate": null }
			]
		}
	]
}
```

- `format` identifies the file, `version` is the version of this format. `exportedAt` is informational.
- A function's `id` only links terms to it within the file; the import assigns new IDs. `functionId` must refer to a function in the file.
- `jubileeYears: null` means the group uses the global list. Dates are `YYYY-MM-DD`, `endDate: null` means the term is still open.
- Values must pass the same rules as the regular input. Keys whose value may be `null` can be left out, all others are required. Unknown keys are ignored.

When the format changes, increase `ExportService::VERSION` and keep reading the older versions in `ImportValidator`, because backups live on. Leave the version 1 sample in `ImportValidatorTest` as it is. Files with a newer version than the app knows are refused.

### Releasing

Releases go to the Nextcloud App Store through the GitLab CI pipeline of a version tag, see [RELEASING.md](RELEASING.md).

## Contributing and support

Issues and pull requests on GitHub are welcome, see [CONTRIBUTING.md](CONTRIBUTING.md). Please report security issues privately as described in [SECURITY.md](SECURITY.md).

## Known issues

- Nextcloud 35.0.2 rejects saving *personal* declarative settings for users that are neither admin nor subadmin (the settings app's `DeclarativeSettingsController::setValue` lacks `#[NoSubAdminRequired]`). The personal setting of this app is therefore in the app itself.

## License

[AGPL-3.0-or-later](LICENSE)
