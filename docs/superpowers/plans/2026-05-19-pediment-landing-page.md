# Pediment Landing Page Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Ship the Pediment landing page as an auto-loaded parent block pattern composed of existing `starter/*` blocks, wired into `inc/seed.php` so a fresh `wp starter-theme seed` produces a real Pediment homepage (image-free, generic copy) plus sample posts for the Insights band.

**Architecture:** A new header-registered pattern `patterns/pediment-landing.php` (WordPress auto-registers patterns from `patterns/`) composes 8 full-bleed `core/group` bands (`is-style-band-surface`/`is-style-band-navy` + `starter-band`) wrapping the existing blocks. `inc/seed.php` sources the Home page body from the registered pattern (with a safe inline fallback) and idempotently seeds 3 categories + 6 posts so `starter/blog-index` renders fully.

**Tech Stack:** WordPress block patterns (header API), `WP_Block_Patterns_Registry`, dynamic `starter/*` blocks, WP-CLI `starter-theme seed`, PHPUnit (`WP_UnitTestCase`).

**Scope:** Parent repo `wp-starter-theme` only. Create: `patterns/pediment-landing.php`, `tests/phpunit/Patterns/PedimentLandingTest.php`, `tests/phpunit/Seed/SeedSampleContentTest.php`. Modify: `inc/seed.php`. NOT: any block source, `theme.json`, `parts/*`, `inc/patterns.php`, the child repo, About/Contact/Blog seed pages, `mega-*`. No images/binaries; no "Pediment"/consultancy copy in the pattern content.

**Verification constraint:** The execution worktree is NOT wp-env-mounted. Per task: env-independent gates — `php -l`, pattern-header grep, a `parse_blocks` structural check is deferred to PHPUnit, `npm run build` stays green. Full PHPUnit runs POST-MERGE in the parent `:8888`/`:8889` base. **Definition of done:** post-merge PHPUnit green (new PedimentLanding + SeedSampleContent cases + the existing SeedCommand/Patterns suites unchanged); then the controller updates `:8890`'s existing Home page content to the registered pattern and seeds posts for a live view, visually reconciling band markup against `docs/design/pediment-mockup.html` (bounded tweak iteration on the pattern file only).

---

## File Structure

| File | Responsibility | Action |
|---|---|---|
| `patterns/pediment-landing.php` | The 8-band landing composition (header-registered pattern) | Create |
| `inc/seed.php` | Home body from the pattern (helper + fallback); idempotent sample categories/posts | Modify |
| `tests/phpunit/Patterns/PedimentLandingTest.php` | Pattern registered, composition correct, renders, no "Pediment" copy | Create |
| `tests/phpunit/Seed/SeedSampleContentTest.php` | Home=pattern, front page, ≥6 posts/≥2 cats, idempotent | Create |

---

### Task 1: The `pediment-landing` pattern + its guard test

**Files:**
- Create: `patterns/pediment-landing.php`
- Create: `tests/phpunit/Patterns/PedimentLandingTest.php`

- [ ] **Step 1: Write the failing test.** Create `tests/phpunit/Patterns/PedimentLandingTest.php` with EXACTLY:

```php
<?php

class PedimentLandingTest extends WP_UnitTestCase {

	private function pattern() {
		do_action( 'init' );
		return WP_Block_Patterns_Registry::get_instance()->get_registered( 'starter/pediment-landing' );
	}

	public function test_pattern_is_registered_in_starter_category() {
		$p = $this->pattern();
		$this->assertIsArray( $p, 'starter/pediment-landing must be registered' );
		$this->assertContains( 'starter', $p['categories'] );
	}

	public function test_pattern_content_parses_cleanly() {
		$content = $this->pattern()['content'];
		$blocks  = parse_blocks( $content );
		$top     = array_values(
			array_filter(
				$blocks,
				static function ( $b ) {
					return ! empty( $b['blockName'] );
				}
			)
		);
		$this->assertNotEmpty( $top, 'pattern must contain real blocks' );
		foreach ( $top as $b ) {
			$this->assertSame(
				'core/group',
				$b['blockName'],
				'every top-level block must be a band group'
			);
		}
		$this->assertCount( 8, $top, 'exactly 8 full-bleed bands' );
	}

	public function test_pattern_composition_blocks_present() {
		$content = $this->pattern()['content'];
		foreach (
			array(
				'wp:starter/hero',
				'"variant":"stat-card"',
				'wp:starter/feature-grid',
				'wp:starter/feature ',
				'wp:starter/steps',
				'wp:starter/step ',
				'wp:starter/stat ',
				'wp:starter/pull-quote',
				'"variant":"testimonial"',
				'wp:starter/faq ',
				'wp:starter/faq-item',
				'wp:starter/cta ',
				'wp:starter/blog-index',
				'is-style-band-surface',
				'is-style-band-navy',
				'starter-band',
			) as $needle
		) {
			$this->assertStringContainsString( $needle, $content, "pattern must contain: $needle" );
		}
		$this->assertStringNotContainsString(
			'wp:starter/logo-cloud',
			$content,
			'image-only logo-cloud band is intentionally omitted'
		);
	}

	public function test_pattern_renders_without_block_errors() {
		$html = do_blocks( $this->pattern()['content'] );
		$this->assertStringNotContainsString( 'block-editor-block-list', $html );
		$this->assertStringNotContainsString( 'is not registered', $html );
		$this->assertStringContainsString( 'is-variant-stat-card', $html );
		$this->assertStringContainsString( 'is-style-band-navy', $html );
		$this->assertStringContainsString( 'starter-blog-index', $html );
	}

	public function test_pattern_copy_is_rebrandable_no_pediment() {
		$content = $this->pattern()['content'];
		$this->assertFalse(
			stripos( $content, 'pediment' ),
			'pattern content must not ship the fictional Pediment brand voice'
		);
		$this->assertFalse( stripos( $content, 'consultanc' ) );
	}
}
```

- [ ] **Step 2: Run it to verify it fails.**

Run: `npx wp-env run tests-cli --env-cwd=wp-content/themes/wp-starter-theme vendor/bin/phpunit --filter PedimentLandingTest`
Expected (post-merge env only — in the worktree just `php -l` it): FAIL — `starter/pediment-landing must be registered` (pattern file does not exist yet). In the worktree, instead run `php -l tests/phpunit/Patterns/PedimentLandingTest.php` → `No syntax errors detected`.

- [ ] **Step 3: Create the pattern file.** Create `patterns/pediment-landing.php` with EXACTLY:

```php
<?php
/**
 * Title: Pediment Landing
 * Slug: starter/pediment-landing
 * Categories: starter
 * Block Types: core/post-content
 * Description: Full Pediment landing page — hero, services, process, stats, testimonial, FAQ, CTA, and insights bands.
 */
// phpcs:ignoreFile -- block pattern markup
?>
<!-- wp:group {"align":"full","className":"starter-band is-style-band-surface","layout":{"type":"constrained"}} -->
<div class="wp-block-group alignfull starter-band is-style-band-surface">
<!-- wp:starter/hero {"variant":"stat-card","align":"wide","eyebrow":"What we do","headline":"A clear headline that states your core offer","subheadline":"One supporting sentence that explains the value in plain, specific language.","ctaText":"Get started","ctaUrl":"/contact","secondaryText":"Learn more","secondaryUrl":"/about","ticks":["A concrete proof point","Another concrete proof point","A third proof point"],"statValue":"98%","statText":"A short outcome metric caption","metrics":[{"value":"12+","label":"Metric one"},{"value":"4.9","label":"Metric two"}]} /-->
</div>
<!-- /wp:group -->

<!-- wp:group {"align":"full","className":"starter-band is-style-band-surface","layout":{"type":"constrained"}} -->
<div class="wp-block-group alignfull starter-band is-style-band-surface">
<!-- wp:starter/feature-grid {"align":"wide"} -->
<!-- wp:starter/feature {"icon":"trend-up","title":"First service","text":"One concise sentence describing this service and the outcome it delivers.","linkText":"Learn more","linkUrl":"/about"} /-->
<!-- wp:starter/feature {"icon":"gear","title":"Second service","text":"One concise sentence describing this service and the outcome it delivers.","linkText":"Learn more","linkUrl":"/about"} /-->
<!-- wp:starter/feature {"icon":"stack","title":"Third service","text":"One concise sentence describing this service and the outcome it delivers.","linkText":"Learn more","linkUrl":"/about"} /-->
<!-- wp:starter/feature {"icon":"check-circle","title":"Fourth service","text":"One concise sentence describing this service and the outcome it delivers.","linkText":"Learn more","linkUrl":"/about"} /-->
<!-- /wp:starter/feature-grid -->
</div>
<!-- /wp:group -->

<!-- wp:group {"align":"full","className":"starter-band is-style-band-surface","layout":{"type":"constrained"}} -->
<div class="wp-block-group alignfull starter-band is-style-band-surface">
<!-- wp:starter/steps {"align":"wide"} -->
<!-- wp:starter/step {"title":"Discover","text":"What happens in this step, in one clear sentence."} /-->
<!-- wp:starter/step {"title":"Design","text":"What happens in this step, in one clear sentence."} /-->
<!-- wp:starter/step {"title":"Build","text":"What happens in this step, in one clear sentence."} /-->
<!-- wp:starter/step {"title":"Launch","text":"What happens in this step, in one clear sentence."} /-->
<!-- /wp:starter/steps -->
</div>
<!-- /wp:group -->

<!-- wp:group {"align":"full","className":"starter-band is-style-band-navy","layout":{"type":"constrained"}} -->
<div class="wp-block-group alignfull starter-band is-style-band-navy">
<!-- wp:columns {"align":"wide"} -->
<div class="wp-block-columns alignwide">
<!-- wp:column -->
<div class="wp-block-column"><!-- wp:starter/stat {"value":"120+","label":"Projects shipped"} /--></div>
<!-- /wp:column -->
<!-- wp:column -->
<div class="wp-block-column"><!-- wp:starter/stat {"value":"18","label":"Countries"} /--></div>
<!-- /wp:column -->
<!-- wp:column -->
<div class="wp-block-column"><!-- wp:starter/stat {"value":"30","label":"Avg. years experience"} /--></div>
<!-- /wp:column -->
<!-- wp:column -->
<div class="wp-block-column"><!-- wp:starter/stat {"value":"94%","label":"Repeat clients"} /--></div>
<!-- /wp:column -->
</div>
<!-- /wp:columns -->
</div>
<!-- /wp:group -->

<!-- wp:group {"align":"full","className":"starter-band is-style-band-surface","layout":{"type":"constrained"}} -->
<div class="wp-block-group alignfull starter-band is-style-band-surface">
<!-- wp:starter/pull-quote {"variant":"testimonial","align":"wide","quote":"A short, specific endorsement written in the customer&rsquo;s own words.","authorName":"A. Customer","authorRole":"Title, Company"} /-->
</div>
<!-- /wp:group -->

<!-- wp:group {"align":"full","className":"starter-band is-style-band-surface","layout":{"type":"constrained"}} -->
<div class="wp-block-group alignfull starter-band is-style-band-surface">
<!-- wp:starter/faq {"align":"wide"} -->
<!-- wp:starter/faq-item {"question":"A question a prospective customer would ask?","answer":"A clear, concise answer in one or two sentences."} /-->
<!-- wp:starter/faq-item {"question":"Another common question?","answer":"A clear, concise answer in one or two sentences."} /-->
<!-- wp:starter/faq-item {"question":"A question about scope or process?","answer":"A clear, concise answer in one or two sentences."} /-->
<!-- wp:starter/faq-item {"question":"A question about timelines?","answer":"A clear, concise answer in one or two sentences."} /-->
<!-- wp:starter/faq-item {"question":"A question about what makes you different?","answer":"A clear, concise answer in one or two sentences."} /-->
<!-- /wp:starter/faq -->
</div>
<!-- /wp:group -->

<!-- wp:group {"align":"full","className":"starter-band is-style-band-navy","layout":{"type":"constrained"}} -->
<div class="wp-block-group alignfull starter-band is-style-band-navy">
<!-- wp:starter/cta {"align":"wide","title":"Ready to start?","body":"One sentence inviting the visitor to take the next step.","primaryText":"Get in touch","primaryUrl":"/contact","secondaryText":"Learn more","secondaryUrl":"/about"} /-->
</div>
<!-- /wp:group -->

<!-- wp:group {"align":"full","className":"starter-band is-style-band-surface","layout":{"type":"constrained"}} -->
<div class="wp-block-group alignfull starter-band is-style-band-surface">
<!-- wp:starter/blog-index {"count":6,"showFilter":true,"align":"wide"} /-->
</div>
<!-- /wp:group -->
```

