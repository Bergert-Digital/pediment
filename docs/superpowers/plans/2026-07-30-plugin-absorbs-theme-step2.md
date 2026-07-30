# Plugin Absorbs Theme — v3.0.0 (Migration Step 2) Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Move everything shared — runtime and presentation — into `plugin/`, retire the parent theme, rename the plugin to Pediment, and ship it as v3.0.0's single artifact `pediment-plugin.zip`, implementing migration step 2 of `docs/superpowers/specs/2026-07-29-pediment-dev-flow-design.md`.

**Architecture:** The plugin becomes the whole product: 24 blocks (contact-form removed), templates via `register_block_template()`, patterns (footer becomes one), default tokens injected via the spike-proven `wp_theme_json_data_theme` base-merge with per-slug presets, the forms engine, and all former `inc/` modules. The repo root stops being a theme; a minimal fixture theme under `tests/fixtures/` plays the standalone-client-theme role for wp-env and e2e. One artifact per tag.

**Tech Stack:** WP 6.9 APIs (`register_block_template` 6.7+, `wp_theme_json_data_theme` 6.1+, `wp_register_block_metadata_collection` 6.7+), wp-scripts, release-please, PUC v5.

## Global Constraints

- **Never push without explicit user approval.** All work is local until the single gated push in Task 11.
- Work happens on `pediment-dev-flow-review` rebased onto `origin/main`; the gated push is `git push origin HEAD:main`. No new branches/worktrees.
- **WP floor 6.9** (`WordPress/WordPress#6.9` pin) — spike claims re-verified on 6.9 on 2026-07-30, all five hold.
- **Version lands at exactly 3.0.0**: the Task 2 commit carries `Release-As: 3.0.0`.
- **Never rename stored data**: options (`pediment_ai_*`), DB tables (`wp_pediment_ai_*`), and transients keep their names — renaming would silently orphan API keys and chat history on every existing install. Only code identifiers, user-visible strings, and file/asset names rename.
- **Asset regex safety**: the plugin's PUC watches `/pediment-plugin\.zip$/` after rename. v3 tags must carry NO asset named `pediment.zip` or `pediment-ai.zip` (old 2.4.x theme/plugin updaters watch those; matching them would offer wrong-typed updates).
- Conventional commits; the breaking commit uses `feat!:`.
- Every task ends with the affected test suite(s) green locally. wp-env is the project-local one (`npx wp-env`), never `@wordpress/env@latest`.
- The e2e theme-slug caveat: local full-suite e2e needs the fixture theme mounted at a pinned path (Task 8 pins it via mappings, same trick as step 1).
- Working directory: `/Users/jonas/conductor/workspaces/pediment/west-monroe`.

## File Structure (end state)

```
pediment/  (repo root — monorepo shell, no theme)
  plugin/
    plugin.php                     Plugin Name: Pediment; constants unchanged in name
    inc/                           procedural modules moved from theme: forms*, settings-page,
                                   register-blocks, icons, block-styles, hero-variants,
                                   layout-variations, mega-menu, nav-active, bootstrap, patterns
    src/                           PSR-4 Pediment\ (renamed from PedimentAi\)
    src/blocks/                    24 blocks moved from theme root src/blocks/
    editor/                        AI editor app (unchanged)
    templates/                     7 template HTML files moved from theme
    patterns/                      5 theme patterns nach hier + footer pattern (new)
    tokens/theme.json              the theme's theme.json, minus theme-only keys
    src/Tokens/Injector.php        wp_theme_json_data_theme base-merge + per-slug presets
    src/Templates/Registrar.php    register_block_template() loader for templates/*.html
    assets/                        theme assets (fonts, css, js, icons) moved
    wpml-config.xml                moved from theme root, contact-form entry dropped
    build/                         editor bundle + blocks build + blocks-manifest.php
  tests/fixtures/client-theme/     minimal standalone theme for wp-env/e2e (like the spike's)
  tools/                           lint scripts, paths updated to plugin/src/blocks
  .github/workflows/               ci.yml + release: single-artifact
DELETED from root: style.css, functions.php, theme.json, templates/, parts/, patterns/,
  src/, assets/, inc/, phpunit.xml.dist (moves under plugin/tests), screenshot.png
```

