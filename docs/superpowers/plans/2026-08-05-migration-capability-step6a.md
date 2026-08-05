# Migration Capability — Claim, Header Ownership, Client Blocks (Step 6a) Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Give the engine everything a pre-existing WordPress site needs before it can be seeded — identity backfill (`wp pediment claim`), a client-ownable header, and client blocks in the scaffolded theme — implementing the first half of migration step 6 per `docs/superpowers/specs/2026-08-05-migration-step6-design.md`.

**Architecture:** `Pediment\Seeder\Claimer` walks the manifest's declared identity (never its content) and matches each `(key, language)` that no row already carries against unkeyed posts by post type, slug, parent and language, writing exactly one meta key — `_pediment_seed_key` — and never a hash, so the next seed still treats every claimed row as client-edited. It plans and applies like the rest of the engine, renders through its own `Reporter::claimText()`, and is reachable from WP-CLI and from the wp-admin Seeding tab, which is the only path that exists on admin-only hosting. Separately, the plugin's header bootstrap learns to take its initial markup from a theme-registered `<stylesheet>/header` pattern, and the client template gains an optional blocks build behind a scaffolder flag.

**Tech Stack:** PHP 8.1, WordPress 6.9, WP-CLI, PHPUnit 9.6 (the WP integration suite plus the Polylang suite), Node 20 (`node:test`), GitHub Actions composite actions and reusable workflows, `@wordpress/scripts`, `@wordpress/env`.

## Global Constraints

- **Never push without explicit user approval.** All work is local until the gated push in Task 15.
- Work stays on the current branch in this Conductor workspace. No new branches or worktrees — the workspace *is* the isolation.
- **Nothing existing is removed or renamed**, so this ships as a **minor** — conventional `feat:`/`fix:`/`docs:`/`test:`/`ci:` commits only, no `!`, no `Release-As:` footer. Version files belong to release-please; never hand-bump.
- **Never rename stored data.** `_pediment_seed_key`, `_pediment_seed_hash`, `_pediment_seed_source` keep their exact names. Options (`pediment_ai_*`), tables (`wp_pediment_ai_*`) and transients keep theirs.
- **A claim writes `Meta::KEY` and nothing else.** No hashes, no post fields, no language tags, no term assignments. This is the property that makes it safe against a live site (spec decision 5).
- **A claim never touches the trash.** Statuses considered are exactly `publish`, `draft`, `pending`, `private`, `future`.
- **A claim never takes a post that already carries any seed key**, so it cannot move content between manifest keys.
- **Never look content up with the `name` query var.** `WP_Query::parse_query()` treats `name` as singular, and `get_posts()` skips `tax_query` on singular queries — the bug that made the header part match across themes (see `docs/BACKLOG.md`). Use `post_name__in` everywhere.
- **The seeder never sets `permalink_structure`** and never hard-flushes. Unchanged from steps 3 and 4; the claim path flushes nothing at all.
- **A monolingual site must behave exactly as it does today.** `NullProvider` stays the default; the 545-test suite plus the Polylang suite are the regression gate.
- New PHP lives under `Pediment\Seeder\` (`plugin/src/Seeder/`) and `Pediment\Cli\` (`plugin/wp-cli/`), both already PSR-4 mapped. Procedural admin glue lives in `plugin/inc/` as `pediment_*` snake_case functions.
- **PHPUnit runs in wp-env**, never against a bare PHP: `npx wp-env run tests-wordpress --env-cwd=wp-content/plugins/pediment-ai ./vendor/bin/phpunit`. The in-container directory is still `pediment-ai` — that is the mount name, not a rename target.
- Commit messages: conventional summary of at most 60 characters, with the trailer `Co-Authored-By: Claude Opus 5 (1M context) <noreply@anthropic.com>`. Stage files explicitly by name; never `git add -A`.

---

## File Structure

### Create

- `plugin/src/Seeder/Claimer.php` — matching and application. The whole claim policy lives here.
- `plugin/wp-cli/ClaimCommand.php` — `wp pediment claim`.
- `plugin/tests/phpunit/Seeder/ClaimerTest.php` — monolingual matching, safety rules, idempotency.
- `plugin/tests/phpunit/Seeder/ClaimReporterTest.php` — the exact bytes of a claim report.
- `plugin/tests/polylang/ClaimerLanguageTest.php` — per-language matching and untagged posts.
- `client-template/functions.php` — client block registration and stylesheet enqueue (shipped in the template, pruned by the scaffolder when blocks were not requested).
- `client-template/src/blocks/example-notice/{block.json,index.js,render.php,style.scss}` — one worked client block.

### Modify

- `plugin/src/Seeder/PlanItem.php` — three claim actions.
- `plugin/src/Seeder/Reporter.php` — `claimText()`.
- `plugin/src/Seeder/NavSeeder.php:254` — `slugFor()` becomes public so the Claimer can reuse the derivation.
- `plugin/plugin.php:108-112` — register the new command.
- `plugin/inc/seeding-admin.php` — two more buttons and their handler branch.
- `plugin/inc/bootstrap.php:35-102` — initial header markup from a `<stylesheet>/header` pattern.
- `plugin/tests/phpunit/Bootstrap/` — header pattern coverage (add to the existing bootstrap test file).
- `client-kit/scripts/scaffold.mjs` — `--with-blocks`, and pruning when absent.
- `client-kit/tests/scaffold.test.mjs` — both branches.
- `client-kit/skills/start/SKILL.md` — the one question that decides the flag.
- `client-template/package.json`, `client-template/.gitignore` — build scripts and `build/`.
- `.github/actions/seed-check/action.yml` — build blocks when `src/blocks/` exists.
- `.github/workflows/client-release.yml` — same, plus keep `src/` out of the zip.
- `docs/seeding.md`, `docs/client-sites.md`, `docs/BACKLOG.md`, `docs/SESSION_LOG.md`.

### Interfaces

```php
// plugin/src/Seeder/PlanItem.php — new actions, same class
public const CLAIM     = 'claim';      // an unkeyed row will receive this key
public const NO_MATCH  = 'no-match';   // nothing to claim; the next seed will create it
public const AMBIGUOUS = 'ambiguous';  // more than one candidate; nothing is written

// plugin/src/Seeder/Claimer.php
final class Claimer {
    public function __construct( private LanguageProvider $lang ) {}
    /** @param array<string,ActualEntry> $actual Keyed "<seedKey>|<language>". */
    public function plan( Manifest $manifest, array $actual ): Plan;
    /** @return array{claimed:int,errors:string[]} */
    public function apply( Plan $plan ): array;
}

// plugin/src/Seeder/Reporter.php
public static function claimText( Plan $plan, bool $applied, string $manifestPath, array $errors = [] ): string;

// plugin/src/Seeder/NavSeeder.php — visibility change only, signature unchanged
public function slugFor( NavSpec $spec, string $language ): string;
```

---

## Part A — the claim path

### Task 1: Claim actions and monolingual entry matching

**Files:**
- Modify: `plugin/src/Seeder/PlanItem.php`
- Create: `plugin/src/Seeder/Claimer.php`
- Test: `plugin/tests/phpunit/Seeder/ClaimerTest.php`

**Interfaces:**
- Consumes: `Manifest::fromArray()`, `Manifest::entriesInDependencyOrder()`, `EntrySpec::slugFor()`, `Meta::KEY`, `Plan`, `PlanItem`, `NullProvider`.
- Produces: `PlanItem::CLAIM`, `PlanItem::NO_MATCH`, `PlanItem::AMBIGUOUS`, `Claimer::plan()`.

- [ ] **Step 1: Write the failing test**

Create `plugin/tests/phpunit/Seeder/ClaimerTest.php`:

```php
<?php
// plugin/tests/phpunit/Seeder/ClaimerTest.php

use Pediment\Language\NullProvider;
use Pediment\Seeder\Claimer;
use Pediment\Seeder\Manifest;
use Pediment\Seeder\Meta;
use Pediment\Seeder\PlanItem;

class ClaimerTest extends WP_UnitTestCase {

	private function manifest( array $pages ): Manifest {
		return Manifest::fromArray( [ 'pages' => $pages ], '/tmp/theme' );
	}

	private function page( string $slug, array $args = [] ): int {
		return self::factory()->post->create(
			array_merge(
				[ 'post_type' => 'page', 'post_title' => 'Legacy', 'post_name' => $slug, 'post_status' => 'publish' ],
				$args
			)
		);
	}

	/** @return array<string,PlanItem> mapKey => item */
	private function byMapKey( \Pediment\Seeder\Plan $plan ): array {
		$out = [];
		foreach ( $plan->items() as $item ) {
			$out[ $item->mapKey() ] = $item;
		}
		return $out;
	}

	public function test_an_unkeyed_page_matching_slug_and_type_is_claimed() {
		$id       = $this->page( 'about' );
		$manifest = $this->manifest( [ 'about' => [ 'title' => 'About', 'content' => '<p>a</p>' ] ] );

		$plan = ( new Claimer( new NullProvider() ) )->plan( $manifest, [] );

		$item = $this->byMapKey( $plan )['about|'];
		$this->assertSame( PlanItem::CLAIM, $item->action );
		$this->assertSame( $id, $item->postId );
		$this->assertTrue( $item->writes() );
	}

	public function test_a_page_carrying_another_seed_key_is_never_taken() {
		$id = $this->page( 'about' );
		update_post_meta( $id, Meta::KEY, 'legacy-about' );
		$manifest = $this->manifest( [ 'about' => [ 'title' => 'About', 'content' => '<p>a</p>' ] ] );

		$plan = ( new Claimer( new NullProvider() ) )->plan( $manifest, [] );

		$item = $this->byMapKey( $plan )['about|'];
		$this->assertSame( PlanItem::NO_MATCH, $item->action );
		$this->assertSame( 0, $item->postId );
	}

	public function test_a_trashed_page_is_never_claimed() {
		$this->page( 'about', [ 'post_status' => 'trash' ] );
		$manifest = $this->manifest( [ 'about' => [ 'title' => 'About', 'content' => '<p>a</p>' ] ] );

		$plan = ( new Claimer( new NullProvider() ) )->plan( $manifest, [] );

		$this->assertSame( PlanItem::NO_MATCH, $this->byMapKey( $plan )['about|']->action );
	}

