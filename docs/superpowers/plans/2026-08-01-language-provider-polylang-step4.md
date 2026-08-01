# LanguageProvider and the Polylang Adapter (Migration Step 4) Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Make a Pediment site multilingual end to end — a real `PolylangProvider` behind the seam step 3 shipped, per-language titles/slugs/patterns in the manifest, translation groups linked on write, the header's navigation bound to the current language, and a generated `wpml-config.xml` — implementing migration step 4 of `docs/superpowers/specs/2026-07-29-pediment-dev-flow-design.md` §4.3.

**Architecture:** The manifest gains a `languages` section (site languages, default first) and a per-entry `languages` block (title/slug/pattern overrides). `wp pediment languages` configures Polylang from that declaration *before* any content is written; `wp pediment seed` refuses to run when the two disagree. `Pediment\Language\PolylangProvider` implements the six-method seam against the `pll_*` API, encapsulating the `lang => ''` idiom exactly once. Everything else in the seeding engine already carries a language — step 4 fills the seam in rather than reworking the phases.

**Tech Stack:** PHP 8.1, WordPress 6.9, Polylang (free build, pinned version), PHPUnit 9.6 with the WP integration suite plus a second Polylang-loading suite, WP-CLI, Playwright, Node 20 for the `wpml-config.xml` generator.

## Global Constraints

- **Never push without explicit user approval.** All work is local until the single gated push in Task 17.
- Work stays on the current branch `pediment-dev-flow-review`, rebased onto `origin/main`. No new branches or worktrees — the Conductor workspace *is* the isolation.
- **Nothing existing is removed or renamed**, so this ships as a **minor** (3.2.0) — conventional `feat:`/`fix:`/`docs:` commits only, no `!`, no `Release-As:` footer. Version files belong to release-please; never hand-bump.
- **Never rename stored data.** `_pediment_seed_key`, `_pediment_seed_hash`, `_pediment_seed_source` keep their exact names. No new post meta is introduced by this plan.
- **The seeder never sets `permalink_structure`** and never hard-flushes. Unchanged from step 3.
- **The seeder never deletes.** Unchanged from step 3.
- **A monolingual site must behave exactly as it does today.** `NullProvider` stays the default; every new code path is a no-op when `languages()` returns `[ '' ]`. The existing 545-test suite is the regression gate for this.
- New PHP lives under `Pediment\Language\` (`plugin/src/Language/`), `Pediment\Seeder\` (`plugin/src/Seeder/`), `Pediment\Cli\` (`plugin/wp-cli/`) — all PSR-4 mapped already. Procedural front-end glue lives in `plugin/inc/` as `pediment_*` snake_case functions.
- Method naming in `src/` is camelCase; short arrays allowed; Yoda and `ValidFunctionName` are excluded in `plugin/phpcs.xml.dist`.
- Every task ends with its suite green locally:
  - PHPUnit: `npx wp-env run tests-wordpress --env-cwd=wp-content/plugins/pediment-ai ./vendor/bin/phpunit --filter <Name>`
  - Polylang PHPUnit: `npx wp-env run tests-wordpress --env-cwd=wp-content/plugins/pediment-ai ./vendor/bin/phpunit -c phpunit-polylang.xml.dist`
  - Lint: `cd plugin && composer lint`, plus `npm run lint:colors` and `npm run lint:blocks` from the root.
  - wp-env is the project-local one (`npx wp-env`), never `@wordpress/env@latest`.
- Working directory: `/Users/jonas/conductor/workspaces/pediment/west-monroe`.

## Design decisions this plan makes beyond the spec

The spec fixes the architecture. Seven gaps had to be closed to make it implementable. Each is deliberate — flag them to the user if a review disagrees.

1. **The manifest declares the site's languages; the seeder does not configure Polylang mid-run.** The spec requires "languages are configured before any content is written" but does not say who configures them. Making phase 4 write plugin settings would put an irreversible side effect inside a run that `--dry-run` promises is inspectable. Instead: `languages` is a manifest section, `wp pediment languages` reconciles Polylang against it, and `wp pediment seed` **hard-errors** when the configured set does not match. The ordering becomes impossible to get wrong rather than merely documented.
2. **A missing per-language slug derives as `<slug>-<lang>`, it is not an error.** Polylang does not hook `wp_unique_post_slug`, so all top-level pages share one slug namespace regardless of language — two languages declaring `home` land as `home` and `home-2`, the Verifier reports a slug mismatch forever, and no re-run converges. Deriving a distinct slug is the only behaviour that converges. `NavSeeder::slugFor()` already uses exactly this idiom.
3. **Missing translations are `notices`, not `problems`.** `RunResult::ok()` is false when `problems` is non-empty, and `SeedCommand` turns that into `WP_CLI::error`. A newly-added language legitimately has no translated patterns yet, so reporting it as a problem would make every fresh multilingual site fail its first seed. `RunResult` gains a third bucket that prints loudly and exits zero.
4. **Per-language patterns are found in the registry as `<pattern>-<lang>`, with the file convention `patterns/<slug>.<lang>.php`.** The spec names files, but the seeder consumes *registered* patterns (`WP_Block_Patterns_Registry`), and WordPress registers theme pattern files under whatever their `Slug:` header says — not their filename. Binding the two conventions (`patterns/about.de.php` carries `Slug: <theme>/about-de`) keeps one lookup rule, and `wp pediment adopt --language=de` generates the correct header itself.
5. **Media and taxonomies are NOT translated.** `PolylangSetup` writes `media_support => 0` and leaves `taxonomies => []`. One attachment and one term set serve every language. This matches the engine's existing contract (media is keyed globally in `MediaMap`; terms are create-only) and removes a large class of per-language drift. Revisit in step 6 if Workation needs it.
6. **Ref-less `core/navigation` is bound at render time.** `plugin/inc/bootstrap.php` seeds a header template part containing `<!-- wp:navigation … /-->` with no `ref`. Core resolves that through `block_core_navigation_get_fallback_ref()`, which returns the **most recently created** `wp_navigation` post — so on a five-language site every language renders whichever nav was seeded last. This is the Workation header outage exactly. A `render_block_data` filter binds a ref-less navigation block to the seeded nav for the current language.
7. **`wpml-config.xml` is generated by a Node tool, checked in CI.** The repo already lints blocks with `tools/lint-blocks.mjs`; a generator in the same place with a `--check` mode fits the existing gate and keeps generation out of the runtime. Array attributes carry no item shape in `block.json` today, so the blocks that need nested keys gain a standard JSON-Schema `items` declaration (Task 14) — the generator reads that rather than guessing.

## File Structure (end state)

```
plugin/src/Language/
  LanguageProvider.php        UNCHANGED interface (step 3)
  NullProvider.php            UNCHANGED
  LanguageRegistry.php        MODIFIED: auto-detects Polylang
  PolylangProvider.php        NEW: the six methods against the pll_* API
  PolylangSetup.php           NEW: reconcile Polylang settings from the manifest
plugin/src/Seeder/
  LanguageSpec.php            NEW: one declared site language
  Manifest.php                MODIFIED: `languages` section + per-entry overrides
  EntrySpec.php               MODIFIED: carries per-language overrides
  ContentResolver.php         MODIFIED: resolve( $entry, $language )
  DesiredState.php            MODIFIED: per-language title/slug/pattern + notices
  Applier.php                 MODIFIED: links translation groups after writing
  NavSeeder.php               MODIFIED: links nav translation groups
  Adopter.php                 MODIFIED: per-language pattern files
  Runner.php                  MODIFIED: phase 0 language reconciliation check
  RunResult.php  Reporter.php MODIFIED: the `notices` bucket
plugin/inc/
  nav-language.php            NEW: bind ref-less core/navigation per language
  polylang-compat.php         NEW: pll_get_post_types filter for wp_navigation
plugin/wp-cli/
  LanguagesCommand.php        NEW: wp pediment languages [--dry-run]
plugin/tests/phpunit/         existing suite, monolingual, unchanged bootstrap
plugin/tests/polylang/        NEW suite: bootstrap.php + the adapter's tests
plugin/phpunit-polylang.xml.dist  NEW
tools/generate-wpml-config.mjs    NEW (root tools/, beside lint-blocks.mjs)
tests/fixtures/client-theme/
  seed/manifest.php           MODIFIED: en + de
  patterns/about.de.php       NEW
plugin/tests/e2e/multilingual.spec.ts  NEW
docs/seeding.md               MODIFIED: the multilingual section
docs/WORDPRESS_TRAPS.md       MODIFIED: the Polylang idioms
```

---

### Task 1: Preflight, baseline, and a pinned Polylang

**Files:**
- Modify: `.wp-env.json`

**Interfaces:**
- Produces: HEAD rebased on `origin/main`, all suites green, Polylang installed in both wp-env environments at a pinned version.

- [ ] **Step 1: Rebase and confirm the starting point**

```bash
git fetch origin
git rebase origin/main
git status --porcelain
grep -m1 "PEDIMENT_AI_VERSION" plugin/plugin.php
ls plugin/src/Language/
```

Expected: clean tree; `plugin/src/Language/` contains `LanguageProvider.php`, `LanguageRegistry.php`, `NullProvider.php`. If those are missing, step 3 has not landed on `main` — STOP and report.

- [ ] **Step 2: Green baseline**

```bash
npm run env:start
npx wp-env run tests-wordpress --env-cwd=wp-content/plugins/pediment-ai ./vendor/bin/phpunit
cd plugin && composer lint && cd ..
npm run lint:colors
npm run lint:blocks
```

Expected: PHPUnit OK (545 tests, 1 skip), phpcs 0 errors, both linters clean. A red baseline is not this plan's bug — report it and stop.

- [ ] **Step 3: Find the current Polylang version and pin it**

```bash
curl -s "https://api.wordpress.org/plugins/info/1.0/polylang.json" | node -e "let s='';process.stdin.on('data',d=>s+=d).on('end',()=>{const j=JSON.parse(s);console.log(j.version, j.tested, j.requires_php)})"
```

Note the version. The plan below writes `<VERSION>` — substitute the real number everywhere. Do not use `latest-stable`: a silent Polylang upgrade turning CI red weeks later is the failure this pin exists to prevent.

- [ ] **Step 4: Add Polylang to wp-env**

In `.wp-env.json`, change the `plugins` array:

```json
  "plugins": [
    "https://downloads.wordpress.org/plugin/polylang.<VERSION>.zip"
  ],
```

- [ ] **Step 5: Restart and verify it is present in BOTH environments**

```bash
npm run env:stop && npm run env:start
npx wp-env run cli wp plugin list --field=name
npx wp-env run tests-cli wp plugin list --field=name
```

Expected: `polylang` appears in both lists. The tests environment matters most — Task 2's suite loads Polylang's files from there.

- [ ] **Step 6: Confirm the existing PHPUnit suite is unaffected**

```bash
npx wp-env run tests-wordpress --env-cwd=wp-content/plugins/pediment-ai ./vendor/bin/phpunit
```

Expected: still 545 tests OK. The WP integration bootstrap loads only what `tests/phpunit/bootstrap.php` requires, so an *activated* Polylang in the tests site must not reach the suite. If this goes red, STOP — the isolation assumption this plan rests on is wrong.

- [ ] **Step 7: Commit**

```bash
git add .wp-env.json
git commit -m "$(cat <<'EOF'
chore(env): install Polylang in wp-env at a pinned version

Step 4 needs a real Polylang to test the adapter against; a stubbed pll_*
surface would prove nothing about the parse_query scoping that caused the
outages this adapter exists to prevent. Pinned rather than latest-stable so a
Polylang release cannot turn CI red on an unrelated day.

Co-Authored-By: Claude Opus 5 (1M context) <noreply@anthropic.com>
EOF
)"
```

---

### Task 2: A PHPUnit suite that boots real Polylang

**Files:**
- Create: `plugin/tests/polylang/bootstrap.php`, `plugin/tests/polylang/HarnessTest.php`
- Create: `plugin/phpunit-polylang.xml.dist`

**Interfaces:**
- Produces: a second PHPUnit configuration whose bootstrap loads Polylang and configures `en` (default) + `de`, and a global helper `pediment_test_languages(): array` returning the configured slugs. Every later Polylang test runs under this config. The default suite is untouched.

This task exists to de-risk everything after it. If Polylang cannot be booted inside the WP test harness, that must be discovered now, not in Task 9.

- [ ] **Step 1: Write the failing test**

Create `plugin/tests/polylang/HarnessTest.php`:

```php
<?php

class HarnessTest extends WP_UnitTestCase {

	public function test_polylang_is_loaded() {
		$this->assertTrue( function_exists( 'pll_languages_list' ), 'Polylang did not load.' );
		$this->assertInstanceOf( 'PLL_Base', PLL() );
	}

	public function test_two_languages_are_configured_default_first() {
		$this->assertSame( [ 'en', 'de' ], pll_languages_list() );
		$this->assertSame( 'en', pll_default_language() );
	}

	public function test_a_post_can_be_tagged_and_read_back() {
		$id = self::factory()->post->create( [ 'post_type' => 'page' ] );
		pll_set_post_language( $id, 'de' );

		$this->assertSame( 'de', pll_get_post_language( $id ) );
	}

	public function test_a_language_scoped_query_hides_the_other_language() {
		$en = self::factory()->post->create( [ 'post_type' => 'page' ] );
		$de = self::factory()->post->create( [ 'post_type' => 'page' ] );
		pll_set_post_language( $en, 'en' );
		pll_set_post_language( $de, 'de' );

		$scoped = get_posts( [ 'post_type' => 'page', 'numberposts' => -1, 'fields' => 'ids', 'lang' => 'en' ] );

		$this->assertContains( $en, $scoped );
		$this->assertNotContains( $de, $scoped, 'Polylang is not scoping queries — the adapter has nothing to escape.' );
	}
}
```

The last test is the important one: it proves Polylang is *actively filtering*, which is the whole premise of `unscopedQuery()`.

- [ ] **Step 2: Write the bootstrap**

Create `plugin/tests/polylang/bootstrap.php`:

```php
<?php
/**
 * PHPUnit bootstrap for the Polylang adapter suite.
 *
 * Separate from tests/phpunit/bootstrap.php on purpose: loading Polylang adds a
 * `language` taxonomy and a parse_query filter to every query in the process,
 * which would change the meaning of the 545 monolingual tests. One process per
 * world.
 */

$_tests_dir = getenv( 'WP_TESTS_DIR' );
if ( ! $_tests_dir ) {
	$_tests_dir = rtrim( sys_get_temp_dir(), '/\\' ) . '/wordpress-tests-lib';
}

if ( ! defined( 'WP_TESTS_PHPUNIT_POLYFILLS_PATH' ) ) {
	define( 'WP_TESTS_PHPUNIT_POLYFILLS_PATH', dirname( __DIR__, 2 ) . '/vendor/yoast/phpunit-polyfills' );
}

require_once $_tests_dir . '/includes/functions.php';

tests_add_filter( 'muplugins_loaded', function () {
	$polylang = WP_PLUGIN_DIR . '/polylang/polylang.php';
	if ( ! is_readable( $polylang ) ) {
		echo "Polylang is not installed at {$polylang}. Run `npm run env:start` first.\n";
		exit( 1 );
	}
	require $polylang;

	require dirname( __DIR__, 2 ) . '/vendor/autoload.php';
	require dirname( __DIR__, 2 ) . '/plugin.php';
} );

require $_tests_dir . '/includes/bootstrap.php';

do_action( 'rest_api_init' );

/**
 * Configure en (default) + de once, before any test runs.
 *
 * Written through PLL()->options->merge() + save(), never update_option():
 * since 3.7 Polylang holds its options in memory and flushes them on shutdown,
 * so a raw option write is both invisible to this process and overwritten at
 * the end of it.
 */
$model    = PLL()->model;
$existing = wp_list_pluck( $model->get_languages_list(), 'slug' );

foreach ( [
	[ 'slug' => 'en', 'name' => 'English', 'locale' => 'en_US', 'flag' => 'gb', 'rtl' => 0, 'term_group' => 0 ],
	[ 'slug' => 'de', 'name' => 'Deutsch', 'locale' => 'de_DE', 'flag' => 'de', 'rtl' => 0, 'term_group' => 1 ],
] as $language ) {
	if ( ! in_array( $language['slug'], $existing, true ) ) {
		$model->add_language( $language );
	}
}

PLL()->options->merge( [ 'default_lang' => 'en', 'hide_default' => 1, 'force_lang' => 1, 'media_support' => 0 ] );
PLL()->options->save();
$model->clean_languages_cache();

/** @return string[] The slugs this harness configured. */
function pediment_test_languages(): array {
	return [ 'en', 'de' ];
}
```

- [ ] **Step 3: Write the PHPUnit configuration**

Create `plugin/phpunit-polylang.xml.dist`:

```xml
<?xml version="1.0"?>
<phpunit
  bootstrap="tests/polylang/bootstrap.php"
  backupGlobals="false"
  colors="true"
  beStrictAboutCoversAnnotation="true"
  beStrictAboutOutputDuringTests="true"
  beStrictAboutTestsThatDoNotTestAnything="false"
  verbose="true">
  <testsuites>
    <testsuite name="polylang">
      <directory>tests/polylang/</directory>
    </testsuite>
  </testsuites>
</phpunit>
```

- [ ] **Step 4: Run it**

```bash
npx wp-env run tests-wordpress --env-cwd=wp-content/plugins/pediment-ai ./vendor/bin/phpunit -c phpunit-polylang.xml.dist
```

Expected: 4 tests PASS. If `WP_UnitTestCase`'s transaction rollback wipes the language terms between tests, move the language creation into a `set_up_before_class()` on a shared base class instead — the terms must survive because the harness creates them before the suite starts. If `pll_languages_list()` returns `[]` inside a test, that is the symptom.

- [ ] **Step 5: Confirm the default suite still passes**

```bash
npx wp-env run tests-wordpress --env-cwd=wp-content/plugins/pediment-ai ./vendor/bin/phpunit
```

Expected: 545 tests OK, unchanged.

- [ ] **Step 6: Add the CI job**

In `.github/workflows/ci.yml`, after the `phpunit` job's phpunit line, add a second run to the same job (one env start, two suites):

```yaml
      - run: npx wp-env run tests-wordpress --env-cwd=wp-content/plugins/pediment-ai ./vendor/bin/phpunit
      - run: npx wp-env run tests-wordpress --env-cwd=wp-content/plugins/pediment-ai ./vendor/bin/phpunit -c phpunit-polylang.xml.dist
