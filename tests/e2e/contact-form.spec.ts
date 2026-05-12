import { test, expect } from '@playwright/test';
import { login, createPageWithContent, deletePageBySlug } from './utils';

const CONTACT_SLUG = 'e2e-contact';
const HONEYPOT_SLUG = 'e2e-honeypot';

test('contact form submission shows success and creates a submission row', async ({ page }) => {
  deletePageBySlug(CONTACT_SLUG);
  const url = createPageWithContent(CONTACT_SLUG, 'Contact test', '<!-- wp:starter/contact-form /-->');

  await page.goto(url);
  await page.fill('input[name="name"]', 'Alice E2E');
  await page.fill('input[name="email"]', 'alice-e2e@example.com');
  await page.fill('textarea[name="message"]', 'Hello from Playwright.');

  await page.waitForTimeout(6000);

  await page.click('button.starter-contact-form__submit');

  await expect(page.locator('.starter-contact-form__status')).toContainText(/thanks/i, { timeout: 10_000 });

  await login(page);
  await page.goto('/wp-admin/edit.php?post_type=contact_submission');
  await expect(page.locator('text=alice-e2e@example.com').first()).toBeVisible();

  deletePageBySlug(CONTACT_SLUG);
});

test('honeypot triggers rejection (silent)', async ({ page }) => {
  deletePageBySlug(HONEYPOT_SLUG);
  const url = createPageWithContent(HONEYPOT_SLUG, 'Honeypot test', '<!-- wp:starter/contact-form /-->');

  await page.goto(url);
  await page.fill('input[name="name"]', 'Bot');
  await page.fill('input[name="email"]', 'bot@example.com');
  await page.fill('textarea[name="message"]', 'Spam');
  await page.evaluate(() => {
    const el = document.querySelector<HTMLInputElement>('input[name="hp_field"]');
    if (el) el.value = 'bot was here';
  });
  await page.waitForTimeout(6000);

  await page.click('button.starter-contact-form__submit');

  await expect(page.locator('.starter-contact-form__status')).toContainText(/something went wrong|submission rejected/i);

  deletePageBySlug(HONEYPOT_SLUG);
});
