# Check-for-Theme-Updates Button Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** A "Check for theme updates" button on Dashboard → Updates that forces a cache-bypassing PUC check for the Pediment parent and child themes and reports the per-theme result in an admin notice.

**Architecture:** Each theme's `ThemeUpdater` exposes its PUC instance via a `pediment_update_checkers` filter. A new parent-theme file `inc/update-check.php` renders a section on the Updates screen (documented `core_upgrade_preamble` hook), handles the button via `admin-post.php` (nonce + `update_themes` capability), calls PUC's `checkForUpdates()` per checker, stores results in a per-user transient, and renders them as a one-shot admin notice. All button logic is filter-driven, so tests inject fake checkers and never need PUC installed.

**Tech Stack:** WordPress theme PHP 8.1, plugin-update-checker v5.7 (vendored in release builds only), `WP_UnitTestCase` via wp-env's `tests-wordpress` container, phpcs (WordPress coding standards — warnings fail CI).

**Spec:** `docs/superpowers/specs/2026-06-12-check-theme-updates-button-design.md`

**Verified PUC v5.7 API facts** (from `Puc/v5p7/` source — do not re-derive):
- `checkForUpdates()` returns `Update|null` (`null` = no newer version OR check failed).
- `getLastRequestApiErrors()` is public and returns the API errors collected during the last `checkForUpdates()` call — this distinguishes "up to date" from "check failed".
- `getUpdateState()->getLastCheck()` is public and returns a Unix timestamp (`0` if never checked).
- The update object exposes the new version as the public property `$update->version`.

**Repos and branches:**
- Parent: `/Users/jonas/Entwicklung/pediment`, working branch `development`. Multi-task plan → work in a worktree (Task 0).
- Child: `/Users/jonas/Entwicklung/pediment-child-theme`, working branch `development`. Single-file change (Task 6) — done directly on `development`, no worktree.

---

### Task 0: Worktree and test environment

The parent theme's PHPUnit suite runs inside wp-env's `tests-wordpress` container (see `.github/workflows/ci.yml`). The worktree needs its own `vendor/` and wp-env instance. Note: the child theme's wp-env (port 8890) pulls the parent theme from a **release zip**, so it can NOT test local parent changes — always use the parent repo's own wp-env as below.

- [ ] **Step 1: Create the worktree** (per `superpowers:using-git-worktrees`; location policy: inside the project)

```bash
cd /Users/jonas/Entwicklung/pediment
git worktree add .worktrees/check-updates-button development
cd .worktrees/check-updates-button
```

Verify `.worktrees/` is gitignored; if not, add it to `.gitignore` on `development` first.

- [ ] **Step 2: Install dependencies in the worktree**

```bash
composer install --prefer-dist --no-progress
npm ci
```

- [ ] **Step 3: Start wp-env and verify the existing suite passes**

```bash
npm run env:start
npx wp-env run tests-wordpress --env-cwd=wp-content/themes/pediment vendor/bin/phpunit
```

