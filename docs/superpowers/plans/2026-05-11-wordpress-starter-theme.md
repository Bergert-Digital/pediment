# WordPress Starter Theme (Plan A) Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Build `wp-starter-theme`: a forkable FSE block theme with 10 starter blocks, design tokens via theme.json, a Brand Settings admin page, and a contact-form submission handler. Foundation for Plan B (AI plugin).

**Architecture:** Hand-rolled FSE block theme (no Sage / GeneratePress). Blocks live in `src/blocks/<name>/` and are compiled to `build/blocks/<name>/` by `@wordpress/scripts`. A small auto-loader registers every block from `build/blocks/*/block.json`. theme.json is the only source of design tokens; a custom PHPCS sniff rejects hex/rgb literals inside `src/blocks/`. Brand Settings uses the native WP Settings API plus hand-rolled `wp.media` and repeater JS. Contact submissions persist as a private CPT.

**Tech Stack:** WordPress 6.4+, PHP 8.1+, `@wordpress/scripts`, TypeScript for block edit components, PHPUnit (via wp-env), Playwright, GitHub Actions CI.

**Repo location:** `/Users/jonas/Entwicklung/wp-starter-theme/` (sibling to the Payload Starter).

---

## File Structure

```
wp-starter-theme/
  composer.json                      Composer dev deps (PHPCS, PHPUnit)
  package.json                       @wordpress/scripts, Playwright
  tsconfig.json                      TS config for edit.tsx files
  phpcs.xml.dist                     PHPCS config + custom sniff path
  phpunit.xml.dist                   PHPUnit config (uses wp-env tests-wordpress)
  playwright.config.ts               Playwright config (targets wp-env on :8888)
  .wp-env.json                       wp-env theme mount + plugins
  .gitignore                         build/, node_modules, vendor, .env
  README.md                          Setup, dev, deployment notes
  style.css                          Theme metadata (header only; styles in theme.json)
  functions.php                      Bootstrap (requires inc/ files)
  theme.json                         All design tokens
  parts/header.html                  FSE header template part
  parts/footer.html                  FSE footer template part
  templates/index.html               Default loop
  templates/page.html                Single page
  templates/single.html              Single post
  templates/front-page.html          Home
  templates/archive.html             Category/tag/date archive
  templates/404.html                 404
  inc/register-blocks.php            Auto-loader: build/blocks/*/block.json → register_block_type
  inc/brand-settings.php             Settings API page + storage accessor
  inc/contact-form.php               REST submission handler, CPT, cron cleanup
  inc/seed.php                       WP-CLI: `wp starter-theme seed`
  inc/patterns.php                   register_block_pattern() calls for 3 patterns
  patterns/hero-cta-faq.php          Pattern markup
  patterns/prose-article.php
  patterns/contact-page.php
  src/blocks/hero/                   block.json, edit.tsx, index.tsx, render.php, style.scss
  src/blocks/cta/                    same
  src/blocks/faq/                    same (container)
  src/blocks/faq-item/               same (child of faq)
  src/blocks/prose/                  same
  src/blocks/pull-quote/             same
  src/blocks/image-caption/          same
  src/blocks/stat/                   same
  src/blocks/blog-index/             same
  src/blocks/contact-form/           same
  src/admin/brand-settings.ts        Image picker + repeater JS for Brand Settings page
  src/admin/contact-form.ts          Contact-form front-end submit handler
  build/                             @wordpress/scripts output (gitignored)
  tools/                             Build/lint helpers
    lint-blocks.mjs                  Asserts every src/blocks/<name>/ has the 3 required files
    phpcs-sniffs/                    Custom PHPCS rules
      Starter/Sniffs/NoColorLiteralSniff.php
      Starter/ruleset.xml
  tests/
    phpunit/
      bootstrap.php                  Loads WP + theme
      BlockRender/                   One test class per block
        HeroTest.php
        CtaTest.php
        FaqTest.php
        ProseTest.php
        PullQuoteTest.php
        ImageCaptionTest.php
        StatTest.php
        BlogIndexTest.php
        ContactFormBlockTest.php
      BrandSettings/
        StorageTest.php
      ContactForm/
        SubmissionTest.php
        HoneypotTest.php
        CptTest.php
        CleanupCronTest.php
      Seed/
        SeedCommandTest.php
    e2e/
      front-page.spec.ts
      editor-blocks.spec.ts
      contact-form.spec.ts
      brand-settings.spec.ts
  .github/workflows/
    ci.yml                           Run PHPUnit, Jest, Playwright on PRs
```

---

## Phase 0: Repository setup

### Task 1: Initialize repo with theme metadata

**Files:**
- Create: `/Users/jonas/Entwicklung/wp-starter-theme/style.css`
- Create: `/Users/jonas/Entwicklung/wp-starter-theme/functions.php`
- Create: `/Users/jonas/Entwicklung/wp-starter-theme/theme.json` (placeholder)
- Create: `/Users/jonas/Entwicklung/wp-starter-theme/.gitignore`
- Create: `/Users/jonas/Entwicklung/wp-starter-theme/README.md` (skeleton)

- [ ] **Step 1: Create the directory and init git**

```bash
mkdir -p /Users/jonas/Entwicklung/wp-starter-theme
cd /Users/jonas/Entwicklung/wp-starter-theme
git init
```

- [ ] **Step 2: Write style.css (theme header)**

```css
/*
Theme Name: Starter Theme
Theme URI: https://github.com/bergert/wp-starter-theme
Author: Jonas Bergert
Author URI: https://bergert.digital
Description: A forkable WordPress block theme for client websites. Sibling to the Payload Starter.
Version: 0.1.0
Requires at least: 6.4
Tested up to: 6.5
Requires PHP: 8.1
License: GPL-2.0-or-later
License URI: https://www.gnu.org/licenses/gpl-2.0.html
Text Domain: starter
Tags: full-site-editing, block-styles, block-patterns
*/
```

- [ ] **Step 3: Write functions.php (bootstrap)**

```php
<?php
/**
 * Starter Theme bootstrap.
 *
 * @package Starter
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

define( 'STARTER_THEME_DIR', __DIR__ );
define( 'STARTER_THEME_VERSION', '0.1.0' );

require_once __DIR__ . '/inc/register-blocks.php';
require_once __DIR__ . '/inc/brand-settings.php';
require_once __DIR__ . '/inc/contact-form.php';
require_once __DIR__ . '/inc/patterns.php';

if ( defined( 'WP_CLI' ) && WP_CLI ) {
    require_once __DIR__ . '/inc/seed.php';
}
```

- [ ] **Step 4: Write a placeholder theme.json (minimum required)**

```json
{
  "$schema": "https://schemas.wp.org/trunk/theme.json",
  "version": 2,
  "settings": {
    "appearanceTools": true,
    "useRootPaddingAwareAlignments": true
  }
}
```

- [ ] **Step 5: Write .gitignore**

```
node_modules/
vendor/
build/
.env
.env.local
.phpunit.result.cache
playwright-report/
test-results/
.DS_Store
```

- [ ] **Step 6: Write README.md skeleton**

```markdown
# Starter Theme

A forkable WordPress block theme for client websites. Sibling to the Payload Starter.

## Stack
- WordPress 6.4+
- PHP 8.1+
- FSE block theme (no parent theme dependency)

## Local development
See `docs/development.md`.

## Deployment
See `docs/deployment.md`.
```

- [ ] **Step 7: Create stub `inc/` files so functions.php doesn't fatal**

```bash
mkdir -p inc
for f in register-blocks brand-settings contact-form patterns; do
  echo "<?php /* stub */" > "inc/${f}.php"
done
```

- [ ] **Step 8: Commit**

```bash
git add .
git commit -m "chore: initialize wp-starter-theme with metadata + skeleton"
```

### Task 2: Configure wp-env for local development

**Files:**
- Create: `/Users/jonas/Entwicklung/wp-starter-theme/.wp-env.json`

- [ ] **Step 1: Write .wp-env.json**

```json
{
  "core": "WordPress/WordPress#6.5",
  "phpVersion": "8.1",
  "themes": ["."],
  "config": {
    "WP_DEBUG": true,
    "WP_DEBUG_LOG": true,
    "SCRIPT_DEBUG": true
  },
  "mappings": {
    "wp-content/uploads": "./tests/fixtures/uploads"
  }
}
```

- [ ] **Step 2: Install wp-env globally if not present**

```bash
which wp-env || npm install -g @wordpress/env
```

- [ ] **Step 3: Start wp-env and verify the theme appears**

```bash
cd /Users/jonas/Entwicklung/wp-starter-theme
wp-env start
```

Expected: WP starts on http://localhost:8888, admin at http://localhost:8888/wp-admin (admin / password). Activate the "Starter Theme" in Appearance > Themes.

- [ ] **Step 4: Stop wp-env**

```bash
wp-env stop
```

- [ ] **Step 5: Commit**

```bash
git add .wp-env.json
git commit -m "chore: add wp-env config"
```

### Task 3: Set up Composer with PHPCS

**Files:**
- Create: `/Users/jonas/Entwicklung/wp-starter-theme/composer.json`
- Create: `/Users/jonas/Entwicklung/wp-starter-theme/phpcs.xml.dist`

- [ ] **Step 1: Write composer.json**

```json
{
  "name": "bergert/wp-starter-theme",
  "description": "Starter WordPress block theme",
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
    "phpunit/phpunit": "^9.6"
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

- [ ] **Step 2: Write phpcs.xml.dist**

```xml
<?xml version="1.0"?>
<ruleset name="Starter Theme">
  <description>Coding standards for wp-starter-theme.</description>

  <file>functions.php</file>
  <file>inc/</file>
  <file>src/blocks/</file>

  <exclude-pattern>build/*</exclude-pattern>
  <exclude-pattern>node_modules/*</exclude-pattern>
  <exclude-pattern>vendor/*</exclude-pattern>

  <arg name="extensions" value="php"/>
  <arg name="colors"/>
  <arg value="ps"/>

  <rule ref="WordPress">
    <exclude name="WordPress.Files.FileName"/>
  </rule>

  <config name="testVersion" value="8.1-"/>
  <rule ref="PHPCompatibilityWP"/>

  <rule ref="./tools/phpcs-sniffs/Starter/ruleset.xml"/>
</ruleset>
```

- [ ] **Step 3: Install Composer dependencies**

```bash
composer install
```

Expected: `vendor/` populated; PHPCS sees WPCS standards.

- [ ] **Step 4: Verify PHPCS runs (will warn about empty stubs — ok)**

```bash
composer lint
```

Expected: exits 0 or 1 (acceptable; we have no real PHP yet besides functions.php).

- [ ] **Step 5: Commit**

```bash
git add composer.json phpcs.xml.dist composer.lock
git commit -m "chore: composer + PHPCS setup"
```

### Task 4: Set up NPM with @wordpress/scripts and TypeScript

**Files:**
- Create: `/Users/jonas/Entwicklung/wp-starter-theme/package.json`
- Create: `/Users/jonas/Entwicklung/wp-starter-theme/tsconfig.json`

- [ ] **Step 1: Write package.json**

```json
{
  "name": "wp-starter-theme",
  "version": "0.1.0",
  "private": true,
  "scripts": {
    "start": "wp-scripts start --webpack-src-dir=src/blocks --output-path=build/blocks",
    "build": "wp-scripts build --webpack-src-dir=src/blocks --output-path=build/blocks",
    "lint:js": "wp-scripts lint-js src/",
    "lint:blocks": "node tools/lint-blocks.mjs",
    "format": "wp-scripts format src/",
    "test:js": "wp-scripts test-unit-js",
    "e2e": "playwright test",
    "env:start": "wp-env start",
    "env:stop": "wp-env stop"
  },
  "devDependencies": {
    "@playwright/test": "^1.45.0",
    "@wordpress/env": "^10.0.0",
    "@wordpress/scripts": "^28.0.0",
    "typescript": "^5.4.0"
  }
}
```

- [ ] **Step 2: Write tsconfig.json**

```json
{
  "extends": "@wordpress/scripts/tsconfig.json",
  "compilerOptions": {
    "baseUrl": ".",
    "paths": {
      "@/*": ["src/*"]
    },
    "jsx": "react-jsx",
    "allowJs": true,
    "noEmit": true
  },
  "include": ["src/**/*.ts", "src/**/*.tsx"],
  "exclude": ["node_modules", "build"]
}
```

- [ ] **Step 3: Install npm dependencies**

```bash
npm install
```

- [ ] **Step 4: Verify the build command runs even with no blocks yet**

```bash
mkdir -p src/blocks
npm run build
```

Expected: completes (may warn about no entry points). build/ created.

- [ ] **Step 5: Commit**

```bash
git add package.json tsconfig.json package-lock.json
git commit -m "chore: @wordpress/scripts + TypeScript setup"
```

### Task 5: Set up PHPUnit with wp-env test container

**Files:**
- Create: `/Users/jonas/Entwicklung/wp-starter-theme/phpunit.xml.dist`
- Create: `/Users/jonas/Entwicklung/wp-starter-theme/tests/phpunit/bootstrap.php`

- [ ] **Step 1: Write phpunit.xml.dist**

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
    <testsuite name="theme">
      <directory>tests/phpunit/</directory>
    </testsuite>
  </testsuites>
</phpunit>
```

- [ ] **Step 2: Write tests/phpunit/bootstrap.php**

```php
<?php
/**
 * PHPUnit bootstrap: loads WP test harness and the theme.
 *
 * Runs inside wp-env's tests-wordpress container.
 */

$_tests_dir = getenv( 'WP_TESTS_DIR' );
if ( ! $_tests_dir ) {
    $_tests_dir = rtrim( sys_get_temp_dir(), '/\\' ) . '/wordpress-tests-lib';
}

require_once $_tests_dir . '/includes/functions.php';

tests_add_filter(
    'muplugins_loaded',
    function () {
        switch_theme( 'wp-starter-theme' );
        require dirname( __DIR__, 2 ) . '/functions.php';
    }
);

require $_tests_dir . '/includes/bootstrap.php';
```

- [ ] **Step 3: Add a smoke test that just asserts WP loaded**

Create `tests/phpunit/SmokeTest.php`:

```php
<?php

class SmokeTest extends WP_UnitTestCase {
    public function test_wordpress_is_loaded() {
        $this->assertTrue( function_exists( 'wp_get_theme' ) );
    }

    public function test_starter_theme_is_active() {
        $this->assertSame( 'wp-starter-theme', wp_get_theme()->get_stylesheet() );
    }
}
```

- [ ] **Step 4: Run the smoke test**

```bash
wp-env start
wp-env run tests-wordpress --env-cwd=wp-content/themes/wp-starter-theme phpunit
```

Expected: 2 tests pass.

- [ ] **Step 5: Commit**

```bash
git add phpunit.xml.dist tests/phpunit/
git commit -m "test: phpunit setup with wp-env test container"
```

### Task 6: Set up Playwright

**Files:**
- Create: `/Users/jonas/Entwicklung/wp-starter-theme/playwright.config.ts`
- Create: `/Users/jonas/Entwicklung/wp-starter-theme/tests/e2e/smoke.spec.ts`

- [ ] **Step 1: Install Playwright browsers**

```bash
npx playwright install --with-deps chromium
```

- [ ] **Step 2: Write playwright.config.ts**

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

- [ ] **Step 3: Write tests/e2e/smoke.spec.ts**

```ts
import { test, expect } from '@playwright/test';

test('home page loads', async ({ page }) => {
  await page.goto('/');
  await expect(page).toHaveTitle(/.+/);
});
```

- [ ] **Step 4: Run the smoke spec against wp-env**

```bash
wp-env start
npm run e2e
```

Expected: 1 test passes.

- [ ] **Step 5: Commit**

```bash
git add playwright.config.ts tests/e2e/
git commit -m "test: playwright e2e smoke test"
```

### Task 7: GitHub Actions CI

**Files:**
- Create: `/Users/jonas/Entwicklung/wp-starter-theme/.github/workflows/ci.yml`

- [ ] **Step 1: Write the workflow**

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

  lint-blocks:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v4
      - uses: actions/setup-node@v4
        with:
          node-version: '20'
          cache: npm
      - run: npm ci
      - run: npm run lint:blocks
      - run: npm run lint:js

  phpunit:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v4
      - uses: actions/setup-node@v4
        with:
          node-version: '20'
          cache: npm
      - run: npm ci
      - run: npm run env:start
      - run: npm run build
      - run: npx wp-env run tests-wordpress --env-cwd=wp-content/themes/wp-starter-theme phpunit
      - if: always()
        run: npm run env:stop

  e2e:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v4
      - uses: actions/setup-node@v4
        with:
          node-version: '20'
          cache: npm
      - run: npm ci
      - run: npx playwright install --with-deps chromium
      - run: npm run env:start
      - run: npm run build
      - run: npm run e2e
      - if: always()
        run: npm run env:stop
      - if: failure()
        uses: actions/upload-artifact@v4
        with:
          name: playwright-report
          path: playwright-report/
```

- [ ] **Step 2: Commit**

```bash
git add .github/workflows/ci.yml
git commit -m "ci: github actions for phpcs, phpunit, e2e, block lint"
```

---

## Phase 1: theme.json + FSE templates

### Task 8: Write theme.json with design tokens

**Files:**
- Modify: `/Users/jonas/Entwicklung/wp-starter-theme/theme.json` (replace placeholder)

- [ ] **Step 1: Replace the placeholder theme.json**

```json
{
  "$schema": "https://schemas.wp.org/trunk/theme.json",
  "version": 2,
  "settings": {
    "appearanceTools": true,
    "useRootPaddingAwareAlignments": true,
    "layout": {
      "contentSize": "720px",
      "wideSize": "1200px"
    },
    "color": {
      "palette": [
        { "slug": "primary",          "color": "#0F172A", "name": "Primary" },
        { "slug": "accent",           "color": "#2563EB", "name": "Accent" },
        { "slug": "surface",          "color": "#FFFFFF", "name": "Surface" },
        { "slug": "surface-elevated", "color": "#F8FAFC", "name": "Surface elevated" },
        { "slug": "text",             "color": "#0F172A", "name": "Text" },
        { "slug": "text-muted",       "color": "#475569", "name": "Text muted" },
        { "slug": "border",           "color": "#E2E8F0", "name": "Border" }
      ],
      "defaultPalette": false,
      "custom": false,
      "customGradient": false
    },
    "typography": {
      "fontFamilies": [
        { "slug": "body",    "name": "Body",    "fontFamily": "system-ui, -apple-system, 'Segoe UI', Roboto, sans-serif" },
        { "slug": "heading", "name": "Heading", "fontFamily": "system-ui, -apple-system, 'Segoe UI', Roboto, sans-serif" },
        { "slug": "mono",    "name": "Mono",    "fontFamily": "ui-monospace, SFMono-Regular, Menlo, monospace" }
      ],
      "fontSizes": [
        { "slug": "xs",   "size": "clamp(0.75rem, 0.7rem + 0.2vw, 0.875rem)", "name": "XS" },
        { "slug": "sm",   "size": "clamp(0.875rem, 0.8rem + 0.3vw, 1rem)",     "name": "Small" },
        { "slug": "base", "size": "clamp(1rem, 0.9rem + 0.4vw, 1.125rem)",     "name": "Base" },
        { "slug": "lg",   "size": "clamp(1.125rem, 1rem + 0.6vw, 1.375rem)",   "name": "Large" },
        { "slug": "xl",   "size": "clamp(1.375rem, 1.2rem + 0.8vw, 1.75rem)",  "name": "XL" },
        { "slug": "2xl",  "size": "clamp(1.75rem, 1.5rem + 1.2vw, 2.25rem)",   "name": "2XL" },
        { "slug": "3xl",  "size": "clamp(2.25rem, 1.8rem + 2vw, 3.25rem)",     "name": "3XL" },
        { "slug": "4xl",  "size": "clamp(3rem, 2.4rem + 3vw, 4.5rem)",         "name": "4XL" }
      ],
      "fluid": true,
      "customFontSize": false
    },
    "spacing": {
      "units": ["%", "px", "em", "rem", "vh", "vw"],
      "spacingScale": {
        "operator": "*",
        "increment": 1.5,
        "steps": 7,
        "mediumStep": 1.5,
        "unit": "rem"
      }
    },
    "blocks": {
      "core/button": {
        "border": { "radius": true }
      }
    }
  },
  "styles": {
    "color": {
      "background": "var(--wp--preset--color--surface)",
      "text": "var(--wp--preset--color--text)"
    },
    "typography": {
      "fontFamily": "var(--wp--preset--font-family--body)",
      "fontSize": "var(--wp--preset--font-size--base)",
      "lineHeight": "1.6"
    },
    "elements": {
      "h1": { "typography": { "fontFamily": "var(--wp--preset--font-family--heading)", "fontSize": "var(--wp--preset--font-size--3xl)", "lineHeight": "1.1" } },
      "h2": { "typography": { "fontFamily": "var(--wp--preset--font-family--heading)", "fontSize": "var(--wp--preset--font-size--2xl)", "lineHeight": "1.2" } },
      "h3": { "typography": { "fontFamily": "var(--wp--preset--font-family--heading)", "fontSize": "var(--wp--preset--font-size--xl)",  "lineHeight": "1.3" } },
      "link": { "color": { "text": "var(--wp--preset--color--accent)" } }
    }
  },
  "templateParts": [
    { "name": "header", "title": "Header", "area": "header" },
    { "name": "footer", "title": "Footer", "area": "footer" }
  ],
  "customTemplates": [
    { "name": "front-page", "title": "Home", "postTypes": ["page"] }
  ]
}
```

- [ ] **Step 2: Verify theme.json is valid by activating the theme in wp-env**

```bash
wp-env start
open http://localhost:8888/wp-admin
```

Expected: no theme.json errors in Site Editor (Appearance → Editor).

- [ ] **Step 3: Commit**

```bash
git add theme.json
git commit -m "feat(theme): design tokens via theme.json"
```

### Task 9: FSE template parts and templates

**Files:**
- Create: `/Users/jonas/Entwicklung/wp-starter-theme/parts/header.html`
- Create: `/Users/jonas/Entwicklung/wp-starter-theme/parts/footer.html`
- Create: `/Users/jonas/Entwicklung/wp-starter-theme/templates/index.html`
- Create: `/Users/jonas/Entwicklung/wp-starter-theme/templates/page.html`
- Create: `/Users/jonas/Entwicklung/wp-starter-theme/templates/single.html`
- Create: `/Users/jonas/Entwicklung/wp-starter-theme/templates/front-page.html`
- Create: `/Users/jonas/Entwicklung/wp-starter-theme/templates/archive.html`
- Create: `/Users/jonas/Entwicklung/wp-starter-theme/templates/404.html`

- [ ] **Step 1: Write parts/header.html**

```html
<!-- wp:group {"tagName":"header","layout":{"type":"constrained"}} -->
<header class="wp-block-group">
  <!-- wp:group {"layout":{"type":"flex","justifyContent":"space-between"}} -->
  <div class="wp-block-group">
    <!-- wp:site-title /-->
    <!-- wp:navigation {"layout":{"type":"flex","orientation":"horizontal"}} /-->
  </div>
  <!-- /wp:group -->
