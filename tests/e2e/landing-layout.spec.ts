import { test, expect } from '@playwright/test';

test.describe('landing layout (1440×900)', () => {
  test.use({ viewport: { width: 1440, height: 900 } });

  test('cta: bounding box width matches max-width (border-box)', async ({ page }) => {
    await page.goto('/');
    const cta = page.locator('.starter-cta').first();
    await cta.scrollIntoViewIfNeeded();
    const { box, maxWidth } = await cta.evaluate((el) => {
      const r = el.getBoundingClientRect();
      return { box: { w: Math.round(r.width) }, maxWidth: window.getComputedStyle(el).maxWidth };
    });
    // alignwide → max-width: 1200px (wide-size). With border-box, the outer box
    // is exactly that. Without border-box (current bug), padding-inline adds
    // ~120px and the box swells to ~1320px.
    expect(maxWidth).toBe('1200px');
    expect(box.w).toBeLessThanOrEqual(1200);
  });

  test('testimonial: pull-quote bounding box ≤ 900px wide', async ({ page }) => {
    await page.goto('/');
    const quote = page.locator('.starter-pull-quote.is-variant-testimonial').first();
    await quote.scrollIntoViewIfNeeded();
    const w = await quote.evaluate((el) => Math.round(el.getBoundingClientRect().width));
    // Mockup is 880px; allow 20px slack for the gutter rounding.
    expect(w).toBeLessThanOrEqual(900);
  });

  test('services: section-head and feature-grid share the same left edge', async ({ page }) => {
    await page.goto('/');
    // Services band is the 2nd starter-band (index 1) in pediment-landing.
    const band = page.locator('.entry-content > .starter-band').nth(1);
    await band.scrollIntoViewIfNeeded();
    const head = band.locator('.starter-section-head');
    const grid = band.locator('.starter-feature-grid');
    const headX = await head.evaluate((el) => Math.round(el.getBoundingClientRect().left));
    const gridX = await grid.evaluate((el) => Math.round(el.getBoundingClientRect().left));
    // The whole point of the new block: head and grid sit on the same alignwide
    // left edge. Allow 1px rounding slack.
    expect(Math.abs(headX - gridX)).toBeLessThanOrEqual(1);
  });
});
