# Declarative Seeding Engine (Migration Step 3) Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Replace imperative seed-replay with a declarative desired-state engine in the plugin — identity by `_pediment_seed_key`, content arbitrated by `_pediment_seed_hash`, a readable `--dry-run` plan — implementing migration step 3 of `docs/superpowers/specs/2026-07-29-pediment-dev-flow-design.md` §4.2.

**Architecture:** A client theme declares its site structure in `seed/manifest.php`; pattern files supply content. `Pediment\Seeder\Runner` executes five phases in fixed order — resolve desired → resolve actual → diff into a `Plan` → apply → verify — and never guesses: everything it touches is looked up by seed key, never by slug. Content is written only when the persisted row still hashes to what the seeder last wrote; a client edit permanently flips that page to structure-only. The same `Runner` backs `wp pediment seed` and the wp-admin Seeding tab.

**Tech Stack:** PHP 8.1, WordPress 6.9 (`WP_Block_Patterns_Registry`, `wp_insert_post`/`wp_update_post`, `register_post_type`, `wp_navigation` entities), WP-CLI, PHPUnit 9.6 with the WP integration suite, Playwright.

## Global Constraints

- **Never push without explicit user approval.** All work is local until the single gated push in Task 17.
- Work stays on the current branch `pediment-dev-flow-review`, rebased onto `origin/main`; the gated push is `git push origin HEAD:main`. No new branches or worktrees (Conductor workspace *is* the isolation).
- **Nothing existing is removed or renamed**, so this ships as a **minor** (3.1.0) — conventional `feat:` commits only, no `!`, no `Release-As:` footer. Version files are release-please's; never hand-bump.
- **Never rename stored data.** New meta keys are exactly `_pediment_seed_key`, `_pediment_seed_hash`, `_pediment_seed_source`. Options, tables (`wp_pediment_ai_*`), and transients keep their names.
- **The seeder never sets `permalink_structure`.** Forcing pretty permalinks breaks REST in containerized installs (see the comment in `plugin/inc/bootstrap.php` and Bergert-Digital/pediment#47). The seeder flushes rewrite rules **once, at the end, soft** (`flush_rewrite_rules( false )`).
- **The seeder never deletes.** Posts carrying a seed key that is no longer in the manifest are reported as `orphan` and left in place.
- **Multilingual is step 4.** This plan builds the `LanguageProvider` seam and the `NullProvider` only — every phase is written language-aware so step 4 adds an adapter, not a rewrite. No Polylang code, no per-language pattern files, no `wpml-config.xml` generation here.
- New PHP lives under `Pediment\Seeder\` (`plugin/src/Seeder/`) and `Pediment\Language\` (`plugin/src/Language/`); commands under `Pediment\Cli\` (`plugin/wp-cli/`). Both roots are already PSR-4 mapped in `plugin/composer.json` — no autoload changes needed.
- Method naming in `src/` is camelCase (matches `Pediment\Settings\Page`, `Pediment\Chat\*`). Short arrays are allowed; `WordPress.NamingConventions.ValidFunctionName` and Yoda are excluded in `plugin/phpcs.xml.dist`.
- Every task ends with its suite green locally:
  - PHPUnit: `npx wp-env run tests-wordpress --env-cwd=wp-content/plugins/pediment-ai ./vendor/bin/phpunit --filter <Name>`
  - Lint: `cd plugin && composer lint` plus `npm run lint:colors` from the root.
  - wp-env is the project-local one (`npx wp-env`), never `@wordpress/env@latest`.
- Working directory: `/Users/jonas/conductor/workspaces/pediment/west-monroe`.

## Design decisions this plan makes beyond the spec

The spec fixes the architecture; four gaps had to be closed to make it implementable. Each is deliberate — flag them to the user if a review disagrees.

1. **A second meta, `_pediment_seed_source`.** `_pediment_seed_hash` alone cannot tell "git changed" from "nothing changed": it hashes the *persisted* row, and WordPress normalizes markup on write (void tags, texturize, un-slashing — `docs/WORDPRESS_TRAPS.md`), so the persisted row never byte-equals the pattern source. Comparing desired-vs-persisted directly would report `update` for every page on every run and rewrite content forever. `_pediment_seed_source` stores the hash of the *input* (title + resolved content) the seeder last wrote, so "did git change?" is one comparison and "did the client edit?" is the other. `_pediment_seed_hash` keeps exactly the spec's meaning and remains the arbiter.
2. **KSES is suspended for seeder writes.** Seeded content is git-authored pattern markup, not user input. Under WP-CLI there is no current user, so `kses_init_filters()` is active and can mangle block-comment JSON. The Applier wraps writes in `kses_remove_filters()` / restore.
3. **Navigation entities are fully owned.** The spec lists "nav membership" as structure the seeder always owns, so the seeder rewrites the `wp_navigation` entity's items when they differ from the manifest — a client nav edit is reverted, and the dry-run plan shows it before it happens (same contract as slug). If that turns out to be too sharp in practice, the fix is to give nav the same hash arbitration pages get; do not soften it silently.
4. **The manifest covers pages, posts, CPT entries, media, navs, post types, and the site logo.** Pages alone cannot replace `plugin/tests/e2e/fixtures.php` (Task 16) nor carry Workation's two CPTs (step 6). `pages`/`posts`/`entries` all parse into one `EntrySpec` differing only by `post_type`.

## File Structure (end state)

```
plugin/src/Language/
  LanguageProvider.php      interface: languages, defaultLanguage, setLanguage,
                            linkTranslations, translationOf, unscopedQuery
  NullProvider.php          monolingual implementation; the only one in step 3
  LanguageRegistry.php      resolves the active provider via `pediment_language_provider`
plugin/src/Seeder/
  Meta.php                  the three meta-key constants
  ContentHash.php           versioned hash; computes from the persisted row
  Manifest.php              loads + validates seed/manifest.php into value objects
  ManifestError.php         validation failure, message is user-facing
  EntrySpec.php             one page/post/CPT entry as declared
  MediaSpec.php  NavSpec.php  PostTypeSpec.php
  ContentResolver.php       pattern slug -> markup, media placeholders -> URL/ID
  MediaMap.php              seed key -> attachment id/url
  DesiredState.php          phase 1: manifest x languages -> DesiredEntry[]
  DesiredEntry.php
  StateReader.php           phase 2: query by seed key, unscoped by language
  ActualEntry.php
  Differ.php                phase 3: desired x actual -> Plan
  Plan.php  PlanItem.php
  Applier.php               phase 4: writes, language-on-create, hashes after write
  MediaSeeder.php           attachments + site logo
  NavSeeder.php             wp_navigation entities by seed key
  PostTypes.php             registers manifest CPTs on init
  Verifier.php              phase 5: post-conditions, fails loudly
  Runner.php  RunResult.php Reporter.php
plugin/wp-cli/
  SeedCommand.php           wp pediment seed [--dry-run] [--json]
  AdoptCommand.php          wp pediment adopt <key> [--dry-run]
plugin/inc/seeding-admin.php  Settings > Pediment Theme > Seeding tab
plugin/tests/phpunit/Seeder/  one test class per unit above
plugin/tests/phpunit/Language/LanguageProviderTest.php
tests/fixtures/client-theme/
  seed/manifest.php         replaces plugin/tests/e2e/fixtures.php
  seed/media/logo.svg
  patterns/*.php            fixture page content
docs/seeding.md             the manifest reference
DELETED: plugin/tests/e2e/fixtures.php (Task 16)
```

---

### Task 1: Preflight and baseline

**Files:** none modified.

**Interfaces:**
- Produces: HEAD rebased on `origin/main`, all suites green, a recorded baseline sha every later task builds on.

- [ ] **Step 1: Rebase and confirm the starting point**

```bash
git fetch origin
git rebase origin/main
git status --porcelain
grep -m1 "PEDIMENT_AI_VERSION" plugin/plugin.php
```

Expected: clean tree, version `'3.0.0'`. If the version reads lower, step 2 has not shipped — STOP and report; this plan assumes the v3.0.0 plugin-only product is on `main`.

- [ ] **Step 2: Green baseline**

```bash
npm run env:start
npx wp-env run tests-wordpress --env-cwd=wp-content/plugins/pediment-ai ./vendor/bin/phpunit
cd plugin && composer lint && cd ..
node tools/lint-colors.mjs
```

Expected: PHPUnit OK, phpcs no errors, lint:colors clean. A red baseline is not this plan's bug — report it and stop.

- [ ] **Step 3: Record the baseline**

```bash
git rev-parse HEAD
```

Note the sha in the session log; every "expected: FAIL" below assumes this tree.

---

### Task 2: Seed meta keys and the content hash

**Files:**
- Create: `plugin/src/Seeder/Meta.php`, `plugin/src/Seeder/ContentHash.php`
- Test: `plugin/tests/phpunit/Seeder/ContentHashTest.php`

**Interfaces:**
- Produces: `Pediment\Seeder\Meta::KEY|HASH|SOURCE` (string constants) and `Pediment\Seeder\ContentHash::compute( string $title, string $content ): string`, `::forPost( int $postId ): string`, `::matches( string $stored, string $current ): bool`. Every later task uses these; nothing else may compute a hash.

- [ ] **Step 1: Write the failing test**

```php
<?php
// plugin/tests/phpunit/Seeder/ContentHashTest.php

use Pediment\Seeder\ContentHash;

class ContentHashTest extends WP_UnitTestCase {

	public function test_hash_is_version_prefixed_and_stable() {
		$a = ContentHash::compute( 'Home', '<!-- wp:paragraph --><p>Hi</p><!-- /wp:paragraph -->' );
		$b = ContentHash::compute( 'Home', '<!-- wp:paragraph --><p>Hi</p><!-- /wp:paragraph -->' );
		$this->assertSame( $a, $b );
		$this->assertStringStartsWith( '1:', $a );
	}

	public function test_title_and_content_are_both_covered() {
		$base  = ContentHash::compute( 'Home', 'x' );
		$this->assertNotSame( $base, ContentHash::compute( 'Home ', 'x' ) );
		$this->assertNotSame( $base, ContentHash::compute( 'Home', 'y' ) );
		// The separator must not be forgeable by shifting bytes across the boundary.
		$this->assertNotSame( ContentHash::compute( 'ab', 'c' ), ContentHash::compute( 'a', 'bc' ) );
	}

	public function test_for_post_reads_the_persisted_row_not_the_input() {
		// WordPress normalizes `<img alt=""/>` to `<img alt="" />` on write
		// (docs/WORDPRESS_TRAPS.md). Hashing the input would mismatch forever.
		$input = '<!-- wp:image --><figure class="wp-block-image"><img src="http://e.test/a.png" alt=""/></figure><!-- /wp:image -->';
		$id    = self::factory()->post->create( [ 'post_type' => 'page', 'post_title' => 'T', 'post_content' => $input ] );

		$persisted = ContentHash::forPost( $id );

		$this->assertNotSame( ContentHash::compute( 'T', $input ), $persisted );
		$this->assertSame( $persisted, ContentHash::forPost( $id ), 'forPost must be stable across calls' );
	}

	public function test_for_post_ignores_a_stale_object_cache() {
		// The post factory generates a title when none is given, so read the
		// stored title rather than assuming one.
		$id    = self::factory()->post->create( [ 'post_type' => 'page', 'post_content' => 'one' ] );
		$title = get_post( $id )->post_title; // also warms the cache
		$GLOBALS['wpdb']->update( $GLOBALS['wpdb']->posts, [ 'post_content' => 'two' ], [ 'ID' => $id ] );

		$this->assertSame( ContentHash::compute( $title, 'two' ), ContentHash::forPost( $id ) );
	}

	public function test_matches_rejects_empty_and_foreign_versions() {
		$current = ContentHash::compute( 'A', 'B' );
		$this->assertTrue( ContentHash::matches( $current, $current ) );
		$this->assertFalse( ContentHash::matches( '', $current ) );
		$this->assertFalse( ContentHash::matches( '2:' . substr( $current, 2 ), $current ) );
		$this->assertFalse( ContentHash::matches( ContentHash::compute( 'A', 'C' ), $current ) );
	}

	public function test_for_post_returns_empty_string_for_a_missing_post() {
		$this->assertSame( '', ContentHash::forPost( 999999 ) );
	}
}
```

Note: `test_for_post_ignores_a_stale_object_cache` proves `forPost()` re-reads the row rather than trusting a warmed cache — the title is read back from the post because the factory supplies one.

- [ ] **Step 2: Run it and watch it fail**

Run: `npx wp-env run tests-wordpress --env-cwd=wp-content/plugins/pediment-ai ./vendor/bin/phpunit --filter ContentHashTest`
Expected: FAIL — `Class "Pediment\Seeder\ContentHash" not found`.

- [ ] **Step 3: Implement**

```php
<?php
/**
 * Meta keys the seeding engine owns.
 *
 * @package Pediment
 */

declare(strict_types=1);

namespace Pediment\Seeder;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Meta {
	/** Stable identity. Never look seeded content up by slug. */
	public const KEY = '_pediment_seed_key';

	/** Hash of the PERSISTED row as last written by the seeder; arbitrates content. */
	public const HASH = '_pediment_seed_hash';

	/** Hash of the git-side INPUT the seeder last wrote; detects content changes. */
	public const SOURCE = '_pediment_seed_source';
}
```

```php
<?php
/**
 * Versioned content hash used to arbitrate between git and the database.
 *
 * @package Pediment
 */

declare(strict_types=1);

namespace Pediment\Seeder;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class ContentHash {
	/**
	 * Bump when the hashed shape changes. A stored hash carrying a different
	 * version never matches, so pages fall back to "treat as edited" —
	 * structure-only, never a content overwrite.
	 */
	public const VERSION = '1';

	public static function compute( string $title, string $content ): string {
		return self::VERSION . ':' . hash( 'sha256', $title . "\x00" . $content );
	}

	/**
	 * Hash the row as WordPress actually stored it. Hashing the intended input
	 * instead makes every page mismatch on the first re-seed and silently
	 * disables content updates site-wide (spec §4.2).
	 */
	public static function forPost( int $postId ): string {
		clean_post_cache( $postId );
		$post = get_post( $postId );
		if ( ! $post instanceof \WP_Post ) {
			return '';
		}
		return self::compute( (string) $post->post_title, (string) $post->post_content );
	}

	public static function matches( string $stored, string $current ): bool {
		if ( '' === $stored || ! str_starts_with( $stored, self::VERSION . ':' ) ) {
			return false;
		}
		return hash_equals( $stored, $current );
	}
}
```

- [ ] **Step 4: Run it green**

Run: `npx wp-env run tests-wordpress --env-cwd=wp-content/plugins/pediment-ai ./vendor/bin/phpunit --filter ContentHashTest`
Expected: PASS (6 tests).

- [ ] **Step 5: Commit**

```bash
git add plugin/src/Seeder/Meta.php plugin/src/Seeder/ContentHash.php plugin/tests/phpunit/Seeder/ContentHashTest.php
git commit -m "feat(seeder): add seed meta keys and versioned content hash"
```

---

### Task 3: The LanguageProvider seam

**Files:**
- Create: `plugin/src/Language/LanguageProvider.php`, `plugin/src/Language/NullProvider.php`, `plugin/src/Language/LanguageRegistry.php`
- Test: `plugin/tests/phpunit/Language/LanguageProviderTest.php`

**Interfaces:**
- Produces:
  ```php
  interface Pediment\Language\LanguageProvider {
      public function languages(): array;                       // string[]; NullProvider -> ['']
      public function defaultLanguage(): string;                // NullProvider -> ''
      public function setLanguage( int $postId, string $language ): void;
      public function linkTranslations( array $map ): void;     // language => post id
      public function translationOf( int $postId, string $language ): int; // 0 when none
      public function unscopedQuery( array $args ): array;      // WP_Query args, language filter off
  }
  Pediment\Language\LanguageRegistry::provider(): LanguageProvider
  Pediment\Language\LanguageRegistry::reset(): void
  ```
  Every seeder class takes a `LanguageProvider` in its constructor. Step 4 adds `PolylangProvider` and nothing else changes.

- [ ] **Step 1: Write the failing test**

```php
<?php
// plugin/tests/phpunit/Language/LanguageProviderTest.php

use Pediment\Language\LanguageProvider;
use Pediment\Language\LanguageRegistry;
use Pediment\Language\NullProvider;

class LanguageProviderTest extends WP_UnitTestCase {

	public function tear_down(): void {
		remove_all_filters( 'pediment_language_provider' );
		LanguageRegistry::reset();
		parent::tear_down();
	}

	public function test_default_provider_is_monolingual() {
		$provider = LanguageRegistry::provider();
		$this->assertInstanceOf( NullProvider::class, $provider );
		$this->assertSame( [ '' ], $provider->languages() );
		$this->assertSame( '', $provider->defaultLanguage() );
	}

	public function test_null_provider_language_writes_are_no_ops() {
		$id       = self::factory()->post->create( [ 'post_type' => 'page' ] );
		$provider = new NullProvider();

		$provider->setLanguage( $id, '' );
		$provider->linkTranslations( [ '' => $id ] );

		$this->assertSame( 0, $provider->translationOf( $id, 'de' ) );
		$this->assertSame( $id, $provider->translationOf( $id, '' ) );
	}

	public function test_unscoped_query_is_identity_for_the_null_provider() {
		$args = [ 'post_type' => 'page', 'posts_per_page' => -1 ];
		$this->assertSame( $args, ( new NullProvider() )->unscopedQuery( $args ) );
	}

	public function test_filter_swaps_the_provider() {
		$fake = new class() extends NullProvider {
			public function languages(): array {
				return [ 'en', 'de' ];
			}
		};
		add_filter( 'pediment_language_provider', static fn() => $fake );
		LanguageRegistry::reset();

		$this->assertSame( [ 'en', 'de' ], LanguageRegistry::provider()->languages() );
	}

	public function test_registry_memoizes() {
		$this->assertSame( LanguageRegistry::provider(), LanguageRegistry::provider() );
	}

	public function test_non_provider_filter_return_is_ignored() {
		add_filter( 'pediment_language_provider', static fn() => 'nonsense' );
		LanguageRegistry::reset();

		$this->assertInstanceOf( LanguageProvider::class, LanguageRegistry::provider() );
	}
}
```

- [ ] **Step 2: Run it and watch it fail**

Run: `... --filter LanguageProviderTest`
Expected: FAIL — `Class "Pediment\Language\LanguageRegistry" not found`.

- [ ] **Step 3: Implement the interface**

```php
<?php
/**
 * The one seam between the seeding engine and a multilingual plugin.
 *
 * @package Pediment
 */

declare(strict_types=1);

namespace Pediment\Language;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

interface LanguageProvider {
	/** @return string[] Configured language codes; [''] when monolingual. */
	public function languages(): array;

	public function defaultLanguage(): string;

	/** Assign a language. MUST be called in the same write path as creation. */
	public function setLanguage( int $postId, string $language ): void;

	/** @param array<string,int> $map language code => post ID */
	public function linkTranslations( array $map ): void;

	/** @return int Post ID of the translation, 0 when there is none. */
	public function translationOf( int $postId, string $language ): int;

	/**
	 * Return query args that see every language.
	 *
	 * `suppress_filters` does NOT escape a multilingual plugin's parse_query
	 * scoping (dd23712); the idiom that does lives in the adapter, once.
	 *
	 * @param array<string,mixed> $args
	 * @return array<string,mixed>
	 */
	public function unscopedQuery( array $args ): array;
}
```

- [ ] **Step 4: Implement NullProvider and LanguageRegistry**

```php
<?php
/**
 * Monolingual implementation. Every site runs this code path, which is what
 * makes the multilingual path testable (spec §4.3).
 *
 * @package Pediment
 */

declare(strict_types=1);

namespace Pediment\Language;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class NullProvider implements LanguageProvider {
	public function languages(): array {
		return [ '' ];
	}

	public function defaultLanguage(): string {
		return '';
	}

	public function setLanguage( int $postId, string $language ): void {
		// No language taxonomy on a monolingual site.
	}

	public function linkTranslations( array $map ): void {
		// Nothing to link.
	}

	public function translationOf( int $postId, string $language ): int {
		return '' === $language ? $postId : 0;
	}

	public function unscopedQuery( array $args ): array {
		return $args;
	}
}
```

```php
<?php
/**
 * Resolves the active LanguageProvider.
 *
 * @package Pediment
 */

declare(strict_types=1);

namespace Pediment\Language;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class LanguageRegistry {
	private static ?LanguageProvider $provider = null;

	public static function provider(): LanguageProvider {
		if ( self::$provider instanceof LanguageProvider ) {
			return self::$provider;
		}

		/**
		 * Filter the active language provider.
		 *
		 * @param LanguageProvider $provider Defaults to the monolingual NullProvider.
		 */
		$filtered = apply_filters( 'pediment_language_provider', new NullProvider() );

		self::$provider = $filtered instanceof LanguageProvider ? $filtered : new NullProvider();

		return self::$provider;
	}

	public static function reset(): void {
		self::$provider = null;
	}
}
```

- [ ] **Step 5: Run it green**

Run: `... --filter LanguageProviderTest`
Expected: PASS (6 tests).

- [ ] **Step 6: Commit**

```bash
git add plugin/src/Language plugin/tests/phpunit/Language
git commit -m "feat(language): add LanguageProvider seam with a null implementation"
```

---

### Task 4: Manifest loading and validation

**Files:**
- Create: `plugin/src/Seeder/Manifest.php`, `ManifestError.php`, `EntrySpec.php`, `MediaSpec.php`, `NavSpec.php`, `PostTypeSpec.php`
- Test: `plugin/tests/phpunit/Seeder/ManifestTest.php`

**Interfaces:**
- Consumes: nothing from earlier tasks.
- Produces:
  ```php
  final class Pediment\Seeder\EntrySpec {
      public function __construct(
          public readonly string $key,        // manifest key, e.g. 'guide/faq'
          public readonly string $postType,   // 'page' | 'post' | CPT slug
          public readonly string $title,
          public readonly string $slug,       // defaults to the last '/'-segment of $key
          public readonly ?string $parent,    // parent seed key, or null
          public readonly ?string $pattern,   // registered pattern slug
          public readonly ?string $content,   // literal markup; mutually exclusive with $pattern
          public readonly bool $frontPage,
          public readonly bool $postsPage,
          public readonly int $menuOrder,
          public readonly array $terms,       // taxonomy => string[] term slugs
      ) {}
  }
  final class MediaSpec    { public readonly string $key; public readonly string $file; public readonly string $title; }
  final class NavSpec      { public readonly string $key; public readonly string $title; public readonly array $items; }
  final class PostTypeSpec { public readonly string $slug; public readonly array $args; }

  final class Pediment\Seeder\Manifest {
      public static function load(): ?self;                                  // null when the theme ships none
      public static function fromArray( array $raw, string $baseDir ): self; // throws ManifestError
      public function path(): string;
      public function baseDir(): string;
      /** @return array<string,EntrySpec>    */ public function entries(): array;
      /** @return array<string,MediaSpec>    */ public function media(): array;
      /** @return array<string,NavSpec>      */ public function navs(): array;
      /** @return array<string,PostTypeSpec> */ public function postTypes(): array;
      public function siteLogo(): string;   // media key or ''
      /** @return EntrySpec[] Parents before children. */ public function entriesInDependencyOrder(): array;
  }
  ```
  `Manifest::load()` reads `get_stylesheet_directory() . '/seed/manifest.php'` and then applies the `pediment_seed_manifest` filter (array in, array out) so tests and mu-plugins can inject one.

- [ ] **Step 1: Write the failing test**

```php
<?php
// plugin/tests/phpunit/Seeder/ManifestTest.php

use Pediment\Seeder\Manifest;
use Pediment\Seeder\ManifestError;

class ManifestTest extends WP_UnitTestCase {

	private function raw(): array {
		return [
			'version' => 1,
			'pages'   => [
				'home'      => [ 'title' => 'Home', 'pattern' => 'pediment/pediment-landing', 'front_page' => true ],
				'blog'      => [ 'title' => 'Blog', 'content' => '', 'posts_page' => true ],
				'guide'     => [ 'title' => 'Guide', 'content' => '<p>g</p>' ],
				'guide/faq' => [ 'title' => 'FAQ', 'content' => '<p>f</p>', 'parent' => 'guide' ],
			],
			'posts'   => [
				'sample-one' => [ 'title' => 'Sample one', 'content' => '<p>s</p>', 'terms' => [ 'category' => [ 'insights' ] ] ],
			],
		];
	}

	public function test_defaults_are_derived_from_the_key() {
		$m   = Manifest::fromArray( $this->raw(), '/tmp/theme' );
		$faq = $m->entries()['guide/faq'];

		$this->assertSame( 'faq', $faq->slug, 'slug defaults to the last key segment' );
		$this->assertSame( 'guide', $faq->parent );
		$this->assertSame( 'page', $faq->postType );
		$this->assertSame( 0, $faq->menuOrder );
		$this->assertSame( 'post', $m->entries()['sample-one']->postType );
		$this->assertSame( [ 'category' => [ 'insights' ] ], $m->entries()['sample-one']->terms );
	}

	public function test_explicit_slug_wins_so_a_page_can_be_renamed_without_losing_identity() {
		$raw                            = $this->raw();
		$raw['pages']['guide']['slug']  = 'handbook';
		$this->assertSame( 'handbook', Manifest::fromArray( $raw, '/tmp/theme' )->entries()['guide']->slug );
	}

	public function test_dependency_order_puts_parents_first() {
		$keys = array_map(
			static fn( $e ) => $e->key,
			Manifest::fromArray( $this->raw(), '/tmp/theme' )->entriesInDependencyOrder()
		);
		$this->assertLessThan( array_search( 'guide/faq', $keys, true ), array_search( 'guide', $keys, true ) );
	}

	public function test_missing_title_is_a_validation_error() {
		$raw = $this->raw();
		unset( $raw['pages']['home']['title'] );

		$this->expectException( ManifestError::class );
		$this->expectExceptionMessageMatches( '/pages\.home.*title/' );
		Manifest::fromArray( $raw, '/tmp/theme' );
	}

	public function test_both_pattern_and_content_is_a_validation_error() {
		$raw                            = $this->raw();
		$raw['pages']['guide']['pattern'] = 'pediment/prose-article';

		$this->expectException( ManifestError::class );
		Manifest::fromArray( $raw, '/tmp/theme' );
	}

	public function test_unknown_parent_is_a_validation_error() {
		$raw                                = $this->raw();
		$raw['pages']['guide/faq']['parent'] = 'nope';

		$this->expectException( ManifestError::class );
		$this->expectExceptionMessageMatches( '/nope/' );
		Manifest::fromArray( $raw, '/tmp/theme' );
	}

	public function test_parent_cycle_is_a_validation_error() {
		$raw = [
			'pages' => [
				'a' => [ 'title' => 'A', 'content' => '', 'parent' => 'b' ],
				'b' => [ 'title' => 'B', 'content' => '', 'parent' => 'a' ],
			],
		];

		$this->expectException( ManifestError::class );
		$this->expectExceptionMessageMatches( '/cycle/i' );
		Manifest::fromArray( $raw, '/tmp/theme' );
	}

	public function test_a_key_reused_across_sections_is_a_validation_error() {
		$raw                    = $this->raw();
		$raw['posts']['home']   = [ 'title' => 'Clash', 'content' => '' ];

		$this->expectException( ManifestError::class );
		$this->expectExceptionMessageMatches( '/duplicate seed key/i' );
		Manifest::fromArray( $raw, '/tmp/theme' );
	}

	public function test_more_than_one_front_page_is_a_validation_error() {
		$raw                                = $this->raw();
		$raw['pages']['guide']['front_page'] = true;

		$this->expectException( ManifestError::class );
		Manifest::fromArray( $raw, '/tmp/theme' );
	}

	public function test_media_paths_resolve_against_the_manifest_directory() {
		$dir = get_temp_dir() . 'pediment-manifest-test';
		wp_mkdir_p( $dir . '/seed/media' );
		file_put_contents( $dir . '/seed/media/logo.svg', '<svg xmlns="http://www.w3.org/2000/svg"/>' );

		$m = Manifest::fromArray(
			[ 'media' => [ 'logo' => [ 'file' => 'seed/media/logo.svg', 'title' => 'Logo' ] ], 'site' => [ 'logo' => 'logo' ] ],
			$dir
		);

		$this->assertSame( $dir . '/seed/media/logo.svg', $m->media()['logo']->file );
		$this->assertSame( 'logo', $m->siteLogo() );
	}

	public function test_missing_media_file_is_a_validation_error() {
		$this->expectException( ManifestError::class );
		$this->expectExceptionMessageMatches( '/gone\.jpg/' );
		Manifest::fromArray( [ 'media' => [ 'x' => [ 'file' => 'seed/media/gone.jpg' ] ] ], '/tmp/theme' );
	}

	public function test_site_logo_must_reference_a_declared_media_key() {
		$this->expectException( ManifestError::class );
		Manifest::fromArray( [ 'site' => [ 'logo' => 'nope' ] ], '/tmp/theme' );
	}

	public function test_nav_items_must_reference_declared_entries() {
		$raw         = $this->raw();
		$raw['navs'] = [ 'primary' => [ 'title' => 'Primary', 'items' => [ [ 'entry' => 'ghost' ] ] ] ];

		$this->expectException( ManifestError::class );
		$this->expectExceptionMessageMatches( '/ghost/' );
		Manifest::fromArray( $raw, '/tmp/theme' );
	}

	public function test_post_types_are_parsed_with_sane_defaults() {
		$m = Manifest::fromArray(
			[ 'post_types' => [ 'listing' => [ 'label' => 'Listings', 'has_archive' => true ] ] ],
			'/tmp/theme'
		);
		$spec = $m->postTypes()['listing'];

		$this->assertSame( 'listing', $spec->slug );
		$this->assertTrue( $spec->args['public'] );
		$this->assertTrue( $spec->args['show_in_rest'], 'CPT entries must be block-editable' );
		$this->assertSame( 'Listings', $spec->args['label'] );
	}

	public function test_load_returns_null_without_a_theme_manifest_and_honours_the_filter() {
		$this->assertNull( Manifest::load() );

		add_filter( 'pediment_seed_manifest', fn() => $this->raw() );
		$m = Manifest::load();
		remove_all_filters( 'pediment_seed_manifest' );

		$this->assertNotNull( $m );
		$this->assertCount( 5, $m->entries() );
	}
}
```

- [ ] **Step 2: Run it and watch it fail**

Run: `... --filter ManifestTest`
Expected: FAIL — `Class "Pediment\Seeder\Manifest" not found`.

- [ ] **Step 3: Implement the value objects**

```php
<?php
declare(strict_types=1);

namespace Pediment\Seeder;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** A manifest that cannot be trusted. The message is shown to the operator verbatim. */
final class ManifestError extends \RuntimeException {}
```

```php
<?php
declare(strict_types=1);

namespace Pediment\Seeder;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class EntrySpec {
	/** @param array<string,string[]> $terms */
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
		public readonly array $terms
	) {}
}
```

```php
<?php
declare(strict_types=1);