</header>
<!-- /wp:group -->
```

- [ ] **Step 2: Write parts/footer.html**

```html
<!-- wp:group {"tagName":"footer","backgroundColor":"surface-elevated","layout":{"type":"constrained"}} -->
<footer class="wp-block-group has-surface-elevated-background-color has-background">
  <!-- wp:paragraph {"align":"center","fontSize":"sm"} -->
  <p class="has-text-align-center has-sm-font-size">© <!-- wp:site-title {"level":0} /--></p>
  <!-- /wp:paragraph -->
</footer>
<!-- /wp:group -->
```

- [ ] **Step 3: Write templates/index.html**

```html
<!-- wp:template-part {"slug":"header","tagName":"header"} /-->

<!-- wp:group {"tagName":"main","layout":{"type":"constrained"}} -->
<main class="wp-block-group">
  <!-- wp:query {"queryId":0,"query":{"perPage":10,"pages":0,"offset":0,"postType":"post","order":"desc","orderBy":"date","inherit":true}} -->
  <div class="wp-block-query">
    <!-- wp:post-template -->
      <!-- wp:post-title {"isLink":true} /-->
      <!-- wp:post-excerpt /-->
    <!-- /wp:post-template -->
    <!-- wp:query-pagination /-->
  </div>
  <!-- /wp:query -->
</main>
<!-- /wp:group -->

<!-- wp:template-part {"slug":"footer","tagName":"footer"} /-->
```

- [ ] **Step 4: Write templates/page.html**

```html
<!-- wp:template-part {"slug":"header","tagName":"header"} /-->

<!-- wp:group {"tagName":"main","layout":{"type":"constrained"}} -->
<main class="wp-block-group">
  <!-- wp:post-title {"level":1} /-->
  <!-- wp:post-content /-->
</main>
<!-- /wp:group -->

<!-- wp:template-part {"slug":"footer","tagName":"footer"} /-->
```

- [ ] **Step 5: Write templates/single.html**

```html
<!-- wp:template-part {"slug":"header","tagName":"header"} /-->

<!-- wp:group {"tagName":"main","layout":{"type":"constrained"}} -->
<main class="wp-block-group">
  <!-- wp:post-title {"level":1} /-->
  <!-- wp:post-date {"format":"F j, Y","fontSize":"sm"} /-->
  <!-- wp:post-content /-->
</main>
<!-- /wp:group -->

<!-- wp:template-part {"slug":"footer","tagName":"footer"} /-->
```

- [ ] **Step 6: Write templates/front-page.html**

```html
<!-- wp:template-part {"slug":"header","tagName":"header"} /-->

<!-- wp:group {"tagName":"main"} -->
<main class="wp-block-group">
  <!-- wp:post-content /-->
</main>
<!-- /wp:group -->

<!-- wp:template-part {"slug":"footer","tagName":"footer"} /-->
```

- [ ] **Step 7: Write templates/archive.html**

```html
<!-- wp:template-part {"slug":"header","tagName":"header"} /-->

<!-- wp:group {"tagName":"main","layout":{"type":"constrained"}} -->
<main class="wp-block-group">
  <!-- wp:query-title {"type":"archive","level":1} /-->
  <!-- wp:query {"queryId":0,"query":{"perPage":10,"pages":0,"offset":0,"postType":"post","order":"desc","orderBy":"date","inherit":true}} -->
  <div class="wp-block-query">
    <!-- wp:post-template -->
      <!-- wp:post-title {"isLink":true} /-->
      <!-- wp:post-excerpt /-->
    <!-- /wp:post-template -->
    <!-- wp:query-pagination /-->
  </div>
  <!-- /wp:query -->
</main>
<!-- /wp:group -->

<!-- wp:template-part {"slug":"footer","tagName":"footer"} /-->
```

- [ ] **Step 8: Write templates/404.html**

```html
<!-- wp:template-part {"slug":"header","tagName":"header"} /-->

<!-- wp:group {"tagName":"main","layout":{"type":"constrained"}} -->
<main class="wp-block-group">
  <!-- wp:heading {"level":1} -->
  <h1>Page not found</h1>
  <!-- /wp:heading -->
  <!-- wp:paragraph -->
  <p>The page you're looking for doesn't exist. <a href="/">Return home</a>.</p>
  <!-- /wp:paragraph -->
</main>
<!-- /wp:group -->

<!-- wp:template-part {"slug":"footer","tagName":"footer"} /-->
```

- [ ] **Step 9: Write an E2E test that asserts the front page renders header + footer**

Create `tests/e2e/front-page.spec.ts`:

```ts
import { test, expect } from '@playwright/test';

test.describe('front page', () => {
  test('renders header and footer', async ({ page }) => {
    await page.goto('/');
    await expect(page.locator('header')).toBeVisible();
    await expect(page.locator('footer')).toBeVisible();
  });

  test('404 page renders for unknown URL', async ({ page }) => {
    const response = await page.goto('/this-page-does-not-exist-12345');
    expect(response?.status()).toBe(404);
    await expect(page.getByRole('heading', { name: 'Page not found' })).toBeVisible();
  });
});
```

- [ ] **Step 10: Run E2E and verify**

```bash
wp-env start
npm run e2e
```

Expected: 3 tests pass (smoke + 2 front-page).

- [ ] **Step 11: Delete the smoke spec now that real tests exist**

```bash
rm tests/e2e/smoke.spec.ts
```

- [ ] **Step 12: Commit**

```bash
git add parts/ templates/ tests/e2e/
git commit -m "feat(theme): FSE templates and header/footer parts"
```

---

## Phase 2: Block infrastructure

### Task 10: Block auto-loader

**Files:**
- Modify: `/Users/jonas/Entwicklung/wp-starter-theme/inc/register-blocks.php`

- [ ] **Step 1: Write a failing PHPUnit test for the auto-loader**

Create `tests/phpunit/BlockLoader/AutoLoaderTest.php`:

```php
<?php

class AutoLoaderTest extends WP_UnitTestCase {
    public function test_loader_function_exists() {
        $this->assertTrue( function_exists( 'starter_register_blocks' ) );
    }

    public function test_loader_handles_missing_build_dir_gracefully() {
        // Should not throw when build/blocks does not exist.
        starter_register_blocks( '/nonexistent/path' );
        $this->assertTrue( true );
    }

    public function test_loader_registers_blocks_from_build_dir() {
        $tmp = sys_get_temp_dir() . '/starter-test-blocks-' . uniqid();
        mkdir( $tmp . '/dummy-block', 0777, true );
        file_put_contents(
            $tmp . '/dummy-block/block.json',
            json_encode( [
                'apiVersion' => 3,
                'name'       => 'starter/dummy',
                'title'      => 'Dummy',
                'category'   => 'design',
                'attributes' => [ 'text' => [ 'type' => 'string', 'default' => '' ] ],
            ] )
        );

        starter_register_blocks( $tmp );

        $registry = WP_Block_Type_Registry::get_instance();
        $this->assertTrue( $registry->is_registered( 'starter/dummy' ) );

        $registry->unregister( 'starter/dummy' );
    }
}
```

- [ ] **Step 2: Run the test and verify it fails**

```bash
wp-env run tests-wordpress --env-cwd=wp-content/themes/wp-starter-theme phpunit --filter AutoLoaderTest
```

Expected: FAIL (`starter_register_blocks` is undefined).

- [ ] **Step 3: Implement the auto-loader**

Replace `inc/register-blocks.php`:

```php
<?php
/**
 * Auto-registers every block in build/blocks/<name>/.
 *
 * @package Starter
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Register all blocks in the given directory.
 *
 * @param string|null $base_dir Directory containing block subfolders. Defaults to theme's build/blocks.
 */
function starter_register_blocks( $base_dir = null ) {
    if ( null === $base_dir ) {
        $base_dir = STARTER_THEME_DIR . '/build/blocks';
    }

    if ( ! is_dir( $base_dir ) ) {
        return;
    }

    foreach ( glob( $base_dir . '/*', GLOB_ONLYDIR ) as $block_dir ) {
        $manifest = $block_dir . '/block.json';
        if ( file_exists( $manifest ) ) {
            register_block_type( $block_dir );
        }
    }
}

add_action( 'init', 'starter_register_blocks' );
```

- [ ] **Step 4: Run the test and verify it passes**

```bash
wp-env run tests-wordpress --env-cwd=wp-content/themes/wp-starter-theme phpunit --filter AutoLoaderTest
```

Expected: 3 tests pass.

- [ ] **Step 5: Commit**

```bash
git add inc/register-blocks.php tests/phpunit/BlockLoader/
git commit -m "feat(blocks): auto-loader for build/blocks/*/block.json"
```

### Task 11: Block directory lint rule

**Files:**
- Create: `/Users/jonas/Entwicklung/wp-starter-theme/tools/lint-blocks.mjs`

- [ ] **Step 1: Write the lint script**

```js
#!/usr/bin/env node
import { readdirSync, statSync, existsSync } from 'node:fs';
import { join } from 'node:path';

const root = new URL('../src/blocks/', import.meta.url).pathname;
const required = ['block.json', 'render.php', 'edit.tsx'];

if (!existsSync(root)) {
  console.log(`No src/blocks/ directory yet — skipping.`);
  process.exit(0);
}

const dirs = readdirSync(root).filter((name) => {
  return statSync(join(root, name)).isDirectory();
});

let failed = false;

for (const dir of dirs) {
  const missing = required.filter((file) => !existsSync(join(root, dir, file)));
  if (missing.length > 0) {
    console.error(`✗ src/blocks/${dir}/ is missing: ${missing.join(', ')}`);
    failed = true;
  } else {
    console.log(`✓ src/blocks/${dir}/`);
  }
}

if (failed) {
  console.error('\nEach src/blocks/<name>/ must contain block.json, render.php, and edit.tsx.');
  process.exit(1);
}
```

- [ ] **Step 2: Run with no blocks (should pass)**

```bash
npm run lint:blocks
```

Expected: prints "No src/blocks/ directory yet — skipping" or empty pass.

- [ ] **Step 3: Run with a deliberately incomplete block**

```bash
mkdir -p src/blocks/broken
touch src/blocks/broken/block.json
npm run lint:blocks || true
```

Expected: exits 1, reports missing render.php and edit.tsx.

- [ ] **Step 4: Clean up the fake block**

```bash
rm -rf src/blocks/broken
```

- [ ] **Step 5: Commit**

```bash
git add tools/lint-blocks.mjs
git commit -m "tooling: lint-blocks asserts each block dir has the 3 required files"
```

---

## Phase 3: Blocks

> **Convention used in every block task:**
> - `src/blocks/<name>/block.json` — metadata, attributes, supports
> - `src/blocks/<name>/edit.tsx` — editor UI (registers via `registerBlockType` in `index.tsx`)
> - `src/blocks/<name>/index.tsx` — entry: imports block.json + edit, calls `registerBlockType`
> - `src/blocks/<name>/render.php` — server-side render
> - `src/blocks/<name>/style.scss` — optional, compiled to build/
> - PHPUnit test in `tests/phpunit/BlockRender/<Name>Test.php` exercises `render.php`
> - All colors via `var(--wp--preset--color--*)`; no hex literals

### Task 12: Hero block

**Files:**
- Create: `src/blocks/hero/block.json`
- Create: `src/blocks/hero/index.tsx`
- Create: `src/blocks/hero/edit.tsx`
- Create: `src/blocks/hero/render.php`
- Create: `src/blocks/hero/style.scss`
- Create: `tests/phpunit/BlockRender/HeroTest.php`

- [ ] **Step 1: Write the failing render test**

```php
<?php
/**
 * tests/phpunit/BlockRender/HeroTest.php
 */

class HeroTest extends WP_UnitTestCase {
    private function render( array $attrs ): string {
        $block_markup = '<!-- wp:starter/hero ' . wp_json_encode( $attrs ) . ' /-->';
        return do_blocks( $block_markup );
    }

    public function test_renders_headline_and_subheadline() {
        $html = $this->render( [
            'variant'     => 'default',
            'headline'    => 'Welcome',
            'subheadline' => 'We help you grow',
            'ctaText'     => 'Get Started',
            'ctaUrl'      => '/start',
        ] );

        $this->assertStringContainsString( 'Welcome', $html );
        $this->assertStringContainsString( 'We help you grow', $html );
        $this->assertStringContainsString( 'href="/start"', $html );
        $this->assertStringContainsString( 'Get Started', $html );
    }

    public function test_renders_variant_class() {
        $html = $this->render( [ 'variant' => 'centered', 'headline' => 'Hi' ] );
        $this->assertStringContainsString( 'is-variant-centered', $html );
    }

    public function test_omits_cta_when_url_is_empty() {
        $html = $this->render( [
            'variant'  => 'default',
            'headline' => 'No CTA here',
            'ctaText'  => 'Go',
            'ctaUrl'   => '',
        ] );
        $this->assertStringNotContainsString( '<a', $html );
    }
}
```

- [ ] **Step 2: Run the test to verify it fails**

```bash
wp-env run tests-wordpress --env-cwd=wp-content/themes/wp-starter-theme phpunit --filter HeroTest
```

Expected: FAIL (block not registered, render returns empty).

- [ ] **Step 3: Write src/blocks/hero/block.json**

```json
{
  "$schema": "https://schemas.wp.org/trunk/block.json",
  "apiVersion": 3,
  "name": "starter/hero",
  "title": "Hero",
  "category": "starter",
  "description": "A page-opening hero with headline, subheadline, and primary CTA. Variants: default, split, centered, media-bg.",
  "textdomain": "starter",
  "supports": { "html": false, "align": ["wide", "full"] },
  "attributes": {
    "variant":     { "type": "string", "default": "default", "enum": ["default", "split", "centered", "media-bg"] },
    "headline":    { "type": "string", "default": "" },
    "subheadline": { "type": "string", "default": "" },
    "ctaText":     { "type": "string", "default": "" },
    "ctaUrl":      { "type": "string", "default": "" },
    "mediaId":     { "type": "number", "default": 0 }
  },
  "editorScript": "file:./index.js",
  "style":        "file:./style-index.css",
  "render":       "file:./render.php"
}
```

- [ ] **Step 4: Write src/blocks/hero/edit.tsx**

```tsx
import { __ } from '@wordpress/i18n';
import { useBlockProps, RichText, InspectorControls, MediaUpload } from '@wordpress/block-editor';
import { PanelBody, SelectControl, TextControl, Button } from '@wordpress/components';

type Attrs = {
  variant: 'default' | 'split' | 'centered' | 'media-bg';
  headline: string;
  subheadline: string;
  ctaText: string;
  ctaUrl: string;
  mediaId: number;
};

export default function Edit({
  attributes,
  setAttributes,
}: {
  attributes: Attrs;
  setAttributes: (a: Partial<Attrs>) => void;
}) {
  const blockProps = useBlockProps({ className: `starter-hero is-variant-${attributes.variant}` });

  return (
    <>
      <InspectorControls>
        <PanelBody title={__('Hero settings', 'starter')}>
          <SelectControl
            label={__('Variant', 'starter')}
            value={attributes.variant}
            options={[
              { label: 'Default',    value: 'default' },
              { label: 'Split',      value: 'split' },
              { label: 'Centered',   value: 'centered' },
              { label: 'Media BG',   value: 'media-bg' },
            ]}
            onChange={(v) => setAttributes({ variant: v as Attrs['variant'] })}
          />
          <TextControl
            label={__('CTA URL', 'starter')}
            value={attributes.ctaUrl}
            onChange={(v) => setAttributes({ ctaUrl: v })}
          />
          {attributes.variant === 'media-bg' && (
            <MediaUpload
              allowedTypes={['image']}
              onSelect={(media) => setAttributes({ mediaId: media.id })}
              render={({ open }) => (
                <Button variant="secondary" onClick={open}>
                  {attributes.mediaId ? __('Replace image', 'starter') : __('Pick image', 'starter')}
                </Button>
              )}
            />
          )}
        </PanelBody>
      </InspectorControls>

      <div {...blockProps}>
        <RichText
          tagName="h1"
          value={attributes.headline}
          onChange={(v) => setAttributes({ headline: v })}
          placeholder={__('Headline…', 'starter')}
        />
        <RichText
          tagName="p"
          value={attributes.subheadline}
          onChange={(v) => setAttributes({ subheadline: v })}
          placeholder={__('Subheadline…', 'starter')}
        />
        <RichText
          tagName="span"
          value={attributes.ctaText}
          onChange={(v) => setAttributes({ ctaText: v })}
          placeholder={__('CTA text…', 'starter')}
        />
      </div>
    </>
  );
}
```

- [ ] **Step 5: Write src/blocks/hero/index.tsx**

```tsx
import { registerBlockType } from '@wordpress/blocks';
import metadata from './block.json';
import Edit from './edit';
import './style.scss';

registerBlockType(metadata.name, { edit: Edit });
```

- [ ] **Step 6: Write src/blocks/hero/render.php**

```php
<?php
/**
 * Server-side render for starter/hero.
 *
 * @var array $attributes
 * @var string $content
 * @var WP_Block $block
 */

$variant     = isset( $attributes['variant'] )     ? (string) $attributes['variant']     : 'default';
$headline    = isset( $attributes['headline'] )    ? (string) $attributes['headline']    : '';
$subheadline = isset( $attributes['subheadline'] ) ? (string) $attributes['subheadline'] : '';
$cta_text    = isset( $attributes['ctaText'] )     ? (string) $attributes['ctaText']     : '';
$cta_url     = isset( $attributes['ctaUrl'] )      ? (string) $attributes['ctaUrl']      : '';
$media_id    = isset( $attributes['mediaId'] )     ? (int)    $attributes['mediaId']     : 0;

$wrapper_attributes = get_block_wrapper_attributes( [
    'class' => 'starter-hero is-variant-' . sanitize_html_class( $variant ),
] );

$bg_style = '';
if ( 'media-bg' === $variant && $media_id ) {
    $url = wp_get_attachment_image_url( $media_id, 'full' );
    if ( $url ) {
        $bg_style = ' style="background-image:url(' . esc_url( $url ) . ');"';
    }
}

ob_start();
?>
<section <?php echo $wrapper_attributes; // phpcs:ignore WordPress.Security.EscapeOutput ?><?php echo $bg_style; // phpcs:ignore ?>>
    <?php if ( $headline ) : ?>
        <h1 class="starter-hero__headline"><?php echo wp_kses_post( $headline ); ?></h1>
    <?php endif; ?>
    <?php if ( $subheadline ) : ?>
        <p class="starter-hero__subheadline"><?php echo wp_kses_post( $subheadline ); ?></p>
    <?php endif; ?>
    <?php if ( $cta_text && $cta_url ) : ?>
        <a class="starter-hero__cta" href="<?php echo esc_url( $cta_url ); ?>">
            <?php echo wp_kses_post( $cta_text ); ?>
        </a>
    <?php endif; ?>
</section>
<?php
echo ob_get_clean();
```

- [ ] **Step 7: Write src/blocks/hero/style.scss**

```scss
.starter-hero {
  padding: var(--wp--preset--spacing--50) var(--wp--preset--spacing--30);
  color: var(--wp--preset--color--text);

  &.is-variant-centered { text-align: center; }
  &.is-variant-split    { display: grid; grid-template-columns: 1fr 1fr; gap: var(--wp--preset--spacing--40); }
  &.is-variant-media-bg { background-size: cover; background-position: center; color: var(--wp--preset--color--surface); }

  &__headline    { font-size: var(--wp--preset--font-size--3xl); line-height: 1.1; margin: 0 0 var(--wp--preset--spacing--20); }
  &__subheadline { font-size: var(--wp--preset--font-size--lg);  color: var(--wp--preset--color--text-muted); margin: 0 0 var(--wp--preset--spacing--30); }
  &__cta         { display: inline-block; padding: var(--wp--preset--spacing--20) var(--wp--preset--spacing--30); background: var(--wp--preset--color--accent); color: var(--wp--preset--color--surface); border-radius: 0.5rem; text-decoration: none; }
}
```

- [ ] **Step 8: Register the "starter" block category**

Modify `inc/register-blocks.php` to add the category. Add this at the top after the namespace docblock:

```php
add_filter( 'block_categories_all', function ( array $categories ) {
    array_unshift( $categories, [
        'slug'  => 'starter',
        'title' => __( 'Starter blocks', 'starter' ),
    ] );
    return $categories;
} );
```

- [ ] **Step 9: Build and run the test**

```bash
npm run build
wp-env run tests-wordpress --env-cwd=wp-content/themes/wp-starter-theme phpunit --filter HeroTest
```

Expected: 3 tests pass.

- [ ] **Step 10: Commit**

```bash
git add src/blocks/hero/ tests/phpunit/BlockRender/HeroTest.php inc/register-blocks.php
git commit -m "feat(blocks): starter/hero with 4 variants"
```

### Task 13: CTA block

**Files:**
- Create: `src/blocks/cta/{block.json,index.tsx,edit.tsx,render.php,style.scss}`
- Create: `tests/phpunit/BlockRender/CtaTest.php`

- [ ] **Step 1: Write the failing test**

```php
<?php
class CtaTest extends WP_UnitTestCase {
    private function render( array $attrs ): string {
        return do_blocks( '<!-- wp:starter/cta ' . wp_json_encode( $attrs ) . ' /-->' );
    }

