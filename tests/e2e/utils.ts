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
 * Resolves the new post id BY SLUG, not by parsing the create command's stdout:
 * `npx wp-env run cli` wraps wp-cli output with decoration lines (`ℹ Starting…`,
 * `✔ Ran … (in 0s 871ms)`), so `--porcelain` last-line parsing yields garbage.
 * Returns the front-end URL.
 */
export function createPageWithContent(slug: string, title: string, content: string): string {
  const escapedContent = content.replace(/'/g, "'\\''");
  const escapedTitle = title.replace(/'/g, "'\\''");
  execSync(
    `npx wp-env run cli wp post create --post_type=page --post_status=publish --post_title='${escapedTitle}' --post_name='${slug}' --post_content='${escapedContent}'`,
    { stdio: 'ignore' }
  );
  // Resolve by slug; keep only a line that is purely digits so wp-env's
  // ℹ/✔ decoration lines are discarded regardless of which stream they use.
  const out = execSync(
    `bash -c "npx wp-env run cli wp post list --post_type=page --name='${slug}' --field=ID --format=ids 2>/dev/null | grep -E '^[0-9]+$' | head -n 1"`,
    { encoding: 'utf8' }
  );
  const id = parseInt(out.trim(), 10);
  if (!id) {
    throw new Error(`Failed to create/resolve page for slug '${slug}'; output: ${out}`);
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
