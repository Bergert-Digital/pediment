import { test, expect } from '@playwright/test';

test.describe('top navigation', () => {
  test('renders About, Blog and Contact items', async ({ page }) => {
    await page.goto('/');
    const nav = page.locator('header .wp-block-navigation').first();
    await expect(nav.getByRole('link', { name: 'About', exact: true })).toBeVisible();
    await expect(nav.getByRole('link', { name: 'Blog', exact: true })).toBeVisible();
    await expect(nav.getByRole('link', { name: 'Contact', exact: true })).toBeVisible();
  });

  test('header is sticky-positioned', async ({ page }) => {
    await page.goto('/');
    const header = page.locator('header.site-header').first();
    await expect(header).toHaveCSS('position', 'sticky');
    await expect(header).toHaveCSS('top', '0px');
  });

  test('Contact item is styled as a filled CTA button', async ({ page }) => {
    await page.goto('/');
    const cta = page.locator('header .wp-block-navigation-item.nav-cta a').first();
    await expect(cta).toBeVisible();
    // Filled accent background (#4F46E5) and a non-zero border radius.
    await expect(cta).toHaveCSS('background-color', 'rgb(79, 70, 229)');
    const radius = await cta.evaluate((el) => getComputedStyle(el).borderTopLeftRadius);
    expect(parseFloat(radius)).toBeGreaterThan(0);
  });
});