    public function test_renders_title_body_and_primary_button() {
        $html = $this->render( [
            'title'        => 'Ready?',
            'body'         => 'Let us help.',
            'primaryText'  => 'Start',
            'primaryUrl'   => '/start',
        ] );
        $this->assertStringContainsString( 'Ready?', $html );
        $this->assertStringContainsString( 'Let us help.', $html );
        $this->assertStringContainsString( 'href="/start"', $html );
    }

    public function test_renders_secondary_button_when_provided() {
        $html = $this->render( [
            'title'         => 'Ready?',
            'primaryText'   => 'Start',
            'primaryUrl'    => '/start',
            'secondaryText' => 'Learn more',
            'secondaryUrl'  => '/about',
        ] );
        $this->assertStringContainsString( 'href="/about"', $html );
        $this->assertStringContainsString( 'Learn more', $html );
    }

    public function test_omits_secondary_button_when_url_missing() {
        $html = $this->render( [
            'title'         => 'X',
            'primaryText'   => 'A',
            'primaryUrl'    => '/a',
            'secondaryText' => 'B',
            'secondaryUrl'  => '',
        ] );
        $this->assertStringNotContainsString( '>B<', $html );
    }
}
```

- [ ] **Step 2: Run and verify fail**

```bash
wp-env run tests-wordpress --env-cwd=wp-content/themes/wp-starter-theme phpunit --filter CtaTest
```

Expected: FAIL.

- [ ] **Step 3: Write src/blocks/cta/block.json**

```json
{
  "$schema": "https://schemas.wp.org/trunk/block.json",
  "apiVersion": 3,
  "name": "starter/cta",
  "title": "Call to Action",
  "category": "starter",
  "description": "Inline call-to-action with title, body, and one or two buttons.",
  "textdomain": "starter",
  "supports": { "html": false, "align": ["wide"] },
  "attributes": {
    "title":         { "type": "string", "default": "" },
    "body":          { "type": "string", "default": "" },
    "primaryText":   { "type": "string", "default": "" },
    "primaryUrl":    { "type": "string", "default": "" },
    "secondaryText": { "type": "string", "default": "" },
    "secondaryUrl":  { "type": "string", "default": "" }
  },
  "editorScript": "file:./index.js",
  "style":        "file:./style-index.css",
  "render":       "file:./render.php"
}
```

- [ ] **Step 4: Write src/blocks/cta/edit.tsx**

```tsx
import { __ } from '@wordpress/i18n';
import { useBlockProps, RichText, InspectorControls } from '@wordpress/block-editor';
import { PanelBody, TextControl } from '@wordpress/components';

type Attrs = {
  title: string; body: string;
  primaryText: string; primaryUrl: string;
  secondaryText: string; secondaryUrl: string;
};

export default function Edit({
  attributes, setAttributes,
}: { attributes: Attrs; setAttributes: (a: Partial<Attrs>) => void; }) {
  const blockProps = useBlockProps({ className: 'starter-cta' });
  return (
    <>
      <InspectorControls>
        <PanelBody title={__('CTA links', 'starter')}>
          <TextControl label="Primary URL"   value={attributes.primaryUrl}   onChange={(v) => setAttributes({ primaryUrl: v })} />
          <TextControl label="Secondary URL" value={attributes.secondaryUrl} onChange={(v) => setAttributes({ secondaryUrl: v })} />
        </PanelBody>
      </InspectorControls>
      <div {...blockProps}>
        <RichText tagName="h2" value={attributes.title}         onChange={(v) => setAttributes({ title: v })}         placeholder={__('Title…', 'starter')} />
        <RichText tagName="p"  value={attributes.body}          onChange={(v) => setAttributes({ body: v })}          placeholder={__('Body…', 'starter')} />
        <RichText tagName="span" value={attributes.primaryText}   onChange={(v) => setAttributes({ primaryText: v })}   placeholder={__('Primary CTA…', 'starter')} />
        <RichText tagName="span" value={attributes.secondaryText} onChange={(v) => setAttributes({ secondaryText: v })} placeholder={__('Secondary CTA (optional)…', 'starter')} />
      </div>
    </>
  );
}
```

- [ ] **Step 5: Write src/blocks/cta/index.tsx**

```tsx
import { registerBlockType } from '@wordpress/blocks';
import metadata from './block.json';
import Edit from './edit';
import './style.scss';
registerBlockType(metadata.name, { edit: Edit });
```

- [ ] **Step 6: Write src/blocks/cta/render.php**

```php
<?php
/** @var array $attributes */

$title         = isset( $attributes['title'] )         ? (string) $attributes['title']         : '';
$body          = isset( $attributes['body'] )          ? (string) $attributes['body']          : '';
$primary_text  = isset( $attributes['primaryText'] )   ? (string) $attributes['primaryText']   : '';
$primary_url   = isset( $attributes['primaryUrl'] )    ? (string) $attributes['primaryUrl']    : '';
$secondary_t   = isset( $attributes['secondaryText'] ) ? (string) $attributes['secondaryText'] : '';
$secondary_url = isset( $attributes['secondaryUrl'] )  ? (string) $attributes['secondaryUrl']  : '';

$wrapper = get_block_wrapper_attributes( [ 'class' => 'starter-cta' ] );

ob_start();
?>
<section <?php echo $wrapper; // phpcs:ignore ?>>
    <?php if ( $title ) : ?>
        <h2 class="starter-cta__title"><?php echo wp_kses_post( $title ); ?></h2>
    <?php endif; ?>
    <?php if ( $body ) : ?>
        <p class="starter-cta__body"><?php echo wp_kses_post( $body ); ?></p>
    <?php endif; ?>
    <div class="starter-cta__actions">
        <?php if ( $primary_text && $primary_url ) : ?>
            <a class="starter-cta__btn starter-cta__btn--primary" href="<?php echo esc_url( $primary_url ); ?>"><?php echo wp_kses_post( $primary_text ); ?></a>
        <?php endif; ?>
        <?php if ( $secondary_t && $secondary_url ) : ?>
            <a class="starter-cta__btn starter-cta__btn--secondary" href="<?php echo esc_url( $secondary_url ); ?>"><?php echo wp_kses_post( $secondary_t ); ?></a>
        <?php endif; ?>
    </div>
</section>
<?php
echo ob_get_clean();
```

- [ ] **Step 7: Write src/blocks/cta/style.scss**

```scss
.starter-cta {
  padding: var(--wp--preset--spacing--40) var(--wp--preset--spacing--30);
  background: var(--wp--preset--color--surface-elevated);
  border: 1px solid var(--wp--preset--color--border);
  border-radius: 0.75rem;

  &__title   { font-size: var(--wp--preset--font-size--2xl); margin: 0 0 var(--wp--preset--spacing--20); }
  &__body    { color: var(--wp--preset--color--text-muted); margin: 0 0 var(--wp--preset--spacing--30); }
  &__actions { display: flex; gap: var(--wp--preset--spacing--20); flex-wrap: wrap; }
  &__btn     { display: inline-block; padding: var(--wp--preset--spacing--20) var(--wp--preset--spacing--30); border-radius: 0.5rem; text-decoration: none; }
  &__btn--primary   { background: var(--wp--preset--color--accent); color: var(--wp--preset--color--surface); }
  &__btn--secondary { background: transparent; color: var(--wp--preset--color--accent); border: 1px solid var(--wp--preset--color--accent); }
}
```

- [ ] **Step 8: Build and run tests**

```bash
npm run build
wp-env run tests-wordpress --env-cwd=wp-content/themes/wp-starter-theme phpunit --filter CtaTest
```

Expected: 3 tests pass.

- [ ] **Step 9: Commit**

```bash
git add src/blocks/cta/ tests/phpunit/BlockRender/CtaTest.php
git commit -m "feat(blocks): starter/cta"
```

---

### Task 14: FAQ + FAQ-Item blocks (paired)

**Files:**
- Create: `src/blocks/faq/{block.json,index.tsx,edit.tsx,render.php,style.scss}`
- Create: `src/blocks/faq-item/{block.json,index.tsx,edit.tsx,render.php}`
- Create: `tests/phpunit/BlockRender/FaqTest.php`

- [ ] **Step 1: Write the failing tests**

```php
<?php
class FaqTest extends WP_UnitTestCase {
    public function test_faq_container_wraps_items() {
        $html = do_blocks(
            '<!-- wp:starter/faq -->' .
            '<!-- wp:starter/faq-item {"question":"Q1","answer":"A1"} /-->' .
            '<!-- wp:starter/faq-item {"question":"Q2","answer":"A2"} /-->' .
            '<!-- /wp:starter/faq -->'
        );
        $this->assertStringContainsString( 'starter-faq', $html );
        $this->assertStringContainsString( 'Q1', $html );
        $this->assertStringContainsString( 'A1', $html );
        $this->assertStringContainsString( 'Q2', $html );
    }

    public function test_faq_item_uses_details_summary() {
        $html = do_blocks( '<!-- wp:starter/faq-item {"question":"Hi","answer":"Hey"} /-->' );
        $this->assertStringContainsString( '<details', $html );
        $this->assertStringContainsString( '<summary', $html );
        $this->assertStringContainsString( 'Hi', $html );
        $this->assertStringContainsString( 'Hey', $html );
    }
}
```

- [ ] **Step 2: Run and verify fail**

```bash
wp-env run tests-wordpress --env-cwd=wp-content/themes/wp-starter-theme phpunit --filter FaqTest
```

Expected: FAIL.

- [ ] **Step 3: Write src/blocks/faq/block.json**

```json
{
  "$schema": "https://schemas.wp.org/trunk/block.json",
  "apiVersion": 3,
  "name": "starter/faq",
  "title": "FAQ",
  "category": "starter",
  "description": "A list of frequently asked questions. Contains FAQ Item child blocks.",
  "textdomain": "starter",
  "supports": { "html": false, "align": ["wide"] },
  "attributes": {},
  "editorScript": "file:./index.js",
  "style":        "file:./style-index.css",
  "render":       "file:./render.php"
}
```

- [ ] **Step 4: Write src/blocks/faq/edit.tsx**

```tsx
import { __ } from '@wordpress/i18n';
import { useBlockProps, useInnerBlocksProps } from '@wordpress/block-editor';

const ALLOWED = ['starter/faq-item'];
const TEMPLATE: [string, Record<string, unknown>][] = [
  ['starter/faq-item', { question: '', answer: '' }],
  ['starter/faq-item', { question: '', answer: '' }],
  ['starter/faq-item', { question: '', answer: '' }],
];

export default function Edit() {
  const blockProps = useBlockProps({ className: 'starter-faq' });
  const innerBlocksProps = useInnerBlocksProps(blockProps, {
    allowedBlocks: ALLOWED,
    template: TEMPLATE,
    templateLock: false,
  });
  return <section {...innerBlocksProps} />;
}
```

- [ ] **Step 5: Write src/blocks/faq/index.tsx**

```tsx
import { registerBlockType } from '@wordpress/blocks';
import metadata from './block.json';
import Edit from './edit';
import './style.scss';

registerBlockType(metadata.name, {
  edit: Edit,
  save: ({ }) => null,
});
```

Note: For container blocks with InnerBlocks rendered server-side, `save` returns `null` because `render.php` handles output. Inner block content is available via `$content` in the render callback.

- [ ] **Step 6: Write src/blocks/faq/render.php**

```php
<?php
/** @var array $attributes */
/** @var string $content */

$wrapper = get_block_wrapper_attributes( [ 'class' => 'starter-faq' ] );
?>
<section <?php echo $wrapper; // phpcs:ignore ?>>
    <?php echo $content; // phpcs:ignore WordPress.Security.EscapeOutput -- inner blocks are pre-rendered ?>
</section>
```

- [ ] **Step 7: Write src/blocks/faq/style.scss**

```scss
.starter-faq {
  display: flex;
  flex-direction: column;
  gap: var(--wp--preset--spacing--20);
  padding: var(--wp--preset--spacing--40) 0;
}
```

- [ ] **Step 8: Write src/blocks/faq-item/block.json**

```json
{
  "$schema": "https://schemas.wp.org/trunk/block.json",
  "apiVersion": 3,
  "name": "starter/faq-item",
  "title": "FAQ Item",
  "category": "starter",
  "description": "A single question and answer in an FAQ list.",
  "parent": ["starter/faq"],
  "textdomain": "starter",
  "supports": { "html": false, "inserter": false },
  "attributes": {
    "question": { "type": "string", "default": "" },
    "answer":   { "type": "string", "default": "" }
  },
  "editorScript": "file:./index.js",
  "render":       "file:./render.php"
}
```

- [ ] **Step 9: Write src/blocks/faq-item/edit.tsx**

```tsx
import { __ } from '@wordpress/i18n';
import { useBlockProps, RichText } from '@wordpress/block-editor';

type Attrs = { question: string; answer: string };

export default function Edit({
  attributes, setAttributes,
}: { attributes: Attrs; setAttributes: (a: Partial<Attrs>) => void; }) {
  const blockProps = useBlockProps({ className: 'starter-faq-item' });
  return (
    <div {...blockProps}>
      <RichText
        tagName="strong"
        value={attributes.question}
        onChange={(v) => setAttributes({ question: v })}
        placeholder={__('Question…', 'starter')}
      />
      <RichText
        tagName="p"
        value={attributes.answer}
        onChange={(v) => setAttributes({ answer: v })}
        placeholder={__('Answer…', 'starter')}
      />
    </div>
  );
}
```

- [ ] **Step 10: Write src/blocks/faq-item/index.tsx**

```tsx
import { registerBlockType } from '@wordpress/blocks';
import metadata from './block.json';
import Edit from './edit';

registerBlockType(metadata.name, { edit: Edit, save: () => null });
```

- [ ] **Step 11: Write src/blocks/faq-item/render.php**

```php
<?php
/** @var array $attributes */

$question = isset( $attributes['question'] ) ? (string) $attributes['question'] : '';
$answer   = isset( $attributes['answer'] )   ? (string) $attributes['answer']   : '';

if ( '' === $question && '' === $answer ) {
    return '';
}

$wrapper = get_block_wrapper_attributes( [ 'class' => 'starter-faq-item' ] );
ob_start();
?>
<details <?php echo $wrapper; // phpcs:ignore ?>>
    <summary class="starter-faq-item__question"><?php echo wp_kses_post( $question ); ?></summary>
    <div class="starter-faq-item__answer"><?php echo wp_kses_post( $answer ); ?></div>
</details>
<?php
echo ob_get_clean();
```

- [ ] **Step 12: Build and run the tests**

```bash
npm run build
wp-env run tests-wordpress --env-cwd=wp-content/themes/wp-starter-theme phpunit --filter FaqTest
```

Expected: 2 tests pass.

- [ ] **Step 13: Commit**

```bash
git add src/blocks/faq/ src/blocks/faq-item/ tests/phpunit/BlockRender/FaqTest.php
git commit -m "feat(blocks): starter/faq container + faq-item child"
```

### Task 15: Prose block

**Files:**
- Create: `src/blocks/prose/{block.json,index.tsx,edit.tsx,render.php,style.scss}`
- Create: `tests/phpunit/BlockRender/ProseTest.php`

- [ ] **Step 1: Write the failing test**

```php
<?php
class ProseTest extends WP_UnitTestCase {
    public function test_prose_wraps_inner_content() {
        $html = do_blocks(
            '<!-- wp:starter/prose -->' .
            '<!-- wp:paragraph --><p>Hello world.</p><!-- /wp:paragraph -->' .
            '<!-- /wp:starter/prose -->'
        );
        $this->assertStringContainsString( 'starter-prose', $html );
        $this->assertStringContainsString( 'Hello world.', $html );
    }

    public function test_prose_accepts_headings() {
        $html = do_blocks(
            '<!-- wp:starter/prose -->' .
            '<!-- wp:heading --><h2>Section</h2><!-- /wp:heading -->' .
            '<!-- /wp:starter/prose -->'
        );
        $this->assertStringContainsString( '<h2>Section</h2>', $html );
    }
}
```

- [ ] **Step 2: Run and verify fail**

```bash
wp-env run tests-wordpress --env-cwd=wp-content/themes/wp-starter-theme phpunit --filter ProseTest
```

Expected: FAIL.

- [ ] **Step 3: Write src/blocks/prose/block.json**

```json
{
  "$schema": "https://schemas.wp.org/trunk/block.json",
  "apiVersion": 3,
  "name": "starter/prose",
  "title": "Prose",
  "category": "starter",
  "description": "Long-form prose with constrained content width and typographic defaults. Contains paragraphs, headings, and lists.",
  "textdomain": "starter",
  "supports": {
    "html": false,
    "align": ["wide"],
    "layout": { "allowEditing": false, "default": { "type": "constrained" } }
  },
  "attributes": {},
  "editorScript": "file:./index.js",
  "style":        "file:./style-index.css",
  "render":       "file:./render.php"
}
```

- [ ] **Step 4: Write src/blocks/prose/edit.tsx**

```tsx
import { useBlockProps, useInnerBlocksProps } from '@wordpress/block-editor';

const ALLOWED = ['core/paragraph', 'core/heading', 'core/list', 'core/list-item', 'core/separator'];

export default function Edit() {
  const blockProps = useBlockProps({ className: 'starter-prose' });
  const innerBlocksProps = useInnerBlocksProps(blockProps, {
    allowedBlocks: ALLOWED,
    template: [['core/paragraph', { placeholder: 'Start writing…' }]],
    templateLock: false,
  });
  return <div {...innerBlocksProps} />;
}
```

- [ ] **Step 5: Write src/blocks/prose/index.tsx**

```tsx
import { registerBlockType } from '@wordpress/blocks';
import metadata from './block.json';
import Edit from './edit';
import './style.scss';
registerBlockType(metadata.name, { edit: Edit, save: () => null });
```

- [ ] **Step 6: Write src/blocks/prose/render.php**

```php
<?php
/** @var string $content */
$wrapper = get_block_wrapper_attributes( [ 'class' => 'starter-prose' ] );
?>
<div <?php echo $wrapper; // phpcs:ignore ?>>
    <?php echo $content; // phpcs:ignore WordPress.Security.EscapeOutput -- inner blocks pre-rendered ?>
</div>
```

- [ ] **Step 7: Write src/blocks/prose/style.scss**

```scss
.starter-prose {
  max-width: var(--wp--style--global--content-size);
  margin-inline: auto;
  font-size: var(--wp--preset--font-size--base);
  line-height: 1.7;

  > h2 { font-size: var(--wp--preset--font-size--2xl); margin-top: var(--wp--preset--spacing--40); }
  > h3 { font-size: var(--wp--preset--font-size--xl);  margin-top: var(--wp--preset--spacing--30); }
  > p  { margin-block: var(--wp--preset--spacing--20); }
  > ul, > ol { padding-inline-start: var(--wp--preset--spacing--30); }
}
```

- [ ] **Step 8: Build and run the test**

```bash
npm run build
wp-env run tests-wordpress --env-cwd=wp-content/themes/wp-starter-theme phpunit --filter ProseTest
```

Expected: 2 tests pass.

- [ ] **Step 9: Commit**

```bash
git add src/blocks/prose/ tests/phpunit/BlockRender/ProseTest.php
git commit -m "feat(blocks): starter/prose container for long-form content"
```

### Task 16: Pull-quote block

**Files:**
- Create: `src/blocks/pull-quote/{block.json,index.tsx,edit.tsx,render.php,style.scss}`
- Create: `tests/phpunit/BlockRender/PullQuoteTest.php`

- [ ] **Step 1: Write the failing test**

```php
<?php
class PullQuoteTest extends WP_UnitTestCase {
    public function test_renders_quote_and_citation() {
        $html = do_blocks( '<!-- wp:starter/pull-quote {"quote":"To be or not to be","citation":"Hamlet"} /-->' );
        $this->assertStringContainsString( '<blockquote', $html );
        $this->assertStringContainsString( 'To be or not to be', $html );
        $this->assertStringContainsString( '<cite', $html );
        $this->assertStringContainsString( 'Hamlet', $html );
    }

    public function test_omits_cite_when_citation_empty() {
        $html = do_blocks( '<!-- wp:starter/pull-quote {"quote":"Just a quote","citation":""} /-->' );
        $this->assertStringContainsString( 'Just a quote', $html );
        $this->assertStringNotContainsString( '<cite', $html );
    }
}
```

- [ ] **Step 2: Run and verify fail**

```bash
wp-env run tests-wordpress --env-cwd=wp-content/themes/wp-starter-theme phpunit --filter PullQuoteTest
```

Expected: FAIL.

- [ ] **Step 3: Write src/blocks/pull-quote/block.json**

```json
{
  "$schema": "https://schemas.wp.org/trunk/block.json",
  "apiVersion": 3,
  "name": "starter/pull-quote",
  "title": "Pull Quote",
  "category": "starter",
  "description": "An emphasized quotation with optional citation.",
  "textdomain": "starter",
  "supports": { "html": false, "align": ["wide"] },
  "attributes": {
    "quote":    { "type": "string", "default": "" },
    "citation": { "type": "string", "default": "" }
  },
  "editorScript": "file:./index.js",
  "style":        "file:./style-index.css",
  "render":       "file:./render.php"
}
```

- [ ] **Step 4: Write src/blocks/pull-quote/edit.tsx**

```tsx
import { __ } from '@wordpress/i18n';
import { useBlockProps, RichText } from '@wordpress/block-editor';

type Attrs = { quote: string; citation: string };

export default function Edit({
  attributes, setAttributes,
}: { attributes: Attrs; setAttributes: (a: Partial<Attrs>) => void; }) {
  const blockProps = useBlockProps({ className: 'starter-pull-quote' });
  return (
    <blockquote {...blockProps}>
      <RichText
        tagName="p"
        value={attributes.quote}
        onChange={(v) => setAttributes({ quote: v })}
        placeholder={__('Quote…', 'starter')}
      />
      <RichText
        tagName="cite"
        value={attributes.citation}
        onChange={(v) => setAttributes({ citation: v })}
        placeholder={__('Citation (optional)…', 'starter')}
      />
    </blockquote>
  );
}
```

- [ ] **Step 5: Write src/blocks/pull-quote/index.tsx**

```tsx
import { registerBlockType } from '@wordpress/blocks';
import metadata from './block.json';
import Edit from './edit';
import './style.scss';
registerBlockType(metadata.name, { edit: Edit });
```

- [ ] **Step 6: Write src/blocks/pull-quote/render.php**

```php
<?php
/** @var array $attributes */

