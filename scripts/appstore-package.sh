#!/bin/sh
# SPDX-FileCopyrightText: 2026 Adrian Dymorz <dev@adrian.dymorz.ch>
# SPDX-License-Identifier: AGPL-3.0-or-later
#
# Builds the App Store archive build/appstore/appreciation-<version>.tar.gz from
# the working copy. Run `npm run build` first.
#
# Signing, needed for a release (all paths absolute):
#   APP_PRIVATE_KEY  appreciation.key
#   APP_CERTIFICATE  appreciation.crt
#   NEXTCLOUD_ROOT   an installed Nextcloud whose occ signs the code (appinfo/signature.json)
# With a key, the archive signature for the App Store is written to <archive>.sig.

set -eu

APP_ID=appreciation
MAX_SIZE=$((20 * 1024 * 1024))

fail() {
	echo "$1" >&2
	exit 1
}

cd "$(dirname "$0")/.."

version=$(sed -n 's:.*<version>\(.*\)</version>.*:\1:p' appinfo/info.xml)
[ -n "$version" ] || fail "No version found in appinfo/info.xml."

# The App Store takes the release notes from this heading; pre-releases from "Unreleased"
case $version in
	*-*) heading='## [Unreleased]' ;;
	*) heading="## $version" ;;
esac
awk -v h="$heading" '$0 == h || index($0, h " ") == 1 { found = 1 } END { exit !found }' CHANGELOG.md \
	|| fail "CHANGELOG.md has no heading \"$heading\" (e.g. \"$heading – $(date +%Y-%m-%d)\")."

[ -f js/appreciation-main.mjs ] || fail "The frontend is not built, run 'npm run build' first."

out=build/appstore
archive="$out/$APP_ID-$version.tar.gz"
rm -rf "$out"
mkdir -p "$out/$APP_ID"
cp -R appinfo css img js l10n lib templates CHANGELOG.md LICENSE README.md openapi.json "$out/$APP_ID/"

if [ -n "${APP_PRIVATE_KEY:-}" ]; then
	[ -n "${APP_CERTIFICATE:-}" ] && [ -n "${NEXTCLOUD_ROOT:-}" ] \
		|| fail "Signing needs APP_PRIVATE_KEY, APP_CERTIFICATE and NEXTCLOUD_ROOT."
	php "$NEXTCLOUD_ROOT/occ" integrity:sign-app \
		--privateKey="$APP_PRIVATE_KEY" --certificate="$APP_CERTIFICATE" --path="$(pwd)/$out/$APP_ID"
fi

tar -czf "$archive" -C "$out" "$APP_ID"
size=$(wc -c < "$archive")
[ "$size" -le "$MAX_SIZE" ] || fail "$archive is $size bytes, the App Store accepts at most $MAX_SIZE."

if [ -n "${APP_PRIVATE_KEY:-}" ]; then
	openssl dgst -sha512 -sign "$APP_PRIVATE_KEY" "$archive" | openssl base64 -A > "$archive.sig"
	echo "Built and signed $archive ($size bytes)."
else
	echo "Built $archive ($size bytes), unsigned."
fi
