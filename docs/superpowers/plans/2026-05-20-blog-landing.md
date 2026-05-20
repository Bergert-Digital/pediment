# Blog Landing Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Build a proper blog landing page at `/blog/` styled in the Pediment design system, backed by the WP main query so URL-driven pagination works natively.

**Architecture:** New `templates/home.html` with two full-bleed bands (heading band + paginated Insight grid band). A new `core/query` block style `is-style-insights-grid` makes the query loop render with the same Insight card visuals as `starter/blog-index`. Shared CSS lives in `assets/css/theme.css` (always loaded), keyed off two selectors so both blocks stay in sync.

**Tech Stack:** WordPress block templates (FSE), `register_block_style`, core query loop + post-template + query-pagination, plain CSS, PHPUnit, Playwright.

**Spec:** [`docs/superpowers/specs/2026-05-20-blog-landing-design.md`](../specs/2026-05-20-blog-landing-design.md)

---

## Branch strategy

Per project memory ("Worktree tests = verify-after-merge — wp-env mounts main checkout, not worktrees"), this plan works directly on `development` rather than in a worktree. PHPUnit and Playwright both require wp-env, which mounts the main checkout — a worktree would block test runs mid-task.

If you spawn parallel agents anyway, only the agent doing the visual verification needs the main checkout; the others can edit in worktrees and rebase before merge.

## File map

| File | Responsibility | Action |
| --- | --- | --- |
| `inc/block-styles.php` | Register theme block styles | Modify — add `core/query` style |
| `templates/home.html` | Blog landing template (FSE) | Create |
| `assets/css/theme.css` | Theme-wide CSS (bands, tokens) | Modify — append Insight card + pagination rules |
| `src/blocks/blog-index/style.scss` | Curated blog-index block styles | Modify — de-dupe card visuals (now shared in theme.css) |
| `inc/seed.php` | First-run content seeder | Modify — clear Blog page's `post_content` |
| `tests/phpunit/BlockStylesTest.php` | Block style registration tests | Modify — assert `insights-grid` on `core/query` |
| `tests/phpunit/Templates/HomeTemplateTest.php` | `home.html` structure tests | Create |
| `tests/phpunit/Seed/SeedCommandTest.php` | Seeder behavior tests | Modify — assert Blog page content is empty |
| `tests/e2e/blog-landing.spec.ts` | Playwright e2e for `/blog/` | Create |

---

## Task 1: Register `is-style-insights-grid` block style on `core/query`

**Files:**
- Modify: `tests/phpunit/BlockStylesTest.php`
- Modify: `inc/block-styles.php:29-36` (insert new `register_block_style` after the existing `band-navy` block)

- [ ] **Step 1: Add the failing test**

Append to `tests/phpunit/BlockStylesTest.php` (before the closing `}`):

```php
	public function test_query_insights_grid_style_registered() {
		do_action( 'init' );
		$names = $this->styles_for( 'core/query' );
		$this->assertContains( 'insights-grid', $names );
	}
```

- [ ] **Step 2: Run test to confirm failure**

Run: `npx wp-env run tests-cli vendor/bin/phpunit --filter test_query_insights_grid_style_registered`
Expected: FAIL — assertion fails because the style isn't registered yet.

- [ ] **Step 3: Register the new style**

In `inc/block-styles.php`, inside the existing `add_action( 'init', function () { ... } )` callback, after the `band-navy` registration block, add:

```php
			register_block_style(
				'core/query',
				array(
					'name'  => 'insights-grid',
					'label' => __( 'Insights grid', 'starter' ),
				)
			);
```

- [ ] **Step 4: Run test to confirm pass**

Run: `npx wp-env run tests-cli vendor/bin/phpunit --filter test_query_insights_grid_style_registered`
Expected: PASS.

- [ ] **Step 5: Run the full BlockStylesTest to verify no regressions**

Run: `npx wp-env run tests-cli vendor/bin/phpunit --filter BlockStylesTest`
Expected: all assertions PASS.

- [ ] **Step 6: Commit**

```bash
git add tests/phpunit/BlockStylesTest.php inc/block-styles.php
git commit -m "feat(blocks): register is-style-insights-grid block style on core/query"
```

---

## Task 2: PHPUnit test for `home.html` structure

