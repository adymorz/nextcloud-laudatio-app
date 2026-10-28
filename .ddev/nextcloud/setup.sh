#!/usr/bin/env bash
#
# SPDX-FileCopyrightText: 2026 Adrian Dymorz <dev@adrian.dymorz.ch>
# SPDX-License-Identifier: AGPL-3.0-or-later
#
# Sets up the Nextcloud dev instance inside the DDEV web container.
# Runs as post-start hook on every `ddev start` and is idempotent:
#   - downloads and verifies the Nextcloud release ($NEXTCLOUD_VERSION),
#     or upgrades the code in place if the version was bumped
#   - links this repository into apps-extra/ and the dev config into config/
#   - installs Nextcloud (MariaDB) with test users on first run
#   - runs pending upgrades and enables the app

set -euo pipefail
shopt -s inherit_errexit

NC="${NEXTCLOUD_ROOT:-/var/www/nextcloud}"
APP_ID="${NEXTCLOUD_APP_ID:-appreciation}"
APP_DIR=/var/www/html
DEV_CONFIG=/mnt/ddev_config/nextcloud/ddev.config.php

log() { echo "[nextcloud] $*"; }
occ() { php "$NC/occ" "$@"; }

installed_version() {
	[ -f "$NC/version.php" ] && php -r 'include $argv[1]; echo $OC_VersionString;' "$NC/version.php"
}

config_says_installed() {
	[ -f "$NC/config/config.php" ] && php -r 'include $argv[1]; exit(empty($CONFIG["installed"]) ? 1 : 0);' "$NC/config/config.php"
}

db_has_nextcloud() {
	mysql -h db -u db -pdb db -N -e "SHOW TABLES LIKE 'oc_appconfig'" 2>/dev/null | grep -q oc_appconfig
}

# Downloads the release into a temp dir and prints the path of the extracted "nextcloud" folder.
download_release() {
	local file="nextcloud-${NEXTCLOUD_VERSION}.tar.bz2"
	local url="https://download.nextcloud.com/server/releases/$file"
	local tmp
	tmp="$(mktemp -d)"
	log "Downloading Nextcloud ${NEXTCLOUD_VERSION} ..." >&2
	curl -fsSL -o "$tmp/$file" "$url"
	curl -fsSL -o "$tmp/$file.sha256" "$url.sha256"
	# The .sha256 file also lists the .metadata file; only check the tarball.
	(cd "$tmp" && grep " $file\$" "$file.sha256" | sha256sum --check --strict --quiet -) >&2
	tar -xjf "$tmp/$file" -C "$tmp"
	rm "$tmp/$file"
	echo "$tmp/nextcloud"
}

# --- Server code -------------------------------------------------------------

# The named volume is created root-owned; the web server runs as the DDEV user.
if [ ! -w "$NC" ]; then
	sudo chown "$(id -u):$(id -g)" "$NC"
fi

current="$(installed_version || true)"
needs_upgrade=false
if [ -z "$current" ]; then
	src="$(download_release)"
	cp -a "$src/." "$NC/"
	rm -rf "$(dirname "$src")"
elif [ "$current" != "$NEXTCLOUD_VERSION" ]; then
	log "Updating server code from $current to $NEXTCLOUD_VERSION ..."
	src="$(download_release)"
	rsync -a --delete \
		--exclude=/config/ --exclude=/data/ --exclude=/apps-extra/ --exclude=/custom_apps/ \
		"$src/" "$NC/"
	rm -rf "$(dirname "$src")"
	needs_upgrade=true
fi

mkdir -p "$NC/apps-extra" "$NC/custom_apps"
ln -sfn "$APP_DIR" "$NC/apps-extra/$APP_ID"
ln -sfn "$DEV_CONFIG" "$NC/config/ddev.config.php"

# --- Installation ------------------------------------------------------------

if config_says_installed && ! db_has_nextcloud; then
	log "config.php exists but the database is empty (e.g. after 'ddev delete'), reinstalling ..."
	rm -f "$NC/config/config.php"
	rm -rf "$NC/data"
fi

if ! config_says_installed; then
	log "Installing Nextcloud ..."
	# Start from an empty database, regardless of leftovers.
	mysql -h db -u root -proot -e "DROP DATABASE IF EXISTS db; CREATE DATABASE db; GRANT ALL ON db.* TO 'db'@'%';"
	# Nextcloud refuses to write a fresh config without this marker.
	touch "$NC/config/CAN_INSTALL"
	occ maintenance:install \
		--database=mysql --database-host=db --database-name=db --database-user=db --database-pass=db \
		--admin-user=admin --admin-pass=admin --data-dir="$NC/data"

	occ maintenance:update:htaccess
	occ user:setting admin settings email admin@example.com
	# Background jobs are run by the nextcloud-cron daemon (see config.yaml).
	occ background:cron

	# Allow trivial passwords for the test users below, and skip the welcome
	# wizard that would otherwise greet every test user.
	occ app:disable password_policy
	occ app:disable firstrunwizard
	for user in alice bob; do
		OC_PASS="$user" occ user:add --password-from-env --display-name="${user^}" "$user"
		occ user:setting "$user" settings email "$user@example.com"
	done
fi

# --- Upgrades and app --------------------------------------------------------

if [ "$needs_upgrade" = true ] || occ status --output=json | php -r 'exit(json_decode(stream_get_contents(STDIN))->needsDbUpgrade ? 0 : 1);'; then
	log "Running occ upgrade ..."
	occ upgrade
	occ maintenance:update:htaccess
fi

if [ -f "$APP_DIR/appinfo/info.xml" ]; then
	if [ "$(occ config:app:get "$APP_ID" enabled 2>/dev/null || true)" != "yes" ]; then
		occ app:enable "$APP_ID" || log "WARNING: could not enable app '$APP_ID', check the output above."
	fi
else
	log "No appinfo/info.xml found in the repository, skipping app activation."
fi

log "Nextcloud $(installed_version) is ready at ${DDEV_PRIMARY_URL:-https://appreciation.ddev.site}"
log "Logins: admin/admin, alice/alice, bob/bob · Mails: ddev mailpit"
