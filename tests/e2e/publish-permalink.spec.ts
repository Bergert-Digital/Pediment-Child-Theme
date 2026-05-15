import { test, expect } from '@playwright/test';
import { execSync } from 'node:child_process';
import { login, openNewPage, publishAndGetPermalink } from './utils';

// Non-live regression guard for publishAndGetPermalink: the returned URL must
// resolve on the front-end regardless of the site's permalink structure
// (the wp-env default is plain permalinks, where slug-path URLs 404 at Apache).
// Needs the wp-env at :8890 like smoke.spec.ts; spends zero AI tokens.
test('publishAndGetPermalink returns a front-end-resolvable URL', async ({ page }) => {
  test.setTimeout(90_000);

  await login(page);
  await openNewPage(page, `Permalink Helper E2E ${Date.now()}`);

  const permalink = await publishAndGetPermalink(page);
  const pageId = await page.evaluate(
    () => (window as any).wp.data.select('core/editor').getCurrentPostId() as number,
  );

  try {
    const response = await page.goto(permalink);
    expect(response?.status(), `front-end status for ${permalink}`).toBeLessThan(400);
    await expect(page.locator('body')).not.toContainText(
      'The requested URL was not found on this server',
    );
  } finally {
    if (pageId) {
      try {
        execSync(`npx wp-env run cli wp post delete ${pageId} --force`, { stdio: 'ignore' });
      } catch {
        /* best-effort cleanup; this regression page is not for manual review */
      }
    }
  }
});
