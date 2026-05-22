# Live AI Page-Generation E2E Test — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** A manual-only Playwright e2e test that drives the real AI Chat panel with "create a landing page for a local bike mechanic", lets the live Claude model compose a page, publishes it, and asserts it renders cleanly for a visitor — leaving the page alive for manual inspection.

**Architecture:** New standalone spec in the child-theme e2e suite, gated behind `RUN_LIVE_AI=1` so it never runs in the default `npm run e2e` or CI. Runs against the existing child-theme wp-env at `http://localhost:8890`, which already has a live `ANTHROPIC_API_KEY` and runs `pediment-ai` in live mode. Completion of the non-deterministic streaming turn is detected via the authoritative `pediment-ai/chat` Redux store selector. No teardown — the published page persists.

**Tech Stack:** Playwright (`@playwright/test` ^1.45), WordPress 6.5 block editor, `@wordpress/data` store `pediment-ai/chat`.

---

## Spec reference

`docs/superpowers/specs/2026-05-15-ai-page-generation-live-e2e-design.md`

## Pinned facts (verified from source)

- Chat store name: `pediment-ai/chat`. Selector `getStreaming()` returns the streaming `ChatMessage | null` (null when the turn is finished/cleared). Selector `getError()` returns `string | null`. Source: `pediment-ai/editor/chat/store.ts`.
- Live provider is active unless `STARTER_AI_MOCK` constant or `mock_mode` option is set; the child-theme env sets neither and supplies `ANTHROPIC_API_KEY` via `.wp-env.override.json`. Source: `pediment-ai/src/Bootstrap.php`.
- Front-end render classes are `starter-<block>` (NOT `wp-block-pediment-*`): `starter-hero`, `starter-cta`, `starter-faq`, `starter-prose`, `starter-pull-quote`, `starter-stat`, `starter-contact-form`, `starter-blog-index`. Source: `pediment/src/blocks/*/render.php` and `pediment/tests/e2e/editor-blocks.spec.ts`.
- Admin credentials: `admin` / `password`. Editor canvas is an iframe `iframe[name="editor-canvas"]` in WP 6.5. Source: `pediment-ai/tests/e2e/utils.ts`.
- Child-theme `tests/e2e/` has only `smoke.spec.ts` and **no `utils.ts`** — helpers are created locally by this plan. Base URL `http://localhost:8890` is set in `pediment-child-theme/playwright.config.ts`.

## File Structure

- Create: `pediment-child-theme/tests/e2e/utils.ts` — shared local helpers (`canvas`, `login`, `openNewPage`, `openAIChatPanel`, `waitForChatTurnComplete`, `publishAndGetPermalink`). One responsibility: reusable editor/chat e2e primitives for the child-theme suite.
- Create: `pediment-child-theme/tests/e2e/ai-page-generation.live.spec.ts` — the single gated live test. One responsibility: the end-to-end live page-generation scenario + assertions.

No existing files are modified.

---

### Task 1: Local e2e helpers for the child-theme suite

**Files:**
- Create: `pediment-child-theme/tests/e2e/utils.ts`
- Test: exercised via Task 2's spec (helpers have no standalone unit test; they are e2e glue verified by the live run in Task 3).

- [ ] **Step 1: Write the helpers file**

Create `pediment-child-theme/tests/e2e/utils.ts` with exactly this content:

```ts
import { Page, FrameLocator } from '@playwright/test';

/**
 * Returns a locator scope for the editor canvas — the iframe in WP 6.5+ block
 * themes, or the page itself in classic / non-iframed setups.
 */
export async function canvas(page: Page): Promise<FrameLocator | Page> {
  return (await page.locator('iframe[name="editor-canvas"]').count())
    ? page.frameLocator('iframe[name="editor-canvas"]')
    : page;
}

export async function login(page: Page) {
  await page.goto('/wp-login.php');
  await page.fill('input#user_login', 'admin');
  await page.fill('input#user_pass', 'password');
  await page.click('input#wp-submit');
  await page.waitForURL(/wp-admin/);
}

export async function openNewPage(page: Page, title: string) {
  await page.goto('/wp-admin/post-new.php?post_type=page');
  const closeBtn = page.getByRole('button', { name: /close dialog|close/i });
  if (await closeBtn.count()) { await closeBtn.first().click().catch(() => {}); }
  await page
    .locator('iframe[name="editor-canvas"], .editor-post-title__input')
    .first()
    .waitFor({ timeout: 20_000 });
  const scope = await canvas(page);
  const titleField = scope
    .locator('.editor-post-title__input, [aria-label*="Add title" i], [placeholder*="Add title" i]')
    .first();
  await titleField.waitFor({ state: 'visible', timeout: 20_000 });
  await titleField.fill(title);
}

async function openSidebarTab(page: Page, tab: 'edit-post/document' | 'edit-post/block') {
  await page.evaluate((target) => {
    const wp = (window as any).wp;
    const dispatch = wp?.data?.dispatch?.('core/edit-post') ?? wp?.data?.dispatch?.('core/editor');
    dispatch?.openGeneralSidebar?.(target);
  }, tab);
}

/**
 * Opens the Document sidebar, expands the "AI Chat" panel, and returns its
 * locator (`.starter-ai-chat`).
 */
export async function openAIChatPanel(page: Page) {
  await openSidebarTab(page, 'edit-post/document');
  const toggle = page.getByRole('button', { name: /^AI Chat$/i }).first();
  await toggle.waitFor({ state: 'visible', timeout: 10_000 });
  if ((await toggle.getAttribute('aria-expanded')) === 'false') {
    await toggle.click();
  }
  const panel = page.locator('.starter-ai-chat').first();
  await panel.waitFor({ state: 'visible', timeout: 10_000 });
  return panel;
}

/**
 * Waits for the live, non-deterministic streaming turn to finish. The
 * `pediment-ai/chat` store sets `getStreaming()` back to null when the turn
 * completes or is cleared. Throws if the store reports an error.
 *
 * @param timeoutMs generous default to absorb multi-round agentic tool use.
 */
export async function waitForChatTurnComplete(page: Page, timeoutMs = 120_000) {
  // First make sure a turn actually started (streaming became non-null), so we
  // don't pass instantly on the initial null state before the POST fires.
  await page.waitForFunction(
    () => {
      const s = (window as any).wp?.data?.select?.('pediment-ai/chat');
      return !!s && s.getStreaming() !== null;
    },
    undefined,
    { timeout: 15_000 },
  );

  await page.waitForFunction(
    () => {
      const s = (window as any).wp?.data?.select?.('pediment-ai/chat');
      if (!s) return false;
      if (s.getError()) return true; // resolve; caller inspects error after
      return s.getStreaming() === null;
    },
    undefined,
    { timeout: timeoutMs },
  );

  const err = await page.evaluate(
    () => (window as any).wp?.data?.select?.('pediment-ai/chat')?.getError() ?? null,
  );
  if (err) throw new Error(`AI chat turn errored: ${err}`);
}

/**
 * Clicks Publish through the WP 6.5 publish flow and returns the public
 * permalink of the published page.
 */
export async function publishAndGetPermalink(page: Page): Promise<string> {
  // Top-bar Publish button.
  await page.getByRole('button', { name: /^Publish$/i }).first().click();

  // Publish confirmation panel.
  const panel = page.locator('.editor-post-publish-panel');
  await panel.waitFor({ state: 'visible', timeout: 15_000 });
  await panel.getByRole('button', { name: /^Publish$/i }).first().click();

  // Post-publish state exposes a "View Page"/"View page" link to the permalink.
  const viewLink = page.getByRole('link', { name: /view page/i }).first();
  await viewLink.waitFor({ state: 'visible', timeout: 15_000 });
  const href = await viewLink.getAttribute('href');
  if (!href) throw new Error('Published but could not read permalink from View Page link');
  return href;
}
```

- [ ] **Step 2: Type-check the helpers compile**

Run: `cd /Users/jonas/Entwicklung/pediment-child-theme && npx tsc --noEmit tests/e2e/utils.ts --moduleResolution node --target es2020 --skipLibCheck`

Expected: no output / exit 0 (or only "Cannot find module '@playwright/test' types" if not installed — acceptable; the real gate is the Playwright run in Task 3). If `@playwright/test` resolves, expect zero errors.