namespace Pediment\Seeder;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class MediaSpec {
	public function __construct(
		public readonly string $key,
		public readonly string $file,
		public readonly string $title
	) {}
}
```

```php
<?php
declare(strict_types=1);

namespace Pediment\Seeder;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class NavSpec {
	/** @param array<int,array{entry?:string,url?:string,label?:string}> $items */
	public function __construct(
		public readonly string $key,
		public readonly string $title,
		public readonly array $items
	) {}
}
```

```php
<?php
declare(strict_types=1);

namespace Pediment\Seeder;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class PostTypeSpec {
	/** @param array<string,mixed> $args register_post_type() args */
	public function __construct(
		public readonly string $slug,
		public readonly array $args
	) {}
}
```

- [ ] **Step 4: Implement Manifest**

```php
<?php
/**
 * Loads and validates a client theme's seed manifest.
 *
 * The manifest declares STRUCTURE (which entries exist, where they sit, what
 * they are called); pattern files supply CONTENT. Validation is strict and
 * fails before anything is written — a manifest error must never become a
 * half-seeded database.
 *
 * @package Pediment
 */

declare(strict_types=1);

namespace Pediment\Seeder;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Manifest {
	public const RELATIVE_PATH = 'seed/manifest.php';

	private static ?self $cache = null;
	private static bool $loaded = false;

	/**
	 * @param array<string,EntrySpec>    $entries
	 * @param array<string,MediaSpec>    $media
	 * @param array<string,NavSpec>      $navs
	 * @param array<string,PostTypeSpec> $postTypes
	 */
	private function __construct(
		private string $path,
		private string $baseDir,
		private array $entries,
		private array $media,
		private array $navs,
		private array $postTypes,
		private string $siteLogo
	) {}

	/**
	 * Load and validate the active theme's manifest.
	 *
	 * Memoized per request: `PostTypes` calls this on every `init`, and without
	 * the memo each page load would re-read the file, re-validate every entry,
	 * and stat every declared media file. Seed runs and tests call
	 * `resetCache()` first so they always see the file as it is now.
	 */
	public static function load(): ?self {
		if ( self::$loaded ) {
			return self::$cache;
		}

		$baseDir = untrailingslashit( get_stylesheet_directory() );
		$path    = $baseDir . '/' . self::RELATIVE_PATH;
		$raw     = is_readable( $path ) ? include $path : null;

		/**
		 * Filter the raw seed manifest array.
		 *
		 * @param array|null $raw     Manifest array, or null when the theme ships none.
		 * @param string     $baseDir Stylesheet directory.
		 */
		$raw = apply_filters( 'pediment_seed_manifest', $raw, $baseDir );

		if ( ! is_array( $raw ) ) {
			self::$cache  = null;
			self::$loaded = true;
			return null;
		}

		// fromArray() throws on an invalid manifest, and the memo is only set
		// after it returns — an error is never cached, so fixing the file does
		// not require a new request.
		$manifest = self::fromArray( $raw, $baseDir, is_readable( $path ) ? $path : 'pediment_seed_manifest filter' );

		self::$cache  = $manifest;
		self::$loaded = true;

		return $manifest;
	}

	/** Drop the per-request memo. Call before any read that must see current state. */
	public static function resetCache(): void {
		self::$cache  = null;
		self::$loaded = false;
	}

	/** @param array<string,mixed> $raw */
	public static function fromArray( array $raw, string $baseDir, string $path = '(array)' ): self {
		$baseDir = untrailingslashit( $baseDir );

		$entries = [];
		foreach ( [ 'pages' => 'page', 'posts' => 'post', 'entries' => '' ] as $section => $defaultType ) {
			foreach ( (array) ( $raw[ $section ] ?? [] ) as $key => $declared ) {
				$key = (string) $key;
				if ( isset( $entries[ $key ] ) ) {
					throw new ManifestError( "Duplicate seed key '{$key}' (declared more than once across pages/posts/entries)." );
				}
				$entries[ $key ] = self::entry( $section, $key, (array) $declared, $defaultType );
			}
		}

		self::validateRelations( $entries );

		$media = [];
		foreach ( (array) ( $raw['media'] ?? [] ) as $key => $declared ) {
			$key      = (string) $key;
			$declared = (array) $declared;
			$file     = (string) ( $declared['file'] ?? '' );
			if ( '' === $file ) {
				throw new ManifestError( "media.{$key}: 'file' is required." );
			}
			$absolute = path_is_absolute( $file ) ? $file : $baseDir . '/' . ltrim( $file, '/' );
			if ( ! is_readable( $absolute ) ) {
				throw new ManifestError( "media.{$key}: file not found — {$absolute}" );
			}
			$media[ $key ] = new MediaSpec( $key, $absolute, (string) ( $declared['title'] ?? $key ) );
		}

		$navs = [];
		foreach ( (array) ( $raw['navs'] ?? [] ) as $key => $declared ) {
			$key      = (string) $key;
			$declared = (array) $declared;
			$items    = [];
			foreach ( (array) ( $declared['items'] ?? [] ) as $index => $item ) {
				$item = (array) $item;
				if ( isset( $item['entry'] ) ) {
					$target = (string) $item['entry'];
					if ( ! isset( $entries[ $target ] ) ) {
						throw new ManifestError( "navs.{$key}.items.{$index}: unknown entry '{$target}'." );
					}
				} elseif ( ! isset( $item['url'], $item['label'] ) ) {
					throw new ManifestError( "navs.{$key}.items.{$index}: needs either 'entry' or both 'url' and 'label'." );
				}
				$items[] = $item;
			}
			$navs[ $key ] = new NavSpec( $key, (string) ( $declared['title'] ?? ucfirst( $key ) ), $items );
		}

		$postTypes = [];
		foreach ( (array) ( $raw['post_types'] ?? [] ) as $slug => $declared ) {
			$slug               = (string) $slug;
			$postTypes[ $slug ] = new PostTypeSpec(
				$slug,
				array_merge(
					[
						'public'       => true,
						'show_in_rest' => true,
						'has_archive'  => false,
						'supports'     => [ 'title', 'editor', 'excerpt', 'thumbnail', 'custom-fields' ],
						'label'        => ucfirst( $slug ),
					],
					(array) $declared
				)
			);
		}

		$siteLogo = (string) ( $raw['site']['logo'] ?? '' );
		if ( '' !== $siteLogo && ! isset( $media[ $siteLogo ] ) ) {
			throw new ManifestError( "site.logo: '{$siteLogo}' is not a declared media key." );
		}

		return new self( $path, $baseDir, $entries, $media, $navs, $postTypes, $siteLogo );
	}

	/** @param array<string,mixed> $declared */
	private static function entry( string $section, string $key, array $declared, string $defaultType ): EntrySpec {
		$title = (string) ( $declared['title'] ?? '' );
		if ( '' === $title ) {
			throw new ManifestError( "{$section}.{$key}: 'title' is required." );
		}

		$postType = (string) ( $declared['post_type'] ?? $defaultType );
		if ( '' === $postType ) {
			throw new ManifestError( "{$section}.{$key}: 'post_type' is required for entries." );
		}

		$hasPattern = isset( $declared['pattern'] );
		$hasContent = array_key_exists( 'content', $declared );
		if ( $hasPattern && $hasContent ) {
			throw new ManifestError( "{$section}.{$key}: declare either 'pattern' or 'content', not both." );
		}
		if ( ! $hasPattern && ! $hasContent ) {
			throw new ManifestError( "{$section}.{$key}: declare either 'pattern' or 'content'." );
		}

		$segments = explode( '/', $key );
		$slug     = (string) ( $declared['slug'] ?? end( $segments ) );
		if ( sanitize_title( $slug ) !== $slug ) {
			throw new ManifestError( "{$section}.{$key}: slug '{$slug}' is not a valid post slug." );
		}

		$terms = [];
		foreach ( (array) ( $declared['terms'] ?? [] ) as $taxonomy => $slugs ) {
			$terms[ (string) $taxonomy ] = array_values( array_map( 'strval', (array) $slugs ) );
		}

		return new EntrySpec(
			$key,
			$postType,
			$title,
			$slug,
			isset( $declared['parent'] ) ? (string) $declared['parent'] : null,
			$hasPattern ? (string) $declared['pattern'] : null,
			$hasContent ? (string) $declared['content'] : null,
			! empty( $declared['front_page'] ),
			! empty( $declared['posts_page'] ),
			(int) ( $declared['menu_order'] ?? 0 ),
			$terms
		);
	}

	/** @param array<string,EntrySpec> $entries */
	private static function validateRelations( array $entries ): void {
		$fronts = [];
		$posts  = [];
		foreach ( $entries as $key => $entry ) {
			if ( $entry->frontPage ) {
				$fronts[] = $key;
			}
			if ( $entry->postsPage ) {
				$posts[] = $key;
			}
			if ( null === $entry->parent ) {
				continue;
			}
			if ( ! isset( $entries[ $entry->parent ] ) ) {
				throw new ManifestError( "{$key}: parent '{$entry->parent}' is not declared." );
			}

			$seen   = [ $key => true ];
			$cursor = $entry->parent;
			while ( null !== $cursor ) {
				if ( isset( $seen[ $cursor ] ) ) {
					throw new ManifestError( "Parent cycle detected at '{$key}'." );
				}
				$seen[ $cursor ] = true;
				$cursor          = $entries[ $cursor ]->parent ?? null;
			}
		}

		if ( count( $fronts ) > 1 ) {
			throw new ManifestError( 'Only one entry may set front_page; got: ' . implode( ', ', $fronts ) . '.' );
		}
		if ( count( $posts ) > 1 ) {
			throw new ManifestError( 'Only one entry may set posts_page; got: ' . implode( ', ', $posts ) . '.' );
		}
	}

	public function path(): string {
		return $this->path;
	}

	public function baseDir(): string {
		return $this->baseDir;
	}

	/** @return array<string,EntrySpec> */
	public function entries(): array {
		return $this->entries;
	}

	/** @return array<string,MediaSpec> */
	public function media(): array {
		return $this->media;
	}

	/** @return array<string,NavSpec> */
	public function navs(): array {
		return $this->navs;
	}

	/** @return array<string,PostTypeSpec> */
	public function postTypes(): array {
		return $this->postTypes;
	}

	public function siteLogo(): string {
		return $this->siteLogo;
	}

	/**
	 * Parents before children, so the applier always knows a parent's post ID
	 * by the time it writes the child.
	 *
	 * @return EntrySpec[]
	 */
	public function entriesInDependencyOrder(): array {
		$ordered = $this->entries;
		uasort(
			$ordered,
			fn( EntrySpec $a, EntrySpec $b ): int => $this->depth( $a ) <=> $this->depth( $b )
		);
		return array_values( $ordered );
	}

	private function depth( EntrySpec $entry ): int {
		$depth  = 0;
		$cursor = $entry->parent;
		while ( null !== $cursor && isset( $this->entries[ $cursor ] ) ) {
			++$depth;
			$cursor = $this->entries[ $cursor ]->parent;
		}
		return $depth;
	}
}
```

- [ ] **Step 5: Run it green**

Run: `... --filter ManifestTest`
Expected: PASS. `uasort` is stable in PHP 8, so equal-depth entries keep manifest order.

Cover every declared failure mode, not only the ones written out above: the tests as listed leave four untested (neither `pattern` nor `content`; an invalid slug; two entries setting `posts_page`; a self-parent cycle) and three asserting only that *some* `ManifestError` was thrown. Add the missing tests and give every validation test an `expectExceptionMessageMatches()` — a reordered check must not be able to pass the suite by throwing a different error. That brings the file to 19 tests.

- [ ] **Step 6: Commit**

```bash
git add plugin/src/Seeder plugin/tests/phpunit/Seeder/ManifestTest.php
git commit -m "feat(seeder): load and validate the declarative seed manifest"
```

---

### Task 5: Content resolution and the media map

**Files:**
- Create: `plugin/src/Seeder/ContentResolver.php`, `plugin/src/Seeder/MediaMap.php`
- Test: `plugin/tests/phpunit/Seeder/ContentResolverTest.php`

**Interfaces:**
- Consumes: `EntrySpec` (Task 4).
- Produces:
  ```php
  final class Pediment\Seeder\MediaMap {
      public function __construct( array $ids );          // seed key => attachment ID
      public function id( string $key ): int;             // 0 when unseeded
      public function url( string $key ): string;         // '' when unseeded
      public function has( string $key ): bool;
  }
  final class Pediment\Seeder\ContentResolver {
      public function __construct( MediaMap $media );
      public function resolve( EntrySpec $entry ): string;   // throws ManifestError on a missing pattern
      /** @return string[] media keys the MOST RECENT resolve() call could not resolve */
      public function unresolvedMediaKeys(): array;
  }
  ```
  Placeholders in pattern markup: `{{media_url:<key>}}` and `{{media_id:<key>}}`. Unseeded keys resolve to `PEDIMENT_SEED_MEDIA_URL:<key>` and `0` respectively, so a dry-run on a fresh site still produces a readable plan.

  Report unresolved media from the resolver's own record, not by scanning the output: an unseeded `{{media_id:…}}` resolves to `0`, which is indistinguishable from a legitimate zero in the emitted markup. `resolve()` records the keys it could not resolve and `unresolvedMediaKeys()` returns them, so an id-only reference is reported just like a url one.

- [ ] **Step 1: Write the failing test**

```php
<?php
// plugin/tests/phpunit/Seeder/ContentResolverTest.php

use Pediment\Seeder\ContentResolver;
use Pediment\Seeder\Manifest;
use Pediment\Seeder\ManifestError;
use Pediment\Seeder\MediaMap;

class ContentResolverTest extends WP_UnitTestCase {

	private function entry( array $declared ): \Pediment\Seeder\EntrySpec {
		return Manifest::fromArray( [ 'pages' => [ 'x' => $declared + [ 'title' => 'X' ] ] ], '/tmp/theme' )->entries()['x'];
	}

	public function test_literal_content_passes_through() {
		$resolver = new ContentResolver( new MediaMap( [] ) );
		$this->assertSame( '<p>hi</p>', $resolver->resolve( $this->entry( [ 'content' => '<p>hi</p>' ] ) ) );
	}

	public function test_pattern_content_comes_from_the_registry() {
		do_action( 'init' );
		$resolver = new ContentResolver( new MediaMap( [] ) );

		$content = $resolver->resolve( $this->entry( [ 'pattern' => 'pediment/prose-article' ] ) );

		$this->assertNotSame( '', $content );
		$this->assertSame(
			WP_Block_Patterns_Registry::get_instance()->get_registered( 'pediment/prose-article' )['content'],
			$content
		);
	}

	public function test_unregistered_pattern_fails_loudly() {
		$resolver = new ContentResolver( new MediaMap( [] ) );

		$this->expectException( ManifestError::class );
		$this->expectExceptionMessageMatches( '/client\/ghost/' );
		$resolver->resolve( $this->entry( [ 'pattern' => 'client/ghost' ] ) );
	}

	public function test_media_placeholders_resolve_to_url_and_id() {
		$attachment = self::factory()->attachment->create_object(
			[ 'file' => 'hero.png', 'post_mime_type' => 'image/png' ]
		);
		$resolver = new ContentResolver( new MediaMap( [ 'hero' => $attachment ] ) );

		$content = $resolver->resolve(
			$this->entry( [ 'content' => '<img src="{{media_url:hero}}" data-id="{{media_id:hero}}" />' ] )
		);

		$this->assertStringContainsString( wp_get_attachment_url( $attachment ), $content );
		$this->assertStringContainsString( 'data-id="' . $attachment . '"', $content );
		$this->assertSame( [], $resolver->unresolvedMediaKeys() );
	}

	public function test_unseeded_media_url_resolves_to_a_reportable_sentinel() {
		$resolver = new ContentResolver( new MediaMap( [] ) );

		$content = $resolver->resolve( $this->entry( [ 'content' => '<img src="{{media_url:hero}}" />' ] ) );

		$this->assertStringContainsString( 'PEDIMENT_SEED_MEDIA_URL:hero', $content );
		$this->assertSame( [ 'hero' ], $resolver->unresolvedMediaKeys() );
	}

	public function test_an_unseeded_media_id_is_reported_even_though_it_emits_a_bare_zero() {
		$resolver = new ContentResolver( new MediaMap( [] ) );

		$content = $resolver->resolve( $this->entry( [ 'content' => '<!-- wp:image {"id":{{media_id:hero}}} /-->' ] ) );

		$this->assertStringContainsString( '"id":0', $content );
		$this->assertSame( [ 'hero' ], $resolver->unresolvedMediaKeys(), 'a bare 0 is invisible in the markup; the resolver must remember it' );
	}

	public function test_unresolved_keys_are_scoped_to_the_last_resolve_call() {
		$resolver = new ContentResolver( new MediaMap( [] ) );
		$resolver->resolve( $this->entry( [ 'content' => '{{media_url:hero}}' ] ) );

		$resolver->resolve( $this->entry( [ 'content' => '<p>no media here</p>' ] ) );

		$this->assertSame( [], $resolver->unresolvedMediaKeys() );
	}
}
```

- [ ] **Step 2: Run it and watch it fail**

Run: `... --filter ContentResolverTest`
Expected: FAIL — `Class "Pediment\Seeder\ContentResolver" not found`.

- [ ] **Step 3: Implement**

```php
<?php
/**
 * Seed key => attachment lookups for content resolution.
 *
 * @package Pediment
 */

declare(strict_types=1);

namespace Pediment\Seeder;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class MediaMap {
	/** @param array<string,int> $ids seed key => attachment ID */
	public function __construct( private array $ids = [] ) {}

	public function has( string $key ): bool {
		return ! empty( $this->ids[ $key ] );
	}

	public function id( string $key ): int {
		return (int) ( $this->ids[ $key ] ?? 0 );
	}

	public function url( string $key ): string {
		$id = $this->id( $key );
		return $id > 0 ? (string) wp_get_attachment_url( $id ) : '';
	}
}
```

```php
<?php
/**
 * Turns an EntrySpec into the block markup the seeder intends to write.
 *
 * Content is sourced from registered patterns rather than hand-copied markup,
 * so seeded pages can never drift from the patterns the product ships.
 *
 * @package Pediment
 */

declare(strict_types=1);

namespace Pediment\Seeder;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class ContentResolver {
	private const SENTINEL = 'PEDIMENT_SEED_MEDIA_URL:';

	/** @var array<string,string> Media keys the last resolve() call could not resolve. */
	private array $unresolved = [];

	public function __construct( private MediaMap $media ) {}

	public function resolve( EntrySpec $entry ): string {
		$content = $entry->content;

		if ( null === $content ) {
			$pattern = \WP_Block_Patterns_Registry::get_instance()->get_registered( (string) $entry->pattern );
			if ( ! is_array( $pattern ) || ! isset( $pattern['content'] ) ) {
				throw new ManifestError(
					"{$entry->key}: pattern '{$entry->pattern}' is not registered. Patterns register on `init`; check the slug and that the file lives in the theme's patterns/ directory."
				);
			}
			$content = (string) $pattern['content'];
		}

		return $this->rewriteMedia( $content );
	}

	/** @return string[] */
	public function unresolvedMediaKeys(): array {
		return array_values( $this->unresolved );
	}

	private function rewriteMedia( string $content ): string {
		$this->unresolved = [];

		$rewritten = preg_replace_callback(
			'/\{\{media_(url|id):([a-z0-9_\-\/]+)\}\}/i',
			function ( array $m ): string {
				$key = $m[2];
				if ( ! $this->media->has( $key ) ) {
					// An unseeded id resolves to a bare 0, which no amount of
					// scanning the output can tell from a real one — so record it.
					$this->unresolved[ $key ] = $key;
				}
				if ( 'id' === strtolower( $m[1] ) ) {
					return (string) $this->media->id( $key );
				}
				return $this->media->has( $key ) ? $this->media->url( $key ) : self::SENTINEL . $key;
			},
			$content
		);

		if ( null === $rewritten ) {
			// preg_replace_callback returns null on a PCRE failure. Falling back
			// to '' here would seed an empty page — the exact failure this engine exists to prevent.
			throw new ManifestError( 'Media placeholder rewriting failed (PCRE error: ' . preg_last_error_msg() . ').' );
		}

		return $rewritten;
	}
}
```

- [ ] **Step 4: Run it green**

Run: `... --filter ContentResolverTest`
Expected: PASS (5 tests).

- [ ] **Step 5: Commit**

```bash
git add plugin/src/Seeder/ContentResolver.php plugin/src/Seeder/MediaMap.php plugin/tests/phpunit/Seeder/ContentResolverTest.php
git commit -m "feat(seeder): resolve entry content from patterns and media keys"
```

---

### Task 6: Desired state and actual state

**Files:**
- Create: `plugin/src/Seeder/DesiredEntry.php`, `DesiredState.php`, `ActualEntry.php`, `StateReader.php`
- Test: `plugin/tests/phpunit/Seeder/StateReaderTest.php`

**Interfaces:**
- Consumes: `Manifest`, `ContentResolver`, `ContentHash`, `Meta`, `LanguageProvider`.
- Produces:
  ```php
  final class Pediment\Seeder\DesiredEntry {
      public readonly string $key, $language, $postType, $title, $slug, $content;
      public readonly ?string $parentKey;
      public readonly bool $frontPage, $postsPage;
      public readonly int $menuOrder;
      public readonly array $terms;
      public readonly string $sourceHash;      // ContentHash::compute( title, content )
      public function id(): string;            // "key|language" — the map key everywhere
  }
  final class Pediment\Seeder\DesiredState {
      public function __construct( LanguageProvider $lang, ContentResolver $resolver );
      /** @return array<string,DesiredEntry> keyed by id() */
      public function build( Manifest $manifest ): array;
  }
  final class Pediment\Seeder\ActualEntry {
      public readonly int $id; public readonly string $key, $language, $postType, $title, $slug, $status;
      public readonly int $parentId, $menuOrder;
      public readonly string $storedHash, $currentHash, $sourceHash;
      public function mapKey(): string;        // "key|language"
  }
  final class Pediment\Seeder\StateReader {
      public function __construct( LanguageProvider $lang );
      /** @return array<string,ActualEntry> keyed by mapKey() */
      public function read(): array;
      /** @return array<string,int[]> mapKey => post IDs, only where more than one exists */
      public function duplicates(): array;
  }
  ```

- [ ] **Step 1: Write the failing test**

```php
<?php
// plugin/tests/phpunit/Seeder/StateReaderTest.php

use Pediment\Language\NullProvider;
use Pediment\Seeder\ContentHash;
use Pediment\Seeder\ContentResolver;
use Pediment\Seeder\DesiredState;
use Pediment\Seeder\Manifest;
use Pediment\Seeder\MediaMap;
use Pediment\Seeder\Meta;
use Pediment\Seeder\StateReader;

class StateReaderTest extends WP_UnitTestCase {

	private function seeded( string $key, array $args = [] ): int {
		$id = self::factory()->post->create(
			array_merge( [ 'post_type' => 'page', 'post_title' => 'T', 'post_content' => 'C' ], $args )
		);
		update_post_meta( $id, Meta::KEY, $key );
		update_post_meta( $id, Meta::HASH, ContentHash::forPost( $id ) );
		update_post_meta( $id, Meta::SOURCE, ContentHash::compute( 'T', 'C' ) );
		return $id;
	}

	public function test_desired_state_crosses_the_manifest_with_languages() {
		$manifest = Manifest::fromArray(
			[ 'pages' => [ 'home' => [ 'title' => 'Home', 'content' => '<p>h</p>', 'front_page' => true ] ] ],
			'/tmp/theme'
		);
		$desired = ( new DesiredState( new NullProvider(), new ContentResolver( new MediaMap( [] ) ) ) )->build( $manifest );

		$this->assertArrayHasKey( 'home|', $desired );
		$entry = $desired['home|'];
		$this->assertSame( 'Home', $entry->title );
		$this->assertSame( '<p>h</p>', $entry->content );
		$this->assertTrue( $entry->frontPage );
		$this->assertSame( ContentHash::compute( 'Home', '<p>h</p>' ), $entry->sourceHash );
	}

	public function test_reads_seeded_entries_by_key_not_slug() {
		$id = $this->seeded( 'home', [ 'post_name' => 'startseite' ] );

		$actual = ( new StateReader( new NullProvider() ) )->read();

		$this->assertArrayHasKey( 'home|', $actual );
		$this->assertSame( $id, $actual['home|']->id );
		$this->assertSame( 'startseite', $actual['home|']->slug );
		$this->assertTrue( ContentHash::matches( $actual['home|']->storedHash, $actual['home|']->currentHash ) );
	}

	public function test_a_client_edit_shows_as_a_hash_mismatch() {
		$id = $this->seeded( 'home' );
		wp_update_post( [ 'ID' => $id, 'post_content' => 'client wrote this' ] );

		$actual = ( new StateReader( new NullProvider() ) )->read()['home|'];

		$this->assertFalse( ContentHash::matches( $actual->storedHash, $actual->currentHash ) );
	}

	public function test_drafts_and_trashed_entries_are_still_found() {
		$this->seeded( 'draft-page', [ 'post_status' => 'draft' ] );
		$this->seeded( 'trashed-page', [ 'post_status' => 'trash' ] );

		$actual = ( new StateReader( new NullProvider() ) )->read();

		$this->assertSame( 'draft', $actual['draft-page|']->status );
		$this->assertSame( 'trash', $actual['trashed-page|']->status );
	}

	public function test_unseeded_posts_are_invisible() {
		self::factory()->post->create( [ 'post_type' => 'page', 'post_title' => 'Client page' ] );

		$this->assertSame( [], ( new StateReader( new NullProvider() ) )->read() );
	}