```

- [ ] **Step 7: Commit**

```bash
git add plugin/tests/polylang plugin/phpunit-polylang.xml.dist .github/workflows/ci.yml
git commit -m "$(cat <<'EOF'
test(polylang): boot a real Polylang in its own PHPUnit suite

A stubbed pll_* surface cannot prove the one thing that matters — that
Polylang scopes queries through parse_query and that the adapter's escape
hatch actually escapes it. Kept in a separate process from the monolingual
suite: loading Polylang adds a language taxonomy and a query filter that
would change the meaning of all 545 existing tests.

Co-Authored-By: Claude Opus 5 (1M context) <noreply@anthropic.com>
EOF
)"
```

---

### Task 3: PolylangProvider

**Files:**
- Create: `plugin/src/Language/PolylangProvider.php`
- Test: `plugin/tests/polylang/PolylangProviderTest.php`

**Interfaces:**
- Consumes: `Pediment\Language\LanguageProvider` (Task 0, shipped).
- Produces: `Pediment\Language\PolylangProvider` implementing all six methods, plus `public static function isActive(): bool` (Polylang loaded AND at least one language configured). Every later task resolves it through `LanguageRegistry`.

- [ ] **Step 1: Write the failing test**

Create `plugin/tests/polylang/PolylangProviderTest.php`:

```php
<?php

use Pediment\Language\PolylangProvider;

class PolylangProviderTest extends WP_UnitTestCase {

	private PolylangProvider $provider;

	public function set_up(): void {
		parent::set_up();
		$this->provider = new PolylangProvider();
	}

	public function test_is_active_when_languages_are_configured() {
		$this->assertTrue( PolylangProvider::isActive() );
	}

	public function test_languages_are_listed_default_first() {
		$this->assertSame( [ 'en', 'de' ], $this->provider->languages() );
		$this->assertSame( 'en', $this->provider->defaultLanguage() );
	}

	public function test_set_language_tags_a_post() {
		$id = self::factory()->post->create( [ 'post_type' => 'page' ] );
		$this->provider->setLanguage( $id, 'de' );

		$this->assertSame( 'de', pll_get_post_language( $id ) );
	}

	public function test_translation_of_returns_the_post_itself_for_its_own_language() {
		$id = self::factory()->post->create( [ 'post_type' => 'page' ] );
		$this->provider->setLanguage( $id, 'de' );

		$this->assertSame( $id, $this->provider->translationOf( $id, 'de' ) );
	}

	public function test_translation_of_returns_zero_when_there_is_none() {
		$id = self::factory()->post->create( [ 'post_type' => 'page' ] );
		$this->provider->setLanguage( $id, 'en' );

		$this->assertSame( 0, $this->provider->translationOf( $id, 'de' ) );
	}

	public function test_link_translations_makes_each_side_findable_from_the_other() {
		$en = self::factory()->post->create( [ 'post_type' => 'page' ] );
		$de = self::factory()->post->create( [ 'post_type' => 'page' ] );
		$this->provider->setLanguage( $en, 'en' );
		$this->provider->setLanguage( $de, 'de' );

		$this->provider->linkTranslations( [ 'en' => $en, 'de' => $de ] );

		$this->assertSame( $de, $this->provider->translationOf( $en, 'de' ) );
		$this->assertSame( $en, $this->provider->translationOf( $de, 'en' ) );
	}

	public function test_link_translations_ignores_unresolved_ids() {
		$en = self::factory()->post->create( [ 'post_type' => 'page' ] );
		$this->provider->setLanguage( $en, 'en' );

		$this->provider->linkTranslations( [ 'en' => $en, 'de' => 0 ] );

		$this->assertSame( 0, $this->provider->translationOf( $en, 'de' ) );
	}

	public function test_unscoped_query_sees_every_language() {
		$en = self::factory()->post->create( [ 'post_type' => 'page' ] );
		$de = self::factory()->post->create( [ 'post_type' => 'page' ] );
		$this->provider->setLanguage( $en, 'en' );
		$this->provider->setLanguage( $de, 'de' );

		$found = get_posts(
			$this->provider->unscopedQuery(
				[ 'post_type' => 'page', 'numberposts' => -1, 'fields' => 'ids', 'lang' => 'en' ]
			)
		);

		$this->assertContains( $en, $found );
		$this->assertContains( $de, $found, 'unscopedQuery() did not escape the language scoping.' );
	}

	public function test_suppress_filters_alone_does_not_escape_the_scoping() {
		// The regression that cost dd23712. If this ever starts passing with
		// suppress_filters alone, Polylang changed and the comment in
		// unscopedQuery() is stale — but do NOT remove the `lang` key on that
		// basis; WPML still needs suppress_filters.
		$en = self::factory()->post->create( [ 'post_type' => 'page' ] );
		$de = self::factory()->post->create( [ 'post_type' => 'page' ] );
		$this->provider->setLanguage( $en, 'en' );
		$this->provider->setLanguage( $de, 'de' );

		$found = get_posts(
			[ 'post_type' => 'page', 'numberposts' => -1, 'fields' => 'ids', 'lang' => 'en', 'suppress_filters' => true ]
		);

		$this->assertNotContains( $de, $found );
	}
}
```

- [ ] **Step 2: Run it to verify it fails**

```bash
npx wp-env run tests-wordpress --env-cwd=wp-content/plugins/pediment-ai ./vendor/bin/phpunit -c phpunit-polylang.xml.dist --filter PolylangProviderTest
```

Expected: FAIL — `Class "Pediment\Language\PolylangProvider" not found`.

- [ ] **Step 3: Write the implementation**

Create `plugin/src/Language/PolylangProvider.php`:

```php
<?php
/**
 * Polylang implementation of the seeding engine's language seam.
 *
 * Everything Polylang-specific in this product lives here, in
 * PolylangSetup, and in the two files under inc/ that touch the front end.
 * Nothing else may call a pll_* function.
 *
 * @package Pediment
 */

declare(strict_types=1);

namespace Pediment\Language;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class PolylangProvider implements LanguageProvider {
	/**
	 * Whether this provider can actually do its job.
	 *
	 * "Polylang is active" is not enough: an activated-but-unconfigured
	 * Polylang returns an empty language list, and a seeder crossed with zero
	 * languages writes nothing at all while reporting success.
	 */
	public static function isActive(): bool {
		return function_exists( 'pll_languages_list' )
			&& function_exists( 'pll_default_language' )
			&& [] !== (array) pll_languages_list();
	}

	/**
	 * Configured language slugs, default first.
	 *
	 * The order is load-bearing, not cosmetic: DesiredState crosses the
	 * manifest with this list in order, and Applier resolves a child's
	 * post_parent and the front-page option from the default language's IDs.
	 * A default that is not first means children are written before the
	 * parent they point at exists.
	 *
	 * @return string[]
	 */
	public function languages(): array {
		$all     = array_values( array_map( 'strval', (array) pll_languages_list() ) );
		$default = $this->defaultLanguage();

		$rest = array_values( array_filter( $all, static fn( string $slug ): bool => $slug !== $default ) );

		return '' === $default ? $all : array_merge( [ $default ], $rest );
	}

	public function defaultLanguage(): string {
		return (string) pll_default_language();
	}

	public function setLanguage( int $postId, string $language ): void {
		if ( $postId <= 0 || '' === $language ) {
			return;
		}
		pll_set_post_language( $postId, $language );
	}

	/**
	 * @param array<string,int> $map language code => post ID
	 */
	public function linkTranslations( array $map ): void {
		// pll_save_post_translations() REPLACES the whole group. Handing it a
		// map containing a 0 files "no post" under a real language key, which
		// Polylang's validate_translations() then drops — taking whatever post
		// really held that key out of the group with it. Invisible with two
		// languages, silent data loss with five.
		$clean = array_filter(
			$map,
			static fn( $postId, $language ): bool => is_int( $postId ) && $postId > 0 && '' !== $language,
			ARRAY_FILTER_USE_BOTH
		);

		if ( count( $clean ) < 2 ) {
			return;
		}

		pll_save_post_translations( $clean );
	}

	public function translationOf( int $postId, string $language ): int {
		if ( $postId <= 0 || '' === $language ) {
			return 0;
		}

		// Fast path, and the only correct answer for an untranslated post that
		// simply IS the language being asked for.
		if ( (string) pll_get_post_language( $postId ) === $language ) {
			return $postId;
		}

		return (int) pll_get_post( $postId, $language );
	}

	/**
	 * @param array<string,mixed> $args
	 * @return array<string,mixed>
	 */
	public function unscopedQuery( array $args ): array {
		// `suppress_filters` does NOT work here (dd23712). Polylang hooks
		// parse_query and mutates query_vars['tax_query'] directly; WP_Query
		// re-parses that tax query inside get_posts() on a branch gated on
		// `! $this->is_singular`, and nothing on that branch consults
		// suppress_filters, so the language clause survives it.
		//
		// What Polylang does honour is the `lang` query var:
		// PLL_Query::is_already_filtered() treats it as "the caller has
		// decided", and isset() is the whole test — an empty value is enough.
		//
		// suppress_filters is set too, for WPML, which scopes through the
		// posts_* filters this flag turns off. Harmless under Polylang.
		$args['lang']             = '';
		$args['suppress_filters'] = true;

		return $args;
	}
}
```

- [ ] **Step 4: Run the tests**

```bash
npx wp-env run tests-wordpress --env-cwd=wp-content/plugins/pediment-ai ./vendor/bin/phpunit -c phpunit-polylang.xml.dist --filter PolylangProviderTest
```

Expected: 9 tests PASS.

Note: if `test_unscoped_query_sees_every_language` fails because `suppress_filters => true` also disables the meta query the real `StateReader` relies on, drop `suppress_filters` from `unscopedQuery()` and keep only `lang => ''` — then update the comment to say WPML support is deferred. Do not silently keep both if one breaks.

- [ ] **Step 5: Lint and commit**

```bash
cd plugin && composer lint && cd ..
git add plugin/src/Language/PolylangProvider.php plugin/tests/polylang/PolylangProviderTest.php
git commit -m "$(cat <<'EOF'
feat(language): add the Polylang provider

Implements the six-method seam against the pll_* API. Three things are
deliberate and were paid for in production: languages() puts the default
first because Applier resolves parents and the front page from it,
linkTranslations() drops zero IDs because pll_save_post_translations()
replaces the whole group, and unscopedQuery() sets `lang => ''` because
suppress_filters does not escape Polylang's parse_query scoping.

Co-Authored-By: Claude Opus 5 (1M context) <noreply@anthropic.com>
EOF
)"
```

---

### Task 4: Registry auto-detection

**Files:**
- Modify: `plugin/src/Language/LanguageRegistry.php`
- Test: `plugin/tests/polylang/RegistryDetectionTest.php`, `plugin/tests/phpunit/Language/LanguageProviderTest.php`

**Interfaces:**
- Produces: `LanguageRegistry::provider()` returns `PolylangProvider` when `PolylangProvider::isActive()`, else `NullProvider`. The `pediment_language_provider` filter still overrides both.

- [ ] **Step 1: Write the failing test**

Create `plugin/tests/polylang/RegistryDetectionTest.php`:

```php
<?php

use Pediment\Language\LanguageRegistry;
use Pediment\Language\NullProvider;
use Pediment\Language\PolylangProvider;

class RegistryDetectionTest extends WP_UnitTestCase {

	public function tear_down(): void {
		remove_all_filters( 'pediment_language_provider' );
		LanguageRegistry::reset();
		parent::tear_down();
	}

	public function test_polylang_is_detected() {
		LanguageRegistry::reset();
		$this->assertInstanceOf( PolylangProvider::class, LanguageRegistry::provider() );
	}

	public function test_the_filter_still_wins() {
		add_filter( 'pediment_language_provider', static fn() => new NullProvider() );
		LanguageRegistry::reset();

		$this->assertInstanceOf( NullProvider::class, LanguageRegistry::provider() );
	}
}
```

Add to the existing `plugin/tests/phpunit/Language/LanguageProviderTest.php` (monolingual suite — Polylang is not loaded there, so this asserts the fallback):

```php
	public function test_null_provider_when_polylang_is_absent() {
		LanguageRegistry::reset();
		$this->assertInstanceOf( NullProvider::class, LanguageRegistry::provider() );
	}
```

- [ ] **Step 2: Run both to verify the new one fails**

```bash
npx wp-env run tests-wordpress --env-cwd=wp-content/plugins/pediment-ai ./vendor/bin/phpunit -c phpunit-polylang.xml.dist --filter RegistryDetectionTest
```

Expected: `test_polylang_is_detected` FAILS — the registry still returns `NullProvider`.

- [ ] **Step 3: Write the implementation**

In `plugin/src/Language/LanguageRegistry.php`, replace the body of `provider()`:

```php
	public static function provider(): LanguageProvider {
		if ( self::$provider instanceof LanguageProvider ) {
			return self::$provider;
		}

		// Detection, not configuration: an activated-but-unconfigured Polylang
		// is NOT multilingual, and treating it as one crosses the manifest with
		// zero languages and writes nothing while reporting success.
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
```

Note the last line changed from `new NullProvider()` to `$detected` — a filter returning nonsense on a multilingual site must not silently demote it to monolingual.

- [ ] **Step 4: Run both suites**

```bash
npx wp-env run tests-wordpress --env-cwd=wp-content/plugins/pediment-ai ./vendor/bin/phpunit -c phpunit-polylang.xml.dist
npx wp-env run tests-wordpress --env-cwd=wp-content/plugins/pediment-ai ./vendor/bin/phpunit --filter LanguageProviderTest
```

Expected: both PASS.

- [ ] **Step 5: Commit**

```bash
cd plugin && composer lint && cd ..
git add plugin/src/Language/LanguageRegistry.php plugin/tests/polylang/RegistryDetectionTest.php plugin/tests/phpunit/Language/LanguageProviderTest.php
git commit -m "$(cat <<'EOF'
feat(language): detect Polylang in the registry

An activated-but-unconfigured Polylang stays monolingual: an empty language
list crossed with the manifest seeds nothing and reports success. A filter
that returns something invalid now falls back to what was detected, not to
NullProvider — demoting a multilingual site by accident is the worse failure.

Co-Authored-By: Claude Opus 5 (1M context) <noreply@anthropic.com>
EOF
)"
```

---

### Task 5: The manifest's `languages` section

**Files:**
- Create: `plugin/src/Seeder/LanguageSpec.php`
- Modify: `plugin/src/Seeder/Manifest.php`
- Test: `plugin/tests/phpunit/Seeder/ManifestTest.php`

**Interfaces:**
- Produces: `Pediment\Seeder\LanguageSpec` with readonly `slug`, `name`, `locale`, `flag`, `isDefault`; `Manifest::languages(): array<string,LanguageSpec>` (declaration order, default first) and `Manifest::defaultLanguage(): string` (`''` when none declared). A manifest with no `languages` section stays monolingual — `languages()` returns `[]`.

- [ ] **Step 1: Write the failing test**

Add to `plugin/tests/phpunit/Seeder/ManifestTest.php`:

```php
	public function test_languages_default_to_none() {
		$manifest = Manifest::fromArray(
			[ 'pages' => [ 'home' => [ 'title' => 'Home', 'content' => '' ] ] ],
			get_stylesheet_directory()
		);

		$this->assertSame( [], $manifest->languages() );
		$this->assertSame( '', $manifest->defaultLanguage() );
	}

	public function test_languages_are_parsed_default_first() {
		$manifest = Manifest::fromArray(
			[
				'languages' => [
					'de' => [ 'name' => 'Deutsch', 'locale' => 'de_DE', 'flag' => 'de' ],
					'en' => [ 'name' => 'English', 'locale' => 'en_US', 'flag' => 'gb', 'default' => true ],
				],
				'pages'     => [ 'home' => [ 'title' => 'Home', 'content' => '' ] ],
			],
			get_stylesheet_directory()
		);

		$this->assertSame( [ 'en', 'de' ], array_keys( $manifest->languages() ) );
		$this->assertSame( 'en', $manifest->defaultLanguage() );
		$this->assertSame( 'Deutsch', $manifest->languages()['de']->name );
		$this->assertSame( 'de_DE', $manifest->languages()['de']->locale );
		$this->assertTrue( $manifest->languages()['en']->isDefault );
	}

	public function test_the_first_declared_language_is_the_default_when_none_says_so() {
		$manifest = Manifest::fromArray(
			[
				'languages' => [ 'nl' => [ 'name' => 'Nederlands', 'locale' => 'nl_NL' ], 'fr' => [ 'name' => 'Français', 'locale' => 'fr_FR' ] ],
				'pages'     => [ 'home' => [ 'title' => 'Home', 'content' => '' ] ],
			],
			get_stylesheet_directory()
		);

		$this->assertSame( 'nl', $manifest->defaultLanguage() );
	}

	public function test_two_defaults_are_rejected() {
		$this->expectException( ManifestError::class );
		$this->expectExceptionMessageMatches( '/only one language may be the default/i' );

		Manifest::fromArray(
			[
				'languages' => [
					'en' => [ 'name' => 'English', 'locale' => 'en_US', 'default' => true ],
					'de' => [ 'name' => 'Deutsch', 'locale' => 'de_DE', 'default' => true ],
				],
			],
			get_stylesheet_directory()
		);
	}

	public function test_a_language_needs_a_locale() {
		$this->expectException( ManifestError::class );
		$this->expectExceptionMessageMatches( "/languages\.de: 'locale' is required/" );

		Manifest::fromArray(
			[ 'languages' => [ 'de' => [ 'name' => 'Deutsch' ] ] ],
			get_stylesheet_directory()
		);
	}

	public function test_an_unknown_language_key_is_rejected() {
		$this->expectException( ManifestError::class );
		$this->expectExceptionMessageMatches( "/languages\.de: unknown key 'lokale'/" );

		Manifest::fromArray(
			[ 'languages' => [ 'de' => [ 'name' => 'Deutsch', 'locale' => 'de_DE', 'lokale' => 'x' ] ] ],
			get_stylesheet_directory()
		);
	}

	public function test_a_language_slug_must_be_a_slug() {
		$this->expectException( ManifestError::class );
		$this->expectExceptionMessageMatches( '/is not a valid language code/' );

		Manifest::fromArray(
			[ 'languages' => [ 'de DE' => [ 'name' => 'Deutsch', 'locale' => 'de_DE' ] ] ],
			get_stylesheet_directory()
		);
	}