- [ ] **Step 4: Verify (env-independent).**

Run: `php -l patterns/pediment-landing.php`
Expected: `No syntax errors detected`

Run: `grep -c "wp:group" patterns/pediment-landing.php`
Expected: `16` (8 opening + 8 closing band group comments)

Run: `grep -aci "pediment\|consultanc" patterns/pediment-landing.php` — note this counts the header `Title: Pediment Landing` / `Slug: starter/pediment-landing` / `Description` lines (above the `?>`), which are metadata, not pattern content. The PHPUnit `test_pattern_copy_is_rebrandable_no_pediment` asserts on `['content']` (everything AFTER `?>`), which must contain neither word. Manually confirm no "Pediment"/"consultancy" appears below the `?>` line.

Run: `npm run build`
Expected: compiles (no JS change; must stay green).

Static trace: `do_action('init')` registers theme `patterns/` files → `get_registered('starter/pediment-landing')` returns the array; `['content']` is everything after `?>`; `parse_blocks` yields 8 `core/group` top-level blocks; needles all present; `do_blocks` renders the dynamic `starter/*` children (all registered from `build/blocks/*`) → `is-variant-stat-card`, `is-style-band-navy`, `starter-blog-index` appear, no unregistered-block markup.

- [ ] **Step 5: Commit**

```bash
git add patterns/pediment-landing.php tests/phpunit/Patterns/PedimentLandingTest.php
git commit -m "feat(pattern): Pediment landing — 8-band composition + guard test"
```

---

### Task 2: Seed the Home page from the pattern

**Files:**
- Modify: `inc/seed.php`
- Create: `tests/phpunit/Seed/SeedSampleContentTest.php`

- [ ] **Step 1: Write the failing test.** Create `tests/phpunit/Seed/SeedSampleContentTest.php` with EXACTLY:

```php
<?php

class SeedSampleContentTest extends WP_UnitTestCase {

	public function set_up(): void {
		parent::set_up();
		foreach ( get_posts( array( 'post_type' => array( 'page', 'post' ), 'numberposts' => -1, 'post_status' => 'any' ) ) as $p ) {
			wp_delete_post( $p->ID, true );
		}
		foreach ( get_terms( array( 'taxonomy' => 'category', 'hide_empty' => false ) ) as $t ) {
			if ( 'uncategorized' !== $t->slug ) {
				wp_delete_term( $t->term_id, 'category' );
			}
		}
	}

	public function test_home_content_is_the_pattern() {
		do_action( 'init' );
		starter_seed_run();
		$home = get_page_by_path( 'home' );
		$this->assertInstanceOf( WP_Post::class, $home );
		$expected = starter_pediment_landing_content();
		$this->assertNotEmpty( $expected );
		$this->assertSame( $expected, $home->post_content );
		$this->assertStringContainsString( 'wp:starter/hero', $home->post_content );
	}

	public function test_static_front_page_is_home() {
		do_action( 'init' );
		starter_seed_run();
		$this->assertSame( 'page', get_option( 'show_on_front' ) );
		$this->assertSame(
			(int) get_page_by_path( 'home' )->ID,
			(int) get_option( 'page_on_front' )
		);
	}

	public function test_pattern_fallback_is_non_empty_string() {
		// Even if the pattern registry is empty, seeding must never write an
		// empty Home; the fallback returns valid block markup.
		$this->assertNotEmpty( starter_pediment_landing_content() );
		$this->assertStringContainsString( 'wp:starter/', starter_pediment_landing_content() );
	}
}
```