	public function test_duplicate_keys_are_reported_and_not_silently_picked() {
		$a = $this->seeded( 'home' );
		$b = $this->seeded( 'home' );

		$reader = new StateReader( new NullProvider() );

		$this->assertSame( [ 'home|' => [ $a, $b ] ], $reader->duplicates() );
	}

	public function test_attachments_and_navigation_entities_are_not_entries() {
		$nav = self::factory()->post->create( [ 'post_type' => 'wp_navigation' ] );
		update_post_meta( $nav, Meta::KEY, 'primary' );

		$this->assertSame( [], ( new StateReader( new NullProvider() ) )->read() );
	}
}
```

- [ ] **Step 2: Run it and watch it fail**

Run: `... --filter StateReaderTest`
Expected: FAIL — `Class "Pediment\Seeder\DesiredState" not found`.

- [ ] **Step 3: Implement the two entry value objects**

```php
<?php
declare(strict_types=1);

namespace Pediment\Seeder;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class DesiredEntry {
	/** @param array<string,string[]> $terms */
	public function __construct(
		public readonly string $key,
		public readonly string $language,
		public readonly string $postType,
		public readonly string $title,
		public readonly string $slug,
		public readonly ?string $parentKey,
		public readonly string $content,
		public readonly bool $frontPage,
		public readonly bool $postsPage,
		public readonly int $menuOrder,
		public readonly array $terms,
		public readonly string $sourceHash
	) {}

	public function id(): string {
		return $this->key . '|' . $this->language;
	}
}
```

```php
<?php
declare(strict_types=1);

namespace Pediment\Seeder;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class ActualEntry {
	public function __construct(
		public readonly int $id,
		public readonly string $key,
		public readonly string $language,
		public readonly string $postType,
		public readonly string $title,
		public readonly string $slug,
		public readonly int $parentId,
		public readonly string $status,
		public readonly int $menuOrder,
		public readonly string $storedHash,
		public readonly string $currentHash,
		public readonly string $sourceHash
	) {}

	public function mapKey(): string {
		return $this->key . '|' . $this->language;
	}
}
```

- [ ] **Step 4: Implement DesiredState and StateReader**

```php
<?php
/**
 * Phase 1: the manifest crossed with the configured languages.
 *
 * @package Pediment
 */

declare(strict_types=1);

namespace Pediment\Seeder;

use Pediment\Language\LanguageProvider;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class DesiredState {
	public function __construct(
		private LanguageProvider $lang,
		private ContentResolver $resolver
	) {}

	/** @return array<string,DesiredEntry> */
	public function build( Manifest $manifest ): array {
		$desired = [];

		foreach ( $this->lang->languages() as $language ) {
			foreach ( $manifest->entriesInDependencyOrder() as $spec ) {
				// Step 4 gives per-language patterns (patterns/<slug>.<lang>.php);
				// with the NullProvider there is exactly one language and one source.
				$content = $this->resolver->resolve( $spec );
				$entry   = new DesiredEntry(
					$spec->key,
					$language,
					$spec->postType,
					$spec->title,
					$spec->slug,
					$spec->parent,
					$content,
					$spec->frontPage,
					$spec->postsPage,
					$spec->menuOrder,
					$spec->terms,
					ContentHash::compute( $spec->title, $content )
				);

				$desired[ $entry->id() ] = $entry;
			}
		}

		return $desired;
	}
}
```

```php
<?php
/**
 * Phase 2: what the database actually holds, looked up by seed key and
 * unscoped by language.
 *
 * Slug lookups are what produced `primary-2` menus and duplicate pages
 * (7d7ca30, 45c9ca5). Nothing in the engine may call get_page_by_path().
 *
 * @package Pediment
 */

declare(strict_types=1);

namespace Pediment\Seeder;

use Pediment\Language\LanguageProvider;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class StateReader {
	/** Post types the engine treats as entries; navs and attachments are handled by their own seeders. */
	private const EXCLUDED_TYPES = [ 'wp_navigation', 'attachment', 'wp_template', 'wp_template_part', 'wp_global_styles' ];

	public function __construct( private LanguageProvider $lang ) {}

	/** @return array<string,ActualEntry> */
	public function read(): array {
		$entries = [];
		foreach ( $this->query() as $post ) {
			$entry                       = $this->toEntry( $post );
			$entries[ $entry->mapKey() ] = $entry;
		}
		return $entries;
	}

	/** @return array<string,int[]> */
	public function duplicates(): array {
		$byKey = [];
		foreach ( $this->query() as $post ) {
			$entry                      = $this->toEntry( $post );
			$byKey[ $entry->mapKey() ][] = $entry->id;
		}
		return array_filter( $byKey, static fn( array $ids ): bool => count( $ids ) > 1 );
	}

	/** @return \WP_Post[] */
	private function query(): array {
		$args = $this->lang->unscopedQuery(
			[
				'post_type'      => 'any',
				'post_status'    => 'any',
				'posts_per_page' => -1,
				'orderby'        => 'ID',
				'order'          => 'ASC',
				'no_found_rows'  => true,
				// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- seed identity lookup; runs once per seed.
				'meta_key'       => Meta::KEY,
				'meta_compare'   => 'EXISTS',
			]
		);

		return array_values(
			array_filter(
				get_posts( $args ),
				static fn( \WP_Post $post ): bool => ! in_array( $post->post_type, self::EXCLUDED_TYPES, true )
			)
		);
	}

	private function toEntry( \WP_Post $post ): ActualEntry {
		return new ActualEntry(
			(int) $post->ID,
			(string) get_post_meta( $post->ID, Meta::KEY, true ),
			$this->languageOf( (int) $post->ID ),
			(string) $post->post_type,
			(string) $post->post_title,
			(string) $post->post_name,
			(int) $post->post_parent,
			(string) $post->post_status,
			(int) $post->menu_order,
			(string) get_post_meta( $post->ID, Meta::HASH, true ),
			ContentHash::forPost( (int) $post->ID ),
			(string) get_post_meta( $post->ID, Meta::SOURCE, true )
		);
	}

	private function languageOf( int $postId ): string {
		foreach ( $this->lang->languages() as $language ) {
			if ( $this->lang->translationOf( $postId, $language ) === $postId ) {
				return $language;
			}
		}
		return $this->lang->defaultLanguage();
	}
}
```

- [ ] **Step 5: Run it green**

Run: `... --filter StateReaderTest`
Expected: PASS (7 tests). `post_status => 'any'` excludes trashed posts in `WP_Query`, so the query above must use the explicit list `'post_status' => [ 'publish', 'draft', 'pending', 'private', 'future', 'trash' ]` — change it in the single shared `query()` method so `read()` and `duplicates()` can never see different post sets.

- [ ] **Step 6: Commit**

```bash
git add plugin/src/Seeder plugin/tests/phpunit/Seeder/StateReaderTest.php
git commit -m "feat(seeder): resolve desired and actual state by seed key"
```

---

### Task 7: The Differ — hash arbitration, in one place

**Files:**
- Create: `plugin/src/Seeder/PlanItem.php`, `plugin/src/Seeder/Plan.php`, `plugin/src/Seeder/Differ.php`
- Test: `plugin/tests/phpunit/Seeder/DifferTest.php`

**Interfaces:**
- Consumes: `DesiredEntry`, `ActualEntry`, `ContentHash`.
- Produces:
  ```php
  final class Pediment\Seeder\PlanItem {
      public const CREATE = 'create';       // no post carries this key yet
      public const RESTORE = 'restore';     // exists but trashed
      public const UPDATE = 'update';       // at least one field will be written
      public const PROTECTED = 'protected'; // client-edited: nothing will be written
      public const UNCHANGED = 'unchanged';
      public const ORPHAN = 'orphan';       // carries a seed key the manifest dropped

      public const KIND_ENTRY = 'entry';
      public const KIND_MEDIA = 'media';
      public const KIND_NAV = 'nav';

      public function __construct(
          public readonly string $action,
          public readonly string $kind,
          public readonly string $key,
          public readonly string $language,
          public readonly int $postId,
          public readonly array $changes,        // field => ['from'=>mixed,'to'=>mixed] — WILL be written
          public readonly array $protectedFields,// field => ['from'=>mixed,'to'=>mixed] — deliberately NOT written
          public readonly string $note
      ) {}
      public function mapKey(): string;          // "key|language"
  }
  final class Pediment\Seeder\Plan {
      public function __construct( array $items, array $errors = [] );
      /** @return PlanItem[] */ public function items(): array;
      /** @return string[]   */ public function errors(): array;
      public function hasErrors(): bool;
      public function isEmpty(): bool;                       // nothing to write
      /** @return PlanItem[] */ public function byAction( string $action ): array;
      /** @return PlanItem[] */ public function byKind( string $kind ): array;
      /** @return array<string,int> */ public function counts(): array;
      public static function merge( Plan ...$plans ): Plan;
  }
  final class Pediment\Seeder\Differ {
      /**
       * @param array<string,DesiredEntry> $desired
       * @param array<string,ActualEntry>  $actual
       * @param array<string,int[]>        $duplicates
       */
      public function diff( array $desired, array $actual, array $duplicates ): Plan;
  }
  ```
  Structure fields the Differ compares: `slug`, `parent`, `status`, `menu_order`, `post_type` (mismatch is an error, never a rewrite). Content fields: `title`, `content`.

- [ ] **Step 1: Write the failing test**

```php
<?php
// plugin/tests/phpunit/Seeder/DifferTest.php

use Pediment\Seeder\ActualEntry;
use Pediment\Seeder\ContentHash;
use Pediment\Seeder\DesiredEntry;
use Pediment\Seeder\Differ;
use Pediment\Seeder\PlanItem;

class DifferTest extends WP_UnitTestCase {

	private function desired( array $over = [] ): DesiredEntry {
		$d = array_merge(
			[
				'key' => 'home', 'language' => '', 'postType' => 'page', 'title' => 'Home',
				'slug' => 'home', 'parentKey' => null, 'content' => '<p>new</p>',
				'frontPage' => false, 'postsPage' => false, 'menuOrder' => 0, 'terms' => [],
			],
			$over
		);
		return new DesiredEntry(
			$d['key'], $d['language'], $d['postType'], $d['title'], $d['slug'], $d['parentKey'],
			$d['content'], $d['frontPage'], $d['postsPage'], $d['menuOrder'], $d['terms'],
			ContentHash::compute( $d['title'], $d['content'] )
		);
	}

	/** @param array $over storedHash/currentHash/sourceHash default to a consistent "seeded, untouched" row. */
	private function actual( array $over = [] ): ActualEntry {
		$persisted = ContentHash::compute( 'Home', '<p>old</p>' );
		$d         = array_merge(
			[
				'id' => 7, 'key' => 'home', 'language' => '', 'postType' => 'page', 'title' => 'Home',
				'slug' => 'home', 'parentId' => 0, 'status' => 'publish', 'menuOrder' => 0,
				'storedHash' => $persisted, 'currentHash' => $persisted,
				'sourceHash' => ContentHash::compute( 'Home', '<p>old</p>' ),
			],
			$over
		);
		return new ActualEntry(
			$d['id'], $d['key'], $d['language'], $d['postType'], $d['title'], $d['slug'], $d['parentId'],
			$d['status'], $d['menuOrder'], $d['storedHash'], $d['currentHash'], $d['sourceHash']
		);
	}

	private function item( array $desired, array $actual, array $duplicates = [] ): PlanItem {
		$plan = ( new Differ() )->diff( $desired, $actual, $duplicates );
		return $plan->items()[0];
	}

	public function test_missing_entry_is_created() {
		$item = $this->item( [ 'home|' => $this->desired() ], [] );

		$this->assertSame( PlanItem::CREATE, $item->action );
		$this->assertSame( '<p>new</p>', $item->changes['content']['to'] );
		$this->assertSame( 0, $item->postId );
	}

	public function test_untouched_entry_with_changed_source_is_updated() {
		$item = $this->item( [ 'home|' => $this->desired() ], [ 'home|' => $this->actual() ] );

		$this->assertSame( PlanItem::UPDATE, $item->action );
		$this->assertArrayHasKey( 'content', $item->changes );
		$this->assertSame( 7, $item->postId );
	}

	public function test_untouched_entry_with_unchanged_source_is_left_alone() {
		$desired = $this->desired( [ 'content' => '<p>old</p>' ] );
		$item    = $this->item( [ 'home|' => $desired ], [ 'home|' => $this->actual() ] );

		$this->assertSame( PlanItem::UNCHANGED, $item->action );
		$this->assertSame( [], $item->changes );
	}

	public function test_normalization_alone_never_triggers_a_rewrite() {
		// The persisted row differs from the source (WP normalizes on write);
		// only the SOURCE hash decides whether git changed.
		$actual = $this->actual(
			[
				'storedHash'  => ContentHash::compute( 'Home', '<p>old normalized </p>' ),
				'currentHash' => ContentHash::compute( 'Home', '<p>old normalized </p>' ),
				'sourceHash'  => ContentHash::compute( 'Home', '<p>old</p>' ),
			]
		);
		$item = $this->item( [ 'home|' => $this->desired( [ 'content' => '<p>old</p>' ] ) ], [ 'home|' => $actual ] );

		$this->assertSame( PlanItem::UNCHANGED, $item->action );
	}

	public function test_client_edited_content_is_protected() {
		$actual = $this->actual( [ 'currentHash' => ContentHash::compute( 'Home', '<p>client wrote this</p>' ) ] );
		$item   = $this->item( [ 'home|' => $this->desired() ], [ 'home|' => $actual ] );

		$this->assertSame( PlanItem::PROTECTED, $item->action );
		$this->assertSame( [], $item->changes );
		$this->assertArrayHasKey( 'content', $item->protectedFields );
		$this->assertStringContainsString( 'edited', $item->note );
	}

	public function test_a_missing_stored_hash_counts_as_edited() {
		// Step 6 property: on a pre-existing database the first run touches no content.
		$item = $this->item( [ 'home|' => $this->desired() ], [ 'home|' => $this->actual( [ 'storedHash' => '' ] ) ] );

		$this->assertSame( PlanItem::PROTECTED, $item->action );
	}

	public function test_a_foreign_hash_version_counts_as_edited() {
		$actual = $this->actual( [ 'storedHash' => '2:' . str_repeat( 'a', 64 ) ] );

		$this->assertSame( PlanItem::PROTECTED, $this->item( [ 'home|' => $this->desired() ], [ 'home|' => $actual ] )->action );
	}

	public function test_a_missing_source_hash_self_heals_when_the_row_is_untouched() {
		$item = $this->item( [ 'home|' => $this->desired() ], [ 'home|' => $this->actual( [ 'sourceHash' => '' ] ) ] );

		$this->assertSame( PlanItem::UPDATE, $item->action );
		$this->assertArrayHasKey( 'content', $item->changes );
	}

	public function test_title_is_content_and_travels_with_the_hash() {
		$actual = $this->actual( [ 'currentHash' => ContentHash::compute( 'Home', '<p>client</p>' ) ] );
		$item   = $this->item( [ 'home|' => $this->desired( [ 'title' => 'Welcome', 'content' => '<p>old</p>' ] ) ], [ 'home|' => $actual ] );

		$this->assertSame( PlanItem::PROTECTED, $item->action );
		$this->assertArrayHasKey( 'title', $item->protectedFields );
	}

	public function test_slug_is_structure_and_is_reverted_even_when_content_is_protected() {
		$actual = $this->actual(
			[ 'slug' => 'kontakt', 'currentHash' => ContentHash::compute( 'Home', '<p>client</p>' ) ]
		);
		$item = $this->item( [ 'home|' => $this->desired() ], [ 'home|' => $actual ] );

		$this->assertSame( PlanItem::UPDATE, $item->action, 'a structure change still counts as an update' );
		$this->assertSame( [ 'from' => 'kontakt', 'to' => 'home' ], $item->changes['slug'] );
		$this->assertArrayHasKey( 'content', $item->protectedFields );
	}

	public function test_parent_and_menu_order_are_structure() {
		$desired = [
			'guide|'     => $this->desired( [ 'key' => 'guide', 'slug' => 'guide', 'content' => '<p>old</p>' ] ),
			'guide/faq|' => $this->desired( [ 'key' => 'guide/faq', 'slug' => 'faq', 'parentKey' => 'guide', 'content' => '<p>old</p>', 'menuOrder' => 3 ] ),
		];
		$actual  = [
			'guide|'     => $this->actual( [ 'id' => 10, 'key' => 'guide', 'slug' => 'guide' ] ),
			'guide/faq|' => $this->actual( [ 'id' => 11, 'key' => 'guide/faq', 'slug' => 'faq', 'parentId' => 0, 'menuOrder' => 0 ] ),
		];
		$plan  = ( new Differ() )->diff( $desired, $actual, [] );
		$items = [];
		foreach ( $plan->items() as $planned ) {
			$items[ $planned->key ] = $planned;
		}

		$this->assertSame( 'guide', $items['guide/faq']->changes['parent_key']['to'] );
		$this->assertSame( 3, $items['guide/faq']->changes['menu_order']['to'] );
		$this->assertSame( PlanItem::UNCHANGED, $items['guide']->action );
	}

	public function test_trashed_entries_are_restored() {
		$item = $this->item( [ 'home|' => $this->desired( [ 'content' => '<p>old</p>' ] ) ], [ 'home|' => $this->actual( [ 'status' => 'trash' ] ) ] );

		$this->assertSame( PlanItem::RESTORE, $item->action );
		$this->assertSame( [ 'from' => 'trash', 'to' => 'publish' ], $item->changes['status'] );
	}

	public function test_draft_and_pending_are_editorial_states_the_seeder_leaves_alone() {
		foreach ( [ 'draft', 'pending' ] as $status ) {
			$item = $this->item(
				[ 'home|' => $this->desired( [ 'content' => '<p>old</p>' ] ) ],
				[ 'home|' => $this->actual( [ 'status' => $status ] ) ]
			);

			$this->assertSame( PlanItem::UNCHANGED, $item->action, $status );
			$this->assertArrayNotHasKey( 'status', $item->changes, $status );
		}
	}

	public function test_a_changed_title_propagates_when_the_row_is_untouched() {
		$item = $this->item(
			[ 'home|' => $this->desired( [ 'title' => 'Welcome' ] ) ],
			[ 'home|' => $this->actual() ]
		);

		$this->assertSame( PlanItem::UPDATE, $item->action );
		$this->assertSame( [ 'from' => 'Home', 'to' => 'Welcome' ], $item->changes['title'] );
	}

	public function test_a_never_seeded_row_is_not_reported_as_client_edited() {
		$item = $this->item( [ 'home|' => $this->desired() ], [ 'home|' => $this->actual( [ 'storedHash' => '' ] ) ] );

		$this->assertStringContainsString( 'never seeded', $item->note );
		$this->assertStringNotContainsString( 'edited in the editor', $item->note );
	}

	public function test_a_foreign_hash_version_says_so_rather_than_blaming_the_client() {
		$actual = $this->actual( [ 'storedHash' => '2:' . str_repeat( 'a', 64 ) ] );

		$this->assertStringContainsString( 'older hash version', $this->item( [ 'home|' => $this->desired() ], [ 'home|' => $actual ] )->note );
	}

	public function test_plan_helpers_slice_and_merge() {
		$create = new PlanItem( PlanItem::CREATE, PlanItem::KIND_ENTRY, 'home', '', 0, [ 'slug' => [ 'from' => null, 'to' => 'home' ] ] );
		$media  = new PlanItem( PlanItem::UNCHANGED, PlanItem::KIND_MEDIA, 'logo', '', 4 );
		$plan   = Plan::merge( new Plan( [ $create ] ), new Plan( [ $media ], [ 'boom' ] ) );

		$this->assertSame( [ $create ], $plan->byAction( PlanItem::CREATE ) );
		$this->assertSame( [ $media ], $plan->byKind( PlanItem::KIND_MEDIA ) );
		$this->assertSame( 'home|', $create->mapKey() );
		$this->assertTrue( $create->writes() );
		$this->assertFalse( $media->writes() );
		$this->assertSame( [ 'boom' ], $plan->errors() );
		$this->assertFalse( $plan->isEmpty(), 'an errored plan is blocked, not idle' );
		$this->assertSame( 0, $plan->counts()[ PlanItem::PROTECTED ], 'counts() reports every action, including empty ones' );
	}

	public function test_orphans_are_reported_and_never_deleted() {
		$plan = ( new Differ() )->diff( [], [ 'legacy|' => $this->actual( [ 'key' => 'legacy', 'id' => 42 ] ) ], [] );

		$item = $plan->items()[0];
		$this->assertSame( PlanItem::ORPHAN, $item->action );
		$this->assertSame( 42, $item->postId );
		$this->assertSame( [], $item->changes );
		$this->assertFalse( $plan->hasErrors(), 'an orphan is a report, not an error' );
	}

	public function test_duplicate_keys_abort_the_plan() {
		$plan = ( new Differ() )->diff( [ 'home|' => $this->desired() ], [ 'home|' => $this->actual() ], [ 'home|' => [ 7, 9 ] ] );

		$this->assertTrue( $plan->hasErrors() );
		$this->assertStringContainsString( '7', $plan->errors()[0] );
		$this->assertStringContainsString( '9', $plan->errors()[0] );
	}

	public function test_a_post_type_mismatch_is_an_error_not_a_rewrite() {
		$plan = ( new Differ() )->diff(
			[ 'home|' => $this->desired() ],
			[ 'home|' => $this->actual( [ 'postType' => 'post' ] ) ],
			[]
		);

		$this->assertTrue( $plan->hasErrors() );
		$this->assertStringContainsString( 'post_type', $plan->errors()[0] );
	}

	public function test_counts_summarize_the_plan() {
		$plan = ( new Differ() )->diff(
			[ 'home|' => $this->desired(), 'guide|' => $this->desired( [ 'key' => 'guide', 'content' => '<p>old</p>' ] ) ],
			[ 'guide|' => $this->actual( [ 'key' => 'guide', 'id' => 8 ] ) ],
			[]
		);

		$this->assertSame( 1, $plan->counts()[ PlanItem::CREATE ] );
		$this->assertSame( 1, $plan->counts()[ PlanItem::UNCHANGED ] );
		$this->assertFalse( $plan->isEmpty() );
	}
}
```

- [ ] **Step 2: Run it and watch it fail**

Run: `... --filter DifferTest`
Expected: FAIL — `Class "Pediment\Seeder\Differ" not found`.

- [ ] **Step 3: Implement PlanItem and Plan**

```php
<?php
declare(strict_types=1);

namespace Pediment\Seeder;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class PlanItem {
	public const CREATE    = 'create';
	public const RESTORE   = 'restore';
	public const UPDATE    = 'update';
	public const PROTECTED = 'protected';
	public const UNCHANGED = 'unchanged';
	public const ORPHAN    = 'orphan';

	public const KIND_ENTRY = 'entry';
	public const KIND_MEDIA = 'media';
	public const KIND_NAV   = 'nav';

	/**
	 * @param array<string,array{from:mixed,to:mixed}> $changes
	 * @param array<string,array{from:mixed,to:mixed}> $protectedFields
	 */
	public function __construct(
		public readonly string $action,
		public readonly string $kind,
		public readonly string $key,
		public readonly string $language,
		public readonly int $postId,
		public readonly array $changes = [],
		public readonly array $protectedFields = [],
		public readonly string $note = ''
	) {}

	public function mapKey(): string {
		return $this->key . '|' . $this->language;
	}

	public function writes(): bool {
		return [] !== $this->changes;
	}
}
```

```php
<?php
declare(strict_types=1);

namespace Pediment\Seeder;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Plan {
	/**
	 * @param PlanItem[] $items
	 * @param string[]   $errors Fatal problems; nothing is applied while any exist.
	 */
	public function __construct( private array $items = [], private array $errors = [] ) {}

	/** @return PlanItem[] */
	public function items(): array {
		return $this->items;
	}

	/** @return string[] */
	public function errors(): array {
		return $this->errors;
	}

	public function hasErrors(): bool {
		return [] !== $this->errors;
	}

	public function isEmpty(): bool {
		// An errored plan is blocked, not idle — never let a caller report
		// "nothing to do" for a plan that could not be computed.
		if ( $this->hasErrors() ) {
			return false;
		}
		foreach ( $this->items as $item ) {
			if ( $item->writes() ) {
				return false;
			}
		}
		return true;
	}

	/** @return PlanItem[] */
	public function byAction( string $action ): array {
		return array_values( array_filter( $this->items, static fn( PlanItem $i ): bool => $i->action === $action ) );
	}

	/** @return PlanItem[] */
	public function byKind( string $kind ): array {
		return array_values( array_filter( $this->items, static fn( PlanItem $i ): bool => $i->kind === $kind ) );
	}

	/** @return array<string,int> Every action, including the ones with no items. */
	public function counts(): array {
		$counts = array_fill_keys(
			[ PlanItem::CREATE, PlanItem::RESTORE, PlanItem::UPDATE, PlanItem::PROTECTED, PlanItem::UNCHANGED, PlanItem::ORPHAN ],
			0
		);
		foreach ( $this->items as $item ) {
			$counts[ $item->action ] = ( $counts[ $item->action ] ?? 0 ) + 1;
		}
		return $counts;
	}

	public static function merge( Plan ...$plans ): Plan {
		$items  = [];
		$errors = [];
		foreach ( $plans as $plan ) {
			$items  = array_merge( $items, $plan->items() );
			$errors = array_merge( $errors, $plan->errors() );
		}
		return new self( $items, $errors );
	}
}
```

- [ ] **Step 4: Implement the Differ**

```php
<?php
/**
 * Phase 3: desired state x actual state -> a plan.
 *
 * The whole arbitration rule lives here and nowhere else:
 *
 *   1. no actual row                                  -> create
 *   2. stored hash absent / foreign / != current hash -> the client edited it:
 *      never touch title or content, enforce structure only
 *   3. otherwise                                      -> write content when the
 *      source hash shows git changed, else leave it
 *
 * @package Pediment
 */

declare(strict_types=1);

