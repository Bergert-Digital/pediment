# Pull-Quote → Testimonial Variant Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Extend `starter/pull-quote` with an opinionated `testimonial` variant (avatar + author name + role), fork-removable via a `starter_pull_quote_variants` filter, exactly mirroring the Plan 5 hero stat-card pattern.

**Architecture:** Add a `variant` enum attribute (`default` | `testimonial`). `render.php` normalizes any unknown/filtered-out variant to `default` (render-authoritative). A new `inc/pull-quote-variants.php` exposes `starter_pull_quote_variants()` (filterable) and reflects the filtered list to the editor via `window.starterPullQuoteVariants`. The `default` branch stays byte-identical to today's markup (only the wrapper class gains ` is-variant-default`); the `testimonial` branch renders a `<figure>` with a `<blockquote>` and a `<figcaption>` avatar/name/role row sourced from the locked mockup.

**Tech Stack:** WordPress FSE dynamic block (block.json apiVersion 3 + render.php, no `save`), `@wordpress/scripts` build (TS/SCSS via ts-loader), PHPUnit (`WP_UnitTestCase`), `apply_filters`/`wp_add_inline_script` extension point.

**Scope:** `src/blocks/pull-quote/{block.json,render.php,edit.tsx,style.scss}`, `tests/phpunit/BlockRender/PullQuoteTest.php`, plus the extension-point infra `inc/pull-quote-variants.php` + a one-line `functions.php` require. NOT here: blog-index→Insights (Plan 7), child-theme.json reconciliation (separate). No other blocks/parts/theme.json/registration/`mega-*`.

**Verification constraint:** The execution worktree is NOT mounted in wp-env. Per task: env-independent gates only — `npm run build`, `php -l`, valid `block.json` JSON, SCSS brace-balance, and a static trace of every PullQuoteTest method against the shipped `render.php`. Full PHPUnit runs POST-MERGE in the main checkout (`:8888`/`:8889`). **Definition of done: post-merge PHPUnit green (all PullQuoteTest incl. testimonial + avatar + filter cases + the 2 original behavioral cases); `npm run build` clean.** Authoritative TS gate is `npm run build` (ts-loader) — do NOT rely on standalone `npx tsc`, which mis-fires without the project tsconfig.

---

## File Structure

| File | Responsibility | Action |
|---|---|---|
| `src/blocks/pull-quote/block.json` | Block metadata: `variant` enum + testimonial attrs + updated description | Modify |
| `inc/pull-quote-variants.php` | Filterable variant registry + editor reflection | Create |
| `functions.php` | Require the new registry (one line, addition-only) | Modify (1 line) |
| `src/blocks/pull-quote/render.php` | Variant normalization + testimonial branch; default branch preserved | Modify |
| `src/blocks/pull-quote/edit.tsx` | Variant select + testimonial editor UI; default editing unchanged | Modify |
| `src/blocks/pull-quote/style.scss` | Append testimonial styles (append-only) | Modify |
| `tests/phpunit/BlockRender/PullQuoteTest.php` | Keep 2 original tests verbatim; add helper + enum/description/testimonial/avatar/filter cases | Modify |

---

### Task 1: block.json — variant enum, testimonial attributes, description; PullQuoteTest guards

**Files:**
- Modify: `src/blocks/pull-quote/block.json`
- Test: `tests/phpunit/BlockRender/PullQuoteTest.php`

- [ ] **Step 1: Replace `src/blocks/pull-quote/block.json` with exactly:**

```json
{
	"$schema": "https://schemas.wp.org/trunk/block.json",
	"apiVersion": 3,
	"name": "starter/pull-quote",
	"title": "Pull Quote",
	"category": "starter",
	"description": "An emphasized quotation, optionally rendered as a testimonial with avatar, author name, and role.",
	"textdomain": "starter",
	"supports": { "html": false, "align": [ "wide" ] },
	"attributes": {
		"variant": { "type": "string", "enum": [ "default", "testimonial" ], "default": "default" },
		"quote": { "type": "string", "default": "" },
		"citation": { "type": "string", "default": "" },
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

- [ ] **Step 2: Replace the entire body of `tests/phpunit/BlockRender/PullQuoteTest.php` with exactly:**

The first two tests (`test_renders_quote_and_citation`, `test_omits_cite_when_citation_empty`) are kept VERBATIM from the current file — do not alter them. Everything from the `render()` helper onward is new.

```php
<?php