We write the structure test first; it fails because `home.html` doesn't yet contain the band layout (the existing file is bare-bones).

**Files:**
- Create: `tests/phpunit/Templates/HomeTemplateTest.php`

- [ ] **Step 1: Write the failing test**

```php
<?php

class HomeTemplateTest extends WP_UnitTestCase {

	private function template_path(): string {
		return get_theme_file_path( 'templates/home.html' );
	}

	private function template_blocks(): array {
		$this->assertFileExists( $this->template_path(), 'templates/home.html must exist' );
		$content = file_get_contents( $this->template_path() );
		return parse_blocks( $content );
	}

	private function find_first_block( array $blocks, string $name ): ?array {
		foreach ( $blocks as $block ) {
			if ( ( $block['blockName'] ?? '' ) === $name ) {
				return $block;
			}
			if ( ! empty( $block['innerBlocks'] ) ) {
				$nested = $this->find_first_block( $block['innerBlocks'], $name );
				if ( null !== $nested ) {
					return $nested;
				}
			}
		}
		return null;
	}

	private function find_all_blocks( array $blocks, string $name ): array {
		$out = array();
		foreach ( $blocks as $block ) {
			if ( ( $block['blockName'] ?? '' ) === $name ) {
				$out[] = $block;
			}
			if ( ! empty( $block['innerBlocks'] ) ) {
				$out = array_merge( $out, $this->find_all_blocks( $block['innerBlocks'], $name ) );
			}
		}
		return $out;
	}

	public function test_template_has_header_and_footer_parts(): void {
		$blocks = $this->template_blocks();
		$parts  = $this->find_all_blocks( $blocks, 'core/template-part' );
		$slugs  = array_map( static fn( $b ) => $b['attrs']['slug'] ?? '', $parts );
		$this->assertContains( 'header', $slugs );
		$this->assertContains( 'footer', $slugs );
	}

	public function test_template_has_heading_band_with_h1(): void {
		$blocks = $this->template_blocks();
		// First band group with kicker + h1 + lead.
		$groups = $this->find_all_blocks( $blocks, 'core/group' );
		$bands  = array_filter(
			$groups,
			static fn( $g ) => isset( $g['attrs']['className'] )
				&& str_contains( $g['attrs']['className'], 'starter-band' )
		);
		$this->assertNotEmpty( $bands, 'must contain at least one starter-band group' );
		$heading_band = array_values( $bands )[0];
		$h1           = $this->find_first_block( $heading_band['innerBlocks'], 'core/heading' );
		$this->assertNotNull( $h1, 'heading band must contain a core/heading' );
		$this->assertSame( 1, (int) ( $h1['attrs']['level'] ?? 2 ), 'heading must be level 1' );
	}

	public function test_template_has_query_with_insights_grid_class_and_inherit(): void {
		$blocks = $this->template_blocks();
		$query  = $this->find_first_block( $blocks, 'core/query' );
		$this->assertNotNull( $query, 'template must contain a core/query block' );
		$this->assertTrue(
			(bool) ( $query['attrs']['query']['inherit'] ?? false ),
			'query must use inherit:true'
		);
		$this->assertStringContainsString(
			'is-style-insights-grid',
			(string) ( $query['attrs']['className'] ?? '' ),
			'query must carry is-style-insights-grid className'
		);
	}

	public function test_query_contains_required_post_blocks(): void {
		$blocks = $this->template_blocks();
		$query  = $this->find_first_block( $blocks, 'core/query' );
		$this->assertNotNull( $query );
		foreach (
			array(
				'core/post-template',
				'core/post-featured-image',
				'core/post-terms',
				'core/post-date',
				'core/post-title',
				'core/post-excerpt',
				'core/read-more',
				'core/query-pagination',
				'core/query-no-results',
			) as $needle
		) {
			$this->assertNotNull(
				$this->find_first_block( $query['innerBlocks'], $needle ),
				"core/query must contain a $needle block"
			);
		}
	}

	public function test_post_terms_block_targets_category_taxonomy(): void {
		$blocks = $this->template_blocks();
		$terms  = $this->find_first_block( $blocks, 'core/post-terms' );
		$this->assertNotNull( $terms );
		$this->assertSame( 'category', $terms['attrs']['term'] ?? '' );
	}
}
```