namespace Pediment\Seeder;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Differ {
	/**
	 * @param array<string,DesiredEntry> $desired
	 * @param array<string,ActualEntry>  $actual
	 * @param array<string,int[]>        $duplicates
	 */
	public function diff( array $desired, array $actual, array $duplicates = [] ): Plan {
		$items  = [];
		$errors = [];

		foreach ( $duplicates as $mapKey => $ids ) {
			[ $duplicateKey, $duplicateLanguage ] = array_pad( explode( '|', (string) $mapKey, 2 ), 2, '' );
			$errors[] = sprintf(
				'Seed key "%s"%s is carried by %d posts (IDs %s). Identity must be unique — delete or re-key the extras before seeding.',
				$duplicateKey,
				'' === $duplicateLanguage ? '' : sprintf( ' (language "%s")', $duplicateLanguage ),
				count( $ids ),
				implode( ', ', $ids )
			);
		}

		foreach ( $desired as $mapKey => $want ) {
			$have = $actual[ $mapKey ] ?? null;

			if ( null === $have ) {
				$items[] = new PlanItem(
					PlanItem::CREATE,
					PlanItem::KIND_ENTRY,
					$want->key,
					$want->language,
					0,
					[
						'title'      => [ 'from' => null, 'to' => $want->title ],
						'content'    => [ 'from' => null, 'to' => $want->content ],
						'slug'       => [ 'from' => null, 'to' => $want->slug ],
						'parent_key' => [ 'from' => null, 'to' => $want->parentKey ],
						'status'     => [ 'from' => null, 'to' => 'publish' ],
						'menu_order' => [ 'from' => null, 'to' => $want->menuOrder ],
					]
				);
				continue;
			}

			if ( $have->postType !== $want->postType ) {
				$errors[] = sprintf(
					'Seed key "%s" is a %s in the database but a %s in the manifest (post ID %d). post_type is never rewritten — re-key one of them.',
					$mapKey,
					$have->postType,
					$want->postType,
					$have->id
				);
				continue;
			}

			$edited    = ! ContentHash::matches( $have->storedHash, $have->currentHash );
			$changes   = [];
			$protected = [];

			if ( $edited ) {
				if ( $have->title !== $want->title ) {
					$protected['title'] = [ 'from' => $have->title, 'to' => $want->title ];
				}
				if ( $want->sourceHash !== $have->sourceHash ) {
					$protected['content'] = [ 'from' => '(database)', 'to' => '(manifest)' ];
				}
			} elseif ( '' === $have->sourceHash || $have->sourceHash !== $want->sourceHash ) {
				// Untouched by the client and git moved (or provenance is
				// unknown but nobody edited it, so writing is safe).
				if ( $have->title !== $want->title ) {
					$changes['title'] = [ 'from' => $have->title, 'to' => $want->title ];
				}
				$changes['content'] = [ 'from' => '(database)', 'to' => $want->content ];
			}

			if ( $have->slug !== $want->slug ) {
				$changes['slug'] = [ 'from' => $have->slug, 'to' => $want->slug ];
			}
			if ( $have->menuOrder !== $want->menuOrder ) {
				$changes['menu_order'] = [ 'from' => $have->menuOrder, 'to' => $want->menuOrder ];
			}

			$wantParentId = null;
			if ( null !== $want->parentKey ) {
				$parentActual = $actual[ $want->parentKey . '|' . $want->language ] ?? null;
				$wantParentId = $parentActual instanceof ActualEntry ? $parentActual->id : null;
			}
			$parentDiffers = ( null === $want->parentKey && 0 !== $have->parentId )
				|| ( null !== $want->parentKey && ( null === $wantParentId || $have->parentId !== $wantParentId ) );
			if ( $parentDiffers ) {
				// Named `parent_key`, not `parent`, because the values are a seed
				// key and a post ID — descriptive, not directly writable. The
				// applier resolves the real post_parent from the desired entry.
				$changes['parent_key'] = [ 'from' => $have->parentId, 'to' => $want->parentKey ];
			}

			// Only `trash` is restored. `draft` and `pending` are editorial states
			// — a client taking a page offline or holding a revision for review
			// must not be overruled by the next seed run.
			$restoring = 'trash' === $have->status;
			if ( $restoring ) {
				$changes['status'] = [ 'from' => $have->status, 'to' => 'publish' ];
			}

			$action = PlanItem::UNCHANGED;
			$note   = '';
			if ( $restoring ) {
				$action = PlanItem::RESTORE;
			} elseif ( [] !== $changes ) {
				$action = PlanItem::UPDATE;
			} elseif ( [] !== $protected ) {
				$action = PlanItem::PROTECTED;
			}
			if ( [] !== $protected ) {
				// Three different situations reach rule 2, and telling an operator
				// "the client edited this" about a database the seeder has simply
				// never stamped is the difference between running `wp pediment
				// adopt` and concluding the whole site was hand-edited.
				if ( '' === $have->storedHash ) {
					$note = 'never seeded by this engine — content left alone; run `wp pediment adopt` to take it into git';
				} elseif ( ! str_starts_with( $have->storedHash, ContentHash::VERSION . ':' ) ) {
					$note = 'seeded by an older hash version — content left alone; re-adopt to refresh it';
				} else {
					$note = 'edited in the editor — content and title left alone';
				}
			}

			$items[] = new PlanItem(
				$action,
				PlanItem::KIND_ENTRY,
				$want->key,
				$want->language,
				$have->id,
				$changes,
				$protected,
				$note
			);
		}

		foreach ( $actual as $mapKey => $have ) {
			if ( isset( $desired[ $mapKey ] ) ) {
				continue;
			}
			$items[] = new PlanItem(
				PlanItem::ORPHAN,
				PlanItem::KIND_ENTRY,
				$have->key,
				$have->language,
				$have->id,
				[],
				[],
				sprintf( '"%s" (ID %d) carries a seed key the manifest no longer declares — left in place', $have->title, $have->id )
			);
		}

		return new Plan( $items, $errors );
	}
}
```

- [ ] **Step 5: Run it green**

Run: `... --filter DifferTest`
Expected: PASS (16 tests).

- [ ] **Step 6: Commit**

```bash
git add plugin/src/Seeder/Plan.php plugin/src/Seeder/PlanItem.php plugin/src/Seeder/Differ.php plugin/tests/phpunit/Seeder/DifferTest.php
git commit -m "feat(seeder): diff desired against actual state into a plan"
```

---

### Task 8: The Applier — writes, hashes, and the traps around them

**Files:**
- Create: `plugin/src/Seeder/Applier.php`, `plugin/src/Seeder/ApplyResult.php`
- Test: `plugin/tests/phpunit/Seeder/ApplierTest.php`

**Interfaces:**
- Consumes: `Plan`, `PlanItem`, `DesiredEntry`, `ContentHash`, `Meta`, `LanguageProvider`.
- Produces:
  ```php
  final class Pediment\Seeder\ApplyResult {
      /** @param array<string,int> $ids mapKey => post ID @param string[] $errors */
      public function __construct( public readonly array $ids, public readonly array $errors ) {}
  }
  final class Pediment\Seeder\Applier {
      public function __construct( LanguageProvider $lang );
      /** @param array<string,DesiredEntry> $desired */
      public function apply( Plan $plan, array $desired ): ApplyResult;
  }
  ```
  Guarantees, each tied to a past failure: content arrays are `wp_slash()`ed before writing (block-JSON corruption, `WORDPRESS_TRAPS.md`); KSES filters are suspended around writes; the language is assigned in the same write as creation (8593c73); `_pediment_seed_hash` is written from the **persisted row** after the write and `_pediment_seed_source` from the input; front-page/posts-page options are set from resolved IDs.

- [ ] **Step 1: Write the failing test**

```php
<?php
// plugin/tests/phpunit/Seeder/ApplierTest.php

use Pediment\Language\NullProvider;
use Pediment\Seeder\Applier;
use Pediment\Seeder\ContentHash;
use Pediment\Seeder\ContentResolver;
use Pediment\Seeder\DesiredState;
use Pediment\Seeder\Differ;
use Pediment\Seeder\Manifest;
use Pediment\Seeder\MediaMap;
use Pediment\Seeder\Meta;
use Pediment\Seeder\StateReader;

class ApplierTest extends WP_UnitTestCase {

	/** Phases 1-3 only: the plan the Runner would compute, with nothing written. */
	private function plan( array $raw ): \Pediment\Seeder\Plan {
		$manifest = Manifest::fromArray( $raw, '/tmp/theme' );
		$lang     = new NullProvider();
		$desired  = ( new DesiredState( $lang, new ContentResolver( new MediaMap( [] ) ) ) )->build( $manifest );
		$reader   = new StateReader( $lang );

		return ( new Differ() )->diff( $desired, $reader->read(), $reader->duplicates() );
	}

	/** Runs the four phases the way the Runner will, and returns the resolved IDs. */
	private function seed( array $raw, bool $expectClean = true ): array {
		$manifest = Manifest::fromArray( $raw, '/tmp/theme' );
		$lang     = new NullProvider();
		$desired  = ( new DesiredState( $lang, new ContentResolver( new MediaMap( [] ) ) ) )->build( $manifest );
		$reader   = new StateReader( $lang );
		$plan     = ( new Differ() )->diff( $desired, $reader->read(), $reader->duplicates() );
		$result   = ( new Applier( $lang ) )->apply( $plan, $desired );

		if ( $expectClean ) {
			$this->assertSame( [], $result->errors );
		}
		$this->lastErrors = $result->errors;

		return $result->ids;
	}

	/** @var string[] */
	private array $lastErrors = [];

	private function manifest( array $pages ): array {
		return [ 'pages' => $pages ];
	}

	public function test_creates_pages_with_key_slug_and_both_hashes() {
		$ids = $this->seed( $this->manifest( [ 'home' => [ 'title' => 'Home', 'content' => '<p>hi</p>' ] ] ) );
		$id  = $ids['home|'];

		$post = get_post( $id );
		$this->assertSame( 'page', $post->post_type );
		$this->assertSame( 'home', $post->post_name );
		$this->assertSame( 'publish', $post->post_status );
		$this->assertSame( 'home', get_post_meta( $id, Meta::KEY, true ) );
		$this->assertSame( ContentHash::forPost( $id ), get_post_meta( $id, Meta::HASH, true ) );
		$this->assertSame( ContentHash::compute( 'Home', '<p>hi</p>' ), get_post_meta( $id, Meta::SOURCE, true ) );
	}

	public function test_block_attribute_json_survives_the_write() {
		// wp_update_post un-slashes post_content; unslashed block JSON fatals the
		// front end (docs/WORDPRESS_TRAPS.md). The applier must wp_slash().
		$markup = '<!-- wp:pediment/hero {"headline":"<span class=\"accent\">Hi</span>"} /-->';
		$ids    = $this->seed( $this->manifest( [ 'home' => [ 'title' => 'Home', 'content' => $markup ] ] ) );

		$stored = get_post( $ids['home|'] )->post_content;
		$blocks = parse_blocks( $stored );

		$this->assertSame( 'pediment/hero', $blocks[0]['blockName'] );
		$this->assertIsArray( $blocks[0]['attrs'], 'attrs must not parse to null' );
		$this->assertSame( '<span class="accent">Hi</span>', $blocks[0]['attrs']['headline'] );
	}

	public function test_reseeding_unchanged_content_plans_nothing() {
		// Asserting on post_modified_gmt would prove nothing: it has one-second
		// granularity and both runs land inside the same second. Assert on the
		// plan, which is what actually decides whether a write happens.
		$m = $this->manifest( [ 'home' => [ 'title' => 'Home', 'content' => '<p>hi</p>' ] ] );
		$this->seed( $m );

		$plan = $this->plan( $m );

		$this->assertTrue( $plan->isEmpty(), 'a re-seed with no manifest change must plan no writes' );
		$this->assertSame( PlanItem::UNCHANGED, $plan->byKind( PlanItem::KIND_ENTRY )[0]->action );
	}

	public function test_changed_manifest_content_is_written_and_rehashed() {
		$ids = $this->seed( $this->manifest( [ 'home' => [ 'title' => 'Home', 'content' => '<p>one</p>' ] ] ) );
		$id  = $ids['home|'];

		$this->seed( $this->manifest( [ 'home' => [ 'title' => 'Home', 'content' => '<p>two</p>' ] ] ) );

		$this->assertStringContainsString( 'two', get_post( $id )->post_content );
		$this->assertSame( ContentHash::forPost( $id ), get_post_meta( $id, Meta::HASH, true ) );
		$this->assertSame( ContentHash::compute( 'Home', '<p>two</p>' ), get_post_meta( $id, Meta::SOURCE, true ) );
	}

	public function test_a_client_edit_is_never_overwritten() {
		$ids = $this->seed( $this->manifest( [ 'home' => [ 'title' => 'Home', 'content' => '<p>one</p>' ] ] ) );
		$id  = $ids['home|'];
		wp_update_post( [ 'ID' => $id, 'post_content' => '<p>client copy</p>', 'post_title' => 'Client title' ] );

		$this->seed( $this->manifest( [ 'home' => [ 'title' => 'Home', 'content' => '<p>two</p>' ] ] ) );

		$this->assertSame( '<p>client copy</p>', get_post( $id )->post_content );
		$this->assertSame( 'Client title', get_post( $id )->post_title );
	}

	public function test_a_structure_write_on_an_edited_page_never_restamps_the_hash() {
		// The failure this guards: a client edits the body AND renames the slug.
		// The Differ makes that an UPDATE carrying only the slug change, with the
		// content protected. Re-stamping the hash there would adopt the client's
		// prose as seeded, and the NEXT run would overwrite their page.
		$ids = $this->seed( $this->manifest( [ 'home' => [ 'title' => 'Home', 'content' => '<p>one</p>' ] ] ) );
		$id  = $ids['home|'];
		wp_update_post( [ 'ID' => $id, 'post_content' => '<p>client copy</p>', 'post_name' => 'startseite' ] );
		$hashBefore = get_post_meta( $id, Meta::HASH, true );

		$this->seed( $this->manifest( [ 'home' => [ 'title' => 'Home', 'content' => '<p>two</p>' ] ] ) );

		$this->assertSame( 'home', get_post( $id )->post_name, 'slug is structure and is reverted' );
		$this->assertSame( '<p>client copy</p>', get_post( $id )->post_content );
		$this->assertSame( $hashBefore, get_post_meta( $id, Meta::HASH, true ), 'a structure-only write must not adopt the client edit' );

		// And the page is still protected on the run after that.
		$this->seed( $this->manifest( [ 'home' => [ 'title' => 'Home', 'content' => '<p>three</p>' ] ] ) );
		$this->assertSame( '<p>client copy</p>', get_post( $id )->post_content );
	}

	public function test_a_slug_collision_is_reported_instead_of_churning_forever() {
		// A pre-existing unseeded page holds /contact, so WordPress uniquifies the
		// seeded one to contact-2. Unreported, the Differ would rewrite the row on
		// every run and never converge.
		self::factory()->post->create( [ 'post_type' => 'page', 'post_name' => 'contact', 'post_title' => 'Client contact' ] );

		$this->seed( $this->manifest( [ 'contact' => [ 'title' => 'Contact', 'content' => '' ] ] ), false );

		$this->assertNotEmpty( $this->lastErrors );
		$this->assertStringContainsString( 'contact-2', $this->lastErrors[0] );
	}

	public function test_client_added_terms_survive_a_structure_only_write() {
		$ids = $this->seed(
			[ 'posts' => [ 'sample' => [ 'title' => 'Sample', 'content' => '', 'terms' => [ 'category' => [ 'insights' ] ] ] ] ]
		);
		$id    = $ids['sample|'];
		$extra = self::factory()->category->create( [ 'slug' => 'client-pick' ] );
		wp_set_object_terms( $id, [ $extra ], 'category', true );

		wp_update_post( [ 'ID' => $id, 'post_name' => 'renamed-by-client' ] );
		$this->seed( [ 'posts' => [ 'sample' => [ 'title' => 'Sample', 'content' => '', 'terms' => [ 'category' => [ 'insights' ] ] ] ] ] );

		$this->assertContains( 'client-pick', wp_get_post_terms( $id, 'category', [ 'fields' => 'slugs' ] ) );
	}

	public function test_an_unregistered_taxonomy_is_reported_not_swallowed() {
		$this->seed(
			[ 'posts' => [ 'sample' => [ 'title' => 'Sample', 'content' => '', 'terms' => [ 'categories' => [ 'oops' ] ] ] ] ],
			false
		);

		$this->assertNotEmpty( $this->lastErrors );
		$this->assertStringContainsString( 'categories', $this->lastErrors[0] );
	}

	public function test_a_client_slug_change_is_reverted() {
		$ids = $this->seed( $this->manifest( [ 'contact' => [ 'title' => 'Contact', 'content' => '' ] ] ) );
		$id  = $ids['contact|'];
		wp_update_post( [ 'ID' => $id, 'post_name' => 'kontakt' ] );

		$this->seed( $this->manifest( [ 'contact' => [ 'title' => 'Contact', 'content' => '' ] ] ) );

		$this->assertSame( 'contact', get_post( $id )->post_name );
	}

	public function test_nesting_menu_order_and_reading_options_are_applied() {
		$ids = $this->seed(
			$this->manifest(
				[
					'home'      => [ 'title' => 'Home', 'content' => '', 'front_page' => true ],
					'blog'      => [ 'title' => 'Blog', 'content' => '', 'posts_page' => true ],
					'guide'     => [ 'title' => 'Guide', 'content' => '' ],
					'guide/faq' => [ 'title' => 'FAQ', 'content' => '', 'parent' => 'guide', 'menu_order' => 3 ],
				]
			)
		);

		$this->assertSame( $ids['guide|'], get_post( $ids['guide/faq|'] )->post_parent );
		$this->assertSame( 3, get_post( $ids['guide/faq|'] )->menu_order );
		$this->assertSame( 'page', get_option( 'show_on_front' ) );
		$this->assertSame( $ids['home|'], (int) get_option( 'page_on_front' ) );
		$this->assertSame( $ids['blog|'], (int) get_option( 'page_for_posts' ) );
	}

	public function test_terms_are_created_and_assigned() {
		$ids = $this->seed(
			[
				'posts' => [
					'sample-one' => [ 'title' => 'Sample', 'content' => '', 'terms' => [ 'category' => [ 'insights' ] ] ],
				],
			]
		);

		$terms = wp_get_post_terms( $ids['sample-one|'], 'category', [ 'fields' => 'slugs' ] );
		$this->assertContains( 'insights', $terms );
	}

	public function test_a_trashed_entry_is_restored_in_place() {
		$ids = $this->seed( $this->manifest( [ 'home' => [ 'title' => 'Home', 'content' => '' ] ] ) );
		$id  = $ids['home|'];
		wp_trash_post( $id );

		$again = $this->seed( $this->manifest( [ 'home' => [ 'title' => 'Home', 'content' => '' ] ] ) );

		$this->assertSame( $id, $again['home|'], 'restore, never re-create' );
		$this->assertSame( 'publish', get_post( $id )->post_status );
		$this->assertSame( 'home', get_post( $id )->post_name, 'wp_trash_post appends __trashed; the restore must undo it' );
		$this->assertSame( '', get_post_meta( $id, '_wp_trash_meta_status', true ), 'trash bookkeeping must not survive the restore' );
	}

	public function test_errors_in_the_plan_block_every_write() {
		$manifest = Manifest::fromArray( $this->manifest( [ 'home' => [ 'title' => 'Home', 'content' => '' ] ] ), '/tmp/theme' );
		$lang     = new NullProvider();
		$desired  = ( new DesiredState( $lang, new ContentResolver( new MediaMap( [] ) ) ) )->build( $manifest );
		$plan     = new \Pediment\Seeder\Plan( [], [ 'duplicate identity' ] );

		$result = ( new Applier( $lang ) )->apply( $plan, $desired );

		$this->assertSame( [ 'duplicate identity' ], $result->errors );
		$this->assertSame( [], get_posts( [ 'post_type' => 'page', 'fields' => 'ids' ] ) );
	}
}
```

- [ ] **Step 2: Run it and watch it fail**

Run: `... --filter ApplierTest`
Expected: FAIL — `Class "Pediment\Seeder\Applier" not found`.

- [ ] **Step 3: Implement**

```php
<?php
declare(strict_types=1);

namespace Pediment\Seeder;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class ApplyResult {
	/**
	 * @param array<string,int> $ids    mapKey => post ID for every desired entry that exists after the run.
	 * @param string[]          $errors
	 */
	public function __construct(
		public readonly array $ids = [],
		public readonly array $errors = []
	) {}
}
```

```php
<?php
/**
 * Phase 4: apply the plan.
 *
 * Rules that are not obvious and cost a lot when missed:
 *   - wp_slash() before every write: wp_insert_post/wp_update_post un-slash
 *     post_content, which corrupts block-attribute JSON (WORDPRESS_TRAPS.md).
 *   - KSES is suspended: under WP-CLI there is no user, so kses_init_filters()
 *     is active and mangles block comments. Seeded content is git-authored, not
 *     user input.
 *   - The language is set in the same write as creation, never after (8593c73).
 *   - _pediment_seed_hash comes from the PERSISTED row, _pediment_seed_source
 *     from the input.
 *
 * @package Pediment
 */

declare(strict_types=1);

namespace Pediment\Seeder;

use Pediment\Language\LanguageProvider;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Applier {
	public function __construct( private LanguageProvider $lang ) {}

	/** @param array<string,DesiredEntry> $desired */
	public function apply( Plan $plan, array $desired ): ApplyResult {
		if ( $plan->hasErrors() ) {
			return new ApplyResult( [], $plan->errors() );
		}

		$ids    = [];
		$errors = [];

		// Seed existing IDs first so parents resolve even when only a child changes.
		foreach ( $plan->byKind( PlanItem::KIND_ENTRY ) as $item ) {
			if ( $item->postId > 0 && PlanItem::ORPHAN !== $item->action ) {
				$ids[ $item->mapKey() ] = $item->postId;
			}
		}

		$kses = $this->suspendKses();

		try {
			foreach ( $plan->byKind( PlanItem::KIND_ENTRY ) as $item ) {
				if ( in_array( $item->action, [ PlanItem::ORPHAN, PlanItem::UNCHANGED, PlanItem::PROTECTED ], true ) ) {
					continue;
				}
				$entry = $desired[ $item->mapKey() ] ?? null;
				if ( ! $entry instanceof DesiredEntry ) {
					continue;
				}

				$isCreate = PlanItem::CREATE === $item->action;
				$postId   = $isCreate
					? $this->create( $entry, $ids, $errors )
					: $this->update( $item, $entry, $ids, $errors );

				if ( $postId > 0 ) {
					$ids[ $item->mapKey() ] = $postId;
					// Terms are create-only. Re-applying them on a structure-only
					// write would use wp_set_object_terms(), which REPLACES the
					// taxonomy's assignments — a client who filed a seeded post
					// under an extra category would lose it on an unrelated slug
					// revert. The Differ does not diff terms, so a manifest-side
					// term change is not enforced either; that is documented, not
					// accidental (docs/seeding.md).
					if ( $isCreate ) {
						$this->applyTerms( $postId, $entry, $errors );
					}
					$this->assertSlug( $postId, $entry, $errors );
				}
			}
		} finally {
			$this->restoreKses( $kses );
		}

		$this->applyReadingOptions( $desired, $ids );

		return new ApplyResult( $ids, $errors );
	}

	/** @param array<string,int> $ids @param string[] $errors */
	private function create( DesiredEntry $entry, array $ids, array &$errors ): int {
		$postId = wp_insert_post(
			wp_slash(
				[
					'post_type'    => $entry->postType,
					'post_status'  => 'publish',
					'post_title'   => $entry->title,
					'post_name'    => $entry->slug,
					'post_content' => $entry->content,
					'post_parent'  => $this->parentId( $entry, $ids ),
					'menu_order'   => $entry->menuOrder,
				]
			),
			true
		);

		if ( is_wp_error( $postId ) ) {
			$errors[] = sprintf( '%s: %s', $entry->key, $postId->get_error_message() );
			return 0;
		}

		$postId = (int) $postId;

		// Same write path as creation — never a second pass (8593c73).
		$this->lang->setLanguage( $postId, $entry->language );

		update_post_meta( $postId, Meta::KEY, $entry->key );
		$this->recordHashes( $postId, $entry );

		return $postId;
	}

	/** @param array<string,int> $ids @param string[] $errors */
	private function update( PlanItem $item, DesiredEntry $entry, array $ids, array &$errors ): int {
		$postarr = [ 'ID' => $item->postId ];

		foreach ( $item->changes as $field => $change ) {
			switch ( $field ) {
				case 'title':
					$postarr['post_title'] = $entry->title;
					break;
				case 'content':
					$postarr['post_content'] = $entry->content;
					break;
				case 'slug':
					$postarr['post_name'] = $entry->slug;
					break;
				case 'parent_key':
					// The change record carries a seed key for display; the real
					// post_parent is resolved here from the desired entry.
					$postarr['post_parent'] = $this->parentId( $entry, $ids );
					break;
				case 'menu_order':
					$postarr['menu_order'] = $entry->menuOrder;
					break;
				case 'status':
					$postarr['post_status'] = 'publish';
					break;
			}
		}

		// Restoring by writing post_status directly skips the untrash hooks, so
		// wp_trash_post()'s bookkeeping would stay behind forever.
		if ( isset( $postarr['post_status'] ) && 'trash' === $item->changes['status']['from'] ) {
			foreach ( [ '_wp_trash_meta_status', '_wp_trash_meta_time', '_wp_desired_post_slug' ] as $trashMeta ) {
				delete_post_meta( $item->postId, $trashMeta );
			}
		}

		if ( 1 === count( $postarr ) ) {
			return $item->postId;
		}

		$result = wp_update_post( wp_slash( $postarr ), true );
		if ( is_wp_error( $result ) ) {
			$errors[] = sprintf( '%s: %s', $entry->key, $result->get_error_message() );
			return $item->postId;
		}

		// Only re-hash when this run actually wrote content; a structure-only
		// update on a client-edited page must leave the arbitration hash alone.
		if ( isset( $item->changes['content'] ) || isset( $item->changes['title'] ) ) {
			$this->recordHashes( $item->postId, $entry );
		}

		return $item->postId;
	}

	private function recordHashes( int $postId, DesiredEntry $entry ): void {
		update_post_meta( $postId, Meta::HASH, ContentHash::forPost( $postId ) );
		update_post_meta( $postId, Meta::SOURCE, $entry->sourceHash );
	}

	/** @param array<string,int> $ids */
	private function parentId( DesiredEntry $entry, array $ids ): int {
		if ( null === $entry->parentKey ) {
			return 0;
		}
		return (int) ( $ids[ $entry->parentKey . '|' . $entry->language ] ?? 0 );
	}

	/** @param string[] $errors */
	private function applyTerms( int $postId, DesiredEntry $entry, array &$errors ): void {
		foreach ( $entry->terms as $taxonomy => $slugs ) {
			// Silence here would be indistinguishable from success: a typo'd
			// taxonomy, or one whose post type has not registered yet, would
			// produce a clean run with no terms assigned.
			if ( ! taxonomy_exists( $taxonomy ) ) {
				$errors[] = sprintf( '%s: taxonomy "%s" is not registered — no terms were assigned.', $entry->key, $taxonomy );
				continue;
			}
			$termIds = [];
			foreach ( $slugs as $slug ) {
				$term = get_term_by( 'slug', $slug, $taxonomy );
				if ( ! $term ) {
					$created = wp_insert_term( ucfirst( str_replace( '-', ' ', $slug ) ), $taxonomy, [ 'slug' => $slug ] );
					if ( is_wp_error( $created ) ) {
						$errors[] = sprintf( '%s: could not create term "%s" in %s — %s', $entry->key, $slug, $taxonomy, $created->get_error_message() );
						continue;
					}
					$termIds[] = (int) $created['term_id'];
					continue;
				}
				$termIds[] = (int) $term->term_id;
			}
			$assigned = wp_set_object_terms( $postId, $termIds, $taxonomy );
			if ( is_wp_error( $assigned ) ) {
				$errors[] = sprintf( '%s: could not assign %s terms — %s', $entry->key, $taxonomy, $assigned->get_error_message() );
			}
		}
	}

	/**
	 * WordPress uniquifies a colliding slug (`contact` -> `contact-2`) and reports
	 * success. Left unreported, the Differ then sees a slug difference on EVERY
	 * later run, rewrites the row, and never converges — silently, forever.
	 *
	 * @param string[] $errors
	 */
	private function assertSlug( int $postId, DesiredEntry $entry, array &$errors ): void {
		$stored = (string) get_post_field( 'post_name', $postId );
		if ( '' === $stored || $stored === $entry->slug ) {
			return;
		}
		$errors[] = sprintf(
			'%s: WordPress stored the slug "%s" instead of "%s" — another post already occupies it. Free that slug or declare a different one in the manifest.',
			$entry->key,
			$stored,
			$entry->slug
		);
	}

	/**
	 * @param array<string,DesiredEntry> $desired
	 * @param array<string,int>          $ids
	 */
	private function applyReadingOptions( array $desired, array $ids ): void {
		$default = $this->lang->defaultLanguage();

		foreach ( $desired as $mapKey => $entry ) {
			if ( $entry->language !== $default || ! isset( $ids[ $mapKey ] ) ) {
				continue;
			}
			if ( $entry->frontPage ) {
				if ( 'page' !== get_option( 'show_on_front' ) ) {
					update_option( 'show_on_front', 'page' );
				}
				if ( (int) get_option( 'page_on_front' ) !== $ids[ $mapKey ] ) {
					update_option( 'page_on_front', $ids[ $mapKey ] );
				}
			}
			if ( $entry->postsPage && (int) get_option( 'page_for_posts' ) !== $ids[ $mapKey ] ) {
				update_option( 'page_for_posts', $ids[ $mapKey ] );
			}
		}
	}

	private function suspendKses(): bool {
		$active = false !== has_filter( 'content_save_pre', 'wp_filter_post_kses' );
		if ( $active ) {
			kses_remove_filters();
		}
		return $active;
	}

	private function restoreKses( bool $wasActive ): void {
		if ( $wasActive ) {
			kses_init_filters();
		}
	}
}
```

- [ ] **Step 4: Run it green**

Run: `... --filter ApplierTest`
Expected: PASS (10 tests).

- [ ] **Step 5: Commit**

```bash
git add plugin/src/Seeder/Applier.php plugin/src/Seeder/ApplyResult.php plugin/tests/phpunit/Seeder/ApplierTest.php
git commit -m "feat(seeder): apply the plan with slash-safe writes and hash recording"
```

---

### Task 9: Media presence and the site logo

**Files:**
- Create: `plugin/src/Seeder/MediaSeeder.php`
- Test: `plugin/tests/phpunit/Seeder/MediaSeederTest.php`

**Interfaces:**
- Consumes: `Manifest`, `MediaSpec`, `MediaMap`, `Meta`, `Plan`, `PlanItem`.
- Produces:
  ```php
  final class Pediment\Seeder\MediaSeeder {
      public function plan( Manifest $manifest ): Plan;            // KIND_MEDIA items: create | restore | unchanged
      public function apply( Plan $plan, Manifest $manifest ): MediaMap;
      public function map( Manifest $manifest ): MediaMap;         // existing attachments only; used for dry runs
      /** @return string[] Failures from the most recent apply(); the Runner folds these into its result. */
      public function errors(): array;
  }
  ```
  Attachments carry `_pediment_seed_key` exactly like entries. Files are copied into `wp_upload_dir()` and inserted with an explicit MIME (SVG never passes `wp_check_filetype`, so the map is explicit). Raster files get `wp_generate_attachment_metadata()`; SVG and PDF skip it. `site.logo` sets the `custom_logo` theme mod — the logo is an enforced default, not client content.

  Three properties this seeder needs for the same reasons the entry path does: every `sideload()` failure is reported rather than returning a bare 0 (a silent partial seed is the failure mode the engine exists to prevent); a trashed attachment is restored instead of re-uploaded (otherwise two attachments end up carrying one seed key); and two attachments sharing a key is a plan error, exactly as it is for entries.

- [ ] **Step 1: Write the failing test**

```php
<?php
// plugin/tests/phpunit/Seeder/MediaSeederTest.php

