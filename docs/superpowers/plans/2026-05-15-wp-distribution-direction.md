# WP Distribution Direction Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Stand up the `wp-starter-child-theme` repo, decisively retire `wp-client-template`, and add installable-zip release pipelines to the plugin, parent theme, and child theme.

**Architecture:** Three independent workstreams executed in order B → C → D. B creates a new sibling git repo seeded from `wp-client-template/web/app/themes/client-theme/` plus the parent theme's test harness. C adds a deprecation banner and archives the old repo. D extends `wp-starter-ai`'s existing `workflow_dispatch` release.yml with a zip-asset step and replicates that shape for both themes.

**Tech Stack:** WordPress block theme (PHP 8.1, `@wordpress/scripts`, `theme.json` v2), PHPUnit via wp-env tests container, Playwright E2E, GitHub Actions, `gh` CLI.

**Spec:** `docs/superpowers/specs/2026-05-15-wp-distribution-direction-design.md`

**Worktree note:** This plan creates a brand-new repo (B) and makes additive, low-conflict changes to three existing repos (C, D — config/workflow files only). Per the user-level worktree policy, additive multi-repo config work like this runs directly on each repo's `development` branch; no worktree. No schema/migration tasks exist in this plan.

**Remote-action protocol:** Tasks B9, C2, C3, and D5 perform shared-state GitHub operations. For each, show the exact command and STOP for explicit user go-ahead before running it. Never run these silently.

**Naming locked:**
- New repo path: `/Users/jonas/Entwicklung/wp-starter-child-theme` (sibling checkout).
- GitHub: `github.com/Bergert-Digital/wp-starter-child-theme`.
- Theme Name `Starter Child Theme`; `Template: wp-starter-theme`; Text Domain `starter-child`; `@package StarterChild`; version `0.1.0`.
- Block: `client/promo-banner` → `starter-child/promo-banner`; CSS base class `client-promo-banner` → `starter-child-promo-banner`.
- Block-loader function `starter_child_register_blocks()` — deliberately NOT `starter_register_blocks` (the parent defines that; both functions.php files load for a child theme and an identical name would fatal-redeclare).

---

## Workstream B — `wp-starter-child-theme` repo

### Task B1: Scaffold repo skeleton

**Files:**
- Create: `/Users/jonas/Entwicklung/wp-starter-child-theme/.gitignore`
- Create: `/Users/jonas/Entwicklung/wp-starter-child-theme/assets/fonts/.gitkeep` (empty)

- [ ] **Step 1: Create the repo directory and init git**

Run:
```bash
mkdir -p /Users/jonas/Entwicklung/wp-starter-child-theme/assets/fonts \
         /Users/jonas/Entwicklung/wp-starter-child-theme/src/blocks/promo-banner \
         /Users/jonas/Entwicklung/wp-starter-child-theme/tests/phpunit \
         /Users/jonas/Entwicklung/wp-starter-child-theme/tests/e2e \
         /Users/jonas/Entwicklung/wp-starter-child-theme/.github/workflows
cd /Users/jonas/Entwicklung/wp-starter-child-theme && git init -b development
```
Expected: `Initialized empty Git repository`

- [ ] **Step 2: Create `.gitignore`**

```
/node_modules/
/vendor/
/build/
/playwright-report/
/test-results/
.DS_Store
```

- [ ] **Step 3: Create `assets/fonts/.gitkeep`** (empty file)

Run: `touch /Users/jonas/Entwicklung/wp-starter-child-theme/assets/fonts/.gitkeep`

- [ ] **Step 4: Commit**

```bash
cd /Users/jonas/Entwicklung/wp-starter-child-theme
git add .gitignore assets/fonts/.gitkeep
git commit -m "chore: scaffold wp-starter-child-theme repo skeleton"
```

---

### Task B2: Theme identity files

**Files:**
- Create: `/Users/jonas/Entwicklung/wp-starter-child-theme/style.css`
- Create: `/Users/jonas/Entwicklung/wp-starter-child-theme/theme.json`
- Create: `/Users/jonas/Entwicklung/wp-starter-child-theme/package.json`
- Create: `/Users/jonas/Entwicklung/wp-starter-child-theme/tsconfig.json`

- [ ] **Step 1: Create `style.css`**

```css
/*
Theme Name: Starter Child Theme
Theme URI: https://github.com/Bergert-Digital/wp-starter-child-theme
Template: wp-starter-theme
Author: Jonas Bergert
Author URI: https://bergert.digital
Description: Agency starting point — a child theme of the Starter Theme. Fork this, rename it, add your blocks and theme.json overrides, and ship per client.
Version: 0.1.0
Requires at least: 6.4
Requires PHP: 8.1
License: GPL-2.0-or-later
License URI: https://www.gnu.org/licenses/gpl-2.0.html
Text Domain: starter-child
*/
```

- [ ] **Step 2: Create `theme.json`** (verbatim copy of the client-theme palette — generic, intended as an override starting point)

```json
{
  "$schema": "https://schemas.wp.org/trunk/theme.json",
  "version": 2,
  "settings": {
    "color": {
      "palette": [
        { "slug": "primary",          "color": "#0F172A", "name": "Primary" },
        { "slug": "accent",           "color": "#4F46E5", "name": "Accent" },
        { "slug": "accent-hover",     "color": "#4338CA", "name": "Accent hover" },
        { "slug": "surface",          "color": "#FFFFFF", "name": "Surface" },
        { "slug": "surface-elevated", "color": "#F8FAFC", "name": "Surface elevated" },
        { "slug": "surface-sunken",   "color": "#F1F5F9", "name": "Surface sunken" },
        { "slug": "text",             "color": "#0F172A", "name": "Text" },
        { "slug": "text-muted",       "color": "#64748B", "name": "Text muted" },
        { "slug": "border",           "color": "#E2E8F0", "name": "Border" },
        { "slug": "border-strong",    "color": "#CBD5E1", "name": "Border strong" }
      ]
    },
    "typography": {
      "fontFamilies": [
        { "slug": "body",    "name": "Body",    "fontFamily": "system-ui, -apple-system, 'Segoe UI', Roboto, sans-serif" },
        { "slug": "heading", "name": "Heading", "fontFamily": "system-ui, -apple-system, 'Segoe UI', Roboto, sans-serif" },
        { "slug": "mono",    "name": "Mono",    "fontFamily": "ui-monospace, SFMono-Regular, Menlo, monospace" }
      ]
    }
  }
}
```

- [ ] **Step 3: Create `package.json`**

```json
{
  "name": "wp-starter-child-theme",
  "version": "0.1.0",
  "private": true,
  "scripts": {
    "start": "wp-scripts start --webpack-src-dir=src/blocks --output-path=build/blocks",
    "build": "wp-scripts build --webpack-src-dir=src/blocks --output-path=build/blocks",
    "lint:js": "wp-scripts lint-js src/",
    "e2e": "playwright test",
    "env:start": "wp-env start",
    "env:stop": "wp-env stop"
  },
  "devDependencies": {
    "@babel/runtime": "^7.29.2",
    "@playwright/test": "^1.45.0",
    "@wordpress/env": "^10.0.0",
    "@wordpress/scripts": "^28.0.0",
    "typescript": "^5.4.0"
  }
}
```

