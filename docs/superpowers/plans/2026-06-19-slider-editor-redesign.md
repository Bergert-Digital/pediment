# Slider Editor Redesign Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Convert `pediment/slider` from InnerBlocks-authored slides to a self-contained block whose slides are a sidebar-managed `slides` array attribute, with the editor canvas rendering the finished slider (active slide, clickable arrows/dots). Delete `pediment/slide`.

**Architecture:** Single server-rendered block (`save: () => null`) modeled on `pediment/mega-menu`'s sidebar-repeater pattern. `render.php` loops the `slides` array to emit the same `.starter-slide` markup as before. `edit.tsx` provides an Inspector form (add/remove/reorder + per-slide fields) and a React canvas preview that mirrors `render.php`'s DOM; a shared TS luminance helper keeps editor text-contrast identical to PHP. Front-end interactivity (`view.ts`), styling, image-side toggle, and panel color are unchanged.

**Tech Stack:** WordPress block API v3, `@wordpress/scripts` build, TSX (`@wordpress/block-editor`, `@wordpress/components`, `@wordpress/data`, `@wordpress/element`), `@wordpress/interactivity` (unchanged `view.ts`), SCSS, PHP `render.php`, Jest (`wp-scripts test-unit-js`), PHPUnit (`do_blocks`), Playwright e2e.

## Global Constraints

- **Clean replacement, no migration.** Delete `src/blocks/slide/` and `build/blocks/slide/` entirely; no `deprecated` versions, no migration code. The slider is unreleased; no content uses the old form.
- **Block category:** `pediment` (already registered). Blocks auto-register from `build/blocks/<name>/` via `inc/register-blocks.php` — NO edits to PHP registration files.
- **Each block dir must contain** `block.json`, `render.php`, `edit.tsx` (enforced by `npm run lint:blocks`). After deletion only `src/blocks/slider/` remains for the slider.
- **NO color literals** (`#hex`, `rgb(`, `hsl(`) anywhere under `src/blocks/` — enforced for `.scss`/`.css` (`npm run lint:colors`) AND `.php` (custom phpcs `Pediment.NoColorLiteral` sniff). Use `var(--wp--preset--color--*)` / theme CSS vars. Hex IS allowed in `block.json`, `.ts`, `.tsx`.
- **phpcs** (`composer lint`) must pass with ZERO errors/warnings; pre-rendered/pre-escaped HTML uses the `// phpcs:ignore WordPress.Security.EscapeOutput` convention; the `ob_start()` / `echo ob_get_clean()` pattern is house style (keep it).
- **Escaping:** plain-text slide fields use `esc_html` (+ `nl2br` for body), urls `esc_url`, attributes `esc_attr`. No `wp_kses_post` on plain-text fields.
- **BEM-ish `starter-*` class names**; editor/user-facing strings wrapped in `__( '…', 'pediment' )`.
- **Slide fields (per slide):** `mediaId` (number), `altOverride` (string), `eyebrow` (string), `heading` (string), `body` (multiline string), `buttonText` (string), `buttonUrl` (string). Button renders only when BOTH `buttonText` and `buttonUrl` are non-empty.
- **Panel text contrast:** dark panel → `var(--wp--preset--color--surface)`; light panel → `var(--wp--preset--color--foreground)`; threshold/coefficients identical in PHP (`pediment_slider_panel_fg`) and the new TS helper (`panelFg`).
- **Default panel color** `#0A1B33`; default `mediaPosition` `"left"`; default `slides` `[]`.
- **Test commands (exact):**
  - PHPUnit: `npx wp-env run tests-wordpress --env-cwd=wp-content/themes/philadelphia vendor/bin/phpunit --filter SliderTest`
  - JS unit (Jest): `npm run test:js -- panel-fg`
  - e2e: `npm run build` then `npm run e2e -- slider editor-blocks`
  - phpcs: `composer lint`; lints: `npm run lint:blocks`, `npm run lint:colors`, `npm run lint:js`
  - wp-env must be running (`npm run env:start`); theme `philadelphia` active.

---

## File Structure

```
src/blocks/slider/
  block.json      # MODIFY: add `slides` array attr; update description + example
  render.php      # MODIFY: loop $slides array instead of echoing $content
  edit.tsx        # REWRITE: sidebar repeater + canvas preview (active slide, clickable nav)
  index.tsx       # MODIFY: import editor.scss (already imports style.scss); save stays null
  save.tsx        # unchanged (returns <InnerBlocks.Content/> → change to null; see Task 1)
  style.scss      # MODIFY: absorb slide layout CSS + add __eyebrow/__button + is-editor/empty
  view.ts         # UNCHANGED (Interactivity store)
  panel-fg.ts     # CREATE: shared hex→contrast-token helper (mirrors PHP)
  panel-fg.test.ts# CREATE: Jest unit test for panelFg

src/blocks/slide/ # DELETE entire directory
build/blocks/slide/ # DELETE (then rebuild)

tests/phpunit/BlockRender/SliderTest.php  # REWRITE for the slides-array model
tests/e2e/slider.spec.ts                  # MODIFY: build slider via slides attribute
tests/e2e/editor-blocks.spec.ts           # MODIFY: slider kitchen-sink entry → attribute markup
```

