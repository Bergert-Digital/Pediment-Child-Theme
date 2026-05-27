#!/usr/bin/env node
/**
 * Verifies the `.wp-env.json` parent-theme and AI-plugin refs match the latest
 * tag in each upstream repo on GitHub. Designed to be invoked both locally
 * (`node tools/check-wpenv-deps.mjs`) and in CI.
 *
 * Exit codes:
 *   0 — all refs current (or `--json` reported, see below)
 *   1 — at least one ref is outdated
 *   2 — unexpected error (bad config, GitHub API failure, etc.)
 *
 * Flags:
 *   --json   Emit a machine-readable report to stdout. Exit code still
 *            reflects current/outdated.
 *
 * Auth:
 *   Not required — the upstream repos are public. The script will still pick
 *   up GITHUB_TOKEN / `gh auth token` if available, only to raise GitHub's
 *   anonymous API rate limit.
 */

import { readFile } from 'node:fs/promises';
import { fileURLToPath } from 'node:url';
import { dirname, resolve } from 'node:path';
import { execFileSync } from 'node:child_process';
import process from 'node:process';

const here = dirname(fileURLToPath(import.meta.url));
const wpEnvPath = resolve(here, '..', '.wp-env.json');

// Each dep is pinned by an auto-zipball URL of a git tag:
//   https://github.com/<owner>/<repo>/archive/refs/tags/v<X.Y.Z>.zip
//
// We use the tag-archive form (rather than a release-asset URL) because:
//   1) Private-repo release-asset URLs require an API-flavored URL with an
//      opaque asset_id, which is unbumpable and ugly.
//   2) Both upstream release workflows force-commit the built output
//      (parent: build/ ; plugin: build/+vendor/) into the tag commit, so
//      the auto-zipball is a functioning installable theme/plugin.
// The check tool just compares the version in the URL to the latest tag
// on GitHub.
function archiveTagUrl(repo, version) {
	return `https://github.com/${repo}/archive/refs/tags/v${version}.zip`;
}

function archiveTagMatcher(repo) {
	const escaped = repo.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
	return new RegExp(`^https://github\\.com/${escaped}/archive/refs/tags/v([^"]+)\\.zip$`, 'i');
}

const DEPS = [
	{
		field: 'themes',
		label: 'parent theme',
		repo: 'Bergert-Digital/pediment',
		match: archiveTagMatcher('Bergert-Digital/pediment'),
		buildUrl: (v) => archiveTagUrl('Bergert-Digital/pediment', v),
	},
	{
		field: 'plugins',
		label: 'pediment-ai plugin',
		repo: 'Bergert-Digital/pediment-ai',
		match: archiveTagMatcher('Bergert-Digital/pediment-ai'),
		buildUrl: (v) => archiveTagUrl('Bergert-Digital/pediment-ai', v),
	},
];

const jsonMode = process.argv.includes('--json');

async function readJson(path) {
	const raw = await readFile(path, 'utf8');
	return JSON.parse(raw);
}

function resolveToken() {
	if (process.env.GITHUB_TOKEN) return process.env.GITHUB_TOKEN;
	try {
		return execFileSync('gh', ['auth', 'token'], { stdio: ['ignore', 'pipe', 'ignore'] }).toString().trim();
	} catch {
		return null;
	}
}

async function latestVersion(repo, token) {
	const headers = { 'User-Agent': 'pediment-child-theme/check-wpenv-deps', Accept: 'application/vnd.github+json' };
	if (token) headers.Authorization = `Bearer ${token}`;
	const res = await fetch(`https://api.github.com/repos/${repo}/tags?per_page=1`, { headers });
	if (!res.ok) throw new Error(`GitHub ${repo}: ${res.status} ${res.statusText}`);
	const tags = await res.json();
	if (!Array.isArray(tags) || tags.length === 0) throw new Error(`${repo}: no tags found`);
	return String(tags[0].name).replace(/^v/, '');
}

function pickPinned(wpEnv, dep) {
	const values = wpEnv[dep.field] ?? [];
	for (const v of values) {
		if (typeof v !== 'string') continue;
		const m = v.match(dep.match);
		if (m) return { value: v, version: m[1] };
	}
	return null;
}

async function main() {
	const wpEnv = await readJson(wpEnvPath);
	const token = resolveToken();
	const report = [];
	let outdated = 0;

	for (const dep of DEPS) {
		const present = pickPinned(wpEnv, dep);
		const latest = await latestVersion(dep.repo, token);
		const latestUrl = dep.buildUrl(latest);
		if (!present) {
			report.push({ field: dep.field, label: dep.label, repo: dep.repo, status: 'missing', latest, latestUrl });
			outdated++;
			continue;
		}
		const status = present.version === latest ? 'current' : 'outdated';
		if (status === 'outdated') outdated++;
		report.push({ field: dep.field, label: dep.label, repo: dep.repo, status, pinned: present.version, latest, latestUrl });
	}

	if (jsonMode) {
		process.stdout.write(JSON.stringify({ outdated, report }, null, 2) + '\n');
	} else {
		for (const r of report) {
			const tag = r.status === 'current' ? 'OK     ' : r.status === 'outdated' ? 'STALE  ' : 'MISSING';
			const detail = r.status === 'current' ? `v${r.pinned}` : `pinned=v${r.pinned ?? '∅'}, latest=v${r.latest}`;
			console.log(`${tag}  ${r.label}  (${r.repo})  ${detail}`);
		}
		if (outdated > 0) {
			console.log(`\n${outdated} dependency reference(s) out of date. Update .wp-env.json to the latest tag(s) above.`);
		} else {
			console.log('\nAll wp-env dependency refs are current.');
		}
	}

	process.exit(outdated > 0 ? 1 : 0);
}

main().catch((err) => {
	console.error(`error: ${err.message}`);
	process.exit(2);
});
