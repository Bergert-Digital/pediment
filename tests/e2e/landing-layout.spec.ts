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
});
