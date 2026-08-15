#!/usr/bin/env node
/**
 * Local dev bootstrap — turns a freshly-booted wp-env into a working Pediment
 * site so the plugin can actually be exercised in the browser.
 *
 * WordPress boots with its bundled default theme active, which has none of the
 * Pediment header/navigation/blocks — "the default theme does not support
 * anything". This activates the fixture client theme + the plugin, configures
 * Polylang languages, and seeds the manifest, all idempotently.
 *
 * Wired as `.wp-env.json` → lifecycleScripts.afterStart, so it runs after every
 * local `wp-env start` (Conductor's run does `env:start` first). It is:
 *   - CI-safe: exits immediately when CI is set. CI's own `npm run env:start`
 *     shares this .wp-env.json, and e2e does its own activation/seeding — this
 *     must never run there.
 *   - Release-safe: repo-root tooling, never part of the plugin zip (plugin/).
 *   - Non-fatal: a hiccup logs a warning but exits 0, so a bad seed can't break
 *     the boot (and thus the Conductor run chain).
 */
import { spawnSync } from 'node:child_process';

if (process.env.CI) {
  // CI (GitHub Actions sets CI=true) runs the same env:start; e2e owns its own
  // setup. Bail before touching anything.
  process.exit(0);
}

/** Run a wp-cli command inside the dev container via wp-env. */
function wp(args, { allowFail = false } = {}) {
  const label = `wp ${args.join(' ')}`;
  console.log(`  → ${label}`);
  const res = spawnSync(
    'npx',
    ['wp-env', 'run', 'cli', 'wp', ...args],
    { stdio: ['ignore', 'pipe', 'pipe'], encoding: 'utf8' },
  );
  if (res.status !== 0 && !allowFail) {
    const err = (res.stderr || res.stdout || '').trim();
    throw new Error(`\`${label}\` failed:\n${err}`);
  }
  return res;
}

try {
  console.log('\n▸ Pediment dev bootstrap: activating theme + plugin, seeding…');
  // pediment-ai is a mapped plugin (not auto-activated by wp-env); polylang is
  // listed in .wp-env.json plugins and already active — activating it again is
  // a harmless no-op that keeps this self-contained.
  wp(['theme', 'activate', 'pediment-fixture']);
  wp(['plugin', 'activate', 'pediment-ai', 'polylang']);
  // Configure Polylang languages before seeding — the seeder needs them, and
  // this command is safe to re-run.
  wp(['pediment', 'languages']);
  wp(['pediment', 'seed']);
  console.log('▸ Pediment dev bootstrap: done. Site ready.\n');
} catch (err) {
  console.warn(
    `\n▸ Pediment dev bootstrap: skipped/incomplete — the env is up but the ` +
    `site may not be fully seeded.\n  ${err.message}\n  Re-run manually with: ` +
    `node tools/dev-bootstrap.mjs\n`,
  );
  // Non-fatal: never fail the boot over dev convenience seeding.
  process.exit(0);
}
