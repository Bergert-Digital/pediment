# Testimonial Grid Block Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Give the AI composer a dedicated `pediment/testimonial-grid` + `pediment/testimonial` block pair that renders customer quotes as a responsive 2-up card grid, and remove the now-redundant `testimonial` variant from `pediment/pull-quote`.

**Architecture:** Mirror the existing `pediment/feature-grid` + `pediment/feature` parent/child InnerBlocks pattern. The grid is a server-rendered wrapper (`render.php` echoes inner content); each child card is server-rendered from attributes (`quote`, `authorName`, `authorRole`, `avatarId`). Styling lives in per-block `style.scss` using theme CSS custom properties. The composer learns the block from its `block.json` description (auto-exposed via `SchemaBuilder`) plus one guidance line in `pediment-ai`'s `PromptBuilder`.

**Tech Stack:** WordPress block theme (`apiVersion: 3`), `@wordpress/scripts` (webpack + SCSS), TypeScript/TSX editor scripts, PHP `render.php` dynamic rendering, PHPUnit (`WP_UnitTestCase`) for render tests, Playwright for e2e.

**Repos:** `pediment` (parent theme — primary), `pediment-ai` (composer prompt). Both are working directories.

---

## Key reference facts (read before starting)

- **Block registration is automatic.** `inc/register-blocks.php` registers every dir in `build/blocks/` on `init`. A new block under `src/blocks/<name>/` is picked up once `npm run build` regenerates `build/blocks/` + `build/blocks-manifest.php`. No manual wiring.
- **Build command:** `npm run build` (runs `wp-scripts build` then `build-blocks-manifest`). PHPUnit loads blocks from `build/blocks/`, so **you must build before running PHP render tests.**
- **Run PHP tests:** `npx wp-env run tests-wordpress --env-cwd=wp-content/themes/pediment ./vendor/bin/phpunit`. Filter a single class with `--filter ClassName`.
- **Parent/child precedent files to copy from:**
  - `src/blocks/feature-grid/{block.json,render.php,edit.tsx,index.tsx,style.scss}`
  - `src/blocks/feature/{block.json,render.php,edit.tsx,index.tsx}`
- **Icon helper:** `pediment_icon( $slug )` returns inline SVG markup (used by `feature/render.php`). Not needed here, but available.
- **`wp-env` dev env** runs from the child theme on port 8890 (canonical local env). Visual verification happens there.

---

## Task 1: Scaffold the `pediment/testimonial` child block (failing render test first)

**Files:**
- Create: `src/blocks/testimonial/block.json`
- Create: `src/blocks/testimonial/render.php`
- Create: `src/blocks/testimonial/index.tsx`
- Create: `src/blocks/testimonial/edit.tsx`
- Test: `tests/phpunit/BlockRender/TestimonialGridTest.php`

- [ ] **Step 1: Write the failing render test.** This task tests the child block in isolation (rendered standalone via `do_blocks`). The grid-wrapper tests are added in Task 2. A `parent`-locked block still renders fine standalone in the PHPUnit harness, so testing the child alone is valid here.

Create `tests/phpunit/BlockRender/TestimonialGridTest.php`:

```php
<?php

class TestimonialGridTest extends WP_UnitTestCase {
	private function child( array $attrs ): string {
		return do_blocks( '<!-- wp:pediment/testimonial ' . wp_json_encode( $attrs ) . ' /-->' );
	}

	public function test_child_renders_quote_name_and_role() {
		$html = $this->child( array(
			'quote'      => 'They stayed until it worked.',
			'authorName' => 'Sarah Klein',
			'authorRole' => 'Group COO, Vantage Industries',
		) );
		$this->assertStringContainsString( 'starter-testimonial', $html );
		$this->assertStringContainsString( '<figure', $html );
		$this->assertStringContainsString( 'starter-testimonial__quote', $html );
		$this->assertStringContainsString( 'They stayed until it worked.', $html );
		$this->assertStringContainsString( 'starter-testimonial__name', $html );
		$this->assertStringContainsString( 'Sarah Klein', $html );
		$this->assertStringContainsString( 'starter-testimonial__role', $html );
		$this->assertStringContainsString( 'Group COO, Vantage Industries', $html );
	}

	public function test_child_renders_nothing_when_quote_empty() {
		$html = $this->child( array( 'quote' => '', 'authorName' => 'No One' ) );
		$this->assertStringNotContainsString( 'starter-testimonial', $html );
	}

	public function test_child_shows_initials_when_no_avatar() {
		$html = $this->child( array(
			'quote'      => 'Great partner.',
			'authorName' => 'Markus Roth',
		) );
		$this->assertStringContainsString( 'starter-testimonial__initials', $html );
		$this->assertStringContainsString( '>MR<', $html );
		$this->assertStringNotContainsString( 'starter-testimonial__avatar', $html );
	}

	public function test_child_renders_avatar_when_id_set() {
		$att = self::factory()->attachment->create_upload_object( DIR_TESTDATA . '/images/canola.jpg' );
		$html = $this->child( array(
			'quote'      => 'With a face.',
			'authorName' => 'Has Pic',
			'avatarId'   => $att,
		) );
		$this->assertStringContainsString( 'starter-testimonial__avatar', $html );
		$this->assertStringContainsString( '<img', $html );
		$this->assertStringNotContainsString( 'starter-testimonial__initials', $html );
		wp_delete_attachment( $att, true );
	}

	public function test_child_omits_byline_when_no_name_or_role() {
		$html = $this->child( array( 'quote' => 'Anonymous but mighty.' ) );
		$this->assertStringContainsString( 'starter-testimonial__quote', $html );
		$this->assertStringNotContainsString( 'starter-testimonial__by', $html );
	}

	public function test_child_block_json_has_parent_and_attributes() {
		$path = dirname( __DIR__, 3 ) . '/src/blocks/testimonial/block.json';
		$this->assertFileIsReadable( $path );
		$data = json_decode( file_get_contents( $path ), true );
		$this->assertSame( array( 'pediment/testimonial-grid' ), $data['parent'] );
		$this->assertFalse( $data['supports']['inserter'] );
		foreach ( array( 'quote', 'authorName', 'authorRole', 'avatarId' ) as $attr ) {
			$this->assertArrayHasKey( $attr, $data['attributes'] );
		}
	}
}
```