- [ ] **Step 2: Run test to confirm it fails**

Run: `npx wp-env run tests-cli vendor/bin/phpunit --filter HomeTemplateTest`
Expected: FAIL — at minimum, `test_template_has_heading_band_with_h1` and `test_template_has_query_with_insights_grid_class_and_inherit` fail because the existing `templates/home.html` doesn't exist or has no band structure.

- [ ] **Step 3: Commit the failing test**

```bash
git add tests/phpunit/Templates/HomeTemplateTest.php
git commit -m "test(home): structure assertions for the new blog landing template"
```

---

## Task 3: Create `templates/home.html`

**Files:**
- Create: `templates/home.html`

- [ ] **Step 1: Write the template**

Create `templates/home.html` with:

```html
<!-- wp:template-part {"slug":"header","tagName":"header"} /-->

<!-- wp:group {"tagName":"main","layout":{"type":"constrained"}} -->
<main class="wp-block-group">

<!-- wp:group {"align":"full","className":"starter-band is-style-band-surface","style":{"spacing":{"margin":{"top":"0","bottom":"0"}}},"layout":{"type":"constrained"}} -->
<div class="wp-block-group alignfull starter-band is-style-band-surface" style="margin-top:0;margin-bottom:0">
	<!-- wp:paragraph {"align":"center","className":"kicker"} -->
	<p class="has-text-align-center kicker">INSIGHTS</p>
	<!-- /wp:paragraph -->
	<!-- wp:heading {"textAlign":"center","level":1} -->
	<h1 class="wp-block-heading has-text-align-center">Latest thinking from the team</h1>
	<!-- /wp:heading -->
	<!-- wp:paragraph {"align":"center","className":"lead"} -->
	<p class="has-text-align-center lead">Field notes, briefings, and longer-form essays from across the team.</p>
	<!-- /wp:paragraph -->
</div>
<!-- /wp:group -->

<!-- wp:group {"align":"full","className":"starter-band","style":{"spacing":{"margin":{"top":"0","bottom":"0"}}},"layout":{"type":"constrained"}} -->
<div class="wp-block-group alignfull starter-band" style="margin-top:0;margin-bottom:0">
	<!-- wp:query {"queryId":0,"query":{"perPage":9,"pages":0,"offset":0,"postType":"post","order":"desc","orderBy":"date","inherit":true},"align":"wide","className":"is-style-insights-grid"} -->
	<div class="wp-block-query alignwide is-style-insights-grid">

		<!-- wp:post-template {"layout":{"type":"grid","columnCount":3}} -->

			<!-- wp:group {"className":"starter-insight-card__media","layout":{"type":"default"}} -->
			<div class="wp-block-group starter-insight-card__media">
				<!-- wp:post-featured-image {"isLink":true,"aspectRatio":"16/11"} /-->
				<!-- wp:post-terms {"term":"category"} /-->
			</div>
			<!-- /wp:group -->

			<!-- wp:group {"className":"starter-insight-card__body","layout":{"type":"flex","orientation":"vertical","flexWrap":"nowrap"}} -->
			<div class="wp-block-group starter-insight-card__body">
				<!-- wp:post-date {"fontSize":"sm"} /-->
				<!-- wp:post-title {"isLink":true,"level":3} /-->
				<!-- wp:post-excerpt {"moreText":"","showMoreOnNewLine":false} /-->
				<!-- wp:read-more {"content":"Read more →"} /-->
			</div>
			<!-- /wp:group -->

		<!-- /wp:post-template -->

		<!-- wp:query-no-results -->
			<!-- wp:paragraph -->
			<p>No posts yet.</p>
			<!-- /wp:paragraph -->
		<!-- /wp:query-no-results -->

		<!-- wp:query-pagination {"paginationArrow":"arrow","layout":{"type":"flex","justifyContent":"center"}} -->
			<!-- wp:query-pagination-previous /-->
			<!-- wp:query-pagination-numbers /-->
			<!-- wp:query-pagination-next /-->
		<!-- /wp:query-pagination -->

	</div>
	<!-- /wp:query -->
</div>
<!-- /wp:group -->

</main>
<!-- /wp:group -->

<!-- wp:template-part {"slug":"footer","tagName":"footer"} /-->
```

