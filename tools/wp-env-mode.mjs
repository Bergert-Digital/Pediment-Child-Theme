#!/usr/bin/env node
/**
 * Switches the local wp-env between two source modes by toggling the
 * theme/plugin refs in `.wp-env.override.json` (which is gitignored):
 *
 *   dev      — mount the sibling working copies for fast iteration:
 *                themes:  [".", "../pediment"]
 *                plugins: ["../pediment-ai"]
 *   publish  — drop the override refs so the *committed* `.wp-env.json`
 *              release-zip URLs take effect (the push-ready config).
 *
 * Why the override file rather than rewriting `.wp-env.json`:
 *   wp-env's merge (see node_modules/@wordpress/env/lib/config/merge-configs.js)
 *   fully *replaces* the `themes`/`plugins` arrays from `.wp-env.override.json`
 *   over the base (only `config`/`mappings`/`lifecycleScripts`/`env` deep-merge).
 *   So the committed `.wp-env.json` always stays publish-ready — you can never
 *   accidentally push the local sibling paths — while dev tweaks live only in
 *   the gitignored override. Other override keys (e.g. ANTHROPIC_API_KEY) are
 *   preserved untouched across switches.
 *
 * Usage:
 *   node tools/wp-env-mode.mjs dev       # or: npm run env:dev
 *   node tools/wp-env-mode.mjs publish   # or: npm run env:publish
 *   node tools/wp-env-mode.mjs status    # report the active mode (default)
 *
 * After switching you must restart the container for the new sources to mount:
 *   npm run env:start
 *
 * Exit codes:
 *   0 — switch applied / status reported
 *   2 — bad argument or unexpected error
 */

import { readFile, writeFile } from 'node:fs/promises';
import { fileURLToPath } from 'node:url';
import { dirname, resolve } from 'node:path';
import process from 'node:process';

const here = dirname(fileURLToPath(import.meta.url));
const overridePath = resolve(here, '..', '.wp-env.override.json');

// The sibling working copies mounted in dev mode. These must match the
// repo layout documented in AGENTS.md (child + parent + pediment-ai checked
// out side by side).
const DEV_THEMES = ['.', '../pediment'];
const DEV_PLUGINS = ['../pediment-ai'];

const FIELDS = ['themes', 'plugins'];

async function readOverride() {
	try {
		return JSON.parse(await readFile(overridePath, 'utf8'));
	} catch (err) {
		if (err.code === 'ENOENT') return {};
		throw new Error(`cannot parse ${overridePath}: ${err.message}`);
	}
}

async function writeOverride(obj) {
	await writeFile(overridePath, JSON.stringify(obj, null, 2) + '\n', 'utf8');
}

function currentMode(override) {
	return FIELDS.some((f) => override[f] !== undefined) ? 'dev' : 'publish';
}

function describe(mode, override) {
	if (mode === 'dev') {
		return [
			'mode: dev (local sibling working copies)',
			`  themes:  ${JSON.stringify(override.themes)}`,
			`  plugins: ${JSON.stringify(override.plugins)}`,
		].join('\n');
	}
	return 'mode: publish (committed .wp-env.json release-zip URLs)';
}

async function main() {
	const action = process.argv[2] ?? 'status';
	if (!['dev', 'publish', 'status'].includes(action)) {
		console.error(`usage: wp-env-mode.mjs <dev|publish|status> (got "${action}")`);
		process.exit(2);
	}

	const override = await readOverride();

	if (action === 'status') {
		console.log(describe(currentMode(override), override));
		process.exit(0);
	}

	const was = currentMode(override);

	if (action === 'dev') {
		override.themes = DEV_THEMES;
		override.plugins = DEV_PLUGINS;
	} else {
		// publish: remove the override refs so the base config wins; leave any
		// other keys (secrets, env config) in place.
		for (const f of FIELDS) delete override[f];
	}

	await writeOverride(override);

	const now = currentMode(override);
	console.log(describe(now, override));
	if (was === now) {
		console.log(`\nAlready in ${now} mode — no change.`);
	} else {
		console.log(`\nSwitched ${was} → ${now}. Restart to apply: npm run env:start`);
	}
	process.exit(0);
}

main().catch((err) => {
	console.error(`error: ${err.message}`);
	process.exit(2);
});
