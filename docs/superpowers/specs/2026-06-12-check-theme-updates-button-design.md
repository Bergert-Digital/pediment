# Check-for-theme-updates button — design

**Date:** 2026-06-12
**Repos:** `pediment` (parent), `Pediment-Child-Theme` (child)
**Builds on:** [2026-06-11-theme-self-updates-design.md](2026-06-11-theme-self-updates-design.md)

## Problem

Both themes self-update via plugin-update-checker (PUC) v5.7 from GitHub releases, and the
pipeline works end-to-end on production. But there is no way to manually trigger or verify a
theme update check from wp-admin:

- The Updates screen's "Check Again" link is labelled as a core re-check; it does re-run theme
  checks too, but gives no theme-specific feedback, so users can't tell whether anything happened.
- PUC's built-in "Check for updates" link exists **for plugins only**. The PUC README explicitly
  states themes don't get that link; its official workaround is the Debug Bar plugin — a developer
  tool, unusable for clients.

Audience: clients on live sites (discoverable, on-demand update checks) and us (verifying a fresh
GitHub release is picked up without waiting for PUC's 12-hour cache).

## Decision

A **"Check for theme updates" button on Dashboard → Updates**, rendered as a small own section via
the documented `core_upgrade_preamble` hook (fires after the core/plugins/themes/translations
tables). Pure PHP — a researched-and-rejected alternative was relocating the button into core's
"Themes" section with admin JS; that section has no hook, and depending on core admin DOM is the
only part of the design that would not sit on a documented extension point.

The button forces `checkForUpdates()` on every registered Pediment update checker, which bypasses
PUC's 12-hour cache and all throttles (the same API PUC's own debug panel uses), then reports the
result per theme in an admin notice.

**Explicitly out of scope:**

- Any change to the local-environment skip (commit 631d8f5). Local dev mounts the theme folders
  directly; no checkers register there, so the section simply doesn't render. This is desired.
- JS relocation into core's Themes section.
- Translation files (`languages/`). All new strings are translatable (`pediment` text domain) but
  ship in English; German `.po` files are a possible follow-up.

## Components

### 1. `ThemeUpdater` changes (both themes)

`pediment/inc/ThemeUpdater.php` and `pediment-child-theme/inc/ThemeUpdater.php`:

- Keep the checker instance returned by `PucFactory::buildUpdateChecker()` in a private static
  property instead of discarding it.
- Register it into a shared filter:

  ```php
  add_filter( 'pediment_update_checkers', static function ( array $checkers ) {
      $checkers[] = array(
          'slug'    => 'pediment',          // or 'pediment-child-theme'
          'name'    => 'Pediment',          // display name for notices
          'checker' => self::$checker,
      );
      return $checkers;
  } );
  ```

- Parent and child register independently. The child requires the parent, so the button code
  (parent-side) is always present when either registers. If only the parent theme is installed,
  the filter yields one entry; with the child active, two.

### 2. New file: `pediment/inc/update-check.php`

Loaded from the parent's `functions.php` (admin requests only). Two responsibilities:

**Rendering** (`core_upgrade_preamble` action):

- `$checkers = apply_filters( 'pediment_update_checkers', array() );` — if empty (local env,
  updater disabled), render nothing.
- Otherwise render: a heading ("Theme Updates"), one line per theme with display name and
  installed version (from `wp_get_theme()`), and a single form button "Check for theme updates"
  posting to `admin-post.php` with action `pediment_check_theme_updates` and a nonce
  (`wp_nonce_field`).
- If PUC's state API exposes it cleanly (`$checker->getUpdateState()->getLastCheck()` or
  equivalent public accessor — verify during implementation), show a "last checked" timestamp per
  theme. If the API is not public, omit the timestamp rather than reaching into internals.

**Handling** (`admin_post_pediment_check_theme_updates` action):

1. `check_admin_referer()` + `current_user_can( 'update_themes' )`; failure → `wp_die()`
   (WordPress standard behaviour).
2. For each registered checker, call `checkForUpdates()`:
   - Returns an update object → result "update available: {new version}".
   - Returns `null` → result "up to date ({installed version})".
   - To distinguish "up to date" from "check failed" (GitHub unreachable, API error), hook PUC's
     API-error action (`puc_api_error` — verify exact hook name in the vendored PUC source during
     implementation) for the duration of the check and record any error per slug. If the hook
     doesn't exist in v5, fall back to reporting "no newer version found", which is honest in
     both cases.
3. Store results in a short-lived transient keyed by user ID
   (`pediment_update_check_{user_id}`, 60 s TTL), then `wp_safe_redirect()` back to
   `update-core.php` and exit.

**Result notice** (`admin_notices`, Updates screen only):

- Read-and-delete the transient; render one notice listing each theme's result: e.g.
  "Pediment: update 0.3.1 available." / "Pediment Child: up to date (0.2.1)." /
  "Pediment: update check failed — could not reach GitHub."
- When an update was found, `checkForUpdates()` has already written it into the `update_themes`
  transient via PUC's filters, so WordPress's own Themes table on the same page now lists it with
  the standard update UI — no custom install flow needed.

### 3. i18n

All user-facing strings use `__()` / `esc_html__()` with the `pediment` text domain (the UI lives
in the parent theme; child theme strings stay in `pediment-child` only inside its own
`ThemeUpdater.php`, which adds no UI strings).

## Error handling

| Failure | Behaviour |
| --- | --- |
| Missing capability / bad nonce | `wp_die()` (WP standard) |
| GitHub unreachable / API error | Per-theme "check failed" notice (best effort via PUC error hook); never claims "up to date" on a known failure |
| No checkers registered (local env) | Section not rendered; handler redirects back without action |
| Transient expired before redirect lands | No notice shown; harmless — themes table still reflects any found update |

## Testing

- **PHPUnit (`tests/phpunit/`, existing `WP_UnitTestCase` harness):** the filter contract makes
  this testable without PUC installed — tests inject fake checker entries via
  `pediment_update_checkers`:
  - Section renders on `core_upgrade_preamble` when checkers exist; renders nothing when empty.
  - Handler rejects users lacking `update_themes`.
  - Handler with a fake checker writes the expected result transient and redirects.
  - Notice renders from a seeded transient and deletes it.
- **Lint gates:** `composer lint` (phpcs, warnings fail CI) in both repos.
- **Manual verification:** in wp-env, temporarily set `WP_ENVIRONMENT_TYPE=production` via
  `.wp-env.json` config (no code change) so PUC registers, and confirm the button performs a real
  GitHub round-trip. Final confirmation on a production site after the next release.

## Release

Both themes need a release for the feature to reach production: parent (button + filter
registration) and child (filter registration). The parent-side button works with an
older child (the child simply doesn't appear in the section until its release lands).
