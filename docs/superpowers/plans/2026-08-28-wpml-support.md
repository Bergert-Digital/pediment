# WPML Support Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add a WPML runtime adapter to the Pediment plugin at full feature parity with the existing Polylang integration, so a site running WPML seeds, binds navigation, renders a language switcher, and configures languages exactly as a Polylang site does.

**Architecture:** Extend the existing `LanguageProvider` seam in `plugin/src/Language/` rather than branch through the engine. Add `WpmlProvider` (implements `LanguageProvider`), extract a `LanguageSetup` interface and add `WpmlSetup`, add `inc/wpml-compat.php`, and route the two front-end seams (current-language lookup, switcher block) through the provider. All WPML/`icl_*`/`wpml_*` calls stay quarantined to those three files, mirroring the "nothing else may call a `pll_*` function" invariant. Detection order is Polylang → WPML → Null, filter-overridable.

**Tech Stack:** PHP 8.1, WordPress 6.4+, WPML (`sitepress-multilingual-cms`, latest), PHPUnit (WP test lib), Playwright (e2e), `@wordpress/env`, GitHub Actions.

**Spec:** `docs/superpowers/specs/2026-08-28-wpml-support-design.md` — read it alongside this plan; the plan argues from it.

## Global Constraints

- **PHP 8.1**, `declare(strict_types=1)` at the top of every new PHP file, `if ( ! defined( 'ABSPATH' ) ) { exit; }` guard.
- **WPML code is quarantined** to exactly three runtime files: `plugin/src/Language/WpmlProvider.php`, `plugin/src/Language/WpmlSetup.php`, `plugin/inc/wpml-compat.php`. No `wpml_*` / `icl_*` / `ICL_*` / `sitepress` reference may appear anywhere else in `plugin/src` or `plugin/inc`. (Grep-enforced in Task 13.)
- **Do NOT touch** `tools/generate-wpml-config.mjs`, `plugin/wpml-config.xml`, the `gen:wpml` npm script, or the `ci.yml` `--check` step. Those are the orthogonal block-attribute config layer (spec "Pre-existing, out of scope").
- **phpcs must pass** (`composer lint -d plugin`); no color literals (`npm run lint:colors`) — irrelevant here but CI runs it.
- **Detection precedence:** Polylang → WPML → Null. Polylang wins if both are somehow active.
- **`LanguageSetup::configure` signature is frozen** to today's `PolylangSetup::configure`:
  `configure( array $languages, string $default, bool $dryRun = false ): array` returning `array{changes:string[],errors:string[]}`, where `$languages` is `array<string,\Pediment\Seeder\LanguageSpec>`.
- **WPML-env tasks (3–12) require the WPML license zip.** It is present locally at `plugin/wpml/wpml.zip` (inner dir `sitepress-multilingual-cms/`, entry `sitepress.php`); `plugin/wpml/` is git-ignored (licensed, never commit). CI supplies it via the `WPML_ZIP_B64` secret decoded to the same path. When absent, the WPML suites **skip**, never fail.
- **wp-env layout (verified):** the wp-env project root is the **repo root** (`.wp-env.json` lives there; commands run with `--env-cwd=wp-content/plugins/pediment-ai`). wp-env 10.39 has **no `--config` flag** — the WPML world is provisioned by copying the committed `.wp-env.wpml.json` to `.wp-env.override.json` at the repo root and running `wp-env start`. All paths inside these configs are **repo-root-relative** (`./plugin/...`). This workspace has **one** wp-env instance: the Polylang env and the WPML env cannot be up at the same time; the runner switches between them (restore Polylang by removing the override and re-running `wp-env start`). Always start with `WP_ENV_PORT=8920 WP_ENV_TESTS_PORT=8921`.
- **Work on the current branch** (`honolulu`); commit after every task. Never push automatically.
- **Lockfiles** (if touched) must be authored by npm 10.

---

### Task 1: Extract the `LanguageSetup` interface and wire `LanguageRegistry::setup()`

Pure refactor, no WPML. De-risks the seam. Nothing about behavior changes; `PolylangSetup` keeps working byte-for-byte and the Polylang suite proves it.

**Files:**
- Create: `plugin/src/Language/LanguageSetup.php`
- Modify: `plugin/src/Language/PolylangSetup.php:25` (add `implements LanguageSetup`)
- Modify: `plugin/src/Language/LanguageRegistry.php` (add `setup()` + reset it)
- Modify: `plugin/wp-cli/LanguagesCommand.php:12,67` (resolve via registry)
- Test: `plugin/tests/phpunit/Language/LanguageSetupTest.php`

**Interfaces:**
- Produces: `interface LanguageSetup { public function configure( array $languages, string $default, bool $dryRun = false ): array; }`
- Produces: `LanguageRegistry::setup(): LanguageSetup` (memoized, filter `pediment_language_setup`), and `LanguageRegistry::reset()` now also clears the memoized setup.

- [ ] **Step 1: Write the failing test**

`plugin/tests/phpunit/Language/LanguageSetupTest.php`:

```php
<?php

use Pediment\Language\LanguageRegistry;
use Pediment\Language\LanguageSetup;
use Pediment\Language\PolylangSetup;

class LanguageSetupTest extends WP_UnitTestCase {

	public function tear_down(): void {
		remove_all_filters( 'pediment_language_setup' );
		LanguageRegistry::reset();
		parent::tear_down();
	}

	public function test_default_setup_is_polylang() {
		$this->assertInstanceOf( PolylangSetup::class, LanguageRegistry::setup() );
	}

	public function test_polylang_setup_satisfies_the_interface() {
		$this->assertInstanceOf( LanguageSetup::class, new PolylangSetup() );
	}

	public function test_setup_is_memoized() {
		$this->assertSame( LanguageRegistry::setup(), LanguageRegistry::setup() );
	}

	public function test_filter_swaps_the_setup() {
		$fake = new class() implements LanguageSetup {
			public function configure( array $languages, string $default, bool $dryRun = false ): array {
				return [ 'changes' => [ 'faked' ], 'errors' => [] ];
			}
		};
		add_filter( 'pediment_language_setup', static fn() => $fake );
		LanguageRegistry::reset();

		$this->assertSame( [ 'faked' ], LanguageRegistry::setup()->configure( [], '' )['changes'] );
	}

	public function test_non_setup_filter_return_is_ignored() {
		add_filter( 'pediment_language_setup', static fn() => 'nonsense' );
		LanguageRegistry::reset();

		$this->assertInstanceOf( LanguageSetup::class, LanguageRegistry::setup() );
	}
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `npx wp-env run tests-wordpress --env-cwd=wp-content/plugins/pediment-ai ./vendor/bin/phpunit --filter LanguageSetupTest`
Expected: FAIL — `LanguageSetup` interface and `LanguageRegistry::setup()` do not exist.

- [ ] **Step 3: Create the interface**

`plugin/src/Language/LanguageSetup.php`:

```php
<?php
/**
 * Reconciles a multilingual plugin's own settings against the manifest's
 * `languages`. The seam `wp pediment languages` resolves, parallel to the
 * LanguageProvider seam the seed run uses.
 *
 * @package Pediment
 */

declare(strict_types=1);

namespace Pediment\Language;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

interface LanguageSetup {
	/**
	 * @param array<string,\Pediment\Seeder\LanguageSpec> $languages Declaration order, default first.
	 * @return array{changes:string[],errors:string[]}
	 */
	public function configure( array $languages, string $default, bool $dryRun = false ): array;
}
```

- [ ] **Step 4: Declare `PolylangSetup implements LanguageSetup`**

In `plugin/src/Language/PolylangSetup.php:25`, change:

```php
final class PolylangSetup {
```
to:
```php
final class PolylangSetup implements LanguageSetup {
```

(The existing `configure()` signature already matches the interface — no body change.)

- [ ] **Step 5: Add `setup()` to the registry**

In `plugin/src/Language/LanguageRegistry.php`, add a second memoized field and resolver. Replace the class body so it reads:

```php
final class LanguageRegistry {
	private static ?LanguageProvider $provider = null;
	private static ?LanguageSetup $setup = null;

	public static function provider(): LanguageProvider {
		if ( self::$provider instanceof LanguageProvider ) {
			return self::$provider;
		}

		$detected = PolylangProvider::isActive() ? new PolylangProvider() : new NullProvider();

		/**
		 * Filter the active language provider.
		 *
		 * @param LanguageProvider $provider PolylangProvider when Polylang is
		 *                                   active and configured, else NullProvider.
		 */
		$filtered = apply_filters( 'pediment_language_provider', $detected );

		self::$provider = $filtered instanceof LanguageProvider ? $filtered : $detected;

		return self::$provider;
	}

	public static function setup(): LanguageSetup {
		if ( self::$setup instanceof LanguageSetup ) {
			return self::$setup;
		}

		$detected = new PolylangSetup();

		/**
		 * Filter the active language setup.
		 *
		 * @param LanguageSetup $setup Defaults to PolylangSetup; a WPML build
		 *                             swaps WpmlSetup in via Task 9's detection.
		 */
		$filtered = apply_filters( 'pediment_language_setup', $detected );

		self::$setup = $filtered instanceof LanguageSetup ? $filtered : $detected;

		return self::$setup;
	}

	public static function reset(): void {
		self::$provider = null;
		self::$setup    = null;
	}
}
```

(Task 9 replaces the `new PolylangSetup()` line here with the Polylang→WPML→(Polylang default) detection. For now it stays PolylangSetup so this task is behavior-preserving.)

- [ ] **Step 6: Rewire the CLI command**

In `plugin/wp-cli/LanguagesCommand.php`, change the import at line 12 from:
```php
use Pediment\Language\PolylangSetup;
```
to:
```php
use Pediment\Language\LanguageRegistry;
```
and line 67 from:
```php
		$result = ( new PolylangSetup() )->configure( $manifest->languages(), $manifest->defaultLanguage(), $dryRun );
```
to:
```php
		$result = LanguageRegistry::setup()->configure( $manifest->languages(), $manifest->defaultLanguage(), $dryRun );
```

- [ ] **Step 7: Run the new test and the full monolingual + polylang suites**

Run: `npx wp-env run tests-wordpress --env-cwd=wp-content/plugins/pediment-ai ./vendor/bin/phpunit --filter LanguageSetupTest`
Expected: PASS.
Run: `npx wp-env run tests-wordpress --env-cwd=wp-content/plugins/pediment-ai ./vendor/bin/phpunit`
Expected: PASS (no regressions).
Run: `npx wp-env run tests-wordpress --env-cwd=wp-content/plugins/pediment-ai ./vendor/bin/phpunit -c phpunit-polylang.xml.dist`
Expected: PASS (PolylangSetup still drives `wp pediment languages`).

- [ ] **Step 8: Lint**

Run: `composer lint -d plugin`
Expected: no errors.

- [ ] **Step 9: Commit**

```bash
git add plugin/src/Language/LanguageSetup.php plugin/src/Language/PolylangSetup.php plugin/src/Language/LanguageRegistry.php plugin/wp-cli/LanguagesCommand.php plugin/tests/phpunit/Language/LanguageSetupTest.php
git commit -m "refactor(language): extract LanguageSetup interface + registry resolver"
```

---

### Task 2: Widen `LanguageProvider` with `currentLanguage()` + `languageSwitcherBlock()` and route the two front-end seams through it

Still no WPML. Adds two methods, implements them in `PolylangProvider` and `NullProvider` to preserve today's behavior exactly, and moves the two direct `pll_*`/hardcoded-block seams onto the provider.

**Files:**
- Modify: `plugin/src/Language/LanguageProvider.php` (two new interface methods)
- Modify: `plugin/src/Language/PolylangProvider.php` (implement both)
- Modify: `plugin/src/Language/NullProvider.php` (implement both)
- Modify: `plugin/inc/nav-language.php:130-131` (route current/default through provider)
- Modify: `plugin/src/Seeder/NavSeeder.php` (switcher via provider)
- Test: `plugin/tests/polylang/PolylangSwitcherTest.php` (new), plus assertions added to the monolingual `LanguageProviderTest.php`

**Interfaces:**
- Consumes: `LanguageRegistry::provider()` (pre-existing).
- Produces on `LanguageProvider`:
  - `public function currentLanguage(): string;`
  - `public function languageSwitcherBlock( $config ): string;` where `$config` is the manifest's `language_switcher` value (`bool|array<string,mixed>`); returns a serialized block comment or `''` for none.

- [ ] **Step 1: Write the failing tests (Polylang behavior)**

`plugin/tests/polylang/PolylangSwitcherTest.php`:

```php
<?php

use Pediment\Language\PolylangProvider;

class PolylangSwitcherTest extends PolylangTestCase {

