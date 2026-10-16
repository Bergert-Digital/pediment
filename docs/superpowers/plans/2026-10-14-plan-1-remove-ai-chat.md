# Plan 1 — Remove the In-Plugin AI Chat Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Delete the in-plugin AI chat (editor UI, Anthropic client, chat REST API, usage tracking, settings tab, tables) and clean its data off existing sites, without touching blocks, forms, seeding or languages.

**Architecture:** Removal in three code tasks that each leave the plugin green: (1) the editor bundle and its enqueue, (2) the server side, (3) a version-independent cleanup routine that drops chat tables and options on upgrade and on uninstall. A fourth task fixes docs and metadata. The `react-jsx-runtime` shim lived inside the chat enqueue closure, so this plan raises `Requires at least` to 6.9 (core registers that handle since 6.6).

**Tech Stack:** PHP 8.1, WordPress 6.9+, PHPUnit via wp-env, `@wordpress/scripts`, Playwright.

**Spec:** `docs/superpowers/specs/2026-10-14-core-blocks-mcp-design.md` (§1 "Removed", §4 "Chat removal"). Roadmap: `docs/superpowers/plans/2026-10-14-roadmap.md`.

## Global Constraints

- Plugin header `Requires at least:` becomes `6.9`; `Requires PHP:` stays `8.1`.
- Keep the general constants `PEDIMENT_AI_VERSION`, `PEDIMENT_AI_PLUGIN_FILE`, `PEDIMENT_AI_PLUGIN_DIR`, `PEDIMENT_AI_PLUGIN_URL` and the `pediment_ai_*` function prefix — they are not chat (see `plugin/AGENTS.md`).
- Keep the REST namespace `pediment/v1` (forms use it: `plugin/inc/forms.php:12-13`, route `/forms`).
- Keep every settings tab except `ai`: `general`, `seed`, `destinations`, `secrets`.
- Chat data to remove on every site: tables `{prefix}pediment_ai_jobs`, `pediment_ai_usage`, `pediment_ai_chat_conversations`, `pediment_ai_chat_messages`, `pediment_ai_chat_attachments`; options `pediment_ai_settings`, `pediment_ai_rate_limits`, `pediment_ai_db_version`; transients `pediment_ai_schema`, `pediment_ai_rl_*`, `pediment_ai_turn_token_*`, `pediment_ai_turn_input_*`; cron hook `pediment_ai_job_run`.
- Run commands from the repo root unless a step says otherwise. Tests: `npx wp-env run tests-wordpress --env-cwd=wp-content/plugins/pediment-ai ./vendor/bin/phpunit --filter <Name>`. Lint: `composer lint -d plugin`, `npm run lint:js`.
- Commit after each task with a conventional-commit message, staging files by name (`git rm` for deletions).

## Review Focus

1. A site upgraded from 3.x with chat rows in its tables must lose all five tables and three options on the first request after the update, and must not run the cleanup again — Task 3 pins it.
2. Blocks must still load in the editor on a fresh WP 6.9 install after the shim goes (every block bundle depends on `react-jsx-runtime`) — Task 1 pins it.
3. The forms REST route `pediment/v1/forms` must survive the removal of the chat routes in the same namespace — Task 2 pins it.
4. The settings page must still render with its four remaining tabs — Task 2 pins it.
5. Uninstalling the plugin must remove the chat data that 3.x's `uninstall.php` forgot (three chat tables, two options) — Task 3 pins it.

---

### Task 1: Remove the editor chat UI and raise the WordPress floor

**Files:**
- Delete: `plugin/editor/` (whole directory, incl. its Jest tests)
- Delete: `plugin/webpack.config.js`
- Delete: `plugin/assets/react-jsx-runtime-shim.js`
- Delete: `plugin/tests/e2e/chat-abort.spec.ts`, `chat-compose.spec.ts`, `chat-image.spec.ts`, `chat-refine.spec.ts`
- Delete: `plugin/tests/phpunit/Bootstrap/EditorAiStatusConfigTest.php`
- Modify: `plugin/src/Bootstrap.php` (remove the `enqueue_block_editor_assets` closure)
- Modify: `plugin/plugin.php:9`
- Modify: `plugin/package.json` (scripts; remove 5 devDependencies)
- Modify: `plugin/tsconfig.json`, `plugin/.distignore`
- Modify: `plugin/tests/e2e/utils.ts` (remove 3 helpers), `plugin/tests/e2e/global-setup.ts` (remove the transient loop)
- Test: `plugin/tests/phpunit/Bootstrap/EditorAssetsTest.php`