	public function test_two_candidates_are_reported_and_nothing_is_planned() {
		$first  = $this->page( 'about', [ 'post_type' => 'page' ] );
		$second = self::factory()->post->create(
			[ 'post_type' => 'page', 'post_name' => 'about', 'post_status' => 'draft', 'post_title' => 'About draft' ]
		);
		$manifest = $this->manifest( [ 'about' => [ 'title' => 'About', 'content' => '<p>a</p>' ] ] );

		$item = $this->byMapKey( ( new Claimer( new NullProvider() ) )->plan( $manifest, [] ) )['about|'];

		$this->assertSame( PlanItem::AMBIGUOUS, $item->action );
		$this->assertFalse( $item->writes() );
		$this->assertStringContainsString( (string) $first, $item->note );
		$this->assertStringContainsString( (string) $second, $item->note );
	}
}
```

- [ ] **Step 2: Run the test to verify it fails**

Run: `npx wp-env run tests-wordpress --env-cwd=wp-content/plugins/pediment-ai ./vendor/bin/phpunit --filter ClaimerTest`
Expected: FAIL with `Class "Pediment\Seeder\Claimer" not found`.

- [ ] **Step 3: Add the three actions**

In `plugin/src/Seeder/PlanItem.php`, after the `ORPHAN` constant:

```php
	public const CLAIM     = 'claim'; // An unkeyed row will receive this seed key.
	public const NO_MATCH  = 'no-match'; // Nothing to claim; the next seed creates it.
	public const AMBIGUOUS = 'ambiguous'; // More than one candidate; nothing is written.
```

- [ ] **Step 4: Write the Claimer**

Create `plugin/src/Seeder/Claimer.php`:

```php
<?php
/**
 * Backfills seed identity onto content that predates the engine.
 *
 * The engine resolves actual state only by `_pediment_seed_key`
 * (StateReader), which is what keeps it off slug lookups — and what makes a
 * site seeded by anything else invisible to it. Running a first seed against
 * such a site plans a CREATE for every entry and duplicates the whole site.
 *
 * A claim is the one-time bridge: it matches by the things a legacy row and a
 * manifest entry can still agree on, and writes exactly one meta key. It never
 * writes a hash, so every claimed row stays protected under the Differ's rule
 * 2 (missing hash = treat as edited); bringing a page under git's control is
 * still an explicit `wp pediment adopt`.
 *
 * @package Pediment
 */

declare(strict_types=1);

namespace Pediment\Seeder;

use Pediment\Language\LanguageProvider;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Claimer {
	/** Statuses a claim considers. Trash is deliberately absent. */
	private const STATUSES = [ 'publish', 'draft', 'pending', 'private', 'future' ];

	public function __construct( private LanguageProvider $lang ) {}

	/**
	 * @param array<string,ActualEntry> $actual Keyed "<seedKey>|<language>", from StateReader::read().
	 */
	public function plan( Manifest $manifest, array $actual ): Plan {
		$items    = [];
		$resolved = [];
		foreach ( $actual as $mapKey => $entry ) {
			$resolved[ $mapKey ] = $entry->id;
		}

		$default = $this->lang->defaultLanguage();

		foreach ( $this->lang->languages() as $language ) {
			foreach ( $manifest->entriesInDependencyOrder() as $spec ) {
				$mapKey = $spec->key . '|' . $language;
				if ( isset( $resolved[ $mapKey ] ) ) {
					continue; // Already carries the key: nothing to claim.
				}

				$item = $this->planOne( $spec, $language, $default, $resolved );
				if ( PlanItem::CLAIM === $item->action ) {
					$resolved[ $mapKey ] = $item->postId;
				}
				$items[] = $item;
			}
		}

		return new Plan( $items );
	}

	/** @param array<string,int> $resolved mapKey => post ID, including rows claimed earlier in this run. */
	private function planOne( EntrySpec $spec, string $language, string $default, array $resolved ): PlanItem {
		$slug = $spec->slugFor( $language, $default );

		$parentId = 0;
		if ( null !== $spec->parent ) {
			$parentMapKey = $spec->parent . '|' . $language;
			if ( ! isset( $resolved[ $parentMapKey ] ) ) {
				return $this->noMatch(
					$spec,
					$language,
					sprintf(
						'parent "%s" is not resolved in this language, so a nested match cannot be verified.',
						$spec->parent
					)
				);
			}
			$parentId = $resolved[ $parentMapKey ];
		}

		$candidates = $this->candidates( $spec->postType, $slug, $parentId, $language, $default );

		if ( [] === $candidates ) {
			return $this->noMatch(
				$spec,
				$language,
				sprintf( 'no unclaimed %s with slug "%s" — the next seed will create it.', $spec->postType, $slug )
			);
		}

		if ( count( $candidates ) > 1 ) {
			return new PlanItem(
				PlanItem::AMBIGUOUS,
				PlanItem::KIND_ENTRY,
				$spec->key,
				$language,
				0,
				[],
				[],
				sprintf(
					'%d unclaimed %s posts share the slug "%s" (IDs %s) — claim nothing until one is deleted or re-slugged.',
					count( $candidates ),
					$spec->postType,
					$slug,
					implode( ', ', $candidates )
				)
			);
		}

		$postId = (int) $candidates[0];

		return new PlanItem(
			PlanItem::CLAIM,
			PlanItem::KIND_ENTRY,
			$spec->key,
			$language,
			$postId,
			[ 'seed_key' => [ 'from' => null, 'to' => $spec->key ] ],
			[],
			sprintf( '%s "%s" (ID %d)', $spec->postType, $slug, $postId )
		);
	}

	private function noMatch( EntrySpec $spec, string $language, string $note ): PlanItem {
		return new PlanItem( PlanItem::NO_MATCH, PlanItem::KIND_ENTRY, $spec->key, $language, 0, [], [], $note );
	}

	/**
	 * Unkeyed posts of this type and slug, in this language, under this parent.
	 *
	 * `post_name__in`, never `name`: the `name` query var makes WP_Query
	 * singular, and get_posts() skips tax_query on singular queries — which is
	 * exactly how the header template part matched across themes.
	 *
	 * @return int[]
	 */
	private function candidates( string $postType, string $slug, int $parentId, string $language, string $default ): array {
		$args = $this->lang->unscopedQuery(
			[
				'post_type'      => $postType,
				'post_name__in'  => [ $slug ],
				'post_status'    => self::STATUSES,
				'posts_per_page' => -1,
				'orderby'        => 'ID',
				'order'          => 'ASC',
				'no_found_rows'  => true,
			]
		);

		$out = [];
		foreach ( get_posts( $args ) as $post ) {
			$id = (int) $post->ID;

			if ( '' !== (string) get_post_meta( $id, Meta::KEY, true ) ) {
				continue; // Belongs to another key — never stolen.
			}
			if ( (int) $post->post_parent !== $parentId ) {
				continue; // A same-slug page nested elsewhere is a different page.
			}
			if ( ! $this->languageMatches( $id, $language, $default ) ) {
				continue;
			}

			$out[] = $id;
		}

		return $out;
	}

	/**
	 * A post's language must equal the one being claimed for — except that a
	 * post carrying no language at all is a candidate for the default language
	 * only. That is the monolingual-site-adopting-Polylang case, and claiming
	 * it into a non-default language would silently move a page between
	 * languages.
	 */
	private function languageMatches( int $postId, string $language, string $default ): bool {
		if ( '' === $language ) {
			return true; // Monolingual: NullProvider reports one empty language.
		}
		if ( ! $this->lang->hasLanguage( $postId ) ) {
			return $language === $default;
		}
		return $this->lang->translationOf( $postId, $language ) === $postId;
	}

	/** @return array{claimed:int,errors:string[]} */
	public function apply( Plan $plan ): array {
		$claimed = 0;
		$errors  = [];

		foreach ( $plan->items() as $item ) {
			if ( PlanItem::CLAIM !== $item->action || $item->postId <= 0 ) {
				continue;
			}
			if ( '' !== (string) get_post_meta( $item->postId, Meta::KEY, true ) ) {
				// Something claimed it between plan and apply. Refusing is the
				// only safe answer: the alternative is two keys racing for one row.
				$errors[] = sprintf(
					'%s: post %d already carries a seed key — nothing was written for this entry.',
					$item->mapKey(),
					$item->postId
				);
				continue;
			}

			update_post_meta( $item->postId, Meta::KEY, $item->key );
			++$claimed;
		}

		return [
			'claimed' => $claimed,
			'errors'  => $errors,
		];
	}
}
```

- [ ] **Step 5: Run the test to verify it passes**

Run: `npx wp-env run tests-wordpress --env-cwd=wp-content/plugins/pediment-ai ./vendor/bin/phpunit --filter ClaimerTest`
Expected: PASS, 4 tests.

- [ ] **Step 6: Commit**

```bash
git add plugin/src/Seeder/Claimer.php plugin/src/Seeder/PlanItem.php plugin/tests/phpunit/Seeder/ClaimerTest.php
git commit -m "feat(seeder): match unkeyed content for identity claiming"
```

---

### Task 2: Parent-aware matching and apply semantics

**Files:**
- Modify: `plugin/tests/phpunit/Seeder/ClaimerTest.php`
- Modify: `plugin/src/Seeder/Claimer.php` (only if a test fails)

**Interfaces:**
- Consumes: `Claimer::plan()`, `Claimer::apply()` from Task 1.
- Produces: nothing new — this task proves the rules the cutover depends on.

- [ ] **Step 1: Write the failing tests**

Append to `plugin/tests/phpunit/Seeder/ClaimerTest.php`, inside the class:

```php
	public function test_a_child_is_matched_under_its_claimed_parent() {
		$parent = $this->page( 'guide' );
		$right  = $this->page( 'faq', [ 'post_parent' => $parent ] );
		$wrong  = $this->page( 'faq' ); // Same slug, top level.

		$manifest = $this->manifest(
			[
				'guide'     => [ 'title' => 'Guide', 'content' => '<p>g</p>' ],
				'guide/faq' => [ 'title' => 'FAQ', 'slug' => 'faq', 'parent' => 'guide', 'content' => '<p>f</p>' ],
			]
		);

		$items = $this->byMapKey( ( new Claimer( new NullProvider() ) )->plan( $manifest, [] ) );

		$this->assertSame( $parent, $items['guide|']->postId );
		$this->assertSame( PlanItem::CLAIM, $items['guide/faq|']->action );
		$this->assertSame( $right, $items['guide/faq|']->postId );
		$this->assertNotSame( $wrong, $items['guide/faq|']->postId );
	}

	public function test_a_top_level_entry_does_not_match_a_nested_page() {
		$parent = $this->page( 'guide' );
		$this->page( 'about', [ 'post_parent' => $parent ] );

		$manifest = $this->manifest( [ 'about' => [ 'title' => 'About', 'content' => '<p>a</p>' ] ] );

		$this->assertSame(
			PlanItem::NO_MATCH,
			$this->byMapKey( ( new Claimer( new NullProvider() ) )->plan( $manifest, [] ) )['about|']->action
		);
	}

	public function test_apply_writes_only_the_key_and_is_idempotent() {
		$id       = $this->page( 'about', [ 'post_content' => 'live copy', 'post_title' => 'Live title' ] );
		$manifest = $this->manifest( [ 'about' => [ 'title' => 'About', 'content' => '<p>a</p>' ] ] );
		$claimer  = new Claimer( new NullProvider() );

		$first = $claimer->apply( $claimer->plan( $manifest, [] ) );

		$this->assertSame( 1, $first['claimed'] );
		$this->assertSame( [], $first['errors'] );
		$this->assertSame( 'about', get_post_meta( $id, Meta::KEY, true ) );
		$this->assertSame( '', get_post_meta( $id, Meta::HASH, true ) );
		$this->assertSame( '', get_post_meta( $id, Meta::SOURCE, true ) );
		$this->assertSame( 'live copy', get_post( $id )->post_content );
		$this->assertSame( 'Live title', get_post( $id )->post_title );

		// A second run sees the row in actual state and plans nothing.
		$actual  = ( new \Pediment\Seeder\StateReader( new NullProvider() ) )->read();
		$replan  = $claimer->plan( $manifest, $actual );
		$this->assertSame( [], $replan->byAction( PlanItem::CLAIM ) );
	}

	public function test_a_claimed_page_is_protected_by_the_next_seed() {
		$id       = $this->page( 'about', [ 'post_content' => 'live copy' ] );
		$manifest = $this->manifest( [ 'about' => [ 'title' => 'About', 'content' => '<p>a</p>' ] ] );
		$claimer  = new Claimer( new NullProvider() );
		$claimer->apply( $claimer->plan( $manifest, [] ) );

		$desired = ( new \Pediment\Seeder\DesiredState(
			new NullProvider(),
			new \Pediment\Seeder\ContentResolver( new \Pediment\Seeder\MediaMap( [] ) )
		) )->build( $manifest );
		$reader  = new \Pediment\Seeder\StateReader( new NullProvider() );
		$plan    = ( new \Pediment\Seeder\Differ() )->diff( $desired, $reader->read(), $reader->duplicates() );

		$item = $plan->items()[0];
		$this->assertSame( PlanItem::PROTECTED, $item->action );
		$this->assertSame( 'live copy', get_post( $id )->post_content );
	}