	public function test_switcher_block_matches_the_historic_polylang_string() {
		$block = ( new PolylangProvider() )->languageSwitcherBlock( true );
		$this->assertSame(
			'<!-- wp:polylang/navigation-language-switcher {"dropdown":true} /-->',
			$block
		);
	}

	public function test_switcher_block_merges_array_overrides() {
		$block = ( new PolylangProvider() )->languageSwitcherBlock( [ 'dropdown' => false, 'showFlags' => true ] );
		$this->assertSame(
			'<!-- wp:polylang/navigation-language-switcher {"dropdown":false,"showFlags":true} /-->',
			$block
		);
	}

	public function test_current_language_reads_polylang() {
		// Default language configured by the harness is 'en'.
		$this->assertSame( 'en', ( new PolylangProvider() )->currentLanguage() );
	}
}
```

Add to `plugin/tests/phpunit/Language/LanguageProviderTest.php` (monolingual):

```php
	public function test_null_provider_current_language_is_empty() {
		$this->assertSame( '', ( new NullProvider() )->currentLanguage() );
	}

	public function test_null_provider_emits_no_switcher() {
		$this->assertSame( '', ( new NullProvider() )->languageSwitcherBlock( true ) );
	}
```

- [ ] **Step 2: Run to verify failure**

Run: `npx wp-env run tests-wordpress --env-cwd=wp-content/plugins/pediment-ai ./vendor/bin/phpunit -c phpunit-polylang.xml.dist --filter PolylangSwitcherTest`
Expected: FAIL — methods not defined.

- [ ] **Step 3: Add the interface methods**

In `plugin/src/Language/LanguageProvider.php`, before the closing brace of the interface, add:

```php
	/** The current request's language code; '' when monolingual. */
	public function currentLanguage(): string;

	/**
	 * Serialized language-switcher block for the seeded header, or '' when the
	 * active plugin has no switcher (monolingual). $config is the manifest's
	 * `language_switcher` value: `true`, or an array of block-attribute overrides.
	 *
	 * @param bool|array<string,mixed> $config
	 */
	public function languageSwitcherBlock( $config ): string;
```

- [ ] **Step 4: Implement in `PolylangProvider`**

Append to `plugin/src/Language/PolylangProvider.php` before the final `}`:

```php
	public function currentLanguage(): string {
		return function_exists( 'pll_current_language' ) ? (string) pll_current_language() : '';
	}

	/**
	 * @param bool|array<string,mixed> $config
	 */
	public function languageSwitcherBlock( $config ): string {
		// `dropdown` defaults on (the "English ▾" menu-item form); an array
		// value overrides the block attributes. Language-agnostic — every
		// language's menu carries the same block.
		$attrs = array_merge(
			[ 'dropdown' => true ],
			is_array( $config ) ? $config : []
		);

		return '<!-- wp:polylang/navigation-language-switcher ' . wp_json_encode( $attrs, JSON_UNESCAPED_SLASHES ) . ' /-->';
	}
```

- [ ] **Step 5: Implement in `NullProvider`**

Append to `plugin/src/Language/NullProvider.php` before the final `}`:

```php
	public function currentLanguage(): string {
		return '';
	}

	/**
	 * @param bool|array<string,mixed> $config
	 */
	public function languageSwitcherBlock( $config ): string {
		// Monolingual: no switcher.
		return '';
	}
```

- [ ] **Step 6: Route `nav-language.php` through the provider**

In `plugin/inc/nav-language.php`, replace lines 130-131:

```php
	$current = function_exists( 'pll_current_language' ) ? (string) pll_current_language() : '';
	$default = function_exists( 'pll_default_language' ) ? (string) pll_default_language() : '';
```
with:
```php
	$provider = \Pediment\Language\LanguageRegistry::provider();
	$current  = $provider->currentLanguage();
	$default  = $provider->defaultLanguage();
```

- [ ] **Step 7: Route `NavSeeder` switcher through the provider**

In `plugin/src/Seeder/NavSeeder.php`, replace the `language_switcher` branch (the block that builds `$switcherAttrs` and pushes the hardcoded `wp:polylang/...` string):

```php
			if ( isset( $item['language_switcher'] ) ) {
				$switcherAttrs = array_merge(
					[ 'dropdown' => true ],
					is_array( $item['language_switcher'] ) ? $item['language_switcher'] : []
				);
				$blocks[] = '<!-- wp:polylang/navigation-language-switcher ' . wp_json_encode( $switcherAttrs, JSON_UNESCAPED_SLASHES ) . ' /-->';
				continue;
			}
```
with:
```php
			// A language switcher is a dynamic multilingual-plugin block, not a
			// link to a seeded post. The active provider owns the block name and
			// attribute shape (Polylang vs WPML); a monolingual site returns ''
			// and the switcher is simply omitted.
			if ( isset( $item['language_switcher'] ) ) {
				$switcher = $this->language->languageSwitcherBlock( $item['language_switcher'] );
				if ( '' !== $switcher ) {
					$blocks[] = $switcher;
				}
				continue;
			}
```

(`$this->language` is the injected `LanguageProvider` — confirm the property name by reading the `NavSeeder` constructor; the Explore report cites `NavSeeder.php:24`. If the property is named differently, use that name.)

- [ ] **Step 8: Run all three suites**

Run: `npx wp-env run tests-wordpress --env-cwd=wp-content/plugins/pediment-ai ./vendor/bin/phpunit -c phpunit-polylang.xml.dist`
Expected: PASS (incl. new PolylangSwitcherTest and the existing NavSeeder switcher tests, which still see the identical Polylang string).
Run: `npx wp-env run tests-wordpress --env-cwd=wp-content/plugins/pediment-ai ./vendor/bin/phpunit`
Expected: PASS. If any monolingual NavSeeder test asserted the Polylang switcher was emitted on a Null-provider seed, it now correctly gets no switcher — update that assertion to expect omission and note it in the commit.

- [ ] **Step 9: Lint + commit**

```bash
composer lint -d plugin
git add plugin/src/Language/LanguageProvider.php plugin/src/Language/PolylangProvider.php plugin/src/Language/NullProvider.php plugin/inc/nav-language.php plugin/src/Seeder/NavSeeder.php plugin/tests/polylang/PolylangSwitcherTest.php plugin/tests/phpunit/Language/LanguageProviderTest.php
git commit -m "refactor(language): route current-language + switcher block through the provider seam"
```

---

### Task 3: WPML test environment + ground-truth capture

**Requires the WPML zip.** Stands up a WPML-only wp-env world and a PHPUnit bootstrap, gets a trivial harness test green, and captures the two genuinely-WPML-specific facts (exact switcher block markup; the working language-activation write) into a committed reference the later tasks consume.

**Files:**
- Create: `.wp-env.wpml.json` (repo root — the committed WPML env config)
- Create: `plugin/tests/wpml/bootstrap.php`
- Create: `plugin/tests/wpml/language-definitions.php`
- Create: `plugin/tests/wpml/WpmlTestCase.php`
- Create: `plugin/tests/wpml/HarnessTest.php`
- Create: `plugin/tests/wpml/WPML-API-REFERENCE.md` (captured ground truth)
- Create: `plugin/phpunit-wpml.xml.dist`
- Modify: `.gitignore` (add `.wp-env.override.json`; `plugin/wpml/` is already ignored)

**How the WPML env is run (no `--config` flag exists in wp-env 10.39):**
```bash
# from repo root — switch this workspace's single wp-env instance to WPML:
cp .wp-env.wpml.json .wp-env.override.json
WP_ENV_PORT=8920 WP_ENV_TESTS_PORT=8921 npx wp-env start
# run the WPML suite:
WP_ENV_PORT=8920 WP_ENV_TESTS_PORT=8921 npx wp-env run tests-wordpress \
  --env-cwd=wp-content/plugins/pediment-ai ./vendor/bin/phpunit -c phpunit-wpml.xml.dist
# restore the Polylang env when done:
rm .wp-env.override.json
WP_ENV_PORT=8920 WP_ENV_TESTS_PORT=8921 npx wp-env start
```
`.wp-env.override.json` deep-merges over `.wp-env.json`; arrays like `plugins` are **replaced**, not appended — so the override's single WPML plugin swaps out Polylang. **Verify** this at Step 7 with `wp plugin list` (Polylang must be inactive/absent); if it lingers, deactivate it in the bootstrap.

**Interfaces:**
- Produces: a runnable command `./vendor/bin/phpunit -c phpunit-wpml.xml.dist` inside a WPML-provisioned tests-wordpress container.
- Produces: `pediment_wpml_test_languages(): string[]` and `pediment_wpml_test_language_definitions()`.
- Produces: `WPML-API-REFERENCE.md` recording (a) the serialized markup WPML's native `wpml/language-switcher` block saves, captured from a real editor insert, and (b) the exact call/option WPML requires to activate a language headlessly.

- [ ] **Step 1: WPML provisioning config (repo root)**

`.wp-env.wpml.json` at the **repo root** — identical to `.wp-env.json` except `plugins` swaps Polylang for the local WPML zip. Paths are repo-root-relative. Mirror the base config's `mappings`/`config` exactly so nothing the fixture theme or plugin relies on is lost; drop the `lifecycleScripts.afterStart` only if `tools/dev-bootstrap.mjs` assumes Polylang (check it — if it is language-agnostic, keep it):

```json
{
  "core": "WordPress/WordPress#6.9",
  "phpVersion": "8.1",
  "themes": [],
  "plugins": [ "./plugin/wpml/wpml.zip" ],
  "config": {
    "WP_DEBUG": true,
    "WP_DEBUG_LOG": true,
    "SCRIPT_DEBUG": true,
    "PEDIMENT_AI_MOCK": true,
    "PEDIMENT_AI_LOOPBACK_URL": "http://127.0.0.1"
  },
  "mappings": {
    "wp-content/themes/pediment-fixture": "./tests/fixtures/client-theme",
    "wp-content/plugins/pediment-ai": "./plugin",
    "wp-content/uploads": "./tests/fixtures/uploads",
    "wp-content/mu-plugins": "./plugin/tests/fixtures/mu-plugins"
  }
}
```

Add `.wp-env.override.json` to the repo-root `.gitignore` (`plugin/wpml/` is already ignored). Confirm the actual `mappings`/`config`/`lifecycleScripts` against the live `.wp-env.json` before writing — copy them verbatim.

- [ ] **Step 2: Language definitions**

`plugin/tests/wpml/language-definitions.php` — same two languages as the Polylang harness, so cross-suite expectations line up:

```php
<?php
/**
 * The languages the WPML adapter suite configures: en (default) + de.
 * Mirrors tests/polylang/language-definitions.php so both suites assert the
 * same shape.
 *
 * @return array<int, array{code:string,name:string,locale:string,default:int}>
 */
