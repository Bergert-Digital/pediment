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

  test('testimonial: grid is alignwide and shows two cards', async ({ page }) => {
    await page.goto('/');
    const grid = page.locator('.starter-testimonial-grid').first();
    await grid.scrollIntoViewIfNeeded();
    const gw = await grid.evaluate((el) => Math.round(el.getBoundingClientRect().width));
    // alignwide → ≤ wide-size (1200px).
    expect(gw).toBeLessThanOrEqual(1200);
    const cards = grid.locator('.starter-testimonial');
    await expect(cards).toHaveCount(2);
    // 2-up grid: each card is narrower than the whole grid.
    const c0 = await cards.nth(0).evaluate((el) => Math.round(el.getBoundingClientRect().width));
    expect(c0).toBeLessThan(gw);
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

  test('insights: section-head is centered', async ({ page }) => {
    await page.goto('/');
    // Insights is the last starter-band on the landing page.
    const bands = page.locator('.entry-content > .starter-band');
    const insightsBand = bands.last();
    await insightsBand.scrollIntoViewIfNeeded();
    const head = insightsBand.locator('.starter-section-head');
    await expect(head).toBeVisible();
    const { textAlign, parentWidth, boxWidth, boxLeft } =
      await head.evaluate((el) => {
        const inner = el.querySelector('.starter-section-head__inner') as HTMLElement;
        const cs = window.getComputedStyle(inner);
        const r = inner.getBoundingClientRect();
        const parentR = (el as HTMLElement).getBoundingClientRect();
        return {
          textAlign: cs.textAlign,
          parentWidth: Math.round(parentR.width),
          boxWidth: Math.round(r.width),
          boxLeft: Math.round(r.left - parentR.left),
        };
      });
    expect(textAlign).toBe('center');
    // is-alignment-center → margin-inline: auto → symmetric leading/trailing space.
    const trailing = parentWidth - boxLeft - boxWidth;
    expect(Math.abs(boxLeft - trailing)).toBeLessThanOrEqual(2);
  });
});