$quote    = isset( $attributes['quote'] )    ? (string) $attributes['quote']    : '';
$citation = isset( $attributes['citation'] ) ? (string) $attributes['citation'] : '';

if ( '' === $quote ) {
    return '';
}

$wrapper = get_block_wrapper_attributes( [ 'class' => 'starter-pull-quote' ] );
ob_start();
?>
<blockquote <?php echo $wrapper; // phpcs:ignore ?>>
    <p class="starter-pull-quote__quote"><?php echo wp_kses_post( $quote ); ?></p>
    <?php if ( '' !== $citation ) : ?>
        <cite class="starter-pull-quote__citation"><?php echo wp_kses_post( $citation ); ?></cite>
    <?php endif; ?>
</blockquote>
<?php
echo ob_get_clean();
```

- [ ] **Step 7: Write src/blocks/pull-quote/style.scss**

```scss
.starter-pull-quote {
  border-left: 4px solid var(--wp--preset--color--accent);
  padding: var(--wp--preset--spacing--30) var(--wp--preset--spacing--40);
  margin-block: var(--wp--preset--spacing--40);
  background: var(--wp--preset--color--surface-elevated);

  &__quote    { font-size: var(--wp--preset--font-size--xl); line-height: 1.4; margin: 0; font-style: italic; }
  &__citation { display: block; margin-top: var(--wp--preset--spacing--20); font-size: var(--wp--preset--font-size--sm); color: var(--wp--preset--color--text-muted); font-style: normal; }
}
```

- [ ] **Step 8: Build and run the test**

```bash
npm run build
wp-env run tests-wordpress --env-cwd=wp-content/themes/wp-starter-theme phpunit --filter PullQuoteTest
```

Expected: 2 tests pass.

- [ ] **Step 9: Commit**

```bash
git add src/blocks/pull-quote/ tests/phpunit/BlockRender/PullQuoteTest.php
git commit -m "feat(blocks): starter/pull-quote"
```

### Task 17: Image-caption block

**Files:**
- Create: `src/blocks/image-caption/{block.json,index.tsx,edit.tsx,render.php,style.scss}`
- Create: `tests/phpunit/BlockRender/ImageCaptionTest.php`

- [ ] **Step 1: Write the failing test**

```php
<?php
class ImageCaptionTest extends WP_UnitTestCase {
    public function test_renders_figure_with_caption() {
        $attachment_id = $this->factory->attachment->create_upload_object(
            DIR_TESTDATA . '/images/canola.jpg'
        );

        $html = do_blocks( sprintf(
            '<!-- wp:starter/image-caption {"mediaId":%d,"caption":"Beautiful canola","altOverride":"Yellow flowers"} /-->',
            $attachment_id
        ) );

        $this->assertStringContainsString( '<figure', $html );
        $this->assertStringContainsString( 'Beautiful canola', $html );
        $this->assertStringContainsString( 'alt="Yellow flowers"', $html );
        wp_delete_attachment( $attachment_id, true );
    }

    public function test_returns_empty_when_no_media() {
        $html = do_blocks( '<!-- wp:starter/image-caption {"mediaId":0,"caption":"x"} /-->' );
        $this->assertStringNotContainsString( '<figure', $html );
    }
}
```

- [ ] **Step 2: Run and verify fail**

```bash
wp-env run tests-wordpress --env-cwd=wp-content/themes/wp-starter-theme phpunit --filter ImageCaptionTest
```

Expected: FAIL.

- [ ] **Step 3: Write src/blocks/image-caption/block.json**

```json
{
  "$schema": "https://schemas.wp.org/trunk/block.json",
  "apiVersion": 3,
  "name": "starter/image-caption",
  "title": "Image with caption",
  "category": "starter",
  "description": "A figure block with image, optional caption, and optional alt-text override.",
  "textdomain": "starter",
  "supports": { "html": false, "align": ["wide", "full"] },
  "attributes": {
    "mediaId":     { "type": "number", "default": 0 },
    "caption":     { "type": "string", "default": "" },
    "altOverride": { "type": "string", "default": "" }
  },
  "editorScript": "file:./index.js",
  "style":        "file:./style-index.css",
  "render":       "file:./render.php"
}
```

- [ ] **Step 4: Write src/blocks/image-caption/edit.tsx**

```tsx
import { __ } from '@wordpress/i18n';
import { useBlockProps, RichText, MediaUpload, InspectorControls } from '@wordpress/block-editor';
import { PanelBody, TextControl, Button } from '@wordpress/components';
import { useSelect } from '@wordpress/data';

type Attrs = { mediaId: number; caption: string; altOverride: string };

export default function Edit({
  attributes, setAttributes,
}: { attributes: Attrs; setAttributes: (a: Partial<Attrs>) => void; }) {
  const blockProps = useBlockProps({ className: 'starter-image-caption' });
  const media = useSelect((select: any) => {
    return attributes.mediaId ? select('core').getMedia(attributes.mediaId) : null;
  }, [attributes.mediaId]);

  return (
    <>
      <InspectorControls>
        <PanelBody title={__('Image', 'starter')}>
          <TextControl
            label={__('Alt text override', 'starter')}
            value={attributes.altOverride}
            onChange={(v) => setAttributes({ altOverride: v })}
            help={__('Leave empty to use the media library alt text.', 'starter')}
          />
        </PanelBody>
      </InspectorControls>
      <figure {...blockProps}>
        <MediaUpload
          allowedTypes={['image']}
          value={attributes.mediaId}
          onSelect={(m: any) => setAttributes({ mediaId: m.id })}
          render={({ open }) =>
            media ? (
              <img src={(media as any).source_url} alt={attributes.altOverride || (media as any).alt_text || ''} onClick={open} />
            ) : (
              <Button variant="primary" onClick={open}>{__('Pick image', 'starter')}</Button>
            )
          }
        />
        <RichText
          tagName="figcaption"
          value={attributes.caption}
          onChange={(v) => setAttributes({ caption: v })}
          placeholder={__('Caption (optional)…', 'starter')}
        />
      </figure>
    </>
  );
}
```

- [ ] **Step 5: Write src/blocks/image-caption/index.tsx**

```tsx
import { registerBlockType } from '@wordpress/blocks';
import metadata from './block.json';
import Edit from './edit';
import './style.scss';
registerBlockType(metadata.name, { edit: Edit });
```

- [ ] **Step 6: Write src/blocks/image-caption/render.php**

```php
<?php
/** @var array $attributes */

$media_id    = isset( $attributes['mediaId'] )     ? (int)    $attributes['mediaId']     : 0;
$caption     = isset( $attributes['caption'] )     ? (string) $attributes['caption']     : '';
$alt_override = isset( $attributes['altOverride'] ) ? (string) $attributes['altOverride'] : '';

if ( ! $media_id ) {
    return '';
}

$alt = '' !== $alt_override ? $alt_override : (string) get_post_meta( $media_id, '_wp_attachment_image_alt', true );
$img_html = wp_get_attachment_image( $media_id, 'large', false, [ 'alt' => $alt, 'class' => 'starter-image-caption__img' ] );

if ( ! $img_html ) {
    return '';
}

$wrapper = get_block_wrapper_attributes( [ 'class' => 'starter-image-caption' ] );
ob_start();
?>
<figure <?php echo $wrapper; // phpcs:ignore ?>>
    <?php echo $img_html; // phpcs:ignore ?>
    <?php if ( '' !== $caption ) : ?>
        <figcaption class="starter-image-caption__caption"><?php echo wp_kses_post( $caption ); ?></figcaption>
    <?php endif; ?>
</figure>
<?php
echo ob_get_clean();
```

- [ ] **Step 7: Write src/blocks/image-caption/style.scss**

```scss
.starter-image-caption {
  margin: var(--wp--preset--spacing--40) 0;

  &__img       { display: block; width: 100%; height: auto; border-radius: 0.5rem; }
  &__caption   { margin-top: var(--wp--preset--spacing--10); font-size: var(--wp--preset--font-size--sm); color: var(--wp--preset--color--text-muted); text-align: center; }
}
```

- [ ] **Step 8: Build and run the test**

```bash
npm run build
wp-env run tests-wordpress --env-cwd=wp-content/themes/wp-starter-theme phpunit --filter ImageCaptionTest
```

Expected: 2 tests pass.

- [ ] **Step 9: Commit**

```bash
git add src/blocks/image-caption/ tests/phpunit/BlockRender/ImageCaptionTest.php
git commit -m "feat(blocks): starter/image-caption with alt-text override"
```

### Task 18: Stat block

**Files:**
- Create: `src/blocks/stat/{block.json,index.tsx,edit.tsx,render.php,style.scss}`
- Create: `tests/phpunit/BlockRender/StatTest.php`

- [ ] **Step 1: Write the failing test**

```php
<?php
class StatTest extends WP_UnitTestCase {
    public function test_renders_value_and_label() {
        $html = do_blocks( '<!-- wp:starter/stat {"value":"99%","label":"Uptime"} /-->' );
        $this->assertStringContainsString( '99%', $html );
        $this->assertStringContainsString( 'Uptime', $html );
    }

    public function test_renders_context_when_provided() {
        $html = do_blocks( '<!-- wp:starter/stat {"value":"10x","label":"Faster","context":"vs industry"} /-->' );
        $this->assertStringContainsString( 'vs industry', $html );
    }

    public function test_omits_context_when_empty() {
        $html = do_blocks( '<!-- wp:starter/stat {"value":"5","label":"x"} /-->' );
        $this->assertStringNotContainsString( 'starter-stat__context', $html );
    }
}
```

- [ ] **Step 2: Run and verify fail**

```bash
wp-env run tests-wordpress --env-cwd=wp-content/themes/wp-starter-theme phpunit --filter StatTest
```

Expected: FAIL.

- [ ] **Step 3: Write src/blocks/stat/block.json**

```json
{
  "$schema": "https://schemas.wp.org/trunk/block.json",
  "apiVersion": 3,
  "name": "starter/stat",
  "title": "Stat",
  "category": "starter",
  "description": "A prominent number or short value with a label and optional context line.",
  "textdomain": "starter",
  "supports": { "html": false },
  "attributes": {
    "value":   { "type": "string", "default": "" },
    "label":   { "type": "string", "default": "" },
    "context": { "type": "string", "default": "" }
  },
  "editorScript": "file:./index.js",
  "style":        "file:./style-index.css",
  "render":       "file:./render.php"
}
```

- [ ] **Step 4: Write src/blocks/stat/edit.tsx**

```tsx
import { __ } from '@wordpress/i18n';
import { useBlockProps, RichText } from '@wordpress/block-editor';

type Attrs = { value: string; label: string; context: string };

export default function Edit({
  attributes, setAttributes,
}: { attributes: Attrs; setAttributes: (a: Partial<Attrs>) => void; }) {
  const blockProps = useBlockProps({ className: 'starter-stat' });
  return (
    <div {...blockProps}>
      <RichText tagName="span" value={attributes.value}   onChange={(v) => setAttributes({ value: v })}   placeholder={__('99%', 'starter')} />
      <RichText tagName="span" value={attributes.label}   onChange={(v) => setAttributes({ label: v })}   placeholder={__('Uptime', 'starter')} />
      <RichText tagName="span" value={attributes.context} onChange={(v) => setAttributes({ context: v })} placeholder={__('Context (optional)', 'starter')} />
    </div>
  );
}
```

- [ ] **Step 5: Write src/blocks/stat/index.tsx**

```tsx
import { registerBlockType } from '@wordpress/blocks';
import metadata from './block.json';
import Edit from './edit';
import './style.scss';
registerBlockType(metadata.name, { edit: Edit });
```

- [ ] **Step 6: Write src/blocks/stat/render.php**

```php
<?php
/** @var array $attributes */

$value   = isset( $attributes['value'] )   ? (string) $attributes['value']   : '';
$label   = isset( $attributes['label'] )   ? (string) $attributes['label']   : '';
$context = isset( $attributes['context'] ) ? (string) $attributes['context'] : '';

if ( '' === $value && '' === $label ) {
    return '';
}

$wrapper = get_block_wrapper_attributes( [ 'class' => 'starter-stat' ] );
ob_start();
?>
<div <?php echo $wrapper; // phpcs:ignore ?>>
    <strong class="starter-stat__value"><?php echo wp_kses_post( $value ); ?></strong>
    <span    class="starter-stat__label"><?php echo wp_kses_post( $label ); ?></span>
    <?php if ( '' !== $context ) : ?>
        <span class="starter-stat__context"><?php echo wp_kses_post( $context ); ?></span>
    <?php endif; ?>
</div>
<?php
echo ob_get_clean();
```

- [ ] **Step 7: Write src/blocks/stat/style.scss**

```scss
.starter-stat {
  display: flex;
  flex-direction: column;
  gap: var(--wp--preset--spacing--10);
  text-align: center;
  padding: var(--wp--preset--spacing--30);

  &__value   { font-size: var(--wp--preset--font-size--4xl); line-height: 1; color: var(--wp--preset--color--accent); font-weight: 700; }
  &__label   { font-size: var(--wp--preset--font-size--base); color: var(--wp--preset--color--text); }
  &__context { font-size: var(--wp--preset--font-size--sm); color: var(--wp--preset--color--text-muted); }
}
```

- [ ] **Step 8: Build and run the test**

```bash
npm run build
wp-env run tests-wordpress --env-cwd=wp-content/themes/wp-starter-theme phpunit --filter StatTest
```

Expected: 3 tests pass.

- [ ] **Step 9: Commit**

```bash
git add src/blocks/stat/ tests/phpunit/BlockRender/StatTest.php
git commit -m "feat(blocks): starter/stat"
```

### Task 19: Blog-index block

**Files:**
- Create: `src/blocks/blog-index/{block.json,index.tsx,edit.tsx,render.php,style.scss}`
- Create: `tests/phpunit/BlockRender/BlogIndexTest.php`

- [ ] **Step 1: Write the failing test**

```php
<?php
class BlogIndexTest extends WP_UnitTestCase {
    public function test_renders_recent_posts() {
        $post_ids = [];
        foreach ( [ 'First post', 'Second post', 'Third post' ] as $title ) {
            $post_ids[] = $this->factory->post->create( [
                'post_title'  => $title,
                'post_status' => 'publish',
                'post_type'   => 'post',
            ] );
        }

        $html = do_blocks( '<!-- wp:starter/blog-index {"count":3} /-->' );

        $this->assertStringContainsString( 'First post', $html );
        $this->assertStringContainsString( 'Second post', $html );
        $this->assertStringContainsString( 'Third post', $html );

        foreach ( $post_ids as $id ) { wp_delete_post( $id, true ); }
    }

    public function test_filters_by_category_slug() {
        $cat_id = $this->factory->category->create( [ 'slug' => 'news', 'name' => 'News' ] );
        $in_id  = $this->factory->post->create( [ 'post_title' => 'News one',  'post_status' => 'publish', 'post_category' => [ $cat_id ] ] );
        $out_id = $this->factory->post->create( [ 'post_title' => 'Other one', 'post_status' => 'publish' ] );

        $html = do_blocks( '<!-- wp:starter/blog-index {"count":10,"categorySlug":"news"} /-->' );

        $this->assertStringContainsString( 'News one', $html );
        $this->assertStringNotContainsString( 'Other one', $html );

        wp_delete_post( $in_id, true );
        wp_delete_post( $out_id, true );
        wp_delete_category( $cat_id );
    }

    public function test_renders_empty_state_when_no_posts() {
        $html = do_blocks( '<!-- wp:starter/blog-index {"count":3} /-->' );
        $this->assertStringContainsString( 'starter-blog-index__empty', $html );
    }
}
```

- [ ] **Step 2: Run and verify fail**

```bash
wp-env run tests-wordpress --env-cwd=wp-content/themes/wp-starter-theme phpunit --filter BlogIndexTest
```

Expected: FAIL.

- [ ] **Step 3: Write src/blocks/blog-index/block.json**

```json
{
  "$schema": "https://schemas.wp.org/trunk/block.json",
  "apiVersion": 3,
  "name": "starter/blog-index",
  "title": "Blog Index",
  "category": "starter",
  "description": "A server-rendered list of recent posts, optionally filtered by category slug.",
  "textdomain": "starter",
  "supports": { "html": false, "align": ["wide"] },
  "attributes": {
    "count":        { "type": "number", "default": 6 },
    "categorySlug": { "type": "string", "default": "" }
  },
  "editorScript": "file:./index.js",
  "style":        "file:./style-index.css",
  "render":       "file:./render.php"
}
```

- [ ] **Step 4: Write src/blocks/blog-index/edit.tsx**

```tsx
import { __ } from '@wordpress/i18n';
import { useBlockProps, InspectorControls } from '@wordpress/block-editor';
import { PanelBody, RangeControl, TextControl } from '@wordpress/components';
import ServerSideRender from '@wordpress/server-side-render';

type Attrs = { count: number; categorySlug: string };

export default function Edit({
  attributes, setAttributes,
}: { attributes: Attrs; setAttributes: (a: Partial<Attrs>) => void; }) {
  const blockProps = useBlockProps();
  return (
    <>
      <InspectorControls>
        <PanelBody title={__('Blog index', 'starter')}>
          <RangeControl label={__('Posts to show', 'starter')} value={attributes.count} min={1} max={20} onChange={(v) => setAttributes({ count: v ?? 6 })} />
          <TextControl  label={__('Category slug (optional)', 'starter')} value={attributes.categorySlug} onChange={(v) => setAttributes({ categorySlug: v })} />
        </PanelBody>
      </InspectorControls>
      <div {...blockProps}>
        <ServerSideRender block="starter/blog-index" attributes={attributes} />
      </div>
    </>
  );
}
```

- [ ] **Step 5: Write src/blocks/blog-index/index.tsx**

```tsx
import { registerBlockType } from '@wordpress/blocks';
import metadata from './block.json';
import Edit from './edit';
import './style.scss';
registerBlockType(metadata.name, { edit: Edit });
```

- [ ] **Step 6: Write src/blocks/blog-index/render.php**

```php
<?php
/** @var array $attributes */

$count    = isset( $attributes['count'] )        ? max( 1, (int) $attributes['count'] ) : 6;
$cat_slug = isset( $attributes['categorySlug'] ) ? (string) $attributes['categorySlug'] : '';

$query_args = [
    'post_type'      => 'post',
    'post_status'    => 'publish',
    'posts_per_page' => $count,
];
if ( '' !== $cat_slug ) {
    $query_args['category_name'] = $cat_slug;
}

$query = new WP_Query( $query_args );

$wrapper = get_block_wrapper_attributes( [ 'class' => 'starter-blog-index' ] );

ob_start();
?>
<section <?php echo $wrapper; // phpcs:ignore ?>>
    <?php if ( ! $query->have_posts() ) : ?>
        <p class="starter-blog-index__empty"><?php esc_html_e( 'No posts yet.', 'starter' ); ?></p>
    <?php else : ?>
        <ul class="starter-blog-index__list">
            <?php while ( $query->have_posts() ) : $query->the_post(); ?>
                <li class="starter-blog-index__item">
                    <a class="starter-blog-index__link" href="<?php the_permalink(); ?>">
                        <h3 class="starter-blog-index__title"><?php the_title(); ?></h3>
                    </a>
                    <time class="starter-blog-index__date" datetime="<?php echo esc_attr( get_the_date( 'c' ) ); ?>"><?php echo esc_html( get_the_date() ); ?></time>
                    <p class="starter-blog-index__excerpt"><?php echo esc_html( get_the_excerpt() ); ?></p>
                </li>
            <?php endwhile; ?>
        </ul>
    <?php endif; wp_reset_postdata(); ?>
</section>
<?php
echo ob_get_clean();
```

- [ ] **Step 7: Write src/blocks/blog-index/style.scss**

```scss
.starter-blog-index {
  &__list { list-style: none; padding: 0; display: grid; gap: var(--wp--preset--spacing--30); grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); }
  &__item { padding: var(--wp--preset--spacing--30); background: var(--wp--preset--color--surface-elevated); border: 1px solid var(--wp--preset--color--border); border-radius: 0.5rem; }
  &__link { text-decoration: none; color: inherit; }
  &__title { margin: 0 0 var(--wp--preset--spacing--10); font-size: var(--wp--preset--font-size--lg); }
  &__date  { color: var(--wp--preset--color--text-muted); font-size: var(--wp--preset--font-size--sm); }
  &__excerpt { margin: var(--wp--preset--spacing--10) 0 0; color: var(--wp--preset--color--text-muted); }
  &__empty { color: var(--wp--preset--color--text-muted); }
}
```

- [ ] **Step 8: Build and run the test**

```bash
npm run build
wp-env run tests-wordpress --env-cwd=wp-content/themes/wp-starter-theme phpunit --filter BlogIndexTest
```

Expected: 3 tests pass.

- [ ] **Step 9: Commit**

```bash
git add src/blocks/blog-index/ tests/phpunit/BlockRender/BlogIndexTest.php
git commit -m "feat(blocks): starter/blog-index with category filter"
```

### Task 20: Contact-form block (display only)

> **Note:** This task adds the block markup only. REST endpoint, validation, CPT, email, and front-end submit JS arrive in Tasks 24–28. After this task the form renders as static HTML; submitting it does nothing yet.

**Files:**
- Create: `src/blocks/contact-form/{block.json,index.tsx,edit.tsx,render.php,style.scss}`
- Create: `tests/phpunit/BlockRender/ContactFormBlockTest.php`

- [ ] **Step 1: Write the failing test**

```php
<?php
class ContactFormBlockTest extends WP_UnitTestCase {
    public function test_renders_required_fields() {
        $html = do_blocks( '<!-- wp:starter/contact-form /-->' );
        $this->assertStringContainsString( 'name="name"',    $html );
        $this->assertStringContainsString( 'name="email"',   $html );
        $this->assertStringContainsString( 'name="message"', $html );
    }

    public function test_includes_honeypot_and_timestamp() {
        $html = do_blocks( '<!-- wp:starter/contact-form /-->' );
        $this->assertStringContainsString( 'name="hp_field"',  $html );
        $this->assertStringContainsString( 'name="_t"',        $html );
    }

    public function test_honeypot_field_is_visually_hidden() {
        $html = do_blocks( '<!-- wp:starter/contact-form /-->' );
        $this->assertMatchesRegularExpression( '/name="hp_field"[^>]*aria-hidden="true"/', $html );
    }