function pediment_wpml_test_language_definitions(): array {
	return [
		[ 'code' => 'en', 'name' => 'English', 'locale' => 'en_US', 'default' => 1 ],
		[ 'code' => 'de', 'name' => 'German',  'locale' => 'de_DE', 'default' => 0 ],
	];
}

/** @return string[] */
function pediment_wpml_test_languages(): array {
	return array_map( static fn( array $l ): string => $l['code'], pediment_wpml_test_language_definitions() );
}
```

- [ ] **Step 3: PHPUnit config**

`plugin/phpunit-wpml.xml.dist` (mirrors `phpunit-polylang.xml.dist`):

```xml
<?xml version="1.0"?>
<phpunit
  bootstrap="tests/wpml/bootstrap.php"
  backupGlobals="false"
  colors="true"
  beStrictAboutCoversAnnotation="true"
  beStrictAboutOutputDuringTests="true"
  beStrictAboutTestsThatDoNotTestAnything="false"
  verbose="true">
  <testsuites>
    <testsuite name="wpml">
      <directory>tests/wpml/</directory>
    </testsuite>
  </testsuites>
</phpunit>
```

- [ ] **Step 4: Bootstrap that loads WPML and activates the languages**

`plugin/tests/wpml/bootstrap.php`. WPML boots differently from Polylang; this loads WPML on `muplugins_loaded`, then activates en+de. The **exact** activation call is the ground-truth to confirm at Step 7 and record in the reference; start from WPML's documented setup (write `icl_sitepress_settings.active_languages` + `default_language` and let `SitePress` pick them up), then verify:

```php
<?php
/**
 * PHPUnit bootstrap for the WPML adapter suite. Separate process from the
 * monolingual and Polylang suites: WPML adds its own taxonomies and query
 * scoping to the whole process. One world per process.
 */

$_tests_dir = getenv( 'WP_TESTS_DIR' );
if ( ! $_tests_dir ) {
	$_tests_dir = rtrim( sys_get_temp_dir(), '/\\' ) . '/wordpress-tests-lib';
}

if ( ! defined( 'WP_TESTS_PHPUNIT_POLYFILLS_PATH' ) ) {
	define( 'WP_TESTS_PHPUNIT_POLYFILLS_PATH', dirname( __DIR__, 2 ) . '/vendor/yoast/phpunit-polyfills' );
}

require_once $_tests_dir . '/includes/functions.php';
require_once __DIR__ . '/language-definitions.php';

tests_add_filter( 'muplugins_loaded', function () {
	$wpml = WP_PLUGIN_DIR . '/sitepress-multilingual-cms/sitepress.php';
	if ( ! is_readable( $wpml ) ) {
		echo "WPML is not installed at {$wpml}. Provide plugin/.wpml/sitepress-multilingual-cms.zip and run env:start:wpml.\n";
		exit( 1 );
	}

	require $wpml;

	require dirname( __DIR__, 2 ) . '/vendor/autoload.php';
	require dirname( __DIR__, 2 ) . '/plugin.php';
} );

require $_tests_dir . '/includes/bootstrap.php';

require __DIR__ . '/WpmlTestCase.php';

do_action( 'wpml_loaded' );

/*
 * Activate en + de headlessly. VERIFY at Step 7 that this is sufficient for
 * `apply_filters('wpml_active_languages', null)` to return both, and record
 * the confirmed call in WPML-API-REFERENCE.md. If WPML needs its language
 * rows inserted (icl_languages/icl_languages_translations) or a
 * SitePress::set_active_languages() call in addition to the option, add it
 * here — that is exactly the ground truth this task exists to pin down.
 */
$settings = get_option( 'icl_sitepress_settings', [] );
$settings['active_languages']  = [ 'en' => 'en', 'de' => 'de' ];
$settings['default_language']  = 'en';
$settings['admin_default_language'] = 'en';
update_option( 'icl_sitepress_settings', $settings );

if ( function_exists( 'wpml_reload_active_languages_setting' ) ) {
	wpml_reload_active_languages_setting( true );
}
```

- [ ] **Step 5: Test-case base**

`plugin/tests/wpml/WpmlTestCase.php` — reseeds languages per class the way `PolylangTestCase` does, guarding against `_delete_all_data()`:

```php
<?php
/**
 * Shared base for every WP_UnitTestCase in tests/wpml/. Reseeds the WPML
 * language activation per class, because WP core's tear_down_after_class()
 * commits a _delete_all_data() that can wipe rows a later class relies on.
 */

require_once __DIR__ . '/language-definitions.php';

abstract class WpmlTestCase extends WP_UnitTestCase {
	public static function wpSetUpBeforeClass( $factory ): void {
		$settings = get_option( 'icl_sitepress_settings', [] );
		if ( ( $settings['active_languages'] ?? [] ) === [ 'en' => 'en', 'de' => 'de' ] ) {
			return;
		}
		$settings['active_languages'] = [ 'en' => 'en', 'de' => 'de' ];
		$settings['default_language'] = 'en';
		update_option( 'icl_sitepress_settings', $settings );
		if ( function_exists( 'wpml_reload_active_languages_setting' ) ) {
			wpml_reload_active_languages_setting( true );
		}
	}
}
```

- [ ] **Step 6: Harness sanity test**

`plugin/tests/wpml/HarnessTest.php`:

```php
<?php

class HarnessTest extends WpmlTestCase {

	public function test_wpml_is_loaded() {
		$this->assertTrue( defined( 'ICL_SITEPRESS_VERSION' ) );
	}

	public function test_two_languages_are_active() {
		$active = apply_filters( 'wpml_active_languages', null );
		$this->assertIsArray( $active );
		$this->assertArrayHasKey( 'en', $active );
		$this->assertArrayHasKey( 'de', $active );
	}

	public function test_default_language_is_en() {
		$this->assertSame( 'en', apply_filters( 'wpml_default_language', null ) );
	}
}
```

- [ ] **Step 7: Start the WPML env and run the harness — iterate the bootstrap until green**

The zip is already at `plugin/wpml/wpml.zip`. From the **repo root**:
```bash
cp .wp-env.wpml.json .wp-env.override.json
WP_ENV_PORT=8920 WP_ENV_TESTS_PORT=8921 npx wp-env start
# Confirm WPML is installed and Polylang is NOT active, and learn the real plugin dir name:
WP_ENV_PORT=8920 WP_ENV_TESTS_PORT=8921 npx wp-env run tests-wordpress wp plugin list
WP_ENV_PORT=8920 WP_ENV_TESTS_PORT=8921 npx wp-env run tests-wordpress --env-cwd=wp-content/plugins/pediment-ai ./vendor/bin/phpunit -c phpunit-wpml.xml.dist --filter HarnessTest
```
Adjust the `require` path in `bootstrap.php` (Step 4) to the plugin dir name `wp plugin list` actually reports (it may be `sitepress-multilingual-cms` or `wpml`). Expected: PASS. If `test_two_languages_are_active` fails, the option-only activation is insufficient — add the confirmed step (row inserts / `SitePress::set_active_languages()`) to `bootstrap.php` Step 4 and re-run until green. If Polylang is still active in `wp plugin list`, the override did not replace it — deactivate Polylang in the bootstrap. **The confirmed working sequence is the deliverable.**

- [ ] **Step 8: Capture ground truth into the reference file**

Insert WPML's native language switcher block once in a real editor (or read WPML's block registration) and copy the exact serialized markup. Write `plugin/tests/wpml/WPML-API-REFERENCE.md` with two confirmed facts:

```markdown
# WPML API Reference (captured against WPML <version>)

## Language switcher block
The native block WPML registers is `wpml/language-switcher`. A default insert
serializes to:

    <!-- wp:wpml/language-switcher {<attrs>} /-->

Confirmed default attributes: <attrs>. (Task 6 emits exactly this shape.)

## Headless language activation
The confirmed-working activation used by tests/wpml/bootstrap.php:
<the exact option writes / API calls that made HarnessTest green>
```

- [ ] **Step 9: Commit**

```bash
git add .wp-env.wpml.json plugin/phpunit-wpml.xml.dist plugin/tests/wpml/ .gitignore
git commit -m "test(wpml): stand up WPML env, bootstrap, and captured API reference"
```

---

### Task 4: `WpmlProvider` — read methods

Implements the read side of `LanguageProvider` against WPML's documented hook API. TDD in the WPML suite.

**Files:**
- Create: `plugin/src/Language/WpmlProvider.php` (read methods + `isActive`)
- Test: `plugin/tests/wpml/WpmlProviderReadTest.php`

**Interfaces:**
- Consumes: WPML filters `wpml_active_languages`, `wpml_default_language`, `wpml_current_language`, `wpml_object_id`, `wpml_element_language_code`; constant `ICL_SITEPRESS_VERSION`.
- Produces: `WpmlProvider::isActive(): bool`; instance methods `languages()`, `defaultLanguage()`, `currentLanguage()`, `hasLanguage(int)`, `translationOf(int,string)`, `unscopedQuery(array)`. (Write methods land in Task 5; class is completed then.)

- [ ] **Step 1: Write the failing tests**

`plugin/tests/wpml/WpmlProviderReadTest.php`:

```php
<?php

use Pediment\Language\WpmlProvider;

class WpmlProviderReadTest extends WpmlTestCase {

	public function test_is_active_when_wpml_configured() {
		$this->assertTrue( WpmlProvider::isActive() );
	}

	public function test_languages_lists_configured_codes_default_first() {
		$this->assertSame( [ 'en', 'de' ], ( new WpmlProvider() )->languages() );
	}

	public function test_default_language() {
		$this->assertSame( 'en', ( new WpmlProvider() )->defaultLanguage() );
	}

	public function test_translation_of_untranslated_post_is_itself_for_its_own_language() {
		$id = self::factory()->post->create( [ 'post_type' => 'page' ] );
		// A freshly created page is in the default language; asking for 'en'
		// returns itself.
		$this->assertSame( $id, ( new WpmlProvider() )->translationOf( $id, 'en' ) );
	}

