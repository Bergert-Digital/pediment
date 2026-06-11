# Self-hosted theme updates (parent + child) — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Make the Pediment parent theme and the Pediment child theme deliver one-click updates through the standard WordPress Updates screen, sourced from their own public GitHub Releases — mirroring the `pediment-ai` plugin.

**Architecture:** Each theme gets a small `inc/ThemeUpdater.php` that wires YahnisElsts plugin-update-checker (PUC) v5.7 to its own GitHub repo's releases (theme mode, public repo, no auth). `functions.php` loads Composer's autoloader and registers the updater. Each theme's release workflow installs prod Composer deps before packing the zip so PUC's `vendor/` ships inside the release asset.

**Tech Stack:** PHP 8.1, WordPress block themes, Composer, YahnisElsts/plugin-update-checker ^5.7, GitHub Actions, `shivammathur/setup-php`.

**Spec:** `docs/superpowers/specs/2026-06-11-theme-self-updates-design.md`

---

## Why no red-green unit tests

The only behavior worth asserting — "PUC offers the GitHub release zip as an update" — is bound to a live WordPress runtime and a network call to GitHub. There is no pure-unit seam: when `vendor/` is installed, `PucFactory::buildUpdateChecker()` requires WordPress globals and hits the network; when it is absent, the updater is a deliberate no-op. So verification is **static checks per task** (`php -l`, `phpcs`, `composer validate`, a local `composer install` that proves PUC lands in `vendor/`) plus a **release dry-run** at the end (Task 9). This matches the spec's testing section. Do not fabricate a unit test that mocks the entire WP+PUC stack — it would assert nothing real.

---

## Repos & working order

Two separate Git repositories. Do **Repo A (parent)** fully, then **Repo B (child)**. They share no files; the child copy of `ThemeUpdater.php` differs only in repo URL, slug, theme-dir function, and asset regex.

- Repo A: `/Users/jonas/Entwicklung/pediment` (`Bergert-Digital/pediment`)
- Repo B: `/Users/jonas/Entwicklung/pediment-child-theme` (`Bergert-Digital/Pediment-Child-Theme`)

All commands below assume the working directory is the repo named in each task.

---

# Repo A — Parent theme (`pediment`)

## Task A1: Add the PUC dependency

**Files:**
- Modify: `/Users/jonas/Entwicklung/pediment/composer.json:6-8`
- Modify (generated): `/Users/jonas/Entwicklung/pediment/composer.lock`

- [ ] **Step 1: Add PUC to `require`**

Edit `composer.json` so the `require` block reads exactly:

```json
  "require": {
    "php": ">=8.1",
    "yahnis-elsts/plugin-update-checker": "^5.7"
  },
```

- [ ] **Step 2: Resolve and install (updates `composer.lock` + `vendor/`)**

Run (in `/Users/jonas/Entwicklung/pediment`):

```bash
composer update yahnis-elsts/plugin-update-checker --with-all-dependencies
```

Expected: composer writes `composer.lock` and installs `vendor/yahnis-elsts/plugin-update-checker/`.

- [ ] **Step 3: Verify PUC is present and composer.json is valid**

```bash
composer validate --no-check-publish
test -f vendor/yahnis-elsts/plugin-update-checker/load-v5p7.php && echo "PUC OK"
```

Expected: `./composer.json is valid` and `PUC OK`. (`vendor/` stays gitignored; only the lockfile is committed.)

- [ ] **Step 4: Commit**

```bash
git add composer.json composer.lock
git commit -m "build(deps): add plugin-update-checker for theme self-updates"
```

---

## Task A2: Create the parent `ThemeUpdater` class

**Files:**
- Create: `/Users/jonas/Entwicklung/pediment/inc/ThemeUpdater.php`

- [ ] **Step 1: Write the class**

Create `inc/ThemeUpdater.php` with exactly this content. Note `get_template_directory()` — the parent updates the **parent** theme directory, which differs from the active stylesheet when a child theme is in use.

