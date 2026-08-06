# Nav Submenus (Step 6b, plugin half) Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Let a seed manifest declare a two-level navigation menu, so `NavSeeder` writes `wp:navigation-submenu` blocks instead of flattening every menu to a single level — the one engine gap that blocks Workation's client-theme conversion (`docs/superpowers/specs/2026-08-06-workation-client-theme-step6b-design.md` §1.3, decision 3).

**Architecture:** `NavSpec::$items` gains an optional `children` key. `Manifest::fromArray()` validates items through one recursive helper that permits exactly one level of nesting and applies the identical entry/url rules at both levels. `NavSeeder::serialize()` splits its per-item attribute building into `linkAttrs()`, then emits either a self-closing `wp:navigation-link` or a `wp:navigation-submenu` wrapping its children — attribute order preserved byte-for-byte so existing menus do not all report as changed on the first run after upgrade. `unresolvedEntries()` and the plan's item counter both walk children, so a missing grandchild link still refuses to write a shortened menu.

**Tech Stack:** PHP 8.1, WordPress 6.9, PHPUnit 9.6 (the WP integration suite plus the Polylang suite), `@wordpress/env`.

## Global Constraints

- **Never push without explicit user approval.** All work is local until a gated push at the end.
- Work stays on the current branch in this Conductor workspace. No new branches or worktrees — the workspace *is* the isolation.
- **Nothing existing is removed or renamed**, so this ships as a **minor** — conventional `feat:`/`fix:`/`docs:`/`test:` commits only, no `!`, no `Release-As:` footer. Version files belong to release-please; never hand-bump.
- **Serialized attribute order must not change.** `NavSeeder::plan()` decides UPDATE by comparing the stored `post_content` against a fresh `serialize()` string. Reordering the JSON keys of an existing link makes every nav on every site report as changed and rewrite itself once. The order for an entry item is `label, type, id, kind, url`; for a url item it is `label, url, kind`.
- **`JSON_UNESCAPED_SLASHES` stays on every `wp_json_encode()` call** in the serializer. It matches what the block editor writes and keeps the markup stable under KSES, which strips `\/` on save.
- **Nesting is one level only.** `children` inside `children` is a `ManifestError`, not a silently ignored key.
- **A monolingual site must behave exactly as it does today.** `NullProvider` stays the default; the existing plugin suite plus the Polylang suite are the regression gate.
- **PHPUnit runs in wp-env**, never against a bare PHP: `npx wp-env run tests-wordpress --env-cwd=wp-content/plugins/pediment-ai ./vendor/bin/phpunit`. The in-container directory is still `pediment-ai` — that is the mount name, not a rename target.
- Commit messages: conventional summary of at most 60 characters, with the trailer `Co-Authored-By: Claude Opus 5 (1M context) <noreply@anthropic.com>`. Stage files explicitly by name; never `git add -A`.

---

## File Structure

### Modify

- `plugin/src/Seeder/NavSpec.php` — the `$items` docblock type gains `children`.
- `plugin/src/Seeder/Manifest.php:161-179` — the nav item loop moves into a recursive `navItem()` validator.
- `plugin/src/Seeder/NavSeeder.php` — `serialize()` splits into `serialize()` + `linkAttrs()`; `unresolvedEntries()` splits into `unresolvedEntries()` + `unresolvedItem()`; `plan()` counts links through a new `countLinks()`.
- `plugin/tests/phpunit/Seeder/ManifestTest.php` — validation cases.
- `plugin/tests/phpunit/Seeder/NavSeederTest.php` — serialization, unresolved children, counting.
- `plugin/tests/polylang/NavLanguageTest.php` — a per-language submenu.
- `docs/seeding.md` — the `navs` section.
- `docs/BACKLOG.md`, `docs/SESSION_LOG.md`.

### Interfaces