- [ ] **Step 2: Run to verify it fails.**

Worktree: `php -l tests/phpunit/Seed/SeedSampleContentTest.php` → `No syntax errors detected`. Post-merge env: `--filter SeedSampleContentTest` → FAIL — `Call to undefined function starter_pediment_landing_content()`.

- [ ] **Step 3: Add the pattern-content helper + wire Home.** In `inc/seed.php`, immediately AFTER the closing `}` of `function starter_seed_run()` (currently the last function in the file, ending at the line with the final `}` after `starter_nav_seed_entity()`), append this new function:

```php

/**
 * The Pediment landing pattern content for the Home page.
 *
 * Reads the registered `starter/pediment-landing` pattern. Falls back to a
 * minimal valid block composition so seeding never writes an empty Home even
 * if patterns are unavailable.
 *
 * @return string Block markup.
 */
function starter_pediment_landing_content(): string {
	if ( class_exists( 'WP_Block_Patterns_Registry' ) ) {
		$pattern = WP_Block_Patterns_Registry::get_instance()->get_registered( 'starter/pediment-landing' );
		if ( is_array( $pattern ) && ! empty( $pattern['content'] ) ) {
			return (string) $pattern['content'];
		}
	}
	return '<!-- wp:starter/hero {"variant":"centered","headline":"Welcome","subheadline":"A short benefit-led promise.","ctaText":"Get started","ctaUrl":"/contact","align":"wide"} /-->' .
		'<!-- wp:starter/cta {"title":"Ready to start?","body":"Tell us about your project.","primaryText":"Contact us","primaryUrl":"/contact","align":"wide"} /-->' .
		'<!-- wp:starter/blog-index {"count":3,"align":"wide"} /-->';
}
```

- [ ] **Step 4: Point the `home` seed entry at the helper.** In `inc/seed.php`, in the `$pages` array, replace the entire `'home' => array( … ),` entry (the `home` key whose `content` is the inline `hero+cta+blog-index` string) with EXACTLY:

```php
		'home'    => array(
			'title'   => 'Home',
			'content' => starter_pediment_landing_content(),
		),
```

Leave the `about`, `contact`, `blog` entries byte-unchanged.

- [ ] **Step 5: Verify (env-independent).**

Run: `php -l inc/seed.php`
Expected: `No syntax errors detected`

Run: `git diff inc/seed.php` — confirm: one new `starter_pediment_landing_content()` function appended; the `home` entry now calls it; `about`/`contact`/`blog`/front-page/nav logic unchanged.

Static trace: `test_pattern_fallback_is_non_empty_string` — registry has the pattern after `do_action('init')`; even without it the fallback string is returned (non-empty, contains `wp:starter/`). `test_home_content_is_the_pattern` — `do_action('init')` registers the pattern; `starter_seed_run()` inserts Home with `starter_pediment_landing_content()` (= pattern content); equals helper output, contains `wp:starter/hero`. `test_static_front_page_is_home` — unchanged front-page wiring sets `show_on_front=page`, `page_on_front=home`.

- [ ] **Step 6: Commit**