---

## Task 1: Convert the block — data model, front-end render, styles, delete slide block, PHPUnit + JS unit test

This is one cohesive conversion: the block cannot be half-migrated. After this task the slider renders from the `slides` attribute on the front end AND is fully authorable in the editor sidebar with a finished-slider canvas preview; `pediment/slide` is gone; `SliderTest` (PHPUnit) and `panelFg` (Jest) are green. e2e + kitchen-sink + full browser gate are Task 2.

**Files:**
- Modify: `src/blocks/slider/block.json`, `src/blocks/slider/render.php`, `src/blocks/slider/style.scss`, `src/blocks/slider/index.tsx`, `src/blocks/slider/save.tsx`
- Rewrite: `src/blocks/slider/edit.tsx`
- Create: `src/blocks/slider/panel-fg.ts`, `src/blocks/slider/panel-fg.test.ts`
- Delete: `src/blocks/slide/` (whole dir), `build/blocks/slide/` (whole dir)
- Rewrite: `tests/phpunit/BlockRender/SliderTest.php`

**Interfaces:**
- Produces: block `pediment/slider` with attributes `mediaPosition: string` (default "left"), `panelColor: string` (default "#0A1B33"), `slides: array` (default []). Each slide object: `{ mediaId:number, altOverride:string, eyebrow:string, heading:string, body:string, buttonText:string, buttonUrl:string }`. Front-end markup per slide unchanged: `<div class="starter-slide"><figure class="starter-slide__media">img|placeholder</figure><div class="starter-slide__panel">eyebrow? h2.heading? p.body? a.button?</div></div>`.
- Produces: `panelFg(bg: string): string` in `src/blocks/slider/panel-fg.ts` — returns `'var(--wp--preset--color--surface)'` for dark/unparseable bg, `'var(--wp--preset--color--foreground)'` for light bg.
- Consumes: existing `pediment_slider_panel_fg()` PHP helper (kept in `render.php`), `view.ts` store (`pediment/slider`, context `{active,count}`), and the `.starter-slider` chrome / `is-media-*` / `is-enhanced` CSS.

- [ ] **Step 1: Write the failing JS unit test for the shared contrast helper**

Create `src/blocks/slider/panel-fg.test.ts`:

```ts
import { panelFg } from './panel-fg';

describe( 'panelFg', () => {
	it( 'returns the light token for a dark background', () => {
		expect( panelFg( '#0A1B33' ) ).toBe(
			'var(--wp--preset--color--surface)'
		);
	} );

	it( 'returns the dark token for a light background', () => {
		expect( panelFg( '#E1F1F6' ) ).toBe(
			'var(--wp--preset--color--foreground)'
		);
	} );

	it( 'expands 3-digit hex', () => {
		expect( panelFg( '#000' ) ).toBe(
			'var(--wp--preset--color--surface)'
		);
		expect( panelFg( '#fff' ) ).toBe(
			'var(--wp--preset--color--foreground)'
		);
	} );

	it( 'falls back to the light token for non-hex input', () => {
		expect( panelFg( 'var(--wp--preset--color--primary)' ) ).toBe(
			'var(--wp--preset--color--surface)'
		);
	} );
} );
```

- [ ] **Step 2: Run the JS unit test to verify it fails**

Run: `npm run test:js -- panel-fg`
Expected: FAIL — `Cannot find module './panel-fg'`.

- [ ] **Step 3: Create the shared contrast helper `src/blocks/slider/panel-fg.ts`**

```ts
/**
 * Readable foreground CSS-var token for a panel background color.
 *
 * Mirror of `pediment_slider_panel_fg()` in render.php — keep the 0.55
 * threshold and Rec. 709 coefficients in sync with the PHP source of truth so
 * the editor preview's text contrast matches the front end exactly.
 */
export function panelFg( bg: string ): string {
	const light = 'var(--wp--preset--color--surface)';
	const dark = 'var(--wp--preset--color--foreground)';
	let hex = ( bg ?? '' ).replace( /^#/, '' );
	if ( hex.length === 3 ) {
		hex = hex[ 0 ] + hex[ 0 ] + hex[ 1 ] + hex[ 1 ] + hex[ 2 ] + hex[ 2 ];
	}
	if ( ! /^[0-9a-fA-F]{6}$/.test( hex ) ) {
		return light;
	}
	const r = parseInt( hex.slice( 0, 2 ), 16 ) / 255;
	const g = parseInt( hex.slice( 2, 4 ), 16 ) / 255;
	const b = parseInt( hex.slice( 4, 6 ), 16 ) / 255;
	const lum = 0.2126 * r + 0.7152 * g + 0.0722 * b;
	return lum < 0.55 ? light : dark;
}
```

- [ ] **Step 4: Run the JS unit test to verify it passes**

Run: `npm run test:js -- panel-fg`
Expected: PASS (4 tests).

- [ ] **Step 5: Write the failing PHPUnit suite (rewrite `tests/phpunit/BlockRender/SliderTest.php`)**

Replace the entire file with:

```php
<?php

class SliderTest extends WP_UnitTestCase {
	/**
	 * Render the slider from an attributes array (no inner blocks).
	 *
	 * @param array $attrs Block attributes (e.g. slides, panelColor).
	 * @return string
	 */
	private function slider( array $attrs ): string {
		return do_blocks( '<!-- wp:pediment/slider ' . wp_json_encode( $attrs ) . ' /-->' );
	}

	public function test_renders_one_panel_and_dot_per_slide() {
		$html = $this->slider(
			array(
				'slides' => array(
					array( 'heading' => 'One' ),
					array( 'heading' => 'Two' ),
					array( 'heading' => 'Three' ),
				),
			)
		);
		$this->assertSame( 3, substr_count( $html, 'starter-slide__panel' ) );
		$this->assertSame( 3, substr_count( $html, 'starter-slider__dot' ) );
		$this->assertStringContainsString( 'One', $html );
		$this->assertStringContainsString( 'Three', $html );
	}

	public function test_renders_eyebrow_heading_body_with_line_breaks() {
		$html = $this->slider(
			array(
				'slides' => array(
					array(
						'eyebrow' => 'Kicker',
						'heading' => 'Title',
						'body'    => "Line A\nLine B",
					),
				),
			)
		);
		$this->assertStringContainsString( '<p class="starter-slide__eyebrow">Kicker</p>', $html );
		$this->assertStringContainsString( '<h2 class="starter-slide__heading">Title</h2>', $html );
		$this->assertStringContainsString( 'Line A<br', $html );
	}

	public function test_eyebrow_omitted_when_empty() {
		$html = $this->slider( array( 'slides' => array( array( 'heading' => 'H' ) ) ) );
		$this->assertStringNotContainsString( 'starter-slide__eyebrow', $html );
	}

	public function test_button_requires_both_text_and_url() {
		$only_text = $this->slider(
			array( 'slides' => array( array( 'heading' => 'H', 'buttonText' => 'Go' ) ) )
		);
		$this->assertStringNotContainsString( 'starter-slide__button', $only_text );

		$both = $this->slider(
			array(
				'slides' => array(
					array( 'heading' => 'H', 'buttonText' => 'Go', 'buttonUrl' => '/x' ),
				),
			)
		);
		$this->assertStringContainsString( '<a class="starter-slide__button" href="/x">Go</a>', $both );
	}

	public function test_placeholder_when_no_media() {
		$html = $this->slider(
			array( 'slides' => array( array( 'heading' => 'H', 'mediaId' => 0 ) ) )
		);
		$this->assertStringContainsString( 'starter-slide__placeholder', $html );
		$this->assertStringNotContainsString( '<img', $html );
	}

	public function test_media_renders_img_with_alt_override() {
		$id   = $this->factory->attachment->create_upload_object( DIR_TESTDATA . '/images/canola.jpg' );
		$html = $this->slider(
			array( 'slides' => array( array( 'mediaId' => $id, 'altOverride' => 'Yellow' ) ) )
		);
		$this->assertStringContainsString( 'starter-slide__img', $html );
		$this->assertStringContainsString( 'alt="Yellow"', $html );
		wp_delete_attachment( $id, true );
	}

	public function test_media_position_left_and_right() {
		$l = $this->slider( array( 'slides' => array( array( 'heading' => 'H' ) ) ) );
		$this->assertStringContainsString( 'is-media-left', $l );

		$r = $this->slider(
			array( 'mediaPosition' => 'right', 'slides' => array( array( 'heading' => 'H' ) ) )
		);
		$this->assertStringContainsString( 'is-media-right', $r );
		$this->assertStringNotContainsString( 'is-media-left', $r );
	}

	public function test_panel_color_and_luminance_tokens() {
		$dark = $this->slider(
			array( 'panelColor' => '#0A1B33', 'slides' => array( array( 'heading' => 'H' ) ) )
		);
		$this->assertStringContainsString( '--slide-panel-bg:#0A1B33', $dark );
		$this->assertStringContainsString( '--slide-panel-fg:var(--wp--preset--color--surface)', $dark );

		$light = $this->slider(
			array( 'panelColor' => '#E1F1F6', 'slides' => array( array( 'heading' => 'H' ) ) )
		);
		$this->assertStringContainsString( '--slide-panel-fg:var(--wp--preset--color--foreground)', $light );
	}

	public function test_single_slide_hides_arrows_and_dots() {
		$html = $this->slider( array( 'slides' => array( array( 'heading' => 'Only' ) ) ) );
		$this->assertStringNotContainsString( 'starter-slider__arrow', $html );
		$this->assertStringNotContainsString( 'starter-slider__dot', $html );
	}

	public function test_empty_slides_renders_chrome_but_no_panels() {
		$html = $this->slider( array( 'slides' => array() ) );
		$this->assertStringContainsString( 'starter-slider', $html );
		$this->assertStringNotContainsString( 'starter-slide__panel', $html );
	}
}
```

- [ ] **Step 6: Run PHPUnit to verify it fails**

Run: `npx wp-env run tests-wordpress --env-cwd=wp-content/themes/philadelphia vendor/bin/phpunit --filter SliderTest`
Expected: FAIL — current `render.php` ignores `slides` and reads inner-block `$content`, so panels/eyebrow/button assertions fail.

- [ ] **Step 7: Update `src/blocks/slider/block.json`**