use Pediment\Seeder\Manifest;
use Pediment\Seeder\MediaSeeder;
use Pediment\Seeder\Meta;
use Pediment\Seeder\PlanItem;

class MediaSeederTest extends WP_UnitTestCase {

	private string $dir;

	public function set_up(): void {
		parent::set_up();
		$this->dir = get_temp_dir() . 'pediment-media-test';
		wp_mkdir_p( $this->dir . '/seed/media' );
		file_put_contents( $this->dir . '/seed/media/logo.svg', '<svg xmlns="http://www.w3.org/2000/svg" width="10" height="10"/>' );
		copy( DIR_TESTDATA . '/images/canola.jpg', $this->dir . '/seed/media/hero.jpg' );
	}

	private function manifest(): Manifest {
		return Manifest::fromArray(
			[
				'media' => [
					'logo' => [ 'file' => 'seed/media/logo.svg', 'title' => 'Logo' ],
					'hero' => [ 'file' => 'seed/media/hero.jpg', 'title' => 'Hero' ],
				],
				'site'  => [ 'logo' => 'logo' ],
			],
			$this->dir
		);
	}

	public function test_plan_lists_every_missing_file_as_a_create() {
		$plan = ( new MediaSeeder() )->plan( $this->manifest() );

		$this->assertCount( 2, $plan->byAction( PlanItem::CREATE ) );
		$this->assertSame( PlanItem::KIND_MEDIA, $plan->items()[0]->kind );
	}

	public function test_apply_sideloads_and_keys_the_attachments() {
		$seeder = new MediaSeeder();
		$m      = $this->manifest();

		$map = $seeder->apply( $seeder->plan( $m ), $m );

		$this->assertGreaterThan( 0, $map->id( 'logo' ) );
		$this->assertSame( 'image/svg+xml', get_post_mime_type( $map->id( 'logo' ) ) );
		$this->assertSame( 'logo', get_post_meta( $map->id( 'logo' ), Meta::KEY, true ) );
		$this->assertNotEmpty( wp_get_attachment_metadata( $map->id( 'hero' ) ), 'raster media needs sizes' );
		$this->assertStringContainsString( 'hero', $map->url( 'hero' ) );
	}

	public function test_apply_sets_the_site_logo() {
		$seeder = new MediaSeeder();
		$m      = $this->manifest();

		$map = $seeder->apply( $seeder->plan( $m ), $m );

		$this->assertSame( $map->id( 'logo' ), (int) get_theme_mod( 'custom_logo' ) );
	}

	public function test_reapplying_is_idempotent() {
		$seeder = new MediaSeeder();
		$m      = $this->manifest();
		$first  = $seeder->apply( $seeder->plan( $m ), $m );

		$plan   = $seeder->plan( $m );
		$second = $seeder->apply( $plan, $m );

		$this->assertSame( $first->id( 'logo' ), $second->id( 'logo' ) );
		$this->assertCount( 2, $plan->byAction( PlanItem::UNCHANGED ) );
		$this->assertSame( [], $plan->byAction( PlanItem::CREATE ) );
	}

	public function test_map_sees_only_what_is_already_seeded() {
		$this->assertSame( 0, ( new MediaSeeder() )->map( $this->manifest() )->id( 'logo' ) );
	}

	public function test_a_trashed_attachment_is_restored_rather_than_re_uploaded() {
		$seeder = new MediaSeeder();
		$m      = $this->manifest();
		$first  = $seeder->apply( $seeder->plan( $m ), $m );
		wp_trash_post( $first->id( 'logo' ) );

		$plan   = $seeder->plan( $m );
		$second = $seeder->apply( $plan, $m );

		$this->assertSame( PlanItem::RESTORE, $plan->byAction( PlanItem::RESTORE )[0]->action );
		$this->assertSame( $first->id( 'logo' ), $second->id( 'logo' ), 'restore, never re-upload' );
		// get_post_status() resolves `inherit` on an unattached attachment to
		// `publish`, so assert the raw column instead.
		$this->assertSame( 'inherit', get_post_field( 'post_status', $second->id( 'logo' ) ) );
		$this->assertCount(
			1,
			get_posts( [ 'post_type' => 'attachment', 'post_status' => 'any', 'fields' => 'ids', 'meta_key' => \Pediment\Seeder\Meta::KEY, 'meta_value' => 'logo' ] ),
			'one seed key, one attachment'
		);
	}

	public function test_two_attachments_under_one_key_are_reported() {
		$seeder = new MediaSeeder();
		$m      = $this->manifest();
		$seeder->apply( $seeder->plan( $m ), $m );
		$impostor = self::factory()->attachment->create_object( [ 'file' => 'copy.svg', 'post_mime_type' => 'image/svg+xml' ] );
		update_post_meta( $impostor, \Pediment\Seeder\Meta::KEY, 'logo' );

		$plan = $seeder->plan( $m );

		$this->assertTrue( $plan->hasErrors() );
		$this->assertStringContainsString( 'logo', $plan->errors()[0] );
	}

	public function test_a_sideload_failure_is_reported_rather_than_silently_skipped() {
		$seeder = new MediaSeeder();
		$m      = $this->manifest();
		// Make wp_insert_attachment fail without touching the filesystem.
		add_filter( 'wp_insert_post_empty_content', '__return_true' );

		$map = $seeder->apply( $seeder->plan( $m ), $m );

		remove_filter( 'wp_insert_post_empty_content', '__return_true' );
		$this->assertSame( 0, $map->id( 'logo' ) );
		$this->assertNotEmpty( $seeder->errors() );
		$this->assertStringContainsString( 'logo', implode( "\n", $seeder->errors() ) );
	}

	public function test_an_errored_plan_writes_nothing() {
		$seeder = new MediaSeeder();
		$m      = $this->manifest();

		$map = $seeder->apply( new \Pediment\Seeder\Plan( [], [ 'boom' ] ), $m );

		$this->assertSame( 0, $map->id( 'logo' ) );
		$this->assertSame( [ 'boom' ], $seeder->errors() );
		$this->assertSame( [], get_posts( [ 'post_type' => 'attachment', 'post_status' => 'any', 'fields' => 'ids' ] ) );
	}
}
```

- [ ] **Step 2: Run it and watch it fail**

Run: `... --filter MediaSeederTest`
Expected: FAIL — `Class "Pediment\Seeder\MediaSeeder" not found`.

- [ ] **Step 3: Implement**

```php
<?php
/**
 * Media presence: every media key in the manifest resolves to exactly one
 * attachment, identified by _pediment_seed_key like everything else.
 *
 * @package Pediment
 */

declare(strict_types=1);

namespace Pediment\Seeder;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class MediaSeeder {
	/** @var string[] Failures from the most recent apply(). */
	private array $errors = [];

	private const MIME = [
		'jpg'  => 'image/jpeg',
		'jpeg' => 'image/jpeg',
		'png'  => 'image/png',
		'gif'  => 'image/gif',
		'webp' => 'image/webp',
		'avif' => 'image/avif',
		'svg'  => 'image/svg+xml',
		'pdf'  => 'application/pdf',
	];

	public function plan( Manifest $manifest ): Plan {
		$items    = [];
		$errors   = [];
		$existing = $this->existing();

		foreach ( $this->duplicates() as $key => $duplicateIds ) {
			$errors[] = sprintf(
				'media.%s is carried by %d attachments (IDs %s). Identity must be unique — delete or re-key the extras.',
				$key,
				count( $duplicateIds ),
				implode( ', ', $duplicateIds )
			);
		}

		foreach ( $manifest->media() as $key => $spec ) {
			$extension = strtolower( (string) pathinfo( $spec->file, PATHINFO_EXTENSION ) );
			if ( ! isset( self::MIME[ $extension ] ) ) {
				$errors[] = sprintf( 'media.%s: unsupported file type ".%s".', $key, $extension );
				continue;
			}

			if ( ! isset( $existing[ $key ] ) ) {
				$items[] = new PlanItem(
					PlanItem::CREATE,
					PlanItem::KIND_MEDIA,
					$key,
					'',
					0,
					[ 'file' => [ 'from' => null, 'to' => basename( $spec->file ) ] ]
				);
				continue;
			}

			// A trashed attachment still holds the key. Re-uploading would put two
			// attachments under one identity; restore the one that already exists.
			$items[] = 'trash' === get_post_status( $existing[ $key ] )
				? new PlanItem(
					PlanItem::RESTORE,
					PlanItem::KIND_MEDIA,
					$key,
					'',
					$existing[ $key ],
					[ 'status' => [ 'from' => 'trash', 'to' => 'inherit' ] ]
				)
				: new PlanItem( PlanItem::UNCHANGED, PlanItem::KIND_MEDIA, $key, '', $existing[ $key ] );
		}

		return new Plan( $items, $errors );
	}

	public function map( Manifest $manifest ): MediaMap {
		return new MediaMap( array_intersect_key( $this->existing(), $manifest->media() ) );
	}

	/** @return string[] */
	public function errors(): array {
		return $this->errors;
	}

	public function apply( Plan $plan, Manifest $manifest ): MediaMap {
		$this->errors = [];

		// Same contract as Applier::apply(): an errored plan writes nothing.
		if ( $plan->hasErrors() ) {
			$this->errors = $plan->errors();
			return $this->map( $manifest );
		}

		$ids = array_intersect_key( $this->existing(), $manifest->media() );

		foreach ( $plan->byKind( PlanItem::KIND_MEDIA ) as $item ) {
			$spec = $manifest->media()[ $item->key ] ?? null;
			if ( ! $spec instanceof MediaSpec ) {
				continue;
			}

			if ( PlanItem::RESTORE === $item->action ) {
				$restored = wp_update_post( [ 'ID' => $item->postId, 'post_status' => 'inherit' ], true );
				if ( is_wp_error( $restored ) ) {
					$this->errors[] = sprintf( 'media.%s: could not restore attachment %d — %s', $item->key, $item->postId, $restored->get_error_message() );
					continue;
				}
				$ids[ $item->key ] = $item->postId;
				continue;
			}

			if ( PlanItem::CREATE !== $item->action ) {
				continue;
			}
			$id = $this->sideload( $spec );
			if ( $id > 0 ) {
				$ids[ $item->key ] = $id;
			}
		}

		$map = new MediaMap( $ids );

		$logoKey = $manifest->siteLogo();
		if ( '' !== $logoKey && $map->id( $logoKey ) > 0 && (int) get_theme_mod( 'custom_logo' ) !== $map->id( $logoKey ) ) {
			set_theme_mod( 'custom_logo', $map->id( $logoKey ) );
		}

		return $map;
	}

	private function sideload( MediaSpec $spec ): int {
		require_once ABSPATH . 'wp-admin/includes/image.php';

		$uploads = wp_upload_dir();
		if ( ! empty( $uploads['error'] ) ) {
			$this->errors[] = sprintf( 'media.%s: the uploads directory is not writable — %s', $spec->key, $uploads['error'] );
			return 0;
		}

		$extension = strtolower( (string) pathinfo( $spec->file, PATHINFO_EXTENSION ) );
		$filename  = wp_unique_filename( $uploads['path'], basename( $spec->file ) );
		$target    = trailingslashit( $uploads['path'] ) . $filename;

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_copy -- seeding from a theme-shipped file, not user input.
		if ( ! copy( $spec->file, $target ) ) {
			$this->errors[] = sprintf( 'media.%s: could not copy %s into the uploads directory.', $spec->key, $spec->file );
			return 0;
		}

		$attachmentId = wp_insert_attachment(
			[
				'post_mime_type' => self::MIME[ $extension ],
				'post_title'     => $spec->title,
				'post_status'    => 'inherit',
			],
			$target,
			0,
			true
		);

		if ( is_wp_error( $attachmentId ) ) {
			$this->errors[] = sprintf( 'media.%s: could not insert the attachment — %s', $spec->key, $attachmentId->get_error_message() );
			return 0;
		}

		$attachmentId = (int) $attachmentId;
		update_post_meta( $attachmentId, Meta::KEY, $spec->key );

		if ( 'svg' !== $extension && 'pdf' !== $extension ) {
			wp_update_attachment_metadata( $attachmentId, wp_generate_attachment_metadata( $attachmentId, $target ) );
		}

		return $attachmentId;
	}

	/** @return array<string,int> First attachment per key, trashed ones included. */
	private function existing(): array {
		$ids = [];
		foreach ( $this->keyed() as $key => $attachmentIds ) {
			$ids[ $key ] = $attachmentIds[0];
		}
		return $ids;
	}

	/** @return array<string,int[]> Keys carried by more than one attachment. */
	private function duplicates(): array {
		return array_filter( $this->keyed(), static fn( array $ids ): bool => count( $ids ) > 1 );
	}

	/** @return array<string,int[]> */
	private function keyed(): array {
		$keyed = [];
		foreach (
			get_posts(
				[
					'post_type'      => 'attachment',
					// Trashed attachments still hold their seed key: ignoring them
					// would re-upload and leave two attachments under one identity.
					'post_status'    => [ 'inherit', 'trash' ],
					'posts_per_page' => -1,
					'orderby'        => 'ID',
					'order'          => 'ASC',
					'no_found_rows'  => true,
					// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- seed identity lookup.
					'meta_key'       => Meta::KEY,
					'meta_compare'   => 'EXISTS',
				]
			) as $attachment
		) {
			$key = (string) get_post_meta( $attachment->ID, Meta::KEY, true );
			if ( '' !== $key ) {
				$keyed[ $key ][] = (int) $attachment->ID;
			}
		}
		return $keyed;
	}
}
```

- [ ] **Step 4: Run it green**

Run: `... --filter MediaSeederTest`
Expected: PASS (5 tests). `DIR_TESTDATA` is provided by the WP test suite; if `canola.jpg` is absent in this WP version, use any file under `DIR_TESTDATA . '/images/'`.

- [ ] **Step 5: Commit**

```bash
git add plugin/src/Seeder/MediaSeeder.php plugin/tests/phpunit/Seeder/MediaSeederTest.php
git commit -m "feat(seeder): seed media attachments and the site logo by key"
```

---

### Task 10: Navigation membership by seed key

**Files:**
- Create: `plugin/src/Seeder/NavSeeder.php`, `plugin/src/Seeder/Kses.php`
- Modify: `plugin/src/Seeder/Applier.php` (its private `suspendKses()`/`restoreKses()` move into the shared `Kses` helper)
- Test: `plugin/tests/phpunit/Seeder/NavSeederTest.php`

**Interfaces:**
- Consumes: `Manifest`, `NavSpec`, `Plan`, `PlanItem`, `Meta`, `LanguageProvider`.
- Produces:
  ```php
  final class Pediment\Seeder\Kses {
      public static function suspend(): bool;      // removes the filters if active; returns whether it did
      public static function restore( bool $wasActive ): void;
  }
  final class Pediment\Seeder\NavSeeder {
      public function __construct( LanguageProvider $lang );
      /** @param array<string,int> $entryIds mapKey => post ID */
      public function plan( Manifest $manifest, array $entryIds ): Plan;   // KIND_NAV items
      public function apply( Plan $plan, Manifest $manifest, array $entryIds ): array; // navKey|lang => post ID
      /** @param array<string,int> $entryIds */
      public function serialize( NavSpec $spec, string $language, array $entryIds ): string;
      /** @return string[] Failures from the most recent apply(). */
      public function errors(): array;
  }
  ```
  A `wp_navigation` entity per `(nav key, language)`, identified by `_pediment_seed_key`, published. Membership is structure the seeder owns outright (see "Design decisions", item 3): when the serialized items differ, the entity is rewritten and the plan says so.

  Two traps this path shares with the entry path, both proven by a failing test here:
  - **KSES rewrites the stored markup.** With no `unfiltered_html` user — every WP-CLI run, and PHPUnit — `wp_filter_post_kses()` strips the `\/` escapes out of the navigation-link JSON on save. Comparing a freshly serialized string against the stored one then differs forever, so every nav is rewritten on every run. Writes go through the shared `Kses` helper, and `serialize()` emits `JSON_UNESCAPED_SLASHES` so the markup matches what the block editor itself writes.
  - **Silent failures.** A failed insert/update, an item whose entry never resolved, and two nav entities sharing one seed key are all reported, exactly as the entry and media paths report theirs.

- [ ] **Step 1: Write the failing test**

```php
<?php
// plugin/tests/phpunit/Seeder/NavSeederTest.php

use Pediment\Language\NullProvider;
use Pediment\Seeder\Manifest;
use Pediment\Seeder\Meta;
use Pediment\Seeder\NavSeeder;
use Pediment\Seeder\PlanItem;

class NavSeederTest extends WP_UnitTestCase {

	private function manifest( array $items ): Manifest {
		return Manifest::fromArray(
			[
				'pages' => [ 'home' => [ 'title' => 'Home', 'content' => '' ], 'about' => [ 'title' => 'About', 'content' => '' ] ],
				'navs'  => [ 'primary' => [ 'title' => 'Primary', 'items' => $items ] ],
			],
			'/tmp/theme'
		);
	}

	public function test_serializes_entry_links_by_resolved_id() {
		$seeder = new NavSeeder( new NullProvider() );
		$m      = $this->manifest( [ [ 'entry' => 'home' ], [ 'url' => '/contact', 'label' => 'Contact' ] ] );

		$markup = $seeder->serialize( $m->navs()['primary'], '', [ 'home|' => 12 ] );

		$this->assertStringContainsString( '"id":12', $markup );
		$this->assertStringContainsString( '"kind":"post-type"', $markup );
		$this->assertStringContainsString( '"label":"Contact"', $markup );
		$this->assertStringContainsString( 'wp:navigation-link', $markup );
	}

	public function test_creates_one_entity_per_nav_key() {
		$seeder = new NavSeeder( new NullProvider() );
		$m      = $this->manifest( [ [ 'entry' => 'home' ] ] );
		$ids    = [ 'home|' => self::factory()->post->create( [ 'post_type' => 'page' ] ) ];

		$navIds = $seeder->apply( $seeder->plan( $m, $ids ), $m, $ids );

		$nav = get_post( $navIds['primary|'] );
		$this->assertSame( 'wp_navigation', $nav->post_type );
		$this->assertSame( 'publish', $nav->post_status );
		$this->assertSame( 'primary', get_post_meta( $nav->ID, Meta::KEY, true ) );
	}

	public function test_membership_changes_are_planned_and_applied_in_place() {
		$seeder = new NavSeeder( new NullProvider() );
		$ids    = [
			'home|'  => self::factory()->post->create( [ 'post_type' => 'page' ] ),
			'about|' => self::factory()->post->create( [ 'post_type' => 'page' ] ),
		];
		$first  = $this->manifest( [ [ 'entry' => 'home' ] ] );
		$navIds = $seeder->apply( $seeder->plan( $first, $ids ), $first, $ids );

		$second = $this->manifest( [ [ 'entry' => 'home' ], [ 'entry' => 'about' ] ] );
		$plan   = $seeder->plan( $second, $ids );
		$again  = $seeder->apply( $plan, $second, $ids );

		$this->assertSame( PlanItem::UPDATE, $plan->items()[0]->action );
		$this->assertSame( 2, $plan->items()[0]->changes['items']['to'] );
		$this->assertSame( $navIds['primary|'], $again['primary|'], 'update in place, never re-create' );
		$this->assertSame( 2, substr_count( get_post( $again['primary|'] )->post_content, 'wp:navigation-link' ) );
	}

	public function test_a_nav_with_slashes_in_its_urls_is_not_rewritten_forever() {
		// The trap: KSES strips the `\/` escapes out of the stored JSON, so a
		// freshly serialized string never matches what is in the database and the
		// entity is rewritten on every single run.
		$seeder = new NavSeeder( new NullProvider() );
		$m      = $this->manifest( [ [ 'url' => '/contact/us', 'label' => 'Contact' ] ] );
		$seeder->apply( $seeder->plan( $m, [] ), $m, [] );

		$plan = $seeder->plan( $m, [] );

		$this->assertSame( PlanItem::UNCHANGED, $plan->items()[0]->action );
	}

	public function test_an_item_whose_entry_is_not_seeded_is_reported() {
		$seeder = new NavSeeder( new NullProvider() );
		$m      = $this->manifest( [ [ 'entry' => 'home' ] ] );

		$seeder->apply( $seeder->plan( $m, [] ), $m, [] );

		$this->assertNotEmpty( $seeder->errors() );
		$this->assertStringContainsString( 'home', $seeder->errors()[0] );
	}

	public function test_an_unresolved_link_is_still_reported_once_the_nav_stops_changing() {
		// After the first run the nav's content matches, so the item is UNCHANGED
		// — but the missing link is still missing, and silence would read as success.
		$seeder = new NavSeeder( new NullProvider() );
		$m      = $this->manifest( [ [ 'entry' => 'home' ] ] );
		$seeder->apply( $seeder->plan( $m, [] ), $m, [] );

		$plan = $seeder->plan( $m, [] );
		$seeder->apply( $plan, $m, [] );

		$this->assertSame( PlanItem::UNCHANGED, $plan->items()[0]->action );
		$this->assertNotEmpty( $seeder->errors(), 'the problem persists, so the report must too' );
		$this->assertStringContainsString( 'home', $seeder->errors()[0] );
	}

	public function test_serialize_has_no_side_effects() {
		$seeder = new NavSeeder( new NullProvider() );
		$m      = $this->manifest( [ [ 'entry' => 'home' ] ] );

		$seeder->serialize( $m->navs()['primary'], '', [] );
		$seeder->serialize( $m->navs()['primary'], '', [] );

		$this->assertSame( [], $seeder->errors(), 'serialize() is a formatter, not a reporter' );
	}

	public function test_two_entities_under_one_key_are_reported() {
		$seeder = new NavSeeder( new NullProvider() );
		$m      = $this->manifest( [ [ 'entry' => 'home' ] ] );
		$ids    = [ 'home|' => self::factory()->post->create( [ 'post_type' => 'page' ] ) ];
		$seeder->apply( $seeder->plan( $m, $ids ), $m, $ids );
		$impostor = self::factory()->post->create( [ 'post_type' => 'wp_navigation' ] );
		update_post_meta( $impostor, \Pediment\Seeder\Meta::KEY, 'primary' );

		$plan = $seeder->plan( $m, $ids );

		$this->assertTrue( $plan->hasErrors() );
		$this->assertStringContainsString( 'primary', $plan->errors()[0] );
	}

	public function test_an_unchanged_nav_is_not_rewritten() {
		$seeder = new NavSeeder( new NullProvider() );
		$m      = $this->manifest( [ [ 'entry' => 'home' ] ] );
		$ids    = [ 'home|' => self::factory()->post->create( [ 'post_type' => 'page' ] ) ];
		$navIds = $seeder->apply( $seeder->plan( $m, $ids ), $m, $ids );
		$before = get_post( $navIds['primary|'] )->post_modified_gmt;

		$plan = $seeder->plan( $m, $ids );
		$seeder->apply( $plan, $m, $ids );

		$this->assertSame( PlanItem::UNCHANGED, $plan->items()[0]->action );
		$this->assertSame( $before, get_post( $navIds['primary|'] )->post_modified_gmt );
	}
}
```

- [ ] **Step 2: Run it and watch it fail**

Run: `... --filter NavSeederTest`
Expected: FAIL — `Class "Pediment\Seeder\NavSeeder" not found`.

- [ ] **Step 3: Implement**

```php
<?php
/**
 * Navigation entities, resolved by (seed key, language) like everything else —
 * not by title or slug. A stray post holding the `primary` slug is what turned
 * replacements into `primary-2` (7d7ca30).
 *
 * @package Pediment
 */

declare(strict_types=1);

namespace Pediment\Seeder;