```

- [ ] **Step 2: Run to verify it fails**

```bash
npx wp-env run tests-wordpress --env-cwd=wp-content/plugins/pediment-ai ./vendor/bin/phpunit --filter ManifestTest
```

Expected: FAIL — `Unknown manifest section 'languages'`.

- [ ] **Step 3: Write LanguageSpec**

Create `plugin/src/Seeder/LanguageSpec.php`:

```php
<?php
declare(strict_types=1);

namespace Pediment\Seeder;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class LanguageSpec {
	public function __construct(
		public readonly string $slug,
		public readonly string $name,
		public readonly string $locale,
		public readonly string $flag,
		public readonly bool $isDefault
	) {}
}
```

- [ ] **Step 4: Parse it in Manifest**

In `plugin/src/Seeder/Manifest.php`:

Add `'languages'` to `SECTIONS`:

```php
	private const SECTIONS = [ 'version', 'languages', 'pages', 'posts', 'entries', 'media', 'navs', 'post_types', 'site' ];
```

Add the allowed sub-keys constant next to `ENTRY_KEYS`:

```php
	/** Keys a language may declare. */
	private const LANGUAGE_KEYS = [ 'name', 'locale', 'flag', 'default' ];
```

Add two constructor properties — `private array $languages` and `private string $defaultLanguage` — after `$postTypes`, before `$siteLogo`:

```php
	/**
	 * @param array<string,EntrySpec>    $entries
	 * @param array<string,MediaSpec>    $media
	 * @param array<string,NavSpec>      $navs
	 * @param array<string,PostTypeSpec> $postTypes
	 * @param array<string,LanguageSpec> $languages
	 */
	private function __construct(
		private string $path,
		private string $baseDir,
		private array $entries,
		private array $media,
		private array $navs,
		private array $postTypes,
		private string $siteLogo,
		private array $languages,
		private string $defaultLanguage
	) {}
```

In `fromArray()`, immediately after the section-name validation loop, add:

```php
		[ $languages, $defaultLanguage ] = self::parseLanguages( (array) ( $raw['languages'] ?? [] ) );