- [ ] **Step 2: Build, then run the test to verify it fails**

Run:
```bash
cd /Users/jonas/Entwicklung/pediment
npm run build
npx wp-env run tests-wordpress --env-cwd=wp-content/themes/pediment ./vendor/bin/phpunit --filter TestimonialGridTest
```
Expected: FAIL — block `pediment/testimonial` not registered, so assertions on `starter-testimonial` markup fail.

- [ ] **Step 3: Create `src/blocks/testimonial/block.json`**

```json
{
	"$schema": "https://schemas.wp.org/trunk/block.json",
	"apiVersion": 3,
	"name": "pediment/testimonial",
	"title": "Testimonial",
	"category": "pediment",
	"description": "A single customer testimonial card: quote, author name, role, and optional avatar.",
	"parent": [ "pediment/testimonial-grid" ],
	"textdomain": "pediment",
	"supports": { "html": false, "inserter": false },
	"attributes": {
		"quote": { "type": "string", "default": "" },
		"authorName": { "type": "string", "default": "" },
		"authorRole": { "type": "string", "default": "" },
		"avatarId": { "type": "integer", "default": 0 }
	},
	"editorScript": "file:./index.js",
	"render": "file:./render.php"
}
```

- [ ] **Step 4: Create `src/blocks/testimonial/render.php`**

```php
<?php
/**
 * Server-side render for pediment/testimonial.
 *
 * @var array $attributes
 */

$quote       = isset( $attributes['quote'] ) ? (string) $attributes['quote'] : '';
$author_name = isset( $attributes['authorName'] ) ? (string) $attributes['authorName'] : '';
$author_role = isset( $attributes['authorRole'] ) ? (string) $attributes['authorRole'] : '';
$avatar_id   = isset( $attributes['avatarId'] ) ? (int) $attributes['avatarId'] : 0;

if ( '' === $quote ) {
	return '';
}

/**
 * Build up-to-two-letter initials from the author name (first letter of the
 * first two words). Returns '' when no name is set.
 */
$initials = '';
if ( '' !== $author_name ) {
	$words = preg_split( '/\s+/', trim( wp_strip_all_tags( $author_name ) ) );
	foreach ( array_slice( $words, 0, 2 ) as $word ) {
		$first = function_exists( 'mb_substr' ) ? mb_substr( $word, 0, 1 ) : substr( $word, 0, 1 );
		$initials .= function_exists( 'mb_strtoupper' ) ? mb_strtoupper( $first ) : strtoupper( $first );
	}
}

$has_byline = '' !== $author_name || '' !== $author_role;
$wrapper    = get_block_wrapper_attributes( array( 'class' => 'starter-testimonial' ) );

ob_start();
?>
<figure <?php echo $wrapper; // phpcs:ignore WordPress.Security.EscapeOutput ?>>
	<span class="starter-testimonial__mark" aria-hidden="true">&ldquo;</span>
	<blockquote class="starter-testimonial__quote"><?php echo wp_kses_post( $quote ); ?></blockquote>
	<?php if ( $has_byline ) : ?>
		<figcaption class="starter-testimonial__by">
			<?php if ( $avatar_id ) : ?>
				<?php echo wp_get_attachment_image( $avatar_id, 'thumbnail', false, array( 'class' => 'starter-testimonial__avatar' ) ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
			<?php elseif ( '' !== $initials ) : ?>
				<span class="starter-testimonial__initials" aria-hidden="true"><?php echo esc_html( $initials ); ?></span>
			<?php endif; ?>
			<?php if ( '' !== $author_name || '' !== $author_role ) : ?>
				<div class="starter-testimonial__meta">
					<?php if ( '' !== $author_name ) : ?>
						<b class="starter-testimonial__name"><?php echo wp_kses_post( $author_name ); ?></b>
					<?php endif; ?>
					<?php if ( '' !== $author_role ) : ?>
						<span class="starter-testimonial__role"><?php echo wp_kses_post( $author_role ); ?></span>
					<?php endif; ?>
				</div>
			<?php endif; ?>
		</figcaption>
	<?php endif; ?>
</figure>
<?php
echo ob_get_clean();
```

