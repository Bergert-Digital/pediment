# Mega Menu Seeding Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Client manifests can declare `pediment/mega-menu` items in their navs; the seeder writes them with per-language resolved links, and the block's content becomes editable in the Site Editor without being reverted (hash-arbitrated ownership, mirroring seeded pages).

**Architecture:** Nav membership stays git-owned (byte comparison of `post_content` against a fresh `serialize()`). Mega block *content* is arbitrated per position by a stored hash (`_pediment_seed_mega_hash` on the nav entity): while the stored block still hashes to what the seeder last wrote, git owns it and manifest changes flow through; once a human edits it, the seeder splices the stored markup through verbatim, forever. A new `MegaBlocks` helper owns extraction/hashing; `NavSeeder::serialize()` gains the stored content + nav ID as inputs so `plan()`/`Verifier` byte comparisons keep working unchanged.

**Tech Stack:** PHP 8.1, WordPress 6.9 via wp-env, PHPUnit 9.6 (`WP_UnitTestCase`), phpcs (WordPress coding standards).

**Spec:** `docs/superpowers/specs/2026-08-14-mega-menu-seeding-design.md` — read it first; every rule below (key order, ownership regimes, error wording) is argued there.

## Global Constraints

- All work happens on the current branch. Commit after each task; **never push**.
- **Attribute key order is load-bearing** everywhere in `NavSeeder`: mega attrs are `label`, `columns`; per column `heading`, `icon`, `links`; per link `label`, `description`, `url`. Optional keys are **omitted**, never emitted empty. `wp_json_encode( …, JSON_UNESCAPED_SLASHES )` only.
- Stored mega blocks are handled **verbatim** (substring extraction) — never `parse_blocks()` + re-serialize, which breaks byte stability.
- Match the seeder's code style: tabs, Yoda conditions (`0 === $postId`), short arrays `[]`, `declare(strict_types=1)`, heavily reasoned docblocks, `// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- exception message for the operator, not echoed output.` on every `throw new ManifestError(…)` line.
- A manifest with zero mega items must serialize **byte-identically** to today's output and write no new meta.

## Environment prep (once, before Task 1)

```bash
cd /Users/jonas/conductor/workspaces/pediment/pattaya
composer install --prefer-dist --no-progress -d plugin
cd plugin && npm ci && npm run build && cd ..
npm run env:start
```

Run tests from the repo root, inside the wp-env container (the plugin mounts at `wp-content/plugins/pediment-ai` regardless of the workspace folder name):

```bash
npx wp-env run tests-wordpress --env-cwd=wp-content/plugins/pediment-ai ./vendor/bin/phpunit --filter <TestClassOrMethod>
```