- [ ] **Step 4: Create `tsconfig.json`**

```json
{
  "extends": "@wordpress/scripts/tsconfig.json",
  "compilerOptions": { "jsx": "react-jsx", "noEmit": true },
  "include": ["src/**/*.ts", "src/**/*.tsx"],
  "exclude": ["node_modules", "build"]
}
```

- [ ] **Step 5: Commit**

```bash
cd /Users/jonas/Entwicklung/wp-starter-child-theme
git add style.css theme.json package.json tsconfig.json
git commit -m "feat: theme identity (style.css, theme.json, package.json, tsconfig)"
```

---

### Task B3: Child bootstrap `functions.php` (TDD via the block loader)

The only logic in `functions.php` is the block auto-loader. Mirror the parent's testable `starter_register_blocks` pattern but under a non-colliding name.

**Files:**
- Create: `/Users/jonas/Entwicklung/wp-starter-child-theme/functions.php`
- Test: `/Users/jonas/Entwicklung/wp-starter-child-theme/tests/phpunit/AutoLoaderTest.php` (test written in B5 once the harness exists; this task creates the implementation, B5 adds the failing test then proves it passes — see B5 Step 4)

- [ ] **Step 1: Create `functions.php`**

```php
<?php
/**
 * Starter Child Theme bootstrap.
 *
 * Fork target. The Starter Theme (parent) is read-only; your blocks,
 * theme.json overrides and child-specific PHP live here.
 *
 * @package StarterChild
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! defined( 'STARTER_CHILD_DIR' ) ) {
	define( 'STARTER_CHILD_DIR', __DIR__ );
}
if ( ! defined( 'STARTER_CHILD_VERSION' ) ) {
	define( 'STARTER_CHILD_VERSION', '0.1.0' );
}

/**
 * Register every block in the given directory (defaults to build/blocks).
 *
 * Named distinctly from the parent's starter_register_blocks() — both
 * functions.php files load for a child theme, so an identical name would
 * fatal-redeclare.
 *
 * @param string|null $base_dir Directory containing block subfolders.
 */
function starter_child_register_blocks( $base_dir = null ) {
	if ( null === $base_dir || '' === $base_dir ) {
		$base_dir = STARTER_CHILD_DIR . '/build/blocks';
	}

	if ( ! is_dir( $base_dir ) ) {
		return;
	}

	$registry = WP_Block_Type_Registry::get_instance();
	foreach ( glob( $base_dir . '/*', GLOB_ONLYDIR ) as $block_dir ) {
		$manifest = $block_dir . '/block.json';
		if ( ! file_exists( $manifest ) ) {
			continue;
		}
		$meta = json_decode( file_get_contents( $manifest ), true );
		if ( is_array( $meta ) && isset( $meta['name'] ) && $registry->is_registered( $meta['name'] ) ) {
			continue;
		}
		register_block_type( $block_dir );
	}
}

add_action(
	'init',
	function () {
		starter_child_register_blocks();
	}
);

add_action(
	'wp_enqueue_scripts',
	function () {
		wp_enqueue_style(
			'starter-child',
			get_stylesheet_directory_uri() . '/style.css',
			array(),
			wp_get_theme()->get( 'Version' )
		);
	}
);
```

- [ ] **Step 2: Commit**

```bash
cd /Users/jonas/Entwicklung/wp-starter-child-theme
git add functions.php
git commit -m "feat: child bootstrap with starter_child_register_blocks loader"
```

(The failing-test-first cycle for this loader is Task B5, which sets up the PHPUnit harness it depends on.)

---

### Task B4: Dev tooling (composer + phpcs)

**Files:**
- Create: `/Users/jonas/Entwicklung/wp-starter-child-theme/composer.json`
- Create: `/Users/jonas/Entwicklung/wp-starter-child-theme/phpcs.xml.dist`

- [ ] **Step 1: Create `composer.json`** (dev-only; themes have no runtime composer deps)

```json
{
  "name": "bergert/wp-starter-child-theme",
  "description": "Agency starter child theme for wp-starter-theme",
  "type": "wordpress-theme",
  "license": "GPL-2.0-or-later",
  "require": {
    "php": ">=8.1"
  },
  "require-dev": {
    "dealerdirect/phpcodesniffer-composer-installer": "^1.0",
    "wp-coding-standards/wpcs": "^3.1",
    "phpcompatibility/phpcompatibility-wp": "^2.1",
    "yoast/phpunit-polyfills": "^3.0",
    "phpunit/phpunit": "^9.6",
    "doctrine/instantiator": "^1.5"
  },
  "config": {
    "allow-plugins": {
      "dealerdirect/phpcodesniffer-composer-installer": true,
      "composer/installers": true
    }
  },
  "scripts": {
    "lint": "phpcs",
    "lint:fix": "phpcbf",
    "test": "phpunit"
  }
}
```