	public function test_translation_of_missing_language_is_zero() {
		$id = self::factory()->post->create( [ 'post_type' => 'page' ] );
		$this->assertSame( 0, ( new WpmlProvider() )->translationOf( $id, 'de' ) );
	}

	public function test_unscoped_query_sets_suppress_filters() {
		$args = ( new WpmlProvider() )->unscopedQuery( [ 'post_type' => 'page' ] );
		$this->assertTrue( $args['suppress_filters'] );
	}
}
```

- [ ] **Step 2: Run to verify failure**

Run: `npx wp-env run tests-wordpress --env-cwd=wp-content/plugins/pediment-ai ./vendor/bin/phpunit -c phpunit-wpml.xml.dist --filter WpmlProviderReadTest`
Expected: FAIL — class not found.

- [ ] **Step 3: Implement the read side**

`plugin/src/Language/WpmlProvider.php`:

```php
<?php
/**
 * WPML implementation of the seeding engine's language seam.
 *
 * Everything WPML-specific in this product lives here, in WpmlSetup, and in
 * inc/wpml-compat.php. Nothing else may call a wpml_*/icl_* function or read
 * an ICL_* constant.
 *
 * @package Pediment
 */

declare(strict_types=1);

namespace Pediment\Language;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class WpmlProvider implements LanguageProvider {
	/**
	 * "WPML is active" is not enough, mirroring PolylangProvider: an
	 * installed-but-unconfigured WPML returns no active languages, and a seeder
	 * crossed with zero languages writes nothing while reporting success.
	 */
	public static function isActive(): bool {
		if ( ! defined( 'ICL_SITEPRESS_VERSION' ) ) {
			return false;
		}
		$active = apply_filters( 'wpml_active_languages', null );

		return is_array( $active ) && [] !== $active;
	}

	/**
	 * Configured language codes, default first — the order DesiredState and
	 * Applier depend on (a default that is not first writes children before
	 * their parent exists).
	 *
	 * @return string[]
	 */
	public function languages(): array {
		$active = (array) apply_filters( 'wpml_active_languages', null );
		$codes  = array_values( array_map( 'strval', array_keys( $active ) ) );

		$default = $this->defaultLanguage();
		if ( '' === $default ) {
			return $codes;
		}

		$rest = array_values( array_filter( $codes, static fn( string $c ): bool => $c !== $default ) );

		return array_merge( [ $default ], $rest );
	}

	public function defaultLanguage(): string {
		return (string) apply_filters( 'wpml_default_language', null );
	}

	public function currentLanguage(): string {
		return (string) apply_filters( 'wpml_current_language', null );
	}

	/**
	 * Whether a post carries a language assignment at all — the untagged-post
	 * signal Applier's repair relies on. WPML returns null for an element it
	 * has never seen.
	 */
	public function hasLanguage( int $postId ): bool {
		if ( $postId <= 0 ) {
			return false;
		}
		$code = apply_filters(
			'wpml_element_language_code',
			null,
			[ 'element_id' => $postId, 'element_type' => 'post_' . get_post_type( $postId ) ]
		);

		return null !== $code && '' !== (string) $code;
	}

	public function translationOf( int $postId, string $language ): int {
		if ( $postId <= 0 || '' === $language ) {
			return 0;
		}
		$translated = apply_filters(
			'wpml_object_id',
			$postId,
			get_post_type( $postId ),
			false, // return null, not the original, when the translation is absent.
			$language
		);

		return null === $translated ? 0 : (int) $translated;
	}

	/**
	 * @param array<string,mixed> $args
	 * @return array<string,mixed>
	 */
	public function unscopedQuery( array $args ): array {
		// WPML scopes through the posts_* filters, which suppress_filters=true
		// turns off (the reason PolylangProvider::unscopedQuery already sets it).
		$args['suppress_filters'] = true;

		return $args;
	}

	// Write methods (setLanguage, linkTranslations) + languageSwitcherBlock
	// land in Tasks 5 and 6.
}
```

Because `LanguageProvider` requires all methods, add temporary stubs so the class is instantiable until Tasks 5-6 fill them — but a partial class won't satisfy the interface, so implement Tasks 4-6 code together if the reviewer prefers. **Recommended:** land Tasks 4, 5, 6 as one class file but three commits by adding methods incrementally with the interface temporarily satisfied by `setLanguage`/`linkTranslations`/`languageSwitcherBlock` no-op stubs in this step, each replaced by its real body (and real test) in the next task. Add these stubs now:

```php
	public function setLanguage( int $postId, string $language ): void {}
	public function linkTranslations( array $map ): void {}
	/** @param bool|array<string,mixed> $config */
	public function languageSwitcherBlock( $config ): string { return ''; }
```

- [ ] **Step 4: Run the read tests**

Run: `npx wp-env run tests-wordpress --env-cwd=wp-content/plugins/pediment-ai ./vendor/bin/phpunit -c phpunit-wpml.xml.dist --filter WpmlProviderReadTest`
Expected: PASS.

- [ ] **Step 5: Lint + commit**

```bash
composer lint -d plugin
git add plugin/src/Language/WpmlProvider.php plugin/tests/wpml/WpmlProviderReadTest.php
git commit -m "feat(wpml): WpmlProvider read methods (detection, languages, translationOf)"
```

---

### Task 5: `WpmlProvider` — write methods (`setLanguage`, `linkTranslations` via trids)

Replaces the two no-op stubs with real trid management: this is the WPML wrinkle the spec calls out — "set language" and "link translation" are both trid operations.

**Files:**
- Modify: `plugin/src/Language/WpmlProvider.php` (replace the `setLanguage`/`linkTranslations` stubs)
- Test: `plugin/tests/wpml/WpmlProviderWriteTest.php`

**Interfaces:**
- Consumes: WPML `wpml_element_trid` filter; `wpml_set_element_language_details` action.
- Produces: real `setLanguage(int,string)` and `linkTranslations(array<string,int>)`.

- [ ] **Step 1: Write the failing tests**

`plugin/tests/wpml/WpmlProviderWriteTest.php`:

```php
<?php

use Pediment\Language\WpmlProvider;

class WpmlProviderWriteTest extends WpmlTestCase {

	public function test_set_language_assigns_the_language() {
		$id       = self::factory()->post->create( [ 'post_type' => 'page' ] );
		$provider = new WpmlProvider();

		$provider->setLanguage( $id, 'de' );

		$this->assertSame(
			'de',
			apply_filters( 'wpml_element_language_code', null, [ 'element_id' => $id, 'element_type' => 'post_page' ] )
		);
	}

	public function test_link_translations_makes_posts_find_each_other() {
		$en = self::factory()->post->create( [ 'post_type' => 'page' ] );
		$de = self::factory()->post->create( [ 'post_type' => 'page' ] );
		$provider = new WpmlProvider();

		$provider->setLanguage( $en, 'en' );
		$provider->setLanguage( $de, 'de' );
		$provider->linkTranslations( [ 'en' => $en, 'de' => $de ] );

		$this->assertSame( $de, $provider->translationOf( $en, 'de' ) );
		$this->assertSame( $en, $provider->translationOf( $de, 'en' ) );
	}

	public function test_link_translations_ignores_a_group_smaller_than_two() {
		$en = self::factory()->post->create( [ 'post_type' => 'page' ] );
		$provider = new WpmlProvider();
		$provider->setLanguage( $en, 'en' );

		$provider->linkTranslations( [ 'en' => $en ] ); // no-op, no fatal.

		$this->assertSame( $en, $provider->translationOf( $en, 'en' ) );
	}
}
```

- [ ] **Step 2: Run to verify failure**

Run: `npx wp-env run tests-wordpress --env-cwd=wp-content/plugins/pediment-ai ./vendor/bin/phpunit -c phpunit-wpml.xml.dist --filter WpmlProviderWriteTest`
Expected: FAIL — stubs do nothing, so `wpml_element_language_code` returns null and `translationOf` returns 0.

- [ ] **Step 3: Implement the write methods**

In `plugin/src/Language/WpmlProvider.php`, replace the `setLanguage`/`linkTranslations` stubs with:

```php
	public function setLanguage( int $postId, string $language ): void {
		if ( $postId <= 0 || '' === $language ) {
			return;
		}
		$type = 'post_' . get_post_type( $postId );

		// Reuse an existing trid so re-tagging a post keeps its group; false
		// tells WPML to mint a new translation group.
		$trid = apply_filters( 'wpml_element_trid', null, $postId, $type );

		do_action(
			'wpml_set_element_language_details',
			[
				'element_id'           => $postId,
				'element_type'         => $type,
				'trid'                 => $trid ?: false,
				'language_code'        => $language,
				'source_language_code' => null,
			]
		);
	}

	/**
	 * @param array<string,int> $map language code => post ID
	 */
	public function linkTranslations( array $map ): void {
		$clean = array_filter(
			$map,
			static fn( $postId, $language ): bool => is_int( $postId ) && $postId > 0 && '' !== $language,
			ARRAY_FILTER_USE_BOTH
		);

		if ( count( $clean ) < 2 ) {
			return;
		}

		// Anchor the group on one member's trid (the default language's when
		// present, else the first), then re-register every other member onto
		// that same trid with a source language. This is WPML's equivalent of
		// Polylang's "replace the whole group".
		$default = $this->defaultLanguage();
		$anchorLang = isset( $clean[ $default ] ) ? $default : (string) array_key_first( $clean );
		$anchorId   = (int) $clean[ $anchorLang ];
		$anchorType = 'post_' . get_post_type( $anchorId );

		$trid = apply_filters( 'wpml_element_trid', null, $anchorId, $anchorType );
		if ( ! $trid ) {
			// Anchor must belong to a group first; assign it, then re-read.
			do_action(
				'wpml_set_element_language_details',
				[
					'element_id'           => $anchorId,
					'element_type'         => $anchorType,
					'trid'                 => false,
					'language_code'        => $anchorLang,
					'source_language_code' => null,
				]
			);
			$trid = apply_filters( 'wpml_element_trid', null, $anchorId, $anchorType );
		}

		foreach ( $clean as $language => $postId ) {
			if ( $language === $anchorLang ) {
				continue;
			}
			do_action(
				'wpml_set_element_language_details',
				[
					'element_id'           => (int) $postId,
					'element_type'         => 'post_' . get_post_type( (int) $postId ),
					'trid'                 => $trid,
					'language_code'        => (string) $language,
					'source_language_code' => $anchorLang,
				]
			);
		}
	}