use Pediment\Language\LanguageProvider;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class NavSeeder {
	/** @var string[] Failures from the most recent apply(). */
	private array $errors = [];

	public function __construct( private LanguageProvider $lang ) {}

	/** @param array<string,int> $entryIds */
	public function plan( Manifest $manifest, array $entryIds ): Plan {
		$items    = [];
		$errors   = [];
		$existing = $this->existing();

		foreach ( $this->duplicates() as $mapKey => $duplicateIds ) {
			$errors[] = sprintf(
				'navs.%s is carried by %d navigation entities (IDs %s). Identity must be unique — delete or re-key the extras.',
				explode( '|', (string) $mapKey )[0],
				count( $duplicateIds ),
				implode( ', ', $duplicateIds )
			);
		}

		foreach ( $this->lang->languages() as $language ) {
			foreach ( $manifest->navs() as $key => $spec ) {
				$mapKey  = $key . '|' . $language;
				$desired = $this->serialize( $spec, $language, $entryIds );
				$postId  = (int) ( $existing[ $mapKey ] ?? 0 );

				if ( 0 === $postId ) {
					$items[] = new PlanItem(
						PlanItem::CREATE,
						PlanItem::KIND_NAV,
						$key,
						$language,
						0,
						[ 'items' => [ 'from' => 0, 'to' => count( $spec->items ) ] ]
					);
					continue;
				}

				$current = (string) get_post( $postId )->post_content;
				$items[] = $current === $desired
					? new PlanItem( PlanItem::UNCHANGED, PlanItem::KIND_NAV, $key, $language, $postId )
					: new PlanItem(
						PlanItem::UPDATE,
						PlanItem::KIND_NAV,
						$key,
						$language,
						$postId,
						[ 'items' => [ 'from' => substr_count( $current, 'wp:navigation-link' ), 'to' => count( $spec->items ) ] ],
						[],
						'membership is git-owned; editor changes to this menu are reverted'
					);
			}
		}

		return new Plan( $items, $errors );
	}

	/** @return string[] */
	public function errors(): array {
		return $this->errors;
	}

	/**
	 * @param array<string,int> $entryIds
	 * @return array<string,int> navKey|language => post ID
	 */
	public function apply( Plan $plan, Manifest $manifest, array $entryIds ): array {
		$this->errors = [];

		// Same contract as Applier::apply(): an errored plan writes nothing.
		if ( $plan->hasErrors() ) {
			$this->errors = $plan->errors();
			return $this->existing();
		}

		$ids = $this->existing();

		// Without this, wp_filter_post_kses() strips the escapes out of the
		// navigation-link JSON on save, the stored markup never matches a fresh
		// serialize(), and every nav is rewritten on every run.
		$kses = Kses::suspend();

		try {
			foreach ( $plan->byKind( PlanItem::KIND_NAV ) as $item ) {
				$spec = $manifest->navs()[ $item->key ] ?? null;
				if ( ! $spec instanceof NavSpec ) {
					continue;
				}

				// Checked for every item, including UNCHANGED ones: a menu that
				// quietly comes out short is worse than one that fails, and the
				// problem persists across runs even though the nav stops changing.
				foreach ( $this->unresolvedEntries( $spec, $item->language, $entryIds ) as $missing ) {
					$this->errors[] = sprintf( 'navs.%s: "%s" has no seeded post yet — the link was left out.', $spec->key, $missing );
				}

				if ( PlanItem::UNCHANGED === $item->action ) {
					continue;
				}

				$content = $this->serialize( $spec, $item->language, $entryIds );

				if ( PlanItem::CREATE === $item->action ) {
					$postId = wp_insert_post(
						wp_slash(
							[
								'post_type'    => 'wp_navigation',
								'post_status'  => 'publish',
								'post_title'   => $spec->title,
								'post_name'    => sanitize_title( $spec->key . ( '' !== $item->language ? '-' . $item->language : '' ) ),
								'post_content' => $content,
							]
						),
						true
					);
					if ( is_wp_error( $postId ) ) {
						$this->errors[] = sprintf( 'navs.%s: could not create the navigation entity — %s', $spec->key, $postId->get_error_message() );
						continue;
					}
					$postId = (int) $postId;
					$this->lang->setLanguage( $postId, $item->language );
					update_post_meta( $postId, Meta::KEY, $spec->key );
				} else {
					$postId  = $item->postId;
					$updated = wp_update_post( wp_slash( [ 'ID' => $postId, 'post_content' => $content ] ), true );
					if ( is_wp_error( $updated ) ) {
						$this->errors[] = sprintf( 'navs.%s: could not update the navigation entity — %s', $spec->key, $updated->get_error_message() );
						continue;
					}
				}

				$ids[ $item->mapKey() ] = $postId;
			}
		} finally {
			Kses::restore( $kses );
		}

		return $ids;
	}

	/** @param array<string,int> $entryIds */
	public function serialize( NavSpec $spec, string $language, array $entryIds ): string {
		$links = [];

		foreach ( $spec->items as $item ) {
			if ( isset( $item['entry'] ) ) {
				$postId = (int) ( $entryIds[ $item['entry'] . '|' . $language ] ?? 0 );
				if ( 0 === $postId ) {
					// Reported by apply() via unresolvedEntries(), not from here:
					// serialize() must stay pure, and an unresolved link has to be
					// reported on EVERY run, not only the one that rewrites the nav.
					continue;
				}
				$post    = get_post( $postId );
				// JSON_UNESCAPED_SLASHES matches what the block editor writes, and
				// keeps the markup stable under KSES, which strips `\/` on save.
				$links[] = '<!-- wp:navigation-link ' . wp_json_encode(
					[
						'label' => (string) ( $item['label'] ?? ( $post ? $post->post_title : '' ) ),
						'type'  => $post ? $post->post_type : 'page',
						'id'    => $postId,
						'kind'  => 'post-type',
						'url'   => (string) get_permalink( $postId ),
					],
					JSON_UNESCAPED_SLASHES
				) . ' /-->';
				continue;
			}

			$links[] = '<!-- wp:navigation-link ' . wp_json_encode(
				[
					'label' => (string) $item['label'],
					'url'   => (string) $item['url'],
					'kind'  => 'custom',
				],
				JSON_UNESCAPED_SLASHES
			) . ' /-->';
		}

		return implode( "\n", $links );
	}

	/**
	 * Entry keys this nav references that have no seeded post yet.
	 *
	 * @param array<string,int> $entryIds
	 * @return string[]
	 */
	private function unresolvedEntries( NavSpec $spec, string $language, array $entryIds ): array {
		$missing = [];
		foreach ( $spec->items as $item ) {
			if ( isset( $item['entry'] ) && 0 === (int) ( $entryIds[ $item['entry'] . '|' . $language ] ?? 0 ) ) {
				$missing[] = (string) $item['entry'];
			}
		}
		return $missing;
	}

	/** @return array<string,int> navKey|language => post ID */
	private function existing(): array {
		$ids = [];
		foreach ( $this->keyed() as $mapKey => $navIds ) {
			$ids[ $mapKey ] = $navIds[0];
		}
		return $ids;
	}

	/** @return array<string,int[]> map keys carried by more than one entity */
	private function duplicates(): array {
		return array_filter( $this->keyed(), static fn( array $ids ): bool => count( $ids ) > 1 );
	}

	/** @return array<string,int[]> */
	private function keyed(): array {
		$keyed = [];
		foreach (
			get_posts(
				$this->lang->unscopedQuery(
					[
						'post_type'      => 'wp_navigation',
						'post_status'    => [ 'publish', 'draft' ],
						'posts_per_page' => -1,
						'orderby'        => 'ID',
						'order'          => 'ASC',
						'no_found_rows'  => true,
						// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- seed identity lookup.
						'meta_key'       => Meta::KEY,
						'meta_compare'   => 'EXISTS',
					]
				)
			) as $nav
		) {
			$key = (string) get_post_meta( $nav->ID, Meta::KEY, true );
			if ( '' === $key ) {
				continue;
			}
			$language = '';
			foreach ( $this->lang->languages() as $candidate ) {
				if ( $this->lang->translationOf( (int) $nav->ID, $candidate ) === (int) $nav->ID ) {
					$language = $candidate;
					break;
				}
			}
			$keyed[ $key . '|' . $language ][] = (int) $nav->ID;
		}
		return $keyed;
	}
}
```

The shared KSES helper, extracted from `Applier`'s private methods so both writers share one implementation:

```php
<?php
/**
 * KSES suspension for seeder writes.
 *
 * Seeded content is git-authored markup, not user input. Under WP-CLI there is
 * no current user, so kses_init_filters() is active and rewrites what it stores
 * — which both corrupts block-comment JSON and makes stored content differ from
 * what the seeder computed, so the same rows are rewritten on every run.
 *
 * @package Pediment
 */

declare(strict_types=1);

namespace Pediment\Seeder;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Kses {
	/** @return bool Whether the filters were active — pass this back to restore(). */
	public static function suspend(): bool {
		$active = false !== has_filter( 'content_save_pre', 'wp_filter_post_kses' );
		if ( $active ) {
			kses_remove_filters();
		}
		return $active;
	}

	public static function restore( bool $wasActive ): void {
		if ( $wasActive ) {
			kses_init_filters();
		}
	}
}
```

`Applier`'s private `suspendKses()`/`restoreKses()` are deleted and their two call sites become `Kses::suspend()` / `Kses::restore( $kses )`. Behaviour is unchanged; the point is that one helper is easier to keep correct than two copies.

- [ ] **Step 4: Run it green**

Run: `... --filter NavSeederTest`
Expected: PASS (4 tests).

- [ ] **Step 5: Commit**

```bash
git add plugin/src/Seeder/NavSeeder.php plugin/tests/phpunit/Seeder/NavSeederTest.php
git commit -m "feat(seeder): resolve navigation entities by seed key"
```

---

### Task 11: Manifest post types, registered on init

**Files:**
- Create: `plugin/src/Seeder/PostTypes.php`
- Modify: `plugin/src/Bootstrap.php` (register on `init`, priority 5)
- Test: `plugin/tests/phpunit/Seeder/PostTypesTest.php`

**Interfaces:**
- Consumes: `Manifest`, `PostTypeSpec`.
- Produces: `Pediment\Seeder\PostTypes::register(): void` (wires the hook) and `::registerFromManifest(): void`. CPTs declared in the manifest must exist on **every** request, not only during a seed run — otherwise the seeded entries are unreachable and rewrite rules never form.

- [ ] **Step 1: Write the failing test**

```php
<?php
// plugin/tests/phpunit/Seeder/PostTypesTest.php

use Pediment\Seeder\PostTypes;

class PostTypesTest extends WP_UnitTestCase {

	public function tear_down(): void {
		remove_all_filters( 'pediment_seed_manifest' );
		unregister_post_type( 'listing' );
		parent::tear_down();
	}

	public function test_manifest_post_types_are_registered_with_block_editor_support() {
		add_filter(
			'pediment_seed_manifest',
			static fn() => [ 'post_types' => [ 'listing' => [ 'label' => 'Listings', 'has_archive' => true, 'rewrite' => [ 'slug' => 'listings' ] ] ] ]
		);

		PostTypes::registerFromManifest();

		$type = get_post_type_object( 'listing' );
		$this->assertNotNull( $type );
		$this->assertTrue( $type->show_in_rest, 'CPT entries must be editable in Gutenberg' );
		$this->assertSame( 'Listings', $type->label );
	}

	public function test_no_manifest_is_not_an_error() {
		PostTypes::registerFromManifest();

		$this->assertNull( get_post_type_object( 'listing' ) );
	}

	public function test_an_invalid_manifest_never_takes_the_site_down() {
		add_filter( 'pediment_seed_manifest', static fn() => [ 'pages' => [ 'x' => [] ] ] ); // missing title

		PostTypes::registerFromManifest();

		$this->assertTrue( true, 'a broken manifest must not fatal on every request' );
	}

	public function test_register_wires_the_init_hook() {
		// Without this, the whole Bootstrap line could be deleted and the rest of
		// the suite would still pass while no CPT ever registered on a real site.
		PostTypes::register();

		$this->assertSame( 5, has_action( 'init', [ PostTypes::class, 'registerFromManifest' ] ) );
	}

	public function test_a_slug_another_plugin_owns_is_not_claimed_as_ours() {
		register_post_type( 'listing', [ 'public' => false, 'show_in_rest' => false ] );
		add_filter( 'pediment_seed_manifest', static fn() => [ 'post_types' => [ 'listing' => [ 'label' => 'Listings' ] ] ] );

		PostTypes::registerFromManifest();

		$this->assertNotContains( 'listing', PostTypes::registeredSlugs(), 'the manifest args were not applied, so do not claim it' );
		$this->assertFalse( get_post_type_object( 'listing' )->show_in_rest, 'the other registration still owns the slug' );
	}
}
```

- [ ] **Step 2: Run it and watch it fail**

Run: `... --filter PostTypesTest`
Expected: FAIL — `Class "Pediment\Seeder\PostTypes" not found`.

- [ ] **Step 3: Implement**

```php
<?php
/**
 * Registers the client manifest's custom post types.
 *
 * Runs on every request, not just during seeding: a CPT that only exists while
 * seeding produces entries nobody can reach and rewrite rules that never form.
 *
 * @package Pediment
 */

declare(strict_types=1);

namespace Pediment\Seeder;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class PostTypes {
	/** @var array<string,bool> Slugs this class registered, as opposed to ones already taken. */
	private static array $registered = [];

	public static function register(): void {
		add_action( 'init', [ self::class, 'registerFromManifest' ], 5 );
	}

	/** @return string[] */
	public static function registeredSlugs(): array {
		return array_keys( self::$registered );
	}

	public static function registerFromManifest(): void {
		// `init` already populated the per-request memo (PostTypes reads it on
		// every request), and an operator who just edited the manifest expects
		// this run to see the file as it is now.
		Manifest::resetCache();

		try {
			$manifest = Manifest::load();
		} catch ( ManifestError $e ) {
			// A malformed manifest is a seeding-time error, surfaced by
			// `wp pediment seed` and the admin tab. It must never fatal a
			// front-end request.
			return;
		}

		if ( null === $manifest ) {
			return;
		}

		foreach ( $manifest->postTypes() as $spec ) {
			// `init` can fire more than once per request, and another plugin may
			// already own the slug. Recording what WE registered lets the Verifier
			// tell "registered from the manifest" from "someone else got there
			// first, and the manifest's args were silently discarded".
			if ( post_type_exists( $spec->slug ) ) {
				continue;
			}
			register_post_type( $spec->slug, $spec->args );
			self::$registered[ $spec->slug ] = true;
		}
	}
}
```

- [ ] **Step 4: Wire it in Bootstrap**

In `plugin/src/Bootstrap.php`, inside `register()`, directly after the existing `\Pediment\Tokens\Injector::register();` line:

```php
		\Pediment\Seeder\PostTypes::register();
```

- [ ] **Step 5: Run it green**

Run: `... --filter PostTypesTest`
Expected: PASS (3 tests). Also re-run `--filter SmokeTest` to confirm bootstrap still loads.

- [ ] **Step 6: Commit**

```bash
git add plugin/src/Seeder/PostTypes.php plugin/src/Bootstrap.php plugin/tests/phpunit/Seeder/PostTypesTest.php
git commit -m "feat(seeder): register manifest post types on init"
```

---

### Task 12: The Runner and the Verifier — five phases, fail loudly

**Files:**
- Create: `plugin/src/Seeder/Verifier.php`, `plugin/src/Seeder/RunResult.php`, `plugin/src/Seeder/Runner.php`
- Test: `plugin/tests/phpunit/Seeder/RunnerTest.php`, `plugin/tests/phpunit/Seeder/VerifierTest.php`

**Interfaces:**
- Consumes: everything from Tasks 2–11.
- Produces:
  ```php
  final class Pediment\Seeder\Verifier {
      public function __construct( LanguageProvider $lang );
      /** @param array<string,DesiredEntry> $desired @param array<string,int> $ids
       *  @return string[] problems; empty means the post-conditions hold */
      public function verify( Manifest $manifest, array $desired, array $ids, MediaMap $media ): array;
  }
  final class Pediment\Seeder\RunResult {
      public readonly Plan $plan;
      public readonly bool $applied;
      public readonly string $manifestPath;
      public readonly array $errors;     // string[] — plan/apply failures
      public readonly array $problems;   // string[] — verification failures
      public readonly array $ids;        // mapKey => post ID
      public function ok(): bool;        // no errors AND no problems
  }
  final class Pediment\Seeder\Runner {
      public function __construct( ?LanguageProvider $lang = null );  // defaults to LanguageRegistry::provider()
      /** @param array{dry_run?:bool} $options */
      public function run( array $options = [] ): RunResult;
  }
  ```
  Phase order is fixed and must not be rearranged: desired → actual → diff → apply → verify. Rewrite rules flush **once**, after everything, and only when the run applied changes.

- [ ] **Step 1: Write the failing Verifier test**

```php
<?php
// plugin/tests/phpunit/Seeder/VerifierTest.php

use Pediment\Language\NullProvider;
use Pediment\Seeder\ContentResolver;
use Pediment\Seeder\DesiredState;
use Pediment\Seeder\Manifest;
use Pediment\Seeder\MediaMap;
use Pediment\Seeder\Meta;
use Pediment\Seeder\Verifier;

class VerifierTest extends WP_UnitTestCase {

	private function manifest(): Manifest {
		return Manifest::fromArray(
			[ 'pages' => [ 'home' => [ 'title' => 'Home', 'content' => '<p>h</p>', 'front_page' => true ] ] ],
			'/tmp/theme'
		);
	}

	private function desired( Manifest $m ): array {
		return ( new DesiredState( new NullProvider(), new ContentResolver( new MediaMap( [] ) ) ) )->build( $m );
	}

	public function test_a_correctly_seeded_site_reports_no_problems() {
		$m  = $this->manifest();
		$id = self::factory()->post->create( [ 'post_type' => 'page', 'post_name' => 'home', 'post_title' => 'Home' ] );
		update_post_meta( $id, Meta::KEY, 'home' );
		update_option( 'show_on_front', 'page' );
		update_option( 'page_on_front', $id );

		$this->assertSame( [], ( new Verifier( new NullProvider(), new NavSeeder( new NullProvider() ) ) )->verify( $m, $this->desired( $m ), [ 'home|' => $id ], new MediaMap( [] ) ) );
	}

	public function test_a_missing_post_is_a_problem() {
		$m = $this->manifest();

		$problems = ( new Verifier( new NullProvider(), new NavSeeder( new NullProvider() ) ) )->verify( $m, $this->desired( $m ), [], new MediaMap( [] ) );

		$this->assertNotEmpty( $problems );
		$this->assertStringContainsString( 'home', $problems[0] );
	}

	public function test_a_uniquified_slug_is_a_problem() {
		// WordPress appends -2 when a slug collides; silently accepting that is
		// how seeded URLs drift from the manifest.
		$m  = $this->manifest();
		$id = self::factory()->post->create( [ 'post_type' => 'page', 'post_name' => 'home-2', 'post_title' => 'Home' ] );
		update_post_meta( $id, Meta::KEY, 'home' );
		update_option( 'show_on_front', 'page' );
		update_option( 'page_on_front', $id );

		$problems = ( new Verifier( new NullProvider(), new NavSeeder( new NullProvider() ) ) )->verify( $m, $this->desired( $m ), [ 'home|' => $id ], new MediaMap( [] ) );

		$this->assertStringContainsString( 'home-2', implode( "\n", $problems ) );
	}

	public function test_a_front_page_option_pointing_elsewhere_is_a_problem() {
		$m  = $this->manifest();
		$id = self::factory()->post->create( [ 'post_type' => 'page', 'post_name' => 'home', 'post_title' => 'Home' ] );
		update_post_meta( $id, Meta::KEY, 'home' );
		update_option( 'show_on_front', 'posts' );

		$problems = ( new Verifier( new NullProvider(), new NavSeeder( new NullProvider() ) ) )->verify( $m, $this->desired( $m ), [ 'home|' => $id ], new MediaMap( [] ) );

		$this->assertStringContainsString( 'front page', implode( "\n", $problems ) );
	}

	public function test_unresolved_media_is_a_problem() {
		$dir = get_temp_dir() . 'pediment-verify-test';
		wp_mkdir_p( $dir . '/seed/media' );
		file_put_contents( $dir . '/seed/media/logo.svg', '<svg xmlns="http://www.w3.org/2000/svg"/>' );
		$m = Manifest::fromArray( [ 'media' => [ 'logo' => [ 'file' => 'seed/media/logo.svg' ] ] ], $dir );

		$problems = ( new Verifier( new NullProvider(), new NavSeeder( new NullProvider() ) ) )->verify( $m, [], [], new MediaMap( [] ) );

		$this->assertStringContainsString( 'logo', implode( "\n", $problems ) );
	}
}
```

- [ ] **Step 2: Run it and watch it fail**

Run: `... --filter VerifierTest`
Expected: FAIL — `Class "Pediment\Seeder\Verifier" not found`.

- [ ] **Step 3: Implement the Verifier**

```php
<?php
/**
 * Phase 5: post-conditions.
 *
 * The most expensive failure in this project's history was a seeder reporting
 * success while the live header rendered nothing. Everything the seeder claims
 * to own is re-read from the database here and reported if it does not hold.
 *
 * @package Pediment
 */

declare(strict_types=1);

namespace Pediment\Seeder;

use Pediment\Language\LanguageProvider;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Verifier {
	public function __construct(
		private LanguageProvider $lang,
		private NavSeeder $navSeeder
	) {}

	/**
	 * @param array<string,DesiredEntry> $desired
	 * @param array<string,int>          $ids
	 * @param array<string,int>          $navIds navKey|language => post ID
	 * @return string[]
	 */
	public function verify( Manifest $manifest, array $desired, array $ids, MediaMap $media, array $navIds = [] ): array {
		$problems = [];

		foreach ( $desired as $mapKey => $entry ) {
			$postId = (int) ( $ids[ $mapKey ] ?? 0 );
			if ( 0 === $postId ) {
				$problems[] = sprintf( '%s: no post exists for this seed key.', $mapKey );
				continue;
			}

			$post = get_post( $postId );
			if ( ! $post instanceof \WP_Post ) {
				$problems[] = sprintf( '%s: post ID %d does not exist.', $mapKey, $postId );
				continue;
			}
			// Only trash is a problem: `draft` and `pending` are editorial states
			// a client is entitled to set, and the Differ deliberately leaves them.
			if ( 'trash' === $post->post_status ) {
				$problems[] = sprintf( '%s: post %d is in the trash.', $mapKey, $postId );
			}
			if ( $post->post_name !== $entry->slug ) {
				$problems[] = sprintf(
					'%s: slug is "%s" but the manifest says "%s" (WordPress uniquifies colliding slugs).',
					$mapKey,
					$post->post_name,
					$entry->slug
				);
			}
			if ( (string) get_post_meta( $postId, Meta::KEY, true ) !== $entry->key ) {
				$problems[] = sprintf( '%s: post %d is missing its seed key.', $mapKey, $postId );
			}

			$expectedParent = null === $entry->parentKey ? 0 : (int) ( $ids[ $entry->parentKey . '|' . $entry->language ] ?? 0 );
			if ( (int) $post->post_parent !== $expectedParent ) {
				$problems[] = sprintf( '%s: parent is %d, expected %d.', $mapKey, $post->post_parent, $expectedParent );
			}

			if ( $entry->frontPage && $entry->language === $this->lang->defaultLanguage() ) {
				if ( 'page' !== get_option( 'show_on_front' ) || (int) get_option( 'page_on_front' ) !== $postId ) {
					$problems[] = sprintf( '%s: is declared front page but the front page setting points elsewhere.', $mapKey );
				}
			}
			if ( $entry->postsPage && $entry->language === $this->lang->defaultLanguage() && (int) get_option( 'page_for_posts' ) !== $postId ) {
				$problems[] = sprintf( '%s: is declared posts page but page_for_posts points elsewhere.', $mapKey );
			}
		}

		foreach ( $manifest->media() as $key => $spec ) {
			$attachmentId = $media->id( $key );
			if ( $attachmentId <= 0 ) {
				$problems[] = sprintf( 'media.%s: no attachment was created for %s.', $key, basename( $spec->file ) );
				continue;
			}
			// Re-read rather than trusting the map: the ID could name a row that
			// no longer exists.
			$attachment = get_post( $attachmentId );
			if ( ! $attachment instanceof \WP_Post || 'attachment' !== $attachment->post_type || 'trash' === $attachment->post_status ) {
				$problems[] = sprintf( 'media.%s: attachment %d is missing or trashed.', $key, $attachmentId );
			}
		}

		// Navigation: the header rendering nothing while the seeder reports
		// success is the exact incident this class exists to prevent, so the
		// entities are re-read and their membership re-derived.
		foreach ( $manifest->navs() as $key => $spec ) {
			foreach ( $this->lang->languages() as $language ) {
				$navId = (int) ( $navIds[ $key . '|' . $language ] ?? 0 );
				if ( 0 === $navId ) {
					$problems[] = sprintf( 'navs.%s: no navigation entity exists for this seed key.', $key );
					continue;
				}
				$nav = get_post( $navId );
				if ( ! $nav instanceof \WP_Post || 'wp_navigation' !== $nav->post_type ) {
					$problems[] = sprintf( 'navs.%s: post %d is not a navigation entity.', $key, $navId );
					continue;
				}
				if ( 'publish' !== $nav->post_status ) {
					$problems[] = sprintf( 'navs.%s: entity %d is "%s" — the menu will not render.', $key, $navId, $nav->post_status );
				}
				if ( (string) get_post_meta( $navId, Meta::KEY, true ) !== $key ) {
					$problems[] = sprintf( 'navs.%s: entity %d is missing its seed key.', $key, $navId );
				}
				if ( (string) $nav->post_content !== $this->navSeeder->serialize( $spec, $language, $ids ) ) {
					$problems[] = sprintf( 'navs.%s: stored membership does not match the manifest.', $key );
				}
			}
		}

		$registeredByUs = \Pediment\Seeder\PostTypes::registeredSlugs();
		foreach ( $manifest->postTypes() as $spec ) {
			if ( ! post_type_exists( $spec->slug ) ) {
				$problems[] = sprintf( 'post_types.%s: not registered — entries of this type are unreachable.', $spec->slug );
				continue;
			}
			if ( ! in_array( $spec->slug, $registeredByUs, true ) ) {
				$problems[] = sprintf(
					'post_types.%s: already registered by something else — the manifest\'s settings (show_in_rest, supports, rewrite) were not applied.',
					$spec->slug
				);
			}
		}

		return $problems;
	}
}
```

- [ ] **Step 4: Write the failing Runner test**

```php
<?php
// plugin/tests/phpunit/Seeder/RunnerTest.php

use Pediment\Seeder\Meta;
use Pediment\Seeder\PlanItem;
use Pediment\Seeder\Runner;

class RunnerTest extends WP_UnitTestCase {

	private array $manifest;

	public function set_up(): void {
		parent::set_up();
		$this->manifest = [
			'pages' => [
				'home'  => [ 'title' => 'Home', 'content' => '<p>one</p>', 'front_page' => true ],
				'about' => [ 'title' => 'About', 'content' => '<p>about</p>' ],
			],
			'navs'  => [ 'primary' => [ 'title' => 'Primary', 'items' => [ [ 'entry' => 'about' ] ] ] ],
		];
		add_filter( 'pediment_seed_manifest', fn() => $this->manifest );
	}

	public function tear_down(): void {
		remove_all_filters( 'pediment_seed_manifest' );
		parent::tear_down();
	}

	public function test_dry_run_writes_nothing() {
		$result = ( new Runner() )->run( [ 'dry_run' => true ] );

		$this->assertFalse( $result->applied );
		$this->assertCount( 2, $result->plan->byKind( PlanItem::KIND_ENTRY ) );
		$this->assertSame( [], get_posts( [ 'post_type' => 'page', 'fields' => 'ids' ] ) );
	}

	public function test_a_full_run_creates_verifies_and_reports_ok() {
		$result = ( new Runner() )->run();

		$this->assertTrue( $result->ok(), implode( "\n", array_merge( $result->errors, $result->problems ) ) );
		$this->assertTrue( $result->applied );
		$this->assertSame( 'Home', get_post( $result->ids['home|'] )->post_title );
	}

	public function test_a_second_run_is_a_no_op() {
		( new Runner() )->run();

		$second = ( new Runner() )->run();

		$this->assertTrue( $second->plan->isEmpty(), 'a re-seed with no manifest change must plan no writes' );
		$this->assertTrue( $second->ok() );
	}

	public function test_a_client_edit_survives_a_content_release() {
		$first = ( new Runner() )->run();
		$id    = $first->ids['home|'];
		wp_update_post( [ 'ID' => $id, 'post_content' => '<p>client copy</p>' ] );

		$this->manifest['pages']['home']['content'] = '<p>two</p>';
		$result                                     = ( new Runner() )->run();

		$this->assertSame( '<p>client copy</p>', get_post( $id )->post_content );
		$this->assertNotEmpty( $result->plan->byAction( PlanItem::PROTECTED ) );
		$this->assertTrue( $result->ok(), 'protecting content is a success, not a failure' );
	}

	public function test_a_duplicate_seed_key_aborts_before_any_write() {
		$first = ( new Runner() )->run();
		$clone = self::factory()->post->create( [ 'post_type' => 'page', 'post_title' => 'Impostor' ] );
		update_post_meta( $clone, Meta::KEY, 'home' );

		$this->manifest['pages']['home']['content'] = '<p>two</p>';
		$result                                     = ( new Runner() )->run();

		$this->assertFalse( $result->ok() );
		$this->assertFalse( $result->applied );
		$this->assertStringContainsString( 'home', $result->errors[0] );
		$this->assertSame( '<p>one</p>', get_post( $first->ids['home|'] )->post_content );
	}

	public function test_a_manifest_error_is_reported_not_thrown() {
		$this->manifest['pages']['broken'] = [ 'content' => '' ]; // no title

		$result = ( new Runner() )->run();

		$this->assertFalse( $result->ok() );
		$this->assertStringContainsString( 'title', $result->errors[0] );
	}

	public function test_no_manifest_reports_a_clear_error() {
		remove_all_filters( 'pediment_seed_manifest' );

		$result = ( new Runner() )->run();

		$this->assertFalse( $result->ok() );
		$this->assertStringContainsString( 'seed/manifest.php', $result->errors[0] );
	}

	public function test_navigation_is_seeded_after_pages_so_links_resolve() {
		$result = ( new Runner() )->run();

		$navs = get_posts( [ 'post_type' => 'wp_navigation', 'posts_per_page' => -1 ] );
		$this->assertCount( 1, $navs );
		$this->assertStringContainsString( '"id":' . $result->ids['about|'], $navs[0]->post_content );
	}

	public function test_an_errored_plan_leaves_no_media_behind() {
		// Media used to be applied before the plan was checked, so an unrelated
		// error still created attachments and moved the site logo while the run
		// reported that nothing had been applied. The duplicate key and the
		// unseeded media must both be present on the FIRST run, or the media is
		// already `unchanged` and the regression cannot show.
		$dir = get_temp_dir() . 'pediment-runner-media';
		wp_mkdir_p( $dir . '/seed/media' );
		file_put_contents( $dir . '/seed/media/logo.svg', '<svg xmlns="http://www.w3.org/2000/svg"/>' );
		add_filter( 'stylesheet_directory', static fn() => $dir );
		$this->manifest['media'] = [ 'logo' => [ 'file' => 'seed/media/logo.svg' ] ];
		$this->manifest['site']  = [ 'logo' => 'logo' ];

		foreach ( [ 1, 2 ] as $ignored ) {
			$impostor = self::factory()->post->create( [ 'post_type' => 'page' ] );
			update_post_meta( $impostor, Meta::KEY, 'home' );
		}
		$logoBefore = get_theme_mod( 'custom_logo' );

		$result = ( new Runner() )->run();

		remove_all_filters( 'stylesheet_directory' );
		$this->assertFalse( $result->ok() );
		$this->assertFalse( $result->applied );
		$this->assertSame( [], get_posts( [ 'post_type' => 'attachment', 'post_status' => 'any', 'fields' => 'ids' ] ), 'an errored plan creates no attachments' );
		$this->assertSame( $logoBefore, get_theme_mod( 'custom_logo' ), 'an errored plan writes nothing at all' );
	}

