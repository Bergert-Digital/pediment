import { execSync } from 'node:child_process';

/**
 * Prepare the wp-env site so the e2e suite is deterministic:
 * pretty permalinks, seeded pages/nav, static front page, dismissed
 * editor welcome guides (otherwise the Site/Post Editor never attaches
 * its canvas iframe on a fresh wp-env, hanging every editor-canvas test).
 * Idempotent — safe to run repeatedly.
 */
export default async function globalSetup(): Promise<void> {
  const wp = (cmd: string) =>
    execSync(`npx wp-env run cli wp ${cmd}`, { stdio: 'pipe' }).toString().trim();

  wp(`rewrite structure '/%postname%/' --hard`);
  wp(`pediment seed`);
  const homeId = wp(`post list --post_type=page --name=home --field=ID --posts_per_page=1`);
  if (homeId) {
    wp(`option update show_on_front page`);
    wp(`option update page_on_front ${homeId}`);
  }
  wp(`rewrite flush --hard`);

  // Dismiss the Site Editor + Post Editor welcome guides for every existing
  // user. WP stores these as `wp_persisted_preferences` user meta; the modal
  // overlays the canvas on first visit and blocks the iframe from mounting.
  const prefs = JSON.stringify({
    'core/edit-site': { welcomeGuide: false, welcomeGuideStyles: false, welcomeGuidePage: false, welcomeGuideTemplate: false },
    'core/edit-post': { welcomeGuide: false, welcomeGuideTemplate: false },
    'core/editor': { welcomeGuide: false, welcomeGuideTemplate: false },
    'core/nux': { areTipsEnabled: false },
  });
  const userIds = wp(`user list --field=ID`).split(/\s+/).filter(Boolean);
  for (const uid of userIds) {
    execSync(
      `npx wp-env run cli wp user meta update ${uid} wp_persisted_preferences '${prefs}' --format=json`,
      { stdio: 'pipe' }
    );
  }
}
