import { execSync } from 'node:child_process';

/**
 * Normalize the wp-env site to plain permalinks before the e2e suite runs.
 *
 * The block editor saves through the REST API at `rest_url()`. With pretty
 * permalinks that URL is `/wp-json/...`, which only resolves once Apache's
 * rewrite rules have been flushed to `.htaccess`. The active theme's bootstrap
 * can switch the site to pretty permalinks WITHOUT flushing — leaving
 * `/wp-json/` 404ing in the wp-env container, so every editor save fails and
 * publishes never persist (the post stays `auto-draft`). This surfaced only on
 * CI, where the bootstrap had run; locally the site stayed on the wp-env plain
 * default, so REST went via `/index.php?rest_route=...` and worked.
 *
 * Plain permalinks route REST via `?rest_route=...`, which needs no rewrite
 * rules at all — so forcing the structure the suite was written for makes the
 * endpoint resolvable regardless of what the theme did. `--hard` flushes too,
 * so the change is effective immediately.
 */
export default function globalSetup(): void {
  try {
    execSync("npx wp-env run cli wp rewrite structure '' --hard", { stdio: 'inherit' });
  } catch (err) {
    // Non-fatal: if wp-env isn't reachable the tests will fail with a clearer
    // error than a swallowed setup crash.
    console.warn('[e2e global-setup] could not normalize permalinks:', (err as Error).message);
  }
}
