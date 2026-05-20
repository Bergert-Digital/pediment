import { Page } from '@playwright/test';
import { execSync } from 'node:child_process';
import { resolve } from 'node:path';

// All wp-cli must target the child-theme wp-env (:8890) — the single test
// base. `npx wp-env run cli` uses the cwd's .wp-env.json, so run it from the
// sibling wp-starter-child-theme dir, NOT this theme checkout (which has its
// own :8888 env with a different DB). Override with WP_ENV_CWD if needed.
const WP_ENV_CWD =
  process.env.WP_ENV_CWD ||
  resolve(process.cwd(), '..', 'wp-starter-child-theme');

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
    { stdio: 'ignore', cwd: WP_ENV_CWD }
  );
  // Resolve by slug; keep only a line that is purely digits so wp-env's
  // ℹ/✔ decoration lines are discarded regardless of which stream they use.
  const out = execSync(
    `bash -c "npx wp-env run cli wp post list --post_type=page --name='${slug}' --field=ID --format=ids 2>/dev/null | grep -E '^[0-9]+$' | head -n 1"`,
    { encoding: 'utf8', cwd: WP_ENV_CWD }
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
      { stdio: 'ignore', cwd: WP_ENV_CWD }
    );
  } catch {
    // ignore
  }
}

/**
 * Create a `wp_navigation` entity via wp-cli with given content and a stable
 * slug for later lookup. Mirrors createPageWithContent in resolving the new
 * post ID by slug (wp-env's wp-cli wrapper decorates stdout, so --porcelain
 * last-line parsing is unsafe).
 */
export function createNavigationEntityWithContent(
  slug: string,
  title: string,
  content: string
): number {
  const escapedContent = content.replace(/'/g, "'\\''");
  const escapedTitle = title.replace(/'/g, "'\\''");
  execSync(
    `npx wp-env run cli wp post create --post_type=wp_navigation --post_status=publish --post_title='${escapedTitle}' --post_name='${slug}' --post_content='${escapedContent}'`,
    { stdio: 'ignore', cwd: WP_ENV_CWD }
  );
  const out = execSync(
    `bash -c "npx wp-env run cli wp post list --post_type=wp_navigation --name='${slug}' --field=ID --format=ids 2>/dev/null | grep -E '^[0-9]+$' | head -n 1"`,
    { encoding: 'utf8', cwd: WP_ENV_CWD }
  );
  const id = parseInt(out.trim(), 10);
  if (!id) {
    throw new Error(`Failed to create/resolve wp_navigation '${slug}'; output: ${out}`);
  }
  return id;
}

export function deleteNavigationEntityById(id: number): void {
  if (!id) return;
  try {
    execSync(
      `npx wp-env run cli wp post delete ${id} --force >/dev/null 2>&1`,
      { stdio: 'ignore', cwd: WP_ENV_CWD }
    );
  } catch {
    // ignore
  }
}