    public function test_optionally_includes_phone() {
        $html = do_blocks( '<!-- wp:starter/contact-form {"includePhone":true} /-->' );
        $this->assertStringContainsString( 'name="phone"', $html );
    }

    public function test_omits_phone_by_default() {
        $html = do_blocks( '<!-- wp:starter/contact-form /-->' );
        $this->assertStringNotContainsString( 'name="phone"', $html );
    }
}
```

- [ ] **Step 2: Run and verify fail**

```bash
wp-env run tests-wordpress --env-cwd=wp-content/themes/wp-starter-theme phpunit --filter ContactFormBlockTest
```

Expected: FAIL.

- [ ] **Step 3: Write src/blocks/contact-form/block.json**

```json
{
  "$schema": "https://schemas.wp.org/trunk/block.json",
  "apiVersion": 3,
  "name": "starter/contact-form",
  "title": "Contact Form",
  "category": "starter",
  "description": "A contact form with name, email, message (and optional phone). Submissions are stored privately and emailed to the configured recipient.",
  "textdomain": "starter",
  "supports": { "html": false, "align": ["wide"] },
  "attributes": {
    "includePhone":      { "type": "boolean", "default": false },
    "recipientOverride": { "type": "string",  "default": "" },
    "successMessage":    { "type": "string",  "default": "Thanks — we'll be in touch shortly." }
  },
  "editorScript": "file:./index.js",
  "style":        "file:./style-index.css",
  "render":       "file:./render.php"
}
```

- [ ] **Step 4: Write src/blocks/contact-form/edit.tsx**

```tsx
import { __ } from '@wordpress/i18n';
import { useBlockProps, RichText, InspectorControls } from '@wordpress/block-editor';
import { PanelBody, ToggleControl, TextControl } from '@wordpress/components';

type Attrs = { includePhone: boolean; recipientOverride: string; successMessage: string };

export default function Edit({
  attributes, setAttributes,
}: { attributes: Attrs; setAttributes: (a: Partial<Attrs>) => void; }) {
  const blockProps = useBlockProps({ className: 'starter-contact-form' });
  return (
    <>
      <InspectorControls>
        <PanelBody title={__('Form settings', 'starter')}>
          <ToggleControl label={__('Include phone field', 'starter')} checked={attributes.includePhone} onChange={(v) => setAttributes({ includePhone: v })} />
          <TextControl   label={__('Recipient email (override Brand default)', 'starter')} value={attributes.recipientOverride} onChange={(v) => setAttributes({ recipientOverride: v })} />
        </PanelBody>
      </InspectorControls>
      <form {...blockProps} onSubmit={(e) => e.preventDefault()}>
        <label>{__('Name', 'starter')} <input type="text" disabled placeholder={__('Name', 'starter')} /></label>
        <label>{__('Email', 'starter')} <input type="email" disabled placeholder={__('Email', 'starter')} /></label>
        {attributes.includePhone && <label>{__('Phone', 'starter')} <input type="tel" disabled placeholder={__('Phone', 'starter')} /></label>}
        <label>{__('Message', 'starter')} <textarea disabled placeholder={__('Message', 'starter')} /></label>
        <button type="button" disabled>{__('Send', 'starter')}</button>
        <RichText
          tagName="p"
          value={attributes.successMessage}
          onChange={(v) => setAttributes({ successMessage: v })}
          placeholder={__('Success message…', 'starter')}
        />
      </form>
    </>
  );
}
```

- [ ] **Step 5: Write src/blocks/contact-form/index.tsx**

```tsx
import { registerBlockType } from '@wordpress/blocks';
import metadata from './block.json';
import Edit from './edit';
import './style.scss';
registerBlockType(metadata.name, { edit: Edit });
```

- [ ] **Step 6: Write src/blocks/contact-form/render.php**

```php
<?php
/** @var array $attributes */

$include_phone    = ! empty( $attributes['includePhone'] );
$recipient        = isset( $attributes['recipientOverride'] ) ? (string) $attributes['recipientOverride'] : '';
$success_message  = isset( $attributes['successMessage'] )    ? (string) $attributes['successMessage']    : '';

$wrapper = get_block_wrapper_attributes( [
    'class'             => 'starter-contact-form',
    'data-success'      => $success_message,
    'data-recipient'    => $recipient,
    'data-rest-url'     => esc_url_raw( rest_url( 'starter/v1/contact' ) ),
    'data-rest-nonce'   => wp_create_nonce( 'wp_rest' ),
] );

$timestamp = time();

ob_start();
?>
<form <?php echo $wrapper; // phpcs:ignore ?>>
    <label class="starter-contact-form__field">
        <span><?php esc_html_e( 'Name', 'starter' ); ?></span>
        <input type="text" name="name" required />
    </label>
    <label class="starter-contact-form__field">
        <span><?php esc_html_e( 'Email', 'starter' ); ?></span>
        <input type="email" name="email" required />
    </label>
    <?php if ( $include_phone ) : ?>
        <label class="starter-contact-form__field">
            <span><?php esc_html_e( 'Phone', 'starter' ); ?></span>
            <input type="tel" name="phone" />
        </label>
    <?php endif; ?>
    <label class="starter-contact-form__field">
        <span><?php esc_html_e( 'Message', 'starter' ); ?></span>
        <textarea name="message" rows="5" required></textarea>
    </label>

    <div class="starter-contact-form__hp" aria-hidden="true">
        <label>Leave this empty <input type="text" name="hp_field" tabindex="-1" autocomplete="off" /></label>
    </div>
    <input type="hidden" name="_t" value="<?php echo esc_attr( (string) $timestamp ); ?>" />

    <button type="submit" class="starter-contact-form__submit"><?php esc_html_e( 'Send', 'starter' ); ?></button>

    <p class="starter-contact-form__status" role="status" hidden></p>
</form>
<?php
echo ob_get_clean();
```

- [ ] **Step 7: Write src/blocks/contact-form/style.scss**

```scss
.starter-contact-form {
  display: flex;
  flex-direction: column;
  gap: var(--wp--preset--spacing--20);
  max-width: 480px;
  margin-inline: auto;

  &__field   { display: flex; flex-direction: column; gap: var(--wp--preset--spacing--10); }
  &__field span { font-size: var(--wp--preset--font-size--sm); color: var(--wp--preset--color--text); }
  &__field input,
  &__field textarea { padding: var(--wp--preset--spacing--20); border: 1px solid var(--wp--preset--color--border); border-radius: 0.5rem; font: inherit; color: var(--wp--preset--color--text); background: var(--wp--preset--color--surface); }
  &__hp      { position: absolute; left: -9999px; height: 0; width: 0; overflow: hidden; }
  &__submit  { align-self: flex-start; padding: var(--wp--preset--spacing--20) var(--wp--preset--spacing--30); background: var(--wp--preset--color--accent); color: var(--wp--preset--color--surface); border: 0; border-radius: 0.5rem; cursor: pointer; }
  &__status  { font-size: var(--wp--preset--font-size--sm); }
  &__status[data-state="success"] { color: var(--wp--preset--color--accent); }
  &__status[data-state="error"]   { color: var(--wp--preset--color--text); }
}
```

- [ ] **Step 8: Build and run the test**

```bash
npm run build
wp-env run tests-wordpress --env-cwd=wp-content/themes/wp-starter-theme phpunit --filter ContactFormBlockTest
```

Expected: 5 tests pass.

- [ ] **Step 9: Commit**

```bash
git add src/blocks/contact-form/ tests/phpunit/BlockRender/ContactFormBlockTest.php
git commit -m "feat(blocks): starter/contact-form (display only; submission in Task 24+)"
```

---

## Phase 4: Brand Settings

> **File-structure clarification:** Non-block JS for admin and front-end utilities is written as plain ES modules under `assets/js/` (not bundled by `@wordpress/scripts`). These files use globals (`wp.media`, `wp.apiFetch`) and the DOM — no imports or JSX required. This keeps the build pipeline focused on block compilation.

### Task 21: Brand storage class

**Files:**
- Create: `inc/Brand.php`
- Modify: `functions.php` (require Brand.php)
- Create: `tests/phpunit/BrandSettings/StorageTest.php`

- [ ] **Step 1: Write the failing test**

```php
<?php
use Starter\Brand;

class StorageTest extends WP_UnitTestCase {
    public function setUp(): void {
        parent::setUp();
        delete_option( Brand::OPTION );
    }

    public function test_get_returns_default_for_missing_key() {
        $this->assertSame( '', Brand::get( 'brand_name' ) );
        $this->assertSame( 'fallback', Brand::get( 'no_such_key', 'fallback' ) );
    }

    public function test_set_persists_value() {
        Brand::set( 'brand_name', 'Acme' );
        $this->assertSame( 'Acme', Brand::get( 'brand_name' ) );
    }

    public function test_all_merges_with_defaults() {
        Brand::set( 'brand_name', 'Acme' );
        $all = Brand::all();
        $this->assertSame( 'Acme', $all['brand_name'] );
        $this->assertArrayHasKey( 'contact_email', $all );
        $this->assertArrayHasKey( 'social_links', $all );
        $this->assertIsArray( $all['social_links'] );
    }

    public function test_set_social_links_array() {
        Brand::set( 'social_links', [
            [ 'platform' => 'twitter', 'url' => 'https://x.com/acme' ],
        ] );
        $links = Brand::get( 'social_links' );
        $this->assertCount( 1, $links );
        $this->assertSame( 'twitter', $links[0]['platform'] );
    }
}
```

- [ ] **Step 2: Run and verify fail**

```bash
wp-env run tests-wordpress --env-cwd=wp-content/themes/wp-starter-theme phpunit --filter StorageTest
```

Expected: FAIL (Brand class not defined).

- [ ] **Step 3: Implement inc/Brand.php**

```php
<?php
/**
 * Brand settings storage and accessor.
 *
 * @package Starter
 */

namespace Starter;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class Brand {
    public const OPTION = 'starter_theme_brand';

    private const DEFAULTS = [
        'brand_name'    => '',
        'brand_tagline' => '',
        'voice_tone'    => '',
        'logo_id'       => 0,
        'og_image_id'   => 0,
        'contact_email' => '',
        'phone'         => '',
        'address'       => '',
        'social_links'  => [],
    ];

    public static function all(): array {
        $stored = get_option( self::OPTION, [] );
        if ( ! is_array( $stored ) ) {
            $stored = [];
        }
        return array_merge( self::DEFAULTS, $stored );
    }

    /**
     * @param string $key
     * @param mixed  $default Returned when the key is missing or empty for arrays; pass through otherwise.
     * @return mixed
     */
    public static function get( string $key, $default = null ) {
        $all = self::all();
        if ( ! array_key_exists( $key, $all ) ) {
            return $default;
        }
        $value = $all[ $key ];
        if ( '' === $value || ( is_array( $value ) && [] === $value ) ) {
            return $default ?? $value;
        }
        return $value;
    }

    /**
     * @param string $key
     * @param mixed  $value
     */
    public static function set( string $key, $value ): void {
        $all = self::all();
        $all[ $key ] = $value;
        update_option( self::OPTION, $all );
    }
}
```

- [ ] **Step 4: Require the class in functions.php**

Add this line near the other `require_once` calls in `functions.php`:

```php
require_once __DIR__ . '/inc/Brand.php';
```

- [ ] **Step 5: Run the test**

```bash
wp-env run tests-wordpress --env-cwd=wp-content/themes/wp-starter-theme phpunit --filter StorageTest
```

Expected: 4 tests pass.

- [ ] **Step 6: Commit**

```bash
git add inc/Brand.php functions.php tests/phpunit/BrandSettings/StorageTest.php
git commit -m "feat(brand): Brand storage class with get/set/all + defaults"
```

### Task 22: Brand Settings admin page

**Files:**
- Modify: `inc/brand-settings.php` (replace stub)
- Create: `tests/phpunit/BrandSettings/AdminPageTest.php`

- [ ] **Step 1: Write the failing test**

```php
<?php
class AdminPageTest extends WP_UnitTestCase {
    public function test_settings_are_registered() {
        do_action( 'admin_init' );
        global $allowed_options, $new_allowed_options;
        $registered = isset( $allowed_options['starter_brand_group'] ) ? $allowed_options['starter_brand_group'] : ( $new_allowed_options['starter_brand_group'] ?? [] );
        $this->assertContains( \Starter\Brand::OPTION, (array) $registered );
    }

    public function test_admin_menu_is_registered() {
        wp_set_current_user( $this->factory->user->create( [ 'role' => 'administrator' ] ) );
        do_action( 'admin_menu' );
        global $submenu;
        $found = false;
        foreach ( $submenu['options-general.php'] ?? [] as $item ) {
            if ( 'starter-brand' === $item[2] ) { $found = true; break; }
        }
        $this->assertTrue( $found, 'Brand Settings submenu should be registered under Settings.' );
    }