---

### Task 1: Preflight and baseline

**Files:** none modified.

**Interfaces:**
- Produces: HEAD = `origin/main` + this plan's docs commits; version files read 2.4.1. Every task builds on this.

- [ ] **Step 1: Rebase and verify**

```bash
git fetch origin && git rebase origin/main
grep -m1 "^Version" style.css                      # expect: Version: 2.4.1
grep -m1 "PEDIMENT_AI_VERSION" plugin/plugin.php   # expect: '2.4.1'
git status --porcelain                             # expect: clean
git log --oneline origin/main..HEAD                # expect: only docs(...) commits
```

If versions read 2.4.0, the v2.4.1 release PR (#65) has not been merged — STOP and report; this plan assumes 2.4.1 is the last theme-era release.

- [ ] **Step 2: Record the baseline sha in the ledger** (`git rev-parse HEAD`).

---

### Task 2: Remove the contact-form stack

**Files:**
- Delete: `inc/contact-form.php`, `src/blocks/contact-form/` (5 files), `tests/phpunit/BlockRender/ContactFormBlockTest.php`, `tests/phpunit/ContactForm/` (whole dir), `tests/e2e/contact-form.spec.ts`
- Modify: `functions.php` (drop the require at line 21), `patterns/contact-page.php` (rebuild on `pediment/form`), `wpml-config.xml` (drop the contact-form block entry), `tests/e2e/editor-blocks.spec.ts` + `tests/e2e/fixtures.php` (drop contact-form references)

**Interfaces:**
- Consumes: the decision recorded in the spec (§4.1 "Forms reconciliation"): forms wins, no shim.
- Produces: a tree with exactly 24 blocks and one form stack. Task 4 moves that stack.

- [ ] **Step 1: Delete the stack**

```bash
git rm -r inc/contact-form.php src/blocks/contact-form tests/phpunit/ContactForm tests/phpunit/BlockRender/ContactFormBlockTest.php tests/e2e/contact-form.spec.ts
```

- [ ] **Step 2: Drop the require and config entries**

Remove `require_once __DIR__ . '/inc/contact-form.php';` from `functions.php`. In `wpml-config.xml`, remove the `pediment/contact-form` block's element. In `tests/e2e/editor-blocks.spec.ts` and `tests/e2e/fixtures.php`, remove contact-form assertions/seed usages (read them; the spec files enumerate blocks — drop only the contact-form entries).

- [ ] **Step 3: Rebuild patterns/contact-page.php on pediment/form**

Read the current pattern. Recreate the same page layout with `pediment/form` + `pediment/form-field` blocks providing the equivalent fields (name, email, message, submit — mirror what the old block rendered; consult `src/blocks/form/block.json` and `form-field/block.json` for attribute names). Keep the pattern's header comment/slug/title unchanged.

- [ ] **Step 4: Build + run theme suites**

```bash
npm run build && composer install
npx wp-env start
npx wp-env run cli wp theme activate pediment
npx wp-env run tests-wordpress --env-cwd=wp-content/themes/pediment vendor/bin/phpunit
npm run lint:blocks && npm run lint:colors
```

Expected: PHPUnit green (fewer tests than 253 — the ContactForm ones are gone), lints green.

- [ ] **Step 5: Commit**

```bash
git add -A
git commit -m "feat!: remove the contact-form stack in favor of the forms engine

The parallel contact-form implementation (inc/contact-form.php, the
pediment/contact-form block, its tests) is removed. The forms engine
(destinations, secrets, SSRF, presets, retention) is the single form
system. Content using the old block must migrate to pediment/form.

Release-As: 3.0.0

Co-Authored-By: Claude <noreply@anthropic.com>"
```

---

### Task 3: Rename the plugin to Pediment

**Files:**
- Modify: `plugin/plugin.php` (header: `Plugin Name: Pediment`, `Plugin URI`/`Description`; constants KEEP their `PEDIMENT_AI_*` names), `plugin/composer.json` (autoload `"Pediment\\": "src/"`, name `bergert-digital/pediment-plugin`), every `plugin/src/**/*.php` + `plugin/wp-cli/*.php` + `plugin/tests/phpunit/**/*.php` (`namespace PedimentAi` → `namespace Pediment`, `use PedimentAi\` → `use Pediment\`, `\PedimentAi\` → `\Pediment\`), `plugin/src/Updater.php` (slug `'pediment'`, asset regex `/pediment-plugin\.zip$/`), text domain `'pediment-ai'` → `'pediment'` across `plugin/` PHP + `plugin/editor/` TSX, `plugin/package.json` name.
- NOT modified: option keys, table names, transient keys, `pediment-ai/v1` → rename to `pediment/v1` IS allowed and required (REST namespace ships in both PHP and editor JS in the same artifact — grep `plugin/src/Rest/`, `plugin/editor/` for `pediment-ai/v1` and change both sides).

**Interfaces:**
- Consumes: constants `PEDIMENT_AI_VERSION` etc. (unchanged names — release-please markers and stored-data rules depend on them).
- Produces: namespace `Pediment\`, PUC slug `pediment`, asset regex `/pediment-plugin\.zip$/`. Tasks 7/10 depend on these exact values.

- [ ] **Step 1: Mechanical rename**

```bash
grep -rl "PedimentAi" plugin/src plugin/wp-cli plugin/tests plugin/composer.json | xargs sed -i '' 's/PedimentAi/Pediment/g'
grep -rn "PedimentAi" plugin/ --include="*.php" --include="*.json" | grep -v vendor | grep -v node_modules   # expect: none
( cd plugin && composer dump-autoload )
```

Then hand-edit `plugin/plugin.php` header (`Plugin Name: Pediment`, `Description:` mention it is the Pediment engine + AI), `plugin/src/Updater.php` (`buildUpdateChecker( self::REPO_URL, $plugin_file, 'pediment' )`, `enableReleaseAssets( '/pediment-plugin\.zip$/' )`), and the text-domain sweep: `grep -rn "'pediment-ai'" plugin/src plugin/editor` — change i18n text domains only; REST namespace per Files note; leave option/table strings (`pediment_ai_*`, `wp_pediment_ai_*`) untouched — verify with `grep -c "pediment_ai_" plugin/src -r` before/after (count must not change).

- [ ] **Step 2: Update the UpdaterTest** — it asserts REPO_URL; extend it to assert the new slug and asset regex (read the test; assert via reflection or by parsing the file, matching its existing style).

- [ ] **Step 3: Run the plugin suite**

```bash
composer install -d plugin
npx wp-env run cli wp plugin activate pediment-ai
npx wp-env run tests-wordpress --env-cwd=wp-content/plugins/pediment-ai ./vendor/bin/phpunit
```

Expected: green (158+ tests). The mount path stays `plugins/pediment-ai` until Task 8 repins it — that is fine; slug-on-disk and PUC slug converge at release staging (Task 10).

- [ ] **Step 4: Commit** — `feat!: rename the plugin to Pediment (namespace, slug, asset)` + trailer. Body notes: stored data keys unchanged by design.

---

### Task 4: Move the forms engine and settings hub into the plugin

**Files:**
- Move (git mv, content verbatim except the require wiring): `inc/settings-page.php`, `inc/forms.php`, `inc/forms-storage.php`, `inc/forms-secrets.php`, `inc/forms-ssrf.php`, `inc/forms-template.php`, `inc/forms-presets.php`, `inc/forms-destinations.php`, `inc/forms-delivery.php`, `inc/forms-settings.php` → `plugin/inc/`
- Move: `tests/phpunit/Forms/` (13 files) + `tests/phpunit/Settings/` → `plugin/tests/phpunit/` (namespace per plugin suite conventions — these are procedural-function tests; adjust only the bootstrap-dependent bits)
- Modify: `functions.php` (drop the 10 requires), `plugin/plugin.php` (add the requires after autoload: `require_once PEDIMENT_AI_PLUGIN_DIR . '/inc/settings-page.php';` then the nine forms files in the same order functions.php had)
- Move: `wpml-config.xml` → `plugin/wpml-config.xml` (WPML and Polylang both read plugin-shipped configs)

**Interfaces:**
- Consumes: procedural functions keep their global names (`pediment_settings_register_tab()`, `pediment_form_*`); the plugin's AI settings tab already mounts via `pediment_settings_register_tab` — after this move the hub and the tab live in the same artifact.
- Produces: `plugin/inc/` as the procedural-module home; Task 6 adds more files there.

- [ ] **Step 1: git mv the files, wire requires** as listed. Guard: wrap the theme-era file's registration in `if ( ! function_exists( 'pediment_settings_register_tab' ) )`? NO — single source now; no guard needed. But DO check `plugin/src/Settings/Page.php` (AI settings): it previously registered its tab only when the theme's hub existed — read it; if it has a function_exists fallback creating its own options page, keep that fallback (client sites during migration may briefly run new plugin + old theme).

- [ ] **Step 2: Move the tests**, adjust their bootstrap: the theme suite's `tests/phpunit/bootstrap.php` loads the theme; the plugin suite's loads the plugin. The moved tests exercise functions now loaded by the plugin — they should pass unmodified apart from any explicit theme-path references (grep for `get_template_directory`, `switch_theme` in the moved files; report what you find and adjust minimally).

- [ ] **Step 3: Run BOTH suites** (theme suite shrinks, plugin suite grows; both green). Same invocations as Task 2/3.

- [ ] **Step 4: Commit** — `feat!: move the forms engine and settings hub into the plugin` + trailer.

---

### Task 5: Move blocks and the block build into the plugin

**Files:**
- Move: `src/blocks/` (24 blocks) → `plugin/src/blocks/`; `inc/register-blocks.php`, `inc/icons.php`, `inc/block-styles.php`, `inc/hero-variants.php`, `inc/layout-variations.php`, `inc/mega-menu.php`, `inc/nav-active.php` → `plugin/inc/`; `assets/icons/` → `plugin/assets/icons/`
- Modify: `plugin/package.json` (add the theme's block build: `wp-scripts build --webpack-src-dir=src/blocks --output-path=build/blocks` style — READ the theme's `package.json` `build` script and replicate its exact flags incl. `WP_EXPERIMENTAL_MODULES=true` and the blocks-manifest emission; the editor build keeps its current output), `plugin/inc/register-blocks.php` (paths: `PEDIMENT_AI_PLUGIN_DIR . '/build/blocks'` + manifest), `plugin/plugin.php` (require the seven moved modules), `functions.php` (drop those requires), root `package.json` (drop the block build; keep only what still applies or thin it to a proxy `"build": "cd plugin && npm run build"`), `tools/lint-colors.mjs` + `tools/lint-blocks.mjs` + any tool with a hardcoded `src/blocks` path (repoint to `plugin/src/blocks`)
- Move: `tests/phpunit/BlockRender/`, `tests/phpunit/BlockLoader/`, `tests/phpunit/IconsTest.php`, `tests/phpunit/BlockStylesTest.php`, `tests/phpunit/NavActive/`, `tests/phpunit/MegaMenu/` → `plugin/tests/phpunit/`

**Interfaces:**
- Consumes: `wp_register_block_metadata_collection()` + glob fallback in register-blocks.php (keep both, path-adjusted).
- Produces: blocks registered from the plugin regardless of active theme. Task 8's fixture theme has no blocks.

- [ ] **Step 1: Move + rewire** as listed. In `register-blocks.php`, every `get_template_directory()` becomes `PEDIMENT_AI_PLUGIN_DIR`; every `get_template_directory_uri()` becomes `PEDIMENT_AI_PLUGIN_URL` (trailing-slash semantics differ — verify each concatenation).
- [ ] **Step 2: Build from plugin** — `cd plugin && npm run build`; verify `plugin/build/blocks/<name>/block.json` exists for all 24 and `plugin/build/blocks-manifest.php` is emitted.
- [ ] **Step 3: Suites + lints green** (theme suite shrinks again; plugin suite grows; `node tools/lint-colors.mjs` against the new path).
- [ ] **Step 4: Commit** — `feat!: blocks and block build move into the plugin` + trailer.

---

### Task 6: Templates, patterns, footer, tokens, assets

**Files:**
- Create: `plugin/src/Templates/Registrar.php`, `plugin/src/Tokens/Injector.php`, `plugin/patterns/footer.php`
- Move: `templates/*.html` (7) → `plugin/templates/`; `patterns/*.php` (5) → `plugin/patterns/`; `inc/patterns.php` + `inc/bootstrap.php` → `plugin/inc/`; `theme.json` → `plugin/tokens/theme.json`; `assets/` (fonts/css/js) → `plugin/assets/`
- Modify: `plugin/plugin.php` (wire the new modules), `functions.php` (strip to nothing but the theme-support shims that remain until Task 7 deletes it), `parts/footer.html` → content becomes `plugin/patterns/footer.php` (deleted from parts/)

**Interfaces:**
- Produces: `Pediment\Templates\Registrar::register()` and `Pediment\Tokens\Injector::register()`, both hooked from `Bootstrap` or `plugin.php` on `init` / filter registration. The fixture theme (Task 8) relies on these.

- [ ] **Step 1: Templates registrar** — exact shape (adapt namespacing to the plugin's conventions):

```php
namespace Pediment\Templates;

class Registrar {
	private const TEMPLATES = array(
		'404'        => '404 (Pediment)',
		'archive'    => 'Archive (Pediment)',
		'front-page' => 'Front Page (Pediment)',
		'home'       => 'Home (Pediment)',
		'index'      => 'Index (Pediment)',
		'page'       => 'Page (Pediment)',
		'single'     => 'Single (Pediment)',
	);

	public static function register(): void {
		add_action( 'init', array( self::class, 'register_templates' ) );
	}

	public static function register_templates(): void {
		foreach ( self::TEMPLATES as $slug => $title ) {
			$file = PEDIMENT_AI_PLUGIN_DIR . '/templates/' . $slug . '.html';
			if ( ! is_readable( $file ) ) {
				continue;
			}
			register_block_template(
				'pediment//' . $slug,
				array(
					'title'   => $title,
					'content' => file_get_contents( $file ),
				)
			);
		}
	}
}
```

Template content referencing `parts/footer.html` (`<!-- wp:template-part {"slug":"footer"...} /-->`) must be rewritten in the moved template files to `<!-- wp:pattern {"slug":"pediment/footer"} /-->` — grep all 7 for `template-part` first; the header part reference STAYS (header is DB-seeded by `bootstrap.php`; verify `bootstrap.php` still seeds a `wp_template_part` and works when the active theme is not pediment — its tax terms reference the theme slug: read it and parameterize the `wp_theme` term to `get_stylesheet()`).

- [ ] **Step 2: Footer pattern** — `plugin/patterns/footer.php` registering slug `pediment/footer` with the current `parts/footer.html` markup (keep the placeholder copy for now — the client-content question is a later cleanup; note it in the report). `inc/patterns.php` moves and its registration paths update; add footer to it in the same style as existing patterns.

- [ ] **Step 3: Tokens injector** — the spike-proven mechanism, productionized:

```php
namespace Pediment\Tokens;

class Injector {
	public static function register(): void {
		add_filter( 'wp_theme_json_data_theme', array( self::class, 'inject' ) );
	}

	public static function inject( $theme_json ) {
		$tokens = json_decode( file_get_contents( PEDIMENT_AI_PLUGIN_DIR . '/tokens/theme.json' ), true );
		if ( ! is_array( $tokens ) ) {
			return $theme_json;
		}
		$base   = new \WP_Theme_JSON_Data( $tokens, 'theme' );
		$client = $theme_json->get_data();
		$client = self::merge_presets_by_slug( $base->get_data(), $client );
		return $base->update_with( $client );
	}
	// merge_presets_by_slug: for each preset array under settings (color.palette,
	// typography.fontFamilies, typography.fontSizes, spacing.spacingSizes,
	// color.gradients, color.duotone), merge client entries over base per slug
	// (replace matching slug, append new); write the merged array into the client
	// config so update_with()'s wholesale replace carries the union.
	// Implement with the normalize helper from the spike (handles ['theme'=>[...]]
	// origin-keyed vs flat arrays): .context/spike-plugin-theme/spike-plugin/spike-plugin.php
}
```

Implement `merge_presets_by_slug` fully (the spike file contains the working palette version; generalize over the six preset paths listed). **Critical adjustments to `plugin/tokens/theme.json`** (edit the moved file): (a) `version` must be whatever the file already declares (2) — `WP_Theme_JSON_Data` migrates; (b) any `file:./assets/...` font `src` references break outside a theme — rewrite them at inject time by replacing the literal string `file:./assets/` with `PEDIMENT_AI_PLUGIN_URL . 'assets/'` in the decoded array (walk `typography.fontFamilies[*].fontFace[*].src`); (c) `templateParts`/`customTemplates` keys are theme-only — strip them from the file.

- [ ] **Step 4: PHPUnit for the injector** — new `plugin/tests/phpunit/Tokens/InjectorTest.php`: client palette slug overrides plugin's; plugin-only slug survives; font-face src rewritten to plugin URL; `ThemeJsonTest.php` (moved from theme suite) adjusted to read via `wp_get_global_settings()` instead of theme file. Move remaining theme-suite tests that survive (`Patterns/`, `Templates/HomeTemplateTest.php` — repoint to registered-template lookup, `Bootstrap/BootstrapTest.php`, `ThemeSupportTest.php` → decide per test: theme-support assertions about the *theme* die in Task 7; assertions about plugin behavior move).

- [ ] **Step 5: Asset enqueue** — the theme enqueued CSS/JS via `functions.php`; move those enqueues into a `plugin/inc/assets.php` (`wp_enqueue_style/script` with `PEDIMENT_AI_PLUGIN_URL`), required from `plugin.php`. Read `functions.php` for the exact handles/files; preserve them.

- [ ] **Step 6: Suites green; commit** — `feat!: templates, patterns, tokens and assets ship from the plugin` + trailer.

---

### Task 7: Retire the theme

**Files:**
- Delete: `style.css`, `functions.php`, `theme.json` (already moved), `templates/`, `parts/`, `patterns/`, `src/`, `assets/`, `inc/` (whatever remains: `ThemeUpdater.php` and empties), `screenshot.png`, `phpcs.xml.dist` (root — plugin has its own), `phpunit.xml.dist` (root), `tests/phpunit/` (remnants — everything live has moved), `playwright.config.ts` + `tests/e2e/` → MOVED to plugin (next task handles e2e; this task moves the files: `tests/e2e/` → `plugin/tests/e2e-theme/` as a holding name, `playwright.config.ts` merged into plugin's)
- Modify: root `package.json` (thin to orchestration scripts), `composer.json` (root: drop theme deps — keep only if tools need it; plugin/composer.json is the real one), `.distignore` (root one becomes irrelevant — build stages only from `plugin/`), `tools/check-folder-name.mjs` (still useful for wp-env — keep)

**Interfaces:**
- Consumes: everything previously moved. This task is deletion + orchestration cleanup only — nothing new lands here.
- Produces: a repo whose only shippable content is `plugin/`.

- [ ] **Step 1: Verify nothing live remains** — `grep -rn "require" functions.php` must list nothing that still exists; then `git rm` the list above.
- [ ] **Step 2: ThemeUpdater is deleted WITH intent** — confirm `inc/ThemeUpdater.php` has no remaining references (`grep -rn "ThemeUpdater" . --exclude-dir=node_modules --exclude-dir=vendor --exclude-dir=.git`), then remove. 2.4.x sites keep their bundled copy; v3 has no theme to update.
- [ ] **Step 3: Root package.json** becomes: `"build": "cd plugin && npm run build"`, `"env:start"/"env:stop"` (wp-env still runs from root config), `"e2e": "cd plugin && npm run e2e"`, lint proxies. Delete root `node_modules` remnants from scripts that died.
- [ ] **Step 4: Commit** — `feat!: retire the parent theme; the plugin is the product` + trailer.

---

### Task 8: Fixture client theme, wp-env, and e2e repoint

**Files:**
- Create: `tests/fixtures/client-theme/style.css` (`Theme Name: Pediment Fixture Client`, `Text Domain: pediment-fixture`, no Template header), `tests/fixtures/client-theme/theme.json` (version 2, minimal — one `color.palette` override slug to prove the injector), `tests/fixtures/client-theme/templates/index.html` (minimal post-content group, like the spike's)
- Modify: `.wp-env.json` — mappings become: `"wp-content/themes/pediment-fixture": "./tests/fixtures/client-theme"`, `"wp-content/plugins/pediment-ai": "./plugin"` (mount path keeps the old dir name locally; the plugin's on-disk slug in production comes from the zip staging dir — pinning the local mount to the historical name avoids re-provisioning churn; CI activates by that path), uploads + mu-plugin mappings — the mu-plugin `plugin/tests/fixtures/mu-activate-theme.php` must now activate `pediment-fixture` (edit it)
- Modify: `plugin/tests/e2e-theme/` specs + `plugin/playwright.config.ts` — merge the theme e2e suite into the plugin's Playwright setup (one config, two test dirs or one merged dir `plugin/tests/e2e/`), update every selector/assumption that referenced theme-slug paths (`themes/pediment`) or the old activation flow; `tests/e2e/global-setup.ts` + `fixtures.php` move with them and seed via the fixture theme

**Interfaces:**
- Consumes: Registrar/Injector from Task 6 (the fixture theme has no templates except index — plugin templates must serve pages).
- Produces: the local + CI environment that all remaining verification uses.

- [ ] **Step 1: Write the fixture theme** (3 files, content per spike's `spike-client` adapted: palette override of one real Pediment token slug, e.g. override `foreground`).
- [ ] **Step 2: Rewire .wp-env.json + mu-plugin**; `npx wp-env start` (fresh: `npx wp-env destroy` first since mounts changed), `wp theme activate pediment-fixture`, `wp plugin activate pediment-ai`.
- [ ] **Step 3: Manual smoke before e2e** — create a page, curl it: plugin `page` template renders (grep a template-specific class), footer pattern present, fixture override wins for the overridden slug, plugin tokens present otherwise. This re-runs the spike against the REAL plugin — quote the output.
- [ ] **Step 4: Repoint and run the merged e2e suite** — expect breakage in specs that asserted theme internals; fix selectors/product-level assertions, delete assertions that only made sense for a theme artifact (each deletion listed in the report with one line why). `edit-render-parity.spec.ts` must survive — it guards the blocks, which all still ship.
- [ ] **Step 5: Full local gate**: plugin PHPUnit + full e2e green. Commit — `feat!: fixture client theme; e2e runs against the plugin-served site` + trailer.

---

### Task 9: Release pipeline and CI for a single artifact

**Files:**
- Modify: `.github/workflows/build-release-zip.yml` — delete the theme steps; the plugin sequence stages into `stage-plugin/pediment/` (dir renamed: installs as `plugins/pediment`) and uploads `pediment-plugin.zip`; version sed unchanged
- Modify: `.github/workflows/ci.yml` — jobs collapse: `phpcs` (plugin), `lint-blocks` (tools against plugin/src/blocks), `phpunit` (plugin suite), `e2e` (merged suite). Theme-specific jobs die. Activation steps use the Task 8 mounts.
- Modify: `release-please-config.json` — `extra-files: ["plugin/plugin.php"]` (style.css is gone)
- Modify: `.distignore` handling — only `plugin/.distignore` matters; verify it excludes `tests/`, `e2e`, `tokens/` must SHIP (templates/, patterns/, tokens/, wpml-config.xml, inc/ all ship — check none are excluded)

**Interfaces:**
- Produces: tag → single `pediment-plugin.zip` whose top-level dir is `pediment/`. Task 10 verifies.

- [ ] **Step 1: Rewrite the workflows** as described (read each first; keep the TAG mechanism and chain intact from step 1's work).
- [ ] **Step 2: Local dry-run** — replicate the staging rsync + zip locally; verify: top dir `pediment/`, contains `plugin.php`, `build/blocks/`, `build/blocks-manifest.php`, `templates/`, `patterns/`, `tokens/theme.json`, `wpml-config.xml`, `inc/`, `vendor/`, `src/`; contains NO `editor/`, `tests/`, `node_modules/`. Quote the listing.
- [ ] **Step 3: YAML-validate; commit** — `feat(release): one artifact — pediment-plugin.zip, installs as plugins/pediment` + trailer.

---

### Task 10: Docs sweep

**Files:** `AGENTS.md`, `README.md`, `docs/VISION.md`, `docs/STANDARDS.md`, `docs/client-blocks.md` (stale since the starter-theme era — rewrite or delete; recommend delete with its useful content folded into `docs/blocks.md`), `plugin/README.md`, `plugin/AGENTS.md`, `.claude/commands/*.md` (mount-parent/serve-changes: the "parent theme" framing dies — update or mark deprecated), `.conductor/settings.toml` (build chain now plugin-only), `docs/BACKLOG.md` (close items that step 2 resolved; the memory-noted deferred minors: `--optimize-autoloader` now trivial to add in the single build — DO it here; the CI activation-container no-op — resolve while touching ci.yml in Task 9, note here)

- [ ] **Step 1: Sweep with the same rules as step 1's docs task**: current commands only, main-only, one artifact, fixture-theme dev flow documented (`wp theme activate pediment-fixture`). Grep-verify no stale references: `grep -rn "pediment\.zip\|parent theme\|Template: pediment\|themes/pediment[^-]" AGENTS.md README.md docs/ plugin/README.md plugin/AGENTS.md .claude/ .conductor/ | grep -v superpowers` — remaining hits must be historical prose only.
- [ ] **Step 2: Commit** — `docs: describe the plugin-is-the-product layout` + trailer.

---

### Task 11: GATE — push, CI, release v3.0.0, verify

- [ ] **Step 1: Present the stack** (`git log --oneline origin/main..HEAD`, diffstat) and STOP for approval. Push: `git push origin HEAD:main` (+ `git push origin HEAD` to keep the Conductor branch ref alive).
- [ ] **Step 2: Watch CI** (background `gh run watch`); on silence, check the org Actions budget first.
- [ ] **Step 3: Release PR** must read **3.0.0** (the `Release-As` from Task 2) and bump only `plugin/plugin.php` + manifest + changelog. User merges.
- [ ] **Step 4: Verify the release**:

```bash
gh release view v3.0.0 --repo Bergert-Digital/pediment --json assets -q '[.assets[].name] | join(", ")'
# expect EXACTLY: pediment-plugin.zip  (no pediment.zip, no pediment-ai.zip — Global Constraints)
gh release download v3.0.0 --repo Bergert-Digital/pediment -p '*.zip' -O /tmp/pediment-plugin.zip
unzip -l /tmp/pediment-plugin.zip | grep -m1 "pediment/plugin.php"
unzip -p /tmp/pediment-plugin.zip pediment/plugin.php | grep -m1 "Version:"      # 3.0.0
unzip -l /tmp/pediment-plugin.zip | grep -cE "pediment/(editor|tests)/"          # 0
```

- [ ] **Step 5: Report** — including the standing consequence: 2.4.x sites stop receiving updates by design until step-6 migration; the manual per-site swap installs `pediment-plugin.zip` + a client theme.

---

## Out of scope

- Child-template repo changes (steps 3–5), the seeder (step 3), LanguageProvider (step 4), `/start` (step 5), Workation migration (step 6)
- wpml-config.xml *generation* from block.json (lands with the language work)
- Replacing the footer pattern's placeholder client copy (follows with the seeder/scaffolder)
- Any 2.x maintenance release (if needed: branch from the v2.4.1 tag manually)