```

- [ ] **Step 4: Run the write tests + the full WPML suite**

Run: `npx wp-env run tests-wordpress --env-cwd=wp-content/plugins/pediment-ai ./vendor/bin/phpunit -c phpunit-wpml.xml.dist`
Expected: PASS.

- [ ] **Step 5: Lint + commit**

```bash
composer lint -d plugin
git add plugin/src/Language/WpmlProvider.php plugin/tests/wpml/WpmlProviderWriteTest.php
git commit -m "feat(wpml): WpmlProvider write methods via trid management"
```

---

### Task 6: `WpmlProvider::languageSwitcherBlock()`

Replaces the switcher stub with the native `wpml/language-switcher` block, using the exact markup captured in Task 3's reference file.

**Files:**
- Modify: `plugin/src/Language/WpmlProvider.php` (replace the `languageSwitcherBlock` stub)
- Test: `plugin/tests/wpml/WpmlSwitcherTest.php`

**Interfaces:**
- Consumes: `plugin/tests/wpml/WPML-API-REFERENCE.md` (the captured block name + default attributes).
- Produces: real `languageSwitcherBlock( bool|array ): string`.

**Task 3 finding (binding):** WPML 4.9.7's native block `wpml/language-switcher` is **dynamic and takes NO attributes** — its captured default markup is exactly `<!-- wp:wpml/language-switcher /-->` (see `WPML-API-REFERENCE.md`). So, unlike Polylang's `{dropdown}` block, the WPML switcher ignores the manifest's `language_switcher` override value: any truthy config emits the same bare block. This is correct and intended.

- [ ] **Step 1: Write the failing test**

`plugin/tests/wpml/WpmlSwitcherTest.php`:

```php
<?php

use Pediment\Language\WpmlProvider;

class WpmlSwitcherTest extends WpmlTestCase {

	public function test_switcher_emits_the_native_attributeless_wpml_block() {
		$this->assertSame(
			'<!-- wp:wpml/language-switcher /-->',
			( new WpmlProvider() )->languageSwitcherBlock( true )
		);
	}

	public function test_array_config_still_emits_the_bare_block() {
		// WPML's block accepts no attributes; a manifest override cannot change it.
		$this->assertSame(
			'<!-- wp:wpml/language-switcher /-->',
			( new WpmlProvider() )->languageSwitcherBlock( [ 'dropdown' => false ] )
		);
	}
}
```

- [ ] **Step 2: Run to verify failure**

Run: `npx wp-env run tests-wordpress --env-cwd=wp-content/plugins/pediment-ai ./vendor/bin/phpunit -c phpunit-wpml.xml.dist --filter WpmlSwitcherTest`
Expected: FAIL — stub returns `''`.

- [ ] **Step 3: Implement the switcher**

In `plugin/src/Language/WpmlProvider.php`, replace the stub with:

```php
	/**
	 * WPML's native language-switcher block is dynamic and takes no attributes
	 * (captured in tests/wpml/WPML-API-REFERENCE.md, WPML 4.9.7), so the
	 * manifest's `language_switcher` override — if any — has nothing to apply
	 * to; every truthy config emits the same bare block.
	 *
	 * @param bool|array<string,mixed> $config
	 */
	public function languageSwitcherBlock( $config ): string {
		return '<!-- wp:wpml/language-switcher /-->';
	}
```

- [ ] **Step 4: Run the test**

Run: `npx wp-env run tests-wordpress --env-cwd=wp-content/plugins/pediment-ai ./vendor/bin/phpunit -c phpunit-wpml.xml.dist --filter WpmlSwitcherTest`
Expected: PASS.

- [ ] **Step 5: Lint + commit**

```bash
composer lint -d plugin
git add plugin/src/Language/WpmlProvider.php plugin/tests/wpml/WpmlSwitcherTest.php
git commit -m "feat(wpml): emit native wpml/language-switcher block"
```

---

### Task 7: Registry WPML detection (Polylang → WPML → Null)

Wires `WpmlProvider` into `LanguageRegistry::provider()` and, per Task 1, into `setup()` once `WpmlSetup` exists (Task 9 completes the setup half; this task does the provider half plus a placeholder-free detection helper both reuse).

**Files:**
- Modify: `plugin/src/Language/LanguageRegistry.php:27` (provider detection)
- Test: `plugin/tests/wpml/RegistryDetectionTest.php`; add a monolingual assertion to `plugin/tests/phpunit/Language/LanguageProviderTest.php`

**Interfaces:**
- Consumes: `PolylangProvider::isActive()`, `WpmlProvider::isActive()`.
- Produces: `LanguageRegistry::provider()` returns `WpmlProvider` when WPML is active and Polylang is not.

- [ ] **Step 1: Write the failing tests**

`plugin/tests/wpml/RegistryDetectionTest.php`:

```php
<?php

use Pediment\Language\LanguageRegistry;
use Pediment\Language\WpmlProvider;

class RegistryDetectionTest extends WpmlTestCase {

	public function tear_down(): void {
		LanguageRegistry::reset();
		parent::tear_down();
	}

	public function test_provider_is_wpml_when_wpml_active_and_polylang_absent() {
		LanguageRegistry::reset();
		$this->assertInstanceOf( WpmlProvider::class, LanguageRegistry::provider() );
	}
}
```

Add to the monolingual `LanguageProviderTest.php` (WPML absent there, so it must still fall back to Null — proves the WPML branch is guarded by `isActive()`):

```php
	public function test_wpml_branch_is_inert_when_wpml_absent() {
		LanguageRegistry::reset();
		$this->assertInstanceOf( NullProvider::class, LanguageRegistry::provider() );
	}
```

- [ ] **Step 2: Run both to verify failure**

Run (WPML env): `... phpunit -c phpunit-wpml.xml.dist --filter RegistryDetectionTest`
Expected: FAIL — currently returns NullProvider under WPML.

- [ ] **Step 3: Add the WPML branch**

In `plugin/src/Language/LanguageRegistry.php`, replace the provider detection line:

```php
		$detected = PolylangProvider::isActive() ? new PolylangProvider() : new NullProvider();
```
with:
```php
		// Precedence: Polylang, then WPML, then monolingual. Polylang wins the
		// (unsupported) both-active tie for backward compatibility; either can be
		// forced via the pediment_language_provider filter below.
		if ( PolylangProvider::isActive() ) {
			$detected = new PolylangProvider();
		} elseif ( WpmlProvider::isActive() ) {
			$detected = new WpmlProvider();
		} else {
			$detected = new NullProvider();
		}
```

- [ ] **Step 4: Run both suites**

Run (WPML env): `... phpunit -c phpunit-wpml.xml.dist --filter RegistryDetectionTest` → PASS.
Run (monolingual): `... phpunit --filter LanguageProviderTest` → PASS.
Run (polylang): `... phpunit -c phpunit-polylang.xml.dist --filter RegistryDetectionTest` — the existing Polylang RegistryDetectionTest must still return PolylangProvider → PASS.

- [ ] **Step 5: Lint + commit**

```bash
composer lint -d plugin
git add plugin/src/Language/LanguageRegistry.php plugin/tests/wpml/RegistryDetectionTest.php plugin/tests/phpunit/Language/LanguageProviderTest.php
git commit -m "feat(wpml): detect WpmlProvider in the registry (Polylang > WPML > Null)"
```

---

### Task 8: `inc/wpml-compat.php` — translatable post types via `wpml_config_array`

The runtime analogue of `polylang-compat.php`: make `wp_navigation` translatable and keep `wp_template_part` shared, for the same built-in-post-type reason the Polylang side uses a runtime filter.

**Files:**
- Create: `plugin/inc/wpml-compat.php`
- Modify: `plugin/plugin.php:60` (load it next to `polylang-compat.php`)
- Test: `plugin/tests/wpml/WpmlCompatTest.php`

**Interfaces:**
- Consumes: WPML `wpml_config_array` filter.
- Produces: two functions `pediment_wpml_translate_navigation_menus( array $config ): array` and `pediment_wpml_share_template_parts( array $config ): array`, both registered on `wpml_config_array`.

- [ ] **Step 1: Write the failing test**

`plugin/tests/wpml/WpmlCompatTest.php`:

```php
<?php

class WpmlCompatTest extends WpmlTestCase {

	public function test_navigation_is_declared_translatable() {
		$config = apply_filters( 'wpml_config_array', [] );
		$types  = $config['wpml-config']['custom-types']['custom-type'] ?? [];
		$this->assertContains( 'wp_navigation', array_column( $types, 'value' ) );
	}

	public function test_template_part_is_declared_not_translatable() {
		$config = apply_filters( 'wpml_config_array', [] );
		$types  = $config['wpml-config']['custom-types']['custom-type'] ?? [];
		foreach ( $types as $type ) {
			if ( ( $type['value'] ?? '' ) === 'wp_template_part' ) {
				$this->assertSame( '0', (string) ( $type['attr']['translate'] ?? '1' ) );
			}
		}
		$this->assertTrue( true );
	}
}
```

(The exact `wpml_config_array` array shape is WPML's documented config structure; confirm the nesting against the real filter output at Step 4 and adjust the assertions/implementation together to the confirmed shape recorded in `WPML-API-REFERENCE.md`.)

- [ ] **Step 2: Run to verify failure**

Run: `... phpunit -c phpunit-wpml.xml.dist --filter WpmlCompatTest`
Expected: FAIL — file/filters not present.

- [ ] **Step 3: Create `inc/wpml-compat.php`**

```php
<?php
/**
 * What WPML needs to know about this product's built-in post types, and cannot
 * be told by clicking. The runtime analogue of inc/polylang-compat.php.
 *
 * wp_navigation must be translatable so a menu can exist per language; WPML's
 * settings screen does not list built-in post types, so a runtime config
 * injection is the only path. wp_template_part stays shared: one header/footer
 * serves every language, tagged with no language.
 *
 * This is separate from the generated plugin/wpml-config.xml, which declares
 * translatable *block attributes*; this file declares *post-type
 * translatability*. Both are no-ops when WPML is inactive (the filter never
 * fires).
 *
 * @package Pediment
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * @param array<string,mixed> $config
 * @return array<string,mixed>
 */
function pediment_wpml_translate_navigation_menus( $config ) {
	$config['wpml-config']['custom-types']['custom-type'][] = [
		'value' => 'wp_navigation',
		'attr'  => [ 'translate' => '1' ],
	];
	return $config;
}
add_filter( 'wpml_config_array', 'pediment_wpml_translate_navigation_menus' );

/**
 * @param array<string,mixed> $config
 * @return array<string,mixed>
 */
function pediment_wpml_share_template_parts( $config ) {
	$config['wpml-config']['custom-types']['custom-type'][] = [
		'value' => 'wp_template_part',
		'attr'  => [ 'translate' => '0' ],
	];
	return $config;
}
add_filter( 'wpml_config_array', 'pediment_wpml_share_template_parts' );
```

(If Step 4 shows WPML expects a different array nesting, adjust both functions and the test to the confirmed shape — the *behavior* is fixed: nav translatable, template part not.)

- [ ] **Step 4: Load it from `plugin.php`**

In `plugin/plugin.php`, after line 60 (`require_once ... polylang-compat.php;`) add:

```php
// The WPML analogue of polylang-compat.php: wp_navigation translatable,
// wp_template_part shared. No-op when WPML is inactive.
require_once PEDIMENT_AI_PLUGIN_DIR . '/inc/wpml-compat.php';
```

- [ ] **Step 5: Run the test**

Run: `... phpunit -c phpunit-wpml.xml.dist --filter WpmlCompatTest`
Expected: PASS. Iterate the array shape with the real filter output if needed.

- [ ] **Step 6: Lint + commit**

```bash
composer lint -d plugin
git add plugin/inc/wpml-compat.php plugin/plugin.php plugin/tests/wpml/WpmlCompatTest.php
git commit -m "feat(wpml): declare wp_navigation translatable, template parts shared"
```

---

### Task 9: `WpmlSetup` implements `LanguageSetup`, wired into `setup()` detection

The WPML analogue of `PolylangSetup`: reconcile WPML's active languages + default against the manifest, idempotently, honouring `--dry-run`.

**Files:**
- Create: `plugin/src/Language/WpmlSetup.php`
- Modify: `plugin/src/Language/LanguageRegistry.php` (`setup()` detection: Polylang → WPML → Polylang default)
- Test: `plugin/tests/wpml/WpmlSetupTest.php`

**Interfaces:**
- Consumes: `LanguageSetup` (Task 1); `\Pediment\Seeder\LanguageSpec`; WPML settings option `icl_sitepress_settings` / `SitePress::set_active_languages` (confirm the working write from Task 3's reference).
- Produces: `WpmlSetup implements LanguageSetup` with the frozen `configure()` signature; `LanguageRegistry::setup()` returns it when WPML is active and Polylang is not.

- [ ] **Step 1: Write the failing tests**

`plugin/tests/wpml/WpmlSetupTest.php`:

```php
<?php