class PullQuoteTest extends WP_UnitTestCase {
	private function render( array $attrs ): string {
		$block_markup = '<!-- wp:starter/pull-quote ' . wp_json_encode( $attrs ) . ' /-->';
		return do_blocks( $block_markup );
	}

	public function test_renders_quote_and_citation() {
		$html = do_blocks( '<!-- wp:starter/pull-quote {"quote":"To be or not to be","citation":"Hamlet"} /-->' );
		$this->assertStringContainsString( '<blockquote', $html );
		$this->assertStringContainsString( 'To be or not to be', $html );
		$this->assertStringContainsString( '<cite', $html );
		$this->assertStringContainsString( 'Hamlet', $html );
	}

	public function test_omits_cite_when_citation_empty() {
		$html = do_blocks( '<!-- wp:starter/pull-quote {"quote":"Just a quote","citation":""} /-->' );
		$this->assertStringContainsString( 'Just a quote', $html );
		$this->assertStringNotContainsString( '<cite', $html );
	}

	public function test_block_json_variant_enum_is_exact_and_renderable() {
		$path = dirname( __DIR__, 3 ) . '/src/blocks/pull-quote/block.json';
		$this->assertFileIsReadable( $path );
		$data = json_decode( file_get_contents( $path ), true );
		$this->assertIsArray( $data );
		$this->assertSame(
			array( 'default', 'testimonial' ),
			$data['attributes']['variant']['enum'],
			'block.json variant enum must list exactly the variants the renderer ships'
		);
		$html = $this->render( array( 'variant' => 'testimonial', 'quote' => 'Q' ) );
		$this->assertStringContainsString( 'is-variant-testimonial', $html );
	}

	public function test_block_json_description_mentions_testimonial() {
		$path = dirname( __DIR__, 3 ) . '/src/blocks/pull-quote/block.json';
		$this->assertFileIsReadable( $path );
		$data = json_decode( file_get_contents( $path ), true );
		$this->assertStringContainsStringIgnoringCase( 'testimonial', (string) $data['description'] );
	}

	public function test_default_variant_markup_unchanged() {
		$html = $this->render(
			array( 'variant' => 'default', 'quote' => 'Plain quote', 'citation' => 'Someone' )
		);
		$this->assertStringContainsString( 'is-variant-default', $html );
		$this->assertStringContainsString( '<blockquote', $html );
		$this->assertStringContainsString( 'Plain quote', $html );
		$this->assertStringContainsString( '<cite', $html );
		$this->assertStringNotContainsString( '<figure', $html );
		$this->assertStringNotContainsString( 'starter-pull-quote__by', $html );
		$this->assertStringNotContainsString( 'starter-pull-quote__avatar', $html );
	}

	public function test_testimonial_renders_quote_name_and_role() {
		$html = $this->render(
			array(
				'variant'    => 'testimonial',
				'quote'      => 'They stayed until it worked.',
				'authorName' => 'Sarah Klein',
				'authorRole' => 'Group COO, Vantage Industries',
			)
		);
		$this->assertStringContainsString( 'is-variant-testimonial', $html );
		$this->assertStringContainsString( '<figure', $html );
		$this->assertStringContainsString( 'starter-pull-quote__by', $html );
		$this->assertStringContainsString( 'They stayed until it worked.', $html );
		$this->assertStringContainsString( 'Sarah Klein', $html );
		$this->assertStringContainsString( 'Group COO, Vantage Industries', $html );
		$this->assertStringContainsString( 'starter-pull-quote__name', $html );
		$this->assertStringContainsString( 'starter-pull-quote__role', $html );
	}

