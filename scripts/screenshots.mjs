/**
 * SPDX-FileCopyrightText: 2026 Adrian Dymorz <dev@adrian.dymorz.ch>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

/*
 * Takes the App Store screenshots (screenshots/*.png, 1440 px wide) from the
 * DDEV instance with headless Chromium.
 *
 * One-time preparation in the web container (not part of the image):
 *   ddev exec sudo apt-get install -y chromium
 *   ddev npm install --no-save puppeteer-core
 *
 * Scene: `ddev occ laudatio:demo-data` on an empty app, then rename the groups
 * without the "Demo: " prefix, give Beat Beispiel an address, a mobile phone
 * and notes, set the display names "Alice Keller" and "Bob Meier", create the
 * Nextcloud group "Office" and choose it as manager group, set a gift
 * regulations link and a data sheet note in the admin settings, and install the
 * Calendar app. Build the frontend first (`ddev npm run build`).
 *
 * Usage: ddev exec node scripts/screenshots.mjs [base URL]
 */

import puppeteer from 'puppeteer-core'

const base = process.argv[2] ?? 'https://laudatio.ddev.site'
const width = 1440

const timezone = 'Europe/Zurich'

const browser = await puppeteer.launch({
	executablePath: process.env.CHROMIUM ?? '/usr/bin/chromium',
	headless: true,
	acceptInsecureCerts: true,
	args: ['--no-sandbox', '--disable-dev-shm-usage', '--disable-gpu', '--ignore-certificate-errors', '--lang=en-US'],
	defaultViewport: { width, height: 900 },
	env: { ...process.env, TZ: timezone },
})

/**
 * @param {string} user Login name, the password is the same (DDEV test users)
 */
async function login(user) {
	const context = await browser.createBrowserContext()
	const page = await context.newPage()
	// The Calendar app warns about an unknown time zone otherwise
	await page.emulateTimezone(timezone)
	await page.setExtraHTTPHeaders({ 'Accept-Language': 'en-US,en' })
	await page.goto(`${base}/login`, { waitUntil: 'networkidle0' })
	await page.type('#user', user)
	await page.type('#password', user)
	await Promise.all([
		page.waitForNavigation({ waitUntil: 'networkidle0' }),
		page.click('button[type="submit"]'),
	])
	return page
}

/**
 * @param {import('puppeteer-core').Page} page Logged-in page
 * @param {string} path Path to open
 * @param {string} name File name without extension
 * @param {{ height?: number, prepare?: (page: import('puppeteer-core').Page) => Promise<void> }} options Viewport height and a hook before the shot
 */
async function shot(page, path, name, { height = 900, prepare } = {}) {
	await page.setViewport({ width, height })
	await page.goto(`${base}${path}`, { waitUntil: 'networkidle0' })
	await prepare?.(page)
	await new Promise((resolve) => setTimeout(resolve, 1000))
	await page.screenshot({ path: `screenshots/${name}.png` })
	console.log(`screenshots/${name}.png`)
}

const admin = await login('admin')
const persons = await admin.evaluate(async () => {
	const response = await fetch('/ocs/v2.php/apps/laudatio/api/v1/persons', {
		headers: { 'OCS-APIRequest': 'true', Accept: 'application/json', requesttoken: OC.requestToken },
	})
	return (await response.json()).ocs.data
})
const beat = persons.find((person) => person.lastName === 'Beispiel') ?? persons[0]

await shot(admin, '/apps/laudatio/persons', 'persons')
await shot(admin, `/apps/laudatio/persons/${beat.id}`, 'person')
// Tall enough for the function history below the personal data
await shot(admin, `/apps/laudatio/persons/${beat.id}/sheet`, 'sheet', { height: 1200 })
await shot(admin, '/apps/laudatio/groups', 'groups')
// Only the Laudatio section: the other sections of the page are removed, so it starts at the top
await shot(admin, '/settings/admin/groupware', 'settings', {
	height: 1040,
	prepare: async (page) => {
		await page.evaluate(() => {
			const heading = [...document.querySelectorAll('h2')].find((h) => h.textContent.trim() === 'Laudatio')
			const section = heading?.closest('.settings-section')
			document.querySelectorAll('.settings-section').forEach((other) => {
				if (other !== section) {
					other.remove()
				}
			})
			document.querySelector('#app-content-vue')?.scrollTo(0, 0)
		})
	},
})

const alice = await login('alice')
await shot(alice, '/apps/laudatio/upcoming', 'upcoming')
// Next month, where the demo events fall
const next = new Date()
next.setDate(1)
next.setMonth(next.getMonth() + 1)
const firstDay = `${next.getFullYear()}-${String(next.getMonth() + 1).padStart(2, '0')}-01`
await shot(alice, `/apps/calendar/dayGridMonth/${firstDay}`, 'calendar')

await browser.close()
