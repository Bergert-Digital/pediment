import { test, expect } from '@playwright/test';

test.describe('front page', () => {
  test('renders header and footer', async ({ page }) => {
    await page.goto('/');
    await expect(page.locator('header').first()).toBeVisible();
    await expect(page.locator('footer').first()).toBeVisible();
  });

  test('404 page renders for unknown URL', async ({ page }) => {
    const response = await page.goto('/this-page-does-not-exist-12345');
    expect(response?.status()).toBe(404);
    await expect(page.getByRole('heading', { name: 'Page not found' })).toBeVisible();
  });
});
