<!--
  - SPDX-FileCopyrightText: 2026 Adrian Dymorz <dev@adrian.dymorz.ch>
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

# Appreciation

Appreciation app for Nextcloud. Work in progress.

Requires Nextcloud 35.

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
| `ddev composer lint` | Check PHP syntax |
| `ddev composer cs:check` / `ddev composer cs:fix` | Check / fix the PHP coding style |
| `ddev composer psalm` | Static analysis |
| `ddev composer rector` | Automated refactoring (then runs `cs:fix`) |
| `ddev composer test:unit` | PHPUnit tests |
| `ddev composer openapi` | Regenerate [openapi.json](openapi.json) after API changes |
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

## License

[AGPL-3.0-or-later](LICENSE)