**Interfaces:**
- Consumes: nothing.
- Produces: no `pediment-editor` script or style; `npm run build` builds blocks only.

- [ ] **Step 1: Write the failing test**

`plugin/tests/phpunit/Bootstrap/EditorAssetsTest.php`:

```php
<?php

class EditorAssetsTest extends WP_UnitTestCase {

	public function test_no_chat_editor_bundle_is_enqueued(): void {
		do_action( 'enqueue_block_editor_assets' );
		$this->assertFalse( wp_script_is( 'pediment-editor', 'enqueued' ) );
		$this->assertFalse( wp_style_is( 'pediment-editor', 'enqueued' ) );
	}

	public function test_core_provides_the_jsx_runtime_blocks_depend_on(): void {
		$this->assertTrue( wp_script_is( 'react-jsx-runtime', 'registered' ) );
		$this->assertStringNotContainsString( 'react-jsx-runtime-shim', (string) wp_scripts()->registered['react-jsx-runtime']->src );
	}

	public function test_plugin_requires_wordpress_6_9(): void {
		$data = get_file_data( PEDIMENT_AI_PLUGIN_FILE, array( 'requires' => 'Requires at least' ) );
		$this->assertSame( '6.9', $data['requires'] );
	}
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `npx wp-env run tests-wordpress --env-cwd=wp-content/plugins/pediment-ai ./vendor/bin/phpunit --filter EditorAssetsTest`
Expected: FAIL — `pediment-editor` is enqueued (if `plugin/build/index.asset.php` exists) and `Requires at least` is `6.4`.

- [ ] **Step 3: Remove the enqueue closure**

In `plugin/src/Bootstrap.php`, delete the whole block that starts with `add_action( 'enqueue_block_editor_assets', static function () {` and ends with its closing `} );` (the closure that includes `build/index.asset.php`, the `react-jsx-runtime` shim registration, `pedimentAiEditor` and `build/index.css`). Leave everything else in `register()` for Task 2.

- [ ] **Step 4: Raise the floor**

In `plugin/plugin.php` change ` * Requires at least: 6.4` to ` * Requires at least: 6.9`.

- [ ] **Step 5: Delete the editor sources and chat tests**

```bash
git rm -r plugin/editor
git rm plugin/webpack.config.js plugin/assets/react-jsx-runtime-shim.js
git rm plugin/tests/e2e/chat-abort.spec.ts plugin/tests/e2e/chat-compose.spec.ts plugin/tests/e2e/chat-image.spec.ts plugin/tests/e2e/chat-refine.spec.ts
git rm plugin/tests/phpunit/Bootstrap/EditorAiStatusConfigTest.php
rm -f plugin/build/index.js plugin/build/index.css plugin/build/index.asset.php
```

- [ ] **Step 6: Update the build config**

In `plugin/package.json` `scripts`, replace these entries:

```json
    "start": "WP_EXPERIMENTAL_MODULES=true wp-scripts start --webpack-src-dir=src/blocks --output-path=build/blocks",
    "build": "npm run build:blocks",
    "lint:js": "wp-scripts lint-js src/",
    "format": "wp-scripts format src/",
```

and delete the `"start:blocks"` and `"build:editor"` entries. Then remove the editor-only devDependencies:

```bash
cd plugin && npm uninstall @wordpress/api-fetch @wordpress/compose @wordpress/edit-post @wordpress/hooks @wordpress/plugins && cd ..
```

Before uninstalling, confirm none of the five is imported under `plugin/src/`: `grep -rn "@wordpress/\(api-fetch\|compose\|edit-post\|hooks\|plugins\)" plugin/src` must print nothing. If one is imported, keep that one.

In `plugin/tsconfig.json`, delete the line `"paths": { "@/*": ["editor/*"] },` and change `include` to:

```json
  "include": ["src/**/*.ts", "src/**/*.tsx"],