- [ ] **Step 5: Create `src/blocks/testimonial/index.tsx`**

```tsx
import { registerBlockType } from '@wordpress/blocks';
import metadata from './block.json';
import Edit from './edit';

registerBlockType( metadata.name, { edit: Edit, save: () => null } );
```

- [ ] **Step 6: Create `src/blocks/testimonial/edit.tsx`**

```tsx
import { __ } from '@wordpress/i18n';
import {
	useBlockProps,
	RichText,
	InspectorControls,
	MediaUpload,
} from '@wordpress/block-editor';
import { PanelBody, Button } from '@wordpress/components';

type Attrs = {
	quote: string;
	authorName: string;
	authorRole: string;
	avatarId: number;
};

export default function Edit( {
	attributes,
	setAttributes,
}: {
	attributes: Attrs;
	setAttributes: ( a: Partial< Attrs > ) => void;
} ) {
	const blockProps = useBlockProps( { className: 'starter-testimonial' } );
	return (
		<>
			<InspectorControls>
				<PanelBody title={ __( 'Testimonial', 'pediment' ) }>
					<MediaUpload
						allowedTypes={ [ 'image' ] }
						onSelect={ ( media: any ) =>
							setAttributes( { avatarId: media.id } )
						}
						render={ ( { open }: { open: () => void } ) => (
							<Button variant="secondary" onClick={ open }>
								{ attributes.avatarId
									? __( 'Replace avatar', 'pediment' )
									: __( 'Pick avatar (optional)', 'pediment' ) }
							</Button>
						) }
					/>
				</PanelBody>
			</InspectorControls>
			<figure { ...blockProps }>
				<span className="starter-testimonial__mark" aria-hidden="true">
					&ldquo;
				</span>
				<RichText
					tagName="blockquote"
					className="starter-testimonial__quote"
					value={ attributes.quote }
					onChange={ ( v ) => setAttributes( { quote: v } ) }
					placeholder={ __( 'Quote…', 'pediment' ) }
				/>
				<figcaption className="starter-testimonial__by">
					<div className="starter-testimonial__meta">
						<RichText
							tagName="b"
							className="starter-testimonial__name"
							value={ attributes.authorName }
							onChange={ ( v ) =>
								setAttributes( { authorName: v } )
							}
							placeholder={ __( 'Name…', 'pediment' ) }
						/>
						<RichText
							tagName="span"
							className="starter-testimonial__role"
							value={ attributes.authorRole }
							onChange={ ( v ) =>
								setAttributes( { authorRole: v } )
							}
							placeholder={ __( 'Role, Company…', 'pediment' ) }
						/>
					</div>
				</figcaption>
			</figure>
		</>
	);
}
```

- [ ] **Step 7: Build and run the test — child assertions pass, parent-dependent ones still fail**

Run:
```bash
npm run build
npx wp-env run tests-wordpress --env-cwd=wp-content/themes/pediment ./vendor/bin/phpunit --filter TestimonialGridTest
```
Expected: All `test_child_*` tests PASS. (No grid test yet — that lands in Task 2.)

- [ ] **Step 8: Commit**

```bash
git add src/blocks/testimonial/ tests/phpunit/BlockRender/TestimonialGridTest.php build/
git commit -m "feat(blocks): add pediment/testimonial child block"
```

---

## Task 2: Add the `pediment/testimonial-grid` parent block

**Files:**
- Create: `src/blocks/testimonial-grid/block.json`
- Create: `src/blocks/testimonial-grid/render.php`
- Create: `src/blocks/testimonial-grid/index.tsx`
- Create: `src/blocks/testimonial-grid/edit.tsx`
- Test: `tests/phpunit/BlockRender/TestimonialGridTest.php` (append grid test)

- [ ] **Step 1: Append the failing grid test** to `tests/phpunit/BlockRender/TestimonialGridTest.php` (inside the class):

