# Plan 4b — Seeder Bootstrap-Only and 4.0.0 Release Preparation Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Make `wp pediment seed` a bootstrap tool that only creates what a site is missing and never changes what exists, remove the content-arbitration hashes and their meta, update docs and skills to match, and prepare the branch for the 4.0.0 release without pushing.

**Architecture:** The seeder keeps its five phases (desired state → actual state → plan → apply → verify) and its identity model (`_pediment_seed_key`). The `Differ` plans only `create`, `unchanged` and `orphan`; the `Applier`, `NavSeeder` and `MediaSeeder` write only rows that do not exist; reading settings and the site logo are set only when their row is new or the setting is empty; the `Verifier` checks drift only for rows the same run created. `ContentHash`, `MegaBlocks` and the three hash meta keys go, and a once-per-site upgrade routine deletes the leftover meta.

**Tech Stack:** PHP 8.1, WordPress 6.9, PHPUnit via wp-env (default, Polylang and WPML suites), Playwright e2e, Node 20 (`node:test` for client-kit), release-please.

**Spec:** `docs/superpowers/specs/2026-10-14-core-blocks-mcp-design.md` (§4 "Seeder: bootstrap only" and "Release sequence", §5 "seeder never updates existing content", §6 Documentation). Binding decisions: `.context/plan4-decisions.md` ("Split", "Seeder (4b)", "Release (4b)"). Research with file:line references: `.context/plan4-research-seeder.md` (local, gitignored). Roadmap: `docs/superpowers/plans/2026-10-14-roadmap.md`.

## Global Constraints

- Builds on Plans 1–3 and on Plan 4a (branch `bergjs/elementor-pediment-necessity`, PR #117 not merged). Plan 4a runs first and adds the ninth ability (`pediment/convert-legacy`) and the converter. Never edit the files Plan 4a added for the converter (mapper classes and fixtures, the convert-legacy ability, the convert screen, `wp pediment convert`).
- `Requires at least: 6.9`. PHP 8.1, `declare(strict_types=1);`, `ABSPATH` guard, text domain `pediment`, `_doing_it_wrong` version `'4.0.0'`.
- Everything multilingual goes through `Pediment\Language\LanguageRegistry::provider()`; never call `pll_*` or WPML filters outside `plugin/src/Language/` (docs/STANDARDS.md).
- The summary line keeps the literal prefix `N to write,` (comma included). `.github/actions/seed-check/action.yml` and client CI grep for `(^|[[:space:]])0 to write,`. `--dry-run` and `--format=json` stay.
- The root wp-env port is not fixed in this workspace and permalinks are plain: get the site URL with `npx wp-env run cli wp option get siteurl` and use `?rest_route=` URLs. Tests: `npx wp-env run tests-wordpress --env-cwd=wp-content/plugins/pediment-ai ./vendor/bin/phpunit` (add a path such as `tests/phpunit/Seeder` or `--filter <Name>` to focus); Polylang suite: add `-c phpunit-polylang.xml.dist`. The WPML suite (`-c phpunit-wpml.xml.dist`) runs only in CI's `wpml` job; say so in the task report. Lint: `composer lint -d plugin` (0 errors; warnings are allowed). Kit tests: `npm run test:kit`.
- Playwright e2e may be tried (`cd plugin && npm run e2e -- <spec>`); if it cannot run in this Conductor workspace, run the headless CLI probe the task gives and report that e2e runs in CI.
- npm: never run `npm install` with the local npm 11 in tracked package dirs; use `npx -y npm@10 …`. Stage files by name; conventional commits with summaries of 60 characters or fewer; never push, never open or merge a PR, never cut a release.
- Patches in this plan apply with `git apply` from the repo root. If a hunk does not apply because Plan 4a touched nearby lines, apply that hunk by hand: the `+` lines are the target text.

## Decisions taken in this plan

- S1 — Navigation menus are create-only; `MegaBlocks` and `_pediment_seed_mega_hash` go (Task 2).
- S2 — The header template part stays create-only; a test pins that a seed run never touches it (Task 3).
- S3 — Existing rows are never changed (no slug, parent, `menu_order`, status or trash-restore writes on posts, navs or media); front-page options and the site logo are set only for a row created in the run or while the option is unset (Tasks 1–3).
- S4 — Patterns are read only for entries that will be created; a missing pattern for an existing entry is not an error (Task 1).
- S5 — The Verifier checks slug, parent, front-page and nav drift only for rows created in the same run (Tasks 1–2).
- S6 — The Adopter stops writing hashes and is otherwise unchanged (Task 4).
- S7 — The summary keeps `N to write,`; `--dry-run` and `--format=json` stay; JSON drops `protected` (Task 1).
- S8 — An upgrade routine modelled on `ChatDataCleanup` deletes `_pediment_seed_hash`, `_pediment_seed_source` and `_pediment_seed_mega_hash` once per site (Task 5).
- S9 — A PHPUnit test proves that a second seed after a person edits a page, a menu and the front-page option changes nothing and reports nothing to write for them (Tasks 1–2).
- S10 — Stop-gap in `/pediment:port-page`: the seed creates the page once, every later change goes through `pediment-write-page` after `validate-blocks`; `/pediment:start` matches (Task 7).
- S11 — Docs: seeding.md, client-sites.md, WORDPRESS_TRAPS.md, STANDARDS.md, AGENTS.md, plugin/AGENTS.md, client-template README and AGENTS, the wp-admin tab text (Tasks 6–7).
- R1 — The last task verifies the release config, updates the roadmap, writes "Upgrade to 4.0", and stops before push, merge and release (Task 8).

Further decisions this plan takes (beyond S1–S11 and R1):

- D1 — A trashed row (entry, menu, attachment) stays in the trash: the plan item is `unchanged` with a note, never a restore and never a second row.
- D2 — Entry trash status, menu publish status and attachment trash count as drift too: checked only for rows created in the run; an existing unpublished menu gets a plan note instead of a verification failure.
- D3 — Translation notices and undeclared-media problems are reported only for entries the run creates (S4 means existing rows are not resolved).
- D4 — A menu whose entry links do not all resolve is not created (error "the menu was not created"); existing menus are not checked for missing links.
- D5 — `NavSeeder::plan()` loses its unused `$entryIds` parameter; `NavSeeder::created()` reports new menus to the Verifier.
- D6 — `PlanItem` loses `protectedFields`, `UPDATE`, `PROTECTED` and `RESTORE`; `Plan::counts()` keys become `create`, `unchanged`, `orphan`; `Meta::clearTrashBookkeeping()` goes.
- D7 — "Unset" means `page_on_front`, `page_for_posts` or `custom_logo` is `0`.
- D8 — The cleanup runs on `admin_init` only (the old code runs `upgrader_process_complete`), and `uninstall.php` deletes its done flag.
- D9 — The client template's wp-env installs and activates the MCP Adapter so the port-page stop-gap can call `pediment-write-page` locally.
- D10 — The port-page skill tells agents not to use the legacy `pediment/*` presentation blocks.
- D11 — WORDPRESS_TRAPS drops the hashing trap and gains "Re-asserting seed data on a live site overrules the people who run it".
- D12 — "Upgrade to 4.0" lives in `docs/client-sites.md`, linked from `plugin/README.md`.
- D13 — Tasks 1–3 commit as `feat(seeder)!:` with `BREAKING CHANGE:` footers so the 4.0.0 changelog lists the seeding change.

## Review Focus

1. A 3.x site that still carries `_pediment_seed_hash` / `_pediment_seed_source` on pages a person edited must upgrade with no content change: the seeder never reads the stale hashes, and the first admin request deletes them once — Task 5 pins it (`SeedHashCleanupTest::test_a_v3_site_upgrades_without_any_content_change`).
2. A re-seed after a person renamed a page's slug must keep the new slug and still exit green (no verification problem, so `wp pediment seed` and client CI do not fail forever) — Task 1 pins it (`RunnerTest::test_a_page_a_person_renamed_keeps_its_slug_and_the_seed_stays_green`).
3. A manifest that adds a language later must create only that language's pages, link them into the existing translation groups, and leave the edited default-language page untouched — Task 1 pins it (Polylang suite, `ApplierTranslationTest::test_a_language_added_later_creates_only_its_own_pages`).
4. A menu a person edited in wp-admin must never be rewritten, even when the manifest's `navs` change, and the run must stay green — Task 2 pins it (`NavSeederTest::test_an_edited_nav_is_never_rewritten`, `RunnerTest::test_a_reseed_never_updates_existing_content`).
5. A theme that deleted the pattern file of a page that now lives only on the site must still seed cleanly, while a new page with a missing pattern still stops the run — Task 1 pins it (`RunnerTest::test_a_missing_pattern_for_an_existing_page_is_not_an_error`, `DesiredStateTest`).

---

### Task 1: Entries are create-only

The `Differ` stops planning updates and restores for entries; the `Applier` loses `update()`; the `DesiredState` resolves patterns only for entries that will be created; reading options follow S3; the `Verifier` checks drift only for created entries; the report and JSON lose "protected". Hashes are still written on create until Task 4 (navs still need `ContentHash` through `MegaBlocks` until Task 2).

**Files:**
- Modify: `plugin/src/Seeder/PlanItem.php` (whole file), `plugin/src/Seeder/Plan.php:55-65` (`counts()`), `plugin/src/Seeder/Differ.php` (whole file), `plugin/src/Seeder/DesiredState.php:30-93` (`build()`), `plugin/src/Seeder/ApplyResult.php` (whole file), `plugin/src/Seeder/Applier.php` (whole file), `plugin/src/Seeder/Verifier.php:25-96` (`verify()` signature and entry loop), `plugin/src/Seeder/Runner.php:59-125`, `plugin/src/Seeder/Reporter.php:38-48,149-166`, `plugin/wp-cli/SeedCommand.php:72-94`, `plugin/src/Seeder/NavSeeder.php:97` (one positional argument), `plugin/src/Seeder/Claimer.php` (six `PlanItem` calls)
- Create: `plugin/tests/phpunit/Seeder/DesiredStateTest.php`
- Test: `plugin/tests/phpunit/Seeder/DifferTest.php`, `ApplierTest.php`, `RunnerTest.php`, `VerifierTest.php`, `ReporterTest.php`, `ClaimerTest.php`, `ClaimReporterTest.php`, `plugin/tests/phpunit/Cli/SeedCommandTest.php`, `ClaimCommandTest.php`, `plugin/tests/polylang/ApplierTranslationTest.php`, `plugin/tests/wpml/SeedingBehaviorTest.php`, `plugin/tests/e2e/seeding.spec.ts`, `plugin/tests/e2e/multilingual.spec.ts`

**Interfaces:**
- Consumes: nothing new.
- Produces:
  - `PlanItem::__construct( string $action, string $kind, string $key, string $language, int $postId, array $changes = [], string $note = '' )` — `protectedFields` and `PlanItem::PROTECTED` are gone. `UPDATE` and `RESTORE` stay until Tasks 2 and 3 (navs and media still use them).
  - `Differ::diff( array $desired, array $actual, array $duplicates = [] ): Plan` — per entry `create`, `unchanged` (note `in the trash — left alone; …` for a trashed row) or `orphan`; errors for duplicate keys and post_type mismatches.
  - `DesiredState::build( Manifest $manifest, array $actual = [] ): array<string,DesiredEntry>` — `$actual` is keyed `key|language` like `StateReader::read()`; only its keys are read. An entry whose key is in `$actual` gets `content === ''`, its pattern is not resolved, and it adds no translation notice and no undeclared-media problem.
  - `ApplyResult::__construct( array $ids = [], array $errors = [], array $created = [] )` — `$created` is the list of entry map keys created in this run.
  - `Verifier::verify( Manifest $manifest, array $desired, array $ids, MediaMap $media, array $navIds = [], array $createdEntries = [] ): string[]` — slug, parent, trash and front-page/posts-page checks run only for map keys in `$createdEntries`.
  - `Plan::counts()` keys: `create`, `restore`, `update`, `unchanged`, `orphan`.
  - `Reporter::summaryLine( RunResult $result ): string` returns `'%d to write, %d orphan, %d unchanged.'`, where "to write" counts items whose `writes()` is true.
  - `SeedCommand::render()` JSON items: `kind`, `action`, `key`, `language`, `post_id`, `changes`, `note` (no `protected`).

- [ ] **Step 1: Write the failing tests**

Replace `plugin/tests/phpunit/Seeder/DifferTest.php` with:

```php
<?php

use Pediment\Seeder\ActualEntry;
use Pediment\Seeder\DesiredEntry;
use Pediment\Seeder\Differ;
use Pediment\Seeder\Plan;
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
			$d['content'], $d['frontPage'], $d['postsPage'], $d['menuOrder'], $d['terms'], ''
		);
	}

	private function actual( array $over = [] ): ActualEntry {
		$d = array_merge(
			[
				'id' => 7, 'key' => 'home', 'language' => '', 'postType' => 'page', 'title' => 'Home',
				'slug' => 'home', 'parentId' => 0, 'status' => 'publish', 'menuOrder' => 0,
			],
			$over
		);
		return new ActualEntry(
			$d['id'], $d['key'], $d['language'], $d['postType'], $d['title'], $d['slug'], $d['parentId'],
			$d['status'], $d['menuOrder'], '', '', ''
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

	public function test_an_existing_entry_is_never_changed() {
		// Every field differs from the manifest: a person retitled, rewrote,
		// renamed, moved and reordered the page. None of it is the seeder's.
		$actual = $this->actual( [ 'title' => 'Welcome', 'slug' => 'startseite', 'parentId' => 12, 'menuOrder' => 5 ] );
		$item   = $this->item(
			[ 'home|' => $this->desired( [ 'title' => 'Home', 'content' => '<p>from git</p>', 'menuOrder' => 1 ] ) ],
			[ 'home|' => $actual ]
		);

		$this->assertSame( PlanItem::UNCHANGED, $item->action );
		$this->assertSame( [], $item->changes );
		$this->assertFalse( $item->writes() );
		$this->assertSame( 7, $item->postId );
		$this->assertSame( '', $item->note );
	}

	public function test_a_trashed_entry_stays_in_the_trash_and_says_so() {
		$item = $this->item( [ 'home|' => $this->desired() ], [ 'home|' => $this->actual( [ 'status' => 'trash' ] ) ] );

		$this->assertSame( PlanItem::UNCHANGED, $item->action );
		$this->assertSame( [], $item->changes );
		$this->assertStringContainsString( 'in the trash', $item->note );
	}

	public function test_draft_and_pending_are_editorial_states_the_seeder_leaves_alone() {
		foreach ( [ 'draft', 'pending' ] as $status ) {
			$item = $this->item( [ 'home|' => $this->desired() ], [ 'home|' => $this->actual( [ 'status' => $status ] ) ] );

			$this->assertSame( PlanItem::UNCHANGED, $item->action, $status );
			$this->assertSame( [], $item->changes, $status );
			$this->assertSame( '', $item->note, $status );
		}
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
			[ 'home|' => $this->desired(), 'guide|' => $this->desired( [ 'key' => 'guide' ] ) ],
			[ 'guide|' => $this->actual( [ 'key' => 'guide', 'id' => 8 ] ) ],
			[]
		);

		$this->assertSame( 1, $plan->counts()[ PlanItem::CREATE ] );
		$this->assertSame( 1, $plan->counts()[ PlanItem::UNCHANGED ] );
		$this->assertArrayNotHasKey( 'protected', $plan->counts() );
		$this->assertFalse( $plan->isEmpty() );
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
		$this->assertSame( 0, $plan->counts()[ PlanItem::ORPHAN ], 'counts() reports every action, including empty ones' );
	}
}
```

Create `plugin/tests/phpunit/Seeder/DesiredStateTest.php`:

```php
<?php
// plugin/tests/phpunit/Seeder/DesiredStateTest.php

use Pediment\Language\NullProvider;
use Pediment\Seeder\ContentResolver;
use Pediment\Seeder\DesiredState;
use Pediment\Seeder\Manifest;
use Pediment\Seeder\ManifestError;
use Pediment\Seeder\MediaMap;

class DesiredStateTest extends WP_UnitTestCase {

	private function state(): DesiredState {
		return new DesiredState( new NullProvider(), new ContentResolver( new MediaMap( [] ) ) );
	}

	private function manifest( array $pages ): Manifest {
		return Manifest::fromArray( [ 'pages' => $pages ], '/tmp/theme' );
	}

	public function test_an_existing_entry_does_not_need_its_pattern() {
		// The theme dropped the pattern file of a page that now lives only on
		// the site. The seeder never writes that page again, so the missing
		// pattern must not block the run.
		$desired = $this->state()->build(
			$this->manifest( [ 'about' => [ 'title' => 'About', 'pattern' => 'acme/gone' ] ] ),
			[ 'about|' => true ]
		);

		$this->assertArrayHasKey( 'about|', $desired );
		$this->assertSame( '', $desired['about|']->content );
	}

	public function test_a_new_entry_still_needs_its_pattern() {
		$this->expectException( ManifestError::class );
		$this->expectExceptionMessage( 'acme/gone' );

		$this->state()->build( $this->manifest( [ 'about' => [ 'title' => 'About', 'pattern' => 'acme/gone' ] ] ) );
	}

	public function test_an_undeclared_media_key_is_reported_only_for_a_new_entry() {
		$manifest = $this->manifest(
			[
				'home'  => [ 'title' => 'Home', 'content' => '<img src="{{media_url:hreo}}" />' ],
				'about' => [ 'title' => 'About', 'content' => '<img src="{{media_url:hreo}}" />' ],
			]
		);
		$state = $this->state();

		$state->build( $manifest, [ 'home|' => true ] );

		$this->assertSame( [ 'about|' => [ 'hreo' ] ], $state->undeclaredMediaKeys() );
	}
}
```

Replace `plugin/tests/phpunit/Seeder/ApplierTest.php` with:

```php
<?php

use Pediment\Language\NullProvider;
use Pediment\Seeder\Applier;
use Pediment\Seeder\ContentHash;
use Pediment\Seeder\ContentResolver;
use Pediment\Seeder\DesiredState;
use Pediment\Seeder\Differ;
use Pediment\Seeder\Manifest;
use Pediment\Seeder\MediaMap;
use Pediment\Seeder\Meta;
use Pediment\Seeder\PlanItem;
use Pediment\Seeder\StateReader;

class ApplierTest extends WP_UnitTestCase {

	/** @var string[] */
	private array $lastErrors = [];

	/** Runs the four phases the way the Runner will, and returns the resolved IDs. */
	private function seed( array $raw, bool $expectClean = true ): array {
		$manifest = Manifest::fromArray( $raw, '/tmp/theme' );
		$lang     = new NullProvider();
		$reader   = new StateReader( $lang );
		$actual   = $reader->read();
		$desired  = ( new DesiredState( $lang, new ContentResolver( new MediaMap( [] ) ) ) )->build( $manifest, $actual );
		$plan     = ( new Differ() )->diff( $desired, $actual, $reader->duplicates() );
		$result   = ( new Applier( $lang ) )->apply( $plan, $desired );

		if ( $expectClean ) {
			$this->assertSame( [], $result->errors );
		}
		$this->lastErrors = $result->errors;

		return $result->ids;
	}

	/** Phases 1-3 only: the plan the Runner would compute, with nothing written. */
	private function plan( array $raw ): \Pediment\Seeder\Plan {
		$manifest = Manifest::fromArray( $raw, '/tmp/theme' );
		$lang     = new NullProvider();
		$reader   = new StateReader( $lang );
		$actual   = $reader->read();
		$desired  = ( new DesiredState( $lang, new ContentResolver( new MediaMap( [] ) ) ) )->build( $manifest, $actual );

		return ( new Differ() )->diff( $desired, $actual, $reader->duplicates() );
	}

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

	public function test_a_manifest_change_never_reaches_an_existing_page() {
		$ids = $this->seed( $this->manifest( [ 'home' => [ 'title' => 'Home', 'content' => '<p>one</p>' ] ] ) );
		$id  = $ids['home|'];

		$this->seed( $this->manifest( [ 'home' => [ 'title' => 'Welcome', 'content' => '<p>two</p>' ] ] ) );

		$this->assertSame( '<p>one</p>', get_post( $id )->post_content );
		$this->assertSame( 'Home', get_post( $id )->post_title );
	}

	public function test_a_client_edit_is_never_overwritten() {
		$ids = $this->seed( $this->manifest( [ 'home' => [ 'title' => 'Home', 'content' => '<p>one</p>' ] ] ) );
		$id  = $ids['home|'];
		wp_update_post( [ 'ID' => $id, 'post_content' => '<p>client copy</p>', 'post_title' => 'Client title' ] );

		$this->seed( $this->manifest( [ 'home' => [ 'title' => 'Home', 'content' => '<p>two</p>' ] ] ) );

		$this->assertSame( '<p>client copy</p>', get_post( $id )->post_content );
		$this->assertSame( 'Client title', get_post( $id )->post_title );
	}

	public function test_a_slug_a_person_changed_is_kept() {
		$ids = $this->seed( $this->manifest( [ 'contact' => [ 'title' => 'Contact', 'content' => '' ] ] ) );
		$id  = $ids['contact|'];
		wp_update_post( [ 'ID' => $id, 'post_name' => 'kontakt' ] );

		$again = $this->seed( $this->manifest( [ 'contact' => [ 'title' => 'Contact', 'content' => '' ] ] ) );

		$this->assertSame( 'kontakt', get_post( $id )->post_name );
		$this->assertSame( $id, $again['contact|'] );
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

	public function test_a_front_page_a_person_chose_is_kept() {
		$m   = $this->manifest(
			[
				'home'  => [ 'title' => 'Home', 'content' => '', 'front_page' => true ],
				'about' => [ 'title' => 'About', 'content' => '' ],
			]
		);
		$ids = $this->seed( $m );
		update_option( 'page_on_front', $ids['about|'] );

		$this->seed( $m );

		$this->assertSame( $ids['about|'], (int) get_option( 'page_on_front' ) );
	}

	public function test_an_unset_front_page_is_set_for_an_existing_page() {
		// A claimed legacy site: the home page exists, but no static front page
		// was ever chosen. Filling an empty setting is not overruling anyone.
		$m   = $this->manifest( [ 'home' => [ 'title' => 'Home', 'content' => '', 'front_page' => true ] ] );
		$ids = $this->seed( $m );
		update_option( 'show_on_front', 'posts' );
		update_option( 'page_on_front', 0 );

		$this->seed( $m );

		$this->assertSame( 'page', get_option( 'show_on_front' ) );
		$this->assertSame( $ids['home|'], (int) get_option( 'page_on_front' ) );
	}

	public function test_a_new_child_finds_its_existing_parent() {
		$first = $this->seed( $this->manifest( [ 'guide' => [ 'title' => 'Guide', 'content' => '' ] ] ) );

		$ids = $this->seed(
			$this->manifest(
				[
					'guide'     => [ 'title' => 'Guide', 'content' => '' ],
					'guide/faq' => [ 'title' => 'FAQ', 'content' => '', 'parent' => 'guide' ],
				]
			)
		);

		$this->assertSame( $first['guide|'], get_post( $ids['guide/faq|'] )->post_parent );
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

	public function test_a_trashed_entry_stays_in_the_trash() {
		$ids = $this->seed( $this->manifest( [ 'home' => [ 'title' => 'Home', 'content' => '' ] ] ) );
		$id  = $ids['home|'];
		wp_trash_post( $id );

		$again = $this->seed( $this->manifest( [ 'home' => [ 'title' => 'Home', 'content' => '' ] ] ) );

		$this->assertSame( $id, $again['home|'], 'never a second row under the same key' );
		$this->assertSame( 'trash', get_post( $id )->post_status );
		$this->assertCount( 1, get_posts( [ 'post_type' => 'page', 'post_status' => [ 'publish', 'trash' ], 'meta_key' => Meta::KEY, 'meta_value' => 'home', 'fields' => 'ids' ] ) );
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

	public function test_a_slug_collision_is_reported_when_the_page_is_created() {
		// A pre-existing unseeded page holds /contact, so WordPress uniquifies the
		// seeded one to contact-2. The seeder never touches the row again, so the
		// create is the only moment to say so.
		self::factory()->post->create( [ 'post_type' => 'page', 'post_name' => 'contact', 'post_title' => 'Client contact' ] );

		$this->seed( $this->manifest( [ 'contact' => [ 'title' => 'Contact', 'content' => '' ] ] ), false );

		$this->assertNotEmpty( $this->lastErrors );
		$this->assertStringContainsString( 'contact-2', $this->lastErrors[0] );
	}

	public function test_terms_on_an_existing_entry_are_never_reassigned() {
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
}
```

Replace `plugin/tests/phpunit/Seeder/ReporterTest.php` with:

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
					PlanItem::UNCHANGED,
					PlanItem::KIND_ENTRY,
					'contact',
					'',
					9,
					[],
					'in the trash — left alone; restore it in the editor, or empty the trash and the next seed creates it again'
				),
				new PlanItem( PlanItem::UNCHANGED, PlanItem::KIND_ENTRY, 'guide', '', 11 ),
				new PlanItem( PlanItem::ORPHAN, PlanItem::KIND_ENTRY, 'legacy', '', 42, [], '"Legacy offer" (ID 42) — left in place' ),
			]
		);
		return new RunResult( $plan, $applied, '/themes/acme/seed/manifest.php' );
	}

	public function test_every_action_appears_in_the_report() {
		$text = Reporter::text( $this->result() );

		$this->assertStringContainsString( '/themes/acme/seed/manifest.php', $text );
		$this->assertStringContainsString( 'create', $text );
		$this->assertStringContainsString( 'hero-bg.jpg', $text );
		$this->assertStringContainsString( 'in the trash', $text );
		$this->assertStringContainsString( 'unchanged', $text );
		$this->assertStringContainsString( 'orphan', $text );
	}

	public function test_the_report_has_no_protected_wording_left() {
		$this->assertStringNotContainsString( 'protected', Reporter::text( $this->result() ) );
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

	public function test_summary_line_counts_writes_orphans_and_unchanged() {
		// 2 creates (media hero-bg + page home) = 2 writes.
		$this->assertSame( '2 to write, 1 orphan, 2 unchanged.', Reporter::summaryLine( $this->result( true ) ) );
	}

	public function test_the_summary_keeps_the_seed_check_contract() {
		// .github/actions/seed-check greps for this exact prefix, comma included.
		$this->assertMatchesRegularExpression( '/(^|\s)0 to write,/', Reporter::summaryLine( new RunResult( new Plan(), false ) ) );
	}

	public function test_a_partial_apply_does_not_claim_nothing_was_applied() {
		$result = new RunResult( new Plan(), true, '', [ 'media.logo: could not copy' ] );

		$text = Reporter::text( $result );

		$this->assertStringContainsString( 'the run continued', $text );
		$this->assertStringNotContainsString( 'nothing was applied', $text );
	}

	public function test_notices_print_without_failing_the_run() {
		$result = new RunResult( new Plan(), true, '/x/manifest.php', [], [], [], [ 'home (de): no pattern `x/home-de` is registered — the German page carries the default language content.' ] );

		$text = Reporter::text( $result );

		$this->assertStringContainsString( 'TRANSLATIONS', $text );
		$this->assertStringContainsString( 'x/home-de', $text );
		$this->assertTrue( $result->ok(), 'A missing translation must not fail the run.' );
	}
}
```

Replace `plugin/tests/phpunit/Cli/SeedCommandTest.php` with:

```php
<?php
namespace Pediment\Tests\Cli;

use Pediment\Cli\SeedCommand;
use Pediment\Seeder\Plan;
use Pediment\Seeder\PlanItem;
use Pediment\Seeder\Reporter;
use Pediment\Seeder\RunResult;

class SeedCommandTest extends \WP_UnitTestCase {

	private function result(): RunResult {
		$plan = new Plan(
			[
				new PlanItem(
					PlanItem::CREATE,
					PlanItem::KIND_ENTRY,
					'about',
					'',
					0,
					[ 'slug' => [ 'from' => null, 'to' => 'about' ] ]
				),
				new PlanItem( PlanItem::UNCHANGED, PlanItem::KIND_ENTRY, 'home', '', 7 ),
			]
		);
		return new RunResult( $plan, false, '/themes/acme/seed/manifest.php' );
	}

	public function test_text_mode_returns_the_reporters_output(): void {
		$result = $this->result();

		$this->assertSame( Reporter::text( $result ), SeedCommand::render( $result, false ) );
	}