```

In `plugin/.distignore`, delete the line `editor`.

- [ ] **Step 7: Remove the e2e chat helpers**

In `plugin/tests/e2e/utils.ts`, delete the functions `openSidebarTab`, `openAIChatPanel` and `openBlockAIChatPanel` with their doc comments (the block between the end of the preceding function and `/** Create a page through wp-cli and return its front-end URL. */`). Remove `Page` from the imports only if nothing else in the file uses it (`grep -n "Page" plugin/tests/e2e/utils.ts`).

In `plugin/tests/e2e/global-setup.ts`, delete the comment `// E2E runs share the persistent wp-env database. Reset the per-user …` and the loop `for ( const kind of [ 'compose', 'edit', 'refine' ] ) { … }` that follows it.

- [ ] **Step 8: Verify**

```bash
npx wp-env run tests-wordpress --env-cwd=wp-content/plugins/pediment-ai ./vendor/bin/phpunit --filter 'EditorAssetsTest|BootstrapTest|SmokeTest'
cd plugin && npm run build && npm run test:js && cd ..
npm run lint:js
```

Expected: PHPUnit PASS; build produces `plugin/build/blocks/` and `plugin/build/blocks-manifest.php` only; Jest runs the four remaining `src/` tests and passes; lint passes.

Then open http://localhost:8888/wp-admin/post-new.php?post_type=page, insert a Pediment block (e.g. "Section Head") and a core Paragraph, and confirm both render with no console error about `react-jsx-runtime`.

- [ ] **Step 9: Commit**

```bash
git add plugin/src/Bootstrap.php plugin/plugin.php plugin/package.json plugin/package-lock.json plugin/tsconfig.json plugin/.distignore plugin/tests/e2e/utils.ts plugin/tests/e2e/global-setup.ts plugin/tests/phpunit/Bootstrap/EditorAssetsTest.php
git commit -m "feat!: remove the AI chat editor UI, require WordPress 6.9"
```

---

### Task 2: Remove the server-side chat

**Files:**
- Delete: `plugin/src/Chat/`, `plugin/src/Anthropic/`, `plugin/src/Usage/`, `plugin/src/Mock/`, `plugin/src/Rest/`, `plugin/src/BlockTree/`, `plugin/src/Settings/`, `plugin/src/Schema/`
- Delete: `plugin/wp-cli/DumpSchemaCommand.php`
- Delete tests: `plugin/tests/phpunit/Anthropic/`, `Chat/`, `Mock/`, `Rest/`, `Usage/`, `BlockTree/`, `Schema/`, `Settings/OptionsStoreTest.php`, `Settings/PageSanitizeTest.php`, `Settings/PageMenuTest.php`, `Cli/DumpSchemaCommandTest.php`, `ConversationStoreTest.php`, `TurnRunnerImagesTest.php`
- Modify: `plugin/src/Bootstrap.php`, `plugin/plugin.php`, `plugin/tests/phpunit/Settings/FormsTabsTest.php:18-23`, `plugin/phpcs.xml.dist:60-65`, `plugin/wp-cli/SeedCommand.php:54` (comment)
- Test: `plugin/tests/phpunit/ChatRemovedTest.php`

**Interfaces:**
- Consumes: Task 1 (no editor enqueue left in `Bootstrap`).
- Produces: `Bootstrap::register()` with only the three non-chat registrations (Plan 2 appends to it).

- [ ] **Step 1: Write the failing test**

`plugin/tests/phpunit/ChatRemovedTest.php`:

```php
<?php

class ChatRemovedTest extends WP_UnitTestCase {

	public function test_chat_routes_are_gone_and_forms_route_stays(): void {
		$routes = array_keys( rest_get_server()->get_routes() );
		$this->assertSame( array(), array_values( array_filter( $routes, static fn( string $r ): bool => str_starts_with( $r, '/pediment/v1/chat' ) ) ) );
		$this->assertContains( '/pediment/v1/forms', $routes );
	}

	public function test_ai_settings_tab_is_gone_and_the_rest_remain(): void {
		unset( $GLOBALS['pediment_settings_tabs'] );
		do_action( 'admin_menu' );
		$this->assertSame( array( 'general', 'seed', 'destinations', 'secrets' ), array_keys( pediment_settings_get_tabs() ) );
	}

	public function test_chat_classes_and_install_function_are_gone(): void {
		$this->assertFalse( class_exists( '\Pediment\Chat\TurnRunner' ) );
		$this->assertFalse( class_exists( '\Pediment\Anthropic\Client' ) );
		$this->assertFalse( class_exists( '\Pediment\Settings\Page' ) );
		$this->assertFalse( function_exists( 'pediment_ai_install_tables' ) );
		$this->assertFalse( has_filter( 'pediment_ai_provider' ) );
	}
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `npx wp-env run tests-wordpress --env-cwd=wp-content/plugins/pediment-ai ./vendor/bin/phpunit --filter ChatRemovedTest`
Expected: FAIL — chat routes exist, the `ai` tab exists, classes exist.

- [ ] **Step 3: Strip `Bootstrap`**

Replace the body of `plugin/src/Bootstrap.php` from the class doc comment to the end with:

```php
/**
 * Wires the plugin's hooks.
 */
final class Bootstrap {
	/**
	 * Registers hooks. Called on plugins_loaded.
	 *
	 * @return void
	 */
	public function register(): void {
		\Pediment\Templates\Registrar::register();
		\Pediment\Tokens\Injector::register();
		\Pediment\Seeder\PostTypes::register();
	}
}
```

- [ ] **Step 4: Strip `plugin.php`**

In `plugin/plugin.php`:
- Delete the block from `require_once __DIR__ . '/src/Schema/tables.php';` through the closing `);` of the `plugins_loaded` callback that calls `pediment_ai_install_tables()` (the activation hook and the version-gated install).
- In the WP-CLI block, delete the two lines for `DumpSchemaCommand` / `pediment dump-schema`.
- Change the header description to: ` * Description:       The Pediment site engine: Gutenberg blocks, templates, design tokens, forms and seeding for client block themes.`

- [ ] **Step 5: Delete the chat code and tests**

```bash
git rm -r plugin/src/Chat plugin/src/Anthropic plugin/src/Usage plugin/src/Mock plugin/src/Rest plugin/src/BlockTree plugin/src/Settings plugin/src/Schema
git rm plugin/wp-cli/DumpSchemaCommand.php
git rm -r plugin/tests/phpunit/Anthropic plugin/tests/phpunit/Chat plugin/tests/phpunit/Mock plugin/tests/phpunit/Rest plugin/tests/phpunit/Usage plugin/tests/phpunit/BlockTree plugin/tests/phpunit/Schema
git rm plugin/tests/phpunit/Settings/OptionsStoreTest.php plugin/tests/phpunit/Settings/PageSanitizeTest.php plugin/tests/phpunit/Settings/PageMenuTest.php plugin/tests/phpunit/Cli/DumpSchemaCommandTest.php plugin/tests/phpunit/ConversationStoreTest.php plugin/tests/phpunit/TurnRunnerImagesTest.php
```

Then confirm nothing outside the deleted paths still references them:

```bash
grep -rn "Pediment\\\\\(Chat\|Anthropic\|Usage\|Mock\|Rest\|BlockTree\|Settings\)\\\\\|pediment_ai_install_tables\|OptionsStore\|DumpSchemaCommand" plugin --include=*.php | grep -v vendor
```

Expected: no output, except the `SeedCommand.php:54` comment fixed in Step 6.

- [ ] **Step 6: Fix the shared files**

`plugin/tests/phpunit/Settings/FormsTabsTest.php` — replace the comment and assertion in `test_forms_registers_three_tabs_in_order()` with:

```php
		// The seeding tab ships in the same plugin, so admin_menu also mounts
		// 'seed' (priority 20, between 'general' and 'destinations').
		$tabs = pediment_settings_get_tabs();
		$this->assertSame( array( 'general', 'seed', 'destinations', 'secrets' ), array_keys( $tabs ) );