```

and extend the final `return new self( … )` with `$languages, $defaultLanguage`.

Add the parser and the two accessors:

```php
	/**
	 * @param array<string,mixed> $raw
	 * @return array{0:array<string,LanguageSpec>,1:string}
	 * @throws ManifestError When the languages fail validation.
	 */
	private static function parseLanguages( array $raw ): array {
		if ( [] === $raw ) {
			return [ [], '' ];
		}

		$specs    = [];
		$defaults = [];

		foreach ( $raw as $slug => $declared ) {
			$slug     = (string) $slug;
			$declared = (array) $declared;

			foreach ( array_keys( $declared ) as $key ) {
				if ( ! in_array( (string) $key, self::LANGUAGE_KEYS, true ) ) {
					throw new ManifestError( "languages.{$slug}: unknown key '{$key}'. Allowed: " . implode( ', ', self::LANGUAGE_KEYS ) . '.' ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- operator-facing message.
				}
			}

			// Polylang builds URL prefixes straight from this, and it is also the
			// suffix a derived per-language slug carries. Anything sanitize_title()
			// would rewrite produces URLs nobody declared.
			if ( '' === $slug || sanitize_title( $slug ) !== $slug ) {
				throw new ManifestError( "languages.{$slug}: '{$slug}' is not a valid language code — use a lowercase slug such as 'en' or 'pt-br'." ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- operator-facing message.
			}

			$locale = (string) ( $declared['locale'] ?? '' );
			if ( '' === $locale ) {
				throw new ManifestError( "languages.{$slug}: 'locale' is required (for example 'de_DE')." ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- operator-facing message.
			}

			$isDefault = ! empty( $declared['default'] );
			if ( $isDefault ) {
				$defaults[] = $slug;
			}

			$specs[ $slug ] = new LanguageSpec(
				$slug,
				(string) ( $declared['name'] ?? strtoupper( $slug ) ),
				$locale,
				(string) ( $declared['flag'] ?? '' ),
				$isDefault
			);
		}

		if ( count( $defaults ) > 1 ) {
			throw new ManifestError( 'Only one language may be the default; got: ' . implode( ', ', $defaults ) . '.' ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- operator-facing message.
		}

		$default = $defaults[0] ?? (string) array_key_first( $specs );

		// Default first. Everything downstream depends on this order: the
		// Applier resolves a child's post_parent and the front-page option from
		// the default language's IDs, and translation groups are keyed off it.
		$ordered = [ $default => $specs[ $default ] ];
		foreach ( $specs as $slug => $spec ) {
			if ( $slug !== $default ) {
				$ordered[ $slug ] = $spec;
			}
		}

		// Re-stamp isDefault so a manifest that declared none still has exactly
		// one language reporting itself as the default.
		$ordered[ $default ] = new LanguageSpec(
			$ordered[ $default ]->slug,
			$ordered[ $default ]->name,
			$ordered[ $default ]->locale,
			$ordered[ $default ]->flag,
			true
		);

		return [ $ordered, $default ];
	}

	/** @return array<string,LanguageSpec> Declared site languages, default first; empty when monolingual. */
	public function languages(): array {
		return $this->languages;
	}

	/** The declared default language code, or '' when the manifest declares none. */
	public function defaultLanguage(): string {
		return $this->defaultLanguage;
	}
```

- [ ] **Step 5: Run the tests**

```bash
npx wp-env run tests-wordpress --env-cwd=wp-content/plugins/pediment-ai ./vendor/bin/phpunit --filter ManifestTest
```

Expected: PASS, including every pre-existing `ManifestTest` case.

- [ ] **Step 6: Commit**

```bash
cd plugin && composer lint && cd ..
git add plugin/src/Seeder/LanguageSpec.php plugin/src/Seeder/Manifest.php plugin/tests/phpunit/Seeder/ManifestTest.php
git commit -m "$(cat <<'EOF'
feat(seeder): declare site languages in the manifest

Git owns structure, and which languages a site has is structure. Parsed
default-first because everything downstream — parent resolution, the
front-page option, translation-group keys — reads the default language's IDs
first. A manifest with no `languages` section stays exactly as monolingual as
it is today.

Co-Authored-By: Claude Opus 5 (1M context) <noreply@anthropic.com>
EOF
)"
```

---

### Task 6: PolylangSetup and `wp pediment languages`

**Files:**
- Create: `plugin/src/Language/PolylangSetup.php`, `plugin/wp-cli/LanguagesCommand.php`
- Modify: `plugin/plugin.php` (register the command alongside `seed`/`adopt`)
- Test: `plugin/tests/polylang/PolylangSetupTest.php`

**Interfaces:**
- Consumes: `Manifest::languages()`, `Manifest::defaultLanguage()` (Task 5).
- Produces: `Pediment\Language\PolylangSetup::configure( array $languages, string $default, bool $dryRun = false ): array` returning `['changes' => string[], 'errors' => string[]]`, and `wp pediment languages [--dry-run]`.

- [ ] **Step 1: Write the failing test**

Create `plugin/tests/polylang/PolylangSetupTest.php`:

```php
<?php

use Pediment\Language\PolylangSetup;
use Pediment\Seeder\LanguageSpec;

class PolylangSetupTest extends WP_UnitTestCase {

	/** @return array<string,LanguageSpec> */
	private function specs(): array {
		return [
			'en' => new LanguageSpec( 'en', 'English', 'en_US', 'gb', true ),
			'de' => new LanguageSpec( 'de', 'Deutsch', 'de_DE', 'de', false ),
		];
	}

	public function test_an_already_configured_site_reports_no_changes() {
		$result = ( new PolylangSetup() )->configure( $this->specs(), 'en' );

		$this->assertSame( [], $result['errors'] );
		$this->assertSame( [], $result['changes'], 'The harness already configured en + de; configure() must be idempotent.' );
	}

	public function test_a_dry_run_reports_a_missing_language_without_creating_it() {
		$specs       = $this->specs();
		$specs['fr'] = new LanguageSpec( 'fr', 'Français', 'fr_FR', 'fr', false );

		$result = ( new PolylangSetup() )->configure( $specs, 'en', true );

		$this->assertNotEmpty( $result['changes'] );
		$this->assertStringContainsString( 'fr', implode( "\n", $result['changes'] ) );
		$this->assertNotContains( 'fr', pll_languages_list(), 'A dry run wrote a language.' );
	}

	public function test_wp_navigation_is_translatable() {
		( new PolylangSetup() )->configure( $this->specs(), 'en' );

		$this->assertContains( 'wp_navigation', (array) PLL()->options['post_types'] );
	}

	public function test_media_and_taxonomies_are_not_translated() {
		( new PolylangSetup() )->configure( $this->specs(), 'en' );

		$this->assertSame( 0, (int) PLL()->options['media_support'] );
		$this->assertSame( [], (array) PLL()->options['taxonomies'] );
	}

	public function test_language_roots_serve_the_front_page() {
		( new PolylangSetup() )->configure( $this->specs(), 'en' );

		$this->assertSame( 1, (int) PLL()->options['redirect_lang'] );
	}

	public function test_a_missing_language_is_created() {
		$specs       = $this->specs();
		$specs['it'] = new LanguageSpec( 'it', 'Italiano', 'it_IT', 'it', false );

		$result = ( new PolylangSetup() )->configure( $specs, 'en' );

		$this->assertSame( [], $result['errors'] );
		$this->assertContains( 'it', pll_languages_list() );
	}

	public function test_it_refuses_to_run_without_polylang_configured_state() {
		$result = ( new PolylangSetup() )->configure( [], 'en' );

		$this->assertNotEmpty( $result['errors'] );
	}
}
```

- [ ] **Step 2: Run to verify it fails**

```bash
npx wp-env run tests-wordpress --env-cwd=wp-content/plugins/pediment-ai ./vendor/bin/phpunit -c phpunit-polylang.xml.dist --filter PolylangSetupTest
```

Expected: FAIL — class not found.

- [ ] **Step 3: Write PolylangSetup**

Create `plugin/src/Language/PolylangSetup.php`:

```php
<?php
/**
 * Reconcile Polylang's own settings against the manifest's `languages`.
 *
 * Deliberately NOT part of a seed run: phase 4 must stay inspectable by
 * --dry-run, and writing another plugin's settings inside it is not. This runs
 * from `wp pediment languages`, before any content is written (spec §4.3).
 *
 * Polylang's free build ships no WP-CLI commands, so everything goes through
 * the PLL() API.
 *
 * @package Pediment
 */

declare(strict_types=1);

namespace Pediment\Language;

use Pediment\Seeder\LanguageSpec;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class PolylangSetup {
	/**
	 * @param array<string,LanguageSpec> $languages Declaration order, default first.
	 * @return array{changes:string[],errors:string[]}
	 */
	public function configure( array $languages, string $default, bool $dryRun = false ): array {
		$changes = [];
		$errors  = [];

		if ( ! function_exists( 'PLL' ) || ! PLL() ) {
			return [ 'changes' => [], 'errors' => [ 'Polylang is not active — install and activate it, or remove the manifest\'s `languages` section.' ] ];
		}
		if ( [] === $languages ) {
			return [ 'changes' => [], 'errors' => [ 'The manifest declares no languages — nothing to configure.' ] ];
		}

		$model    = PLL()->model;
		$existing = wp_list_pluck( $model->get_languages_list(), 'slug' );

		$index = 0;
		foreach ( $languages as $spec ) {
			if ( in_array( $spec->slug, $existing, true ) ) {
				++$index;
				continue;
			}

			$changes[] = sprintf( 'create language %s (%s, %s)', $spec->slug, $spec->name, $spec->locale );

			if ( $dryRun ) {
				++$index;
				continue;
			}

			// term_group is how Polylang orders languages, and the manifest's
			// declaration order is the one the site owner reasoned about.
			$added = $model->add_language(
				[
					'slug'       => $spec->slug,
					'name'       => $spec->name,
					'locale'     => $spec->locale,
					'flag'       => $spec->flag,
					'rtl'        => 0,
					'term_group' => $index,
				]
			);
			if ( is_wp_error( $added ) ) {
				$errors[] = sprintf( 'languages.%s: %s', $spec->slug, $added->get_error_message() );
			}
			++$index;
		}

		if ( ! $dryRun ) {
			$model->clean_languages_cache();
		}

		$options = PLL()->options;
		$desired = [
			'default_lang'  => $default,

			// wp_navigation must be translatable or a menu cannot exist per
			// language — and it can never be ticked by hand: Polylang's settings
			// screen lists only post types registered `public => true` and
			// `_builtin => false`, and wp_navigation is neither.
			'post_types'    => array_values( array_unique( array_merge( (array) $options['post_types'], [ 'wp_navigation' ] ) ) ),

			// One attachment and one term set serve every language. MediaMap
			// keys media globally and the engine's terms are create-only, so
			// per-language copies would drift with nothing to reconcile them.
			'media_support' => 0,
			'taxonomies'    => [],

			// Serve each language at its own root: /de/, not /de/startseite/.
			// Polylang defaults redirect_lang to 0, which makes a language's home
			// URL the permalink of its translated front page — every /de/ request
			// then 301s to /de/startseite/, and hreflang, the switcher and every
			// menu home link follow it there.
			'redirect_lang' => 1,

			// The default language keeps unprefixed URLs, which is what existing
			// single-language sites (and the e2e suite) already assume.
			'hide_default'  => 1,
		];

		$diff = [];
		foreach ( $desired as $key => $value ) {
			if ( (array) $options[ $key ] !== (array) $value ) {
				$diff[] = sprintf( 'set %s', $key );
			}
		}
		$changes = array_merge( $changes, $diff );

		if ( ! $dryRun && [] !== $diff ) {
			// Written through Polylang's own options object, never
			// update_option(). Since 3.7 Polylang holds its options in memory and
			// flushes them on `shutdown`: a raw write is invisible to the rest of
			// this process AND gets overwritten by the stale in-memory copy at
			// the end of it. merge() also applies keys in Polylang's registration
			// order, which matters because some options validate against others.
			$saved = $options->merge( $desired );
			if ( is_wp_error( $saved ) && $saved->has_errors() ) {
				$errors[] = 'Polylang rejected an option write — ' . implode( '; ', $saved->get_error_messages() );
			}
			$options->save();

			// Every language object caches the home URL derived from those
			// options, and that cache outlives the write. Without dropping it, a
			// re-run saves the new setting and still serves the old URLs — which
			// reads exactly like "the fix did not work".
			$model->clean_languages_cache();
		}

		return [ 'changes' => $changes, 'errors' => $errors ];
	}
}
```

- [ ] **Step 4: Write the command**

Create `plugin/wp-cli/LanguagesCommand.php`:

```php
<?php
/**
 * WP-CLI: `wp pediment languages`.
 *
 * @package Pediment
 */

declare(strict_types=1);

namespace Pediment\Cli;

use Pediment\Language\PolylangSetup;
use Pediment\Seeder\Manifest;
use Pediment\Seeder\ManifestError;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Configures the multilingual plugin from the active theme's seed manifest.
 */
final class LanguagesCommand {
	/**
	 * ## OPTIONS
	 *
	 * [--dry-run]
	 * : Print what would change and exit without writing anything.
	 *
	 * ## EXAMPLES
	 *
	 *     wp pediment languages --dry-run
	 *     wp pediment languages
	 *
	 * @when after_wp_load
	 *
	 * @param array<int,string>    $args      Positional args (unused).
	 * @param array<string,string> $assocArgs Associative args.
	 */
	public function __invoke( array $args, array $assocArgs ): void {
		$dryRun = isset( $assocArgs['dry-run'] );

		Manifest::resetCache();
		try {
			$manifest = Manifest::load();
		} catch ( ManifestError $e ) {
			if ( class_exists( '\WP_CLI' ) ) {
				\WP_CLI::error( $e->getMessage() );
			}
			return;
		}

		if ( null === $manifest ) {
			if ( class_exists( '\WP_CLI' ) ) {
				\WP_CLI::error( sprintf( 'No seed manifest found. Create %s/%s in the active theme.', get_stylesheet(), Manifest::RELATIVE_PATH ) );
			}
			return;
		}

		if ( [] === $manifest->languages() ) {
			if ( class_exists( '\WP_CLI' ) ) {
				\WP_CLI::success( 'The manifest declares no languages — this site is monolingual. Nothing to do.' );
			}
			return;
		}

		$result = ( new PolylangSetup() )->configure( $manifest->languages(), $manifest->defaultLanguage(), $dryRun );

		if ( ! class_exists( '\WP_CLI' ) ) {
			return;
		}

		foreach ( $result['changes'] as $change ) {
			\WP_CLI::line( ( $dryRun ? 'would ' : '' ) . $change );
		}
		if ( [] === $result['changes'] ) {
			\WP_CLI::line( 'Nothing to change.' );
		}
		foreach ( $result['errors'] as $error ) {
			\WP_CLI::warning( $error );
		}

		if ( [] !== $result['errors'] ) {
			\WP_CLI::error( 'Language configuration did not complete cleanly.' );
		}

		\WP_CLI::success( $dryRun ? 'Dry run complete — nothing was written.' : 'Languages configured.' );
	}
}
```

- [ ] **Step 5: Register the command**

In `plugin/plugin.php`, beside the existing `seed` and `adopt` registrations, add:

```php
	\WP_CLI::add_command( 'pediment languages', \Pediment\Cli\LanguagesCommand::class );
```

Match the surrounding registration style exactly — read the existing two lines first and mirror them.

- [ ] **Step 6: Run the tests**

```bash
npx wp-env run tests-wordpress --env-cwd=wp-content/plugins/pediment-ai ./vendor/bin/phpunit -c phpunit-polylang.xml.dist --filter PolylangSetupTest
```

Expected: 7 tests PASS. Order matters here — `test_an_already_configured_site_reports_no_changes` runs against the harness's `en`+`de`, so if a later test's `it`/`fr` leaks into it, add `--filter` isolation or assert on a subset rather than emptiness.

- [ ] **Step 7: Commit**

```bash
cd plugin && composer lint && cd ..
git add plugin/src/Language/PolylangSetup.php plugin/wp-cli/LanguagesCommand.php plugin/plugin.php plugin/tests/polylang/PolylangSetupTest.php
git commit -m "$(cat <<'EOF'
feat(language): configure Polylang from the manifest

`wp pediment languages` reconciles Polylang's own settings against the
manifest's `languages` section — separate from a seed run, because phase 4
must stay inspectable by --dry-run and writing another plugin's settings
inside it is not. Carries the four idioms that cost a live site its header:
options via PLL()->options (not update_option), wp_navigation forced
translatable, redirect_lang on, and the language cache dropped after the write.

Co-Authored-By: Claude Opus 5 (1M context) <noreply@anthropic.com>
EOF
)"
```

---

### Task 7: The Runner refuses to seed into a mismatched language set

**Files:**
- Modify: `plugin/src/Seeder/Runner.php`
- Test: `plugin/tests/polylang/RunnerLanguageGateTest.php`, `plugin/tests/phpunit/Seeder/RunnerTest.php`

**Interfaces:**
- Produces: `Runner::run()` returns a `RunResult` with errors and an empty plan when `Manifest::languages()` keys differ from `LanguageProvider::languages()`. The message names both sets and the command that fixes it.

- [ ] **Step 1: Write the failing test**

Create `plugin/tests/polylang/RunnerLanguageGateTest.php`:

```php
<?php

use Pediment\Seeder\Runner;

class RunnerLanguageGateTest extends WP_UnitTestCase {

	public function tear_down(): void {
		remove_all_filters( 'pediment_seed_manifest' );
		\Pediment\Seeder\Manifest::resetCache();
		parent::tear_down();
	}

	/** @param array<string,mixed> $manifest */
	private function withManifest( array $manifest ): void {
		add_filter( 'pediment_seed_manifest', static fn() => $manifest );
		\Pediment\Seeder\Manifest::resetCache();
	}

	public function test_a_manifest_language_polylang_does_not_have_blocks_the_run() {
		$this->withManifest(
			[
				'languages' => [
					'en' => [ 'name' => 'English', 'locale' => 'en_US', 'default' => true ],
					'de' => [ 'name' => 'Deutsch', 'locale' => 'de_DE' ],
					'fr' => [ 'name' => 'Français', 'locale' => 'fr_FR' ],
				],
				'pages'     => [ 'home' => [ 'title' => 'Home', 'content' => '' ] ],
			]
		);

		$result = ( new Runner() )->run();

		$this->assertFalse( $result->applied );
		$this->assertNotEmpty( $result->errors );
		$this->assertStringContainsString( 'fr', $result->errors[0] );
		$this->assertStringContainsString( 'wp pediment languages', $result->errors[0] );
	}

	public function test_a_matching_set_runs() {
		$this->withManifest(
			[
				'languages' => [
					'en' => [ 'name' => 'English', 'locale' => 'en_US', 'default' => true ],
					'de' => [ 'name' => 'Deutsch', 'locale' => 'de_DE' ],
				],
				'pages'     => [ 'home' => [ 'title' => 'Home', 'content' => '' ] ],
			]
		);

		$result = ( new Runner() )->run( [ 'dry_run' => true ] );

		$this->assertSame( [], $result->errors );
	}
}
```

Add to `plugin/tests/phpunit/Seeder/RunnerTest.php` (monolingual suite):

```php
	public function test_a_manifest_declaring_languages_without_a_multilingual_plugin_is_blocked() {
		add_filter(
			'pediment_seed_manifest',
			static fn() => [
				'languages' => [ 'en' => [ 'name' => 'English', 'locale' => 'en_US', 'default' => true ], 'de' => [ 'name' => 'Deutsch', 'locale' => 'de_DE' ] ],
				'pages'     => [ 'home' => [ 'title' => 'Home', 'content' => '' ] ],
			]
		);
		Manifest::resetCache();

		$result = ( new Runner() )->run();

		$this->assertFalse( $result->applied );
		$this->assertNotEmpty( $result->errors );

		remove_all_filters( 'pediment_seed_manifest' );
		Manifest::resetCache();
	}
```

- [ ] **Step 2: Run to verify both fail**

```bash
npx wp-env run tests-wordpress --env-cwd=wp-content/plugins/pediment-ai ./vendor/bin/phpunit -c phpunit-polylang.xml.dist --filter RunnerLanguageGateTest
npx wp-env run tests-wordpress --env-cwd=wp-content/plugins/pediment-ai ./vendor/bin/phpunit --filter test_a_manifest_declaring_languages_without
```

Expected: FAIL — the runs succeed instead of erroring.

- [ ] **Step 3: Write the gate**

In `plugin/src/Seeder/Runner.php`, immediately after the `if ( null === $manifest )` block and before `$mediaSeeder = new MediaSeeder();`:

```php
			$mismatch = $this->languageMismatch( $manifest );
			if ( null !== $mismatch ) {
				return new RunResult( new Plan(), false, $manifest->path(), [ $mismatch ] );
			}
```

And add the method:

```php
	/**
	 * Whether the configured languages disagree with the manifest's.
	 *
	 * Seeding into a language set the site does not actually have is the
	 * failure this returns instead of: content written with no language, which
	 * is invisible to every translation lookup and has previously removed a
	 * live site's navigation outright. The manifest is the declaration; the
	 * plugin's configuration must already match it (spec §4.3).
	 *
	 * @return string|null The operator-facing message, or null when they agree.
	 */
	private function languageMismatch( Manifest $manifest ): ?string {
		$declared = array_keys( $manifest->languages() );

		// A monolingual manifest imposes nothing: a site may run Polylang for
		// reasons of its own and still seed a single-language theme.
		if ( [] === $declared ) {
			return null;
		}

		$configured = $this->lang->languages();
		sort( $declared );

		$sortedConfigured = $configured;
		sort( $sortedConfigured );

		if ( $declared === $sortedConfigured ) {
			return null;
		}

		return sprintf(
			'Language mismatch: the manifest declares [%s] but this site has [%s] configured. Run `wp pediment languages` first — seeding into the wrong language set writes content no translation lookup can find.',
			implode( ', ', $declared ),
			'' === implode( '', $configured ) ? 'none' : implode( ', ', $configured )
		);
	}
```

- [ ] **Step 4: Run the tests**

```bash
npx wp-env run tests-wordpress --env-cwd=wp-content/plugins/pediment-ai ./vendor/bin/phpunit -c phpunit-polylang.xml.dist --filter RunnerLanguageGateTest
npx wp-env run tests-wordpress --env-cwd=wp-content/plugins/pediment-ai ./vendor/bin/phpunit --filter RunnerTest
```

Expected: both PASS, and every pre-existing `RunnerTest` case still passes — a manifest with no `languages` section must be entirely unaffected.

- [ ] **Step 5: Commit**

```bash
cd plugin && composer lint && cd ..
git add plugin/src/Seeder/Runner.php plugin/tests/polylang/RunnerLanguageGateTest.php plugin/tests/phpunit/Seeder/RunnerTest.php
git commit -m "$(cat <<'EOF'
feat(seeder): refuse to seed into a mismatched language set

"Languages are configured before any content is written" becomes impossible
to get wrong rather than merely documented: the manifest declares the set,
and a run whose site does not match it stops with both lists named and the
command that fixes it. A monolingual manifest imposes nothing.

Co-Authored-By: Claude Opus 5 (1M context) <noreply@anthropic.com>
EOF
)"
```

---

### Task 8: Per-language entry overrides in the manifest

**Files:**
- Modify: `plugin/src/Seeder/EntrySpec.php`, `plugin/src/Seeder/Manifest.php`
- Test: `plugin/tests/phpunit/Seeder/ManifestTest.php`

**Interfaces:**
- Produces: `EntrySpec::$translations` (`array<string,array{title?:string,slug?:string,pattern?:string}>`) plus three resolvers used by every later task:
  - `EntrySpec::titleFor( string $language, string $default ): string`
  - `EntrySpec::slugFor( string $language, string $default ): string`
  - `EntrySpec::patternFor( string $language, string $default ): ?string`

  In each, `$default` is the site's default language code. For that language, or when no override exists, they return the top-level value — except `slugFor()`, which derives `<slug>-<lang>` for a non-default language with no explicit slug.

- [ ] **Step 1: Write the failing test**

Add to `plugin/tests/phpunit/Seeder/ManifestTest.php`:

```php
	private function bilingual( array $pageDeclaration ): EntrySpec {
		$manifest = Manifest::fromArray(
			[
				'languages' => [ 'en' => [ 'name' => 'English', 'locale' => 'en_US', 'default' => true ], 'de' => [ 'name' => 'Deutsch', 'locale' => 'de_DE' ] ],
				'pages'     => [ 'about' => $pageDeclaration ],
			],
			get_stylesheet_directory()
		);
		return $manifest->entries()['about'];
	}

	public function test_language_overrides_are_parsed() {
		$spec = $this->bilingual(
			[
				'title'     => 'About',
				'pattern'   => 'x/about',
				'languages' => [ 'de' => [ 'title' => 'Über uns', 'slug' => 'ueber-uns' ] ],
			]
		);

		$this->assertSame( 'Über uns', $spec->titleFor( 'de', 'en' ) );
		$this->assertSame( 'ueber-uns', $spec->slugFor( 'de', 'en' ) );
		$this->assertSame( 'About', $spec->titleFor( 'en', 'en' ) );
		$this->assertSame( 'about', $spec->slugFor( 'en', 'en' ) );
	}

	public function test_a_missing_title_falls_back_to_the_default_language() {
		$spec = $this->bilingual( [ 'title' => 'About', 'pattern' => 'x/about' ] );

		$this->assertSame( 'About', $spec->titleFor( 'de', 'en' ) );
	}

	public function test_a_missing_slug_derives_a_distinct_one() {
		// Polylang does not hook wp_unique_post_slug: two languages sharing a
		// slug land as `about` and `about-2`, and the Verifier then reports a
		// mismatch on every run forever with no fix that converges.
		$spec = $this->bilingual( [ 'title' => 'About', 'pattern' => 'x/about' ] );

		$this->assertSame( 'about-de', $spec->slugFor( 'de', 'en' ) );
	}

	public function test_a_pattern_override_is_used_verbatim() {
		$spec = $this->bilingual(
			[ 'title' => 'About', 'pattern' => 'x/about', 'languages' => [ 'de' => [ 'pattern' => 'x/about-german' ] ] ]
		);

		$this->assertSame( 'x/about-german', $spec->patternFor( 'de', 'en' ) );
		$this->assertSame( 'x/about', $spec->patternFor( 'en', 'en' ) );
	}

	public function test_pattern_for_returns_null_for_a_content_entry() {
		$spec = $this->bilingual( [ 'title' => 'About', 'content' => '' ] );

		$this->assertNull( $spec->patternFor( 'de', 'en' ) );
	}

	public function test_an_unknown_language_override_key_is_rejected() {
		$this->expectException( ManifestError::class );
		$this->expectExceptionMessageMatches( "/pages\.about\.languages\.de: unknown key 'titel'/" );

		$this->bilingual( [ 'title' => 'About', 'content' => '', 'languages' => [ 'de' => [ 'titel' => 'x' ] ] ] );
	}

	public function test_an_override_slug_must_be_a_valid_slug() {
		$this->expectException( ManifestError::class );
		$this->expectExceptionMessageMatches( "/pages\.about\.languages\.de: slug 'Über Uns' is not a valid post slug/" );

		$this->bilingual( [ 'title' => 'About', 'content' => '', 'languages' => [ 'de' => [ 'slug' => 'Über Uns' ] ] ] );
	}

	public function test_an_override_for_an_undeclared_language_is_rejected() {
		$this->expectException( ManifestError::class );
		$this->expectExceptionMessageMatches( "/pages\.about\.languages\.fr: 'fr' is not a declared site language/" );

		$this->bilingual( [ 'title' => 'About', 'content' => '', 'languages' => [ 'fr' => [ 'title' => 'À propos' ] ] ] );
	}
```

Add `use Pediment\Seeder\EntrySpec;` to the test file's imports if it is not already there.

- [ ] **Step 2: Run to verify it fails**

```bash
npx wp-env run tests-wordpress --env-cwd=wp-content/plugins/pediment-ai ./vendor/bin/phpunit --filter ManifestTest
```

Expected: FAIL — `unknown key 'languages'` from the entry validator.

- [ ] **Step 3: Extend EntrySpec**

Replace `plugin/src/Seeder/EntrySpec.php`:

```php
<?php
declare(strict_types=1);

namespace Pediment\Seeder;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class EntrySpec {
	/**
	 * @param array<string,string[]>                                          $terms
	 * @param array<string,array{title?:string,slug?:string,pattern?:string}> $translations
	 */
	public function __construct(
		public readonly string $key,
		public readonly string $postType,
		public readonly string $title,
		public readonly string $slug,
		public readonly ?string $parent,
		public readonly ?string $pattern,
		public readonly ?string $content,
		public readonly bool $frontPage,
		public readonly bool $postsPage,
		public readonly int $menuOrder,
		public readonly array $terms,
		public readonly array $translations = []
	) {}

	/** The declared title for a language, falling back to the default language's. */
	public function titleFor( string $language, string $default ): string {
		return (string) ( $this->translations[ $language ]['title'] ?? $this->title );
	}

	/**
	 * The slug for a language.
	 *
	 * A non-default language with no declared slug gets `<slug>-<lang>`, not
	 * the default's slug. Polylang does not hook wp_unique_post_slug, so all
	 * top-level pages share one slug namespace regardless of language: two
	 * languages both asking for `about` land as `about` and `about-2`, the
	 * Verifier reports a slug mismatch on every run, and no re-run converges.
	 * NavSeeder::slugFor() uses the same idiom for the same reason.
	 */
	public function slugFor( string $language, string $default ): string {
		$declared = (string) ( $this->translations[ $language ]['slug'] ?? '' );
		if ( '' !== $declared ) {
			return $declared;
		}
		if ( $language === $default || '' === $language ) {
			return $this->slug;
		}
		return $this->slug . '-' . $language;
	}

	/**
	 * The pattern slug for a language.
	 *
	 * Returns null for a `content`-declared entry. A non-default language with
	 * no override gets the `<pattern>-<lang>` convention; the resolver decides
	 * whether that pattern is actually registered and reports the miss.
	 */
	public function patternFor( string $language, string $default ): ?string {
		if ( null === $this->pattern ) {
			return null;
		}
		$declared = (string) ( $this->translations[ $language ]['pattern'] ?? '' );
		if ( '' !== $declared ) {
			return $declared;
		}
		if ( $language === $default || '' === $language ) {
			return $this->pattern;
		}
		return $this->pattern . '-' . $language;
	}
}
```

- [ ] **Step 4: Parse the overrides**

In `plugin/src/Seeder/Manifest.php`:

Add `'languages'` to `ENTRY_KEYS`:

```php
	private const ENTRY_KEYS = [ 'title', 'slug', 'parent', 'pattern', 'content', 'post_type', 'front_page', 'posts_page', 'menu_order', 'terms', 'languages' ];
```

Add the override sub-keys constant:

```php
	/** Keys a per-language entry override may declare. */
	private const TRANSLATION_KEYS = [ 'title', 'slug', 'pattern' ];
```

`self::entry()` needs to know the declared languages, so thread them through. In `fromArray()`, change the entry loop:

```php
			$entries[ $key ] = self::entry( $section, $key, (array) $declared, $defaultType, array_keys( $languages ) );
```

Change the signature and add the parsing at the end of `entry()`, just before the `return new EntrySpec(...)`:

```php
	/**
	 * @param array<string,mixed> $declared
	 * @param string[]            $declaredLanguages
	 * @throws ManifestError When the entry fails validation.
	 */
	private static function entry( string $section, string $key, array $declared, string $defaultType, array $declaredLanguages = [] ): EntrySpec {
```

```php
		$translations = [];
		foreach ( (array) ( $declared['languages'] ?? [] ) as $language => $override ) {
			$language = (string) $language;
			$override = (array) $override;

			if ( ! in_array( $language, $declaredLanguages, true ) ) {
				throw new ManifestError( "{$section}.{$key}.languages.{$language}: '{$language}' is not a declared site language. Add it to the manifest's `languages` section first." ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- operator-facing message.
			}

			foreach ( array_keys( $override ) as $overrideKey ) {
				if ( ! in_array( (string) $overrideKey, self::TRANSLATION_KEYS, true ) ) {
					throw new ManifestError( "{$section}.{$key}.languages.{$language}: unknown key '{$overrideKey}'. Allowed: " . implode( ', ', self::TRANSLATION_KEYS ) . '.' ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- operator-facing message.
				}
			}

			if ( isset( $override['slug'] ) ) {
				$overrideSlug = (string) $override['slug'];
				if ( '' === $overrideSlug || sanitize_title( $overrideSlug ) !== $overrideSlug ) {
					throw new ManifestError( "{$section}.{$key}.languages.{$language}: slug '{$overrideSlug}' is not a valid post slug." ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- operator-facing message.
				}
			}

			$translations[ $language ] = array_intersect_key( $override, array_flip( self::TRANSLATION_KEYS ) );
		}
```

and pass `$translations` as the last constructor argument.

- [ ] **Step 5: Run the tests**

```bash
npx wp-env run tests-wordpress --env-cwd=wp-content/plugins/pediment-ai ./vendor/bin/phpunit --filter ManifestTest
```

Expected: PASS, all cases old and new.

- [ ] **Step 6: Commit**

```bash
cd plugin && composer lint && cd ..
git add plugin/src/Seeder/EntrySpec.php plugin/src/Seeder/Manifest.php plugin/tests/phpunit/Seeder/ManifestTest.php
git commit -m "$(cat <<'EOF'
feat(seeder): declare per-language titles, slugs and patterns

An entry may override title, slug and pattern per language. A missing slug
derives as <slug>-<lang> rather than reusing the default's: Polylang does not
hook wp_unique_post_slug, so two languages asking for `about` land as `about`
and `about-2`, and the Verifier then reports a mismatch on every run with no
fix that converges. An override naming an undeclared language is rejected —
silently seeding nothing is how a translation goes missing unnoticed.

Co-Authored-By: Claude Opus 5 (1M context) <noreply@anthropic.com>
EOF
)"
```

---

### Task 9: Per-language content resolution and the `notices` bucket

**Files:**
- Modify: `plugin/src/Seeder/ContentResolver.php`, `plugin/src/Seeder/DesiredState.php`, `plugin/src/Seeder/RunResult.php`, `plugin/src/Seeder/Runner.php`, `plugin/src/Seeder/Reporter.php`
- Test: `plugin/tests/phpunit/Seeder/ContentResolverTest.php`, `plugin/tests/phpunit/Seeder/ReporterTest.php`, `plugin/tests/polylang/DesiredStateLanguageTest.php`

**Interfaces:**
- Consumes: `EntrySpec::titleFor/slugFor/patternFor` (Task 8).
- Produces:
  - `ContentResolver::resolve( EntrySpec $entry, string $language = '', string $default = '' ): string`
  - `ContentResolver::missingPatterns(): array<string,string>` — `"key|language" => pattern slug that was not registered`
  - `DesiredState::missingTranslations(): string[]` — operator-facing lines
  - `RunResult::$notices` (fifth constructor argument, after `$problems`), which does **not** affect `ok()`
  - `Reporter::text()` prints a `TRANSLATIONS` section when notices exist

- [ ] **Step 1: Write the failing tests**

Add to `plugin/tests/phpunit/Seeder/ContentResolverTest.php`:

```php
	public function test_a_language_pattern_is_preferred_when_registered() {
		register_block_pattern( 'x/about', [ 'title' => 'About', 'content' => '<p>english</p>' ] );
		register_block_pattern( 'x/about-de', [ 'title' => 'Über uns', 'content' => '<p>deutsch</p>' ] );

		$spec     = new EntrySpec( 'about', 'page', 'About', 'about', null, 'x/about', null, false, false, 0, [] );
		$resolver = new ContentResolver( new MediaMap( [] ) );

		$this->assertSame( '<p>deutsch</p>', $resolver->resolve( $spec, 'de', 'en' ) );
		$this->assertSame( '<p>english</p>', $resolver->resolve( $spec, 'en', 'en' ) );
	}

	public function test_a_missing_language_pattern_falls_back_and_is_recorded() {
		register_block_pattern( 'x/solo', [ 'title' => 'Solo', 'content' => '<p>english</p>' ] );

		$spec     = new EntrySpec( 'solo', 'page', 'Solo', 'solo', null, 'x/solo', null, false, false, 0, [] );
		$resolver = new ContentResolver( new MediaMap( [] ) );

		$this->assertSame( '<p>english</p>', $resolver->resolve( $spec, 'de', 'en' ) );
		$this->assertSame( [ 'solo|de' => 'x/solo-de' ], $resolver->missingPatterns() );
	}

	public function test_an_unregistered_default_pattern_still_throws() {
		$spec     = new EntrySpec( 'ghost', 'page', 'Ghost', 'ghost', null, 'x/ghost', null, false, false, 0, [] );
		$resolver = new ContentResolver( new MediaMap( [] ) );

		$this->expectException( ManifestError::class );
		$resolver->resolve( $spec, 'en', 'en' );
	}
```

Create `plugin/tests/polylang/DesiredStateLanguageTest.php`:

```php
<?php

use Pediment\Language\PolylangProvider;
use Pediment\Seeder\ContentResolver;
use Pediment\Seeder\DesiredState;
use Pediment\Seeder\Manifest;
use Pediment\Seeder\MediaMap;

class DesiredStateLanguageTest extends WP_UnitTestCase {

	public function set_up(): void {
		parent::set_up();
		register_block_pattern( 'x/home', [ 'title' => 'Home', 'content' => '<p>english</p>' ] );
	}

	public function tear_down(): void {
		Manifest::resetCache();
		parent::tear_down();
	}

	private function manifest(): Manifest {
		return Manifest::fromArray(
			[
				'languages' => [ 'en' => [ 'name' => 'English', 'locale' => 'en_US', 'default' => true ], 'de' => [ 'name' => 'Deutsch', 'locale' => 'de_DE' ] ],
				'pages'     => [ 'home' => [ 'title' => 'Home', 'pattern' => 'x/home', 'languages' => [ 'de' => [ 'title' => 'Startseite', 'slug' => 'startseite' ] ] ] ],
			],
			get_stylesheet_directory()
		);
	}

	public function test_one_entry_per_language() {
		$desired = ( new DesiredState( new PolylangProvider(), new ContentResolver( new MediaMap( [] ) ) ) )->build( $this->manifest() );

		$this->assertSame( [ 'home|en', 'home|de' ], array_keys( $desired ) );
	}

	public function test_each_language_carries_its_own_title_and_slug() {
		$desired = ( new DesiredState( new PolylangProvider(), new ContentResolver( new MediaMap( [] ) ) ) )->build( $this->manifest() );

		$this->assertSame( 'Startseite', $desired['home|de']->title );
		$this->assertSame( 'startseite', $desired['home|de']->slug );
		$this->assertSame( 'Home', $desired['home|en']->title );
		$this->assertSame( 'home', $desired['home|en']->slug );
	}

	public function test_the_hashes_differ_per_language() {
		$desired = ( new DesiredState( new PolylangProvider(), new ContentResolver( new MediaMap( [] ) ) ) )->build( $this->manifest() );

		$this->assertNotSame( $desired['home|en']->sourceHash, $desired['home|de']->sourceHash );
	}

	public function test_a_missing_translated_pattern_is_reported() {
		$state = new DesiredState( new PolylangProvider(), new ContentResolver( new MediaMap( [] ) ) );
		$state->build( $this->manifest() );

		$notices = implode( "\n", $state->missingTranslations() );

		$this->assertStringContainsString( 'home', $notices );
		$this->assertStringContainsString( 'de', $notices );
		$this->assertStringContainsString( 'x/home-de', $notices );
	}
}
```

Add to `plugin/tests/phpunit/Seeder/ReporterTest.php`:

```php
	public function test_notices_print_without_failing_the_run() {
		$result = new RunResult( new Plan(), true, '/x/manifest.php', [], [], [ 'home (de): no pattern `x/home-de` is registered — the German page carries the default language content.' ] );

		$text = Reporter::text( $result );

		$this->assertStringContainsString( 'TRANSLATIONS', $text );
		$this->assertStringContainsString( 'x/home-de', $text );
		$this->assertTrue( $result->ok(), 'A missing translation must not fail the run.' );
	}
```

- [ ] **Step 2: Run to verify they fail**

```bash
npx wp-env run tests-wordpress --env-cwd=wp-content/plugins/pediment-ai ./vendor/bin/phpunit --filter "ContentResolverTest|ReporterTest"
npx wp-env run tests-wordpress --env-cwd=wp-content/plugins/pediment-ai ./vendor/bin/phpunit -c phpunit-polylang.xml.dist --filter DesiredStateLanguageTest
```

Expected: FAIL on the new cases only.

- [ ] **Step 3: Teach ContentResolver about languages**

In `plugin/src/Seeder/ContentResolver.php`, add a second recording array and replace `resolve()`:

```php
	/** @var array<string,string> "key|language" => pattern slug that is not registered. */
	private array $missingPatterns = [];
```

```php
	/**
	 * @param string $language The language being resolved ('' when monolingual).
	 * @param string $default  The site's default language code.
	 *
	 * @throws ManifestError When the entry's DEFAULT-language pattern is not registered.
	 */
	public function resolve( EntrySpec $entry, string $language = '', string $default = '' ): string {
		$content = $entry->content;

		if ( null === $content ) {
			$registry = \WP_Block_Patterns_Registry::get_instance();
			$wanted   = (string) $entry->patternFor( $language, $default );
			$pattern  = $registry->get_registered( $wanted );

			// A language with no translated pattern is a normal state on a site
			// that just added one — it renders the default language's content and
			// says so, rather than seeding a blank page or blocking the run.
			if ( ( ! is_array( $pattern ) || ! isset( $pattern['content'] ) ) && $wanted !== $entry->pattern ) {
				$this->missingPatterns[ $entry->key . '|' . $language ] = $wanted;
				$pattern                                                = $registry->get_registered( (string) $entry->pattern );
			}

			if ( ! is_array( $pattern ) || ! isset( $pattern['content'] ) ) {
				throw new ManifestError( "{$entry->key}: pattern '{$entry->pattern}' is not registered. Patterns register on `init`; check the slug and that the file lives in the theme's patterns/ directory." ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- exception message for the operator, not echoed output.
			}
			$content = (string) $pattern['content'];
		}

		return $this->rewriteMarkup( $content );
	}

	/**
	 * Patterns a language wanted but does not have.
	 *
	 * Accumulates across resolve() calls (unlike unresolvedMediaKeys(), which is
	 * per-call) because DesiredState reports these once for the whole build.
	 *
	 * @return array<string,string> "key|language" => pattern slug
	 */
	public function missingPatterns(): array {
		return $this->missingPatterns;
	}
```

- [ ] **Step 4: Cross the manifest with the languages in DesiredState**

In `plugin/src/Seeder/DesiredState.php`, replace `build()`'s loop body and add the notices accessor:

```php
	/**
	 * @return array<string,DesiredEntry>
	 *
	 * @throws ManifestError When an entry's pattern is not registered.
	 */
	public function build( Manifest $manifest ): array {
		$desired               = [];
		$this->undeclaredMedia = [];
		$this->missingTitles   = [];
		$declared              = array_keys( $manifest->media() );
		$default               = $this->lang->defaultLanguage();

		foreach ( $this->lang->languages() as $language ) {
			foreach ( $manifest->entriesInDependencyOrder() as $spec ) {
				$title   = $spec->titleFor( $language, $default );
				$content = $this->resolver->resolve( $spec, $language, $default );

				// A non-default language rendering the default language's title is
				// a translation nobody wrote yet. Not an error — the page is real
				// and navigable — but silence here is how a five-language site
				// ships three languages of English.
				if ( $language !== $default && '' !== $language && ! isset( $spec->translations[ $language ]['title'] ) ) {
					$this->missingTitles[] = sprintf(
						'%s (%s): no title declared — the page carries the default language title "%s".',
						$spec->key,
						$language,
						$spec->title
					);
				}

				$entry = new DesiredEntry(
					$spec->key,
					$language,
					$spec->postType,
					$title,
					$spec->slugFor( $language, $default ),
					$spec->parent,
					$content,
					$spec->frontPage,
					$spec->postsPage,
					$spec->menuOrder,
					$spec->terms,
					ContentHash::compute( $title, $content )
				);

				$desired[ $entry->id() ] = $entry;

				// The resolver records what it could not resolve per call, so it
				// has to be read here or it is overwritten by the next entry.
				// Keys the manifest DOES declare are the fresh-site case — media
				// simply has not been applied yet, and the MediaSeeder/Verifier
				// own that. A key nobody declares is a typo that can never
				// resolve: the literal sentinel lands in a live page and gets
				// hashed as if it were correct.
				$undeclared = array_values( array_diff( $this->resolver->unresolvedMediaKeys(), $declared ) );
				if ( [] !== $undeclared ) {
					$this->undeclaredMedia[ $entry->id() ] = $undeclared;
				}
			}
		}

		return $desired;
	}

	/**
	 * Translations the manifest and the theme do not have yet.
	 *
	 * Reported as notices, never as problems: RunResult::ok() is false when
	 * problems exist and SeedCommand turns that into a non-zero exit, so a site
	 * that just added a language would fail its very first seed.
	 *
	 * @return string[]
	 */
	public function missingTranslations(): array {
		$lines = $this->missingTitles;

		foreach ( $this->resolver->missingPatterns() as $mapKey => $pattern ) {
			[ $key, $language ] = array_pad( explode( '|', (string) $mapKey ), 2, '' );
			$lines[]            = sprintf(
				'%s (%s): no pattern `%s` is registered — the page carries the default language content. Create patterns/%s.%s.php with `Slug: %s`, or run `wp pediment adopt %s --language=%s` once it is translated in the editor.',
				$key,
				$language,
				$pattern,
				$this->fileStem( $pattern ),
				$language,
				$pattern,
				$key,
				$language
			);
		}

		return $lines;
	}

	/** `theme/about-de` -> `about` — the stem the file convention uses. */
	private function fileStem( string $pattern ): string {
		$parts = explode( '/', $pattern );
		$last  = (string) end( $parts );
		return (string) preg_replace( '/-[a-z0-9-]+$/', '', $last );
	}
```

Add the property beside `$undeclaredMedia`:

```php
	/** @var string[] Entries whose language has no declared title. */
	private array $missingTitles = [];
```

- [ ] **Step 5: Add the notices bucket**

In `plugin/src/Seeder/RunResult.php`, add a fifth parameter and leave `ok()` alone:

```php
	/**
	 * @param string[]          $errors
	 * @param string[]          $problems
	 * @param array<string,int> $ids
	 * @param string[]          $notices
	 */
	public function __construct(
		public readonly Plan $plan,
		public readonly bool $applied,
		public readonly string $manifestPath = '',
		public readonly array $errors = [],
		public readonly array $problems = [],
		public readonly array $ids = [],
		public readonly array $notices = []
	) {}
```

Note the position: `notices` goes **after** `ids`, so every existing `new RunResult( … )` call keeps working unchanged.

In `plugin/src/Seeder/Runner.php`, pass the notices in the two returns that build a real result — the dry-run return and the final return:

```php
			if ( $dryRun || $plan->hasErrors() ) {
				return new RunResult( $plan, false, $manifest->path(), $plan->errors(), $this->undeclaredMediaProblems( $previewState ), $entryIds, $previewState->missingTranslations() );
			}
```

```php
		return new RunResult( $plan, true, $manifest->path(), $errors, $problems, $applied->ids, $state->missingTranslations() );
```

In `plugin/src/Seeder/Reporter.php`, print them. Add, immediately after the block that prints `VERIFICATION` problems (read the file and mirror its formatting exactly):

```php
		if ( [] !== $result->notices ) {
			$lines[] = '';
			$lines[] = 'TRANSLATIONS';
			foreach ( $result->notices as $notice ) {
				$lines[] = '  - ' . $notice;
			}
		}
```

- [ ] **Step 6: Run everything**

```bash
npx wp-env run tests-wordpress --env-cwd=wp-content/plugins/pediment-ai ./vendor/bin/phpunit
npx wp-env run tests-wordpress --env-cwd=wp-content/plugins/pediment-ai ./vendor/bin/phpunit -c phpunit-polylang.xml.dist
```

Expected: both green. The monolingual suite is the regression gate — `resolve()` called with two empty strings must behave exactly as the one-argument version did.

- [ ] **Step 7: Commit**

```bash
cd plugin && composer lint && cd ..
git add plugin/src/Seeder/ContentResolver.php plugin/src/Seeder/DesiredState.php plugin/src/Seeder/RunResult.php plugin/src/Seeder/Runner.php plugin/src/Seeder/Reporter.php plugin/tests
git commit -m "$(cat <<'EOF'
feat(seeder): resolve content, titles and slugs per language

Each language gets its own title, slug, pattern and hash. A language with no
translated pattern renders the default language's content and says so under a
TRANSLATIONS heading — as a notice, not a problem: problems make ok() false
and SeedCommand exits non-zero, so a site that just added a language would
fail its first seed for doing nothing wrong.

Co-Authored-By: Claude Opus 5 (1M context) <noreply@anthropic.com>
EOF
)"
```

---

### Task 10: Link entry translation groups

**Files:**
- Modify: `plugin/src/Seeder/Applier.php`
- Test: `plugin/tests/polylang/ApplierTranslationTest.php`

**Interfaces:**
- Consumes: `LanguageProvider::linkTranslations()` (Task 3).
- Produces: after `apply()` returns, every seed key whose languages were all written is one Polylang translation group.

- [ ] **Step 1: Write the failing test**

Create `plugin/tests/polylang/ApplierTranslationTest.php`:

```php
<?php

use Pediment\Language\PolylangProvider;
use Pediment\Seeder\Applier;
use Pediment\Seeder\ContentResolver;
use Pediment\Seeder\DesiredState;
use Pediment\Seeder\Differ;
use Pediment\Seeder\Manifest;
use Pediment\Seeder\MediaMap;
use Pediment\Seeder\StateReader;

class ApplierTranslationTest extends WP_UnitTestCase {

	private function seed(): array {
		$manifest = Manifest::fromArray(
			[
				'languages' => [ 'en' => [ 'name' => 'English', 'locale' => 'en_US', 'default' => true ], 'de' => [ 'name' => 'Deutsch', 'locale' => 'de_DE' ] ],
				'pages'     => [
					'home'  => [ 'title' => 'Home', 'content' => '<p>home</p>', 'front_page' => true, 'languages' => [ 'de' => [ 'title' => 'Startseite', 'slug' => 'startseite' ] ] ],
					'guide' => [ 'title' => 'Guide', 'content' => '<p>guide</p>', 'languages' => [ 'de' => [ 'title' => 'Anleitung', 'slug' => 'anleitung' ] ] ],
					'faq'   => [ 'title' => 'FAQ', 'content' => '<p>faq</p>', 'parent' => 'guide', 'languages' => [ 'de' => [ 'title' => 'Fragen', 'slug' => 'fragen' ] ] ],
				],
			],
			get_stylesheet_directory()
		);

		$lang    = new PolylangProvider();
		$desired = ( new DesiredState( $lang, new ContentResolver( new MediaMap( [] ) ) ) )->build( $manifest );
		$reader  = new StateReader( $lang );
		$plan    = ( new Differ() )->diff( $desired, $reader->read(), $reader->duplicates() );

		return [ ( new Applier( $lang ) )->apply( $plan, $desired ), $lang ];
	}

	public function test_the_two_languages_are_one_translation_group() {
		[ $applied, $lang ] = $this->seed();

		$en = $applied->ids['home|en'];
		$de = $applied->ids['home|de'];

		$this->assertGreaterThan( 0, $en );
		$this->assertGreaterThan( 0, $de );
		$this->assertSame( $de, $lang->translationOf( $en, 'de' ) );
		$this->assertSame( $en, $lang->translationOf( $de, 'en' ) );
	}

	public function test_a_child_is_parented_within_its_own_language() {
		[ $applied ] = $this->seed();

		$this->assertSame(
			$applied->ids['guide|de'],
			(int) get_post( $applied->ids['faq|de'] )->post_parent,
			'The German FAQ must hang off the German Guide, not the English one — a flat permalink breaks every menu URL in that language.'
		);
	}

	public function test_relinking_on_a_second_run_is_stable() {
		$this->seed();
		[ $applied, $lang ] = $this->seed();

		$this->assertSame( $applied->ids['guide|de'], $lang->translationOf( $applied->ids['guide|en'], 'de' ) );
	}

	public function test_the_front_page_option_holds_the_default_language_page() {
		[ $applied ] = $this->seed();

		$this->assertSame( $applied->ids['home|en'], (int) get_option( 'page_on_front' ) );
	}
}
```

- [ ] **Step 2: Run to verify it fails**

```bash
npx wp-env run tests-wordpress --env-cwd=wp-content/plugins/pediment-ai ./vendor/bin/phpunit -c phpunit-polylang.xml.dist --filter ApplierTranslationTest
```

Expected: `test_the_two_languages_are_one_translation_group` FAILS — nothing links them.

- [ ] **Step 3: Link the groups**

In `plugin/src/Seeder/Applier.php`, add one call in `apply()` between the `finally` block and `applyReadingOptions()`:

```php
		$this->linkTranslationGroups( $ids );

		$this->applyReadingOptions( $desired, $ids );
```

and the method:

```php
	/**
	 * Put every language of a seed key into one translation group.
	 *
	 * Runs after the write loop, not inside it: a group is only meaningful once
	 * every language exists, and pll_save_post_translations() REPLACES the
	 * whole group — calling it per language as each one is written would unlink
	 * the ones written before it. Invisible with two languages, silent data
	 * loss with five.
	 *
	 * Passing the full map every run is also what repairs a group an editor
	 * broke by hand, and what links a language added later to the ones that
	 * were already there.
	 *
	 * @param array<string,int> $ids mapKey => post ID
	 */
	private function linkTranslationGroups( array $ids ): void {
		$languages = $this->lang->languages();
		if ( count( $languages ) < 2 ) {
			return;
		}

		$byKey = [];
		foreach ( $ids as $mapKey => $postId ) {
			[ $key, $language ] = array_pad( explode( '|', (string) $mapKey ), 2, '' );
			if ( '' === $language || $postId <= 0 ) {
				continue;
			}
			$byKey[ $key ][ $language ] = $postId;
		}

		foreach ( $byKey as $map ) {
			$this->lang->linkTranslations( $map );
		}
	}
```

- [ ] **Step 4: Run the tests**

```bash
npx wp-env run tests-wordpress --env-cwd=wp-content/plugins/pediment-ai ./vendor/bin/phpunit -c phpunit-polylang.xml.dist --filter ApplierTranslationTest
npx wp-env run tests-wordpress --env-cwd=wp-content/plugins/pediment-ai ./vendor/bin/phpunit --filter ApplierTest
```

Expected: both PASS. `test_a_child_is_parented_within_its_own_language` should already pass — `Applier::parentId()` has always resolved `parentKey|language` — and this test is the proof it does.

- [ ] **Step 5: Commit**

```bash
cd plugin && composer lint && cd ..
git add plugin/src/Seeder/Applier.php plugin/tests/polylang/ApplierTranslationTest.php
git commit -m "$(cat <<'EOF'
feat(seeder): link entry translation groups after the write loop

pll_save_post_translations() replaces the whole group, so linking per
language as each one is written unlinks everything written before it —
invisible with two languages, silent data loss with five. Linking once, with
the full map, also repairs a group an editor broke and adopts a language
added after the others.

Co-Authored-By: Claude Opus 5 (1M context) <noreply@anthropic.com>
EOF
)"
```

---

### Task 11: Navigation menus per language

**Files:**
- Create: `plugin/inc/polylang-compat.php`
- Modify: `plugin/src/Seeder/NavSeeder.php`, `plugin/plugin.php` (or wherever `inc/` files are required — read first and match)
- Test: `plugin/tests/polylang/NavLanguageTest.php`

**Interfaces:**
- Consumes: `LanguageProvider::linkTranslations()`, `PolylangSetup`'s `post_types` option (Task 6).
- Produces: `pediment_polylang_translate_navigation_menus()` filtering `pll_get_post_types`, and nav translation groups linked by `NavSeeder::apply()`.

- [ ] **Step 1: Write the failing test**

Create `plugin/tests/polylang/NavLanguageTest.php`:

```php
<?php

use Pediment\Language\PolylangProvider;
use Pediment\Seeder\Manifest;
use Pediment\Seeder\NavSeeder;

class NavLanguageTest extends WP_UnitTestCase {

	public function test_wp_navigation_is_translatable_outside_the_settings_screen() {
		$this->assertContains( 'wp_navigation', (array) apply_filters( 'pll_get_post_types', [], false ) );
	}

	public function test_the_settings_screen_list_is_left_alone() {
		// Polylang's settings screen offers only public, non-builtin post types,
		// so wp_navigation can never appear there — adding it would render a
		// checkbox a site owner could untick and lose every translated menu to.
		$this->assertNotContains( 'wp_navigation', (array) apply_filters( 'pll_get_post_types', [], true ) );
	}

	public function test_one_navigation_entity_per_language() {
		$manifest = $this->manifest();
		$lang     = new PolylangProvider();
		$seeder   = new NavSeeder( $lang );

		$entryIds = [ 'about|en' => $this->page( 'en' ), 'about|de' => $this->page( 'de' ) ];
		$plan     = $seeder->plan( $manifest, $entryIds );
		$ids      = $seeder->apply( $plan, $manifest, $entryIds );

		$this->assertSame( [], $seeder->errors() );
		$this->assertArrayHasKey( 'primary|en', $ids );
		$this->assertArrayHasKey( 'primary|de', $ids );
		$this->assertNotSame( $ids['primary|en'], $ids['primary|de'] );
	}

	public function test_the_navigation_entities_are_one_translation_group() {
		$manifest = $this->manifest();
		$lang     = new PolylangProvider();
		$seeder   = new NavSeeder( $lang );

		$entryIds = [ 'about|en' => $this->page( 'en' ), 'about|de' => $this->page( 'de' ) ];
		$ids      = $seeder->apply( $seeder->plan( $manifest, $entryIds ), $manifest, $entryIds );

		$this->assertSame( $ids['primary|de'], $lang->translationOf( $ids['primary|en'], 'de' ) );
	}

	public function test_each_language_links_to_its_own_page() {
		$manifest = $this->manifest();
		$lang     = new PolylangProvider();
		$seeder   = new NavSeeder( $lang );

		$en       = $this->page( 'en' );
		$de       = $this->page( 'de' );
		$entryIds = [ 'about|en' => $en, 'about|de' => $de ];
		$ids      = $seeder->apply( $seeder->plan( $manifest, $entryIds ), $manifest, $entryIds );

		$this->assertStringContainsString( '"id":' . $de, get_post( $ids['primary|de'] )->post_content );
		$this->assertStringNotContainsString( '"id":' . $en, get_post( $ids['primary|de'] )->post_content );
	}

	private function page( string $language ): int {
		$id = self::factory()->post->create( [ 'post_type' => 'page', 'post_title' => 'About ' . $language ] );
		pll_set_post_language( $id, $language );
		update_post_meta( $id, \Pediment\Seeder\Meta::KEY, 'about' );
		return $id;
	}

	private function manifest(): Manifest {
		return Manifest::fromArray(
			[
				'languages' => [ 'en' => [ 'name' => 'English', 'locale' => 'en_US', 'default' => true ], 'de' => [ 'name' => 'Deutsch', 'locale' => 'de_DE' ] ],
				'pages'     => [ 'about' => [ 'title' => 'About', 'content' => '' ] ],
				'navs'      => [ 'primary' => [ 'title' => 'Primary', 'items' => [ [ 'entry' => 'about' ] ] ] ],
			],
			get_stylesheet_directory()
		);
	}
}
```

- [ ] **Step 2: Run to verify it fails**

```bash
npx wp-env run tests-wordpress --env-cwd=wp-content/plugins/pediment-ai ./vendor/bin/phpunit -c phpunit-polylang.xml.dist --filter NavLanguageTest
```

Expected: the filter tests FAIL (no filter yet); the group test FAILS (nothing links navs).

- [ ] **Step 3: Write the compat file**

Create `plugin/inc/polylang-compat.php`:

```php
<?php
/**
 * What Polylang needs to know about this product, and cannot be told by
 * clicking.
 *
 * @package Pediment
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Register wp_navigation as a translatable post type.
 *
 * The header binds its navigation block with a language-scoped lookup (see
 * inc/nav-language.php), which only yields a menu per language if Polylang
 * treats wp_navigation as translatable. This cannot be switched on in the UI:
 * Polylang's settings screen offers only post types registered
 * `public => true` and `_builtin => false`, and wp_navigation is
 * `public => false, _builtin => true`, so it never appears there. Polylang
 * carries no wp_navigation handling of its own — its menu translation UI works
 * on classic nav_menu terms, which a block theme does not use.
 *
 * Filtering only when $is_settings is false uses Polylang's
 * "programmatically active" path: always on, shown as a disabled checkbox
 * rather than one a site owner can untick and silently lose every translated
 * menu to.
 *
 * @param string[] $post_types  Post types Polylang manages.
 * @param bool     $is_settings Whether the list is for the settings screen.
 * @return string[]
 */
function pediment_polylang_translate_navigation_menus( $post_types, $is_settings ) {
	if ( ! $is_settings ) {
		$post_types['wp_navigation'] = 'wp_navigation';
	}
	return $post_types;
}
add_filter( 'pll_get_post_types', 'pediment_polylang_translate_navigation_menus', 10, 2 );
```

Require it from wherever `plugin/inc/*.php` files are loaded. Read `plugin/plugin.php` and mirror the existing `require_once` style exactly.

- [ ] **Step 4: Link nav translation groups**

In `plugin/src/Seeder/NavSeeder.php`, at the end of `apply()`, replace `return $ids;` with:

```php
			$this->linkTranslationGroups( $ids );

			return $ids;
```

(inside the `try`/`finally` structure the method already has — place the call after the item loop, before the `return`, so `Kses::restore()` in `finally` still runs.)

Add the method:

```php
	/**
	 * Put every language of a nav key into one translation group.
	 *
	 * Same rule as entries: pll_save_post_translations() replaces the whole
	 * group, so this runs once with the full map after every entity is written.
	 *
	 * Without it, a translated menu is invisible to pll_get_post(), the header's
	 * per-language lookup falls back to whichever nav was saved last, and every
	 * language renders one language's navigation — the outage this engine's nav
	 * identity model exists to prevent.
	 *
	 * @param array<string,int> $ids navKey|language => post ID
	 */
	private function linkTranslationGroups( array $ids ): void {
		if ( count( $this->lang->languages() ) < 2 ) {
			return;
		}

		$byKey = [];
		foreach ( $ids as $mapKey => $postId ) {
			[ $key, $language ] = array_pad( explode( '|', (string) $mapKey ), 2, '' );
			if ( '' === $language || $postId <= 0 ) {
				continue;
			}
			$byKey[ $key ][ $language ] = $postId;
		}

		foreach ( $byKey as $map ) {
			$this->lang->linkTranslations( $map );
		}
	}
```

- [ ] **Step 5: Run the tests**

```bash
npx wp-env run tests-wordpress --env-cwd=wp-content/plugins/pediment-ai ./vendor/bin/phpunit -c phpunit-polylang.xml.dist --filter NavLanguageTest
npx wp-env run tests-wordpress --env-cwd=wp-content/plugins/pediment-ai ./vendor/bin/phpunit --filter NavSeederTest
```

Expected: both PASS.

- [ ] **Step 6: Commit**

```bash
cd plugin && composer lint && cd ..
git add plugin/inc/polylang-compat.php plugin/src/Seeder/NavSeeder.php plugin/plugin.php plugin/tests/polylang/NavLanguageTest.php
git commit -m "$(cat <<'EOF'
feat(language): give every language its own navigation menu

wp_navigation is forced translatable through pll_get_post_types, and only
outside the settings screen: Polylang's UI lists neither builtin nor
non-public post types, so it can never be ticked there, and adding it would
render a checkbox that silently discards every translated menu when unticked.
Nav entities are then linked into one translation group after the write loop,
for the same replace-the-whole-group reason entries are.

Co-Authored-By: Claude Opus 5 (1M context) <noreply@anthropic.com>
EOF
)"
```

---

### Task 12: Bind the header's navigation to the current language

**Files:**
- Create: `plugin/inc/nav-language.php`
- Modify: `plugin/plugin.php` (require it)
- Test: `plugin/tests/polylang/NavBindingTest.php`

**Interfaces:**
- Produces: `pediment_bind_navigation_ref( array $parsedBlock ): array` on `render_block_data`, setting `attrs.ref` on a ref-less `core/navigation` block to the seeded nav for the current language.

This is the outage. `plugin/inc/bootstrap.php` seeds a header template part containing `<!-- wp:navigation … /-->` with no `ref`; core resolves that through `block_core_navigation_get_fallback_ref()`, which returns the **most recently created** `wp_navigation` post. On a bilingual site that is whichever language the seeder wrote last — for every language.

- [ ] **Step 1: Write the failing test**

Create `plugin/tests/polylang/NavBindingTest.php`:

```php
<?php

use Pediment\Seeder\Meta;

class NavBindingTest extends WP_UnitTestCase {

	private int $en;
	private int $de;

	public function set_up(): void {
		parent::set_up();

		$this->en = $this->nav( 'en', 'Primary EN' );
		// German is created LAST on purpose: core's fallback picks the most
		// recently created wp_navigation, so an unbound block renders this one
		// in every language. That is the bug under test.
		$this->de = $this->nav( 'de', 'Primary DE' );
	}

	private function nav( string $language, string $title ): int {
		$id = self::factory()->post->create( [ 'post_type' => 'wp_navigation', 'post_title' => $title, 'post_status' => 'publish' ] );
		pll_set_post_language( $id, $language );
		update_post_meta( $id, Meta::KEY, 'primary' );
		return $id;
	}

	private function bind( string $currentLanguage ): array {
		add_filter( 'pll_current_language', static fn() => $currentLanguage );

		return pediment_bind_navigation_ref( [ 'blockName' => 'core/navigation', 'attrs' => [] ] );
	}

	public function tear_down(): void {
		remove_all_filters( 'pll_current_language' );
		parent::tear_down();
	}

	public function test_english_gets_the_english_menu() {
		$this->assertSame( $this->en, (int) $this->bind( 'en' )['attrs']['ref'] );
	}

	public function test_german_gets_the_german_menu() {
		$this->assertSame( $this->de, (int) $this->bind( 'de' )['attrs']['ref'] );
	}

	public function test_an_explicit_ref_is_never_overridden() {
		$block = pediment_bind_navigation_ref( [ 'blockName' => 'core/navigation', 'attrs' => [ 'ref' => 4242 ] ] );

		$this->assertSame( 4242, $block['attrs']['ref'] );
	}

	public function test_other_blocks_are_untouched() {
		$block = [ 'blockName' => 'core/paragraph', 'attrs' => [] ];

		$this->assertSame( $block, pediment_bind_navigation_ref( $block ) );
	}

	public function test_a_language_with_no_menu_falls_back_to_the_default() {
		add_filter( 'pll_current_language', static fn() => 'fr' );

		// No French menu exists; rendering nothing would strip the header's
		// navigation outright, which is strictly worse than the wrong language.
		$this->assertSame( $this->en, (int) pediment_bind_navigation_ref( [ 'blockName' => 'core/navigation', 'attrs' => [] ] )['attrs']['ref'] );
	}
}
```

- [ ] **Step 2: Run to verify it fails**

```bash
npx wp-env run tests-wordpress --env-cwd=wp-content/plugins/pediment-ai ./vendor/bin/phpunit -c phpunit-polylang.xml.dist --filter NavBindingTest
```

Expected: FAIL — `Call to undefined function pediment_bind_navigation_ref()`.

- [ ] **Step 3: Write the binding**

Create `plugin/inc/nav-language.php`:

```php
<?php
/**
 * Bind the header's navigation block to the current language's menu.
 *
 * The seeded header template part (see inc/bootstrap.php) ships
 * `<!-- wp:navigation /-->` with no `ref`, because post IDs differ per
 * environment and a file cannot hardcode one. Core resolves a ref-less
 * navigation block through block_core_navigation_get_fallback_ref(), which
 * returns the MOST RECENTLY CREATED wp_navigation post — so on a multilingual
 * site every language renders whichever menu the seeder happened to write
 * last. On a live site that has previously shown the wrong language's
 * navigation to everyone, and, when the fallback found nothing, removed the
 * header's navigation outright.
 *
 * @package Pediment
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The seeded navigation entity for a language, by seed key.
 *
 * Identity is the seed key, never the slug: a stray post holding `primary`
 * pushed every replacement to `primary-2`, where a slug lookup could not find
 * it (7d7ca30).
 *
 * @param string $language Language slug, '' for none.
 * @return int Post ID, 0 when there is none.
 */
function pediment_seeded_nav_id( string $language ): int {
	$args = [
		'post_type'      => 'wp_navigation',
		'post_status'    => 'publish',
		'posts_per_page' => 1,
		'no_found_rows'  => true,
		'fields'         => 'ids',
		'orderby'        => 'ID',
		'order'          => 'ASC',
		// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- indexed seed-identity lookup, once per request.
		'meta_key'       => \Pediment\Seeder\Meta::KEY,
		'meta_value'     => 'primary', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value -- see above.
	];

	if ( '' !== $language ) {
		$args['lang'] = $language;
	}

	$found = get_posts( $args );

	return $found ? (int) $found[0] : 0;
}

/**
 * Point a ref-less core/navigation block at this language's seeded menu.
 *
 * Falls back to the default language rather than to nothing: showing the wrong
 * language's navigation is bad, showing none at all is what took a live site's
 * header away. An explicitly-set ref is always left alone — that is a Site
 * Editor decision and outranks this.
 *
 * @param array<string,mixed> $parsed_block Parsed block, pre-render.
 * @return array<string,mixed>
 */
function pediment_bind_navigation_ref( $parsed_block ) {
	if ( ! is_array( $parsed_block ) || 'core/navigation' !== ( $parsed_block['blockName'] ?? '' ) ) {
		return $parsed_block;
	}
	if ( ! empty( $parsed_block['attrs']['ref'] ) ) {
		return $parsed_block;
	}

	$current = function_exists( 'pll_current_language' ) ? (string) pll_current_language() : '';
	$default = function_exists( 'pll_default_language' ) ? (string) pll_default_language() : '';

	foreach ( array_unique( array_filter( [ $current, $default, '' ] ), SORT_REGULAR ) as $language ) {
		$ref = pediment_seeded_nav_id( (string) $language );
		if ( $ref > 0 ) {
			$parsed_block['attrs']['ref'] = $ref;
			return $parsed_block;
		}
	}

	// Nothing seeded: leave the block alone and let core's own fallback run.
	// Better a menu chosen badly than an empty header.
	return $parsed_block;
}
add_filter( 'render_block_data', 'pediment_bind_navigation_ref' );
```

Note `array_unique( array_filter( [...] ) )` keeps `''` out unless both language functions are absent; on a monolingual site `$current` and `$default` are both `''`, `array_filter` empties the list, and the loop then runs once with nothing — so add `''` back explicitly. Verify with the monolingual suite in Step 5; if the fallback never runs there, replace the array expression with:

```php
	$candidates = array_values( array_unique( array_filter( [ $current, $default ] ) ) );
	$candidates[] = '';
```

- [ ] **Step 4: Require it**

Add the `require_once` for `inc/nav-language.php` beside the other `inc/` requires in `plugin/plugin.php`, matching the existing style.

- [ ] **Step 5: Run both suites**

```bash
npx wp-env run tests-wordpress --env-cwd=wp-content/plugins/pediment-ai ./vendor/bin/phpunit -c phpunit-polylang.xml.dist --filter NavBindingTest
npx wp-env run tests-wordpress --env-cwd=wp-content/plugins/pediment-ai ./vendor/bin/phpunit
```

Expected: both PASS. The monolingual suite matters — a single-language site must now bind to its one seeded `primary` nav, which is an improvement, not a regression, but it must not break the existing nav tests.

- [ ] **Step 6: Commit**

```bash
cd plugin && composer lint && cd ..
git add plugin/inc/nav-language.php plugin/plugin.php plugin/tests/polylang/NavBindingTest.php
git commit -m "$(cat <<'EOF'
fix(nav): bind the header's navigation to the current language

The seeded header ships a ref-less core/navigation block, and core resolves
those to the most recently created wp_navigation post — so every language
rendered whichever menu the seeder wrote last. Bind by seed key and current
language instead, falling back to the default language rather than to nothing:
the wrong language's navigation is bad, no navigation is what took a live
site's header away.

Co-Authored-By: Claude Opus 5 (1M context) <noreply@anthropic.com>
EOF
)"
```

---

### Task 13: `wp pediment adopt --language` writes per-language pattern files

**Files:**
- Modify: `plugin/src/Seeder/Adopter.php`
- Test: `plugin/tests/polylang/AdopterLanguageTest.php`

**Interfaces:**
- Consumes: `EntrySpec::patternFor()` (Task 8).
- Produces: `Adopter::adopt( $seedKey, $language, $dryRun )` writes `patterns/<stem>.<lang>.php` with `Slug: <pattern>-<lang>` for a non-default language, and hashes the adopted entry against the language it actually adopted.

Today `adopt --language=de` reads the German post and writes it into the **English** pattern file, then hashes it as if it were English. That is a data-loss bug this task fixes.

- [ ] **Step 1: Write the failing test**

Create `plugin/tests/polylang/AdopterLanguageTest.php`:

```php
<?php

use Pediment\Language\PolylangProvider;
use Pediment\Seeder\Adopter;
use Pediment\Seeder\ContentHash;
use Pediment\Seeder\Manifest;
use Pediment\Seeder\Meta;

class AdopterLanguageTest extends WP_UnitTestCase {

	private string $dir;

	public function set_up(): void {
		parent::set_up();
		$this->dir = get_stylesheet_directory() . '/patterns';
		wp_mkdir_p( $this->dir );
	}

	public function tear_down(): void {
		foreach ( glob( $this->dir . '/adoptme*.php' ) as $file ) {
			wp_delete_file( $file );
		}
		Manifest::resetCache();
		parent::tear_down();
	}

	private function manifest(): void {
		add_filter(
			'pediment_seed_manifest',
			fn() => [
				'languages' => [ 'en' => [ 'name' => 'English', 'locale' => 'en_US', 'default' => true ], 'de' => [ 'name' => 'Deutsch', 'locale' => 'de_DE' ] ],
				'pages'     => [ 'adoptme' => [ 'title' => 'Adopt me', 'pattern' => 'x/adoptme', 'languages' => [ 'de' => [ 'title' => 'Übernimm mich', 'slug' => 'uebernimm-mich' ] ] ] ],
			]
		);
		Manifest::resetCache();
	}

	private function page( string $language, string $content ): int {
		$id = self::factory()->post->create( [ 'post_type' => 'page', 'post_title' => 'x', 'post_content' => $content ] );
		pll_set_post_language( $id, $language );
		update_post_meta( $id, Meta::KEY, 'adoptme' );
		return $id;
	}

	public function test_a_german_adopt_writes_the_german_file() {
		$this->manifest();
		$this->page( 'en', '<p>english</p>' );
		$this->page( 'de', '<p>deutsch</p>' );

		$result = ( new Adopter( new PolylangProvider() ) )->adopt( 'adoptme', 'de' );

		$this->assertSame( [], $result['errors'] );
		$this->assertStringEndsWith( '/patterns/adoptme.de.php', $result['path'] );
		$this->assertFileExists( $this->dir . '/adoptme.de.php' );
		$this->assertStringContainsString( 'deutsch', file_get_contents( $this->dir . '/adoptme.de.php' ) );
	}

	public function test_the_german_file_carries_the_language_slug_header() {
		$this->manifest();
		$this->page( 'en', '<p>english</p>' );
		$this->page( 'de', '<p>deutsch</p>' );

		( new Adopter( new PolylangProvider() ) )->adopt( 'adoptme', 'de' );

		$header = get_file_data( $this->dir . '/adoptme.de.php', [ 'slug' => 'Slug', 'title' => 'Title' ] );

		$this->assertSame( 'x/adoptme-de', $header['slug'], 'The next seed looks the German pattern up by this slug.' );
		$this->assertSame( 'Übernimm mich', $header['title'] );
	}

	public function test_the_default_language_still_writes_the_plain_file() {
		$this->manifest();
		$this->page( 'en', '<p>english</p>' );

		$result = ( new Adopter( new PolylangProvider() ) )->adopt( 'adoptme', 'en' );

		$this->assertStringEndsWith( '/patterns/adoptme.php', $result['path'] );
	}

	public function test_the_german_hashes_are_written_against_the_german_post() {
		$this->manifest();
		$this->page( 'en', '<p>english</p>' );
		$de = $this->page( 'de', '<p>deutsch</p>' );

		( new Adopter( new PolylangProvider() ) )->adopt( 'adoptme', 'de' );

		$this->assertSame( ContentHash::forPost( $de ), get_post_meta( $de, Meta::HASH, true ) );
		$this->assertNotSame( '', (string) get_post_meta( $de, Meta::SOURCE, true ) );
	}
}
```

- [ ] **Step 2: Run to verify it fails**

```bash
npx wp-env run tests-wordpress --env-cwd=wp-content/plugins/pediment-ai ./vendor/bin/phpunit -c phpunit-polylang.xml.dist --filter AdopterLanguageTest
```

Expected: FAIL — the path ends `/adoptme.php` and the header reads `x/adoptme`.

- [ ] **Step 3: Make the Adopter language-aware**

In `plugin/src/Seeder/Adopter.php`:

Replace the file-path derivation:

```php
		$default  = $this->lang->defaultLanguage();
		$pattern  = (string) $spec->patternFor( $language, $default );
		$stemBits = explode( '/', (string) $spec->pattern );
		$stem     = (string) end( $stemBits );
		$suffix   = ( '' === $language || $language === $default ) ? '' : '.' . $language;
		$file     = untrailingslashit( $manifest->baseDir() ) . '/patterns/' . $stem . $suffix . '.php';
```

Pass the language's title and pattern into `render()` — change its signature and the two header lines:

```php
	private function render( EntrySpec $spec, string $markup, string $existing = '', string $title = '', string $pattern = '' ): string {
```

```php
			. ' * Title: ' . ( '' !== $title ? $title : $spec->title ) . "\n"
			. ' * Slug: ' . ( '' !== $pattern ? $pattern : (string) $spec->pattern ) . "\n"
```

and the call site:

```php
		$contents = $this->render( $spec, $markup, $file, $spec->titleFor( $language, $default ), $pattern );
```

Change the title-drift warning to compare against the language's declared title:

```php
		$declaredTitle = $spec->titleFor( $language, $default );
		$warnings      = [];
		if ( (string) $post->post_title !== $declaredTitle ) {
			$warnings[] = sprintf(
				'%s (%s): the live title is "%s" but the manifest still declares "%s" — adopt does not write titles back. Update the manifest by hand if git should carry the new name.',
				$seedKey,
				'' === $language ? 'default' : $language,
				(string) $post->post_title,
				$declaredTitle
			);
		}
```

Change the `Slug:` header check to compare against the language's pattern:

```php
		if ( $header['slug'] !== $pattern ) {
			$this->rollback( $file, $backup );
			return array_merge(
				$empty,
				[ 'errors' => [ sprintf( '%s: wrote %s but its Slug header reads "%s", not "%s" — the next seed would not find it, so the write was rolled back.', $seedKey, $file, $header['slug'], $pattern ) ] ]
			);
		}
```

And the source hash must use the language's title:

```php
		update_post_meta(
			$actual->id,
			Meta::SOURCE,
			ContentHash::compute( $declaredTitle, ( new ContentResolver( $media ) )->rewriteMarkup( $resolved ) )
		);
```

- [ ] **Step 4: Run the tests**

```bash
npx wp-env run tests-wordpress --env-cwd=wp-content/plugins/pediment-ai ./vendor/bin/phpunit -c phpunit-polylang.xml.dist --filter AdopterLanguageTest
npx wp-env run tests-wordpress --env-cwd=wp-content/plugins/pediment-ai ./vendor/bin/phpunit --filter AdopterTest
```

Expected: both PASS. The monolingual `AdopterTest` is the proof that `''`/default behaviour is byte-identical.

- [ ] **Step 5: Commit**

```bash
cd plugin && composer lint && cd ..
git add plugin/src/Seeder/Adopter.php plugin/tests/polylang/AdopterLanguageTest.php
git commit -m "$(cat <<'EOF'
fix(seeder): adopt into the language's own pattern file

`adopt --language=de` read the German post and wrote it into the English
pattern file, then hashed it as English — overwriting one language's source
with another's. It now writes patterns/<stem>.de.php with `Slug: <pattern>-de`
(the slug the next seed looks the German pattern up by) and hashes against the
German post and the German title.

Co-Authored-By: Claude Opus 5 (1M context) <noreply@anthropic.com>
EOF
)"
```

---

### Task 14: Generate `wpml-config.xml`

**Files:**
- Create: `tools/generate-wpml-config.mjs`
- Modify: `plugin/wpml-config.xml` (generated), `package.json` (script), `.github/workflows/ci.yml` (check), several `plugin/src/blocks/*/block.json` (array item shapes)
- Test: the generator's own `--check` mode, run in CI

**Interfaces:**
- Produces: `npm run gen:wpml` (writes) and `npm run gen:wpml -- --check` (exits 1 on drift).

- [ ] **Step 1: Write the generator**

Create `tools/generate-wpml-config.mjs`:

```js
#!/usr/bin/env node
/**
 * Generate plugin/wpml-config.xml from the blocks' own block.json.
 *
 * The hand-maintained file drifted: four shipping blocks (form, form-field,
 * social-links, blog-index) were missing entirely, which means their text was
 * simply not offered for translation on any multilingual site. Polylang reads
 * this format too, so generating it serves both plugins.
 *
 * Rules:
 *   - `string` attributes are translatable
 *   - ...unless they carry an `enum` (a variant token, not prose)
 *   - ...or their name ends in Id/Ids (a reference)
 *   - a name ending in Url/Href is emitted as type="link"
 *   - `array` attributes need an `items.properties` declaration to be
 *     translated; without one there is no way to know which sub-keys hold
 *     text, and guessing would silently mistranslate block attributes
 *   - everything else (number, boolean, object) is skipped
 */

import { readdirSync, readFileSync, writeFileSync } from 'node:fs';
import { join, dirname } from 'node:path';
import { fileURLToPath } from 'node:url';

const ROOT = join( dirname( fileURLToPath( import.meta.url ) ), '..' );
const BLOCKS = join( ROOT, 'plugin', 'src', 'blocks' );
const TARGET = join( ROOT, 'plugin', 'wpml-config.xml' );

const isLink = ( name ) => /(?:url|href)$/i.test( name );
const isReference = ( name ) => /ids?$/i.test( name );

function keysFor( name, schema, indent ) {
	const pad = '\t'.repeat( indent );

	if ( schema.type === 'string' ) {
		if ( schema.enum || isReference( name ) ) return [];
		return [ isLink( name ) ? `${ pad }<key name="${ name }" type="link"/>` : `${ pad }<key name="${ name }"/>` ];
	}

	if ( schema.type === 'array' && schema.items?.properties ) {
		const inner = Object.entries( schema.items.properties )
			.flatMap( ( [ k, v ] ) => keysFor( k, v, indent + 2 ) );
		if ( inner.length === 0 ) return [];
		return [
			`${ pad }<key name="${ name }">`,
			`${ pad }\t<key name="*">`,
			...inner,
			`${ pad }\t</key>`,
			`${ pad }</key>`,
		];
	}

	// A bare array of strings: translate each item.
	if ( schema.type === 'array' && schema.items?.type === 'string' ) {
		return [ `${ pad }<key name="${ name }">`, `${ pad }\t<key name="*"/>`, `${ pad }</key>` ];
	}

	return [];
}

const blocks = readdirSync( BLOCKS, { withFileTypes: true } )
	.filter( ( d ) => d.isDirectory() )
	.map( ( d ) => join( BLOCKS, d.name, 'block.json' ) )
	.map( ( p ) => JSON.parse( readFileSync( p, 'utf8' ) ) )
	.sort( ( a, b ) => a.name.localeCompare( b.name ) );

const sections = [];
for ( const block of blocks ) {
	const keys = Object.entries( block.attributes ?? {} )
		.flatMap( ( [ name, schema ] ) => keysFor( name, schema, 3 ) );
	if ( keys.length === 0 ) continue;
	sections.push( `\t\t<gutenberg-block type="${ block.name }" translate="1">`, ...keys, '\t\t</gutenberg-block>' );
}

const xml =
	'<?xml version="1.0" encoding="UTF-8"?>\n' +
	'<!-- Generated by tools/generate-wpml-config.mjs. Do not edit by hand;\n' +
	'     declare attribute shapes in the block\'s block.json instead. -->\n' +
	'<wpml-config>\n\t<gutenberg-blocks>\n' +
	sections.join( '\n' ) +
	'\n\t</gutenberg-blocks>\n</wpml-config>\n';

if ( process.argv.includes( '--check' ) ) {
	const current = readFileSync( TARGET, 'utf8' );
	if ( current !== xml ) {
		console.error( 'wpml-config.xml is out of date. Run `npm run gen:wpml` and commit the result.' );
		process.exit( 1 );
	}
	console.log( 'wpml-config.xml is up to date.' );
	process.exit( 0 );
}

writeFileSync( TARGET, xml );
console.log( `Wrote ${ TARGET } (${ sections.filter( ( l ) => l.includes( 'gutenberg-block ' ) ).length } blocks).` );
```

- [ ] **Step 2: Run it and diff against the hand-written file**

```bash
node tools/generate-wpml-config.mjs
git diff plugin/wpml-config.xml
```

**Every difference must be explained before continuing.** Expect three kinds:

1. **Blocks added** (`form`, `form-field`, `social-links`, `blog-index`, and any other) — correct; the hand-written file was missing them.
2. **Nested array keys lost** (`hero.ticks`, `hero.metrics`, `mega-menu.columns`, `slider.slides`) — the generator cannot see their shape because `block.json` declares only `"type": "array"`. Fix in Step 3.
3. **Keys the generator drops that the hand file had** — inspect each. A dropped `enum` string is correct. Anything else means the heuristic is wrong; fix the generator, not the expectation.

- [ ] **Step 3: Declare the array shapes in block.json**

For each array attribute the hand-written file translated, add a standard JSON-Schema `items` block. `plugin/src/blocks/hero/block.json`:

```json
    "ticks": {
      "type": "array",
      "default": [],
      "items": { "type": "string" }
    },
```

```json
    "metrics": {
      "type": "array",
      "default": [],
      "items": {
        "type": "object",
        "properties": {
          "value": { "type": "string" },
          "label": { "type": "string" }
        }
      }
    },
```

`plugin/src/blocks/slider/block.json`:

```json
    "slides": {
      "type": "array",
      "default": [],
      "items": {
        "type": "object",
        "properties": {
          "altOverride": { "type": "string" },
          "eyebrow": { "type": "string" },
          "heading": { "type": "string" },
          "body": { "type": "string" },
          "buttonText": { "type": "string" },
          "buttonUrl": { "type": "string" }
        }
      }
    },
```

`plugin/src/blocks/mega-menu/block.json`:

```json
    "columns": {
      "type": "array",
      "default": [],
      "items": {
        "type": "object",
        "properties": {
          "heading": { "type": "string" },
          "links": {
            "type": "array",
            "items": {
              "type": "object",
              "properties": {
                "label": { "type": "string" },
                "description": { "type": "string" },
                "url": { "type": "string" }
              }
            }
          }
        }
      }
    },
```

Do the same for any other array attribute the diff showed was translated. Then check every remaining block with an array attribute (`faq`, `feature-grid`, `stat-grid`, `steps`, `testimonial-grid`, `social-links`, `form`) — if it holds translatable text, declare its shape; if it holds only inner blocks or IDs, leave it.

- [ ] **Step 4: Verify the blocks still build and register**

```bash
cd plugin && npm run build && cd ..
npm run lint:blocks
npx wp-env run tests-wordpress --env-cwd=wp-content/plugins/pediment-ai ./vendor/bin/phpunit
```

Expected: green. `items` is standard JSON Schema; WordPress ignores keys it does not use in an attribute definition. If `lint:blocks` rejects the new key, extend that linter rather than removing the declarations.

- [ ] **Step 5: Regenerate, wire the scripts, and gate CI**

```bash
node tools/generate-wpml-config.mjs
node tools/generate-wpml-config.mjs --check
```

Expected: the second call prints `up to date` and exits 0.

Add to the root `package.json` scripts:

```json
    "gen:wpml": "node tools/generate-wpml-config.mjs",
```

Add to the `lint-blocks` job in `.github/workflows/ci.yml`, after the existing lint steps:

```yaml
      - run: node tools/generate-wpml-config.mjs --check
```

- [ ] **Step 6: Commit**

```bash
git add tools/generate-wpml-config.mjs plugin/wpml-config.xml plugin/src/blocks package.json .github/workflows/ci.yml
git commit -m "$(cat <<'EOF'
feat(i18n): generate wpml-config.xml from block.json

The hand-maintained file had drifted: four shipping blocks were missing
entirely, so their text was never offered for translation on any multilingual
site. Array attributes now declare their item shape in block.json — the
generator refuses to guess which sub-keys hold prose, because guessing
mistranslates block attributes silently. CI fails on drift.

Co-Authored-By: Claude Opus 5 (1M context) <noreply@anthropic.com>
EOF
)"
```

---

### Task 15: A bilingual fixture and the e2e proof

**Files:**
- Modify: `tests/fixtures/client-theme/seed/manifest.php`, `plugin/tests/e2e/global-setup.ts`
- Create: `tests/fixtures/client-theme/patterns/about.de.php`, `plugin/tests/e2e/multilingual.spec.ts`

**Interfaces:**
- Consumes: everything above.
- Produces: the CI fixture site runs `en` + `de`, seeded by the real engine, asserted end to end.

This is the task most likely to surface fallout in the existing e2e suite. Budget for it.

- [ ] **Step 1: Make the fixture bilingual**

In `tests/fixtures/client-theme/seed/manifest.php`, add the `languages` section after `'version' => 1,`:

```php
	'languages' => array(
		'en' => array( 'name' => 'English', 'locale' => 'en_US', 'flag' => 'gb', 'default' => true ),
		'de' => array( 'name' => 'Deutsch', 'locale' => 'de_DE', 'flag' => 'de' ),
	),
