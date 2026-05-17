import { execSync } from 'node:child_process';

/**
 * Prepare the wp-env site so the e2e suite is deterministic:
 * pretty permalinks, seeded pages/nav, static front page.
 * Idempotent — safe to run repeatedly.
 */
export default async function globalSetup(): Promise<void> {
  const wp = (cmd: string) =>
    execSync(`npx wp-env run cli wp ${cmd}`, { stdio: 'pipe' }).toString().trim();

  wp(`rewrite structure '/%postname%/' --hard`);
  wp(`starter-theme seed`);
  const homeId = wp(`post list --post_type=page --name=home --field=ID --posts_per_page=1`);
  if (homeId) {
    wp(`option update show_on_front page`);
    wp(`option update page_on_front ${homeId}`);
  }
  wp(`rewrite flush --hard`);
}
