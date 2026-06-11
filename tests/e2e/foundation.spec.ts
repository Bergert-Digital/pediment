import { test, expect } from '@playwright/test';

test.describe('Pediment foundation', () => {
  test('icons render inline (no sprite) on the front page', async ({ page }) => {
    await page.goto('/');
    // Icons are now inlined by pediment_icon() as <svg data-icon="…"> rather
    // than referencing a sprite symbol. The seeded landing page renders a
    // feature grid whose feature cards each carry an inline icon.
    const inlineIcons = page.locator('.starter-feature-grid svg[data-icon]');
    await expect(inlineIcons.first()).toBeVisible();
    // The old sprite approach must be fully gone.
    await expect(page.locator('svg symbol[id^="ph-"]')).toHaveCount(0);
    await expect(page.locator('svg use')).toHaveCount(0);
  });

  test('anim gate class is set on <html>', async ({ page }) => {
    await page.goto('/');
    await expect(page.locator('html')).toHaveClass(/\banim\b/);
  });

  test('global stylesheet is loaded', async ({ page }) => {
    await page.goto('/');
    const hrefs = await page.locator('link[rel="stylesheet"]').evaluateAll(
      (ls) => ls.map((l) => (l as HTMLLinkElement).href)
    );
    expect(hrefs.some((h) => h.includes('/assets/css/theme.css'))).toBe(true);
  });

  test('reduced-motion users get no reveal transition', async ({ browser }) => {
    const ctx = await browser.newContext({ reducedMotion: 'reduce' });
    const page = await ctx.newPage();
    await page.goto('/');
    // Inject a probe element carrying the reveal contract.
    await page.evaluate(() => {
      const d = document.createElement('div');
      d.setAttribute('data-reveal', '');
      d.id = 'rm-probe';
      document.body.appendChild(d);
    });
    const probe = page.locator('#rm-probe');
    await expect(probe).toHaveCSS('opacity', '1');
    await ctx.close();
  });
});
