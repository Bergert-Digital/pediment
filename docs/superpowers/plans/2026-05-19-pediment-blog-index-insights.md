# Blog-Index → Insights Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Restyle `starter/blog-index` into the Pediment "Insights" design — featured-image cards with a category badge, post meta, and an optional **presentational** category filter driven by a small view-script — without changing the existing server-side post query.

**Architecture:** `render.php` keeps the current `WP_Query` (count + optional `categorySlug` server filter) untouched, and additionally derives each post's primary category to (a) tag every card with `data-cat="<slug>"` + a badge, and (b) build a filter bar listing the distinct categories actually shown. A standalone `view.ts` (compiled to `view.js`, registered via block.json `viewScript`) toggles the `hidden` attribute on cards per the clicked filter button, scoped to each block instance. The filter is purely client-side — zero server/AJAX effect — exactly matching the locked mockup and the spec's "Insights filter is presentational at parity" constraint. This block has a single design (not a variant-bearing block like hero/pull-quote), so no `starter_*_variants` registry/filter is introduced.

**Tech Stack:** WordPress FSE dynamic block (block.json apiVersion 3 + render.php, no `save`), `@wordpress/scripts` build (TS via ts-loader; `view.ts` auto-detected as the block's viewScript entry, same convention as `src/blocks/mega-menu/view.ts`), `ServerSideRender` editor preview, PHPUnit (`WP_UnitTestCase`).

**Scope:** `src/blocks/blog-index/{block.json,render.php,edit.tsx,style.scss}`, a new `src/blocks/blog-index/view.ts`, and `tests/phpunit/BlockRender/BlogIndexTest.php`. NOT here: child-theme.json reconciliation (separate); the suggested `cta`/other variant-filter sweep (separate). No other blocks/parts/theme.json/registration/`functions.php`/`inc/*`/`mega-*`. The Phosphor sprite already ships `ph-arrow-right` (verified) — no sprite change.

**Verification constraint:** The execution worktree is NOT mounted in wp-env. Per task: env-independent gates only — `npm run build`, `php -l`, valid `block.json` JSON, SCSS brace-balance, and a static trace of every BlogIndexTest method against the shipped `render.php`. Full PHPUnit runs POST-MERGE in the main checkout (`:8888`/`:8889`). **Definition of done: post-merge PHPUnit green (all BlogIndexTest incl. the 3 original cases verbatim + the new badge/image/filter cases); `npm run build` clean and emits `build/blocks/blog-index/view.js`.** Authoritative TS gate is `npm run build` (ts-loader) — do NOT rely on standalone `npx tsc`. The presentational filter has no server effect; its DOM contract (`data-filter` buttons + `data-cat` cards + `[hidden]` toggling) is asserted by PHPUnit on the rendered markup and the `view.ts` logic is reviewed statically — consistent with the spec ("presentational at parity") and the established no-new-e2e verification model for this workstream.

---

## File Structure

| File | Responsibility | Action |
|---|---|---|
| `src/blocks/blog-index/block.json` | Metadata: add `showFilter` bool + `viewScript`; updated description | Modify |
| `src/blocks/blog-index/render.php` | Insight-card markup + primary-category derivation + presentational filter bar | Modify (rewrite) |
| `src/blocks/blog-index/view.ts` | Per-instance client-side filter (toggle `hidden`) | Create |
| `src/blocks/blog-index/edit.tsx` | Add `showFilter` ToggleControl; keep ServerSideRender | Modify |
| `src/blocks/blog-index/style.scss` | Insight card grid/media/badge/meta/filter styles (rewrite) | Modify (rewrite) |
| `tests/phpunit/BlockRender/BlogIndexTest.php` | Keep 3 original tests verbatim; add helper + viewScript/badge/image/filter cases | Modify |

---

### Task 1: block.json — `showFilter` + `viewScript` + description; BlogIndexTest guards

**Files:**
- Modify: `src/blocks/blog-index/block.json`
- Test: `tests/phpunit/BlockRender/BlogIndexTest.php`

- [ ] **Step 1: Replace `src/blocks/blog-index/block.json` with EXACTLY:**

```json
{
	"$schema": "https://schemas.wp.org/trunk/block.json",
	"apiVersion": 3,
	"name": "starter/blog-index",
	"title": "Blog Index",
	"category": "starter",
	"description": "Recent posts as Insight cards with featured image, category badge, and an optional presentational category filter.",
	"textdomain": "starter",
	"supports": { "html": false, "align": [ "wide" ] },
	"attributes": {
		"count": { "type": "number", "default": 6 },
		"categorySlug": { "type": "string", "default": "" },
		"showFilter": { "type": "boolean", "default": true }
	},
	"editorScript": "file:./index.js",
	"editorStyle": "file:./style-index.css",
	"style": "file:./style-index.css",
	"viewScript": "file:./view.js",
	"render": "file:./render.php"
}
```

- [ ] **Step 2: Replace the ENTIRE body of `tests/phpunit/BlockRender/BlogIndexTest.php` with EXACTLY:**

The first three tests (`test_renders_recent_posts`, `test_filters_by_category_slug`, `test_renders_empty_state_when_no_posts`) are kept VERBATIM from the current file — do not alter them. Everything from the `render()` helper onward is new.

```php
<?php

class BlogIndexTest extends WP_UnitTestCase {
	private function render( string $json ): string {
		return do_blocks( '<!-- wp:starter/blog-index ' . $json . ' /-->' );
	}

	public function test_renders_recent_posts() {
		$post_ids = array();
		foreach ( array( 'First post', 'Second post', 'Third post' ) as $title ) {
			$post_ids[] = $this->factory->post->create(
				array(
					'post_title'  => $title,
					'post_status' => 'publish',
					'post_type'   => 'post',
				)
			);
		}

		$html = do_blocks( '<!-- wp:starter/blog-index {"count":3} /-->' );

		$this->assertStringContainsString( 'First post', $html );
		$this->assertStringContainsString( 'Second post', $html );
		$this->assertStringContainsString( 'Third post', $html );

		foreach ( $post_ids as $id ) {
			wp_delete_post( $id, true );
		}
	}

	public function test_filters_by_category_slug() {
		$cat_id = $this->factory->category->create( array( 'slug' => 'news', 'name' => 'News' ) );
		$in_id  = $this->factory->post->create( array( 'post_title' => 'News one',  'post_status' => 'publish', 'post_category' => array( $cat_id ) ) );
		$out_id = $this->factory->post->create( array( 'post_title' => 'Other one', 'post_status' => 'publish' ) );

		$html = do_blocks( '<!-- wp:starter/blog-index {"count":10,"categorySlug":"news"} /-->' );

		$this->assertStringContainsString( 'News one', $html );
		$this->assertStringNotContainsString( 'Other one', $html );

		wp_delete_post( $in_id, true );
		wp_delete_post( $out_id, true );
		wp_delete_category( $cat_id );
	}

	public function test_renders_empty_state_when_no_posts() {
		$html = do_blocks( '<!-- wp:starter/blog-index {"count":3} /-->' );
		$this->assertStringContainsString( 'starter-blog-index__empty', $html );
	}

	public function test_block_json_has_viewscript_and_showfilter_default() {
		$path = dirname( __DIR__, 3 ) . '/src/blocks/blog-index/block.json';
		$this->assertFileIsReadable( $path );
		$data = json_decode( file_get_contents( $path ), true );
		$this->assertIsArray( $data );
		$this->assertSame( 'file:./view.js', $data['viewScript'] );
		$this->assertTrue( $data['attributes']['showFilter']['default'] );
	}

	public function test_card_has_featured_image_and_category_badge() {
		$cat_id = $this->factory->category->create( array( 'slug' => 'briefing', 'name' => 'Briefing' ) );
		$att_id = self::factory()->attachment->create_upload_object( DIR_TESTDATA . '/images/canola.jpg' );
		$post_id = $this->factory->post->create(
			array(
				'post_title'    => 'Imaged post',
				'post_status'   => 'publish',
				'post_category' => array( $cat_id ),
			)
		);
		set_post_thumbnail( $post_id, $att_id );

		$html = $this->render( '{"count":5}' );

		$this->assertStringContainsString( 'starter-blog-index__media', $html );
		$this->assertStringContainsString( 'starter-blog-index__img', $html );
		$this->assertStringContainsString( '<img', $html );
		$this->assertStringContainsString( 'starter-blog-index__badge', $html );
		$this->assertStringContainsString( 'starter-blog-index__badge--briefing', $html );
		$this->assertStringContainsString( 'Briefing', $html );
		$this->assertStringContainsString( 'data-cat="briefing"', $html );

		wp_delete_post( $post_id, true );
		wp_delete_attachment( $att_id, true );
		wp_delete_category( $cat_id );
	}

	public function test_filter_bar_lists_categories_when_multiple_and_enabled() {
		$a = $this->factory->category->create( array( 'slug' => 'article', 'name' => 'Article' ) );
		$b = $this->factory->category->create( array( 'slug' => 'podcast', 'name' => 'Podcast' ) );
		$pa = $this->factory->post->create( array( 'post_title' => 'A one', 'post_status' => 'publish', 'post_category' => array( $a ) ) );
		$pb = $this->factory->post->create( array( 'post_title' => 'B one', 'post_status' => 'publish', 'post_category' => array( $b ) ) );

		$html = $this->render( '{"count":10}' );

		$this->assertStringContainsString( 'starter-blog-index__filter', $html );
		$this->assertStringContainsString( 'data-filter="all"', $html );
		$this->assertStringContainsString( 'data-filter="article"', $html );
		$this->assertStringContainsString( 'data-filter="podcast"', $html );
		$this->assertMatchesRegularExpression( '/class="is-active"[^>]*data-filter="all"/', $html );

		wp_delete_post( $pa, true );
		wp_delete_post( $pb, true );
		wp_delete_category( $a );
		wp_delete_category( $b );
	}

	public function test_filter_bar_omitted_when_showfilter_false() {
		$a = $this->factory->category->create( array( 'slug' => 'cata', 'name' => 'Cat A' ) );
		$b = $this->factory->category->create( array( 'slug' => 'catb', 'name' => 'Cat B' ) );
		$pa = $this->factory->post->create( array( 'post_title' => 'PA', 'post_status' => 'publish', 'post_category' => array( $a ) ) );
		$pb = $this->factory->post->create( array( 'post_title' => 'PB', 'post_status' => 'publish', 'post_category' => array( $b ) ) );

		$html = $this->render( '{"count":10,"showFilter":false}' );

		$this->assertStringNotContainsString( 'starter-blog-index__filter', $html );
		$this->assertStringContainsString( 'PA', $html );

		wp_delete_post( $pa, true );
		wp_delete_post( $pb, true );
		wp_delete_category( $a );
		wp_delete_category( $b );
	}

	public function test_filter_bar_omitted_for_single_category() {
		$a = $this->factory->category->create( array( 'slug' => 'solo', 'name' => 'Solo' ) );
		$p1 = $this->factory->post->create( array( 'post_title' => 'One', 'post_status' => 'publish', 'post_category' => array( $a ) ) );
		$p2 = $this->factory->post->create( array( 'post_title' => 'Two', 'post_status' => 'publish', 'post_category' => array( $a ) ) );

		$html = $this->render( '{"count":10}' );

		$this->assertStringNotContainsString( 'starter-blog-index__filter', $html );
		$this->assertStringContainsString( 'data-cat="solo"', $html );

		wp_delete_post( $p1, true );
		wp_delete_post( $p2, true );
		wp_delete_category( $a );
	}

	public function test_card_has_permalink_link_meta_and_readmore() {
		$post_id = $this->factory->post->create(
			array(
				'post_title'   => 'Linkable',
				'post_status'  => 'publish',
				'post_excerpt' => 'Short summary here.',
			)
		);

		$html = $this->render( '{"count":3}' );

		$this->assertStringContainsString( 'starter-blog-index__item', $html );
		$this->assertStringContainsString( 'starter-blog-index__title', $html );
		$this->assertStringContainsString( 'Linkable', $html );
		$this->assertStringContainsString( 'Short summary here.', $html );
		$this->assertStringContainsString( 'starter-blog-index__readmore', $html );
		$this->assertStringContainsString( esc_url( get_permalink( $post_id ) ), $html );
		$this->assertStringContainsString( '#ph-arrow-right', $html );

		wp_delete_post( $post_id, true );
	}
}
```

- [ ] **Step 3: Verify (env-independent).**

Run: `python3 -c "import json;d=json.load(open('src/blocks/blog-index/block.json'));print(d['viewScript']);print(d['attributes']['showFilter'])"`
Expected: `file:./view.js` then `{'type': 'boolean', 'default': True}`.

Run: `php -l tests/phpunit/BlockRender/BlogIndexTest.php`
Expected: `No syntax errors detected`

Run: `npm run build`
Expected: compiles (will warn the `view.js` referenced by block.json is absent until Task 3 — acceptable; webpack still exits 0). `build/blocks/blog-index/block.json` regenerated.

TDD note: the new badge/image/filter/meta tests go green only after Tasks 2 & 3 land — expected. The 3 original tests + `test_block_json_has_viewscript_and_showfilter_default` are green now.

- [ ] **Step 4: Commit**

```bash
git add src/blocks/blog-index/block.json tests/phpunit/BlockRender/BlogIndexTest.php
git commit -m "test(blog-index): block.json viewScript/showFilter + Insights BlogIndexTest cases"
```

---

### Task 2: render.php — Insight cards + primary-category derivation + presentational filter

**Files:**
- Modify: `src/blocks/blog-index/render.php`

- [ ] **Step 1: Replace `src/blocks/blog-index/render.php` with EXACTLY:**

```php
<?php
/**
 * Server-side render for starter/blog-index (Insights cards).
 *
 * The post query is unchanged (count + optional categorySlug). The filter
 * bar is purely presentational — view.js toggles card visibility client-side.
 *
 * @var array $attributes
 */

$count       = isset( $attributes['count'] ) ? max( 1, (int) $attributes['count'] ) : 6;
$cat_slug    = isset( $attributes['categorySlug'] ) ? (string) $attributes['categorySlug'] : '';
$show_filter = ! isset( $attributes['showFilter'] ) || (bool) $attributes['showFilter'];

$query_args = array(
	'post_type'      => 'post',
	'post_status'    => 'publish',
	'posts_per_page' => $count,
);
if ( '' !== $cat_slug ) {
	$query_args['category_name'] = $cat_slug;
}

$query = new WP_Query( $query_args );

$wrapper = get_block_wrapper_attributes( array( 'class' => 'starter-blog-index' ) );

ob_start();
?>
<section <?php echo $wrapper; // phpcs:ignore WordPress.Security.EscapeOutput ?>>
	<?php if ( ! $query->have_posts() ) : ?>
		<p class="starter-blog-index__empty"><?php esc_html_e( 'No posts yet.', 'starter' ); ?></p>
		<?php
	else :
		$cards   = array();
		$filters = array(); // slug => name, in first-appearance order.
		while ( $query->have_posts() ) :
			$query->the_post();
			$post_id = get_the_ID();
			$terms   = get_the_category( $post_id );
			$primary = ! empty( $terms ) ? $terms[0] : null;
			$slug    = $primary ? (string) $primary->slug : '';
			$name    = $primary ? (string) $primary->name : '';
			if ( '' !== $slug && ! isset( $filters[ $slug ] ) ) {
				$filters[ $slug ] = $name;
			}
			$cards[] = array(
				'slug'      => $slug,
				'cat_name'  => $name,
				'permalink' => get_permalink( $post_id ),
				'title'     => get_the_title( $post_id ),
				'date'      => get_the_date( '', $post_id ),
				'datetime'  => get_the_date( 'c', $post_id ),
				'excerpt'   => get_the_excerpt( $post_id ),
				'thumb'     => has_post_thumbnail( $post_id )
					? get_the_post_thumbnail( $post_id, 'large', array( 'class' => 'starter-blog-index__img', 'alt' => '' ) )
					: '',
			);
		endwhile;
		wp_reset_postdata();

		$render_filter = $show_filter && count( $filters ) > 1;
		?>
		<?php if ( $render_filter ) : ?>
			<div class="starter-blog-index__filter">
				<button type="button" class="is-active" data-filter="all"><?php esc_html_e( 'All', 'starter' ); ?></button>
				<?php foreach ( $filters as $f_slug => $f_name ) : ?>
					<button type="button" data-filter="<?php echo esc_attr( $f_slug ); ?>"><?php echo esc_html( $f_name ); ?></button>
				<?php endforeach; ?>
			</div>
		<?php endif; ?>
		<ul class="starter-blog-index__list">
			<?php foreach ( $cards as $card ) : ?>
				<li class="starter-blog-index__item" data-cat="<?php echo esc_attr( $card['slug'] ); ?>">
					<div class="starter-blog-index__media">
						<?php echo $card['thumb']; // phpcs:ignore WordPress.Security.EscapeOutput -- get_the_post_thumbnail() output is escaped. ?>
						<?php if ( '' !== $card['cat_name'] ) : ?>
							<span class="starter-blog-index__badge starter-blog-index__badge--<?php echo esc_attr( $card['slug'] ); ?>"><?php echo esc_html( $card['cat_name'] ); ?></span>
						<?php endif; ?>
					</div>
					<div class="starter-blog-index__body">
						<div class="starter-blog-index__meta">
							<time class="starter-blog-index__date" datetime="<?php echo esc_attr( $card['datetime'] ); ?>"><?php echo esc_html( $card['date'] ); ?></time>
						</div>
						<a class="starter-blog-index__link" href="<?php echo esc_url( $card['permalink'] ); ?>">
							<h3 class="starter-blog-index__title"><?php echo esc_html( $card['title'] ); ?></h3>
						</a>
						<p class="starter-blog-index__excerpt"><?php echo esc_html( $card['excerpt'] ); ?></p>
						<a class="starter-blog-index__readmore" href="<?php echo esc_url( $card['permalink'] ); ?>">
							<?php esc_html_e( 'Read more', 'starter' ); ?>
							<svg class="i" aria-hidden="true" focusable="false"><use href="#ph-arrow-right"></use></svg>
						</a>
					</div>
				</li>
			<?php endforeach; ?>
		</ul>
		<?php
	endif;
	?>
</section>
<?php
echo ob_get_clean();
```

- [ ] **Step 2: Verify (env-independent).**

Run: `php -l src/blocks/blog-index/render.php`
Expected: `No syntax errors detected`

Run: `npm run build`
Expected: compiles; `build/blocks/blog-index/render.php` regenerated.

Static-trace every BlogIndexTest method (read `tests/phpunit/BlockRender/BlogIndexTest.php`), one line each, all PASS:
- `test_renders_recent_posts` → 3 posts, no categories asserted → titles printed in `__title` ⇒ pass.
- `test_filters_by_category_slug` → `categorySlug:"news"` unchanged → `category_name` query arg → only "News one" ⇒ pass.
- `test_renders_empty_state_when_no_posts` → no posts → `starter-blog-index__empty` ⇒ pass.
- `test_block_json_has_viewscript_and_showfilter_default` → Task 1 block.json ⇒ pass.
- `test_card_has_featured_image_and_category_badge` → thumbnail set → `get_the_post_thumbnail` emits `<img class="starter-blog-index__img">`; primary cat "briefing" → `__media`, `__badge`, `__badge--briefing`, "Briefing", `data-cat="briefing"` ⇒ pass.
- `test_filter_bar_lists_categories_when_multiple_and_enabled` → 2 distinct cats + showFilter default true → `__filter`, `data-filter="all"` (with `class="is-active"`), `data-filter="article"`, `data-filter="podcast"` ⇒ pass (regex `class="is-active"[^>]*data-filter="all"` matches the All button attribute order as emitted).
- `test_filter_bar_omitted_when_showfilter_false` → `showFilter:false` → `$render_filter` false → no `__filter`; posts still rendered ⇒ pass.
- `test_filter_bar_omitted_for_single_category` → 1 distinct cat → `count($filters)===1` → no `__filter`; `data-cat="solo"` present ⇒ pass.
- `test_card_has_permalink_link_meta_and_readmore` → `__item`, `__title` "Linkable", excerpt, `__readmore`, permalink, `#ph-arrow-right` ⇒ pass.

- [ ] **Step 3: Commit**

```bash
git add src/blocks/blog-index/render.php
git commit -m "feat(blog-index): Insight card markup, category badge, presentational filter bar"
```

---

### Task 3: view.ts — per-instance presentational filter

**Files:**
- Create: `src/blocks/blog-index/view.ts`

- [ ] **Step 1: Create `src/blocks/blog-index/view.ts` with EXACTLY:**

```ts
/**
 * Presentational category filter for starter/blog-index.
 *
 * Pure client-side: toggles the `hidden` attribute on cards. No server
 * round-trip — matches the locked design ("filter is presentational").
 * Scoped per block instance so multiple blog-index blocks coexist.
 */

function initBlogIndexFilter( root: HTMLElement ): void {
	const buttons = Array.from(
		root.querySelectorAll< HTMLButtonElement >(
			'.starter-blog-index__filter button'
		)
	);
	if ( ! buttons.length ) {
		return;
	}
	const cards = Array.from(
		root.querySelectorAll< HTMLElement >( '.starter-blog-index__item' )
	);

	buttons.forEach( ( btn ) => {
		btn.addEventListener( 'click', () => {
			buttons.forEach( ( b ) => b.classList.remove( 'is-active' ) );
			btn.classList.add( 'is-active' );
			const filter = btn.getAttribute( 'data-filter' ) || 'all';
			cards.forEach( ( card ) => {
				const cat = card.getAttribute( 'data-cat' ) || '';
				card.hidden = ! ( 'all' === filter || cat === filter );
			} );
		} );
	} );
}

function boot(): void {
	document
		.querySelectorAll< HTMLElement >( '.starter-blog-index' )
		.forEach( initBlogIndexFilter );
}

if ( 'loading' === document.readyState ) {
	document.addEventListener( 'DOMContentLoaded', boot );
} else {
	boot();
}
```

- [ ] **Step 2: Verify (env-independent).**

Run: `npm run build`
Expected: compiles cleanly (authoritative TS gate via ts-loader; do NOT use standalone `npx tsc`). `build/blocks/blog-index/view.js` is emitted (wp-scripts auto-detects `view.ts` as the block's viewScript entry — same mechanism as `src/blocks/mega-menu/view.ts`).

Run: `ls build/blocks/blog-index/view.js`
Expected: the file exists.

Static review: `boot()` selects each `.starter-blog-index` root and wires its own buttons/cards; `card.hidden = !(…)` reflects the `hidden` attribute the CSS `&[hidden]{display:none}` rule (Task 5) enforces over the card's `display:flex`. No globals leak; idempotent per root; no dependency on jQuery or `@wordpress/*`.

- [ ] **Step 3: Commit**

```bash
git add src/blocks/blog-index/view.ts
git commit -m "feat(blog-index): presentational per-instance category filter view-script"
```

---

### Task 4: edit.tsx — `showFilter` ToggleControl

**Files:**
- Modify: `src/blocks/blog-index/edit.tsx`

- [ ] **Step 1: Replace `src/blocks/blog-index/edit.tsx` with EXACTLY:**

```tsx
import { __ } from '@wordpress/i18n';
import { useBlockProps, InspectorControls } from '@wordpress/block-editor';
import {
	PanelBody,
	RangeControl,
	TextControl,
	ToggleControl,
} from '@wordpress/components';
import ServerSideRender from '@wordpress/server-side-render';

type Attrs = {
	count: number;
	categorySlug: string;
	showFilter: boolean;
};

export default function Edit( {
	attributes,
	setAttributes,
}: {
	attributes: Attrs;
	setAttributes: ( a: Partial< Attrs > ) => void;
} ) {
	const blockProps = useBlockProps();
	return (
		<>
			<InspectorControls>
				<PanelBody title={ __( 'Blog index', 'starter' ) }>
					<RangeControl
						label={ __( 'Posts to show', 'starter' ) }
						value={ attributes.count }
						min={ 1 }
						max={ 20 }
						onChange={ ( v ) => setAttributes( { count: v ?? 6 } ) }
					/>
					<TextControl
						label={ __( 'Category slug (optional)', 'starter' ) }
						value={ attributes.categorySlug }
						onChange={ ( v ) =>
							setAttributes( { categorySlug: v } )
						}
					/>
					<ToggleControl
						label={ __( 'Show category filter', 'starter' ) }
						checked={ attributes.showFilter }
						onChange={ ( v ) =>
							setAttributes( { showFilter: v } )
						}
					/>
				</PanelBody>
			</InspectorControls>
			<div { ...blockProps }>
				<ServerSideRender
					block="starter/blog-index"
					attributes={ attributes }
				/>
			</div>
		</>
	);
}
```

- [ ] **Step 2: Verify (env-independent).**

Run: `npm run build`
Expected: compiles cleanly (authoritative TS gate; do NOT use `npx tsc`). Only `edit.tsx` changed.

- [ ] **Step 3: Commit**

```bash
git add src/blocks/blog-index/edit.tsx
git commit -m "feat(blog-index): editor toggle for the category filter"
```

---

### Task 5: style.scss — Insight card design (rewrite)

**Files:**
- Modify: `src/blocks/blog-index/style.scss`

- [ ] **Step 1: Replace `src/blocks/blog-index/style.scss` with EXACTLY:**

(Values are ported from the locked mockup's `.filter` / `.m-grid` / `.m-card` / `.m-media` / `.m-badge` / `.m-body` / `.m-meta` / `.m-link` rules, mapped onto theme tokens. The single emitter of every `.starter-blog-index__*` class is `render.php`, so a coordinated rewrite is correct here — this block has one design, not a default+variant split.)

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

  &__item {
    display: flex;
    flex-direction: column;
    background: var(--wp--preset--color--surface);
    border: 1px solid var(--wp--preset--color--border);
    border-radius: var(--r-lg, 20px);
    overflow: hidden;
    transition: transform .18s ease, box-shadow .18s ease,
      border-color .18s ease;

    &:hover {
      transform: translateY(-3px);
      box-shadow: var(--wp--preset--shadow--subtle);
      border-color: var(--wp--preset--color--border-strong);
    }

    &[hidden] {
      display: none;
    }
  }

  &__media {
    position: relative;
    aspect-ratio: 16 / 11;
    overflow: hidden;
    background: var(--wp--preset--color--surface-elevated);
  }

  &__img {
    width: 100%;
    height: 100%;
    object-fit: cover;
  }

  &__badge {
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

    &::before {
      content: "";
      width: 7px;
      height: 7px;
      border-radius: 50%;
      background: var(--wp--preset--color--accent);
    }
  }

  &__body {
    display: flex;
    flex-direction: column;
    flex: 1;
    padding: 26px 26px 28px;
  }

  &__meta {
    font-size: 0.85rem;
    font-weight: 600;
    color: var(--wp--preset--color--text-muted);
  }

  &__date {
    color: inherit;
  }

  &__link {
    text-decoration: none;
    color: inherit;

    &:hover .starter-blog-index__title {
      color: var(--wp--preset--color--accent);
    }
  }

  &__title {
    margin: 12px 0 0;
    font-size: 1.18rem;
    line-height: 1.28;
    letter-spacing: -0.01em;
    color: var(--wp--preset--color--text);
    transition: color .15s ease;
  }

  &__excerpt {
    margin: 12px 0 0;
    flex: 1;
    color: var(--wp--preset--color--text-muted);
    line-height: 1.55;
    font-size: 0.96rem;
  }

  &__readmore {
    display: inline-flex;
    align-items: center;
    gap: 7px;
    align-self: flex-start;
    margin-top: 20px;
    color: var(--wp--preset--color--accent);
    font-weight: 700;
    font-size: 0.92rem;
    text-decoration: none;

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

  /* Per-category badge colours are an intentional CSS extension point.
     Example a site/child theme can add:
       .starter-blog-index__badge--podcast { color:#9A3412; }
       .starter-blog-index__badge--podcast::before { background:#F97316; } */
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

- [ ] **Step 2: Verify (env-independent).**

Run: `npm run build`
Expected: compiles; `build/blocks/blog-index/style-index.css` regenerated.

Run: `awk '{o+=gsub(/{/,"{"); c+=gsub(/}/,"}")} END{print o, c}' src/blocks/blog-index/style.scss`
Expected: two EQUAL numbers (braces balanced).

Confirm: every selector corresponds to a class `render.php` emits (`__filter` + `button`/`is-active`, `__list`, `__item` + `[hidden]`, `__media`, `__img`, `__badge` + `--<slug>`, `__body`, `__meta`, `__date`, `__link`, `__title`, `__excerpt`, `__readmore` + `.i`, `__empty`). The `&[hidden]{display:none}` rule is required so the view-script's `hidden` toggle wins over `&__item{display:flex}`.

- [ ] **Step 3: Commit**

```bash
git add src/blocks/blog-index/style.scss
git commit -m "style(blog-index): Insight card grid, media, badge, filter (token-mapped)"
```

---

### Task 6: Final integration verification

**Files:** None modified — verification only.

- [ ] **Step 1: Build clean and emits block + view-script.**

Run: `npm run build`
Expected: compiles; these all exist — `build/blocks/blog-index/{block.json,index.js,style-index.css,render.php,view.js}`. `build/blocks/blog-index/block.json` has `"viewScript": "file:./view.js"` and `attributes.showFilter.default == true`.

- [ ] **Step 2: Scope diff is exactly the intended files.**

Run: `git diff <branch-base>..HEAD --name-only`
Expected — ONLY:
```
src/blocks/blog-index/block.json
src/blocks/blog-index/render.php
src/blocks/blog-index/view.ts
src/blocks/blog-index/edit.tsx
src/blocks/blog-index/style.scss
tests/phpunit/BlockRender/BlogIndexTest.php
```
No other blocks/parts/theme.json/registration/`functions.php`/`inc/*`/`mega-*`.

- [ ] **Step 3: Static cross-check of symbols.**

- `Attrs` (edit.tsx) ↔ block.json attributes ↔ render.php reads: `count` (number/`number`/`(int)`), `categorySlug` (string), `showFilter` (boolean/`boolean`/`(bool)`) — consistent.
- block.json `"viewScript": "file:./view.js"` ↔ `src/blocks/blog-index/view.ts` compiled by wp-scripts to `build/blocks/blog-index/view.js`.
- CSS classes emitted by render.php all have selectors in style.scss; `view.ts` queries only `.starter-blog-index`, `.starter-blog-index__filter button`, `.starter-blog-index__item`, attributes `data-filter`/`data-cat`/`hidden` — all produced by render.php.
- The 3 original BlogIndexTest cases are byte-identical to the pre-plan file (server query path unchanged).

**Post-merge (main checkout `:8888`/`:8889`, controller — NOT a worktree step):** `npm run build` → `npx wp-env run tests-cli --env-cwd=wp-content/themes/wp-starter-theme vendor/bin/phpunit`. Expect: all BlogIndexTest green — the 3 original cases, `test_block_json_has_viewscript_and_showfilter_default`, the featured-image/badge case (`DIR_TESTDATA/images/canola.jpg`), the three filter-bar cases, and the link/meta/readmore case; the rest of the suite unchanged. Playwright unaffected (no e2e changes; unrelated mega-menu failures stay out of scope per the established workstream split).

---

## Self-Review

**1. Spec coverage.** Spec row 10 — "Insights: pill filter + media cards → `starter/blog-index` → Extend: category filter + card style + type badges": Task 2 emits featured-image cards + per-post category badge; Tasks 2+3 deliver the pill filter (server renders the bar from distinct categories, `view.ts` toggles it client-side); Task 5 ports the mockup card/badge/filter styling. Spec line 99 — "Insights filter is presentational at parity": honored exactly — the `WP_Query` is byte-unchanged; the filter has zero server/AJAX effect. No `starter_*_variants` filter because blog-index is single-design, not variant-bearing (YAGNI; the variant-filter pattern stays scoped to hero/pull-quote and the future cta sweep).

**2. Placeholder scan.** No "TBD/TODO/handle edge cases/similar to Task N". Every code step is a complete file; every command has an expected result. The per-category badge colour is explicitly an in-file documented CSS extension point, not a placeholder.

**3. Type consistency.** `Attrs` (edit.tsx) = `{count:number; categorySlug:string; showFilter:boolean}` ↔ block.json attributes (`number`/`string`/`boolean`, `showFilter` default `true`) ↔ render.php (`(int)`/`(string)`/`! isset || (bool)`). `viewScript` value `file:./view.js` ↔ source `view.ts` (wp-scripts emits `view.js`). CSS BEM names emitted by render.php match style.scss selectors and the `view.ts` query selectors/attributes. The 3 original tests are preserved verbatim so the unchanged server query path stays covered.
