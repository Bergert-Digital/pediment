# Image/Content Slider Block Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add a `pediment/slider` + `pediment/slide` block pair: a one-slide-at-a-time carousel pairing a full-bleed image with a colored content panel, with prev/next arrows + dot navigation, an image-side toggle, and a panel color picker.

**Architecture:** Two server-rendered blocks following the established `testimonial-grid`/`testimonial` parent/child pattern. The parent (`slider`) owns the chrome (rounded shadowed card, arrows, dots, live region) and the WordPress Interactivity API view module; the child (`slide`) is a context-free template = full-bleed image + InnerBlocks panel. Active state is driven imperatively by DOM order in `view.ts` (`data-wp-watch`), with progressive enhancement (`is-enhanced`) so it degrades to a readable stack of cards without JS.

**Tech Stack:** WordPress block API v3, `@wordpress/scripts` build, TypeScript/TSX edit components, `@wordpress/interactivity` (view module), SCSS, PHP `render.php`, Playwright e2e, PHPUnit (`WP_UnitTestCase` + `do_blocks`).

## Global Constraints

- **Block category:** `pediment` (already registered).
- **Auto-registration:** blocks register from `build/blocks/<name>/` via `wp_register_block_metadata_collection` in `inc/register-blocks.php`. No PHP registration edits — just add `src/blocks/<name>/` and build.
- **Required files per block** (enforced by `npm run lint:blocks`): `block.json`, `render.php`, `edit.tsx`.
- **No color literals** in any `src/blocks/**/*.scss` / `*.css` (enforced by `npm run lint:colors` — regex bans `#hex`, `rgb(`, `hsl(`). Use `var(--wp--preset--color--*)` / theme CSS vars only. Hex is allowed in `block.json` and `.php`/`.ts`.)
- **PHP:** `phpcs` must pass with zero warnings (warnings fail CI). Escape all output; use the existing `// phpcs:ignore WordPress.Security.EscapeOutput` convention for pre-rendered/pre-escaped HTML, exactly as the other blocks do.
- **Class naming:** BEM-ish `starter-*` prefix (matches every existing block).
- **Theme tokens available:** colors `primary #0A1B33`, `accent #0E7490`, `accent-hover #155E75`, `accent-tint #E1F1F6`, `surface #FFFFFF`, `surface-elevated`, `foreground #0B1B33`, `foreground-muted #5C6B82`, `border`, `border-strong`; shadow presets `subtle|medium|lifted|focus` → `var(--wp--preset--shadow--medium)`; radius vars (from `assets/css/theme.css`) `--r-panel: 28px`, `--r-pill: 999px`, `--r-lg`, `--r-md`; spacing presets `var(--wp--preset--spacing--20|40|50)`.
- **Build commands:** `npm run build` (compiles + regenerates `build/blocks-manifest.php`). For iterative dev, `npm run start` (watch). Lints: `npm run lint:blocks`, `npm run lint:colors`, `npm run lint:js`. PHP lint: `composer lint` (phpcs, per `phpcs.xml.dist`).
- **Test commands (exact):**
  - wp-env must be running: `npm run env:start` (first run also `composer install` so `vendor/bin/phpunit` exists).
  - **PHPUnit** (theme's own wp-env, as CI runs it): `npx wp-env run tests-wordpress --env-cwd=wp-content/themes/pediment vendor/bin/phpunit`. Filter one class: append `--filter SliderTest`.
  - **Playwright e2e** (needs wp-env up + a fresh `npm run build`): `npm run e2e`. Filter one spec: `npm run e2e -- slider`.
- **i18n:** all editor-facing strings wrapped in `__( '…', 'pediment' )`.

---

## File Structure

```
src/blocks/slide/
  block.json      # child block metadata (parent: pediment/slider)
  index.tsx       # registerBlockType, save: () => <InnerBlocks.Content/>
  save.tsx        # <InnerBlocks.Content/> (panel content persists)
  edit.tsx        # image picker (MediaPlaceholder/MediaUpload) + InnerBlocks panel
  render.php      # full-bleed figure (image|placeholder) + panel ($content)
  style.scss      # .starter-slide layout (grid halves, full-bleed img)

src/blocks/slider/
  block.json      # parent block metadata, viewScriptModule
  index.tsx       # registerBlockType, save: () => <InnerBlocks.Content/>
  edit.tsx        # InnerBlocks (slide-only) + InspectorControls (side toggle + color)
  save.tsx        # <InnerBlocks.Content/>
  render.php      # rounded card + track ($content) + arrows + dots + live region
  view.ts         # @wordpress/interactivity store: next/prev/goTo/onKeydown + render
  style.scss      # .starter-slider chrome, is-enhanced stacking, arrows, dots

tests/phpunit/BlockRender/SliderTest.php   # render.php logic (structure, dot count, color/luminance, media position)
tests/e2e/slider.spec.ts                   # front-end interactivity (next/prev/dots/keyboard/wrap)
tests/e2e/editor-blocks.spec.ts            # MODIFY: add slider to kitchen-sink render list
```

---

## Task 1: Slide child block (static render + editor)

Builds the `pediment/slide` block: a full-bleed image (or placeholder) beside an InnerBlocks panel. The block's `parent` key only restricts editor insertion — `do_blocks()` renders a registered block server-side regardless of parent — so this task verifies the slide by rendering it alone via `do_blocks`. The slider parent arrives in Task 2.

**Files:**
- Create: `src/blocks/slide/block.json`, `src/blocks/slide/index.tsx`, `src/blocks/slide/save.tsx`, `src/blocks/slide/edit.tsx`, `src/blocks/slide/render.php`, `src/blocks/slide/style.scss`
- Test: `tests/phpunit/BlockRender/SliderTest.php` (slide cases; slider cases added in Task 2)

**Interfaces:**
- Produces: block `pediment/slide` with attributes `mediaId: number` (default 0), `altOverride: string` (default ""). Renders `<div class="starter-slide"><figure class="starter-slide__media">…</figure><div class="starter-slide__panel">{content}</div></div>`. Panel content = InnerBlocks. When `mediaId` is 0, renders the placeholder SVG inside `.starter-slide__media`.

- [ ] **Step 1: Write the failing test**

Create `tests/phpunit/BlockRender/SliderTest.php`:

```php
<?php

class SliderTest extends WP_UnitTestCase {
	private function slide( string $attrs = '{}', string $inner = '' ): string {
		return do_blocks( '<!-- wp:pediment/slide ' . $attrs . ' -->' . $inner . '<!-- /wp:pediment/slide -->' );
	}

	public function test_slide_renders_panel_with_inner_content() {
		$html = $this->slide( '{}', '<!-- wp:paragraph --><p>Hello slide</p><!-- /wp:paragraph -->' );
		$this->assertStringContainsString( 'class="starter-slide"', $html );
		$this->assertStringContainsString( 'starter-slide__panel', $html );
		$this->assertStringContainsString( 'Hello slide', $html );
	}

	public function test_slide_without_media_renders_placeholder_not_img() {
		$html = $this->slide( '{"mediaId":0}', '<!-- wp:paragraph --><p>x</p><!-- /wp:paragraph -->' );
		$this->assertStringContainsString( 'starter-slide__placeholder', $html );
		$this->assertStringNotContainsString( '<img', $html );
	}

	public function test_slide_with_media_renders_img_with_alt_override() {
		$attachment_id = $this->factory->attachment->create_upload_object(
			DIR_TESTDATA . '/images/canola.jpg'
		);
		$html = $this->slide(
			sprintf( '{"mediaId":%d,"altOverride":"Yellow flowers"}', $attachment_id ),
			'<!-- wp:paragraph --><p>x</p><!-- /wp:paragraph -->'
		);
		$this->assertStringContainsString( 'starter-slide__img', $html );
		$this->assertStringContainsString( 'alt="Yellow flowers"', $html );
		$this->assertStringNotContainsString( 'starter-slide__placeholder', $html );
		wp_delete_attachment( $attachment_id, true );
	}
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `npx wp-env run tests-wordpress --env-cwd=wp-content/themes/pediment vendor/bin/phpunit --filter SliderTest`
Expected: FAIL — `pediment/slide` is not registered (block render returns empty / no `starter-slide`).

- [ ] **Step 3: Create `src/blocks/slide/block.json`**

```json
{
	"$schema": "https://schemas.wp.org/trunk/block.json",
	"apiVersion": 3,
	"name": "pediment/slide",
	"title": "Slide",
	"category": "pediment",
	"description": "A single slide: a full-bleed image beside a colored content panel. Used inside a Slider.",
	"parent": [ "pediment/slider" ],
	"textdomain": "pediment",
	"supports": { "html": false, "inserter": false, "reusable": false },
	"attributes": {
		"mediaId": { "type": "number", "default": 0 },
		"altOverride": { "type": "string", "default": "" }
	},
	"editorScript": "file:./index.js",
	"editorStyle": "file:./style-index.css",
	"style": "file:./style-index.css",
	"render": "file:./render.php"
}
```

- [ ] **Step 4: Create `src/blocks/slide/render.php`**

```php
<?php
/**
 * Server-side render for pediment/slide.
 *
 * @var array  $attributes
 * @var string $content    Pre-rendered inner blocks (the panel content).
 */

$media_id     = isset( $attributes['mediaId'] ) ? (int) $attributes['mediaId'] : 0;
$alt_override = isset( $attributes['altOverride'] ) ? (string) $attributes['altOverride'] : '';

$img_html = '';
if ( $media_id ) {
	$alt      = '' !== $alt_override ? $alt_override : (string) get_post_meta( $media_id, '_wp_attachment_image_alt', true );
	$img_html = wp_get_attachment_image(
		$media_id,
		'large',
		false,
		array(
			'alt'   => $alt,
			'class' => 'starter-slide__img',
		)
	);
}

$wrapper = get_block_wrapper_attributes( array( 'class' => 'starter-slide' ) );
ob_start();
?>
<div <?php echo $wrapper; // phpcs:ignore WordPress.Security.EscapeOutput ?>>
	<figure class="starter-slide__media">
		<?php if ( '' !== $img_html ) : ?>
			<?php echo $img_html; // phpcs:ignore WordPress.Security.EscapeOutput -- pre-escaped by wp_get_attachment_image ?>
		<?php else : ?>
			<span class="starter-slide__placeholder" aria-hidden="true">
				<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
					<rect x="3" y="3" width="18" height="18" rx="2" />
					<circle cx="8.5" cy="8.5" r="1.5" />
					<path d="M21 15l-5-5L5 21" />
				</svg>
			</span>
		<?php endif; ?>
	</figure>
	<div class="starter-slide__panel">
		<?php echo $content; // phpcs:ignore WordPress.Security.EscapeOutput -- inner blocks pre-rendered ?>
	</div>
</div>
<?php
echo ob_get_clean();
```

- [ ] **Step 5: Create `src/blocks/slide/save.tsx`**

```tsx
import { InnerBlocks } from '@wordpress/block-editor';

/**
 * Persist the panel inner blocks. The block is server-rendered (render.php),
 * which wraps the saved content beside the figure. useInnerBlocksProps sits on a
 * nested element, so without an explicit save the editor would serialize a
 * self-closing block and the panel content would vanish on the front end.
 */
export default function save() {
	return <InnerBlocks.Content />;
}
```

- [ ] **Step 6: Create `src/blocks/slide/index.tsx`**

```tsx
import { registerBlockType } from '@wordpress/blocks';
import metadata from './block.json';
import Edit from './edit';
import save from './save';
import './style.scss';

registerBlockType( metadata.name, { edit: Edit, save } );
```

- [ ] **Step 7: Create `src/blocks/slide/edit.tsx`**

```tsx
import { __ } from '@wordpress/i18n';
import {
	useBlockProps,
	useInnerBlocksProps,
	MediaUpload,
	MediaPlaceholder,
	InspectorControls,
} from '@wordpress/block-editor';
import { PanelBody, TextControl } from '@wordpress/components';
import { useSelect } from '@wordpress/data';

type Attrs = {
	mediaId: number;
	altOverride: string;
};

const ALLOWED = [
	'core/heading',
	'core/paragraph',
	'core/list',
	'core/list-item',
	'core/separator',
	'core/buttons',
];

const TEMPLATE: [ string, Record< string, unknown > ][] = [
	[ 'core/heading', { level: 2 } ],
	[ 'core/paragraph', { placeholder: 'Start writing…' } ],
];

export default function Edit( {
	attributes,
	setAttributes,
}: {
	attributes: Attrs;
	setAttributes: ( a: Partial< Attrs > ) => void;
} ) {
	const blockProps = useBlockProps( { className: 'starter-slide' } );
	const innerBlocksProps = useInnerBlocksProps(
		{ className: 'starter-slide__panel' },
		{ allowedBlocks: ALLOWED, template: TEMPLATE, templateLock: false }
	);
	const media = useSelect(
		( select: any ) =>
			attributes.mediaId
				? select( 'core' ).getMedia( attributes.mediaId )
				: null,
		[ attributes.mediaId ]
	);

	return (
		<>
			<InspectorControls>
				<PanelBody title={ __( 'Image', 'pediment' ) }>
					<TextControl
						label={ __( 'Alt text override', 'pediment' ) }
						value={ attributes.altOverride }
						onChange={ ( v ) => setAttributes( { altOverride: v } ) }
						help={ __(
							'Leave empty to use the media library alt text.',
							'pediment'
						) }
					/>
				</PanelBody>
			</InspectorControls>
			<div { ...blockProps }>
				<figure className="starter-slide__media">
					{ media ? (
						<MediaUpload
							allowedTypes={ [ 'image' ] }
							value={ attributes.mediaId }
							onSelect={ ( m: any ) =>
								setAttributes( { mediaId: m.id } )
							}
							render={ ( { open }: { open: () => void } ) => (
								<button
									type="button"
									className="starter-slide__replace"
									onClick={ open }
									aria-label={ __( 'Replace image', 'pediment' ) }
								>
									<img
										className="starter-slide__img"
										src={ ( media as any ).source_url }
										alt={
											attributes.altOverride ||
											( media as any ).alt_text ||
											''
										}
									/>
								</button>
							) }
						/>
					) : (
						<MediaPlaceholder
							icon="format-image"
							labels={ { title: __( 'Image', 'pediment' ) } }
							allowedTypes={ [ 'image' ] }
							accept="image/*"
							onSelect={ ( m: any ) =>
								setAttributes( { mediaId: m.id } )
							}
						/>
					) }
				</figure>
				<div { ...innerBlocksProps } />
			</div>
		</>
	);
}
```

- [ ] **Step 8: Create `src/blocks/slide/style.scss`**

```scss
.starter-slide {
  display: grid;
  grid-template-columns: 1fr 1fr;
  align-items: stretch;

  &__media {
    margin: 0;
    position: relative;
  }

  &__img {
    display: block;
    width: 100%;
    height: 100%;
    aspect-ratio: 4 / 3;
    object-fit: cover;
  }

  &__replace {
    display: block;
    padding: 0;
    border: 0;
    background: none;
    cursor: pointer;
    width: 100%;
    height: 100%;
  }

  // Front-end stand-in shown until an image is uploaded.
  &__placeholder {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 100%;
    height: 100%;
    aspect-ratio: 4 / 3;
    background: var(--wp--preset--color--accent-tint);
    color: var(--wp--preset--color--foreground-muted);

    svg {
      width: 2.75rem;
      height: 2.75rem;
    }
  }

  &__panel {
    display: flex;
    flex-direction: column;
    justify-content: center;
    gap: var(--wp--preset--spacing--20);
    padding: clamp(28px, 4vw, 56px);
    background: var(--slide-panel-bg, var(--wp--preset--color--primary));
    color: var(--slide-panel-fg, var(--wp--preset--color--surface));

    > :first-child { margin-top: 0; }
    > :last-child { margin-bottom: 0; }

    // Headings inherit the computed panel foreground.
    h1, h2, h3, h4, h5, h6 { color: inherit; }
  }
}

@media (max-width: 781px) {
  .starter-slide {
    grid-template-columns: 1fr;

    &__img,
    &__placeholder { aspect-ratio: 16 / 9; }
  }
}
```

- [ ] **Step 9: Build and lint**

Run: `npm run build && npm run lint:blocks && npm run lint:colors && npm run lint:js`
Expected: build succeeds; `✓ src/blocks/slide/`; `✓ No color literals`; eslint clean.

- [ ] **Step 10: Run the test to verify slide cases pass**

Run: `npx wp-env run tests-wordpress --env-cwd=wp-content/themes/pediment vendor/bin/phpunit --filter SliderTest`
Expected: PASS for the three slide tests (`test_slide_renders_panel_with_inner_content`, `test_slide_without_media_renders_placeholder_not_img`, `test_slide_with_media_renders_img_with_alt_override`).

- [ ] **Step 11: Commit**

```bash
git add src/blocks/slide build tests/phpunit/BlockRender/SliderTest.php
git commit -m "feat(slider): add pediment/slide child block (image + panel)"
```

---

## Task 2: Slider parent block (static render + nesting)

Builds `pediment/slider`: the rounded shadowed card wrapping slides, plus prev/next arrows, dots, and a live region — but **without** interactivity yet (static markup; controls present but inert). Verifies the parent renders its slides and the right number of dots.

**Files:**
- Create: `src/blocks/slider/block.json`, `src/blocks/slider/index.tsx`, `src/blocks/slider/save.tsx`, `src/blocks/slider/edit.tsx`, `src/blocks/slider/render.php`, `src/blocks/slider/style.scss`
- Modify: `tests/phpunit/BlockRender/SliderTest.php` (add slider cases)

**Interfaces:**
- Consumes: `pediment/slide` (Task 1) as its only allowed inner block.
- Produces: block `pediment/slider` with attributes `mediaPosition: string` (default "left"), `panelColor: string` (default "#0A1B33"). Renders `<section class="starter-slider is-media-{left|right}" style="--slide-panel-bg:…;--slide-panel-fg:…">` containing `.starter-slider__track` (the slides), two `.starter-slider__arrow` buttons, a `.starter-slider__dots` group with one `.starter-slider__dot[data-index]` per slide, and a `.starter-slider__live` region. Exposes a PHP helper `pediment_slider_panel_fg( string $bg ): string` returning a CSS color var for readable text. (Color application detail is finalized here; the inspector controls that set these attributes land in Task 4 — defaults are used until then.)

- [ ] **Step 1: Write the failing test (append slider cases to `SliderTest.php`)**

Add these methods inside the existing `SliderTest` class:

```php
	private function slider( string $attrs = '{}', int $slides = 2 ): string {
		$inner = '';
		for ( $i = 0; $i < $slides; $i++ ) {
			$inner .= '<!-- wp:pediment/slide --><!-- wp:paragraph --><p>Slide ' . $i . '</p><!-- /wp:paragraph --><!-- /wp:pediment/slide -->';
		}
		return do_blocks( '<!-- wp:pediment/slider ' . $attrs . ' -->' . $inner . '<!-- /wp:pediment/slider -->' );
	}

	public function test_slider_renders_track_with_slides() {
		$html = $this->slider( '{}', 3 );
		$this->assertStringContainsString( 'class="starter-slider', $html );
		$this->assertStringContainsString( 'starter-slider__track', $html );
		$this->assertSame( 3, substr_count( $html, 'starter-slide__panel' ) );
	}

	public function test_slider_renders_one_dot_per_slide() {
		$html = $this->slider( '{}', 4 );
		$this->assertSame( 4, substr_count( $html, 'starter-slider__dot' ) );
		$this->assertStringContainsString( 'data-index="0"', $html );
		$this->assertStringContainsString( 'data-index="3"', $html );
	}

	public function test_slider_has_prev_next_arrows_and_live_region() {
		$html = $this->slider();
		$this->assertStringContainsString( 'starter-slider__arrow--prev', $html );
		$this->assertStringContainsString( 'starter-slider__arrow--next', $html );
		$this->assertStringContainsString( 'starter-slider__live', $html );
	}

	public function test_slider_default_media_position_is_left() {
		$html = $this->slider( '{}' );
		$this->assertStringContainsString( 'is-media-left', $html );
	}

	public function test_slider_media_position_right() {
		$html = $this->slider( '{"mediaPosition":"right"}' );
		$this->assertStringContainsString( 'is-media-right', $html );
		$this->assertStringNotContainsString( 'is-media-left', $html );
	}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `npx wp-env run tests-wordpress --env-cwd=wp-content/themes/pediment vendor/bin/phpunit --filter SliderTest`
Expected: FAIL on the new slider methods — `pediment/slider` not registered.

- [ ] **Step 3: Create `src/blocks/slider/block.json`**

```json
{
	"$schema": "https://schemas.wp.org/trunk/block.json",
	"apiVersion": 3,
	"name": "pediment/slider",
	"title": "Slider",
	"category": "pediment",
	"description": "An image/content slider: one slide at a time, each pairing a full-bleed image with a colored content panel. Contains Slide child blocks.",
	"textdomain": "pediment",
	"supports": { "html": false, "align": [ "wide", "full" ] },
	"attributes": {
		"mediaPosition": { "type": "string", "default": "left" },
		"panelColor": { "type": "string", "default": "#0A1B33" }
	},
	"example": {
		"innerBlocks": [
			{
				"name": "pediment/slide",
				"innerBlocks": [
					{ "name": "core/heading", "attributes": { "level": 2, "content": "Lebenslanges Lernen" } },
					{ "name": "core/paragraph", "attributes": { "content": "Wir bringen unser eigenes Organismus immer auf den neuesten Stand." } }
				]
			},
			{
				"name": "pediment/slide",
				"innerBlocks": [
					{ "name": "core/heading", "attributes": { "level": 2, "content": "Gemeinsam wachsen" } },
					{ "name": "core/paragraph", "attributes": { "content": "Tägliche Team-Updates und ein intensiver Austausch." } }
				]
			}
		],
		"viewportWidth": 1280
	},
	"editorScript": "file:./index.js",
	"editorStyle": "file:./style-index.css",
	"style": "file:./style-index.css",
	"viewScriptModule": "file:./view.js",
	"render": "file:./render.php"
}
```

- [ ] **Step 4: Create `src/blocks/slider/render.php`**

```php
<?php
/**
 * Server-side render for pediment/slider.
 *
 * @var array    $attributes
 * @var string   $content    Pre-rendered inner blocks (the slides).
 * @var WP_Block $block
 */

$position = ( isset( $attributes['mediaPosition'] ) && 'right' === $attributes['mediaPosition'] ) ? 'right' : 'left';
$bg       = isset( $attributes['panelColor'] ) ? (string) $attributes['panelColor'] : '#0A1B33';

/**
 * Pick a readable foreground token for a panel background. Parses a #hex color,
 * computes relative luminance, and returns the surface (light) token for dark
 * backgrounds or the foreground (dark) token for light backgrounds. Falls back
 * to the light token for non-hex / unparseable values.
 */
$fg = pediment_slider_panel_fg( $bg );

// Count slides from the parsed inner block list.
$count = is_object( $block ) && ! empty( $block->parsed_block['innerBlocks'] )
	? count( $block->parsed_block['innerBlocks'] )
	: 0;

$style   = sprintf( '--slide-panel-bg:%s;--slide-panel-fg:%s;', esc_attr( $bg ), esc_attr( $fg ) );
$wrapper = get_block_wrapper_attributes(
	array(
		'class' => 'starter-slider is-media-' . $position,
		'style' => $style,
	)
);

ob_start();
?>
<section <?php echo $wrapper; // phpcs:ignore WordPress.Security.EscapeOutput ?>
	data-wp-interactive="pediment/slider"
	data-wp-context='<?php echo esc_attr( wp_json_encode( array( 'active' => 0, 'count' => $count ) ) ); ?>'
	data-wp-init="callbacks.init"
	data-wp-watch="callbacks.render"
	data-wp-on--keydown="actions.onKeydown"
	role="group" aria-roledescription="carousel" tabindex="-1">
	<div class="starter-slider__track">
		<?php echo $content; // phpcs:ignore WordPress.Security.EscapeOutput -- inner blocks pre-rendered ?>
	</div>
	<button type="button" class="starter-slider__arrow starter-slider__arrow--prev" aria-label="<?php esc_attr_e( 'Vorherige Folie', 'pediment' ); ?>" data-wp-on--click="actions.prev">
		<span aria-hidden="true">&lsaquo;</span>
	</button>
	<button type="button" class="starter-slider__arrow starter-slider__arrow--next" aria-label="<?php esc_attr_e( 'Nächste Folie', 'pediment' ); ?>" data-wp-on--click="actions.next">
		<span aria-hidden="true">&rsaquo;</span>
	</button>
	<?php if ( $count > 1 ) : ?>
		<div class="starter-slider__dots" role="tablist" aria-label="<?php esc_attr_e( 'Folien', 'pediment' ); ?>">
			<?php for ( $i = 0; $i < $count; $i++ ) : ?>
				<button type="button" class="starter-slider__dot" data-index="<?php echo esc_attr( (string) $i ); ?>" data-wp-on--click="actions.goTo" aria-label="<?php echo esc_attr( sprintf( /* translators: %d: slide number */ __( 'Gehe zu Folie %d', 'pediment' ), $i + 1 ) ); ?>"></button>
			<?php endfor; ?>
		</div>
	<?php endif; ?>
	<p class="starter-slider__live screen-reader-text" aria-live="polite"></p>
</section>
<?php
echo ob_get_clean();
```

- [ ] **Step 5: Add the `pediment_slider_panel_fg` helper**

The render references `pediment_slider_panel_fg()`. Define it once, guarded, at the top of `render.php` (render templates are re-included per block, so guard against redeclaration):

Insert near the top of `src/blocks/slider/render.php`, right after the docblock:

```php
if ( ! function_exists( 'pediment_slider_panel_fg' ) ) {
	/**
	 * Readable foreground CSS var for a given panel background hex.
	 *
	 * @param string $bg Background color (expects #rgb or #rrggbb).
	 * @return string A CSS var() reference for the text color.
	 */
	function pediment_slider_panel_fg( $bg ) {
		$light = 'var(--wp--preset--color--surface)';
		$dark  = 'var(--wp--preset--color--foreground)';
		$hex   = ltrim( (string) $bg, '#' );
		if ( 3 === strlen( $hex ) ) {
			$hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
		}
		if ( 6 !== strlen( $hex ) || ! ctype_xdigit( $hex ) ) {
			return $light;
		}
		$r = hexdec( substr( $hex, 0, 2 ) ) / 255;
		$g = hexdec( substr( $hex, 2, 2 ) ) / 255;
		$b = hexdec( substr( $hex, 4, 2 ) ) / 255;
		// Perceived luminance (Rec. 709 coefficients).
		$lum = ( 0.2126 * $r ) + ( 0.7152 * $g ) + ( 0.0722 * $b );
		return $lum < 0.55 ? $light : $dark;
	}
}
```

- [ ] **Step 6: Create `src/blocks/slider/save.tsx`**

```tsx
import { InnerBlocks } from '@wordpress/block-editor';

export default function save() {
	return <InnerBlocks.Content />;
}
```

- [ ] **Step 7: Create `src/blocks/slider/index.tsx`**

```tsx
import { registerBlockType } from '@wordpress/blocks';
import metadata from './block.json';
import Edit from './edit';
import save from './save';
import './style.scss';

registerBlockType( metadata.name, { edit: Edit, save } );
```

- [ ] **Step 8: Create `src/blocks/slider/edit.tsx` (minimal — InnerBlocks only; controls added in Task 4)**

```tsx
import { useBlockProps, useInnerBlocksProps } from '@wordpress/block-editor';

type Attrs = {
	mediaPosition: string;
	panelColor: string;
};

const ALLOWED = [ 'pediment/slide' ];
const TEMPLATE: [ string, Record< string, unknown > ][] = [
	[ 'pediment/slide', {} ],
	[ 'pediment/slide', {} ],
];

export default function Edit( {
	attributes,
}: {
	attributes: Attrs;
	setAttributes: ( a: Partial< Attrs > ) => void;
} ) {
	const position = attributes.mediaPosition === 'right' ? 'right' : 'left';
	const blockProps = useBlockProps( {
		className: `starter-slider is-editor is-media-${ position }`,
		style: {
			[ '--slide-panel-bg' as string ]: attributes.panelColor || undefined,
		},
	} );
	const innerProps = useInnerBlocksProps( blockProps, {
		allowedBlocks: ALLOWED,
		template: TEMPLATE,
		templateLock: false,
		orientation: 'vertical',
	} );
	return <section { ...innerProps } />;
}
```

- [ ] **Step 9: Create `src/blocks/slider/style.scss`**

```scss
.starter-slider {
  position: relative;
  margin: var(--wp--preset--spacing--50) 0;

  &__track {
    border-radius: var(--r-panel, 28px);
    box-shadow: var(--wp--preset--shadow--medium);
    overflow: hidden;
  }

  // Controls are inert without JS — reveal them only once enhanced.
  &__arrow,
  &__dots { display: none; }

  &__arrow {
    position: absolute;
    top: 50%;
    transform: translateY(-50%);
    z-index: 2;
    width: 44px;
    height: 44px;
    border-radius: var(--r-pill, 999px);
    border: 1.5px solid var(--wp--preset--color--border-strong);
    background: var(--wp--preset--color--surface);
    color: var(--wp--preset--color--foreground);
    font-size: 1.5rem;
    line-height: 1;
    cursor: pointer;
    display: none;
    align-items: center;
    justify-content: center;
    transition: background-color .15s ease, color .15s ease;

    &:hover {
      background: var(--wp--preset--color--accent);
      color: var(--wp--preset--color--surface);
      border-color: var(--wp--preset--color--accent);
    }

    &:focus-visible {
      outline: 2px solid var(--wp--preset--color--accent);
      outline-offset: 2px;
    }

    &--prev { left: 14px; }
    &--next { right: 14px; }
  }

  &__dots {
    margin-top: var(--wp--preset--spacing--20);
    justify-content: center;
    gap: 10px;
  }

  &__dot {
    width: 10px;
    height: 10px;
    padding: 0;
    border-radius: var(--r-pill, 999px);
    border: 0;
    background: var(--wp--preset--color--border-strong);
    cursor: pointer;
    transition: background-color .15s ease, transform .15s ease;

    &:hover { background: var(--wp--preset--color--accent-hover); }

    &.is-current {
      background: var(--wp--preset--color--accent);
      transform: scale(1.25);
    }

    &:focus-visible {
      outline: 2px solid var(--wp--preset--color--accent);
      outline-offset: 2px;
    }
  }

  // --- Enhanced (JS active): turn the stack into a one-at-a-time carousel ---
  &.is-enhanced {
    .starter-slider__arrow { display: flex; }
    .starter-slider__dots { display: flex; }

    .starter-slide {
      grid-area: 1 / 1;
      opacity: 0;
      visibility: hidden;
      transition: opacity .35s ease;

      &.is-active {
        opacity: 1;
        visibility: visible;
      }
    }

    .starter-slider__track { display: grid; }
  }
}
```

- [ ] **Step 10: Build and lint**

Run: `npm run build && npm run lint:blocks && npm run lint:colors && npm run lint:js`
Expected: build succeeds; `✓ src/blocks/slider/`; no color literals; eslint clean.

Note: `view.ts` does not exist yet, so `viewScriptModule: file:./view.js` points at a missing file. Create a stub now so the build emits it and registration does not warn:

Create `src/blocks/slider/view.ts`:

```ts
// Interactivity is implemented in Task 3.
export {};
```

Re-run the build.

- [ ] **Step 11: Run the test to verify slider cases pass**

Run: `npx wp-env run tests-wordpress --env-cwd=wp-content/themes/pediment vendor/bin/phpunit --filter SliderTest`
Expected: PASS for all slider methods (track + slides, dot count + data-index, arrows + live region, default/right media position) and the Task 1 slide methods still pass.

- [ ] **Step 12: Commit**

```bash
git add src/blocks/slider build tests/phpunit/BlockRender/SliderTest.php
git commit -m "feat(slider): add pediment/slider parent block (static card, arrows, dots)"
```

---

## Task 3: Frontend interactivity (view.ts)

Replaces the `view.ts` stub with the Interactivity store so arrows, dots, and arrow keys navigate, wrapping around, with the active slide/dot reflected and the live region updated. Verified with a Playwright e2e spec.

**Files:**
- Modify: `src/blocks/slider/view.ts`
- Create: `tests/e2e/slider.spec.ts`

**Interfaces:**
- Consumes: the markup from Task 2 (`data-wp-interactive="pediment/slider"`, context `{active,count}`, `.starter-slide`, `.starter-slider__dot[data-index]`, `.starter-slider__live`, `.starter-slider__arrow--prev/--next`).
- Produces: store `pediment/slider` with `actions.next/prev/goTo/onKeydown` and `callbacks.init/render`; root gains `is-enhanced`; active slide gets `.is-active` + `aria-hidden="false"`; active dot gets `.is-current` + `aria-selected="true"` + `aria-current="true"`.

- [ ] **Step 1: Write the failing e2e test**

Create `tests/e2e/slider.spec.ts`:

```ts
import { test, expect, type Page } from '@playwright/test';
import { createPageWithContent, deletePageBySlug } from './utils';

const SLUG = 'e2e-slider';

const SLIDE = ( n: number ) =>
	`<!-- wp:pediment/slide --><!-- wp:heading --><h2>Slide ${ n }</h2><!-- /wp:heading --><!-- wp:paragraph --><p>Body ${ n }</p><!-- /wp:paragraph --><!-- /wp:pediment/slide -->`;

const MARKUP = `<!-- wp:pediment/slider -->${ SLIDE( 1 ) }${ SLIDE( 2 ) }${ SLIDE( 3 ) }<!-- /wp:pediment/slider -->`;

const slider = ( page: Page ) => page.locator( '.starter-slider' );
const activeHeading = ( page: Page ) =>
	page.locator( '.starter-slide.is-active h2' );

test.describe( 'image/content slider', () => {
	test.beforeAll( () => {
		deletePageBySlug( SLUG );
	} );
	test.afterAll( () => {
		deletePageBySlug( SLUG );
	} );

	test( 'enhances, shows first slide, and next/prev wrap around', async ( {
		page,
	} ) => {
		const url = createPageWithContent( SLUG, 'Slider', MARKUP );
		await page.goto( url );

		await expect( slider( page ) ).toHaveClass( /is-enhanced/ );
		await expect( activeHeading( page ) ).toHaveText( 'Slide 1' );

		await slider( page ).locator( '.starter-slider__arrow--next' ).click();
		await expect( activeHeading( page ) ).toHaveText( 'Slide 2' );

		// Wrap forward: 3 -> 1
		await slider( page ).locator( '.starter-slider__arrow--next' ).click();
		await expect( activeHeading( page ) ).toHaveText( 'Slide 3' );
		await slider( page ).locator( '.starter-slider__arrow--next' ).click();
		await expect( activeHeading( page ) ).toHaveText( 'Slide 1' );

		// Wrap backward: 1 -> 3
		await slider( page ).locator( '.starter-slider__arrow--prev' ).click();
		await expect( activeHeading( page ) ).toHaveText( 'Slide 3' );
	} );

	test( 'dots jump to a slide and reflect the current one', async ( {
		page,
	} ) => {
		const url = createPageWithContent( SLUG, 'Slider', MARKUP );
		await page.goto( url );

		const dots = slider( page ).locator( '.starter-slider__dot' );
		await expect( dots ).toHaveCount( 3 );

		await dots.nth( 2 ).click();
		await expect( activeHeading( page ) ).toHaveText( 'Slide 3' );
		await expect( dots.nth( 2 ) ).toHaveClass( /is-current/ );
		await expect( dots.nth( 2 ) ).toHaveAttribute( 'aria-current', 'true' );
	} );

	test( 'arrow keys navigate when focus is in the slider', async ( {
		page,
	} ) => {
		const url = createPageWithContent( SLUG, 'Slider', MARKUP );
		await page.goto( url );

		await slider( page ).locator( '.starter-slider__arrow--next' ).focus();
		await page.keyboard.press( 'ArrowRight' );
		await expect( activeHeading( page ) ).toHaveText( 'Slide 2' );
		await page.keyboard.press( 'ArrowLeft' );
		await expect( activeHeading( page ) ).toHaveText( 'Slide 1' );
	} );
} );
```

- [ ] **Step 2: Run the test to verify it fails**

Run: `npm run e2e -- slider` (wp-env must be running; build from Task 2 in place).
Expected: FAIL — `is-enhanced` never appears and `.is-active` is never set (view stub is a no-op).

- [ ] **Step 3: Implement `src/blocks/slider/view.ts`**

```ts
import { store, getContext, getElement } from '@wordpress/interactivity';

type Ctx = { active: number; count: number };

const wrap = ( i: number, n: number ) => ( n > 0 ? ( ( i % n ) + n ) % n : 0 );

/**
 * Apply the active index to the DOM imperatively, by document order. DOM order
 * is the single source of truth for slide/dot position, so no per-slide context
 * is needed.
 */
const paint = ( root: HTMLElement, active: number ) => {
	const slides = root.querySelectorAll< HTMLElement >( '.starter-slide' );
	const dots = root.querySelectorAll< HTMLElement >( '.starter-slider__dot' );

	slides.forEach( ( slide, i ) => {
		const on = i === active;
		slide.classList.toggle( 'is-active', on );
		slide.setAttribute( 'aria-hidden', on ? 'false' : 'true' );
	} );

	dots.forEach( ( dot, i ) => {
		const on = i === active;
		dot.classList.toggle( 'is-current', on );
		dot.setAttribute( 'aria-selected', on ? 'true' : 'false' );
		if ( on ) {
			dot.setAttribute( 'aria-current', 'true' );
		} else {
			dot.removeAttribute( 'aria-current' );
		}
	} );

	const live = root.querySelector< HTMLElement >( '.starter-slider__live' );
	if ( live ) {
		live.textContent = `${ active + 1 } / ${ slides.length }`;
	}
};

const { actions } = store( 'pediment/slider', {
	actions: {
		next() {
			const ctx = getContext< Ctx >();
			ctx.active = wrap( ctx.active + 1, ctx.count );
		},
		prev() {
			const ctx = getContext< Ctx >();
			ctx.active = wrap( ctx.active - 1, ctx.count );
		},
		goTo() {
			const ctx = getContext< Ctx >();
			const { ref } = getElement();
			const idx = Number( ref?.getAttribute( 'data-index' ) ?? 0 );
			ctx.active = wrap( idx, ctx.count );
		},
		onKeydown( event: KeyboardEvent ) {
			if ( event.key === 'ArrowRight' ) {
				actions.next();
			} else if ( event.key === 'ArrowLeft' ) {
				actions.prev();
			}
		},
	},
	callbacks: {
		init() {
			const { ref } = getElement();
			if ( ! ref ) {
				return;
			}
			ref.classList.add( 'is-enhanced' );
			const ctx = getContext< Ctx >();
			paint( ref as HTMLElement, ctx.active );
		},
		render() {
			const { ref } = getElement();
			if ( ! ref ) {
				return;
			}
			const ctx = getContext< Ctx >();
			paint( ref as HTMLElement, ctx.active );
		},
	},
} );
```

- [ ] **Step 4: Build**

Run: `npm run build`
Expected: build succeeds; `build/blocks/slider/view.js` regenerated.

- [ ] **Step 5: Run the e2e test to verify it passes**

Run: `npm run e2e -- slider`
Expected: PASS — all three tests green.

- [ ] **Step 6: Commit**

```bash
git add src/blocks/slider/view.ts build tests/e2e/slider.spec.ts
git commit -m "feat(slider): frontend interactivity (arrows, dots, keyboard, wrap)"
```

---

## Task 4: Inspector controls — image-side toggle + panel color picker

Adds the slider-level controls: an image-on-left toggle and a theme-palette-bound panel color picker. Verified by a render test (attributes drive the wrapper class + `--slide-panel-bg`) plus an e2e check.

**Files:**
- Modify: `src/blocks/slider/edit.tsx`
- Modify: `tests/phpunit/BlockRender/SliderTest.php` (panel color cases)
- Modify: `tests/e2e/slider.spec.ts` (assert color + position on the front end)

**Interfaces:**
- Consumes: attributes `mediaPosition`, `panelColor` (Task 2). The render already applies `is-media-{position}` and `--slide-panel-bg`/`--slide-panel-fg`.
- Produces: editor InspectorControls writing `mediaPosition` ("left"|"right") and `panelColor` (CSS color string from the theme palette or a custom pick).

- [ ] **Step 1: Write the failing render test (append to `SliderTest.php`)**

```php
	public function test_slider_applies_panel_color_to_css_var() {
		$html = $this->slider( '{"panelColor":"#0E7490"}' );
		$this->assertStringContainsString( '--slide-panel-bg:#0E7490', $html );
	}

	public function test_dark_panel_gets_light_text_token() {
		$html = $this->slider( '{"panelColor":"#0A1B33"}' );
		$this->assertStringContainsString( '--slide-panel-fg:var(--wp--preset--color--surface)', $html );
	}

	public function test_light_panel_gets_dark_text_token() {
		$html = $this->slider( '{"panelColor":"#E1F1F6"}' );
		$this->assertStringContainsString( '--slide-panel-fg:var(--wp--preset--color--foreground)', $html );
	}
```

- [ ] **Step 2: Run test to verify status**

Run: `npx wp-env run tests-wordpress --env-cwd=wp-content/themes/pediment vendor/bin/phpunit --filter SliderTest`
Expected: these three PASS already (render logic from Task 2 handles color + luminance). If any fail, fix `pediment_slider_panel_fg` / the inline style in `render.php` before continuing. (This step locks the render contract that the editor controls feed.)

- [ ] **Step 3: Replace `src/blocks/slider/edit.tsx` with the controls version**

```tsx
import { __ } from '@wordpress/i18n';
import {
	useBlockProps,
	useInnerBlocksProps,
	InspectorControls,
	PanelColorSettings,
} from '@wordpress/block-editor';
import { PanelBody, ToggleControl } from '@wordpress/components';

type Attrs = {
	mediaPosition: string;
	panelColor: string;
};

const ALLOWED = [ 'pediment/slide' ];
const TEMPLATE: [ string, Record< string, unknown > ][] = [
	[ 'pediment/slide', {} ],
	[ 'pediment/slide', {} ],
];

export default function Edit( {
	attributes,
	setAttributes,
}: {
	attributes: Attrs;
	setAttributes: ( a: Partial< Attrs > ) => void;
} ) {
	const position = attributes.mediaPosition === 'right' ? 'right' : 'left';
	const blockProps = useBlockProps( {
		className: `starter-slider is-editor is-media-${ position }`,
		style: {
			[ '--slide-panel-bg' as string ]:
				attributes.panelColor || undefined,
		},
	} );
	const innerProps = useInnerBlocksProps( blockProps, {
		allowedBlocks: ALLOWED,
		template: TEMPLATE,
		templateLock: false,
		orientation: 'vertical',
	} );

	return (
		<>
			<InspectorControls>
				<PanelBody title={ __( 'Layout', 'pediment' ) }>
					<ToggleControl
						label={ __( 'Image on the left', 'pediment' ) }
						checked={ position === 'left' }
						onChange={ ( v ) =>
							setAttributes( {
								mediaPosition: v ? 'left' : 'right',
							} )
						}
						help={ __(
							'Off places the image on the right of every slide.',
							'pediment'
						) }
					/>
				</PanelBody>
			</InspectorControls>
			<InspectorControls group="color">
				<PanelColorSettings
					title={ __( 'Panel', 'pediment' ) }
					colorSettings={ [
						{
							value: attributes.panelColor,
							onChange: ( c?: string ) =>
								setAttributes( { panelColor: c || '#0A1B33' } ),
							label: __( 'Panel background', 'pediment' ),
						},
					] }
				/>
			</InspectorControls>
			<section { ...innerProps } />
		</>
	);
}
```

Notes for the implementer:
- `PanelColorSettings` surfaces the theme palette swatches automatically (from `theme.json`) plus a custom picker; `onChange` yields the resolved CSS color string (hex for palette + custom). Storing that hex is exactly what `render.php` expects.
- `InspectorControls group="color"` docks the picker in the editor's Styles → Color area, consistent with core blocks.

- [ ] **Step 4: Add the front-end assertions to `tests/e2e/slider.spec.ts`**

Append this test inside the `describe`:

```ts
	test( 'panel color and image side apply on the front end', async ( {
		page,
	} ) => {
		const markup = `<!-- wp:pediment/slider {"mediaPosition":"right","panelColor":"#0E7490"} -->${ SLIDE(
			1
		) }${ SLIDE( 2 ) }<!-- /wp:pediment/slider -->`;
		const url = createPageWithContent( SLUG, 'Slider', markup );
		await page.goto( url );

		await expect( slider( page ) ).toHaveClass( /is-media-right/ );
		const panel = page
			.locator( '.starter-slide.is-active .starter-slide__panel' )
			.first();
		// --slide-panel-bg cascades to the panel background.
		await expect( panel ).toHaveCSS(
			'background-color',
			'rgb(14, 116, 144)'
		);
	} );
```

- [ ] **Step 5: Build and run tests**

Run: `npm run build && npm run lint:js`
Then the `SliderTest` PHPUnit suite (expect all PASS) and `npm run e2e -- slider` (expect all PASS).

- [ ] **Step 6: Commit**

```bash
git add src/blocks/slider/edit.tsx build tests
git commit -m "feat(slider): inspector controls for image side and panel color"
```

---

## Task 5: Register in kitchen-sink test, docs, and full verification

Wires the slider into the existing block-registration smoke test, adds a one-line changelog/docs note where appropriate, and runs the full gate.

**Files:**
- Modify: `tests/e2e/editor-blocks.spec.ts` (add slider entry)
- Modify: `CHANGELOG.md` only if the repo's release flow expects manual entries — otherwise rely on Conventional Commit messages (the repo uses release-please; do **not** hand-edit `CHANGELOG.md` if release-please owns it). Verify by checking `release-please-config.json` before editing.

**Interfaces:**
- Consumes: registered `pediment/slider` + `pediment/slide`.

- [ ] **Step 1: Add the slider to the kitchen-sink render list**

In `tests/e2e/editor-blocks.spec.ts`, add to `BLOCKS_TO_VERIFY`:

```ts
  { name: 'slider', cls: 'starter-slider', markup: '<!-- wp:pediment/slider --><!-- wp:pediment/slide --><!-- wp:paragraph --><p>x</p><!-- /wp:paragraph --><!-- /wp:pediment/slide --><!-- /wp:pediment/slider -->' },
```

- [ ] **Step 2: Run the kitchen-sink test**

Run: `npm run e2e -- editor-blocks`
Expected: PASS — `.starter-slider` visible alongside the other blocks.

- [ ] **Step 3: Full local gate**

Run, in order, and confirm each is green:
```bash
npm run build
npm run lint:blocks
npm run lint:colors
npm run lint:js
```
Then phpcs over the theme (per `phpcs.xml.dist`) — expect zero errors and zero warnings.
Then the full PHPUnit suite (`npx wp-env run tests-wordpress --env-cwd=wp-content/themes/pediment vendor/bin/phpunit`) and `npm run e2e` — expect green.

- [ ] **Step 4: Verify changelog ownership, then commit**

```bash
cat release-please-config.json
```
If release-please owns `CHANGELOG.md` (it does in this repo), skip editing it — the Conventional Commit subjects from Tasks 1–5 become the changelog. Then:

```bash
git add tests/e2e/editor-blocks.spec.ts
git commit -m "test(slider): include slider in block registration smoke test"
```

---

## Self-Review Notes (for the implementer)

- **Manual visual check** (not automatable here): insert a Slider in the Site Editor, upload images to two slides, confirm: full-bleed cover images, rounded shadowed card, square image/panel seam, arrows over the card edges, dots below, image-side toggle flips layout, panel color picker recolors the panel with readable text, mobile (<781px) stacks image-over-panel.
- **Accessibility check:** Tab reaches arrows + dots; Enter/Space activate; ArrowLeft/Right navigate when focus is inside; the `.starter-slider__live` region announces "n / N"; inactive slides are `aria-hidden`.
- **No-JS check:** disable JS → slides render as a readable vertical stack of cards; arrows/dots hidden.
