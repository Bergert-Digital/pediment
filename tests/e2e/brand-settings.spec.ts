import { test, expect } from '@playwright/test';
import { login } from './utils';

test('Brand Settings persists name + social link after save and reload', async ({ page }) => {
  await login(page);
  await page.goto('/wp-admin/options-general.php?page=starter-brand');

  await page.fill('input[name="starter_theme_brand[brand_name]"]', 'Acme E2E');

  await page.click('.starter-brand-social__add');
  const lastRow = page.locator('.starter-brand-social__row').last();
  await lastRow.locator('input[type="text"]').fill('twitter');
  await lastRow.locator('input[type="url"]').fill('https://x.com/acme-e2e');

  await page.click('input[type="submit"]');
  await expect(page.locator('.notice-success')).toBeVisible();

  await page.goto('/wp-admin/options-general.php?page=starter-brand');
  await expect(page.locator('input[name="starter_theme_brand[brand_name]"]')).toHaveValue('Acme E2E');
  await expect(page.locator('input[name="starter_theme_brand[social_links][0][platform]"]')).toHaveValue('twitter');
  await expect(page.locator('input[name="starter_theme_brand[social_links][0][url]"]')).toHaveValue('https://x.com/acme-e2e');
});
