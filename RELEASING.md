<!--
  - SPDX-FileCopyrightText: 2026 Adrian Dymorz <dev@adrian.dymorz.ch>
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

# Releasing

Releases go to the [Nextcloud App Store](https://apps.nextcloud.com/apps/laudatio) through the GitLab CI pipeline of a version tag. The release archives are attached to the GitHub releases of the mirror, from where the App Store and every Nextcloud server download them.

## For each release

1. Set the new version in [appinfo/info.xml](appinfo/info.xml) and `package.json`.
2. In [CHANGELOG.md](CHANGELOG.md), rename `## [Unreleased]` to `## X.Y.Z – YYYY-MM-DD` and add a new empty `## [Unreleased]` above it. The App Store only finds headings that start with the plain version (no brackets). Pre-releases (`X.Y.Z-beta.1`) take their notes from `## [Unreleased]`.
3. Retake the screenshots in `screenshots/` if the UI changed; the App Store loads them from the `main` branch of the mirror.
4. Merge to `main`, then tag and push: `git tag vX.Y.Z && git push origin vX.Y.Z`.

After all checks pass, `release:package` builds the archive and signs both the code (`appinfo/signature.json`) and the archive ([scripts/appstore-package.sh](scripts/appstore-package.sh)). `release:publish` then attaches it to the GitHub release and registers it in the App Store ([scripts/appstore-publish.sh](scripts/appstore-publish.sh)). The pipeline fails if the tag, the version in info.xml and the changelog heading don't match.

To try the packaging locally, run `ddev npm run build` and then `ddev exec scripts/appstore-package.sh`, which builds the unsigned archive in `build/appstore/`.

## One-time setup

1. **GitHub mirror**: a public repository `adymorz/nextcloud-laudatio-app` with issues enabled, created empty (no README or license) so the mirror can push to it. In GitLab, Settings → Repository → Mirroring repositories, push to `https://github.com/adymorz/nextcloud-laudatio-app.git` with a fine-grained GitHub token (Contents: read and write), including tags. All links in `appinfo/info.xml` and the README point to this repository, including the screenshots.
2. **Certificate**: the private key `laudatio.key` and the signing request `laudatio.csr` are in `~/.nextcloud/certificates/`. Never commit or share the key. The GitHub profile must show its email address, then submit the request as `laudatio/laudatio.csr` in a pull request to [nextcloud/app-certificate-requests](https://github.com/nextcloud/app-certificate-requests) and save the signed certificate as `~/.nextcloud/certificates/laudatio.crt`.
3. **App Store**: log in at [apps.nextcloud.com](https://apps.nextcloud.com) and [register the app](https://apps.nextcloud.com/developer/apps/new) with the certificate and the signature of the app ID: `echo -n laudatio | openssl dgst -sha512 -sign ~/.nextcloud/certificates/laudatio.key | openssl base64`.
4. **GitLab CI/CD**: protect the tags `v*` (Settings → Repository → Protected tags) and add these protected variables (Settings → CI/CD → Variables):

| Variable | Type | Value |
| --- | --- | --- |
| `APP_PRIVATE_KEY` | File | Contents of `laudatio.key` |
| `APP_CERTIFICATE` | File | Contents of `laudatio.crt` |
| `GITHUB_TOKEN` | Variable, masked | Fine-grained GitHub token for the mirror repository with "Contents: read and write" |
| `APPSTORE_TOKEN` | Variable, masked | API token from [apps.nextcloud.com/account/token](https://apps.nextcloud.com/account/token) |