```php
<?php
/**
 * GitHub-release auto-updates for the Pediment parent theme.
 *
 * Points Plugin Update Checker at the public GitHub repo's releases so theme
 * updates arrive through wp-admin's normal one-click flow (Dashboard → Updates
 * / Appearance → Themes) instead of manual zip uploads.
 *
 * @package Pediment
 */

namespace Pediment;

use YahnisElsts\PluginUpdateChecker\v5\PucFactory;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class ThemeUpdater {
	/** Public repo whose GitHub Releases drive theme updates. */
	private const REPO_URL = 'https://github.com/Bergert-Digital/pediment/';

	/**
	 * Wire the update checker to this repo's GitHub releases.
	 */
	public static function register(): void {
		if ( ! class_exists( PucFactory::class ) ) {
			return;
		}

		// get_template_directory(): always the parent theme dir, even when a
		// child theme is the active stylesheet.
		$checker = PucFactory::buildUpdateChecker(
			self::REPO_URL,
			get_template_directory() . '/style.css',
			'pediment'
		);

		// Fallback branch for reading the version header if a release is ever absent.
		if ( method_exists( $checker, 'setBranch' ) ) {
			$checker->setBranch( 'main' );
		}

		// Install the built release asset (pediment.zip) rather than GitHub's
		// auto-generated "Source code" zip, which has the wrong folder name and
		// ships no vendor/ autoloader.
		$api = $checker->getVcsApi();
		if ( method_exists( $api, 'enableReleaseAssets' ) ) {
			$api->enableReleaseAssets( '/pediment\.zip$/' );
		}
	}
}
```

- [ ] **Step 2: Lint the file**

```bash
php -l inc/ThemeUpdater.php
composer lint -- inc/ThemeUpdater.php
```

Expected: `No syntax errors detected` and phpcs reports no errors/warnings (CI fails on warnings).

- [ ] **Step 3: Commit**

```bash
git add inc/ThemeUpdater.php
git commit -m "feat(updates): add ThemeUpdater wiring PUC to GitHub releases"
```

---

## Task A3: Bootstrap the updater and add the `Update URI` header

**Files:**
- Modify: `/Users/jonas/Entwicklung/pediment/functions.php:27-29` (vendor autoload + register)
- Modify: `/Users/jonas/Entwicklung/pediment/style.css:13` (add header)

- [ ] **Step 1: Load Composer autoload and register the updater**

In `functions.php`, immediately after the existing block of `require_once __DIR__ . '/inc/...';` lines (after line 29, the `mega-menu.php` require), insert:

```php

// One-click theme updates from GitHub Releases (no manual zip uploads).
if ( file_exists( __DIR__ . '/vendor/autoload.php' ) ) {
	require_once __DIR__ . '/vendor/autoload.php';
}
require_once __DIR__ . '/inc/ThemeUpdater.php';
if ( is_admin() ) {
	\Pediment\ThemeUpdater::register();
}
```

(`is_admin()` guard: update checks only run in wp-admin, matching how WP surfaces updates and avoiding front-end overhead.)

- [ ] **Step 2: Add the `Update URI` header to `style.css`**

In `style.css`, add a new line after `Text Domain: pediment` (line 13) so the header includes:

```
Update URI: https://github.com/Bergert-Digital/pediment
```

- [ ] **Step 3: Lint changed PHP**

```bash
php -l functions.php
composer lint -- functions.php
```

Expected: `No syntax errors detected` and no phpcs errors/warnings.

- [ ] **Step 4: Confirm PUC loads under WP (smoke check)**

Confirm the class resolves through the autoloader without a fatal:

```bash
php -r 'require "vendor/autoload.php"; echo class_exists("YahnisElsts\\PluginUpdateChecker\\v5\\PucFactory") ? "PUC autoloads\n" : "MISSING\n";'
```

Expected: `PUC autoloads`.

- [ ] **Step 5: Commit**

```bash
git add functions.php style.css
git commit -m "feat(updates): bootstrap ThemeUpdater + add Update URI header"
```

---

## Task A4: Ship `vendor/` in the release zip

**Files:**
- Modify: `/Users/jonas/Entwicklung/pediment/.github/workflows/build-release-zip.yml:29-37`

- [ ] **Step 1: Add a PHP/Composer install step before the JS build**