	public function test_a_post_type_only_manifest_still_flushes_rewrite_rules() {
		// The plan must be EMPTY for this to prove anything: with pages still to
		// create, the old `! $plan->isEmpty()` gate would flush anyway.
		$GLOBALS['wp_rewrite']->set_permalink_structure( '/%postname%/' );
		( new Runner() )->run();

		$this->manifest['post_types'] = [ 'project' => [ 'label' => 'Projects', 'has_archive' => true ] ];
		\Pediment\Seeder\Manifest::resetCache();
		\Pediment\Seeder\PostTypes::registerFromManifest();
		delete_option( 'rewrite_rules' );

		$result = ( new Runner() )->run();

		$this->assertTrue( $result->plan->isEmpty(), 'nothing but the post type changed' );
		$this->assertStringContainsString( 'post_type=project', implode( "\n", (array) get_option( 'rewrite_rules' ) ) );
		unregister_post_type( 'project' );
	}

	public function test_a_client_unpublishing_the_menu_is_reported() {
		$result = ( new Runner() )->run();
		$navs   = get_posts( [ 'post_type' => 'wp_navigation', 'posts_per_page' => -1 ] );
		wp_update_post( [ 'ID' => $navs[0]->ID, 'post_status' => 'draft' ] );

		$second = ( new Runner() )->run();

		$this->assertFalse( $second->ok(), 'a menu that will not render is not a successful seed' );
		$this->assertStringContainsString( 'navs.primary', implode( "\n", $second->problems ) );
	}

	public function test_a_media_plan_error_is_reported_exactly_once() {
		// A missing file is rejected at manifest load, which never reaches the
		// media seeder. An unsupported extension on a file that DOES exist is a
		// media-plan error, which is the path that used to double-report.
		$dir = get_temp_dir() . 'pediment-runner-mime';
		wp_mkdir_p( $dir . '/seed/media' );
		file_put_contents( $dir . '/seed/media/notes.txt', 'not an image' );
		add_filter( 'stylesheet_directory', static fn() => $dir );
		$this->manifest['media'] = [ 'notes' => [ 'file' => 'seed/media/notes.txt' ] ];

		$result = ( new Runner() )->run();

		remove_all_filters( 'stylesheet_directory' );
		$this->assertFalse( $result->ok() );
		$this->assertSame( 1, substr_count( implode( "\n", $result->errors ), 'media.notes' ), 'reported once, not once per channel' );
	}
}
```

- [ ] **Step 5: Run both and watch them fail**

Run: `... --filter RunnerTest`
Expected: FAIL — `Class "Pediment\Seeder\Runner" not found`.

- [ ] **Step 6: Implement RunResult and Runner**

```php
<?php
declare(strict_types=1);

namespace Pediment\Seeder;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class RunResult {
	/**
	 * @param string[]          $errors
	 * @param string[]          $problems
	 * @param array<string,int> $ids
	 */
	public function __construct(
		public readonly Plan $plan,
		public readonly bool $applied,
		public readonly string $manifestPath = '',
		public readonly array $errors = [],
		public readonly array $problems = [],
		public readonly array $ids = []
	) {}

	public function ok(): bool {
		return [] === $this->errors && [] === $this->problems;
	}
}
```

```php
<?php
/**
 * The seeding engine's entry point. One code path for WP-CLI and wp-admin.
 *
 * Five phases, always in this order (spec §4.2):
 *   1. resolve desired state   2. resolve actual state   3. diff into a plan
 *   4. apply                   5. verify and fail loudly
 *
 * @package Pediment
 */

declare(strict_types=1);

namespace Pediment\Seeder;

use Pediment\Language\LanguageProvider;
use Pediment\Language\LanguageRegistry;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Runner {
	private LanguageProvider $lang;

	public function __construct( ?LanguageProvider $lang = null ) {
		$this->lang = $lang ?? LanguageRegistry::provider();
	}

	/** @param array{dry_run?:bool} $options */
	public function run( array $options = [] ): RunResult {
		$dryRun = ! empty( $options['dry_run'] );

		// `init` already populated the per-request memo (PostTypes reads it on
		// every request), and an operator who just edited the manifest expects
		// this run to see the file as it is now.
		Manifest::resetCache();

		try {
			$manifest = Manifest::load();
		} catch ( ManifestError $e ) {
			return new RunResult( new Plan(), false, '', [ $e->getMessage() ] );
		}

		if ( null === $manifest ) {
			return new RunResult(
				new Plan(),
				false,
				'',
				[ sprintf( 'No seed manifest found. Create %s/%s in the active theme.', get_stylesheet(), Manifest::RELATIVE_PATH ) ]
			);
		}

		$mediaSeeder = new MediaSeeder();
		$navSeeder   = new NavSeeder( $this->lang );
		$mediaPlan   = $mediaSeeder->plan( $manifest );
		$reader      = new StateReader( $this->lang );

		// Phases 1-3 are planned against the media that exists NOW, so the whole
		// plan — media, entries and navs — is known before anything is written.
		// Applying media first would mean an errored plan still left attachments
		// and a changed site logo behind while reporting "nothing was applied".
		try {
			$preview = ( new DesiredState( $this->lang, new ContentResolver( $mediaSeeder->map( $manifest ) ) ) )->build( $manifest );
		} catch ( ManifestError $e ) {
			return new RunResult( $mediaPlan, false, $manifest->path(), [ $e->getMessage() ] );
		}

		$entryPlan = ( new Differ() )->diff( $preview, $reader->read(), $reader->duplicates() );
		$entryIds  = $this->resolvedIds( $entryPlan );
		$plan      = Plan::merge( $mediaPlan, $entryPlan, $navSeeder->plan( $manifest, $entryIds ) );

		if ( $dryRun || $plan->hasErrors() ) {
			return new RunResult( $plan, false, $manifest->path(), $plan->errors(), [], $entryIds );
		}

		// Phase 4. Media goes first here, because page content references
		// attachments by key and the map has to be real before content is
		// resolved for the write.
		$mediaMap    = $mediaSeeder->apply( $mediaPlan, $manifest );
		$mediaErrors = $mediaSeeder->errors();

		try {
			$desired = ( new DesiredState( $this->lang, new ContentResolver( $mediaMap ) ) )->build( $manifest );
		} catch ( ManifestError $e ) {
			return new RunResult( $plan, true, $manifest->path(), array_merge( $mediaErrors, [ $e->getMessage() ] ) );
		}

		$entryPlan = ( new Differ() )->diff( $desired, $reader->read(), $reader->duplicates() );
		$applied   = ( new Applier( $this->lang ) )->apply( $entryPlan, $desired );

		// Nav links need the resolved page IDs, so nav is planned again against them.
		$navPlan = $navSeeder->plan( $manifest, $applied->ids );
		$navIds  = $navSeeder->apply( $navPlan, $manifest, $applied->ids );

		// Report the plan that actually ran, not the preview.
		$plan = Plan::merge( $mediaPlan, $entryPlan, $navPlan );

		// Once, at the end, after every post type is registered. Soft flush: a
		// hard flush rewrites .htaccess, and this engine never touches the
		// permalink structure (see plugin/inc/bootstrap.php and pediment#47).
		// It runs on a partial failure too — writes that landed still need their
		// rules — and when a manifest post type has no rules yet, which no plan
		// item can express because post types produce none.
		if ( ! $plan->isEmpty() || $this->postTypeRulesMissing( $manifest ) ) {
			flush_rewrite_rules( false );
		}

		// Phase 5 runs even when something failed: an operator debugging a
		// partial apply needs to know what actually landed.
		$problems = ( new Verifier( $this->lang, $navSeeder ) )->verify( $manifest, $desired, $applied->ids, $mediaMap, $navIds );
		$errors   = array_values( array_unique( array_merge( $mediaErrors, $applied->errors, $navSeeder->errors() ) ) );

		return new RunResult( $plan, true, $manifest->path(), $errors, $problems, $applied->ids );
	}

	/** @return array<string,int> mapKey => post ID for entries that already exist */
	private function resolvedIds( Plan $entryPlan ): array {
		$ids = [];
		foreach ( $entryPlan->byKind( PlanItem::KIND_ENTRY ) as $item ) {
			if ( $item->postId > 0 && PlanItem::ORPHAN !== $item->action ) {
				$ids[ $item->mapKey() ] = $item->postId;
			}
		}
		return $ids;
	}

	/**
	 * Whether any manifest post type has no rewrite rules yet.
	 *
	 * Post types produce no plan items, so a manifest that adds a CPT and
	 * nothing else would otherwise never flush, and its permalinks would 404
	 * until someone re-saved Settings > Permalinks.
	 */
	private function postTypeRulesMissing( Manifest $manifest ): bool {
		$postTypes = $manifest->postTypes();
		if ( [] === $postTypes ) {
			return false;
		}

		$rules = implode( "\n", array_values( (array) get_option( 'rewrite_rules', [] ) ) );
		foreach ( $postTypes as $spec ) {
			if ( ! str_contains( $rules, 'post_type=' . $spec->slug ) ) {
				return true;
			}
		}
		return false;
	}
}
```

- [ ] **Step 7: Run both suites green**

Run: `... --filter VerifierTest` then `... --filter RunnerTest`
Expected: PASS (5 and 8 tests).

- [ ] **Step 8: Full suite and lint**

```bash
npx wp-env run tests-wordpress --env-cwd=wp-content/plugins/pediment-ai ./vendor/bin/phpunit
cd plugin && composer lint && cd ..
```

Expected: all green. This is the engine's checkpoint — the remaining tasks are surfaces on top of it.

- [ ] **Step 9: Commit**

```bash
git add plugin/src/Seeder/Runner.php plugin/src/Seeder/RunResult.php plugin/src/Seeder/Verifier.php plugin/tests/phpunit/Seeder/RunnerTest.php plugin/tests/phpunit/Seeder/VerifierTest.php
git commit -m "feat(seeder): run the five seeding phases with post-condition checks"
```

---

### Task 13: The readable plan and `wp pediment seed`

**Files:**
- Create: `plugin/src/Seeder/Reporter.php`, `plugin/wp-cli/SeedCommand.php`
- Modify: `plugin/plugin.php` (register the command next to `pediment dump-schema`)
- Test: `plugin/tests/phpunit/Seeder/ReporterTest.php`

**Interfaces:**
- Consumes: `RunResult`, `Plan`, `PlanItem`.
- Produces: `Pediment\Seeder\Reporter::text( RunResult $result ): string` and `::summaryLine( RunResult $result ): string`; `wp pediment seed [--dry-run] [--json]` exiting non-zero when `RunResult::ok()` is false.
- Rationale: "the seeder reported `skipped (exists, published)` while the live header rendered nothing" is the failure this output exists to prevent. Every action, every protected field, every orphan appears.

Target output:

```
Pediment seed — dry run
manifest: /var/www/html/wp-content/themes/acme/seed/manifest.php
languages: (monolingual)

MEDIA
  create      hero-bg          hero-bg.jpg
  unchanged   logo

PAGES & POSTS
  create      home             slug=home, front page
  update      about            title "About" -> "About us"; content 812 -> 947 bytes
  update      contact          slug "kontakt" -> "contact"
              ^ protected: content (edited in the editor — content and title left alone)
  unchanged   guide
  orphan      legacy-offer     "Legacy offer" (ID 42) carries a seed key the manifest no longer declares — left in place

NAV
  update      primary          items 3 -> 4

3 to write, 1 protected, 1 orphan, 1 unchanged. Nothing was written (--dry-run).
```

- [ ] **Step 1: Write the failing test**

```php
<?php
// plugin/tests/phpunit/Seeder/ReporterTest.php

use Pediment\Seeder\Plan;
use Pediment\Seeder\PlanItem;
use Pediment\Seeder\Reporter;
use Pediment\Seeder\RunResult;

class ReporterTest extends WP_UnitTestCase {

	private function result( bool $applied = false ): RunResult {
		$plan = new Plan(
			[
				new PlanItem( PlanItem::CREATE, PlanItem::KIND_MEDIA, 'hero-bg', '', 0, [ 'file' => [ 'from' => null, 'to' => 'hero-bg.jpg' ] ] ),
				new PlanItem( PlanItem::CREATE, PlanItem::KIND_ENTRY, 'home', '', 0, [ 'slug' => [ 'from' => null, 'to' => 'home' ] ] ),
				new PlanItem(
					PlanItem::UPDATE,
					PlanItem::KIND_ENTRY,
					'contact',
					'',
					9,
					[ 'slug' => [ 'from' => 'kontakt', 'to' => 'contact' ] ],
					[ 'content' => [ 'from' => '(database)', 'to' => '(manifest)' ] ],
					'edited in the editor — content and title left alone'
				),
				new PlanItem( PlanItem::UNCHANGED, PlanItem::KIND_ENTRY, 'guide', '', 11 ),
				new PlanItem( PlanItem::ORPHAN, PlanItem::KIND_ENTRY, 'legacy', '', 42, [], [], '"Legacy offer" (ID 42) — left in place' ),
			]
		);
		return new RunResult( $plan, $applied, '/themes/acme/seed/manifest.php' );
	}

	public function test_every_action_appears_in_the_report() {
		$text = Reporter::text( $this->result() );

		$this->assertStringContainsString( '/themes/acme/seed/manifest.php', $text );
		$this->assertStringContainsString( 'create', $text );
		$this->assertStringContainsString( 'hero-bg.jpg', $text );
		$this->assertStringContainsString( 'kontakt', $text );
		$this->assertStringContainsString( 'unchanged', $text );
		$this->assertStringContainsString( 'orphan', $text );
	}

	public function test_protected_fields_are_called_out_under_their_entry() {
		$this->assertStringContainsString( 'protected: content', Reporter::text( $this->result() ) );
	}

	public function test_a_dry_run_says_nothing_was_written() {
		$this->assertStringContainsString( 'Nothing was written', Reporter::text( $this->result( false ) ) );
		$this->assertStringNotContainsString( 'Nothing was written', Reporter::text( $this->result( true ) ) );
	}

	public function test_errors_and_problems_are_never_buried() {
		$result = new RunResult( new Plan(), false, '', [ 'duplicate key "home"' ], [ 'home: slug is "home-2"' ] );

		$text = Reporter::text( $result );

		$this->assertStringContainsString( 'ERRORS', $text );
		$this->assertStringContainsString( 'duplicate key "home"', $text );
		$this->assertStringContainsString( 'VERIFICATION', $text );
		$this->assertStringContainsString( 'home-2', $text );
	}

	public function test_a_partial_apply_does_not_claim_nothing_was_applied() {
		$result = new RunResult( new Plan(), true, '', [ 'media.logo: could not copy' ] );

		$text = Reporter::text( $result );

		$this->assertStringContainsString( 'the run continued', $text );
		$this->assertStringNotContainsString( 'nothing was applied', $text );
	}

	public function test_a_protected_item_states_its_note_once() {
		$plan = new Plan(
			[
				new PlanItem(
					PlanItem::PROTECTED,
					PlanItem::KIND_ENTRY,
					'about',
					'',
					9,
					[],
					[
						'title'   => [ 'from' => 'About', 'to' => 'About us' ],
						'content' => [ 'from' => '(database)', 'to' => '(manifest)' ],
					],
					'edited in the editor — content and title left alone'
				),
			]
		);

		$text = Reporter::text( new RunResult( $plan, false, '' ) );

		$this->assertSame( 1, substr_count( $text, 'edited in the editor' ), 'one item, one explanation' );
	}

	public function test_summary_line_counts_writes_protections_and_orphans() {
		// 2 creates (media hero-bg + page home) + 1 update (contact) = 3 writes.
		$this->assertSame( '3 to write, 1 protected, 1 orphan, 1 unchanged.', Reporter::summaryLine( $this->result( true ) ) );
	}
}
```

- [ ] **Step 2: Run it and watch it fail**

Run: `... --filter ReporterTest`
Expected: FAIL — `Class "Pediment\Seeder\Reporter" not found`.

- [ ] **Step 3: Implement the Reporter**

```php
<?php
/**
 * Renders a RunResult as plain text for WP-CLI and (inside <pre>) wp-admin.
 *
 * @package Pediment
 */

declare(strict_types=1);

namespace Pediment\Seeder;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Reporter {
	public static function text( RunResult $result ): string {
		$lines = [];

		$lines[] = $result->applied ? 'Pediment seed' : 'Pediment seed — dry run';
		if ( '' !== $result->manifestPath ) {
			$lines[] = 'manifest: ' . $result->manifestPath;
		}

		foreach (
			[
				PlanItem::KIND_MEDIA => 'MEDIA',
				PlanItem::KIND_ENTRY => 'PAGES & POSTS',
				PlanItem::KIND_NAV   => 'NAV',
			] as $kind => $heading
		) {
			$items = $result->plan->byKind( $kind );
			if ( [] === $items ) {
				continue;
			}
			$lines[] = '';
			$lines[] = $heading;
			foreach ( $items as $item ) {
				$described = self::describe( $item );
				$lines[]   = sprintf( '  %-11s %-16s %s', $item->action, $item->key, $described );

				// When describe() already fell back to the note, the fields line
				// would repeat that same sentence once per protected field — three
				// identical sentences for the commonest shape on a live site.
				if ( [] !== $item->protectedFields && $described !== $item->note ) {
					$lines[] = sprintf( '              ^ protected: %s (%s)', implode( ', ', array_keys( $item->protectedFields ) ), $item->note );
				}
			}
		}

		if ( [] !== $result->errors ) {
			$lines[] = '';
			// The Runner deliberately continues past a failed write so it can
			// still verify and flush, so "nothing was applied" is only true
			// before phase 4. Saying it afterwards would be a lie of exactly the
			// kind this report exists to prevent.
			$lines[] = $result->applied
				? 'ERRORS (the run continued — see above for what landed)'
				: 'ERRORS (nothing was applied)';
			foreach ( $result->errors as $error ) {
				$lines[] = '  - ' . $error;
			}
		}

		if ( [] !== $result->problems ) {
			$lines[] = '';
			$lines[] = 'VERIFICATION FAILED';
			foreach ( $result->problems as $problem ) {
				$lines[] = '  - ' . $problem;
			}
		}

		$lines[] = '';
		$lines[] = self::summaryLine( $result ) . ( $result->applied ? '' : ' Nothing was written (--dry-run).' );

		return implode( "\n", $lines );
	}

	public static function summaryLine( RunResult $result ): string {
		$counts    = $result->plan->counts();
		$writes    = ( $counts[ PlanItem::CREATE ] ?? 0 ) + ( $counts[ PlanItem::UPDATE ] ?? 0 ) + ( $counts[ PlanItem::RESTORE ] ?? 0 );
		$protected = 0;
		foreach ( $result->plan->items() as $item ) {
			if ( [] !== $item->protectedFields ) {
				++$protected;
			}
		}

		return sprintf(
			'%d to write, %d protected, %d orphan, %d unchanged.',
			$writes,
			$protected,
			$counts[ PlanItem::ORPHAN ] ?? 0,
			$counts[ PlanItem::UNCHANGED ] ?? 0
		);
	}

	private static function describe( PlanItem $item ): string {
		$parts = [];
		foreach ( $item->changes as $field => $change ) {
			if ( 'content' === $field ) {
				$parts[] = sprintf( 'content -> %d bytes', strlen( (string) $change['to'] ) );
				continue;
			}
			$parts[] = null === $change['from']
				? sprintf( '%s=%s', $field, self::truncate( self::scalar( $change['to'] ) ) )
				: self::change( $field, $change['from'], $change['to'] );
		}
		if ( [] === $parts && '' !== $item->note ) {
			return $item->note;
		}
		return implode( '; ', $parts );
	}

	private static function scalar( mixed $value ): string {
		if ( is_bool( $value ) ) {
			return $value ? 'true' : 'false';
		}
		return (string) ( null === $value ? '' : $value );
	}

	/** Keeps one plan line readable when a title or slug is very long. */
	private static function truncate( string $value, int $limit = 60 ): string {
		return mb_strlen( $value ) > $limit ? mb_substr( $value, 0, $limit - 1 ) . '…' : $value;
	}

	private static function change( string $field, mixed $from, mixed $to ): string {
		// Numbers read badly in quotes: `items 3 -> 4`, not `items "3" -> "4"`.
		if ( is_int( $from ) && is_int( $to ) ) {
			return sprintf( '%s %d -> %d', $field, $from, $to );
		}
		return sprintf( '%s "%s" -> "%s"', $field, self::truncate( self::scalar( $from ) ), self::truncate( self::scalar( $to ) ) );
	}
}
```

- [ ] **Step 4: Run it green**

Run: `... --filter ReporterTest`
Expected: PASS (5 tests). If `summaryLine`'s exact string differs from the assertion, fix the assertion to match the implementation — the format is the contract, not the test's phrasing.

- [ ] **Step 5: Add the WP-CLI command**

```php
<?php
/**
 * WP-CLI: `wp pediment seed`.
 *
 * @package Pediment
 */

declare(strict_types=1);

namespace Pediment\Cli;

use Pediment\Seeder\Reporter;
use Pediment\Seeder\Runner;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Applies the active theme's seed manifest to this site.
 */
final class SeedCommand {
	/**
	 * ## OPTIONS
	 *
	 * [--dry-run]
	 * : Print the plan and exit without writing anything.
	 *
	 * [--json]
	 * : Emit the plan as JSON instead of text.
	 *
	 * ## EXAMPLES
	 *
	 *     wp pediment seed --dry-run
	 *     wp pediment seed
	 *
	 * @when after_wp_load
	 *
	 * @param array<int,string>    $args       Positional args (unused).
	 * @param array<string,string> $assocArgs  Associative args.
	 */
	public function __invoke( array $args, array $assocArgs ): void {
		$result = ( new Runner() )->run( [ 'dry_run' => isset( $assocArgs['dry-run'] ) ] );
		$output = self::render( $result, isset( $assocArgs['json'] ) );

		// Guarded like DumpSchemaCommand so the rendering is unit-testable
		// without WP-CLI loaded.
		if ( class_exists( '\WP_CLI' ) ) {
			\WP_CLI::line( $output );

			if ( ! $result->ok() ) {
				\WP_CLI::error( 'Seeding did not complete cleanly. See the report above.' );
			}

			\WP_CLI::success( $result->applied ? 'Seed applied.' : 'Dry run complete — nothing was written.' );
		}
	}

	/** The exact bytes the command prints, so the shape can be tested. */
	public static function render( RunResult $result, bool $json = false ): string {
		if ( ! $json ) {
			return Reporter::text( $result );
		}

		$items = [];
		foreach ( $result->plan->items() as $item ) {
			$items[] = [
				'kind'      => $item->kind,
				'action'    => $item->action,
				'key'       => $item->key,
				'language'  => $item->language,
				'post_id'   => $item->postId,
				'changes'   => array_keys( $item->changes ),
				'protected' => array_keys( $item->protectedFields ),
				'note'      => $item->note,
			];
		}

		return (string) wp_json_encode(
			[
				'applied'  => $result->applied,
				'ok'       => $result->ok(),
				'manifest' => $result->manifestPath,
				'counts'   => $result->plan->counts(),
				// Counts alone cannot answer "which pages are protected?", which
				// is the question anyone scripting against --json is asking.
				'items'    => $items,
				'errors'   => $result->errors,
				'problems' => $result->problems,
			],
			JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES
		);
	}
}
```

In `plugin/plugin.php`, extend the existing WP-CLI block:

```php
if ( defined( 'WP_CLI' ) && WP_CLI ) {
	require_once __DIR__ . '/wp-cli/DumpSchemaCommand.php';
	\WP_CLI::add_command( 'pediment dump-schema', \Pediment\Cli\DumpSchemaCommand::class );

	require_once __DIR__ . '/wp-cli/SeedCommand.php';
	\WP_CLI::add_command( 'pediment seed', \Pediment\Cli\SeedCommand::class );
}
```

- [ ] **Step 6: Exercise the command against wp-env**

```bash
npx wp-env run cli wp pediment seed --dry-run
```

Expected on a site whose theme ships no manifest: the "No seed manifest found." error with a non-zero exit. That is the correct behaviour until Task 16 gives the fixture theme a manifest — record the output and move on.

- [ ] **Step 7: Commit**

```bash
git add plugin/src/Seeder/Reporter.php plugin/wp-cli/SeedCommand.php plugin/plugin.php plugin/tests/phpunit/Seeder/ReporterTest.php
git commit -m "feat(seeder): add wp pediment seed with a readable dry-run plan"
```

---

### Task 14: The wp-admin runner

**Files:**
- Create: `plugin/inc/seeding-admin.php`
- Modify: `plugin/plugin.php` (require the new file next to the other `inc/` requires)
- Test: `plugin/tests/phpunit/Seeder/SeedingAdminTest.php`

**Interfaces:**
- Consumes: `Runner`, `Reporter`.
- Produces: a "Seeding" tab on the existing Settings > Pediment Theme hub via `pediment_settings_register_tab( 'seed', … )`, plus `pediment_seed_admin_handle_post(): ?string` (returns the rendered report, or null when the request is not a seed submission).
- The admin path is the same `Runner`, with PHP limits lifted — identical code passing under CLI and dying with a generic critical error in wp-admin is exactly what `bfd550f` cost.

- [ ] **Step 1: Write the failing test**

```php
<?php
// plugin/tests/phpunit/Seeder/SeedingAdminTest.php

class SeedingAdminTest extends WP_UnitTestCase {

	public function set_up(): void {
		parent::set_up();
		$GLOBALS['pediment_settings_tabs'] = [];
		add_filter(
			'pediment_seed_manifest',
			static fn() => [ 'pages' => [ 'home' => [ 'title' => 'Home', 'content' => '<p>hi</p>' ] ] ]
		);
	}

	public function tear_down(): void {
		remove_all_filters( 'pediment_seed_manifest' );
		unset( $_POST['pediment_seed_action'], $_POST['_wpnonce'] );
		parent::tear_down();
	}

	public function test_the_seeding_tab_is_registered() {
		do_action( 'admin_menu' );

		$this->assertArrayHasKey( 'seed', pediment_settings_get_tabs() );
	}

	public function test_a_preview_submission_returns_a_plan_without_writing() {
		wp_set_current_user( self::factory()->user->create( [ 'role' => 'administrator' ] ) );
		$_POST['pediment_seed_action'] = 'preview';
		$_POST['_wpnonce']             = wp_create_nonce( 'pediment_seed' );

		$report = pediment_seed_admin_handle_post();

		$this->assertStringContainsString( 'dry run', $report );
		$this->assertSame( [], get_posts( [ 'post_type' => 'page', 'fields' => 'ids' ] ) );
	}

	public function test_an_apply_submission_writes() {
		wp_set_current_user( self::factory()->user->create( [ 'role' => 'administrator' ] ) );
		$_POST['pediment_seed_action'] = 'apply';
		$_POST['_wpnonce']             = wp_create_nonce( 'pediment_seed' );

		pediment_seed_admin_handle_post();

		$this->assertCount( 1, get_posts( [ 'post_type' => 'page', 'fields' => 'ids' ] ) );
	}

	public function test_a_subscriber_cannot_seed() {
		wp_set_current_user( self::factory()->user->create( [ 'role' => 'subscriber' ] ) );
		$_POST['pediment_seed_action'] = 'apply';
		$_POST['_wpnonce']             = wp_create_nonce( 'pediment_seed' );

		$this->assertNull( pediment_seed_admin_handle_post() );
		$this->assertSame( [], get_posts( [ 'post_type' => 'page', 'fields' => 'ids' ] ) );
	}