	public function test_testimonial_omits_avatar_when_id_zero() {
		$html = $this->render(
			array(
				'variant'    => 'testimonial',
				'quote'      => 'No avatar here.',
				'authorName' => 'No Pic',
				'avatarId'   => 0,
			)
		);
		$this->assertStringContainsString( 'is-variant-testimonial', $html );
		$this->assertStringNotContainsString( 'starter-pull-quote__avatar', $html );
		$this->assertStringContainsString( 'No Pic', $html );
	}

	public function test_testimonial_renders_avatar_when_id_set() {
		$attachment_id = self::factory()->attachment->create_upload_object(
			DIR_TESTDATA . '/images/canola.jpg'
		);
		$html = $this->render(
			array(
				'variant'    => 'testimonial',
				'quote'      => 'With a face.',
				'authorName' => 'Has Pic',
				'avatarId'   => $attachment_id,
			)
		);
		$this->assertStringContainsString( 'starter-pull-quote__avatar', $html );
		$this->assertStringContainsString( '<img', $html );
		wp_delete_attachment( $attachment_id, true );
	}

	public function test_starter_pull_quote_variants_filter_is_default_superset() {
		$this->assertTrue( function_exists( 'starter_pull_quote_variants' ) );
		$this->assertSame(
			array( 'default', 'testimonial' ),
			starter_pull_quote_variants()
		);
	}

	public function test_filter_removing_testimonial_falls_back_to_default() {
		$cb = static function ( $variants ) {
			return array_values( array_diff( $variants, array( 'testimonial' ) ) );
		};
		add_filter( 'starter_pull_quote_variants', $cb );
		try {
			$html = $this->render(
				array(
					'variant'    => 'testimonial',
					'quote'      => 'Filtered.',
					'authorName' => 'X',
				)
			);
			$this->assertStringContainsString( 'is-variant-default', $html );
			$this->assertStringNotContainsString( 'is-variant-testimonial', $html );
			$this->assertStringNotContainsString( 'starter-pull-quote__by', $html );
		} finally {
			remove_filter( 'starter_pull_quote_variants', $cb );
		}
	}
}
```

- [ ] **Step 3: Verify (env-independent).**

Run: `python3 -c "import json;d=json.load(open('src/blocks/pull-quote/block.json'));print(d['attributes']['variant']['enum']);print(d['description'])"`
Expected: `['default', 'testimonial']` then the description string containing the word "testimonial".

Run: `php -l tests/phpunit/BlockRender/PullQuoteTest.php`
Expected: `No syntax errors detected`

Run: `npm run build`
Expected: compiles; `build/blocks/pull-quote/block.json` regenerated with the new attributes.

TDD note: `test_block_json_variant_enum_is_exact_and_renderable` (the renderable half), the testimonial/avatar tests, and the two filter tests go green only once Tasks 2 & 3 land — that is expected. The two original behavioral tests and `test_block_json_description_mentions_testimonial` are green now. Only `block.json` and `PullQuoteTest.php` changed.

- [ ] **Step 4: Commit**

```bash
git add src/blocks/pull-quote/block.json tests/phpunit/BlockRender/PullQuoteTest.php
git commit -m "test(pull-quote): block.json testimonial variant attrs + PullQuoteTest cases"
```

---

### Task 2: inc/pull-quote-variants.php — filterable registry + editor reflection

**Files:**
- Create: `inc/pull-quote-variants.php`
- Modify: `functions.php` (one added require line, addition-only)

- [ ] **Step 1: Create `inc/pull-quote-variants.php` with exactly:**

```php
<?php
/**
 * Pull-quote variant registry — the fork-friendly extension point.
 *
 * The parent ships an opinionated set of pull-quote variants. A child theme
 * can remove one with a single line, e.g.:
 *
 *   add_filter( 'starter_pull_quote_variants', fn( $v ) => array_diff( $v, [ 'testimonial' ] ) );
 *
 * render.php normalizes any variant not in this list to "default", and the
 * block editor only offers the filtered list.
 *
 * @package Starter
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The allowed pull-quote variants (filterable).
 *
 * @return string[] Re-indexed list of variant slugs.
 */