```

and give the pages German titles and slugs:

```php
	'pages'   => array(
		'home'      => array(
			'title'      => 'Home',
			'pattern'    => 'pediment/pediment-landing',
			'front_page' => true,
			'languages'  => array( 'de' => array( 'title' => 'Startseite', 'slug' => 'startseite' ) ),
		),
		'about'     => array(
			'title'     => 'About',
			'pattern'   => 'pediment-fixture/about',
			'languages' => array( 'de' => array( 'title' => 'Über uns', 'slug' => 'ueber-uns' ) ),
		),
		'contact'   => array(
			'title'     => 'Contact',
			'content'   => '',
			'languages' => array( 'de' => array( 'title' => 'Kontakt', 'slug' => 'kontakt' ) ),
		),
		'blog'      => array(
			'title'      => 'Blog',
			'content'    => '',
			'posts_page' => true,
			'languages'  => array( 'de' => array( 'title' => 'Journal', 'slug' => 'journal' ) ),
		),
		'mega-demo' => array(
			'title'     => 'Mega Menu Demo',
			'pattern'   => 'pediment/mega-menu-header',
			'languages' => array( 'de' => array( 'title' => 'Mega-Menü Demo', 'slug' => 'mega-menue-demo' ) ),
		),
	),
```

Leave the six `posts` monolingual on purpose: that exercises the missing-translation notice path on every CI run, and the derived `-de` slugs prove Task 8's rule in practice.

- [ ] **Step 2: Add one translated pattern**

Create `tests/fixtures/client-theme/patterns/about.de.php`. Read `tests/fixtures/client-theme/patterns/about.php` first and mirror its structure, changing only the header and the prose:

```php
<?php
/**
 * Title: Über uns
 * Slug: pediment-fixture/about-de
 * Categories: pediment
 * Inserter: no
 *
 * The German counterpart of about.php. The `-de` slug suffix is the convention
 * the seeder looks a translated pattern up by; the filename suffix is how a
 * developer finds it.
 *
 * @package Pediment
 */