- [ ] **Step 2: Run HomeTemplateTest to confirm all assertions pass**

Run: `npx wp-env run tests-cli vendor/bin/phpunit --filter HomeTemplateTest`
Expected: 5/5 PASS.

- [ ] **Step 3: Run the full PHPUnit suite to catch unintended regressions**

Run: `npx wp-env run tests-cli vendor/bin/phpunit`
Expected: all PASS (the existing `templates/index.html` was untouched, so blog-related tests should still pass).

- [ ] **Step 4: Commit**

```bash
git add templates/home.html
git commit -m "feat(templates): home.html with banded heading + paginated insights grid"
```

---

## Task 4: Add Insight card shared CSS to `assets/css/theme.css`

We add grouped-selector rules so the existing curated `starter/blog-index` block and the new `is-style-insights-grid` query loop look identical.

**Files:**
- Modify: `assets/css/theme.css`

- [ ] **Step 1: Read the current file once to find a sensible insertion point**

Read `assets/css/theme.css` — append the new section at the end of the file.

- [ ] **Step 2: Append the shared Insight card rules**

Append to the end of `assets/css/theme.css`:

```css

/* ---------- Insight card (shared: starter/blog-index + is-style-insights-grid) ---------- */

.is-style-insights-grid .wp-block-post-template {
	list-style: none;
	padding: 0;
	margin: 0;
	display: grid;
	gap: 24px;
	grid-template-columns: repeat(3, 1fr);
}

.starter-blog-index__item,
.is-style-insights-grid .wp-block-post-template > li {
	display: flex;
	flex-direction: column;
	background: var(--wp--preset--color--surface);
	border: 1px solid var(--wp--preset--color--border);
	border-radius: var(--r-lg, 20px);
	overflow: hidden;
	transition: transform .18s ease, box-shadow .18s ease, border-color .18s ease;
}

.starter-blog-index__item:hover,
.is-style-insights-grid .wp-block-post-template > li:hover {
	transform: translateY(-3px);
	box-shadow: var(--wp--preset--shadow--subtle);
	border-color: var(--wp--preset--color--border-strong);
}

.starter-blog-index__media,
.is-style-insights-grid .starter-insight-card__media {
	position: relative;
	aspect-ratio: 16 / 11;
	overflow: hidden;
	background: var(--wp--preset--color--surface-elevated);
	margin: 0;
}

.is-style-insights-grid .starter-insight-card__media .wp-block-post-featured-image,
.is-style-insights-grid .starter-insight-card__media .wp-block-post-featured-image a,
.is-style-insights-grid .starter-insight-card__media .wp-block-post-featured-image img {
	width: 100%;
	height: 100%;
	display: block;
}

.is-style-insights-grid .starter-insight-card__media .wp-block-post-featured-image img {
	object-fit: cover;
}

.starter-blog-index__badge,
.is-style-insights-grid .wp-block-post-terms {
	position: absolute;
	top: 16px;
	left: 16px;
	display: inline-flex;
	align-items: center;
	gap: 7px;
	background: color-mix(in srgb, #fff 95%, transparent);
	color: var(--wp--preset--color--accent-hover);
	font-size: 0.78rem;
	font-weight: 800;
	padding: 7px 13px;
	border-radius: var(--r-pill, 999px);
	box-shadow: var(--wp--preset--shadow--subtle);
	margin: 0;
}

.starter-blog-index__badge::before,
.is-style-insights-grid .wp-block-post-terms::before {
	content: "";
	width: 7px;
	height: 7px;
	border-radius: 50%;
	background: var(--wp--preset--color--accent);
}

.is-style-insights-grid .wp-block-post-terms a {
	color: inherit;
	text-decoration: none;
}

.starter-blog-index__body,
.is-style-insights-grid .starter-insight-card__body {
	display: flex;
	flex-direction: column;
	flex: 1;
	padding: 26px 26px 28px;
	gap: 0;
}

.is-style-insights-grid .starter-insight-card__body .wp-block-post-date {
	font-size: 0.85rem;
	font-weight: 600;
	color: var(--wp--preset--color--text-muted);
	margin: 0;
}

.is-style-insights-grid .starter-insight-card__body .wp-block-post-title {
	margin: 12px 0 0;
	font-size: 1.18rem;
	line-height: 1.28;
	letter-spacing: -0.01em;
	color: var(--wp--preset--color--text);
	transition: color .15s ease;
}

.is-style-insights-grid .starter-insight-card__body .wp-block-post-title a {
	color: inherit;
	text-decoration: none;
}

.is-style-insights-grid .starter-insight-card__body .wp-block-post-title a:hover {
	color: var(--wp--preset--color--accent);
}

.is-style-insights-grid .starter-insight-card__body .wp-block-post-excerpt {
	margin: 12px 0 0;
	flex: 1;
	color: var(--wp--preset--color--text-muted);
	line-height: 1.55;
	font-size: 0.96rem;
}

.is-style-insights-grid .starter-insight-card__body .wp-block-post-excerpt p {
	margin: 0;
}

.is-style-insights-grid .starter-insight-card__body .wp-block-read-more {
	align-self: flex-start;
	margin-top: 20px;
	color: var(--wp--preset--color--accent);
	font-weight: 700;
	font-size: 0.92rem;
	text-decoration: none;
}

.is-style-insights-grid .starter-insight-card__body .wp-block-read-more:hover {
	color: var(--wp--preset--color--accent-hover);
}

@media (max-width: 900px) {
	.is-style-insights-grid .wp-block-post-template {
		grid-template-columns: 1fr;
	}
}
```

