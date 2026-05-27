import { test, expect } from '@playwright/test';

test.describe('Pediment foundation', () => {
  test('Phosphor sprite is present once', async ({ page }) => {
    await page.goto('/');
    const symbols = page.locator('svg symbol#ph-bank');
    await expect(symbols).toHaveCount(1);
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
