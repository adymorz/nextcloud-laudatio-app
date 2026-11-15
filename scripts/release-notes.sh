#!/bin/sh
# SPDX-FileCopyrightText: 2026 Adrian Dymorz <dev@adrian.dymorz.ch>
# SPDX-License-Identifier: AGPL-3.0-or-later
#
# Prints the release notes of the version in appinfo/info.xml from CHANGELOG.md:
# the section "## X.Y.Z – YYYY-MM-DD", or "## [Unreleased]" for a pre-release.
# The App Store finds the notes under the same heading. Exits with 1 if the
# heading is missing.

set -eu

fail() {
	echo "$1" >&2
	exit 1
}

cd "$(dirname "$0")/.."

version=$(sed -n 's:.*<version>\(.*\)</version>.*:\1:p' appinfo/info.xml)
[ -n "$version" ] || fail "No version found in appinfo/info.xml."

case $version in
	*-*) heading='## [Unreleased]' ;;
	*) heading="## $version" ;;
esac

awk -v h="$heading" '
	found && /^## / { exit }
	found { print }
	$0 == h || index($0, h " ") == 1 { found = 1 }
	END { exit !found }
' CHANGELOG.md || fail "CHANGELOG.md has no heading \"$heading\" (e.g. \"$heading – $(date +%Y-%m-%d)\")."