In `build-release-zip.yml`, between the `actions/checkout@v4` step (ends line 27) and the `actions/setup-node@v4` step (line 29), insert these two steps so prod deps (PUC) are installed into `vendor/` before the zip is packed:

```yaml
      - uses: shivammathur/setup-php@v2
        with:
          php-version: '8.1'
          tools: composer:v2
          coverage: none

      - name: Install Composer prod deps (PUC) into vendor/
        run: composer install --no-dev --optimize-autoloader --prefer-dist --no-progress
```

- [ ] **Step 2: Verify `.distignore` keeps `vendor/`**

```bash
grep -qx 'vendor' .distignore && echo "BUG: vendor excluded" || echo "vendor ships OK"
```

Expected: `vendor ships OK` (the parent `.distignore` excludes `composer.json`/`composer.lock` but **not** `vendor`).

- [ ] **Step 3: Locally reproduce the packed zip and confirm PUC is inside**

```bash
composer install --no-dev --optimize-autoloader --prefer-dist --no-progress
STAGE="$(mktemp -d)/pediment"; mkdir -p "$STAGE"
rsync -a --exclude-from=.distignore ./ "$STAGE/"
( cd "$(dirname "$STAGE")" && zip -rq /tmp/pediment-test.zip pediment )
unzip -l /tmp/pediment-test.zip | grep -q 'pediment/vendor/yahnis-elsts/plugin-update-checker/' && echo "PUC in zip OK"
```

Expected: `PUC in zip OK`. Then restore dev deps: `composer install`.

- [ ] **Step 4: Commit**

```bash
git add .github/workflows/build-release-zip.yml
git commit -m "ci(release): install PUC prod deps so vendor/ ships in pediment.zip"
```

---

# Repo B — Child theme (`pediment-child-theme`)

## Task B1: Add the PUC dependency

**Files:**
- Modify: `/Users/jonas/Entwicklung/pediment-child-theme/composer.json:6-8`
- Modify (generated): `/Users/jonas/Entwicklung/pediment-child-theme/composer.lock`

- [ ] **Step 1: Add PUC to `require`**

Edit `composer.json` so the `require` block reads exactly:

```json
  "require": {
    "php": ">=8.1",
    "yahnis-elsts/plugin-update-checker": "^5.7"
  },
```

- [ ] **Step 2: Resolve and install**

Run (in `/Users/jonas/Entwicklung/pediment-child-theme`):

```bash
composer update yahnis-elsts/plugin-update-checker --with-all-dependencies
```

- [ ] **Step 3: Verify**

```bash
composer validate --no-check-publish
test -f vendor/yahnis-elsts/plugin-update-checker/load-v5p7.php && echo "PUC OK"
```

Expected: `./composer.json is valid` and `PUC OK`.

- [ ] **Step 4: Commit**

```bash
git add composer.json composer.lock
git commit -m "build(deps): add plugin-update-checker for theme self-updates"
```

---

## Task B2: Create the child `ThemeUpdater` class

**Files:**
- Create: `/Users/jonas/Entwicklung/pediment-child-theme/inc/ThemeUpdater.php`

- [ ] **Step 1: Write the class**

Create `inc/ThemeUpdater.php`. This differs from the parent in: namespace, repo URL, `get_stylesheet_directory()` (the child updates the **active stylesheet** = the child dir), slug `pediment-child-theme` (must equal the theme folder name so WP matches the update), and the asset regex `/pediment-child-theme\.zip$/`.