Replace the `description`, `attributes`, and `example` (keep everything else):

```json
{
	"$schema": "https://schemas.wp.org/trunk/block.json",
	"apiVersion": 3,
	"name": "pediment/slider",
	"title": "Slider",
	"category": "pediment",
	"description": "An image/content slider: one slide at a time, each pairing a full-bleed image with a colored content panel. Slides are managed in the block settings sidebar.",
	"textdomain": "pediment",
	"supports": { "html": false, "align": [ "wide", "full" ] },
	"attributes": {
		"mediaPosition": { "type": "string", "default": "left" },
		"panelColor": { "type": "string", "default": "#0A1B33" },
		"slides": { "type": "array", "default": [] }
	},
	"example": {
		"attributes": {
			"slides": [
				{ "eyebrow": "Lernen", "heading": "Lebenslanges Lernen", "body": "Wir bringen unser Organismus immer auf den neuesten Stand." },
				{ "eyebrow": "Wachsen", "heading": "Gemeinsam wachsen", "body": "Tägliche Team-Updates und ein intensiver Austausch." }
			]
		},
		"viewportWidth": 1280
	},
	"editorScript": "file:./index.js",
	"editorStyle": "file:./style-index.css",
	"style": "file:./style-index.css",
	"viewScriptModule": "file:./view.js",
	"render": "file:./render.php"
}
```

- [ ] **Step 8: Rewrite `src/blocks/slider/render.php` to loop the `slides` array**

Replace the file with (the `pediment_slider_panel_fg` helper block is unchanged from the current file; the loop body replaces the old `$content` echo):

```php
<?php
/**
 * Server-side render for pediment/slider.
 *
 * @var array    $attributes
 * @var WP_Block $block
 */

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

$position = ( isset( $attributes['mediaPosition'] ) && 'right' === $attributes['mediaPosition'] ) ? 'right' : 'left';
$bg       = ( isset( $attributes['panelColor'] ) && '' !== $attributes['panelColor'] )
	? (string) $attributes['panelColor']
	: 'var(--wp--preset--color--primary)';
$fg       = pediment_slider_panel_fg( $bg );
$slides   = ( isset( $attributes['slides'] ) && is_array( $attributes['slides'] ) ) ? $attributes['slides'] : array();
$count    = count( $slides );

$style   = sprintf( '--slide-panel-bg:%s;--slide-panel-fg:%s;', esc_attr( $bg ), esc_attr( $fg ) );
$wrapper = get_block_wrapper_attributes(
	array(
		'class' => 'starter-slider is-media-' . $position,
		'style' => $style,
	)
);

$context = wp_json_encode(
	array(
		'active' => 0,
		'count'  => $count,
	)
);

ob_start();
?>
<section <?php echo $wrapper; // phpcs:ignore WordPress.Security.EscapeOutput ?>
	data-wp-interactive="pediment/slider"
	data-wp-context='<?php echo esc_attr( $context ); ?>'
	data-wp-init="callbacks.init"
	data-wp-watch="callbacks.render"
	data-wp-on--keydown="actions.onKeydown"
	role="group" aria-roledescription="carousel" tabindex="-1">
	<div class="starter-slider__track">
		<?php
		foreach ( $slides as $slide ) :
			$media_id     = isset( $slide['mediaId'] ) ? (int) $slide['mediaId'] : 0;
			$alt_override = isset( $slide['altOverride'] ) ? (string) $slide['altOverride'] : '';
			$eyebrow      = isset( $slide['eyebrow'] ) ? (string) $slide['eyebrow'] : '';
			$heading      = isset( $slide['heading'] ) ? (string) $slide['heading'] : '';
			$body         = isset( $slide['body'] ) ? (string) $slide['body'] : '';
			$btn_text     = isset( $slide['buttonText'] ) ? (string) $slide['buttonText'] : '';
			$btn_url      = isset( $slide['buttonUrl'] ) ? (string) $slide['buttonUrl'] : '';

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
			?>
			<div class="starter-slide">
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
					<?php if ( '' !== $eyebrow ) : ?>
						<p class="starter-slide__eyebrow"><?php echo esc_html( $eyebrow ); ?></p>
					<?php endif; ?>
					<?php if ( '' !== $heading ) : ?>
						<h2 class="starter-slide__heading"><?php echo esc_html( $heading ); ?></h2>
					<?php endif; ?>
					<?php if ( '' !== $body ) : ?>
						<p class="starter-slide__body"><?php echo nl2br( esc_html( $body ) ); // phpcs:ignore WordPress.Security.EscapeOutput -- esc_html escaped; nl2br only inserts <br /> ?></p>
					<?php endif; ?>
					<?php if ( '' !== $btn_text && '' !== $btn_url ) : ?>
						<a class="starter-slide__button" href="<?php echo esc_url( $btn_url ); ?>"><?php echo esc_html( $btn_text ); ?></a>
					<?php endif; ?>
				</div>
			</div>
			<?php
		endforeach;
		?>
	</div>
	<?php if ( $count > 1 ) : ?>
	<button type="button" class="starter-slider__arrow starter-slider__arrow--prev" aria-label="<?php esc_attr_e( 'Vorherige Folie', 'pediment' ); ?>" data-wp-on--click="actions.prev">
		<span aria-hidden="true">&lsaquo;</span>
	</button>
	<button type="button" class="starter-slider__arrow starter-slider__arrow--next" aria-label="<?php esc_attr_e( 'Nächste Folie', 'pediment' ); ?>" data-wp-on--click="actions.next">
		<span aria-hidden="true">&rsaquo;</span>
	</button>
		<div class="starter-slider__pagination" role="group" aria-label="<?php esc_attr_e( 'Folien', 'pediment' ); ?>">
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

- [ ] **Step 9: Replace `src/blocks/slider/style.scss` (absorb slide layout + add eyebrow/button + editor preview)**

```scss
.starter-slider {
  position: relative;
  margin: var(--wp--preset--spacing--50) 0;

  &__track {
    border-radius: var(--r-panel, 28px);
    box-shadow: var(--wp--preset--shadow--medium);
    overflow: hidden;
  }

  // Controls are inert without JS — reveal them only once enhanced (front end)
  // or in the editor preview (is-editor, set by edit.tsx).
  &__arrow,
  &__pagination { display: none; }

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

  &__pagination {
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

  // Empty-state placeholder shown in the editor when there are no slides yet.
  &__empty {
    padding: var(--wp--preset--spacing--40);
    text-align: center;
    border: 1px dashed var(--wp--preset--color--border-strong);
    border-radius: var(--r-panel, 28px);
    color: var(--wp--preset--color--foreground-muted);
  }

  // --- Enhanced (front-end JS active): one-at-a-time carousel ---
  &.is-enhanced {
    .starter-slider__arrow { display: flex; }
    .starter-slider__pagination { display: flex; }

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

  // --- Editor preview: only the active slide is rendered, so no stacking is
  //     needed; just reveal the (display-only) controls. is-editor is set by
  //     edit.tsx and never appears on the front end. ---
  &.is-editor {
    .starter-slider__arrow { display: flex; }
    .starter-slider__pagination { display: flex; }
  }
}

// Slide layout (formerly in the deleted slide block's stylesheet).
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

  // Front-end stand-in shown until an image is set.
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
  }

  &__eyebrow {
    margin: 0;
    font-size: 0.8125rem;
    font-weight: 700;
    letter-spacing: 0.08em;
    text-transform: uppercase;
    opacity: 0.75;
  }

  &__heading {
    margin: 0;
    color: inherit;
  }

  &__body {
    margin: 0;
  }

  &__button {
    align-self: flex-start;
    display: inline-flex;
    align-items: center;
    padding: 12px 22px;
    border-radius: var(--r-pill, 999px);
    background: var(--slide-panel-fg, var(--wp--preset--color--surface));
    color: var(--slide-panel-bg, var(--wp--preset--color--primary));
    text-decoration: none;
    font-weight: 700;
    transition: opacity .15s ease;

    &:hover { opacity: 0.9; }

    &:focus-visible {
      outline: 2px solid var(--slide-panel-fg, var(--wp--preset--color--surface));
      outline-offset: 2px;
    }
  }
}

