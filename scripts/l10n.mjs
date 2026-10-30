/**
 * SPDX-FileCopyrightText: 2026 Adrian Dymorz <dev@adrian.dymorz.ch>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

/*
 * Translation helper without external tools.
 *
 * - Extracts the translatable strings from lib/ (PHP: ->t('…'), ->n('…', '…'))
 *   and src/ (t('laudatio', '…'), n('laudatio', '…', '…')).
 * - Checks every l10n/<lang>.json for missing and unused strings.
 * - Writes l10n/<lang>.js (used by the frontend) from l10n/<lang>.json.
 *
 * Usage: node scripts/l10n.mjs [--list]
 * Exits with 1 if a translation is missing.
 */

import { readdirSync, readFileSync, statSync, writeFileSync } from 'node:fs'
import { join } from 'node:path'

const APP = 'laudatio'
const root = new URL('..', import.meta.url).pathname
const string = "'((?:[^'\\\\]|\\\\.)*)'"
const patterns = {
	php: [
		[new RegExp(`->t\\(\\s*${string}`, 'g'), false],
		[new RegExp(`->n\\(\\s*${string}\\s*,\\s*${string}`, 'g'), true],
	],
	js: [
		[new RegExp(`\\bt\\(\\s*'${APP}'\\s*,\\s*${string}`, 'g'), false],
		[new RegExp(`\\bn\\(\\s*'${APP}'\\s*,\\s*${string}\\s*,\\s*${string}`, 'g'), true],
	],
}

/**
 * @param {string} dir directory
 * @param {string[]} extensions file extensions
 * @return {string[]}
 */
function files(dir, extensions) {
	return readdirSync(dir).flatMap((name) => {
		const path = join(dir, name)
		if (statSync(path).isDirectory()) {
			return files(path, extensions)
		}
		return extensions.some((extension) => name.endsWith(extension)) ? [path] : []
	})
}

const unescape = (value) => value.replace(/\\(['\\])/g, '$1')

// Keys as used by Nextcloud: plain string, or "_singular_::_plural_"
const keys = new Set()
for (const [dir, extensions, kind] of [['lib', ['.php'], 'php'], ['src', ['.ts', '.vue'], 'js']]) {
	for (const file of files(join(root, dir), extensions)) {
		if (file.endsWith('openapi.ts')) {
			continue
		}
		const content = readFileSync(file, 'utf8')
		for (const [regex, plural] of patterns[kind]) {
			for (const match of content.matchAll(regex)) {
				keys.add(plural ? `_${unescape(match[1])}_::_${unescape(match[2])}_` : unescape(match[1]))
			}
		}
	}
}

if (process.argv.includes('--list')) {
	console.log(JSON.stringify([...keys].sort(), null, '\t'))
	process.exit(0)
}

let missingTotal = 0
for (const file of readdirSync(join(root, 'l10n')).filter((name) => name.endsWith('.json'))) {
	const language = file.slice(0, -'.json'.length)
	const { translations, pluralForm } = JSON.parse(readFileSync(join(root, 'l10n', file), 'utf8'))
	const missing = [...keys].filter((key) => !(key in translations))
	const unused = Object.keys(translations).filter((key) => !keys.has(key))
	missing.forEach((key) => console.log(`${language}: missing "${key}"`))
	unused.forEach((key) => console.log(`${language}: unused "${key}"`))
	missingTotal += missing.length

	const js = `OC.L10N.register(\n    ${JSON.stringify(APP)},\n    ${JSON.stringify(translations, null, 4).replace(/\n/g, '\n    ')},\n${JSON.stringify(pluralForm)});\n`
	writeFileSync(join(root, 'l10n', language + '.js'), js)
	console.log(`${language}: ${Object.keys(translations).length} translations, ${missing.length} missing, ${unused.length} unused`)
}
process.exit(missingTotal > 0 ? 1 : 0)