```php
// plugin/src/Seeder/NavSpec.php — docblock only, no signature change
/** @param array<int,array{entry?:string,url?:string,label?:string,children?:array<int,array{entry?:string,url?:string,label?:string}>}> $items */

// plugin/src/Seeder/Manifest.php — new private static
/**
 * @param array<string,mixed>     $item
 * @param array<string,EntrySpec> $entries
 * @return array<string,mixed>
 */
private static function navItem( array $item, array $entries, string $path, bool $allowChildren ): array;

// plugin/src/Seeder/NavSeeder.php — new privates, public signatures unchanged
/**
 * @param array<string,mixed> $item
 * @param array<string,int>   $entryIds
 * @return array<string,mixed> Empty when an entry item has no resolved post.
 */
private function linkAttrs( array $item, string $language, array $entryIds ): array;

/**
 * @param array<string,mixed> $item
 * @param array<string,int>   $entryIds
 * @return string[]
 */
private function unresolvedItem( array $item, string $language, array $entryIds ): array;

/** @param array<int,array<string,mixed>> $items */
private static function countLinks( array $items ): int;
```

---

### Task 1: The manifest accepts one level of `children`

**Files:**
- Modify: `plugin/src/Seeder/NavSpec.php`
- Modify: `plugin/src/Seeder/Manifest.php:161-179`
- Test: `plugin/tests/phpunit/Seeder/ManifestTest.php`

**Interfaces:**
- Consumes: `Manifest::fromArray()`, `ManifestError`, `EntrySpec`.
- Produces: `Manifest::navItem()`; `NavSpec::$items` entries may now carry a `children` array.

- [ ] **Step 1: Write the failing tests**

Append to `plugin/tests/phpunit/Seeder/ManifestTest.php`, inside the class:

```php
	private function navManifest( array $items ): array {
		return [
			'pages' => [
				'home'  => [ 'title' => 'Home', 'content' => '' ],
				'guide' => [ 'title' => 'Guide', 'content' => '' ],
				'faq'   => [ 'title' => 'FAQ', 'content' => '' ],
			],
			'navs'  => [ 'primary' => [ 'title' => 'Primary', 'items' => $items ] ],
		];
	}

	public function test_a_nav_item_may_declare_children() {
		$manifest = Manifest::fromArray(
			$this->navManifest(
				[
					[ 'entry' => 'home' ],
					[ 'entry' => 'guide', 'children' => [ [ 'entry' => 'faq' ] ] ],
				]
			),
			'/tmp/theme'
		);

		$items = $manifest->navs()['primary']->items;

		$this->assertCount( 2, $items );
		$this->assertArrayNotHasKey( 'children', $items[0] );
		$this->assertSame( 'faq', $items[1]['children'][0]['entry'] );
	}

	public function test_a_child_naming_an_undeclared_entry_is_rejected() {
		$this->expectException( \Pediment\Seeder\ManifestError::class );
		$this->expectExceptionMessage( "navs.primary.items.0.children.0: unknown entry 'nope'." );

		Manifest::fromArray(
			$this->navManifest( [ [ 'entry' => 'guide', 'children' => [ [ 'entry' => 'nope' ] ] ] ] ),
			'/tmp/theme'
		);
	}

	public function test_a_child_needs_an_entry_or_a_url_and_label() {
		$this->expectException( \Pediment\Seeder\ManifestError::class );
		$this->expectExceptionMessage( "navs.primary.items.0.children.0: needs either 'entry' or both 'url' and 'label'." );

		Manifest::fromArray(
			$this->navManifest( [ [ 'entry' => 'guide', 'children' => [ [ 'label' => 'Orphan' ] ] ] ] ),
			'/tmp/theme'
		);
	}

	public function test_children_may_not_nest_a_second_level() {
		$this->expectException( \Pediment\Seeder\ManifestError::class );
		$this->expectExceptionMessage( "navs.primary.items.0.children.0: 'children' may not nest" );

		Manifest::fromArray(
			$this->navManifest(
				[
					[
						'entry'    => 'guide',
						'children' => [ [ 'entry' => 'faq', 'children' => [ [ 'entry' => 'home' ] ] ] ],
					],
				]
			),
			'/tmp/theme'
		);
	}
```

- [ ] **Step 2: Run them to verify they fail**

Run: `npx wp-env run tests-wordpress --env-cwd=wp-content/plugins/pediment-ai ./vendor/bin/phpunit --filter ManifestTest`
Expected: FAIL — `test_children_may_not_nest_a_second_level` and the two child-validation tests do not throw, because today's loop ignores `children` entirely.

