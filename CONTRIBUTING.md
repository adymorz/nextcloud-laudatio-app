<!--
  - SPDX-FileCopyrightText: 2026 Adrian Dymorz <dev@adrian.dymorz.ch>
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

# Contributing to Laudatio

Thank you for your interest. Laudatio is developed on a private, self-hosted GitLab; [GitHub](https://github.com/adymorz/nextcloud-laudatio-app) is a public mirror where issues and pull requests are welcome.

## Issues

Report bugs and ideas in the [issue tracker](https://github.com/adymorz/nextcloud-laudatio-app/issues). For a bug, include the versions of Nextcloud and the app, what you did, what happened and what you expected. Do **not** report security issues there, see [SECURITY.md](SECURITY.md).

## Pull requests

1. Fork the mirror and create a branch.
2. Set up the development environment as described in the [README](README.md#development) and run the checks before you push: `ddev composer cs:fix`, `ddev composer psalm`, `ddev composer test`, `ddev npm run lint`, `ddev npm run typecheck` and `ddev npm run l10n`. After API changes, regenerate `openapi.json` and `src/types/openapi.ts` with `ddev composer openapi` and `ddev npm run typescript:generate`.
3. Keep user-visible strings in English in the code, add the German translations to both `l10n/de.json` (informal) and `l10n/de_DE.json` (formal) and run `ddev npm run l10n`.
4. Open the pull request on GitHub. Accepted pull requests are merged on GitLab by the maintainer and arrive in the mirror with the next push; the pull request is then closed with a reference to the commit. Your authorship is kept.

By contributing you agree that your contribution is licensed under the [AGPL-3.0-or-later](LICENSE), like the rest of the project.

## Translations

A new language is a pull request with `l10n/<code>.json`: copy `l10n/de.json`, translate the values (keep placeholders like `%s`, `%1$s` and `{name}`), set the `pluralForm` of the language and run `ddev npm run l10n`, which checks for missing strings and writes `l10n/<code>.js`.