```php
<?php
/**
 * GitHub-release auto-updates for the Pediment child theme.
 *
 * Points Plugin Update Checker at the public GitHub repo's releases so theme
 * updates arrive through wp-admin's normal one-click flow (Dashboard → Updates
 * / Appearance → Themes) instead of manual zip uploads.
 *
 * @package PedimentChild
 */

namespace PedimentChild;

use YahnisElsts\PluginUpdateChecker\v5\PucFactory;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class ThemeUpdater {
	/** Public repo whose GitHub Releases drive theme updates. */
	private const REPO_URL = 'https://github.com/Bergert-Digital/Pediment-Child-Theme/';

	/**
	 * Wire the update checker to this repo's GitHub releases.
	 */
	public static function register(): void {
		if ( ! class_exists( PucFactory::class ) ) {
			return;
		}

		// get_stylesheet_directory(): the active theme dir — here, the child.
		// Slug must equal the theme folder name (pediment-child-theme) so WP
		// matches the update to the installed theme.
		$checker = PucFactory::buildUpdateChecker(
			self::REPO_URL,
			get_stylesheet_directory() . '/style.css',
			'pediment-child-theme'
		);

		// Fallback branch for reading the version header if a release is ever absent.
		if ( method_exists( $checker, 'setBranch' ) ) {
			$checker->setBranch( 'main' );
		}

		// Install the built release asset (pediment-child-theme.zip) rather than
		// GitHub's auto-generated "Source code" zip, which has the wrong folder
		// name and ships no vendor/ autoloader.
		$api = $checker->getVcsApi();
		if ( method_exists( $api, 'enableReleaseAssets' ) ) {
			$api->enableReleaseAssets( '/pediment-child-theme\.zip$/' );
		}
	}
}
```

- [ ] **Step 2: Lint**

```bash
php -l inc/ThemeUpdater.php
composer lint -- inc/ThemeUpdater.php
```

Expected: `No syntax errors detected`; no phpcs errors/warnings.

- [ ] **Step 3: Commit**

```bash
git add inc/ThemeUpdater.php
git commit -m "feat(updates): add ThemeUpdater wiring PUC to GitHub releases"
```

---

## Task B3: Bootstrap the updater and add the `Update URI` header

**Files:**
- Modify: `/Users/jonas/Entwicklung/pediment-child-theme/functions.php:20-21` (vendor autoload + register, after the constants)
- Modify: `/Users/jonas/Entwicklung/pediment-child-theme/style.css:13` (add header)

- [ ] **Step 1: Load Composer autoload and register the updater**

In `functions.php`, immediately after the `PEDIMENT_CHILD_VERSION` define block (after line 20, the closing `}` of the `if ( ! defined( 'PEDIMENT_CHILD_VERSION' ) )` guard), insert:

```php

// One-click theme updates from GitHub Releases (no manual zip uploads).
if ( file_exists( __DIR__ . '/vendor/autoload.php' ) ) {
	require_once __DIR__ . '/vendor/autoload.php';
}
require_once __DIR__ . '/inc/ThemeUpdater.php';
if ( is_admin() ) {
	\PedimentChild\ThemeUpdater::register();
}
```

- [ ] **Step 2: Add the `Update URI` header to `style.css`**

In `style.css`, add a new line after `Text Domain: pediment-child` (line 13):

```
Update URI: https://github.com/Bergert-Digital/Pediment-Child-Theme
```

- [ ] **Step 3: Lint changed PHP**

```bash
php -l functions.php
composer lint -- functions.php
```

Expected: `No syntax errors detected`; no phpcs errors/warnings.

- [ ] **Step 4: Confirm PUC autoloads**

```bash
php -r 'require "vendor/autoload.php"; echo class_exists("YahnisElsts\\PluginUpdateChecker\\v5\\PucFactory") ? "PUC autoloads\n" : "MISSING\n";'
```

Expected: `PUC autoloads`.

- [ ] **Step 5: Commit**

```bash
git add functions.php style.css
git commit -m "feat(updates): bootstrap ThemeUpdater + add Update URI header"
```

---

## Task B4: Ship `vendor/` and normalize the release asset to a version-less zip

**Files:**
- Modify: `/Users/jonas/Entwicklung/pediment-child-theme/.distignore:8` (remove `vendor`)
- Modify: `/Users/jonas/Entwicklung/pediment-child-theme/.github/workflows/release.yml:47-55,81-95`

- [ ] **Step 1: Stop excluding `vendor/` from the distribution**

In `.distignore`, delete the line containing exactly `vendor`. Verify:

```bash
grep -qx 'vendor' .distignore && echo "BUG: vendor still excluded" || echo "vendor ships OK"
```

Expected: `vendor ships OK`.

- [ ] **Step 2: Add PHP/Composer install before the JS build**

In `.github/workflows/release.yml`, between the `actions/setup-node@v4` step (lines 47–50) and the `Build JS bundle` step (line 52), insert:

```yaml
      - uses: shivammathur/setup-php@v2
        with:
          php-version: '8.1'
          tools: composer:v2
          coverage: none

      - name: Install Composer prod deps (PUC) into vendor/
        run: composer install --no-dev --optimize-autoloader --prefer-dist --no-progress
```

- [ ] **Step 3: Rename the built asset to a version-less zip**

In the `Build distributable zip` step (lines 81–89), change the zip filename from `pediment-child-theme-$VERSION.zip` to `pediment-child-theme.zip`. The step body becomes:

```yaml
      - name: Build distributable zip
        env:
          VERSION: ${{ inputs.version }}
        run: |
          STAGE="$(mktemp -d)/pediment-child-theme"
          mkdir -p "$STAGE"
          rsync -a --exclude-from=.distignore ./ "$STAGE/"
          ( cd "$(dirname "$STAGE")" && zip -rq "$GITHUB_WORKSPACE/pediment-child-theme.zip" pediment-child-theme )
          unzip -l "pediment-child-theme.zip" | tail -5
```

- [ ] **Step 4: Update the release-create step to upload the renamed asset**

In the `Create GitHub Release` step (lines 91–95), change the asset argument from `pediment-child-theme-$VERSION.zip` to `pediment-child-theme.zip`. The `run` line becomes:

```yaml
        run: gh release create "v$VERSION" --title "v$VERSION" --generate-notes "pediment-child-theme.zip"
```

- [ ] **Step 5: Locally reproduce the packed zip and confirm PUC is inside**

```bash
composer install --no-dev --optimize-autoloader --prefer-dist --no-progress
STAGE="$(mktemp -d)/pediment-child-theme"; mkdir -p "$STAGE"
rsync -a --exclude-from=.distignore ./ "$STAGE/"
( cd "$(dirname "$STAGE")" && zip -rq /tmp/child-test.zip pediment-child-theme )
unzip -l /tmp/child-test.zip | grep -q 'pediment-child-theme/vendor/yahnis-elsts/plugin-update-checker/' && echo "PUC in zip OK"
```

Expected: `PUC in zip OK`. Then restore dev deps: `composer install`.

- [ ] **Step 6: Commit**

```bash
git add .distignore .github/workflows/release.yml
git commit -m "ci(release): ship PUC vendor/ and normalize asset to version-less zip"
```

---

## Task 9: Release dry-run verification (both themes)

This is the real end-to-end check; it requires cutting actual releases, so do it after both repos' changes are merged to `main`.

**Files:** none (operational verification).

- [ ] **Step 1: Cut a patch release of each theme** via its release workflow (parent: release-please / `build-release-zip`; child: the `Release` workflow_dispatch with a new patch version).

- [ ] **Step 2: Confirm PUC ships in each published asset**

```bash
gh release download -R Bergert-Digital/pediment -p 'pediment.zip' -D /tmp/relcheck --clobber
unzip -l /tmp/relcheck/pediment.zip | grep -q 'vendor/yahnis-elsts/plugin-update-checker/' && echo "parent OK"
gh release download -R Bergert-Digital/Pediment-Child-Theme -p 'pediment-child-theme.zip' -D /tmp/relcheck --clobber
unzip -l /tmp/relcheck/pediment-child-theme.zip | grep -q 'vendor/yahnis-elsts/plugin-update-checker/' && echo "child OK"
```

Expected: `parent OK` and `child OK`.

- [ ] **Step 3: Verify the update appears in WordPress**

On the wp-env dev install (child theme env, port 8890 — see `[[project_dev_env]]`), with both themes installed at a version **below** the release just cut:

1. Visit **Dashboard → Updates** (or `wp-admin/update-core.php`).
2. Confirm both "Pediment" and "Pediment Child Theme" show an available update with the new version number.
3. Run the update for each and confirm it applies without error and the theme still renders.

Expected: both themes update one-click and the site renders normally.

---

## Roll-out note

`vendor/` is gitignored in both repos, so a correct release depends entirely on the workflow's `composer install` step running. A release built by hand (`gh release upload` without the workflow) would ship a PUC-less zip and silently break updates. Treat the GitHub Actions release workflow as the **only** canonical release path for both themes.