?>
<!-- wp:paragraph --><p>Deutschsprachige Fassung der Über-uns-Seite.</p><!-- /wp:paragraph -->
```

- [ ] **Step 3: Configure languages before seeding**

In `plugin/tests/e2e/global-setup.ts`, insert one line **before** the `wp( 'pediment seed' )` call:

```ts
	// Languages first, always: content written before the languages exist
	// carries no language, is invisible to every translation lookup, and has
	// previously removed a live site's header outright. `wp pediment seed`
	// refuses to run when the two disagree, so this is also what unblocks it.
	wp( `plugin activate polylang` );
	wp( `pediment languages` );

	// Content comes from the fixture theme's seed manifest, applied by the real
	// engine — the suite exercises `wp pediment seed` on every run.
	wp( `pediment seed` );
```

- [ ] **Step 4: Write the e2e spec**

Create `plugin/tests/e2e/multilingual.spec.ts`:

```ts
import { test, expect } from '@playwright/test';
import { execSync } from 'node:child_process';

const WP_ENV_CWD = process.env.WP_ENV_CWD || process.cwd();
const wp = ( cmd: string ) =>
	execSync( `npx wp-env run cli wp ${ cmd }`, { cwd: WP_ENV_CWD, stdio: 'pipe' } )
		.toString()
		.trim();