```

`plugin/phpcs.xml.dist` — in the comment above `Squiz.Commenting.FileComment.MissingPackageTag`, replace `(Anthropic/, Rest/, Chat/, Usage/, …)` with `(Seeder/, Language/, Design/, …)`. On line 2–3 change `name="Pediment AI"` to `name="Pediment"` and the description to `Coding standards for the Pediment plugin.`

`plugin/wp-cli/SeedCommand.php:54` — reword the comment so it no longer names `DumpSchemaCommand` (for example `Guarded so it only loads under WP-CLI.`); read the surrounding lines first and keep the meaning.

- [ ] **Step 7: Run tests to verify they pass**

```bash
npx wp-env run tests-wordpress --env-cwd=wp-content/plugins/pediment-ai ./vendor/bin/phpunit
composer lint -d plugin
```

Expected: full suite PASS (it is now smaller); lint PASS. Open http://localhost:8888/wp-admin/options-general.php?page=pediment-theme and confirm four tabs render.

- [ ] **Step 8: Commit**

```bash
git add plugin/src/Bootstrap.php plugin/plugin.php plugin/tests/phpunit/Settings/FormsTabsTest.php plugin/phpcs.xml.dist plugin/wp-cli/SeedCommand.php plugin/tests/phpunit/ChatRemovedTest.php
git commit -m "feat!: remove the AI chat server, REST API and settings"
```

---

### Task 3: Clean chat data off existing sites

**Files:**
- Create: `plugin/src/Upgrade/ChatDataCleanup.php`
- Modify: `plugin/plugin.php` (one hook), `plugin/uninstall.php` (rewrite)
- Test: `plugin/tests/phpunit/Upgrade/ChatDataCleanupTest.php`

**Interfaces:**
- Consumes: nothing.
- Produces: `Pediment\Upgrade\ChatDataCleanup::needed(): bool`, `ChatDataCleanup::run(): void`, constants `TABLES` (5 short names) and `OPTIONS` (3 names).

- [ ] **Step 1: Write the failing test**

`plugin/tests/phpunit/Upgrade/ChatDataCleanupTest.php`:

```php
<?php

use Pediment\Upgrade\ChatDataCleanup;

class ChatDataCleanupTest extends WP_UnitTestCase {

	private function table_exists( string $short ): bool {
		global $wpdb;
		$wpdb->suppress_errors( true );
		$result = $wpdb->query( "SELECT 1 FROM {$wpdb->prefix}{$short} LIMIT 1" ); // phpcs:ignore WordPress.DB
		$wpdb->suppress_errors( false );
		return false !== $result;
	}

	private function seed_legacy_data(): void {
		global $wpdb;
		foreach ( ChatDataCleanup::TABLES as $short ) {
			$wpdb->query( "CREATE TABLE {$wpdb->prefix}{$short} ( id bigint(20) unsigned NOT NULL AUTO_INCREMENT, PRIMARY KEY (id) )" ); // phpcs:ignore WordPress.DB
			$wpdb->query( "INSERT INTO {$wpdb->prefix}{$short} () VALUES ()" ); // phpcs:ignore WordPress.DB
		}
		update_option( 'pediment_ai_settings', array( 'api_key_encrypted' => 'x' ) );
		update_option( 'pediment_ai_rate_limits', array( 'compose' => 30 ) );
		update_option( 'pediment_ai_db_version', '3.10.0' );
		set_transient( 'pediment_ai_schema', 'x' );
		set_transient( 'pediment_ai_rl_1_compose', 3 );
		set_transient( 'pediment_ai_turn_token_42', 'x' );
		set_transient( 'pediment_ai_turn_input_42', 'x' );
		wp_schedule_single_event( time() + 60, 'pediment_ai_job_run' );
	}

