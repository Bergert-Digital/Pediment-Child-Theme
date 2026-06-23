#!/usr/bin/env node
/**
 * Opt-in: re-point wp-env at LOCAL checkouts of the parent theme (`pediment`)
 * and AI plugin (`pediment-ai`) instead of the official release zips pinned in
 * `.wp-env.json`. Writes a gitignored `.wp-env.override.json` that wp-env
 * deep-merges over the committed config (arrays are replaced wholesale, so we
 * emit each full array with only the matching dep swapped for its local path).
 *
 * Why this is opt-in and guarded: the committed `.wp-env.json` pins *released*
 * versions on purpose — that's what CI and every other contributor test
 * against. Mounting a teammate's machine paths, or a cloud workspace's
 * non-existent ones, would break them. So this is a no-op unless ALL hold:
 *   - PEDIMENT_LINK_LOCAL is truthy (set it in your personal, gitignored
 *     .conductor/settings.local.toml [environment_variables]); and
 *   - not running in a cloud workspace (CONDUCTOR_IS_LOCAL !== '0').
 *
 * Dep paths resolve, in order:
 *   1. PEDIMENT_PATH / PEDIMENT_AI_PATH env vars (explicit override), else
 *   2. sibling convention relative to the repo root checkout:
 *        <root>/../pediment  and  <root>/../pediment-ai
 * Only deps whose directory actually exists are mounted; a missing one keeps
 * its pinned release (and is reported). When opted in but nothing resolves,
 * the file is left untouched.
 *
 * This script deliberately does NOT build the local deps — that mutates
 * external repos and would race across parallel workspaces. Build the parent /
 * plugin in their own repos when you change them.
 *
 * Idempotent: only rewrites `.wp-env.override.json` when the content changes.
 *
 * Exit codes:
 *   0 — applied, or intentionally skipped (not opted in / cloud / nothing local)
 *   2 — unexpected error (unreadable base config, etc.)
 */

import { readFileSync, writeFileSync, existsSync, statSync } from 'node:fs';
import { fileURLToPath } from 'node:url';
import { dirname, resolve } from 'node:path';
import { execFileSync } from 'node:child_process';
import process from 'node:process';

const here = dirname(fileURLToPath(import.meta.url));
const wpEnvPath = resolve(here, '..', '.wp-env.json');
const overridePath = resolve(here, '..', '.wp-env.override.json');

// Each local-linkable dep: which `.wp-env.json` array it lives in, how to spot
// its pinned release URL, and how to find a local checkout.
const DEPS = [
	{
		field: 'themes',
		label: 'parent theme (pediment)',
		match: /Bergert-Digital\/pediment\/releases\//i,
		envPath: 'PEDIMENT_PATH',
		sibling: 'pediment',
	},
	{
		field: 'plugins',
		label: 'AI plugin (pediment-ai)',
		match: /Bergert-Digital\/pediment-ai\/releases\//i,
		envPath: 'PEDIMENT_AI_PATH',
		sibling: 'pediment-ai',
	},
];

const isTruthy = (v) => v != null && !['', '0', 'false', 'no', 'off'].includes(String(v).toLowerCase());
const log = (msg) => console.log(`[link-local-deps] ${msg}`);

// Root checkout: the directory that holds the shared `.git`. For a Conductor
// worktree, that's the repo root whose siblings are the other Pediment repos.
function repoRoot() {
	if (process.env.CONDUCTOR_ROOT_PATH) return process.env.CONDUCTOR_ROOT_PATH;
	try {
		const gitDir = execFileSync('git', ['rev-parse', '--path-format=absolute', '--git-common-dir'], {
			cwd: here,
			stdio: ['ignore', 'pipe', 'ignore'],
		})
			.toString()
			.trim();
		return dirname(gitDir);
	} catch {
		return resolve(here, '..');
	}
}

function isDir(p) {
	try {
		return statSync(p).isDirectory();
	} catch {
		return false;
	}
}

// Resolve a local checkout for a dep, or null. Explicit env path wins; else the
// sibling-of-root convention. Returns null when nothing usable exists.
function localPathFor(dep, root) {
	const explicit = process.env[dep.envPath];
	if (explicit) {
		const abs = resolve(explicit);
		if (isDir(abs)) return abs;
		log(`${dep.envPath}="${explicit}" is not a directory — ignoring.`);
		return null;
	}
	const sib = resolve(root, '..', dep.sibling);
	return isDir(sib) ? sib : null;
}

function main() {
	// Gate 1: explicit opt-in.
	if (!isTruthy(process.env.PEDIMENT_LINK_LOCAL)) {
		log('PEDIMENT_LINK_LOCAL not set — using pinned releases from .wp-env.json.');
		return 0;
	}
	// Gate 2: never in a cloud workspace (machine-local paths won't exist there).
	if (process.env.CONDUCTOR_IS_LOCAL === '0') {
		log('cloud workspace (CONDUCTOR_IS_LOCAL=0) — skipping local link.');
		return 0;
	}

	let base;
	try {
		base = JSON.parse(readFileSync(wpEnvPath, 'utf8'));
	} catch (err) {
		log(`could not read ${wpEnvPath}: ${err.message}`);
		return 2;
	}

	const root = repoRoot();
	const override = {};
	const mounted = [];
	const fellBack = [];

	for (const dep of DEPS) {
		const arr = Array.isArray(base[dep.field]) ? base[dep.field] : [];
		const local = localPathFor(dep, root);
		if (!local) {
			if (arr.some((v) => typeof v === 'string' && dep.match.test(v))) fellBack.push(dep.label);
			continue;
		}
		// Replace only the entry whose URL matches this dep; keep "." and others.
		const next = arr.map((v) => (typeof v === 'string' && dep.match.test(v) ? local : v));
		override[dep.field] = next;
		mounted.push(`${dep.label} → ${local}`);
	}

	if (mounted.length === 0) {
		log('opted in, but no local checkouts found — leaving override untouched, using pinned releases.');
		if (fellBack.length) log(`looked for siblings of ${root} (or *_PATH env vars).`);
		return 0;
	}

	const next = JSON.stringify(override, null, '\t') + '\n';
	const prev = existsSync(overridePath) ? readFileSync(overridePath, 'utf8') : null;
	if (prev === next) {
		log('override already current.');
	} else {
		writeFileSync(overridePath, next);
		log(`wrote .wp-env.override.json`);
	}
	for (const m of mounted) log(`  mounted ${m}`);
	for (const f of fellBack) log(`  pinned  ${f} (no local checkout)`);
	log('run/restart the env to apply (npm run env:setup).');
	return 0;
}

process.exit(main());