```bash
git add inc/seed.php tests/phpunit/Seed/SeedSampleContentTest.php
git commit -m "feat(seed): Home page from the pediment-landing pattern (safe fallback)"
```

---

### Task 3: Idempotent sample categories + posts for the Insights band

**Files:**
- Modify: `inc/seed.php`
- Modify: `tests/phpunit/Seed/SeedSampleContentTest.php`

- [ ] **Step 1: Add the failing assertions.** Append these THREE methods to the `SeedSampleContentTest` class in `tests/phpunit/Seed/SeedSampleContentTest.php` (immediately before the final closing `}` of the class):

```php
	public function test_seed_creates_sample_posts_in_multiple_categories() {
		do_action( 'init' );
		starter_seed_run();
		$posts = get_posts( array( 'post_type' => 'post', 'numberposts' => -1, 'post_status' => 'publish' ) );
		$this->assertGreaterThanOrEqual( 6, count( $posts ), 'expect >= 6 sample posts' );
		$cats = wp_get_object_terms( wp_list_pluck( $posts, 'ID' ), 'category' );
		$slugs = array_unique( wp_list_pluck( $cats, 'slug' ) );
		$this->assertGreaterThanOrEqual( 2, count( $slugs ), 'posts span >= 2 categories' );
		$this->assertContains( 'insights', $slugs );
	}

	public function test_sample_posts_are_idempotent() {
		do_action( 'init' );
		starter_seed_run();
		$first = count( get_posts( array( 'post_type' => 'post', 'numberposts' => -1, 'post_status' => 'publish' ) ) );
		starter_seed_run();
		$second = count( get_posts( array( 'post_type' => 'post', 'numberposts' => -1, 'post_status' => 'publish' ) ) );
		$this->assertSame( $first, $second, 'second seed must not duplicate posts' );
	}

	public function test_sample_posts_have_no_pediment_copy() {
		do_action( 'init' );
		starter_seed_run();
		foreach ( get_posts( array( 'post_type' => 'post', 'numberposts' => -1, 'post_status' => 'publish' ) ) as $p ) {
			$this->assertFalse( stripos( $p->post_title . ' ' . $p->post_content . ' ' . $p->post_excerpt, 'pediment' ) );
		}
	}
```

- [ ] **Step 2: Run to verify it fails.**

Worktree: `php -l tests/phpunit/Seed/SeedSampleContentTest.php` → clean. Post-merge: `--filter SeedSampleContentTest` → the 3 new methods FAIL (no sample posts created yet).

- [ ] **Step 3: Add the sample-content seeder.** In `inc/seed.php`, append this function AFTER `starter_pediment_landing_content()`:

```php

/**
 * Idempotently create sample categories + posts so the Insights band
 * (starter/blog-index) renders fully. Skips anything that already exists.
 *
 * @return void
 */
function starter_seed_sample_posts(): void {
	$categories = array(
		'insights'  => 'Insights',
		'briefings' => 'Briefings',
		'notes'     => 'Notes',
	);
	$cat_ids = array();
	foreach ( $categories as $slug => $name ) {
		$term = get_term_by( 'slug', $slug, 'category' );
		if ( $term ) {
			$cat_ids[ $slug ] = (int) $term->term_id;
			continue;
		}
		$created = wp_insert_term( $name, 'category', array( 'slug' => $slug ) );
		if ( ! is_wp_error( $created ) ) {
			$cat_ids[ $slug ] = (int) $created['term_id'];
		}
	}

	$posts = array(
		array( 'slug' => 'sample-insight-one',   'title' => 'A practical insight on getting started', 'cat' => 'insights' ),
		array( 'slug' => 'sample-insight-two',   'title' => 'What good looks like, in plain terms',     'cat' => 'insights' ),
		array( 'slug' => 'sample-briefing-one',  'title' => 'A short briefing on a common decision',    'cat' => 'briefings' ),
		array( 'slug' => 'sample-briefing-two',  'title' => 'Trade-offs worth weighing early',          'cat' => 'briefings' ),
		array( 'slug' => 'sample-note-one',      'title' => 'A quick note on process',                  'cat' => 'notes' ),
		array( 'slug' => 'sample-note-two',      'title' => 'A quick note on outcomes',                 'cat' => 'notes' ),
	);
	foreach ( $posts as $p ) {
		if ( get_page_by_path( $p['slug'], OBJECT, 'post' ) ) {
			continue;
		}
		$post_id = wp_insert_post(
			array(
				'post_type'    => 'post',
				'post_status'  => 'publish',
				'post_title'   => $p['title'],
				'post_name'    => $p['slug'],
				'post_excerpt' => 'A one-sentence summary of this sample article, ready to be replaced.',
				'post_content' => '<!-- wp:paragraph --><p>Replace this sample article with your own writing.</p><!-- /wp:paragraph -->',
			),
			true
		);
		if ( ! is_wp_error( $post_id ) && isset( $cat_ids[ $p['cat'] ] ) ) {
			wp_set_post_categories( (int) $post_id, array( $cat_ids[ $p['cat'] ] ) );
		}
	}
}
```