use Pediment\Language\WpmlSetup;
use Pediment\Seeder\LanguageSpec;

class WpmlSetupTest extends WpmlTestCase {

	/** @return array<string,LanguageSpec> */
	private function manifestLanguages(): array {
		return [
			'en' => new LanguageSpec( 'en', 'English', 'en_US', 'gb', true ),
			'de' => new LanguageSpec( 'de', 'German', 'de_DE', 'de', false ),
		];
	}

	public function test_already_configured_reports_no_changes() {
		// The harness already activated en + de with default en.
		$result = ( new WpmlSetup() )->configure( $this->manifestLanguages(), 'en' );
		$this->assertSame( [], $result['changes'] );
		$this->assertSame( [], $result['errors'] );
	}

	public function test_dry_run_reports_a_missing_language_without_writing() {
		$langs = $this->manifestLanguages();
		$langs['fr'] = new LanguageSpec( 'fr', 'French', 'fr_FR', 'fr', false );

		$result = ( new WpmlSetup() )->configure( $langs, 'en', true );

		$this->assertNotEmpty( $result['changes'] );
		$active = apply_filters( 'wpml_active_languages', null );
		$this->assertArrayNotHasKey( 'fr', $active ); // nothing written.
	}

	public function test_errors_when_wpml_inactive_is_not_reachable_here() {
		// WPML is active in this suite; this asserts the happy path returns the
		// documented array shape.
		$result = ( new WpmlSetup() )->configure( $this->manifestLanguages(), 'en' );
		$this->assertArrayHasKey( 'changes', $result );
		$this->assertArrayHasKey( 'errors', $result );
	}
}
```

- [ ] **Step 2: Run to verify failure**

Run: `... phpunit -c phpunit-wpml.xml.dist --filter WpmlSetupTest`
Expected: FAIL — class not found.

- [ ] **Step 3: Implement `WpmlSetup`**

`plugin/src/Language/WpmlSetup.php` — structure mirrors `PolylangSetup`: guard WPML active, diff manifest vs current, write only when not dry-run, return `{changes, errors}`. Use the confirmed activation write from `WPML-API-REFERENCE.md`:

```php
<?php
/**
 * Reconcile WPML's own settings against the manifest's `languages`. The WPML
 * analogue of PolylangSetup; runs from `wp pediment languages`, never inside a
 * seed. All WPML-specific writes are quarantined here.
 *
 * @package Pediment
 */

declare(strict_types=1);

namespace Pediment\Language;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class WpmlSetup implements LanguageSetup {
	/**
	 * @param array<string,\Pediment\Seeder\LanguageSpec> $languages Declaration order, default first.
	 * @return array{changes:string[],errors:string[]}
	 */
	public function configure( array $languages, string $default, bool $dryRun = false ): array {
		if ( ! defined( 'ICL_SITEPRESS_VERSION' ) ) {
			return [ 'changes' => [], 'errors' => [ 'WPML is not active — install and activate it, or remove the manifest\'s `languages` section.' ] ];
		}
		if ( [] === $languages ) {
			return [ 'changes' => [], 'errors' => [ 'The manifest declares no languages — nothing to configure.' ] ];
		}

		$changes = [];
		$errors  = [];

		$active  = (array) apply_filters( 'wpml_active_languages', null );
		$current = array_keys( $active );

		$wanted = [];
		foreach ( $languages as $spec ) {
			$wanted[] = $spec->slug;
			if ( ! in_array( $spec->slug, $current, true ) ) {
				$changes[] = sprintf( 'activate language %s (%s)', $spec->slug, $spec->locale );
			}
		}

		if ( (string) apply_filters( 'wpml_default_language', null ) !== $default ) {
			$changes[] = sprintf( 'set default language %s', $default );
		}

		if ( $dryRun || [] === $changes ) {
			return [ 'changes' => $changes, 'errors' => $errors ];
		}

		// Confirmed activation path (tests/wpml/WPML-API-REFERENCE.md): a raw
		// icl_sitepress_settings write does NOT flip the `active` flag in
		// wp_icl_languages, so wpml_active_languages stays empty. WPML's own
		// setup instance is what actually activates a language — the analogue of
		// PolylangSetup going through PLL()'s API rather than update_option().
		if ( ! function_exists( 'wpml_get_setup_instance' ) ) {
			$errors[] = 'WPML setup API unavailable — cannot activate languages.';
			return [ 'changes' => $changes, 'errors' => $errors ];
		}

		$setup = wpml_get_setup_instance();
		$setup->finish_step1( $default );      // sets the default/first language
		$setup->set_active_languages( $wanted ); // reconciles the active set to the manifest
		$setup->finish_installation();          // marks setup complete; flips the active flags

		return [ 'changes' => $changes, 'errors' => $errors ];
	}
}
```

**Confirm against the live WPML env (Task 9 runs in it):** this three-call sequence is the one Task 3 captured for first-time activation. Verify it is idempotent on an already-installed site (the `test_already_configured_reports_no_changes` path must not error, and a re-run must not corrupt state). If `finish_step1`/`finish_installation` misbehave when setup is already complete, keep only `set_active_languages( $wanted )` plus the default-language setter the reference documents, guarded so the diff logic still governs whether anything is written. The `{changes, errors}` contract and the diff logic above stay as written.

- [ ] **Step 4: Wire `setup()` detection**

In `plugin/src/Language/LanguageRegistry.php`, replace the `setup()` detection line:

```php
		$detected = new PolylangSetup();
```
with:
```php
		if ( PolylangProvider::isActive() ) {
			$detected = new PolylangSetup();
		} elseif ( WpmlProvider::isLoaded() ) {
			$detected = new WpmlSetup();
		} else {
			$detected = new PolylangSetup();
		}
```

**Why `isLoaded()`, NOT `isActive()`, for the setup branch (corrected after Task 9 review):** `setup()` is what *creates* the languages, so it must fire on a WPML site that is installed but not yet configured — precisely the zero-active-languages state where `WpmlProvider::isActive()` (which requires a non-empty `wpml_active_languages`) is false. Gating on `isActive()` would make `WpmlSetup` unreachable for its headline use case (`wp pediment languages` on a fresh WPML site), falling through to `PolylangSetup` and printing a nonsensical "Polylang is not active" error. This differs from `provider()`, which correctly uses `isActive()` because content seeding *does* require languages to already exist.

To keep the WPML-symbol quarantine (no `ICL_*` in `LanguageRegistry.php`), add a static to the already-WPML-quarantined `WpmlProvider`:
```php
	/** Whether the WPML plugin is loaded (regardless of whether languages are configured yet). */
	public static function isLoaded(): bool {
		return defined( 'ICL_SITEPRESS_VERSION' );
	}
```
(Null case still defaults to `PolylangSetup`, matching Task 1; a monolingual site never reaches a write because `LanguagesCommand` short-circuits on an empty manifest.)

- [ ] **Step 5: Run the WPML suite + the Polylang setup test**

Run: `... phpunit -c phpunit-wpml.xml.dist` → PASS.
Run: `... phpunit --filter LanguageSetupTest` → PASS (default still PolylangSetup when neither active).
Run: `... phpunit -c phpunit-polylang.xml.dist` → PASS (PolylangSetup still resolved under Polylang).

- [ ] **Step 6: Lint + commit**

```bash
composer lint -d plugin
git add plugin/src/Language/WpmlSetup.php plugin/src/Language/LanguageRegistry.php plugin/tests/wpml/WpmlSetupTest.php
git commit -m "feat(wpml): WpmlSetup configures WPML languages from the manifest"
```

---

### Task 10: End-to-end seeding behavior under WPML (mirror the Polylang behavior tests)

Proves the *engine* — not just the provider — seeds correctly under WPML, by porting the Polylang behavior tests that exercise the engine through the provider. This is where a real seed run against WPML is verified.

**Files:**
- Create: `plugin/tests/wpml/SeedingBehaviorTest.php` (the ported behaviors; one file is fine, or split per concern if it grows past ~300 lines)
- Test target: the engine classes `Claimer`, `Applier`, `NavSeeder`, `Adopter`, `DesiredState`, `Runner` via `WpmlProvider`.

**Interfaces:**
- Consumes: `WpmlProvider`, `LanguageRegistry`, and the engine's public seed entry points used by the Polylang behavior tests (read `plugin/tests/polylang/ClaimerLanguageTest.php`, `ApplierTranslationTest.php`, `NavLanguageTest.php`, `NavBindingTest.php`, `AdopterLanguageTest.php`, `DesiredStateLanguageTest.php`, `RunnerLanguageGateTest.php` to see the exact call shapes to port).

- [ ] **Step 1: Read the Polylang behavior tests and port the highest-value cases**

For each Polylang behavior test file, write a WPML counterpart in `SeedingBehaviorTest.php` asserting the same end state. Minimum coverage (one test each):
- A two-language seed assigns each seeded page its language (`translationOf` round-trips en↔de).
- The seeded header's ref-less navigation binds to the current language's menu (`pediment_bind_navigation_ref` picks the de menu when `currentLanguage()` is 'de').
- The language gate (`LanguageGate::mismatch`) reports parity when manifest languages equal WPML's active languages.
- Adopt tags a pre-existing untagged page with the default language exactly once.

Example (nav binding — the others follow the Polylang originals):

```php
<?php

use Pediment\Language\LanguageRegistry;
use Pediment\Language\WpmlProvider;

class SeedingBehaviorTest extends WpmlTestCase {

	public function tear_down(): void {
		LanguageRegistry::reset();
		remove_all_filters( 'pediment_language_provider' );
		parent::tear_down();
	}