```php
	public function test_grid_wraps_three_testimonial_cards() {
		$html = do_blocks(
			'<!-- wp:pediment/testimonial-grid -->' .
			'<!-- wp:pediment/testimonial {"quote":"First quote.","authorName":"Aa Bb","authorRole":"CEO, One"} /-->' .
			'<!-- wp:pediment/testimonial {"quote":"Second quote.","authorName":"Cc Dd","authorRole":"CTO, Two"} /-->' .
			'<!-- wp:pediment/testimonial {"quote":"Third quote.","authorName":"Ee Ff","authorRole":"COO, Three"} /-->' .
			'<!-- /wp:pediment/testimonial-grid -->'
		);
		$this->assertStringContainsString( 'starter-testimonial-grid', $html );
		$this->assertSame( 3, substr_count( $html, 'starter-testimonial__quote' ) );
		$this->assertStringContainsString( 'First quote.', $html );
		$this->assertStringContainsString( 'Second quote.', $html );
		$this->assertStringContainsString( 'Third quote.', $html );
	}

	public function test_grid_skips_empty_quote_child() {
		$html = do_blocks(
			'<!-- wp:pediment/testimonial-grid -->' .
			'<!-- wp:pediment/testimonial {"quote":"Kept."} /-->' .
			'<!-- wp:pediment/testimonial {"quote":""} /-->' .
			'<!-- /wp:pediment/testimonial-grid -->'
		);
		$this->assertSame( 1, substr_count( $html, 'starter-testimonial__quote' ) );
		$this->assertStringContainsString( 'Kept.', $html );
	}

	public function test_grid_block_json_description_mentions_testimonial() {
		$path = dirname( __DIR__, 3 ) . '/src/blocks/testimonial-grid/block.json';
		$data = json_decode( file_get_contents( $path ), true );
		$this->assertStringContainsStringIgnoringCase( 'testimonial', (string) $data['description'] );
		$this->assertContains( 'wide', $data['supports']['align'] );
	}
```

- [ ] **Step 2: Build and run to verify the new tests fail**

Run:
```bash
npm run build
npx wp-env run tests-wordpress --env-cwd=wp-content/themes/pediment ./vendor/bin/phpunit --filter TestimonialGridTest
```
Expected: `test_grid_*` FAIL — `pediment/testimonial-grid` not registered.

- [ ] **Step 3: Create `src/blocks/testimonial-grid/block.json`**

```json
{
	"$schema": "https://schemas.wp.org/trunk/block.json",
	"apiVersion": 3,
	"name": "pediment/testimonial-grid",
	"title": "Testimonial Grid",
	"category": "pediment",
	"description": "A responsive grid of customer testimonial cards. Use for 'what our clients say' / Kundenstimmen sections. Contains Testimonial child blocks.",
	"textdomain": "pediment",
	"supports": { "html": false, "align": [ "wide", "full" ] },
	"attributes": {},
	"editorScript": "file:./index.js",
	"editorStyle": "file:./style-index.css",
	"style": "file:./style-index.css",
	"render": "file:./render.php"
}
```

- [ ] **Step 4: Create `src/blocks/testimonial-grid/render.php`**

```php
<?php
/**
 * Server-side render for pediment/testimonial-grid.
 *
 * @var array  $attributes
 * @var string $content
 */

$wrapper = get_block_wrapper_attributes( array( 'class' => 'starter-testimonial-grid' ) );
?>
<section <?php echo $wrapper; // phpcs:ignore WordPress.Security.EscapeOutput ?>>
	<?php echo $content; // phpcs:ignore WordPress.Security.EscapeOutput -- inner blocks pre-rendered ?>
</section>
```

- [ ] **Step 5: Create `src/blocks/testimonial-grid/index.tsx`**

```tsx
import { registerBlockType } from '@wordpress/blocks';
import { InnerBlocks } from '@wordpress/block-editor';
import metadata from './block.json';
import Edit from './edit';
import './style.scss';

registerBlockType( metadata.name, {
	edit: Edit,
	save: () => <InnerBlocks.Content />,
} );
```

- [ ] **Step 6: Create `src/blocks/testimonial-grid/edit.tsx`**

```tsx
import { useBlockProps, useInnerBlocksProps } from '@wordpress/block-editor';

const ALLOWED = [ 'pediment/testimonial' ];
const TEMPLATE: [ string, Record< string, unknown > ][] = [
	[ 'pediment/testimonial', {} ],
	[ 'pediment/testimonial', {} ],
];

export default function Edit() {
	const blockProps = useBlockProps( { className: 'starter-testimonial-grid' } );
	const innerProps = useInnerBlocksProps( blockProps, {
		allowedBlocks: ALLOWED,
		template: TEMPLATE,
		templateLock: false,
	} );
	return <section { ...innerProps } />;
}
```

- [ ] **Step 7: Build and run — all TestimonialGridTest tests pass**

Run:
```bash
npm run build
npx wp-env run tests-wordpress --env-cwd=wp-content/themes/pediment ./vendor/bin/phpunit --filter TestimonialGridTest
```
Expected: PASS (all child + grid tests).

- [ ] **Step 8: Commit**

```bash
git add src/blocks/testimonial-grid/ tests/phpunit/BlockRender/TestimonialGridTest.php build/
git commit -m "feat(blocks): add pediment/testimonial-grid parent block"
```

---

## Task 3: Style the testimonial cards (Option A look)