- [ ] **Step 4: Call the seeder from `starter_seed_run()`.** In `inc/seed.php`, inside `function starter_seed_run()`, add the call immediately BEFORE the `if ( function_exists( 'starter_nav_seed_entity' ) ) {` line so it ends as:

```php
	if ( isset( $page_ids['blog'] ) ) {
		update_option( 'page_for_posts', $page_ids['blog'] );
	}

	starter_seed_sample_posts();

	if ( function_exists( 'starter_nav_seed_entity' ) ) {
		starter_nav_seed_entity();
	}
}
```

(Only the `starter_seed_sample_posts();` line plus its surrounding blank line is added — nothing else in `starter_seed_run()` changes.)

- [ ] **Step 5: Verify (env-independent).**

Run: `php -l inc/seed.php`
Expected: `No syntax errors detected`

Run: `git diff inc/seed.php` — confirm only: the new `starter_seed_sample_posts()` function and the single `starter_seed_sample_posts();` call line added; everything else unchanged.

Static trace: first `starter_seed_run()` → 3 categories created, 6 posts created + categorized (`insights` among them) ⇒ `test_seed_creates_sample_posts_in_multiple_categories` passes; second run → all slugs exist, `get_page_by_path(...,'post')` truthy → `continue`, zero new posts ⇒ `test_sample_posts_are_idempotent` passes; titles/excerpt/content contain no "pediment" ⇒ `test_sample_posts_have_no_pediment_copy` passes. Existing `SeedCommandTest` (pages, brand, front page, page idempotency) is unaffected — only posts/categories are added; its `set_up` deletes pages only, and the new posts do not change page counts.

- [ ] **Step 6: Commit**

```bash
git add inc/seed.php tests/phpunit/Seed/SeedSampleContentTest.php
git commit -m "feat(seed): idempotent sample categories + posts for the Insights band"
```

---

### Task 4: Final integration verification

**Files:** None modified — verification only.

- [ ] **Step 1: Build + lint clean.**

Run: `npm run build` → compiles. `php -l patterns/pediment-landing.php && php -l inc/seed.php` → both clean.

- [ ] **Step 2: Scope diff.**

Run: `git diff <branch-base>..HEAD --name-only`
Expected — ONLY:
```
patterns/pediment-landing.php
inc/seed.php
tests/phpunit/Patterns/PedimentLandingTest.php
tests/phpunit/Seed/SeedSampleContentTest.php
```
No block source, `theme.json`, `parts/*`, `inc/patterns.php`, `mega-*`, or child-repo path.

- [ ] **Step 3: Static cross-check.**