// Image side. The `is-media-*` modifier lives on the `.starter-slider` ancestor.
// Default (is-media-left) keeps DOM order — image left, panel right — so only the
// flipped case needs rules: panel first (left), media second (right).
.starter-slider.is-media-right {
  .starter-slide__panel { order: 1; }
  .starter-slide__media { order: 2; }
}

@media (max-width: 781px) {
  .starter-slide {
    grid-template-columns: 1fr;

    &__img,
    &__placeholder { aspect-ratio: 16 / 9; }
  }

  // Single column: always stack image on top, regardless of the desktop side.
  .starter-slider.is-media-right {
    .starter-slide__media { order: -1; }
    .starter-slide__panel { order: 0; }
  }
}
```

- [ ] **Step 10: Change `src/blocks/slider/save.tsx` to return `null`**

The block is fully server-rendered from attributes (no inner blocks). Replace the file with:

```tsx
export default function save() {
	return null;
}
```

- [ ] **Step 11: Rewrite `src/blocks/slider/edit.tsx`**

```tsx
import { __ } from '@wordpress/i18n';
import {
	useBlockProps,
	InspectorControls,
	PanelColorSettings,
	MediaUpload,
	MediaUploadCheck,
} from '@wordpress/block-editor';
import {
	PanelBody,
	TextControl,
	TextareaControl,
	ToggleControl,
	Button,
} from '@wordpress/components';
import { useSelect } from '@wordpress/data';
import { useState } from '@wordpress/element';
import { panelFg } from './panel-fg';

type Slide = {
	mediaId: number;
	altOverride: string;
	eyebrow: string;
	heading: string;
	body: string;
	buttonText: string;
	buttonUrl: string;
};

type Attrs = {
	mediaPosition: string;
	panelColor: string;
	slides: Slide[];
};

const emptySlide = (): Slide => ( {
	mediaId: 0,
	altOverride: '',
	eyebrow: '',
	heading: '',
	body: '',
	buttonText: '',
	buttonUrl: '',
} );

function move< T >( arr: T[], from: number, to: number ): T[] {
	if ( to < 0 || to >= arr.length ) {
		return arr;
	}
	const copy = arr.slice();
	const [ item ] = copy.splice( from, 1 );
	copy.splice( to, 0, item );
	return copy;
}