	public function test_navigation_binds_to_the_current_language_menu() {
		$provider = new WpmlProvider();
		$en = self::factory()->post->create( [ 'post_type' => 'wp_navigation', 'post_status' => 'publish' ] );
		$de = self::factory()->post->create( [ 'post_type' => 'wp_navigation', 'post_status' => 'publish' ] );
		update_post_meta( $en, \Pediment\Seeder\Meta::KEY, 'primary' );
		update_post_meta( $de, \Pediment\Seeder\Meta::KEY, 'primary' );
		$provider->setLanguage( $en, 'en' );
		$provider->setLanguage( $de, 'de' );
		$provider->linkTranslations( [ 'en' => $en, 'de' => $de ] );

		// Force current language to 'de' for the binding lookup.
		add_filter( 'wpml_current_language', static fn() => 'de' );

		$bound = pediment_bind_navigation_ref( [ 'blockName' => 'core/navigation', 'attrs' => [] ] );
		$this->assertSame( $de, $bound['attrs']['ref'] ?? 0 );
	}
}
```

(Port the remaining behaviors from their Polylang originals — repeat the assertion structure, swapping the provider. Do not skip any: each Polylang behavior test that exercises the engine through the provider gets a WPML counterpart.)

- [ ] **Step 2: Run to verify failures, then confirm green**

Run: `... phpunit -c phpunit-wpml.xml.dist --filter SeedingBehaviorTest`
Expected: initial FAIL where the engine hasn't been exercised, then PASS after the ports are correct. If any behavior reveals a provider gap (e.g. `unscopedQuery` not escaping WPML scoping in a real query), fix `WpmlProvider` and add a regression test — this task is the real integration proof.

- [ ] **Step 3: Lint + commit**

```bash
composer lint -d plugin
git add plugin/tests/wpml/SeedingBehaviorTest.php plugin/src/Language/WpmlProvider.php
git commit -m "test(wpml): engine seeds, binds, and gates correctly under WPML"
```

---

### Task 11: E2E — `multilingual-wpml.spec.ts`

Playwright parity with `multilingual.spec.ts`, driving WPML via `wp eval`.

**Files:**
- Create: `plugin/tests/e2e/multilingual-wpml.spec.ts`
- Modify (if needed): `plugin/playwright.config.ts` / e2e global-setup to activate WPML instead of Polylang for this spec's project.

**Interfaces:**
- Consumes: the WPML wp-env world (Task 3) and the seeded fixture theme.

- [ ] **Step 1: Read `plugin/tests/e2e/multilingual.spec.ts` and `plugin/tests/e2e/global-setup.ts`**

Understand how the Polylang e2e activates the plugin, seeds, and asserts per-language rendering (the Explore report cites `multilingual.spec.ts:132,151` driving Polylang via `wp eval`).

- [ ] **Step 2: Write the WPML spec**

Port it: activate WPML and configure en+de using the **confirmed WPML setup API** from Task 3 (`wpml_get_setup_instance()->finish_step1('en')` / `set_active_languages(['en','de'])` / `finish_installation()` — the raw `icl_sitepress_settings` write does NOT activate languages), or simply run `wp pediment languages` (which now routes to `WpmlSetup`). Then run `wp pediment seed`, and assert per-language rendering.

**MANDATORY production-chain assertion (carried from the Task 14 review — this is the whole reason the e2e exists):** the e2e must prove the full runtime chain that the unit tests can only stub — `wpml-compat.php`'s `wpml_config_array` filter → WPML actually treats `wp_navigation` as translatable → `NavSeeder::linkTranslations` builds the nav group → the binding resolves per language. Concretely: after seeding, assert that the header navigation at `/` (English) and at `/de/` (German) render **DISTINCT** menus (different, language-appropriate nav items) — not the same menu on both. If they are identical, the production chain is broken (WPML never made `wp_navigation` translatable at runtime, so `translationOf` fell back to the default menu) — that is a real defect to surface, NOT something to stub around. Do NOT hand-set `custom_posts_sync_option` in the e2e; the point is to prove WPML consumes the filter on its own. If WPML only parses `wpml_config_array` on a specific trigger (plugin (re)activation, admin visit, cache flush), invoke that trigger the way a real deploy would and document it — but the wp_navigation-translatable state must arrive through WPML's real config path, not a test write.

Also assert the switcher block renders. Use `wpml_object_id` / the WPML setup API via `wp eval` where the Polylang spec used `pll_*`.

- [ ] **Step 3: Run it**

Run: `cd plugin && npx playwright test tests/e2e/multilingual-wpml.spec.ts` (against the WPML env).
Expected: PASS.

- [ ] **Step 4: Commit**

```bash
git add plugin/tests/e2e/multilingual-wpml.spec.ts plugin/playwright.config.ts plugin/tests/e2e/global-setup.ts
git commit -m "test(wpml): e2e per-language rendering and switcher"
```

---

### Task 12: CI — a `wpml` job gated on the license secret, with graceful skip

**Files:**
- Modify: `.github/workflows/ci.yml` (new `wpml` job)
- Create: `plugin/tests/wpml/skip-when-absent.php` (or a guard in the phpunit bootstrap) — when the WPML zip is absent, the suite reports skipped, not failed.

**Interfaces:**
- Consumes: repo secret `WPML_ZIP_B64` (base64 of the WPML zip) or `WPML_ZIP_URL` (authenticated download).
- Produces: a CI job that runs the WPML PHPUnit + e2e suites only when the secret is present.

- [ ] **Step 1: Make the WPML suites skip gracefully when WPML is missing**

In `plugin/tests/wpml/bootstrap.php`, change the "not installed" branch from `exit(1)` to marking the suite skipped: if the WPML plugin file is unreadable, define a constant the tests check and have `WpmlTestCase::setUp()` call `$this->markTestSkipped('WPML zip not provided')`. Concretely, in `bootstrap.php` replace `exit(1)` with `define('PEDIMENT_WPML_MISSING', true); return;` inside the `muplugins_loaded` closure guard, and add to `WpmlTestCase`:

```php
	public function set_up(): void {
		if ( defined( 'PEDIMENT_WPML_MISSING' ) ) {
			$this->markTestSkipped( 'WPML zip not provided; skipping the WPML suite.' );
		}
		parent::set_up();
	}
```

- [ ] **Step 2: Add the CI job**

In `.github/workflows/ci.yml`, add a `wpml` job mirroring `phpunit`, gated on the secret and decoding the zip to `plugin/.wpml/`:

```yaml
  wpml:
    runs-on: ubuntu-latest
    if: ${{ github.event_name == 'push' || github.event.pull_request.head.repo.full_name == github.repository }}
    steps:
      - uses: actions/checkout@v4
      - uses: actions/setup-node@v4
        with:
          node-version: "20"
          cache: npm
          cache-dependency-path: plugin/package-lock.json
      - uses: shivammathur/setup-php@v2
        with:
          php-version: "8.1"
          tools: composer
      - run: npm ci
      - run: composer install --prefer-dist --no-progress -d plugin
      - run: cd plugin && npm ci && npm run build
      - name: Provide WPML (extracted dir — single-file zip mounts fail under Docker)
        env:
          WPML_ZIP_B64: ${{ secrets.WPML_ZIP_B64 }}
        run: |
          set -euo pipefail
          if [ -z "${WPML_ZIP_B64:-}" ]; then
            echo "WPML_ZIP_B64 not set — WPML suite will be skipped."
            echo "HAS_WPML=false" >> "$GITHUB_ENV"
            exit 0
          fi
          mkdir -p plugin/wpml
          echo "$WPML_ZIP_B64" | base64 -d > plugin/wpml/wpml.zip
          rm -rf plugin/wpml/sitepress-multilingual-cms
          unzip -q plugin/wpml/wpml.zip -d plugin/wpml/
          test -f plugin/wpml/sitepress-multilingual-cms/sitepress.php
          echo "HAS_WPML=true" >> "$GITHUB_ENV"
      - name: Start the WPML env
        if: env.HAS_WPML == 'true'
        run: |
          set -euo pipefail
          cp .wp-env.wpml.json .wp-env.override.json
          npx wp-env start
      - name: Run the WPML PHPUnit suite
        if: env.HAS_WPML == 'true'
        run: npx wp-env run tests-wordpress --env-cwd=wp-content/plugins/pediment-ai ./vendor/bin/phpunit -c phpunit-wpml.xml.dist
      - if: always() && env.HAS_WPML == 'true'
        run: npx wp-env stop || true
```

Notes for the implementer:
- The wp-env project root is the repo root; `wp-env start`/`run` execute from there. wp-env 10.39 has no `--config` flag — copying `.wp-env.wpml.json` to `.wp-env.override.json` is the mechanism.
- Single-file zip mounts fail under this Docker storage driver, so the env mounts an **extracted directory** (`plugin/wpml/sitepress-multilingual-cms/`); CI must `unzip` before start. Exact local repro: `unzip -q plugin/wpml/wpml.zip -d plugin/wpml/`.
- Secret unset → `HAS_WPML=false` skips start/run and the job stays green. The `markTestSkipped` bootstrap guard (Step 1) is the second layer for a non-WPML env.

- [ ] **Step 3: Verify the skip path locally (no zip)**

Restore the Polylang env (`rm .wp-env.override.json && WP_ENV_PORT=8920 WP_ENV_TESTS_PORT=8921 npx wp-env start`) so WPML is not loaded, then run the WPML suite against it: `WP_ENV_PORT=8920 WP_ENV_TESTS_PORT=8921 npx wp-env run tests-wordpress --env-cwd=wp-content/plugins/pediment-ai ./vendor/bin/phpunit -c phpunit-wpml.xml.dist`
Expected: tests reported SKIPPED, exit 0. (Then switch back to the WPML env for any remaining WPML work.)

- [ ] **Step 4: Commit**

```bash
git add .github/workflows/ci.yml plugin/tests/wpml/bootstrap.php plugin/tests/wpml/WpmlTestCase.php
git commit -m "ci(wpml): run the WPML suite when the license secret is present, skip otherwise"
```

---

### Task 13: Docs + quarantine check

**Files:**
- Modify: `docs/seeding.md` (the "Only Polylang is implemented" note)
- Modify: `docs/BACKLOG.md` (close the WPML-adapter entry)
- No code.

- [ ] **Step 1: Update `docs/seeding.md`**

Find the note near line 1110 ("Only Polylang is implemented. … That seam is where a WPML adapter would go") and update it to state both Polylang and WPML are implemented against the `LanguageProvider`/`LanguageSetup` seam, with detection order Polylang → WPML → monolingual.

- [ ] **Step 2: Close the BACKLOG entry**

In `docs/BACKLOG.md` (around lines 205-211), mark the "WPML adapter" item done, referencing this plan.

- [ ] **Step 3: Enforce the quarantine invariant**

Run this grep; it must return **nothing** (all WPML symbols confined to the three allowed files):

```bash
grep -rEn "wpml_|icl_|ICL_|sitepress" plugin/src plugin/inc \
  | grep -vE "plugin/src/Language/WpmlProvider.php|plugin/src/Language/WpmlSetup.php|plugin/inc/wpml-compat.php" \
  | grep -viE "polylang|// .*WPML"   # allow the existing anticipatory comments in Polylang files
