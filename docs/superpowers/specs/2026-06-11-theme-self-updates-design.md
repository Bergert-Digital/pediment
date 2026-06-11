# Self-hosted theme updates (parent + child)

**Date:** 2026-06-11
**Status:** Approved (design)
**Repos touched:** `Bergert-Digital/pediment` (parent), `Bergert-Digital/Pediment-Child-Theme` (child)

## Goal

Both themes surface one-click updates in the WordPress admin (Dashboard → Updates and
Appearance → Themes), sourced from their own **public** GitHub Releases — exactly like the
`pediment-ai` plugin does today. No custom "update button"; the standard WP update flow is the
deliverable.

## Background

`pediment-ai` already does this via the YahnisElsts **plugin-update-checker (PUC) v5.7** library,
wired in `pediment-ai/src/Updater.php`:

- `PucFactory::buildUpdateChecker(REPO_URL, $plugin_file, 'pediment-ai')`
- `setBranch('main')` fallback
- `enableReleaseAssets('/pediment-ai\.zip$/')` so the custom-built zip (with `vendor/`) is the
  update package instead of GitHub's auto-generated source zip
- Public repo → **no authentication** call

PUC's same `buildUpdateChecker` auto-detects **theme** mode when handed a `style.css` path, so the
plugin pattern ports directly to themes.

### Current state of the themes

| | Parent (`pediment`) | Child (`pediment-child-theme`) |
|---|---|---|
| `style.css` Version | 0.1.4 | 0.1.0 |
| `Update URI:` header | absent | absent |
| PUC in `composer.json` | no | no |
| Composer autoloader | no (manual `require_once`) | no |
| Release zip asset | `pediment.zip` (version-less) | `pediment-child-theme-<VERSION>.zip` (versioned) |
| Release workflow runs `composer install` | **no** | **no** |
| `.distignore` excludes `vendor/` | **no** (vendor ships) | **yes** (vendor stripped) |
| GitHub repo | public | public |

The key gap: neither release workflow installs prod Composer deps, so today's zips would **not**
contain PUC even after we add it as a dependency. The child additionally strips `vendor/` via
`.distignore`. Fixing the release pipeline is the bulk of the work.

## Design

Decisions locked during brainstorming: **both themes**; **normalize the child to a version-less
zip**; **no custom button** (standard WP update flow).

### Per-theme changes (four each)

1. **`composer.json`**
   - Add to `require`: `"yahnis-elsts/plugin-update-checker": "^5.7"`.
   - No PSR-4 `autoload` block needed: `functions.php` `require_once`s `inc/ThemeUpdater.php`
     directly (matching the themes' existing manual-require convention) and only relies on
     `vendor/autoload.php` to load PUC itself.

2. **`inc/ThemeUpdater.php`** — a near-verbatim copy of the plugin's `Updater.php`:
   - `PucFactory::buildUpdateChecker(REPO_URL, get_stylesheet_directory() . '/style.css', SLUG)`
     - parent `REPO_URL`: `https://github.com/Bergert-Digital/pediment/`, `SLUG` `pediment`
     - child `REPO_URL`: `https://github.com/Bergert-Digital/Pediment-Child-Theme/`, `SLUG` `pediment-child`
   - `setBranch('main')` (guarded with `method_exists`).
   - `enableReleaseAssets()` with the asset regex:
     - parent: `'/pediment\.zip$/'`
     - child: `'/pediment-child-theme\.zip$/'`
   - **Each updater points at its own theme directory** — this is the one place the two copies
     meaningfully differ. When a child theme is active, `get_template_directory()` (parent) and
     `get_stylesheet_directory()` (child) resolve to different paths, so:
     - parent updater → `get_template_directory() . '/style.css'`
     - child updater → `get_stylesheet_directory() . '/style.css'`

3. **`functions.php`** — after a `require_once __DIR__ . '/vendor/autoload.php'` guard
   (only if the file exists, to avoid fatals in dev without `composer install`), instantiate the
   updater (parent: `new \Pediment\ThemeUpdater()` or a static `::init()`; child analogous),
   following the parent's existing `require_once`/bootstrap convention in `functions.php`.

4. **`style.css`** — add `Update URI:` header pointing at the GitHub repo. Cosmetic/correctness
   (prevents WordPress.org from ever claiming the slug); PUC drives the actual check via the class.

### Release workflow changes

**Parent (`.github/workflows/build-release-zip.yml`):**
- Add a step **before** the rsync/zip step: `composer install --no-dev --optimize-autoloader`.
- `.distignore` already keeps `vendor/`, so no change there. Confirm `composer.json`/`composer.lock`
  exclusion in `.distignore` does **not** also drop `vendor/` (it doesn't).

**Child (`.github/workflows/release.yml`):**
- Add `composer install --no-dev --optimize-autoloader` before the zip step.
- Remove `vendor` from the child's `.distignore` so PUC ships in the zip.
- Rename the built asset `pediment-child-theme-$VERSION.zip` → version-less
  `pediment-child-theme.zip` (update the `zip` command, the `unzip -l` echo, and the
  `gh release create` asset argument). This matches the updater regex and the plugin/parent
  convention, and makes the GitHub download URL stable (`/releases/download/v<X.Y.Z>/pediment-child-theme.zip`).

## Out of scope

- No custom "Check for updates" admin button (the plugin has none; PUC auto-polls). If a manual
  button is later wanted, PUC ships a one-line `addQueryArgFilter`/admin-row link helper we can add.
- No change to versioning, release-please, or the JS build steps beyond the composer/zip additions.

## Testing & verification

Unit tests can't meaningfully exercise a network-bound updater, so verification is a **release
dry-run per theme**:

1. Cut a patch release via the (updated) workflow.
2. `unzip -l` the published asset and confirm `vendor/yahnis-elsts/plugin-update-checker/` is
   present inside the zip.
3. On a local wp-env install (port 8890, child theme env), pin the installed theme to a lower
   version, trigger an update check, and confirm the update appears and applies cleanly in
   Dashboard → Updates.

## Risks / notes

- `vendor/` is gitignored in both repos; updates rely entirely on the workflow's `composer install`
  step running. A release built outside the workflow (manual `gh release upload`) would ship a
  PUC-less zip — document that the workflow is the canonical release path.
- PUC reads the `Version:` header from the release/branch; the existing workflows already patch
  `style.css` Version on release, so this stays consistent.