const has = ( s: string ) => ( s ?? '' ).trim() !== '';

function SlideImage( { slide }: { slide: Slide } ) {
	const media = useSelect(
		( select: any ) =>
			slide.mediaId ? select( 'core' ).getMedia( slide.mediaId ) : null,
		[ slide.mediaId ]
	);
	const url = media ? ( media as any ).source_url : '';
	if ( ! url ) {
		return (
			<span className="starter-slide__placeholder" aria-hidden="true">
				<svg
					viewBox="0 0 24 24"
					fill="none"
					stroke="currentColor"
					strokeWidth="1.5"
					strokeLinecap="round"
					strokeLinejoin="round"
				>
					<rect x="3" y="3" width="18" height="18" rx="2" />
					<circle cx="8.5" cy="8.5" r="1.5" />
					<path d="M21 15l-5-5L5 21" />
				</svg>
			</span>
		);
	}
	return (
		<img
			className="starter-slide__img"
			src={ url }
			alt={ slide.altOverride || ( media as any ).alt_text || '' }
		/>
	);
}

export default function Edit( {
	attributes,
	setAttributes,
}: {
	attributes: Attrs;
	setAttributes: ( a: Partial< Attrs > ) => void;
} ) {
	const position = attributes.mediaPosition === 'right' ? 'right' : 'left';
	const bg = has( attributes.panelColor ) ? attributes.panelColor : '#0A1B33';
	const slides = attributes.slides ?? [];
	const [ active, setActive ] = useState( 0 );
	const [ autoOpen, setAutoOpen ] = useState( -1 );

	const activeIndex = Math.min( active, Math.max( 0, slides.length - 1 ) );

	const commit = ( next: Slide[] ) => setAttributes( { slides: next } );
	const updateSlide = ( i: number, patch: Partial< Slide > ) =>
		commit(
			slides.map( ( s, idx ) => ( idx === i ? { ...s, ...patch } : s ) )
		);

	const blockProps = useBlockProps( {
		className: `starter-slider is-editor is-media-${ position }`,
		style: {
			[ '--slide-panel-bg' as string ]: bg,
			[ '--slide-panel-fg' as string ]: panelFg( bg ),
		},
	} );

	const current = slides[ activeIndex ];

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
			<InspectorControls>
				{ slides.map( ( slide, i ) => (
					<PanelBody
						key={ i }
						title={ `${ __( 'Slide', 'pediment' ) } ${ i + 1 }` }
						initialOpen={ i === autoOpen }
						onToggle={ ( open: boolean ) => {
							if ( open ) {
								setActive( i );
							}
						} }
					>
						<MediaUploadCheck>
							<MediaUpload
								allowedTypes={ [ 'image' ] }
								value={ slide.mediaId }
								onSelect={ ( m: any ) =>
									updateSlide( i, { mediaId: m.id } )
								}
								render={ ( {
									open,
								}: {
									open: () => void;
								} ) => (
									<div className="starter-slider-form__media">
										<Button
											variant="secondary"
											onClick={ open }
										>
											{ slide.mediaId
												? __( 'Replace image', 'pediment' )
												: __( 'Select image', 'pediment' ) }
										</Button>
										{ slide.mediaId ? (
											<Button
												variant="link"
												isDestructive
												onClick={ () =>
													updateSlide( i, {
														mediaId: 0,
													} )
												}
											>
												{ __( 'Remove image', 'pediment' ) }
											</Button>
										) : null }
									</div>
								) }
							/>
						</MediaUploadCheck>
						<TextControl
							label={ __( 'Alt text override', 'pediment' ) }
							value={ slide.altOverride }
							onChange={ ( v ) =>
								updateSlide( i, { altOverride: v } )
							}
						/>
						<TextControl
							label={ __( 'Eyebrow', 'pediment' ) }
							value={ slide.eyebrow }
							onChange={ ( v ) => updateSlide( i, { eyebrow: v } ) }
						/>
						<TextControl
							label={ __( 'Heading', 'pediment' ) }
							value={ slide.heading }
							onChange={ ( v ) => updateSlide( i, { heading: v } ) }
						/>
						<TextareaControl
							label={ __( 'Body', 'pediment' ) }
							value={ slide.body }
							onChange={ ( v ) => updateSlide( i, { body: v } ) }
						/>
						<TextControl
							label={ __( 'Button text', 'pediment' ) }
							value={ slide.buttonText }
							onChange={ ( v ) =>
								updateSlide( i, { buttonText: v } )
							}
						/>
						<TextControl
							label={ __( 'Button URL', 'pediment' ) }
							type="url"
							value={ slide.buttonUrl }
							onChange={ ( v ) =>
								updateSlide( i, { buttonUrl: v } )
							}
						/>
						<div className="starter-slider-form__toolbar">
							<Button
								size="small"
								variant="secondary"
								aria-label={ __( 'Move slide up', 'pediment' ) }
								disabled={ i === 0 }
								onClick={ () => commit( move( slides, i, i - 1 ) ) }
							>
								↑
							</Button>
							<Button
								size="small"
								variant="secondary"
								aria-label={ __( 'Move slide down', 'pediment' ) }
								disabled={ i === slides.length - 1 }
								onClick={ () => commit( move( slides, i, i + 1 ) ) }
							>
								↓
							</Button>
							<Button
								size="small"
								isDestructive
								variant="tertiary"
								onClick={ () => {
									commit(
										slides.filter( ( _, idx ) => idx !== i )
									);
									setActive( 0 );
								} }
							>
								{ __( 'Remove slide', 'pediment' ) }
							</Button>
						</div>
					</PanelBody>
				) ) }
				<PanelBody title={ __( 'Slides', 'pediment' ) }>
					<Button
						variant="primary"
						onClick={ () => {
							const next = [ ...slides, emptySlide() ];
							setAutoOpen( next.length - 1 );
							setActive( next.length - 1 );
							commit( next );
						} }
					>
						{ __( 'Add slide', 'pediment' ) }
					</Button>
				</PanelBody>
			</InspectorControls>

			<div { ...blockProps }>
				{ slides.length === 0 ? (
					<div className="starter-slider__empty">
						{ __(
							'Add your first slide from the block settings sidebar.',
							'pediment'
						) }
					</div>
				) : (
					<>
						<div className="starter-slider__track">
							<div className="starter-slide is-active">
								<figure className="starter-slide__media">
									<SlideImage slide={ current } />
								</figure>
								<div className="starter-slide__panel">
									{ has( current.eyebrow ) && (
										<p className="starter-slide__eyebrow">
											{ current.eyebrow }
										</p>
									) }
									{ has( current.heading ) && (
										<h2 className="starter-slide__heading">
											{ current.heading }
										</h2>
									) }
									{ has( current.body ) && (
										<p className="starter-slide__body">
											{ current.body }
										</p>
									) }
									{ has( current.buttonText ) &&
										has( current.buttonUrl ) && (
											<span className="starter-slide__button">
												{ current.buttonText }
											</span>
										) }
								</div>
							</div>
						</div>
						{ slides.length > 1 && (
							<>
								<button
									type="button"
									className="starter-slider__arrow starter-slider__arrow--prev"
									aria-label={ __( 'Previous slide', 'pediment' ) }
									onClick={ () =>
										setActive(
											( activeIndex - 1 + slides.length ) %
												slides.length
										)
									}
								>
									<span aria-hidden="true">&lsaquo;</span>
								</button>
								<button
									type="button"
									className="starter-slider__arrow starter-slider__arrow--next"
									aria-label={ __( 'Next slide', 'pediment' ) }
									onClick={ () =>
										setActive(
											( activeIndex + 1 ) % slides.length
										)
									}
								>
									<span aria-hidden="true">&rsaquo;</span>
								</button>
								<div
									className="starter-slider__pagination"
									role="group"
									aria-label={ __( 'Slides', 'pediment' ) }
								>
									{ slides.map( ( _, i ) => (
										<button
											key={ i }
											type="button"
											className={
												i === activeIndex
													? 'starter-slider__dot is-current'
													: 'starter-slider__dot'
											}
											aria-label={ `${ __(
												'Go to slide',
												'pediment'
											) } ${ i + 1 }` }
											onClick={ () => setActive( i ) }
										/>
									) ) }
								</div>
							</>
						) }
					</>
				) }
			</div>
		</>
	);
}
```

- [ ] **Step 12: Confirm `src/blocks/slider/index.tsx` is correct**

It should import the stylesheet and register with the `null` save. Ensure it reads exactly:

```tsx
import { registerBlockType } from '@wordpress/blocks';
import metadata from './block.json';
import Edit from './edit';
import save from './save';
import './style.scss';

