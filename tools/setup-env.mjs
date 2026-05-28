#!/usr/bin/env node
/**
 * One-shot dev-env bootstrap. Boots wp-env, activates this child theme,
 * and runs the parent's seed command so a fresh clone goes from `npm install`
 * to a working demo page in a single step.
 *
 * Why this script exists:
 *   - `wp-env start` installs themes/plugins but doesn't activate them.
 *     Out of the box, WP falls back to `twentytwentyfive`, which means the
 *     parent's `inc/seed.php` (and its `wp pediment seed` CLI command) never
 *     loads — so a new contributor running just `env:start` sees no demo
 *     and gets "pediment is not a registered wp command" if they try to seed.
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
import { basename } from 'node:path';
import process from 'node:process';

const themeSlug = basename(process.cwd());

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
		'seed demo content',
		'npx',
		['wp-env', 'run', 'cli', 'wp', 'pediment', 'seed']
	);
	console.log('\n✔ env ready.');
} catch (err) {
	process.exit(err.status ?? 1);
}
