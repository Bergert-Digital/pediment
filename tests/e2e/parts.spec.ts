import { test, expect } from '@playwright/test';

test.describe('Pediment parts', () => {
  test('header renders site title + nav CTA pill', async ({ page }) => {
    await page.goto('/');
    const header = page.locator('header.site-header').first();
    await expect(header).toBeVisible();
    const cta = header.getByRole('link', { name: 'Book a consultation' });
    await expect(cta).toBeVisible();
  });

  test('footer renders columns and bottom bar', async ({ page }) => {
    await page.goto('/');
    const footer = page.locator('footer').first();
    await expect(footer).toBeVisible();
    await expect(footer.getByText(/All rights reserved\./)).toBeVisible();
  });
});