**Files:**
- Create: `src/blocks/testimonial-grid/style.scss`
- Create: `src/blocks/testimonial/style.scss`
- Modify: `src/blocks/testimonial/index.tsx` (import the child style)

This is presentational; verification is visual (Task 7), so no new PHPUnit test. We still build to confirm SCSS compiles.

- [ ] **Step 1: Create `src/blocks/testimonial-grid/style.scss`**

```scss
.starter-testimonial-grid {
  display: grid;
  grid-template-columns: repeat(2, 1fr);
  gap: 22px;
}

@media (max-width: 781px) {
  .starter-testimonial-grid { grid-template-columns: 1fr; }
}
```

- [ ] **Step 2: Create `src/blocks/testimonial/style.scss`**

```scss
.starter-testimonial {
  display: flex;
  flex-direction: column;
  background: var(--wp--preset--color--surface);
  border: 1px solid var(--wp--preset--color--border);
  border-radius: var(--r-lg, 20px);
  padding: 28px 30px;
  transition: box-shadow .18s ease, transform .18s ease, border-color .18s ease;

  &:hover {
    box-shadow: var(--wp--preset--shadow--subtle);
    transform: translateY(-3px);
    border-color: var(--wp--preset--color--border-strong);
  }

  &__mark {
    color: var(--wp--preset--color--accent);
    font-size: 2.6rem;
    font-weight: 800;
    line-height: .6;
    height: .7em;
  }

  &__quote {
    margin: 8px 0 18px;
    border: 0;
    padding: 0;
    font-size: clamp(1.05rem, 1.4vw, 1.2rem);
    font-weight: 600;
    line-height: 1.5;
    color: var(--wp--preset--color--foreground);
  }

  &__by {
    margin-top: auto;
    display: flex;
    align-items: center;
    gap: 12px;
  }

  &__avatar,
  &__initials {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    flex: none;
  }

  &__avatar { object-fit: cover; }

  &__initials {
    display: flex;
    align-items: center;
    justify-content: center;
    background: var(--wp--preset--color--accent);
    color: #fff;
    font-weight: 700;
    font-size: .85rem;
    letter-spacing: .02em;
  }

  &__meta { display: flex; flex-direction: column; }

  &__name {
    font-weight: 700;
    color: var(--wp--preset--color--foreground);
  }

  &__role {
    color: var(--wp--preset--color--foreground-muted);
    font-size: .9rem;
  }
}
```

> Note: no `.is-style-band-navy` override — testimonial cards carry their own `surface`
> background and stay readable on any band (unlike `pull-quote`, which sits directly on
> the band). Adding a light-text override here would be a light-on-light bug.

- [ ] **Step 3: Import the child style** so webpack emits `build/blocks/testimonial/style-index.css`. Edit `src/blocks/testimonial/index.tsx` to add the style import:

```tsx
import { registerBlockType } from '@wordpress/blocks';
import metadata from './block.json';
import Edit from './edit';
import './style.scss';

registerBlockType( metadata.name, { edit: Edit, save: () => null } );
```

Then add `"style"` + `"editorStyle"` to `src/blocks/testimonial/block.json` so the CSS is enqueued on the front end and in the editor. Update the file to:

```json
{
	"$schema": "https://schemas.wp.org/trunk/block.json",
	"apiVersion": 3,
	"name": "pediment/testimonial",
	"title": "Testimonial",
	"category": "pediment",
	"description": "A single customer testimonial card: quote, author name, role, and optional avatar.",
	"parent": [ "pediment/testimonial-grid" ],
	"textdomain": "pediment",
	"supports": { "html": false, "inserter": false },
	"attributes": {
		"quote": { "type": "string", "default": "" },
		"authorName": { "type": "string", "default": "" },
		"authorRole": { "type": "string", "default": "" },
		"avatarId": { "type": "integer", "default": 0 }
	},
	"editorScript": "file:./index.js",
	"editorStyle": "file:./style-index.css",
	"style": "file:./style-index.css",
	"render": "file:./render.php"
}
```

- [ ] **Step 4: Build and confirm SCSS compiles + render tests still green**

Run:
```bash
npm run build
npx wp-env run tests-wordpress --env-cwd=wp-content/themes/pediment ./vendor/bin/phpunit --filter TestimonialGridTest
```
Expected: build succeeds (no SCSS errors), tests PASS. Confirm `build/blocks/testimonial/style-index.css` and `build/blocks/testimonial-grid/style-index.css` exist.

- [ ] **Step 5: Commit**

```bash
git add src/blocks/testimonial/ src/blocks/testimonial-grid/style.scss build/
git commit -m "style(blocks): testimonial card grid (Option A)"
```

---

## Task 4: Remove the `testimonial` variant from `pediment/pull-quote`

**Files:**
- Modify: `src/blocks/pull-quote/block.json`
- Modify: `src/blocks/pull-quote/render.php`
- Modify: `src/blocks/pull-quote/edit.tsx`
- Modify: `src/blocks/pull-quote/style.scss`
- Modify: `tests/phpunit/BlockRender/PullQuoteTest.php` (drop variant tests)
- Delete: `inc/pull-quote-variants.php`
- Modify: `functions.php` (remove the require)