function starter_pull_quote_variants() {
	$defaults = array( 'default', 'testimonial' );
	$variants = apply_filters( 'starter_pull_quote_variants', $defaults );
	$variants = is_array( $variants ) ? array_values( array_filter( array_map( 'strval', $variants ) ) ) : $defaults;
	if ( ! in_array( 'default', $variants, true ) ) {
		array_unshift( $variants, 'default' );
	}
	return $variants;
}

/**
 * Expose the filtered variant list to the block editor so the Pull Quote
 * inspector only offers variants the site actually ships.
 */
add_action(
	'enqueue_block_editor_assets',
	function () {
		wp_add_inline_script(
			'wp-blocks',
			'window.starterPullQuoteVariants = ' . wp_json_encode( starter_pull_quote_variants() ) . ';',
			'after'
		);
	}
);
```

- [ ] **Step 2: Add the require to `functions.php`.** It currently has, in order:

```php
require_once __DIR__ . '/inc/icons.php';
require_once __DIR__ . '/inc/block-styles.php';
require_once __DIR__ . '/inc/hero-variants.php';
require_once __DIR__ . '/inc/brand-settings.php';
```

Insert the new require immediately after the `inc/hero-variants.php` line so the block ends as:

```php
require_once __DIR__ . '/inc/icons.php';
require_once __DIR__ . '/inc/block-styles.php';
require_once __DIR__ . '/inc/hero-variants.php';
require_once __DIR__ . '/inc/pull-quote-variants.php';
require_once __DIR__ . '/inc/brand-settings.php';
```

This is addition-only — exactly one new line, no other change to `functions.php`.

- [ ] **Step 3: Verify (env-independent).**

Run: `php -l inc/pull-quote-variants.php && php -l functions.php`
Expected: `No syntax errors detected` for both.

Run: `git diff functions.php`
Expected: a single added line `require_once __DIR__ . '/inc/pull-quote-variants.php';` and zero removed lines.

Run: `npm run build`
Expected: compiles unaffected.

Static trace of `test_starter_pull_quote_variants_filter_is_default_superset`: with no filter registered, `starter_pull_quote_variants()` returns `apply_filters` of `['default','testimonial']` → `is_array` true → `array_values(array_filter(array_map('strval', …)))` = `['default','testimonial']` → `'default'` already present → returns exactly `['default','testimonial']` ⇒ assertion passes. `wp-blocks` is a core editor script handle always present in the block editor; `wp_add_inline_script(...,'after')` runs before block edit components ⇒ `window.starterPullQuoteVariants` is defined for `edit.tsx`. Only the 2 files changed.

- [ ] **Step 4: Commit**

```bash
git add inc/pull-quote-variants.php functions.php
git commit -m "feat(pull-quote): starter_pull_quote_variants fork-friendly filter + editor reflection"
```

---

### Task 3: render.php — variant normalization + testimonial branch; default branch preserved

**Files:**
- Modify: `src/blocks/pull-quote/render.php`

- [ ] **Step 1: Replace `src/blocks/pull-quote/render.php` with exactly:**

```php
<?php
/**
 * Server-side render for starter/pull-quote.
 *
 * @var array $attributes
 */

$variant  = isset( $attributes['variant'] ) ? (string) $attributes['variant'] : 'default';
$quote    = isset( $attributes['quote'] ) ? (string) $attributes['quote'] : '';
$citation = isset( $attributes['citation'] ) ? (string) $attributes['citation'] : '';

$allowed = function_exists( 'starter_pull_quote_variants' )
	? starter_pull_quote_variants()
	: array( 'default', 'testimonial' );
if ( ! in_array( $variant, $allowed, true ) ) {
	$variant = 'default';
}

if ( '' === $quote ) {
	return '';
}

$wrapper = get_block_wrapper_attributes(
	array( 'class' => 'starter-pull-quote is-variant-' . sanitize_html_class( $variant ) )
);