	public function test_removes_tables_options_transients_and_cron(): void {
		$this->seed_legacy_data();
		$this->assertTrue( ChatDataCleanup::needed() );

		ChatDataCleanup::run();

		foreach ( ChatDataCleanup::TABLES as $short ) {
			$this->assertFalse( $this->table_exists( $short ), $short );
		}
		foreach ( ChatDataCleanup::OPTIONS as $option ) {
			$this->assertFalse( get_option( $option ), $option );
		}
		$this->assertFalse( get_transient( 'pediment_ai_schema' ) );
		$this->assertFalse( get_transient( 'pediment_ai_rl_1_compose' ) );
		$this->assertFalse( get_transient( 'pediment_ai_turn_token_42' ) );
		$this->assertFalse( get_transient( 'pediment_ai_turn_input_42' ) );
		$this->assertFalse( wp_next_scheduled( 'pediment_ai_job_run' ) );
		$this->assertFalse( ChatDataCleanup::needed() );
	}

	public function test_does_not_touch_non_chat_data(): void {
		update_option( 'pediment_form_retention_days', 30 );
		update_option( 'pediment_ai_db_version', '3.10.0' );
		ChatDataCleanup::run();
		$this->assertSame( 30, (int) get_option( 'pediment_form_retention_days' ) );
	}

	public function test_fresh_site_needs_nothing(): void {
		delete_option( 'pediment_ai_db_version' );
		delete_option( 'pediment_ai_settings' );
		delete_option( 'pediment_ai_rate_limits' );
		$this->assertFalse( ChatDataCleanup::needed() );
	}
}
```

Note: the WordPress test suite turns `CREATE TABLE` into `CREATE TEMPORARY TABLE`, which `SHOW TABLES` cannot see; the helper therefore probes with `SELECT`.

- [ ] **Step 2: Run test to verify it fails**

Run: `npx wp-env run tests-wordpress --env-cwd=wp-content/plugins/pediment-ai ./vendor/bin/phpunit --filter ChatDataCleanupTest`
Expected: FAIL — `Class "Pediment\Upgrade\ChatDataCleanup" not found`.

- [ ] **Step 3: Write the cleanup**

`plugin/src/Upgrade/ChatDataCleanup.php`:

```php
<?php
/**
 * Removes the data the retired AI chat (Pediment ≤ 3.x) left on a site.
 *
 * @package Pediment
 */

declare(strict_types=1);

namespace Pediment\Upgrade;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Idempotent: `needed()` is true while any chat option remains, so the routine
 * runs once per site and never again.
 */
final class ChatDataCleanup {

	public const TABLES = array(
		'pediment_ai_jobs',
		'pediment_ai_usage',
		'pediment_ai_chat_conversations',
		'pediment_ai_chat_messages',
		'pediment_ai_chat_attachments',
	);

	public const OPTIONS = array(
		'pediment_ai_settings',
		'pediment_ai_rate_limits',
		'pediment_ai_db_version',
	);

	private const TRANSIENT_PREFIXES = array(
		'pediment_ai_schema',
		'pediment_ai_rl_',
		'pediment_ai_turn_token_',
		'pediment_ai_turn_input_',
	);