- [ ] **Step 3: Commit**

```bash
cd /Users/jonas/Entwicklung/pediment-child-theme
git add tests/e2e/utils.ts
git commit -m "test(e2e): local helpers for child-theme suite (chat turn wait, publish)

Co-Authored-By: Claude Opus 4.7 (1M context) <noreply@anthropic.com>"
```

---

### Task 2: The gated live page-generation spec

**Files:**
- Create: `pediment-child-theme/tests/e2e/ai-page-generation.live.spec.ts`
- Test: this file IS the test.

- [ ] **Step 1: Write the spec file**

Create `pediment-child-theme/tests/e2e/ai-page-generation.live.spec.ts` with exactly this content:

```ts
import { test, expect } from '@playwright/test';
import {
  login,
  openNewPage,
  openAIChatPanel,
  canvas,
  waitForChatTurnComplete,
  publishAndGetPermalink,
} from './utils';

// Manual-only: spends real Anthropic tokens against the live model. Never runs
// in the default `npm run e2e` or CI. Run with:
//   RUN_LIVE_AI=1 npx playwright test ai-page-generation.live
test.skip(
  !process.env.RUN_LIVE_AI,
  'Live AI test — set RUN_LIVE_AI=1 to run (spends Anthropic tokens)',
);

// Front-end render classes emitted by starter blocks (NOT wp-block-pediment-*).
const SECTION_CLASSES = [
  'starter-hero',
  'starter-cta',
  'starter-faq',
  'starter-prose',
  'starter-pull-quote',
  'starter-stat',
  'starter-contact-form',
  'starter-blog-index',
];

test('live model composes a bike-mechanic landing page that renders for a visitor', async ({
  page,
}) => {
  // A live, multi-round agentic turn plus publish + front-end load needs headroom.
  test.setTimeout(240_000);

  await login(page);
  await openNewPage(page, `AI Live: Bike Mechanic ${new Date().toISOString()}`);

  const sidebar = await openAIChatPanel(page);

  await sidebar.locator('textarea').fill('create a landing page for a local bike mechanic');
  await sidebar.getByRole('button', { name: /^send$/i }).click();

  // Optimistic UI echoes the user's message immediately.
  await expect(
    sidebar.getByText('create a landing page for a local bike mechanic'),
  ).toBeVisible({ timeout: 10_000 });

  // Wait for the non-deterministic streaming turn to finish (throws on error).
  await waitForChatTurnComplete(page, 180_000);

  // Editor sanity guard: a real page was composed, not a single paragraph.
  const editor = await canvas(page);
  const topLevelBlocks = editor.locator('.block-editor-block-list__layout > [data-block]');
  await expect
    .poll(async () => topLevelBlocks.count(), { timeout: 15_000 })
    .toBeGreaterThanOrEqual(4);

  // Publish; keep the page alive (no teardown) for manual inspection.
  const permalink = await publishAndGetPermalink(page);
  // Surface the permalink in the Playwright report for manual review.
  // eslint-disable-next-line no-console
  console.log(`\n[ai-page-generation.live] Published page: ${permalink}\n`);

  // ---- Primary assertion: renders cleanly for a visitor ----
  const consoleErrors: string[] = [];
  page.on('console', (msg) => {
    if (msg.type() === 'error') consoleErrors.push(msg.text());
  });

  const response = await page.goto(permalink);
  expect(response?.status(), 'front-end HTTP status').toBeLessThan(400);

  const body = page.locator('body');
  await expect(body).not.toContainText('There has been a critical error');
  await expect(body).not.toContainText(/Fatal error|Parse error|Warning:|Notice:|Deprecated:/);

  // Multiple distinct section blocks rendered visibly.
  let renderedSections = 0;
  for (const cls of SECTION_CLASSES) {
    if (await page.locator(`.${cls}`).first().isVisible().catch(() => false)) {
      renderedSections += 1;
    }
  }
  expect(renderedSections, 'distinct rendered starter section blocks').toBeGreaterThanOrEqual(3);

  // Page is non-trivial: meaningful visible text.
  const textLen = (await body.innerText()).trim().length;
  expect(textLen, 'visible body text length').toBeGreaterThan(400);

  // No browser console errors during the visitor load.
  expect(consoleErrors, `console errors: ${consoleErrors.join(' | ')}`).toHaveLength(0);
});
```