- [ ] **Step 3: Visually verify (manual smoke)**

In a browser, open `http://localhost:8890/blog/` (per project memory, the child-theme wp-env at `:8890` is the working test base).

Expected: heading band at top with "INSIGHTS" kicker + h1 + lead; below it, a 3-column grid of Insight cards (image with category pill badge, date, title, excerpt, "Read more →"). No card filter row.

If a card has no featured image, the media wrapper should still render with the elevated-surface background (and badge if categorized).

- [ ] **Step 4: Commit**

```bash
git add assets/css/theme.css
git commit -m "style(theme): Insight card visuals shared by blog-index + insights-grid query"
```

---

## Task 5: Add pagination styles to `assets/css/theme.css`

**Files:**
- Modify: `assets/css/theme.css`

- [ ] **Step 1: Append the pagination block**

Append to the end of `assets/css/theme.css`:

```css

/* ---------- Insights-grid pagination ---------- */

.is-style-insights-grid .wp-block-query-pagination {
	display: flex;
	justify-content: center;
	gap: 8px;
	margin-top: 48px;
	flex-wrap: wrap;
}

.is-style-insights-grid .wp-block-query-pagination a,
.is-style-insights-grid .wp-block-query-pagination .page-numbers {
	display: inline-flex;
	align-items: center;
	gap: 6px;
	padding: 10px 16px;
	border-radius: var(--r-pill, 999px);
	border: 1.5px solid var(--wp--preset--color--border);
	background: var(--wp--preset--color--surface);
	color: var(--wp--preset--color--text);
	font-weight: 700;
	font-size: 0.92rem;
	text-decoration: none;
	transition: color .15s ease, border-color .15s ease, background-color .15s ease;
}

.is-style-insights-grid .wp-block-query-pagination a:hover {
	border-color: var(--wp--preset--color--accent);
	color: var(--wp--preset--color--accent);
}

.is-style-insights-grid .wp-block-query-pagination .page-numbers.current {
	background: var(--wp--preset--color--primary);
	color: #fff;
	border-color: var(--wp--preset--color--primary);
}

.is-style-insights-grid .wp-block-query-pagination a:focus-visible {
	outline: 2px solid var(--wp--preset--color--accent);
	outline-offset: 2px;
}
```

- [ ] **Step 2: Visually verify**

Seed 12+ posts so pagination is exercised, or temporarily lower posts-per-page:

Run: `npx wp-env run cli wp option update posts_per_page 4`

Then refresh `http://localhost:8890/blog/`. Expected: a row of pill-shaped pagination links centered below the grid; the current page number is filled with the primary color.

Restore: `npx wp-env run cli wp option update posts_per_page 10`

- [ ] **Step 3: Commit**

```bash
git add assets/css/theme.css
git commit -m "style(theme): pagination pills for the insights-grid query"
```