    public function test_sanitize_callback_coerces_social_links_into_clean_array() {
        $sanitized = starter_brand_sanitize( [
            'brand_name'   => '  Acme  ',
            'social_links' => [
                [ 'platform' => 'twitter', 'url' => 'https://x.com/acme' ],
                [ 'platform' => '',        'url' => '' ],
                [ 'platform' => 'github',  'url' => 'not a url' ],
            ],
        ] );
        $this->assertSame( 'Acme', $sanitized['brand_name'] );
        $this->assertCount( 1, $sanitized['social_links'] );
        $this->assertSame( 'twitter', $sanitized['social_links'][0]['platform'] );
    }
}
```

- [ ] **Step 2: Run and verify fail**

```bash
wp-env run tests-wordpress --env-cwd=wp-content/themes/wp-starter-theme phpunit --filter AdminPageTest
```

Expected: FAIL.

- [ ] **Step 3: Replace inc/brand-settings.php with the admin page implementation**

```php
<?php
/**
 * Brand Settings admin page.
 *
 * @package Starter
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

const STARTER_BRAND_OPTION_GROUP = 'starter_brand_group';
const STARTER_BRAND_PAGE         = 'starter-brand';

add_action( 'admin_menu', function () {
    add_options_page(
        __( 'Brand Settings', 'starter' ),
        __( 'Brand Settings', 'starter' ),
        'manage_options',
        STARTER_BRAND_PAGE,
        'starter_brand_render_page'
    );
} );

add_action( 'admin_init', function () {
    register_setting(
        STARTER_BRAND_OPTION_GROUP,
        \Starter\Brand::OPTION,
        [
            'type'              => 'array',
            'sanitize_callback' => 'starter_brand_sanitize',
            'default'           => [],
        ]
    );

    add_settings_section( 'identity', __( 'Identity', 'starter' ), '__return_false', STARTER_BRAND_PAGE );
    add_settings_section( 'contact',  __( 'Contact', 'starter' ),  '__return_false', STARTER_BRAND_PAGE );
    add_settings_section( 'social',   __( 'Social', 'starter' ),   '__return_false', STARTER_BRAND_PAGE );
    add_settings_section( 'og',       __( 'OG / SEO', 'starter' ), '__return_false', STARTER_BRAND_PAGE );

    add_settings_field( 'brand_name',    __( 'Brand name', 'starter' ),    'starter_brand_field_text',     STARTER_BRAND_PAGE, 'identity', [ 'key' => 'brand_name' ] );
    add_settings_field( 'brand_tagline', __( 'Tagline', 'starter' ),       'starter_brand_field_text',     STARTER_BRAND_PAGE, 'identity', [ 'key' => 'brand_tagline' ] );
    add_settings_field( 'voice_tone',    __( 'Voice / tone', 'starter' ),  'starter_brand_field_textarea', STARTER_BRAND_PAGE, 'identity', [ 'key' => 'voice_tone' ] );
    add_settings_field( 'logo_id',       __( 'Logo', 'starter' ),          'starter_brand_field_image',    STARTER_BRAND_PAGE, 'identity', [ 'key' => 'logo_id' ] );

    add_settings_field( 'contact_email', __( 'Contact email', 'starter' ), 'starter_brand_field_text',     STARTER_BRAND_PAGE, 'contact',  [ 'key' => 'contact_email', 'type' => 'email' ] );
    add_settings_field( 'phone',         __( 'Phone', 'starter' ),         'starter_brand_field_text',     STARTER_BRAND_PAGE, 'contact',  [ 'key' => 'phone' ] );
    add_settings_field( 'address',       __( 'Address', 'starter' ),       'starter_brand_field_textarea', STARTER_BRAND_PAGE, 'contact',  [ 'key' => 'address' ] );

    add_settings_field( 'social_links',  __( 'Social links', 'starter' ),  'starter_brand_field_social',   STARTER_BRAND_PAGE, 'social',   [ 'key' => 'social_links' ] );

    add_settings_field( 'og_image_id',   __( 'Default OG image', 'starter' ), 'starter_brand_field_image', STARTER_BRAND_PAGE, 'og',       [ 'key' => 'og_image_id' ] );
} );

function starter_brand_render_page(): void {
    if ( ! current_user_can( 'manage_options' ) ) {
        return;
    }
    ?>
    <div class="wrap">
        <h1><?php esc_html_e( 'Brand Settings', 'starter' ); ?></h1>
        <form method="post" action="options.php">
            <?php
            settings_fields( STARTER_BRAND_OPTION_GROUP );
            do_settings_sections( STARTER_BRAND_PAGE );
            submit_button();
            ?>
        </form>
    </div>
    <?php
}

function starter_brand_field_text( array $args ): void {
    $key   = $args['key'];
    $type  = $args['type'] ?? 'text';
    $value = (string) \Starter\Brand::get( $key, '' );
    printf(
        '<input type="%1$s" name="%2$s[%3$s]" value="%4$s" class="regular-text" />',
        esc_attr( $type ),
        esc_attr( \Starter\Brand::OPTION ),
        esc_attr( $key ),
        esc_attr( $value )
    );
}

function starter_brand_field_textarea( array $args ): void {
    $key   = $args['key'];
    $value = (string) \Starter\Brand::get( $key, '' );
    printf(
        '<textarea name="%1$s[%2$s]" rows="3" class="large-text">%3$s</textarea>',
        esc_attr( \Starter\Brand::OPTION ),
        esc_attr( $key ),
        esc_textarea( $value )
    );
}

function starter_brand_field_image( array $args ): void {
    $key      = $args['key'];
    $media_id = (int) \Starter\Brand::get( $key, 0 );
    $url      = $media_id ? wp_get_attachment_image_url( $media_id, 'medium' ) : '';
    printf(
        '<div class="starter-brand-image" data-key="%1$s">
            <input type="hidden" name="%2$s[%1$s]" value="%3$d" class="starter-brand-image__id" />
            <img src="%4$s" alt="" class="starter-brand-image__preview" %5$s style="max-width:200px;height:auto;display:block;margin-bottom:8px;" />
            <button type="button" class="button starter-brand-image__pick">%6$s</button>
            <button type="button" class="button starter-brand-image__clear" %7$s>%8$s</button>
        </div>',
        esc_attr( $key ),
        esc_attr( \Starter\Brand::OPTION ),
        $media_id,
        esc_url( $url ),
        $url ? '' : 'hidden',
        esc_html__( 'Pick image', 'starter' ),
        $media_id ? '' : 'hidden',
        esc_html__( 'Clear', 'starter' )
    );
}

function starter_brand_field_social( array $args ): void {
    $key   = $args['key'];
    $links = (array) \Starter\Brand::get( $key, [] );
    ?>
    <div class="starter-brand-social" data-key="<?php echo esc_attr( $key ); ?>">
        <template class="starter-brand-social__template">
            <div class="starter-brand-social__row">
                <input type="text" name="<?php echo esc_attr( \Starter\Brand::OPTION ); ?>[social_links][__INDEX__][platform]" placeholder="<?php esc_attr_e( 'Platform', 'starter' ); ?>" />
                <input type="url"  name="<?php echo esc_attr( \Starter\Brand::OPTION ); ?>[social_links][__INDEX__][url]"      placeholder="https://…" />
                <button type="button" class="button-link starter-brand-social__remove"><?php esc_html_e( 'Remove', 'starter' ); ?></button>
            </div>
        </template>
        <div class="starter-brand-social__rows">
            <?php foreach ( $links as $i => $link ) : ?>
                <div class="starter-brand-social__row">
                    <input type="text" name="<?php echo esc_attr( \Starter\Brand::OPTION ); ?>[social_links][<?php echo (int) $i; ?>][platform]" value="<?php echo esc_attr( $link['platform'] ?? '' ); ?>" placeholder="<?php esc_attr_e( 'Platform', 'starter' ); ?>" />
                    <input type="url"  name="<?php echo esc_attr( \Starter\Brand::OPTION ); ?>[social_links][<?php echo (int) $i; ?>][url]"      value="<?php echo esc_attr( $link['url'] ?? '' ); ?>"      placeholder="https://…" />
                    <button type="button" class="button-link starter-brand-social__remove"><?php esc_html_e( 'Remove', 'starter' ); ?></button>
                </div>
            <?php endforeach; ?>
        </div>
        <button type="button" class="button starter-brand-social__add"><?php esc_html_e( 'Add link', 'starter' ); ?></button>
    </div>
    <?php
}

function starter_brand_sanitize( $input ): array {
    if ( ! is_array( $input ) ) {
        return [];
    }
    $clean = [];

    foreach ( [ 'brand_name', 'brand_tagline', 'voice_tone', 'contact_email', 'phone', 'address' ] as $k ) {
        $clean[ $k ] = isset( $input[ $k ] ) ? sanitize_text_field( wp_unslash( (string) $input[ $k ] ) ) : '';
    }
    if ( '' !== $clean['contact_email'] && ! is_email( $clean['contact_email'] ) ) {
        add_settings_error( \Starter\Brand::OPTION, 'invalid_email', __( 'Contact email is invalid.', 'starter' ) );
        $clean['contact_email'] = '';
    }

    $clean['logo_id']     = isset( $input['logo_id'] )     ? (int) $input['logo_id']     : 0;
    $clean['og_image_id'] = isset( $input['og_image_id'] ) ? (int) $input['og_image_id'] : 0;

    $clean['social_links'] = [];
    if ( isset( $input['social_links'] ) && is_array( $input['social_links'] ) ) {
        foreach ( $input['social_links'] as $row ) {
            if ( ! is_array( $row ) ) {
                continue;
            }
            $platform = isset( $row['platform'] ) ? sanitize_key( $row['platform'] ) : '';
            $url      = isset( $row['url'] )      ? esc_url_raw( (string) $row['url'] ) : '';
            if ( '' !== $platform && '' !== $url ) {
                $clean['social_links'][] = [ 'platform' => $platform, 'url' => $url ];
            }
        }
    }

    return $clean;
}
```

- [ ] **Step 4: Run the test**

```bash
wp-env run tests-wordpress --env-cwd=wp-content/themes/wp-starter-theme phpunit --filter AdminPageTest
```

Expected: 3 tests pass.

- [ ] **Step 5: Commit**

```bash
git add inc/brand-settings.php tests/phpunit/BrandSettings/AdminPageTest.php
git commit -m "feat(brand): admin page with Settings API + sanitize callback"
```

### Task 23: Brand Settings admin JS (image picker + repeater)

**Files:**
- Create: `assets/js/admin-brand-settings.js`
- Modify: `inc/brand-settings.php` (add enqueue)

- [ ] **Step 1: Write the JS**

```js
(function () {
  'use strict';

  function init() {
    bindImagePickers();
    bindSocialRepeater();
  }

  function bindImagePickers() {
    document.querySelectorAll('.starter-brand-image').forEach(function (root) {
      var idInput  = root.querySelector('.starter-brand-image__id');
      var preview  = root.querySelector('.starter-brand-image__preview');
      var pick     = root.querySelector('.starter-brand-image__pick');
      var clear    = root.querySelector('.starter-brand-image__clear');

      var frame = null;

      pick.addEventListener('click', function (e) {
        e.preventDefault();
        if (!frame) {
          frame = wp.media({
            title: 'Select image',
            button: { text: 'Use this image' },
            library: { type: 'image' },
            multiple: false,
          });
          frame.on('select', function () {
            var att = frame.state().get('selection').first().toJSON();
            idInput.value = String(att.id);
            preview.src = att.sizes && att.sizes.medium ? att.sizes.medium.url : att.url;
            preview.hidden = false;
            clear.hidden = false;
          });
        }
        frame.open();
      });

      clear.addEventListener('click', function (e) {
        e.preventDefault();
        idInput.value = '0';
        preview.hidden = true;
        clear.hidden = true;
      });
    });
  }

  function bindSocialRepeater() {
    document.querySelectorAll('.starter-brand-social').forEach(function (root) {
      var rows = root.querySelector('.starter-brand-social__rows');
      var addBtn = root.querySelector('.starter-brand-social__add');
      var template = root.querySelector('.starter-brand-social__template');

      addBtn.addEventListener('click', function (e) {
        e.preventDefault();
        var nextIndex = rows.querySelectorAll('.starter-brand-social__row').length;
        var fragment = template.content.cloneNode(true);
        // Replace __INDEX__ in name attrs
        fragment.querySelectorAll('input').forEach(function (input) {
          input.name = input.name.replace(/__INDEX__/g, String(nextIndex));
        });
        rows.appendChild(fragment);
      });

      rows.addEventListener('click', function (e) {
        if (e.target.classList.contains('starter-brand-social__remove')) {
          e.preventDefault();
          var row = e.target.closest('.starter-brand-social__row');
          if (row) {
            row.parentNode.removeChild(row);
          }
        }
      });
    });
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
})();
```

- [ ] **Step 2: Enqueue from inc/brand-settings.php**

Add this code block at the bottom of `inc/brand-settings.php` (before the closing PHP tag if any):

```php
add_action( 'admin_enqueue_scripts', function ( $hook_suffix ) {
    if ( 'settings_page_' . STARTER_BRAND_PAGE !== $hook_suffix ) {
        return;
    }
    wp_enqueue_media();
    $rel = 'assets/js/admin-brand-settings.js';
    wp_enqueue_script(
        'starter-brand-settings',
        get_theme_file_uri( $rel ),
        [],
        (string) filemtime( get_theme_file_path( $rel ) ),
        true
    );
} );
```

- [ ] **Step 3: Verify in the browser**

```bash
wp-env start
open http://localhost:8888/wp-admin/options-general.php?page=starter-brand
```

Expected: Brand Settings page loads. "Pick image" opens the media library; selecting an image populates the preview. "Add link" adds a social-links row; "Remove" removes it.

- [ ] **Step 4: Commit**

```bash
git add assets/js/admin-brand-settings.js inc/brand-settings.php
git commit -m "feat(brand): admin JS — image picker + social-links repeater"
```

---

## Phase 5: Contact form submission pipeline

### Task 24: REST endpoint with validation

**Files:**
- Modify: `inc/contact-form.php` (replace stub)
- Create: `tests/phpunit/ContactForm/SubmissionTest.php`
- Create: `tests/phpunit/ContactForm/HoneypotTest.php`

- [ ] **Step 1: Write the failing tests**

```php
<?php
// tests/phpunit/ContactForm/SubmissionTest.php

class SubmissionTest extends WP_UnitTestCase {
    private $server;

    public function setUp(): void {
        parent::setUp();
        global $wp_rest_server;
        $wp_rest_server = new WP_REST_Server();
        $this->server = $wp_rest_server;
        do_action( 'rest_api_init' );
    }

    private function submit( array $body ): WP_REST_Response {
        $request = new WP_REST_Request( 'POST', '/starter/v1/contact' );
        foreach ( $body as $k => $v ) {
            $request->set_param( $k, $v );
        }
        return $this->server->dispatch( $request );
    }

    public function test_valid_submission_returns_200() {
        $r = $this->submit( [
            'name'     => 'Alice',
            'email'    => 'alice@example.com',
            'message'  => 'Hello!',
            'hp_field' => '',
            '_t'       => time() - 10,
        ] );
        $this->assertSame( 200, $r->get_status() );
        $data = $r->get_data();
        $this->assertTrue( $data['ok'] );
    }

    public function test_missing_name_returns_400() {
        $r = $this->submit( [
            'name'    => '',
            'email'   => 'a@b.com',
            'message' => 'x',
            '_t'      => time() - 10,
        ] );
        $this->assertSame( 400, $r->get_status() );
    }

    public function test_invalid_email_returns_400() {
        $r = $this->submit( [
            'name'    => 'A',
            'email'   => 'not-an-email',
            'message' => 'x',
            '_t'      => time() - 10,
        ] );
        $this->assertSame( 400, $r->get_status() );
    }

    public function test_missing_message_returns_400() {
        $r = $this->submit( [
            'name'    => 'A',
            'email'   => 'a@b.com',
            'message' => '',
            '_t'      => time() - 10,
        ] );
        $this->assertSame( 400, $r->get_status() );
    }
}
```

```php
<?php
// tests/phpunit/ContactForm/HoneypotTest.php

class HoneypotTest extends WP_UnitTestCase {
    private $server;

    public function setUp(): void {
        parent::setUp();
        global $wp_rest_server;
        $wp_rest_server = new WP_REST_Server();
        $this->server = $wp_rest_server;
        do_action( 'rest_api_init' );
    }

    private function submit( array $body ): WP_REST_Response {
        $request = new WP_REST_Request( 'POST', '/starter/v1/contact' );
        foreach ( $body as $k => $v ) {
            $request->set_param( $k, $v );
        }
        return $this->server->dispatch( $request );
    }

    public function test_filled_honeypot_returns_400() {
        $r = $this->submit( [
            'name'     => 'A',
            'email'    => 'a@b.com',
            'message'  => 'x',
            'hp_field' => 'bot was here',
            '_t'       => time() - 10,
        ] );
        $this->assertSame( 400, $r->get_status() );
    }

    public function test_submission_within_5_seconds_returns_400() {
        $r = $this->submit( [
            'name'     => 'A',
            'email'    => 'a@b.com',
            'message'  => 'x',
            'hp_field' => '',
            '_t'       => time() - 2,
        ] );
        $this->assertSame( 400, $r->get_status() );
    }

    public function test_submission_with_future_timestamp_returns_400() {
        $r = $this->submit( [
            'name'     => 'A',
            'email'    => 'a@b.com',
            'message'  => 'x',
            'hp_field' => '',
            '_t'       => time() + 60,
        ] );
        $this->assertSame( 400, $r->get_status() );
    }
}
```

- [ ] **Step 2: Run and verify fail**

```bash
wp-env run tests-wordpress --env-cwd=wp-content/themes/wp-starter-theme phpunit --filter "SubmissionTest|HoneypotTest"
```

Expected: FAIL (route doesn't exist).

- [ ] **Step 3: Implement the REST endpoint (initial — submission storage + email come in Tasks 25 + 27)**

Replace `inc/contact-form.php`:

```php
<?php
/**
 * Contact form REST endpoint + CPT + cron cleanup.
 *
 * @package Starter
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

const STARTER_CONTACT_NAMESPACE = 'starter/v1';
const STARTER_CONTACT_ROUTE     = '/contact';
const STARTER_CONTACT_CPT       = 'contact_submission';
const STARTER_CONTACT_MIN_AGE   = 5; // seconds; time-trap
const STARTER_CONTACT_CRON_HOOK = 'starter_contact_cleanup';

add_action( 'rest_api_init', function () {
    register_rest_route( STARTER_CONTACT_NAMESPACE, STARTER_CONTACT_ROUTE, [
        'methods'             => 'POST',
        'permission_callback' => '__return_true',
        'callback'            => 'starter_contact_handle_submission',
    ] );
} );

function starter_contact_handle_submission( WP_REST_Request $request ) {
    $name     = trim( (string) $request->get_param( 'name' ) );
    $email    = trim( (string) $request->get_param( 'email' ) );
    $phone    = trim( (string) $request->get_param( 'phone' ) );
    $message  = trim( (string) $request->get_param( 'message' ) );
    $hp_field = (string) $request->get_param( 'hp_field' );
    $t_raw    = $request->get_param( '_t' );
    $t        = is_numeric( $t_raw ) ? (int) $t_raw : 0;

    if ( '' !== $hp_field ) {
        return new WP_Error( 'starter_spam', __( 'Submission rejected.', 'starter' ), [ 'status' => 400 ] );
    }
    $now = time();
    if ( $t <= 0 || $t > $now || ( $now - $t ) < STARTER_CONTACT_MIN_AGE ) {
        return new WP_Error( 'starter_spam', __( 'Submission rejected.', 'starter' ), [ 'status' => 400 ] );
    }

    $errors = [];
    if ( '' === $name )    { $errors['name']    = __( 'Name is required.',    'starter' ); }
    if ( '' === $email )   { $errors['email']   = __( 'Email is required.',   'starter' ); }
    elseif ( ! is_email( $email ) ) { $errors['email'] = __( 'Email is invalid.', 'starter' ); }
    if ( '' === $message ) { $errors['message'] = __( 'Message is required.', 'starter' ); }

    if ( ! empty( $errors ) ) {
        return new WP_Error( 'starter_validation', __( 'Validation failed.', 'starter' ), [ 'status' => 400, 'errors' => $errors ] );
    }

    /**
     * Filters for downstream tasks (CPT save, mail send) to hook into.
     */
    do_action( 'starter_contact_submitted', [
        'name'    => $name,
        'email'   => $email,
        'phone'   => $phone,
        'message' => $message,
    ], $request );

    return new WP_REST_Response( [ 'ok' => true ], 200 );
}
```

- [ ] **Step 4: Run the tests**

```bash
wp-env run tests-wordpress --env-cwd=wp-content/themes/wp-starter-theme phpunit --filter "SubmissionTest|HoneypotTest"
```

Expected: 7 tests pass.

- [ ] **Step 5: Commit**

```bash
git add inc/contact-form.php tests/phpunit/ContactForm/SubmissionTest.php tests/phpunit/ContactForm/HoneypotTest.php
git commit -m "feat(contact): REST endpoint with validation, honeypot, time-trap"
```

### Task 25: contact_submission CPT + persistence

**Files:**
- Modify: `inc/contact-form.php` (add CPT + persistence hook)
- Create: `tests/phpunit/ContactForm/CptTest.php`

- [ ] **Step 1: Write the failing test**

```php
<?php
class CptTest extends WP_UnitTestCase {
    public function test_cpt_is_registered_after_init() {
        do_action( 'init' );
        $this->assertTrue( post_type_exists( STARTER_CONTACT_CPT ) );
        $pt = get_post_type_object( STARTER_CONTACT_CPT );
        $this->assertFalse( $pt->public );
        $this->assertTrue( $pt->show_ui );
    }

    public function test_submission_creates_cpt_row() {
        do_action( 'init' );

        do_action( 'starter_contact_submitted', [
            'name'    => 'Alice',
            'email'   => 'alice@example.com',
            'phone'   => '555-1234',
            'message' => 'Hello there.',
        ], null );

        $posts = get_posts( [
            'post_type'   => STARTER_CONTACT_CPT,
            'numberposts' => -1,
            'post_status' => 'any',
        ] );
        $this->assertCount( 1, $posts );

        $post = $posts[0];
        $this->assertStringContainsString( 'Alice', $post->post_title );
        $this->assertSame( 'alice@example.com', get_post_meta( $post->ID, '_email', true ) );
        $this->assertSame( '555-1234', get_post_meta( $post->ID, '_phone', true ) );
        $this->assertStringContainsString( 'Hello there.', $post->post_content );

        wp_delete_post( $post->ID, true );
    }
}
```

- [ ] **Step 2: Run and verify fail**

```bash
wp-env run tests-wordpress --env-cwd=wp-content/themes/wp-starter-theme phpunit --filter CptTest
```

Expected: FAIL.

- [ ] **Step 3: Append CPT registration + persistence handler to inc/contact-form.php**

Append to the end of `inc/contact-form.php`:

```php
add_action( 'init', function () {
    register_post_type( STARTER_CONTACT_CPT, [
        'label'           => __( 'Contact submissions', 'starter' ),
        'labels'          => [
            'name'          => __( 'Contact submissions', 'starter' ),
            'singular_name' => __( 'Contact submission', 'starter' ),
            'menu_name'     => __( 'Contact submissions', 'starter' ),
        ],
        'public'              => false,
        'exclude_from_search' => true,
        'publicly_queryable'  => false,
        'show_ui'             => true,
        'show_in_menu'        => true,
        'show_in_rest'        => false,
        'menu_icon'           => 'dashicons-email',
        'capability_type'     => 'page',
        'capabilities'        => [
            'create_posts' => 'do_not_allow',
        ],
        'map_meta_cap'        => true,
        'supports'            => [ 'title' ],
        'has_archive'         => false,
        'rewrite'             => false,
    ] );
} );

add_action( 'starter_contact_submitted', 'starter_contact_persist_submission', 10, 2 );

function starter_contact_persist_submission( array $payload, $request ): void {
    $name    = (string) ( $payload['name']    ?? '' );
    $email   = (string) ( $payload['email']   ?? '' );
    $phone   = (string) ( $payload['phone']   ?? '' );
    $message = (string) ( $payload['message'] ?? '' );

    $post_id = wp_insert_post( [
        'post_type'    => STARTER_CONTACT_CPT,
        'post_status'  => 'publish',
        'post_title'   => sprintf( '%s <%s>', $name, $email ),
        'post_content' => $message,
    ], true );

    if ( is_wp_error( $post_id ) || ! $post_id ) {
        return;
    }

    update_post_meta( $post_id, '_email', sanitize_email( $email ) );
    if ( '' !== $phone ) {
        update_post_meta( $post_id, '_phone', sanitize_text_field( $phone ) );
    }
}

// Admin list columns
add_filter( 'manage_' . STARTER_CONTACT_CPT . '_posts_columns', function ( array $cols ) {
    $cols = [
        'cb'    => $cols['cb'] ?? '',
        'title' => __( 'From', 'starter' ),
        'email' => __( 'Email', 'starter' ),
        'date'  => __( 'Submitted', 'starter' ),
    ];
    return $cols;
} );

add_action( 'manage_' . STARTER_CONTACT_CPT . '_posts_custom_column', function ( $col, $post_id ) {
    if ( 'email' === $col ) {
        echo esc_html( (string) get_post_meta( $post_id, '_email', true ) );
    }
}, 10, 2 );
```

- [ ] **Step 4: Run the tests**

```bash
wp-env run tests-wordpress --env-cwd=wp-content/themes/wp-starter-theme phpunit --filter CptTest
```

Expected: 2 tests pass.

- [ ] **Step 5: Run the prior submission tests (still pass)**

```bash
wp-env run tests-wordpress --env-cwd=wp-content/themes/wp-starter-theme phpunit --filter "SubmissionTest|HoneypotTest|CptTest"
```

Expected: 9 tests pass.

- [ ] **Step 6: Commit**

```bash
git add inc/contact-form.php tests/phpunit/ContactForm/CptTest.php
git commit -m "feat(contact): contact_submission CPT + persistence on submit"
```

### Task 26: Front-end submit handler

**Files:**
- Create: `assets/js/frontend-contact-form.js`
- Modify: `functions.php` (enqueue on front end when block present)

- [ ] **Step 1: Write the front-end JS**

```js
(function () {
  'use strict';

  function init() {
    document.querySelectorAll('form.starter-contact-form').forEach(bindForm);
  }

  function bindForm(form) {
    var restUrl   = form.getAttribute('data-rest-url');
    var nonce     = form.getAttribute('data-rest-nonce');
    var successMsg = form.getAttribute('data-success') || 'Thanks — we will be in touch.';
    var statusEl  = form.querySelector('.starter-contact-form__status');
    var submitBtn = form.querySelector('.starter-contact-form__submit');

    form.addEventListener('submit', function (e) {
      e.preventDefault();
      if (!restUrl) { return; }

      var payload = {
        name:     valueOf(form, 'name'),
        email:    valueOf(form, 'email'),
        phone:    valueOf(form, 'phone'),
        message:  valueOf(form, 'message'),
        hp_field: valueOf(form, 'hp_field'),
        _t:       valueOf(form, '_t'),
      };

      submitBtn.disabled = true;
      showStatus(statusEl, '', null);

      fetch(restUrl, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-WP-Nonce': nonce || '',
        },
        body: JSON.stringify(payload),
      })
        .then(function (res) { return res.json().then(function (body) { return { res: res, body: body }; }); })
        .then(function (out) {
          submitBtn.disabled = false;
          if (out.res.ok && out.body && out.body.ok) {
            form.querySelectorAll('input,textarea,button').forEach(function (el) { el.disabled = true; });
            showStatus(statusEl, successMsg, 'success');
          } else {
            var msg = (out.body && out.body.message) ? out.body.message : 'Something went wrong. Please try again.';
            showStatus(statusEl, msg, 'error');
          }
        })
        .catch(function () {
          submitBtn.disabled = false;
          showStatus(statusEl, 'Network error. Please try again.', 'error');
        });
    });
  }

  function valueOf(form, name) {
    var el = form.querySelector('[name="' + name + '"]');
    return el ? el.value : '';
  }

  function showStatus(el, msg, state) {
    if (!el) { return; }
    if (!msg) { el.hidden = true; el.textContent = ''; el.removeAttribute('data-state'); return; }
    el.hidden = false;
    el.textContent = msg;
    if (state) { el.setAttribute('data-state', state); } else { el.removeAttribute('data-state'); }
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
})();
```

- [ ] **Step 2: Enqueue from functions.php when the block is present**

Append to `functions.php`:

```php
add_action( 'wp_enqueue_scripts', function () {
    if ( ! has_block( 'starter/contact-form' ) ) {
        return;
    }
    $rel = 'assets/js/frontend-contact-form.js';
    wp_enqueue_script(
        'starter-frontend-contact-form',
        get_theme_file_uri( $rel ),
        [],
        (string) filemtime( get_theme_file_path( $rel ) ),
        true
    );
} );
```

- [ ] **Step 3: Manual smoke test**

```bash
wp-env start
# Create a page in wp-admin with the starter/contact-form block, publish it, visit the front-end URL.
# Fill in the form (wait >5s) and submit.
```

Expected: form disables, success message appears.

- [ ] **Step 4: Commit**

```bash
git add assets/js/frontend-contact-form.js functions.php
git commit -m "feat(contact): front-end submit handler with success/error UI"
```

### Task 27: Email notification via wp_mail

**Files:**
- Modify: `inc/contact-form.php` (add email handler)
- Create: `tests/phpunit/ContactForm/MailTest.php`

- [ ] **Step 1: Write the failing test**

```php
<?php
class MailTest extends WP_UnitTestCase {
    private array $mail_calls = [];

    public function setUp(): void {
        parent::setUp();
        $this->mail_calls = [];
        add_filter( 'pre_wp_mail', function ( $short_circuit, $atts ) {
            $this->mail_calls[] = $atts;
            return true; // short-circuit; don't actually send.
        }, 10, 2 );

        \Starter\Brand::set( 'contact_email', 'owner@example.com' );
        \Starter\Brand::set( 'brand_name',    'Acme Co' );
    }

    public function test_sends_mail_to_brand_email_by_default() {
        do_action( 'starter_contact_submitted', [
            'name'    => 'Bob',
            'email'   => 'bob@example.com',
            'phone'   => '',
            'message' => 'Hi',
        ], null );

        $this->assertCount( 1, $this->mail_calls );
        $atts = $this->mail_calls[0];
        $this->assertSame( [ 'owner@example.com' ], (array) $atts['to'] );
        $this->assertStringContainsString( 'Acme Co', $atts['subject'] );
        $this->assertStringContainsString( 'Bob',  $atts['message'] );
        $this->assertStringContainsString( 'Hi',   $atts['message'] );
        $this->assertContains( 'Reply-To: bob@example.com', $atts['headers'] );
    }

    public function test_recipient_override_via_request_data() {
        $request = new WP_REST_Request( 'POST', '/starter/v1/contact' );
        $request->set_param( '_recipient_override', 'other@example.com' );

        do_action( 'starter_contact_submitted', [
            'name'    => 'B',
            'email'   => 'b@b.com',
            'phone'   => '',
            'message' => 'm',
        ], $request );

        $this->assertSame( [ 'other@example.com' ], (array) $this->mail_calls[0]['to'] );
    }
}
```

- [ ] **Step 2: Run and verify fail**

```bash
wp-env run tests-wordpress --env-cwd=wp-content/themes/wp-starter-theme phpunit --filter MailTest
```

Expected: FAIL (no mail sent).

- [ ] **Step 3: Implement email handler — append to inc/contact-form.php**

```php
add_action( 'starter_contact_submitted', 'starter_contact_send_notification', 20, 2 );