```

- [ ] **Step 2: Run them**

Run: `npx wp-env run tests-wordpress --env-cwd=wp-content/plugins/pediment-ai ./vendor/bin/phpunit --filter ClaimerTest`
Expected: the four new tests pass with the Task 1 implementation. If `test_a_claimed_page_is_protected_by_the_next_seed` reports `update` instead of `protected`, STOP — the arbitration contract is not what the spec assumes and the whole cutover design depends on it.

- [ ] **Step 3: Commit**

```bash
git add plugin/tests/phpunit/Seeder/ClaimerTest.php
git commit -m "test(seeder): pin claim parenting and protection rules"
```

---

### Task 3: Claiming navigation entities

**Files:**
- Modify: `plugin/src/Seeder/NavSeeder.php` (visibility of `slugFor()`)
- Modify: `plugin/src/Seeder/Claimer.php`
- Test: `plugin/tests/phpunit/Seeder/ClaimerTest.php`

**Interfaces:**
- Consumes: `Manifest::navs()` returning `NavSpec[]`, `NavSeeder::slugFor( NavSpec $spec, string $language ): string`.
- Produces: `PlanItem` items with `kind = PlanItem::KIND_NAV` in the claim plan.

- [ ] **Step 1: Write the failing test**

Append to `ClaimerTest`:

```php
	private function nav( string $title, string $slug ): int {
		return self::factory()->post->create(
			[ 'post_type' => 'wp_navigation', 'post_title' => $title, 'post_name' => $slug, 'post_status' => 'publish' ]
		);
	}

	public function test_the_only_unclaimed_navigation_is_claimed_for_a_single_nav_manifest() {
		$id       = $this->nav( 'Primary', 'primary' );
		$manifest = Manifest::fromArray(
			[
				'pages' => [ 'home' => [ 'title' => 'Home', 'content' => '<p>h</p>', 'front_page' => true ] ],
				'navs'  => [ 'primary' => [ 'title' => 'Primary', 'items' => [ [ 'entry' => 'home' ] ] ] ],
			],
			'/tmp/theme'
		);

		$plan  = ( new Claimer( new NullProvider() ) )->plan( $manifest, [] );
		$navs  = $plan->byKind( PlanItem::KIND_NAV );

		$this->assertCount( 1, $navs );
		$this->assertSame( PlanItem::CLAIM, $navs[0]->action );
		$this->assertSame( $id, $navs[0]->postId );
	}

	public function test_two_unclaimed_navigations_fall_back_to_slug_matching() {
		$this->nav( 'Footer', 'footer-menu' );
		$primary  = $this->nav( 'Primary', 'primary' );
		$manifest = Manifest::fromArray(
			[
				'pages' => [ 'home' => [ 'title' => 'Home', 'content' => '<p>h</p>', 'front_page' => true ] ],
				'navs'  => [ 'primary' => [ 'title' => 'Primary', 'items' => [ [ 'entry' => 'home' ] ] ] ],
			],
			'/tmp/theme'
		);

		$navs = ( new Claimer( new NullProvider() ) )->plan( $manifest, [] )->byKind( PlanItem::KIND_NAV );

		$this->assertSame( PlanItem::CLAIM, $navs[0]->action );
		$this->assertSame( $primary, $navs[0]->postId );
	}