---

## Task 6: Refactor `src/blocks/blog-index/style.scss` to remove duplicated card visuals

The card shell, media, badge, body, title, excerpt, and read-more rules now live in `assets/css/theme.css`. The block-scoped SCSS keeps only:

- the curated block's grid template (`__list`)
- the filter row (`__filter`) — curated-only
- the empty state (`__empty`)
- the `is-style-band-navy` overrides (curated-only — those colors only apply where the curated block sits on a navy band)

**Files:**
- Modify: `src/blocks/blog-index/style.scss`

- [ ] **Step 1: Overwrite the SCSS file with the slimmed version**

Replace the entire contents of `src/blocks/blog-index/style.scss` with:

```scss
.starter-blog-index {
  &__filter {
    display: flex;
    justify-content: center;
    gap: 10px;
    flex-wrap: wrap;
    margin: 0 0 46px;

    button {
      font: inherit;
      font-weight: 700;
      font-size: 0.92rem;
      padding: 11px 22px;
      border-radius: var(--r-pill, 999px);
      border: 1.5px solid var(--wp--preset--color--border);
      background: var(--wp--preset--color--surface);
      color: var(--wp--preset--color--text);
      cursor: pointer;
      transition: color .15s ease, border-color .15s ease,
        background-color .15s ease;

      &:hover {
        border-color: var(--wp--preset--color--accent);
        color: var(--wp--preset--color--accent);
      }

      &.is-active {
        background: var(--wp--preset--color--primary);
        color: #fff;
        border-color: var(--wp--preset--color--primary);
      }

      &:focus-visible {
        outline: 2px solid var(--wp--preset--color--accent);
        outline-offset: 2px;
      }
    }
  }

  &__list {
    list-style: none;
    padding: 0;
    margin: 0;
    display: grid;
    gap: 24px;
    grid-template-columns: repeat(3, 1fr);
  }

  &__item[hidden] {
    display: none;
  }

  &__img {
    width: 100%;
    height: 100%;
    object-fit: cover;
  }

  &__link {
    text-decoration: none;
    color: inherit;

    &:hover .starter-blog-index__title {
      color: var(--wp--preset--color--accent);
    }
  }

  &__readmore {
    display: inline-flex;
    align-items: center;
    gap: 7px;

    .i {
      width: 1em;
      height: 1em;
      fill: currentColor;
    }
  }

  &__empty {
    color: var(--wp--preset--color--text-muted);
    text-align: center;
    padding: var(--wp--preset--spacing--40);
  }
}

@media (max-width: 900px) {
  .starter-blog-index__list {
    grid-template-columns: 1fr;
  }
}

.is-style-band-navy .starter-blog-index {
  &__filter button {
    background: transparent;
    border-color: color-mix(in srgb, #fff 28%, transparent);
    color: #fff;

    &.is-active {
      background: #fff;
      color: var(--wp--preset--color--primary);
      border-color: #fff;
    }
  }
}
```

- [ ] **Step 2: Rebuild the block stylesheet**

Run: `npm run build`
Expected: success, no SCSS errors. The compiled `build/blocks/blog-index/style-index.css` shrinks compared to before.

- [ ] **Step 3: Visually verify the Pediment landing page still looks correct**

Open `http://localhost:8890/` (the seeded Home page with the Pediment landing pattern). Scroll to the Insights band — the cards should look identical to before this change because the shared visuals are now in `theme.css` instead of `style.scss`.

Expected: no visual difference from prior to Task 4. Hover effect, badge pill, body padding, "Read more" arrow all unchanged.

- [ ] **Step 4: Commit**

```bash
git add src/blocks/blog-index/style.scss build/blocks/blog-index
git commit -m "refactor(blog-index): move card visuals to theme.css for cross-block reuse"
```

---

## Task 7: Clear the seeded Blog page's `post_content`

`home.html` now renders the listing — the Blog page's `post_content` is unused. Clear it so the page edit screen doesn't show a confusing leftover block.

**Files:**
- Modify: `tests/phpunit/Seed/SeedCommandTest.php`
- Modify: `inc/seed.php:55-58`

- [ ] **Step 1: Add the failing test**

Append a new test method to `tests/phpunit/Seed/SeedCommandTest.php` (before the closing `}`):