- Pattern slug `starter/pediment-landing` is identical in: the pattern header `Slug:`, `PedimentLandingTest`, and `starter_pediment_landing_content()`.
- `starter_pediment_landing_content()` and `starter_seed_sample_posts()` are defined once in `inc/seed.php`; the former is called by the `home` seed entry and `SeedSampleContentTest`; the latter is called once inside `starter_seed_run()`.
- The 8 band groups in the pattern all carry `starter-band` + one of `is-style-band-surface`/`is-style-band-navy`; navy bands are exactly the Stats and CTA bands (matching the mockup); blocks referenced (`hero`,`feature-grid`/`feature`,`steps`/`step`,`stat`,`pull-quote`,`faq`/`faq-item`,`cta`,`blog-index`) are all registered from `build/blocks/*`.
- `SeedCommandTest` is untouched and still green (pages/brand/front-page/idempotency unaffected by added posts).

**Post-merge (parent `:8888`/`:8889`, controller — NOT a worktree step):**
1. `npm run build` → `npx wp-env run cli wp theme activate wp-starter-theme` → full `npx wp-env run tests-cli --env-cwd=wp-content/themes/wp-starter-theme vendor/bin/phpunit`. Expect all green: new PedimentLanding (5) + SeedSampleContent (6) cases, existing SeedCommand/Patterns/rest unchanged.
2. **Live view on `:8890`** (explicit one-off per the spec — `:8890` already has a stub Home so the idempotent seed will not replace it): from the child env, refresh that env's Home to the pattern and seed posts —
   `npx wp-env run cli wp eval 'do_action("init"); $c=starter_pediment_landing_content(); $h=get_page_by_path("home"); wp_update_post(["ID"=>$h->ID,"post_content"=>$c]); starter_seed_sample_posts(); update_option("show_on_front","page"); update_option("page_on_front",$h->ID); echo "ok";'`
   then open `http://localhost:8890/` and compare against `docs/design/pediment-mockup.html`.
3. **Bounded band reconciliation:** if a band's width/padding/background does not match the mockup (the likely candidates: `core/group` `layout` type, the inner block `align`, or the `core/columns` stat row), adjust ONLY `patterns/pediment-landing.php`, re-run the `:8890` eval above, and re-check. Cap at small markup tweaks to the pattern file (no block/CSS changes — blocks are final from Plans 1–7). Re-run PedimentLandingTest after any change.

---

## Self-Review

**1. Spec coverage.** Delivery (auto-loaded pattern + seed-sourced Home, safe fallback) → Task 1 + Task 2. Image-free, 8 bands, generic copy, no logo-cloud → Task 1 pattern + `test_pattern_copy_is_rebrandable_no_pediment` + `test_pattern_composition_blocks_present` (asserts no `logo-cloud`). Sample posts/3 categories idempotent → Task 3. Skip-if-exists preserved + explicit `:8890` one-off → Task 2 (the `$pages` loop's existing skip is untouched) + Task 4 post-merge step. Tests (pattern registered/parses/renders/no-Pediment; seed home=pattern/front-page/posts/idempotent) → PedimentLandingTest + SeedSampleContentTest. Scope boundaries → Task 4 Step 2. Covered.

**2. Placeholder scan.** No "TBD/TODO/handle X". Every file is given in full; every command has expected output; generic copy strings are concrete (and deliberately rebrandable, not "Pediment"). The post-merge band reconciliation is explicitly bounded (pattern-file-only tweaks) — not an open-ended placeholder.

**3. Type/contract consistency.** `starter/pediment-landing` slug identical across pattern header, both helpers' lookups, and tests. `starter_pediment_landing_content()` / `starter_seed_sample_posts()` defined once, called as described, signatures (`: string` / `: void`) consistent with the file's existing `: void` style. Category slug `insights` asserted in both the seeder and `test_seed_creates_sample_posts_in_multiple_categories`. Band class names (`starter-band`, `is-style-band-surface`, `is-style-band-navy`) consistent between the pattern, `theme.css`, the registered `core/group` block styles, and the tests.
