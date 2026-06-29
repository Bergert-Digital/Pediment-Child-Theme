#!/usr/bin/env node
/**
 * One-shot dev-env bootstrap. Boots wp-env, activates this child theme,
 * and seeds content so a fresh clone goes from `npm install` to a working
 * page in a single step. It seeds the client's committed patterns when
 * `patterns/` holds any `*.php` (via `wp pediment-child seed`), otherwise the
 * starter showcase (via `wp pediment-child seed-demo`).
 *
 * Why this script exists:
 *   - `wp-env start` installs themes/plugins but doesn't activate them.
 *     Out of the box, WP falls back to `twentytwentyfive`, which means the
 *     child theme's seed commands never load — so a new contributor running
 *     just `env:start` sees no content and gets "pediment-child is not a
 *     registered wp command" if they try to seed.
 *   - The child theme's slug is the host directory's basename (that's how
 *     wp-env names the mount). Hard-coding it in package.json would break
 *     for anyone who clones into a differently-named folder, so we resolve
 *     it dynamically here.
 *
 * Idempotent: re-running is safe (theme activate + seed are both no-ops on
 * already-active / already-seeded state).
 *
 * Exit codes:
 *   0 — full flow completed
 *   non-zero — propagated from whichever child step failed
 */

import { execFileSync } from 'node:child_process';
import { readdirSync, existsSync } from 'node:fs';
import { basename } from 'node:path';
import process from 'node:process';

const themeSlug = basename(process.cwd());
const hasPatterns = existsSync('patterns') &&
	readdirSync('patterns').some((f) => f.endsWith('.php'));

const run = (label, cmd, args) => {
	console.log(`\n› ${label}`);
	execFileSync(cmd, args, { stdio: 'inherit' });
};

try {
	run('wp-env start', 'npx', ['wp-env', 'start']);
	run(
		`activate child theme (${themeSlug})`,
		'npx',
		['wp-env', 'run', 'cli', 'wp', 'theme', 'activate', themeSlug]
	);
	run(
		hasPatterns ? 'seed client content' : 'seed demo content',
		'npx',
		['wp-env', 'run', 'cli', 'wp', 'pediment-child', hasPatterns ? 'seed' : 'seed-demo']
	);
	console.log('\n✔ env ready.');
} catch (err) {
	process.exit(err.status ?? 1);
}
