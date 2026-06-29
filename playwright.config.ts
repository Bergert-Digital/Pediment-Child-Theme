import { defineConfig, devices } from '@playwright/test';
import { readFileSync } from 'node:fs';
import { resolve } from 'node:path';

// Resolve the same dev port wp-env actually bound to. `.wp-env.override.json`
// (gitignored, written by tools/ensure-port.mjs) overrides the committed
// `.wp-env.json` base — so when a workspace boots on a free port to dodge a
// collision, the e2e suite follows it instead of hammering a stale :8890.
// Paths resolve from cwd (Playwright runs from the repo root); avoid
// `import.meta.url` here — it forces this config into ESM mode and breaks
// Playwright's CJS transpile.
function wpEnvPort(): number {
  const read = (f: string): { port?: number } => {
    try {
      return JSON.parse(readFileSync(resolve(process.cwd(), f), 'utf8'));
    } catch {
      return {};
    }
  };
  return (
    Number(process.env.WP_ENV_PORT) ||
    read('.wp-env.override.json').port ||
    read('.wp-env.json').port ||
    8888
  );
}

export default defineConfig({
  testDir: './tests/e2e',
  globalSetup: './tests/e2e/global-setup.ts',
  fullyParallel: false,
  forbidOnly: !!process.env.CI,
  retries: process.env.CI ? 2 : 0,
  workers: 1,
  reporter: 'html',
  use: {
    baseURL: `http://localhost:${wpEnvPort()}`,
    trace: 'on-first-retry',
  },
  projects: [
    { name: 'chromium', use: { ...devices['Desktop Chrome'] } },
  ],
});
