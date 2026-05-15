import { test, expect } from '@playwright/test';

test('home page responds and is not a fatal error', async ({ page }) => {
  const response = await page.goto('/');
  expect(response?.status()).toBeLessThan(400);
  await expect(page.locator('body')).not.toContainText('There has been a critical error');
});