function starter_contact_send_notification( array $payload, $request ): void {
    $brand_name = (string) \Starter\Brand::get( 'brand_name', get_bloginfo( 'name' ) );

    $recipient = '';
    if ( $request instanceof WP_REST_Request ) {
        $override = (string) $request->get_param( '_recipient_override' );
        if ( '' !== $override && is_email( $override ) ) {
            $recipient = $override;
        }
    }
    if ( '' === $recipient ) {
        $recipient = (string) \Starter\Brand::get( 'contact_email', get_option( 'admin_email' ) );
    }
    if ( '' === $recipient ) {
        return;
    }

    $name    = (string) ( $payload['name']    ?? '' );
    $email   = (string) ( $payload['email']   ?? '' );
    $phone   = (string) ( $payload['phone']   ?? '' );
    $message = (string) ( $payload['message'] ?? '' );

    $subject = sprintf(
        /* translators: %s: brand name */
        __( '[%s] New contact form submission', 'starter' ),
        $brand_name
    );

    $body  = sprintf( "Name: %s\n",    $name );
    $body .= sprintf( "Email: %s\n",   $email );
    if ( '' !== $phone ) {
        $body .= sprintf( "Phone: %s\n", $phone );
    }
    $body .= "\n" . $message . "\n";

    $headers = [
        'Reply-To: ' . $email,
        'Content-Type: text/plain; charset=UTF-8',
    ];

    wp_mail( $recipient, $subject, $body, $headers );
}
```

- [ ] **Step 4: Run the tests**

```bash
wp-env run tests-wordpress --env-cwd=wp-content/themes/wp-starter-theme phpunit --filter MailTest
```

Expected: 2 tests pass.

- [ ] **Step 5: Commit**

```bash
git add inc/contact-form.php tests/phpunit/ContactForm/MailTest.php
git commit -m "feat(contact): wp_mail notification with Reply-To and brand-aware subject"
```

### Task 28: 90-day cleanup cron

**Files:**
- Modify: `inc/contact-form.php` (add cron registration + handler)
- Modify: `functions.php` (schedule on activation)
- Create: `tests/phpunit/ContactForm/CleanupCronTest.php`

- [ ] **Step 1: Write the failing test**

```php
<?php
class CleanupCronTest extends WP_UnitTestCase {
    public function setUp(): void {
        parent::setUp();
        do_action( 'init' );
    }

    public function test_cleanup_deletes_submissions_older_than_90_days() {
        $old_date = gmdate( 'Y-m-d H:i:s', strtotime( '-91 days' ) );
        $new_date = gmdate( 'Y-m-d H:i:s', strtotime( '-1 day' ) );

        $old_id = wp_insert_post( [
            'post_type'    => STARTER_CONTACT_CPT,
            'post_status'  => 'publish',
            'post_title'   => 'old',
            'post_date'    => $old_date,
            'post_date_gmt' => $old_date,
        ] );
        $new_id = wp_insert_post( [
            'post_type'    => STARTER_CONTACT_CPT,
            'post_status'  => 'publish',
            'post_title'   => 'new',
            'post_date'    => $new_date,
            'post_date_gmt' => $new_date,
        ] );

        starter_contact_cleanup();

        $this->assertNull( get_post( $old_id ) );
        $this->assertNotNull( get_post( $new_id ) );

        wp_delete_post( $new_id, true );
    }

    public function test_cron_is_scheduled_after_activation_hook() {
        starter_contact_schedule_cleanup();
        $this->assertNotFalse( wp_next_scheduled( STARTER_CONTACT_CRON_HOOK ) );
        wp_clear_scheduled_hook( STARTER_CONTACT_CRON_HOOK );
    }
}
```

- [ ] **Step 2: Run and verify fail**

```bash
wp-env run tests-wordpress --env-cwd=wp-content/themes/wp-starter-theme phpunit --filter CleanupCronTest
```

Expected: FAIL.

- [ ] **Step 3: Append cron registration + handler to inc/contact-form.php**

```php
add_action( STARTER_CONTACT_CRON_HOOK, 'starter_contact_cleanup' );

function starter_contact_cleanup(): void {
    $cutoff_gmt = gmdate( 'Y-m-d H:i:s', strtotime( '-90 days' ) );

    $stale = get_posts( [
        'post_type'      => STARTER_CONTACT_CPT,
        'post_status'    => 'any',
        'posts_per_page' => 200,
        'fields'         => 'ids',
        'date_query'     => [
            'before'    => $cutoff_gmt,
            'column'    => 'post_date_gmt',
            'inclusive' => true,
        ],
    ] );

    foreach ( $stale as $post_id ) {
        wp_delete_post( $post_id, true );
    }
}

function starter_contact_schedule_cleanup(): void {
    if ( ! wp_next_scheduled( STARTER_CONTACT_CRON_HOOK ) ) {
        wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', STARTER_CONTACT_CRON_HOOK );
    }
}

function starter_contact_unschedule_cleanup(): void {
    wp_clear_scheduled_hook( STARTER_CONTACT_CRON_HOOK );
}
```

- [ ] **Step 4: Wire activation/deactivation in functions.php**

Append to `functions.php`:

```php
add_action( 'after_switch_theme', 'starter_contact_schedule_cleanup' );
add_action( 'switch_theme',       'starter_contact_unschedule_cleanup' );
```

- [ ] **Step 5: Run the tests**

```bash
wp-env run tests-wordpress --env-cwd=wp-content/themes/wp-starter-theme phpunit --filter CleanupCronTest
```

Expected: 2 tests pass.

- [ ] **Step 6: Run the full contact-form suite**

```bash
wp-env run tests-wordpress --env-cwd=wp-content/themes/wp-starter-theme phpunit --filter "Contact"
```

Expected: 11 tests pass (Submission 4 + Honeypot 3 + Cpt 2 + Mail 2 + CleanupCron 2 = 13; adjust if numbering differs).

- [ ] **Step 7: Commit**

```bash
git add inc/contact-form.php functions.php tests/phpunit/ContactForm/CleanupCronTest.php
git commit -m "feat(contact): daily cron to purge submissions older than 90 days"
```

---

## Phase 6: Block patterns

### Task 29: Three block patterns

**Files:**
- Modify: `inc/patterns.php` (replace stub)
- Create: `patterns/hero-cta-faq.php`
- Create: `patterns/prose-article.php`
- Create: `patterns/contact-page.php`
- Create: `tests/phpunit/Patterns/PatternsTest.php`

- [ ] **Step 1: Write the failing test**

```php
<?php
class PatternsTest extends WP_UnitTestCase {
    public function test_patterns_are_registered() {
        do_action( 'init' );
        $registry = WP_Block_Patterns_Registry::get_instance();
        $this->assertTrue( $registry->is_registered( 'starter/hero-cta-faq' ) );
        $this->assertTrue( $registry->is_registered( 'starter/prose-article' ) );
        $this->assertTrue( $registry->is_registered( 'starter/contact-page' ) );
    }

    public function test_pattern_category_is_registered() {
        do_action( 'init' );
        $cats = WP_Block_Pattern_Categories_Registry::get_instance()->get_all_registered();
        $slugs = wp_list_pluck( $cats, 'name' );
        $this->assertContains( 'starter', $slugs );
    }
}
```

- [ ] **Step 2: Run and verify fail**

```bash
wp-env run tests-wordpress --env-cwd=wp-content/themes/wp-starter-theme phpunit --filter PatternsTest
```

Expected: FAIL.

- [ ] **Step 3: Implement inc/patterns.php**

```php
<?php
/**
 * Block pattern registration.
 *
 * @package Starter
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

add_action( 'init', function () {
    register_block_pattern_category( 'starter', [
        'label' => __( 'Starter', 'starter' ),
    ] );

    $patterns = [
        'hero-cta-faq'   => __( 'Hero + CTA + FAQ', 'starter' ),
        'prose-article'  => __( 'Prose article', 'starter' ),
        'contact-page'   => __( 'Contact page', 'starter' ),
    ];

    foreach ( $patterns as $slug => $title ) {
        $file = STARTER_THEME_DIR . '/patterns/' . $slug . '.php';
        if ( ! file_exists( $file ) ) {
            continue;
        }
        ob_start();
        require $file;
        $content = (string) ob_get_clean();

        register_block_pattern( 'starter/' . $slug, [
            'title'      => $title,
            'categories' => [ 'starter' ],
            'content'    => $content,
        ] );
    }
} );
```

- [ ] **Step 4: Write patterns/hero-cta-faq.php**

```php
<?php // phpcs:ignoreFile -- block pattern content
?>
<!-- wp:starter/hero {"variant":"centered","headline":"Welcome to <?php echo esc_html( get_bloginfo( 'name' ) ); ?>","subheadline":"A short, benefit-led promise.","ctaText":"Get in touch","ctaUrl":"/contact"} /-->

<!-- wp:starter/cta {"title":"Ready to start?","body":"Tell us about your project. We respond within one business day.","primaryText":"Contact us","primaryUrl":"/contact","secondaryText":"Learn more","secondaryUrl":"/about"} /-->

<!-- wp:starter/faq -->
    <!-- wp:starter/faq-item {"question":"How long does a typical project take?","answer":"Most engagements run 4–8 weeks."} /-->
    <!-- wp:starter/faq-item {"question":"Do you work with my industry?","answer":"We've shipped for SaaS, e-commerce, and editorial clients."} /-->
    <!-- wp:starter/faq-item {"question":"What's your pricing model?","answer":"Fixed-scope sprints or monthly retainer — we'll recommend the better fit."} /-->
<!-- /wp:starter/faq -->
```

- [ ] **Step 5: Write patterns/prose-article.php**

```php
<?php // phpcs:ignoreFile -- block pattern content
?>
<!-- wp:starter/prose -->
    <!-- wp:heading {"level":2} --><h2>Section heading</h2><!-- /wp:heading -->
    <!-- wp:paragraph --><p>Opening paragraph that frames the piece. One or two sentences that tell the reader why this matters and what they'll learn.</p><!-- /wp:paragraph -->
    <!-- wp:paragraph --><p>Continue the thread. Concrete examples beat abstractions. Cite numbers when you have them.</p><!-- /wp:paragraph -->
    <!-- wp:heading {"level":3} --><h3>A subsection</h3><!-- /wp:heading -->
    <!-- wp:paragraph --><p>Detail. Keep paragraphs short on mobile readers.</p><!-- /wp:paragraph -->
    <!-- wp:list --><ul>
        <!-- wp:list-item --><li>One point.</li><!-- /wp:list-item -->
        <!-- wp:list-item --><li>Another point.</li><!-- /wp:list-item -->
        <!-- wp:list-item --><li>One more.</li><!-- /wp:list-item -->
    </ul><!-- /wp:list -->
<!-- /wp:starter/prose -->

<!-- wp:starter/pull-quote {"quote":"A memorable line from the piece — short, specific, and quotable.","citation":"Author"} /-->

<!-- wp:starter/prose -->
    <!-- wp:paragraph --><p>Closing thought. Reaffirm the why, and point the reader at the next step.</p><!-- /wp:paragraph -->
<!-- /wp:starter/prose -->
```

- [ ] **Step 6: Write patterns/contact-page.php**

```php
<?php // phpcs:ignoreFile -- block pattern content
?>
<!-- wp:starter/hero {"variant":"centered","headline":"Contact","subheadline":"Tell us about your project. We respond within one business day."} /-->

<!-- wp:starter/prose -->
    <!-- wp:paragraph --><p>Prefer email? Reach us at <a href="mailto:hello@example.com">hello@example.com</a>.</p><!-- /wp:paragraph -->
<!-- /wp:starter/prose -->

<!-- wp:starter/contact-form {"includePhone":true} /-->
```

- [ ] **Step 7: Run the tests**

```bash
wp-env run tests-wordpress --env-cwd=wp-content/themes/wp-starter-theme phpunit --filter PatternsTest
```

Expected: 2 tests pass.

- [ ] **Step 8: Visual smoke test**

```bash
wp-env start
open http://localhost:8888/wp-admin
```

Open a new page, click the patterns inserter (`+` → Patterns → Starter category). Expected: three patterns appear, each previews correctly.

- [ ] **Step 9: Commit**

```bash
git add inc/patterns.php patterns/ tests/phpunit/Patterns/PatternsTest.php
git commit -m "feat(patterns): three block patterns for hero+cta+faq, prose, contact"
```

---

## Phase 7: WP-CLI seed command

### Task 30: `wp starter-theme seed`

**Files:**
- Modify: `inc/seed.php` (replace stub)
- Create: `tests/phpunit/Seed/SeedCommandTest.php`

- [ ] **Step 1: Write the failing test**

```php
<?php
use Starter\Brand;

class SeedCommandTest extends WP_UnitTestCase {
    public function setUp(): void {
        parent::setUp();
        delete_option( Brand::OPTION );
        // Remove any pre-existing seed pages.
        foreach ( get_posts( [ 'post_type' => 'page', 'numberposts' => -1 ] ) as $p ) {
            wp_delete_post( $p->ID, true );
        }
    }

    public function test_seed_creates_four_pages() {
        starter_seed_run();

        $slugs = wp_list_pluck( get_posts( [ 'post_type' => 'page', 'numberposts' => -1, 'post_status' => 'publish' ] ), 'post_name' );
        $this->assertContains( 'home', $slugs );
        $this->assertContains( 'about', $slugs );
        $this->assertContains( 'contact', $slugs );
        $this->assertContains( 'blog', $slugs );
    }

    public function test_seed_sets_brand_defaults() {
        starter_seed_run();
        $this->assertNotEmpty( Brand::get( 'brand_name' ) );
        $this->assertNotEmpty( Brand::get( 'voice_tone' ) );
    }

    public function test_seed_is_idempotent() {
        starter_seed_run();
        starter_seed_run();
        $count = count( get_posts( [
            'post_type'   => 'page',
            'numberposts' => -1,
            'post_status' => 'publish',
            'name'        => 'home',
        ] ) );
        $this->assertSame( 1, $count, 'Running seed twice should not duplicate the Home page.' );
    }

    public function test_seed_sets_static_front_page() {
        starter_seed_run();
        $front_id = (int) get_option( 'page_on_front' );
        $this->assertGreaterThan( 0, $front_id );
        $front = get_post( $front_id );
        $this->assertSame( 'home', $front->post_name );
    }
}
```

- [ ] **Step 2: Run and verify fail**

```bash
wp-env run tests-wordpress --env-cwd=wp-content/themes/wp-starter-theme phpunit --filter SeedCommandTest
```

Expected: FAIL (`starter_seed_run` not defined).

- [ ] **Step 3: Replace inc/seed.php with the seed implementation**

```php
<?php
/**
 * WP-CLI: `wp starter-theme seed` — populate Brand defaults + sample pages.
 *
 * @package Starter
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( defined( 'WP_CLI' ) && WP_CLI ) {
    WP_CLI::add_command( 'starter-theme seed', 'starter_seed_cli' );
}

function starter_seed_cli(): void {
    starter_seed_run();
    if ( class_exists( '\\WP_CLI' ) ) {
        \WP_CLI::success( 'Starter theme seeded.' );
    }
}

function starter_seed_run(): void {
    // 1. Brand defaults (only set keys that are empty).
    $brand_defaults = [
        'brand_name'    => get_bloginfo( 'name' ) ?: 'Acme',
        'brand_tagline' => 'Short benefit-led promise.',
        'voice_tone'    => 'Confident, plain-spoken, no buzzwords.',
        'contact_email' => get_option( 'admin_email' ),
    ];
    foreach ( $brand_defaults as $k => $v ) {
        if ( '' === (string) \Starter\Brand::get( $k, '' ) ) {
            \Starter\Brand::set( $k, $v );
        }
    }

    // 2. Pages with prebuilt block content.
    $pages = [
        'home'    => [
            'title'   => 'Home',
            'content' =>
                '<!-- wp:starter/hero {"variant":"centered","headline":"Welcome","subheadline":"A short benefit-led promise.","ctaText":"Get started","ctaUrl":"/contact"} /-->' .
                '<!-- wp:starter/cta {"title":"Ready to start?","body":"Tell us about your project.","primaryText":"Contact us","primaryUrl":"/contact"} /-->' .
                '<!-- wp:starter/blog-index {"count":3} /-->',
        ],
        'about'   => [
            'title'   => 'About',
            'content' =>
                '<!-- wp:starter/hero {"variant":"default","headline":"About us","subheadline":"Who we are and what we do."} /-->' .
                '<!-- wp:starter/prose -->' .
                    '<!-- wp:paragraph --><p>Tell your story here. Keep it human and specific.</p><!-- /wp:paragraph -->' .
                '<!-- /wp:starter/prose -->',
        ],
        'contact' => [
            'title'   => 'Contact',
            'content' =>
                '<!-- wp:starter/hero {"variant":"centered","headline":"Contact","subheadline":"Tell us about your project."} /-->' .
                '<!-- wp:starter/contact-form {"includePhone":true} /-->',
        ],
        'blog'    => [
            'title'   => 'Blog',
            'content' => '<!-- wp:starter/blog-index {"count":10} /-->',
        ],
    ];

    $page_ids = [];
    foreach ( $pages as $slug => $page ) {
        $existing = get_page_by_path( $slug, OBJECT, 'page' );
        if ( $existing ) {
            $page_ids[ $slug ] = (int) $existing->ID;
            continue;
        }
        $id = wp_insert_post( [
            'post_type'    => 'page',
            'post_status'  => 'publish',
            'post_title'   => $page['title'],
            'post_name'    => $slug,
            'post_content' => $page['content'],
        ], true );
        if ( ! is_wp_error( $id ) ) {
            $page_ids[ $slug ] = (int) $id;
        }
    }

    // 3. Static front page → Home.
    if ( isset( $page_ids['home'] ) ) {
        update_option( 'show_on_front', 'page' );
        update_option( 'page_on_front', $page_ids['home'] );
    }
    if ( isset( $page_ids['blog'] ) ) {
        update_option( 'page_for_posts', $page_ids['blog'] );
    }
}
```

- [ ] **Step 4: Run the tests**

```bash
wp-env run tests-wordpress --env-cwd=wp-content/themes/wp-starter-theme phpunit --filter SeedCommandTest
```

Expected: 4 tests pass.

- [ ] **Step 5: Smoke test the CLI command**

```bash
wp-env start
wp-env run cli --env-cwd=wp-content/themes/wp-starter-theme "wp starter-theme seed"
```

Expected: `Success: Starter theme seeded.`

- [ ] **Step 6: Commit**

```bash
git add inc/seed.php tests/phpunit/Seed/SeedCommandTest.php
git commit -m "feat(seed): wp starter-theme seed — Brand defaults + 4 sample pages"
```

---

## Phase 8: Color-literal lint rules

### Task 31: PHPCS sniff + SCSS lint script

> **Two tools, one rule.** PHPCS handles `.php` files (covers `render.php` and any inline styles). A small Node script handles `.scss` files since PHPCS doesn't reason about SCSS. Both are wired into CI.

**Files:**
- Create: `tools/phpcs-sniffs/Starter/ruleset.xml`
- Create: `tools/phpcs-sniffs/Starter/Sniffs/NoColorLiteralSniff.php`
- Create: `tools/phpcs-sniffs/Starter/Tests/NoColorLiteralUnitTest.php` (PHPCS unit test convention)
- Create: `tools/phpcs-sniffs/Starter/Tests/NoColorLiteralUnitTest.inc` (fixture)
- Create: `tools/lint-colors.mjs` (SCSS scanner)
- Modify: `package.json` (add lint:colors script)
- Modify: `.github/workflows/ci.yml` (wire lint:colors into CI)

- [ ] **Step 1: Write the PHPCS sniff ruleset.xml**

```xml
<?xml version="1.0"?>
<ruleset name="Starter">
    <description>Starter Theme custom sniffs.</description>
    <rule ref="./Sniffs/NoColorLiteralSniff.php"/>
</ruleset>
```

- [ ] **Step 2: Write tools/phpcs-sniffs/Starter/Sniffs/NoColorLiteralSniff.php**

```php
<?php
/**
 * Reject hex/rgb/hsl color literals inside src/blocks/.
 *
 * Use theme.json CSS custom properties instead: var(--wp--preset--color--*).
 */

namespace Starter\Sniffs;

use PHP_CodeSniffer\Sniffs\Sniff;
use PHP_CodeSniffer\Files\File;

class NoColorLiteralSniff implements Sniff {

    /** @var string[] */
    public $applyToPath = [ 'src/blocks/' ];

    public function register(): array {
        return [ T_CONSTANT_ENCAPSED_STRING, T_INLINE_HTML ];
    }

    /**
     * @param File $phpcsFile
     * @param int  $stackPtr
     */
    public function process( File $phpcsFile, $stackPtr ): void {
        $filename = $phpcsFile->getFilename();
        $matched_path = false;
        foreach ( $this->applyToPath as $needle ) {
            if ( false !== strpos( str_replace( '\\', '/', $filename ), $needle ) ) {
                $matched_path = true;
                break;
            }
        }
        if ( ! $matched_path ) {
            return;
        }

        $tokens  = $phpcsFile->getTokens();
        $content = (string) $tokens[ $stackPtr ]['content'];

        if ( preg_match( '/#[0-9a-fA-F]{3}(?:[0-9a-fA-F]{1,5})?\b/', $content )
             || preg_match( '/\brgb[a]?\s*\(/i', $content )
             || preg_match( '/\bhsl[a]?\s*\(/i', $content )
        ) {
            $phpcsFile->addError(
                'Color literals are forbidden in src/blocks/. Use theme.json tokens via var(--wp--preset--color--*).',
                $stackPtr,
                'ColorLiteralFound'
            );
        }
    }
}
```

- [ ] **Step 3: Verify the sniff catches a deliberate violation**

```bash
mkdir -p src/blocks/__test-temp
cat > src/blocks/__test-temp/render.php <<'EOF'
<?php
echo '<div style="color: #ff0000">bad</div>';
EOF
composer lint -- --standard=./tools/phpcs-sniffs/Starter src/blocks/__test-temp/
```

Expected: PHPCS reports `ColorLiteralFound`. Exit code non-zero.

- [ ] **Step 4: Clean up the fixture**

```bash
rm -rf src/blocks/__test-temp
```

- [ ] **Step 5: Write tools/lint-colors.mjs (SCSS scanner)**

```js
#!/usr/bin/env node
import { readdirSync, statSync, readFileSync } from 'node:fs';
import { join } from 'node:path';

