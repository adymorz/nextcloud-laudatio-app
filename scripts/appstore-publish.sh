#!/bin/sh
# SPDX-FileCopyrightText: 2026 Adrian Dymorz <dev@adrian.dymorz.ch>
# SPDX-License-Identifier: AGPL-3.0-or-later
#
# Publishes the signed archive built by appstore-package.sh: attaches it to the
# GitHub release of the tag v<version> on the public mirror, then registers the
# release in the Nextcloud App Store, which downloads it from there.
#
# The mirror may have immutable releases enabled: a published release then takes
# no more assets and its tag can never get another release. So the release is
# created as a draft and only published once the archive is attached.
#
#   GITHUB_TOKEN       fine-grained token with "Contents: read and write" on the mirror
#   APPSTORE_TOKEN     API token from https://apps.nextcloud.com/account/token
#   GITHUB_REPOSITORY  owner/name of the mirror (default: adymorz/nextcloud-laudatio-app)
#   DRY_RUN=1          only print what would be published
#
# Requires curl and jq.

set -eu

APP_ID=laudatio
APPSTORE_API=https://apps.nextcloud.com/api/v1/apps/releases

fail() {
	echo "$1" >&2
	exit 1
}

cd "$(dirname "$0")/.."

repo=${GITHUB_REPOSITORY:-adymorz/nextcloud-laudatio-app}
version=$(sed -n 's:.*<version>\(.*\)</version>.*:\1:p' appinfo/info.xml)
tag="v$version"
name="$APP_ID-$version.tar.gz"
archive="build/appstore/$name"
[ -f "$archive" ] && [ -f "$archive.sig" ] \
	|| fail "$archive or its signature is missing, run scripts/appstore-package.sh with the signing key first."

case $version in
	*-*) prerelease=true ;;
	*) prerelease=false ;;
esac
notes=$(scripts/release-notes.sh)

release=$(jq -n --arg tag "$tag" --arg body "$notes" --argjson prerelease "$prerelease" \
	'{tag_name: $tag, name: $tag, body: ($body | sub("^\\s+"; "") | sub("\\s+$"; "")), prerelease: $prerelease, draft: true}')
appstore=$(jq -n --arg download "https://github.com/$repo/releases/download/$tag/$name" --arg signature "$(cat "$archive.sig")" \
	'{download: $download, signature: $signature, nightly: false}')

if [ "${DRY_RUN:-}" = 1 ]; then
	echo "GitHub release in $repo:"
	echo "$release" | jq .
	echo "Asset: $archive ($(wc -c < "$archive") bytes)"
	echo "App Store release ($APPSTORE_API):"
	echo "$appstore" | jq .
	exit 0
fi

: "${GITHUB_TOKEN:?GITHUB_TOKEN is not set}"
: "${APPSTORE_TOKEN:?APPSTORE_TOKEN is not set}"

api="https://api.github.com/repos/$repo"
github() {
	curl -fsS -H "Authorization: Bearer $GITHUB_TOKEN" -H 'Accept: application/vnd.github+json' \
		-H 'X-GitHub-Api-Version: 2022-11-28' "$@"
}

# The tag reaches GitHub through the push mirror, which may take a moment
attempt=1
while :; do
	status=$(curl -sS -o /dev/null -w '%{http_code}' -H "Authorization: Bearer $GITHUB_TOKEN" "$api/git/ref/tags/$tag")
	case $status in
		200) break ;;
		404) [ "$attempt" -lt 20 ] || fail "$tag did not arrive on github.com/$repo within 10 minutes, check the push mirror in GitLab." ;;
		*) fail "GitHub answered $status when looking up $tag in $repo, check GITHUB_TOKEN." ;;
	esac
	echo "Waiting for the push mirror to bring $tag to github.com/$repo..."
	attempt=$((attempt + 1))
	sleep 30
done

# A draft left by an earlier run is reused. The lookup by tag ignores drafts, the list has them.
releases=$(github "$api/releases?per_page=100")
existing=$(echo "$releases" | jq --arg tag "$tag" 'map(select(.tag_name == $tag)) | .[0] // empty')
[ -n "$existing" ] || existing=$(echo "$release" | github -X POST --data @- "$api/releases")
id=$(echo "$existing" | jq -r .id)

if [ "$(echo "$existing" | jq .draft)" = true ]; then
	# Replace an archive from an earlier run, the App Store gets the signature of this one
	for asset in $(echo "$existing" | jq -r --arg name "$name" '.assets[] | select(.name == $name) | .id'); do
		github -X DELETE "$api/releases/assets/$asset"
	done
	github -X POST -H 'Content-Type: application/gzip' --data-binary "@$archive" \
		"https://uploads.github.com/repos/$repo/releases/$id/assets?name=$name" > /dev/null
	github -X PATCH --data '{"draft": false}' "$api/releases/$id" > /dev/null
	echo "Published https://github.com/$repo/releases/tag/$tag with $name"
else
	# Published by an earlier run that failed at the App Store: go on if it has this very archive
	digest=$(echo "$existing" | jq -r --arg name "$name" '.assets[] | select(.name == $name) | .digest')
	[ "$digest" = "sha256:$(sha256sum "$archive" | cut -d ' ' -f 1)" ] \
		|| fail "The release $tag in $repo is already published without this archive. Delete it on GitHub and run this job again; if it is immutable, its tag cannot be released again, release a new version instead."
	echo "https://github.com/$repo/releases/tag/$tag is already published with $name"
fi

response=$(mktemp)
status=$(echo "$appstore" | curl -sS -o "$response" -w '%{http_code}' -X POST \
	-H "Authorization: Token $APPSTORE_TOKEN" -H 'Content-Type: application/json' --data @- "$APPSTORE_API")
case $status in
	200 | 201) echo "Published $version in the App Store: https://apps.nextcloud.com/apps/$APP_ID" ;;
	*)
		cat "$response" >&2
		fail "The App Store answered $status."
		;;
esac