```php
	public function test_blog_page_has_empty_content_so_home_template_renders_listing() {
		starter_seed_run();
		$blog = get_page_by_path( 'blog' );
		$this->assertInstanceOf( WP_Post::class, $blog );
		$this->assertSame( '', trim( $blog->post_content ), 'Blog page content must be empty; home.html renders the listing.' );
	}
```

- [ ] **Step 2: Run test to confirm failure**

Run: `npx wp-env run tests-cli vendor/bin/phpunit --filter test_blog_page_has_empty_content_so_home_template_renders_listing`
Expected: FAIL — the current seeded content is `<!-- wp:starter/blog-index {"count":10,"align":"wide"} /-->`.

- [ ] **Step 3: Clear the Blog page's content in the seeder**

In `inc/seed.php`, change the `'blog'` entry inside `$pages` from:

```php
		'blog'    => array(
			'title'   => 'Blog',
			'content' => '<!-- wp:starter/blog-index {"count":10,"align":"wide"} /-->',
		),
```

to:

```php
		'blog'    => array(
			'title'   => 'Blog',
			// home.html renders the listing; the page's own content is unused.
			'content' => '',
		),
```

- [ ] **Step 4: Run test to confirm pass**

Run: `npx wp-env run tests-cli vendor/bin/phpunit --filter test_blog_page_has_empty_content_so_home_template_renders_listing`
Expected: PASS.

- [ ] **Step 5: Run the full Seed suite to confirm no regressions**

Run: `npx wp-env run tests-cli vendor/bin/phpunit --filter Seed`
Expected: all PASS.

Note: existing Blog pages from prior seeds won't be updated by this change because the seeder skips existing pages. To exercise the change end-to-end:

Run: `npx wp-env run cli wp post delete $(npx wp-env run cli wp post list --post_type=page --name=blog --field=ID) --force`
Then: `npx wp-env run cli wp starter-theme seed`

- [ ] **Step 6: Commit**

```bash
git add tests/phpunit/Seed/SeedCommandTest.php inc/seed.php
git commit -m "feat(seed): empty Blog page content (home.html renders the listing)"
```

---

## Task 8: Playwright e2e — `/blog/` smoke test

Per project memory, Playwright is verified after merging to `development` (wp-env mounts the main checkout). Write the spec now; verify after merge.

**Files:**
- Create: `tests/e2e/blog-landing.spec.ts`

- [ ] **Step 1: Write the spec**

```typescript
import { test, expect } from '@playwright/test';

test.describe('blog landing (/blog/)', () => {
  test('renders heading band, insight cards, and pagination block', async ({ page }) => {
    await page.goto('/blog/');

    // Heading band
    await expect(
      page.getByRole('heading', { level: 1, name: /latest thinking from the team/i })
    ).toBeVisible();

    // Grid: at least one post card
    const firstCard = page.locator('.is-style-insights-grid .wp-block-post-template > li').first();
    await expect(firstCard).toBeVisible();

    // The post title inside that card is a real link to a post permalink
    const titleLink = firstCard.locator('.wp-block-post-title a').first();
    await expect(titleLink).toBeVisible();
    const href = await titleLink.getAttribute('href');
    expect(href).toMatch(/\/.+\/$/);
    const titleText = (await titleLink.innerText()).trim();
    expect(titleText.length).toBeGreaterThan(0);

    // Category badge is positioned absolute over the media wrapper
    const badge = firstCard.locator('.wp-block-post-terms').first();
    await expect(badge).toBeVisible();
    const position = await badge.evaluate(
      (el) => window.getComputedStyle(el).position
    );
    expect(position).toBe('absolute');

    // Pagination block exists in the DOM (no `next` link if the seeded post
    // count fits on one page — that's fine).
    const pagination = page.locator('.is-style-insights-grid .wp-block-query-pagination');
    await expect(pagination).toHaveCount(1);
  });

  test('no client-side filter row on the paginated landing', async ({ page }) => {
    await page.goto('/blog/');
    // The curated block's filter — must not appear here.
    await expect(page.locator('.starter-blog-index__filter')).toHaveCount(0);
  });
});
```

- [ ] **Step 2: Run the spec locally (after merge to development)**