```

- [ ] **Step 2: Run it to verify it fails**

Run: `npx wp-env run tests-wordpress --env-cwd=wp-content/plugins/pediment-ai ./vendor/bin/phpunit --filter ClaimerTest`
Expected: FAIL — the plan contains no `KIND_NAV` items.

- [ ] **Step 3: Make `slugFor()` public**

In `plugin/src/Seeder/NavSeeder.php:254`, change `private function slugFor(` to `public function slugFor(` and extend its docblock with one line:

```php
	 * Public because Claimer derives the same slug when matching a legacy
	 * navigation entity; the derivation must not exist twice.
```

- [ ] **Step 4: Add nav claiming to the Claimer**

In `Claimer::plan()`, immediately before `return new Plan( $items );`:

```php
		foreach ( $this->lang->languages() as $language ) {
			foreach ( $manifest->navs() as $spec ) {
				$items[] = $this->planNav( $spec, $language, count( $manifest->navs() ) );
			}
		}
```

And add the two methods:

```php
	/**
	 * A legacy navigation entity's slug is whatever the previous seeder gave
	 * it, so slug matching alone is unreliable. When the manifest declares one
	 * nav and the language holds exactly one unclaimed navigation entity, that
	 * is unambiguous and is claimed. Otherwise fall back to the derived slug,
	 * and report rather than guess.
	 */
	private function planNav( NavSpec $spec, string $language, int $declaredNavs ): PlanItem {
		$candidates = $this->navCandidates( $language );

		if ( 1 === $declaredNavs && 1 === count( $candidates ) ) {
			return $this->navItem( PlanItem::CLAIM, $spec, $language, (int) $candidates[0] );
		}

		$slug    = ( new NavSeeder( $this->lang ) )->slugFor( $spec, $language );
		$bySlug  = array_values(
			array_filter(
				$candidates,
				static fn( int $id ): bool => get_post( $id ) instanceof \WP_Post && get_post( $id )->post_name === $slug
			)
		);

		if ( 1 === count( $bySlug ) ) {
			return $this->navItem( PlanItem::CLAIM, $spec, $language, (int) $bySlug[0] );
		}

		if ( [] === $candidates ) {
			return new PlanItem(
				PlanItem::NO_MATCH,
				PlanItem::KIND_NAV,
				$spec->key,
				$language,
				0,
				[],
				[],
				'no unclaimed navigation entity — the next seed will create it.'
			);
		}

		return new PlanItem(
			PlanItem::AMBIGUOUS,
			PlanItem::KIND_NAV,
			$spec->key,
			$language,
			0,
			[],
			[],
			sprintf(
				'%d unclaimed navigation entities (IDs %s) and none whose slug is "%s" — re-slug the right one, or claim it by hand.',
				count( $candidates ),
				implode( ', ', $candidates ),
				$slug
			)
		);
	}

	private function navItem( string $action, NavSpec $spec, string $language, int $postId ): PlanItem {
		return new PlanItem(
			$action,
			PlanItem::KIND_NAV,
			$spec->key,
			$language,
			$postId,
			[ 'seed_key' => [ 'from' => null, 'to' => $spec->key ] ],
			[],
			sprintf( 'navigation "%s" (ID %d)', (string) get_the_title( $postId ), $postId )
		);
	}

	/** @return int[] Unclaimed wp_navigation posts in this language. */
	private function navCandidates( string $language ): array {
		$args = $this->lang->unscopedQuery(
			[
				'post_type'      => 'wp_navigation',
				'post_status'    => self::STATUSES,
				'posts_per_page' => -1,
				'orderby'        => 'ID',
				'order'          => 'ASC',
				'no_found_rows'  => true,
			]
		);

		$out = [];
		foreach ( get_posts( $args ) as $post ) {
			$id = (int) $post->ID;
			if ( '' !== (string) get_post_meta( $id, Meta::KEY, true ) ) {
				continue;
			}
			if ( ! $this->languageMatches( $id, $language, $this->lang->defaultLanguage() ) ) {
				continue;
			}
			$out[] = $id;
		}

		return $out;
	}
```

- [ ] **Step 5: Run the tests**

Run: `npx wp-env run tests-wordpress --env-cwd=wp-content/plugins/pediment-ai ./vendor/bin/phpunit --filter ClaimerTest`
Expected: PASS, all tests including the two new nav cases.

- [ ] **Step 6: Run the whole plugin suite for regressions**

Run: `npx wp-env run tests-wordpress --env-cwd=wp-content/plugins/pediment-ai ./vendor/bin/phpunit`
Expected: PASS, no new failures (one pre-existing skip is normal).

- [ ] **Step 7: Commit**

```bash
git add plugin/src/Seeder/Claimer.php plugin/src/Seeder/NavSeeder.php plugin/tests/phpunit/Seeder/ClaimerTest.php
git commit -m "feat(seeder): claim legacy navigation entities"
```

---

### Task 4: Language-scoped claiming

**Files:**
- Create: `plugin/tests/polylang/ClaimerLanguageTest.php`
- Modify: `plugin/src/Seeder/Claimer.php` (only if a test fails)

**Interfaces:**
- Consumes: `PolylangTestCase` (`plugin/tests/polylang/PolylangTestCase.php`), `PolylangProvider`, `Claimer`.
- Produces: nothing new.

- [ ] **Step 1: Read the harness**

Read `plugin/tests/polylang/PolylangTestCase.php` and `plugin/tests/polylang/ApplierTranslationTest.php` in full before writing. `PolylangTestCase` seeds the `en`/`de` language terms in `wpSetUpBeforeClass()` and defines no post helpers — tests create posts with `self::factory()` and tag them with `pll_set_post_language()`, which is the idiom below.

- [ ] **Step 2: Write the failing tests**

Create `plugin/tests/polylang/ClaimerLanguageTest.php`:

```php
<?php
// plugin/tests/polylang/ClaimerLanguageTest.php

use Pediment\Language\PolylangProvider;
use Pediment\Seeder\Claimer;
use Pediment\Seeder\Manifest;
use Pediment\Seeder\PlanItem;

class ClaimerLanguageTest extends PolylangTestCase {

	private function manifest(): Manifest {
		return Manifest::fromArray(
			[
				'languages' => [ 'en' => [ 'name' => 'English', 'locale' => 'en_US', 'default' => true ], 'de' => [ 'name' => 'Deutsch', 'locale' => 'de_DE' ] ],
				'pages'     => [
					'about' => [
						'title'     => 'About',
						'content'   => '<p>a</p>',
						'languages' => [ 'de' => [ 'title' => 'Über uns', 'slug' => 'ueber-uns' ] ],
					],
				],
			],
			'/tmp/theme'
		);
	}

	private function page( string $slug, ?string $language = null ): int {
		$id = self::factory()->post->create(
			[ 'post_type' => 'page', 'post_name' => $slug, 'post_title' => 'Legacy', 'post_status' => 'publish' ]
		);
		if ( null !== $language ) {
			pll_set_post_language( $id, $language );
		}
		return $id;
	}

	/** @return array<string,PlanItem> */
	private function byMapKey( \Pediment\Seeder\Plan $plan ): array {
		$out = [];
		foreach ( $plan->items() as $item ) {
			$out[ $item->mapKey() ] = $item;
		}
		return $out;
	}

	public function test_each_language_claims_its_own_page() {
		$en = $this->page( 'about', 'en' );
		$de = $this->page( 'ueber-uns', 'de' );

		$items = $this->byMapKey( ( new Claimer( new PolylangProvider() ) )->plan( $this->manifest(), [] ) );

		$this->assertSame( $en, $items['about|en']->postId );
		$this->assertSame( $de, $items['about|de']->postId );
	}

	public function test_a_german_page_is_never_claimed_for_english() {
		$this->page( 'about', 'de' );

		$items = $this->byMapKey( ( new Claimer( new PolylangProvider() ) )->plan( $this->manifest(), [] ) );

		$this->assertSame( PlanItem::NO_MATCH, $items['about|en']->action );
	}

	public function test_an_untagged_page_is_claimed_for_the_default_language_only() {
		$untagged = $this->page( 'about' );

		$items = $this->byMapKey( ( new Claimer( new PolylangProvider() ) )->plan( $this->manifest(), [] ) );

		$this->assertSame( PlanItem::CLAIM, $items['about|en']->action );
		$this->assertSame( $untagged, $items['about|en']->postId );
		$this->assertSame( PlanItem::NO_MATCH, $items['about|de']->action );
	}
}
```

- [ ] **Step 3: Run the Polylang suite**

Run: `npx wp-env run tests-wordpress --env-cwd=wp-content/plugins/pediment-ai ./vendor/bin/phpunit -c phpunit-polylang.xml.dist --filter ClaimerLanguageTest`
Expected: PASS. If `test_an_untagged_page_is_claimed_for_the_default_language_only` fails, the fix belongs in `Claimer::languageMatches()`, not in the test — the rule is spec §3.1 item 5.

- [ ] **Step 4: Run the full Polylang suite**

Run: `npx wp-env run tests-wordpress --env-cwd=wp-content/plugins/pediment-ai ./vendor/bin/phpunit -c phpunit-polylang.xml.dist`
Expected: PASS, no regressions.

- [ ] **Step 5: Commit**

```bash
git add plugin/tests/polylang/ClaimerLanguageTest.php
git commit -m "test(seeder): pin per-language claim matching"
```

---

### Task 5: The claim report

**Files:**
- Modify: `plugin/src/Seeder/Reporter.php`
- Test: `plugin/tests/phpunit/Seeder/ClaimReporterTest.php`

**Interfaces:**
- Consumes: `Plan`, `PlanItem`.
- Produces: `Reporter::claimText( Plan $plan, bool $applied, string $manifestPath, array $errors = [] ): string`.

- [ ] **Step 1: Write the failing test**

Create `plugin/tests/phpunit/Seeder/ClaimReporterTest.php`:

```php
<?php
// plugin/tests/phpunit/Seeder/ClaimReporterTest.php

use Pediment\Seeder\Plan;
use Pediment\Seeder\PlanItem;
use Pediment\Seeder\Reporter;

class ClaimReporterTest extends WP_UnitTestCase {

	public function test_a_dry_run_names_every_outcome_and_says_nothing_was_written() {
		$plan = new Plan(
			[
				new PlanItem( PlanItem::CLAIM, PlanItem::KIND_ENTRY, 'home', '', 12, [ 'seed_key' => [ 'from' => null, 'to' => 'home' ] ], [], 'page "home" (ID 12)' ),
				new PlanItem( PlanItem::NO_MATCH, PlanItem::KIND_ENTRY, 'about', '', 0, [], [], 'no unclaimed page with slug "about" — the next seed will create it.' ),
				new PlanItem( PlanItem::AMBIGUOUS, PlanItem::KIND_NAV, 'primary', '', 0, [], [], '2 unclaimed navigation entities (IDs 7, 9)' ),
			]
		);

		$text = Reporter::claimText( $plan, false, '/srv/theme/seed/manifest.php' );

		$this->assertStringContainsString( 'Pediment claim — dry run', $text );
		$this->assertStringContainsString( 'manifest: /srv/theme/seed/manifest.php', $text );
		$this->assertStringContainsString( 'claim', $text );
		$this->assertStringContainsString( 'no-match', $text );
		$this->assertStringContainsString( 'ambiguous', $text );
		$this->assertStringContainsString( '1 to claim, 1 without a match, 1 ambiguous.', $text );
		$this->assertStringContainsString( 'Nothing was written (--dry-run).', $text );
	}

	public function test_an_applied_run_does_not_claim_to_be_a_dry_run() {
		$text = Reporter::claimText( new Plan( [] ), true, '/srv/theme/seed/manifest.php' );

		$this->assertStringContainsString( 'Pediment claim', $text );
		$this->assertStringNotContainsString( 'dry run', $text );
		$this->assertStringNotContainsString( '--dry-run', $text );
		$this->assertStringContainsString( '0 to claim, 0 without a match, 0 ambiguous.', $text );
	}

	public function test_errors_are_printed_under_their_own_heading() {
		$text = Reporter::claimText( new Plan( [] ), true, '', [ 'about|: post 12 already carries a seed key' ] );

		$this->assertStringContainsString( 'ERRORS', $text );
		$this->assertStringContainsString( 'post 12 already carries a seed key', $text );
	}
}
```

- [ ] **Step 2: Run it to verify it fails**

Run: `npx wp-env run tests-wordpress --env-cwd=wp-content/plugins/pediment-ai ./vendor/bin/phpunit --filter ClaimReporterTest`
Expected: FAIL with `Call to undefined method Pediment\Seeder\Reporter::claimText()`.

- [ ] **Step 3: Implement `claimText()`**

Add to `plugin/src/Seeder/Reporter.php`, after `text()`:

```php
	/**
	 * A claim report is its own renderer, not a seed report.
	 *
	 * Claiming writes no content, so summaryLine()'s "N to write" would read 0
	 * over a run that just gave 95 rows their identity — the exact shape of
	 * misreport this reporting exists to prevent.
	 *
	 * @param string[] $errors
	 */
	public static function claimText( Plan $plan, bool $applied, string $manifestPath, array $errors = [] ): string {
		$lines   = [];
		$lines[] = $applied ? 'Pediment claim' : 'Pediment claim — dry run';
		if ( '' !== $manifestPath ) {
			$lines[] = 'manifest: ' . $manifestPath;
		}

		foreach (
			[
				PlanItem::KIND_ENTRY => 'PAGES & POSTS',
				PlanItem::KIND_NAV   => 'NAV',
			] as $kind => $heading
		) {
			$items = $plan->byKind( $kind );
			if ( [] === $items ) {
				continue;
			}
			$lines[] = '';
			$lines[] = $heading;
			foreach ( $items as $item ) {
				$language = '' === $item->language ? '' : ' (' . $item->language . ')';
				$lines[]  = sprintf( '  %-10s %-16s %s', $item->action, $item->key . $language, $item->note );
			}
		}

		if ( [] !== $errors ) {
			$lines[] = '';
			$lines[] = 'ERRORS';
			foreach ( $errors as $error ) {
				$lines[] = '  - ' . $error;
			}
		}

		$counts  = [
			PlanItem::CLAIM     => count( $plan->byAction( PlanItem::CLAIM ) ),
			PlanItem::NO_MATCH  => count( $plan->byAction( PlanItem::NO_MATCH ) ),
			PlanItem::AMBIGUOUS => count( $plan->byAction( PlanItem::AMBIGUOUS ) ),
		];
		$lines[] = '';
		$lines[] = sprintf(
			'%d to claim, %d without a match, %d ambiguous.%s',
			$counts[ PlanItem::CLAIM ],
			$counts[ PlanItem::NO_MATCH ],
			$counts[ PlanItem::AMBIGUOUS ],
			$applied ? '' : ' Nothing was written (--dry-run).'
		);

		return implode( "\n", $lines );
	}
```

- [ ] **Step 4: Run the test**

Run: `npx wp-env run tests-wordpress --env-cwd=wp-content/plugins/pediment-ai ./vendor/bin/phpunit --filter ClaimReporterTest`
Expected: PASS, 3 tests.

- [ ] **Step 5: Commit**

```bash
git add plugin/src/Seeder/Reporter.php plugin/tests/phpunit/Seeder/ClaimReporterTest.php
git commit -m "feat(seeder): render a claim plan on its own terms"
```

---

### Task 6: `wp pediment claim`

**Files:**
- Create: `plugin/wp-cli/ClaimCommand.php`
- Modify: `plugin/plugin.php` (after the `pediment adopt` registration, around line 109)
- Test: `plugin/tests/phpunit/Cli/ClaimCommandTest.php`

**Interfaces:**
- Consumes: `Manifest::load()`, `Manifest::resetCache()`, `StateReader::read()`, `Claimer`, `Reporter::claimText()`, `LanguageRegistry::provider()`.
- Produces: `Pediment\Cli\ClaimCommand::__invoke()`, and `ClaimCommand::render( Plan $plan, bool $applied, string $manifestPath, array $errors ): string` for testability without WP-CLI loaded.

- [ ] **Step 1: Read the neighbouring command**

Read `plugin/wp-cli/AdoptCommand.php` and `plugin/tests/phpunit/Cli/` in full. Match their structure exactly — the `class_exists( '\WP_CLI' )` guard, the docblock option syntax, and where errors become `WP_CLI::error()`.

- [ ] **Step 2: Write the failing test**

Create `plugin/tests/phpunit/Cli/ClaimCommandTest.php`:

```php
<?php
// plugin/tests/phpunit/Cli/ClaimCommandTest.php

use Pediment\Cli\ClaimCommand;
use Pediment\Seeder\Plan;
use Pediment\Seeder\PlanItem;

class ClaimCommandTest extends WP_UnitTestCase {

	public function test_render_prints_the_claim_report() {
		$plan = new Plan(
			[ new PlanItem( PlanItem::CLAIM, PlanItem::KIND_ENTRY, 'home', '', 12, [ 'seed_key' => [ 'from' => null, 'to' => 'home' ] ], [], 'page "home" (ID 12)' ) ]
		);

		$out = ClaimCommand::render( $plan, false, '/srv/theme/seed/manifest.php', [] );

		$this->assertStringContainsString( 'Pediment claim — dry run', $out );
		$this->assertStringContainsString( '1 to claim, 0 without a match, 0 ambiguous.', $out );
	}
}
```

- [ ] **Step 3: Run it to verify it fails**

Run: `npx wp-env run tests-wordpress --env-cwd=wp-content/plugins/pediment-ai ./vendor/bin/phpunit --filter ClaimCommandTest`
Expected: FAIL with `Class "Pediment\Cli\ClaimCommand" not found`.

- [ ] **Step 4: Write the command**

Create `plugin/wp-cli/ClaimCommand.php`:

```php
<?php
/**
 * WP-CLI: `wp pediment claim`.
 *
 * @package Pediment
 */

declare(strict_types=1);

namespace Pediment\Cli;

use Pediment\Language\LanguageRegistry;
use Pediment\Seeder\Claimer;
use Pediment\Seeder\Manifest;
use Pediment\Seeder\Plan;
use Pediment\Seeder\Reporter;
use Pediment\Seeder\StateReader;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Gives existing content the seed identity the engine resolves by.
 *
 * Run once, on a site whose content predates Pediment's seeding engine, before
 * the first `wp pediment seed`. Writes `_pediment_seed_key` and nothing else,
 * so every claimed row stays protected from content writes.
 */
final class ClaimCommand {
	/**
	 * ## OPTIONS
	 *
	 * [--dry-run]
	 * : Print the plan and exit without writing anything.
	 *
	 * ## EXAMPLES
	 *
	 *     wp pediment claim --dry-run
	 *     wp pediment claim
	 *
	 * @when after_wp_load
	 *
	 * @param array<int,string>    $args      Positional args (unused).
	 * @param array<string,string> $assocArgs Associative args.
	 */
	public function __invoke( array $args, array $assocArgs ): void {
		$dryRun = isset( $assocArgs['dry-run'] );

		Manifest::resetCache();
		$manifest = Manifest::load();

		if ( null === $manifest ) {
			$output = self::render( new Plan(), false, '', [ sprintf( 'No seed manifest found. Create %s/%s in the active theme.', get_stylesheet(), Manifest::RELATIVE_PATH ) ] );
			if ( class_exists( '\WP_CLI' ) ) {
				\WP_CLI::line( $output );
				\WP_CLI::error( 'Nothing was claimed.' );
			}
			return;
		}

		$provider = LanguageRegistry::provider();
		$claimer  = new Claimer( $provider );
		$plan     = $claimer->plan( $manifest, ( new StateReader( $provider ) )->read() );
		$errors   = [];

		if ( ! $dryRun ) {
			$result = $claimer->apply( $plan );
			$errors = $result['errors'];
		}

		$output = self::render( $plan, ! $dryRun, $manifest->path(), $errors );

		if ( class_exists( '\WP_CLI' ) ) {
			\WP_CLI::line( $output );
			if ( [] !== $errors ) {
				\WP_CLI::error( 'Claiming did not complete cleanly. See the report above.' );
			}
			\WP_CLI::success( $dryRun ? 'Dry run complete — nothing was written.' : 'Claim applied.' );
		}
	}

	/**
	 * The exact bytes the command prints, so the shape can be tested.
	 *
	 * @param string[] $errors
	 */
	public static function render( Plan $plan, bool $applied, string $manifestPath, array $errors ): string {
		return Reporter::claimText( $plan, $applied, $manifestPath, $errors );
	}
}
```

- [ ] **Step 5: Register it**

In `plugin/plugin.php`, after the `pediment adopt` registration:

```php
	require_once __DIR__ . '/wp-cli/ClaimCommand.php';
	\WP_CLI::add_command( 'pediment claim', \Pediment\Cli\ClaimCommand::class );
```

- [ ] **Step 6: Run the test**

Run: `npx wp-env run tests-wordpress --env-cwd=wp-content/plugins/pediment-ai ./vendor/bin/phpunit --filter ClaimCommandTest`
Expected: PASS.

- [ ] **Step 7: Prove it end to end against the fixture theme**

```bash
npx wp-env start
npx wp-env run cli wp pediment claim --dry-run
```
Expected: a report naming the fixture theme's manifest and `0 to claim` (the fixture's content is already keyed), exiting zero. A fatal here means the command is not registered.

- [ ] **Step 8: Commit**

```bash
git add plugin/wp-cli/ClaimCommand.php plugin/plugin.php plugin/tests/phpunit/Cli/ClaimCommandTest.php
git commit -m "feat(cli): add wp pediment claim"
```

---

### Task 7: Claiming from wp-admin

**Files:**
- Modify: `plugin/inc/seeding-admin.php`
- Test: `plugin/tests/phpunit/Seeder/SeedingAdminTest.php`

**Interfaces:**
- Consumes: `Claimer`, `StateReader`, `Manifest`, `Reporter::claimText()`, `LanguageRegistry::provider()`.
- Produces: two more accepted values of `$_POST['pediment_seed_action']` — `claim-preview` and `claim-apply` — under the existing `pediment_seed` nonce.

This task exists because production is admin-only hosting: without it, the claim path cannot run where the only live sites are.

- [ ] **Step 1: Write the failing test**

`SeedingAdminTest::set_up()` already injects a manifest through the `pediment_seed_manifest` filter — one page, key `home`, title `Home` — and every test drives the handler by assigning `$_POST` directly. Both new tests use that same manifest, so the page they create must carry the slug `home`. Append to `plugin/tests/phpunit/Seeder/SeedingAdminTest.php`:

```php
	private function legacyHome(): int {
		return self::factory()->post->create(
			[ 'post_type' => 'page', 'post_name' => 'home', 'post_title' => 'Legacy home', 'post_status' => 'publish' ]
		);
	}

	public function test_a_claim_preview_reports_without_writing() {
		wp_set_current_user( self::factory()->user->create( [ 'role' => 'administrator' ] ) );
		$id                            = $this->legacyHome();
		$_POST['pediment_seed_action'] = 'claim-preview';
		$_POST['_wpnonce']             = wp_create_nonce( 'pediment_seed' );

		$report = pediment_seed_admin_handle_post();

		$this->assertNotNull( $report );
		$this->assertStringContainsString( 'Pediment claim — dry run', $report );
		$this->assertSame( '', get_post_meta( $id, \Pediment\Seeder\Meta::KEY, true ) );
	}

	public function test_a_claim_apply_writes_the_key_and_nothing_else() {
		wp_set_current_user( self::factory()->user->create( [ 'role' => 'administrator' ] ) );
		$id                            = $this->legacyHome();
		$_POST['pediment_seed_action'] = 'claim-apply';
		$_POST['_wpnonce']             = wp_create_nonce( 'pediment_seed' );

		$report = pediment_seed_admin_handle_post();

		$this->assertStringContainsString( 'Pediment claim', $report );
		$this->assertSame( 'home', get_post_meta( $id, \Pediment\Seeder\Meta::KEY, true ) );
		$this->assertSame( '', get_post_meta( $id, \Pediment\Seeder\Meta::HASH, true ) );
		$this->assertSame( 'Legacy home', get_post( $id )->post_title );
	}

	public function test_a_subscriber_cannot_claim() {
		wp_set_current_user( self::factory()->user->create( [ 'role' => 'subscriber' ] ) );
		$id                            = $this->legacyHome();
		$_POST['pediment_seed_action'] = 'claim-apply';
		$_POST['_wpnonce']             = wp_create_nonce( 'pediment_seed' );

		$report = pediment_seed_admin_handle_post();

		$this->assertNull( $report );
		$this->assertSame( '', get_post_meta( $id, \Pediment\Seeder\Meta::KEY, true ) );
	}
```

- [ ] **Step 2: Run it to verify it fails**

Run: `npx wp-env run tests-wordpress --env-cwd=wp-content/plugins/pediment-ai ./vendor/bin/phpunit --filter SeedingAdminTest`
Expected: FAIL — `claim-preview` is not an accepted action, so the handler returns null.

- [ ] **Step 3: Accept the two new actions**

In `plugin/inc/seeding-admin.php`, widen the guard in `pediment_seed_admin_handle_post()`:

```php
	if ( ! in_array( $action, array( 'preview', 'apply', 'claim-preview', 'claim-apply' ), true ) ) {
		return null;
	}
```

and, after `wp_raise_memory_limit( 'admin' );`, branch before the existing seed run:

```php
	if ( 'claim-preview' === $action || 'claim-apply' === $action ) {
		return pediment_seed_admin_run_claim( 'claim-apply' === $action );
	}
```

Then add the runner:

```php
/**
 * Run a claim from wp-admin.
 *
 * Admin-only hosting has no WP-CLI, so this is the only path a live site can
 * take to give its existing content seed identity before the first seed.
 *
 * @param bool $apply Whether to write, as opposed to previewing.
 * @return string Rendered report.
 */
function pediment_seed_admin_run_claim( bool $apply ): string {
	\Pediment\Seeder\Manifest::resetCache();
	$manifest = \Pediment\Seeder\Manifest::load();

	if ( null === $manifest ) {
		return \Pediment\Seeder\Reporter::claimText(
			new \Pediment\Seeder\Plan(),
			false,
			'',
			array(
				sprintf(
					/* translators: 1: theme slug, 2: relative manifest path. */
					__( 'No seed manifest found. Create %1$s/%2$s in the active theme.', 'pediment' ),
					get_stylesheet(),
					\Pediment\Seeder\Manifest::RELATIVE_PATH
				),
			)
		);
	}

	$provider = \Pediment\Language\LanguageRegistry::provider();
	$claimer  = new \Pediment\Seeder\Claimer( $provider );
	$plan     = $claimer->plan( $manifest, ( new \Pediment\Seeder\StateReader( $provider ) )->read() );
	$errors   = array();

	if ( $apply ) {
		$result = $claimer->apply( $plan );
		$errors = $result['errors'];
	}

	return \Pediment\Seeder\Reporter::claimText( $plan, $apply, $manifest->path(), $errors );
}
```

- [ ] **Step 4: Add the buttons**

In `pediment_seed_admin_render_tab()`, after the existing button row, add a second row with its own explanation:

```php
	echo '<hr />';
	echo '<h3>' . esc_html__( 'Claim existing content', 'pediment' ) . '</h3>';
	echo '<p>' . esc_html__(
		'For a site whose pages were built before Pediment. Matches existing pages, posts and menus to the manifest by slug and language and gives them the identity the seeder resolves by. It writes nothing but that identity — titles, content and menus are untouched — and claimed pages stay protected from content updates until you adopt them. Run this once, and preview first.',
		'pediment'
	) . '</p>';

	echo '<div style="display:flex;gap:8px;align-items:center;">';
	foreach ( array(
		'claim-preview' => array( __( 'Preview claim', 'pediment' ), 'secondary' ),
		'claim-apply'   => array( __( 'Claim content', 'pediment' ), 'secondary' ),
	) as $value => $button ) {
		echo '<form method="post" style="margin:0;">';
		wp_nonce_field( 'pediment_seed' );
		echo '<input type="hidden" name="pediment_seed_action" value="' . esc_attr( $value ) . '" />';
		submit_button( $button[0], $button[1], 'submit', false );
		echo '</form>';
	}
	echo '</div>';
```

Both buttons are `secondary`: on this tab the primary action is seeding, and claiming is a one-time preliminary.

- [ ] **Step 5: Run the tests**

Run: `npx wp-env run tests-wordpress --env-cwd=wp-content/plugins/pediment-ai ./vendor/bin/phpunit --filter SeedingAdminTest`
Expected: PASS, including the three new cases.

- [ ] **Step 6: Look at it**

```bash
npx wp-env start
```
Open `http://localhost:8888/wp-admin/options-general.php?page=pediment&tab=seed` (confirm the real tab URL from `plugin/inc/settings-page.php`), click **Preview claim**, and confirm a report renders inside the `<pre>` with no PHP notice above it.

- [ ] **Step 7: Commit**

```bash
git add plugin/inc/seeding-admin.php plugin/tests/phpunit/Seeder/SeedingAdminTest.php
git commit -m "feat(admin): claim existing content from the Seeding tab"
```

---

### Task 8: Document the claim

**Files:**
- Modify: `docs/seeding.md`
- Modify: `docs/client-sites.md`

**Interfaces:**
- Consumes: everything Tasks 1-7 shipped.
- Produces: the operator-facing contract the cutover plan will cite.

- [ ] **Step 1: Write the `docs/seeding.md` section**

Insert a `## wp pediment claim` section immediately before `## The wp-admin tab`, covering, in prose that matches the file's existing voice:

- what a claim is and the one meta key it writes;
- the five matching rules, in the order `Claimer::planOne()` applies them;
- that trash, already-keyed rows and ambiguous matches are never claimed;
- that a claimed row is *protected*, because it has no `_pediment_seed_hash` — and that `wp pediment adopt <key>` is how a page later comes under git's control;
- that it is idempotent and safe to re-run;
- worked example output of `wp pediment claim --dry-run` with one `claim`, one `no-match` and one `ambiguous` line, copied verbatim from a real run rather than invented;
- the admin path, naming the two buttons.

Also extend the existing `## Failure modes` section with an `### Ambiguous claim` subsection stating the fix: delete or re-slug the extra row, then re-run.

- [ ] **Step 2: Add the migration paragraph to `docs/client-sites.md`**

Under a new `## Moving an existing site onto Pediment` heading, give the six-step order from spec §3.3 as a numbered list, and state plainly that step 5's preview is a gate: anything other than protected pages and expected structure means stop.

- [ ] **Step 3: Verify the docs match the code**

Run: `npx wp-env run cli wp pediment claim --dry-run`
Compare the printed headings and summary line against what the docs now claim. Fix the docs, not the output.

- [ ] **Step 4: Commit**

```bash
git add docs/seeding.md docs/client-sites.md
git commit -m "docs(seeding): document the claim path"
```

---

## Part B — header ownership

### Task 9: Initial header markup from a theme pattern

**Files:**
- Modify: `plugin/inc/bootstrap.php`
- Test: `plugin/tests/phpunit/Bootstrap/BootstrapTest.php`

**Interfaces:**
- Consumes: `WP_Block_Patterns_Registry::get_instance()->get_registered( "<stylesheet>/header" )`.
- Produces: unchanged function signature `pediment_bootstrap_header_template_part(): void`.

- [ ] **Step 1: Write the failing test**

`BootstrapTest` defines no helpers — each test queries parts inline and calls `pediment_bootstrap()` or `pediment_bootstrap_header_template_part()` directly. Append, in that style:

```php
	private function headerPart(): WP_Post {
		$parts = get_posts(
			array(
				'post_type'   => 'wp_template_part',
				'post_name__in' => array( 'header' ),
				'post_status' => 'publish',
				'numberposts' => -1,
			)
		);
		$this->assertCount( 1, $parts, 'exactly one header part should exist' );
		return $parts[0];
	}

	public function test_a_theme_registered_header_pattern_supplies_the_initial_markup(): void {
		register_block_pattern(
			get_stylesheet() . '/header',
			array(
				'title'    => 'Header',
				'content'  => '<!-- wp:paragraph --><p>Branded header</p><!-- /wp:paragraph -->',
				'inserter' => false,
			)
		);

		pediment_bootstrap_header_template_part();

		$this->assertStringContainsString( 'Branded header', $this->headerPart()->post_content );

		unregister_block_pattern( get_stylesheet() . '/header' );
	}

	public function test_the_generic_header_is_used_when_no_pattern_is_registered(): void {
		pediment_bootstrap_header_template_part();

		$this->assertStringContainsString( 'site-header', $this->headerPart()->post_content );
	}

	public function test_an_existing_header_part_is_never_overwritten_by_the_pattern(): void {
		pediment_bootstrap_header_template_part();
		wp_update_post(
			array(
				'ID'           => $this->headerPart()->ID,
				'post_content' => '<!-- wp:paragraph --><p>Edited</p><!-- /wp:paragraph -->',
			)
		);

		register_block_pattern(
			get_stylesheet() . '/header',
			array(
				'title'    => 'Header',
				'content'  => '<!-- wp:paragraph --><p>Branded header</p><!-- /wp:paragraph -->',
				'inserter' => false,
			)
		);
		pediment_bootstrap_header_template_part();

		$this->assertStringContainsString( 'Edited', $this->headerPart()->post_content );

		unregister_block_pattern( get_stylesheet() . '/header' );
	}
```

- [ ] **Step 2: Run it to verify it fails**

Run: `npx wp-env run tests-wordpress --env-cwd=wp-content/plugins/pediment-ai ./vendor/bin/phpunit --filter BootstrapTest`
Expected: the first test FAILS (the generic markup is inserted); the other two PASS already.

- [ ] **Step 3: Implement**

In `plugin/inc/bootstrap.php`, replace the line that assigns `$markup` with a call to a new resolver, and add it below:

```php
/**
 * The markup a freshly created `header` part starts life with.
 *
 * A client theme owns its header by registering a pattern named
 * `<stylesheet>/header`. That keeps the branded markup in git — template parts
 * cannot ship from a plugin, and a theme-file part would not be editable in
 * the Site Editor, which is the property this project chose deliberately.
 * The pattern is read once, at creation; later edits belong to the database.
 */
function pediment_bootstrap_header_markup(): string {
	$registry = \WP_Block_Patterns_Registry::get_instance();
	$pattern  = $registry->get_registered( get_stylesheet() . '/header' );

	if ( is_array( $pattern ) && ! empty( $pattern['content'] ) ) {
		return (string) $pattern['content'];
	}

	return PEDIMENT_DEFAULT_HEADER_MARKUP;
}
```

Move today's literal markup into a `const PEDIMENT_DEFAULT_HEADER_MARKUP` defined at the top of the file (or a `pediment_bootstrap_default_header_markup()` function if the existing string interpolates anything), so the fallback stays byte-identical to what ships today.

- [ ] **Step 4: Run the tests**

Run: `npx wp-env run tests-wordpress --env-cwd=wp-content/plugins/pediment-ai ./vendor/bin/phpunit --filter BootstrapTest`
Expected: PASS, all three.

- [ ] **Step 5: Run the e2e suite — the header renders on every page**

Run: `npm run e2e`
Expected: PASS. The front page asserting a resolvable header part is the standing guard here.

- [ ] **Step 6: Document it**

Add a short `### The header` subsection to `docs/client-sites.md` stating: register a pattern named `<theme-slug>/header` to own the initial header markup; it seeds the database part once; from then on the header is edited in the Site Editor and the pattern is not consulted again.

- [ ] **Step 7: Commit**

```bash
git add plugin/inc/bootstrap.php plugin/tests/phpunit/Bootstrap/BootstrapTest.php docs/client-sites.md
git commit -m "feat(bootstrap): seed the header from a theme pattern"
```

---

## Part C — client blocks in the template

### Task 10: The template's optional blocks layer

**Files:**
- Create: `client-template/functions.php`
- Create: `client-template/src/blocks/example-notice/block.json`
- Create: `client-template/src/blocks/example-notice/index.js`
- Create: `client-template/src/blocks/example-notice/render.php`
- Modify: `client-template/.gitignore`

**Interfaces:**
- Consumes: `register_block_type()`, `wp_enqueue_style()`.
- Produces: files the scaffolder either keeps or prunes in Task 11.

- [ ] **Step 1: Write `functions.php`**

```php
<?php
/**
 * __PEDIMENT_NAME__ theme bootstrap.
 *
 * A Pediment client theme has almost no PHP: blocks, templates, tokens,
 * seeding and the AI editor all ship in the Pediment plugin. What lives here
 * is what is genuinely specific to this client — bespoke blocks under
 * src/blocks/, and the stylesheet.
 *
 * @package __PEDIMENT_SLUG__
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Register every built client block.
 *
 * Blocks are authored in src/blocks/ and built into build/blocks/ by
 * `npm run build`. A missing build directory means the release zip was built
 * without that step: every client block then renders as raw markup, which is
 * silent on the front end, so it is surfaced in wp-admin instead.
 */
add_action(
	'init',
	function () {
		$dir = __DIR__ . '/build/blocks';

		if ( ! is_dir( $dir ) ) {
			// A closure, not a named function: the theme slug is hyphenated and
			// would not make a valid PHP identifier after token replacement.
			add_action(
				'admin_notices',
				function () {
					if ( ! current_user_can( 'switch_themes' ) ) {
						return;
					}
					echo '<div class="notice notice-error"><p>'
						. esc_html__( 'This theme was packaged without its built blocks, so its custom sections render as raw markup. Rebuild with `npm run build` and re-upload the release zip.', '__PEDIMENT_SLUG__' )
						. '</p></div>';
				}
			);
			return;
		}

		foreach ( (array) glob( $dir . '/*', GLOB_ONLYDIR ) as $block ) {
			if ( file_exists( $block . '/block.json' ) ) {
				register_block_type( $block );
			}
		}
	}
);

add_action(
	'wp_enqueue_scripts',
	function () {
		wp_enqueue_style(
			'__PEDIMENT_SLUG__',
			get_stylesheet_uri(),
			array(),
			(string) filemtime( __DIR__ . '/style.css' )
		);
	}
);
```

Every `__PEDIMENT_*__` token here sits inside a string, a comment or a docblock, never in an identifier — a hyphenated slug in a function name would not parse.

- [ ] **Step 2: Write the example block**

`client-template/src/blocks/example-notice/block.json`:

```json
{
  "$schema": "https://schemas.wp.org/trunk/block.json",
  "apiVersion": 3,
  "name": "__PEDIMENT_SLUG__/example-notice",
  "title": "Example notice",
  "category": "design",
  "icon": "info",
  "description": "A worked example of a client-specific block. Rename it or delete it.",
  "textdomain": "__PEDIMENT_SLUG__",
  "attributes": {
    "message": { "type": "string", "default": "" }
  },
  "render": "file:./render.php",
  "editorScript": "file:./index.js"
}
```

`client-template/src/blocks/example-notice/index.js`:

```js
import { registerBlockType } from '@wordpress/blocks';
import { useBlockProps, RichText } from '@wordpress/block-editor';
import metadata from './block.json';

registerBlockType( metadata.name, {
	edit( { attributes, setAttributes } ) {
		return (
			<p { ...useBlockProps() }>
				<RichText
					tagName="span"
					value={ attributes.message }
					onChange={ ( message ) => setAttributes( { message } ) }
					placeholder="Notice text…"
				/>
			</p>
		);
	},
	save() {
		return null;
	},
} );
```

`client-template/src/blocks/example-notice/render.php`:

```php
<?php
/**
 * Server render for the example client block.
 *
 * @package __PEDIMENT_SLUG__
 *
 * @var array $attributes
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$message = isset( $attributes['message'] ) ? (string) $attributes['message'] : '';

if ( '' === $message ) {
	return;
}
?>
<p <?php echo wp_kses_data( get_block_wrapper_attributes() ); ?>><?php echo esc_html( $message ); ?></p>
```

- [ ] **Step 3: Add `build/` to `.gitignore`**

Append `build/` to `client-template/.gitignore` if it is not already there.

- [ ] **Step 4: Verify the PHP parses**

Run: `php -l client-template/functions.php && php -l client-template/src/blocks/example-notice/render.php`
Expected: `No syntax errors detected` for both. (Token placeholders are inside strings and comments, so they do not break parsing.)

- [ ] **Step 5: Commit**

```bash
git add client-template/functions.php client-template/src client-template/.gitignore
git commit -m "feat(template): add the optional client blocks layer"
```

---

### Task 11: `--with-blocks`

**Files:**
- Modify: `client-kit/scripts/scaffold.mjs`
- Modify: `client-kit/tests/scaffold.test.mjs`
- Modify: `client-kit/skills/start/SKILL.md`

**Interfaces:**
- Consumes: `scaffold( answers, { target, template, git } )`, `answers.blocks` (new optional boolean).
- Produces: `scaffold( answers, { target, template, git, withBlocks } )`; CLI flag `--with-blocks`. When false (the default), `functions.php` and `src/` are pruned and `package.json` keeps today's thin script set.

- [ ] **Step 1: Write the failing tests**

Append to `client-kit/tests/scaffold.test.mjs`, following the file's existing fixture and temp-dir idioms:

```js
test( 'a scaffold without blocks prunes the blocks layer', async () => {
	const target = await scaffoldFixture( { withBlocks: false } );

	assert.equal( existsSync( path.join( target, 'functions.php' ) ), false );
	assert.equal( existsSync( path.join( target, 'src' ) ), false );

	const pkg = JSON.parse( await readFile( path.join( target, 'package.json' ), 'utf8' ) );
	assert.equal( pkg.scripts.build, undefined );
	assert.equal( ( pkg.devDependencies || {} )[ '@wordpress/scripts' ], undefined );
} );

test( 'a scaffold with blocks keeps it and wires the build', async () => {
	const target = await scaffoldFixture( { withBlocks: true } );

	const functions = await readFile( path.join( target, 'functions.php' ), 'utf8' );
	assert.match( functions, /build\/blocks/ );
	assert.equal( functions.includes( '__PEDIMENT_' ), false );

	const blockJson = JSON.parse(
		await readFile( path.join( target, 'src/blocks/example-notice/block.json' ), 'utf8' )
	);
	assert.equal( blockJson.name, 'acme-roofing/example-notice' );

	const pkg = JSON.parse( await readFile( path.join( target, 'package.json' ), 'utf8' ) );
	assert.equal( pkg.scripts.build, 'wp-scripts build --webpack-src-dir=src/blocks --output-path=build/blocks' );
	assert.equal( pkg.devDependencies[ '@wordpress/scripts' ], '^34.0.0' );
} );
```

Write `scaffoldFixture()` as a local helper in the test file that runs `scaffold()` against `client-template/` with the existing greenfield answers fixture and a slug of `acme-roofing`, into a fresh temp dir, passing `withBlocks` through.

- [ ] **Step 2: Run them to verify they fail**

Run: `node --test client-kit/tests/scaffold.test.mjs`
Expected: FAIL — `functions.php` exists in both cases and no build script is written.

- [ ] **Step 3: Implement in `scaffold.mjs`**

In `scaffold()`, after the Polylang branch and before the `docs/` write:

```js
  if ( withBlocks ) {
    const pkgPath = path.join( target, 'package.json' );
    const pkg = JSON.parse( await readFile( pkgPath, 'utf8' ) );
    pkg.scripts = {
      ...pkg.scripts,
      build: 'wp-scripts build --webpack-src-dir=src/blocks --output-path=build/blocks',
      start: 'wp-scripts start --webpack-src-dir=src/blocks --output-path=build/blocks',
    };
    pkg.devDependencies = { ...pkg.devDependencies, '@wordpress/scripts': '^34.0.0' };
    await writeFile( pkgPath, JSON.stringify( pkg, null, 2 ) + '\n' );
  } else {
    // The template always carries the blocks layer so it is built and tested in
    // one place; a repo that did not ask for bespoke blocks should not inherit a
    // build step it never runs.
    await rm( path.join( target, 'functions.php' ), { force: true } );
    await rm( path.join( target, 'src' ), { recursive: true, force: true } );
  }
```

Destructure `withBlocks = false` from `opts` at the top of `scaffold()`, alongside `git = true`. Add the flag to `parseArgs()`:

```js
    if (arg === '--with-blocks') out.withBlocks = true;
```

and pass `withBlocks: args.withBlocks` in the CLI entry point. Update the usage string in `parseArgs()` to include `[--with-blocks]`.

- [ ] **Step 4: Run the kit tests**

Run: `npm run test:kit`
Expected: PASS, including the two new cases and every existing one.

- [ ] **Step 5: Teach `/pediment:start` to ask**

In `client-kit/skills/start/SKILL.md`, add one question to both branches, asked last, before scaffolding:

> Does this site need any bespoke blocks — sections that Pediment's own blocks cannot express? (Most sites do not. You can add them later with `--with-blocks` on a fresh scaffold, or by hand.)

and pass `--with-blocks` to the scaffolder when the answer is yes. State in the skill that answering yes adds a `npm run build` step the client repo must run before every release.

- [ ] **Step 6: Commit**

```bash
git add client-kit/scripts/scaffold.mjs client-kit/tests/scaffold.test.mjs client-kit/skills/start/SKILL.md
git commit -m "feat(kit): scaffold client blocks behind --with-blocks"
```

---

### Task 12: Build client blocks in CI and in the release zip

**Files:**
- Modify: `.github/actions/seed-check/action.yml`
- Modify: `.github/workflows/client-release.yml`

**Interfaces:**
- Consumes: `package.json`'s `build` script, present only in a `--with-blocks` repo.
- Produces: a `build/blocks/` directory before wp-env boots, and inside the release zip.

- [ ] **Step 1: Build before booting in `seed-check`**

In `.github/actions/seed-check/action.yml`, replace the `Install and boot` step's body with:

```yaml
      run: |
        set -euo pipefail
        npm install
        if [ -d src/blocks ]; then
          echo "Client blocks present — building before boot."
          npm run build
        fi
        npm run env:start
```

A theme whose blocks are not built boots fine and then renders its own sections as raw markup, which no later assertion in this action would catch.

- [ ] **Step 2: Build before staging in `client-release.yml`**

Insert a step after `Verify the header actually moved` and before `Stage and zip`:

```yaml
      - name: Build client blocks
        run: |
          set -euo pipefail
          if [ -d src/blocks ]; then
            npm ci
            npm run build
          else
            echo "No client blocks — nothing to build."
          fi
```

A shell test, not `if: hashFiles(...)`: the client checkout is not at the workflow's root in every call shape, and a step-level expression that silently evaluates to empty would skip the build without saying so.

- [ ] **Step 3: Keep sources out of the zip, and `build/` in it**

In the `Stage and zip` step's `rsync` invocation, add `--exclude 'src'` and extend the comment above it:

```
        # `build/` MUST ship and `src/` MUST NOT: WordPress loads built blocks
        # from build/blocks, and the sources are dead weight in a theme
        # directory that is web-servable. `build/` is gitignored, which is why
        # the build step above runs before this one.
```

- [ ] **Step 4: Verify the YAML parses**

Run: `node -e "const y=require('node:fs').readFileSync('.github/workflows/client-release.yml','utf8'); if(!/Build client blocks/.test(y)) process.exit(1); console.log('present')"`
Expected: `present`. Then confirm both files still lint by opening them — GitHub Actions YAML has no local linter in this repo.

- [ ] **Step 5: Commit**

```bash
git add .github/actions/seed-check/action.yml .github/workflows/client-release.yml
git commit -m "ci(client): build client blocks before boot and zip"
```

---

### Task 13: Prove a with-blocks scaffold in CI

**Files:**
- Modify: `.github/workflows/ci.yml`
- Create: `client-kit/tests/fixtures/answers-blocks.json`

**Interfaces:**
- Consumes: the `scaffold` job's existing matrix and the `seed-check` action.
- Produces: a second matrix entry that scaffolds with blocks, builds them and seeds.

- [ ] **Step 1: Read the existing job**

Read the `scaffold` job in `.github/workflows/ci.yml` in full. Note exactly how `answers-ci.json` is passed and whether the job is already a matrix; the change below assumes it is not and introduces one.

- [ ] **Step 2: Add the fixture**

Create `client-kit/tests/fixtures/answers-blocks.json` as a copy of `answers-ci.json` with a different `client.slug` and `client.name` (e.g. `ci-blocks` / `CI Blocks`), so both matrix legs can run concurrently without colliding on a directory name.

- [ ] **Step 3: Turn the job into a matrix**

```yaml
    strategy:
      fail-fast: false
      matrix:
        include:
          - answers: client-kit/tests/fixtures/answers-ci.json
            slug: ci-fixture
            flags: ""
          - answers: client-kit/tests/fixtures/answers-blocks.json
            slug: ci-blocks
            flags: "--with-blocks"
```

Thread `matrix.answers`, `matrix.slug` and `matrix.flags` through the scaffold step, keeping the existing single-leg behaviour identical for the first entry. Use the real slug values from each fixture — the scaffolder rejects a target directory whose basename differs from `client.slug`.

- [ ] **Step 4: Assert the blocks actually built**

In the blocks leg, after `seed-check`, add:

```yaml
      - name: Assert the client block registered
        if: matrix.flags == '--with-blocks'
        working-directory: ${{ matrix.slug }}
        run: |
          set -euo pipefail
          npx wp-env run cli wp eval 'echo WP_Block_Type_Registry::get_instance()->is_registered("ci-blocks/example-notice") ? "yes" : "no";' | grep -q yes
```

- [ ] **Step 5: Commit**

```bash
git add .github/workflows/ci.yml client-kit/tests/fixtures/answers-blocks.json
git commit -m "ci: scaffold and seed a with-blocks client theme"
```

---

## Wrap-up

### Task 14: Record what shipped and what it changes

**Files:**
- Modify: `docs/BACKLOG.md`
- Modify: `docs/SESSION_LOG.md`

- [ ] **Step 1: Update the backlog**

- Close, with a dated note, the two items this plan resolves: the deferred `--with-blocks` flag (step 5 decision 5) and the "client theme has no bespoke-block tooling" gap.
- Add one 🟡 item: **the cutover plan is not written** — Workation still runs the retired parent/child stack, and the claim path has never met a real legacy database.
- Add one 🟢 item: **media and terms are never claimed** (spec decision 6), naming the consequence — a manifest declaring media that already exists as attachments will upload duplicates.

- [ ] **Step 2: Add a session entry**

`docs/SESSION_LOG.md` stops at 2026-08-01 and is missing step 5, the client-kit distribution and the licensing pass. Add an entry for this session at the top, in the file's existing format, and one line acknowledging the three-session gap so the omission is visible rather than silently backfilled.

- [ ] **Step 3: Commit**

```bash
git add docs/BACKLOG.md docs/SESSION_LOG.md
git commit -m "docs: record the claim path and its open follow-ups"
```

---

### Task 15: Full verification and the gated push

- [ ] **Step 1: Every gate, in order, from a clean environment**

```bash
npx wp-env destroy --debug
npx wp-env start
npx wp-env run tests-wordpress --env-cwd=wp-content/plugins/pediment-ai ./vendor/bin/phpunit
npx wp-env run tests-wordpress --env-cwd=wp-content/plugins/pediment-ai ./vendor/bin/phpunit -c phpunit-polylang.xml.dist
npm run test:kit
npm run test:js
npm run lint:js
npm run lint:blocks
npm run lint:colors
npm run lint:icons
npm run e2e
cd plugin && composer lint && cd ..
```

Expected: all green. `lint:colors` and `phpcs` are the two CI gates most often forgotten, and phpcs fails on warnings.

- [ ] **Step 2: Rehearse the whole claim path by hand**

In a scratch wp-env, create three pages by hand whose slugs match the fixture theme's manifest, delete their seed keys, then:

```bash
npx wp-env run cli wp pediment claim --dry-run
npx wp-env run cli wp pediment claim
npx wp-env run cli wp pediment seed --dry-run
```

Expected: the dry run names three claims; the apply writes three keys; the seed plan then reports those three as `protected` and `0 to write` for their content. Record the actual output — it is the evidence the cutover plan will be written against, and Task 8's worked example should match it verbatim.

- [ ] **Step 3: Report before pushing**

Summarise for the user: what shipped, the exact test counts, the recorded output from Step 2, and the fact that nothing here has yet touched a real legacy database. **Ask for approval to push.**

- [ ] **Step 4: Push, on approval only**

```bash
git push origin HEAD:main
```

Then watch CI (`/check-ci`), and confirm the `scaffold` matrix's blocks leg went green — it is the only proof that the `--with-blocks` path boots.

---

## Self-review notes

Checked against `docs/superpowers/specs/2026-08-05-migration-step6-design.md`:

- Spec decision 1 (claim path, CLI + wp-admin) → Tasks 1-8.
- Spec decision 5 (claims never write hashes) → Task 1 Step 4, pinned by Task 2's `test_apply_writes_only_the_key_and_is_idempotent` and `test_a_claimed_page_is_protected_by_the_next_seed`.
- Spec decision 6 (media is not claimed) → deliberately absent; recorded as a backlog item in Task 14.
- Spec decision 7 (navs are claimed) → Task 3.
- Spec decision 8 (header from a theme pattern) → Task 9.
- Spec §3.5 (client blocks) → Tasks 10-13.
- Spec §3.3 (order of operations) → documented in Task 8, exercised in Task 15 Step 2.
- Spec §3.4 (namespace rename) and §3.6 (cutover questions) → **not in this plan by design**; they belong to the cutover plan, which is written after this one ships.