test.describe( 'multilingual seeding', () => {
	test( 'both languages exist and are configured', () => {
		const json = JSON.parse( wp( `pediment seed --dry-run --json` ) );
		const languages = new Set(
			json.items.filter( ( i ) => i.kind === 'entry' ).map( ( i ) => i.language )
		);

		expect( [ ...languages ].sort() ).toEqual( [ 'de', 'en' ] );
	} );

	test( 'a re-seed plans no writes', () => {
		expect( wp( `pediment seed --dry-run` ) ).toContain( '0 to write' );
	} );

	test( 'the untranslated posts are reported, not failed', () => {
		const plan = wp( `pediment seed --dry-run` );

		expect( plan ).toContain( 'TRANSLATIONS' );
		expect( plan ).toContain( 'sample-insight-one' );
	} );

	test( 'the German page is reachable at its own slug', async ( { page } ) => {
		const response = await page.goto( '/de/ueber-uns/' );

		expect( response?.status() ).toBe( 200 );
		await expect( page.locator( 'body' ) ).toContainText( 'Deutschsprachige Fassung' );
	} );

	test( 'the German language root serves the front page, not a redirect chain', async ( { page } ) => {
		await page.goto( '/de/' );

		expect( new URL( page.url() ).pathname ).toBe( '/de/' );
	} );

	test( 'the header navigation links to German pages on a German page', async ( { page } ) => {
		await page.goto( '/de/ueber-uns/' );

		const header = page.locator( 'header.site-header' );
		await expect( header.getByRole( 'link', { name: 'Kontakt' } ) ).toHaveAttribute(
			'href',
			/\/de\/kontakt\/?$/
		);
	} );

	test( 'the two About pages are one translation group', () => {
		const en = wp( `post list --post_type=page --name=about --field=ID` );
		const de = wp( `post list --post_type=page --name=ueber-uns --field=ID` );

		const linked = wp( `eval 'echo (int) pll_get_post( ${ en }, "de" );'` );

		expect( linked ).toBe( de );
	} );
} );
```

- [ ] **Step 5: Run the WHOLE e2e suite**

```bash
npm run env:stop && npm run env:start
cd plugin && npm run e2e && cd ..
```

Expected: 44 existing tests plus the new ones, all green. Fallout to expect and fix here rather than paper over:

- **Existing specs asserting unprefixed URLs** should still pass — `hide_default => 1` keeps English at `/about/`. If one fails, that is a real behaviour change and needs a decision, not a URL tweak.
- **`seeding.spec.ts`'s `0 to write` assertion** now covers both languages; if it reports writes, a derived slug or a per-language hash is unstable and must be fixed in Tasks 8–9, not asserted around.
- **Nav assertions in existing specs** may now resolve a different `wp_navigation` post because of Task 12's binding — verify the menu they get is the right one rather than relaxing the assertion.

- [ ] **Step 6: Commit**

```bash
git add tests/fixtures/client-theme plugin/tests/e2e
git commit -m "$(cat <<'EOF'
test(e2e): seed the fixture site in two languages