if ( 'testimonial' === $variant ) {
	$author_name = isset( $attributes['authorName'] ) ? (string) $attributes['authorName'] : '';
	$author_role = isset( $attributes['authorRole'] ) ? (string) $attributes['authorRole'] : '';
	$avatar_id   = isset( $attributes['avatarId'] ) ? (int) $attributes['avatarId'] : 0;

	ob_start();
	?>
	<figure <?php echo $wrapper; // phpcs:ignore WordPress.Security.EscapeOutput ?>>
		<blockquote class="starter-pull-quote__quote"><?php echo wp_kses_post( $quote ); ?></blockquote>
		<?php if ( $avatar_id || '' !== $author_name || '' !== $author_role ) : ?>
			<figcaption class="starter-pull-quote__by">
				<?php
				if ( $avatar_id ) {
					echo wp_get_attachment_image( $avatar_id, 'thumbnail', false, array( 'class' => 'starter-pull-quote__avatar' ) ); // phpcs:ignore WordPress.Security.EscapeOutput
				}
				?>
				<?php if ( '' !== $author_name || '' !== $author_role ) : ?>
					<div>
						<?php if ( '' !== $author_name ) : ?>
							<b class="starter-pull-quote__name"><?php echo wp_kses_post( $author_name ); ?></b>
						<?php endif; ?>
						<?php if ( '' !== $author_role ) : ?>
							<span class="starter-pull-quote__role"><?php echo wp_kses_post( $author_role ); ?></span>
						<?php endif; ?>
					</div>
				<?php endif; ?>
			</figcaption>
		<?php endif; ?>
	</figure>
	<?php
	echo ob_get_clean();
	return;
}

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

- [ ] **Step 2: Verify (env-independent).**

Run: `php -l src/blocks/pull-quote/render.php`
Expected: `No syntax errors detected`

Run: `npm run build`
Expected: compiles; `build/blocks/pull-quote/render.php` regenerated.

Static-trace every PullQuoteTest method against this `render.php`:
- `test_renders_quote_and_citation` / `test_omits_cite_when_citation_empty`: no `variant` → `'default'`; `'default'` in `$allowed` → default branch → `<blockquote>` + `<p class="starter-pull-quote__quote">` + `<cite>` only when citation non-empty ⇒ both pass (markup byte-identical to pre-plan apart from the additive ` is-variant-default` wrapper class, which neither test asserts against).
- `test_block_json_variant_enum_is_exact_and_renderable`: `variant=testimonial`, default filter includes `testimonial` → testimonial branch → wrapper class `starter-pull-quote is-variant-testimonial` ⇒ contains `is-variant-testimonial` ⇒ passes.
- `test_default_variant_markup_unchanged`: `variant=default` → default branch → `is-variant-default`, `<blockquote`, quote, `<cite`; no `<figure`/`__by`/`__avatar` ⇒ passes.
- `test_testimonial_renders_quote_name_and_role`: testimonial branch → `<figure>`, `__by`, quote text, `<b class="starter-pull-quote__name">` name, `<span class="starter-pull-quote__role">` role ⇒ passes.
- `test_testimonial_omits_avatar_when_id_zero`: `avatarId=0` → `$avatar_id` falsy → `wp_get_attachment_image` not called → no `starter-pull-quote__avatar`; name still rendered ⇒ passes.
- `test_testimonial_renders_avatar_when_id_set`: real attachment id → `wp_get_attachment_image($id,'thumbnail',false,['class'=>'starter-pull-quote__avatar'])` emits `<img … class="… starter-pull-quote__avatar …">` ⇒ contains `starter-pull-quote__avatar` and `<img` ⇒ passes (runs post-merge; `DIR_TESTDATA/images/canola.jpg` is a standard WP core test fixture present in wp-env phpunit).
- `test_filter_removing_testimonial_falls_back_to_default`: filter drops `testimonial` → not in `$allowed` → `$variant='default'` → default branch → `is-variant-default`, no `__by` ⇒ passes.
- `test_starter_pull_quote_variants_filter_is_default_superset`: covered by Task 2.

Only `render.php` changed.

- [ ] **Step 3: Commit**

```bash
git add src/blocks/pull-quote/render.php
git commit -m "feat(pull-quote): testimonial render branch; default markup preserved"
```