registerBlockType( metadata.name, { edit: Edit, save } );
```

(If the current file differs, make it match. No `editor.scss` import is needed — the `is-editor` / `__empty` rules live in `style.scss`.)

- [ ] **Step 13: Delete the slide block**

Use `git rm` for both — `build/` is gitignored but the artifacts are force-tracked, so `git rm` stages the deletion cleanly (a plain `rm` would leave the deletion unstaged):

```bash
git rm -r src/blocks/slide
git rm -r build/blocks/slide
```

- [ ] **Step 14: Build, then run the JS + PHPUnit suites**

```bash
npm run build
```
Expected: compiles; `build/blocks-manifest.php` regenerated (no `slide` entry). Then:

Run: `npm run test:js -- panel-fg` → PASS (4).
Run: `npx wp-env run tests-wordpress --env-cwd=wp-content/themes/philadelphia vendor/bin/phpunit --filter SliderTest` → PASS (all methods).

- [ ] **Step 15: Lints + phpcs**

```bash
npm run lint:blocks
npm run lint:colors
npm run lint:js
composer lint
```
Expected: all clean. `lint:blocks` lists only `src/blocks/slider/` for the slider (no `slide`). `lint:colors` clean (no literals in scss). `composer lint` 0 errors (NoColorLiteral clean — render.php uses only `var(...)` and attribute values, no hex literal).

- [ ] **Step 16: Commit**

The slide deletions (src + build) are already staged from Step 13. Stage the slider source, its rebuilt artifacts, the regenerated manifest, and the test with explicit `build/...` paths (`git add build` warns because `build/` is gitignored, though tracked files still stage):

```bash
git add src/blocks/slider build/blocks/slider build/blocks-manifest.php tests/phpunit/BlockRender/SliderTest.php
git commit -m "feat(slider): sidebar-managed slides + finished-slider editor preview