- [ ] **Step 2: Verify the test is skipped by default (no tokens spent)**

Run: `cd /Users/jonas/Entwicklung/pediment-child-theme && npx playwright test ai-page-generation.live --list && npx playwright test ai-page-generation.live`

Expected: the test is listed, then the run reports it **skipped** (1 skipped, 0 passed) because `RUN_LIVE_AI` is unset. No network/model call occurs.

- [ ] **Step 3: Commit**

```bash
cd /Users/jonas/Entwicklung/pediment-child-theme
git add tests/e2e/ai-page-generation.live.spec.ts
git commit -m "test(e2e): manual-only live AI page-generation test

Drives the AI Chat panel with a bike-mechanic prompt, publishes the
live-composed page, and asserts it renders for a visitor. Gated behind
RUN_LIVE_AI=1; the page is intentionally left alive for manual review.

Co-Authored-By: Claude Opus 4.7 (1M context) <noreply@anthropic.com>"
```

---

### Task 3: Manual live verification (human-in-the-loop)

**Files:** none (verification only).

This task is **not** automated and **not** run by a subagent. It requires the live env and spends tokens, so the human runs it.

- [ ] **Step 1: Confirm the child-theme wp-env is up at :8890**

Run: `curl -s -o /dev/null -w "%{http_code}" http://localhost:8890`
Expected: `200` (or `30x`). If it fails, the env is down — start/repair the child-theme wp-env before continuing (do not start wp-env from `pediment-ai` or `pediment`).

- [ ] **Step 2: Run the live test**

Run: `cd /Users/jonas/Entwicklung/pediment-child-theme && RUN_LIVE_AI=1 npx playwright test ai-page-generation.live --reporter=list`

Expected: `1 passed`. The console output contains a line:
`[ai-page-generation.live] Published page: http://localhost:8890/...`

- [ ] **Step 3: Manually inspect the generated page**

Open the logged permalink in a browser. Confirm by eye that it is a plausible bike-mechanic landing page with multiple sections. The page is intentionally persisted; prune old test pages manually when desired.

- [ ] **Step 4 (on failure): triage, do not auto-fix blindly**

If it fails on `waitForChatTurnComplete` timeout, the live turn legitimately ran long or errored — inspect the Playwright trace and the chat panel error. If it fails on the editor guard (<4 blocks), the model may have streamed prose instead of calling `emit_page`; re-run once (non-determinism) before changing assertions. Only then adjust thresholds, and re-confirm with the human.

---

## Self-Review

**1. Spec coverage:**
- Live (not mock) → Task 2 runs against live env, no mock toggle. ✓
- Env `:8890`, reuse existing key → Tasks 1/3, no new secrets. ✓
- Location & gating (`ai-page-generation.live.spec.ts`, `RUN_LIVE_AI` skip, manual-only) → Task 2 Step 1, verified Task 2 Step 2. ✓
- Local helpers ported (no cross-repo import) → Task 1. ✓
- Flow steps 1–6 (login→newpage→panel→prompt→wait→guard→publish→front-end asserts) → Task 2 Step 1. ✓
- Completion via authoritative store selector → `waitForChatTurnComplete` using `pediment-ai/chat` `getStreaming`/`getError`. ✓
- Persistence / no teardown / log permalink → Task 2 Step 1 (no cleanup, `console.log`). ✓
- Out-of-scope items (no separator/keyword/strict matrix) → not asserted. ✓
- "Pin during planning" details → resolved in "Pinned facts". ✓

**2. Placeholder scan:** No TBD/TODO; all code blocks complete; commands have expected output. ✓

**3. Type consistency:** Helper names (`canvas`, `login`, `openNewPage`, `openAIChatPanel`, `waitForChatTurnComplete`, `publishAndGetPermalink`) are defined in Task 1 and imported with identical names in Task 2. Store selectors `getStreaming`/`getError` match `pediment-ai/chat` source. ✓
