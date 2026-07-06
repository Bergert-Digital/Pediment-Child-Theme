#!/usr/bin/env node
/**
 * Ensures this workspace's wp-env binds to host ports that aren't already
 * taken by another checkout. The committed `.wp-env.json` pins :8890/:8891,
 * which collides the moment two Conductor workspaces (or any two clones) try
 * to boot at once — `wp-env start` then dies with "port is already allocated".
 *
 * Fix: persist a free `port`/`testsPort` pair into `.wp-env.override.json`
 * (gitignored, per-machine). wp-env's merge replaces the base scalars with the
 * override's, so the chosen ports win for `start`, `run`, `stop`, and the
 * Playwright config alike — without ever touching the push-ready base config.
 *
 * Assign-once, then stable: if the override already names a port that's still
 * free we keep it, so a workspace's URL doesn't churn between runs. We only
 * pick a new port when none is set or the old one is now occupied (e.g. by the
 * workspace's own already-running container).
 *
 * CI is exempt: runners are single-tenant, so there's nothing to collide with.
 * When `CI` is set we return the base `.wp-env.json` ports and write nothing —
 * keeping the run byte-identical to the committed config.
 *
 * Importable: `ensurePorts()` returns `{ port, testsPort }` (plus `ci: true`
 * under CI). As a CLI it writes the override and prints the development URL.
 *
 * Exit codes:
 *   0 — ports ensured (printed as JSON-ish summary)
 *   2 — unexpected error (bad override JSON, etc.)
 */

import { readFile, writeFile } from 'node:fs/promises';
import { fileURLToPath } from 'node:url';
import { dirname, resolve } from 'node:path';
import net from 'node:net';
import process from 'node:process';

const here = dirname(fileURLToPath(import.meta.url));
const overridePath = resolve(here, '..', '.wp-env.override.json');
const basePath = resolve(here, '..', '.wp-env.json');

/** Resolve true if nothing is currently bound to `port` on loopback. */
function isFree(port) {
	return new Promise((res) => {
		const srv = net.createServer();
		srv.once('error', () => res(false));
		srv.listen(port, '127.0.0.1', () => srv.close(() => res(true)));
	});
}

/** Ask the OS for an unused ephemeral port. */
function pickFree() {
	return new Promise((res, rej) => {
		const srv = net.createServer();
		srv.once('error', rej);
		srv.listen(0, '127.0.0.1', () => {
			const { port } = srv.address();
			srv.close(() => res(port));
		});
	});
}

async function readOverride() {
	try {
		return JSON.parse(await readFile(overridePath, 'utf8'));
	} catch (err) {
		if (err.code === 'ENOENT') return {};
		throw new Error(`cannot parse ${overridePath}: ${err.message}`);
	}
}

/**
 * Keep `current` if it's a valid, still-free port; otherwise allocate a new
 * one, avoiding anything in `taken` so the dev and tests ports never coincide.
 */
async function ensureOne(current, taken) {
	if (Number.isInteger(current) && !taken.has(current) && (await isFree(current))) {
		return current;
	}
	let next;
	do {
		next = await pickFree();
	} while (taken.has(next));
	return next;
}

async function readBasePorts() {
	try {
		const base = JSON.parse(await readFile(basePath, 'utf8'));
		return { port: base.port ?? 8888, testsPort: base.testsPort ?? 8889 };
	} catch {
		return { port: 8888, testsPort: 8889 };
	}
}

export async function ensurePorts() {
	// CI runners are single-tenant — there's no sibling workspace to collide
	// with — so keep the deterministic ports from .wp-env.json and never write
	// an override. Randomizing in CI only adds a moving part for zero benefit
	// (and keeps the run identical to the base config on `development`).
	if (process.env.CI) {
		return { ...(await readBasePorts()), ci: true };
	}

	const override = await readOverride();
	const taken = new Set();

	const port = await ensureOne(override.port, taken);
	taken.add(port);
	const testsPort = await ensureOne(override.testsPort, taken);

	if (override.port !== port || override.testsPort !== testsPort) {
		override.port = port;
		override.testsPort = testsPort;
		await writeFile(overridePath, JSON.stringify(override, null, 2) + '\n', 'utf8');
	}
	return { port, testsPort };
}

const invokedDirectly =
	process.argv[1] && resolve(process.argv[1]) === fileURLToPath(import.meta.url);

if (invokedDirectly) {
	ensurePorts()
		.then(({ port, testsPort, ci }) => {
			const tag = ci ? ' (CI: base .wp-env.json ports)' : '';
			console.log(`wp-env ports: dev :${port}  tests :${testsPort}${tag}`);
			console.log(`  → http://localhost:${port}`);
		})
		.catch((err) => {
			console.error(`error: ${err.message}`);
			process.exit(2);
		});
}