- [ ] **Step 3: Widen the `NavSpec` docblock**

In `plugin/src/Seeder/NavSpec.php`, replace line 11:

```php
	/** @param array<int,array{entry?:string,url?:string,label?:string,children?:array<int,array{entry?:string,url?:string,label?:string}>}> $items */
```

- [ ] **Step 4: Route nav items through a recursive validator**

In `plugin/src/Seeder/Manifest.php`, replace the inner item loop (lines 166-177) so the `foreach` body becomes a single call:

```php
			foreach ( (array) ( $declared['items'] ?? [] ) as $index => $item ) {
				$items[] = self::navItem( (array) $item, $entries, "navs.{$key}.items.{$index}", true );
			}
```

Then add the validator as a private static method on the class, next to the other parsing helpers:

```php
	/**
	 * Validate one navigation item, and its children when it has them.
	 *
	 * One level of nesting is all a header menu is, and all the serializer
	 * emits. Rejecting a second level here — rather than dropping it quietly —
	 * is what keeps a manifest that declares a three-level menu from shipping a
	 * two-level one and looking correct in review.
	 *
	 * @param array<string,mixed>     $item
	 * @param array<string,EntrySpec> $entries
	 * @param string                  $path          Operator-facing location, e.g. `navs.primary.items.2`.
	 * @param bool                    $allowChildren False for an item that is already a child.
	 * @return array<string,mixed>
	 *
	 * @throws ManifestError When the item names an unknown entry, declares neither
	 *                       an entry nor a url/label pair, or nests too deeply.
	 */
	private static function navItem( array $item, array $entries, string $path, bool $allowChildren ): array {
		if ( isset( $item['entry'] ) ) {
			$target = (string) $item['entry'];
			if ( ! isset( $entries[ $target ] ) ) {
				throw new ManifestError( "{$path}: unknown entry '{$target}'." ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- exception message for the operator, not echoed output.
			}
		} elseif ( ! isset( $item['url'], $item['label'] ) ) {
			throw new ManifestError( "{$path}: needs either 'entry' or both 'url' and 'label'." ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- exception message for the operator, not echoed output.
		}

		if ( ! isset( $item['children'] ) ) {
			return $item;
		}

		if ( ! $allowChildren ) {
			throw new ManifestError( "{$path}: 'children' may not nest — a header menu is two levels, and deeper trees are not serialized." ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- exception message for the operator, not echoed output.
		}

		$children = [];
		foreach ( (array) $item['children'] as $index => $child ) {
			$children[] = self::navItem( (array) $child, $entries, "{$path}.children.{$index}", false );
		}
		$item['children'] = $children;

		return $item;
	}
```

- [ ] **Step 5: Run the tests to verify they pass**

Run: `npx wp-env run tests-wordpress --env-cwd=wp-content/plugins/pediment-ai ./vendor/bin/phpunit --filter ManifestTest`
Expected: PASS, including the four new cases and every pre-existing one — the top-level error messages are byte-identical to before because `$path` reproduces the old string.

- [ ] **Step 6: Commit**

```bash
git add plugin/src/Seeder/Manifest.php plugin/src/Seeder/NavSpec.php plugin/tests/phpunit/Seeder/ManifestTest.php
git commit -m "feat(seeder): accept one level of nav children"
```

---

### Task 2: `serialize()` emits `wp:navigation-submenu`

**Files:**
- Modify: `plugin/src/Seeder/NavSeeder.php:212-250` (`serialize()`)
- Test: `plugin/tests/phpunit/Seeder/NavSeederTest.php`

**Interfaces:**
- Consumes: `NavSpec::$items` with `children` from Task 1.
- Produces: `NavSeeder::linkAttrs()`; `serialize()` output may now contain `wp:navigation-submenu` blocks. `serialize()`'s signature is unchanged.

- [ ] **Step 1: Write the failing tests**

Append to `plugin/tests/phpunit/Seeder/NavSeederTest.php`, inside the class. Note this file's existing `manifest()` helper declares only `home` and `about`, so these tests build their own manifest with the extra entries:

```php
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
```

- [ ] **Step 2: Run them to verify they fail**

Run: `npx wp-env run tests-wordpress --env-cwd=wp-content/plugins/pediment-ai ./vendor/bin/phpunit --filter NavSeederTest`
Expected: FAIL — `serialize()` emits only `wp:navigation-link`, so no submenu markup appears.

- [ ] **Step 3: Split the attribute building out of `serialize()`**

In `plugin/src/Seeder/NavSeeder.php`, replace the whole of `serialize()` (from `public function serialize` through its closing brace) with:

```php
	/** @param array<string,int> $entryIds */
	public function serialize( NavSpec $spec, string $language, array $entryIds ): string {
		$blocks = [];

		foreach ( $spec->items as $item ) {
			$item  = (array) $item;
			$attrs = $this->linkAttrs( $item, $language, $entryIds );

			// Reported by apply() via unresolvedEntries(), not from here:
			// serialize() must stay pure, and an unresolved link has to be
			// reported on EVERY run, not only the one that rewrites the nav.
			// A submenu parent takes its children with it — a submenu whose
			// own link is missing is not a menu anyone meant.
			if ( [] === $attrs ) {
				continue;
			}

			if ( ! isset( $item['children'] ) ) {
				$blocks[] = '<!-- wp:navigation-link ' . wp_json_encode( $attrs, JSON_UNESCAPED_SLASHES ) . ' /-->';
				continue;
			}

			$children = [];
			foreach ( (array) $item['children'] as $child ) {
				$childAttrs = $this->linkAttrs( (array) $child, $language, $entryIds );
				if ( [] === $childAttrs ) {
					continue;
				}
				$children[] = '<!-- wp:navigation-link ' . wp_json_encode( $childAttrs, JSON_UNESCAPED_SLASHES ) . ' /-->';
			}

			$blocks[] = '<!-- wp:navigation-submenu ' . wp_json_encode( $attrs, JSON_UNESCAPED_SLASHES ) . ' -->'
				. ( [] === $children ? "\n" : "\n" . implode( "\n", $children ) . "\n" )
				. '<!-- /wp:navigation-submenu -->';
		}

		return implode( "\n", $blocks );
	}

	/**
	 * The block attributes one navigation item resolves to.
	 *
	 * Key order is load-bearing, not cosmetic: plan() decides UPDATE by
	 * comparing stored post_content against a fresh serialize(), so reordering
	 * these would make every nav on every site rewrite itself once.
	 *
	 * JSON_UNESCAPED_SLASHES matches what the block editor writes, and keeps
	 * the markup stable under KSES, which strips `\/` on save.
	 *
	 * @param array<string,mixed> $item
	 * @param array<string,int>   $entryIds
	 * @return array<string,mixed> Empty when an entry item has no resolved post.
	 */
	private function linkAttrs( array $item, string $language, array $entryIds ): array {
		if ( ! isset( $item['entry'] ) ) {
			return [
				'label' => (string) $item['label'],
				'url'   => (string) $item['url'],
				'kind'  => 'custom',
			];
		}

		$postId = (int) ( $entryIds[ $item['entry'] . '|' . $language ] ?? 0 );
		if ( 0 === $postId ) {
			return [];
		}

		$post = get_post( $postId );

		return [
			'label' => (string) ( $item['label'] ?? ( $post ? $post->post_title : '' ) ),
			'type'  => $post ? $post->post_type : 'page',
			'id'    => $postId,
			'kind'  => 'post-type',
			'url'   => (string) get_permalink( $postId ),
		];
	}
```

- [ ] **Step 4: Run the tests to verify they pass**

Run: `npx wp-env run tests-wordpress --env-cwd=wp-content/plugins/pediment-ai ./vendor/bin/phpunit --filter NavSeederTest`
Expected: PASS, including every pre-existing case. If `test_an_unchanged_nav_is_not_rewritten` fails, the attribute order or the `JSON_UNESCAPED_SLASHES` flag changed — fix the serializer, never the test.

- [ ] **Step 5: Commit**