Per project memory, Playwright runs against the child-theme env at `localhost:8890` *after* the change is merged to the development branch (wp-env mounts the main checkout). Two ways:

If you're still on a worktree branch and merge isn't done:

```bash
PLAYWRIGHT_BASE_URL=http://localhost:8890 npx playwright test tests/e2e/blog-landing.spec.ts
```

…will run, but against whichever code is currently checked out in the main directory — so verify by either merging first OR switching the main checkout to this branch temporarily.

Expected: 2/2 PASS.

- [ ] **Step 3: Commit**

```bash
git add tests/e2e/blog-landing.spec.ts
git commit -m "test(e2e): blog landing renders heading, grid, badge, pagination"
```

---

## Task 9: Final verification

- [ ] **Step 1: Run the full PHPUnit suite**

Run: `npx wp-env run tests-cli vendor/bin/phpunit`
Expected: all PASS, including:
- `BlockStylesTest` (3 tests now)
- `HomeTemplateTest` (5 tests)
- `SeedCommandTest` (5 tests)
- All previously passing tests.

- [ ] **Step 2: Run the full Playwright suite**

Per project memory, Playwright runs against the child-theme env at `:8890` after merge. Once the branch is merged to development:

Run: `PLAYWRIGHT_BASE_URL=http://localhost:8890 npx playwright test`
Expected: all PASS, including the new `blog-landing.spec.ts` (2 tests).

If unrelated pre-existing failures appear (e.g., the navigation/404 e2e failing on an unseeded DB — flagged in project memory as pre-existing), note them but do not block merge on them.

- [ ] **Step 3: Final manual visual check**

Open `http://localhost:8890/blog/` and verify:

- "INSIGHTS" kicker, h1 heading, lead paragraph centered in the top band.
- 3-column grid of cards below (single-column on narrow viewports).
- Each card: featured image (or empty media background), category badge as a pill positioned top-left, date, title (link), excerpt, "Read more →".
- Hover on a card → subtle lift + shadow + accent-border.
- Pagination row centered below the grid (or empty/single-page if there aren't enough posts).
- Site header and footer present.

Open `http://localhost:8890/` (Pediment landing) and confirm the Insights band on the front page still looks visually identical to before this change.

- [ ] **Step 4: If using a worktree, merge back to development**

If you used a worktree for the editing, merge to `development` now. Otherwise, this step is a no-op.

```bash
git checkout development
git merge --ff-only <worktree-branch>
# delete worktree per /Users/jonas/.claude/CLAUDE.md worktree policy
```

---

## Self-review against spec

**Coverage check:**

| Spec section | Task |
| --- | --- |
| New `templates/home.html` | Task 2 (test) + Task 3 (file) |
| `is-style-insights-grid` block style on `core/query` | Task 1 |
| Shared Insight card CSS in `assets/css/theme.css` | Task 4 |
| Pagination styles | Task 5 |
| `src/blocks/blog-index/style.scss` refactor | Task 6 |
| `inc/seed.php` Blog page content cleanup | Task 7 |
| PHPUnit `HomeTemplateTest` | Task 2 |
| PHPUnit `BlockStylesTest` extension | Task 1 |
| Playwright `blog-landing.spec.ts` | Task 8 |

No spec section unaddressed. The "editor preview" risk in the spec is handled implicitly: `assets/css/theme.css` is enqueued globally via `wp_enqueue_scripts` (verified in `functions.php:52-71`) and is also picked up by the editor through `add_editor_style` *if* that hook is registered — verify in Task 4 step 3 by opening the Site Editor and confirming the cards render with the same look there. If they don't, add `add_editor_style( 'assets/css/theme.css' )` to `functions.php`'s `after_setup_theme` callback (add this as a follow-up — not blocking the front-end work).

**Type/name consistency:**

- `is-style-insights-grid` — used identically in block-style registration, template className, and CSS selectors.
- `.starter-insight-card__media` / `.starter-insight-card__body` — used in template inner groups and matched by the CSS rules.
- `wp:read-more` block — confirmed by Task 4 CSS targeting `.wp-block-read-more`.
- `wp:post-terms` `term: "category"` — asserted by `HomeTemplateTest::test_post_terms_block_targets_category_taxonomy` and styled in Task 4.

All matches.