Convert pediment/slider to a self-contained block with a slides array
attribute (image + eyebrow + heading + body + optional button per slide).
Slides are managed in the Inspector sidebar; the canvas renders the active
slide with clickable arrows/dots. Delete the pediment/slide child block."
```

---

## Task 2: e2e, kitchen-sink, full gate, and browser verification

Update the integration tests to the attribute-based markup, run the complete gate, and verify the new editor in a browser (the editor UI is not covered by the front-end e2e).

**Files:**
- Modify: `tests/e2e/slider.spec.ts`, `tests/e2e/editor-blocks.spec.ts`

**Interfaces:**
- Consumes: the converted block from Task 1 (`slides` array attribute; front-end markup unchanged per slide).

- [ ] **Step 1: Update `tests/e2e/slider.spec.ts` to build the slider from a `slides` attribute**

Replace the `SLIDE` / `MARKUP` helpers (top of file) with:

```ts
const slideObj = ( n: number ) => ( {
	heading: `Slide ${ n }`,
	body: `Body ${ n }`,
} );

const sliderMarkup = (
	slides: Array< Record< string, unknown > >,
	attrs: Record< string, unknown > = {}
) => `<!-- wp:pediment/slider ${ JSON.stringify( { ...attrs, slides } ) } /-->`;

const MARKUP = sliderMarkup( [ slideObj( 1 ), slideObj( 2 ), slideObj( 3 ) ] );
```

Then update the two places that build markup:
- Every `createPageWithContent( SLUG, 'Slider', MARKUP )` call stays as-is (now uses the new `MARKUP`).
- In the "panel color and image side" test, replace the inline `markup` with:

```ts
		const markup = sliderMarkup(
			[ slideObj( 1 ), slideObj( 2 ) ],
			{ mediaPosition: 'right', panelColor: '#0E7490' }
		);
```

Leave all assertions unchanged — `activeHeading` (`.starter-slide.is-active h2`), the dot/keyboard/wrap checks, the panel `background-color`, and the `activeMediaPanelX` layout assertions all still hold (the front-end markup per slide is identical to before).

- [ ] **Step 2: Update `tests/e2e/editor-blocks.spec.ts` slider entry**

Change the slider line in `BLOCKS_TO_VERIFY` to attribute-based markup:

```ts
  { name: 'slider', cls: 'starter-slider', markup: '<!-- wp:pediment/slider {"slides":[{"heading":"x"}]} /-->' },
```

- [ ] **Step 3: Build and run the e2e specs**

```bash
npm run build
npm run e2e -- slider editor-blocks
```
Expected: all pass (the 4 slider tests + the kitchen-sink test). If the slider tests fail on the layout assertion, confirm Task 1's `is-media-*` CSS made it into `build/blocks/slider/style-index.css`.

- [ ] **Step 4: Full gate**

Run and confirm each is green:
```bash
npm run build
npm run lint:blocks
npm run lint:colors
npm run lint:js
composer lint
npm run test:js
npx wp-env run tests-wordpress --env-cwd=wp-content/themes/philadelphia vendor/bin/phpunit --filter SliderTest
npm run e2e -- slider editor-blocks
```

- [ ] **Step 5: Browser verification of the editor (manual — the editor UI has no automated coverage)**

In the Site Editor / a page (wp-env, theme `philadelphia` active), insert a **Slider** block and confirm:
1. Empty state shows "Add your first slide from the block settings sidebar."
2. The block settings sidebar has a "Slides" panel with **Add slide**; adding a slide opens its panel and shows it in the canvas.
3. Per-slide fields (image picker, alt, eyebrow, heading, body, button text/URL) update the canvas preview live.
4. With ≥2 slides, the canvas arrows and dots are clickable and switch the previewed slide; the active dot shows `is-current`.
5. The image-side toggle flips the canvas layout; the panel color picker recolors the panel with readable text (matches the front end).
6. Save the page, view the front end: the carousel matches the editor preview and navigates (arrows/dots/keyboard).

Capture a screenshot of the editor canvas showing the finished slider preview.

- [ ] **Step 6: Commit**

```bash
git add tests/e2e/slider.spec.ts tests/e2e/editor-blocks.spec.ts
git commit -m "test(slider): drive slider via slides attribute in e2e + kitchen-sink"
```

---

## Self-Review Notes (for the implementer)

- The editor preview deliberately mirrors `render.php`'s DOM/classes so the shipped `style.scss` styles it (no `ServerSideRender`). The one intentional divergence: the editor renders the slide's `__button` as a `<span>` (not an `<a>`) so clicking it doesn't navigate away mid-edit.
- `panelFg` (TS) and `pediment_slider_panel_fg` (PHP) MUST stay in sync (same 0.55 threshold + Rec. 709 coefficients). They're tested independently (Jest + PHPUnit).
- `is-editor` CSS ships in the front-end bundle but never matches there (the class is editor-only) — consistent with the codebase's single-stylesheet convention.
