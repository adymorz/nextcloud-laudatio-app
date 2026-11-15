/**
 * SPDX-FileCopyrightText: 2026 Adrian Dymorz <dev@adrian.dymorz.ch>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

/*
 * Keeps the app version consistent between appinfo/info.xml, package.json and
 * package-lock.json, and with the release tag.
 *
 * Usage:
 *   node scripts/version.mjs check [tag]
 *     Exits with 1 if the versions differ, are no SemVer, or the tag is given
 *     and is not v<version>. An empty tag counts as none (for CI branch pipelines).
 *   node scripts/version.mjs sync
 *     Writes the version of package.json into appinfo/info.xml, then checks.
 *     Runs as the "version" script of `npm version X.Y.Z` (see RELEASING.md).
 */

import { readFileSync, writeFileSync } from 'node:fs'
import { join } from 'node:path'
import { fileURLToPath } from 'node:url'

const root = fileURLToPath(new URL('..', import.meta.url))
const INFO_XML = 'appinfo/info.xml'
const VERSION_TAG = /<version>([^<]*)<\/version>/g
const SEMVER = /^\d+\.\d+\.\d+(-[0-9A-Za-z.-]+)?$/

/**
 * @param {string} message error message
 * @return {never}
 */
function fail(message) {
	console.error(message)
	process.exit(1)
}

/**
 * @param {string} file path relative to the app
 * @return {string}
 */
function read(file) {
	return readFileSync(join(root, file), 'utf8')
}

/**
 * @param {string} xml contents of info.xml
 * @return {RegExpMatchArray}
 */
function versionTag(xml) {
	const matches = [...xml.matchAll(VERSION_TAG)]
	if (matches.length !== 1) {
		fail(`${INFO_XML} must contain exactly one <version>, found ${matches.length}.`)
	}
	return matches[0]
}

/**
 * @return {[string, string][]} pairs of location and version, info.xml first
 */
function versions() {
	const pkg = JSON.parse(read('package.json'))
	const lock = JSON.parse(read('package-lock.json'))
	return [
		[INFO_XML, versionTag(read(INFO_XML))[1].trim()],
		['package.json', pkg.version],
		['package-lock.json', lock.version],
		['package-lock.json (packages[""])', lock.packages?.['']?.version],
	]
}

/**
 * @param {string} tag git tag, empty for none
 */
function check(tag) {
	const found = versions()
	const [, version] = found[0]
	if (found.some(([, v]) => v !== version)) {
		const width = Math.max(...found.map(([location]) => location.length))
		fail('The versions differ:\n'
			+ found.map(([location, v]) => `  ${location.padEnd(width)}  ${v ?? '(none)'}`).join('\n')
			+ '\nSet them all at once with `npm version X.Y.Z`.')
	}
	if (!SEMVER.test(version)) {
		fail(`The version "${version}" is no SemVer version X.Y.Z or X.Y.Z-pre.release.`)
	}
	if (tag && tag !== `v${version}`) {
		fail(`The tag ${tag} does not match the version ${version}, it must be v${version}.\n`
			+ `Delete the tag (git tag -d ${tag} && git push origin :refs/tags/${tag}) and tag the commit that carries the version.`)
	}
	console.log(tag ? `Version ${version} matches the tag ${tag}.` : `Version ${version} is consistent.`)
}

function sync() {
	const version = JSON.parse(read('package.json')).version
	if (!SEMVER.test(version ?? '')) {
		fail(`The version "${version}" in package.json is no SemVer version X.Y.Z or X.Y.Z-pre.release.`)
	}
	const xml = read(INFO_XML)
	const match = versionTag(xml)
	const updated = xml.slice(0, match.index) + `<version>${version}</version>` + xml.slice(match.index + match[0].length)
	writeFileSync(join(root, INFO_XML), updated)
	check('')
}

const [command, tag = ''] = process.argv.slice(2)
switch (command) {
case 'check':
	check(tag)
	break
case 'sync':
	sync()
	break
default:
	fail('Usage: node scripts/version.mjs check [tag] | sync')
}