- [ ] **Step 1: Rewrite `tests/phpunit/BlockRender/PullQuoteTest.php`** to the plain-quote contract (removing every testimonial/variant test). Replace the whole file with:

```php
<?php

class PullQuoteTest extends WP_UnitTestCase {
	private function render( array $attrs ): string {
		return do_blocks( '<!-- wp:pediment/pull-quote ' . wp_json_encode( $attrs ) . ' /-->' );
	}

	public function test_renders_quote_and_citation() {
		$html = $this->render( array( 'quote' => 'To be or not to be', 'citation' => 'Hamlet' ) );
		$this->assertStringContainsString( '<blockquote', $html );
		$this->assertStringContainsString( 'To be or not to be', $html );
		$this->assertStringContainsString( '<cite', $html );
		$this->assertStringContainsString( 'Hamlet', $html );
	}

	public function test_omits_cite_when_citation_empty() {
		$html = $this->render( array( 'quote' => 'Just a quote', 'citation' => '' ) );
		$this->assertStringContainsString( 'Just a quote', $html );
		$this->assertStringNotContainsString( '<cite', $html );
	}

	public function test_returns_empty_when_quote_missing() {
		$html = $this->render( array( 'quote' => '' ) );
		$this->assertStringNotContainsString( 'starter-pull-quote', $html );
	}

	public function test_block_json_has_no_testimonial_variant() {
		$path = dirname( __DIR__, 3 ) . '/src/blocks/pull-quote/block.json';
		$data = json_decode( file_get_contents( $path ), true );
		$this->assertArrayNotHasKey( 'variant', $data['attributes'] );
		$this->assertArrayNotHasKey( 'authorName', $data['attributes'] );
		$this->assertArrayNotHasKey( 'avatarId', $data['attributes'] );
		$this->assertStringNotContainsStringIgnoringCase( 'testimonial', (string) $data['description'] );
	}

	public function test_stale_testimonial_attrs_render_as_plain_quote() {
		// Content authored before the cleanup must not error and must show the quote.
		$html = $this->render( array(
			'variant'    => 'testimonial',
			'quote'      => 'Legacy quote survives.',
			'authorName' => 'Old Author',
		) );
		$this->assertStringContainsString( 'Legacy quote survives.', $html );
		$this->assertStringNotContainsString( '<figure', $html );
		$this->assertStringNotContainsString( 'starter-pull-quote__by', $html );
	}

	public function test_variants_helper_is_gone() {
		$this->assertFalse( function_exists( 'pediment_pull_quote_variants' ) );
	}
}
```

- [ ] **Step 2: Build and run to verify the rewritten tests fail**

Run:
```bash
npm run build
npx wp-env run tests-wordpress --env-cwd=wp-content/themes/pediment ./vendor/bin/phpunit --filter PullQuoteTest
```
Expected: FAIL — `variant` attr still present, `pediment_pull_quote_variants` still defined, `<figure>` still emitted for the stale-attrs case.

- [ ] **Step 3: Rewrite `src/blocks/pull-quote/block.json`** to drop testimonial attributes:

```json
{
	"$schema": "https://schemas.wp.org/trunk/block.json",
	"apiVersion": 3,
	"name": "pediment/pull-quote",
	"title": "Pull Quote",
	"category": "pediment",
	"description": "An emphasized quotation with optional citation.",
	"textdomain": "pediment",
	"supports": { "html": false, "align": [ "wide" ] },
	"attributes": {
		"quote": { "type": "string", "default": "" },
		"citation": { "type": "string", "default": "" }
	},
	"editorScript": "file:./index.js",
	"editorStyle": "file:./style-index.css",
	"style": "file:./style-index.css",
	"render": "file:./render.php"
}
```

- [ ] **Step 4: Rewrite `src/blocks/pull-quote/render.php`** to the plain-quote-only renderer:

```php
<?php
/**
 * Server-side render for pediment/pull-quote.
 *
 * @var array $attributes
 */

$quote    = isset( $attributes['quote'] ) ? (string) $attributes['quote'] : '';
$citation = isset( $attributes['citation'] ) ? (string) $attributes['citation'] : '';

if ( '' === $quote ) {
	return '';
}

$wrapper = get_block_wrapper_attributes( array( 'class' => 'starter-pull-quote' ) );

ob_start();
?>
<blockquote <?php echo $wrapper; // phpcs:ignore WordPress.Security.EscapeOutput ?>>
	<p class="starter-pull-quote__quote"><?php echo wp_kses_post( $quote ); ?></p>
	<?php if ( '' !== $citation ) : ?>
		<cite class="starter-pull-quote__citation"><?php echo wp_kses_post( $citation ); ?></cite>
	<?php endif; ?>
</blockquote>
<?php
echo ob_get_clean();
```