Lint before each commit: `composer lint -d plugin` (errors block CI; warnings don't).

---

### Task 1: Manifest validates the `mega` item grammar

**Files:**
- Modify: `plugin/src/Seeder/Manifest.php` (`navItem()`, new private `megaItem()`)
- Test: `plugin/tests/phpunit/Seeder/ManifestTest.php`

**Interfaces:**
- Consumes: existing `Manifest::navItem( array $item, array $entries, string $path, bool $allowChildren )` and `ManifestError`.
- Produces: a validated nav item array that may carry a normalized `mega` key: `{ label: string, columns: array<int, { heading: string, icon?: string, links: array<int, { entry?: string, label?: string, url?: string, description?: string }> }> }`. Later tasks (`NavSeeder::megaMarkup()`, `unresolvedItem()`) rely on this exact shape and on the fact that anything else was rejected at parse time.

- [ ] **Step 1: Write the failing tests**

Add to `plugin/tests/phpunit/Seeder/ManifestTest.php` (inside the existing class; `raw()` already declares the `guide` page these tests link to):

```php
	private function withNav( array $items ): array {
		$raw         = $this->raw();
		$raw['navs'] = [ 'primary' => [ 'title' => 'Primary', 'items' => $items ] ];
		return $raw;
	}

	private function megaSpec( array $overrides = [] ): array {
		return array_merge(
			[
				'label'   => 'Products',
				'columns' => [
					[
						'heading' => 'Guides',
						'icon'    => 'bank',
						'links'   => [
							[ 'entry' => 'guide', 'description' => 'The handbook' ],
							[ 'label' => 'Savings', 'url' => '/savings/' ],
						],
					],
				],
			],
			$overrides
		);
	}

	public function test_a_valid_mega_item_parses() {
		$m = Manifest::fromArray( $this->withNav( [ [ 'mega' => $this->megaSpec() ] ] ), '/tmp/theme' );

		$this->assertSame( 'Products', $m->navs()['primary']->items[0]['mega']['label'] );
		$this->assertSame( 'Guides', $m->navs()['primary']->items[0]['mega']['columns'][0]['heading'] );
	}

	public function test_a_mega_item_is_a_leaf() {
		$this->expectException( ManifestError::class );
		$this->expectExceptionMessageMatches( '/navs\.primary\.items\.0: a mega item is a leaf/' );
		Manifest::fromArray(
			$this->withNav( [ [ 'mega' => $this->megaSpec(), 'children' => [ [ 'url' => '/x', 'label' => 'X' ] ] ] ] ),
			'/tmp/theme'
		);
	}

	public function test_mega_inside_children_is_rejected() {
		$this->expectException( ManifestError::class );
		$this->expectExceptionMessageMatches( "/navs\.primary\.items\.0\.children\.0: a mega item may not appear inside 'children'/" );
		Manifest::fromArray(
			$this->withNav( [ [ 'entry' => 'guide', 'children' => [ [ 'mega' => $this->megaSpec() ] ] ] ] ),
			'/tmp/theme'
		);
	}

	public function test_mega_does_not_combine_with_other_item_types() {
		$this->expectException( ManifestError::class );
		$this->expectExceptionMessageMatches( "/navs\.primary\.items\.0: 'mega' is its own item type and may not combine with 'entry'/" );
		Manifest::fromArray( $this->withNav( [ [ 'mega' => $this->megaSpec(), 'entry' => 'guide' ] ] ), '/tmp/theme' );
	}

	public function test_mega_label_is_required() {
		$this->expectException( ManifestError::class );
		$this->expectExceptionMessageMatches( "/navs\.primary\.items\.0\.mega: 'label' is required/" );
		Manifest::fromArray( $this->withNav( [ [ 'mega' => $this->megaSpec( [ 'label' => ' ' ] ) ] ] ), '/tmp/theme' );
	}

	public function test_mega_columns_must_be_a_non_empty_array() {
		$this->expectException( ManifestError::class );
		$this->expectExceptionMessageMatches( "/navs\.primary\.items\.0\.mega: 'columns' must be a non-empty array/" );
		Manifest::fromArray( $this->withNav( [ [ 'mega' => $this->megaSpec( [ 'columns' => [] ] ) ] ] ), '/tmp/theme' );
	}

	public function test_a_mega_column_needs_a_heading() {
		$mega = $this->megaSpec();
		unset( $mega['columns'][0]['heading'] );

		$this->expectException( ManifestError::class );
		$this->expectExceptionMessageMatches( "/mega\.columns\.0: 'heading' is required/" );
		Manifest::fromArray( $this->withNav( [ [ 'mega' => $mega ] ] ), '/tmp/theme' );
	}

	public function test_a_mega_column_needs_links() {
		$mega                        = $this->megaSpec();
		$mega['columns'][0]['links'] = [];

		$this->expectException( ManifestError::class );
		$this->expectExceptionMessageMatches( "/mega\.columns\.0: 'links' must be a non-empty array/" );
		Manifest::fromArray( $this->withNav( [ [ 'mega' => $mega ] ] ), '/tmp/theme' );
	}

	public function test_a_mega_link_needs_an_entry_or_a_url_label_pair() {
		$mega                           = $this->megaSpec();
		$mega['columns'][0]['links'][1] = [ 'label' => 'Dangling' ];

		$this->expectException( ManifestError::class );
		$this->expectExceptionMessageMatches( "/mega\.columns\.0\.links\.1: needs either 'entry' or both 'url' and 'label'/" );
		Manifest::fromArray( $this->withNav( [ [ 'mega' => $mega ] ] ), '/tmp/theme' );
	}

	public function test_a_mega_link_naming_an_unknown_entry_is_rejected() {
		$mega                           = $this->megaSpec();
		$mega['columns'][0]['links'][0] = [ 'entry' => 'ghost' ];

		$this->expectException( ManifestError::class );
		$this->expectExceptionMessageMatches( "/mega\.columns\.0\.links\.0: unknown entry 'ghost'/" );
		Manifest::fromArray( $this->withNav( [ [ 'mega' => $mega ] ] ), '/tmp/theme' );
	}
```

- [ ] **Step 2: Run to verify they fail**

Run: `npx wp-env run tests-wordpress --env-cwd=wp-content/plugins/pediment-ai ./vendor/bin/phpunit --filter ManifestTest`
Expected: the ten new tests FAIL (`mega` currently falls through to "needs either 'entry' or both 'url' and 'label'", so exceptions carry the wrong message or none).

- [ ] **Step 3: Implement validation**

In `plugin/src/Seeder/Manifest.php`, at the **top** of `navItem()` (before the `language_switcher` branch — an item declaring both must hit the mega combination error, and the switcher branch returns early):

```php
		if ( isset( $item['mega'] ) ) {
			if ( ! $allowChildren ) {
				throw new ManifestError( "{$path}: a mega item may not appear inside 'children' — the block only renders at the top level of the menu." ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- exception message for the operator, not echoed output.
			}
			if ( isset( $item['children'] ) ) {
				throw new ManifestError( "{$path}: a mega item is a leaf and may not declare 'children'." ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- exception message for the operator, not echoed output.
			}
			foreach ( [ 'entry', 'url', 'label', 'language_switcher' ] as $other ) {
				if ( isset( $item[ $other ] ) ) {
					throw new ManifestError( "{$path}: 'mega' is its own item type and may not combine with '{$other}'." ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- exception message for the operator, not echoed output.
				}
			}
			$item['mega'] = self::megaItem( (array) $item['mega'], $entries, "{$path}.mega" );
			return $item;
		}
```

Then add the new private method below `navItem()`:

```php
	/**
	 * Validate one mega item's full shape at parse time, leaf by leaf.
	 *
	 * Same argument as the nesting rule above: a mega menu that quietly drops
	 * a malformed column or link would look correct in review and ship a
	 * different menu than the manifest declares.
	 *
	 * @param array<string,mixed>     $mega
	 * @param array<string,EntrySpec> $entries
	 * @param string                  $path Operator-facing location, e.g. `navs.primary.items.1.mega`.
	 * @return array<string,mixed>
	 *
	 * @throws ManifestError When the label or a column/link is malformed, or a
	 *                       link names an unknown entry.
	 */
	private static function megaItem( array $mega, array $entries, string $path ): array {
		if ( ! isset( $mega['label'] ) || '' === trim( (string) $mega['label'] ) ) {
			throw new ManifestError( "{$path}: 'label' is required — it is the menu item the panel opens from." ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- exception message for the operator, not echoed output.
		}
		if ( ! isset( $mega['columns'] ) || ! is_array( $mega['columns'] ) || [] === $mega['columns'] ) {
			throw new ManifestError( "{$path}: 'columns' must be a non-empty array." ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- exception message for the operator, not echoed output.
		}

		$columns = [];
		foreach ( array_values( $mega['columns'] ) as $columnIndex => $column ) {
			$columnPath = "{$path}.columns.{$columnIndex}";
			$column     = (array) $column;

			if ( ! isset( $column['heading'] ) || '' === trim( (string) $column['heading'] ) ) {
				throw new ManifestError( "{$columnPath}: 'heading' is required." ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- exception message for the operator, not echoed output.
			}
			if ( ! isset( $column['links'] ) || ! is_array( $column['links'] ) || [] === $column['links'] ) {
				throw new ManifestError( "{$columnPath}: 'links' must be a non-empty array." ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- exception message for the operator, not echoed output.
			}

			$links = [];
			foreach ( array_values( $column['links'] ) as $linkIndex => $link ) {
				$linkPath = "{$columnPath}.links.{$linkIndex}";
				$link     = (array) $link;

				if ( isset( $link['entry'] ) ) {
					$target = (string) $link['entry'];
					if ( ! isset( $entries[ $target ] ) ) {
						throw new ManifestError( "{$linkPath}: unknown entry '{$target}'." ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- exception message for the operator, not echoed output.
					}
				} elseif ( ! isset( $link['url'], $link['label'] ) ) {
					throw new ManifestError( "{$linkPath}: needs either 'entry' or both 'url' and 'label'." ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- exception message for the operator, not echoed output.
				}

				$links[] = $link;
			}

			$column['links'] = $links;
			$columns[]       = $column;
		}
		$mega['columns'] = $columns;

		return $mega;
	}
```

- [ ] **Step 4: Run to verify they pass**

Run: `npx wp-env run tests-wordpress --env-cwd=wp-content/plugins/pediment-ai ./vendor/bin/phpunit --filter ManifestTest`
Expected: PASS (all, including the pre-existing tests).

- [ ] **Step 5: Lint and commit**

```bash
composer lint -d plugin
git add plugin/src/Seeder/Manifest.php plugin/tests/phpunit/Seeder/ManifestTest.php
git commit -m "feat(seeder): validate the mega nav-item grammar in the manifest"
```

---

### Task 2: `Meta::MEGA_HASH` and the `MegaBlocks` helper

**Files:**
- Modify: `plugin/src/Seeder/Meta.php`
- Create: `plugin/src/Seeder/MegaBlocks.php`
- Test: `plugin/tests/phpunit/Seeder/MegaBlocksTest.php`

**Interfaces:**
- Consumes: `ContentHash::VERSION`, `ContentHash::matches( string $stored, string $current ): bool`, `Meta::MEGA_HASH`.
- Produces (all static, used by Tasks 4–5):
  - `MegaBlocks::extract( string $content ): string[]` — verbatim `<!-- wp:pediment/mega-menu {…} /-->` substrings, document order.
  - `MegaBlocks::hash( string $block ): string` — `ContentHash::VERSION . ':' . sha256`.
  - `MegaBlocks::gitOwns( ?string $storedBlock, string $storedHash ): bool` — true when nothing is stored at that position or the stored markup is exactly what the seeder last wrote.
  - `MegaBlocks::storedHashes( int $navId ): string[]`
  - `MegaBlocks::writeHashes( int $navId, string $oldContent, string $newContent ): void`

- [ ] **Step 1: Write the failing tests**

Create `plugin/tests/phpunit/Seeder/MegaBlocksTest.php`:

```php
<?php
// plugin/tests/phpunit/Seeder/MegaBlocksTest.php

use Pediment\Seeder\MegaBlocks;
use Pediment\Seeder\Meta;

class MegaBlocksTest extends WP_UnitTestCase {

	private const BLOCK = '<!-- wp:pediment/mega-menu {"label":"Products","columns":[{"heading":"Banking","links":[{"label":"Features","url":"/features/"}]}]} /-->';

	public function test_extracts_blocks_verbatim_in_document_order() {
		$content = '<!-- wp:navigation-link {"label":"Home","url":"/","kind":"custom"} /-->' . "\n"
			. self::BLOCK . "\n"
			. '<!-- wp:pediment/mega-menu {"label":"More","columns":[{"heading":"B","links":[{"label":"L","url":"/l/"}]}]} /-->';

		$blocks = MegaBlocks::extract( $content );

		$this->assertCount( 2, $blocks );
		$this->assertSame( self::BLOCK, $blocks[0] );
		$this->assertStringContainsString( '"label":"More"', $blocks[1] );
	}

	public function test_extract_finds_nothing_in_mega_free_markup() {
		$this->assertSame( [], MegaBlocks::extract( '<!-- wp:navigation-link {"label":"Home","url":"/","kind":"custom"} /-->' ) );
		$this->assertSame( [], MegaBlocks::extract( '' ) );
	}

	public function test_git_owns_an_empty_position_and_an_unedited_block() {
		$this->assertTrue( MegaBlocks::gitOwns( null, '' ) );
		$this->assertTrue( MegaBlocks::gitOwns( self::BLOCK, MegaBlocks::hash( self::BLOCK ) ) );
	}

	public function test_the_client_owns_an_edited_or_unhashed_block() {
		$this->assertFalse( MegaBlocks::gitOwns( self::BLOCK, '' ), 'a missing hash (claimed nav) means edited' );
		$this->assertFalse( MegaBlocks::gitOwns( self::BLOCK, MegaBlocks::hash( 'something else' ) ) );
		$this->assertFalse( MegaBlocks::gitOwns( self::BLOCK, '0:deadbeef' ), 'a foreign hash version never matches' );
	}

	public function test_write_hashes_freshens_git_owned_positions_and_carries_edited_ones() {
		$navId = self::factory()->post->create( [ 'post_type' => 'wp_navigation' ] );
		update_post_meta( $navId, Meta::MEGA_HASH, wp_json_encode( [ MegaBlocks::hash( self::BLOCK ) ] ) );

		// The client edited the block; a membership rewrite splices it through.
		$edited = str_replace( 'Features', 'Edited', self::BLOCK );
		$new    = '<!-- wp:navigation-link {"label":"About","url":"/about/","kind":"custom"} /-->' . "\n" . $edited;

		MegaBlocks::writeHashes( $navId, $edited, $new );

		$stored = MegaBlocks::storedHashes( $navId );
		$this->assertSame( MegaBlocks::hash( self::BLOCK ), $stored[0], 'the stale hash is carried forward, so the block stays client-owned' );
		$this->assertFalse( MegaBlocks::gitOwns( $edited, $stored[0] ) );
	}

	public function test_write_hashes_freshens_a_git_owned_position() {
		$navId = self::factory()->post->create( [ 'post_type' => 'wp_navigation' ] );
		update_post_meta( $navId, Meta::MEGA_HASH, wp_json_encode( [ MegaBlocks::hash( self::BLOCK ) ] ) );

		// Git changed the manifest; the old block was untouched, so the new
		// markup was emitted from the manifest and gets a fresh hash.
		$new = str_replace( 'Products', 'Solutions', self::BLOCK );

		MegaBlocks::writeHashes( $navId, self::BLOCK, $new );

		$this->assertSame( [ MegaBlocks::hash( $new ) ], MegaBlocks::storedHashes( $navId ) );
	}

	public function test_write_hashes_deletes_the_meta_when_no_mega_remains() {
		$navId = self::factory()->post->create( [ 'post_type' => 'wp_navigation' ] );
		update_post_meta( $navId, Meta::MEGA_HASH, wp_json_encode( [ MegaBlocks::hash( self::BLOCK ) ] ) );

		MegaBlocks::writeHashes( $navId, self::BLOCK, '<!-- wp:navigation-link {"label":"Home","url":"/","kind":"custom"} /-->' );

		$this->assertSame( '', (string) get_post_meta( $navId, Meta::MEGA_HASH, true ) );
	}
}
```

- [ ] **Step 2: Run to verify they fail**

Run: `npx wp-env run tests-wordpress --env-cwd=wp-content/plugins/pediment-ai ./vendor/bin/phpunit --filter MegaBlocksTest`
Expected: FAIL — `Class "Pediment\Seeder\MegaBlocks" not found`.

- [ ] **Step 3: Implement**

Add to `plugin/src/Seeder/Meta.php`, below the `SOURCE` const:

```php
	/**
	 * JSON array of per-position hashes over the pediment/mega-menu blocks in
	 * a navigation entity, as last written by the seeder. Arbitrates mega
	 * content the way HASH arbitrates page content: matching = git owns the
	 * block and manifest changes flow through; anything else = the client
	 * edited it in the editor and keeps it. See MegaBlocks.
	 */
	public const MEGA_HASH = '_pediment_seed_mega_hash';
```

Create `plugin/src/Seeder/MegaBlocks.php`:

```php
<?php
/**
 * The pediment/mega-menu blocks inside a stored navigation entity, and the
 * per-position hash that arbitrates who owns each one.
 *
 * The nav-side twin of the page contract in docs/seeding.md ("The two
 * hashes"), scoped to one block type: membership stays git-owned, but a mega
 * block's content belongs to git only while its stored markup still hashes to
 * what the seeder last wrote.
 *
 * @package Pediment
 */

declare(strict_types=1);

namespace Pediment\Seeder;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class MegaBlocks {

	/**
	 * The self-closing block comments, verbatim and in document order — never
	 * parsed and re-serialized, which would break the byte stability plan()
	 * compares by.
	 *
	 * @return string[]
	 */
	public static function extract( string $content ): array {
		if ( '' === $content ) {
			return [];
		}
		preg_match_all( '#<!-- wp:pediment/mega-menu\s+\{.*?\}\s+/-->#s', $content, $matches );
		return $matches[0];
	}

	/**
	 * ContentHash's versioned shape so a VERSION bump makes every stored mega
	 * hash foreign, which falls back to "treat as edited" — never a silent
	 * overwrite.
	 */
	public static function hash( string $block ): string {
		return ContentHash::VERSION . ':' . hash( 'sha256', $block );
	}

	/**
	 * Git owns a position when nothing is stored there yet, or the stored
	 * markup is exactly what the seeder last wrote. A missing, foreign-version
	 * or mismatched hash means a human edited the block in the editor, and the
	 * seeder must splice the stored markup through verbatim.
	 */
	public static function gitOwns( ?string $storedBlock, string $storedHash ): bool {
		return null === $storedBlock || ContentHash::matches( $storedHash, self::hash( $storedBlock ) );
	}

	/** @return string[] Hash per mega position, in nav order. */
	public static function storedHashes( int $navId ): array {
		$raw     = get_post_meta( $navId, Meta::MEGA_HASH, true );
		$decoded = json_decode( is_string( $raw ) ? $raw : '', true );
		return is_array( $decoded ) ? array_map( 'strval', array_values( $decoded ) ) : [];
	}

	/**
	 * Record the hashes after a write. Per position: a block the seeder
	 * emitted from the manifest gets a fresh hash; a block spliced through
	 * because the client owns it carries its old entry (or absence) forward,
	 * so it stays client-owned. The twin of Applier's "an update on a
	 * client-edited page must leave the arbitration hash alone" — without the
	 * carry-forward, any membership-driven rewrite would re-hash the client's
	 * edited markup, flip the block back to git-owned, and the next manifest
	 * change would silently overwrite the client's edits.
	 *
	 * Ownership is re-derived from the pre-write content and hashes, so this
	 * needs no side channel from serialize().
	 */
	public static function writeHashes( int $navId, string $oldContent, string $newContent ): void {
		$oldBlocks = self::extract( $oldContent );
		$oldHashes = self::storedHashes( $navId );

		$next = [];
		foreach ( self::extract( $newContent ) as $i => $block ) {
			$next[] = self::gitOwns( $oldBlocks[ $i ] ?? null, (string) ( $oldHashes[ $i ] ?? '' ) )
				? self::hash( $block )
				: (string) ( $oldHashes[ $i ] ?? '' );
		}

		if ( [] === $next ) {
			delete_post_meta( $navId, Meta::MEGA_HASH );
			return;
		}
		update_post_meta( $navId, Meta::MEGA_HASH, wp_json_encode( $next ) );
	}
}
```

- [ ] **Step 4: Run to verify they pass**

Run: `npx wp-env run tests-wordpress --env-cwd=wp-content/plugins/pediment-ai ./vendor/bin/phpunit --filter MegaBlocksTest`
Expected: PASS.

- [ ] **Step 5: Lint and commit**

```bash
composer lint -d plugin
git add plugin/src/Seeder/Meta.php plugin/src/Seeder/MegaBlocks.php plugin/tests/phpunit/Seeder/MegaBlocksTest.php
git commit -m "feat(seeder): MegaBlocks extraction and per-position hash arbitration"
```

---

### Task 3: `NavSeeder` serializes mega items from the manifest

**Files:**
- Modify: `plugin/src/Seeder/NavSeeder.php` (`serialize()`, new private `megaMarkup()`, `unresolvedItem()`)
- Test: `plugin/tests/phpunit/Seeder/NavSeederTest.php`

**Interfaces:**
- Consumes: the validated `mega` item shape from Task 1; existing `linkAttrs( array $item, string $language, array $entryIds ): array` (returns `[]` for an unresolved entry; resolves label → post title, url → permalink per language).
- Produces: `megaMarkup( array $mega, string $language, array $entryIds ): string` — one self-closing block comment; used again by Task 4's arbitration branch. `unresolvedItem()` now also reports entries inside mega columns.

- [ ] **Step 1: Write the failing tests**

Add to `plugin/tests/phpunit/Seeder/NavSeederTest.php`. Also add `use Pediment\Seeder\MegaBlocks;` to the file's imports (used from Task 4 on; harmless now).

```php
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
		$seeder->apply( $seeder->plan( $m, $ids ), $m, $ids );

		$this->assertStringNotContainsString( '"description":"Who we are"', $markup, 'the unresolved link is dropped' );
		$this->assertStringContainsString( '"label":"Savings"', $markup, 'resolvable links stay' );
		$this->assertNotEmpty( $seeder->errors() );
		$this->assertStringContainsString( 'about', $seeder->errors()[0] );
	}
```

- [ ] **Step 2: Run to verify they fail**

Run: `npx wp-env run tests-wordpress --env-cwd=wp-content/plugins/pediment-ai ./vendor/bin/phpunit --filter NavSeederTest`
Expected: the three new tests FAIL — `serialize()` currently treats a `mega` item as a link item with no url/label (PHP warnings / missing markup), and `apply()` reports nothing for `about`.

- [ ] **Step 3: Implement**

In `plugin/src/Seeder/NavSeeder.php`:

**(a)** In `serialize()`'s item loop, directly after `$item = (array) $item;` and before the `language_switcher` branch:

```php
			// A mega item is one self-closing block; its links are JSON
			// attributes, not nested navigation-link blocks, which is why
			// countLinks() and plan()'s needles both count it as 1.
			if ( isset( $item['mega'] ) ) {
				$blocks[] = $this->megaMarkup( (array) $item['mega'], $language, $entryIds );
				continue;
			}
```

**(b)** Add the new private method below `linkAttrs()`:

```php
	/**
	 * One `pediment/mega-menu` block from a manifest mega item.
	 *
	 * Key order is load-bearing for the same reason as linkAttrs(): label,
	 * columns; per column heading, icon, links; per link label, description,
	 * url — optional keys are omitted, never emitted empty. Entry links
	 * resolve through linkAttrs() (per-language permalink, label falls back
	 * to the post title); the resolver's id/kind/type keys are dropped
	 * because the block schema does not know them.
	 *
	 * @param array<string,mixed> $mega
	 * @param array<string,int>   $entryIds
	 */
	private function megaMarkup( array $mega, string $language, array $entryIds ): string {
		$columns = [];

		foreach ( (array) $mega['columns'] as $column ) {
			$column = (array) $column;
			$links  = [];

			foreach ( (array) $column['links'] as $link ) {
				$link  = (array) $link;
				$attrs = $this->linkAttrs( $link, $language, $entryIds );
				// Same contract as the top-level items: an unresolved entry is
				// dropped here and reported by apply() via unresolvedEntries(),
				// which also refuses to write the shortened menu.
				if ( [] === $attrs ) {
					continue;
				}

				$out = [ 'label' => (string) $attrs['label'] ];
				if ( isset( $link['description'] ) ) {
					$out['description'] = (string) $link['description'];
				}
				$out['url'] = (string) $attrs['url'];
				$links[]    = $out;
			}

			$col = [ 'heading' => (string) $column['heading'] ];
			if ( isset( $column['icon'] ) ) {
				$col['icon'] = (string) $column['icon'];
			}
			$col['links'] = $links;
			$columns[]    = $col;
		}

		$attrs = [
			'label'   => (string) $mega['label'],
			'columns' => $columns,
		];

		return '<!-- wp:pediment/mega-menu ' . wp_json_encode( $attrs, JSON_UNESCAPED_SLASHES ) . ' /-->';
	}
```

**(c)** In `unresolvedItem()`, after the existing `children` loop:

```php
		foreach ( (array) ( ( (array) ( $item['mega'] ?? [] ) )['columns'] ?? [] ) as $column ) {
			foreach ( (array) ( ( (array) $column )['links'] ?? [] ) as $link ) {
				$missing = array_merge( $missing, $this->unresolvedItem( (array) $link, $language, $entryIds ) );
			}
		}
```

- [ ] **Step 4: Run to verify they pass**

Run: `npx wp-env run tests-wordpress --env-cwd=wp-content/plugins/pediment-ai ./vendor/bin/phpunit --filter NavSeederTest`
Expected: PASS (all, including the pre-existing tests).

- [ ] **Step 5: Lint and commit**

```bash
composer lint -d plugin
git add plugin/src/Seeder/NavSeeder.php plugin/tests/phpunit/Seeder/NavSeederTest.php
git commit -m "feat(seeder): serialize manifest mega items as pediment/mega-menu blocks"
```

---

### Task 4: Arbitration in `serialize()` + `plan()` wiring

**Files:**
- Modify: `plugin/src/Seeder/NavSeeder.php` (`serialize()` signature, `plan()`, new private `hasMega()`, `countLinks()` docblock)
- Test: `plugin/tests/phpunit/Seeder/NavSeederTest.php`

**Interfaces:**
- Consumes: `MegaBlocks::extract()`, `MegaBlocks::storedHashes()`, `MegaBlocks::gitOwns()` (Task 2); `megaMarkup()` (Task 3).
- Produces: `serialize( NavSpec $spec, string $language, array $entryIds, string $current = '', int $navId = 0 ): string` — the signature Tasks 5–6 call with the stored content. Existing callers keep compiling via the defaults.

- [ ] **Step 1: Write the failing tests**

Add to `NavSeederTest.php`:

```php
	public function test_an_editor_edited_mega_block_is_preserved() {
		$seeder = new NavSeeder( new NullProvider() );
		$m      = $this->manifest( $this->megaItems() );
		$ids    = [
			'home|'  => self::factory()->post->create( [ 'post_type' => 'page' ] ),
			'about|' => self::factory()->post->create( [ 'post_type' => 'page', 'post_title' => 'About' ] ),
		];
		$navId  = $seeder->apply( $seeder->plan( $m, $ids ), $m, $ids )['primary|'];

		$edited = str_replace( '"label":"Savings"', '"label":"ISA"', get_post( $navId )->post_content );
		wp_update_post( [ 'ID' => $navId, 'post_content' => wp_slash( $edited ) ] );

		$plan = $seeder->plan( $m, $ids );
		$seeder->apply( $plan, $m, $ids );

		$this->assertSame( PlanItem::UNCHANGED, $plan->items()[0]->action, 'the edit belongs to the client' );
		$this->assertStringContainsString( '"label":"ISA"', get_post( $navId )->post_content );
	}

	public function test_a_deleted_mega_block_is_reseeded_from_the_manifest() {
		$seeder = new NavSeeder( new NullProvider() );
		$m      = $this->manifest( $this->megaItems() );
		$ids    = [
			'home|'  => self::factory()->post->create( [ 'post_type' => 'page' ] ),
			'about|' => self::factory()->post->create( [ 'post_type' => 'page', 'post_title' => 'About' ] ),
		];
		$navId  = $seeder->apply( $seeder->plan( $m, $ids ), $m, $ids )['primary|'];

		$block   = MegaBlocks::extract( get_post( $navId )->post_content )[0];
		$without = trim( str_replace( $block, '', get_post( $navId )->post_content ) );
		wp_update_post( [ 'ID' => $navId, 'post_content' => wp_slash( $without ) ] );

		$plan = $seeder->plan( $m, $ids );
		$seeder->apply( $plan, $m, $ids );

		$this->assertSame( PlanItem::UPDATE, $plan->items()[0]->action, 'membership is git-owned — the block comes back' );
		$this->assertStringContainsString( 'wp:pediment/mega-menu', get_post( $navId )->post_content );
	}

	public function test_a_legacy_hand_built_mega_is_preserved_on_first_seed() {
		// A claimed nav carries a seed key but no hash meta — exactly what
		// Claimer leaves behind. Its mega content must survive the first seed,
		// like a claimed page's content does.
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

		$seeder->apply( $seeder->plan( $m, $ids ), $m, $ids );

		$content = get_post( $navId )->post_content;
		$this->assertStringContainsString( '"label":"Hand-built"', $content, 'the hand-built block is spliced through verbatim' );
		$this->assertStringContainsString( 'wp:navigation-link', $content, 'membership still comes from the manifest' );
	}

	public function test_the_items_tally_counts_a_mega_item_as_one_on_both_sides() {
		$seeder = new NavSeeder( new NullProvider() );
		$ids    = [
			'home|'  => self::factory()->post->create( [ 'post_type' => 'page' ] ),
			'about|' => self::factory()->post->create( [ 'post_type' => 'page', 'post_title' => 'About' ] ),
		];
		$first  = $this->manifest( [ [ 'entry' => 'home' ] ] );
		$seeder->apply( $seeder->plan( $first, $ids ), $first, $ids );

		$mega = $this->manifest( $this->megaItems() );
		$grow = $seeder->plan( $mega, $ids );
		$this->assertSame( [ 'from' => 1, 'to' => 2 ], $grow->items()[0]->changes['items'] );

		$seeder->apply( $grow, $mega, $ids );
		$shrink = $seeder->plan( $first, $ids );
		$this->assertSame( [ 'from' => 2, 'to' => 1 ], $shrink->items()[0]->changes['items'], 'the stored mega block is counted by its own needle' );
	}

	public function test_the_plan_note_names_the_mega_ownership_split() {
		$seeder = new NavSeeder( new NullProvider() );
		$ids    = [
			'home|'  => self::factory()->post->create( [ 'post_type' => 'page' ] ),
			'about|' => self::factory()->post->create( [ 'post_type' => 'page', 'post_title' => 'About' ] ),
		];
		$first  = $this->manifest( [ [ 'entry' => 'home' ] ] );
		$seeder->apply( $seeder->plan( $first, $ids ), $first, $ids );

		$plan = $seeder->plan( $this->manifest( $this->megaItems() ), $ids );

		$this->assertSame( 'membership is git-owned; mega menu content is kept once edited in the editor', $plan->items()[0]->note );
	}
```

Note: `wp_update_post()` in these tests runs with KSES active (no logged-in user), which is fine — the edited JSON here contains no escape sequences for KSES to strip. If a future variant trips KSES, wrap the write in `kses_remove_filters()` / `kses_init_filters()` instead of weakening the assertion.

- [ ] **Step 2: Run to verify they fail**

Run: `npx wp-env run tests-wordpress --env-cwd=wp-content/plugins/pediment-ai ./vendor/bin/phpunit --filter NavSeederTest`
Expected: the five new tests FAIL — the edited/hand-built blocks are overwritten (plan says UPDATE and rewrites from the manifest), and the note/tally don't mention mega.

- [ ] **Step 3: Implement**

In `plugin/src/Seeder/NavSeeder.php`:

**(a)** Extend `serialize()` — new signature and arbitration branch replacing Task 3's simple emission:

```php
	/** @param array<string,int> $entryIds */
	public function serialize( NavSpec $spec, string $language, array $entryIds, string $current = '', int $navId = 0 ): string {
		$blocks     = [];
		$storedMega = MegaBlocks::extract( $current );
		$megaHashes = 0 < $navId ? MegaBlocks::storedHashes( $navId ) : [];
		$megaIndex  = 0;

		foreach ( $spec->items as $item ) {
			$item = (array) $item;

			// Membership is git-owned, content is hash-arbitrated: the block
			// always (re)appears where the manifest says, but once a human
			// edits it in the editor its stored markup stops hashing to what
			// the seeder last wrote, and from then on it is spliced through
			// verbatim. Matching is positional: nth mega item, nth stored
			// block. See docs/seeding.md ("Mega menus").
			if ( isset( $item['mega'] ) ) {
				$stored   = $storedMega[ $megaIndex ] ?? null;
				$blocks[] = null !== $stored && ! MegaBlocks::gitOwns( $stored, (string) ( $megaHashes[ $megaIndex ] ?? '' ) )
					? $stored
					: $this->megaMarkup( (array) $item['mega'], $language, $entryIds );
				++$megaIndex;
				continue;
			}
```

(The rest of the loop — switcher, link, submenu — is unchanged.)

**(b)** In `plan()`: move the `$desired = $this->serialize( … )` line from before the CREATE branch to after the trash branch, so it can take the stored content (CREATE never used `$desired`):

```php
				$mapKey = $key . '|' . $language;
				$postId = (int) ( $existing[ $mapKey ] ?? 0 );

				if ( 0 === $postId ) {
					// … existing CREATE item, unchanged …
					continue;
				}

				// … existing trash/RESTORE branch, unchanged …

				$current = (string) get_post( $postId )->post_content;
				$desired = $this->serialize( $spec, $language, $entryIds, $current, $postId );
```

and extend the UPDATE item's tally and note:

```php
							'items' => [
								// The `<!-- ` prefix on the submenu needle is deliberate: the
								// closing delimiter is `<!-- /wp:navigation-submenu -->`, and a
								// bare needle would match it too and double every submenu.
								// mega-menu is self-closing, so its needle has no such twin;
								// the full prefix is used for consistency.
								'from' => substr_count( $current, 'wp:navigation-link' )
									+ substr_count( $current, '<!-- wp:navigation-submenu' )
									+ substr_count( $current, '<!-- wp:pediment/mega-menu' ),
								'to'   => self::countLinks( $spec->items ),
							],
						],
						[],
						self::hasMega( $spec )
							? 'membership is git-owned; mega menu content is kept once edited in the editor'
							: 'membership is git-owned; editor changes to this menu are reverted'
```

**(c)** Add the helper near `countLinks()`:

```php
	/** Whether any of the spec's items declares a mega menu. */
	private static function hasMega( NavSpec $spec ): bool {
		foreach ( $spec->items as $item ) {
			if ( isset( ( (array) $item )['mega'] ) ) {
				return true;
			}
		}
		return false;
	}
```

**(d)** Append one line to `countLinks()`'s docblock: `A mega item counts as 1: its links are JSON attributes, invisible to the needles plan() counts on the stored side.` (No code change — a mega item has no `children`, so the existing `++$count` already counts it as 1.)

- [ ] **Step 4: Run to verify they pass**

Run: `npx wp-env run tests-wordpress --env-cwd=wp-content/plugins/pediment-ai ./vendor/bin/phpunit --filter NavSeederTest`
Expected: PASS. (`test_an_editor_edited_mega_block_is_preserved` passes even before Task 5: with no hashes stored, every stored block is treated as client-owned, which is the same regime.)

- [ ] **Step 5: Run the full seeder suite to catch regressions**

Run: `npx wp-env run tests-wordpress --env-cwd=wp-content/plugins/pediment-ai ./vendor/bin/phpunit`
Expected: PASS — in particular the pre-existing `RunnerTest`/`VerifierTest`/`ClaimRunnerTest` (they call `serialize()` through its old arity, which the defaults keep valid).

- [ ] **Step 6: Lint and commit**

```bash
composer lint -d plugin
git add plugin/src/Seeder/NavSeeder.php plugin/tests/phpunit/Seeder/NavSeederTest.php
git commit -m "feat(seeder): hash-arbitrated splice keeps editor-owned mega content"
```

---

### Task 5: `apply()` hash bookkeeping

**Files:**
- Modify: `plugin/src/Seeder/NavSeeder.php` (`apply()`)
- Test: `plugin/tests/phpunit/Seeder/NavSeederTest.php`

**Interfaces:**
- Consumes: `MegaBlocks::writeHashes( int $navId, string $oldContent, string $newContent )` (Task 2); `serialize()` with `$current`/`$navId` (Task 4).
- Produces: after every nav write, `Meta::MEGA_HASH` holds one hash per mega position (fresh for git-owned positions, carried forward for client-owned ones); no meta for mega-free navs.

- [ ] **Step 1: Write the failing tests**

Add to `NavSeederTest.php`:

```php
	public function test_seeding_writes_one_hash_per_mega_position() {
		$seeder = new NavSeeder( new NullProvider() );
		$m      = $this->manifest( $this->megaItems() );
		$ids    = [
			'home|'  => self::factory()->post->create( [ 'post_type' => 'page' ] ),
			'about|' => self::factory()->post->create( [ 'post_type' => 'page', 'post_title' => 'About' ] ),
		];

		$navId  = $seeder->apply( $seeder->plan( $m, $ids ), $m, $ids )['primary|'];
		$hashes = MegaBlocks::storedHashes( $navId );

		$this->assertCount( 1, $hashes );
		$this->assertTrue( MegaBlocks::gitOwns( MegaBlocks::extract( get_post( $navId )->post_content )[0], $hashes[0] ) );
	}

	public function test_a_manifest_change_to_an_untouched_mega_applies_and_rehashes() {
		$seeder = new NavSeeder( new NullProvider() );
		$ids    = [
			'home|'  => self::factory()->post->create( [ 'post_type' => 'page' ] ),
			'about|' => self::factory()->post->create( [ 'post_type' => 'page', 'post_title' => 'About' ] ),
		];
		$first  = $this->manifest( $this->megaItems( 'Products' ) );
		$navId  = $seeder->apply( $seeder->plan( $first, $ids ), $first, $ids )['primary|'];

		$second = $this->manifest( $this->megaItems( 'Solutions' ) );
		$plan   = $seeder->plan( $second, $ids );
		$seeder->apply( $plan, $second, $ids );

		$this->assertSame( PlanItem::UPDATE, $plan->items()[0]->action, 'git still owns an untouched block' );
		$this->assertStringContainsString( '"label":"Solutions"', get_post( $navId )->post_content );
		$this->assertSame( PlanItem::UNCHANGED, $seeder->plan( $second, $ids )->items()[0]->action, 'the fresh hash matches again' );
	}

	public function test_a_manifest_change_to_an_edited_mega_is_not_applied() {
		$seeder = new NavSeeder( new NullProvider() );
		$ids    = [
			'home|'  => self::factory()->post->create( [ 'post_type' => 'page' ] ),
			'about|' => self::factory()->post->create( [ 'post_type' => 'page', 'post_title' => 'About' ] ),
		];
		$first  = $this->manifest( $this->megaItems( 'Products' ) );
		$navId  = $seeder->apply( $seeder->plan( $first, $ids ), $first, $ids )['primary|'];

		$edited = str_replace( '"label":"Savings"', '"label":"ISA"', get_post( $navId )->post_content );
		wp_update_post( [ 'ID' => $navId, 'post_content' => wp_slash( $edited ) ] );

		$second = $this->manifest( $this->megaItems( 'Solutions' ) );
		$plan   = $seeder->plan( $second, $ids );
		$seeder->apply( $plan, $second, $ids );

		$this->assertSame( PlanItem::UNCHANGED, $plan->items()[0]->action, 'the client owns the block; the manifest change does not apply' );
		$this->assertStringContainsString( '"label":"ISA"', get_post( $navId )->post_content );
	}

	public function test_a_membership_rewrite_keeps_an_edited_mega_client_owned() {
		$seeder = new NavSeeder( new NullProvider() );
		$ids    = [
			'home|'  => self::factory()->post->create( [ 'post_type' => 'page' ] ),
			'about|' => self::factory()->post->create( [ 'post_type' => 'page', 'post_title' => 'About' ] ),
		];
		$first  = $this->manifest( $this->megaItems( 'Products' ) );
		$navId  = $seeder->apply( $seeder->plan( $first, $ids ), $first, $ids )['primary|'];

		$edited = str_replace( '"label":"Savings"', '"label":"ISA"', get_post( $navId )->post_content );
		wp_update_post( [ 'ID' => $navId, 'post_content' => wp_slash( $edited ) ] );

		// A membership change (extra item) rewrites the nav around the block.
		$wider = $this->manifest( array_merge( [ [ 'entry' => 'about' ] ], $this->megaItems( 'Products' ) ) );
		$plan  = $seeder->plan( $wider, $ids );
		$seeder->apply( $plan, $wider, $ids );

		$this->assertSame( PlanItem::UPDATE, $plan->items()[0]->action );
		$this->assertStringContainsString( '"label":"ISA"', get_post( $navId )->post_content, 'the edit survives the rewrite' );

		// The block must STILL be client-owned afterwards: a later manifest
		// change to the mega spec does not apply. Without the hash
		// carry-forward this is the case that silently reverts client edits.
		$widerChanged = $this->manifest( array_merge( [ [ 'entry' => 'about' ] ], $this->megaItems( 'Solutions' ) ) );
		$seeder->apply( $seeder->plan( $widerChanged, $ids ), $widerChanged, $ids );
		$this->assertStringContainsString( '"label":"ISA"', get_post( $navId )->post_content );
	}

	public function test_a_reseeded_mega_after_deletion_returns_to_git_ownership() {
		$seeder = new NavSeeder( new NullProvider() );
		$ids    = [
			'home|'  => self::factory()->post->create( [ 'post_type' => 'page' ] ),
			'about|' => self::factory()->post->create( [ 'post_type' => 'page', 'post_title' => 'About' ] ),
		];
		$first  = $this->manifest( $this->megaItems( 'Products' ) );
		$navId  = $seeder->apply( $seeder->plan( $first, $ids ), $first, $ids )['primary|'];

		$block   = MegaBlocks::extract( get_post( $navId )->post_content )[0];
		$without = trim( str_replace( $block, '', get_post( $navId )->post_content ) );
		wp_update_post( [ 'ID' => $navId, 'post_content' => wp_slash( $without ) ] );
		$seeder->apply( $seeder->plan( $first, $ids ), $first, $ids );

		// Delete-and-reseed is the documented way to re-assert git.
		$second = $this->manifest( $this->megaItems( 'Solutions' ) );
		$seeder->apply( $seeder->plan( $second, $ids ), $second, $ids );

		$this->assertStringContainsString( '"label":"Solutions"', get_post( $navId )->post_content );
	}

	public function test_no_hash_meta_is_written_for_mega_free_navs() {
		$seeder = new NavSeeder( new NullProvider() );
		$m      = $this->manifest( [ [ 'entry' => 'home' ] ] );
		$ids    = [ 'home|' => self::factory()->post->create( [ 'post_type' => 'page' ] ) ];

		$navId = $seeder->apply( $seeder->plan( $m, $ids ), $m, $ids )['primary|'];

		$this->assertSame( '', (string) get_post_meta( $navId, Meta::MEGA_HASH, true ) );
	}
```

- [ ] **Step 2: Run to verify they fail**

Run: `npx wp-env run tests-wordpress --env-cwd=wp-content/plugins/pediment-ai ./vendor/bin/phpunit --filter NavSeederTest`
Expected: `test_seeding_writes_one_hash_per_mega_position`, `test_a_manifest_change_to_an_untouched_mega_applies_and_rehashes`, and `test_a_reseeded_mega_after_deletion_returns_to_git_ownership` FAIL (no hashes are written yet, so every stored block is treated as client-owned and manifest changes never apply). The others pass already — keep them; they pin the client-owned side of the matrix against regressions from this task.

- [ ] **Step 3: Implement**

In `NavSeeder::apply()`, restructure the write branches so each computes its own content (RESTORE/UPDATE need the pre-write content) and records hashes after a successful write. Replace the block from the current `$content = $this->serialize( … );` line through the end of the UPDATE branch with:

```php
				if ( PlanItem::CREATE === $item->action ) {
					$content = $this->serialize( $spec, $item->language, $entryIds );
					$postId  = wp_insert_post(
						wp_slash(
							[
								'post_type'    => 'wp_navigation',
								'post_status'  => 'publish',
								'post_title'   => $spec->title,
								'post_name'    => $this->slugFor( $spec, $item->language ),
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
					MegaBlocks::writeHashes( $postId, '', $content );
				} elseif ( PlanItem::RESTORE === $item->action ) {
					$postId  = $item->postId;
					$old     = (string) get_post( $postId )->post_content;
					$content = $this->serialize( $spec, $item->language, $entryIds, $old, $postId );
					// The slug is rewritten too: wp_trash_post() renames it to
					// `primary__trashed`, and leaving that behind is what a later
					// create would collide with.
					$restored = wp_update_post(
						wp_slash(
							[
								'ID'           => $postId,
								'post_status'  => 'publish',
								'post_name'    => $this->slugFor( $spec, $item->language ),
								'post_content' => $content,
							]
						),
						true
					);
					if ( is_wp_error( $restored ) ) {
						$this->errors[] = sprintf( 'navs.%s: could not restore the navigation entity %d — %s', $spec->key, $postId, $restored->get_error_message() );
						continue;
					}
					Meta::clearTrashBookkeeping( $postId );
					MegaBlocks::writeHashes( $postId, $old, $content );
				} else {
					$postId  = $item->postId;
					$old     = (string) get_post( $postId )->post_content;
					$content = $this->serialize( $spec, $item->language, $entryIds, $old, $postId );
					$updated = wp_update_post( wp_slash( [ 'ID' => $postId, 'post_content' => $content ] ), true );
					if ( is_wp_error( $updated ) ) {
						$this->errors[] = sprintf( 'navs.%s: could not update the navigation entity — %s', $spec->key, $updated->get_error_message() );
						continue;
					}
					MegaBlocks::writeHashes( $postId, $old, $content );
				}
```

(This deliberately passes the trashed entity's own content into RESTORE's `serialize()`: a client-owned mega block survives a trash/restore cycle the same way it survives any other rewrite.)

- [ ] **Step 4: Run to verify they pass**

Run: `npx wp-env run tests-wordpress --env-cwd=wp-content/plugins/pediment-ai ./vendor/bin/phpunit --filter NavSeederTest`
Expected: PASS (all).

- [ ] **Step 5: Lint and commit**

```bash
composer lint -d plugin
git add plugin/src/Seeder/NavSeeder.php plugin/tests/phpunit/Seeder/NavSeederTest.php
git commit -m "feat(seeder): record and carry forward mega ownership hashes on apply"
```

---

### Task 6: Verifier passes the stored content through

**Files:**
- Modify: `plugin/src/Seeder/Verifier.php` (~line 149, the nav membership comparison)
- Test: `plugin/tests/phpunit/Seeder/VerifierTest.php`

**Interfaces:**
- Consumes: `serialize( $spec, $language, $ids, string $current, int $navId )` (Task 4).
- Produces: no interface change — `verify()`'s signature is untouched.

- [ ] **Step 1: Write the failing test**

Add to `plugin/tests/phpunit/Seeder/VerifierTest.php` (the file already imports `NullProvider`, `Manifest`, `Meta`, `MediaMap`, `NavSeeder`, `Verifier` and has the `desired()` helper):

```php
	public function test_an_edited_mega_block_passes_verification() {
		$seeder = new NavSeeder( new NullProvider() );
		$m      = Manifest::fromArray(
			[
				'pages' => [ 'home' => [ 'title' => 'Home', 'content' => '<p>h</p>' ] ],
				'navs'  => [
					'primary' => [
						'title' => 'Primary',
						'items' => [
							[ 'url' => '/features/', 'label' => 'Features' ],
							[ 'mega' => [ 'label' => 'Products', 'columns' => [ [ 'heading' => 'H', 'links' => [ [ 'label' => 'L', 'url' => '/l/' ] ] ] ] ] ],
						],
					],
				],
			],
			'/tmp/theme'
		);

		$navIds = $seeder->apply( $seeder->plan( $m, [] ), $m, [] );
		$navId  = $navIds['primary|'];
		$edited = str_replace( '"label":"L"', '"label":"Edited"', get_post( $navId )->post_content );
		wp_update_post( [ 'ID' => $navId, 'post_content' => wp_slash( $edited ) ] );

		$id = self::factory()->post->create( [ 'post_type' => 'page', 'post_name' => 'home', 'post_title' => 'Home' ] );
		update_post_meta( $id, Meta::KEY, 'home' );

		$problems = ( new Verifier( new NullProvider(), $seeder ) )->verify( $m, $this->desired( $m ), [ 'home|' => $id ], new MediaMap( [] ), $navIds );

		$this->assertSame( [], $problems, 'a client-owned mega block is not a membership mismatch' );
	}
```

- [ ] **Step 2: Run to verify it fails**

Run: `npx wp-env run tests-wordpress --env-cwd=wp-content/plugins/pediment-ai ./vendor/bin/phpunit --filter VerifierTest`
Expected: the new test FAILS with `navs.primary|: stored membership does not match the manifest.` (the Verifier still serializes without the stored content, so the edited block reads as a mismatch).

- [ ] **Step 3: Implement**

In `plugin/src/Seeder/Verifier.php`, change the nav comparison line:

```php
				if ( (string) $nav->post_content !== $this->navSeeder->serialize( $spec, $language, $ids, (string) $nav->post_content, $navId ) ) {
					$problems[] = sprintf( 'navs.%s: stored membership does not match the manifest.', $mapKey );
				}
```

- [ ] **Step 4: Run to verify it passes**

Run: `npx wp-env run tests-wordpress --env-cwd=wp-content/plugins/pediment-ai ./vendor/bin/phpunit --filter VerifierTest`
Expected: PASS (all).

- [ ] **Step 5: Lint and commit**

```bash
composer lint -d plugin
git add plugin/src/Seeder/Verifier.php plugin/tests/phpunit/Seeder/VerifierTest.php
git commit -m "fix(seeder): verifier honors editor-owned mega content"
```

---

### Task 7: KSES round-trip, zero-mega golden, per-language resolution

**Files:**
- Test: `plugin/tests/phpunit/Seeder/NavSeederTest.php`
- Test: `plugin/tests/polylang/NavLanguageTest.php` (tests only — these lock guarantees; no production code should need to change)

**Interfaces:**
- Consumes: everything from Tasks 3–5. `NavLanguageTest` already provides `private function page( string $language ): int` (creates a page titled `About <language>`, tags it via `pll_set_post_language()`, sets the `about` seed key).

- [ ] **Step 1: Write the tests**

```php
	public function test_a_saved_mega_nav_round_trips_byte_identical() {
		// The nav-side version of the slashes trap, for nested JSON full of
		// client copy: if any byte shifts on save, the stored markup never
		// matches a fresh serialize() and the nav rewrites on every run.
		$seeder = new NavSeeder( new NullProvider() );
		$m      = $this->manifest(
			[
				[
					'mega' => [
						'label'   => 'Zoë & Sons — "Products"',
						'columns' => [
							[
								'heading' => 'Straße & Co',
								'links'   => [
									[ 'label' => 'A/B', 'url' => '/a/b/c/', 'description' => 'Ünïcode, "quotes" & ampersands' ],
								],
							],
						],
					],
				],
			]
		);

		$navId = $seeder->apply( $seeder->plan( $m, [] ), $m, [] )['primary|'];

		$this->assertSame( PlanItem::UNCHANGED, $seeder->plan( $m, [] )->items()[0]->action );
		$this->assertNotSame( '', get_post( $navId )->post_content );
	}

	public function test_a_mega_free_manifest_serializes_exactly_as_before() {
		// Locks the no-rewrite-once guarantee: byte-identical output for
		// manifests that never mention mega, so upgraded sites plan UNCHANGED.
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
				'<!-- wp:polylang/navigation-language-switcher {"dropdown":true} /-->',
			]
		);

		$this->assertSame( $expected, $seeder->serialize( $m->navs()['primary'], '', $ids ) );

		$navIds = $seeder->apply( $seeder->plan( $m, $ids ), $m, $ids );
		$this->assertSame( '', (string) get_post_meta( $navIds['primary|'], Meta::MEGA_HASH, true ) );
	}
```

- [ ] **Step 2: Run — both should pass immediately**

Run: `npx wp-env run tests-wordpress --env-cwd=wp-content/plugins/pediment-ai ./vendor/bin/phpunit --filter NavSeederTest`
Expected: PASS. These tests are locks, not drivers — if either fails, STOP: a real guarantee broke in Tasks 3–5 (most likely a key-order or escaping drift). Fix the production code, never the expected strings.

- [ ] **Step 3: Add the per-language resolution test to the Polylang suite**

Add to `plugin/tests/polylang/NavLanguageTest.php` (inside the existing class):

```php
	public function test_a_mega_link_resolves_per_language() {
		$manifest = Manifest::fromArray(
			[
				'languages' => [ 'en' => [ 'name' => 'English', 'locale' => 'en_US', 'default' => true ], 'de' => [ 'name' => 'Deutsch', 'locale' => 'de_DE' ] ],
				'pages'     => [ 'about' => [ 'title' => 'About', 'content' => '' ] ],
				'navs'      => [
					'primary' => [
						'title' => 'Primary',
						'items' => [
							[
								'mega' => [
									'label'   => 'Products',
									'columns' => [ [ 'heading' => 'H', 'links' => [ [ 'entry' => 'about', 'description' => 'D' ] ] ] ],
								],
							],
						],
					],
				],
			],
			get_stylesheet_directory()
		);

		$lang     = new PolylangProvider();
		$seeder   = new NavSeeder( $lang );
		$en       = $this->page( 'en' );
		$de       = $this->page( 'de' );
		$entryIds = [ 'about|en' => $en, 'about|de' => $de ];

		$ids = $seeder->apply( $seeder->plan( $manifest, $entryIds ), $manifest, $entryIds );

		$this->assertSame( [], $seeder->errors() );
		$deContent = get_post( $ids['primary|de'] )->post_content;
		$this->assertStringContainsString( '"label":"About de"', $deContent, 'the mega link label falls back to the per-language post title' );
		$this->assertStringContainsString( '"url":"' . get_permalink( $de ) . '"', $deContent );
		$this->assertStringNotContainsString( '"url":"' . get_permalink( $en ) . '"', $deContent, 'the full url attribute is compared so ?page_id prefixes cannot collide' );
	}
```

Run: `npx wp-env run tests-wordpress --env-cwd=wp-content/plugins/pediment-ai ./vendor/bin/phpunit -c phpunit-polylang.xml.dist --filter NavLanguageTest`
Expected: PASS immediately (`megaMarkup()` rides `linkAttrs()`, which is already per-language). If it fails, the language plumbing in Tasks 3–4 dropped the `$language` argument somewhere — fix the production code.

- [ ] **Step 4: Run the entire phpunit suite (both configs, as CI does)**

```bash
npx wp-env run tests-wordpress --env-cwd=wp-content/plugins/pediment-ai ./vendor/bin/phpunit
npx wp-env run tests-wordpress --env-cwd=wp-content/plugins/pediment-ai ./vendor/bin/phpunit -c phpunit-polylang.xml.dist
```

Expected: PASS.

- [ ] **Step 5: Lint and commit**

```bash
composer lint -d plugin
git add plugin/tests/phpunit/Seeder/NavSeederTest.php plugin/tests/polylang/NavLanguageTest.php
git commit -m "test(seeder): lock mega KSES round-trip, zero-mega byte identity, per-language links"
```

---

### Task 8: Documentation

**Files:**
- Modify: `docs/seeding.md`

- [ ] **Step 1: Add the "Mega menus" grammar section**

In `docs/seeding.md`, directly after the `#### Submenus` section (which ends around line 180), add:

````markdown
#### Mega menus

An item may declare `mega`, which serializes as a self-closing
`pediment/mega-menu` block — the panel of icon-and-description link columns
the header renders for it:

```php
array(
	'mega' => array(
		'label'   => 'Products',
		'columns' => array(
			array(
				'heading' => 'Banking',
				'icon'    => 'bank', // optional pediment icon slug
				'links'   => array(
					array( 'entry' => 'features', 'description' => 'Everyday account' ),
					array( 'label' => 'Savings', 'url' => '/savings/', 'description' => 'Earn more' ),
				),
			),
		),
	),
),
```

Each link follows the same rule as a top-level item — `entry` (resolved to the
live per-language permalink, label falling back to the post title) or
`url` + `label` — plus an optional `description`. A `mega` item is a leaf: it
may not declare `children`, may not appear inside `children`, and may not
combine with the other item types. All of it is validated at parse time with
exact paths (`navs.primary.items.1.mega.columns.0.links.2`).

Ownership is split, and this is the one place nav seeding deviates from
"membership is git-owned; editor changes are reverted":

- **Membership is still git-owned.** Where the mega menu sits, and that it
  exists at all, comes from the manifest. Deleting the block in the editor
  brings it back on the next seed; adding a second one removes it.
- **Content is hash-arbitrated, exactly like page content** ("The two hashes"
  above, scoped to one block). The seeder stores a per-position hash of each
  mega block it writes (`_pediment_seed_mega_hash` on the nav entity). While
  the stored block still matches, manifest changes flow through on re-seed.
  The moment someone edits the block in the Site Editor the hash stops
  matching, and from then on the seeder splices the stored block through
  verbatim — the client's edits win, on every future run.
- **Re-asserting git** = delete the block in the editor and re-seed:
  membership re-creates it from the manifest and re-hashes it.

A claimed legacy nav carries no hash, so a hand-built mega menu survives its
first seed — the same "a claimed row's very first seed is safe" property pages
have. Matching between manifest mega items and stored blocks is positional
(nth item ↔ nth block): swapping two mega items within one nav transfers
their edit-ownership, so treat multi-mega navs with care.
````

- [ ] **Step 2: Scope the two "navs are fully git-owned" statements**

Both currently state the pre-mega contract absolutely; each needs a pointer, not a rewrite:

1. The blockquote around line 731 ("There is no hash arbitration for navs at all: menu membership is git-owned…") — append to it: `The one exception is mega-menu *content*, which is hash-arbitrated per block — see "Mega menus" under `navs`.`
2. The bullet around line 1006 ("**Nav membership is git-owned, not client-editable.**") — append: `Mega-menu *content* is the exception: it is hash-arbitrated like page content, so a client's edits to a seeded mega menu persist ("Mega menus" above).`

- [ ] **Step 3: Commit**

```bash
git add docs/seeding.md
git commit -m "docs(seeding): mega item grammar and split ownership model"
```

---

## Final verification (after Task 8)

Run the full CI-equivalent locally, then re-read the spec's Acceptance section and check each line:

```bash
composer lint -d plugin
npx wp-env run tests-wordpress --env-cwd=wp-content/plugins/pediment-ai ./vendor/bin/phpunit
npx wp-env run tests-wordpress --env-cwd=wp-content/plugins/pediment-ai ./vendor/bin/phpunit -c phpunit-polylang.xml.dist
```

- Manifest declares a mega menu → seeded nav renders a `pediment/mega-menu` with resolved links (Tasks 3–5 tests).
- Editor edits persist across seeds; other nav edits still revert (Tasks 4–5 tests).
- Manifest changes to untouched mega re-apply (Task 5).
- Verifier passes in both regimes (Task 6).
- Zero-mega sites: byte-identical serialization, no meta (Task 7).