```bash
git add plugin/src/Seeder/NavSeeder.php plugin/tests/phpunit/Seeder/NavSeederTest.php
git commit -m "feat(seeder): serialize nav children as submenus"
```

---

### Task 3: Unresolved children block the write, and the plan counts them

**Files:**
- Modify: `plugin/src/Seeder/NavSeeder.php:41-91` (`plan()`), `:341-349` (`unresolvedEntries()`)
- Test: `plugin/tests/phpunit/Seeder/NavSeederTest.php`

**Interfaces:**
- Consumes: `NavSeeder::linkAttrs()` from Task 2.
- Produces: `NavSeeder::unresolvedItem()`, `NavSeeder::countLinks()`. No public signature changes.

This task closes the gap Task 2 opens. `serialize()` drops a child it cannot resolve; without the checks below, a nav whose *child* entry failed would be written one link short and silently.

- [ ] **Step 1: Write the failing tests**

Append to `plugin/tests/phpunit/Seeder/NavSeederTest.php`:

```php
	public function test_an_unresolved_child_leaves_the_whole_menu_alone() {
		$seeder = new NavSeeder( new NullProvider() );
		$guide  = self::factory()->post->create( [ 'post_type' => 'page' ] );
		$faq    = self::factory()->post->create( [ 'post_type' => 'page' ] );

		$full   = $this->submenuManifest( [ [ 'entry' => 'guide', 'children' => [ [ 'entry' => 'faq' ] ] ] ] );
		$ids    = [ 'guide|' => $guide, 'faq|' => $faq ];
		$navIds = $seeder->apply( $seeder->plan( $full, $ids ), $full, $ids );
		$before = get_post( $navIds['primary|'] )->post_content;

		// The child's page disappears — its ID no longer resolves.
		$short = [ 'guide|' => $guide ];
		$seeder->apply( $seeder->plan( $full, $short ), $full, $short );

		$this->assertSame( $before, get_post( $navIds['primary|'] )->post_content, 'never write a shortened menu' );
		$this->assertContains(
			'navs.primary: "faq" has no seeded post yet — the link is missing from the menu.',
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

		$plan = $seeder->plan( $m, [] );

		$this->assertSame( PlanItem::CREATE, $plan->items()[0]->action );
		$this->assertSame( 3, $plan->items()[0]->changes['items']['to'], 'home + guide + faq' );
	}

	public function test_an_existing_submenu_is_counted_when_it_changes() {
		$seeder = new NavSeeder( new NullProvider() );
		$ids    = [
			'home|'  => self::factory()->post->create( [ 'post_type' => 'page' ] ),
			'guide|' => self::factory()->post->create( [ 'post_type' => 'page' ] ),
			'faq|'   => self::factory()->post->create( [ 'post_type' => 'page' ] ),
		];
		$first  = $this->submenuManifest( [ [ 'entry' => 'guide', 'children' => [ [ 'entry' => 'faq' ] ] ] ] );
		$seeder->apply( $seeder->plan( $first, $ids ), $first, $ids );

		$second = $this->submenuManifest(
			[
				[ 'entry' => 'home' ],
				[ 'entry' => 'guide', 'children' => [ [ 'entry' => 'faq' ] ] ],
			]
		);
		$plan   = $seeder->plan( $second, $ids );

		$this->assertSame( PlanItem::UPDATE, $plan->items()[0]->action );
		$this->assertSame( 2, $plan->items()[0]->changes['items']['from'], 'the stored submenu and its one child' );
		$this->assertSame( 3, $plan->items()[0]->changes['items']['to'] );
	}
```

- [ ] **Step 2: Run them to verify they fail**

Run: `npx wp-env run tests-wordpress --env-cwd=wp-content/plugins/pediment-ai ./vendor/bin/phpunit --filter NavSeederTest`
Expected: FAIL — `unresolvedEntries()` never inspects `children`, so the shortened menu is written; and `count( $spec->items )` reports 2 where the tests expect 3.

- [ ] **Step 3: Walk children when collecting unresolved entries**

In `plugin/src/Seeder/NavSeeder.php`, replace `unresolvedEntries()` with:

```php
	/**
	 * Entry keys this nav references that have no seeded post yet, at either level.
	 *
	 * @param array<string,int> $entryIds
	 * @return string[]
	 */
	private function unresolvedEntries( NavSpec $spec, string $language, array $entryIds ): array {
		$missing = [];
		foreach ( $spec->items as $item ) {
			$missing = array_merge( $missing, $this->unresolvedItem( (array) $item, $language, $entryIds ) );
		}
		return $missing;
	}

	/**
	 * @param array<string,mixed> $item
	 * @param array<string,int>   $entryIds
	 * @return string[]
	 */
	private function unresolvedItem( array $item, string $language, array $entryIds ): array {
		$missing = [];

		if ( isset( $item['entry'] ) && 0 === (int) ( $entryIds[ $item['entry'] . '|' . $language ] ?? 0 ) ) {
			$missing[] = (string) $item['entry'];
		}

		foreach ( (array) ( $item['children'] ?? [] ) as $child ) {
			$missing = array_merge( $missing, $this->unresolvedItem( (array) $child, $language, $entryIds ) );
		}

		return $missing;
	}
```

- [ ] **Step 4: Count links at both levels**

Still in `plugin/src/Seeder/NavSeeder.php`, add next to `unresolvedItem()`:

```php
	/**
	 * Total links a nav spec describes, counting submenu parents and children.
	 *
	 * The plan's `items` change is operator-facing arithmetic, so it has to
	 * count the same things on both sides: `count( $spec->items )` would report
	 * a two-level menu as its top-level width and read as a shrink.
	 *
	 * @param array<int,array<string,mixed>> $items
	 */
	private static function countLinks( array $items ): int {
		$count = 0;
		foreach ( $items as $item ) {
			++$count;
			$count += self::countLinks( (array) ( ( (array) $item )['children'] ?? [] ) );
		}
		return $count;
	}
```

In `plan()`, replace the CREATE branch's change array (line 54):

```php
						[ 'items' => [ 'from' => 0, 'to' => self::countLinks( $spec->items ) ] ]
```

and the UPDATE branch's (line 84):

```php
							[
								'items' => [
									'from' => substr_count( $current, 'wp:navigation-link' )
										+ substr_count( $current, '<!-- wp:navigation-submenu' ),
									'to'   => self::countLinks( $spec->items ),
								],
							],
```

The `<!-- ` prefix on the submenu count is deliberate: the closing delimiter is `<!-- /wp:navigation-submenu -->`, and a bare `wp:navigation-submenu` would match it too and double every submenu.

- [ ] **Step 5: Run the tests to verify they pass**

Run: `npx wp-env run tests-wordpress --env-cwd=wp-content/plugins/pediment-ai ./vendor/bin/phpunit --filter NavSeederTest`
Expected: PASS, all cases.

- [ ] **Step 6: Run the whole plugin suite for regressions**

Run: `npx wp-env run tests-wordpress --env-cwd=wp-content/plugins/pediment-ai ./vendor/bin/phpunit`
Expected: PASS, no new failures (one pre-existing skip is normal).

- [ ] **Step 7: Commit**

```bash
git add plugin/src/Seeder/NavSeeder.php plugin/tests/phpunit/Seeder/NavSeederTest.php
git commit -m "fix(seeder): block writes on unresolved nav children"
```

---

### Task 4: A submenu is built per language

**Files:**
- Modify: `plugin/tests/polylang/NavLanguageTest.php`
- Modify: `plugin/src/Seeder/NavSeeder.php` (only if a test fails)

**Interfaces:**
- Consumes: `PolylangTestCase`, `PolylangProvider`, `NavSeeder`, everything from Tasks 1-3.
- Produces: nothing new — this task proves the property Workation's manifest depends on.

- [ ] **Step 1: Read the harness**

Read `plugin/tests/polylang/PolylangTestCase.php` and `plugin/tests/polylang/NavLanguageTest.php` in full before writing. `PolylangTestCase` seeds the `en`/`de` language terms in `wpSetUpBeforeClass()`; tests create posts with `self::factory()` and tag them with `pll_set_post_language()`.

- [ ] **Step 2: Write the failing test**