	/**
	 * Whether chat data is still present.
	 */
	public static function needed(): bool {
		foreach ( self::OPTIONS as $option ) {
			if ( false !== get_option( $option ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Drops chat tables, options, transients and cron events.
	 */
	public static function run(): void {
		global $wpdb;

		foreach ( self::TABLES as $short ) {
			$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}{$short}" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery.SchemaChange
		}

		foreach ( self::TRANSIENT_PREFIXES as $prefix ) {
			$like = $wpdb->esc_like( '_transient_' . $prefix ) . '%';
			$keys = $wpdb->get_col( $wpdb->prepare( "SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE %s", $like ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			foreach ( $keys as $key ) {
				delete_transient( substr( (string) $key, strlen( '_transient_' ) ) );
			}
		}

		wp_clear_scheduled_hook( 'pediment_ai_job_run' );

		foreach ( self::OPTIONS as $option ) {
			delete_option( $option );
		}
	}
}
```

The options are deleted last, so an interrupted run is retried on the next request.

- [ ] **Step 4: Run it on upgrade and on uninstall**

In `plugin/plugin.php`, where the removed table-install hook used to be (before the WP-CLI block), add:

```php
// Pediment 4 removed the AI chat; drop its leftover data once per site.
add_action(
	'plugins_loaded',
	static function () {
		if ( \Pediment\Upgrade\ChatDataCleanup::needed() ) {
			\Pediment\Upgrade\ChatDataCleanup::run();
		}
	},
	5
);
```

Replace `plugin/uninstall.php` with:

```php
<?php
/**
 * Runs when the plugin is deleted from wp-admin. Removes the retired AI chat's
 * data. Forms submissions and seeded content belong to the site and stay.
 *
 * @package Pediment
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

require_once __DIR__ . '/vendor/autoload.php';

\Pediment\Upgrade\ChatDataCleanup::run();
```

- [ ] **Step 5: Run tests to verify they pass**

Run: `npx wp-env run tests-wordpress --env-cwd=wp-content/plugins/pediment-ai ./vendor/bin/phpunit --filter 'ChatDataCleanupTest|ChatRemovedTest|SmokeTest'`
Expected: PASS.

- [ ] **Step 6: Lint and commit**

```bash
composer lint -d plugin
git add plugin/src/Upgrade/ChatDataCleanup.php plugin/plugin.php plugin/uninstall.php plugin/tests/phpunit/Upgrade/ChatDataCleanupTest.php
git commit -m "feat: drop retired AI chat data on upgrade and uninstall"
```

---

### Task 4: Docs, metadata and environment

**Files:**
- Delete: `docs/privacy.md`, `docs/extending.md`
- Modify: `.wp-env.json`, `.wp-env.wpml.json` (remove two constants each)
- Modify: `README.md`, `AGENTS.md`, `plugin/README.md`, `plugin/AGENTS.md`, `plugin/composer.json`, `docs/VISION.md`, `docs/STANDARDS.md`, `docs/blocks.md`, `docs/client-sites.md`, `docs/BACKLOG.md`, `client-template/functions.php`, `client-kit/skills/start/SKILL.md`
- Test: `npm run test:kit`; a grep check

**Interfaces:** none.

- [ ] **Step 1: Remove the env constants**

In `.wp-env.json` and `.wp-env.wpml.json`, delete the `"PEDIMENT_AI_MOCK": true,` and `"PEDIMENT_AI_LOOPBACK_URL": "http://127.0.0.1"` entries from `config` (fix the trailing comma on `"SCRIPT_DEBUG": true`). Check `.wp-env.override.json` too if it exists locally (it is gitignored; edit, do not commit).

- [ ] **Step 2: Delete chat-only docs**

```bash
git rm docs/privacy.md docs/extending.md
grep -rn "extending.md\|privacy.md\|docs/prompts.md" --include=*.md . | grep -v node_modules | grep -v docs/superpowers
```

Remove every link the grep prints (outside `docs/superpowers/`, which is history).

- [ ] **Step 3: Rewrite the product descriptions**

`README.md` lines 3–4 → `blocks, templates, patterns, design tokens, forms and seeding.` (sentence: "Pediment is a WordPress plugin that ships the shared site engine: 24 Gutenberg blocks, templates, patterns, design tokens, forms and seeding.").

`AGENTS.md` line 8: `blocks, templates, patterns, tokens, forms, assets, and AI editor experience.` → `blocks, templates, patterns, tokens, forms, assets, and seeding.`

`plugin/composer.json` description → `"The Pediment site engine: WordPress blocks, templates, design tokens, forms and seeding"`.

`docs/VISION.md`:
- line 6: `templates, patterns, default design tokens, forms, assets, and AI authoring.` → `templates, patterns, default design tokens, forms, assets, and seeding.`
- lines 22–23 → `2. **Site editors** need solid blocks and sensible defaults in Gutenberg.`
- lines 30–31 → `- **AI-consumable by construction.** Complete \`block.json\` metadata lets AI agents discover the same registered blocks that sites use.`

`docs/STANDARDS.md`: line 33 `Forms, patterns, templates, and AI have suites.` → `Forms, patterns, and templates have suites.`; line 45 `forms, and AI flows.` → `and forms.`

`docs/blocks.md` line 18 → `For AI agents to compose with a block, \`block.json\` must include:`; line 20 drop `Goes directly into the AI tool schema.`; line 31 example description → `"Short, action-oriented description of what the block does."`.

`docs/client-sites.md`:
- lines 10–11 → `seeding to WordPress and ships as \`pediment-plugin.zip\`.` (drop "and AI features").
- lines 46–47 → `into that question does not change the plugin's behaviour — see "What a scaffolded repo contains"`
- lines 87–89 → `read by humans and agents; nothing in the plugin reads it programmatically`

`client-template/functions.php` line 5–6 → `seeding all ship in the Pediment plugin. What lives here`.

`client-kit/skills/start/SKILL.md` line 80: delete the sentence `Do not imply the AI features will pick it up.`

- [ ] **Step 4: Rewrite `plugin/README.md`**

Replace lines 1–25 (title through the `pediment/v1` sentence) with:

```markdown
# Pediment

WordPress site engine with shared Gutenberg blocks, templates, patterns, design
tokens, forms and seeding.

## Requirements

- WordPress 6.9+, PHP 8.1+
- A standalone client block theme

## Install

Upload `pediment-plugin.zip` from the latest [GitHub Release](https://github.com/Bergert-Digital/pediment/releases) via **Plugins → Add New → Upload Plugin**, then activate. It installs as `pediment`; after the first install, updates arrive through the normal wp-admin Updates screen.

Upgrading from 3.x removes the retired AI chat's tables and settings on the first request.

The REST API uses the `pediment/v1` namespace.
```

Delete the sections `## Web fetch`, `## Models` and `## Rate limits`. In "Local dev", change `--filter ComposeJobTest` to `--filter SmokeTest`, and delete the paragraph that starts `Mock mode is on by default` and the line that links `docs/prompts.md` and `docs/privacy.md`.

`plugin/AGENTS.md`: line 4 → `patterns, tokens, forms, assets and seeding as one`; delete line 29 (`- Do not reintroduce streaming, extra AI modals, or client-theme dependencies.`) and add `- Do not add client-theme dependencies.` in its place.

- [ ] **Step 5: Update the backlog**

In `docs/BACKLOG.md`:
- In the "Commercial protection" entry, replace the sentences naming `plugin/src/Chat/PromptBuilder.php`, `TurnRunner.php`, `SchemaBuilder.php` and `Client.php:30` with: `The in-plugin AI chat was removed in 4.0.0 (spec 2026-10-14-core-blocks-mcp-design), so there are no shipped prompts left to protect; revisit this entry when the MCP tools gain server-side logic worth gating.`
- Delete the entry `**Brand voice is captured but not consumed.**`.
- In the `wp pediment translate` entry, replace `via the existing AI editor plumbing` with `via the MCP tools (Plan 3)`.

- [ ] **Step 6: Verify**

```bash
grep -rniE "anthropic|ai composer|ai editor|ai chat|PEDIMENT_AI_MOCK|LOOPBACK_URL" --include=*.md --include=*.php --include=*.json . | grep -v node_modules | grep -v vendor | grep -v "docs/superpowers" | grep -v SESSION_LOG | grep -v CHANGELOG
npm run test:kit
```

Expected: the grep prints only the BACKLOG sentence from Step 5 (which mentions the removal) — fix anything else it prints; `test:kit` passes.

- [ ] **Step 7: Commit**

```bash
git add .wp-env.json .wp-env.wpml.json README.md AGENTS.md plugin/README.md plugin/AGENTS.md plugin/composer.json docs/VISION.md docs/STANDARDS.md docs/blocks.md docs/client-sites.md docs/BACKLOG.md client-template/functions.php client-kit/skills/start/SKILL.md
git commit -m "docs: remove the AI chat from docs and environment"
```

---

## Final verification

- [ ] Restart wp-env so the removed constants disappear (`npx wp-env stop && npx wp-env start`), then run:

```bash
npx wp-env run tests-wordpress --env-cwd=wp-content/plugins/pediment-ai ./vendor/bin/phpunit
composer lint -d plugin
npm run lint:js
npm run lint:blocks
npm run lint:colors
cd plugin && npm run build && npm run test:js && npm run e2e && cd ..
npm run test:kit
```

Expected: all pass. Any failure is reported with its output.