---

### Task 4: edit.tsx — variant select + testimonial editor UI

**Files:**
- Modify: `src/blocks/pull-quote/edit.tsx`

- [ ] **Step 1: Replace `src/blocks/pull-quote/edit.tsx` with exactly:**

```tsx
import { __ } from '@wordpress/i18n';
import {
	useBlockProps,
	RichText,
	InspectorControls,
	MediaUpload,
} from '@wordpress/block-editor';
import {
	PanelBody,
	SelectControl,
	TextControl,
	Button,
} from '@wordpress/components';

type Attrs = {
	variant: 'default' | 'testimonial';
	quote: string;
	citation: string;
	authorName: string;
	authorRole: string;
	avatarId: number;
};

const ALL_VARIANTS = [ 'default', 'testimonial' ] as const;
const LABELS: Record< string, string > = {
	default: 'Default',
	testimonial: 'Testimonial',
};

function allowedVariants(): string[] {
	const w = ( window as unknown as { starterPullQuoteVariants?: unknown } )
		.starterPullQuoteVariants;
	if ( Array.isArray( w ) && w.length ) {
		return w.map( String );
	}
	return [ ...ALL_VARIANTS ];
}

export default function Edit( {
	attributes,
	setAttributes,
}: {
	attributes: Attrs;
	setAttributes: ( a: Partial< Attrs > ) => void;
} ) {
	const blockProps = useBlockProps( {
		className: `starter-pull-quote is-variant-${ attributes.variant }`,
	} );
	const isTestimonial = attributes.variant === 'testimonial';
	const options = allowedVariants().map( ( v ) => ( {
		label: LABELS[ v ] ?? v,
		value: v,
	} ) );

	return (
		<>
			<InspectorControls>
				<PanelBody title={ __( 'Pull quote settings', 'starter' ) }>
					<SelectControl
						label={ __( 'Variant', 'starter' ) }
						value={ attributes.variant }
						options={ options }
						onChange={ ( v ) =>
							setAttributes( {
								variant: v as Attrs[ 'variant' ],
							} )
						}
					/>
					{ isTestimonial && (
						<>
							<TextControl
								label={ __( 'Author name', 'starter' ) }
								value={ attributes.authorName }
								onChange={ ( v ) =>
									setAttributes( { authorName: v } )
								}
							/>
							<TextControl
								label={ __( 'Author role', 'starter' ) }
								value={ attributes.authorRole }
								onChange={ ( v ) =>
									setAttributes( { authorRole: v } )
								}
							/>
							<MediaUpload
								allowedTypes={ [ 'image' ] }
								onSelect={ ( media: any ) =>
									setAttributes( { avatarId: media.id } )
								}
								render={ ( { open }: { open: () => void } ) => (
									<Button
										variant="secondary"
										onClick={ open }
									>
										{ attributes.avatarId
											? __( 'Replace avatar', 'starter' )
											: __( 'Pick avatar', 'starter' ) }
									</Button>
								) }
							/>
						</>
					) }
				</PanelBody>
			</InspectorControls>

			{ isTestimonial ? (
				<figure { ...blockProps }>
					<RichText
						tagName="blockquote"
						className="starter-pull-quote__quote"
						value={ attributes.quote }
						onChange={ ( v ) => setAttributes( { quote: v } ) }
						placeholder={ __( 'Quote…', 'starter' ) }
					/>
					<figcaption className="starter-pull-quote__by">
						<div>
							<RichText
								tagName="b"
								className="starter-pull-quote__name"
								value={ attributes.authorName }
								onChange={ ( v ) =>
									setAttributes( { authorName: v } )
								}
								placeholder={ __( 'Name…', 'starter' ) }
							/>
							<RichText
								tagName="span"
								className="starter-pull-quote__role"
								value={ attributes.authorRole }
								onChange={ ( v ) =>
									setAttributes( { authorRole: v } )
								}
								placeholder={ __( 'Role…', 'starter' ) }
							/>
						</div>
					</figcaption>
				</figure>
			) : (
				<blockquote { ...blockProps }>
					<RichText
						tagName="p"
						className="starter-pull-quote__quote"
						value={ attributes.quote }
						onChange={ ( v ) => setAttributes( { quote: v } ) }
						placeholder={ __( 'Quote…', 'starter' ) }
					/>
					<RichText
						tagName="cite"
						className="starter-pull-quote__citation"
						value={ attributes.citation }
						onChange={ ( v ) => setAttributes( { citation: v } ) }
						placeholder={ __( 'Citation (optional)…', 'starter' ) }
					/>
				</blockquote>
			) }
		</>
	);
}
```