	public function test_json_mode_exposes_counts_and_per_item_detail(): void {
		$data = json_decode( SeedCommand::render( $this->result(), true ), true );

		$this->assertIsArray( $data );
		$this->assertArrayHasKey( 'ok', $data );
		$this->assertArrayHasKey( 'counts', $data );
		$this->assertArrayHasKey( 'items', $data );
		$this->assertArrayNotHasKey( 'protected', $data['counts'] );

		$this->assertCount( 2, $data['items'] );
		$item = $data['items'][0];
		$this->assertSame( PlanItem::CREATE, $item['action'] );
		$this->assertSame( 'about', $item['key'] );
		$this->assertSame( [ 'slug' ], $item['changes'] );
		$this->assertArrayNotHasKey( 'protected', $item );
	}
}
```

Apply the test edits for `RunnerTest`, `VerifierTest`, `ClaimerTest`, `ClaimReporterTest`, `ClaimCommandTest`, the Polylang `ApplierTranslationTest` (helpers pass the actual rows to `build()`, the "already tagged" case plans `unchanged`, and a new test for a language added later — Review Focus 3) and the WPML `SeedingBehaviorTest`:

```bash
git apply <<'PATCH'
diff --git a/plugin/tests/phpunit/Cli/ClaimCommandTest.php b/plugin/tests/phpunit/Cli/ClaimCommandTest.php
index 0c8686f..9de3dfc 100644
--- a/plugin/tests/phpunit/Cli/ClaimCommandTest.php
+++ b/plugin/tests/phpunit/Cli/ClaimCommandTest.php
@@ -10,7 +10,7 @@ class ClaimCommandTest extends WP_UnitTestCase {
 
 	public function test_render_prints_the_claim_report() {
 		$plan = new Plan(
-			[ new PlanItem( PlanItem::CLAIM, PlanItem::KIND_ENTRY, 'home', '', 12, [ 'seed_key' => [ 'from' => null, 'to' => 'home' ] ], [], 'page "home" (ID 12)' ) ]
+			[ new PlanItem( PlanItem::CLAIM, PlanItem::KIND_ENTRY, 'home', '', 12, [ 'seed_key' => [ 'from' => null, 'to' => 'home' ] ], 'page "home" (ID 12)' ) ]
 		);
 
 		$out = ClaimCommand::render( new ClaimResult( $plan, false, '/srv/theme/seed/manifest.php', [] ) );
diff --git a/plugin/tests/phpunit/Seeder/ClaimReporterTest.php b/plugin/tests/phpunit/Seeder/ClaimReporterTest.php
index a1d6ec7..6bc251b 100644
--- a/plugin/tests/phpunit/Seeder/ClaimReporterTest.php
+++ b/plugin/tests/phpunit/Seeder/ClaimReporterTest.php
@@ -11,9 +11,9 @@ class ClaimReporterTest extends WP_UnitTestCase {
 	public function test_a_dry_run_names_every_outcome_and_says_nothing_was_written() {
 		$plan = new Plan(
 			[
-				new PlanItem( PlanItem::CLAIM, PlanItem::KIND_ENTRY, 'home', '', 12, [ 'seed_key' => [ 'from' => null, 'to' => 'home' ] ], [], 'page "home" (ID 12)' ),
-				new PlanItem( PlanItem::NO_MATCH, PlanItem::KIND_ENTRY, 'about', '', 0, [], [], 'no unclaimed page with slug "about" — the next seed will create it.' ),
-				new PlanItem( PlanItem::AMBIGUOUS, PlanItem::KIND_NAV, 'primary', '', 0, [], [], '2 unclaimed navigation entities (IDs 7, 9)' ),
+				new PlanItem( PlanItem::CLAIM, PlanItem::KIND_ENTRY, 'home', '', 12, [ 'seed_key' => [ 'from' => null, 'to' => 'home' ] ], 'page "home" (ID 12)' ),
+				new PlanItem( PlanItem::NO_MATCH, PlanItem::KIND_ENTRY, 'about', '', 0, [], 'no unclaimed page with slug "about" — the next seed will create it.' ),
+				new PlanItem( PlanItem::AMBIGUOUS, PlanItem::KIND_NAV, 'primary', '', 0, [], '2 unclaimed navigation entities (IDs 7, 9)' ),
 			]
 		);
 
@@ -36,7 +36,7 @@ class ClaimReporterTest extends WP_UnitTestCase {
 	public function test_a_language_specific_item_shows_its_language_code_after_the_key() {
 		$plan = new Plan(
 			[
-				new PlanItem( PlanItem::CLAIM, PlanItem::KIND_ENTRY, 'home', 'fr', 14, [ 'seed_key' => [ 'from' => null, 'to' => 'home' ] ], [], 'page "home" (ID 14)' ),
+				new PlanItem( PlanItem::CLAIM, PlanItem::KIND_ENTRY, 'home', 'fr', 14, [ 'seed_key' => [ 'from' => null, 'to' => 'home' ] ], 'page "home" (ID 14)' ),
 			]
 		);
 
diff --git a/plugin/tests/phpunit/Seeder/ClaimerTest.php b/plugin/tests/phpunit/Seeder/ClaimerTest.php
index 2b49f6c..b39f7aa 100644
--- a/plugin/tests/phpunit/Seeder/ClaimerTest.php
+++ b/plugin/tests/phpunit/Seeder/ClaimerTest.php
@@ -132,21 +132,23 @@ class ClaimerTest extends WP_UnitTestCase {
 		$this->assertSame( [], $replan->byAction( PlanItem::CLAIM ) );
 	}
 
-	public function test_a_claimed_page_is_protected_by_the_next_seed() {
+	public function test_a_claimed_page_is_left_alone_by_the_next_seed() {
 		$id       = $this->page( 'about', [ 'post_content' => 'live copy' ] );
 		$manifest = $this->manifest( [ 'about' => [ 'title' => 'About', 'content' => '<p>a</p>' ] ] );
 		$claimer  = new Claimer( new NullProvider() );
 		$claimer->apply( $claimer->plan( $manifest, [] ) );
 
+		$reader  = new \Pediment\Seeder\StateReader( new NullProvider() );
+		$actual  = $reader->read();
 		$desired = ( new \Pediment\Seeder\DesiredState(
 			new NullProvider(),
 			new \Pediment\Seeder\ContentResolver( new \Pediment\Seeder\MediaMap( [] ) )
-		) )->build( $manifest );
-		$reader  = new \Pediment\Seeder\StateReader( new NullProvider() );
-		$plan    = ( new \Pediment\Seeder\Differ() )->diff( $desired, $reader->read(), $reader->duplicates() );
+		) )->build( $manifest, $actual );
+		$plan    = ( new \Pediment\Seeder\Differ() )->diff( $desired, $actual, $reader->duplicates() );
 
 		$item = $plan->items()[0];
-		$this->assertSame( PlanItem::PROTECTED, $item->action );
+		$this->assertSame( PlanItem::UNCHANGED, $item->action );
+		$this->assertFalse( $item->writes() );
 		$this->assertSame( 'live copy', get_post( $id )->post_content );
 	}
 
diff --git a/plugin/tests/phpunit/Seeder/RunnerTest.php b/plugin/tests/phpunit/Seeder/RunnerTest.php
index 56b4736..4a116b5 100644
--- a/plugin/tests/phpunit/Seeder/RunnerTest.php
+++ b/plugin/tests/phpunit/Seeder/RunnerTest.php
@@ -52,17 +52,64 @@ class RunnerTest extends WP_UnitTestCase {
 		$this->assertTrue( $second->ok() );
 	}
 
-	public function test_a_client_edit_survives_a_content_release() {
+	public function test_a_reseed_never_updates_an_existing_page() {
+		$first   = ( new Runner() )->run();
+		$aboutId = $first->ids['about|'];
+		$homeId  = $first->ids['home|'];
+
+		// A person edits the page and the reading settings in wp-admin.
+		wp_update_post( [ 'ID' => $aboutId, 'post_title' => 'About us', 'post_content' => '<p>written live</p>', 'post_name' => 'ueber-uns', 'menu_order' => 4 ] );
+		update_option( 'page_on_front', $aboutId );
+		$before    = get_post( $aboutId );
+		$revisions = count( wp_get_post_revisions( $aboutId ) );
+
+		// The manifest moves on: new copy, title and slug for about, and a new page.
+		$this->manifest['pages']['about'] = [ 'title' => 'About', 'content' => '<p>from git</p>', 'slug' => 'about-git' ];
+		$this->manifest['pages']['team']  = [ 'title' => 'Team', 'content' => '<p>team</p>' ];
+
+		$second = ( new Runner() )->run();
+
+		$this->assertTrue( $second->ok(), implode( "\n", array_merge( $second->errors, $second->problems ) ) );
+		$after = get_post( $aboutId );
+		$this->assertSame( $before->post_title, $after->post_title );
+		$this->assertSame( $before->post_content, $after->post_content );
+		$this->assertSame( 'ueber-uns', $after->post_name );
+		$this->assertSame( 4, $after->menu_order );
+		$this->assertCount( $revisions, wp_get_post_revisions( $aboutId ), 'no write, so no new revision' );
+		$this->assertSame( $aboutId, (int) get_option( 'page_on_front' ), 'the reading setting a person chose stays' );
+		$this->assertGreaterThan( 0, $second->ids['team|'] ?? 0, 'a page the manifest added is still created' );
+
+		$actions = [];
+		foreach ( $second->plan->byKind( PlanItem::KIND_ENTRY ) as $item ) {
+			$actions[ $item->key ] = $item->action;
+		}
+		$this->assertSame( [ 'home' => PlanItem::UNCHANGED, 'about' => PlanItem::UNCHANGED, 'team' => PlanItem::CREATE ], $actions );
+		$this->assertSame( $homeId, $second->ids['home|'] );
+	}
+
+	public function test_a_page_a_person_renamed_keeps_its_slug_and_the_seed_stays_green() {
 		$first = ( new Runner() )->run();
-		$id    = $first->ids['home|'];
-		wp_update_post( [ 'ID' => $id, 'post_content' => '<p>client copy</p>' ] );
+		wp_update_post( [ 'ID' => $first->ids['about|'], 'post_name' => 'ueber-uns' ] );
 
-		$this->manifest['pages']['home']['content'] = '<p>two</p>';
-		$result                                     = ( new Runner() )->run();
+		$second = ( new Runner() )->run();
+
+		$this->assertTrue( $second->ok(), implode( "\n", array_merge( $second->errors, $second->problems ) ) );
+		$this->assertSame( 'ueber-uns', get_post( $first->ids['about|'] )->post_name );
+		$this->assertSame( [], array_values( array_filter( $second->plan->byKind( PlanItem::KIND_ENTRY ), static fn( PlanItem $i ): bool => $i->writes() ) ) );
+	}
+
+	public function test_a_missing_pattern_for_an_existing_page_is_not_an_error() {
+		register_block_pattern( 'acme/about', [ 'title' => 'About', 'content' => '<p>about</p>' ] );
+		$this->manifest['pages']['about'] = [ 'title' => 'About', 'pattern' => 'acme/about' ];
+		( new Runner() )->run();
+
+		// The theme drops the pattern file: the page now lives only on the site.
+		unregister_block_pattern( 'acme/about' );
+		$second = ( new Runner() )->run();
 
-		$this->assertSame( '<p>client copy</p>', get_post( $id )->post_content );
-		$this->assertNotEmpty( $result->plan->byAction( PlanItem::PROTECTED ) );
-		$this->assertTrue( $result->ok(), 'protecting content is a success, not a failure' );
+		$this->assertTrue( $second->ok(), implode( "\n", array_merge( $second->errors, $second->problems ) ) );
+		$about = array_values( array_filter( $second->plan->byKind( PlanItem::KIND_ENTRY ), static fn( PlanItem $i ): bool => 'about' === $i->key ) );
+		$this->assertSame( PlanItem::UNCHANGED, $about[0]->action );
 	}
 
 	public function test_a_duplicate_seed_key_aborts_before_any_write() {
@@ -190,10 +237,11 @@ class RunnerTest extends WP_UnitTestCase {
 		$this->assertStringContainsString( 'navs.primary', implode( "\n", $second->problems ) );
 	}
 
-	public function test_a_typoed_media_key_is_reported_on_every_run() {
+	public function test_a_typoed_media_key_is_reported_when_the_page_is_created() {
 		// Nothing else notices: the media plan has no such key and the Verifier
-		// only walks declared media, so the literal sentinel lands in the page
-		// and is hashed as if it were correct.
+		// only walks declared media, so the literal sentinel lands in the new
+		// page. After that the seeder never reads the pattern again — fixing
+		// the live page is a job for the editor or the MCP tools.
 		$dir = get_temp_dir() . 'pediment-runner-typo';
 		wp_mkdir_p( $dir . '/seed/media' );
 		file_put_contents( $dir . '/seed/media/hero.svg', '<svg xmlns="http://www.w3.org/2000/svg"/>' );
@@ -206,9 +254,9 @@ class RunnerTest extends WP_UnitTestCase {
 
 		remove_all_filters( 'stylesheet_directory' );
 		$this->assertStringContainsString( 'hreo', implode( "\n", $first->problems ) );
+		$this->assertFalse( $first->ok() );
 		$this->assertTrue( $second->plan->isEmpty(), 'nothing changed, so nothing is planned' );
-		$this->assertStringContainsString( 'hreo', implode( "\n", $second->problems ), 'a key that can never resolve must not go quiet after the first run' );
-		$this->assertFalse( $second->ok() );
+		$this->assertStringNotContainsString( 'hreo', implode( "\n", $second->problems ), 'the page exists, so its pattern is not read again' );
 	}
 
 	public function test_a_declared_media_key_is_not_reported_as_a_typo_on_a_fresh_site() {
diff --git a/plugin/tests/phpunit/Seeder/VerifierTest.php b/plugin/tests/phpunit/Seeder/VerifierTest.php
index a6bc83d..66dcef1 100644
--- a/plugin/tests/phpunit/Seeder/VerifierTest.php
+++ b/plugin/tests/phpunit/Seeder/VerifierTest.php
@@ -42,31 +42,47 @@ class VerifierTest extends WP_UnitTestCase {
 		$this->assertStringContainsString( 'home', $problems[0] );
 	}
 
-	public function test_a_uniquified_slug_is_a_problem() {
-		// WordPress appends -2 when a slug collides; silently accepting that is
-		// how seeded URLs drift from the manifest.
+	public function test_a_uniquified_slug_on_a_new_page_is_a_problem() {
+		// WordPress appends -2 when a slug collides; the page this run created
+		// did not get the URL the manifest declares.
 		$m  = $this->manifest();
 		$id = self::factory()->post->create( [ 'post_type' => 'page', 'post_name' => 'home-2', 'post_title' => 'Home' ] );
 		update_post_meta( $id, Meta::KEY, 'home' );
 		update_option( 'show_on_front', 'page' );
 		update_option( 'page_on_front', $id );
 
-		$problems = ( new Verifier( new NullProvider(), new NavSeeder( new NullProvider() ) ) )->verify( $m, $this->desired( $m ), [ 'home|' => $id ], new MediaMap( [] ) );
+		$problems = ( new Verifier( new NullProvider(), new NavSeeder( new NullProvider() ) ) )->verify( $m, $this->desired( $m ), [ 'home|' => $id ], new MediaMap( [] ), [], [ 'home|' ] );
 
 		$this->assertStringContainsString( 'home-2', implode( "\n", $problems ) );
 	}
 
-	public function test_a_front_page_option_pointing_elsewhere_is_a_problem() {
+	public function test_a_front_page_option_pointing_elsewhere_is_a_problem_for_a_new_page() {
 		$m  = $this->manifest();
 		$id = self::factory()->post->create( [ 'post_type' => 'page', 'post_name' => 'home', 'post_title' => 'Home' ] );
 		update_post_meta( $id, Meta::KEY, 'home' );
 		update_option( 'show_on_front', 'posts' );
 
-		$problems = ( new Verifier( new NullProvider(), new NavSeeder( new NullProvider() ) ) )->verify( $m, $this->desired( $m ), [ 'home|' => $id ], new MediaMap( [] ) );
+		$problems = ( new Verifier( new NullProvider(), new NavSeeder( new NullProvider() ) ) )->verify( $m, $this->desired( $m ), [ 'home|' => $id ], new MediaMap( [] ), [], [ 'home|' ] );
 
 		$this->assertStringContainsString( 'front page', implode( "\n", $problems ) );
 	}
 
+	public function test_drift_on_an_existing_page_is_not_a_problem() {
+		// A person renamed, moved and trashed the page and chose another front
+		// page. The seeder did not create it in this run, so none of that is
+		// its business.
+		$m      = $this->manifest();
+		$parent = self::factory()->post->create( [ 'post_type' => 'page' ] );
+		$id     = self::factory()->post->create( [ 'post_type' => 'page', 'post_name' => 'startseite', 'post_title' => 'Home', 'post_parent' => $parent ] );
+		update_post_meta( $id, Meta::KEY, 'home' );
+		wp_trash_post( $id );
+		update_option( 'show_on_front', 'posts' );
+
+		$problems = ( new Verifier( new NullProvider(), new NavSeeder( new NullProvider() ) ) )->verify( $m, $this->desired( $m ), [ 'home|' => $id ], new MediaMap( [] ) );
+
+		$this->assertSame( [], $problems );
+	}
+
 	public function test_a_child_whose_parent_has_no_post_is_a_problem() {
 		// The expectation used to fall back to 0 when the parent was missing, so
 		// a child sitting at the site root compared equal to it and the check
@@ -83,7 +99,7 @@ class VerifierTest extends WP_UnitTestCase {
 		$child = self::factory()->post->create( [ 'post_type' => 'page', 'post_name' => 'pricing', 'post_title' => 'Pricing', 'post_parent' => 0 ] );
 		update_post_meta( $child, Meta::KEY, 'guide/pricing' );
 
-		$problems = ( new Verifier( new NullProvider(), new NavSeeder( new NullProvider() ) ) )->verify( $m, $this->desired( $m ), [ 'guide/pricing|' => $child ], new MediaMap( [] ) );
+		$problems = ( new Verifier( new NullProvider(), new NavSeeder( new NullProvider() ) ) )->verify( $m, $this->desired( $m ), [ 'guide/pricing|' => $child ], new MediaMap( [] ), [], [ 'guide/pricing|' ] );
 
 		$this->assertStringContainsString( 'landed at the site root', implode( "\n", $problems ) );
 	}
diff --git a/plugin/tests/polylang/ApplierTranslationTest.php b/plugin/tests/polylang/ApplierTranslationTest.php
index 108123c..62a8eab 100644
--- a/plugin/tests/polylang/ApplierTranslationTest.php
+++ b/plugin/tests/polylang/ApplierTranslationTest.php
@@ -49,9 +49,10 @@ class ApplierTranslationTest extends PolylangTestCase {
 		);
 
 		$lang    = new PolylangProvider();
-		$desired = ( new DesiredState( $lang, new ContentResolver( new MediaMap( [] ) ) ) )->build( $manifest );
 		$reader  = new StateReader( $lang );
-		$plan    = ( new Differ() )->diff( $desired, $reader->read(), $reader->duplicates() );
+		$actual  = $reader->read();
+		$desired = ( new DesiredState( $lang, new ContentResolver( new MediaMap( [] ) ) ) )->build( $manifest, $actual );
+		$plan    = ( new Differ() )->diff( $desired, $actual, $reader->duplicates() );
 
 		return [ ( new Applier( $lang ) )->apply( $plan, $desired ), $lang ];
 	}
@@ -136,10 +137,10 @@ class ApplierTranslationTest extends PolylangTestCase {
 
 		$entry   = new DesiredEntry( 'about', 'en', 'page', 'About', 'about', null, '<p>x</p>', false, false, 0, [], ContentHash::compute( 'About', '<p>x</p>' ) );
 		$desired = [ 'about|en' => $entry ];
-		// A structure-only UPDATE on the same already-tagged post, planned
-		// under the 'en' map key — models a post whose Polylang language
-		// disagrees with what the manifest currently declares for it.
-		$item = new PlanItem( PlanItem::UPDATE, PlanItem::KIND_ENTRY, 'about', 'en', $postId, [ 'menu_order' => [ 'from' => 1, 'to' => 0 ] ] );
+		// The same already-tagged post, planned UNCHANGED under the 'en' map
+		// key — models a post whose Polylang language disagrees with what the
+		// manifest currently declares for it.
+		$item = new PlanItem( PlanItem::UNCHANGED, PlanItem::KIND_ENTRY, 'about', 'en', $postId );
 		$plan = new Plan( [ $item ], [] );
 
 		( new Applier( $lang ) )->apply( $plan, $desired );
@@ -175,9 +176,10 @@ class ApplierTranslationTest extends PolylangTestCase {
 		);
 
 		$lang    = new PolylangProvider();
-		$desired = ( new DesiredState( $lang, new ContentResolver( new MediaMap( [] ) ) ) )->build( $manifest );
 		$reader  = new StateReader( $lang );
-		$plan    = ( new Differ() )->diff( $desired, $reader->read(), $reader->duplicates() );
+		$actual  = $reader->read();
+		$desired = ( new DesiredState( $lang, new ContentResolver( new MediaMap( [] ) ) ) )->build( $manifest, $actual );
+		$plan    = ( new Differ() )->diff( $desired, $actual, $reader->duplicates() );
 
 		$fail = static function ( $maybe_empty, $postarr ) {
 			return ( $postarr['post_name'] ?? '' ) === 'home-fr' ? true : $maybe_empty;
@@ -254,9 +256,10 @@ class ApplierTranslationTest extends PolylangTestCase {
 			);
 
 			$lang    = new PolylangProvider();
-			$desired = ( new DesiredState( $lang, new ContentResolver( new MediaMap( [] ) ) ) )->build( $manifest );
 			$reader  = new StateReader( $lang );
-			$plan    = ( new Differ() )->diff( $desired, $reader->read(), $reader->duplicates() );
+			$actual  = $reader->read();
+			$desired = ( new DesiredState( $lang, new ContentResolver( new MediaMap( [] ) ) ) )->build( $manifest, $actual );
+			$plan    = ( new Differ() )->diff( $desired, $actual, $reader->duplicates() );
 			$applied = ( new Applier( $lang ) )->apply( $plan, $desired );
 		} finally {
 			remove_filter( 'wp_unique_post_slug', $sharePostSlug, 10 );
@@ -269,4 +272,55 @@ class ApplierTranslationTest extends PolylangTestCase {
 		);
 		$this->assertSame( [], $applied->errors, 'A shared slug that lands is not a collision to report.' );
 	}
+
+	/**
+	 * A manifest that adds a language later creates that language's pages and
+	 * links them into the existing groups — and touches nothing else. The
+	 * English page a person rewrote stays exactly as they left it.
+	 */
+	public function test_a_language_added_later_creates_only_its_own_pages() {
+		$run = static function ( array $languages ): array {
+			$manifest = Manifest::fromArray(
+				[
+					'languages' => $languages,
+					'pages'     => [ 'home' => [ 'title' => 'Home', 'content' => '<p>home</p>' ] ],
+				],
+				get_stylesheet_directory()
+			);
+			$lang     = new PolylangProvider();
+			$reader   = new StateReader( $lang );
+			$actual   = $reader->read();
+			$desired  = ( new DesiredState( $lang, new ContentResolver( new MediaMap( [] ) ) ) )->build( $manifest, $actual );
+			$plan     = ( new Differ() )->diff( $desired, $actual, $reader->duplicates() );
+
+			return [ $plan, ( new Applier( $lang ) )->apply( $plan, $desired ), $lang ];
+		};
+		$two = [
+			'en' => [ 'name' => 'English', 'locale' => 'en_US', 'default' => true ],
+			'de' => [ 'name' => 'Deutsch', 'locale' => 'de_DE' ],
+		];
+
+		[ , $first ] = $run( $two );
+		$en          = $first->ids['home|en'];
+		$de          = $first->ids['home|de'];
+		wp_update_post( [ 'ID' => $en, 'post_content' => '<p>edited live</p>' ] );
+		$edited = get_post( $en )->post_content;
+
+		$fr                    = PLL()->model->add_language( [ 'slug' => 'fr', 'name' => 'Français', 'locale' => 'fr_FR', 'flag' => 'fr', 'rtl' => 0, 'term_group' => 2 ] );
+		$this->addedLanguageId = $fr instanceof WP_Error ? null : (int) $fr->term_id;
+		PLL()->model->clean_languages_cache();
+
+		[ $plan, $second, $lang ] = $run( $two + [ 'fr' => [ 'name' => 'Français', 'locale' => 'fr_FR' ] ] );
+
+		$actions = [];
+		foreach ( $plan->items() as $item ) {
+			$actions[ $item->mapKey() ] = $item->action;
+		}
+		ksort( $actions );
+		$this->assertSame( [ 'home|de' => PlanItem::UNCHANGED, 'home|en' => PlanItem::UNCHANGED, 'home|fr' => PlanItem::CREATE ], $actions );
+		$this->assertSame( [], $second->errors );
+		$this->assertSame( $edited, get_post( $en )->post_content, 'the page a person rewrote is untouched' );
+		$this->assertSame( $second->ids['home|fr'], $lang->translationOf( $en, 'fr' ), 'the new language joins the existing group' );
+		$this->assertSame( $de, $lang->translationOf( $en, 'de' ) );
+	}
 }
diff --git a/plugin/tests/wpml/SeedingBehaviorTest.php b/plugin/tests/wpml/SeedingBehaviorTest.php
index 98e8b0d..4d2f11b 100644
--- a/plugin/tests/wpml/SeedingBehaviorTest.php
+++ b/plugin/tests/wpml/SeedingBehaviorTest.php
@@ -92,9 +92,10 @@ class SeedingBehaviorTest extends WpmlTestCase {
 		);
 
 		$lang    = new WpmlProvider();
-		$desired = ( new DesiredState( $lang, new ContentResolver( new MediaMap( [] ) ) ) )->build( $manifest );
 		$reader  = new StateReader( $lang );
-		$plan    = ( new Differ() )->diff( $desired, $reader->read(), $reader->duplicates() );
+		$actual  = $reader->read();
+		$desired = ( new DesiredState( $lang, new ContentResolver( new MediaMap( [] ) ) ) )->build( $manifest, $actual );
+		$plan    = ( new Differ() )->diff( $desired, $actual, $reader->duplicates() );
 
 		return [ ( new Applier( $lang ) )->apply( $plan, $desired ), $lang ];
 	}
@@ -172,7 +173,7 @@ class SeedingBehaviorTest extends WpmlTestCase {
 
 		$entry   = new \Pediment\Seeder\DesiredEntry( 'about', 'en', 'page', 'About', 'about', null, '<p>x</p>', false, false, 0, [], ContentHash::compute( 'About', '<p>x</p>' ) );
 		$desired = [ 'about|en' => $entry ];
-		$item    = new PlanItem( PlanItem::UPDATE, PlanItem::KIND_ENTRY, 'about', 'en', $postId, [ 'menu_order' => [ 'from' => 1, 'to' => 0 ] ] );
+		$item    = new PlanItem( PlanItem::UNCHANGED, PlanItem::KIND_ENTRY, 'about', 'en', $postId );
 		$plan    = new \Pediment\Seeder\Plan( [ $item ], [] );
 
 		( new Applier( $lang ) )->apply( $plan, $desired );
PATCH
```

The RunnerTest patch adds three tests: `test_a_reseed_never_updates_an_existing_page` (S9 for pages and the front-page option; Task 2 widens it to menus), `test_a_page_a_person_renamed_keeps_its_slug_and_the_seed_stays_green` (Review Focus 2) and `test_a_missing_pattern_for_an_existing_page_is_not_an_error` (Review Focus 5). It replaces `test_a_client_edit_survives_a_content_release` and turns the typo'd-media test into "reported when the page is created" (D3).

Replace `plugin/tests/e2e/seeding.spec.ts` with:

```ts
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

	// A post no menu links to, so the edit cannot ripple into a nav. Title and
	// slug only: they restore exactly with `wp post update`, while restoring
	// block markup through WP-CLI would run it through KSES. PHPUnit covers
	// content (RunnerTest::test_a_reseed_never_updates_existing_content).
	test( 'a re-seed never changes a post a person edited', async () => {
		const id = wp( `post list --post_type=post --name=sample-note-two --field=ID` );
		const title = wp( `post get ${ id } --field=post_title` );
		wp( `post update ${ id } --post_title='Edited live' --post_name=edited-live` );

		try {
			const plan = wp( `pediment seed --dry-run` );
			expect( plan ).toContain( '0 to write' );
			expect( plan ).not.toContain( 'protected' );

			wp( `pediment seed` );
			expect( wp( `post get ${ id } --field=post_title` ) ).toBe( 'Edited live' );
			expect( wp( `post get ${ id } --field=post_name` ) ).toBe( 'edited-live' );
		} finally {
			wp( `post update ${ id } --post_title='${ title }' --post_name=sample-note-two` );
		}
	} );
} );
```

In `plugin/tests/e2e/multilingual.spec.ts`, the untranslated-posts notice now belongs to the run that creates a page (D3):

```bash
git apply <<'PATCH'
diff --git a/plugin/tests/e2e/multilingual.spec.ts b/plugin/tests/e2e/multilingual.spec.ts
index 0a565c6..e015e76 100644
--- a/plugin/tests/e2e/multilingual.spec.ts
+++ b/plugin/tests/e2e/multilingual.spec.ts
@@ -37,11 +37,11 @@ test.describe( 'multilingual seeding', () => {
 		expect( wp( `pediment seed --dry-run` ) ).toContain( '0 to write' );
 	} );
 
-	test( 'the untranslated posts are reported, not failed', () => {
-		const plan = wp( `pediment seed --dry-run` );
-
-		expect( plan ).toContain( 'TRANSLATIONS' );
-		expect( plan ).toContain( 'sample-insight-one' );
+	test( 'a re-seed does not re-report translations of pages that exist', () => {
+		// Translation notices belong to the run that creates a page; the
+		// seeder never writes that page again. PHPUnit pins the notice on
+		// creation (DesiredStateLanguageTest).
+		expect( wp( `pediment seed --dry-run` ) ).not.toContain( 'TRANSLATIONS' );
 	} );
 
 	test( 'the German page is reachable at its own slug', async ( { page } ) => {
PATCH
```

- [ ] **Step 2: Run the tests to verify they fail**

Run: `npx wp-env run tests-wordpress --env-cwd=wp-content/plugins/pediment-ai ./vendor/bin/phpunit tests/phpunit/Seeder`
Expected: FAIL — among others `ReporterTest` and `ClaimReporterTest` with `TypeError: … Argument #7 ($protectedFields) must be of type array, string given`, `DifferTest::test_an_existing_entry_is_never_changed` (`update` instead of `unchanged`), `DesiredStateTest::test_an_existing_entry_does_not_need_its_pattern` with `ManifestError … acme/gone`, `ApplierTest::test_a_slug_a_person_changed_is_kept` (slug reverted to `contact`).

- [ ] **Step 3: Implement the plan vocabulary**

Replace `plugin/src/Seeder/PlanItem.php` with:

```php
<?php
declare(strict_types=1);

namespace Pediment\Seeder;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class PlanItem {
	public const CREATE    = 'create'; // No post carries this key yet.
	public const RESTORE   = 'restore'; // Exists but trashed.
	public const UPDATE    = 'update'; // At least one field will be written.
	public const UNCHANGED = 'unchanged'; // Exists; the seeder never changes it.
	public const ORPHAN    = 'orphan'; // Carries a seed key the manifest dropped.

	public const CLAIM     = 'claim'; // An unkeyed row will receive this seed key.
	public const NO_MATCH  = 'no-match'; // Nothing to claim; the next seed creates it.
	public const AMBIGUOUS = 'ambiguous'; // More than one candidate; nothing is written.

	public const KIND_ENTRY = 'entry';
	public const KIND_MEDIA = 'media';
	public const KIND_NAV   = 'nav';

	/**
	 * @param array<string,array{from:mixed,to:mixed}> $changes
	 */
	public function __construct(
		public readonly string $action,
		public readonly string $kind,
		public readonly string $key,
		public readonly string $language,
		public readonly int $postId,
		public readonly array $changes = [],
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

Drop `protectedFields` from the three callers that pass it positionally (`Plan::counts()` loses `PROTECTED`, `NavSeeder`'s update item and `Claimer`'s six items lose the empty array before the note):

```bash
git apply <<'PATCH'
diff --git a/plugin/src/Seeder/Claimer.php b/plugin/src/Seeder/Claimer.php
index 87c30ca..521f547 100644
--- a/plugin/src/Seeder/Claimer.php
+++ b/plugin/src/Seeder/Claimer.php
@@ -124,7 +124,6 @@ final class Claimer {
 				$language,
 				0,
 				[],
-				[],
 				sprintf(
 					'%d unclaimed %s posts share the slug "%s" (IDs %s) — claim nothing until one is deleted or re-slugged.',
 					count( $candidates ),
@@ -144,13 +143,12 @@ final class Claimer {
 			$language,
 			$postId,
 			[ 'seed_key' => [ 'from' => null, 'to' => $spec->key ] ],
-			[],
 			sprintf( '%s "%s" (ID %d)', $spec->postType, $slug, $postId )
 		);
 	}
 
 	private function noMatch( EntrySpec $spec, string $language, string $note ): PlanItem {
-		return new PlanItem( PlanItem::NO_MATCH, PlanItem::KIND_ENTRY, $spec->key, $language, 0, [], [], $note );
+		return new PlanItem( PlanItem::NO_MATCH, PlanItem::KIND_ENTRY, $spec->key, $language, 0, [], $note );
 	}
 
 	/**
@@ -249,7 +247,6 @@ final class Claimer {
 				$language,
 				0,
 				[],
-				[],
 				'no unclaimed navigation entity — the next seed will create it.'
 			);
 		}
@@ -261,7 +258,6 @@ final class Claimer {
 			$language,
 			0,
 			[],
-			[],
 			sprintf(
 				'%d unclaimed navigation entities (IDs %s) and none whose slug is "%s" — re-slug the right one, or claim it by hand.',
 				count( $candidates ),
@@ -279,7 +275,6 @@ final class Claimer {
 			$language,
 			$postId,
 			[ 'seed_key' => [ 'from' => null, 'to' => $spec->key ] ],
-			[],
 			sprintf( 'navigation "%s" (ID %d)', (string) get_the_title( $postId ), $postId )
 		);
 	}
diff --git a/plugin/src/Seeder/NavSeeder.php b/plugin/src/Seeder/NavSeeder.php
index 041f025..492d95f 100644
--- a/plugin/src/Seeder/NavSeeder.php
+++ b/plugin/src/Seeder/NavSeeder.php
@@ -94,7 +94,6 @@ final class NavSeeder {
 								'to'   => self::countLinks( $spec->items ),
 							],
 						],
-						[],
 						self::hasMega( $spec )
 							? 'membership is git-owned; mega menu content is kept once edited in the editor'
 							: 'membership is git-owned; editor changes to this menu are reverted'
diff --git a/plugin/src/Seeder/Plan.php b/plugin/src/Seeder/Plan.php
index 5b0add6..96e3cbf 100644
--- a/plugin/src/Seeder/Plan.php
+++ b/plugin/src/Seeder/Plan.php
@@ -55,7 +55,7 @@ final class Plan {
 	/** @return array<string,int> Every action, including the ones with no items. */
 	public function counts(): array {
 		$counts = array_fill_keys(
-			[ PlanItem::CREATE, PlanItem::RESTORE, PlanItem::UPDATE, PlanItem::PROTECTED, PlanItem::UNCHANGED, PlanItem::ORPHAN ],
+			[ PlanItem::CREATE, PlanItem::RESTORE, PlanItem::UPDATE, PlanItem::UNCHANGED, PlanItem::ORPHAN ],
 			0
 		);
 		foreach ( $this->items as $item ) {
PATCH
```

- [ ] **Step 4: Implement the create-only Differ**

Replace `plugin/src/Seeder/Differ.php` with:

```php
<?php
/**
 * Phase 3: desired state x actual state -> a plan.
 *
 * The seeder bootstraps a site; it never maintains one. Per desired entry:
 *
 *   1. no actual row  -> create
 *   2. a row exists   -> unchanged, whatever a person or an MCP tool did to
 *                        its title, content, slug, parent, menu order or
 *                        status (a trashed row stays in the trash, with a note)
 *
 * Rows the manifest no longer declares are reported as orphans and left in
 * place. Duplicate identities and post_type mismatches block the whole run.
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
			$errors[]                             = sprintf(
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

			// Restoring a trashed row would be a write to a row that exists;
			// creating a second one would duplicate its identity. Neither: say
			// where it is and what makes the next seed create it again.
			$items[] = new PlanItem(
				PlanItem::UNCHANGED,
				PlanItem::KIND_ENTRY,
				$want->key,
				$want->language,
				$have->id,
				[],
				'trash' === $have->status
					? 'in the trash — left alone; restore it in the editor, or empty the trash and the next seed creates it again'
					: ''
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
				sprintf( '"%s" (ID %d) carries a seed key the manifest no longer declares — left in place', $have->title, $have->id )
			);
		}

		return new Plan( $items, $errors );
	}
}
```

- [ ] **Step 5: Resolve patterns only for entries that will be created**

`build()` takes the rows that exist and skips their patterns:

```bash
git apply <<'PATCH'
diff --git a/plugin/src/Seeder/DesiredState.php b/plugin/src/Seeder/DesiredState.php
index 9c30860..e464b96 100644
--- a/plugin/src/Seeder/DesiredState.php
+++ b/plugin/src/Seeder/DesiredState.php
@@ -28,11 +28,13 @@ final class DesiredState {
 	) {}
 
 	/**
+	 * @param array<string,mixed> $actual Rows that already exist, keyed "key|language" as
+	 *                                    StateReader::read() returns them. Only the keys are read.
 	 * @return array<string,DesiredEntry>
 	 *
-	 * @throws ManifestError When an entry's pattern is not registered.
+	 * @throws ManifestError When the pattern of an entry that will be created is not registered.
 	 */
-	public function build( Manifest $manifest ): array {
+	public function build( Manifest $manifest, array $actual = [] ): array {
 		$desired               = [];
 		$this->undeclaredMedia = [];
 		$this->missingTitles   = [];
@@ -42,14 +44,21 @@ final class DesiredState {
 
 		foreach ( $this->lang->languages() as $language ) {
 			foreach ( $manifest->entriesInDependencyOrder() as $spec ) {
-				$title   = $spec->titleFor( $language, $default );
-				$content = $this->resolver->resolve( $spec, $language, $default );
+				$title  = $spec->titleFor( $language, $default );
+				$exists = isset( $actual[ $spec->key . '|' . $language ] );
+
+				// A row that exists is never written again, so its pattern is
+				// not read: a live-first theme may drop the pattern file of a
+				// page that now lives only on the site, and that must not block
+				// the run. Its content stays '' and nothing reads it.
+				$content = $exists ? '' : $this->resolver->resolve( $spec, $language, $default );
 
 				// A non-default language rendering the default language's title is
 				// a translation nobody wrote yet. Not an error — the page is real
 				// and navigable — but silence here is how a five-language site
-				// ships three languages of English.
-				if ( $language !== $default && '' !== $language && ! isset( $spec->translations[ $language ]['title'] ) ) {
+				// ships three languages of English. Only a page this run creates
+				// can still get the declared title.
+				if ( ! $exists && $language !== $default && '' !== $language && ! isset( $spec->translations[ $language ]['title'] ) ) {
 					$this->missingTitles[] = sprintf(
 						'%s (%s): no title declared — the page carries the default language title "%s".',
 						$spec->key,
@@ -75,13 +84,16 @@ final class DesiredState {
 
 				$desired[ $entry->id() ] = $entry;
 
+				if ( $exists ) {
+					continue;
+				}
+
 				// The resolver records what it could not resolve per call, so it
 				// has to be read here or it is overwritten by the next entry.
 				// Keys the manifest DOES declare are the fresh-site case — media
 				// simply has not been applied yet, and the MediaSeeder/Verifier
 				// own that. A key nobody declares is a typo that can never
-				// resolve: the literal sentinel lands in a live page and gets
-				// hashed as if it were correct.
+				// resolve: the literal sentinel would land in the new page.
 				$undeclared = array_values( array_diff( $this->resolver->unresolvedMediaKeys(), $declared ) );
 				if ( [] !== $undeclared ) {
 					$this->undeclaredMedia[ $entry->id() ] = $undeclared;
PATCH
```

- [ ] **Step 6: Apply creates only**

Replace `plugin/src/Seeder/ApplyResult.php` with:

```php
<?php
declare(strict_types=1);

namespace Pediment\Seeder;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class ApplyResult {
	/**
	 * @param array<string,int> $ids     mapKey => post ID for every desired entry that exists after the run.
	 * @param string[]          $errors
	 * @param string[]          $created mapKeys of the entries this run created.
	 */
	public function __construct(
		public readonly array $ids = [],
		public readonly array $errors = [],
		public readonly array $created = []
	) {}
}
```

Replace `plugin/src/Seeder/Applier.php` with:

```php
<?php
/**
 * Phase 4: apply the plan. Creates missing entries; never writes to a row
 * that exists (docs/seeding.md, "Bootstrap only").
 *
 * Rules that are not obvious and cost a lot when missed:
 *   - wp_slash() before every write: wp_insert_post/wp_update_post un-slash
 *     post_content, which corrupts block-attribute JSON (WORDPRESS_TRAPS.md).
 *   - KSES is suspended: under WP-CLI there is no user, so kses_init_filters()
 *     is active and mangles block comments. Seeded content is git-authored, not
 *     user input.
 *   - The language is set in the same write as creation, never after (8593c73).
 *   - A row that exists may still have NO language at all — the migration
 *     case, where a page predates this seeder's language tagging.
 *     repairLanguage() tags it if and only if it is currently untagged; it
 *     never re-tags or moves a post that already carries one. That and the
 *     translation-group link are the only things the seeder does to a row it
 *     did not create in this run.
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

		$ids     = [];
		$errors  = [];
		$created = [];

		// Existing rows first, so a new child resolves a parent that already exists.
		foreach ( $plan->byKind( PlanItem::KIND_ENTRY ) as $item ) {
			if ( $item->postId > 0 && PlanItem::ORPHAN !== $item->action ) {
				$ids[ $item->mapKey() ] = $item->postId;
			}
		}

		$kses = Kses::suspend();

		try {
			// Plan order is dependency order per language, so a parent created in
			// this run exists before its children are inserted.
			foreach ( $plan->byAction( PlanItem::CREATE ) as $item ) {
				if ( PlanItem::KIND_ENTRY !== $item->kind ) {
					continue;
				}
				$entry = $desired[ $item->mapKey() ] ?? null;
				if ( ! $entry instanceof DesiredEntry ) {
					continue;
				}

				$postId = $this->create( $entry, $ids, $errors );
				if ( $postId > 0 ) {
					$ids[ $item->mapKey() ] = $postId;
					$created[]              = $item->mapKey();
					$this->applyTerms( $postId, $entry, $errors );
					$this->assertSlug( $postId, $entry, $errors );
				}
			}
		} finally {
			Kses::restore( $kses );
		}

		$this->repairLanguage( $plan, $desired, $ids );

		$this->linkTranslationGroups( $ids );

		$this->applyReadingOptions( $desired, $ids, $created );

		return new ApplyResult( $ids, $errors, $created );
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

		// A translation may deliberately share its default-language sibling's
		// slug (/en/home and /de/home both `home`). The insert above ran
		// wp_unique_post_slug BEFORE the post had a Polylang language, so
		// Polylang Pro's share-slug filter could not yet tell the collision
		// was cross-language and WordPress uniquified it to `home-2`. Now that
		// the language is set, re-assert the declared slug: under Pro it lands
		// the shared slug; under Free (no share-slug filter) it re-uniquifies
		// exactly as the bare insert did, so this is a no-op there. Guarded so
		// the overwhelming majority of entries — whose slug never collided —
		// take no second write.
		if ( get_post_field( 'post_name', $postId ) !== $entry->slug ) {
			wp_update_post( wp_slash( [ 'ID' => $postId, 'post_name' => $entry->slug ] ) );
		}

		update_post_meta( $postId, Meta::KEY, $entry->key );
		$this->recordHashes( $postId, $entry );

		return $postId;
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
			if ( ! taxonomy_exists( $taxonomy ) ) {
				// Silence here would be indistinguishable from success: a typo'd
				// taxonomy, or one whose post type has not registered yet, would
				// produce a clean run with no terms assigned.
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
	 * success. The seeder never touches the row again, so this run is the only
	 * chance to say the new page did not get the URL the manifest declares.
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
	 * Known consequence, not a bug fixed here: $ids excludes ORPHAN items
	 * (mapKeys the current manifest no longer generates) even when their
	 * postId is still valid, and DesiredState crosses every seed key with
	 * every configured language. So dropping a language from both the
	 * manifest and Polylang's own config in the same run — passing the
	 * Runner's gate because the two sets still match — turns every post in
	 * that language into an ORPHAN everywhere at once, drops it out of every
	 * key's map here, and pll_save_post_translations() then unlinks it from
	 * its group site-wide, even though the plan reports those posts as left
	 * in place. See docs/BACKLOG.md (Medium, step-4 Task 10 review).
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
			// array_pad(explode('|', ...), 2, '') does not truncate if a seed key
			// or language code ever contained '|' itself — not reachable today
			// (both come from manifest identifiers), flagged in case a future
			// caller feeds this the same shape with looser input.
			[ $key, $language ] = array_pad( explode( '|', (string) $mapKey ), 2, '' );
			if ( '' === $language || $postId <= 0 ) {
				continue;
			}
			$byKey[ $key ][ $language ] = $postId;
		}

		foreach ( $byKey as $map ) {
			// A map missing even one configured language must never reach
			// linkTranslations(): pll_save_post_translations() REPLACES the
			// whole group, so Polylang's own save_translations() diffs this
			// partial map against the group's CURRENT membership and unlinks
			// whichever language got left out — even though that language's
			// post never changed. count($map) >= 2 let a 2-of-3 map through
			// on an ordinary single-language write failure; only "the map
			// covers every configured language" is safe. This does not
			// change the documented coordinated-removal case just above:
			// there, $languages shrinks along with the map, so the count
			// still matches — that consequence is intentionally left as is.
			if ( count( $map ) !== count( $languages ) ) {
				continue;
			}
			$this->lang->linkTranslations( $map );
		}
	}

	/**
	 * Tag a pre-existing post that has no language at all.
	 *
	 * Only rows that exist reach here (UNCHANGED items) — a CREATE already
	 * tags its post in the same write (see the class docblock). An existing
	 * row may predate this seeder's language tagging entirely: the
	 * existing-site migration case. hasLanguage() is the only check — a post
	 * that already carries a (possibly different) language is left exactly
	 * as it is, because re-tagging or moving it is an editorial decision,
	 * not this engine's to make.
	 *
	 * A no-op on a monolingual site: NullProvider::hasLanguage() always
	 * returns true.
	 *
	 * @param array<string,DesiredEntry> $desired
	 * @param array<string,int>          $ids
	 */
	private function repairLanguage( Plan $plan, array $desired, array $ids ): void {
		foreach ( $plan->byKind( PlanItem::KIND_ENTRY ) as $item ) {
			if ( PlanItem::CREATE === $item->action || PlanItem::ORPHAN === $item->action ) {
				continue;
			}
			$postId = $ids[ $item->mapKey() ] ?? 0;
			if ( $postId <= 0 ) {
				continue;
			}
			$entry = $desired[ $item->mapKey() ] ?? null;
			if ( ! $entry instanceof DesiredEntry ) {
				continue;
			}
			if ( ! $this->lang->hasLanguage( $postId ) ) {
				$this->lang->setLanguage( $postId, $entry->language );
			}
		}
	}

	/**
	 * Point the front page and the posts page at their entries — only when the
	 * entry was created in this run, or while the site has no such page yet
	 * (option 0). A choice a person made later in Settings → Reading is never
	 * reverted.
	 *
	 * @param array<string,DesiredEntry> $desired
	 * @param array<string,int>          $ids
	 * @param string[]                   $created Map keys created in this run.
	 */
	private function applyReadingOptions( array $desired, array $ids, array $created ): void {
		$default = $this->lang->defaultLanguage();

		foreach ( $desired as $mapKey => $entry ) {
			if ( $entry->language !== $default || ! isset( $ids[ $mapKey ] ) ) {
				continue;
			}
			$isNew = in_array( $mapKey, $created, true );

			if ( $entry->frontPage && ( $isNew || 0 === (int) get_option( 'page_on_front' ) ) ) {
				update_option( 'show_on_front', 'page' );
				update_option( 'page_on_front', $ids[ $mapKey ] );
			}
			if ( $entry->postsPage && ( $isNew || 0 === (int) get_option( 'page_for_posts' ) ) ) {
				update_option( 'page_for_posts', $ids[ $mapKey ] );
			}
		}
	}
}
```

- [ ] **Step 7: Verify drift only for created entries; wire the Runner; report without "protected"**

```bash
git apply <<'PATCH'
diff --git a/plugin/src/Seeder/Reporter.php b/plugin/src/Seeder/Reporter.php
index 6ccf7e1..d019236 100644
--- a/plugin/src/Seeder/Reporter.php
+++ b/plugin/src/Seeder/Reporter.php
@@ -36,15 +36,7 @@ final class Reporter {
 			$lines[] = '';
 			$lines[] = $heading;
 			foreach ( $items as $item ) {
-				$described = self::describe( $item );
-				$lines[]   = sprintf( '  %-11s %-16s %s', $item->action, $item->key, $described );
-
-				// When describe() already fell back to the note, the fields line
-				// would repeat that same sentence once per protected field — three
-				// identical sentences for the commonest shape on a live site.
-				if ( [] !== $item->protectedFields && $described !== $item->note ) {
-					$lines[] = sprintf( '              ^ protected: %s (%s)', implode( ', ', array_keys( $item->protectedFields ) ), $item->note );
-				}
+				$lines[] = sprintf( '  %-11s %-16s %s', $item->action, $item->key, self::describe( $item ) );
 			}
 		}
 
@@ -146,20 +138,18 @@ final class Reporter {
 		return implode( "\n", $lines );
 	}
 
+	/**
+	 * "N to write," is a contract, not wording: the seed-check action and
+	 * client CI grep for "0 to write," to prove a seed converged. Keep both
+	 * the words and the comma.
+	 */
 	public static function summaryLine( RunResult $result ): string {
-		$counts    = $result->plan->counts();
-		$writes    = ( $counts[ PlanItem::CREATE ] ?? 0 ) + ( $counts[ PlanItem::UPDATE ] ?? 0 ) + ( $counts[ PlanItem::RESTORE ] ?? 0 );
-		$protected = 0;
-		foreach ( $result->plan->items() as $item ) {
-			if ( [] !== $item->protectedFields ) {
-				++$protected;
-			}
-		}
+		$counts = $result->plan->counts();
+		$writes = count( array_filter( $result->plan->items(), static fn( PlanItem $item ): bool => $item->writes() ) );
 
 		return sprintf(
-			'%d to write, %d protected, %d orphan, %d unchanged.',
+			'%d to write, %d orphan, %d unchanged.',
 			$writes,
-			$protected,
 			$counts[ PlanItem::ORPHAN ] ?? 0,
 			$counts[ PlanItem::UNCHANGED ] ?? 0
 		);
diff --git a/plugin/src/Seeder/Runner.php b/plugin/src/Seeder/Runner.php
index daa523e..fc787d2 100644
--- a/plugin/src/Seeder/Runner.php
+++ b/plugin/src/Seeder/Runner.php
@@ -65,14 +65,15 @@ final class Runner {
 		// plan — media, entries and navs — is known before anything is written.
 		// Applying media first would mean an errored plan still left attachments
 		// and a changed site logo behind while reporting "nothing was applied".
+		$actual       = $reader->read();
 		$previewState = new DesiredState( $this->lang, new ContentResolver( $mediaSeeder->map( $manifest ) ) );
 		try {
-			$preview = $previewState->build( $manifest );
+			$preview = $previewState->build( $manifest, $actual );
 		} catch ( ManifestError $e ) {
 			return new RunResult( $mediaPlan, false, $manifest->path(), [ $e->getMessage() ] );
 		}
 
-		$entryPlan = ( new Differ() )->diff( $preview, $reader->read(), $reader->duplicates() );
+		$entryPlan = ( new Differ() )->diff( $preview, $actual, $reader->duplicates() );
 		$entryIds  = $this->resolvedIds( $entryPlan );
 		$plan      = Plan::merge( $mediaPlan, $entryPlan, $navSeeder->plan( $manifest, $entryIds ) );
 
@@ -86,14 +87,15 @@ final class Runner {
 		$mediaMap    = $mediaSeeder->apply( $mediaPlan, $manifest );
 		$mediaErrors = $mediaSeeder->errors();
 
-		$state = new DesiredState( $this->lang, new ContentResolver( $mediaMap ) );
+		$actual = $reader->read();
+		$state  = new DesiredState( $this->lang, new ContentResolver( $mediaMap ) );
 		try {
-			$desired = $state->build( $manifest );
+			$desired = $state->build( $manifest, $actual );
 		} catch ( ManifestError $e ) {
 			return new RunResult( $plan, true, $manifest->path(), array_merge( $mediaErrors, [ $e->getMessage() ] ) );
 		}
 
-		$entryPlan = ( new Differ() )->diff( $desired, $reader->read(), $reader->duplicates() );
+		$entryPlan = ( new Differ() )->diff( $desired, $actual, $reader->duplicates() );
 		$applied   = ( new Applier( $this->lang ) )->apply( $entryPlan, $desired );
 
 		// Nav links need the resolved page IDs, so nav is planned again against them.
@@ -116,7 +118,7 @@ final class Runner {
 		// Phase 5 runs even when something failed: an operator debugging a
 		// partial apply needs to know what actually landed.
 		$problems = array_merge(
-			( new Verifier( $this->lang, $navSeeder ) )->verify( $manifest, $desired, $applied->ids, $mediaMap, $navIds ),
+			( new Verifier( $this->lang, $navSeeder ) )->verify( $manifest, $desired, $applied->ids, $mediaMap, $navIds, $applied->created ),
 			$this->undeclaredMediaProblems( $state )
 		);
 		$errors   = array_values( array_unique( array_merge( $mediaErrors, $applied->errors, $navSeeder->errors() ) ) );
@@ -126,9 +128,10 @@ final class Runner {
 
 	/**
 	 * A pattern referencing a media key nobody declared resolves to a literal
-	 * sentinel (or a bare `0`) that gets written into a live page and hashed as
-	 * if it were correct. Nothing else in the engine notices: the media plan has
-	 * no such key and the Verifier only walks declared media.
+	 * sentinel (or a bare `0`) that gets written into the page this run
+	 * creates. Nothing else in the engine notices: the media plan has no such
+	 * key and the Verifier only walks declared media. Reported once, when the
+	 * page is created — the seeder never reads that pattern again.
 	 *
 	 * @return string[]
 	 */
diff --git a/plugin/src/Seeder/Verifier.php b/plugin/src/Seeder/Verifier.php
index 826d635..26dbb64 100644
--- a/plugin/src/Seeder/Verifier.php
+++ b/plugin/src/Seeder/Verifier.php
@@ -23,12 +23,19 @@ final class Verifier {
 	public function __construct( private LanguageProvider $lang, private NavSeeder $navSeeder ) {}
 
 	/**
+	 * Identity, language, media and post types are checked for every row.
+	 * Everything a person may change after the seeder created a row — slug,
+	 * parent, trash, the reading settings — is checked only for the rows this
+	 * run created: on an existing row it is that person's decision, and the
+	 * seeder never reverts it.
+	 *
 	 * @param array<string,DesiredEntry> $desired
 	 * @param array<string,int>          $ids
-	 * @param array<string,int>          $navIds navKey|language => post ID
+	 * @param array<string,int>          $navIds         navKey|language => post ID
+	 * @param string[]                   $createdEntries Entry map keys created in this run.
 	 * @return string[]
 	 */
-	public function verify( Manifest $manifest, array $desired, array $ids, MediaMap $media, array $navIds = [] ): array {
+	public function verify( Manifest $manifest, array $desired, array $ids, MediaMap $media, array $navIds = [], array $createdEntries = [] ): array {
 		$problems = [];
 
 		foreach ( $desired as $mapKey => $entry ) {
@@ -43,19 +50,6 @@ final class Verifier {
 				$problems[] = sprintf( '%s: post ID %d does not exist.', $mapKey, $postId );
 				continue;
 			}
-			// Only trash is a problem: `draft` and `pending` are editorial states
-			// a client is entitled to set, and the Differ deliberately leaves them.
-			if ( 'trash' === $post->post_status ) {
-				$problems[] = sprintf( '%s: post %d is in the trash.', $mapKey, $postId );
-			}
-			if ( $post->post_name !== $entry->slug ) {
-				$problems[] = sprintf(
-					'%s: slug is "%s" but the manifest says "%s" (WordPress uniquifies colliding slugs).',
-					$mapKey,
-					$post->post_name,
-					$entry->slug
-				);
-			}
 			if ( (string) get_post_meta( $postId, Meta::KEY, true ) !== $entry->key ) {
 				$problems[] = sprintf( '%s: post %d is missing its seed key.', $mapKey, $postId );
 			}
@@ -75,6 +69,22 @@ final class Verifier {
 				);
 			}
 
+			if ( ! in_array( $mapKey, $createdEntries, true ) ) {
+				continue;
+			}
+
+			if ( 'trash' === $post->post_status ) {
+				$problems[] = sprintf( '%s: post %d is in the trash.', $mapKey, $postId );
+			}
+			if ( $post->post_name !== $entry->slug ) {
+				$problems[] = sprintf(
+					'%s: slug is "%s" but the manifest says "%s" (WordPress uniquifies colliding slugs).',
+					$mapKey,
+					$post->post_name,
+					$entry->slug
+				);
+			}
+
 			$expectedParent = null === $entry->parentKey ? 0 : (int) ( $ids[ $entry->parentKey . '|' . $entry->language ] ?? 0 );
 			if ( null !== $entry->parentKey && 0 === $expectedParent ) {
 				// Falling back to 0 made this check unfailable: a child whose
diff --git a/plugin/wp-cli/SeedCommand.php b/plugin/wp-cli/SeedCommand.php
index 291df37..3f20d9d 100644
--- a/plugin/wp-cli/SeedCommand.php
+++ b/plugin/wp-cli/SeedCommand.php
@@ -72,14 +72,13 @@ final class SeedCommand {
 		$items = [];
 		foreach ( $result->plan->items() as $item ) {
 			$items[] = [
-				'kind'      => $item->kind,
-				'action'    => $item->action,
-				'key'       => $item->key,
-				'language'  => $item->language,
-				'post_id'   => $item->postId,
-				'changes'   => array_keys( $item->changes ),
-				'protected' => array_keys( $item->protectedFields ),
-				'note'      => $item->note,
+				'kind'     => $item->kind,
+				'action'   => $item->action,
+				'key'      => $item->key,
+				'language' => $item->language,
+				'post_id'  => $item->postId,
+				'changes'  => array_keys( $item->changes ),
+				'note'     => $item->note,
 			];
 		}
 
@@ -89,8 +88,8 @@ final class SeedCommand {
 				'ok'       => $result->ok(),
 				'manifest' => $result->manifestPath,
 				'counts'   => $result->plan->counts(),
-				// Counts alone cannot answer "which pages are protected?", which
-				// is the question anyone scripting against --json is asking.
+				// Counts alone cannot answer "which pages will be created?",
+				// which is the question anyone scripting against --json asks.
 				'items'    => $items,
 				'errors'   => $result->errors,
 				'problems' => $result->problems,
PATCH
```

- [ ] **Step 8: Run the tests to verify they pass**

```bash
npx wp-env run tests-wordpress --env-cwd=wp-content/plugins/pediment-ai ./vendor/bin/phpunit
npx wp-env run tests-wordpress --env-cwd=wp-content/plugins/pediment-ai ./vendor/bin/phpunit -c phpunit-polylang.xml.dist
composer lint -d plugin
```

Expected: both suites OK (the Polylang suite includes the new language-added-later test), lint 0 errors. `grep -rn "PROTECTED\|protectedFields" plugin/src plugin/wp-cli plugin/inc plugin/tests` prints nothing.

- [ ] **Step 9: e2e or headless probe**

Try `cd plugin && npm run e2e -- seeding.spec.ts multilingual.spec.ts`. If Playwright cannot run here, run the same checks by hand (the root env is seeded by `tools/dev-bootstrap.mjs`):

```bash
npx wp-env run cli wp pediment seed --dry-run
id=$(npx wp-env run cli wp post list --post_type=post --name=sample-note-two --field=ID | tail -n 1)
title=$(npx wp-env run cli wp post get "$id" --field=post_title | tail -n 1)
npx wp-env run cli wp post update "$id" --post_title='Edited live' --post_name=edited-live
npx wp-env run cli wp pediment seed
npx wp-env run cli wp post get "$id" --field=post_name
npx wp-env run cli wp post update "$id" --post_title="$title" --post_name=sample-note-two
npx wp-env run cli wp pediment seed --dry-run
```

Expected: both dry runs end with `0 to write, 0 orphan, …` and contain no `protected`; the seed leaves `edited-live`; the multilingual dry run has no `TRANSLATIONS` block. Report which path you used.

- [ ] **Step 10: Commit**

```bash
git add plugin/src/Seeder/PlanItem.php plugin/src/Seeder/Plan.php plugin/src/Seeder/Differ.php plugin/src/Seeder/DesiredState.php plugin/src/Seeder/ApplyResult.php plugin/src/Seeder/Applier.php plugin/src/Seeder/Verifier.php plugin/src/Seeder/Runner.php plugin/src/Seeder/Reporter.php plugin/src/Seeder/NavSeeder.php plugin/src/Seeder/Claimer.php plugin/wp-cli/SeedCommand.php plugin/tests/phpunit/Seeder/DifferTest.php plugin/tests/phpunit/Seeder/DesiredStateTest.php plugin/tests/phpunit/Seeder/ApplierTest.php plugin/tests/phpunit/Seeder/RunnerTest.php plugin/tests/phpunit/Seeder/VerifierTest.php plugin/tests/phpunit/Seeder/ReporterTest.php plugin/tests/phpunit/Seeder/ClaimerTest.php plugin/tests/phpunit/Seeder/ClaimReporterTest.php plugin/tests/phpunit/Cli/SeedCommandTest.php plugin/tests/phpunit/Cli/ClaimCommandTest.php plugin/tests/polylang/ApplierTranslationTest.php plugin/tests/wpml/SeedingBehaviorTest.php plugin/tests/e2e/seeding.spec.ts plugin/tests/e2e/multilingual.spec.ts
git commit -m "feat(seeder)!: seed creates missing entries only" -m "A seed run no longer updates, restores or re-slugs a page or post that exists, and sets the front page and posts page only for a new page or an empty setting. Patterns are read only for entries it creates." -m "BREAKING CHANGE: wp pediment seed never changes existing entries. The summary line drops the protected count, and --format=json drops the protected field and the protected count."
```

---

### Task 2: Navigation menus are create-only

`NavSeeder` creates a missing menu and never rewrites one that exists; `MegaBlocks` and `_pediment_seed_mega_hash` go (S1). The Verifier checks a menu's membership and status only when the run created it. The S9 test now covers a menu edit too.

**Files:**
- Modify: `plugin/src/Seeder/NavSeeder.php` (`plan()`, `apply()`, `serialize()`, docblocks, `hasMega()` removed, new `created()` and `leftAloneNote()`), `plugin/src/Seeder/Verifier.php` (nav loop, signature), `plugin/src/Seeder/Runner.php:75-77,99-101,119`, `plugin/src/Seeder/Meta.php:26-33`, `plugin/src/Seeder/PlanItem.php` (`UPDATE`), `plugin/src/Seeder/Plan.php` (`counts()`)
- Delete: `plugin/src/Seeder/MegaBlocks.php`, `plugin/tests/phpunit/Seeder/MegaBlocksTest.php`
- Test: `plugin/tests/phpunit/Seeder/NavSeederTest.php` (rewritten), `RunnerTest.php`, `VerifierTest.php`, `plugin/tests/polylang/NavLanguageTest.php`

**Interfaces:**
- Consumes: `PlanItem` (7 parameters), `Verifier::verify(…, array $createdEntries = [])` from Task 1.
- Produces:
  - `NavSeeder::plan( Manifest $manifest ): Plan` — per nav and language `create` (changes `items: 0 -> N`) or `unchanged` with a note for a trashed (`in the trash — left alone; …`) or unpublished (`"draft" — left alone; the menu does not render until someone publishes it`) menu; duplicate identities are errors.
  - `NavSeeder::apply( Plan $plan, Manifest $manifest, array $entryIds ): array<string,int>` — creates only; a menu with an unresolved entry is not created and reports `navs.<key>: "<entry>" has no seeded post yet — the menu was not created.`
  - `NavSeeder::created(): string[]` — `navKey|language` map keys the last `apply()` created.
  - `NavSeeder::serialize( NavSpec $spec, string $language, array $entryIds ): string` — no `$current` / `$navId` parameters.
  - `Verifier::verify( Manifest $manifest, array $desired, array $ids, MediaMap $media, array $navIds = [], array $createdEntries = [], array $createdNavs = [] ): string[]`.
  - `PlanItem::UPDATE` and `Meta::MEGA_HASH` no longer exist; `Plan::counts()` keys: `create`, `restore`, `unchanged`, `orphan`.

- [ ] **Step 1: Write the failing tests**

Replace `plugin/tests/phpunit/Seeder/NavSeederTest.php` with:

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

	public function test_serializes_a_language_switcher_item() {
		$seeder = new NavSeeder( new NullProvider() );
		$m      = $this->manifest( [ [ 'entry' => 'home' ], [ 'language_switcher' => true ] ] );

		$markup = $seeder->serialize( $m->navs()['primary'], '', [ 'home|' => 12 ] );

		// NullProvider has no switcher block to emit — a monolingual site's
		// menu shows the ordinary links and nothing where the switcher item was.
		$this->assertStringNotContainsString( 'wp:polylang/navigation-language-switcher', $markup );
		$this->assertStringContainsString( 'wp:navigation-link', $markup );
	}

	public function test_creates_one_entity_per_nav_key() {
		$seeder = new NavSeeder( new NullProvider() );
		$m      = $this->manifest( [ [ 'entry' => 'home' ] ] );
		$ids    = [ 'home|' => self::factory()->post->create( [ 'post_type' => 'page' ] ) ];

		$navIds = $seeder->apply( $seeder->plan( $m ), $m, $ids );

		$nav = get_post( $navIds['primary|'] );
		$this->assertSame( 'wp_navigation', $nav->post_type );
		$this->assertSame( 'publish', $nav->post_status );
		$this->assertSame( 'primary', get_post_meta( $nav->ID, Meta::KEY, true ) );
	}

	public function test_a_manifest_membership_change_never_reaches_an_existing_nav() {
		$seeder = new NavSeeder( new NullProvider() );
		$ids    = [
			'home|'  => self::factory()->post->create( [ 'post_type' => 'page' ] ),
			'about|' => self::factory()->post->create( [ 'post_type' => 'page' ] ),
		];
		$first  = $this->manifest( [ [ 'entry' => 'home' ] ] );
		$navIds = $seeder->apply( $seeder->plan( $first ), $first, $ids );
		$before = get_post( $navIds['primary|'] )->post_content;

		$second = $this->manifest( [ [ 'entry' => 'home' ], [ 'entry' => 'about' ] ] );
		$plan   = $seeder->plan( $second );
		$again  = $seeder->apply( $plan, $second, $ids );

		$this->assertSame( PlanItem::UNCHANGED, $plan->items()[0]->action );
		$this->assertSame( [], $plan->items()[0]->changes );
		$this->assertSame( $navIds['primary|'], $again['primary|'] );
		$this->assertSame( $before, get_post( $navIds['primary|'] )->post_content );
		$this->assertSame( [], $seeder->created() );
	}

	public function test_an_unchanged_nav_is_not_rewritten() {
		$seeder = new NavSeeder( new NullProvider() );
		$m      = $this->manifest( [ [ 'entry' => 'home' ] ] );
		$ids    = [ 'home|' => self::factory()->post->create( [ 'post_type' => 'page' ] ) ];
		$navIds = $seeder->apply( $seeder->plan( $m ), $m, $ids );
		$before = get_post( $navIds['primary|'] )->post_modified_gmt;

		$plan = $seeder->plan( $m );
		$seeder->apply( $plan, $m, $ids );

		$this->assertSame( PlanItem::UNCHANGED, $plan->items()[0]->action );
		$this->assertSame( $before, get_post( $navIds['primary|'] )->post_modified_gmt );
	}

	public function test_a_nav_with_slashes_in_its_urls_is_stored_verbatim() {
		// KSES strips the `\/` escapes out of stored JSON unless the seeder
		// suspends it; the new menu must hold exactly what serialize() wrote.
		$seeder = new NavSeeder( new NullProvider() );
		$m      = $this->manifest( [ [ 'url' => '/contact/us', 'label' => 'Contact' ] ] );

		$navIds = $seeder->apply( $seeder->plan( $m ), $m, [] );

		$this->assertSame( $seeder->serialize( $m->navs()['primary'], '', [] ), get_post( $navIds['primary|'] )->post_content );
	}

	public function test_an_item_whose_entry_is_not_seeded_is_reported() {
		$seeder = new NavSeeder( new NullProvider() );
		$m      = $this->manifest( [ [ 'entry' => 'home' ] ] );

		$seeder->apply( $seeder->plan( $m ), $m, [] );

		$this->assertNotEmpty( $seeder->errors() );
		$this->assertStringContainsString( 'home', $seeder->errors()[0] );
	}

	public function test_a_trashed_nav_stays_in_the_trash_and_is_never_re_created() {
		// Restoring would be a write to a menu that exists; creating a second
		// one would put two navs under one identity, which the very next run
		// reports as fatal. Neither: the plan says where it is.
		$seeder = new NavSeeder( new NullProvider() );
		$m      = $this->manifest( [ [ 'entry' => 'home' ] ] );
		$ids    = [ 'home|' => self::factory()->post->create( [ 'post_type' => 'page' ] ) ];
		$navIds = $seeder->apply( $seeder->plan( $m ), $m, $ids );
		wp_trash_post( $navIds['primary|'] );

		$plan  = $seeder->plan( $m );
		$again = $seeder->apply( $plan, $m, $ids );

		$this->assertSame( PlanItem::UNCHANGED, $plan->items()[0]->action );
		$this->assertStringContainsString( 'in the trash', $plan->items()[0]->note );
		$this->assertSame( $navIds['primary|'], $again['primary|'] );
		$this->assertSame( 'trash', get_post( $navIds['primary|'] )->post_status );
		$this->assertCount( 1, get_posts( [ 'post_type' => 'wp_navigation', 'post_status' => [ 'publish', 'trash' ], 'posts_per_page' => -1, 'fields' => 'ids' ] ) );
	}

	public function test_an_unpublished_nav_is_left_alone_and_says_why() {
		$seeder = new NavSeeder( new NullProvider() );
		$m      = $this->manifest( [ [ 'entry' => 'home' ] ] );
		$ids    = [ 'home|' => self::factory()->post->create( [ 'post_type' => 'page' ] ) ];
		$navIds = $seeder->apply( $seeder->plan( $m ), $m, $ids );
		wp_update_post( [ 'ID' => $navIds['primary|'], 'post_status' => 'draft' ] );

		$plan = $seeder->plan( $m );
		$seeder->apply( $plan, $m, $ids );

		$this->assertSame( PlanItem::UNCHANGED, $plan->items()[0]->action );
		$this->assertStringContainsString( 'does not render', $plan->items()[0]->note );
		$this->assertSame( 'draft', get_post( $navIds['primary|'] )->post_status );
	}

	public function test_two_entities_under_one_key_are_reported() {
		$seeder = new NavSeeder( new NullProvider() );
		$m      = $this->manifest( [ [ 'entry' => 'home' ] ] );
		$ids    = [ 'home|' => self::factory()->post->create( [ 'post_type' => 'page' ] ) ];
		$seeder->apply( $seeder->plan( $m ), $m, $ids );
		$impostor = self::factory()->post->create( [ 'post_type' => 'wp_navigation' ] );
		update_post_meta( $impostor, \Pediment\Seeder\Meta::KEY, 'primary' );

		$plan = $seeder->plan( $m );

		$this->assertTrue( $plan->hasErrors() );
		$this->assertStringContainsString( 'primary', $plan->errors()[0] );
	}

	public function test_an_existing_nav_is_not_checked_for_missing_links() {
		// The seeder no longer manages an existing menu's links, so an entry
		// the menu cannot resolve is not its error to report.
		$seeder = new NavSeeder( new NullProvider() );
		$m      = $this->manifest( [ [ 'entry' => 'home' ] ] );
		$navId  = self::factory()->post->create(
			[ 'post_type' => 'wp_navigation', 'post_content' => $seeder->serialize( $m->navs()['primary'], '', [] ) ]
		);
		update_post_meta( $navId, Meta::KEY, 'primary' );

		$plan = $seeder->plan( $m );
		$seeder->apply( $plan, $m, [] );

		$this->assertSame( PlanItem::UNCHANGED, $plan->items()[0]->action );
		$this->assertSame( [], $seeder->errors() );
	}

	public function test_an_edited_nav_is_never_rewritten() {
		// A person reworked the menu in the Site Editor: dropped a link and
		// added their own. The manifest also changed. The menu stays theirs.
		$seeder = new NavSeeder( new NullProvider() );
		$ids    = [
			'home|'  => self::factory()->post->create( [ 'post_type' => 'page' ] ),
			'about|' => self::factory()->post->create( [ 'post_type' => 'page' ] ),
		];
		$m      = $this->manifest( [ [ 'entry' => 'home' ], [ 'entry' => 'about' ] ] );
		$navId  = $seeder->apply( $seeder->plan( $m ), $m, $ids )['primary|'];
		wp_update_post(
			[
				'ID'           => $navId,
				'post_content' => wp_slash( '<!-- wp:navigation-link {"label":"Shop","url":"/shop/","kind":"custom"} /-->' ),
			]
		);
		$edited = get_post( $navId )->post_content;

		$changed = $this->manifest( [ [ 'entry' => 'about' ], [ 'url' => '/contact', 'label' => 'Contact' ] ] );
		$plan    = $seeder->plan( $changed );
		$seeder->apply( $plan, $changed, $ids );

		$this->assertSame( PlanItem::UNCHANGED, $plan->items()[0]->action );
		$this->assertFalse( $plan->items()[0]->writes() );
		$this->assertSame( $edited, get_post( $navId )->post_content );
		$this->assertSame( [], $seeder->errors() );
	}

	public function test_serialize_has_no_side_effects() {
		$seeder = new NavSeeder( new NullProvider() );
		$m      = $this->manifest( [ [ 'entry' => 'home' ] ] );

		$seeder->serialize( $m->navs()['primary'], '', [] );
		$seeder->serialize( $m->navs()['primary'], '', [] );

		$this->assertSame( [], $seeder->errors(), 'serialize() is a formatter, not a reporter' );
	}

	private function submenuManifest( array $items ): Manifest {
		return Manifest::fromArray(
			[
				'pages' => [
					'home'  => [ 'title' => 'Home', 'content' => '' ],
					'guide' => [ 'title' => 'Guide', 'content' => '' ],
					'faq'   => [ 'title' => 'FAQ', 'content' => '' ],
				],
				'navs'  => [ 'primary' => [ 'title' => 'Primary', 'items' => $items ] ],
			],
			'/tmp/theme'
		);
	}

	public function test_an_item_with_children_serializes_as_a_submenu() {
		$seeder = new NavSeeder( new NullProvider() );
		$m      = $this->submenuManifest(
			[
				[ 'entry' => 'home' ],
				[ 'entry' => 'guide', 'children' => [ [ 'entry' => 'faq' ] ] ],
			]
		);

		$markup = $seeder->serialize( $m->navs()['primary'], '', [ 'home|' => 11, 'guide|' => 12, 'faq|' => 13 ] );

		$this->assertStringContainsString( '<!-- wp:navigation-submenu ', $markup );
		$this->assertStringContainsString( '<!-- /wp:navigation-submenu -->', $markup );
		$this->assertSame( 1, substr_count( $markup, '<!-- wp:navigation-submenu ' ) );
		$this->assertSame( 2, substr_count( $markup, 'wp:navigation-link' ), 'home and faq are links; guide is a submenu' );
		$this->assertStringContainsString( '"id":13', $markup );
	}

	public function test_a_submenu_parent_keeps_the_same_attribute_order_as_a_link() {
		$seeder = new NavSeeder( new NullProvider() );
		$id     = self::factory()->post->create( [ 'post_type' => 'page', 'post_title' => 'Guide' ] );
		$m      = $this->submenuManifest( [ [ 'entry' => 'guide', 'children' => [ [ 'entry' => 'faq' ] ] ] ] );

		$markup = $seeder->serialize( $m->navs()['primary'], '', [ 'guide|' => $id, 'faq|' => 13 ] );

		$this->assertMatchesRegularExpression(
			'/wp:navigation-submenu \{"label":".*?","type":".*?","id":\d+,"kind":"post-type","url":/',
			$markup
		);
	}

	public function test_a_url_item_may_carry_children() {
		$seeder = new NavSeeder( new NullProvider() );
		$m      = $this->submenuManifest(
			[ [ 'url' => '/more', 'label' => 'More', 'children' => [ [ 'entry' => 'faq' ] ] ] ]
		);

		$markup = $seeder->serialize( $m->navs()['primary'], '', [ 'faq|' => 13 ] );

		$this->assertStringContainsString( '"label":"More"', $markup );
		$this->assertStringContainsString( '"kind":"custom"', $markup );
		$this->assertSame( 1, substr_count( $markup, '<!-- wp:navigation-submenu ' ) );
	}

	public function test_an_unresolved_submenu_parent_takes_its_children_with_it() {
		$seeder = new NavSeeder( new NullProvider() );
		$m      = $this->submenuManifest(
			[
				[ 'entry' => 'home' ],
				[ 'entry' => 'guide', 'children' => [ [ 'entry' => 'faq' ] ] ],
			]
		);

		$markup = $seeder->serialize( $m->navs()['primary'], '', [ 'home|' => 11, 'faq|' => 13 ] );

		$this->assertStringNotContainsString( 'navigation-submenu', $markup );
		$this->assertStringNotContainsString( '"id":13', $markup, 'a child without its parent is not promoted to top level' );
		$this->assertSame( 1, substr_count( $markup, 'wp:navigation-link' ) );
	}

	public function test_an_unresolved_child_blocks_creating_the_menu() {
		// serialize() would drop the child, so creating now would ship a
		// shortened menu. The menu waits until the child's page exists.
		$seeder = new NavSeeder( new NullProvider() );
		$guide  = self::factory()->post->create( [ 'post_type' => 'page' ] );
		$full   = $this->submenuManifest( [ [ 'entry' => 'guide', 'children' => [ [ 'entry' => 'faq' ] ] ] ] );

		$navIds = $seeder->apply( $seeder->plan( $full ), $full, [ 'guide|' => $guide ] );

		$this->assertArrayNotHasKey( 'primary|', $navIds );
		$this->assertSame( [], get_posts( [ 'post_type' => 'wp_navigation', 'post_status' => 'any', 'fields' => 'ids' ] ) );
		$this->assertContains(
			'navs.primary: "faq" has no seeded post yet — the menu was not created.',
			$seeder->errors()
		);
	}

	public function test_the_planned_item_count_includes_children() {
		$seeder = new NavSeeder( new NullProvider() );
		$m      = $this->submenuManifest(
			[
				[ 'entry' => 'home' ],
				[ 'entry' => 'guide', 'children' => [ [ 'entry' => 'faq' ] ] ],
			]
		);

		$plan = $seeder->plan( $m );

		$this->assertSame( PlanItem::CREATE, $plan->items()[0]->action );
		$this->assertSame( 3, $plan->items()[0]->changes['items']['to'], 'home + guide + faq' );
	}

	private function megaItems( string $label = 'Products' ): array {
		return [
			[ 'entry' => 'home' ],
			[
				'mega' => [
					'label'   => $label,
					'columns' => [
						[
							'heading' => 'Pages',
							'icon'    => 'bank',
							'links'   => [
								[ 'entry' => 'about', 'description' => 'Who we are' ],
								[ 'label' => 'Savings', 'url' => '/savings/' ],
							],
						],
					],
				],
			],
		];
	}

	public function test_serializes_a_mega_item_with_fixed_key_order() {
		$seeder  = new NavSeeder( new NullProvider() );
		$aboutId = self::factory()->post->create( [ 'post_type' => 'page', 'post_title' => 'About' ] );
		$m       = $this->manifest( $this->megaItems() );

		$markup = $seeder->serialize( $m->navs()['primary'], '', [ 'home|' => 12, 'about|' => $aboutId ] );

		$this->assertStringContainsString(
			'<!-- wp:pediment/mega-menu {"label":"Products","columns":[{"heading":"Pages","icon":"bank","links":['
				. '{"label":"About","description":"Who we are","url":"' . get_permalink( $aboutId ) . '"},'
				. '{"label":"Savings","url":"/savings/"}'
				. ']}]} /-->',
			$markup
		);
		$this->assertStringContainsString( 'wp:navigation-link', $markup, 'ordinary items still serialize around it' );
	}

	public function test_optional_mega_keys_are_omitted_not_emitted_empty() {
		$seeder = new NavSeeder( new NullProvider() );
		$m      = $this->manifest(
			[ [ 'mega' => [ 'label' => 'P', 'columns' => [ [ 'heading' => 'H', 'links' => [ [ 'label' => 'L', 'url' => '/l/' ] ] ] ] ] ] ]
		);

		$markup = $seeder->serialize( $m->navs()['primary'], '', [] );

		$this->assertStringNotContainsString( '"icon"', $markup );
		$this->assertStringNotContainsString( '"description"', $markup );
	}

	public function test_an_unresolved_mega_entry_is_dropped_and_reported() {
		$seeder = new NavSeeder( new NullProvider() );
		$m      = $this->manifest( $this->megaItems() );
		$ids    = [ 'home|' => self::factory()->post->create( [ 'post_type' => 'page' ] ) ]; // 'about' never seeded.

		$markup = $seeder->serialize( $m->navs()['primary'], '', $ids );
		$seeder->apply( $seeder->plan( $m ), $m, $ids );

		$this->assertStringNotContainsString( '"description":"Who we are"', $markup, 'the unresolved link is dropped' );
		$this->assertStringContainsString( '"label":"Savings"', $markup, 'resolvable links stay' );
		$this->assertNotEmpty( $seeder->errors() );
		$this->assertStringContainsString( 'about', $seeder->errors()[0] );
	}

	public function test_a_claimed_nav_is_left_exactly_as_it_is() {
		// A claimed nav carries a seed key and its hand-built content — exactly
		// what Claimer leaves behind. The seed after the claim keeps all of it.
		$seeder   = new NavSeeder( new NullProvider() );
		$m        = $this->manifest( $this->megaItems() );
		$ids      = [
			'home|'  => self::factory()->post->create( [ 'post_type' => 'page' ] ),
			'about|' => self::factory()->post->create( [ 'post_type' => 'page', 'post_title' => 'About' ] ),
		];
		$handmade = '<!-- wp:pediment/mega-menu {"label":"Hand-built","columns":[{"heading":"Mine","links":[{"label":"Keep","url":"/keep/"}]}]} /-->';
		$navId    = self::factory()->post->create(
			[ 'post_type' => 'wp_navigation', 'post_status' => 'publish', 'post_content' => $handmade ]
		);
		update_post_meta( $navId, Meta::KEY, 'primary' );
		$before = get_post( $navId )->post_content;

		$seeder->apply( $seeder->plan( $m ), $m, $ids );

		$this->assertSame( $before, get_post( $navId )->post_content );
	}

	public function test_a_new_mega_nav_carries_no_hash_meta() {
		$seeder = new NavSeeder( new NullProvider() );
		$m      = $this->manifest( $this->megaItems() );
		$ids    = [
			'home|'  => self::factory()->post->create( [ 'post_type' => 'page' ] ),
			'about|' => self::factory()->post->create( [ 'post_type' => 'page', 'post_title' => 'About' ] ),
		];

		$navId = $seeder->apply( $seeder->plan( $m ), $m, $ids )['primary|'];

		$this->assertStringContainsString( 'wp:pediment/mega-menu', get_post( $navId )->post_content );
		$this->assertSame( '', (string) get_post_meta( $navId, '_pediment_seed_mega_hash', true ) );
		$this->assertSame( [ 'primary|' ], $seeder->created() );
	}

	public function test_a_mega_free_manifest_serializes_exactly_as_before() {
		// Locks the byte format menus seeded by earlier versions carry, so a
		// menu created today looks exactly like one created before.
		$seeder  = new NavSeeder( new NullProvider() );
		$homeId  = self::factory()->post->create( [ 'post_type' => 'page', 'post_title' => 'Home' ] );
		$aboutId = self::factory()->post->create( [ 'post_type' => 'page', 'post_title' => 'About' ] );
		$ids     = [ 'home|' => $homeId, 'about|' => $aboutId ];
		$m       = $this->manifest(
			[
				[ 'entry' => 'home' ],
				[ 'url' => '/contact', 'label' => 'Contact' ],
				[ 'entry' => 'about', 'children' => [ [ 'url' => '/faq', 'label' => 'FAQ' ] ] ],
				[ 'language_switcher' => true ],
			]
		);

		$expected = implode(
			"\n",
			[
				'<!-- wp:navigation-link {"label":"Home","type":"page","id":' . $homeId . ',"kind":"post-type","url":"' . get_permalink( $homeId ) . '"} /-->',
				'<!-- wp:navigation-link {"label":"Contact","url":"/contact","kind":"custom"} /-->',
				'<!-- wp:navigation-submenu {"label":"About","type":"page","id":' . $aboutId . ',"kind":"post-type","url":"' . get_permalink( $aboutId ) . '"} -->'
					. "\n" . '<!-- wp:navigation-link {"label":"FAQ","url":"/faq","kind":"custom"} /-->' . "\n"
					. '<!-- /wp:navigation-submenu -->',
				// NullProvider emits no switcher block; a monolingual site's
				// language_switcher item is simply omitted.
			]
		);

		$this->assertSame( $expected, $seeder->serialize( $m->navs()['primary'], '', $ids ) );
	}
}
```

`test_an_edited_nav_is_never_rewritten` pins Review Focus 4.

Apply the `RunnerTest` (S9 now includes a menu edit and a new menu item; an unpublished menu is left alone), `VerifierTest` (an edited existing menu passes; a menu created in this run is checked) and `NavLanguageTest` (`plan()` takes the manifest only) edits:

```bash
git rm -q plugin/tests/phpunit/Seeder/MegaBlocksTest.php
git apply <<'PATCH'
diff --git a/plugin/tests/phpunit/Seeder/RunnerTest.php b/plugin/tests/phpunit/Seeder/RunnerTest.php
index 4a116b5..9e6c488 100644
--- a/plugin/tests/phpunit/Seeder/RunnerTest.php
+++ b/plugin/tests/phpunit/Seeder/RunnerTest.php
@@ -52,20 +52,25 @@ class RunnerTest extends WP_UnitTestCase {
 		$this->assertTrue( $second->ok() );
 	}
 
-	public function test_a_reseed_never_updates_an_existing_page() {
+	public function test_a_reseed_never_updates_existing_content() {
 		$first   = ( new Runner() )->run();
 		$aboutId = $first->ids['about|'];
 		$homeId  = $first->ids['home|'];
+		$navId   = (int) get_posts( [ 'post_type' => 'wp_navigation', 'posts_per_page' => 1, 'fields' => 'ids' ] )[0];
 
-		// A person edits the page and the reading settings in wp-admin.
+		// A person edits the page, the menu and the reading settings in wp-admin.
 		wp_update_post( [ 'ID' => $aboutId, 'post_title' => 'About us', 'post_content' => '<p>written live</p>', 'post_name' => 'ueber-uns', 'menu_order' => 4 ] );
+		wp_update_post( [ 'ID' => $navId, 'post_content' => wp_slash( '<!-- wp:navigation-link {"label":"Shop","url":"/shop/","kind":"custom"} /-->' ) ] );
 		update_option( 'page_on_front', $aboutId );
 		$before    = get_post( $aboutId );
+		$navBefore = get_post( $navId )->post_content;
 		$revisions = count( wp_get_post_revisions( $aboutId ) );
 
-		// The manifest moves on: new copy, title and slug for about, and a new page.
-		$this->manifest['pages']['about'] = [ 'title' => 'About', 'content' => '<p>from git</p>', 'slug' => 'about-git' ];
-		$this->manifest['pages']['team']  = [ 'title' => 'Team', 'content' => '<p>team</p>' ];
+		// The manifest moves on: new copy, title and slug for about, a new page,
+		// and the new page in the menu.
+		$this->manifest['pages']['about']             = [ 'title' => 'About', 'content' => '<p>from git</p>', 'slug' => 'about-git' ];
+		$this->manifest['pages']['team']              = [ 'title' => 'Team', 'content' => '<p>team</p>' ];
+		$this->manifest['navs']['primary']['items'][] = [ 'entry' => 'team' ];
 
 		$second = ( new Runner() )->run();
 
@@ -76,15 +81,23 @@ class RunnerTest extends WP_UnitTestCase {
 		$this->assertSame( 'ueber-uns', $after->post_name );
 		$this->assertSame( 4, $after->menu_order );
 		$this->assertCount( $revisions, wp_get_post_revisions( $aboutId ), 'no write, so no new revision' );
+		$this->assertSame( $navBefore, get_post( $navId )->post_content, 'the menu a person edited stays theirs' );
 		$this->assertSame( $aboutId, (int) get_option( 'page_on_front' ), 'the reading setting a person chose stays' );
 		$this->assertGreaterThan( 0, $second->ids['team|'] ?? 0, 'a page the manifest added is still created' );
+		$this->assertSame( $homeId, $second->ids['home|'] );
 
 		$actions = [];
-		foreach ( $second->plan->byKind( PlanItem::KIND_ENTRY ) as $item ) {
-			$actions[ $item->key ] = $item->action;
+		foreach ( $second->plan->items() as $item ) {
+			$actions[ $item->kind . ':' . $item->key ] = $item->action;
 		}
-		$this->assertSame( [ 'home' => PlanItem::UNCHANGED, 'about' => PlanItem::UNCHANGED, 'team' => PlanItem::CREATE ], $actions );
-		$this->assertSame( $homeId, $second->ids['home|'] );
+		$this->assertSame( PlanItem::UNCHANGED, $actions['entry:home'] );
+		$this->assertSame( PlanItem::UNCHANGED, $actions['entry:about'] );
+		$this->assertSame( PlanItem::UNCHANGED, $actions['nav:primary'] );
+		$this->assertSame( PlanItem::CREATE, $actions['entry:team'] );
+
+		$third = ( new Runner() )->run( [ 'dry_run' => true ] );
+		$this->assertTrue( $third->plan->isEmpty(), 'once the new page exists there is nothing left to write' );
+		$this->assertStringContainsString( '0 to write,', \Pediment\Seeder\Reporter::summaryLine( $third ) );
 	}
 
 	public function test_a_page_a_person_renamed_keeps_its_slug_and_the_seed_stays_green() {
@@ -226,15 +239,16 @@ class RunnerTest extends WP_UnitTestCase {
 		$this->assertTrue( $second->plan->isEmpty(), 'a re-seed of a non-public entry must plan no writes' );
 	}
 
-	public function test_a_client_unpublishing_the_menu_is_reported() {
-		$result = ( new Runner() )->run();
-		$navs   = get_posts( [ 'post_type' => 'wp_navigation', 'posts_per_page' => -1 ] );
+	public function test_a_menu_a_person_unpublished_is_left_alone() {
+		( new Runner() )->run();
+		$navs = get_posts( [ 'post_type' => 'wp_navigation', 'posts_per_page' => -1 ] );
 		wp_update_post( [ 'ID' => $navs[0]->ID, 'post_status' => 'draft' ] );
 
 		$second = ( new Runner() )->run();
 
-		$this->assertFalse( $second->ok(), 'a menu that will not render is not a successful seed' );
-		$this->assertStringContainsString( 'navs.primary', implode( "\n", $second->problems ) );
+		$this->assertTrue( $second->ok(), 'the seeder does not overrule a person who took the menu offline' );
+		$this->assertSame( 'draft', get_post_status( $navs[0]->ID ) );
+		$this->assertStringContainsString( 'does not render', $second->plan->byKind( PlanItem::KIND_NAV )[0]->note );
 	}
 
 	public function test_a_typoed_media_key_is_reported_when_the_page_is_created() {
diff --git a/plugin/tests/phpunit/Seeder/VerifierTest.php b/plugin/tests/phpunit/Seeder/VerifierTest.php
index 66dcef1..92f14d0 100644
--- a/plugin/tests/phpunit/Seeder/VerifierTest.php
+++ b/plugin/tests/phpunit/Seeder/VerifierTest.php
@@ -134,34 +134,38 @@ class VerifierTest extends WP_UnitTestCase {
 		$this->assertStringContainsString( 'logo', implode( "\n", $problems ) );
 	}
 
-	public function test_an_edited_mega_block_passes_verification() {
+	private function navManifest(): Manifest {
+		return Manifest::fromArray(
+			[ 'navs' => [ 'primary' => [ 'title' => 'Primary', 'items' => [ [ 'url' => '/features/', 'label' => 'Features' ] ] ] ] ],
+			'/tmp/theme'
+		);
+	}
+
+	public function test_an_edited_existing_nav_passes_verification() {
 		$seeder = new NavSeeder( new NullProvider() );
-		$m      = Manifest::fromArray(
+		$m      = $this->navManifest();
+		$navIds = $seeder->apply( $seeder->plan( $m ), $m, [] );
+		wp_update_post(
 			[
-				'pages' => [ 'home' => [ 'title' => 'Home', 'content' => '<p>h</p>' ] ],
-				'navs'  => [
-					'primary' => [
-						'title' => 'Primary',
-						'items' => [
-							[ 'url' => '/features/', 'label' => 'Features' ],
-							[ 'mega' => [ 'label' => 'Products', 'columns' => [ [ 'heading' => 'H', 'links' => [ [ 'label' => 'L', 'url' => '/l/' ] ] ] ] ] ],
-						],
-					],
-				],
-			],
-			'/tmp/theme'
+				'ID'           => $navIds['primary|'],
+				'post_content' => wp_slash( '<!-- wp:navigation-link {"label":"Shop","url":"/shop/","kind":"custom"} /-->' ),
+				'post_status'  => 'draft',
+			]
 		);
 
-		$navIds = $seeder->apply( $seeder->plan( $m, [] ), $m, [] );
-		$navId  = $navIds['primary|'];
-		$edited = str_replace( '"label":"L"', '"label":"Edited"', get_post( $navId )->post_content );
-		wp_update_post( [ 'ID' => $navId, 'post_content' => wp_slash( $edited ) ] );
+		$problems = ( new Verifier( new NullProvider(), $seeder ) )->verify( $m, [], [], new MediaMap( [] ), $navIds );
 
-		$id = self::factory()->post->create( [ 'post_type' => 'page', 'post_name' => 'home', 'post_title' => 'Home' ] );
-		update_post_meta( $id, Meta::KEY, 'home' );
+		$this->assertSame( [], $problems, 'a menu this run did not create belongs to the person who edited it' );
+	}
+
+	public function test_a_nav_created_this_run_is_checked_for_membership() {
+		$seeder = new NavSeeder( new NullProvider() );
+		$m      = $this->navManifest();
+		$navIds = $seeder->apply( $seeder->plan( $m ), $m, [] );
+		wp_update_post( [ 'ID' => $navIds['primary|'], 'post_content' => '' ] );
 
-		$problems = ( new Verifier( new NullProvider(), $seeder ) )->verify( $m, $this->desired( $m ), [ 'home|' => $id ], new MediaMap( [] ), $navIds );
+		$problems = ( new Verifier( new NullProvider(), $seeder ) )->verify( $m, [], [], new MediaMap( [] ), $navIds, [], [ 'primary|' ] );
 
-		$this->assertSame( [], $problems, 'a client-owned mega block is not a membership mismatch' );
+		$this->assertContains( 'navs.primary|: stored membership does not match the manifest.', $problems );
 	}
 }
diff --git a/plugin/tests/polylang/NavLanguageTest.php b/plugin/tests/polylang/NavLanguageTest.php
index 0c7badd..d66e0ee 100644
--- a/plugin/tests/polylang/NavLanguageTest.php
+++ b/plugin/tests/polylang/NavLanguageTest.php
@@ -61,7 +61,7 @@ class NavLanguageTest extends PolylangTestCase {
 		$seeder   = new NavSeeder( $lang );
 
 		$entryIds = [ 'about|en' => $this->page( 'en' ), 'about|de' => $this->page( 'de' ) ];
-		$plan     = $seeder->plan( $manifest, $entryIds );
+		$plan     = $seeder->plan( $manifest );
 		$ids      = $seeder->apply( $plan, $manifest, $entryIds );
 
 		$this->assertSame( [], $seeder->errors() );
@@ -76,7 +76,7 @@ class NavLanguageTest extends PolylangTestCase {
 		$seeder   = new NavSeeder( $lang );
 
 		$entryIds = [ 'about|en' => $this->page( 'en' ), 'about|de' => $this->page( 'de' ) ];
-		$ids      = $seeder->apply( $seeder->plan( $manifest, $entryIds ), $manifest, $entryIds );
+		$ids      = $seeder->apply( $seeder->plan( $manifest ), $manifest, $entryIds );
 
 		$this->assertSame( $ids['primary|de'], $lang->translationOf( $ids['primary|en'], 'de' ) );
 	}
@@ -89,7 +89,7 @@ class NavLanguageTest extends PolylangTestCase {
 		$en       = $this->page( 'en' );
 		$de       = $this->page( 'de' );
 		$entryIds = [ 'about|en' => $en, 'about|de' => $de ];
-		$ids      = $seeder->apply( $seeder->plan( $manifest, $entryIds ), $manifest, $entryIds );
+		$ids      = $seeder->apply( $seeder->plan( $manifest ), $manifest, $entryIds );
 
 		$this->assertStringContainsString( '"id":' . $de, get_post( $ids['primary|de'] )->post_content );
 		$this->assertStringNotContainsString( '"id":' . $en, get_post( $ids['primary|de'] )->post_content );
@@ -135,7 +135,7 @@ class NavLanguageTest extends PolylangTestCase {
 			return ( $postarr['post_name'] ?? '' ) === 'primary-fr' ? true : $maybe_empty;
 		};
 		add_filter( 'wp_insert_post_empty_content', $fail, 10, 2 );
-		$ids = $seeder->apply( $seeder->plan( $manifest, $entryIds ), $manifest, $entryIds );
+		$ids = $seeder->apply( $seeder->plan( $manifest ), $manifest, $entryIds );
 		remove_filter( 'wp_insert_post_empty_content', $fail, 10 );
 
 		$this->assertArrayNotHasKey( 'primary|fr', $ids, 'Precondition: the French nav create must actually have failed.' );
@@ -189,7 +189,7 @@ class NavLanguageTest extends PolylangTestCase {
 			'about|de' => $this->page( 'de' ),
 			'about|fr' => $this->page( 'fr' ),
 		];
-		$ids      = $seeder->apply( $seeder->plan( $threeLanguages, $entryIds ), $threeLanguages, $entryIds );
+		$ids      = $seeder->apply( $seeder->plan( $threeLanguages ), $threeLanguages, $entryIds );
 		$this->assertArrayHasKey( 'primary|fr', $ids, 'Precondition: a French nav must exist before the language is dropped.' );
 
 		PLL()->model->delete_language( (int) $this->addedLanguageId );
@@ -203,10 +203,7 @@ class NavLanguageTest extends PolylangTestCase {
 			'Precondition: dropping the language must leave the nav untagged, not tagged with an unconfigured language.'
 		);
 
-		$plan = ( new NavSeeder( new PolylangProvider() ) )->plan(
-			$this->manifest(),
-			[ 'about|en' => $entryIds['about|en'], 'about|de' => $entryIds['about|de'] ]
-		);
+		$plan = ( new NavSeeder( new PolylangProvider() ) )->plan( $this->manifest() );
 
 		$this->assertTrue( $plan->hasErrors() );
 		$this->assertStringContainsString( (string) $ids['primary|en'], $plan->errors()[0] );
@@ -242,7 +239,7 @@ class NavLanguageTest extends PolylangTestCase {
 		];
 
 		$seeder = new NavSeeder( new PolylangProvider() );
-		$navIds = $seeder->apply( $seeder->plan( $manifest, $ids ), $manifest, $ids );
+		$navIds = $seeder->apply( $seeder->plan( $manifest ), $manifest, $ids );
 
 		$this->assertSame( [], $seeder->errors() );
 
@@ -314,7 +311,7 @@ class NavLanguageTest extends PolylangTestCase {
 		$de       = $this->page( 'de' );
 		$entryIds = [ 'about|en' => $en, 'about|de' => $de ];
 
-		$ids = $seeder->apply( $seeder->plan( $manifest, $entryIds ), $manifest, $entryIds );
+		$ids = $seeder->apply( $seeder->plan( $manifest ), $manifest, $entryIds );
 
 		$this->assertSame( [], $seeder->errors() );
 		$deContent = get_post( $ids['primary|de'] )->post_content;
PATCH
```

- [ ] **Step 2: Run the tests to verify they fail**

Run: `npx wp-env run tests-wordpress --env-cwd=wp-content/plugins/pediment-ai ./vendor/bin/phpunit tests/phpunit/Seeder`
Expected: FAIL — `NavSeederTest` with `ArgumentCountError: Too few arguments to function Pediment\Seeder\NavSeeder::plan(), 1 passed … 2 expected` in most tests, `RunnerTest::test_a_menu_a_person_unpublished_is_left_alone` (`ok()` false), `RunnerTest::test_a_reseed_never_updates_existing_content` (the edited menu is rewritten).

- [ ] **Step 3: Make NavSeeder create-only and drop the mega hashes**

```bash
git rm -q plugin/src/Seeder/MegaBlocks.php
git apply <<'PATCH'
diff --git a/plugin/src/Seeder/Meta.php b/plugin/src/Seeder/Meta.php
index 00d7d7d..c3d22e5 100644
--- a/plugin/src/Seeder/Meta.php
+++ b/plugin/src/Seeder/Meta.php
@@ -23,15 +23,6 @@ final class Meta {
 	/** Hash of the git-side INPUT the seeder last wrote; detects content changes. */
 	public const SOURCE = '_pediment_seed_source';
 
-	/**
-	 * JSON array of per-position hashes over the pediment/mega-menu blocks in
-	 * a navigation entity, as last written by the seeder. Arbitrates mega
-	 * content the way HASH arbitrates page content: matching = git owns the
-	 * block and manifest changes flow through; anything else = the client
-	 * edited it in the editor and keeps it. See MegaBlocks.
-	 */
-	public const MEGA_HASH = '_pediment_seed_mega_hash';
-
 	/**
 	 * Drop what wp_trash_post() left behind.
 	 *
diff --git a/plugin/src/Seeder/NavSeeder.php b/plugin/src/Seeder/NavSeeder.php
index 492d95f..240ba53 100644
--- a/plugin/src/Seeder/NavSeeder.php
+++ b/plugin/src/Seeder/NavSeeder.php
@@ -4,6 +4,10 @@
  * not by title or slug. A stray post holding the `primary` slug is what turned
  * replacements into `primary-2` (7d7ca30).
  *
+ * Create-only: a menu that does not exist yet is written from the manifest;
+ * a menu that exists is never rewritten — not its links, not its status. Once
+ * a site is live, its menus belong to the people who edit them.
+ *
  * @package Pediment
  */
 
@@ -21,10 +25,12 @@ final class NavSeeder {
 	/** @var string[] Failures from the most recent apply(). */
 	private array $errors = [];
 
+	/** @var string[] navKey|language map keys the most recent apply() created. */
+	private array $created = [];
+
 	public function __construct( private LanguageProvider $lang ) {}
 
-	/** @param array<string,int> $entryIds */
-	public function plan( Manifest $manifest, array $entryIds ): Plan {
+	public function plan( Manifest $manifest ): Plan {
 		$items    = [];
 		$errors   = [];
 		$existing = $this->existing();
@@ -55,49 +61,7 @@ final class NavSeeder {
 					continue;
 				}
 
-				// A client who deleted the menu still owns the identity: without
-				// this the trashed entity keeps its seed key, a second one is
-				// created beside it, and the next run reports a duplicate — the
-				// `primary-2` shape this class exists to prevent.
-				if ( 'trash' === get_post_status( $postId ) ) {
-					$items[] = new PlanItem(
-						PlanItem::RESTORE,
-						PlanItem::KIND_NAV,
-						$key,
-						$language,
-						$postId,
-						[ 'status' => [ 'from' => 'trash', 'to' => 'publish' ] ]
-					);
-					continue;
-				}
-
-				$current = (string) get_post( $postId )->post_content;
-				$desired = $this->serialize( $spec, $language, $entryIds, $current, $postId );
-				$items[] = $current === $desired
-					? new PlanItem( PlanItem::UNCHANGED, PlanItem::KIND_NAV, $key, $language, $postId )
-					: new PlanItem(
-						PlanItem::UPDATE,
-						PlanItem::KIND_NAV,
-						$key,
-						$language,
-						$postId,
-						[
-							'items' => [
-								// The `<!-- ` prefix on the submenu needle is deliberate: the
-								// closing delimiter is `<!-- /wp:navigation-submenu -->`, and a
-								// bare needle would match it too and double every submenu.
-								// mega-menu is self-closing, so its needle has no such twin;
-								// the full prefix is used for consistency.
-								'from' => substr_count( $current, 'wp:navigation-link' )
-									+ substr_count( $current, '<!-- wp:navigation-submenu' )
-									+ substr_count( $current, '<!-- wp:pediment/mega-menu' ),
-								'to'   => self::countLinks( $spec->items ),
-							],
-						],
-						self::hasMega( $spec )
-							? 'membership is git-owned; mega menu content is kept once edited in the editor'
-							: 'membership is git-owned; editor changes to this menu are reverted'
-					);
+				$items[] = new PlanItem( PlanItem::UNCHANGED, PlanItem::KIND_NAV, $key, $language, $postId, [], $this->leftAloneNote( $postId ) );
 			}
 		}
 
@@ -109,7 +73,8 @@ final class NavSeeder {
 	 * @return array<string,int> navKey|language => post ID
 	 */
 	public function apply( Plan $plan, Manifest $manifest, array $entryIds ): array {
-		$this->errors = [];
+		$this->errors  = [];
+		$this->created = [];
 
 		// Same contract as Applier::apply(): an errored plan writes nothing.
 		if ( $plan->hasErrors() ) {
@@ -120,97 +85,55 @@ final class NavSeeder {
 		$ids = $this->existing();
 
 		// Without this, wp_filter_post_kses() strips the escapes out of the
-		// navigation-link JSON on save, the stored markup never matches a fresh
-		// serialize(), and every nav is rewritten on every run.
+		// navigation-link JSON on save and the stored menu is not what the
+		// manifest describes.
 		$kses = Kses::suspend();
 
 		try {
-			foreach ( $plan->byKind( PlanItem::KIND_NAV ) as $item ) {
+			foreach ( $plan->byAction( PlanItem::CREATE ) as $item ) {
+				if ( PlanItem::KIND_NAV !== $item->kind ) {
+					continue;
+				}
 				$spec = $manifest->navs()[ $item->key ] ?? null;
 				if ( ! $spec instanceof NavSpec ) {
 					continue;
 				}
 
-				// Checked for every item, including UNCHANGED ones: a menu that
-				// quietly comes out short is worse than one that fails, and the
-				// problem persists across runs even though the nav stops changing.
+				// Never create a half-truth. serialize() skips a link it cannot
+				// resolve, so writing now would put a shortened menu in the
+				// header — an entry whose create failed earlier in the same run
+				// would silently be missing from it. Leave the menu uncreated,
+				// say why, and the next seed creates it once the entry exists.
 				$unresolved = $this->unresolvedEntries( $spec, $item->language, $entryIds );
 				foreach ( $unresolved as $missing ) {
-					$this->errors[] = sprintf( 'navs.%s: "%s" has no seeded post yet — the link is missing from the menu.', $spec->key, $missing );
+					$this->errors[] = sprintf( 'navs.%s: "%s" has no seeded post yet — the menu was not created.', $spec->key, $missing );
 				}
-
-				// Never write a half-truth. serialize() skips a link it cannot
-				// resolve, so writing here would REPLACE the live menu with a
-				// shortened one — an entry whose create failed earlier in the same
-				// run would silently delete a link from the header. Leave the
-				// entity exactly as it is and let phase 5 report the mismatch.
 				if ( [] !== $unresolved ) {
 					continue;
 				}
 
-				if ( PlanItem::UNCHANGED === $item->action ) {
+				$postId = wp_insert_post(
+					wp_slash(
+						[
+							'post_type'    => 'wp_navigation',
+							'post_status'  => 'publish',
+							'post_title'   => $spec->title,
+							'post_name'    => $this->slugFor( $spec, $item->language ),
+							'post_content' => $this->serialize( $spec, $item->language, $entryIds ),
+						]
+					),
+					true
+				);
+				if ( is_wp_error( $postId ) ) {
+					$this->errors[] = sprintf( 'navs.%s: could not create the navigation entity — %s', $spec->key, $postId->get_error_message() );
 					continue;
 				}
-
-				if ( PlanItem::CREATE === $item->action ) {
-					$content = $this->serialize( $spec, $item->language, $entryIds );
-					$postId  = wp_insert_post(
-						wp_slash(
-							[
-								'post_type'    => 'wp_navigation',
-								'post_status'  => 'publish',
-								'post_title'   => $spec->title,
-								'post_name'    => $this->slugFor( $spec, $item->language ),
-								'post_content' => $content,
-							]
-						),
-						true
-					);
-					if ( is_wp_error( $postId ) ) {
-						$this->errors[] = sprintf( 'navs.%s: could not create the navigation entity — %s', $spec->key, $postId->get_error_message() );
-						continue;
-					}
-					$postId = (int) $postId;
-					$this->lang->setLanguage( $postId, $item->language );
-					update_post_meta( $postId, Meta::KEY, $spec->key );
-					MegaBlocks::writeHashes( $postId, '' );
-				} elseif ( PlanItem::RESTORE === $item->action ) {
-					$postId  = $item->postId;
-					$old     = (string) get_post( $postId )->post_content;
-					$content = $this->serialize( $spec, $item->language, $entryIds, $old, $postId );
-					// The slug is rewritten too: wp_trash_post() renames it to
-					// `primary__trashed`, and leaving that behind is what a later
-					// create would collide with.
-					$restored = wp_update_post(
-						wp_slash(
-							[
-								'ID'           => $postId,
-								'post_status'  => 'publish',
-								'post_name'    => $this->slugFor( $spec, $item->language ),
-								'post_content' => $content,
-							]
-						),
-						true
-					);
-					if ( is_wp_error( $restored ) ) {
-						$this->errors[] = sprintf( 'navs.%s: could not restore the navigation entity %d — %s', $spec->key, $postId, $restored->get_error_message() );
-						continue;
-					}
-					Meta::clearTrashBookkeeping( $postId );
-					MegaBlocks::writeHashes( $postId, $old );
-				} else {
-					$postId  = $item->postId;
-					$old     = (string) get_post( $postId )->post_content;
-					$content = $this->serialize( $spec, $item->language, $entryIds, $old, $postId );
-					$updated = wp_update_post( wp_slash( [ 'ID' => $postId, 'post_content' => $content ] ), true );
-					if ( is_wp_error( $updated ) ) {
-						$this->errors[] = sprintf( 'navs.%s: could not update the navigation entity — %s', $spec->key, $updated->get_error_message() );
-						continue;
-					}
-					MegaBlocks::writeHashes( $postId, $old );
-				}
+				$postId = (int) $postId;
+				$this->lang->setLanguage( $postId, $item->language );
+				update_post_meta( $postId, Meta::KEY, $spec->key );
 
 				$ids[ $item->mapKey() ] = $postId;
+				$this->created[]        = $item->mapKey();
 			}
 
 			$this->repairLanguage( $plan, $ids );
@@ -227,28 +150,35 @@ final class NavSeeder {
 		return $this->errors;
 	}
 
+	/** @return string[] navKey|language map keys the most recent apply() created. */
+	public function created(): array {
+		return $this->created;
+	}
+
+	/**
+	 * Why an existing menu may not show, said in the plan so nobody mistakes
+	 * a menu a person trashed or unpublished for a seeding failure.
+	 */
+	private function leftAloneNote( int $postId ): string {
+		$status = (string) get_post_status( $postId );
+		if ( 'trash' === $status ) {
+			return 'in the trash — left alone; restore it in the Site Editor, or empty the trash and the next seed creates it again';
+		}
+		if ( 'publish' !== $status ) {
+			return sprintf( '"%s" — left alone; the menu does not render until someone publishes it', $status );
+		}
+		return '';
+	}
+
 	/** @param array<string,int> $entryIds */
-	public function serialize( NavSpec $spec, string $language, array $entryIds, string $current = '', int $navId = 0 ): string {
-		$blocks     = [];
-		$storedMega = MegaBlocks::extract( $current );
-		$megaHashes = 0 < $navId ? MegaBlocks::storedHashes( $navId ) : [];
-		$megaIndex  = 0;
+	public function serialize( NavSpec $spec, string $language, array $entryIds ): string {
+		$blocks = [];
 
 		foreach ( $spec->items as $item ) {
 			$item = (array) $item;
 
-			// Membership is git-owned, content is hash-arbitrated: the block
-			// always (re)appears where the manifest says, but once a human
-			// edits it in the editor its stored markup stops hashing to what
-			// the seeder last wrote, and from then on it is spliced through
-			// verbatim. Matching is positional: nth mega item, nth stored
-			// block. See docs/seeding.md ("Mega menus").
 			if ( isset( $item['mega'] ) ) {
-				$stored   = $storedMega[ $megaIndex ] ?? null;
-				$blocks[] = null !== $stored && ! MegaBlocks::gitOwns( $stored, (string) ( $megaHashes[ $megaIndex ] ?? '' ) )
-					? $stored
-					: $this->megaMarkup( (array) $item['mega'], $language, $entryIds );
-				++$megaIndex;
+				$blocks[] = $this->megaMarkup( (array) $item['mega'], $language, $entryIds );
 				continue;
 			}
 
@@ -267,8 +197,7 @@ final class NavSeeder {
 			$attrs = $this->linkAttrs( $item, $language, $entryIds );
 
 			// Reported by apply() via unresolvedEntries(), not from here:
-			// serialize() must stay pure, and an unresolved link has to be
-			// reported on EVERY run, not only the one that rewrites the nav.
+			// serialize() must stay pure.
 			// A submenu parent takes its children with it — a submenu whose own
 			// link is missing is not a menu anyone meant, and promoting the
 			// children to the top level would silently restructure the header.
@@ -301,9 +230,8 @@ final class NavSeeder {
 	/**
 	 * The block attributes one navigation item resolves to.
 	 *
-	 * Key order is load-bearing, not cosmetic: plan() decides UPDATE by
-	 * comparing stored post_content against a fresh serialize(), so reordering
-	 * these would make every nav on every site rewrite itself once.
+	 * Key order matches what menus seeded by earlier versions carry;
+	 * test_a_mega_free_manifest_serializes_exactly_as_before locks it.
 	 *
 	 * JSON_UNESCAPED_SLASHES matches what the block editor writes, and keeps
 	 * the markup stable under KSES, which strips `\/` on save.
@@ -344,9 +272,9 @@ final class NavSeeder {
 	/**
 	 * One `pediment/mega-menu` block from a manifest mega item.
 	 *
-	 * Key order is load-bearing for the same reason as linkAttrs(): label,
-	 * columns; per column heading, icon, links; per link label, description,
-	 * url — optional keys are omitted, never emitted empty. Entry links
+	 * Key order is fixed like linkAttrs(): label, columns; per column
+	 * heading, icon, links; per link label, description, url — optional keys
+	 * are omitted, never emitted empty. Entry links
 	 * resolve through linkAttrs() (per-language permalink, label falls back
 	 * to the post title); the resolver's id/kind/type keys are dropped
 	 * because the block schema does not know them.
@@ -392,19 +320,13 @@ final class NavSeeder {
 			'columns' => $columns,
 		];
 
-		// Mega attrs carry client copy, and Gutenberg's JS serializeAttributes()
-		// re-serializes EVERY block in the post whenever the nav is saved in the
-		// Site Editor, not just the ones a human touched. serialize_block_attributes()
-		// is core's PHP twin of that JS function (wp_json_encode with
-		// JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE, then --/</>/&/\" each
-		// rewritten to a \u escape) — matching it byte-for-byte is what keeps an
-		// UNTOUCHED mega block's stored hash matching after an unrelated editor
-		// save. wp_json_encode() alone would byte-shift any label/description
-		// containing an umlaut, `&`, `"`, or `--`, and the block would be silently
-		// captured as client-owned. The links/switcher lines below deliberately
-		// keep plain wp_json_encode( …, JSON_UNESCAPED_SLASHES ): their byte format
-		// is locked by existing sites, and the zero-mega golden test
-		// (test_a_mega_free_manifest_serializes_exactly_as_before) enforces that.
+		// serialize_block_attributes() is core's PHP twin of the editor's JS
+		// serializeAttributes() (unescaped slashes and unicode, then --, <, >,
+		// & and \" as \u escapes), so the stored block is byte-identical to
+		// what the Site Editor would save. The links/switcher lines above keep
+		// plain wp_json_encode( …, JSON_UNESCAPED_SLASHES ): their byte format
+		// matches menus seeded by earlier versions, and the zero-mega golden
+		// test (test_a_mega_free_manifest_serializes_exactly_as_before) locks it.
 		return '<!-- wp:pediment/mega-menu ' . serialize_block_attributes( $attrs ) . ' /-->';
 	}
 
@@ -527,34 +449,16 @@ final class NavSeeder {
 		return $missing;
 	}
 
-	/** Whether any of the spec's items declares a mega menu. */
-	private static function hasMega( NavSpec $spec ): bool {
-		foreach ( $spec->items as $item ) {
-			if ( isset( ( (array) $item )['mega'] ) ) {
-				return true;
-			}
-		}
-		return false;
-	}
-
 	/**
-	 * Total links a nav spec describes, counting submenu parents and children.
-	 *
-	 * The plan's `items` change is operator-facing arithmetic, so it has to
-	 * count the same things on both sides: `count( $spec->items )` would report
-	 * a two-level menu as its top-level width and read as a shrink.
-	 *
-	 * A mega item counts as 1: its links are JSON attributes, invisible to the
-	 * needles plan() counts on the stored side.
+	 * Total links a nav spec describes, counting submenu parents and children,
+	 * for the plan's `items` line on a create. A mega item counts as 1.
 	 *
 	 * @param array<int,array<string,mixed>> $items
 	 */
 	private static function countLinks( array $items ): int {
 		$count = 0;
 		foreach ( $items as $item ) {
-			// A language switcher is not a link; skip it so this count stays
-			// symmetric with plan()'s `from` tally (which counts only
-			// navigation-link/-submenu markup in the stored nav).
+			// A language switcher is not a link.
 			if ( isset( ( (array) $item )['language_switcher'] ) ) {
 				continue;
 			}
@@ -664,7 +568,8 @@ final class NavSeeder {
 	}
 
 	/**
-	 * Tag a pre-existing navigation entity that has no language at all.
+	 * Tag a pre-existing navigation entity that has no language at all — the
+	 * one thing the seeder does to a menu that exists.
 	 *
 	 * The nav-side mirror of `Applier::repairLanguage()`, needed for the same
 	 * migration case and reached the same way: a claimed legacy nav carries the
diff --git a/plugin/src/Seeder/Plan.php b/plugin/src/Seeder/Plan.php
index 96e3cbf..b0c4a2e 100644
--- a/plugin/src/Seeder/Plan.php
+++ b/plugin/src/Seeder/Plan.php
@@ -55,7 +55,7 @@ final class Plan {
 	/** @return array<string,int> Every action, including the ones with no items. */
 	public function counts(): array {
 		$counts = array_fill_keys(
-			[ PlanItem::CREATE, PlanItem::RESTORE, PlanItem::UPDATE, PlanItem::UNCHANGED, PlanItem::ORPHAN ],
+			[ PlanItem::CREATE, PlanItem::RESTORE, PlanItem::UNCHANGED, PlanItem::ORPHAN ],
 			0
 		);
 		foreach ( $this->items as $item ) {
diff --git a/plugin/src/Seeder/PlanItem.php b/plugin/src/Seeder/PlanItem.php
index 0de3b35..ba9f5b5 100644
--- a/plugin/src/Seeder/PlanItem.php
+++ b/plugin/src/Seeder/PlanItem.php
@@ -10,7 +10,6 @@ if ( ! defined( 'ABSPATH' ) ) {
 final class PlanItem {
 	public const CREATE    = 'create'; // No post carries this key yet.
 	public const RESTORE   = 'restore'; // Exists but trashed.
-	public const UPDATE    = 'update'; // At least one field will be written.
 	public const UNCHANGED = 'unchanged'; // Exists; the seeder never changes it.
 	public const ORPHAN    = 'orphan'; // Carries a seed key the manifest dropped.
 
PATCH
```

- [ ] **Step 4: Verify only new menus; wire the Runner**

```bash
git apply <<'PATCH'
diff --git a/plugin/src/Seeder/Runner.php b/plugin/src/Seeder/Runner.php
index fc787d2..754cea8 100644
--- a/plugin/src/Seeder/Runner.php
+++ b/plugin/src/Seeder/Runner.php
@@ -75,7 +75,7 @@ final class Runner {
 
 		$entryPlan = ( new Differ() )->diff( $preview, $actual, $reader->duplicates() );
 		$entryIds  = $this->resolvedIds( $entryPlan );
-		$plan      = Plan::merge( $mediaPlan, $entryPlan, $navSeeder->plan( $manifest, $entryIds ) );
+		$plan      = Plan::merge( $mediaPlan, $entryPlan, $navSeeder->plan( $manifest ) );
 
 		if ( $dryRun || $plan->hasErrors() ) {
 			return new RunResult( $plan, false, $manifest->path(), $plan->errors(), $this->undeclaredMediaProblems( $previewState ), $entryIds, $previewState->missingTranslations() );
@@ -98,8 +98,8 @@ final class Runner {
 		$entryPlan = ( new Differ() )->diff( $desired, $actual, $reader->duplicates() );
 		$applied   = ( new Applier( $this->lang ) )->apply( $entryPlan, $desired );
 
-		// Nav links need the resolved page IDs, so nav is planned again against them.
-		$navPlan = $navSeeder->plan( $manifest, $applied->ids );
+		// A new menu links to the resolved page IDs, so navs are applied after entries.
+		$navPlan = $navSeeder->plan( $manifest );
 		$navIds  = $navSeeder->apply( $navPlan, $manifest, $applied->ids );
 
 		// Report the plan that actually ran, not the preview.
@@ -118,7 +118,7 @@ final class Runner {
 		// Phase 5 runs even when something failed: an operator debugging a
 		// partial apply needs to know what actually landed.
 		$problems = array_merge(
-			( new Verifier( $this->lang, $navSeeder ) )->verify( $manifest, $desired, $applied->ids, $mediaMap, $navIds, $applied->created ),
+			( new Verifier( $this->lang, $navSeeder ) )->verify( $manifest, $desired, $applied->ids, $mediaMap, $navIds, $applied->created, $navSeeder->created() ),
 			$this->undeclaredMediaProblems( $state )
 		);
 		$errors   = array_values( array_unique( array_merge( $mediaErrors, $applied->errors, $navSeeder->errors() ) ) );
@@ -149,7 +149,7 @@ final class Runner {
 		return $problems;
 	}
 
-	/** @return array<string,int> mapKey => post ID for entries that already exist */
+	/** @return array<string,int> mapKey => post ID for entries that already exist (the dry-run result's ids) */
 	private function resolvedIds( Plan $entryPlan ): array {
 		$ids = [];
 		foreach ( $entryPlan->byKind( PlanItem::KIND_ENTRY ) as $item ) {
diff --git a/plugin/src/Seeder/Verifier.php b/plugin/src/Seeder/Verifier.php
index 26dbb64..7b9b4a1 100644
--- a/plugin/src/Seeder/Verifier.php
+++ b/plugin/src/Seeder/Verifier.php
@@ -25,17 +25,18 @@ final class Verifier {
 	/**
 	 * Identity, language, media and post types are checked for every row.
 	 * Everything a person may change after the seeder created a row — slug,
-	 * parent, trash, the reading settings — is checked only for the rows this
-	 * run created: on an existing row it is that person's decision, and the
-	 * seeder never reverts it.
+	 * parent, trash, the reading settings, a menu's links and status — is
+	 * checked only for the rows this run created: on an existing row it is
+	 * that person's decision, and the seeder never reverts it.
 	 *
 	 * @param array<string,DesiredEntry> $desired
 	 * @param array<string,int>          $ids
 	 * @param array<string,int>          $navIds         navKey|language => post ID
 	 * @param string[]                   $createdEntries Entry map keys created in this run.
+	 * @param string[]                   $createdNavs    navKey|language map keys created in this run.
 	 * @return string[]
 	 */
-	public function verify( Manifest $manifest, array $desired, array $ids, MediaMap $media, array $navIds = [], array $createdEntries = [] ): array {
+	public function verify( Manifest $manifest, array $desired, array $ids, MediaMap $media, array $navIds = [], array $createdEntries = [], array $createdNavs = [] ): array {
 		$problems = [];
 
 		foreach ( $desired as $mapKey => $entry ) {
@@ -121,8 +122,8 @@ final class Verifier {
 		}
 
 		// Navigation: the header rendering nothing while the seeder reports
-		// success is the exact incident this class exists to prevent, so the
-		// entities are re-read and their membership re-derived.
+		// success is the exact incident this class exists to prevent, so a
+		// menu this run created is re-read and its membership re-derived.
 		foreach ( $manifest->navs() as $key => $spec ) {
 			foreach ( $this->lang->languages() as $language ) {
 				// Same key|language form the entry loop reports: without it a
@@ -138,9 +139,6 @@ final class Verifier {
 					$problems[] = sprintf( 'navs.%s: post %d is not a navigation entity.', $mapKey, $navId );
 					continue;
 				}
-				if ( 'publish' !== $nav->post_status ) {
-					$problems[] = sprintf( 'navs.%s: entity %d is "%s" — the menu will not render.', $mapKey, $navId, $nav->post_status );
-				}
 				if ( (string) get_post_meta( $navId, Meta::KEY, true ) !== $key ) {
 					$problems[] = sprintf( 'navs.%s: entity %d is missing its seed key.', $mapKey, $navId );
 				}
@@ -156,7 +154,15 @@ final class Verifier {
 						'' === $language ? '(default)' : $language
 					);
 				}
-				if ( (string) $nav->post_content !== $this->navSeeder->serialize( $spec, $language, $ids, (string) $nav->post_content, $navId ) ) {
+
+				if ( ! in_array( $mapKey, $createdNavs, true ) ) {
+					continue;
+				}
+
+				if ( 'publish' !== $nav->post_status ) {
+					$problems[] = sprintf( 'navs.%s: entity %d is "%s" — the menu will not render.', $mapKey, $navId, $nav->post_status );
+				}
+				if ( (string) $nav->post_content !== $this->navSeeder->serialize( $spec, $language, $ids ) ) {
 					$problems[] = sprintf( 'navs.%s: stored membership does not match the manifest.', $mapKey );
 				}
 			}
PATCH
```

- [ ] **Step 5: Run the tests to verify they pass**

```bash
npx wp-env run tests-wordpress --env-cwd=wp-content/plugins/pediment-ai ./vendor/bin/phpunit
npx wp-env run tests-wordpress --env-cwd=wp-content/plugins/pediment-ai ./vendor/bin/phpunit -c phpunit-polylang.xml.dist
composer lint -d plugin
grep -rn "MegaBlocks\|MEGA_HASH\|PlanItem::UPDATE" plugin/src plugin/inc plugin/wp-cli plugin/tests
```

Expected: both suites OK, lint 0 errors, grep prints nothing.

- [ ] **Step 6: Probe the menu rule against the dev site**

```bash
nav=$(npx wp-env run cli wp post list --post_type=wp_navigation --posts_per_page=1 --format=ids | tail -n 1)
npx wp-env run cli wp post update "$nav" --post_status=draft
npx wp-env run cli wp pediment seed --dry-run
npx wp-env run cli wp post update "$nav" --post_status=publish
```

Expected: the NAV line reads `unchanged … "draft" — left alone; the menu does not render until someone publishes it`, the summary still starts with `0 to write,`, and no `VERIFICATION FAILED` block. If the root env has several menus, check the NAV line of the one you changed.

- [ ] **Step 7: Commit**

```bash
git add plugin/src/Seeder/NavSeeder.php plugin/src/Seeder/Verifier.php plugin/src/Seeder/Runner.php plugin/src/Seeder/Meta.php plugin/src/Seeder/PlanItem.php plugin/src/Seeder/Plan.php plugin/tests/phpunit/Seeder/NavSeederTest.php plugin/tests/phpunit/Seeder/RunnerTest.php plugin/tests/phpunit/Seeder/VerifierTest.php plugin/tests/polylang/NavLanguageTest.php
git commit -m "feat(seeder)!: create menus once, never rewrite them" -m "A seed run creates a missing navigation menu and leaves an existing one alone: no membership rewrite, no restore from the trash, no mega-menu hash. The Verifier checks membership only for a menu it created." -m "BREAKING CHANGE: wp pediment seed no longer re-syncs navigation menus to the manifest, and _pediment_seed_mega_hash is no longer written or read."
```

(`git rm` already staged the two deleted files.)

---

### Task 3: Media and the site logo are create-only; the header part is pinned

`MediaSeeder` no longer restores a trashed attachment and sets the site logo only for a new logo attachment or an empty `custom_logo` (S3). `PlanItem::RESTORE` and `Meta::clearTrashBookkeeping()` go. A Runner test pins that a seed run never touches the header template part (S2).

**Files:**
- Modify: `plugin/src/Seeder/MediaSeeder.php:2-8,53-66,99-135`, `plugin/src/Seeder/Meta.php` (`clearTrashBookkeeping()`), `plugin/src/Seeder/PlanItem.php` (`RESTORE`), `plugin/src/Seeder/Plan.php` (`counts()`), `plugin/src/Seeder/Verifier.php` (media check)
- Test: `plugin/tests/phpunit/Seeder/MediaSeederTest.php`, `RunnerTest.php`, `VerifierTest.php`

**Interfaces:**
- Consumes: `NavSeeder`, `Verifier` from Task 2.
- Produces: `MediaSeeder::plan( Manifest $manifest ): Plan` — `create` or `unchanged` (note `in the trash — left alone; …` for a trashed attachment). `MediaSeeder::apply( Plan $plan, Manifest $manifest ): MediaMap` — sideloads `create` items only; sets `custom_logo` when the logo attachment was created in this run or `custom_logo` is `0`. `Plan::counts()` keys: `create`, `unchanged`, `orphan`. The Verifier reports `media.<key>: attachment <id> is missing.` and no longer fails on a trashed attachment.

- [ ] **Step 1: Write the failing tests**

```bash
git apply <<'PATCH'
diff --git a/plugin/tests/phpunit/Seeder/MediaSeederTest.php b/plugin/tests/phpunit/Seeder/MediaSeederTest.php
index 4443bd7..e5cf470 100644
--- a/plugin/tests/phpunit/Seeder/MediaSeederTest.php
+++ b/plugin/tests/phpunit/Seeder/MediaSeederTest.php
@@ -77,27 +77,50 @@ class MediaSeederTest extends WP_UnitTestCase {
 		$this->assertSame( 0, ( new MediaSeeder() )->map( $this->manifest() )->id( 'logo' ) );
 	}
 
-	public function test_a_trashed_attachment_is_restored_rather_than_re_uploaded() {
+	public function test_a_trashed_attachment_stays_in_the_trash_and_is_not_uploaded_again() {
 		$seeder = new MediaSeeder();
 		$m      = $this->manifest();
 		$first  = $seeder->apply( $seeder->plan( $m ), $m );
-		wp_trash_post( $first->id( 'logo' ) );
+		wp_trash_post( $first->id( 'hero' ) );
 
 		$plan   = $seeder->plan( $m );
 		$second = $seeder->apply( $plan, $m );
 
-		$this->assertSame( PlanItem::RESTORE, $plan->byAction( PlanItem::RESTORE )[0]->action );
-		$this->assertSame( $first->id( 'logo' ), $second->id( 'logo' ), 'restore, never re-upload' );
-		// get_post_status() resolves `inherit` on an unattached attachment to
-		// `publish`, so assert the raw column instead.
-		$this->assertSame( 'inherit', get_post_field( 'post_status', $second->id( 'logo' ) ) );
+		$this->assertSame( [], $plan->byAction( PlanItem::CREATE ) );
+		$hero = array_values( array_filter( $plan->items(), static fn( PlanItem $i ): bool => 'hero' === $i->key ) )[0];
+		$this->assertSame( PlanItem::UNCHANGED, $hero->action );
+		$this->assertStringContainsString( 'in the trash', $hero->note );
+		$this->assertSame( $first->id( 'hero' ), $second->id( 'hero' ) );
+		$this->assertSame( 'trash', get_post_field( 'post_status', $second->id( 'hero' ) ) );
 		$this->assertCount(
 			1,
-			get_posts( [ 'post_type' => 'attachment', 'post_status' => 'any', 'fields' => 'ids', 'meta_key' => \Pediment\Seeder\Meta::KEY, 'meta_value' => 'logo' ] ),
+			get_posts( [ 'post_type' => 'attachment', 'post_status' => [ 'inherit', 'trash' ], 'fields' => 'ids', 'meta_key' => Meta::KEY, 'meta_value' => 'hero' ] ),
 			'one seed key, one attachment'
 		);
 	}
 
+	public function test_a_logo_a_person_chose_is_kept() {
+		$seeder = new MediaSeeder();
+		$m      = $this->manifest();
+		$first  = $seeder->apply( $seeder->plan( $m ), $m );
+		set_theme_mod( 'custom_logo', $first->id( 'hero' ) );
+
+		$seeder->apply( $seeder->plan( $m ), $m );
+
+		$this->assertSame( $first->id( 'hero' ), (int) get_theme_mod( 'custom_logo' ) );
+	}
+
+	public function test_an_unset_logo_is_set_from_an_existing_attachment() {
+		$seeder = new MediaSeeder();
+		$m      = $this->manifest();
+		$first  = $seeder->apply( $seeder->plan( $m ), $m );
+		remove_theme_mod( 'custom_logo' );
+
+		$seeder->apply( $seeder->plan( $m ), $m );
+
+		$this->assertSame( $first->id( 'logo' ), (int) get_theme_mod( 'custom_logo' ) );
+	}
+
 	public function test_two_attachments_under_one_key_are_reported() {
 		$seeder = new MediaSeeder();
 		$m      = $this->manifest();
diff --git a/plugin/tests/phpunit/Seeder/RunnerTest.php b/plugin/tests/phpunit/Seeder/RunnerTest.php
index 9e6c488..f60d2ee 100644
--- a/plugin/tests/phpunit/Seeder/RunnerTest.php
+++ b/plugin/tests/phpunit/Seeder/RunnerTest.php
@@ -251,6 +251,25 @@ class RunnerTest extends WP_UnitTestCase {
 		$this->assertStringContainsString( 'does not render', $second->plan->byKind( PlanItem::KIND_NAV )[0]->note );
 	}
 
+	public function test_a_seed_run_never_touches_the_header_part() {
+		// The header part is created once, by plugin activation or a theme
+		// switch (inc/bootstrap.php). A seed run neither creates a second one
+		// nor rewrites the one a person edited in the Site Editor.
+		pediment_bootstrap_header_template_part();
+		$query = [ 'post_type' => 'wp_template_part', 'post_name__in' => [ 'header' ], 'post_status' => 'publish', 'numberposts' => -1 ];
+		$parts = get_posts( $query );
+		$this->assertCount( 1, $parts );
+		wp_update_post( [ 'ID' => $parts[0]->ID, 'post_content' => '<!-- wp:paragraph --><p>Edited header</p><!-- /wp:paragraph -->' ] );
+		$before = get_post( $parts[0]->ID )->post_content;
+
+		( new Runner() )->run();
+		( new Runner() )->run();
+
+		$after = get_posts( $query );
+		$this->assertCount( 1, $after );
+		$this->assertSame( $before, $after[0]->post_content );
+	}
+
 	public function test_a_typoed_media_key_is_reported_when_the_page_is_created() {
 		// Nothing else notices: the media plan has no such key and the Verifier
 		// only walks declared media, so the literal sentinel lands in the new
diff --git a/plugin/tests/phpunit/Seeder/VerifierTest.php b/plugin/tests/phpunit/Seeder/VerifierTest.php
index 92f14d0..f700ff6 100644
--- a/plugin/tests/phpunit/Seeder/VerifierTest.php
+++ b/plugin/tests/phpunit/Seeder/VerifierTest.php
@@ -134,6 +134,20 @@ class VerifierTest extends WP_UnitTestCase {
 		$this->assertStringContainsString( 'logo', implode( "\n", $problems ) );
 	}
 
+	public function test_a_trashed_attachment_is_not_a_problem() {
+		$dir = get_temp_dir() . 'pediment-verify-test';
+		wp_mkdir_p( $dir . '/seed/media' );
+		file_put_contents( $dir . '/seed/media/logo.svg', '<svg xmlns="http://www.w3.org/2000/svg"/>' );
+		$m  = Manifest::fromArray( [ 'media' => [ 'logo' => [ 'file' => 'seed/media/logo.svg' ] ] ], $dir );
+		$id = self::factory()->attachment->create_object( [ 'file' => 'logo.svg', 'post_mime_type' => 'image/svg+xml' ] );
+		update_post_meta( $id, Meta::KEY, 'logo' );
+		wp_trash_post( $id );
+
+		$problems = ( new Verifier( new NullProvider(), new NavSeeder( new NullProvider() ) ) )->verify( $m, [], [], new MediaMap( [ 'logo' => $id ] ) );
+
+		$this->assertSame( [], $problems, 'the plan reports the trash; the seeder leaves it there' );
+	}
+
 	private function navManifest(): Manifest {
 		return Manifest::fromArray(
 			[ 'navs' => [ 'primary' => [ 'title' => 'Primary', 'items' => [ [ 'url' => '/features/', 'label' => 'Features' ] ] ] ] ],
PATCH
```

- [ ] **Step 2: Run the tests to verify they fail**

Run: `npx wp-env run tests-wordpress --env-cwd=wp-content/plugins/pediment-ai ./vendor/bin/phpunit --filter 'MediaSeederTest|VerifierTest|RunnerTest'`
Expected: FAIL — `test_a_trashed_attachment_stays_in_the_trash_and_is_not_uploaded_again` (action `restore`), `test_a_logo_a_person_chose_is_kept` (logo reverted), `test_a_trashed_attachment_is_not_a_problem` (`missing or trashed`). `test_a_seed_run_never_touches_the_header_part` passes already: it pins existing behaviour (S2).

- [ ] **Step 3: Implement**

```bash
git apply <<'PATCH'
diff --git a/plugin/src/Seeder/MediaSeeder.php b/plugin/src/Seeder/MediaSeeder.php
index 2de2db1..cdf75be 100644
--- a/plugin/src/Seeder/MediaSeeder.php
+++ b/plugin/src/Seeder/MediaSeeder.php
@@ -2,6 +2,8 @@
 /**
  * Media presence: every media key in the manifest resolves to exactly one
  * attachment, identified by _pediment_seed_key like everything else.
+ * Create-only: an attachment that exists — even one in the trash — is never
+ * written to again.
  *
  * @package Pediment
  */
@@ -52,16 +54,17 @@ final class MediaSeeder {
 
 			if ( isset( $existing[ $key ] ) ) {
 				$postId  = $existing[ $key ];
-				$items[] = 'trash' === get_post_status( $postId )
-					? new PlanItem(
-						PlanItem::RESTORE,
-						PlanItem::KIND_MEDIA,
-						$key,
-						'',
-						$postId,
-						[ 'status' => [ 'from' => 'trash', 'to' => 'inherit' ] ]
-					)
-					: new PlanItem( PlanItem::UNCHANGED, PlanItem::KIND_MEDIA, $key, '', $postId );
+				$items[] = new PlanItem(
+					PlanItem::UNCHANGED,
+					PlanItem::KIND_MEDIA,
+					$key,
+					'',
+					$postId,
+					[],
+					'trash' === get_post_status( $postId )
+						? 'in the trash — left alone; restore it in the Media Library, or delete it permanently and the next seed uploads it again'
+						: ''
+				);
 				continue;
 			}
 
@@ -96,39 +99,30 @@ final class MediaSeeder {
 			return $this->map( $manifest );
 		}
 
-		$ids = array_intersect_key( $this->existing(), $manifest->media() );
+		$ids     = array_intersect_key( $this->existing(), $manifest->media() );
+		$created = [];
 
-		foreach ( $plan->byKind( PlanItem::KIND_MEDIA ) as $item ) {
+		foreach ( $plan->byAction( PlanItem::CREATE ) as $item ) {
 			$spec = $manifest->media()[ $item->key ] ?? null;
-			if ( ! $spec instanceof MediaSpec ) {
-				continue;
-			}
-
-			if ( PlanItem::RESTORE === $item->action ) {
-				$restored = wp_update_post( [ 'ID' => $item->postId, 'post_status' => 'inherit' ], true );
-				if ( is_wp_error( $restored ) ) {
-					$this->errors[] = sprintf( 'media.%s: could not restore attachment %d — %s', $item->key, $item->postId, $restored->get_error_message() );
-					continue;
-				}
-				Meta::clearTrashBookkeeping( $item->postId );
-				$ids[ $item->key ] = $item->postId;
-				continue;
-			}
-
-			if ( PlanItem::CREATE !== $item->action ) {
+			if ( PlanItem::KIND_MEDIA !== $item->kind || ! $spec instanceof MediaSpec ) {
 				continue;
 			}
 			$id = $this->sideload( $spec );
 			if ( $id > 0 ) {
 				$ids[ $item->key ] = $id;
+				$created[]         = $item->key;
 			}
 		}
 
 		$map = new MediaMap( $ids );
 
+		// The site logo follows the same rule as the reading settings: set it
+		// when its attachment was created in this run, or while the site has
+		// none. A logo a person chose later in the Site Editor stays.
 		$logoKey = $manifest->siteLogo();
-		if ( '' !== $logoKey && $map->id( $logoKey ) > 0 && (int) get_theme_mod( 'custom_logo' ) !== $map->id( $logoKey ) ) {
-			set_theme_mod( 'custom_logo', $map->id( $logoKey ) );
+		$logoId  = '' === $logoKey ? 0 : $map->id( $logoKey );
+		if ( $logoId > 0 && ( in_array( $logoKey, $created, true ) || 0 === (int) get_theme_mod( 'custom_logo' ) ) ) {
+			set_theme_mod( 'custom_logo', $logoId );
 		}
 
 		return $map;
diff --git a/plugin/src/Seeder/Meta.php b/plugin/src/Seeder/Meta.php
index c3d22e5..a89eb06 100644
--- a/plugin/src/Seeder/Meta.php
+++ b/plugin/src/Seeder/Meta.php
@@ -22,17 +22,4 @@ final class Meta {
 
 	/** Hash of the git-side INPUT the seeder last wrote; detects content changes. */
 	public const SOURCE = '_pediment_seed_source';
-
-	/**
-	 * Drop what wp_trash_post() left behind.
-	 *
-	 * Every restore in this engine writes post_status directly, which skips the
-	 * untrash hooks — so this bookkeeping would stay on the row forever and a
-	 * later real untrash would act on stale values.
-	 */
-	public static function clearTrashBookkeeping( int $postId ): void {
-		foreach ( [ '_wp_trash_meta_status', '_wp_trash_meta_time', '_wp_desired_post_slug' ] as $key ) {
-			delete_post_meta( $postId, $key );
-		}
-	}
 }
diff --git a/plugin/src/Seeder/Plan.php b/plugin/src/Seeder/Plan.php
index b0c4a2e..25cece1 100644
--- a/plugin/src/Seeder/Plan.php
+++ b/plugin/src/Seeder/Plan.php
@@ -55,7 +55,7 @@ final class Plan {
 	/** @return array<string,int> Every action, including the ones with no items. */
 	public function counts(): array {
 		$counts = array_fill_keys(
-			[ PlanItem::CREATE, PlanItem::RESTORE, PlanItem::UNCHANGED, PlanItem::ORPHAN ],
+			[ PlanItem::CREATE, PlanItem::UNCHANGED, PlanItem::ORPHAN ],
 			0
 		);
 		foreach ( $this->items as $item ) {
diff --git a/plugin/src/Seeder/PlanItem.php b/plugin/src/Seeder/PlanItem.php
index ba9f5b5..97d10ae 100644
--- a/plugin/src/Seeder/PlanItem.php
+++ b/plugin/src/Seeder/PlanItem.php
@@ -9,7 +9,6 @@ if ( ! defined( 'ABSPATH' ) ) {
 
 final class PlanItem {
 	public const CREATE    = 'create'; // No post carries this key yet.
-	public const RESTORE   = 'restore'; // Exists but trashed.
 	public const UNCHANGED = 'unchanged'; // Exists; the seeder never changes it.
 	public const ORPHAN    = 'orphan'; // Carries a seed key the manifest dropped.
 
diff --git a/plugin/src/Seeder/Verifier.php b/plugin/src/Seeder/Verifier.php
index 7b9b4a1..a6dd879 100644
--- a/plugin/src/Seeder/Verifier.php
+++ b/plugin/src/Seeder/Verifier.php
@@ -114,10 +114,11 @@ final class Verifier {
 			}
 
 			// Re-read rather than trusting the map: the ID could name a row that
-			// no longer exists.
+			// no longer exists. A trashed attachment is a person's decision; the
+			// plan says so, and the seeder leaves it there.
 			$attachment = get_post( $attachmentId );
-			if ( ! $attachment instanceof \WP_Post || 'attachment' !== $attachment->post_type || 'trash' === $attachment->post_status ) {
-				$problems[] = sprintf( 'media.%s: attachment %d is missing or trashed.', $key, $attachmentId );
+			if ( ! $attachment instanceof \WP_Post || 'attachment' !== $attachment->post_type ) {
+				$problems[] = sprintf( 'media.%s: attachment %d is missing.', $key, $attachmentId );
 			}
 		}
 
PATCH
```

- [ ] **Step 4: Run the tests to verify they pass**

```bash
npx wp-env run tests-wordpress --env-cwd=wp-content/plugins/pediment-ai ./vendor/bin/phpunit
npx wp-env run tests-wordpress --env-cwd=wp-content/plugins/pediment-ai ./vendor/bin/phpunit -c phpunit-polylang.xml.dist
composer lint -d plugin
grep -rn "PlanItem::RESTORE\|clearTrashBookkeeping" plugin/src plugin/inc plugin/wp-cli plugin/tests
```

Expected: both suites OK, lint 0 errors, grep prints nothing.

- [ ] **Step 5: Commit**

```bash
git add plugin/src/Seeder/MediaSeeder.php plugin/src/Seeder/Meta.php plugin/src/Seeder/PlanItem.php plugin/src/Seeder/Plan.php plugin/src/Seeder/Verifier.php plugin/tests/phpunit/Seeder/MediaSeederTest.php plugin/tests/phpunit/Seeder/RunnerTest.php plugin/tests/phpunit/Seeder/VerifierTest.php
git commit -m "feat(seeder)!: never restore media or reset the logo" -m "A seed run uploads missing media only. A trashed attachment stays in the trash, and the site logo is set only for a new logo attachment or a site without a logo." -m "BREAKING CHANGE: wp pediment seed no longer restores trashed seed media and no longer re-asserts site.logo over a logo chosen on the site."
```

---

### Task 4: Remove the content hashes

Nothing reads the hashes any more. `ContentHash`, `Meta::HASH`, `Meta::SOURCE`, the hash fields of `DesiredEntry` and `ActualEntry`, the Applier's hash writes and the Adopter's hash reset go (S6). Tests that only proved hash behaviour are deleted; tests that assert "no hash meta" use the literal key.

**Files:**
- Delete: `plugin/src/Seeder/ContentHash.php`, `plugin/tests/phpunit/Seeder/ContentHashTest.php`
- Modify: `plugin/src/Seeder/Meta.php`, `DesiredEntry.php:24`, `ActualEntry.php:21-23`, `StateReader.php:72-87`, `DesiredState.php` (`DesiredEntry` call), `Applier.php` (docblock, `create()`, `recordHashes()` removed), `Adopter.php` (docblock, hash writes and `resolveWritten()` removed, generated header text), `ContentResolver.php:98-106` (docblock), `Claimer.php:2-15` (docblock)
- Test: `plugin/tests/phpunit/Seeder/DifferTest.php`, `StateReaderTest.php`, `ApplierTest.php`, `AdopterTest.php`, `ClaimerTest.php`, `ClaimRunnerTest.php`, `SeedingAdminTest.php`, `plugin/tests/polylang/AdopterLanguageTest.php`, `ApplierTranslationTest.php`, `DesiredStateLanguageTest.php`, `plugin/tests/wpml/SeedingBehaviorTest.php`

**Interfaces:**
- Consumes: Tasks 1–3.
- Produces:
  - `DesiredEntry::__construct( string $key, string $language, string $postType, string $title, string $slug, ?string $parentKey, string $content, bool $frontPage, bool $postsPage, int $menuOrder, array $terms )`.
  - `ActualEntry::__construct( int $id, string $key, string $language, string $postType, string $title, string $slug, int $parentId, string $status, int $menuOrder )`.
  - `Meta` holds only `KEY`. `Adopter::adopt()` keeps its signature and return shape `array{path:string,bytes:int,written:bool,backup:string,errors:string[],warnings:string[]}` and writes no post meta.

- [ ] **Step 1: Write the failing tests**

```bash
git rm -q plugin/tests/phpunit/Seeder/ContentHashTest.php
git apply <<'PATCH'
diff --git a/plugin/tests/phpunit/Seeder/AdopterTest.php b/plugin/tests/phpunit/Seeder/AdopterTest.php
index 345d58b..e8fb07a 100644
--- a/plugin/tests/phpunit/Seeder/AdopterTest.php
+++ b/plugin/tests/phpunit/Seeder/AdopterTest.php
@@ -3,10 +3,8 @@
 
 use Pediment\Language\NullProvider;
 use Pediment\Seeder\Adopter;
-use Pediment\Seeder\ContentHash;
 use Pediment\Seeder\Manifest;
 use Pediment\Seeder\MediaSeeder;
-use Pediment\Seeder\Meta;
 use Pediment\Seeder\Runner;
 
 class AdopterTest extends WP_UnitTestCase {
@@ -56,20 +54,18 @@ class AdopterTest extends WP_UnitTestCase {
 		$this->assertStringContainsString( 'client copy', $contents );
 	}
 
-	public function test_adopt_resets_the_hashes_so_the_page_is_no_longer_protected() {
+	public function test_adopt_writes_the_file_and_nothing_else() {
 		$ids = ( new Runner() )->run()->ids;
 		$id  = $ids['home|'];
 		wp_update_post( [ 'ID' => $id, 'post_content' => '<p>client copy</p>' ] );
+		$metaBefore = get_post_meta( $id );
+		$postBefore = get_post( $id );
 
 		( new Adopter( new NullProvider() ) )->adopt( 'home' );
 
-		$this->assertSame( ContentHash::forPost( $id ), get_post_meta( $id, Meta::HASH, true ) );
-		// SOURCE is hashed from what the pattern FILE resolves to, not from the
-		// row — that is what the next run will compare against.
-		$this->assertSame(
-			ContentHash::compute( get_post( $id )->post_title, $this->patternFileContent() ),
-			get_post_meta( $id, Meta::SOURCE, true )
-		);
+		$this->assertSame( $metaBefore, get_post_meta( $id ) );
+		$this->assertSame( $postBefore->post_content, get_post( $id )->post_content );
+		$this->assertSame( $postBefore->post_modified_gmt, get_post( $id )->post_modified_gmt );
 	}
 
 	public function test_dry_run_writes_no_file() {
@@ -99,8 +95,8 @@ class AdopterTest extends WP_UnitTestCase {
 	}
 
 	public function test_the_next_seed_sees_an_adopted_page_as_unchanged() {
-		// The promise of adopt: the client's version becomes the source of truth,
-		// so the run right after it must plan nothing.
+		// The live page and the pattern file now agree, and the run right
+		// after adopt plans nothing.
 		$ids = ( new Runner() )->run()->ids;
 		wp_update_post( [ 'ID' => $ids['home|'], 'post_content' => '<!-- wp:paragraph --><p>client copy</p><!-- /wp:paragraph -->' ] );
 
@@ -113,9 +109,6 @@ class AdopterTest extends WP_UnitTestCase {
 	}
 
 	public function test_the_next_seed_sees_an_adopted_page_with_media_as_unchanged() {
-		// The two halves of adopt — placeholder restoration and hash resetting —
-		// have to agree: the source hash it stores is compared against a hash the
-		// seeder computes AFTER placeholders expand.
 		wp_mkdir_p( $this->dir . '/seed/media' );
 		file_put_contents( $this->dir . '/seed/media/hero.svg', '<svg xmlns="http://www.w3.org/2000/svg"/>' );
 		remove_all_filters( 'pediment_seed_manifest' );
diff --git a/plugin/tests/phpunit/Seeder/ApplierTest.php b/plugin/tests/phpunit/Seeder/ApplierTest.php
index 51c6efa..57fc6eb 100644
--- a/plugin/tests/phpunit/Seeder/ApplierTest.php
+++ b/plugin/tests/phpunit/Seeder/ApplierTest.php
@@ -2,7 +2,6 @@
 
 use Pediment\Language\NullProvider;
 use Pediment\Seeder\Applier;
-use Pediment\Seeder\ContentHash;
 use Pediment\Seeder\ContentResolver;
 use Pediment\Seeder\DesiredState;
 use Pediment\Seeder\Differ;
@@ -50,7 +49,7 @@ class ApplierTest extends WP_UnitTestCase {
 		return [ 'pages' => $pages ];
 	}
 
-	public function test_creates_pages_with_key_slug_and_both_hashes() {
+	public function test_creates_pages_with_key_and_slug_and_no_hash_meta() {
 		$ids = $this->seed( $this->manifest( [ 'home' => [ 'title' => 'Home', 'content' => '<p>hi</p>' ] ] ) );
 		$id  = $ids['home|'];
 
@@ -59,8 +58,8 @@ class ApplierTest extends WP_UnitTestCase {
 		$this->assertSame( 'home', $post->post_name );
 		$this->assertSame( 'publish', $post->post_status );
 		$this->assertSame( 'home', get_post_meta( $id, Meta::KEY, true ) );
-		$this->assertSame( ContentHash::forPost( $id ), get_post_meta( $id, Meta::HASH, true ) );
-		$this->assertSame( ContentHash::compute( 'Home', '<p>hi</p>' ), get_post_meta( $id, Meta::SOURCE, true ) );
+		$this->assertSame( '', get_post_meta( $id, '_pediment_seed_hash', true ) );
+		$this->assertSame( '', get_post_meta( $id, '_pediment_seed_source', true ) );
 	}
 
 	public function test_block_attribute_json_survives_the_write() {
diff --git a/plugin/tests/phpunit/Seeder/ClaimRunnerTest.php b/plugin/tests/phpunit/Seeder/ClaimRunnerTest.php
index c997800..f649ef7 100644
--- a/plugin/tests/phpunit/Seeder/ClaimRunnerTest.php
+++ b/plugin/tests/phpunit/Seeder/ClaimRunnerTest.php
@@ -49,7 +49,7 @@ class ClaimRunnerTest extends WP_UnitTestCase {
 		$this->assertTrue( $result->applied );
 		$this->assertSame( [], $result->errors );
 		$this->assertSame( 'home', get_post_meta( $id, Meta::KEY, true ) );
-		$this->assertSame( '', get_post_meta( $id, Meta::HASH, true ) );
+		$this->assertSame( '', get_post_meta( $id, '_pediment_seed_hash', true ) );
 	}
 
 	/**
diff --git a/plugin/tests/phpunit/Seeder/ClaimerTest.php b/plugin/tests/phpunit/Seeder/ClaimerTest.php
index b39f7aa..112f2b4 100644
--- a/plugin/tests/phpunit/Seeder/ClaimerTest.php
+++ b/plugin/tests/phpunit/Seeder/ClaimerTest.php
@@ -121,8 +121,8 @@ class ClaimerTest extends WP_UnitTestCase {
 		$this->assertSame( 1, $first['claimed'] );
 		$this->assertSame( [], $first['errors'] );
 		$this->assertSame( 'about', get_post_meta( $id, Meta::KEY, true ) );
-		$this->assertSame( '', get_post_meta( $id, Meta::HASH, true ) );
-		$this->assertSame( '', get_post_meta( $id, Meta::SOURCE, true ) );
+		$this->assertSame( '', get_post_meta( $id, '_pediment_seed_hash', true ) );
+		$this->assertSame( '', get_post_meta( $id, '_pediment_seed_source', true ) );
 		$this->assertSame( 'live copy', get_post( $id )->post_content );
 		$this->assertSame( 'Live title', get_post( $id )->post_title );
 
diff --git a/plugin/tests/phpunit/Seeder/DifferTest.php b/plugin/tests/phpunit/Seeder/DifferTest.php
index b3e2c3f..db810e3 100644
--- a/plugin/tests/phpunit/Seeder/DifferTest.php
+++ b/plugin/tests/phpunit/Seeder/DifferTest.php
@@ -19,7 +19,7 @@ class DifferTest extends WP_UnitTestCase {
 		);
 		return new DesiredEntry(
 			$d['key'], $d['language'], $d['postType'], $d['title'], $d['slug'], $d['parentKey'],
-			$d['content'], $d['frontPage'], $d['postsPage'], $d['menuOrder'], $d['terms'], ''
+			$d['content'], $d['frontPage'], $d['postsPage'], $d['menuOrder'], $d['terms']
 		);
 	}
 
@@ -33,7 +33,7 @@ class DifferTest extends WP_UnitTestCase {
 		);
 		return new ActualEntry(
 			$d['id'], $d['key'], $d['language'], $d['postType'], $d['title'], $d['slug'], $d['parentId'],
-			$d['status'], $d['menuOrder'], '', '', ''
+			$d['status'], $d['menuOrder']
 		);
 	}
 
diff --git a/plugin/tests/phpunit/Seeder/SeedingAdminTest.php b/plugin/tests/phpunit/Seeder/SeedingAdminTest.php
index 4de577c..32d931b 100644
--- a/plugin/tests/phpunit/Seeder/SeedingAdminTest.php
+++ b/plugin/tests/phpunit/Seeder/SeedingAdminTest.php
@@ -132,7 +132,7 @@ class SeedingAdminTest extends WP_UnitTestCase {
 
 		$this->assertStringContainsString( 'Pediment claim', $report );
 		$this->assertSame( 'home', get_post_meta( $id, \Pediment\Seeder\Meta::KEY, true ) );
-		$this->assertSame( '', get_post_meta( $id, \Pediment\Seeder\Meta::HASH, true ) );
+		$this->assertSame( '', get_post_meta( $id, '_pediment_seed_hash', true ) );
 		$this->assertSame( 'Legacy home', get_post( $id )->post_title );
 	}
 
diff --git a/plugin/tests/phpunit/Seeder/StateReaderTest.php b/plugin/tests/phpunit/Seeder/StateReaderTest.php
index 7612b4d..882daf1 100644
--- a/plugin/tests/phpunit/Seeder/StateReaderTest.php
+++ b/plugin/tests/phpunit/Seeder/StateReaderTest.php
@@ -2,7 +2,6 @@
 // plugin/tests/phpunit/Seeder/StateReaderTest.php
 
 use Pediment\Language\NullProvider;
-use Pediment\Seeder\ContentHash;
 use Pediment\Seeder\ContentResolver;
 use Pediment\Seeder\DesiredState;
 use Pediment\Seeder\Manifest;
@@ -17,8 +16,6 @@ class StateReaderTest extends WP_UnitTestCase {
 			array_merge( [ 'post_type' => 'page', 'post_title' => 'T', 'post_content' => 'C' ], $args )
 		);
 		update_post_meta( $id, Meta::KEY, $key );
-		update_post_meta( $id, Meta::HASH, ContentHash::forPost( $id ) );
-		update_post_meta( $id, Meta::SOURCE, ContentHash::compute( 'T', 'C' ) );
 		return $id;
 	}
 
@@ -34,7 +31,6 @@ class StateReaderTest extends WP_UnitTestCase {
 		$this->assertSame( 'Home', $entry->title );
 		$this->assertSame( '<p>h</p>', $entry->content );
 		$this->assertTrue( $entry->frontPage );
-		$this->assertSame( ContentHash::compute( 'Home', '<p>h</p>' ), $entry->sourceHash );
 	}
 
 	public function test_reads_seeded_entries_by_key_not_slug() {
@@ -45,16 +41,6 @@ class StateReaderTest extends WP_UnitTestCase {
 		$this->assertArrayHasKey( 'home|', $actual );
 		$this->assertSame( $id, $actual['home|']->id );
 		$this->assertSame( 'startseite', $actual['home|']->slug );
-		$this->assertTrue( ContentHash::matches( $actual['home|']->storedHash, $actual['home|']->currentHash ) );
-	}
-
-	public function test_a_client_edit_shows_as_a_hash_mismatch() {
-		$id = $this->seeded( 'home' );
-		wp_update_post( [ 'ID' => $id, 'post_content' => 'client wrote this' ] );
-
-		$actual = ( new StateReader( new NullProvider() ) )->read()['home|'];
-
-		$this->assertFalse( ContentHash::matches( $actual->storedHash, $actual->currentHash ) );
 	}
 
 	public function test_drafts_and_trashed_entries_are_still_found() {
diff --git a/plugin/tests/polylang/AdopterLanguageTest.php b/plugin/tests/polylang/AdopterLanguageTest.php
index 34cf021..acddb7d 100644
--- a/plugin/tests/polylang/AdopterLanguageTest.php
+++ b/plugin/tests/polylang/AdopterLanguageTest.php
@@ -2,7 +2,6 @@
 
 use Pediment\Language\PolylangProvider;
 use Pediment\Seeder\Adopter;
-use Pediment\Seeder\ContentHash;
 use Pediment\Seeder\Manifest;
 use Pediment\Seeder\Meta;
 
@@ -80,32 +79,4 @@ class AdopterLanguageTest extends PolylangTestCase {
 
 		$this->assertStringEndsWith( '/patterns/adoptme.php', $result['path'] );
 	}
-
-	public function test_the_german_hashes_are_written_against_the_german_post() {
-		$this->manifest();
-		$this->page( 'en', '<p>english</p>' );
-		$de = $this->page( 'de', '<p>deutsch</p>' );
-
-		( new Adopter( new PolylangProvider() ) )->adopt( 'adoptme', 'de' );
-
-		$this->assertSame( ContentHash::forPost( $de ), get_post_meta( $de, Meta::HASH, true ) );
-
-		// HASH alone is self-referential (it is always the live post's own hash,
-		// fix or bug) and does not prove SOURCE was crossed with the GERMAN
-		// title. Assert the exact value the next seed will compare against,
-		// keyed on "Übernimm mich" — the manifest's declared German title, not
-		// the English default — so a regression that hashes the wrong title is
-		// caught here rather than three months from now as a silent overwrite.
-		$this->assertSame(
-			ContentHash::compute( 'Übernimm mich', $this->resolvedPatternContent( $this->dir . '/adoptme.de.php' ) ),
-			get_post_meta( $de, Meta::SOURCE, true )
-		);
-	}
-
-	/** The bytes the pattern registry would read from the written file. */
-	private function resolvedPatternContent( string $file ): string {
-		ob_start();
-		include $file;
-		return (string) ob_get_clean();
-	}
 }
diff --git a/plugin/tests/polylang/ApplierTranslationTest.php b/plugin/tests/polylang/ApplierTranslationTest.php
index 62a8eab..2feb513 100644
--- a/plugin/tests/polylang/ApplierTranslationTest.php
+++ b/plugin/tests/polylang/ApplierTranslationTest.php
@@ -2,7 +2,6 @@
 
 use Pediment\Language\PolylangProvider;
 use Pediment\Seeder\Applier;
-use Pediment\Seeder\ContentHash;
 use Pediment\Seeder\ContentResolver;
 use Pediment\Seeder\DesiredEntry;
 use Pediment\Seeder\DesiredState;
@@ -135,7 +134,7 @@ class ApplierTranslationTest extends PolylangTestCase {
 		$lang->setLanguage( $postId, 'de' );
 		update_post_meta( $postId, Meta::KEY, 'about' );
 
-		$entry   = new DesiredEntry( 'about', 'en', 'page', 'About', 'about', null, '<p>x</p>', false, false, 0, [], ContentHash::compute( 'About', '<p>x</p>' ) );
+		$entry   = new DesiredEntry( 'about', 'en', 'page', 'About', 'about', null, '<p>x</p>', false, false, 0, [] );
 		$desired = [ 'about|en' => $entry ];
 		// The same already-tagged post, planned UNCHANGED under the 'en' map
 		// key — models a post whose Polylang language disagrees with what the
diff --git a/plugin/tests/polylang/DesiredStateLanguageTest.php b/plugin/tests/polylang/DesiredStateLanguageTest.php
index 567a86d..a11fba9 100644
--- a/plugin/tests/polylang/DesiredStateLanguageTest.php
+++ b/plugin/tests/polylang/DesiredStateLanguageTest.php
@@ -46,12 +46,6 @@ class DesiredStateLanguageTest extends PolylangTestCase {
 		$this->assertSame( 'home', $desired['home|en']->slug );
 	}
 
-	public function test_the_hashes_differ_per_language() {
-		$desired = ( new DesiredState( new PolylangProvider(), new ContentResolver( new MediaMap( [] ) ) ) )->build( $this->manifest() );
-
-		$this->assertNotSame( $desired['home|en']->sourceHash, $desired['home|de']->sourceHash );
-	}
-
 	public function test_a_missing_translated_pattern_is_reported() {
 		$state = new DesiredState( new PolylangProvider(), new ContentResolver( new MediaMap( [] ) ) );
 		$state->build( $this->manifest() );
diff --git a/plugin/tests/wpml/SeedingBehaviorTest.php b/plugin/tests/wpml/SeedingBehaviorTest.php
index 4d2f11b..b1d76fd 100644
--- a/plugin/tests/wpml/SeedingBehaviorTest.php
+++ b/plugin/tests/wpml/SeedingBehaviorTest.php
@@ -20,7 +20,6 @@ use Pediment\Language\LanguageRegistry;
 use Pediment\Language\WpmlProvider;
 use Pediment\Seeder\Adopter;
 use Pediment\Seeder\Claimer;
-use Pediment\Seeder\ContentHash;
 use Pediment\Seeder\ContentResolver;
 use Pediment\Seeder\DesiredState;
 use Pediment\Seeder\Differ;
@@ -171,7 +170,7 @@ class SeedingBehaviorTest extends WpmlTestCase {
 		$lang->setLanguage( $postId, 'de' );
 		update_post_meta( $postId, Meta::KEY, 'about' );
 
-		$entry   = new \Pediment\Seeder\DesiredEntry( 'about', 'en', 'page', 'About', 'about', null, '<p>x</p>', false, false, 0, [], ContentHash::compute( 'About', '<p>x</p>' ) );
+		$entry   = new \Pediment\Seeder\DesiredEntry( 'about', 'en', 'page', 'About', 'about', null, '<p>x</p>', false, false, 0, [] );
 		$desired = [ 'about|en' => $entry ];
 		$item    = new PlanItem( PlanItem::UNCHANGED, PlanItem::KIND_ENTRY, 'about', 'en', $postId );
 		$plan    = new \Pediment\Seeder\Plan( [ $item ], [] );
@@ -210,12 +209,6 @@ class SeedingBehaviorTest extends WpmlTestCase {
 		$this->assertSame( 'home', $desired['home|en']->slug );
 	}
 
-	public function test_the_hashes_differ_per_language() {
-		$desired = ( new DesiredState( new WpmlProvider(), new ContentResolver( new MediaMap( [] ) ) ) )->build( $this->desiredManifest() );
-
-		$this->assertNotSame( $desired['home|en']->sourceHash, $desired['home|de']->sourceHash );
-	}
-
 	// ---------------------------------------------------------------------
 	// ClaimerLanguageTest — legacy content claimed per language via the provider.
 	// ---------------------------------------------------------------------
PATCH
```

This deletes `StateReaderTest::test_a_client_edit_shows_as_a_hash_mismatch`, `AdopterLanguageTest::test_the_german_hashes_are_written_against_the_german_post` and the two `test_the_hashes_differ_per_language` tests, and turns `AdopterTest::test_adopt_resets_the_hashes_so_the_page_is_no_longer_protected` into `test_adopt_writes_the_file_and_nothing_else`.

- [ ] **Step 2: Run the tests to verify they fail**

Run: `npx wp-env run tests-wordpress --env-cwd=wp-content/plugins/pediment-ai ./vendor/bin/phpunit tests/phpunit/Seeder`
Expected: FAIL — `DifferTest` with `ArgumentCountError` (the entries still require the hash arguments), `ApplierTest::test_creates_pages_with_key_and_slug_and_no_hash_meta` (`_pediment_seed_hash` is set), `AdopterTest::test_adopt_writes_the_file_and_nothing_else` (meta changed).

- [ ] **Step 3: Implement**

```bash
git rm -q plugin/src/Seeder/ContentHash.php
git apply <<'PATCH'
diff --git a/plugin/src/Seeder/ActualEntry.php b/plugin/src/Seeder/ActualEntry.php
index a80c643..4978b66 100644
--- a/plugin/src/Seeder/ActualEntry.php
+++ b/plugin/src/Seeder/ActualEntry.php
@@ -17,10 +17,7 @@ final class ActualEntry {
 		public readonly string $slug,
 		public readonly int $parentId,
 		public readonly string $status,
-		public readonly int $menuOrder,
-		public readonly string $storedHash,
-		public readonly string $currentHash,
-		public readonly string $sourceHash
+		public readonly int $menuOrder
 	) {}
 
 	public function mapKey(): string {
diff --git a/plugin/src/Seeder/Adopter.php b/plugin/src/Seeder/Adopter.php
index 358a91d..0922131 100644
--- a/plugin/src/Seeder/Adopter.php
+++ b/plugin/src/Seeder/Adopter.php
@@ -1,7 +1,8 @@
 <?php
 /**
  * The inverse of seeding: export a live entry's markup back into the theme's
- * pattern file and clear the "client edited this" state.
+ * pattern file, so a seed in a NEW environment creates the page as it is live.
+ * It writes the file only — never post meta, never the post.
  *
  * @package Pediment
  */
@@ -112,11 +113,9 @@ final class Adopter {
 		// cache expires (30 minutes), which is exactly the first-adopt case.
 		wp_get_theme()->delete_pattern_cache();
 
-		// Read the file back the way the pattern registry will, and hash THAT as
-		// the source. Hashing the database row instead leaves the two a newline
-		// apart, and the very next seed rewrites the page adopt just blessed.
-		$resolved = $this->resolveWritten( $file );
-		$header   = get_file_data( $file, [ 'slug' => 'Slug' ] );
+		// Read the header back the way the pattern registry will: a file whose
+		// Slug does not match is a file the next seed cannot find.
+		$header = get_file_data( $file, [ 'slug' => 'Slug' ] );
 		if ( $header['slug'] !== $pattern ) {
 			$this->rollback( $file, $backup );
 			return array_merge(
@@ -125,21 +124,6 @@ final class Adopter {
 			);
 		}
 
-		// The live row is now the source of truth in git too, so it is no longer
-		// "edited" — the next seed will treat it as up to date. The source hash
-		// has to be the shape DesiredState will compute on that next run: the
-		// language's declared title (adopt never writes titles back) crossed
-		// with the pattern output AFTER media placeholders expand. Hashing the
-		// raw file bytes instead leaves every media-bearing page mismatched
-		// forever; hashing the default-language title instead of the language's
-		// own leaves every non-default adopt mismatched on the very next seed.
-		update_post_meta( $actual->id, Meta::HASH, ContentHash::forPost( $actual->id ) );
-		update_post_meta(
-			$actual->id,
-			Meta::SOURCE,
-			ContentHash::compute( $declaredTitle, ( new ContentResolver( $media ) )->rewriteMarkup( $resolved ) )
-		);
-
 		return [ 'path' => $file, 'bytes' => strlen( $contents ), 'written' => true, 'backup' => $backup, 'errors' => [], 'warnings' => $warnings ];
 	}
 
@@ -166,13 +150,6 @@ final class Adopter {
 		return $last;
 	}
 
-	/** The bytes the pattern registry will see, produced the way it produces them. */
-	private function resolveWritten( string $file ): string {
-		ob_start();
-		include $file;
-		return (string) ob_get_clean();
-	}
-
 	/** Put the file back the way it was when a post-write check fails. */
 	private function rollback( string $file, string $backup ): void {
 		if ( '' !== $backup && is_readable( $backup ) ) {
@@ -230,7 +207,8 @@ final class Adopter {
 			. " * Categories: pediment\n"
 			. " * Inserter: no\n"
 			. " *\n"
-			. " * Adopted from the live site by `wp pediment adopt`. Edit here, then re-seed.\n"
+			. " * Adopted from the live site by `wp pediment adopt`. A seed creates the page\n"
+			. " * from this file in a new environment; it never updates a page that exists.\n"
 			. " *\n"
 			. " * @package Pediment\n"
 			. " */\n"
diff --git a/plugin/src/Seeder/Applier.php b/plugin/src/Seeder/Applier.php
index 6888308..3c2ceeb 100644
--- a/plugin/src/Seeder/Applier.php
+++ b/plugin/src/Seeder/Applier.php
@@ -16,8 +16,6 @@
  *     never re-tags or moves a post that already carries one. That and the
  *     translation-group link are the only things the seeder does to a row it
  *     did not create in this run.
- *   - _pediment_seed_hash comes from the PERSISTED row, _pediment_seed_source
- *     from the input.
  *
  * @package Pediment
  */
@@ -129,16 +127,10 @@ final class Applier {
 		}
 
 		update_post_meta( $postId, Meta::KEY, $entry->key );
-		$this->recordHashes( $postId, $entry );
 
 		return $postId;
 	}
 
-	private function recordHashes( int $postId, DesiredEntry $entry ): void {
-		update_post_meta( $postId, Meta::HASH, ContentHash::forPost( $postId ) );
-		update_post_meta( $postId, Meta::SOURCE, $entry->sourceHash );
-	}
-
 	/** @param array<string,int> $ids */
 	private function parentId( DesiredEntry $entry, array $ids ): int {
 		if ( null === $entry->parentKey ) {
diff --git a/plugin/src/Seeder/Claimer.php b/plugin/src/Seeder/Claimer.php
index 521f547..5aaff63 100644
--- a/plugin/src/Seeder/Claimer.php
+++ b/plugin/src/Seeder/Claimer.php
@@ -8,10 +8,9 @@
  * such a site plans a CREATE for every entry and duplicates the whole site.
  *
  * A claim is the one-time bridge: it matches by the things a legacy row and a
- * manifest entry can still agree on, and writes exactly one meta key. It never
- * writes a hash, so every claimed row stays protected under the Differ's rule
- * 2 (missing hash = treat as edited); bringing a page under git's control is
- * still an explicit `wp pediment adopt`.
+ * manifest entry can still agree on, and writes exactly one meta key. The
+ * seed after a claim finds every claimed row and leaves it as it is — the
+ * seeder never writes to a row that exists.
  *
  * @package Pediment
  */
diff --git a/plugin/src/Seeder/ContentResolver.php b/plugin/src/Seeder/ContentResolver.php
index d3c5584..998dc35 100644
--- a/plugin/src/Seeder/ContentResolver.php
+++ b/plugin/src/Seeder/ContentResolver.php
@@ -98,10 +98,6 @@ final class ContentResolver {
 	/**
 	 * Expand `{{media_url:…}}` / `{{media_id:…}}` in arbitrary markup.
 	 *
-	 * Public because `Adopter` has to hash the same shape the seeder will: the
-	 * bytes it writes back into the pattern file still carry placeholders, and a
-	 * source hash taken from those can never match what `DesiredState` computes.
-	 *
 	 * @throws ManifestError When the PCRE engine itself fails while rewriting placeholders.
 	 */
 	public function rewriteMarkup( string $content ): string {
diff --git a/plugin/src/Seeder/DesiredEntry.php b/plugin/src/Seeder/DesiredEntry.php
index 89af5dc..a3b912b 100644
--- a/plugin/src/Seeder/DesiredEntry.php
+++ b/plugin/src/Seeder/DesiredEntry.php
@@ -20,8 +20,7 @@ final class DesiredEntry {
 		public readonly bool $frontPage,
 		public readonly bool $postsPage,
 		public readonly int $menuOrder,
-		public readonly array $terms,
-		public readonly string $sourceHash
+		public readonly array $terms
 	) {}
 
 	public function id(): string {
diff --git a/plugin/src/Seeder/DesiredState.php b/plugin/src/Seeder/DesiredState.php
index e464b96..9a2a31e 100644
--- a/plugin/src/Seeder/DesiredState.php
+++ b/plugin/src/Seeder/DesiredState.php
@@ -78,8 +78,7 @@ final class DesiredState {
 					$spec->frontPage,
 					$spec->postsPage,
 					$spec->menuOrder,
-					$spec->terms,
-					ContentHash::compute( $title, $content )
+					$spec->terms
 				);
 
 				$desired[ $entry->id() ] = $entry;
diff --git a/plugin/src/Seeder/Meta.php b/plugin/src/Seeder/Meta.php
index a89eb06..d096c37 100644
--- a/plugin/src/Seeder/Meta.php
+++ b/plugin/src/Seeder/Meta.php
@@ -16,10 +16,4 @@ if ( ! defined( 'ABSPATH' ) ) {
 final class Meta {
 	/** Stable identity. Never look seeded content up by slug. */
 	public const KEY = '_pediment_seed_key';
-
-	/** Hash of the PERSISTED row as last written by the seeder; arbitrates content. */
-	public const HASH = '_pediment_seed_hash';
-
-	/** Hash of the git-side INPUT the seeder last wrote; detects content changes. */
-	public const SOURCE = '_pediment_seed_source';
 }
diff --git a/plugin/src/Seeder/StateReader.php b/plugin/src/Seeder/StateReader.php
index cfeb78f..0a118da 100644
--- a/plugin/src/Seeder/StateReader.php
+++ b/plugin/src/Seeder/StateReader.php
@@ -79,10 +79,7 @@ final class StateReader {
 			(string) $post->post_name,
 			(int) $post->post_parent,
 			(string) $post->post_status,
-			(int) $post->menu_order,
-			(string) get_post_meta( $post->ID, Meta::HASH, true ),
-			ContentHash::forPost( (int) $post->ID ),
-			(string) get_post_meta( $post->ID, Meta::SOURCE, true )
+			(int) $post->menu_order
 		);
 	}
 
PATCH
```

- [ ] **Step 4: Run the tests to verify they pass**

```bash
npx wp-env run tests-wordpress --env-cwd=wp-content/plugins/pediment-ai ./vendor/bin/phpunit
npx wp-env run tests-wordpress --env-cwd=wp-content/plugins/pediment-ai ./vendor/bin/phpunit -c phpunit-polylang.xml.dist
composer lint -d plugin
git grep -n -E 'ContentHash|Meta::HASH|Meta::SOURCE|sourceHash|storedHash|currentHash' -- plugin
```

Expected: both suites OK, lint 0 errors, `git grep` prints nothing. (`_pediment_seed_hash` still appears as a literal in tests that assert it is absent; Task 5 adds the routine that deletes it.)

- [ ] **Step 5: Commit**

```bash
git add plugin/src/Seeder/Meta.php plugin/src/Seeder/DesiredEntry.php plugin/src/Seeder/ActualEntry.php plugin/src/Seeder/StateReader.php plugin/src/Seeder/DesiredState.php plugin/src/Seeder/Applier.php plugin/src/Seeder/Adopter.php plugin/src/Seeder/ContentResolver.php plugin/src/Seeder/Claimer.php plugin/tests/phpunit/Seeder/DifferTest.php plugin/tests/phpunit/Seeder/StateReaderTest.php plugin/tests/phpunit/Seeder/ApplierTest.php plugin/tests/phpunit/Seeder/AdopterTest.php plugin/tests/phpunit/Seeder/ClaimerTest.php plugin/tests/phpunit/Seeder/ClaimRunnerTest.php plugin/tests/phpunit/Seeder/SeedingAdminTest.php plugin/tests/polylang/AdopterLanguageTest.php plugin/tests/polylang/ApplierTranslationTest.php plugin/tests/polylang/DesiredStateLanguageTest.php plugin/tests/wpml/SeedingBehaviorTest.php
git commit -m "refactor(seeder): remove the content-arbitration hashes"
```

(`git rm` already staged the two deleted files.)

---

### Task 5: Delete the 3.x hash meta once per site

`Pediment\Upgrade\SeedHashCleanup` follows `ChatDataCleanup`: a done flag, `maybeRun()`, `run()`. It deletes `_pediment_seed_hash`, `_pediment_seed_source` and `_pediment_seed_mega_hash` (S8) on the first admin request after the update (production is admin-only hosting). Its test also pins Review Focus 1.

**Files:**
- Create: `plugin/src/Upgrade/SeedHashCleanup.php`, `plugin/tests/phpunit/Upgrade/SeedHashCleanupTest.php`
- Modify: `plugin/plugin.php:93-98` (hook), `plugin/uninstall.php:15-16` (flag)

**Interfaces:**
- Consumes: `Runner`, `Meta::KEY`, `PlanItem` from Tasks 1–4.
- Produces: `SeedHashCleanup::META_KEYS` (`list<string>`, the three keys), `SeedHashCleanup::DONE_OPTION = 'pediment_seed_hash_cleanup_done'`, `SeedHashCleanup::maybeRun(): void` (returns at once when the flag is `'1'`), `SeedHashCleanup::run(): void` (`delete_post_meta_by_key()` per key, then sets the autoloaded flag). Hooked as `add_action( 'admin_init', [ SeedHashCleanup::class, 'maybeRun' ] )`.

- [ ] **Step 1: Write the failing test**

Create `plugin/tests/phpunit/Upgrade/SeedHashCleanupTest.php`:

```php
<?php

use Pediment\Seeder\Manifest;
use Pediment\Seeder\Meta;
use Pediment\Seeder\PlanItem;
use Pediment\Seeder\Runner;
use Pediment\Upgrade\SeedHashCleanup;

class SeedHashCleanupTest extends WP_UnitTestCase {

	public function set_up(): void {
		parent::set_up();
		delete_option( SeedHashCleanup::DONE_OPTION );
	}

	public function tear_down(): void {
		remove_all_filters( 'pediment_seed_manifest' );
		Manifest::resetCache();
		parent::tear_down();
	}

	/** A post the way a 3.x seed left it: identity plus the two arbitration hashes. */
	private function legacyPage(): int {
		$id = self::factory()->post->create( [ 'post_type' => 'page', 'post_title' => 'About' ] );
		update_post_meta( $id, Meta::KEY, 'about' );
		update_post_meta( $id, '_pediment_seed_hash', '1:' . str_repeat( 'a', 64 ) );
		update_post_meta( $id, '_pediment_seed_source', '1:' . str_repeat( 'b', 64 ) );
		return $id;
	}

	public function test_removes_the_three_keys_and_keeps_the_identity(): void {
		$page = $this->legacyPage();
		$nav  = self::factory()->post->create( [ 'post_type' => 'wp_navigation' ] );
		update_post_meta( $nav, Meta::KEY, 'primary' );
		update_post_meta( $nav, '_pediment_seed_mega_hash', '["1:abc"]' );

		SeedHashCleanup::run();

		foreach ( SeedHashCleanup::META_KEYS as $key ) {
			$this->assertSame( '', get_post_meta( $page, $key, true ), $key );
			$this->assertSame( '', get_post_meta( $nav, $key, true ), $key );
		}
		$this->assertSame( 'about', get_post_meta( $page, Meta::KEY, true ) );
		$this->assertSame( 'primary', get_post_meta( $nav, Meta::KEY, true ) );
		$this->assertSame( '1', get_option( SeedHashCleanup::DONE_OPTION ) );
	}

	public function test_does_not_touch_other_meta(): void {
		$page = $this->legacyPage();
		update_post_meta( $page, '_wp_page_template', 'page-wide' );

		SeedHashCleanup::run();

		$this->assertSame( 'page-wide', get_post_meta( $page, '_wp_page_template', true ) );
	}

	public function test_a_finished_cleanup_does_not_run_again(): void {
		update_option( SeedHashCleanup::DONE_OPTION, '1', true );
		$page = $this->legacyPage();

		SeedHashCleanup::maybeRun();

		$this->assertNotSame( '', get_post_meta( $page, '_pediment_seed_hash', true ), 'the flag short-circuits the routine' );
	}

	public function test_a_fresh_site_records_that_nothing_needs_cleaning(): void {
		SeedHashCleanup::maybeRun();

		$this->assertSame( '1', get_option( SeedHashCleanup::DONE_OPTION ) );
	}

	public function test_it_runs_on_admin_init(): void {
		$this->assertNotFalse( has_action( 'admin_init', array( SeedHashCleanup::class, 'maybeRun' ) ) );
	}

	public function test_a_v3_site_upgrades_without_any_content_change(): void {
		// A 3.x site: seeded, then a person rewrote the page, so its stored hash
		// no longer matches. 4.0 must not read the stale hashes at all — the
		// page stays as the person left it, before and after the cleanup.
		add_filter( 'pediment_seed_manifest', static fn() => [ 'pages' => [ 'about' => [ 'title' => 'About', 'content' => '<p>from git</p>' ] ] ] );
		Manifest::resetCache();
		$page = $this->legacyPage();
		wp_update_post( [ 'ID' => $page, 'post_content' => '<p>written live</p>' ] );
		$before = get_post( $page )->post_content;

		$beforeCleanup = ( new Runner() )->run();
		SeedHashCleanup::maybeRun();
		$afterCleanup = ( new Runner() )->run();

		foreach ( [ $beforeCleanup, $afterCleanup ] as $result ) {
			$this->assertTrue( $result->ok(), implode( "\n", array_merge( $result->errors, $result->problems ) ) );
			$this->assertTrue( $result->plan->isEmpty() );
			$this->assertSame( PlanItem::UNCHANGED, $result->plan->byKind( PlanItem::KIND_ENTRY )[0]->action );
		}
		$this->assertSame( $before, get_post( $page )->post_content );
		$this->assertSame( '', get_post_meta( $page, '_pediment_seed_hash', true ) );
	}
}
```

- [ ] **Step 2: Run the test to verify it fails**

Run: `npx wp-env run tests-wordpress --env-cwd=wp-content/plugins/pediment-ai ./vendor/bin/phpunit --filter SeedHashCleanupTest`
Expected: FAIL — `Error: Class "Pediment\Upgrade\SeedHashCleanup" not found`.

- [ ] **Step 3: Implement**

Create `plugin/src/Upgrade/SeedHashCleanup.php`:

```php
<?php
/**
 * Removes the content-arbitration meta the 3.x seeder left on seeded posts.
 *
 * @package Pediment
 */

declare(strict_types=1);

namespace Pediment\Upgrade;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Pediment 4 seeds bootstrap-only and reads none of these keys. Runs once per
 * site, on the first admin request after the update (production is admin-only
 * hosting, so this is the request that always comes), and records an
 * autoloaded flag so later requests pay nothing. Deleting is the safe
 * direction: a site rolled back to 3.x treats an unhashed row as edited and
 * never overwrites it.
 */
final class SeedHashCleanup {

	public const META_KEYS = array(
		'_pediment_seed_hash',
		'_pediment_seed_source',
		'_pediment_seed_mega_hash',
	);

	public const DONE_OPTION = 'pediment_seed_hash_cleanup_done';

	/**
	 * Runs the cleanup unless it has already finished on this site.
	 */
	public static function maybeRun(): void {
		if ( '1' === get_option( self::DONE_OPTION ) ) {
			return;
		}
		self::run();
	}

	/**
	 * Deletes the three meta keys from every post (one query per key), then
	 * marks the cleanup done. `_pediment_seed_key`, the identity, stays.
	 */
	public static function run(): void {
		foreach ( self::META_KEYS as $key ) {
			delete_post_meta_by_key( $key );
		}
		update_option( self::DONE_OPTION, '1', true );
	}
}
```

Wire it in `plugin/plugin.php` and clear its flag on uninstall:

```bash
git apply <<'PATCH'
diff --git a/plugin/plugin.php b/plugin/plugin.php
index 656b2c1..a28ecbb 100644
--- a/plugin/plugin.php
+++ b/plugin/plugin.php
@@ -97,6 +97,9 @@ add_action(
 	5
 );
 
+// Pediment 4 seeds bootstrap-only; drop the 3.x content-arbitration meta once per site.
+add_action( 'admin_init', array( \Pediment\Upgrade\SeedHashCleanup::class, 'maybeRun' ) );
+
 if ( defined( 'WP_CLI' ) && WP_CLI ) {
 	require_once __DIR__ . '/wp-cli/SeedCommand.php';
 	\WP_CLI::add_command( 'pediment seed', \Pediment\Cli\SeedCommand::class );
diff --git a/plugin/uninstall.php b/plugin/uninstall.php
index 78de65e..9771403 100644
--- a/plugin/uninstall.php
+++ b/plugin/uninstall.php
@@ -14,3 +14,4 @@ require_once __DIR__ . '/vendor/autoload.php';
 
 \Pediment\Upgrade\ChatDataCleanup::run();
 delete_option( \Pediment\Upgrade\ChatDataCleanup::DONE_OPTION );
+delete_option( \Pediment\Upgrade\SeedHashCleanup::DONE_OPTION );
PATCH
```

- [ ] **Step 4: Run the tests to verify they pass**

```bash
npx wp-env run tests-wordpress --env-cwd=wp-content/plugins/pediment-ai ./vendor/bin/phpunit tests/phpunit/Upgrade
npx wp-env run tests-wordpress --env-cwd=wp-content/plugins/pediment-ai ./vendor/bin/phpunit
composer lint -d plugin
```

Expected: OK; lint 0 errors.

- [ ] **Step 5: Probe on the dev site**

```bash
id=$(npx wp-env run cli wp post list --post_type=page --name=about --field=ID | tail -n 1)
npx wp-env run cli wp post meta update "$id" _pediment_seed_hash 1:stale
npx wp-env run cli wp option delete pediment_seed_hash_cleanup_done
npx wp-env run cli wp eval 'var_dump( has_action( "admin_init", [ \Pediment\Upgrade\SeedHashCleanup::class, "maybeRun" ] ) );'
npx wp-env run cli wp eval '\Pediment\Upgrade\SeedHashCleanup::maybeRun();'
npx wp-env run cli wp post meta get "$id" _pediment_seed_hash
npx wp-env run cli wp option get pediment_seed_hash_cleanup_done
```

Expected: `int(10)` (the hook is registered), then the meta read prints nothing (deleted) and the option prints `1`.

- [ ] **Step 6: Commit**

```bash
git add plugin/src/Upgrade/SeedHashCleanup.php plugin/tests/phpunit/Upgrade/SeedHashCleanupTest.php plugin/plugin.php plugin/uninstall.php
git commit -m "feat(upgrade): drop the 3.x seed hash meta once per site"
```

---

### Task 6: Seeding docs and the wp-admin tab

Say "bootstrap only" everywhere the plugin explains seeding (S11): the wp-admin tab, `docs/seeding.md`, `docs/client-sites.md`, `docs/WORDPRESS_TRAPS.md`, `docs/STANDARDS.md`, the root `AGENTS.md`, `plugin/AGENTS.md`, `plugin/README.md`, and the comment in the seed-check action.

**Files:**
- Modify: `plugin/inc/seeding-admin.php:112-150`, `docs/seeding.md`, `docs/client-sites.md:150-215`, `docs/WORDPRESS_TRAPS.md:370-382,400-446,593-594` (and a new trap at the end), `docs/STANDARDS.md:35-38`, `AGENTS.md:28-32`, `plugin/AGENTS.md:29`, `plugin/README.md:18-30`, `.github/actions/seed-check/action.yml:116-119`
- Test: `plugin/tests/phpunit/Seeder/SeedingAdminTest.php`

**Interfaces:**
- Consumes: the behaviour of Tasks 1–5 (the docs describe it).
- Produces: no code interfaces. The wp-admin tab text contains "never changes anything that already exists".

- [ ] **Step 1: Write the failing test**

```bash
git apply <<'PATCH'
diff --git a/plugin/tests/phpunit/Seeder/SeedingAdminTest.php b/plugin/tests/phpunit/Seeder/SeedingAdminTest.php
index 32d931b..0f87254 100644
--- a/plugin/tests/phpunit/Seeder/SeedingAdminTest.php
+++ b/plugin/tests/phpunit/Seeder/SeedingAdminTest.php
@@ -147,4 +147,16 @@ class SeedingAdminTest extends WP_UnitTestCase {
 		$this->assertNull( $report );
 		$this->assertSame( '', get_post_meta( $id, \Pediment\Seeder\Meta::KEY, true ) );
 	}
+
+	public function test_the_tab_says_the_seed_only_creates_what_is_missing() {
+		wp_set_current_user( self::factory()->user->create( [ 'role' => 'administrator' ] ) );
+
+		ob_start();
+		pediment_seed_admin_render_tab();
+		$html = (string) ob_get_clean();
+
+		$this->assertStringContainsString( 'never changes anything that already exists', $html );
+		$this->assertStringNotContainsString( 'protected', $html );
+		$this->assertStringNotContainsString( 'rewrites', $html );
+	}
 }
PATCH
```

- [ ] **Step 2: Run the test to verify it fails**

Run: `npx wp-env run tests-wordpress --env-cwd=wp-content/plugins/pediment-ai ./vendor/bin/phpunit --filter SeedingAdminTest`
Expected: FAIL — `test_the_tab_says_the_seed_only_creates_what_is_missing` (the old text says "never overwritten" and "protected").

- [ ] **Step 3: Change the tab text**

```bash
git apply <<'PATCH'
diff --git a/plugin/inc/seeding-admin.php b/plugin/inc/seeding-admin.php
index 6f7b8c3..95a04ca 100644
--- a/plugin/inc/seeding-admin.php
+++ b/plugin/inc/seeding-admin.php
@@ -110,7 +110,7 @@ function pediment_seed_admin_render_tab(): void {
 	}
 
 	echo '<p>' . esc_html__(
-		'Applies the active theme\'s seed manifest. Structure (which pages exist, their slugs, nesting, menus) is owned by the theme; page content you have edited in the editor is never overwritten.',
+		'Creates what the active theme\'s seed manifest declares and this site does not have yet: pages, posts, menus, media and the site logo. It never changes anything that already exists — not page content, titles or slugs, not menus, not your reading settings or logo. Change live pages in the editor or through the Pediment MCP tools.',
 		'pediment'
 	) . '</p>';
 
@@ -132,20 +132,17 @@ function pediment_seed_admin_render_tab(): void {
 	echo '<hr />';
 	echo '<h3>' . esc_html__( 'Claim existing content', 'pediment' ) . '</h3>';
 	echo '<p>' . esc_html__(
-		'For a site whose pages were built before Pediment. Matches existing pages, posts and menus to the manifest by slug and language and gives them the identity the seeder resolves by. The claim itself writes nothing but that identity, and a claimed page then stays protected from content updates until you adopt it. Run this once, and preview first.',
+		'For a site whose pages were built before Pediment. Matches existing pages, posts and menus to the manifest by slug and language and gives them the identity the seeder resolves by. The claim writes nothing but that identity, and the seed after it leaves every claimed page and menu exactly as it is. Run this once, and preview first.',
 		'pediment'
 	) . '</p>';
 
-	// The claim promise holds for pages, not for menus, and the panel used to
-	// imply otherwise — it told the operator to run claim and then Apply plan,
-	// while promising menus were untouched. There is no hash arbitration for
-	// navs: menu membership is git-owned, so the next Apply plan rewrites a
-	// claimed menu's links. Say so where the buttons are, not only in the docs.
+	// The slug-blind single-menu rule is the one place a claim can pick the
+	// wrong row. Say so where the buttons are, not only in the docs.
 	echo '<p><strong>' . esc_html__(
-		'Menus are the exception.',
+		'Check the menu lines.',
 		'pediment'
 	) . '</strong> ' . esc_html__(
-		'Menu membership is owned by the theme, so the next "Apply plan" rewrites a claimed menu\'s links — a claimed menu is not protected the way a claimed page is. And when the manifest declares one menu and this site has exactly one unclaimed menu, that menu is claimed without any slug or title check. Read every navigation line in the preview and confirm it names the menu you meant.',
+		'When the manifest declares one menu and this site has exactly one unclaimed menu, that menu is claimed without any slug or title check. Read every navigation line in the preview and confirm it names the menu you meant.',
 		'pediment'
 	) . '</p>';
 
PATCH
```

- [ ] **Step 4: Run the test to verify it passes**

Run the command from Step 2, then `composer lint -d plugin`.
Expected: OK; lint 0 errors.

- [ ] **Step 5: Rewrite `docs/seeding.md` as bootstrap only**

This replaces "The arbitration contract", "What structure means", "The two hashes", the mega-menu ownership rules, the plan examples, the adopt hash bullets, the claim-to-protected example and the git-owned limitations:

````bash
git apply <<'PATCH'
--- a/docs/seeding.md
+++ b/docs/seeding.md
@@ -2,28 +2,33 @@
 
 The plugin ships a declarative seeding engine: a client theme declares the pages,
 posts, media, navigation, and custom post types a site needs in a single
-`seed/manifest.php` file, and `wp pediment seed` makes the database match it.
+`seed/manifest.php` file, and `wp pediment seed` creates whatever of it the
+site does not have yet.
 Content itself (block markup) lives in the theme's `patterns/` directory, not in
 the manifest — the manifest is structure, patterns are content.
 
 This is the reference for a client-theme author who has never touched the
-engine: what you declare, what the seeder owns forever, what your client can
-safely edit once the site is live, and what to do when a run reports a problem.
+engine: what you declare, what the seeder creates, why it never changes a page,
+menu or setting that already exists, and what to do when a run reports a
+problem.
 Every snippet below is copied from the plugin's own reference fixture,
 [`tests/fixtures/client-theme/seed/manifest.php`](../tests/fixtures/client-theme/seed/manifest.php)
 — the file the e2e suite seeds and asserts against on every CI run.
 
-## The arbitration contract, in one paragraph
+## Bootstrap only, in one paragraph
 
-Git owns structure — which entries exist, their slugs, their nesting, which
-page is the front page, which menu a page belongs to, which custom post types
-are registered, which media files exist. The database owns content once a
-human has edited it in the block editor. A stored hash decides which regime
-applies to a given row: if the hash the seeder wrote still matches what's in
-the database, git also still owns the content and a manifest/pattern change
-gets written on the next run; the moment the row's hash stops matching (an
-editor changed the title or body), content and title freeze permanently and
-only structure keeps being enforced.
+The seeder bootstraps a site; it does not maintain one. On a fresh site,
+`wp pediment seed` creates every page, post, menu and media file the manifest
+declares, sets the front page, the posts page and the site logo, and gives
+every row its identity (`_pediment_seed_key`). On a site that is already
+seeded, it creates only what is missing — a page or a language the manifest
+added, a menu or a media file that does not exist yet — and never changes a
+row that exists: not its title, content, slug, parent, order or status, not a
+menu's links, not a reading setting or a logo a person chose. A live site
+belongs to the people and the AI agent who edit it: pages change in the block
+editor or through the Pediment MCP tools ([mcp.md](mcp.md)), never through a
+re-seed. Pattern files stay in the theme for one job: building the same pages
+in a new environment.
 
 ## The manifest
 
@@ -89,11 +94,11 @@
 | `title` | yes | — | Non-empty string. |
 | `pattern` **or** `content` | yes, exactly one | — | `pattern` is a registered block-pattern slug; `content` is literal block markup (an empty string is valid, e.g. the fixture's `contact` and `blog` pages). Declaring both, or neither, is a validation error. |
 | `post_type` | `pages`/`posts`: no; `entries`: yes | `page` / `post` | Never rewritten once a row exists — a mismatch between the manifest and the database is a hard error (see below). |
-| `slug` | no | the last `/`-segment of the key | Must already be a valid slug (`sanitize_title($slug) === $slug`) and non-empty, so a key may not end in `/`; it is structure and gets reverted if an editor changes it. |
+| `slug` | no | the last `/`-segment of the key | Must already be a valid slug (`sanitize_title($slug) === $slug`) and non-empty, so a key may not end in `/`. Used when the entry is created; a later change on the site stays. |
 | `parent` | no | none | Another entry's key. Must be declared; parent cycles are rejected at load time. |
 | `front_page` | no | `false` | At most one entry site-wide may set this. |
 | `posts_page` | no | `false` | At most one entry site-wide may set this. |
-| `menu_order` | no | `0` | Structure; enforced. |
+| `menu_order` | no | `0` | Used when the entry is created. |
 | `terms` | no | `[]` | `taxonomy => [slug, …]`. Create-only — see Limitations. |
 
 ### `media`
@@ -134,11 +139,14 @@
 to that entry's live post ID and permalink at write time — or `{ url, label }`
 for an external/custom link. An `entry` that names an undeclared key fails
 validation immediately; an `entry` that's declared but has no live post yet
-(e.g. its own write failed earlier in the same run) is reported on every run,
-and the whole navigation is left exactly as it is rather than written without
-that link — a menu that quietly comes out one item short is worse than one
-that visibly fails.
+(e.g. its own write failed earlier in the same run) is reported, and the menu
+is not created until every link resolves — a menu that quietly comes out one
+item short is worse than one that visibly fails.
 
+The `navs` section describes a menu at the moment it is created. After that
+the menu is edited in the Site Editor, and a change to `navs` reaches only
+environments where that menu does not exist yet.
+
 `label` is optional on an `{ entry, … }` item. When it is omitted,
 `NavSeeder::serialize()` falls back to the linked entry's own `post_title` —
 which is already resolved per language — instead of a fixed string. On a
@@ -195,7 +203,7 @@
   live post in a language, the whole submenu is omitted from that language's
   serialized menu rather than its children being promoted to the top level —
   and, as with any unresolved link, `unresolvedEntries()` reports it and the
-  navigation is left exactly as it is rather than written short.
+  menu is not created yet.
 
 #### Mega menus
 
@@ -228,31 +236,9 @@
 combine with the other item types. All of it is validated at parse time with
 exact paths (`navs.primary.items.1.mega.columns.0.links.2`).
 
-Ownership is split, and this is the one place nav seeding deviates from
-"membership is git-owned; editor changes are reverted":
-
-- **Membership is still git-owned.** Where the mega menu sits, and that it
-  exists at all, comes from the manifest. Deleting the block in the editor
-  brings it back on the next seed; adding a second one removes it.
-- **Content is hash-arbitrated, exactly like page content** ("The two hashes"
-  above, scoped to one block). The seeder stores a per-position hash of each
-  mega block it writes (`_pediment_seed_mega_hash` on the nav entity). While
-  the stored block still matches, manifest changes flow through on re-seed.
-  The moment someone edits the block in the Site Editor the hash stops
-  matching, and from then on the seeder splices the stored block through
-  verbatim — the client's edits win, on every future run.
-- **Re-asserting git** = delete the block in the editor and re-seed:
-  membership re-creates it from the manifest and re-hashes it.
-
-A claimed legacy nav carries no hash, so a hand-built mega menu survives its
-first seed — the same "a claimed row's very first seed is safe" property pages
-have. Matching between manifest mega items and stored blocks is positional
-(nth item ↔ nth block): swapping two mega items within one nav transfers
-their edit-ownership, so treat multi-mega navs with care. Deleting or
-inserting a mega item in a multi-mega nav likewise shifts the positions after
-it, so an edited block's ownership can transfer to a neighboring position —
-it does not flip back to git (the carry-forward still holds), but placement
-can surprise.
+Like every menu, a mega menu is written once, when its menu is created. After
+that its links, labels and descriptions are edited in the Site Editor, and the
+seeder never touches them again.
 
 ### `post_types`
 
@@ -280,10 +266,9 @@
 'site' => array( 'logo' => 'logo' ),
 ```
 
-Names a `media` key; must already be declared there. On apply, that
-attachment becomes the site's custom logo (`theme_mod: custom_logo`) — and
-keeps becoming it: this is structure, re-asserted on every run, not a
-one-time default (see Limitations).
+Names a `media` key; must already be declared there. That attachment becomes
+the site's custom logo (`theme_mod: custom_logo`) when the seeder uploads it,
+or while the site has no logo. A logo a person chose later stays.
 
 ## Languages
 
@@ -441,6 +426,10 @@
     `wp pediment adopt contact --language=fr` once it is translated in the editor.
 ```
 
+Notices are about pages the run creates. A page that already exists is never
+written again, so its translation gaps are not reported; translate it on the
+site.
+
 **Notices never fail the run.** `RunResult::ok()` only inspects `errors` and
 `problems`; a fresh site that just added a language would otherwise fail its
 very first seed on the exact gap the notice exists to describe. Read the
@@ -462,77 +451,65 @@
 omitted or matches the default and the file has no suffix, same as before
 this step existed.
 
-## What "structure" means, concretely
+## On a site that is already seeded
 
-Everything the seeder re-asserts on every run, regardless of what an editor
-has done to a row:
+Every run, for every row the manifest declares:
 
-- **Existence** — a manifest entry with no matching row gets created.
-- **Slug** — reverted to the manifest's value if changed (see the slug-collision
-  failure mode below for the one case this can't do).
-- **Nesting** — `post_parent`, resolved from the `parent` key to the parent's
-  live post ID.
-- **Front page / posts page** — `show_on_front` / `page_on_front` /
-  `page_for_posts`, enforced for the default-language entry.
-- **Nav membership** — which entries a navigation menu links to, and in what
-  order (see Limitations: this one *does* revert an editor's menu edit). A
-  trashed menu is restored in place, not replaced by a second entity.
-- **Custom post type registration** — the CPT exists and is reachable.
-- **Media presence** — a `media` key resolves to exactly one live attachment;
-  trashed attachments are restored rather than re-uploaded.
-- **The site logo** — `site.logo` is re-asserted on every run whenever
-  `custom_logo` has drifted, including away from a value a client chose in the
-  Customizer (see Limitations).
-- **Editorial state** — only `trash` is reverted (restored to `publish`).
-  `draft` and `pending` are left alone: a client taking a page offline, or
-  holding a revision for review, is not overruled by the next seed.
+- **Missing** (no row carries its key) → `create`, with its language, terms,
+  slug, parent and menu order. A new child finds a parent that already exists.
+- **Exists** → `unchanged`. Title, content, slug, parent, menu order, status
+  and terms stay as they are, however far they are from the manifest.
+- **In the trash** → `unchanged`, with a note. The seeder neither restores it
+  nor creates a second row under the same key. Restore it yourself, or empty
+  the trash: once the row is gone for good, the next seed creates it again.
+- **Not declared any more** → `orphan`, left in place.
 
-Title and body content are explicitly *not* on this list once a row has been
-edited — that's the arbitration contract above.
+Menus and media follow the same rule. A menu that is not published gets a
+note too, because it does not render.
 
-## The two hashes
+Three things the seeder still does on a site that is already seeded, because
+they are identity, not content:
 
-`Pediment\Seeder\Meta` defines the identity and arbitration keys:
+- **Language repair.** A row with no language at all (a page that predates the
+  site's languages) is tagged with the language it was found under. A row that
+  already has a language is never moved.
+- **Translation groups.** Every language of a seed key is linked into one
+  translation group on every run, so a language the manifest adds later
+  creates its own pages and joins the groups that already exist.
+- **Empty settings.** The front page, the posts page and the site logo are set
+  when their entry or attachment is created in this run, or while the site
+  has none (`page_on_front`, `page_for_posts` or `custom_logo` is `0`). A
+  choice a person made stays.
 
-- `_pediment_seed_key` — stable identity. The engine never looks up seeded
-  content by slug or title; duplicate keys are the one thing it treats as
-  fatal (below).
-- `_pediment_seed_hash` — a hash of the **persisted row**
-  (`ContentHash::forPost()`, title + `post_content` as WordPress actually
-  stored them, *after* WordPress's own normalization). This is what decides
-  "has the client edited this?" — hashing the intended input instead would
-  make WordPress's own markup normalization look like an edit on literally
-  every page, on the very first re-seed.
-- `_pediment_seed_source` — a hash of the **git-side input** (the resolved
-  pattern or literal content) the seeder last wrote. This is what decides "has
-  the manifest changed since the last write?"
+The header template part is not part of the seeder: plugin activation and a
+theme switch create it once (`plugin/inc/bootstrap.php`), and `wp pediment
+seed` never touches it.
 
-On every entry, the Differ applies one rule, in order:
+Patterns are read only for rows the run creates. A theme may delete the
+pattern file of a page that now lives only on the site; the next seed does not
+need it. A *new* entry whose pattern is not registered still stops the run.
 
-1. No row exists → **create** it.
-2. The stored hash is missing, from an older hash version, or no longer
-   matches the row → the row is treated as edited: title and content are
-   never touched; only structure is enforced. The dry-run plan's note tells
-   you which of the three it was — see "Reading a plan" below.
-3. Otherwise → content is up for grabs: write it if `_pediment_seed_source`
-   shows the manifest/pattern changed, leave it alone if not.
+## Identity
 
-Because rule 2 also fires for a row the engine has simply never written a
-hash to yet, a *claimed* row's very first `wp pediment seed` is safe — it is
-treated as edited and only gets structure applied, never a silent content
-overwrite. That safety net only covers rows the engine can see in the first
-place: `StateReader::read()` only reads posts that already carry
-`_pediment_seed_key` at all (`meta_key => Meta::KEY, meta_compare =>
-'EXISTS'`), so an *unclaimed* legacy row has no `$actual` entry whatsoever.
-For that row `Differ::diff()` never reaches rule 2 — `$have` is `null`, rule
-1 fires, and `wp pediment seed` plans a **create**, duplicating the row
-instead of protecting it. Rule 2's protection is conditional on the row
-already carrying a key; `wp pediment claim` (below) is what gives an existing
-site's content that key in the first place, before its first seed.
+`_pediment_seed_key` (`Pediment\Seeder\Meta::KEY`) is the only meta the
+seeder writes. It is the stable identity: the engine never looks seeded content
+up by slug or title, and duplicate keys are the one thing it treats as fatal
+(below).
 
+Pediment 3.x also stored two content hashes (`_pediment_seed_hash`,
+`_pediment_seed_source`) and a mega-menu hash (`_pediment_seed_mega_hash`) to
+decide when a re-seed could overwrite a page. 4.0 removes them: on the first
+admin request after the update, `Pediment\Upgrade\SeedHashCleanup` deletes
+all three from every post, once.
+
+`StateReader::read()` only sees posts that already carry a key, so on a site
+whose content predates the engine a first seed plans a `create` for every
+entry and duplicates the site. `wp pediment claim` (below) gives that content
+its keys first.
+
 ## Reading a plan (`wp pediment seed --dry-run`)
 
-A clean, up-to-date site:
+A seeded site where nothing is missing:
 
 ```
 Pediment seed — dry run
@@ -557,67 +534,35 @@
 NAV
   unchanged   primary
 
-0 to write, 0 protected, 0 orphan, 13 unchanged. Nothing was written (--dry-run).
+0 to write, 0 orphan, 13 unchanged. Nothing was written (--dry-run).
 ```
 
 That's the state every commit to a client theme's `seed/manifest.php` /
 `patterns/` should converge to before merging: `0 to write`. `docs/STANDARDS.md`
-makes this a requirement, not a suggestion.
+makes this a requirement, and the seed-check action in client CI greps for
+`0 to write,` — the words and the comma are a contract.
 
-A row that needs a structural fix (here, an editor renamed a page's slug)
-shows the field, its current and desired value, and rolls up into the
-`N to write` count:
+A page a person rewrote, renamed and moved still reads `unchanged`: the plan
+does not compare a row that exists with the manifest at all, so a dry run
+cannot answer "has anyone edited this page?".
 
+A manifest that added a page (`team`), on a site where someone trashed
+`contact`:
+
 ```
 PAGES & POSTS
-  update      about            slug "about-clientslug" -> "about"
+  unchanged   home
+  unchanged   about
+  unchanged   contact          in the trash — left alone; restore it in the editor, or empty the trash and the next seed creates it again
+  create      team             title=Team; content -> 11 bytes; slug=team; parent_key=; status=publish; menu_order=0
 
-NAV
-  update      primary          items 3 -> 3
-
-2 to write, 0 protected, 0 orphan, 11 unchanged.
+1 to write, 0 orphan, 13 unchanged.
 ```
 
-(The nav shows `update` too here because the nav's stored links reference the
-page's numeric ID; the *count* of items doesn't change, but this is a case
-where a stale reference would be invisible if the report only counted items —
-so treat "items 3 -> 3" as "the membership no longer matches the manifest",
-not as "nothing to see here.")
-
-A row whose content is genuinely protected — the manifest/pattern changed
-*and* an editor separately changed the same row — carries a `^ protected:`
-line naming the frozen fields and why:
-
-```
-  update      guide/pricing    slug "pricing-old" -> "pricing"
-              ^ protected: title, content (edited in the editor — content and title left alone)
-```
-
-The three notes you'll see there, and what each means:
-
-- `edited in the editor — content and title left alone` — a real client edit;
-  the fix (if you want git to own it again) is `wp pediment adopt`.
-- `never seeded by this engine — content left alone; run wp pediment adopt to
-  take it into git` — this row predates the seeder (e.g. migrating an existing
-  site onto it). Same fix.
-- `seeded by an older hash version — content left alone; re-adopt to refresh
-  it` — the engine's hash format changed since this row was last written.
-
-**A plan-only edit that does not collide with a manifest change never shows
-up as "protected" at all.** If nobody has touched the manifest since a client
-edited a page, the Differ has nothing it wants to write, so the item reports
-`unchanged` — correctly, since there's genuinely no pending action, but it
-means a dry run cannot answer "has anyone edited this page in the editor?" on
-its own.
-
 Rows the manifest no longer declares, but that still carry a seed key, are
 listed as `orphan` and left in place — nothing ever deletes a post on your
 behalf.
 
-Two things a dry-run plan is silent about, because the Applier — not the
-Differ — owns them and they never produce plan items: the front-page/posts-page
-options, and taxonomy term drift on an existing entry (see Limitations).
-
 ## `wp pediment seed`
 
 ```
@@ -628,7 +573,9 @@
 state → resolve actual state → diff into a plan → apply → verify. `--dry-run`
 stops after the plan and writes nothing. `--json` emits the same information
 machine-readably (`applied`, `ok`, `counts`, one object per plan item,
-`errors`, `problems`) instead of the text report above.
+`errors`, `problems`) instead of the text report above. Since 4.0, `counts`
+has exactly the keys `create`, `unchanged` and `orphan`, and an item has no
+`protected` field.
 
 Rewrite rules are flushed once, at the very end, and only softly
 (`flush_rewrite_rules(false)`) — the engine never sets `permalink_structure`
@@ -643,9 +590,12 @@
 wp pediment adopt <key> [--language=<code>] [--dry-run]
 ```
 
-The inverse of seeding: exports a live entry's current block markup back into
-its `patterns/<slug>.php` file, and resets both hashes so the next seed treats
-the page as up to date rather than protected. Concretely, it:
+Exports a live entry's current block markup into its `patterns/<slug>.php`
+file. Use it when a page was built or changed on the site and a new
+environment (a fresh local wp-env, CI, a staging site) should get the same
+page from its first seed. It writes the file only — no post meta, no post —
+and the next seed on the site it came from still plans `unchanged`.
+Concretely, it:
 
 - refuses to run against an entry declared with literal `content` — only
   `pattern`-backed entries have a file to adopt into;
@@ -656,22 +606,16 @@
   one is already there, rather than regenerating a bare one;
 - keeps a `.bak` sibling when it's about to overwrite a file whose contents
   differ from what it's about to write;
-- reads the file back the way the pattern registry will, verifies the
-  written `Slug:` header still matches what the manifest expects, and rolls
-  back to the backup (or deletes the new file) if it doesn't — a mismatched
-  header would mean the next seed can't find the pattern at all;
-- stores the source hash in exactly the shape the next seed will compute it —
-  the *manifest's* title crossed with the pattern output *after* media
-  placeholders expand. Hashing the file's raw bytes instead would leave every
-  media-bearing page mismatched, and hashing the live title would make the
-  next run write the manifest's old title back over a client's rename.
+- reads the written `Slug:` header back the way the pattern registry will, and
+  rolls back to the backup (or deletes the new file) if it does not match the
+  manifest — a mismatched header would mean a seed in a new environment cannot
+  find the pattern.
 
 **Adopt takes the body into git, never the title.** The manifest is a
 hand-edited file and the engine does not rewrite it, so adopting a page a
 client renamed prints a warning naming both titles and leaves the manifest
-alone. The next seed will not revert the rename (the source hash matches), but
-git and the database now disagree about the name until you edit the manifest
-yourself.
+alone. Update the manifest by hand if new environments should get the new
+name.
 
 **Known limitation:** sized image variants (`hero-300x200.jpg`) and `srcset`
 attributes are *not* rewritten to placeholders — only the exact attachment URL
@@ -694,12 +638,9 @@
 agree on, and writes exactly one meta key, `_pediment_seed_key`
 (`Pediment\Seeder\Claimer`, `plugin/src/Seeder/Claimer.php`).
 
-That's the only thing it writes. It never writes `_pediment_seed_hash` — and
-the omission is the point, not an oversight: the Differ's rule 2 above ("The
-two hashes") treats a missing hash exactly like an edited row, so a claimed
-row comes out of the very next seed *protected* — structure is enforced,
-title and content are left alone. Bringing a page under git's control after
-that is `wp pediment adopt <key>`, the same command that adopts any other row.
+That's the only thing it writes. The seed after a claim finds every claimed
+row by its key and plans it `unchanged`: content, title, slug and status stay
+exactly as they were. Claimed menus are left as they are too.
 
 ### Precondition: configure languages before you claim
 
@@ -712,9 +653,9 @@
 unconfigured, `LanguageRegistry` hands back `NullProvider`, whose language
 list is a single empty string: the claim would key only the default-slug rows
 and report every other language's live page as `no-match`. Configure the
-languages afterwards, seed, and each of those unclaimed pages trips the
-Differ's rule 1 and is created again alongside the live one — the exact
-duplication a claim exists to prevent.
+languages afterwards, seed, and each of those unclaimed pages has no key, so
+the seed creates it again alongside the live one — the exact duplication a
+claim exists to prevent.
 
 `wp pediment languages` is WP-CLI only; it has **no wp-admin equivalent**.
 The option that makes `wp_navigation` translatable, in particular, can never
@@ -783,25 +724,17 @@
 match the derived one is reported `ambiguous`, not `no-match` — the claim can
 see a menu there and will not guess.
 
-> **Check every nav line in a claim preview by hand.** Navs are the one place
-> where the claim's "writes nothing but the identity" promise does not carry
-> through to the seed that follows. There is no hash arbitration for navs at
-> all: menu membership is git-owned, so `NavSeeder::plan()` compares the
-> serialized item list and emits an `update` whose note reads *"membership is
-> git-owned; editor changes to this menu are reverted."* A claimed nav's links
-> are therefore **rewritten by the very next seed** — unlike a claimed page,
-> which comes out `protected`.
->
-> That matters because of the slug-blind rule above. On a live site whose only
-> Site-Editor menu is, say, a footer menu, one declared nav plus one unclaimed
-> `wp_navigation` is enough: the claim keys that footer menu as `primary`
-> without comparing slug or title, and the next seed replaces its links with
-> the manifest's. The rule is deliberate — legacy menus carry slugs like
-> `primary-2` that cannot slug-match, which is the case it was built for — so
-> the safeguard is you, reading the `navigation "<title>" (ID <n>)` note in
-> `wp pediment claim --dry-run` and confirming it names the menu you meant.
->
-> The one exception is mega-menu *content*, which is hash-arbitrated per block — see "Mega menus" under `navs`.
+> **Check every nav line in a claim preview by hand.** On a live site whose
+> only Site-Editor menu is, say, a footer menu, one declared nav plus one
+> unclaimed `wp_navigation` is enough: the claim keys that footer menu as
+> `primary` without comparing slug or title. The seed after it does not
+> rewrite a claimed menu's links, but the header's ref-less navigation block
+> binds to whichever menu is keyed `primary` (`pediment_seeded_nav_id()`), so
+> the footer menu would render in the header. The rule is deliberate — legacy
+> menus carry slugs like `primary-2` that cannot slug-match, which is the case
+> it was built for — so the safeguard is you, reading the
+> `navigation "<title>" (ID <n>)` note in `wp pediment claim --dry-run` and
+> confirming it names the menu you meant.
 
 ### Re-running claim
 
@@ -846,49 +779,13 @@
 and stops there — `no-match` and `ambiguous` lines are report-only either
 way.
 
-### Worked example: claim through to protected
-
-The example above stops at the claim's own report. This one continues one
-step further, onto the *next* `wp pediment seed`, to show the payoff the
-claim exists for: a claimed row reads back as `protected`, not `unchanged`,
-because `Claimer::apply()` never wrote a hash. Captured from a rehearsal
-against three pages (`about` ID 46, `contact` ID 8, `blog` ID 9) with every
-seed meta key — `_pediment_seed_key`, `_pediment_seed_hash`,
-`_pediment_seed_source` — stripped, so the setup actually models a page that
-predates the seeding engine (see the note below for why that distinction
-matters):
-
-```
-Pediment claim — dry run
-manifest: /var/www/html/wp-content/themes/pediment-fixture/seed/manifest.php
-
-PAGES & POSTS
-  claim      about (en)       page "about" (ID 46)
-  claim      contact (en)     page "contact" (ID 8)
-  claim      blog (en)        page "blog" (ID 9)
-
-3 to claim, 0 without a match, 0 ambiguous. Nothing was written (--dry-run).
-Success: Dry run complete — nothing was written.
-```
-
-```
-Pediment claim
-manifest: /var/www/html/wp-content/themes/pediment-fixture/seed/manifest.php
+### Worked example: claim, then seed
 
-PAGES & POSTS
-  claim      about (en)       page "about" (ID 46)
-  claim      contact (en)     page "contact" (ID 8)
-  claim      blog (en)        page "blog" (ID 9)
+One step past the claim's own report: the next seed's dry run finds the
+claimed rows by their keys and plans them `unchanged`. The shape for three
+claimed pages (`about`, `contact`, `blog`) on the fixture site:
 
-3 to claim, 0 without a match, 0 ambiguous.
-Success: Claim applied.
 ```
-
-Nothing so far has touched content — both runs above only name and then
-write a `_pediment_seed_key`. The next seed's dry run is where the claim's
-promise shows up:
-
-```
 Pediment seed — dry run
 manifest: /var/www/html/wp-content/themes/pediment-fixture/seed/manifest.php
 
@@ -897,34 +794,18 @@
 
 PAGES & POSTS
   unchanged   home
-  protected   about            never seeded by this engine — content left alone; run `wp pediment adopt` to take it into git
-  protected   contact          never seeded by this engine — content left alone; run `wp pediment adopt` to take it into git
-  protected   blog             never seeded by this engine — content left alone; run `wp pediment adopt` to take it into git
-  unchanged   mega-demo
-  ... (remaining unchanged rows and the TRANSLATIONS block elided — same shape as the "Reading a plan" example above)
+  unchanged   about
+  unchanged   contact
+  unchanged   blog
+  ... (remaining unchanged rows elided)
 
-0 to write, 3 protected, 0 orphan, 22 unchanged. Nothing was written (--dry-run).
-Success: Dry run complete — nothing was written.
+0 to write, 0 orphan, 13 unchanged. Nothing was written (--dry-run).
 ```
 
-`--dry-run` again, so this still wrote nothing — it only reports what a real
-`wp pediment seed` would do. Three claimed rows, three `protected` lines, the
-summary's `protected` count matching, and the note text is exactly rule 2's
-`'' === $have->storedHash` branch (see "The two hashes" above): the row was
-never seeded by this engine, so title and content are left alone.
+A seed never writes a claimed page, so its live content, title and slug are
+safe from the first run on. Anything the claim reported `no-match` is created
+by the next seed — read those lines before you apply.
 
-**Building a rehearsal or test fixture for this sequence, deleting only
-`_pediment_seed_key` from a page that has already been through a real seed
-is not enough** — the stored hash and source hash survive untouched, still
-match the untouched content, and the claim only restores the identity key,
-so the next seed's Differ reaches rule 3 (in sync) before it ever gets to
-rule 2, and reports `unchanged` instead of `protected`. Both outcomes are
-safe and neither writes content; the weaker setup just can't demonstrate the
-protection property, because the surviving hash makes the row look
-already-in-sync rather than never-seeded. A faithful rehearsal of a legacy
-page has to delete `_pediment_seed_hash` and `_pediment_seed_source` too, not
-just the identity key.
-
 ### The wp-admin path
 
 Admin-only hosting has no WP-CLI, so Settings → Pediment Theme → Seeding
@@ -1002,7 +883,7 @@
 ### Verification problems
 
 Phase 5 re-reads the database after every apply and reports anything it
-claims to own that doesn't actually hold — this is deliberately paranoid,
+just created that doesn't actually hold — this is deliberately paranoid,
 because the incident that justified writing it was a seed run reporting
 success while the live header rendered nothing. A `VERIFICATION FAILED`
 section names the specific problem per entry (keys are printed in the
@@ -1017,19 +898,24 @@
 - `about: references media key "hreo", which the manifest does not declare —
   the placeholder was written out unresolved.` — a typo in a
   `{{media_url:…}}` / `{{media_id:…}}` placeholder. Nothing else catches it:
-  the media plan has no such key, so the literal sentinel would otherwise be
-  written into the page and hashed as if it were correct.
-- `navs.primary: stored membership does not match the manifest.`
+  the media plan has no such key. Reported on the run that creates the page;
+  fix the live page in the editor, and the pattern file for new environments.
+- `navs.primary: stored membership does not match the manifest.` — for a menu
+  this run created.
 - `post_types.guide: already registered by something else — the manifest's
   settings (show_in_rest, supports, rewrite) were not applied.`
 
-**The slug-collision case is the one structural rule the seeder cannot force
-through.** If another post already occupies the slug the manifest wants,
+Slug, parent, trash, the front-page and posts-page settings, and a menu's
+links and status are checked only for rows this run created. On a row that
+existed before the run they are a person's decision, not a problem.
+
+**A slug collision is the one thing a create cannot force through.** If
+another post already occupies the slug the manifest wants,
 `wp_unique_post_slug()` silently uniquifies it (`contact` → `contact-2`)
-instead of erroring, and Applier / Verifier both report it rather than
-retrying forever (a retry would rewrite the row every single run without ever
-converging). Fix: free the slug (rename or delete whatever's squatting on it),
-or declare a different `slug` in the manifest for this entry, then re-run.
+instead of erroring, and Applier and Verifier both report it on the run that
+creates the page. Later runs leave the page alone. Fix: rename the new page on
+the site (or free the slug and rename it), then declare the slug you want in
+the manifest so new environments match.
 
 A run's exit reflects both errors and verification problems — `RunResult::ok()`
 is false if either is non-empty, and WP-CLI's `wp pediment seed` exits non-zero
@@ -1062,20 +948,15 @@
 
 ## Limitations, by design
 
-- **Nav membership is git-owned, not client-editable.** Unlike page content,
-  a navigation menu's links are structure: the seeder rewrites the whole
-  entity whenever its serialized membership differs from the manifest, so an
-  editor who adds, removes, or reorders a link in the Site Editor's
-  navigation block will see that change reverted on the next
-  `wp pediment seed`. An `entry`-type link the manifest references but that
-  has no seeded post yet is reported as unresolved on *every* run (not just
-  the one that changes the nav), so a missing link doesn't silently disappear
-  from the report once the nav itself stops needing a rewrite. Mega-menu *content* is the exception: it is hash-arbitrated like page content, so a client's edits to a seeded mega menu persist ("Mega menus" above).
-- **The site logo is git-owned, not client-editable.** Like nav membership,
-  `site.logo` is re-asserted whenever `custom_logo` differs from the manifest's
-  media key, so a client who picks a different logo in the Customizer will see
-  it reverted on the next `wp pediment seed`. Change the manifest, not the
-  Customizer.
+- **The manifest describes a site at creation time.** A change to an
+  existing page's title, slug, parent or content in the manifest or a pattern
+  file never reaches a site where that page exists. Change the live page in
+  the editor or through the MCP tools, and run `wp pediment adopt` if new
+  environments should match.
+- **Menus are written once.** A change to `navs` reaches only environments
+  where that menu does not exist yet. A page the manifest adds later is
+  created, but it is not added to an existing menu — add it in the Site
+  Editor.
 - **Terms are create-only.** `wp_set_object_terms()` replaces a taxonomy's
   assignments rather than merging into them; re-applying the manifest's terms
   on every run would strip a category a client added by hand in the editor.
@@ -1087,18 +968,12 @@
   there's no current user in that context, and the engine doesn't set one.
   If your theme or a plugin displays "by {author}", account for a `0` author
   on seeded content.
-- **A dry-run plan is silent about front-page/posts-page drift.** The
-  Applier — not the Differ — owns `show_on_front` / `page_on_front` /
-  `page_for_posts`, and enforces them unconditionally on every real apply, but
-  neither the reassignment nor the fact that one is pending produces a plan
-  item, so `--dry-run` cannot tell you "the front page option is about to
-  change." Only an actual `wp pediment seed` (or the Verifier, after the
-  fact) will show it.
-- **A dry-run plan is silent about term drift too, but for a different
-  reason: nothing ever fixes it.** Terms are create-only (above) on *every*
-  run, dry or real, so a manifest-side term change on an existing entry never
-  produces a plan item and never gets applied — `--dry-run` isn't hiding a
-  pending write here, there simply isn't one.
+- **A dry-run plan is silent about the reading settings and the logo.** They
+  are set only when their entry or attachment is created in the same run, or
+  while the site has none, and neither produces a plan item.
+- **A dry-run plan is silent about term drift too.** Terms are create-only
+  (above), so a manifest-side term change on an existing entry never produces
+  a plan item and never gets applied.
 - **The wp-admin "Apply plan" button has no confirmation step.** It runs on
   click, same as the CLI without `--dry-run`. Preview first.
 - **Media and taxonomies are not translated.** One attachment and one term
PATCH
````

- [ ] **Step 6: Update the other docs**

```bash
git apply <<'PATCH'
--- a/docs/client-sites.md
+++ b/docs/client-sites.md
@@ -152,23 +152,28 @@
 
 ## Day-two work
 
-Structure — which pages exist, their slugs, nesting, nav membership, languages — lives in
-`seed/manifest.php`. Content lives in `patterns/*.php`. See
-[docs/seeding.md](seeding.md) for the full manifest/pattern format, the content-arbitration
-contract, and how to read a dry-run plan; this section is only the day-to-day loop:
+The seeder bootstraps a site and never changes a page, menu or setting that
+already exists (see [docs/seeding.md](seeding.md), "Bootstrap only"). So the
+day-to-day loop depends on where the page lives:
 
-```bash
-npm run seed:plan    # see what a seed would change; read it before applying
-npm run seed
-```
+- **A page that does not exist yet** — declare it in `seed/manifest.php`, add
+  `patterns/<key>.php`, then:
 
-To take a client's live edit in the block editor back into git (the seeder otherwise leaves an
-edited page's content alone forever, by design — see `docs/seeding.md`'s arbitration contract):
+  ```bash
+  npm run seed:plan    # read the plan: expect one create per new row
+  npm run seed
+  ```
 
-```bash
-npm run adopt -- <key>
-```
+- **A page that exists** — change it in the block editor, or through the
+  Pediment MCP tools ([docs/mcp.md](mcp.md)) after validating the markup with
+  client-kit's `validate-blocks`. A re-seed does not touch it.
+- **New environments should match the live page** — export it into its
+  pattern file and commit the file:
 
+  ```bash
+  npm run adopt -- <key>
+  ```
+
 The `--` matters: `npm run adopt -- about --language=de` forwards `about --language=de` to `wp
 pediment adopt` intact. A second `--` would arrive as a literal extra positional argument and
 WP-CLI rejects it.
@@ -181,7 +186,9 @@
 
 1. Download the zip from the release.
 2. Appearance → Themes → Add New → Upload in wp-admin, and activate it.
-3. Settings → Pediment Theme → Seeding → apply the plan, to bring the manifest's structure live.
+3. Settings → Pediment Theme → Seeding → **Preview plan**, then **Apply plan**. It creates what
+   the new release declares and the site does not have yet (a new page, a new menu); it never
+   changes a page that exists.
 
 **The client theme has no auto-updater.** Unlike the Pediment plugin, which updates itself
 through wp-admin, a new theme release only reaches production when someone uploads the zip by
@@ -205,14 +212,14 @@
 4. **Claim content** — identity only; see
    [`wp pediment claim`](seeding.md#wp-pediment-claim) for what it matches
    and what it refuses to.
-5. **Preview plan** (seed) — expect `0 to write`, N `protected`.
-6. **Apply plan** (seed) — structure only.
+5. **Preview plan** (seed) — expect every claimed row `unchanged` and a
+   `create` only for pages, menus or media the site really does not have.
+6. **Apply plan** (seed) — creates those rows; claimed rows stay as they are.
 
-**Step 5 is a gate, not a formality.** A preview that reports anything other
-than protected pages and the structural changes you actually expect means
-the claim was incomplete — some row didn't match, or matched the wrong one —
-and the fix is to go back and correct the manifest or the claim, never to
-apply the plan anyway. Applying over an incomplete claim risks creating
+**Step 5 is a gate, not a formality.** A `create` for a page the site already
+has means the claim was incomplete — that row didn't match, or matched the
+wrong one — and the fix is to go back and correct the manifest or the claim,
+never to apply the plan anyway. Applying over an incomplete claim risks creating
 duplicate content for whatever didn't get identity in step 4, which is
 exactly the failure claiming exists to prevent.
 
--- a/docs/WORDPRESS_TRAPS.md
+++ b/docs/WORDPRESS_TRAPS.md
@@ -366,23 +366,7 @@
 the metrics agree numerically.
 
 ---
-
-## Hashing seeded content before the write disables all future updates
 
-**Symptom.** Every page reports "protected" on the first re-seed after the initial seed,
-even though nothing was ever edited in the editor.
-**Cause.** WordPress normalizes markup on write (KSES, wptexturize, block-comment
-round-trips), so a hash computed from the intended input never matches a hash computed
-from the persisted row — the two diverge on the very first write, and every page looks
-client-edited forever after.
-**Fix.** Hash the row WordPress actually stored, after the write:
-`ContentHash::forPost()` (`plugin/src/Seeder/ContentHash.php`). A separate hash,
-`_pediment_seed_source`, is computed from the git-side input and answers a different
-question ("did the manifest change?") — never conflate the two.
-**Catch it early.** `ApplierTest::test_reseeding_unchanged_content_plans_nothing`.
-
----
-
 ## KSES is active under WP-CLI and mangles block-comment JSON
 
 **Symptom.** Seeded pages render as raw markup on the front end, or the request fatals
@@ -404,36 +388,32 @@
 error anywhere in the seed report.
 **Cause.** `wp_unique_post_slug()` silently renames a colliding slug instead of
 erroring; a plan/apply step that only checks for a WP_Error return sees success.
-**Fix.** The Verifier re-reads `post_name` after the write and compares it against the
-manifest's declared slug, reporting a problem instead of retrying forever (a retry would
-rewrite the row — and get uniquified again — on every single run without converging).
+**Fix.** The Applier and the Verifier re-read `post_name` after the create and compare it
+against the manifest's declared slug, reporting a problem on the run that creates the
+page. The seeder never writes that row again, so this is the only chance to say so.
 See `Applier::assertSlug()` and `Verifier::verify()` in `plugin/src/Seeder/`.
-**Catch it early.** `VerifierTest::test_a_uniquified_slug_is_a_problem`.
+**Catch it early.** `VerifierTest::test_a_uniquified_slug_on_a_new_page_is_a_problem`.
 
 ---
 
 ## `get_post_status()` resolves an unattached attachment's raw status to `publish`
 
-**Symptom.** A test asserts an attachment was "restored from trash" by checking
-`get_post_status()`, and the assertion passes even when the restore silently failed to
-flip the row back to `inherit`.
+**Symptom.** A test asserts an attachment's status by checking `get_post_status()`,
+and the assertion passes even when the stored status is not what the test claims.
 **Cause.** `get_post_status()` has special-case logic for attachments: an `inherit`
 status with no living parent post resolves to `publish` for display purposes. That
 resolution hides the real stored value from anything that trusts the helper.
-**Fix.** Read the raw column instead: `get_post_field( 'post_status', $id )`. Restoring
-a trashed attachment writes `post_status = 'inherit'` directly
-(`plugin/src/Seeder/MediaSeeder.php`); assert against that literal value, not the
-resolved one.
-**Catch it early.** `MediaSeederTest::test_a_trashed_attachment_is_restored_rather_than_re_uploaded`.
+**Fix.** Read the raw column instead: `get_post_field( 'post_status', $id )`, and assert
+against the literal stored value (`inherit`, `trash`), not the resolved one.
+**Catch it early.** `MediaSeederTest::test_a_trashed_attachment_stays_in_the_trash_and_is_not_uploaded_again`.
 
 ---
 
 ## A theme's `patterns/` header metadata is cached for 30 minutes
 
 **Symptom.** `wp pediment adopt` writes a brand-new pattern file to the theme's
-`patterns/` directory, but the very next request (including the next `wp pediment seed`
-in the same test run) still can't find it — `ContentResolver` reports the pattern as
-unregistered.
+`patterns/` directory, but the very next request still can't find it — the pattern
+registry reports it as unregistered.
 **Cause.** WordPress caches a theme's scanned pattern-file headers in a site transient
 for 30 minutes so it isn't re-reading every pattern file's docblock on every request. A
 file written after that scan populated is invisible until the transient expires or is
@@ -444,8 +424,9 @@
 models a fresh request's pattern re-scan and asserts the follow-up `Runner` run plans
 nothing. PHPUnit runs the write and the re-seed in one process, so it can't reproduce the
 real cross-process cache staleness — after touching `Adopter`, also manually verify with
-two separate `wp` invocations: `wp pediment adopt <key>` then `wp pediment seed --dry-run`
-and confirm the adopted pattern resolves instead of reporting "not registered".
+two separate `wp` invocations: `wp pediment adopt <key>`, then
+`wp eval 'var_dump( WP_Block_Patterns_Registry::get_instance()->is_registered( "<slug>" ) );'`
+and confirm it prints `bool(true)`.
 
 ---
 
@@ -591,7 +572,7 @@
 language's row will ever ask for — `EntrySpec`'s version additionally keeps the
 default language's own URL unsuffixed, `NavSeeder`'s does not.
 **Catch it early.** `ManifestTest::test_a_missing_slug_derives_a_distinct_one`
-(`plugin/tests/phpunit/Seeder/ManifestTest.php`); `VerifierTest::test_a_uniquified_slug_is_a_problem`
+(`plugin/tests/phpunit/Seeder/ManifestTest.php`); `VerifierTest::test_a_uniquified_slug_on_a_new_page_is_a_problem`
 covers the same-slug-collision failure mode this rule exists to avoid in the first place.
 
 ---
@@ -725,3 +706,19 @@
 `links` array the one time `platform` briefly carried an `enum` during development; run
 the full PHPUnit suite after adding or changing any `items.properties` entry, not just a
 lint pass, since no linter in this repo checks block-attribute schemas for this hazard.
+
+---
+
+## Re-asserting seed data on a live site overrules the people who run it
+
+**Symptom.** A site owner renames a page, edits the header menu or picks another front
+page, and the next deploy's seed puts the old value back. On a site whose pages an AI
+agent builds through MCP, every re-seed fights the agent.
+**Cause.** Pediment 3.x treated the manifest as the owner of structure (slug, parent,
+order, menu links, reading settings, logo) and re-asserted it on every run, with content
+hashes deciding when a page body could be overwritten.
+**Fix.** 4.0 seeds bootstrap-only: a row that exists is never written again
+(`Differ`, `Applier`, `NavSeeder`, `MediaSeeder` in `plugin/src/Seeder/`), and the
+Verifier checks drift only for rows the same run created. The 3.x hash meta is deleted
+once by `Pediment\Upgrade\SeedHashCleanup`.
+**Catch it early.** `RunnerTest::test_a_reseed_never_updates_existing_content`.
--- a/docs/STANDARDS.md
+++ b/docs/STANDARDS.md
@@ -33,9 +33,10 @@
   edge-case (empty) attributes. Forms, patterns, and templates have suites.
   Run: `npx wp-env run tests-wordpress --env-cwd=wp-content/plugins/pediment-ai ./vendor/bin/phpunit`
 - **Seeding:** a manifest-format, applier, differ, or verifier change needs a test in
-  `plugin/tests/phpunit/Seeder/` (see `docs/seeding.md`). `wp pediment seed --dry-run`
-  must plan zero writes (`0 to write`) against an unchanged manifest — a dry run that
-  wants to write something nobody changed is a regression, not a quirk.
+  `plugin/tests/phpunit/Seeder/` (see `docs/seeding.md`). The seeder is bootstrap-only:
+  it creates missing rows and never writes to a row that exists — a change that makes it
+  write to one is a regression. `wp pediment seed --dry-run` must plan zero writes
+  (`0 to write`) once everything the manifest declares exists.
 - **Languages:** a manifest declaring `languages` makes a site multilingual; see
   [docs/seeding.md#languages](seeding.md#languages) for the manifest shape, the derived
   slug rule, and `wp pediment languages` / `wp pediment adopt --language`. A
--- a/AGENTS.md
+++ b/AGENTS.md
@@ -25,11 +25,13 @@
   `style.scss`, plus PHPUnit coverage for valid and empty attributes.
 - Client themes extend through normal WordPress theme facilities. Never depend
   on editing plugin files for client-specific behavior.
-- Content seeding is declarative now (see `docs/seeding.md`): edit the client
-  theme's `seed/manifest.php` and its `patterns/*.php` files, then run
-  `wp pediment seed`. Never write pages with `wp post create` / `wp post update`
-  — the seeder owns identity (`_pediment_seed_key`) and the content-arbitration
-  hashes, and a hand-written post bypasses both.
+- Seeding is bootstrap-only (see `docs/seeding.md`): `wp pediment seed`
+  creates what the client theme's `seed/manifest.php` and `patterns/*.php`
+  declare and the site lacks, and never changes a page, menu or setting that
+  exists. Never write pages with `wp post create` / `wp post update` — the
+  seeder owns identity (`_pediment_seed_key`), a hand-written post has none,
+  and the next seed creates a duplicate. Change an existing page through the
+  Pediment MCP tools.
 - Languages are declared in the manifest's `languages` section and configured
   with `wp pediment languages` before seeding, never after — see
   [docs/seeding.md#languages](docs/seeding.md#languages).
--- a/plugin/AGENTS.md
+++ b/plugin/AGENTS.md
@@ -27,5 +27,8 @@
 - Blocks render server-side and use plugin token custom properties; see the
   root `AGENTS.md` and `docs/STANDARDS.md` for the full contract.
 - Do not add client-theme dependencies.
+- Seeding is bootstrap-only: the seeder creates missing rows and never writes
+  to a row that exists. The content-arbitration hashes are gone; do not add
+  per-row hash meta or a re-sync path (see `docs/seeding.md`).
 
 Use conventional commits and do not push without explicit user approval.
--- a/plugin/README.md
+++ b/plugin/README.md
@@ -20,14 +20,15 @@
 
 Client themes declare their pages, posts, media, navigation, and custom post types in a
 `seed/manifest.php` file; content itself lives in the theme's `patterns/` directory.
-`wp pediment seed` makes the database match the manifest without ever overwriting
-content a client has edited, and `wp pediment adopt <key>` exports a live page's edited
-markup back into its pattern file. A manifest that declares a `languages` section makes
-the site multilingual (via Polylang); `wp pediment languages` configures Polylang from
-that section before the first seed, and `wp pediment adopt <key> --language=de` adopts
-one language's translation. See [docs/seeding.md](../docs/seeding.md) for the full
-manifest reference, the arbitration rules, the languages section, and how to read a
-dry-run plan.
+`wp pediment seed` bootstraps a site: it creates what the manifest declares and the site
+does not have yet, and never changes a page, menu or setting that exists. Live pages
+change in the editor or through the MCP tools; `wp pediment adopt <key>` exports a live
+page's markup into its pattern file so new environments match. A manifest that declares a
+`languages` section makes the site multilingual (via Polylang); `wp pediment languages`
+configures Polylang from that section before the first seed, and
+`wp pediment adopt <key> --language=de` adopts one language's translation. See
+[docs/seeding.md](../docs/seeding.md) for the full manifest reference, what a seed does
+on a site that is already seeded, the languages section, and how to read a dry-run plan.
 
 ## AI tools (MCP)
 
--- a/.github/actions/seed-check/action.yml
+++ b/.github/actions/seed-check/action.yml
@@ -114,9 +114,9 @@
       shell: bash
       working-directory: ${{ inputs.theme-path }}
       # Assert on the seeder's own summary line rather than re-deriving its verb
-      # list. Reporter::summaryLine() counts CREATE + UPDATE + RESTORE as writes
-      # (plugin/src/Seeder/Reporter.php); a grep for create|update alone would
-      # silently pass "converged" while a restore was still pending.
+      # list. Reporter::summaryLine() counts every plan item that writes
+      # (plugin/src/Seeder/Reporter.php); since 4.0 the seeder only creates, so
+      # "0 to write," means everything the manifest declares exists.
       run: |
         set -euo pipefail
         npm run seed:plan | tee /tmp/replan.txt
PATCH
```

- [ ] **Step 7: Check for leftovers**

```bash
git grep -n -i -E 'arbitration|protected|content hash|_pediment_seed_hash|re-assert|git-owned' -- docs/seeding.md docs/client-sites.md docs/STANDARDS.md AGENTS.md plugin/AGENTS.md plugin/README.md plugin/inc/seeding-admin.php
```

Expected: exactly three intended hits — the 3.x history sentence in the "Identity" section of `docs/seeding.md`, the `--json` note in `docs/seeding.md` ("an item has no `protected` field"), and the new rule in `plugin/AGENTS.md` ("The content-arbitration hashes are gone"). Fix anything else.

- [ ] **Step 8: Commit**

```bash
git add plugin/inc/seeding-admin.php plugin/tests/phpunit/Seeder/SeedingAdminTest.php docs/seeding.md docs/client-sites.md docs/WORDPRESS_TRAPS.md docs/STANDARDS.md AGENTS.md plugin/AGENTS.md plugin/README.md .github/actions/seed-check/action.yml
git commit -m "docs(seeder): describe bootstrap-only seeding"
```

---

### Task 7: Client template and skills (port-page stop-gap)

The seeder now creates a page once, so `/pediment:port-page` can no longer iterate by editing the pattern file and re-seeding (S10). Stop-gap: the seed creates the page once; every later change goes through the `pediment-write-page` MCP tool after `validate-blocks`; `npm run adopt` refreshes the pattern file at the end. `/pediment:start` and the client template's README and AGENTS say the same (S11). The template's wp-env installs the MCP Adapter so the tool exists locally (D9). The full live-first rewrite of port-page stays in Plan 5.

**Files:**
- Modify: `client-kit/skills/port-page/SKILL.md`, `client-kit/skills/start/SKILL.md:188-193`, `client-template/README.md`, `client-template/AGENTS.md`, `client-template/.wp-env.json`, `client-template/package.json:11`
- Test: `client-kit/tests/kit.test.mjs`

**Interfaces:**
- Consumes: `pediment-write-page`, `pediment-read-page`, `pediment-get-vocabulary`, `pediment-check-page` (Plan 3), `validate-blocks` (Plan 3).
- Produces: no code interfaces.

- [ ] **Step 1: Write the failing test**

Append to `client-kit/tests/kit.test.mjs`:

```js
test('port-page and start send changes to an existing page through MCP, not a re-seed', async () => {
  const port = await readFile(path.join(kit, 'skills', 'port-page', 'SKILL.md'), 'utf8');
  const start = await readFile(path.join(kit, 'skills', 'start', 'SKILL.md'), 'utf8');

  assert.match(port, /pediment-write-page/);
  assert.match(port, /npm run adopt -- <key>/);
  assert.doesNotMatch(port, /re-validate \(step 5\), re-seed/);
  assert.match(start, /pediment-write-page/);
  assert.doesNotMatch(start, /`npm run seed` re-applies them/);
});
```

- [ ] **Step 2: Run the test to verify it fails**

Run: `node --test client-kit/tests/kit.test.mjs`
Expected: FAIL — `port-page and start send changes to an existing page through MCP, not a re-seed` (no `pediment-write-page` in port-page).

- [ ] **Step 3: Update the skills and the client template**

````bash
git apply <<'PATCH'
--- a/client-kit/skills/port-page/SKILL.md
+++ b/client-kit/skills/port-page/SKILL.md
@@ -1,14 +1,18 @@
 ---
 name: port-page
-description: Rebuild one existing page as native Pediment blocks in a scaffolded client theme, iterating under an independent fidelity critic until it matches the source, then adopt it back into git. Use after /pediment:start has scaffolded and seeded the site.
+description: Rebuild one existing page as native blocks in a scaffolded client theme. The seed creates the page once; every later change goes through the pediment-write-page MCP tool, iterating under an independent fidelity critic until it matches the source; then adopt it back into git. Use after /pediment:start has scaffolded and seeded the site.
 allowed-tools: Bash(node ${CLAUDE_SKILL_DIR}/../../scripts/validate-blocks.mjs:*)
 ---
 
 # Port one page to Pediment
 
-Rebuild ONE existing public page as native Pediment blocks, iterate under an independent visual
-fidelity critic, then commit the result to git as a pattern file.
+Rebuild ONE existing public page as native blocks, iterate under an independent visual fidelity
+critic, then commit the result to git as a pattern file.
 
+The seeder bootstraps: `npm run seed` creates the page once, from its pattern file, and never
+updates it again. Every change after that goes to the live page through the `pediment-write-page`
+MCP tool. The pattern file is refreshed at the end with `npm run adopt`, for new environments.
+
 Claude Code prepends `Base directory for this skill: <absolute path>` when this skill loads. Call
 that directory `<skill-dir>` and resolve the bundled review instructions as
 `<skill-dir>/../../shared/fidelity-critic-prompt.md` and
@@ -24,7 +28,7 @@
 
 ## 1. Preconditions
 
-Check all three. Stop on the first failure with the stated message.
+Check all four. Stop on the first failure with the stated message.
 
 1. **wp-env is running** — `npx wp-env run cli wp option get siteurl`. If it errors: "wp-env is not
    running — start it with `npm run env:start`, then re-run `/pediment:port-page`."
@@ -33,6 +37,12 @@
    first."
 3. **The source URL is publicly reachable** — load it in the browser (Chrome). Stop on an error or
    a redirect loop.
+4. **The Pediment MCP server is connected** — the `pediment-get-vocabulary` tool is available. If
+   it is not, create an application password with
+   `npx wp-env run cli wp user application-password create admin claude --porcelain`, then run
+   `claude mcp add --transport http pediment "<siteurl>/?rest_route=/pediment/v1/mcp" --header "Authorization: Basic $(printf 'admin:<password>' | base64)"`
+   with the site URL from check 1, and stop: "The Pediment MCP server is now configured —
+   restart Claude Code in this directory, then re-run `/pediment:port-page`."
 
 ---
 
@@ -79,29 +89,24 @@
 ?>
 ```
 
-Then `npm run seed:plan`, read the plan, and `npm run seed`.
+Do not seed yet. The seed creates the page once, from the pattern as it is at that moment.
 
 ---
 
-## 4. Build the page
+## 4. Build the first version
 
-Two routes; pick per page and say which you are using.
+Author the page's markup in `patterns/<key>.php`, below the header. Call `pediment-get-vocabulary`
+first and use the blocks and named styles it lists. Never set custom colours, font sizes or
+spacing — the brand is in `theme.json` and the named styles, and hardcoding defeats it. Do not
+use the legacy `pediment/*` presentation blocks (the ones `pediment-check-page` reports as
+legacy): Pediment 4.0 hides them from the inserter, and 5.0 removes them.
 
-- **Author the pattern file directly** when the structure is clear from the source. Faster, and the
-  diff is readable.
-- **Build it in the block editor**, then `npm run adopt -- <key>` to write it back into
-  `patterns/<key>.php` with media URLs converted to `{{media_*}}` placeholders. Use this when the
-  layout needs to be seen to be got right.
-
-Prefer a purpose-built `pediment/*` block over composing primitives. Never set custom colours, font
-sizes or spacing — the brand is in `theme.json` and hardcoding defeats it.
-
 ---
 
-## 5. Validate the pattern
+## 5. Validate, then seed
 
-Validate the pattern before seeding it with real content. The editor rejects a static block whose
-markup does not match what it generates, and the page then opens as "invalid content".
+Validate the pattern before seeding it. The editor rejects a static block whose markup does not
+match what it generates, and the page then opens as "invalid content".
 
 ```bash
 node ${CLAUDE_SKILL_DIR}/../../scripts/validate-blocks.mjs --file patterns/<key>.php
@@ -117,6 +122,13 @@
 does not run WordPress 6.9. Exit code 2 means the tool itself failed (for example no network on
 the first run), not that the page is invalid.
 
+Then `npm run seed:plan` — expect exactly one `create` line for `<key>` — and `npm run seed`. Note
+the new page's ID:
+
+```bash
+npx wp-env run cli wp post list --post_type=page --name=<key> --field=ID
+```
+
 ---
 
 ## 6. Iterate under the critic
@@ -125,17 +137,29 @@
 `<skill-dir>/../../shared/fidelity-critic-prompt.md`. Before dispatch, replace its
 `{{RUBRIC_PATH}}` placeholder with the resolved absolute path of
 `<skill-dir>/../../shared/visual-qa.md`, fill the remaining placeholders, and send the resulting
-prompt verbatim to an independent critic with the source and rebuilt screenshots. Fix what it
-names, re-validate (step 5), re-seed, re-screenshot, re-run. Stop when it reports no material
-differences, or after four rounds — then report honestly what still differs and why.
+prompt verbatim to an independent critic with the source and rebuilt screenshots.
 
+Fix what it names on the live page, never by editing the pattern file and re-seeding (a seed does
+not update a page that exists):
+
+1. Read the current markup with `pediment-read-page` (`id` = the page ID) and keep a working copy
+   in `.context/port/<key>/page.html`. It carries real media URLs, not placeholders.
+2. Edit the working copy, then validate it:
+   `node ${CLAUDE_SKILL_DIR}/../../scripts/validate-blocks.mjs --file .context/port/<key>/page.html`.
+3. Write it with `pediment-write-page` (`id`, `title`, `markup`). A rejected write lists every rule
+   it broke; fix them all and write again.
+
+Re-screenshot and re-run the critic. Stop when it reports no material differences, or after four
+rounds — then report honestly what still differs and why.
+
 Run the checks in `<skill-dir>/../../shared/visual-qa.md` before declaring the page done.
 
 ---
 
 ## 7. Persist
 
-If the page was built in the editor, adopt it now:
+Export the live page into its pattern file, so the first seed of a new environment creates the
+page as it is now:
 
 ```bash
 npm run adopt -- <key>
@@ -151,8 +175,9 @@
 
 ## Multilingual pages
 
-Port the default language first. For each additional language, translate the page in the editor and
-run `npm run adopt -- <key> --language=<code>` — note the single `--`; npm forwards everything after
+Port the default language first. For each additional language, write the translation with
+`pediment-write-page` (`language` = the code, `translation_of` = the default-language page ID) or
+translate it in the editor, then run `npm run adopt -- <key> --language=<code>` — note the single `--`; npm forwards everything after
 it verbatim, and a second `--` would arrive at `wp pediment adopt` as a literal extra positional
 argument, which WP-CLI rejects with "Too many positional arguments: --". This writes
 `patterns/<key>.<lang>.php` with the correct `Slug: <theme-slug>/<key>-<lang>` header. The filename
--- a/client-kit/skills/start/SKILL.md
+++ b/client-kit/skills/start/SKILL.md
@@ -188,8 +188,11 @@
 
 ## Phase 4 — hand off
 
-- **Greenfield:** tell the user which pattern files to edit (`patterns/<key>.php`), and that
-  `npm run seed` re-applies them. Offer to build the first page with them.
+- **Greenfield:** the seed created every page once, from its pattern file (`patterns/<key>.php`).
+  Tell the user that later changes to a page go through the `pediment-write-page` MCP tool or the
+  block editor — a re-seed never updates a page that exists — and that `npm run adopt -- <key>`
+  writes a finished page back into its pattern file for new environments. Offer to build the
+  first page with them.
 - **Port:** hand over the page list and run `/pediment:port-page <url>` per page, one at a time.
 
 Offer to create a GitHub remote **last**, and only with explicit confirmation:
--- a/client-template/README.md
+++ b/client-template/README.md
@@ -9,23 +9,27 @@
 ```bash
 npm install
 npm run env:start     # http://localhost:8888
-npm run seed:plan     # see what a seed would change
+npm run seed:plan     # see what a seed would create
 npm run seed
 ```
 
 ## Changing content
 
-Structure — which pages exist, their slugs, nesting, nav membership — lives in
-`seed/manifest.php`. Content lives in `patterns/*.php`. Edit either, then run `npm run seed`.
+The seed bootstraps the site: it creates the pages, posts, menus and media that
+`seed/manifest.php` and `patterns/*.php` declare and the site does not have yet. It never
+changes anything that exists.
 
-A page a client has edited in the editor is protected: the seeder compares a content hash and
-leaves edited pages alone, reconciling structure only. To bring a client's live edit back into
-git, run `npm run adopt -- <page-key>`.
+- **New page:** declare it in `seed/manifest.php`, add `patterns/<key>.php`, run `npm run seed`.
+- **Existing page:** change it in the block editor, or with the Pediment MCP tools (see the
+  Pediment repo's `docs/mcp.md`). Editing its pattern file does not change the site.
+- **New environments should match a live page:** run `npm run adopt -- <page-key>` and commit
+  the pattern file.
 
 ## Deploying
 
 Push a `v*` tag. The release workflow builds `__PEDIMENT_SLUG__.zip` with the version header
 stamped, and attaches it to the GitHub release. Install it via Appearance → Themes → Add New →
-Upload, then re-seed from Settings → Pediment Theme → Seeding.
+Upload, then use Settings → Pediment Theme → Seeding → Apply plan to create any page or menu
+the release added. It never changes a page that exists.
 
 The Pediment plugin updates itself through wp-admin; this theme does not, by design.
--- a/client-template/AGENTS.md
+++ b/client-template/AGENTS.md
@@ -5,11 +5,13 @@
 ## Hard rules
 
 - **Never write pages with `wp post create` or `wp post update`.** The seeder owns identity
-  (`_pediment_seed_key`) and the content-arbitration hashes; a hand-written post bypasses both.
-- Structure goes in `seed/manifest.php`; content goes in `patterns/*.php`. Run
-  `npm run seed:plan` before `npm run seed`, always.
-- To capture an edit made in the block editor, run `npm run adopt -- <key>` — never copy markup
-  out of the database by hand.
+  (`_pediment_seed_key`); a hand-written post has none, and the next seed creates a duplicate.
+- A new page goes in `seed/manifest.php` plus `patterns/<key>.php`; `npm run seed` creates it.
+  Run `npm run seed:plan` before `npm run seed`, always. A seed never changes a page that
+  exists.
+- Change a page that exists with the `pediment-write-page` MCP tool (validate the markup with
+  client-kit's `validate-blocks` first) or in the block editor. To make new environments match
+  it, run `npm run adopt -- <key>` — never copy markup out of the database by hand.
 - Never edit plugin files to get client-specific behaviour. Use theme.json, patterns, template
   overrides in `templates/`, and the plugin's filters.
 - Colours come from `theme.json` presets. Use `var(--wp--preset--color--…)`, never literals.
--- a/client-template/.wp-env.json
+++ b/client-template/.wp-env.json
@@ -2,7 +2,8 @@
   "core": "WordPress/WordPress#6.9",
   "phpVersion": "8.1",
   "plugins": [
-    "https://github.com/Bergert-Digital/pediment/releases/download/v__PEDIMENT_PLUGIN_VERSION__/pediment-plugin.zip"
+    "https://github.com/Bergert-Digital/pediment/releases/download/v__PEDIMENT_PLUGIN_VERSION__/pediment-plugin.zip",
+    "https://downloads.wordpress.org/plugin/mcp-adapter.0.7.0.zip"
   ],
   "config": {
     "WP_DEBUG": true,
--- a/client-template/package.json
+++ b/client-template/package.json
@@ -8,7 +8,7 @@
     "plugin": "__PEDIMENT_PLUGIN_VERSION__"
   },
   "scripts": {
-    "env:start": "wp-env start && wp-env run cli wp theme activate __PEDIMENT_SLUG__ && wp-env run cli wp plugin activate pediment-plugin",
+    "env:start": "wp-env start && wp-env run cli wp theme activate __PEDIMENT_SLUG__ && wp-env run cli wp plugin activate pediment-plugin mcp-adapter",
     "env:stop": "wp-env stop",
     "languages": "wp-env run cli wp pediment languages",
     "seed:plan": "wp-env run cli wp pediment seed --dry-run",
PATCH
````

- [ ] **Step 4: Run the tests to verify they pass**

```bash
npm run test:kit
node -e "JSON.parse(require('fs').readFileSync('client-template/.wp-env.json','utf8')); JSON.parse(require('fs').readFileSync('client-template/package.json','utf8')); console.log('json ok')"
```

Expected: all kit tests pass (the new one included); `json ok`. CI's client-template job ("Scaffold a client site from the template", "Seed it and assert it renders") boots the template with the adapter; say in the report that it runs in CI.

- [ ] **Step 5: Commit**

```bash
git add client-kit/tests/kit.test.mjs client-kit/skills/port-page/SKILL.md client-kit/skills/start/SKILL.md client-template/README.md client-template/AGENTS.md client-template/.wp-env.json client-template/package.json
git commit -m "docs(kit): change seeded pages through MCP, not a re-seed"
```

---

### Task 8: Release preparation for 4.0.0 (stops before push)

Verify that release-please will cut 4.0.0 with the right version headers, record the plan statuses in the roadmap, and write "Upgrade to 4.0" (R1). **This task stops before any push, PR merge or release.** Pushing the branch, merging PR #117 into `main`, and merging the release-please PR each need Jonas's explicit approval (AGENTS.md, "Commits and releases").

**Files:**
- Modify: `docs/client-sites.md` (new section "Upgrade to 4.0"), `plugin/README.md:14` (link), `docs/superpowers/plans/2026-10-14-roadmap.md` (table rows 3 and 4, Plan 4 and Plan 5 notes)
- Read only: `release-please-config.json`, `.release-please-manifest.json`, `plugin/plugin.php:1-24`, `client-kit/.claude-plugin/plugin.json`, `.claude-plugin/marketplace.json`, `.github/workflows/build-release-zip.yml:39-46`

**Interfaces:** none.

- [ ] **Step 1: Verify the release configuration**

```bash
node -e "const c=require('./release-please-config.json').packages['.']; const m=require('./.release-please-manifest.json'); console.log(c['release-type'], c['bump-minor-pre-major']===undefined, m['.']); console.log(JSON.stringify(c['extra-files']))"
grep -n "x-release-please-start-version\|^ \* Version:\|x-release-please-end\|Requires at least: 6.9\|PEDIMENT_AI_VERSION'" plugin/plugin.php
grep -n '"version"' client-kit/.claude-plugin/plugin.json .claude-plugin/marketplace.json
git log --format='%h %s' origin/main..HEAD | grep -E '^[0-9a-f]+ [a-z]+(\([a-z-]+\))?!:'
git log --format='%B' origin/main..HEAD | grep -c '^BREAKING CHANGE:'
```

Expected: `simple true 3.10.0`; the extra-files list names `plugin/plugin.php`, `client-kit/.claude-plugin/plugin.json` (`$.version`) and `.claude-plugin/marketplace.json` (`$.plugins[0].version`); `plugin.php` shows the `x-release-please-start-version` block around `Version: 3.10.0`, `Requires at least: 6.9`, and `PEDIMENT_AI_VERSION` with the `x-release-please-version` comment; both JSON files show `3.10.0`; the `!:` list contains `feat!: remove the AI chat server, REST API and settings`, `feat!: remove the AI chat editor UI, require WordPress 6.9` and the three `feat(seeder)!:` commits from Tasks 1–3; the `BREAKING CHANGE:` count is at least 5. If anything differs, stop and report it — do not edit the release config without Jonas.

- [ ] **Step 2: Write "Upgrade to 4.0"**

Plan 4a added the "Convert legacy blocks" screen. Find its menu path first:

```bash
grep -rn "Convert legacy blocks" plugin/src plugin/inc | head -5
```

The text below assumes "Settings → Pediment Theme → Convert legacy blocks". If the grep shows a different registration (another parent menu or tab label), write that path instead in item 3.

In `docs/client-sites.md`, insert this section directly before `## Moving an existing site onto Pediment`:

```markdown
## Upgrade to 4.0

Pediment 4.0 is a breaking release. On each site, in this order:

1. **Update the plugin.** wp-admin → Plugins → update Pediment (or upload
   `pediment-plugin.zip`). WordPress 6.9 or later is required. The first
   request after the update removes the retired AI chat's tables and settings;
   the first admin request removes the 3.x seeder's content hashes
   (`_pediment_seed_hash`, `_pediment_seed_source`, `_pediment_seed_mega_hash`).
   Pages, menus and media do not change.
2. **Install the MCP Adapter plugin** (`mcp-adapter` 0.7.0) if AI agents
   should build pages on the site. [mcp.md](mcp.md) has the application
   password and the `claude mcp add` line.
3. **Convert legacy blocks.** Settings → Pediment Theme → Convert legacy
   blocks. Run the dry run first and read the per-page diff, then convert.
   Every page gets a revision first. The legacy `pediment/*` presentation
   blocks still render in 4.0, but the inserter hides them, and 5.0 deletes
   them.
4. **Know what changed in seeding.** `wp pediment seed` and "Apply plan" are
   bootstrap-only now: they create what is missing and never change a page,
   menu, reading setting or logo that exists. A deploy that used to push
   pattern edits to live pages through a re-seed no longer does — change live
   pages in the editor or through the MCP tools ([seeding.md](seeding.md),
   "Bootstrap only"). The plan summary reads `N to write, N orphan,
   N unchanged.` (no `protected` count), and `--format=json` drops
   `protected` from each item and from `counts`.
5. **The AI chat is gone.** The editor sidebar, its REST routes and the AI
   settings tab were removed. The MCP tools replace them.
```

In `plugin/README.md`, replace the line

```markdown
Upgrading from 3.x removes the retired AI chat's tables and settings on the first request.
```

with

```markdown
Upgrading from 3.x: read [Upgrade to 4.0](../docs/client-sites.md#upgrade-to-40) first.
```

- [ ] **Step 3: Update the roadmap**

In `docs/superpowers/plans/2026-10-14-roadmap.md` (Plan 4a may have edited this file; keep its additions):

- Table row 3: set the status cell to `**done** (branch \`bergjs/elementor-pediment-necessity\`)`.
- Table row 4: set the status cell to `**done** — 4a and 4b (\`2026-10-15-plan-4b-seeder-release.md\` and Plan 4a's file, see \`ls docs/superpowers/plans/2026-10-15-plan-4a-*\`); release 4.0.0 waits for Jonas's approval`.
- Replace the whole "**Plan 4 — Converter and v4.0.0.**" notes block (the bold line and its bullets) with:

```markdown
**Plan 4 — Converter and v4.0.0.** Written and implemented as Plan 4a
(converter, default styles, legacy blocks hidden, `convert-legacy`) and Plan
4b (seeder bootstrap-only, hash cleanup, docs and skills, release
preparation). Still open, each only with Jonas's explicit approval: push the
branch, merge PR #117 into `main`, merge the release-please PR that cuts
4.0.0. Client templates pin `v<version>/pediment-plugin.zip`, so 4.0.0 must
exist as a release before Plan 5 starts.
```

- Append to the "**Plan 5 — Site conversions.**" notes paragraph:

```markdown
- `straight-solutions-website` still runs the retired architecture (child
  theme of parent pediment v0.8.0 + pediment-ai v0.1.7). It needs a move to
  the Pediment plugin before any block conversion.
- Workation registers block styles on `pediment/feature` and has tests tied to
  legacy markup; `pediment-website` has no tests; no site has 375/768/1440
  screenshot tooling yet.
- The production content inventory per site is unknown (admin-only hosting):
  the wp-admin convert screen's dry run is the inventory tool.
- The live-first rewrite of `/pediment:port-page` (build through MCP instead of
  patterns + seed) belongs here; Plan 4b only added a stop-gap.
```

- [ ] **Step 4: Final verification**

```bash
npx wp-env run tests-wordpress --env-cwd=wp-content/plugins/pediment-ai ./vendor/bin/phpunit
npx wp-env run tests-wordpress --env-cwd=wp-content/plugins/pediment-ai ./vendor/bin/phpunit -c phpunit-polylang.xml.dist
composer lint -d plugin
npm run lint:js
npm run lint:blocks
npm run lint:colors
npm run test:kit
node tools/mcp-smoke.mjs
npx wp-env run cli wp pediment seed --dry-run
```

Expected: all pass; the dry run ends with `0 to write, 0 orphan, … unchanged. Nothing was written (--dry-run).` The WPML suite and Playwright e2e run in CI; say so.

- [ ] **Step 5: Commit, then stop**

```bash
git add docs/client-sites.md plugin/README.md docs/superpowers/plans/2026-10-14-roadmap.md
git commit -m "docs: add the upgrade-to-4.0 guide and roadmap status"
git status --short
git log --oneline origin/main..HEAD | head -20
```

Stop here. Report the commit list and say that pushing, merging PR #117 and merging the release-please PR are open and need Jonas's approval. Do not run `git push`, `gh pr merge` or any release command.

---

## Final verification

- [ ] Run everything on the final tree:

```bash
npx wp-env run tests-wordpress --env-cwd=wp-content/plugins/pediment-ai ./vendor/bin/phpunit
npx wp-env run tests-wordpress --env-cwd=wp-content/plugins/pediment-ai ./vendor/bin/phpunit -c phpunit-polylang.xml.dist
composer lint -d plugin
npm run lint:js
npm run lint:blocks
npm run lint:colors
npm run test:kit
git grep -n -E 'ContentHash|MegaBlocks|Meta::HASH|Meta::SOURCE|MEGA_HASH|protectedFields|PlanItem::(UPDATE|RESTORE|PROTECTED)' -- plugin client-kit tools
```

Expected: all pass; the last grep prints nothing. The WPML suite and Playwright e2e run only in CI; say so.
