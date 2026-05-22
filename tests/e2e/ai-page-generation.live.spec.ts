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
  // Worst case: ~195s inside waitForChatTurnComplete plus ~95s of login/open/
  // publish/FE waits ≈ 290s, so 360s keeps comfortable slack on a slow local
  // Docker env. Test is manual-only, so a generous timeout costs nothing.
  test.setTimeout(360_000);

  // Diagnostic capture from the very start: if the block editor hits its React
  // error boundary while applying AI-emitted blocks, the useful signal is the
  // uncaught exception / console.error, not a downstream "0 blocks". Collect
  // both for the whole test so a crash fails with an actionable stack.
  const editorJsErrors: string[] = [];
  page.on('pageerror', (err) => {
    editorJsErrors.push(`[pageerror] ${err.stack ?? err.message}`);
  });
  page.on('console', (msg) => {
    if (msg.type() === 'error') editorJsErrors.push(`[console.error] ${msg.text()}`);
  });

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

  // Explicit editor-crash detection. A non-deterministic AI emit can drive
  // Gutenberg into its React error boundary; without this check the test would
  // fail later with an opaque "expected >= 4, received 0". Fail here instead
  // with the captured JS errors so the product root cause is actionable.
  const crashBanner = page.getByText(/The editor has encountered an unexpected error/i);
  if (await crashBanner.isVisible().catch(() => false)) {
    throw new Error(
      'Gutenberg crashed after the AI turn (React error boundary). ' +
        'The AI-emitted block tree likely broke a block edit component.\n' +
        'Captured browser JS errors:\n' +
        (editorJsErrors.length ? editorJsErrors.join('\n---\n') : '(none captured)'),
    );
  }

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

  // The AI used the design system, not just core blocks. Deliberately
  // permissive (>= 2, not >= 3): live runs vary in richness — some compose
  // 6+ starter sections, others a thinner hero+faq page leaning on core
  // blocks. >= 2 distinct starter sections + the >= 4-block editor guard +
  // the text floor below still rejects a crash, an empty page, or a
  // single-paragraph / pure-core response, while tolerating model variance.
  let renderedSections = 0;
  for (const cls of SECTION_CLASSES) {
    if (await page.locator(`.${cls}`).first().isVisible().catch(() => false)) {
      renderedSections += 1;
    }
  }
  expect(renderedSections, 'distinct rendered starter section blocks').toBeGreaterThanOrEqual(2);

  // Page is substantial (a real landing page, even if it uses core blocks).
  const textLen = (await body.innerText()).trim().length;
  expect(textLen, 'visible body text length').toBeGreaterThan(600);

  // No browser console errors during the visitor load.
  expect(consoleErrors, `console errors: ${consoleErrors.join(' | ')}`).toHaveLength(0);
});
