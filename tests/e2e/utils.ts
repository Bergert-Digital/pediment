import { Page } from '@playwright/test';
import { execSync } from 'node:child_process';

export async function login(page: Page, user = 'admin', pass = 'password') {
  await page.goto('/wp-login.php');
  await page.fill('input#user_login', user);
  await page.fill('input#user_pass', pass);
  await page.click('input#wp-submit');
  await page.waitForURL(/wp-admin/);
}

/**
 * Create + publish a page via wp-cli so we don't depend on Gutenberg UI selectors.
 * Returns the front-end URL.
 */
export function createPageWithContent(slug: string, title: string, content: string): string {
  const escapedContent = content.replace(/'/g, "'\\''");
  const escapedTitle = title.replace(/'/g, "'\\''");
  const cmd = `npx wp-env run cli wp post create --post_type=page --post_status=publish --post_title='${escapedTitle}' --post_name='${slug}' --post_content='${escapedContent}' --porcelain`;
  const out = execSync(cmd, { encoding: 'utf8' });
  const id = parseInt(out.trim().split('\n').pop()!.trim(), 10);
  if (!id) {
    throw new Error(`Failed to create page; wp-cli output: ${out}`);
  }
  return `/?page_id=${id}`;
}

export function deletePageBySlug(slug: string): void {
  try {
    execSync(
      `bash -c "ids=\\$(npx wp-env run cli wp post list --post_type=page --name=${slug} --format=ids 2>/dev/null | tail -n 1); [ -n \\"\\$ids\\" ] && npx wp-env run cli wp post delete \\$ids --force >/dev/null 2>&1"`,
      { stdio: 'ignore' }
    );
  } catch {
    // ignore
  }
}