- [ ] **Step 2: Create `phpcs.xml.dist`** (adapted from parent — drops the parent-only `inc/` path and the parent's custom `tools/phpcs-sniffs` ruleset which does not exist here)

```xml
<?xml version="1.0"?>
<ruleset name="Starter Child Theme">
  <description>Coding standards for wp-starter-child-theme.</description>

  <file>functions.php</file>
  <file>src/blocks/</file>

  <exclude-pattern>build/*</exclude-pattern>
  <exclude-pattern>node_modules/*</exclude-pattern>
  <exclude-pattern>vendor/*</exclude-pattern>

  <arg name="extensions" value="php"/>
  <arg name="colors"/>
  <arg value="ps"/>

  <rule ref="WordPress">
    <exclude name="WordPress.Files.FileName"/>
    <exclude name="Squiz.Commenting.FunctionComment.Missing"/>
    <exclude name="Squiz.Commenting.ClassComment.Missing"/>
    <exclude name="Squiz.Commenting.FileComment.MissingPackageTag"/>
    <exclude name="Generic.Commenting.DocComment.MissingShort"/>
    <exclude name="Universal.Operators.DisallowShortTernary.Found"/>
    <exclude name="Universal.NamingConventions.NoReservedKeywordParameterNames.defaultFound"/>
    <exclude name="WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents"/>
    <exclude name="Generic.CodeAnalysis.UnusedFunctionParameter"/>
    <exclude name="WordPress.Security.EscapeOutput.OutputNotEscaped"/>
  </rule>

  <config name="testVersion" value="8.1-"/>
  <rule ref="PHPCompatibilityWP"/>
</ruleset>
```

- [ ] **Step 3: Install composer dev deps and verify lint runs**

Run:
```bash
cd /Users/jonas/Entwicklung/wp-starter-child-theme
composer install --prefer-dist --no-progress
composer lint
```
Expected: phpcs runs and reports 0 errors on `functions.php` (the loader was written to WPCS style). If it reports violations, fix them in `functions.php` until clean.

- [ ] **Step 4: Commit**

```bash
cd /Users/jonas/Entwicklung/wp-starter-child-theme
git add composer.json phpcs.xml.dist
git commit -m "build: dev tooling — composer dev deps + phpcs ruleset"
```

---

### Task B5: PHPUnit harness + block-loader test (TDD)

**Files:**
- Create: `/Users/jonas/Entwicklung/wp-starter-child-theme/phpunit.xml.dist`
- Create: `/Users/jonas/Entwicklung/wp-starter-child-theme/.wp-env.json`
- Create: `/Users/jonas/Entwicklung/wp-starter-child-theme/tests/phpunit/bootstrap.php`
- Create: `/Users/jonas/Entwicklung/wp-starter-child-theme/tests/phpunit/SmokeTest.php`
- Create: `/Users/jonas/Entwicklung/wp-starter-child-theme/tests/phpunit/AutoLoaderTest.php`

- [ ] **Step 1: Create `phpunit.xml.dist`**

```xml
<?xml version="1.0"?>
<phpunit
  bootstrap="tests/phpunit/bootstrap.php"
  backupGlobals="false"
  colors="true"
  beStrictAboutCoversAnnotation="true"
  beStrictAboutOutputDuringTests="true"
  beStrictAboutTestsThatDoNotTestAnything="true"
  beStrictAboutTodoAnnotatedTests="true"
  verbose="true">
  <testsuites>
    <testsuite name="child-theme">
      <directory>tests/phpunit/</directory>
    </testsuite>
  </testsuites>
</phpunit>
```

- [ ] **Step 2: Create `.wp-env.json`** (parent + plugin mapped from sibling checkouts; child is `.`)

```json
{
  "core": "WordPress/WordPress#6.5",
  "phpVersion": "8.1",
  "themes": [".", "../wp-starter-theme"],
  "plugins": ["../wp-starter-ai"],
  "config": {
    "WP_DEBUG": true,
    "WP_DEBUG_LOG": true,
    "SCRIPT_DEBUG": true
  }
}
```

- [ ] **Step 3: Create `tests/phpunit/bootstrap.php`** (switches to the child theme; loads child functions.php — the loader is self-contained and needs no parent code at unit-test time)

```php
<?php
/**
 * PHPUnit bootstrap: loads WP test harness and the child theme.
 *
 * Runs inside wp-env's tests-wordpress container.
 */

$_tests_dir = getenv( 'WP_TESTS_DIR' );
if ( ! $_tests_dir ) {
	$_tests_dir = rtrim( sys_get_temp_dir(), '/\\' ) . '/wordpress-tests-lib';
}

if ( ! defined( 'WP_TESTS_PHPUNIT_POLYFILLS_PATH' ) ) {
	define( 'WP_TESTS_PHPUNIT_POLYFILLS_PATH', dirname( __DIR__, 2 ) . '/vendor/yoast/phpunit-polyfills' );
}

require_once $_tests_dir . '/includes/functions.php';

tests_add_filter(
	'muplugins_loaded',
	function () {
		switch_theme( 'wp-starter-child-theme' );
		require_once dirname( __DIR__, 2 ) . '/functions.php';
	}
);

require $_tests_dir . '/includes/bootstrap.php';
```

- [ ] **Step 4: Write the failing block-loader test**

`tests/phpunit/AutoLoaderTest.php`:
```php
<?php

class AutoLoaderTest extends WP_UnitTestCase {
	public function test_loader_function_exists() {
		$this->assertTrue( function_exists( 'starter_child_register_blocks' ) );
	}

	public function test_loader_handles_missing_build_dir_gracefully() {
		starter_child_register_blocks( '/nonexistent/path' );
		$this->assertTrue( true );
	}

	public function test_loader_registers_blocks_from_build_dir() {
		$tmp = sys_get_temp_dir() . '/starter-child-test-blocks-' . uniqid();
		mkdir( $tmp . '/dummy-block', 0777, true );
		file_put_contents(
			$tmp . '/dummy-block/block.json',
			wp_json_encode(
				array(
					'apiVersion' => 3,
					'name'       => 'starter-child/dummy',
					'title'      => 'Dummy',
					'category'   => 'design',
					'attributes' => array( 'text' => array( 'type' => 'string', 'default' => '' ) ),
				)
			)
		);

		starter_child_register_blocks( $tmp );

		$registry = WP_Block_Type_Registry::get_instance();
		$this->assertTrue( $registry->is_registered( 'starter-child/dummy' ) );

		$registry->unregister( 'starter-child/dummy' );
	}
}
```

- [ ] **Step 5: Create `tests/phpunit/SmokeTest.php`**

```php
<?php

class SmokeTest extends WP_UnitTestCase {
	public function test_wordpress_is_loaded() {
		$this->assertTrue( function_exists( 'wp_get_theme' ) );
	}

	public function test_child_theme_is_active() {
		$this->assertSame( 'wp-starter-child-theme', wp_get_theme()->get_stylesheet() );
	}

	public function test_parent_template_is_starter_theme() {
		$this->assertSame( 'wp-starter-theme', wp_get_theme()->get_template() );
	}
}
```

- [ ] **Step 6: Start wp-env and run the suite (expect PASS — implementation from B3 already exists)**

Run:
```bash
cd /Users/jonas/Entwicklung/wp-starter-child-theme
npm install
npm run env:start
npx wp-env run tests-wordpress --env-cwd=wp-content/themes/wp-starter-child-theme vendor/bin/phpunit
```
Expected: all tests in `AutoLoaderTest` and `SmokeTest` PASS. If `test_parent_template_is_starter_theme` fails with the template unresolved, confirm `../wp-starter-theme` exists as a sibling checkout (it does in this environment) and that `.wp-env.json` `themes` includes it; re-run `npm run env:start --update`.

> Note: this task intentionally has the implementation (B3) precede the test. The loader is a faithful port of the parent's already-proven `starter_register_blocks`; the test here is a regression guard for the rename, and it cannot run until this task's harness exists. If you want a literal red→green, comment out the `add_action`/function body, watch `test_loader_function_exists` fail, then restore.

- [ ] **Step 7: Commit**

```bash
cd /Users/jonas/Entwicklung/wp-starter-child-theme
git add phpunit.xml.dist .wp-env.json tests/phpunit/
git commit -m "test: phpunit harness + block-loader and smoke coverage"
```

---

### Task B6: promo-banner example block (renamed namespace)

**Files:**
- Create: `/Users/jonas/Entwicklung/wp-starter-child-theme/src/blocks/promo-banner/block.json`
- Create: `/Users/jonas/Entwicklung/wp-starter-child-theme/src/blocks/promo-banner/index.tsx`
- Create: `/Users/jonas/Entwicklung/wp-starter-child-theme/src/blocks/promo-banner/edit.tsx`
- Create: `/Users/jonas/Entwicklung/wp-starter-child-theme/src/blocks/promo-banner/render.php`
- Create: `/Users/jonas/Entwicklung/wp-starter-child-theme/src/blocks/promo-banner/style.scss`

- [ ] **Step 1: Create `block.json`** (namespace + textdomain rebranded)

```json
{
  "$schema": "https://schemas.wp.org/trunk/block.json",
  "apiVersion": 3,
  "name": "starter-child/promo-banner",
  "title": "Promo Banner",
  "category": "design",
  "description": "Example child-theme block: a promotional banner with headline, body, and link. Replace or delete before shipping to a client.",
  "textdomain": "starter-child",
  "supports": { "html": false, "align": ["wide", "full"] },
  "attributes": {
    "headline": { "type": "string", "default": "" },
    "body":     { "type": "string", "default": "" },
    "linkText": { "type": "string", "default": "" },
    "linkUrl":  { "type": "string", "default": "" }
  },
  "editorScript": "file:./index.js",
  "style":        "file:./style-index.css",
  "render":       "file:./render.php"
}
```

- [ ] **Step 2: Create `index.tsx`**

```tsx
import { registerBlockType } from '@wordpress/blocks';
import metadata from './block.json';
import Edit from './edit';
import './style.scss';

registerBlockType(metadata.name, { edit: Edit });
```

- [ ] **Step 3: Create `edit.tsx`** (textdomain `starter-child`, CSS class rebranded)

```tsx
import { __ } from '@wordpress/i18n';
import { useBlockProps, RichText, InspectorControls } from '@wordpress/block-editor';
import { PanelBody, TextControl } from '@wordpress/components';

type Attrs = { headline: string; body: string; linkText: string; linkUrl: string };

export default function Edit({
  attributes, setAttributes,
}: { attributes: Attrs; setAttributes: (a: Partial<Attrs>) => void; }) {
  const blockProps = useBlockProps({ className: 'starter-child-promo-banner' });
  return (
    <>
      <InspectorControls>
        <PanelBody title={__('Promo banner', 'starter-child')}>
          <TextControl
            label={__('Link URL', 'starter-child')}
            value={attributes.linkUrl}
            onChange={(v) => setAttributes({ linkUrl: v })}
          />
        </PanelBody>
      </InspectorControls>
      <aside {...blockProps}>
        <RichText tagName="strong" value={attributes.headline} onChange={(v) => setAttributes({ headline: v })} placeholder={__('Headline…',  'starter-child')} />
        <RichText tagName="p"      value={attributes.body}     onChange={(v) => setAttributes({ body: v })}     placeholder={__('Body…',      'starter-child')} />
        <RichText tagName="span"   value={attributes.linkText} onChange={(v) => setAttributes({ linkText: v })} placeholder={__('Link text…', 'starter-child')} />
      </aside>
    </>
  );
}
```

- [ ] **Step 4: Create `render.php`** (CSS classes rebranded `client-` → `starter-child-`)

```php
<?php
/** @var array $attributes */

$headline  = isset( $attributes['headline'] ) ? (string) $attributes['headline'] : '';
$body      = isset( $attributes['body'] )     ? (string) $attributes['body']     : '';
$link_text = isset( $attributes['linkText'] ) ? (string) $attributes['linkText'] : '';
$link_url  = isset( $attributes['linkUrl'] )  ? (string) $attributes['linkUrl']  : '';

if ( '' === $headline && '' === $body ) {
	return '';
}

$wrapper = get_block_wrapper_attributes( array( 'class' => 'starter-child-promo-banner' ) );

ob_start();
?>
<aside <?php echo $wrapper; // phpcs:ignore ?>>
	<?php if ( '' !== $headline ) : ?>
		<strong class="starter-child-promo-banner__headline"><?php echo wp_kses_post( $headline ); ?></strong>
	<?php endif; ?>
	<?php if ( '' !== $body ) : ?>
		<p class="starter-child-promo-banner__body"><?php echo wp_kses_post( $body ); ?></p>
	<?php endif; ?>
	<?php if ( '' !== $link_text && '' !== $link_url ) : ?>
		<a class="starter-child-promo-banner__link" href="<?php echo esc_url( $link_url ); ?>"><?php echo wp_kses_post( $link_text ); ?></a>
	<?php endif; ?>
</aside>
<?php
echo ob_get_clean();
```

- [ ] **Step 5: Create `style.scss`** (CSS classes rebranded)

```scss
.starter-child-promo-banner {
  display: flex;
  flex-direction: column;
  gap: var(--wp--preset--spacing--10);
  padding: var(--wp--preset--spacing--30);
  background: var(--wp--preset--color--accent);
  color: var(--wp--preset--color--surface);
  border-radius: 0.5rem;

  &__headline { font-size: var(--wp--preset--font-size--lg); }
  &__body     { margin: 0; opacity: 0.95; }
  &__link     { align-self: flex-start; color: var(--wp--preset--color--surface); text-decoration: underline; }
}
```

- [ ] **Step 6: Build and verify the block compiles + lint passes**

Run:
```bash
cd /Users/jonas/Entwicklung/wp-starter-child-theme
npm run build
npm run lint:js
composer lint
```
Expected: `build/blocks/promo-banner/` is produced (index.js, style-index.css, block.json, render.php copied); `lint:js` and `composer lint` report no errors. Fix any reported issues before committing.

- [ ] **Step 7: Re-run PHPUnit to confirm the real block now auto-registers**

Run:
```bash
cd /Users/jonas/Entwicklung/wp-starter-child-theme
npx wp-env run tests-wordpress --env-cwd=wp-content/themes/wp-starter-child-theme vendor/bin/phpunit
```
Expected: still all green (the loader test used a temp dir; this confirms nothing regressed with a real built block present).

- [ ] **Step 8: Commit**

```bash
cd /Users/jonas/Entwicklung/wp-starter-child-theme
git add src/blocks/promo-banner/
git commit -m "feat: promo-banner example block (starter-child namespace)"
```

---

### Task B7: E2E harness

**Files:**
- Create: `/Users/jonas/Entwicklung/wp-starter-child-theme/playwright.config.ts`
- Create: `/Users/jonas/Entwicklung/wp-starter-child-theme/tests/e2e/smoke.spec.ts`

- [ ] **Step 1: Create `playwright.config.ts`** (wp-env default port 8888 — child repo has no custom port)

```ts
import { defineConfig, devices } from '@playwright/test';

export default defineConfig({
  testDir: './tests/e2e',
  fullyParallel: false,
  forbidOnly: !!process.env.CI,
  retries: process.env.CI ? 1 : 0,
  workers: 1,
  reporter: 'html',
  use: {
    baseURL: 'http://localhost:8888',
    trace: 'on-first-retry',
  },
  projects: [
    { name: 'chromium', use: { ...devices['Desktop Chrome'] } },
  ],
});
```

- [ ] **Step 2: Create `tests/e2e/smoke.spec.ts`** (front page renders with the child theme active)

```ts
import { test, expect } from '@playwright/test';

test('home page responds and is not a fatal error', async ({ page }) => {
  const response = await page.goto('/');
  expect(response?.status()).toBeLessThan(400);
  await expect(page.locator('body')).not.toContainText('There has been a critical error');
});
```

- [ ] **Step 3: Run E2E to verify the harness works**

Run:
```bash
cd /Users/jonas/Entwicklung/wp-starter-child-theme
npx playwright install --with-deps chromium
npx wp-env run cli wp theme activate wp-starter-child-theme
npm run e2e
```
Expected: the single smoke spec PASSES. If theme activation fails because the parent isn't registered, run `npx wp-env run cli wp theme list` and confirm both `wp-starter-theme` and `wp-starter-child-theme` are listed.

- [ ] **Step 4: Commit**

```bash
cd /Users/jonas/Entwicklung/wp-starter-child-theme
git add playwright.config.ts tests/e2e/
git commit -m "test: playwright e2e harness with front-page smoke spec"
```

---

### Task B8: README

**Files:**
- Create: `/Users/jonas/Entwicklung/wp-starter-child-theme/README.md`

- [ ] **Step 1: Create `README.md`**

````markdown
# wp-starter-child-theme

The agency starting point. A child theme of [wp-starter-theme](https://github.com/Bergert-Digital/wp-starter-theme). Fork or download as a zip, rename it, add your blocks and `theme.json` overrides, and push to your own git for per-client install.

## Install order on a fresh WordPress

WordPress has no automatic theme-dependency resolution, so order matters:

1. Upload and install the **parent**: `wp-starter-theme` zip (Appearance → Add New → Upload).
2. Upload and install **this child** theme zip.
3. **Activate the child** (`Starter Child Theme`).
4. Install the **wp-starter-ai** plugin zip any time (Plugins → Add New → Upload).

## First-fork rename checklist

Grep-replace these tokens with your client's identity before first client ship:

- `wp-starter-child-theme` → your theme slug (also rename the repo/directory)
- `Starter Child Theme` → your theme's display name (`style.css` `Theme Name`)
- `starter-child` → your text domain (in `style.css`, `functions.php`, `block.json`, `edit.tsx`, CSS classes)
- `StarterChild` → your PHP `@package` tag
- `starter_child_register_blocks` / `STARTER_CHILD_*` → your prefixed function/constant names

Then **replace or delete** `src/blocks/promo-banner/` — it's a worked example, not production content.

## Development

All three repos cloned side by side (`../wp-starter-theme`, `../wp-starter-ai`):

```bash
composer install
npm install
npm run env:start          # wp-env, mounts parent + plugin from siblings
npm run build              # build blocks
npm run e2e                # Playwright
npx wp-env run tests-wordpress --env-cwd=wp-content/themes/wp-starter-child-theme vendor/bin/phpunit
composer lint
```
````

- [ ] **Step 2: Commit**

```bash
cd /Users/jonas/Entwicklung/wp-starter-child-theme
git add README.md
git commit -m "docs: README — install order, fork checklist, dev commands"
```

---

### Task B9: Create remote and push (REMOTE — pause for go-ahead)

- [ ] **Step 1: Show the user the exact remote-creating command and STOP**

Present this block to the user and wait for explicit approval before running:
```bash
gh repo create Bergert-Digital/wp-starter-child-theme \
  --private \
  --source /Users/jonas/Entwicklung/wp-starter-child-theme \
  --remote origin \
  --description "Agency starter child theme for wp-starter-theme"
```
Ask: "Create this GitHub repo now? (private — say if you want public)."

- [ ] **Step 2: After approval, create the repo and push both branches**

```bash
cd /Users/jonas/Entwicklung/wp-starter-child-theme
git branch main
git push -u origin development
git push origin main
```
Expected: both branches on `origin`. Confirm with `gh repo view Bergert-Digital/wp-starter-child-theme --web` (or `git remote -v`).

---

## Workstream C — Retire `wp-client-template`

### Task C1: Deprecation banner commit

**Files:**
- Modify: `/Users/jonas/Entwicklung/wp-client-template/README.md` (prepend banner at top, line 1)

- [ ] **Step 1: Read the current README top**

Run: `head -5 /Users/jonas/Entwicklung/wp-client-template/README.md`
(Confirms what the banner is being prepended above; do not delete existing content.)

- [ ] **Step 2: Prepend this banner as the new top of `README.md`** (insert above the existing first line, followed by a blank line)

```markdown
> # ⚠️ DEPRECATED — this repository is retired
>
> `wp-client-template` (a Bedrock/Composer/wp-env scaffold) is no longer the
> way to build sites with the Starter ecosystem. It is archived and read-only.
>
> **Use these instead:**
>
> | Piece | Repo | Install |
> |---|---|---|
> | Parent theme | [wp-starter-theme](https://github.com/Bergert-Digital/wp-starter-theme) | Upload zip (1st) |
> | Child / agency starter | [wp-starter-child-theme](https://github.com/Bergert-Digital/wp-starter-child-theme) | Upload zip (2nd), activate |
> | AI plugin | [WP-Starter-AI](https://github.com/Bergert-Digital/WP-Starter-AI) | Upload zip any time |
>
> Install order on a fresh WordPress: **parent theme → child theme → activate child → plugin**.
>
> ---
```

- [ ] **Step 3: Commit on `development`**

```bash
cd /Users/jonas/Entwicklung/wp-client-template
git add README.md
git commit -m "docs: deprecate — point to wp-starter-theme / -child-theme / -ai"
```

---

### Task C2: Fast-forward main and push (REMOTE — pause for go-ahead)

- [ ] **Step 1: Fast-forward `main` to `development` locally**

```bash
cd /Users/jonas/Entwicklung/wp-client-template
git checkout main
git merge --ff-only development
git checkout development
```
Expected: `main` now points at the deprecation commit. If `--ff-only` fails (main diverged), STOP and report to the user — do not force.

- [ ] **Step 2: Show the push command and STOP**

Present and wait for approval:
```bash
cd /Users/jonas/Entwicklung/wp-client-template
git push origin development main
```
Ask: "Push the deprecation commit to wp-client-template's development and main?"

- [ ] **Step 3: After approval, push**

Run the command above. Expected: both branches updated on origin.

---

### Task C3: Archive the repo (REMOTE — pause for go-ahead)

- [ ] **Step 1: Verify the banner renders on GitHub before archiving**

Run: `gh repo view Bergert-Digital/wp-client-template --web`
Confirm with the user that the banner and links render correctly. (Archiving is reversible, but verify first.)

- [ ] **Step 2: Show the archive command and STOP**

Present and wait for explicit approval:
```bash
gh repo archive Bergert-Digital/wp-client-template --yes
```
Ask: "Archive wp-client-template now? It becomes read-only (reversible via gh repo unarchive)."

- [ ] **Step 3: After approval, archive**

Run the command. Expected: `✓ Archived repository Bergert-Digital/wp-client-template`.

---

## Workstream D — Zip pipeline

### Task D1: `wp-starter-ai` — `.distignore` + zip step in release.yml

**Files:**
- Create: `/Users/jonas/Entwicklung/wp-starter-ai/.distignore`
- Modify: `/Users/jonas/Entwicklung/wp-starter-ai/.github/workflows/release.yml` (add a zip-assembly step before "Create GitHub Release"; change the release step to attach the zip)

- [ ] **Step 1: Create `.distignore`** (plugin: ship runtime, exclude dev)

```
.git
.github
.distignore
.wp-env.json
node_modules
tests
test-results
playwright-report
playwright.config.ts
tsconfig.json
package.json
package-lock.json
phpcs.xml.dist
phpunit.xml.dist
composer.json
composer.lock
editor
AGENTS.md
```

- [ ] **Step 2: Add a "Build distributable zip" step to `release.yml`** immediately AFTER "Create release commit and push tag" and BEFORE "Create GitHub Release"

```yaml
      - name: Build distributable zip
        env:
          VERSION: ${{ inputs.version }}
        run: |
          STAGE="$(mktemp -d)/wp-starter-ai"
          mkdir -p "$STAGE"
          rsync -a --exclude-from=.distignore ./ "$STAGE/"
          ( cd "$(dirname "$STAGE")" && zip -rq "$GITHUB_WORKSPACE/wp-starter-ai-$VERSION.zip" wp-starter-ai )
          unzip -l "wp-starter-ai-$VERSION.zip" | tail -5
```

- [ ] **Step 3: Replace the "Create GitHub Release" step to attach the zip**

Change the existing final step to:
```yaml
      - name: Create GitHub Release
        env:
          GH_TOKEN: ${{ secrets.GITHUB_TOKEN }}
          VERSION: ${{ inputs.version }}
        run: gh release create "v$VERSION" --title "v$VERSION" --generate-notes "wp-starter-ai-$VERSION.zip"
```

- [ ] **Step 4: Lint the workflow YAML locally**

Run: `cd /Users/jonas/Entwicklung/wp-starter-ai && python3 -c "import yaml,sys; yaml.safe_load(open('.github/workflows/release.yml')); print('YAML OK')"`
Expected: `YAML OK`.

- [ ] **Step 5: Commit**

```bash
cd /Users/jonas/Entwicklung/wp-starter-ai
git add .distignore .github/workflows/release.yml
git commit -m "ci(release): attach clean installable zip to the GitHub Release"
```

---

### Task D2: `wp-starter-theme` — `.distignore` + new release.yml

**Files:**
- Create: `/Users/jonas/Entwicklung/wp-starter-theme/.distignore`
- Create: `/Users/jonas/Entwicklung/wp-starter-theme/.github/workflows/release.yml`

- [ ] **Step 1: Create `.distignore`** (parent theme: ship runtime, exclude TS sources + dev)

```
.git
.github
.distignore
.wp-env.json
node_modules
src
tools
tests
test-results
playwright-report
playwright.config.ts
tsconfig.json
package.json
package-lock.json
phpcs.xml.dist
phpunit.xml.dist
composer.json
composer.lock
docs
```

- [ ] **Step 2: Create `.github/workflows/release.yml`** (mirrors wp-starter-ai's shape; patches `style.css` Version + `STARTER_THEME_VERSION`; no `composer install --no-dev`)

```yaml
name: Release

on:
  workflow_dispatch:
    inputs:
      version:
        description: 'Version to release (e.g. 0.1.3, no leading v)'
        required: true
        type: string
      ref:
        description: 'Source branch or commit to release from'
        required: false
        type: string
        default: 'main'

permissions:
  contents: write

jobs:
  release:
    runs-on: ubuntu-latest
    steps:
      - name: Validate version input
        env:
          VERSION: ${{ inputs.version }}
        run: |
          if ! [[ "$VERSION" =~ ^[0-9]+\.[0-9]+\.[0-9]+(-[A-Za-z0-9.-]+)?$ ]]; then
            echo "::error::version must look like 0.1.3 or 0.1.3-rc.1 (got '$VERSION')"
            exit 1
          fi

      - uses: actions/checkout@v4
        with:
          ref: ${{ inputs.ref }}
          fetch-depth: 0

      - name: Ensure tag does not already exist
        env:
          VERSION: ${{ inputs.version }}
        run: |
          TAG="v$VERSION"
          if git ls-remote --exit-code --tags origin "refs/tags/$TAG" >/dev/null 2>&1; then
            echo "::error::tag $TAG already exists on origin"
            exit 1
          fi

      - uses: actions/setup-node@v4
        with:
          node-version: '20'
          cache: npm

      - name: Build JS bundle
        run: |
          npm ci
          npm run build

      - name: Patch version metadata in release commit
        env:
          VERSION: ${{ inputs.version }}
        run: |
          sed -i -E "s/^(Version:[[:space:]]+).+$/\1$VERSION/" style.css
          sed -i -E "s/(define\([[:space:]]*'STARTER_THEME_VERSION',[[:space:]]*')[^']*('[[:space:]]*\))/\1$VERSION\2/" functions.php
          node -e "const f='package.json';const p=require('./'+f);p.version=process.env.VERSION;require('fs').writeFileSync(f,JSON.stringify(p,null,2)+'\n');"
          grep -E "^Version:" style.css
          grep "STARTER_THEME_VERSION" functions.php
          grep '"version"' package.json

      - name: Create release commit and push tag
        env:
          VERSION: ${{ inputs.version }}
        run: |
          TAG="v$VERSION"
          git config user.name  "github-actions[bot]"
          git config user.email "41898282+github-actions[bot]@users.noreply.github.com"
          git add -f build
          git add style.css functions.php package.json
          git commit -m "build: artifacts for $TAG"
          git tag -a "$TAG" -m "Release $TAG"
          git push origin "refs/tags/$TAG"

      - name: Build distributable zip
        env:
          VERSION: ${{ inputs.version }}
        run: |
          STAGE="$(mktemp -d)/wp-starter-theme"
          mkdir -p "$STAGE"
          rsync -a --exclude-from=.distignore ./ "$STAGE/"
          ( cd "$(dirname "$STAGE")" && zip -rq "$GITHUB_WORKSPACE/wp-starter-theme-$VERSION.zip" wp-starter-theme )
          unzip -l "wp-starter-theme-$VERSION.zip" | tail -5

      - name: Create GitHub Release
        env:
          GH_TOKEN: ${{ secrets.GITHUB_TOKEN }}
          VERSION: ${{ inputs.version }}
        run: gh release create "v$VERSION" --title "v$VERSION" --generate-notes "wp-starter-theme-$VERSION.zip"
```

- [ ] **Step 3: Lint the workflow YAML locally**

Run: `cd /Users/jonas/Entwicklung/wp-starter-theme && python3 -c "import yaml; yaml.safe_load(open('.github/workflows/release.yml')); print('YAML OK')"`
Expected: `YAML OK`.

- [ ] **Step 4: Verify the sed targets actually match** (guard against a no-op patch)

Run:
```bash
cd /Users/jonas/Entwicklung/wp-starter-theme
grep -nE "^Version:" style.css
grep -n "STARTER_THEME_VERSION" functions.php
```
Expected: `style.css` has a line starting `Version:` and `functions.php` has the `define( 'STARTER_THEME_VERSION', '0.1.0' )`. If the `style.css` header uses leading whitespace before `Version:`, adjust the sed in Step 2 to `s/^([[:space:]]*Version:[[:space:]]+).+$/\1$VERSION/`.

- [ ] **Step 5: Commit**

```bash
cd /Users/jonas/Entwicklung/wp-starter-theme
git add .distignore .github/workflows/release.yml
git commit -m "ci: add workflow_dispatch release with installable zip asset"
```

---

### Task D3: `wp-starter-child-theme` — `.distignore` + release.yml + CI

**Files:**
- Create: `/Users/jonas/Entwicklung/wp-starter-child-theme/.distignore`
- Create: `/Users/jonas/Entwicklung/wp-starter-child-theme/.github/workflows/release.yml`
- Create: `/Users/jonas/Entwicklung/wp-starter-child-theme/.github/workflows/ci.yml`

- [ ] **Step 1: Create `.distignore`** (child is FORKABLE — keep `src/` + config, drop only true cruft)

```
.git
.github
.distignore
.wp-env.json
node_modules
test-results
playwright-report
vendor
```

- [ ] **Step 2: Create `.github/workflows/release.yml`** (same shape; patches `style.css` Version + `STARTER_CHILD_VERSION`; forkable zip keeps src/)

```yaml
name: Release

on:
  workflow_dispatch:
    inputs:
      version:
        description: 'Version to release (e.g. 0.1.1, no leading v)'
        required: true
        type: string
      ref:
        description: 'Source branch or commit to release from'
        required: false
        type: string
        default: 'main'

permissions:
  contents: write

jobs:
  release:
    runs-on: ubuntu-latest
    steps:
      - name: Validate version input
        env:
          VERSION: ${{ inputs.version }}
        run: |
          if ! [[ "$VERSION" =~ ^[0-9]+\.[0-9]+\.[0-9]+(-[A-Za-z0-9.-]+)?$ ]]; then
            echo "::error::version must look like 0.1.1 or 0.1.1-rc.1 (got '$VERSION')"
            exit 1
          fi

      - uses: actions/checkout@v4
        with:
          ref: ${{ inputs.ref }}
          fetch-depth: 0

      - name: Ensure tag does not already exist
        env:
          VERSION: ${{ inputs.version }}
        run: |
          TAG="v$VERSION"
          if git ls-remote --exit-code --tags origin "refs/tags/$TAG" >/dev/null 2>&1; then
            echo "::error::tag $TAG already exists on origin"
            exit 1
          fi

      - uses: actions/setup-node@v4
        with:
          node-version: '20'
          cache: npm

      - name: Build JS bundle
        run: |
          npm ci
          npm run build

      - name: Patch version metadata in release commit
        env:
          VERSION: ${{ inputs.version }}
        run: |
          sed -i -E "s/^(Version:[[:space:]]+).+$/\1$VERSION/" style.css
          sed -i -E "s/(define\([[:space:]]*'STARTER_CHILD_VERSION',[[:space:]]*')[^']*('[[:space:]]*\))/\1$VERSION\2/" functions.php
          node -e "const f='package.json';const p=require('./'+f);p.version=process.env.VERSION;require('fs').writeFileSync(f,JSON.stringify(p,null,2)+'\n');"
          grep -E "^Version:" style.css
          grep "STARTER_CHILD_VERSION" functions.php
          grep '"version"' package.json

      - name: Create release commit and push tag
        env:
          VERSION: ${{ inputs.version }}
        run: |
          TAG="v$VERSION"
          git config user.name  "github-actions[bot]"
          git config user.email "41898282+github-actions[bot]@users.noreply.github.com"
          git add -f build
          git add style.css functions.php package.json
          git commit -m "build: artifacts for $TAG"
          git tag -a "$TAG" -m "Release $TAG"
          git push origin "refs/tags/$TAG"

      - name: Build distributable zip
        env:
          VERSION: ${{ inputs.version }}
        run: |
          STAGE="$(mktemp -d)/wp-starter-child-theme"
          mkdir -p "$STAGE"
          rsync -a --exclude-from=.distignore ./ "$STAGE/"
          ( cd "$(dirname "$STAGE")" && zip -rq "$GITHUB_WORKSPACE/wp-starter-child-theme-$VERSION.zip" wp-starter-child-theme )
          unzip -l "wp-starter-child-theme-$VERSION.zip" | tail -5

      - name: Create GitHub Release
        env:
          GH_TOKEN: ${{ secrets.GITHUB_TOKEN }}
          VERSION: ${{ inputs.version }}
        run: gh release create "v$VERSION" --title "v$VERSION" --generate-notes "wp-starter-child-theme-$VERSION.zip"
```

- [ ] **Step 3: Create `.github/workflows/ci.yml`** (phpcs + lint:js standalone; phpunit/e2e need the parent — cross-repo checkout mirroring wp-starter-ai's ci.yml)

```yaml
name: CI

on:
  pull_request:
  push:
    branches: [main]

jobs:
  phpcs:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v4
      - uses: shivammathur/setup-php@v2
        with:
          php-version: '8.1'
          tools: composer
      - run: composer install --prefer-dist --no-progress
      - run: composer lint

  lint-js:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v4
      - uses: actions/setup-node@v4
        with:
          node-version: '20'
          cache: npm
      - run: npm ci
      - run: npm run lint:js
      - run: npm run build

  phpunit:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v4
        with:
          path: wp-starter-child-theme
      - uses: actions/checkout@v4
        with:
          repository: Bergert-Digital/WP-Starter
          ref: development
          token: ${{ secrets.STARTER_THEME_PAT }}
          path: wp-starter-theme
      - uses: actions/setup-node@v4
        with:
          node-version: '20'
          cache: npm
          cache-dependency-path: wp-starter-child-theme/package-lock.json
      - uses: shivammathur/setup-php@v2
        with:
          php-version: '8.1'
          tools: composer
      - name: Build parent theme blocks
        run: cd wp-starter-theme && npm ci && npm run build
      - name: Install child deps
        run: cd wp-starter-child-theme && composer install --prefer-dist --no-progress && npm ci
      - name: Start wp-env
        run: cd wp-starter-child-theme && npm run env:start
      - name: Build child blocks
        run: cd wp-starter-child-theme && npm run build
      - name: Run PHPUnit
        run: cd wp-starter-child-theme && npx wp-env run tests-wordpress --env-cwd=wp-content/themes/wp-starter-child-theme vendor/bin/phpunit
      - if: always()
        run: cd wp-starter-child-theme && npm run env:stop

  e2e:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v4
        with:
          path: wp-starter-child-theme
      - uses: actions/checkout@v4
        with:
          repository: Bergert-Digital/WP-Starter
          ref: development
          token: ${{ secrets.STARTER_THEME_PAT }}
          path: wp-starter-theme
      - uses: actions/setup-node@v4
        with:
          node-version: '20'
          cache: npm
          cache-dependency-path: wp-starter-child-theme/package-lock.json
      - run: cd wp-starter-theme && npm ci && npm run build
      - run: cd wp-starter-child-theme && npm ci
      - run: cd wp-starter-child-theme && npx playwright install --with-deps chromium
      - run: cd wp-starter-child-theme && npm run env:start
      - run: cd wp-starter-child-theme && npm run build
      - run: cd wp-starter-child-theme && npx wp-env run cli wp theme activate wp-starter-child-theme
      - run: cd wp-starter-child-theme && npm run e2e
      - if: always()
        run: cd wp-starter-child-theme && npm run env:stop
      - if: failure()
        uses: actions/upload-artifact@v4
        with:
          name: playwright-report
          path: wp-starter-child-theme/playwright-report/
```

- [ ] **Step 4: Lint both workflow YAMLs locally**

Run:
```bash
cd /Users/jonas/Entwicklung/wp-starter-child-theme
python3 -c "import yaml; yaml.safe_load(open('.github/workflows/release.yml')); yaml.safe_load(open('.github/workflows/ci.yml')); print('YAML OK')"
```
Expected: `YAML OK`.

- [ ] **Step 5: Commit and push**

```bash
cd /Users/jonas/Entwicklung/wp-starter-child-theme
git add .distignore .github/
git commit -m "ci: release (forkable zip) + CI with cross-repo parent checkout"
git push origin development
```

---

### Task D4: Spec-coverage cross-check

- [ ] **Step 1: Re-read the spec and confirm every requirement maps to a completed task**

Run: `cat /Users/jonas/Entwicklung/wp-starter-theme/docs/superpowers/specs/2026-05-15-wp-distribution-direction-design.md`
Walk each section; confirm: B (identity, seed, harness, wp-env, README) → B1–B8; C (banner, archive, client-theme left in place) → C1–C3; D (extend ai release.yml, new theme/child release.yml, .distignore per repo) → D1–D3. Note any gap and add a task before proceeding to D5.

---

### Task D5: Pipeline validation (REMOTE — pause for go-ahead)

Validates one repo end-to-end. The child theme is the best target (newest, exercises the forkable-zip path).

- [ ] **Step 1: Show the test-release command and STOP**

Present and wait for explicit approval:
```bash
gh workflow run Release --repo Bergert-Digital/wp-starter-child-theme \
  -f version=0.0.0-rc.test -f ref=development
```
Ask: "Run a throwaway test release (0.0.0-rc.test) on wp-starter-child-theme to validate the zip pipeline?"

- [ ] **Step 2: After approval, run it and watch**

```bash
gh workflow run Release --repo Bergert-Digital/wp-starter-child-theme -f version=0.0.0-rc.test -f ref=development
sleep 5
gh run watch --repo Bergert-Digital/wp-starter-child-theme "$(gh run list --repo Bergert-Digital/wp-starter-child-theme --workflow Release --limit 1 --json databaseId -q '.[0].databaseId')"
```
Expected: workflow succeeds; a `v0.0.0-rc.test` release exists with `wp-starter-child-theme-0.0.0-rc.test.zip` attached.

- [ ] **Step 3: Manually verify the zip installs in a fresh WP**

Download the asset, then in a clean WP (the child repo's wp-env, or any standard WP): Appearance → Add New → Upload Theme → the zip. Confirm it installs and lists as `Starter Child Theme` with parent `wp-starter-theme`. Report the result to the user. (This is a manual smoke test the user performs on their own WP — do not run it inside a worktree.)

- [ ] **Step 4: Clean up the throwaway release and tag (REMOTE — pause)**

Present and wait for approval:
```bash
gh release delete v0.0.0-rc.test --repo Bergert-Digital/wp-starter-child-theme --yes --cleanup-tag
```
Ask: "Delete the throwaway test release and its tag?"

- [ ] **Step 5: Reset the test release commit on development (REMOTE — pause)**

The Release workflow created a `build: artifacts for v0.0.0-rc.test` commit. Inspect and, with approval, drop it:
```bash
cd /Users/jonas/Entwicklung/wp-starter-child-theme
git fetch origin
git log --oneline origin/development -3
```
If the top commit is the throwaway build commit, present and wait for approval:
```bash
git push origin +origin/development~1:development   # force-update development back one commit
```
Ask: "The test release added a build-artifact commit to development. Force-update development back one commit to drop it? (force push — confirm)." If the user declines, leave it and note it for manual cleanup.

---

## Self-Review

**Spec coverage:**
- B identity/seed/harness/wp-env/README → B1–B8 ✓
- B "promo-banner kept, README flags replace" → B6 + B8 ✓
- B "fresh history, single initial commit" → B1 inits a fresh repo; history is a short clean series, not literally one commit (deliberate: bite-sized commits per the writing-plans skill — the *repo* has fresh history with no `wp-client-template` ancestry, satisfying the spec's intent). Noted, acceptable.
- B `main` + `development` branches → B9 Step 2 ✓
- B sibling-checkout wp-env → B5 Step 2 ✓
- C banner + fast-forward main + archive + client-theme left in place → C1–C3 ✓ (no strip task, matching the declined option)
- D extend ai release.yml + add zip → D1 ✓; new theme/child release.yml → D2/D3 ✓; `.distignore` per repo → D1/D2/D3 ✓; child CI cross-repo parent checkout → D3 ✓
- Remote-action protocol → B9, C2, C3, D5 all gated ✓

**Placeholder scan:** No TBD/TODO; every file step contains full content; every command has expected output. The B3-before-B5 test ordering is explicitly justified, not a hidden gap.

**Type/name consistency:** `starter_child_register_blocks` used identically in functions.php (B3), AutoLoaderTest (B5), and never collides with parent `starter_register_blocks`. `STARTER_CHILD_VERSION` consistent across B3 and D3. Block name `starter-child/promo-banner` consistent across B5 test, B6 block.json, edit.tsx, render.php, style.scss. Zip filenames consistent with repo dir names in D1/D2/D3.

**Open risk flagged for execution:** The child CI `phpunit`/`e2e` jobs and `wp-starter-ai`'s existing CI both rely on `secrets.STARTER_THEME_PAT` being present in the new `wp-starter-child-theme` repo. The plan cannot set that secret (it's a manual GitHub setting). Surface to the user at B9: "the new repo needs the `STARTER_THEME_PAT` secret added (Settings → Secrets → Actions) or the phpunit/e2e CI jobs will fail on the cross-repo checkout."