- [ ] **Step 2: Verify (env-independent).**

Run: `npm run build`
Expected: compiles cleanly (authoritative TS gate via ts-loader; do NOT rely on standalone `npx tsc`, which mis-fires without the project tsconfig — only flag a TS issue if `npm run build` fails).

The default branch markup (`<blockquote>` → `<p>` quote + `<cite>`) is identical in shape to the pre-plan editor, so default-variant authoring is unchanged. Only `edit.tsx` changed.

- [ ] **Step 3: Commit**

```bash
git add src/blocks/pull-quote/edit.tsx
git commit -m "feat(pull-quote): editor variant select + testimonial UI"
```

---

### Task 5: style.scss — append testimonial styles (append-only)

**Files:**
- Modify: `src/blocks/pull-quote/style.scss`

- [ ] **Step 1: Append the following block to the END of `src/blocks/pull-quote/style.scss`.**

Do not modify or remove any existing rule — this is append-only (zero `-` lines in the diff). The current last rule in the file is the `.is-style-band-navy .starter-pull-quote { … }` block; add a blank line after it, then exactly:

```scss
.starter-pull-quote.is-variant-testimonial {
  .starter-pull-quote__quote {
    font-size: clamp(1.5rem, 2.6vw, 2.1rem);
    font-weight: 700;
    line-height: 1.35;
    letter-spacing: -0.02em;
    color: var(--wp--preset--color--text);
    margin: 0;
  }

  .starter-pull-quote__by {
    margin-top: 30px;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 14px;

    > div {
      text-align: left;
    }
  }

  .starter-pull-quote__avatar {
    width: 48px;
    height: 48px;
    border-radius: 50%;
    object-fit: cover;
    flex: none;
  }

  .starter-pull-quote__name {
    display: block;
    font-weight: 700;
    color: var(--wp--preset--color--text);
  }

  .starter-pull-quote__role {
    display: block;
    color: var(--wp--preset--color--text-muted);
    font-size: 0.9rem;
  }
}

.is-style-band-navy .starter-pull-quote.is-variant-testimonial {
  .starter-pull-quote__quote,
  .starter-pull-quote__name {
    color: #fff;
  }

  .starter-pull-quote__role {
    color: #9db6e6;
  }
}
```

(Rationale: values lifted from the locked mockup — `.quote .by{margin-top:30px;display:flex;align-items:center;justify-content:center;gap:14px}`, `.quote .av{width:48px;height:48px;border-radius:50%;object-fit:cover}`, `.quote .by div{text-align:left}`, `.quote .by b{font-weight:700}`, `.quote .by span{color:var(--muted);font-size:.9rem}`. The base `.starter-pull-quote` rule already supplies max-width, centered text and `padding-block`, so the testimonial `<figure>` inherits the section rhythm. Navy-band overrides mirror the existing default-variant navy treatment.)

- [ ] **Step 2: Verify (env-independent).**

Run: `npm run build`
Expected: compiles; `build/blocks/pull-quote/style-index.css` regenerated.

Run: `awk '{o+=gsub(/{/,"{"); c+=gsub(/}/,"}")} END{print o, c}' src/blocks/pull-quote/style.scss`
Expected: two equal numbers (braces balanced).

Run: `git diff src/blocks/pull-quote/style.scss`
Expected: append-only — zero `-` lines; the pre-existing `.starter-pull-quote { … }` and `.is-style-band-navy .starter-pull-quote { … }` rules are byte-unchanged. Only `style.scss` changed.