The fixture now declares en + de and seeds itself through the real engine, so
CI exercises translation groups, per-language slugs and the language-bound
header on every run. The six sample posts stay untranslated on purpose: that
keeps the missing-translation notice and the derived `-de` slug rule under
test instead of only documented.

Co-Authored-By: Claude Opus 5 (1M context) <noreply@anthropic.com>
EOF
)"
```

---

### Task 16: Documentation

**Files:**
- Modify: `docs/seeding.md`, `docs/WORDPRESS_TRAPS.md`, `docs/STANDARDS.md`, `plugin/README.md`, `AGENTS.md`, `docs/SESSION_LOG.md`, `docs/BACKLOG.md`, `.distignore`

**Interfaces:**
- Produces: a reader who has never seen this code can add a language to a client site without rediscovering any of the traps.

- [ ] **Step 1: Add the multilingual section to `docs/seeding.md`**

Insert a `## Languages` section after `## The manifest`, covering, each with a worked example:

- the `languages` section and which language is the default
- the per-entry `languages` overrides (`title`, `slug`, `pattern`)
- **the derived slug rule** — no declared slug means `<slug>-<lang>`, and *why*: Polylang does not hook `wp_unique_post_slug`
- the pattern convention — `patterns/<stem>.<lang>.php` carrying `Slug: <pattern>-<lang>`
- `wp pediment languages`, and that `wp pediment seed` refuses to run on a mismatch
- the `TRANSLATIONS` section of a dry-run plan, and that it does not fail the run
- `wp pediment adopt <key> --language=de`

Extend `## Limitations, by design` with:

- media and taxonomies are not translated — one attachment and one term set serve every language
- only Polylang is implemented; the `LanguageProvider` seam is where a WPML adapter would go
- translation *content* is not generated; the seeder reports what is missing and `adopt` brings an editor's translation back into git

- [ ] **Step 2: Add the traps**

Append to `docs/WORDPRESS_TRAPS.md`, matching the file's existing entry format, one entry each for:

1. **`suppress_filters` does not escape Polylang's scoping.** It hooks `parse_query` and mutates `query_vars['tax_query']`; `WP_Query::get_posts()` re-parses that on a branch gated on `! $this->is_singular` which never consults the flag. `lang => ''` is what works, because `PLL_Query::is_already_filtered()` tests only `isset()`.
2. **Polylang's options are in-memory until `shutdown`.** Since 3.7, `update_option()` is invisible to the rest of the request *and* gets overwritten by the stale in-memory copy on save. Use `PLL()->options->merge()` then `->save()`, then `clean_languages_cache()` — language objects cache home URLs derived from those options and that cache outlives the write.
3. **`pll_save_post_translations()` replaces the whole group.** Calling it per language unlinks every language saved before it. Pass the complete map, always.
4. **`wp_navigation` cannot be made translatable by clicking.** Polylang's settings screen lists only `public => true, _builtin => false` post types. Filter `pll_get_post_types` with `$is_settings === false`.
5. **A ref-less `core/navigation` block resolves to the newest `wp_navigation` post.** `block_core_navigation_get_fallback_ref()` picks by date, so every language renders whichever menu was seeded last.
6. **Polylang does not hook `wp_unique_post_slug`.** Two languages declaring the same slug land as `x` and `x-2`, and a slug-enforcing seeder never converges.

- [ ] **Step 3: Point at the new docs**

- `docs/STANDARDS.md` — a line pointing multilingual work at `docs/seeding.md#languages`
- `plugin/README.md` — `wp pediment languages` beside the existing `seed`/`adopt` entries
- `AGENTS.md` — one line: languages are declared in the manifest and configured before seeding, never after

- [ ] **Step 4: Keep dev-only files out of the zip**

Check `.distignore` covers `tests/polylang/`, `phpunit-polylang.xml.dist`, and `tools/`. Add whatever is missing.

```bash
grep -nE "tests|phpunit|tools" .distignore
```

- [ ] **Step 5: Write the session log entry**

Add a `## Session 2026-08-01 — LanguageProvider and Polylang, step 4` entry to `docs/SESSION_LOG.md`, matching the existing format: what shipped, the verification numbers from Task 17, what is documented-not-fixed, and a `### Planned next` naming migration step 5 (scaffolder and `/start`).

- [ ] **Step 6: Park what this step deferred**

Add to `docs/BACKLOG.md` under Medium:

- a WPML adapter (the seam exists; roughly 150 lines when someone needs it)
- per-language media and taxonomy translation, if step 6 shows Workation needs it
- `wp pediment translate` — the AI command that writes the missing `patterns/<slug>.<lang>.php` the notices name
- language-aware `Verifier` post-conditions: a language root serving the wrong front page produces no problem today, only an e2e failure

- [ ] **Step 7: Commit**

```bash
git add docs plugin/README.md AGENTS.md .distignore
git commit -m "$(cat <<'EOF'
docs(language): document the multilingual seeding contract

The six Polylang traps this step paid for are in WORDPRESS_TRAPS.md, so the
next person meets them as documentation rather than as an outage. seeding.md
gains a Languages section covering the manifest shape, the derived-slug rule
and why it exists, the pattern file convention, and the two commands.

Co-Authored-By: Claude Opus 5 (1M context) <noreply@anthropic.com>
EOF
)"
```

---

### Task 17: Full verification and the gated push

**Files:** none modified.

**Interfaces:**
- Produces: a verified branch and, only after explicit user approval, a push.

- [ ] **Step 1: Rebuild from a clean environment**

```bash
npm run env:stop
npm run env:start
cd plugin && npm ci && npm run build && cd ..
```

Starting from a destroyed environment is the point: it proves the fixture seeds itself from nothing, in two languages, the way CI will.

- [ ] **Step 2: Run everything**

```bash
npx wp-env run tests-wordpress --env-cwd=wp-content/plugins/pediment-ai ./vendor/bin/phpunit
npx wp-env run tests-wordpress --env-cwd=wp-content/plugins/pediment-ai ./vendor/bin/phpunit -c phpunit-polylang.xml.dist
cd plugin && composer lint && npm run lint:js && cd ..
npm run lint:colors
npm run lint:blocks
node tools/generate-wpml-config.mjs --check
cd plugin && npm run e2e && cd ..
```

Record the real numbers — test counts, assertion counts, pass/fail. Do not write them down before seeing them.

- [ ] **Step 3: Verify the seeded site by hand**

```bash
npx wp-env run cli wp pediment seed --dry-run
npx wp-env run cli wp pediment languages --dry-run
curl -s -o /dev/null -w "%{http_code} %{url_effective}\n" -L http://localhost:8888/de/
curl -s -o /dev/null -w "%{http_code} %{url_effective}\n" -L http://localhost:8888/de/ueber-uns/
curl -s http://localhost:8888/de/ueber-uns/ | grep -c "de/kontakt"
```

Expected: the dry run reports `0 to write` with a `TRANSLATIONS` section naming the six untranslated posts; `wp pediment languages --dry-run` reports nothing to change; both German URLs return 200 without a redirect chain; the German page's header links to `/de/kontakt/`.

- [ ] **Step 4: Review the whole diff**

```bash
git log --oneline origin/main..HEAD
git diff --stat origin/main...HEAD
```

Check: no version file hand-edited, no stored meta key renamed, no `permalink_structure` write, nothing dev-only escaping into the release zip.

- [ ] **Step 5: Ask before pushing**

Report to the user: what shipped, the verification numbers from Step 2, anything documented-not-fixed, and the exact push command. **Then stop.** Do not push without an explicit yes — the global constraint at the top of this plan is not satisfied by "the tests passed".

```bash
git push origin HEAD:main
```

---

## Self-Review

**Spec coverage (§4.3 and migration step 4):**

| Spec requirement | Task |
|---|---|
| `LanguageProvider` with `Null` and `Polylang` implementations | 3 (Null shipped in step 3) |
| `unscopedQuery()` encapsulates the `lang => ''` idiom exactly once | 3 |
| Languages configured before any content is written | 6, 7 |
| Per-language patterns `patterns/<slug>.<lang>.php` | 8, 9, 13 |
| The seeder reports which translations are missing | 9 |
| `wpml-config.xml` is generated from `block.json` | 14 |
| Nav resolved by `(seed_key, language)` | 11 (seeding), 12 (rendering) |
| Absorbs Workation's `inc/Polylang.php` | 11 (`pll_get_post_types`); the untagged-content tagger is obsolete — the Applier tags on create |
| Absorbs the generic half of `PrimaryNav.php` | 12 |
| Absorbs `tools/polylang-setup.php` | 6 |
| No WPML adapter | Parked in 16 |
| Translation-group linking (`linkTranslations()` was a no-op) | 10, 11 |

**Gaps I am choosing to leave, and where they are recorded:** language-aware `Verifier` post-conditions (a language root serving the wrong front page is caught by e2e, not by phase 5), per-language media and terms, and an AI `translate` command. All three are in the BACKLOG additions in Task 16 rather than silently dropped.

**Type consistency:** `titleFor`/`slugFor`/`patternFor` all take `( string $language, string $default )` and are used with that signature in Tasks 9 and 13. `ContentResolver::resolve()` gains two parameters in Task 9 and both call sites (`DesiredState`, and the `Adopter`'s hash recomputation) are updated there and in Task 13. `RunResult`'s `notices` is the seventh parameter, after `ids`, so every pre-existing construction still compiles. `linkTranslations( array<string,int> )` is the interface as shipped in step 3 — unchanged.

**Riskiest task:** 15. Making the shared fixture bilingual touches every existing e2e test. Its Step 5 names the three failure shapes to expect and says explicitly that each is a bug to fix upstream, not an assertion to relax.
