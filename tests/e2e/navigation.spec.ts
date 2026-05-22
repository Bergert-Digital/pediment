import { test, expect } from '@playwright/test';

// Pediment accent (Deep Cyan #0E7490).
const ACCENT = 'rgb(14, 116, 144)';

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

  test('header has no leftover nav-cta button', async ({ page }) => {
    await page.goto('/');
    const header = page.locator('header.site-header').first();
    // The accent-pill CTA was retired during the header polish refactor;
    // guard against it sneaking back in as a navigation-item variant.
    await expect(
      header.locator('.wp-block-navigation-item.nav-cta')
    ).toHaveCount(0);
  });

  test('mobile overlay opens and closes', async ({ page }) => {
    await page.setViewportSize({ width: 375, height: 800 });
    await page.goto('/');
    const openBtn = page
      .locator('header .wp-block-navigation__responsive-container-open')
      .first();
    await expect(openBtn).toBeVisible();
    await openBtn.click();
    const overlay = page
      .locator('header .wp-block-navigation__responsive-container.is-menu-open')
      .first();
    await expect(overlay).toBeVisible();
    await page
      .locator('header .wp-block-navigation__responsive-container-close')
      .first()
      .click();
    await expect(overlay).toBeHidden();
  });

  test('current page nav item gets the active indicator', async ({ page }) => {
    const resp = await page.goto('/about/');
    expect(resp?.status()).toBe(200);
    const active = page
      .locator(
        "header .wp-block-navigation a[aria-current=\"page\"], header .wp-block-navigation .current-menu-item > a"
      )
      .first();
    await expect(active).toBeVisible();
    await expect(active).toHaveText('About');
    await expect(active).toHaveCSS('color', ACCENT);
  });

  test('header shows the site logo', async ({ page }) => {
    await page.goto('/');
    const logo = page
      .locator('header.site-header .wp-block-site-logo img')
      .first();
    await expect(logo).toBeVisible();
  });
});