- [ ] **Step 3: Commit**

```bash
git add src/blocks/pull-quote/style.scss
git commit -m "style(pull-quote): testimonial variant styles (append-only)"
```

---

### Task 6: Final integration verification

**Files:** None modified — verification only.

- [ ] **Step 1: Build is clean and emits the block.**

Run: `npm run build`
Expected: compiles; `build/blocks/pull-quote/{block.json,index.js,style-index.css,render.php}` all present, and `build/blocks/pull-quote/block.json` contains the `variant` enum `["default","testimonial"]`.

- [ ] **Step 2: Scope diff is exactly the intended files.**

Run: `git diff <branch-base>..HEAD --name-only` (use the branch point this plan started from)
Expected — ONLY these paths:
```
src/blocks/pull-quote/block.json
src/blocks/pull-quote/render.php
src/blocks/pull-quote/edit.tsx
src/blocks/pull-quote/style.scss
tests/phpunit/BlockRender/PullQuoteTest.php
inc/pull-quote-variants.php
functions.php
```
No other blocks/parts/theme.json/registration/`mega-*`. `functions.php` shows exactly one added line.

- [ ] **Step 3: Static cross-check of types & symbols.**

- `block.json` attribute names ↔ `render.php` reads ↔ `edit.tsx` `Attrs`: `variant`, `quote`, `citation`, `authorName`, `authorRole`, `avatarId` — all six consistent across all three; `avatarId` is `integer` in block.json, `(int)` in render.php, `number` in TS.
- `starter_pull_quote_variants()` defined in `inc/pull-quote-variants.php`, required by `functions.php`, called (function_exists-guarded) by `render.php`, and asserted by two PullQuoteTest methods — name identical everywhere.
- `window.starterPullQuoteVariants` set by `inc/pull-quote-variants.php`, read by `allowedVariants()` in `edit.tsx` — name identical.
- CSS class names emitted by `render.php` (`starter-pull-quote__quote`, `__by`, `__avatar`, `__name`, `__role`, `is-variant-testimonial`, `is-variant-default`) all have matching selectors in `style.scss`.

**Post-merge (main checkout `:8888`/`:8889`, controller — NOT a worktree step):** `npm run build` → `npx wp-env run cli wp theme activate wp-starter-theme` → full `vendor/bin/phpunit`. Expect: all PullQuoteTest green — the 2 original behavioral cases, the enum/description guards, `test_default_variant_markup_unchanged`, the 3 testimonial cases (incl. `test_testimonial_renders_avatar_when_id_set` using `DIR_TESTDATA/images/canola.jpg`), and both filter cases; the rest of the suite unchanged. Playwright unaffected (no e2e changes; any unrelated mega-menu failures stay out of scope per the established workstream split).

---

## Self-Review

**1. Spec coverage.** Spec row 7 — "Testimonial (quote + avatar + role) → `starter/pull-quote` → Extend to a testimonial variant (avatar/role)": Task 1 adds the `variant` enum + `authorName`/`authorRole`/`avatarId`; Task 3 renders the avatar/name/role figure from the locked mockup markup; Task 4 gives editor controls; Task 5 ports the mockup's `.quote .by`/`.av` styling. The fork-friendly filter (Tasks 2/3) matches the Plan 5 architecture decision ("Parent = opinionated Pediment default" + one-line child opt-out) and the roadmap item to apply the variant-filter pattern to other variant-bearing blocks. Covered.

**2. Placeholder scan.** No "TBD/TODO/handle edge cases/similar to Task N". Every code step contains complete file contents; every command has an expected result.

**3. Type consistency.** `Attrs` (edit.tsx) ↔ block.json attributes ↔ render.php reads ↔ test JSON keys all use `variant`/`quote`/`citation`/`authorName`/`authorRole`/`avatarId`. `starter_pull_quote_variants` and `window.starterPullQuoteVariants` are spelled identically in the registry, functions.php require, render.php guard, edit.tsx reader, and tests. CSS BEM names emitted by render.php match the SCSS selectors and the editor `className`s. No drift.