Append to `plugin/tests/polylang/NavLanguageTest.php`, inside the class:

```php
	public function test_a_submenu_is_written_per_language_with_translated_child_titles() {
		$manifest = Manifest::fromArray(
			[
				'languages' => [
					'en' => [ 'name' => 'English', 'locale' => 'en_US', 'default' => true ],
					'de' => [ 'name' => 'Deutsch', 'locale' => 'de_DE' ],
				],
				'pages'     => [
					'guide' => [ 'title' => 'Guide', 'content' => '' ],
					'faq'   => [ 'title' => 'FAQ', 'content' => '', 'languages' => [ 'de' => [ 'title' => 'Häufige Fragen' ] ] ],
				],
				'navs'      => [
					'primary' => [
						'title' => 'Primary',
						'items' => [ [ 'entry' => 'guide', 'children' => [ [ 'entry' => 'faq' ] ] ] ],
					],
				],
			],
			'/tmp/theme'
		);

		$page = function ( string $title, string $language ): int {
			$id = self::factory()->post->create( [ 'post_type' => 'page', 'post_title' => $title ] );
			pll_set_post_language( $id, $language );
			return $id;
		};

		$ids = [
			'guide|en' => $page( 'Guide', 'en' ),
			'faq|en'   => $page( 'FAQ', 'en' ),
			'guide|de' => $page( 'Guide', 'de' ),
			'faq|de'   => $page( 'Häufige Fragen', 'de' ),
		];

		$seeder = new NavSeeder( new PolylangProvider() );
		$navIds = $seeder->apply( $seeder->plan( $manifest, $ids ), $manifest, $ids );

		$this->assertSame( [], $seeder->errors() );

		$en = get_post( $navIds['primary|en'] )->post_content;
		$de = get_post( $navIds['primary|de'] )->post_content;

		$this->assertSame( 1, substr_count( $en, '<!-- wp:navigation-submenu ' ) );
		$this->assertSame( 1, substr_count( $de, '<!-- wp:navigation-submenu ' ) );
		$this->assertStringContainsString( '"label":"FAQ"', $en );
		$this->assertStringContainsString( 'Häufige Fragen', $de, 'the child label comes from the German title, not the English one' );
		$this->assertStringContainsString( '"id":' . $ids['faq|de'], $de );
	}
```

- [ ] **Step 3: Run the Polylang suite**

Run: `npx wp-env run tests-wordpress --env-cwd=wp-content/plugins/pediment-ai ./vendor/bin/phpunit -c phpunit-polylang.xml.dist --filter NavLanguageTest`
Expected: PASS with the Task 1-3 implementation. If the German submenu carries the English child label, the bug is that `linkAttrs()` is not reading the per-language `$entryIds` key — fix `NavSeeder`, not the test.

- [ ] **Step 4: Run the full Polylang suite**

Run: `npx wp-env run tests-wordpress --env-cwd=wp-content/plugins/pediment-ai ./vendor/bin/phpunit -c phpunit-polylang.xml.dist`
Expected: PASS, no regressions.

- [ ] **Step 5: Commit**

```bash
git add plugin/tests/polylang/NavLanguageTest.php
git commit -m "test(seeder): pin per-language nav submenus"
```

---

### Task 5: Document it, and record what it does not do

**Files:**
- Modify: `docs/seeding.md` (the `### navs` section, currently around line 118)
- Modify: `docs/BACKLOG.md`
- Modify: `docs/SESSION_LOG.md`

**Interfaces:**
- Consumes: everything from Tasks 1-4.
- Produces: nothing executable.

- [ ] **Step 1: Extend the `navs` documentation**

In `docs/seeding.md`, immediately after the paragraph ending "…so a theme is free to key its other menus however it likes, but the menu it wants bound to the header's ref-less block must be called `primary`, or must opt out via the `pediment_primary_nav_key` filter.", insert:

````markdown
#### Submenus

An item may declare `children`, which serializes it as a
`wp:navigation-submenu` wrapping their `wp:navigation-link` blocks:

```php
'navs' => array(
	'primary' => array(
		'title' => 'Primary',
		'items' => array(
			array( 'entry' => 'activities' ),
			array(
				'entry'    => 'guide',
				'children' => array(
					array( 'entry' => 'arrival' ),
					array( 'entry' => 'faq' ),
				),
			),
		),
	),
),
```

Children take the same shape as top-level items — `{ entry, label }` or
`{ url, label }`, with `label` optional on an `entry` item and falling back to
that entry's own per-language title — and the same validation. **Nesting stops
there:** `children` inside `children` is a `ManifestError`, not a silently
flattened menu.

Two consequences worth knowing before you declare one:

- **The nav tree is not the page tree.** A submenu child needs no `parent` in
  the `pages` section, and a page with a `parent` need not appear under it in a
  menu. They are independent.
- **A submenu parent takes its children with it.** If the parent's entry has no
  live post in a language, the whole submenu is omitted from that language's
  serialized menu rather than its children being promoted to the top level —
  and, as with any unresolved link, `unresolvedEntries()` reports it and the
  navigation is left exactly as it is rather than written short.
````

- [ ] **Step 2: Record the deliberate limits in the backlog**

In `docs/BACKLOG.md`, add to the open list:

```markdown
- [ ] **Nav submenus are one level deep, and nav labels are not translatable.** Both are
  deliberate (migration step 6b design decisions 3 and 4,
  [2026-08-06-workation-client-theme-step6b-design.md](superpowers/specs/2026-08-06-workation-client-theme-step6b-design.md)).
  A `children` key inside `children` is rejected by `Manifest::navItem()` rather than flattened,
  because an unbounded tree needs recursion guards on a serializer that runs on every seed. A
  declared `label` is still written verbatim into every language, so a multilingual menu must omit
  `label` and let each entry's per-language title carry it — a site that genuinely needs a menu
  label differing from its page title in more than one language has no way to express it, which is
  what Workation's retired `NavTranslations.php` did with a 489-line map. Revisit only if a client
  actually needs it.

- [ ] **The derived-slug rule's stated reason does not hold universally.**
  [docs/seeding.md](seeding.md)'s "The derived slug rule, and why" asserts that Polylang does not
  hook `wp_unique_post_slug`, and derives the `<slug>-<lang>` default from that. Workation's
  staging site has four pages all carrying `post_name = home`, one per language, so on that
  configuration Polylang does uniquify per language. The rule itself stays correct as a default — a
  per-language slug that collides really does produce the permanent `Verifier` mismatch described
  there — but the explanation should say Polylang *may not* hook it rather than that it does not,
  and note that an explicit per-language `slug` override is how a site that does allow shared slugs
  declares them. Found while writing the migration step 6b design.
```

- [ ] **Step 3: Append to the session log**

In `docs/SESSION_LOG.md`, add a dated entry recording that `NavSpec`/`Manifest`/`NavSeeder` gained one-level submenu support, that attribute order and `JSON_UNESCAPED_SLASHES` were preserved so existing menus do not rewrite on upgrade, and that this unblocks the Workation client-theme conversion.

- [ ] **Step 4: Run the full suites one more time**

Run both:
```bash
npx wp-env run tests-wordpress --env-cwd=wp-content/plugins/pediment-ai ./vendor/bin/phpunit
npx wp-env run tests-wordpress --env-cwd=wp-content/plugins/pediment-ai ./vendor/bin/phpunit -c phpunit-polylang.xml.dist
```
Expected: PASS both.

- [ ] **Step 5: Run the lint gates CI will run**

```bash
npm run lint:colors
npm run lint:js
composer --working-dir=plugin phpcs
```
Expected: `lint:colors` and `lint:js` clean; `phpcs` reports no errors (warnings are non-blocking by config).

- [ ] **Step 6: Commit**

```bash
git add docs/seeding.md docs/BACKLOG.md docs/SESSION_LOG.md
git commit -m "docs: document nav submenus and their limits"
```

- [ ] **Step 7: STOP — ask before pushing**

Report the suite and lint results, then ask whether to push and open the PR. Do not push without an explicit yes. Once merged and released, the plugin minor is available to the theme half of step 6b (`2026-08-06-workation-client-theme-step6b.md`), whose CI runs against the published plugin zip.