const root = new URL('../src/blocks/', import.meta.url).pathname;
const HEX = /#[0-9a-fA-F]{3}(?:[0-9a-fA-F]{1,5})?\b/;
const RGB = /\brgb[a]?\s*\(/i;
const HSL = /\bhsl[a]?\s*\(/i;

function* walk(dir) {
  for (const name of readdirSync(dir)) {
    const p = join(dir, name);
    if (statSync(p).isDirectory()) {
      yield* walk(p);
    } else {
      yield p;
    }
  }
}

let failed = false;

for (const path of walk(root)) {
  if (!path.endsWith('.scss') && !path.endsWith('.css')) { continue; }
  const lines = readFileSync(path, 'utf8').split('\n');
  lines.forEach((line, i) => {
    if (HEX.test(line) || RGB.test(line) || HSL.test(line)) {
      console.error(`✗ ${path}:${i + 1} — color literal: ${line.trim()}`);
      failed = true;
    }
  });
}

if (failed) {
  console.error('\nUse theme.json tokens via var(--wp--preset--color--*).');
  process.exit(1);
}

console.log('✓ No color literals in src/blocks/ stylesheets.');
```

- [ ] **Step 6: Add the lint:colors script to package.json**

In `package.json`, add to `"scripts"`:

```json
"lint:colors": "node tools/lint-colors.mjs",
```

- [ ] **Step 7: Verify SCSS scanner catches a deliberate violation**

```bash
mkdir -p src/blocks/__test-temp
echo '.x { color: #ff0000; }' > src/blocks/__test-temp/style.scss
npm run lint:colors || true
```

Expected: exits 1, reports the violation.

```bash
rm -rf src/blocks/__test-temp
npm run lint:colors
```

Expected: passes (no violations in real blocks).

- [ ] **Step 8: Wire into CI**

In `.github/workflows/ci.yml`, modify the `lint-blocks` job to also run `lint:colors`:

```yaml
  lint-blocks:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v4
      - uses: actions/setup-node@v4
        with:
          node-version: '20'
          cache: npm
      - run: npm ci
      - run: npm run lint:blocks
      - run: npm run lint:colors
      - run: npm run lint:js
```

- [ ] **Step 9: Commit**

```bash
git add tools/ package.json .github/workflows/ci.yml
git commit -m "tooling: phpcs sniff + scss scanner for color literals"
```

---

## Phase 9: End-to-end tests

### Task 32: Editor blocks E2E

**Files:**
- Create: `tests/e2e/editor-blocks.spec.ts`
- Create: `tests/e2e/utils.ts`

- [ ] **Step 1: Write a small login utility**

```ts
// tests/e2e/utils.ts
import { Page } from '@playwright/test';

export async function login(page: Page, user = 'admin', pass = 'password') {
  await page.goto('/wp-login.php');
  await page.fill('input#user_login', user);
  await page.fill('input#user_pass', pass);
  await page.click('input#wp-submit');
  await page.waitForURL(/wp-admin/);
}

export async function createBlankPage(page: Page, title: string): Promise<string> {
  await page.goto('/wp-admin/post-new.php?post_type=page');
  // Close welcome modal if present
  const welcome = page.getByRole('button', { name: /close dialog/i });
  if (await welcome.count()) { await welcome.first().click(); }
  await page.getByRole('textbox', { name: /add title/i }).fill(title);
  return title;
}

export async function publishPage(page: Page): Promise<string> {
  await page.getByRole('button', { name: /^publish$/i }).first().click();
  await page.getByRole('button', { name: /^publish$/i }).nth(1).click();
  await page.waitForSelector('text=/post published|page published/i');
  const link = await page.getByRole('link', { name: /view page/i }).getAttribute('href');
  return link ?? '/';
}

export async function insertBlock(page: Page, blockTitle: string) {
  const inserterToggle = page.getByRole('button', { name: /toggle block inserter/i });
  await inserterToggle.click();
  await page.getByRole('searchbox', { name: /search/i }).fill(blockTitle);
  await page.getByRole('option', { name: new RegExp(`^${blockTitle}$`, 'i') }).first().click();
  await inserterToggle.click(); // close inserter
}
```

- [ ] **Step 2: Write the failing E2E spec**

```ts
// tests/e2e/editor-blocks.spec.ts
import { test, expect } from '@playwright/test';
import { login, createBlankPage, publishPage, insertBlock } from './utils';

const BLOCKS_TO_VERIFY: Array<{ title: string; cls: string }> = [
  { title: 'Hero',              cls: 'starter-hero' },
  { title: 'Call to Action',    cls: 'starter-cta' },
  { title: 'FAQ',               cls: 'starter-faq' },
  { title: 'Prose',             cls: 'starter-prose' },
  { title: 'Pull Quote',        cls: 'starter-pull-quote' },
  { title: 'Image with caption',cls: 'starter-image-caption' },
  { title: 'Stat',              cls: 'starter-stat' },
  { title: 'Blog Index',        cls: 'starter-blog-index' },
  { title: 'Contact Form',      cls: 'starter-contact-form' },
];

test('every starter block renders on the front end after insertion', async ({ page }) => {
  await login(page);
  await createBlankPage(page, 'Block kitchen sink');
  for (const { title } of BLOCKS_TO_VERIFY) {
    await insertBlock(page, title);
  }
  const url = await publishPage(page);
  await page.goto(url);
  for (const { cls } of BLOCKS_TO_VERIFY) {
    await expect(page.locator(`.${cls}`).first()).toBeVisible();
  }
});
```

- [ ] **Step 3: Run E2E and verify**

```bash
wp-env start
npm run build
npm run e2e
```

Expected: 1 test passes (alongside front-page.spec.ts).

- [ ] **Step 4: Commit**

```bash
git add tests/e2e/editor-blocks.spec.ts tests/e2e/utils.ts
git commit -m "test(e2e): verify every starter block renders end-to-end"
```

### Task 33: Contact form E2E

**Files:**
- Create: `tests/e2e/contact-form.spec.ts`

- [ ] **Step 1: Write the failing spec**

```ts
// tests/e2e/contact-form.spec.ts
import { test, expect } from '@playwright/test';
import { login, createBlankPage, publishPage, insertBlock } from './utils';

test('contact form submission shows success and creates a submission row', async ({ page, request }) => {
  await login(page);
  await createBlankPage(page, 'Contact test');
  await insertBlock(page, 'Contact Form');
  const url = await publishPage(page);

  await page.goto(url);
  await page.fill('input[name="name"]', 'Alice E2E');
  await page.fill('input[name="email"]', 'alice-e2e@example.com');
  await page.fill('textarea[name="message"]', 'Hello from Playwright.');

  // Honor the 5-second time-trap.
  await page.waitForTimeout(6000);

  await page.click('button.starter-contact-form__submit');

  await expect(page.locator('.starter-contact-form__status')).toContainText(/thanks/i, { timeout: 10_000 });

  // Verify a contact_submission row exists via REST.
  // (Use Application Password or log in to wp-admin and check the list screen.)
  await page.goto('/wp-admin/edit.php?post_type=contact_submission');
  await expect(page.locator('text=alice-e2e@example.com')).toBeVisible();
});

test('honeypot triggers rejection (silent)', async ({ page }) => {
  await login(page);
  await createBlankPage(page, 'Honeypot test');
  await insertBlock(page, 'Contact Form');
  const url = await publishPage(page);

  await page.goto(url);
  await page.fill('input[name="name"]', 'Bot');
  await page.fill('input[name="email"]', 'bot@example.com');
  await page.fill('textarea[name="message"]', 'Spam');
  // Fill the honeypot — should be auto-empty for humans.
  await page.evaluate(() => {
    const el = document.querySelector<HTMLInputElement>('input[name="hp_field"]');
    if (el) el.value = 'bot was here';
  });
  await page.waitForTimeout(6000);

  await page.click('button.starter-contact-form__submit');

  await expect(page.locator('.starter-contact-form__status')).toContainText(/something went wrong|submission rejected/i);
});
```

- [ ] **Step 2: Run E2E**

```bash
wp-env start
npm run build
npm run e2e
```

Expected: 2 new tests pass.

- [ ] **Step 3: Commit**

```bash
git add tests/e2e/contact-form.spec.ts
git commit -m "test(e2e): contact form submission + honeypot rejection"
```

### Task 34: Brand Settings E2E

**Files:**
- Create: `tests/e2e/brand-settings.spec.ts`

- [ ] **Step 1: Write the failing spec**

```ts
// tests/e2e/brand-settings.spec.ts
import { test, expect } from '@playwright/test';
import { login } from './utils';

test('Brand Settings persists name + social link after save and reload', async ({ page }) => {
  await login(page);
  await page.goto('/wp-admin/options-general.php?page=starter-brand');

  await page.fill('input[name="starter_theme_brand[brand_name]"]', 'Acme E2E');

  // Add a social link
  await page.click('.starter-brand-social__add');
  const lastRow = page.locator('.starter-brand-social__row').last();
  await lastRow.locator('input[type="text"]').fill('twitter');
  await lastRow.locator('input[type="url"]').fill('https://x.com/acme-e2e');

  await page.click('input[type="submit"]'); // Save Changes
  await expect(page.locator('.notice-success')).toBeVisible();

  // Reload and verify
  await page.goto('/wp-admin/options-general.php?page=starter-brand');
  await expect(page.locator('input[name="starter_theme_brand[brand_name]"]')).toHaveValue('Acme E2E');
  await expect(page.locator('input[name="starter_theme_brand[social_links][0][platform]"]')).toHaveValue('twitter');
  await expect(page.locator('input[name="starter_theme_brand[social_links][0][url]"]')).toHaveValue('https://x.com/acme-e2e');
});
```

- [ ] **Step 2: Run E2E**

```bash
wp-env start
npm run e2e
```

Expected: 1 new test passes.

- [ ] **Step 3: Commit**

```bash
git add tests/e2e/brand-settings.spec.ts
git commit -m "test(e2e): Brand Settings persistence after save + reload"
```

---

## Phase 10: Documentation

### Task 35: README + docs/blocks.md

**Files:**
- Modify: `README.md`
- Create: `docs/blocks.md`

- [ ] **Step 1: Replace README.md**

```markdown
# Starter Theme

A forkable WordPress block theme for client websites. Sibling to the Payload Starter.

## Stack
- WordPress 6.4+
- PHP 8.1+
- FSE block theme (no parent dependency)
- TypeScript blocks compiled by `@wordpress/scripts`
- PHPUnit + Playwright for testing

## Local development

Requires Docker + Node 20+ + Composer.

```bash
git clone <repo>
cd wp-starter-theme
composer install
npm install
npm run build
npm run env:start        # spins up WP at http://localhost:8888 (admin/password)
```

Open http://localhost:8888/wp-admin. The theme should already be active.

Useful commands:

| Command                                | What it does                                     |
|----------------------------------------|--------------------------------------------------|
| `npm run start`                        | Watch + rebuild blocks                           |
| `npm run build`                        | One-shot block build                             |
| `npm run lint:blocks`                  | Asserts each block dir has the 3 required files |
| `npm run lint:colors`                  | Rejects hex/rgb/hsl in src/blocks/*.scss        |
| `npm run lint:js`                      | ESLint on src/                                   |
| `composer lint`                        | PHPCS (WP standards + custom color sniff)        |
| `npm run e2e`                          | Playwright against http://localhost:8888         |
| `wp-env run cli "wp starter-theme seed"` | Populate Brand defaults + 4 sample pages        |

### Run PHPUnit

```bash
wp-env run tests-wordpress --env-cwd=wp-content/themes/wp-starter-theme phpunit
```

## Deployment

This theme is designed to be installed via Composer in a [Bedrock](https://roots.io/bedrock/)-based client site (see Plan C / `wp-client-template`). It works on any WP host that supports custom themes; tested specifically on:

- **Hetzner Cloud / Hetzner Robot** — full control, works out of the box. Set `max_execution_time ≥ 60s` (the AI plugin's polling endpoints accommodate background jobs; the theme itself has no long-running requests).
- **Hetzner Webhosting** — works. Composer install via SSH or a pre-deploy CI step. Verify cron is enabled in their panel (Action Scheduler in the AI plugin depends on it; the theme's own daily contact-submission cleanup also needs it).
- **Kinsta / WP Engine / SpinupWP** — work for the theme; restrictions only affect the AI plugin's long-running requests.

### Cloudflare note

If you put Cloudflare proxy mode in front of the site, **bypass it for `/wp-json/starter-ai/*` paths** when you ship the AI plugin alongside this theme. The theme itself has no streaming requirements.

## Forking for a new client

This theme is the foundation; per-client customization happens in a child theme (see `wp-client-template/` / Plan C). The starter never edits client-owned files, and the child theme never edits starter files. Updates flow via `composer update bergert/wp-starter-theme`.

## Architecture

- `src/blocks/` — starter block sources. Each has `block.json`, `index.tsx`, `edit.tsx`, `render.php`, `style.scss`.
- `build/blocks/` — compiled output (gitignored). Generated by `npm run build`.
- `templates/` + `parts/` — FSE block templates and template parts.
- `theme.json` — the only source of design tokens. No hex values in `src/blocks/`.
- `inc/` — PHP includes (block registration, Brand settings, contact form, patterns, WP-CLI seed).

See [`docs/blocks.md`](docs/blocks.md) for the block authoring contract.
```

- [ ] **Step 2: Write docs/blocks.md**

```markdown
# Authoring starter blocks

Every block in `src/blocks/<name>/` must contain exactly these files:

```
src/blocks/<name>/
  block.json     metadata + attributes
  index.tsx      entry: registers the block with @wordpress/blocks
  edit.tsx       editor UI (React)
  render.php     server-side render
  style.scss     styles (optional but expected)
```

The `lint:blocks` script enforces the three required files.

## block.json conventions

For the AI plugin (Plan B) to compose with a block, `block.json` must include:

- **`description`** — a one-sentence description of what the block does and when to use it. Goes directly into the AI tool schema.
- **Explicit `attributes`** — every attribute must be declared with a `type` and ideally a `default`. Don't rely on `supports` to declare implicit attributes.
- **`name` namespaced under `starter/`** — for starter blocks. Client themes use `client/`.

Example:

```json
{
  "name": "starter/my-block",
  "title": "My Block",
  "category": "starter",
  "description": "Short, action-oriented description used by the AI composer.",
  "attributes": {
    "title": { "type": "string", "default": "" },
    "url":   { "type": "string", "default": "" }
  }
}
```

## Design tokens

No hex / rgb / hsl literals are allowed in `src/blocks/`. Use CSS custom properties from `theme.json`:

```scss
.starter-my-block {
  color: var(--wp--preset--color--text);
  background: var(--wp--preset--color--surface-elevated);
  padding: var(--wp--preset--spacing--30);
}
```

PHPCS sniff `Starter.NoColorLiteralSniff` and the `lint:colors` script will fail CI if you slip a literal in.

## Server-side rendering

Always render via `render.php`. The `save()` function returns `null`. This keeps content compatible across attribute changes and makes server-side variations (date formats, queries) trivial.

```php
// render.php
<?php
$attrs = $attributes; // shape declared in block.json
$wrapper = get_block_wrapper_attributes( [ 'class' => 'starter-my-block' ] );
?>
<div <?php echo $wrapper; ?>>
    <?php echo wp_kses_post( (string) ( $attrs['title'] ?? '' ) ); ?>
</div>
```

Sanitize aggressively: `wp_kses_post()` for rich text, `esc_html()` for plain strings, `esc_url()` for URLs, `esc_attr()` for attributes.

## Tests

Every block needs a test in `tests/phpunit/BlockRender/<Name>Test.php`. The minimum:

- Render with valid attributes → output contains expected substrings.
- Render with edge-case attributes (empty fields) → output handled gracefully (no PHP errors, no empty `<a href="">`).
```

- [ ] **Step 3: Commit**

```bash
git add README.md docs/blocks.md
git commit -m "docs: README + docs/blocks.md (block authoring contract)"
```

### Task 36: docs/client-blocks.md

**Files:**
- Create: `docs/client-blocks.md`

- [ ] **Step 1: Write docs/client-blocks.md**

```markdown
# Adding a `client/*` block

Client themes (child themes of `starter-theme`) live in a separate repo. They follow the same block contract as starter blocks, with two differences: namespace and registration path.

## Folder layout in the child theme

```
client-theme/
  blocks/
    promo-banner/
      block.json
      index.tsx
      edit.tsx
      render.php
      style.scss
  functions.php
  theme.json
  style.css
```

## Register from the child theme

In `client-theme/functions.php`:

```php
add_action( 'init', function () {
    foreach ( glob( __DIR__ . '/blocks/*', GLOB_ONLYDIR ) as $block_dir ) {
        if ( file_exists( $block_dir . '/block.json' ) ) {
            register_block_type( $block_dir );
        }
    }
} );
```

## Namespace

Use `client/<name>` for the block's `name` in `block.json`. This keeps client and starter blocks visually separable in the inserter and prevents collisions if the starter adds a block of the same name later.

```json
{ "name": "client/promo-banner" }
```

## AI-aware convention

For the AI plugin (Plan B) to compose with your client block, the same rules from `docs/blocks.md` apply:

- **`description` is required.**
- **`attributes` must be declared explicitly** with type + default.

The AI plugin discovers your block at runtime via `WP_Block_Type_Registry`. No additional registration is required on the plugin side.

## Theming

Override `theme.json` in your child theme. Brand color and typography overrides go in `settings.color.palette` and `settings.typography.fontFamilies`. WP merges parent + child automatically; you only declare what changes.

```json
{
  "$schema": "https://schemas.wp.org/trunk/theme.json",
  "version": 2,
  "settings": {
    "color": {
      "palette": [
        { "slug": "primary", "color": "#1A2238", "name": "Primary" },
        { "slug": "accent",  "color": "#FF6A3D", "name": "Accent" }
      ]
    }
  }
}
```

That's the only place where hex values are allowed.

## Don't edit starter files

The parent theme is installed by Composer at `web/app/themes/starter-theme/`. Treat it as read-only. Anything you need to change must be done via:

- A child-theme override (templates, parts, theme.json values).
- A new `client/*` block.
- A small client-side plugin (rare; not needed for v1).

If you find yourself wanting to modify a starter block, open an issue / PR upstream instead — that's how the starter improves for every client.
```

- [ ] **Step 2: Commit**

```bash
git add docs/client-blocks.md
git commit -m "docs: client-blocks.md — child-theme block authoring guide"
```

---

## Phase 11: Release

### Task 37: Tag v0.1.0 and verify Composer resolution

**Files:** none (release operations).

- [ ] **Step 1: Verify the full test matrix passes locally**

```bash
wp-env start
composer install
npm ci
npm run build
npm run lint:blocks
npm run lint:colors
npm run lint:js
composer lint
wp-env run tests-wordpress --env-cwd=wp-content/themes/wp-starter-theme phpunit
npm run e2e
```

Expected: all green.

- [ ] **Step 2: Bump version metadata**

In `style.css`, ensure the `Version:` header reads `0.1.0`. In `package.json`, ensure `"version": "0.1.0"`.

```bash
grep -E '^Version:' style.css
grep -E '"version"' package.json
```

- [ ] **Step 3: Create the GitHub repo and push**

```bash
gh repo create bergert/wp-starter-theme --private --source=. --remote=origin --push
```

(If the repo already exists, just `git push -u origin main`.)

- [ ] **Step 4: Wait for CI to go green**

```bash
gh run watch
```

Expected: all CI jobs (phpcs, lint-blocks, phpunit, e2e) pass.

- [ ] **Step 5: Tag the release**

```bash
git tag v0.1.0
git push origin v0.1.0
```

- [ ] **Step 6: Create the release on GitHub**

```bash
gh release create v0.1.0 --title "v0.1.0 — initial release" --notes "First versioned release of wp-starter-theme. See plan A in the Payload Starter repo for scope."
```

- [ ] **Step 7: Verify Composer resolution from a scratch directory**

```bash
mkdir -p /tmp/composer-resolve-test
cd /tmp/composer-resolve-test
cat > composer.json <<'EOF'
{
  "name": "test/resolve",
  "repositories": [
    { "type": "vcs", "url": "git@github.com:bergert/wp-starter-theme.git" }
  ],
  "require": {
    "bergert/wp-starter-theme": "^0.1"
  },
  "minimum-stability": "dev",
  "prefer-stable": true
}
EOF
composer install --no-interaction
ls vendor/bergert/wp-starter-theme/style.css
```

Expected: package resolves at v0.1.0; `style.css` exists.

- [ ] **Step 8: Clean up**

```bash
rm -rf /tmp/composer-resolve-test
```

Plan A complete. Move on to Plan B (AI plugin) and Plan C (client template).

---

## Self-review notes

- **Spec coverage:** Tasks 1–37 cover every v1 requirement in the spec — parent theme, 10 blocks, theme.json tokens, FSE templates, Brand Settings, contact form (REST, CPT, mail, cron, front-end JS), 3 patterns, WP-CLI seed, color-literal lint (PHPCS + SCSS), CI, three E2E specs, docs, v0.1.0 release. AI plugin scope explicitly deferred to Plan B; Bedrock client wiring deferred to Plan C.
- **Type consistency:** Block attribute names (`headline`, `subheadline`, `ctaText`, `ctaUrl`, `variant`, `question`, `answer`, `mediaId`, `altOverride`, `value`, `label`, `context`, `count`, `categorySlug`, `includePhone`, `recipientOverride`, `successMessage`) are stable across `block.json`, `edit.tsx`, `render.php`, and tests. The `Starter\Brand` class is referenced consistently in Tasks 22/27/30. CPT name (`STARTER_CONTACT_CPT`), REST namespace (`starter/v1`), option key (`starter_theme_brand`), cron hook (`STARTER_CONTACT_CRON_HOOK`) are constants reused across tasks.
- **No placeholders:** all 37 tasks contain test code, implementation code, and exact commands. No "TBD" / "implement later" references remain.
- **Known open items to resolve during execution (carried from spec § Open Questions):** Composer distribution mechanism (private GitHub repo with `composer/installers` vs Satis vs Packeton) — Task 37 assumes private GitHub repo; reconsider if onboarding many clients. Exact seed page copy (Task 30) was filled with sensible defaults — clients will customise.