- [ ] **Step 5: Rewrite `src/blocks/pull-quote/edit.tsx`** to remove the variant selector, avatar, and testimonial fields:

```tsx
import { __ } from '@wordpress/i18n';
import { useBlockProps, RichText } from '@wordpress/block-editor';

type Attrs = {
	quote: string;
	citation: string;
};

export default function Edit( {
	attributes,
	setAttributes,
}: {
	attributes: Attrs;
	setAttributes: ( a: Partial< Attrs > ) => void;
} ) {
	const blockProps = useBlockProps( { className: 'starter-pull-quote' } );
	return (
		<blockquote { ...blockProps }>
			<RichText
				tagName="p"
				className="starter-pull-quote__quote"
				value={ attributes.quote }
				onChange={ ( v ) => setAttributes( { quote: v } ) }
				placeholder={ __( 'Quote…', 'pediment' ) }
			/>
			<RichText
				tagName="cite"
				className="starter-pull-quote__citation"
				value={ attributes.citation }
				onChange={ ( v ) => setAttributes( { citation: v } ) }
				placeholder={ __( 'Citation (optional)…', 'pediment' ) }
			/>
		</blockquote>
	);
}
```

- [ ] **Step 6: Edit `src/blocks/pull-quote/style.scss`** — remove the testimonial blocks. Delete lines 33–89 (the `.starter-pull-quote.is-variant-testimonial …` rule and the `.is-style-band-navy … .is-variant-testimonial` rule). Keep only the base `.starter-pull-quote` rule (lines 1–26) and the default band-navy override (lines 28–31). The file becomes:

```scss
.starter-pull-quote {
  max-width: 880px;
  margin-inline: auto;
  text-align: center;
  padding-block: var(--wp--preset--spacing--40);
  border: 0;
  background: none;

  &__quote {
    font-size: clamp(1.5rem, 2.6vw, 2.1rem);
    font-weight: 700;
    line-height: 1.35;
    letter-spacing: -0.02em;
    color: var(--wp--preset--color--foreground);
    margin: 0;
  }

  &__citation {
    display: block;
    margin-top: var(--wp--preset--spacing--30);
    font-style: normal;
    font-weight: 600;
    color: var(--wp--preset--color--foreground-muted);
    font-size: var(--wp--preset--font-size--sm);
  }
}

.is-style-band-navy .starter-pull-quote {
  &__quote { color: var(--wp--preset--color--surface); }
  &__citation { color: var(--wp--custom--color--text-on-dark); }
}
```

- [ ] **Step 7: Delete the variant registry and its include**

```bash
rm inc/pull-quote-variants.php
```

Then edit `functions.php` and delete the line:

```php
require_once __DIR__ . '/inc/pull-quote-variants.php';
```

(It is line 22. Remove that single line.)

- [ ] **Step 8: Build and run PullQuoteTest — green**

Run:
```bash
npm run build
npx wp-env run tests-wordpress --env-cwd=wp-content/themes/pediment ./vendor/bin/phpunit --filter PullQuoteTest
```
Expected: PASS.

- [ ] **Step 9: Commit**

```bash
git add -A src/blocks/pull-quote/ tests/phpunit/BlockRender/PullQuoteTest.php functions.php build/
git rm inc/pull-quote-variants.php
git commit -m "refactor(pull-quote): remove testimonial variant; testimonial-grid owns testimonials"
```

---

## Task 5: Update the landing pattern to use the testimonial grid

**Files:**
- Modify: `patterns/pediment-landing.php` (the `pull-quote {"variant":"testimonial",…}` line, ~line 80)

- [ ] **Step 1: Replace the testimonial pull-quote line.** In `patterns/pediment-landing.php`, find:

```php
<!-- wp:pediment/pull-quote {"variant":"testimonial","align":"wide","quote":"A short, specific endorsement written in the customer's own words.","authorName":"A. Customer","authorRole":"Title, Company"} /-->
```

Replace it with a testimonial grid of two cards:

```php
<!-- wp:pediment/testimonial-grid {"align":"wide"} -->
<!-- wp:pediment/testimonial {"quote":"A short, specific endorsement written in the customer's own words.","authorName":"A. Customer","authorRole":"Title, Company"} /-->
<!-- wp:pediment/testimonial {"quote":"A second endorsement that speaks to a different strength or outcome.","authorName":"B. Client","authorRole":"Title, Company"} /-->
<!-- /wp:pediment/testimonial-grid -->
```

- [ ] **Step 2: Verify the existing pattern e2e/render test still passes.** The landing pattern is covered by `tests/phpunit/BlockRender` / `tests/phpunit/Patterns` and `tests/e2e/landing-layout.spec.ts`. Run the PHP pattern tests:

