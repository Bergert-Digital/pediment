import { execSync } from 'node:child_process';

/**
 * Prepare the wp-env site so the e2e suite is deterministic:
 * active theme, pretty permalinks, inline demo fixtures (pages/posts/nav/logo),
 * static front page, dismissed editor welcome guides (otherwise the Site/Post
 * Editor never attaches its canvas iframe on a fresh wp-env, hanging every
 * editor-canvas test). Idempotent — safe to run repeatedly.
 */
export default async function globalSetup(): Promise<void> {
  const wp = (cmd: string) =>
    execSync(`npx wp-env run cli wp ${cmd}`, { stdio: 'pipe' }).toString().trim();

  // Ensure Pediment is active so framework bootstrap (header part) has run
  // and the registered patterns the fixtures
  // source from are available. CI activates it explicitly too; guard so a
  // re-run never errors on an already-active theme.
  const active = wp(`option get stylesheet`);
  if (active !== 'pediment') {
    wp(`theme activate pediment`);
  }

  wp(`rewrite structure '/%postname%/' --hard`);

  // Build the minimal demo content the suite depends on. The standalone
  // `wp pediment seed` command was removed when seeding moved to the child
  // theme; these fixtures source canonical page markup from the registered
  // patterns. eval-file runs inside the container against the mapped theme dir.
  wp(`eval-file wp-content/themes/pediment/tests/e2e/fixtures.php`);

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