Expected: all existing tests PASS. (The parent `.wp-env.json` uses default ports 8888/8889 — stop any other env on those ports first. The child's env on 8890/8891 does not conflict.)

All subsequent parent-repo tasks run inside `/Users/jonas/Entwicklung/pediment/.worktrees/check-updates-button`.

---

### Task 1: Core check logic (`pediment_update_checkers()` + `pediment_run_update_check()`)

**Files:**
- Create: `inc/update-check.php`
- Modify: `functions.php` (after line 36, `\Pediment\ThemeUpdater::register();`)
- Test: `tests/phpunit/UpdateCheck/UpdateCheckTest.php`

- [ ] **Step 1: Write the failing tests**

Create `tests/phpunit/UpdateCheck/UpdateCheckTest.php`:

```php
<?php

class UpdateCheckTest extends WP_UnitTestCase {

	public function tear_down() {
		delete_transient( 'pediment_update_check_' . get_current_user_id() );
		unset( $_REQUEST['_wpnonce'] );
		$GLOBALS['current_screen'] = null;
		parent::tear_down();
	}

	/**
	 * Fake PUC checker exposing exactly the API surface inc/update-check.php uses.
	 */
	private function fake_checker( $update = null, array $errors = array(), int $last_check = 0 ): object {
		return new class( $update, $errors, $last_check ) {
			public function __construct( private $update, private array $errors, private int $last_check ) {}

			public function checkForUpdates() {
				return $this->update;
			}

			public function getLastRequestApiErrors(): array {
				return $this->errors;
			}

			public function getUpdateState(): object {
				return new class( $this->last_check ) {
					public function __construct( private int $last_check ) {}

					public function getLastCheck(): int {
						return $this->last_check;
					}
				};
			}
		};
	}

	private function entry( object $checker ): array {
		return array(
			'slug'    => 'pediment',
			'name'    => 'Pediment',
			'checker' => $checker,
		);
	}

	private function register_fake_checker( $update = null, array $errors = array() ): void {
		$entry = $this->entry( $this->fake_checker( $update, $errors ) );
		add_filter(
			'pediment_update_checkers',
			static function ( array $checkers ) use ( $entry ): array {
				$checkers[] = $entry;
				return $checkers;
			}
		);
	}

	public function test_update_checkers_returns_empty_array_by_default() {
		$this->assertSame( array(), pediment_update_checkers() );
	}

	public function test_update_checkers_returns_registered_entries() {
		$this->register_fake_checker();
		$checkers = pediment_update_checkers();
		$this->assertCount( 1, $checkers );
		$this->assertSame( 'pediment', $checkers[0]['slug'] );
	}

	public function test_run_update_check_reports_available_update() {
		$result = pediment_run_update_check(
			$this->entry( $this->fake_checker( (object) array( 'version' => '9.9.9' ) ) )
		);
		$this->assertSame( 'update', $result['status'] );
		$this->assertSame( '9.9.9', $result['new_version'] );
		$this->assertSame( wp_get_theme( 'pediment' )->get( 'Version' ), $result['installed'] );
	}

	public function test_run_update_check_reports_up_to_date() {
		$result = pediment_run_update_check( $this->entry( $this->fake_checker( null, array() ) ) );
		$this->assertSame( 'current', $result['status'] );
		$this->assertSame( '', $result['new_version'] );
	}

	public function test_run_update_check_reports_error_when_api_failed() {
		$result = pediment_run_update_check(
			$this->entry( $this->fake_checker( null, array( array( 'error' => 'request failed' ) ) ) )
		);
		$this->assertSame( 'error', $result['status'] );
	}

	public function test_run_update_check_reports_error_when_checker_throws() {
		$checker = new class() {
			public function checkForUpdates() {
				throw new RuntimeException( 'network down' );
			}

			public function getLastRequestApiErrors(): array {
				return array();
			}
		};
		$result = pediment_run_update_check( $this->entry( $checker ) );
		$this->assertSame( 'error', $result['status'] );
	}
}
```

- [ ] **Step 2: Run tests to verify they fail**

```bash
npx wp-env run tests-wordpress --env-cwd=wp-content/themes/pediment "vendor/bin/phpunit --filter UpdateCheckTest"
```

Expected: ERRORS — `Call to undefined function pediment_update_checkers()`.

- [ ] **Step 3: Create `inc/update-check.php` with the two functions**

```php
<?php
/**
 * Manual "Check for theme updates" button on Dashboard → Updates.
 *
 * WordPress offers no per-theme re-check UI on the Updates screen, and Plugin
 * Update Checker's built-in manual-check link exists for plugins only. This
 * file renders a small section via the documented core_upgrade_preamble hook
 * and forces a cache-bypassing PUC check for every registered Pediment theme.
 *
 * @package Pediment
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Update checkers registered by the parent and (when active) child theme.
 *
 * Each entry: array{slug: string, name: string, checker: object}. Empty when
 * the updaters are disabled (local environments) or PUC is not installed.
 *
 * @return array[] Checker entries.
 */
function pediment_update_checkers(): array {
	$checkers = apply_filters( 'pediment_update_checkers', array() );
	if ( ! is_array( $checkers ) ) {
		return array();
	}
	return array_values( array_filter( $checkers, 'is_array' ) );
}

/**
 * Force one cache-bypassing update check and normalise the outcome.
 *
 * @param array $entry Checker entry from pediment_update_checkers().
 * @return array{name: string, status: string, installed: string, new_version: string}
 *               status is one of 'update', 'current', 'error'.
 */
function pediment_run_update_check( array $entry ): array {
	$theme  = wp_get_theme( $entry['slug'] );
	$result = array(
		'name'        => $entry['name'],
		'status'      => 'error',
		'installed'   => $theme->exists() ? (string) $theme->get( 'Version' ) : '',
		'new_version' => '',
	);

	try {
		$update = $entry['checker']->checkForUpdates();
		$errors = $entry['checker']->getLastRequestApiErrors();
	} catch ( \Throwable $e ) {
		return $result;
	}

	if ( null !== $update && isset( $update->version ) ) {
		$result['status']      = 'update';
		$result['new_version'] = (string) $update->version;
	} elseif ( array() === $errors ) {
		$result['status'] = 'current';
	}

	return $result;
}
```

- [ ] **Step 4: Load the file from `functions.php`**

In `functions.php`, directly after `\Pediment\ThemeUpdater::register();` (line 36), add:

```php
require_once __DIR__ . '/inc/update-check.php';
```

- [ ] **Step 5: Run tests to verify they pass**

```bash
npx wp-env run tests-wordpress --env-cwd=wp-content/themes/pediment "vendor/bin/phpunit --filter UpdateCheckTest"
```

Expected: 6 tests PASS.

- [ ] **Step 6: Commit**

```bash
git add inc/update-check.php functions.php tests/phpunit/UpdateCheck/UpdateCheckTest.php
git commit -m "feat(updates): core logic for manual theme update checks"
```

---

### Task 2: Render the section on Dashboard → Updates

**Files:**
- Modify: `inc/update-check.php` (append)
- Test: `tests/phpunit/UpdateCheck/UpdateCheckTest.php` (append methods)

- [ ] **Step 1: Write the failing tests** — append inside `UpdateCheckTest`:

```php
	public function test_section_renders_nothing_without_checkers() {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		ob_start();
		pediment_render_update_check_section();
		$this->assertSame( '', ob_get_clean() );
	}

	public function test_section_renders_nothing_without_capability() {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'subscriber' ) ) );
		$this->register_fake_checker();
		ob_start();
		pediment_render_update_check_section();
		$this->assertSame( '', ob_get_clean() );
	}

	public function test_section_renders_button_for_admins() {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		$this->register_fake_checker();
		ob_start();
		pediment_render_update_check_section();
		$html = ob_get_clean();
		$this->assertStringContainsString( 'pediment_check_theme_updates', $html );
		$this->assertStringContainsString( 'Pediment', $html );
		$this->assertStringContainsString( 'not checked yet', $html );
		$this->assertStringContainsString( 'admin-post.php', $html );
	}

	public function test_section_is_hooked_to_core_upgrade_preamble() {
		$this->assertNotFalse( has_action( 'core_upgrade_preamble', 'pediment_render_update_check_section' ) );
	}
```

- [ ] **Step 2: Run tests to verify they fail**

```bash
npx wp-env run tests-wordpress --env-cwd=wp-content/themes/pediment "vendor/bin/phpunit --filter UpdateCheckTest"
```

Expected: new tests ERROR — `Call to undefined function pediment_render_update_check_section()`.

- [ ] **Step 3: Append the renderer to `inc/update-check.php`**

```php
add_action( 'core_upgrade_preamble', 'pediment_render_update_check_section' );

/**
 * Render the "Theme Updates" section at the bottom of Dashboard → Updates.
 */
function pediment_render_update_check_section(): void {
	$checkers = pediment_update_checkers();
	if ( array() === $checkers || ! current_user_can( 'update_themes' ) ) {
		return;
	}

	echo '<h2>' . esc_html__( 'Theme Updates', 'pediment' ) . '</h2>';
	echo '<p>' . esc_html__( 'Check GitHub for new releases of the Pediment themes. Updates found here appear in the Themes list above.', 'pediment' ) . '</p>';
	echo '<ul>';
	foreach ( $checkers as $entry ) {
		$theme   = wp_get_theme( $entry['slug'] );
		$version = $theme->exists() ? (string) $theme->get( 'Version' ) : '';
		$last    = (int) $entry['checker']->getUpdateState()->getLastCheck();
		if ( $last > 0 ) {
			/* translators: %s: human-readable time difference, e.g. "3 hours". */
			$checked = sprintf( __( 'last checked %s ago', 'pediment' ), human_time_diff( $last ) );
		} else {
			$checked = __( 'not checked yet', 'pediment' );
		}
		echo '<li><strong>' . esc_html( $entry['name'] ) . '</strong> ' . esc_html( $version ) . ' (' . esc_html( $checked ) . ')</li>';
	}
	echo '</ul>';

	echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
	echo '<input type="hidden" name="action" value="pediment_check_theme_updates" />';
	wp_nonce_field( 'pediment_check_theme_updates' );
	submit_button( __( 'Check for theme updates', 'pediment' ), 'secondary', 'pediment-check-updates', false );
	echo '</form>';
}
```

- [ ] **Step 4: Run tests to verify they pass**

```bash
npx wp-env run tests-wordpress --env-cwd=wp-content/themes/pediment "vendor/bin/phpunit --filter UpdateCheckTest"
```

Expected: 10 tests PASS.

- [ ] **Step 5: Commit**

```bash
git add inc/update-check.php tests/phpunit/UpdateCheck/UpdateCheckTest.php
git commit -m "feat(updates): render check-for-updates section on Updates screen"
```

---

### Task 3: Button handler (guards, check, transient, redirect)

**Files:**
- Modify: `inc/update-check.php` (append)
- Test: `tests/phpunit/UpdateCheck/UpdateCheckTest.php` (append methods)

The handler splits into a testable storage function and a thin hook callback (the `wp_safe_redirect()` + `exit` tail is not unit-testable; guards are tested via `WPDieException`).

- [ ] **Step 1: Write the failing tests** — append inside `UpdateCheckTest`:

```php
	public function test_store_results_writes_transient_for_current_user() {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		$this->register_fake_checker( (object) array( 'version' => '9.9.9' ) );
		pediment_store_update_check_results();
		$results = get_transient( 'pediment_update_check_' . get_current_user_id() );
		$this->assertIsArray( $results );
		$this->assertCount( 1, $results );
		$this->assertSame( 'update', $results[0]['status'] );
		$this->assertSame( '9.9.9', $results[0]['new_version'] );
	}

	public function test_store_results_writes_no_transient_without_checkers() {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		pediment_store_update_check_results();
		$this->assertFalse( get_transient( 'pediment_update_check_' . get_current_user_id() ) );
	}

	public function test_handler_dies_on_invalid_nonce() {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		$_REQUEST['_wpnonce'] = 'invalid';
		$this->expectException( 'WPDieException' );
		pediment_handle_update_check();
	}

	public function test_handler_dies_without_capability() {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'subscriber' ) ) );
		$_REQUEST['_wpnonce'] = wp_create_nonce( 'pediment_check_theme_updates' );
		$this->expectException( 'WPDieException' );
		pediment_handle_update_check();
	}

	public function test_handler_is_hooked_to_admin_post() {
		$this->assertNotFalse( has_action( 'admin_post_pediment_check_theme_updates', 'pediment_handle_update_check' ) );
	}
```

- [ ] **Step 2: Run tests to verify they fail**

```bash
npx wp-env run tests-wordpress --env-cwd=wp-content/themes/pediment "vendor/bin/phpunit --filter UpdateCheckTest"
```

Expected: new tests ERROR — `Call to undefined function pediment_store_update_check_results()`.

- [ ] **Step 3: Append the handler to `inc/update-check.php`**

```php
/**
 * Run every registered checker and stash per-theme results for the notice.
 */
function pediment_store_update_check_results(): void {
	$results = array();
	foreach ( pediment_update_checkers() as $entry ) {
		$results[] = pediment_run_update_check( $entry );
	}
	if ( array() !== $results ) {
		set_transient( 'pediment_update_check_' . get_current_user_id(), $results, MINUTE_IN_SECONDS );
	}
}

add_action( 'admin_post_pediment_check_theme_updates', 'pediment_handle_update_check' );

/**
 * Handle the button submission: verify intent, check, redirect back.
 */
function pediment_handle_update_check(): void {
	check_admin_referer( 'pediment_check_theme_updates' );
	if ( ! current_user_can( 'update_themes' ) ) {
		wp_die( esc_html__( 'Sorry, you are not allowed to update themes for this site.', 'pediment' ) );
	}

	pediment_store_update_check_results();

	wp_safe_redirect( self_admin_url( 'update-core.php' ) );
	exit;
}
```

- [ ] **Step 4: Run tests to verify they pass**

```bash
npx wp-env run tests-wordpress --env-cwd=wp-content/themes/pediment "vendor/bin/phpunit --filter UpdateCheckTest"
```

Expected: 15 tests PASS.

- [ ] **Step 5: Commit**

```bash
git add inc/update-check.php tests/phpunit/UpdateCheck/UpdateCheckTest.php
git commit -m "feat(updates): admin-post handler for manual update checks"
```

---

### Task 4: One-shot result notice on the Updates screen

**Files:**
- Modify: `inc/update-check.php` (append)
- Test: `tests/phpunit/UpdateCheck/UpdateCheckTest.php` (append methods)

- [ ] **Step 1: Write the failing tests** — append inside `UpdateCheckTest`:

```php
	private function seed_results( array $results ): void {
		set_transient( 'pediment_update_check_' . get_current_user_id(), $results, MINUTE_IN_SECONDS );
	}

	public function test_notice_renders_results_once_on_update_core_screen() {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		set_current_screen( 'update-core' );
		$this->seed_results(
			array(
				array(
					'name'        => 'Pediment',
					'status'      => 'update',
					'installed'   => '0.3.0',
					'new_version' => '9.9.9',
				),
				array(
					'name'        => 'Pediment Child',
					'status'      => 'current',
					'installed'   => '0.2.1',
					'new_version' => '',
				),
			)
		);
		ob_start();
		pediment_update_check_notice();
		$html = ob_get_clean();
		$this->assertStringContainsString( 'update 9.9.9 available', $html );
		$this->assertStringContainsString( 'up to date (0.2.1)', $html );
		$this->assertStringContainsString( 'notice-success', $html );
		$this->assertFalse( get_transient( 'pediment_update_check_' . get_current_user_id() ), 'Transient must be deleted after rendering.' );
	}

	public function test_notice_uses_warning_class_when_a_check_failed() {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		set_current_screen( 'update-core' );
		$this->seed_results(
			array(
				array(
					'name'        => 'Pediment',
					'status'      => 'error',
					'installed'   => '0.3.0',
					'new_version' => '',
				),
			)
		);
		ob_start();
		pediment_update_check_notice();
		$html = ob_get_clean();
		$this->assertStringContainsString( 'update check failed', $html );
		$this->assertStringContainsString( 'notice-warning', $html );
	}

	public function test_notice_is_silent_on_other_screens_and_keeps_transient() {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		set_current_screen( 'dashboard' );
		$this->seed_results(
			array(
				array(
					'name'        => 'Pediment',
					'status'      => 'current',
					'installed'   => '0.3.0',
					'new_version' => '',
				),
			)
		);
		ob_start();
		pediment_update_check_notice();
		$this->assertSame( '', ob_get_clean() );
		$this->assertIsArray( get_transient( 'pediment_update_check_' . get_current_user_id() ) );
	}

	public function test_notice_is_hooked_to_admin_notices() {
		$this->assertNotFalse( has_action( 'admin_notices', 'pediment_update_check_notice' ) );
	}
```

- [ ] **Step 2: Run tests to verify they fail**

```bash
npx wp-env run tests-wordpress --env-cwd=wp-content/themes/pediment "vendor/bin/phpunit --filter UpdateCheckTest"
```

Expected: new tests ERROR — `Call to undefined function pediment_update_check_notice()`.

- [ ] **Step 3: Append the notice to `inc/update-check.php`**

```php
add_action( 'admin_notices', 'pediment_update_check_notice' );

/**
 * Show the stored per-theme results once, on the Updates screen only.
 */
function pediment_update_check_notice(): void {
	$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
	if ( null === $screen || 'update-core' !== $screen->id ) {
		return;
	}

	$key     = 'pediment_update_check_' . get_current_user_id();
	$results = get_transient( $key );
	if ( ! is_array( $results ) || array() === $results ) {
		return;
	}
	delete_transient( $key );

	$lines     = array();
	$has_error = false;
	foreach ( $results as $result ) {
		if ( 'update' === $result['status'] ) {
			/* translators: 1: theme name, 2: new version number. */
			$lines[] = sprintf( __( '%1$s: update %2$s available.', 'pediment' ), $result['name'], $result['new_version'] );
		} elseif ( 'current' === $result['status'] ) {
			/* translators: 1: theme name, 2: installed version number. */
			$lines[] = sprintf( __( '%1$s: up to date (%2$s).', 'pediment' ), $result['name'], $result['installed'] );
		} else {
			$has_error = true;
			/* translators: %s: theme name. */
			$lines[] = sprintf( __( '%s: update check failed — could not reach the update server.', 'pediment' ), $result['name'] );
		}
	}

	echo '<div class="notice ' . ( $has_error ? 'notice-warning' : 'notice-success' ) . ' is-dismissible"><p>';
	echo wp_kses( implode( '<br />', array_map( 'esc_html', $lines ) ), array( 'br' => array() ) );
	echo '</p></div>';
}
```

- [ ] **Step 4: Run tests to verify they pass**

```bash
npx wp-env run tests-wordpress --env-cwd=wp-content/themes/pediment "vendor/bin/phpunit --filter UpdateCheckTest"
```

Expected: 19 tests PASS.

- [ ] **Step 5: Commit**

```bash
git add inc/update-check.php tests/phpunit/UpdateCheck/UpdateCheckTest.php
git commit -m "feat(updates): admin notice reporting manual check results"
```

---

### Task 5: Register the parent theme's checker in the filter

**Files:**
- Modify: `inc/ThemeUpdater.php:54-62` (parent theme)

No unit test: `ThemeUpdater::register()` exits early in the test environment (no PUC class, `local` environment type), so this wiring is only exercised on production-like installs. The consumer side is fully covered by Tasks 1–4 via filter injection.

- [ ] **Step 1: Append the filter registration**

In the parent `inc/ThemeUpdater.php`, at the end of `register()` (after the `enableReleaseAssets` block, currently lines 58–61), add:

```php
		// Expose the checker so inc/update-check.php can offer a manual
		// "Check for theme updates" button on Dashboard → Updates.
		add_filter(
			'pediment_update_checkers',
			static function ( array $checkers ) use ( $checker ): array {
				$checkers[] = array(
					'slug'    => 'pediment',
					'name'    => 'Pediment',
					'checker' => $checker,
				);
				return $checkers;
			}
		);
```

- [ ] **Step 2: Run the full suite as a regression check**

```bash
npx wp-env run tests-wordpress --env-cwd=wp-content/themes/pediment vendor/bin/phpunit
```

Expected: all tests PASS.

- [ ] **Step 3: Commit**

```bash
git add inc/ThemeUpdater.php
git commit -m "feat(updates): expose parent PUC checker via pediment_update_checkers"
```

---

### Task 6: Register the child theme's checker (child repo)

**Files:**
- Modify: `/Users/jonas/Entwicklung/pediment-child-theme/inc/ThemeUpdater.php:59-62`

Single-file change in the child repo, done directly on its `development` branch (no worktree). The child has no PHPUnit suite; its gate is phpcs.

- [ ] **Step 1: Append the filter registration**

In the child `inc/ThemeUpdater.php`, at the end of `register()` (after the `enableReleaseAssets` block, currently lines 59–62), add:

```php
		// Expose the checker so the parent theme's inc/update-check.php can
		// include the child in its manual "Check for theme updates" button.
		add_filter(
			'pediment_update_checkers',
			static function ( array $checkers ) use ( $checker ): array {
				$checkers[] = array(
					'slug'    => 'pediment-child-theme',
					'name'    => 'Pediment Child',
					'checker' => $checker,
				);
				return $checkers;
			}
		);
```

- [ ] **Step 2: Lint**

```bash
cd /Users/jonas/Entwicklung/pediment-child-theme
composer lint
```

Expected: no errors, no warnings.

- [ ] **Step 3: Commit**

```bash
git add inc/ThemeUpdater.php
git commit -m "feat(updates): expose child PUC checker via pediment_update_checkers"
```

---

### Task 7: Final verification and merge

- [ ] **Step 1: Full test suite + lint gates in the parent worktree** (CI runs `lint:colors` and phpcs; phpcs fails on warnings)

```bash
cd /Users/jonas/Entwicklung/pediment/.worktrees/check-updates-button
npx wp-env run tests-wordpress --env-cwd=wp-content/themes/pediment vendor/bin/phpunit
composer lint
npm run lint:colors
```

Expected: all PASS / clean.

- [ ] **Step 2: Stop the worktree's wp-env**

```bash
npm run env:stop
```

- [ ] **Step 3: Merge back to `development` and clean up** — use the `superpowers:finishing-a-development-branch` skill. Rebase the worktree branch onto `development` first if `development` moved; delete the worktree after merging.

- [ ] **Step 4: Manual verification note (post-release)** — the real GitHub round-trip can only be confirmed on a production-type install. Either temporarily add `"WP_ENVIRONMENT_TYPE": "production"` to a scratch wp-env config, or verify on a client site after the next parent + child releases: Dashboard → Updates shows the "Theme Updates" section, the button reports per-theme results, and a found update appears in the Themes table above.