```bash
npm run build
npx wp-env run tests-wordpress --env-cwd=wp-content/themes/pediment ./vendor/bin/phpunit --filter Pattern
```
Expected: PASS. If a pattern test asserts on `is-variant-testimonial` or pull-quote testimonial markup, update that assertion to the new `starter-testimonial-grid` / `starter-testimonial` markup (grep `tests/phpunit/Patterns` for `testimonial` and `pull-quote` first; fix any stale assertion to match the two-card grid).

- [ ] **Step 3: Commit**

```bash
git add patterns/pediment-landing.php tests/phpunit/Patterns/ 2>/dev/null
git commit -m "feat(patterns): landing page uses testimonial-grid"
```

---

## Task 6: Steer the AI composer toward the testimonial grid

**Files:**
- Modify: `/Users/jonas/Entwicklung/pediment-ai/src/Chat/PromptBuilder.php` (the `systemPrompt()` "Page structure" paragraph)

The new blocks already appear in the auto-generated "Available blocks" list (they have `description`s). We add one explicit composition rule so the model stops using repeated `pull-quote` for collections.

- [ ] **Step 1: Add a guidance line.** In `src/Chat/PromptBuilder.php`, immediately after the existing "Page structure:" `$lines[]` statement (the long paragraph ending "…group them into their section."), insert a new line before the following `$lines[] = '';`:

```php
		$lines[] = 'Testimonials: for a customer-quote / "what clients say" / Kundenstimmen section, emit one pediment/testimonial-grid (align "wide") containing pediment/testimonial children (quote + authorName + authorRole), not a stack of pediment/pull-quote blocks. Use pediment/pull-quote only for a single standalone highlighted quote.';
```

- [ ] **Step 2: Sanity-check the prompt builds.** This repo is a plugin; if it has a test suite, run it, otherwise lint the file:

```bash
cd /Users/jonas/Entwicklung/pediment-ai
php -l src/Chat/PromptBuilder.php
composer test 2>/dev/null || echo "no composer test script — php lint passed is sufficient"
```
Expected: `No syntax errors detected`, and any existing tests pass.

- [ ] **Step 3: Commit (in the pediment-ai repo)**

```bash
cd /Users/jonas/Entwicklung/pediment-ai
git add src/Chat/PromptBuilder.php
git commit -m "feat(prompt): steer composer to testimonial-grid for Kundenstimmen"
```

---

## Task 7: Full verification (build, suite, lint, visual)

**Files:** none (verification only)

- [ ] **Step 1: Clean build**

```bash
cd /Users/jonas/Entwicklung/pediment
npm run build
```
Expected: succeeds; `build/blocks/testimonial/` and `build/blocks/testimonial-grid/` exist with `block.json`, `render.php`, `style-index.css`; `build/blocks-manifest.php` lists both.

- [ ] **Step 2: Full PHP test suite**

```bash
npx wp-env run tests-wordpress --env-cwd=wp-content/themes/pediment ./vendor/bin/phpunit
```
Expected: PASS, no failures, no errors.

- [ ] **Step 3: Block + JS lint**

```bash
npm run lint:blocks
npm run lint:js
```
Expected: both pass. (`lint:blocks` enforces the block.json data contract — fix any reported issue on the two new blocks.)

- [ ] **Step 4: Grep for dangling references** to the removed machinery:

```bash
grep -rn "pediment_pull_quote_variants\|pedimentPullQuoteVariants\|is-variant-testimonial\|pull-quote-variants" src/ inc/ functions.php patterns/ tests/ | grep -v build/
```
Expected: no matches.

- [ ] **Step 5: Visual confirm in the dev env.** Ensure `wp-env` is running (port 8890), then in the editor insert a Testimonial Grid (or compose a Kundenstimmen section via the AI) and confirm on the front end:
  - Cards render 2-up on desktop, 1-up under 782px.
  - Quote text is ~half the old size; name (bold) + role (muted) byline shows; initials circle appears when no avatar.
  - The landing pattern's testimonial section shows the two-card grid.
  - On an `is-style-band-surface` band the cards read correctly (light card, dark text).

- [ ] **Step 6: Run the relevant e2e (optional but recommended)**

```bash
npm run e2e -- landing-layout editor-blocks edit-render-parity
```
Expected: PASS. If `edit-render-parity` flags the new blocks, reconcile editor markup (`edit.tsx`) with `render.php` class names.

---

## Self-review notes (already reconciled)

- **Spec coverage:** new grid block (Task 2), child card block (Task 1), Option A styling incl. initials & no dark-band override (Task 3), pull-quote cleanup incl. variants-registry deletion + landing pattern (Tasks 4–5), AI composer guidance (Task 6), verification incl. stale-content migration check (Tasks 4 & 7). All spec sections map to a task.
- **Class-name consistency:** `starter-testimonial-grid`, `starter-testimonial`, `__mark`, `__quote`, `__by`, `__avatar`, `__initials`, `__meta`, `__name`, `__role` are used identically across `render.php`, `edit.tsx`, `style.scss`, and tests.
- **Build-before-test:** every PHP-test step is preceded by `npm run build`, because PHPUnit registers blocks from `build/blocks/`.
