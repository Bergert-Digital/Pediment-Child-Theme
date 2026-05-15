import { Page, FrameLocator } from '@playwright/test';

/**
 * Returns a locator scope for the editor canvas — the iframe in WP 6.5+ block
 * themes, or the page itself in classic / non-iframed setups.
 *
 * Precondition: the editor must already be mounted before calling. `.count()`
 * resolves against the current DOM snapshot and does NOT wait, so calling this
 * before the `iframe[name="editor-canvas"]` exists silently falls back to
 * `page` and subsequent locators will miss the iframe content.
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
 * locator (`.starter-ai-chat`) — only once the panel can actually accept a
 * send.
 *
 * ChatPanel.send early-returns `if (!conv || !postId)`, silently dropping the
 * message (the Composer still clears its textarea). useConversation loads the
 * conversation asynchronously via REST after the post has an id, so sending
 * immediately after the panel renders is a race: the message vanishes with no
 * optimistic echo. Block until the editor has a post id and the
 * `starter-ai/chat` store holds the conversation for that post.
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

  await page.waitForFunction(
    () => {
      const wp = (window as any).wp;
      const postId = wp?.data?.select?.('core/editor')?.getCurrentPostId?.();
      const conv = wp?.data?.select?.('starter-ai/chat')?.getConversation?.();
      return !!postId && !!conv && conv.post_id === postId;
    },
    undefined,
    { timeout: 20_000 },
  );

  return panel;
}

/**
 * Waits for the live, non-deterministic streaming turn to finish. The
 * `starter-ai/chat` store sets `getStreaming()` back to null when the turn
 * completes or is cleared. Throws if the store reports an error.
 *
 * @param timeoutMs generous default to absorb multi-round agentic tool use.
 */
export async function waitForChatTurnComplete(page: Page, timeoutMs = 120_000) {
  // First make sure a turn actually started (streaming became non-null), so we
  // don't pass instantly on the initial null state before the POST fires.
  await page.waitForFunction(
    () => {
      const s = (window as any).wp?.data?.select?.('starter-ai/chat');
      return !!s && s.getStreaming() !== null;
    },
    undefined,
    { timeout: 15_000 },
  );

  await page.waitForFunction(
    () => {
      const s = (window as any).wp?.data?.select?.('starter-ai/chat');
      if (!s) return false;
      if (s.getError()) return true; // resolve; caller inspects error after
      return s.getStreaming() === null;
    },
    undefined,
    { timeout: timeoutMs },
  );

  const err = await page.evaluate(
    () => (window as any).wp?.data?.select?.('starter-ai/chat')?.getError() ?? null,
  );
  if (err) throw new Error(`AI chat turn errored: ${err}`);
}

/**
 * Clicks Publish through the WP 6.5 publish flow and returns an absolute,
 * front-end-resolvable permalink for the published page.
 *
 * The URL is built as `<origin>/?page_id=<id>` from the editor store rather
 * than scraped from a UI link. Scraping was unreliable: `getByRole('link',
 * { name: /view page/i })` matched the admin "Pages" menu link, and slug-path
 * permalinks 404 at Apache under the wp-env default plain-permalink structure.
 * `?page_id=<id>` resolves regardless of permalink structure (it is canonical
 * under plain permalinks and 301s to the pretty URL when one is configured),
 * matching the e2e URL convention used elsewhere in this project.
 */
export async function publishAndGetPermalink(page: Page): Promise<string> {
  // Top-bar Publish button.
  await page.getByRole('button', { name: /^Publish$/i }).first().click();

  // Publish confirmation panel.
  const panel = page.locator('.editor-post-publish-panel');
  await panel.waitFor({ state: 'visible', timeout: 15_000 });
  await panel.getByRole('button', { name: /^Publish$/i }).first().click();

  // Wait until the editor reports the post as actually published, so the post
  // id is assigned and stable before we build the URL.
  await page.waitForFunction(
    () => {
      const ed = (window as any).wp?.data?.select?.('core/editor');
      return !!ed && !!ed.getCurrentPostId() && ed.getEditedPostAttribute('status') === 'publish';
    },
    undefined,
    { timeout: 15_000 },
  );

  const id = await page.evaluate(
    () => (window as any).wp.data.select('core/editor').getCurrentPostId() as number,
  );
  if (!id) throw new Error('Published but could not read post id from the editor store');

  // page.url() is the editor admin URL on the same origin as the front-end.
  return new URL(`/?page_id=${id}`, page.url()).toString();
}