	public function test_a_bad_nonce_is_rejected() {
		wp_set_current_user( self::factory()->user->create( [ 'role' => 'administrator' ] ) );
		$_POST['pediment_seed_action'] = 'apply';
		$_POST['_wpnonce']             = 'nope';

		$this->assertNull( pediment_seed_admin_handle_post() );
		$this->assertSame( [], get_posts( [ 'post_type' => 'page', 'fields' => 'ids' ] ) );
	}
}
```

- [ ] **Step 2: Run it and watch it fail**

Run: `... --filter SeedingAdminTest`
Expected: FAIL — `Call to undefined function pediment_seed_admin_handle_post()`.

- [ ] **Step 3: Implement**

```php
<?php
/**
 * Settings > Pediment Theme > Seeding.
 *
 * The same Runner the CLI uses, with PHP limits lifted: identical code passing
 * under WP-CLI and dying with a generic critical error in wp-admin is what
 * bfd550f cost a day to.
 *
 * @package Pediment
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action(
	'admin_menu',
	function () {
		if ( function_exists( 'pediment_settings_register_tab' ) ) {
			pediment_settings_register_tab( 'seed', __( 'Seeding', 'pediment' ), 'pediment_seed_admin_render_tab', 20 );
		}
	}
);

/**
 * Handle a seed form submission.
 *
 * @return string|null Rendered report, or null when this is not a valid seed submission.
 */
function pediment_seed_admin_handle_post(): ?string {
	$action = isset( $_POST['pediment_seed_action'] ) ? sanitize_key( wp_unslash( $_POST['pediment_seed_action'] ) ) : '';
	if ( ! in_array( $action, array( 'preview', 'apply' ), true ) ) {
		return null;
	}
	if ( ! current_user_can( 'manage_options' ) ) {
		return null;
	}
	$nonce = isset( $_POST['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ) ) : '';
	if ( ! wp_verify_nonce( $nonce, 'pediment_seed' ) ) {
		return null;
	}

	// Seeding a large site writes hundreds of rows and generates image sizes.
	if ( function_exists( 'set_time_limit' ) ) {
		// phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- disabled by some hosts.
		@set_time_limit( 0 );
	}
	wp_raise_memory_limit( 'admin' );

	$result = ( new \Pediment\Seeder\Runner() )->run( array( 'dry_run' => 'preview' === $action ) );

	return \Pediment\Seeder\Reporter::text( $result );
}

/**
 * Render the tab body.
 *
 * @return void
 */
function pediment_seed_admin_render_tab(): void {
	$report = pediment_seed_admin_handle_post();

	echo '<p>' . esc_html__(
		'Applies the active theme\'s seed manifest. Structure (which pages exist, their slugs, nesting, menus) is owned by the theme; page content you have edited in the editor is never overwritten.',
		'pediment'
	) . '</p>';

	// Two forms rather than two submit buttons in one: each carries its own
	// hidden action, so the POST is unambiguous and no JS is involved.
	echo '<div style="display:flex;gap:8px;align-items:center;">';
	foreach ( array(
		'preview' => array( __( 'Preview plan', 'pediment' ), 'secondary' ),
		'apply'   => array( __( 'Apply plan', 'pediment' ), 'primary' ),
	) as $value => $button ) {
		echo '<form method="post" style="margin:0;">';
		wp_nonce_field( 'pediment_seed' );
		echo '<input type="hidden" name="pediment_seed_action" value="' . esc_attr( $value ) . '" />';
		submit_button( $button[0], $button[1], 'submit', false );
		echo '</form>';
	}
	echo '</div>';

	if ( null !== $report ) {
		echo '<pre class="pediment-seed-report" style="max-height:60vh;overflow:auto;padding:12px;background:#fff;border:1px solid #c3c4c7;">'
			. esc_html( $report ) . '</pre>';
	}
}
```

In `plugin/plugin.php`, next to the other presentation requires:

```php
require_once PEDIMENT_AI_PLUGIN_DIR . '/inc/seeding-admin.php';
```

- [ ] **Step 4: Run it green**

Run: `... --filter SeedingAdminTest`
Expected: PASS (5 tests).

- [ ] **Step 5: Look at it**

```bash
npx wp-env run cli wp option get siteurl
```

Open `/wp-admin/options-general.php?page=pediment-theme&tab=seed` in Chrome (never Brave), click **Preview plan**, and confirm the report renders inside the `<pre>`. Screenshot it for the session log.

- [ ] **Step 6: Commit**

```bash
git add plugin/inc/seeding-admin.php plugin/plugin.php plugin/tests/phpunit/Seeder/SeedingAdminTest.php
git commit -m "feat(seeder): add the wp-admin seeding tab with lifted PHP limits"
```

---

### Task 15: `wp pediment adopt` — the inverse operation

**Files:**
- Create: `plugin/src/Seeder/Adopter.php`, `plugin/wp-cli/AdoptCommand.php`
- Modify: `plugin/plugin.php` (register the command)
- Test: `plugin/tests/phpunit/Seeder/AdopterTest.php`

**Interfaces:**
- Consumes: `Manifest`, `Meta`, `ContentHash`, `StateReader`.
- Produces:
  ```php
  final class Pediment\Seeder\Adopter {
      public function __construct( LanguageProvider $lang );
      /** @return array{path:string,bytes:int,written:bool,errors:string[]} */
      public function adopt( string $seedKey, string $language = '', bool $dryRun = false ): array;
  }
  ```
  Exports a live entry's `post_content` back to the theme's `patterns/<slug>.php` (header block + markup), then resets `_pediment_seed_hash` and `_pediment_seed_source` so the next seed sees an unedited page. This is what makes `port-page`'s "Step 9: persist to version control" a command instead of a ritual, and what makes step 6's Workation migration a first-run adopt.

- [ ] **Step 1: Write the failing test**

```php
<?php
// plugin/tests/phpunit/Seeder/AdopterTest.php

use Pediment\Language\NullProvider;
use Pediment\Seeder\Adopter;
use Pediment\Seeder\ContentHash;
use Pediment\Seeder\Meta;
use Pediment\Seeder\Runner;

class AdopterTest extends WP_UnitTestCase {

	private string $dir;

	public function set_up(): void {
		parent::set_up();
		$this->dir = get_temp_dir() . 'pediment-adopt-test';
		wp_mkdir_p( $this->dir . '/patterns' );
		add_filter(
			'pediment_seed_manifest',
			fn() => [ 'pages' => [ 'home' => [ 'title' => 'Home', 'pattern' => 'acme/home' ] ] ]
		);
		register_block_pattern( 'acme/home', [ 'title' => 'Home', 'content' => '<!-- wp:paragraph --><p>seeded</p><!-- /wp:paragraph -->' ] );
		add_filter( 'stylesheet_directory', fn() => $this->dir );
	}

	public function tear_down(): void {
		remove_all_filters( 'pediment_seed_manifest' );
		remove_all_filters( 'stylesheet_directory' );
		unregister_block_pattern( 'acme/home' );
		parent::tear_down();
	}

	public function test_adopt_writes_the_live_markup_to_the_pattern_file() {
		$ids = ( new Runner() )->run()->ids;
		wp_update_post( [ 'ID' => $ids['home|'], 'post_content' => '<!-- wp:paragraph --><p>client copy</p><!-- /wp:paragraph -->' ] );

		$result = ( new Adopter( new NullProvider() ) )->adopt( 'home' );

		$this->assertTrue( $result['written'] );
		$this->assertSame( $this->dir . '/patterns/home.php', $result['path'] );
		$contents = file_get_contents( $result['path'] );
		$this->assertStringContainsString( 'Slug: acme/home', $contents );
		$this->assertStringContainsString( 'client copy', $contents );
	}

	public function test_adopt_resets_the_hashes_so_the_page_is_no_longer_protected() {
		$ids = ( new Runner() )->run()->ids;
		$id  = $ids['home|'];
		wp_update_post( [ 'ID' => $id, 'post_content' => '<p>client copy</p>' ] );

		( new Adopter( new NullProvider() ) )->adopt( 'home' );

		$this->assertSame( ContentHash::forPost( $id ), get_post_meta( $id, Meta::HASH, true ) );
		$this->assertSame(
			ContentHash::compute( get_post( $id )->post_title, get_post( $id )->post_content ),
			get_post_meta( $id, Meta::SOURCE, true )
		);
	}

	public function test_dry_run_writes_no_file() {
		( new Runner() )->run();

		$result = ( new Adopter( new NullProvider() ) )->adopt( 'home', '', true );

		$this->assertFalse( $result['written'] );
		$this->assertFileDoesNotExist( $this->dir . '/patterns/home.php' );
	}

	public function test_an_unknown_key_is_an_error_not_a_silent_no_op() {
		$result = ( new Adopter( new NullProvider() ) )->adopt( 'ghost' );

		$this->assertNotEmpty( $result['errors'] );
		$this->assertStringContainsString( 'ghost', $result['errors'][0] );
	}

	public function test_an_entry_declared_with_literal_content_cannot_be_adopted() {
		remove_all_filters( 'pediment_seed_manifest' );
		add_filter( 'pediment_seed_manifest', static fn() => [ 'pages' => [ 'home' => [ 'title' => 'Home', 'content' => '<p>x</p>' ] ] ] );
		( new Runner() )->run();

		$result = ( new Adopter( new NullProvider() ) )->adopt( 'home' );

		$this->assertStringContainsString( 'pattern', $result['errors'][0] );
	}
}
```

- [ ] **Step 2: Run it and watch it fail**

Run: `... --filter AdopterTest`
Expected: FAIL — `Class "Pediment\Seeder\Adopter" not found`.

- [ ] **Step 3: Implement the Adopter**

```php
<?php
/**
 * The inverse of seeding: export a live entry's markup back into the theme's
 * pattern file and clear the "client edited this" state.
 *
 * @package Pediment
 */

declare(strict_types=1);

namespace Pediment\Seeder;

use Pediment\Language\LanguageProvider;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Adopter {
	public function __construct( private LanguageProvider $lang ) {}

	/** @return array{path:string,bytes:int,written:bool,errors:string[]} */
	public function adopt( string $seedKey, string $language = '', bool $dryRun = false ): array {
		$empty = [ 'path' => '', 'bytes' => 0, 'written' => false, 'errors' => [] ];

		// `init` already populated the per-request memo (PostTypes reads it on
		// every request), and an operator who just edited the manifest expects
		// this run to see the file as it is now.
		Manifest::resetCache();

		try {
			$manifest = Manifest::load();
		} catch ( ManifestError $e ) {
			return array_merge( $empty, [ 'errors' => [ $e->getMessage() ] ] );
		}
		if ( null === $manifest ) {
			return array_merge( $empty, [ 'errors' => [ 'No seed manifest found in the active theme.' ] ] );
		}

		$spec = $manifest->entries()[ $seedKey ] ?? null;
		if ( ! $spec instanceof EntrySpec ) {
			return array_merge( $empty, [ 'errors' => [ sprintf( 'Seed key "%s" is not declared in the manifest.', $seedKey ) ] ] );
		}
		if ( null === $spec->pattern ) {
			return array_merge(
				$empty,
				[ 'errors' => [ sprintf( '"%s" is declared with literal content; give it a `pattern` to adopt into.', $seedKey ) ] ]
			);
		}

		$actual = ( new StateReader( $this->lang ) )->read()[ $seedKey . '|' . $language ] ?? null;
		if ( ! $actual instanceof ActualEntry ) {
			return array_merge( $empty, [ 'errors' => [ sprintf( 'No seeded post carries the key "%s".', $seedKey ) ] ] );
		}

		$post = get_post( $actual->id );
		if ( ! $post instanceof \WP_Post ) {
			return array_merge( $empty, [ 'errors' => [ sprintf( 'Post %d disappeared.', $actual->id ) ] ] );
		}

		$slugParts = explode( '/', $spec->pattern );
		$file      = untrailingslashit( $manifest->baseDir() ) . '/patterns/' . end( $slugParts ) . '.php';
		$contents  = $this->render( $spec, (string) $post->post_content );

		if ( $dryRun ) {
			return [ 'path' => $file, 'bytes' => strlen( $contents ), 'written' => false, 'errors' => [] ];
		}

		if ( ! wp_mkdir_p( dirname( $file ) ) ) {
			return array_merge( $empty, [ 'errors' => [ sprintf( 'Cannot create %s.', dirname( $file ) ) ] ] );
		}
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- developer-side export, runs on a dev machine.
		if ( false === file_put_contents( $file, $contents ) ) {
			return array_merge( $empty, [ 'errors' => [ sprintf( 'Cannot write %s.', $file ) ] ] );
		}

		// The live row is now the source of truth in git too, so it is no longer
		// "edited" — the next seed will treat it as up to date.
		update_post_meta( $actual->id, Meta::HASH, ContentHash::forPost( $actual->id ) );
		update_post_meta( $actual->id, Meta::SOURCE, ContentHash::compute( (string) $post->post_title, (string) $post->post_content ) );

		return [ 'path' => $file, 'bytes' => strlen( $contents ), 'written' => true, 'errors' => [] ];
	}

	private function render( EntrySpec $spec, string $markup ): string {
		return "<?php\n"
			. "/**\n"
			. ' * Title: ' . $spec->title . "\n"
			. ' * Slug: ' . $spec->pattern . "\n"
			. " * Categories: pediment\n"
			. " * Inserter: no\n"
			. " *\n"
			. " * Adopted from the live site by `wp pediment adopt`. Edit here, then re-seed.\n"
			. " *\n"
			. " * @package Pediment\n"
			. " */\n"
			. "\n"
			. "?>\n"
			. $markup . "\n";
	}
}
```

- [ ] **Step 4: Add the command**

```php
<?php
/**
 * WP-CLI: `wp pediment adopt`.
 *
 * @package Pediment
 */

declare(strict_types=1);

namespace Pediment\Cli;

use Pediment\Language\LanguageRegistry;
use Pediment\Seeder\Adopter;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Exports a live page's block markup back into its pattern file.
 */
final class AdoptCommand {
	/**
	 * ## OPTIONS
	 *
	 * <key>
	 * : The seed key to adopt, as declared in the manifest.
	 *
	 * [--language=<code>]
	 * : Language to adopt. Defaults to the site's default language.
	 *
	 * [--dry-run]
	 * : Print the target file and size without writing.
	 *
	 * ## EXAMPLES
	 *
	 *     wp pediment adopt home --dry-run
	 *     wp pediment adopt guide/faq
	 *
	 * @when after_wp_load
	 *
	 * @param array<int,string>    $args      Positional args.
	 * @param array<string,string> $assocArgs Associative args.
	 */
	public function __invoke( array $args, array $assocArgs ): void {
		$provider = LanguageRegistry::provider();
		$language = (string) ( $assocArgs['language'] ?? $provider->defaultLanguage() );

		$result = ( new Adopter( $provider ) )->adopt( (string) ( $args[0] ?? '' ), $language, isset( $assocArgs['dry-run'] ) );

		foreach ( $result['errors'] as $error ) {
			\WP_CLI::warning( $error );
		}
		if ( [] !== $result['errors'] ) {
			\WP_CLI::error( 'Nothing was adopted.' );
		}

		\WP_CLI::success(
			sprintf(
				'%s %s (%d bytes).',
				$result['written'] ? 'Wrote' : 'Would write',
				$result['path'],
				$result['bytes']
			)
		);
	}
}
```

In `plugin/plugin.php`'s WP-CLI block:

```php
	require_once __DIR__ . '/wp-cli/AdoptCommand.php';
	\WP_CLI::add_command( 'pediment adopt', \Pediment\Cli\AdoptCommand::class );
```

- [ ] **Step 5: Run it green**

Run: `... --filter AdopterTest`
Expected: PASS (5 tests).

- [ ] **Step 6: Commit**

```bash
git add plugin/src/Seeder/Adopter.php plugin/wp-cli/AdoptCommand.php plugin/plugin.php plugin/tests/phpunit/Seeder/AdopterTest.php
git commit -m "feat(seeder): add wp pediment adopt to export live pages back to git"
```

---

### Task 16: The fixture theme seeds itself

**Files:**
- Create: `tests/fixtures/client-theme/seed/manifest.php`, `tests/fixtures/client-theme/seed/media/logo.svg`, `tests/fixtures/client-theme/patterns/about.php`, `.../patterns/sample-post.php`
- Modify: `plugin/tests/e2e/global-setup.ts`
- Delete: `plugin/tests/e2e/fixtures.php`
- Test: `plugin/tests/e2e/seeding.spec.ts` (new)

**Interfaces:**
- Consumes: `wp pediment seed` (Task 13).
- Produces: the e2e site's content comes from a manifest, exercising the real engine on every CI run. The fixture manifest must reproduce exactly what the existing specs assert: pages `home` (front page, `pediment/pediment-landing`), `about`, `contact`, `blog` (posts page), `mega-demo` (`pediment/mega-menu-header`); six posts across the `insights` / `briefings` / `notes` categories; a `primary` nav with About / Blog / Contact; a site logo.

- [ ] **Step 1: Read what the suite actually depends on**

```bash
grep -rn "about\|contact\|mega-demo\|sample-insight\|Header Navigation\|custom_logo" plugin/tests/e2e/*.spec.ts | sort
```

Write the list down. Anything asserted there must appear in the manifest; anything missing is a red e2e run, which is how this task fails.

- [ ] **Step 2: Write the fixture manifest**

```php
<?php
/**
 * Seed manifest for the e2e fixture client theme.
 *
 * This is the reference example of the format AND the content the Playwright
 * suite asserts against — it replaced tests/e2e/fixtures.php so every CI run
 * exercises the real seeding engine.
 *
 * @package Pediment
 */

return array(
	'version' => 1,
	'site'    => array( 'logo' => 'logo' ),
	'media'   => array(
		'logo' => array( 'file' => 'seed/media/logo.svg', 'title' => 'Pediment e2e logo' ),
	),
	'pages'   => array(
		'home'      => array( 'title' => 'Home', 'pattern' => 'pediment/pediment-landing', 'front_page' => true ),
		'about'     => array( 'title' => 'About', 'pattern' => 'pediment-fixture/about' ),
		'contact'   => array( 'title' => 'Contact', 'content' => '' ),
		'blog'      => array( 'title' => 'Blog', 'content' => '', 'posts_page' => true ),
		'mega-demo' => array( 'title' => 'Mega Menu Demo', 'pattern' => 'pediment/mega-menu-header' ),
	),
	'posts'   => array(
		'sample-insight-one'  => array( 'title' => 'A practical insight on getting started', 'pattern' => 'pediment-fixture/sample-post', 'terms' => array( 'category' => array( 'insights' ) ) ),
		'sample-insight-two'  => array( 'title' => 'What good looks like, in plain terms', 'pattern' => 'pediment-fixture/sample-post', 'terms' => array( 'category' => array( 'insights' ) ) ),
		'sample-briefing-one' => array( 'title' => 'A short briefing on a common decision', 'pattern' => 'pediment-fixture/sample-post', 'terms' => array( 'category' => array( 'briefings' ) ) ),
		'sample-briefing-two' => array( 'title' => 'Trade-offs worth weighing early', 'pattern' => 'pediment-fixture/sample-post', 'terms' => array( 'category' => array( 'briefings' ) ) ),
		'sample-note-one'     => array( 'title' => 'A quick note on process', 'pattern' => 'pediment-fixture/sample-post', 'terms' => array( 'category' => array( 'notes' ) ) ),
		'sample-note-two'     => array( 'title' => 'A quick note on outcomes', 'pattern' => 'pediment-fixture/sample-post', 'terms' => array( 'category' => array( 'notes' ) ) ),
	),
	'navs'    => array(
		'primary' => array(
			'title' => 'Header Navigation',
			'items' => array(
				array( 'entry' => 'about', 'label' => 'About' ),
				array( 'entry' => 'blog', 'label' => 'Blog' ),
				array( 'entry' => 'contact', 'label' => 'Contact' ),
			),
		),
	),
);
```

`tests/fixtures/client-theme/patterns/about.php` and `sample-post.php` carry the markup the old `fixtures.php` inlined, in the standard pattern-file shape (`Title:` / `Slug: pediment-fixture/<name>` / `Inserter: no` header, then markup). Core auto-registers a theme's `patterns/` directory, so no registration code is needed. Copy the SVG from the existing `tests/fixtures/uploads/2026/07/pediment-e2e-logo.svg`.

- [ ] **Step 3: Swap global-setup over**

In `plugin/tests/e2e/global-setup.ts`, replace the `eval-file` line and its comment with:

```ts
	// Content comes from the fixture theme's seed manifest, applied by the real
	// engine — the suite exercises `wp pediment seed` on every run.
	wp( `pediment seed` );
```

- [ ] **Step 4: Delete the old fixtures**

```bash
git rm plugin/tests/e2e/fixtures.php
```

- [ ] **Step 5: Add the seeding e2e spec**

```ts
// plugin/tests/e2e/seeding.spec.ts
import { test, expect } from '@playwright/test';
import { execSync } from 'node:child_process';

const WP_ENV_CWD = process.env.WP_ENV_CWD || process.cwd();
const wp = ( cmd: string ) =>
	execSync( `npx wp-env run cli wp ${ cmd }`, { cwd: WP_ENV_CWD, stdio: 'pipe' } )
		.toString()
		.trim();

test.describe( 'seeding engine', () => {
	test( 'a re-seed plans no writes', async () => {
		const plan = wp( `pediment seed --dry-run` );
		expect( plan ).toContain( '0 to write' );
	} );

	test( 'an editor change survives the next seed', async () => {
		const id = wp( `post list --post_type=page --name=about --field=ID` ).trim();
		wp( `post update ${ id } --post_content='<!-- wp:paragraph --><p>client edit</p><!-- /wp:paragraph -->'` );

		const plan = wp( `pediment seed --dry-run` );
		expect( plan ).toContain( 'protected' );

		wp( `pediment seed` );
		expect( wp( `post get ${ id } --field=post_content` ) ).toContain( 'client edit' );

		// Restore the fixture state for the rest of the suite.
		wp( `post meta delete ${ id } _pediment_seed_hash` );
		wp( `post meta delete ${ id } _pediment_seed_source` );
		wp( `pediment seed` );
	} );

	test( 'a slug change is reverted', async () => {
		const id = wp( `post list --post_type=page --name=contact --field=ID` ).trim();
		wp( `post update ${ id } --post_name=kontakt` );

		wp( `pediment seed` );

		expect( wp( `post get ${ id } --field=post_name` ) ).toBe( 'contact' );
	} );
} );
```

Note: the second test's restore path deliberately deletes both hashes and re-seeds — with the stored hash gone the entry is "edited" again, so also delete the page's content first (`wp post update <id> --post_content=''`) before the final seed if the About assertions elsewhere fail.

- [ ] **Step 6: Run the suite**

```bash
npm run env:start
cd plugin && npm run e2e && cd ..
```

Expected: PASS. If a spec fails on content that used to come from `fixtures.php`, the manifest is missing it — add it there, not back into a fixtures file.

- [ ] **Step 7: Commit**

```bash
git add tests/fixtures/client-theme plugin/tests/e2e/global-setup.ts plugin/tests/e2e/seeding.spec.ts
git rm --cached plugin/tests/e2e/fixtures.php 2>/dev/null; git add -u plugin/tests/e2e
git commit -m "test(e2e): seed the fixture site from a manifest via wp pediment seed"
```

---

### Task 17: Documentation, traps, and the gated push

**Files:**
- Create: `docs/seeding.md`
- Modify: `docs/WORDPRESS_TRAPS.md`, `docs/STANDARDS.md`, `plugin/README.md`, `docs/SESSION_LOG.md`, `AGENTS.md`

**Interfaces:**
- Consumes: everything above.
- Produces: the manifest reference a client-theme author reads, and the trap entries the next session needs.

- [ ] **Step 1: Write `docs/seeding.md`**

Cover, with real snippets taken from `tests/fixtures/client-theme/seed/manifest.php`: the manifest format (every section and key, defaults, validation rules); the arbitration contract in one paragraph (git owns structure, the database owns content once edited, the hash decides); what "structure" means concretely (existence, slug, nesting, front/posts page, nav membership, CPTs, media presence); `wp pediment seed --dry-run` and how to read the plan; `wp pediment adopt`; the wp-admin tab; and the two failure modes with their fixes (duplicate seed key, verification problems).

- [ ] **Step 2: Add three trap entries to `docs/WORDPRESS_TRAPS.md`**

Follow the file's four-line shape. Titles:
- `Hashing seeded content before the write disables all future updates` — symptom: every page reports "protected" on the first re-seed; cause: WP normalizes markup on write, so the input never matches the row; fix: `ContentHash::forPost()` after the write, plus `_pediment_seed_source` for the git side; catch it early: `ApplierTest::test_reseeding_unchanged_content_is_a_no_op`.
- `KSES is active under WP-CLI and mangles block-comment JSON` — symptom: seeded pages render as raw markup or fatal in `align.php`; cause: no current user means `kses_init_filters()` applies; fix: `Applier::suspendKses()` around writes plus `wp_slash()`; catch it early: `ApplierTest::test_block_attribute_json_survives_the_write`.
- `WordPress uniquifies a colliding post slug and the seeder looks successful` — symptom: `/about-2/`; cause: `wp_unique_post_slug()`; fix: the Verifier compares `post_name` to the manifest and reports; catch it early: `VerifierTest::test_a_uniquified_slug_is_a_problem`.

- [ ] **Step 3: Update the standing docs**

- `docs/STANDARDS.md` → under Tests, add that seeding changes require a `plugin/tests/phpunit/Seeder/` test and that `wp pediment seed --dry-run` must plan zero writes on an unchanged manifest.
- `plugin/README.md` → a "Seeding" section pointing at `docs/seeding.md` and listing the two commands.
- `AGENTS.md` → note that content seeding is declarative now: edit `seed/manifest.php` plus pattern files, never write pages with `wp post create`.
- `docs/SESSION_LOG.md` → one entry recording step 3 with the shipped surface.

- [ ] **Step 4: Full verification**

```bash
npx wp-env run tests-wordpress --env-cwd=wp-content/plugins/pediment-ai ./vendor/bin/phpunit
cd plugin && composer lint && npm run lint:js && cd ..
node tools/lint-colors.mjs && node tools/lint-blocks.mjs
cd plugin && npm run e2e && cd ..
npx wp-env run cli wp pediment seed --dry-run
```

Expected: PHPUnit green, phpcs no errors, lint scripts clean, e2e green, and the dry run reporting `0 to write`. Paste the dry-run output into the final report — that output *is* the deliverable's proof.

- [ ] **Step 5: Commit the docs**

```bash
git add docs/seeding.md docs/WORDPRESS_TRAPS.md docs/STANDARDS.md docs/SESSION_LOG.md AGENTS.md plugin/README.md
git commit -m "docs(seeder): document the manifest format, commands, and new traps"
```

- [ ] **Step 6: Ask before pushing**

Report to the user: the commit list, the dry-run output, and the suite results. **Do not push.** On explicit approval:

```bash
git fetch origin && git rebase origin/main
npx wp-env run tests-wordpress --env-cwd=wp-content/plugins/pediment-ai ./vendor/bin/phpunit
git push origin HEAD:main
```

Then watch CI (`/check-ci`) and report. release-please opens the 3.1.0 release PR; merging it is a separate, user-gated decision.

---

## Coverage notes

Spec §4.2 requirement → task:

| Requirement | Task |
|---|---|
| Declarative manifest, pattern files supply content | 4, 5 |
| Phase 1 desired state (manifest × languages) | 6 |
| Phase 2 actual state, queried by key, unscoped by language | 6 |
| Phase 3 diff into a plan | 7 |
| Phase 4 apply, language assigned in the same write | 8 |
| Phase 5 verify per language, fail loudly | 12 |
| Identity is `_pediment_seed_key`, never slug | 6, 7 |
| `_pediment_seed_hash` arbitrates content | 7, 8 |
| Hash computed from the persisted row | 2, 8 |
| `post_title` is content; slug is structure, reverted | 7, 8 |
| Structure the seeder owns (existence, slug, nesting, front/posts page, nav, CPTs, media) | 8, 9, 10, 11 |
| `--dry-run` prints the plan | 13 |
| Rewrite rules flush once, at the end | 12 |
| One code path for WP-CLI and wp-admin, limits lifted in admin | 13, 14 |
| `adopt` exports a live page back to git and resets the hash | 15 |
| Step-6 property: missing hash ⇒ treat as edited | 7 |

Deferred to step 4 by design: the Polylang adapter, per-language pattern files (`patterns/<slug>.<lang>.php`), missing-translation reporting, generated `wpml-config.xml`, and translation-group linking (`linkTranslations()` exists and is a no-op).