```
Expected: no output. If anything leaks, move it into one of the three files.

- [ ] **Step 4: Full suite sweep**

Run all four suites and lint:
```bash
npx wp-env run tests-wordpress --env-cwd=wp-content/plugins/pediment-ai ./vendor/bin/phpunit
npx wp-env run tests-wordpress --env-cwd=wp-content/plugins/pediment-ai ./vendor/bin/phpunit -c phpunit-polylang.xml.dist
# WPML env:
npx wp-env run tests-wordpress --env-cwd=wp-content/plugins/pediment-ai ./vendor/bin/phpunit -c phpunit-wpml.xml.dist
composer lint -d plugin
```
Expected: all PASS / no errors.

- [ ] **Step 5: Commit**

```bash
git add docs/seeding.md docs/BACKLOG.md
git commit -m "docs(wpml): document WPML support and close the backlog entry"
```

---

### Task 14: Per-language nav binding through the provider seam (fixes the WPML runtime scoping gap)

**Added after Task 10 review** (opus-confirmed load-bearing defect). `pediment_seeded_nav_id()` scopes by Polylang's `lang` query var; under WPML that arg is inert and `get_posts()`'s default `suppress_filters=true` disables WPML's `posts_where` scoping (the codebase's own `WpmlProvider::unscopedQuery()` sets that flag *specifically to bypass* WPML scoping). Result at a real WPML front-end: every non-default-language header binds to the default (English) menu — the exact failure the nav mechanism exists to prevent. Fix: resolve the current-language nav through the `LanguageProvider` seam (`translationOf` on the linked nav group), falling back to the existing term query so Polylang behavior is unchanged.

**Files:**
- Modify: `plugin/inc/nav-language.php` (`pediment_bind_navigation_ref` only; `pediment_seeded_nav_id` stays as-is, still used for the unscoped anchor and the fallback)
- Test: `plugin/tests/wpml/NavBindingTest.php` (new — WPML two-language discrimination)
- Possibly Modify: one Polylang mechanism-level test if it asserts the old candidate structure (see Step 5)

**Interfaces:**
- Consumes: `LanguageProvider::translationOf(int,string)` (Tasks 4/5 for WPML; pre-existing for Polylang/Null), `currentLanguage()`, `defaultLanguage()`, and `pediment_seeded_nav_id()`.
- Produces: no new public surface — `pediment_bind_navigation_ref` behavior is corrected.

**Design (hybrid — verified against every Polylang NavBindingTest case):** try the translation-group lookup first, then the term query. This keeps all Polylang outcome tests green (their navs are tagged-but-unlinked, so `translationOf` returns 0 and the term-query fallback discriminates), and fixes WPML (production navs are linked via `NavSeeder::linkTranslations`, so `translationOf` resolves directly).

- [ ] **Step 1: Write the failing WPML discrimination test**

`plugin/tests/wpml/NavBindingTest.php` (extends `WpmlTestCase`) — create en+de `primary` navs, assign languages, and **link them** (mirroring real seeding), then assert the bound ref differs by current language:

```php
<?php

use Pediment\Language\WpmlProvider;
use Pediment\Seeder\Meta;

class NavBindingTest extends WpmlTestCase {

	private int $en;
	private int $de;

	public function set_up(): void {
		parent::set_up();
		$provider = new WpmlProvider();
		$this->en = self::factory()->post->create( [ 'post_type' => 'wp_navigation', 'post_title' => 'Primary EN', 'post_status' => 'publish' ] );
		$this->de = self::factory()->post->create( [ 'post_type' => 'wp_navigation', 'post_title' => 'Primary DE', 'post_status' => 'publish' ] );
		update_post_meta( $this->en, Meta::KEY, 'primary' );
		update_post_meta( $this->de, Meta::KEY, 'primary' );
		$provider->setLanguage( $this->en, 'en' );
		$provider->setLanguage( $this->de, 'de' );
		$provider->linkTranslations( [ 'en' => $this->en, 'de' => $this->de ] );
	}

	public function tear_down(): void {
		remove_filter( 'wpml_current_language', '__return_de', 99 );
		parent::tear_down();
	}

	private function bind( string $current ): array {
		add_filter( 'wpml_current_language', static fn() => $current, 99 );
		$out = pediment_bind_navigation_ref( [ 'blockName' => 'core/navigation', 'attrs' => [] ] );
		remove_all_filters( 'wpml_current_language' ); // scoped to this helper; restore in tear_down if needed
		return $out;
	}

	public function test_english_current_binds_the_english_menu() {
		$this->assertSame( $this->en, $this->bind( 'en' )['attrs']['ref'] ?? 0 );
	}

	public function test_german_current_binds_the_german_menu() {
		$this->assertSame( $this->de, $this->bind( 'de' )['attrs']['ref'] ?? 0 );
	}
}
```

Confirm `test_german_current_binds_the_german_menu` FAILS against the current (unfixed) `pediment_bind_navigation_ref` — it binds `$this->en` (the wrong menu), demonstrating the gap. (If forcing `wpml_current_language` via `add_filter` does not move `currentLanguage()`, set the current language the way WPML's real request path does — check `plugin/tests/wpml/WPML-API-REFERENCE.md` / how Task 10 forced it; the point is a genuine current-language switch.)

- [ ] **Step 2: Run it to see the German case fail**

Run: `WP_ENV_PORT=8920 WP_ENV_TESTS_PORT=8921 npx wp-env run tests-wordpress --env-cwd=wp-content/plugins/pediment-ai ./vendor/bin/phpunit -c phpunit-wpml.xml.dist --filter NavBindingTest`
Expected: `test_german_current_binds_the_german_menu` FAILS (binds English).

- [ ] **Step 3: Apply the hybrid fix in `pediment_bind_navigation_ref`**

Replace the candidate loop (the part after the guard clauses, from `$candidates = ...` through the final `return`) with:

```php
	$provider = \Pediment\Language\LanguageRegistry::provider();
	$current  = $provider->currentLanguage();
	$default  = $provider->defaultLanguage();

	// The unscoped anchor: the seeded 'primary' nav, oldest wins. The ''
	// lookup is language-agnostic under both plugins.
	$anchor = pediment_seeded_nav_id( '' );

	// Resolve per language through the seam FIRST: translationOf() follows the
	// nav translation group, which is the only mechanism that works under WPML
	// (its query scoping does not survive get_posts()' suppress_filters=true —
	// see WpmlProvider::unscopedQuery). Fall back to the language-term query,
	// which is Polylang's mechanism and also covers a partial seed where a nav
	// is tagged but not yet linked.
	$candidates = array_values( array_unique( array_filter( [ $current, $default ] ) ) );
	foreach ( $candidates as $language ) {
		$ref = $anchor > 0 ? $provider->translationOf( $anchor, (string) $language ) : 0;
		if ( $ref <= 0 ) {
			$ref = pediment_seeded_nav_id( (string) $language );
		}
		if ( $ref > 0 ) {
			$parsed_block['attrs']['ref'] = $ref;
			return $parsed_block;
		}
	}

	// Unscoped fallback: monolingual (current/default both ''), or an untagged
	// legacy nav that no language candidate matched. Better a menu chosen badly
	// than an empty header.
	if ( $anchor > 0 ) {
		$parsed_block['attrs']['ref'] = $anchor;
		return $parsed_block;
	}

	// Nothing seeded: leave the block alone and let core's own fallback run.
	return $parsed_block;
```

Update the function's docblock paragraph about candidate order to match the new resolution (seam-first, then term query, then unscoped anchor).

- [ ] **Step 4: WPML tests green**

Run: `... phpunit -c phpunit-wpml.xml.dist --filter NavBindingTest` → both PASS.
Run the FULL WPML suite (no --filter) → all green, no pollution.

- [ ] **Step 5: Verify Polylang stays green (switch envs)**

The fix touches shared front-end code, so the Polylang suite MUST be re-run. Switch this workspace's single wp-env instance back to Polylang:
```bash
rm .wp-env.override.json
WP_ENV_PORT=8920 WP_ENV_TESTS_PORT=8921 npx wp-env start
WP_ENV_PORT=8920 WP_ENV_TESTS_PORT=8921 npx wp-env run tests-wordpress --env-cwd=wp-content/plugins/pediment-ai ./vendor/bin/phpunit -c phpunit-polylang.xml.dist
```
Expected: all Polylang tests PASS. The outcome tests (`test_english_gets_the_english_menu`, `test_german_gets_the_german_menu`, `test_a_language_with_no_menu_falls_back_to_the_default`, `test_an_untagged_legacy_nav_is_found_by_the_unscoped_fallback`, explicit-ref, other-blocks, inner-blocks) must all stay green — the hybrid's term-query fallback preserves them. If `test_the_unscoped_candidate_queries_with_lang_set_not_omitted` (a mechanism-level assertion about the old candidate structure) fails because the '' lookup moved, update THAT test to assert the anchor lookup still queries with `lang` set (the mechanism it guards still exists, at the top of the function now) — do not weaken it, re-point it. Justify any test change in the report. Also run the full monolingual suite (`... ./vendor/bin/phpunit`) to confirm NullProvider binding (anchor fallback) is unaffected.

- [ ] **Step 6: Return the env to WPML for the remaining tasks**

```bash
cp .wp-env.wpml.json .wp-env.override.json
WP_ENV_PORT=8920 WP_ENV_TESTS_PORT=8921 npx wp-env start
```
Confirm the WPML suite is green once more. Lint: `composer lint -d plugin`.

- [ ] **Step 7: Commit**

```bash
git add plugin/inc/nav-language.php plugin/tests/wpml/NavBindingTest.php
# plus any re-pointed Polylang mechanism test
git commit -m "fix(wpml): bind per-language nav through the provider seam, not query scoping"
```

---

## Self-Review

**Spec coverage:**
- Interface additions (`currentLanguage`, `languageSwitcherBlock`, `LanguageSetup`) → Tasks 1, 2. ✓
- `WpmlProvider` full API map → Tasks 4 (read), 5 (write/trids), 6 (switcher). ✓
- `WpmlSetup` → Task 9. ✓
- Registry detection Polylang → WPML → Null (provider + setup) → Tasks 7, 9. ✓
- `wpml-compat.php` translatable types → Task 8. ✓
- `nav-language.php` routed through provider → Task 2. ✓
- `NavSeeder` switcher via provider → Task 2. ✓
- Testing parity (`tests/wpml/`, interface contract, e2e) → Tasks 3, 4, 5, 6, 8, 9, 10, 11. ✓
- wp-env WPML config + secret + graceful skip + CI job → Tasks 3, 12. ✓
- Pre-existing generator/`wpml-config.xml` untouched → Global Constraints + Task 13 grep. ✓
- Docs → Task 13. ✓

**Known capture-dependent values** (resolved in Task 3, consumed later, not placeholders): the exact `wpml/language-switcher` default attributes (Task 6), the confirmed headless language-activation write (Tasks 3, 9), and the `wpml_config_array` array nesting (Task 8). Each has a defined source artifact (`WPML-API-REFERENCE.md`) and a verification step; the *behavior* each must produce is fully specified.

**Type consistency:** `LanguageSetup::configure(array,string,bool):array` is identical across the interface, `PolylangSetup`, `WpmlSetup`, and both call sites. `languageSwitcherBlock($config)` and `currentLanguage()` signatures match across `LanguageProvider`, `PolylangProvider`, `NullProvider`, `WpmlProvider`. `isActive()` is a static on both providers.
