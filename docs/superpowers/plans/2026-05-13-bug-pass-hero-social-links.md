# Bug Pass — Hero Split Removal + Social Links Block Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Remove the `split` variant from the `starter/hero` block (the UI lies — it produces a non-split layout) and add a new `starter/social-links` block that renders `Brand::get('social_links')` as a row of inline-SVG icons in the footer.

**Architecture:** Two independent fixes shipped together. Hero fix is a deletion in three files plus tests. Social-links is a new server-rendered block following the existing pattern (`src/blocks/<name>/{block.json, index.tsx, edit.tsx, render.php, style.scss}`), auto-registered by `inc/register-blocks.php`'s glob over `build/blocks/*`. Footer template gets one new centred row.

**Tech Stack:** WordPress block API v3, `@wordpress/scripts` build pipeline (compiles `src/blocks/*/index.tsx` → `build/blocks/*/index.js`), PHPUnit via wp-env's `tests-wordpress` container, Simple Icons (CC0) for brand SVG path data.

**Spec:** [docs/superpowers/specs/2026-05-13-bug-pass-hero-social-links-design.md](../specs/2026-05-13-bug-pass-hero-social-links-design.md)

**Verified preconditions before writing this plan:**
- `inc/register-blocks.php` auto-globs `build/blocks/*/block.json` and registers every directory. No registration code needed for the new block.
- Tests live at `tests/phpunit/`, not `tests/php/`. Block render tests live under `tests/phpunit/BlockRender/`. Existing `HeroTest.php` already extends `WP_UnitTestCase` and renders with `do_blocks()`.
- Existing block convention: each block has `index.tsx` (registers via `metadata.name` + imports `style.scss`), `edit.tsx` (editor component), `block.json`, `render.php` (uses `echo ob_get_clean()`, not `return`), `style.scss`.
- `npm run build` runs `wp-scripts build --webpack-src-dir=src/blocks --output-path=build/blocks`.
- PHPUnit runs inside wp-env: `npx wp-env run tests-wordpress --env-cwd=wp-content/themes/wp-starter-theme ./vendor/bin/phpunit`.

**Deviations from spec (intentional):**
- Spec proposed `tests/php/blocks/HeroVariantTest.php` as a new test file. Reality: the test directory is `tests/phpunit/` and `BlockRender/HeroTest.php` already exists. This plan adds two test methods *to the existing* `HeroTest.php` rather than creating a new file — keeps all hero tests together. Spec's `SocialLinksTest.php` is correct in intent; actual path is `tests/phpunit/BlockRender/SocialLinksTest.php`.

---

## Task 1: Hero `split` variant removal (TDD)

**Files:**
- Modify: `src/blocks/hero/block.json`
- Modify: `src/blocks/hero/edit.tsx`
- Modify: `src/blocks/hero/style.scss`
- Modify: `tests/phpunit/BlockRender/HeroTest.php` (add two test methods)

This is a single TDD cycle. Add two failing tests against the current state, then apply the three-file edit, then confirm green. Commit once.

- [ ] **Step 1: Read the current `tests/phpunit/BlockRender/HeroTest.php`**

```bash
cat tests/phpunit/BlockRender/HeroTest.php
```

Confirm the file ends with the closing `}` of the `HeroTest` class. You'll append two new test methods inside the class.

- [ ] **Step 2: Add two failing test methods to `HeroTest.php`**

Insert these methods inside the `HeroTest` class, after the existing `test_omits_cta_when_url_is_empty`:

```php
	public function test_block_json_variant_enum_excludes_split() {
		$path = dirname( __DIR__, 3 ) . '/src/blocks/hero/block.json';
		$this->assertFileIsReadable( $path );
		$data = json_decode( file_get_contents( $path ), true );
		$this->assertIsArray( $data );
		$this->assertSame(
			array( 'default', 'centered', 'media-bg' ),
			$data['attributes']['variant']['enum'],
			'block.json variant enum should not include "split" — UI advertised a variant the renderer never produced'
		);
	}

	public function test_block_json_description_does_not_mention_split() {
		$path = dirname( __DIR__, 3 ) . '/src/blocks/hero/block.json';
		$data = json_decode( file_get_contents( $path ), true );
		$this->assertIsArray( $data );
		$this->assertStringNotContainsStringIgnoringCase( 'split', $data['description'] );
	}
```

`dirname( __DIR__, 3 )` from `tests/phpunit/BlockRender/HeroTest.php` resolves to the theme root.

- [ ] **Step 3: Run the new tests to verify they fail**

```bash
npx wp-env run tests-wordpress --env-cwd=wp-content/themes/wp-starter-theme ./vendor/bin/phpunit --filter HeroTest
```

Expected: 5 tests run, 2 failures (`test_block_json_variant_enum_excludes_split` fails because `enum` currently contains `"split"`; `test_block_json_description_does_not_mention_split` fails because the description contains `"split"`).

If they don't fail, STOP and investigate — the fix may already be in place.

- [ ] **Step 4: Edit `src/blocks/hero/block.json`**

Apply two changes:

1. In the `description` field, replace `"Variants: default, split, centered, media-bg."` with `"Variants: default, centered, media-bg."`.
2. In `attributes.variant.enum`, change `[ "default", "split", "centered", "media-bg" ]` to `[ "default", "centered", "media-bg" ]`.

After the edit, the relevant block.json fragment should read:

```json
  "description": "A page-opening hero with headline, subheadline, and primary CTA. Variants: default, centered, media-bg.",
  ...
  "attributes": {
    "variant": {
      "type": "string",
      "default": "default",
      "enum": [ "default", "centered", "media-bg" ]
    },
```

- [ ] **Step 5: Edit `src/blocks/hero/edit.tsx`**

Find the `SelectControl` `options` array and remove the `Split` entry. The remaining array should be exactly:

```tsx
options={ [
    { label: 'Default', value: 'default' },
    { label: 'Centered', value: 'centered' },
    { label: 'Media BG', value: 'media-bg' },
] }
```

- [ ] **Step 6: Edit `src/blocks/hero/style.scss` to remove dead `.is-variant-split` styles**

The current file contains a `&.is-variant-split { … }` block (verified — lines 6-19 of the current file). Remove that entire block. The selectors that should remain are `&.is-variant-centered`, `&.is-variant-media-bg`, and any non-variant rules.

After the edit, run a sanity grep:

```bash
grep -n "is-variant-split" src/blocks/hero/
```

Expected: no matches in any file.

- [ ] **Step 7: Run the test suite to verify everything passes**

```bash
npx wp-env run tests-wordpress --env-cwd=wp-content/themes/wp-starter-theme ./vendor/bin/phpunit --filter HeroTest
```

Expected: 5 tests pass.

- [ ] **Step 8: Rebuild the editor JS bundle**

```bash
npm run build
```

Expected: builds without errors. `build/blocks/hero/index.js` updates.

- [ ] **Step 9: Commit**

```bash
git add src/blocks/hero/block.json src/blocks/hero/edit.tsx src/blocks/hero/style.scss tests/phpunit/BlockRender/HeroTest.php build/blocks/hero/
git commit -m "fix(hero): remove unimplemented \"split\" variant from enum + UI

The block.json variant enum and edit.tsx SelectControl both
advertised a \"split\" variant, but render.php produced identical
markup for split and default. The .is-variant-split CSS was a
two-column grid that hadn't been wired up since the hero markup
only ever emitted a single content column.

Drops split from the enum, the SelectControl options, the
description string, and the orphaned CSS. Existing content with
variant=\"split\" saved on disk renders unchanged (it was already
indistinguishable from default); on next edit the dropdown lands
on Default. Documented as a non-issue in the spec.

Adds two HeroTest assertions to guard against re-introduction."
```

---

## Task 2: Social-links block — scaffold + rendering logic + PHPUnit tests (TDD)

**Files:**
- Create: `src/blocks/social-links/block.json`
- Create: `src/blocks/social-links/index.tsx`
- Create: `src/blocks/social-links/edit.tsx`
- Create: `src/blocks/social-links/render.php`
- Create: `tests/phpunit/BlockRender/SocialLinksTest.php`

`style.scss` and footer wiring are deferred to Task 3 — this task lands the working block with all rendering logic and full automated test coverage. Style and template wiring is purely presentation.

This task has substantial content. The TDD discipline: write the failing test, implement the minimum to make it pass, repeat. The test class accumulates assertions; the render.php accumulates branches.

- [ ] **Step 1: Create `src/blocks/social-links/block.json`**

```json
{
  "$schema": "https://schemas.wp.org/trunk/block.json",
  "apiVersion": 3,
  "name": "starter/social-links",
  "title": "Social Links",
  "category": "starter",
  "description": "Renders the social links configured in Settings → Brand Settings. Hides itself when none are configured.",
  "textdomain": "starter",
  "supports": { "html": false, "align": [ "wide" ] },
  "attributes": {},
  "editorScript": "file:./index.js",
  "style": "file:./style-index.css",
  "render": "file:./render.php"
}
```

- [ ] **Step 2: Create `src/blocks/social-links/index.tsx`**

Matches the existing block convention (see `src/blocks/contact-form/index.tsx`):

```tsx
import { registerBlockType } from '@wordpress/blocks';
import metadata from './block.json';
import Edit from './edit';
import './style.scss';

registerBlockType( metadata.name, { edit: Edit } );
```

- [ ] **Step 3: Create `src/blocks/social-links/edit.tsx`**

```tsx
import { __ } from '@wordpress/i18n';
import { useBlockProps } from '@wordpress/block-editor';
import ServerSideRender from '@wordpress/server-side-render';
import { Placeholder } from '@wordpress/components';

export default function Edit() {
	const blockProps = useBlockProps();
	return (
		<div { ...blockProps }>
			<ServerSideRender
				block="starter/social-links"
				EmptyResponsePlaceholder={ () => (
					<Placeholder
						label={ __( 'Social links', 'starter' ) }
						instructions={ __(
							'No social links configured. Add them under Settings → Brand Settings → Social.',
							'starter'
						) }
					/>
				) }
			/>
		</div>
	);
}
```

- [ ] **Step 4: Create `src/blocks/social-links/style.scss` (empty stub)**

Empty file. Real styles land in Task 3. Webpack requires the file to exist because `index.tsx` imports it.

```scss
// Styles land in Task 3.
```

- [ ] **Step 5: Create `src/blocks/social-links/render.php` — minimal empty-state stub**

```php
<?php
/**
 * Server-side render for starter/social-links.
 *
 * Reads brand-wide social links from Settings → Brand Settings and renders
 * them as a row of inline-SVG icons. Returns an empty string when none are
 * configured so the block hides itself entirely on the front end.
 *
 * @var array $attributes
 */

if ( ! class_exists( '\\Starter\\Brand' ) ) {
	return '';
}

$links = \Starter\Brand::get( 'social_links', array() );
$links = is_array( $links ) ? $links : array();

if ( empty( $links ) ) {
	return '';
}

return '';
```

This stub renders nothing in both the empty and populated paths. We'll fill in the populated path under TDD, one test case at a time.

- [ ] **Step 6: Run `npm run build` to compile the new block**

```bash
npm run build
```

Expected: builds without errors. `build/blocks/social-links/` is created with `block.json`, `index.js`, `index.asset.php`, `render.php`, `style-index.css`.

- [ ] **Step 7: Create `tests/phpunit/BlockRender/SocialLinksTest.php` with the first failing test (empty-state)**

```php
<?php

class SocialLinksTest extends WP_UnitTestCase {
	protected function setUp(): void {
		parent::setUp();
		// Reset Brand option to a clean state for each test.
		delete_option( \Starter\Brand::OPTION );
	}

	private function render(): string {
		return do_blocks( '<!-- wp:starter/social-links /-->' );
	}

	public function test_returns_empty_string_when_brand_social_links_is_empty() {
		// No setup — Brand option is empty.
		$html = $this->render();
		$this->assertSame( '', trim( $html ) );
	}
}
```

- [ ] **Step 8: Run the test to verify it passes against the stub render.php**

```bash
npx wp-env run tests-wordpress --env-cwd=wp-content/themes/wp-starter-theme ./vendor/bin/phpunit --filter SocialLinksTest
```

Expected: 1 test passes (the stub already returns empty in both paths).

If it fails, the stub `render.php` is doing something wrong. Investigate before continuing.

- [ ] **Step 9: Add the failing test for "renders one anchor per configured link"**

Append to `SocialLinksTest`:

```php
	public function test_renders_one_anchor_per_configured_link() {
		\Starter\Brand::set(
			'social_links',
			array(
				array( 'platform' => 'twitter', 'url' => 'https://twitter.com/x' ),
				array( 'platform' => 'github',  'url' => 'https://github.com/x' ),
			)
		);

		$html = $this->render();
		// Two <a> elements, one per configured link.
		$this->assertSame( 2, substr_count( $html, '<a ' ) );
	}
```

- [ ] **Step 10: Run the test — confirm it fails**

```bash
npx wp-env run tests-wordpress --env-cwd=wp-content/themes/wp-starter-theme ./vendor/bin/phpunit --filter SocialLinksTest
```

Expected: 1 pass, 1 failure (no anchors in stub output).

- [ ] **Step 11: Implement the render loop in `render.php`**

Replace the entire `render.php` content with:

```php
<?php
/**
 * Server-side render for starter/social-links.
 *
 * @var array $attributes
 */

if ( ! class_exists( '\\Starter\\Brand' ) ) {
	return '';
}

$links = \Starter\Brand::get( 'social_links', array() );
$links = is_array( $links ) ? $links : array();

if ( empty( $links ) ) {
	return '';
}

$icons  = starter_social_links_icons();
$labels = starter_social_links_labels();

$wrapper = get_block_wrapper_attributes( array( 'class' => 'starter-social-links' ) );

ob_start();
?>
<ul <?php echo $wrapper; // phpcs:ignore WordPress.Security.EscapeOutput ?> role="list">
	<?php foreach ( $links as $link ) :
		$platform = isset( $link['platform'] ) ? (string) $link['platform'] : '';
		$url      = isset( $link['url'] ) ? (string) $link['url'] : '';
		if ( '' === $platform || '' === $url ) {
			continue;
		}

		$icon  = $icons[ $platform ] ?? '';
		$label = $labels[ $platform ] ?? ucfirst( $platform );
		?>
		<li class="starter-social-links__item">
			<a
				href="<?php echo esc_url( $url ); ?>"
				aria-label="<?php echo esc_attr( $label ); ?>"
				rel="noopener noreferrer"
			>
				<?php if ( '' !== $icon ) : ?>
					<span class="starter-social-links__icon" aria-hidden="true"><?php echo $icon; // phpcs:ignore WordPress.Security.EscapeOutput ?></span>
				<?php else : ?>
					<span class="starter-social-links__label"><?php echo esc_html( $label ); ?></span>
				<?php endif; ?>
			</a>
		</li>
	<?php endforeach; ?>
</ul>
<?php
echo ob_get_clean();

/**
 * Inline SVG icon strings for known platforms.
 *
 * Path data sourced from Simple Icons (https://simpleicons.org/), CC0.
 * Each SVG is the canonical 24×24 single-path glyph with fill via currentColor
 * (so theme.json colour tokens can tint it).
 *
 * @return array<string, string>
 */
function starter_social_links_icons(): array {
	$svg = static function ( string $title, string $path ): string {
		return sprintf(
			'<svg role="img" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg" aria-hidden="true"><title>%s</title><path d="%s"/></svg>',
			esc_html( $title ),
			esc_attr( $path )
		);
	};

	return array(
		'twitter'   => $svg( 'X (Twitter)', 'M18.901 1.153h3.68l-8.04 9.19L24 22.846h-7.406l-5.8-7.584-6.638 7.584H.474l8.6-9.83L0 1.154h7.594l5.243 6.932ZM17.61 20.644h2.039L6.486 3.24H4.298Z' ),
		'x'         => $svg( 'X (Twitter)', 'M18.901 1.153h3.68l-8.04 9.19L24 22.846h-7.406l-5.8-7.584-6.638 7.584H.474l8.6-9.83L0 1.154h7.594l5.243 6.932ZM17.61 20.644h2.039L6.486 3.24H4.298Z' ),
		'github'    => $svg( 'GitHub', 'M12 .297c-6.63 0-12 5.373-12 12 0 5.303 3.438 9.8 8.205 11.387.6.113.82-.258.82-.577 0-.285-.01-1.04-.015-2.04-3.338.724-4.042-1.61-4.042-1.61C4.422 18.07 3.633 17.7 3.633 17.7c-1.087-.744.084-.729.084-.729 1.205.084 1.838 1.236 1.838 1.236 1.07 1.835 2.809 1.305 3.495.998.108-.776.417-1.305.76-1.605-2.665-.3-5.466-1.332-5.466-5.93 0-1.31.465-2.38 1.235-3.22-.135-.303-.54-1.523.105-3.176 0 0 1.005-.322 3.3 1.23.96-.267 1.98-.399 3-.405 1.02.006 2.04.138 3 .405 2.28-1.552 3.285-1.23 3.285-1.23.645 1.653.24 2.873.12 3.176.765.84 1.23 1.91 1.23 3.22 0 4.61-2.805 5.625-5.475 5.92.42.36.81 1.096.81 2.22 0 1.606-.015 2.896-.015 3.286 0 .315.21.69.825.57C20.565 22.092 24 17.592 24 12.297c0-6.627-5.373-12-12-12' ),
		'linkedin'  => $svg( 'LinkedIn', 'M20.447 20.452h-3.554v-5.569c0-1.328-.027-3.037-1.852-3.037-1.853 0-2.136 1.445-2.136 2.939v5.667H9.351V9h3.414v1.561h.046c.477-.9 1.637-1.85 3.37-1.85 3.601 0 4.267 2.37 4.267 5.455v6.286zM5.337 7.433a2.062 2.062 0 01-2.063-2.065 2.063 2.063 0 112.063 2.065zm1.782 13.019H3.555V9h3.564v11.452zM22.225 0H1.771C.792 0 0 .774 0 1.729v20.542C0 23.227.792 24 1.771 24h20.451C23.2 24 24 23.227 24 22.271V1.729C24 .774 23.2 0 22.222 0h.003z' ),
		'instagram' => $svg( 'Instagram', 'M12 0C8.74 0 8.333.015 7.053.072 5.775.132 4.905.333 4.14.63c-.789.306-1.459.717-2.126 1.384S.935 3.35.63 4.14C.333 4.905.131 5.775.072 7.053.012 8.333 0 8.74 0 12s.015 3.667.072 4.947c.06 1.277.261 2.148.558 2.913.306.788.717 1.459 1.384 2.126.667.666 1.336 1.079 2.126 1.384.766.296 1.636.499 2.913.558C8.333 23.988 8.74 24 12 24s3.667-.015 4.947-.072c1.277-.06 2.148-.262 2.913-.558.788-.306 1.459-.718 2.126-1.384.666-.667 1.079-1.335 1.384-2.126.296-.765.499-1.636.558-2.913.06-1.28.072-1.687.072-4.947s-.015-3.667-.072-4.947c-.06-1.277-.262-2.149-.558-2.913-.306-.789-.718-1.459-1.384-2.126C21.319 1.347 20.651.935 19.86.63c-.765-.297-1.636-.499-2.913-.558C15.667.012 15.26 0 12 0zm0 2.16c3.203 0 3.585.016 4.85.071 1.17.055 1.805.249 2.227.415.562.217.96.477 1.382.896.419.42.679.819.896 1.381.164.422.36 1.057.413 2.227.057 1.266.07 1.646.07 4.85s-.015 3.585-.074 4.85c-.061 1.17-.256 1.805-.421 2.227-.224.562-.479.96-.899 1.382-.419.419-.824.679-1.38.896-.42.164-1.065.36-2.235.413-1.274.057-1.649.07-4.859.07-3.211 0-3.586-.015-4.859-.074-1.171-.061-1.816-.256-2.236-.421-.569-.224-.96-.479-1.379-.899-.421-.419-.69-.824-.9-1.38-.165-.42-.359-1.065-.42-2.235-.045-1.26-.061-1.649-.061-4.844 0-3.196.016-3.586.061-4.861.061-1.17.255-1.814.42-2.234.21-.57.479-.96.9-1.381.419-.419.81-.689 1.379-.898.42-.166 1.051-.361 2.221-.421 1.275-.045 1.65-.06 4.859-.06l.045.03zm0 3.678c-3.405 0-6.162 2.76-6.162 6.162 0 3.405 2.76 6.162 6.162 6.162 3.405 0 6.162-2.76 6.162-6.162 0-3.405-2.76-6.162-6.162-6.162zM12 16c-2.21 0-4-1.79-4-4s1.79-4 4-4 4 1.79 4 4-1.79 4-4 4zm7.846-10.405c0 .795-.646 1.44-1.44 1.44-.795 0-1.44-.646-1.44-1.44 0-.794.646-1.439 1.44-1.439.793-.001 1.44.645 1.44 1.439z' ),
		'facebook'  => $svg( 'Facebook', 'M9.101 23.691v-7.98H6.627v-3.667h2.474v-1.58c0-4.085 1.848-5.978 5.858-5.978.401 0 .955.042 1.468.103a8.68 8.68 0 0 1 1.141.195v3.325a8.623 8.623 0 0 0-.653-.036 26.805 26.805 0 0 0-.733-.009c-.707 0-1.259.096-1.675.309a1.686 1.686 0 0 0-.679.622c-.258.42-.374.995-.374 1.752v1.297h3.919l-.386 2.103-.287 1.564h-3.246v8.245C19.396 23.238 24 18.179 24 12.044c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.628 3.874 10.35 9.101 11.647Z' ),
		'youtube'   => $svg( 'YouTube', 'M23.498 6.186a3.016 3.016 0 0 0-2.122-2.136C19.505 3.545 12 3.545 12 3.545s-7.505 0-9.377.505A3.017 3.017 0 0 0 .502 6.186C0 8.07 0 12 0 12s0 3.93.502 5.814a3.016 3.016 0 0 0 2.122 2.136c1.871.505 9.376.505 9.376.505s7.505 0 9.377-.505a3.015 3.015 0 0 0 2.122-2.136C24 15.93 24 12 24 12s0-3.93-.502-5.814zM9.545 15.568V8.432L15.818 12l-6.273 3.568z' ),
		'mastodon'  => $svg( 'Mastodon', 'M23.268 5.313c-.35-2.578-2.617-4.61-5.304-5.004C17.51.242 15.792 0 11.813 0h-.03c-3.98 0-4.835.242-5.288.309C3.882.692 1.496 2.518.917 5.127.64 6.412.61 7.837.661 9.143c.074 1.874.088 3.745.26 5.611.118 1.24.325 2.47.62 3.68.55 2.237 2.777 4.098 4.96 4.857 2.336.792 4.849.923 7.256.38.265-.061.527-.132.786-.213.585-.184 1.27-.39 1.774-.753a.057.057 0 0 0 .023-.043v-1.809a.052.052 0 0 0-.02-.041.053.053 0 0 0-.046-.01 20.282 20.282 0 0 1-4.709.545c-2.73 0-3.463-1.284-3.674-1.818a5.593 5.593 0 0 1-.319-1.433.053.053 0 0 1 .066-.054c1.517.363 3.072.546 4.632.546.376 0 .75 0 1.125-.01 1.57-.044 3.224-.124 4.768-.422.038-.008.077-.015.11-.024 2.435-.464 4.753-1.92 4.989-5.604.008-.145.03-1.52.03-1.67.002-.512.167-3.63-.024-5.545zm-3.748 9.195h-2.561V8.29c0-1.309-.55-1.976-1.67-1.976-1.23 0-1.846.79-1.846 2.35v3.403h-2.546V8.663c0-1.56-.617-2.35-1.848-2.35-1.112 0-1.668.668-1.67 1.977v6.218H4.822V8.102c0-1.31.337-2.35 1.011-3.12.696-.77 1.608-1.164 2.74-1.164 1.311 0 2.302.5 2.962 1.498l.638 1.06.638-1.06c.66-.999 1.65-1.498 2.96-1.498 1.13 0 2.043.395 2.74 1.164.675.77 1.012 1.81 1.012 3.12z' ),
		'rss'       => $svg( 'RSS', 'M19.199 24C19.199 13.467 10.533 4.8 0 4.8V0c13.165 0 24 10.835 24 24h-4.801zM3.291 17.415c1.814 0 3.293 1.479 3.293 3.295 0 1.813-1.485 3.29-3.301 3.29C1.47 24 0 22.526 0 20.71s1.475-3.294 3.291-3.295zM15.909 24h-4.665c0-6.169-5.075-11.245-11.244-11.245V8.09c8.727 0 15.909 7.184 15.909 15.91z' ),
	);
}

/**
 * Display labels for known platforms (used for aria-label and the text fallback).
 *
 * @return array<string, string>
 */
function starter_social_links_labels(): array {
	return array(
		'twitter'   => __( 'Twitter', 'starter' ),
		'x'         => __( 'X', 'starter' ),
		'github'    => __( 'GitHub', 'starter' ),
		'linkedin'  => __( 'LinkedIn', 'starter' ),
		'instagram' => __( 'Instagram', 'starter' ),
		'facebook'  => __( 'Facebook', 'starter' ),
		'youtube'   => __( 'YouTube', 'starter' ),
		'mastodon'  => __( 'Mastodon', 'starter' ),
		'rss'       => __( 'RSS', 'starter' ),
	);
}
```

The SVG path data above is the canonical Simple Icons CC0 path for each platform's 24×24 glyph. Treat as authoritative — do not modify.

- [ ] **Step 12: Run the test suite — confirm the second test passes**

```bash
npx wp-env run tests-wordpress --env-cwd=wp-content/themes/wp-starter-theme ./vendor/bin/phpunit --filter SocialLinksTest
```

Expected: 2 tests pass.

- [ ] **Step 13: Append the remaining six tests to `SocialLinksTest.php`**

Add inside the class, after `test_renders_one_anchor_per_configured_link`:

```php
	public function test_known_platform_renders_inline_svg_icon() {
		\Starter\Brand::set(
			'social_links',
			array( array( 'platform' => 'github', 'url' => 'https://github.com/x' ) )
		);

		$html = $this->render();
		$this->assertStringContainsString( '<span class="starter-social-links__icon" aria-hidden="true">', $html );
		$this->assertStringContainsString( '<svg', $html );
		$this->assertStringContainsString( '<title>GitHub</title>', $html );
		$this->assertStringNotContainsString( '<span class="starter-social-links__label">', $html );
	}

	public function test_unknown_platform_renders_text_label_fallback_with_ucfirst() {
		\Starter\Brand::set(
			'social_links',
			array( array( 'platform' => 'bluesky', 'url' => 'https://bsky.app/profile/x' ) )
		);

		$html = $this->render();
		$this->assertStringContainsString( '<span class="starter-social-links__label">Bluesky</span>', $html );
		$this->assertStringNotContainsString( '<svg', $html );
	}

	public function test_twitter_and_x_aliases_render_the_same_icon() {
		\Starter\Brand::set(
			'social_links',
			array(
				array( 'platform' => 'twitter', 'url' => 'https://twitter.com/x' ),
				array( 'platform' => 'x',       'url' => 'https://x.com/x' ),
			)
		);

		$html = $this->render();
		// Both entries should produce an <svg> with the X path data.
		$this->assertSame( 2, substr_count( $html, '<svg' ) );
		// Both titles read "X (Twitter)" per the Simple Icons canonical title.
		$this->assertSame( 2, substr_count( $html, '<title>X (Twitter)</title>' ) );
	}

	public function test_skips_entries_with_empty_platform_or_url() {
		\Starter\Brand::set(
			'social_links',
			array(
				array( 'platform' => 'github',   'url' => 'https://github.com/x' ),
				array( 'platform' => '',         'url' => 'https://example.com' ),  // empty platform
				array( 'platform' => 'linkedin', 'url' => '' ),                       // empty url
				array( 'platform' => 'youtube',  'url' => 'https://youtube.com/@x' ),
			)
		);

		$html = $this->render();
		$this->assertSame( 2, substr_count( $html, '<a ' ), 'only github and youtube should render — empty fields skipped' );
	}

	public function test_each_anchor_has_rel_noopener_noreferrer() {
		\Starter\Brand::set(
			'social_links',
			array( array( 'platform' => 'github', 'url' => 'https://github.com/x' ) )
		);

		$html = $this->render();
		$this->assertStringContainsString( 'rel="noopener noreferrer"', $html );
	}

	public function test_each_anchor_has_aria_label_matching_platform() {
		\Starter\Brand::set(
			'social_links',
			array(
				array( 'platform' => 'github',   'url' => 'https://github.com/x' ),
				array( 'platform' => 'linkedin', 'url' => 'https://linkedin.com/in/x' ),
			)
		);

		$html = $this->render();
		$this->assertStringContainsString( 'aria-label="GitHub"', $html );
		$this->assertStringContainsString( 'aria-label="LinkedIn"', $html );
	}
```

- [ ] **Step 14: Run the full test suite — confirm all eight tests pass**

```bash
npx wp-env run tests-wordpress --env-cwd=wp-content/themes/wp-starter-theme ./vendor/bin/phpunit --filter SocialLinksTest
```

Expected: 8 tests pass.

Also run the full theme test suite to confirm nothing else broke:

```bash
npx wp-env run tests-wordpress --env-cwd=wp-content/themes/wp-starter-theme ./vendor/bin/phpunit
```

Expected: all previously-passing tests continue to pass, plus the new SocialLinksTest and the two new HeroTest assertions from Task 1.

- [ ] **Step 15: Rebuild + commit**

```bash
npm run build
git add src/blocks/social-links/ build/blocks/social-links/ tests/phpunit/BlockRender/SocialLinksTest.php
git commit -m "feat(social-links): server-rendered block reading Brand::social_links

New starter/social-links block. Reads Brand-wide social URLs from
Settings -> Brand Settings and renders a <ul> of <a> elements with
inline-SVG icons for 9 known platforms (twitter/x alias, github,
linkedin, instagram, facebook, youtube, mastodon, rss). Unknown
platform falls back to a text label (ucfirst).

- Zero block attributes — Brand Settings is the single source of truth.
- Hides itself entirely when no links are configured.
- aria-label per anchor for screen readers; SVG marked aria-hidden.
- rel=\"noopener noreferrer\" on every external link.
- ServerSideRender preview in editor; explicit placeholder when empty.

Path data sourced from Simple Icons (CC0).

Coverage: 8 PHPUnit assertions in tests/phpunit/BlockRender/SocialLinksTest.php
(empty state, anchor count, known-icon render, unknown fallback, alias
parity, invalid-entry skipping, rel attribute, aria-label binding)."
```

---

## Task 3: Social-links styling + footer wiring

**Files:**
- Modify: `src/blocks/social-links/style.scss` (currently empty stub)
- Modify: `parts/footer.html`

No automated tests — this is purely presentation. Manual smoke covers it.

- [ ] **Step 1: Replace `src/blocks/social-links/style.scss` with the production styles**

```scss
.starter-social-links {
	display: flex;
	gap: var(--wp--preset--spacing--20);
	list-style: none;
	padding: 0;
	margin: 0;
	justify-content: center;

	&__item {
		display: inline-block;
	}

	a {
		display: inline-flex;
		align-items: center;
		justify-content: center;
		width: 2rem;
		height: 2rem;
		color: var(--wp--preset--color--text-muted);
		border-radius: 9999px;
		text-decoration: none;
		transition: color 0.15s ease;

		&:hover,
		&:focus-visible {
			color: var(--wp--preset--color--accent);
		}

		&:focus-visible {
			outline: 2px solid var(--wp--preset--color--accent);
			outline-offset: 2px;
		}
	}

	&__icon svg {
		width: 1.125rem;
		height: 1.125rem;
		display: block;
		fill: currentColor;
	}

	&__label {
		font-size: var(--wp--preset--font-size--xs);
		padding: 0 var(--wp--preset--spacing--10);
		color: var(--wp--preset--color--text-muted);
	}
}
```

- [ ] **Step 2: Modify `parts/footer.html` to add the social-links row**

Read the current file first:

```bash
cat parts/footer.html
```

The file currently has this structure (verified):

```html
<!-- wp:group {"tagName":"footer", ...} -->
<footer class="wp-block-group ...">
  <!-- wp:group {"layout":{"type":"flex","justifyContent":"space-between","flexWrap":"wrap"}} -->
  <div class="wp-block-group">
    <!-- wp:site-title ... /-->
    <!-- wp:paragraph ... -->
    <p ...>© All rights reserved.</p>
    <!-- /wp:paragraph -->
  </div>
  <!-- /wp:group -->
</footer>
<!-- /wp:group -->
```

Insert a new flex group as a *sibling* of the existing inner group, *inside* the outer `<footer>` group. The closing tags should end up in the order `</div></footer></div>` reading bottom-up after the edit. Final structure:

```html
<!-- wp:group {"tagName":"footer","backgroundColor":"surface-elevated", ... } -->
<footer class="wp-block-group ...">

  <!-- existing inner group: title + copyright row — UNCHANGED -->
  <!-- wp:group {"layout":{"type":"flex","justifyContent":"space-between","flexWrap":"wrap"}} -->
  <div class="wp-block-group">
    <!-- wp:site-title ... /-->
    <!-- wp:paragraph ... -->
    <p ...>© All rights reserved.</p>
    <!-- /wp:paragraph -->
  </div>
  <!-- /wp:group -->

  <!-- wp:group {"layout":{"type":"flex","justifyContent":"center"},"style":{"spacing":{"margin":{"top":"var:preset|spacing|30"}}}} -->
  <div class="wp-block-group" style="margin-top:var(--wp--preset--spacing--30);justify-content:center">
    <!-- wp:starter/social-links /-->
  </div>
  <!-- /wp:group -->

</footer>
<!-- /wp:group -->
```

Use the Edit tool. The `old_string` should be the existing `</div>\n<!-- /wp:group -->\n</footer>` closing pattern of the inner group; the `new_string` should be the same closing followed by the new group block. Use enough surrounding context to make the match unique.

- [ ] **Step 3: Rebuild**

```bash
npm run build
```

Expected: builds without errors. `build/blocks/social-links/style-index.css` now contains the compiled SCSS.

- [ ] **Step 4: Run the full PHPUnit suite — sanity check that the previous tests still pass**

```bash
npx wp-env run tests-wordpress --env-cwd=wp-content/themes/wp-starter-theme ./vendor/bin/phpunit
```

Expected: all green. No regressions from style or template changes.

- [ ] **Step 5: Commit**

```bash
git add src/blocks/social-links/style.scss build/blocks/social-links/ parts/footer.html
git commit -m "style(social-links): production styles + drop into footer

Token-driven layout: flex row, gap=spacing-20, icon-buttons sized
2rem with rounded-pill background, currentColor SVG fill for theme
tinting. Hover/focus shifts to accent token.

Footer.html gains a second row below the title+copyright row,
centred, with spacing-30 margin from the row above. The
social-links block stays layout-agnostic; the wrapping group
centres it."
```

---

## Task 4: Version bump + final automated verification

**Files:**
- Modify: `style.css` (the WP theme stylesheet header)

The theme is currently at 0.1.1. This work is bugfix + small additive feature. Bump to 0.1.2.

- [ ] **Step 1: Read the current `style.css` header**

```bash
head -20 style.css
```

Confirm the `Version:` line reads `Version:           0.1.1` (or similar).

- [ ] **Step 2: Bump the version**

Use the Edit tool to change `Version: 0.1.1` to `Version: 0.1.2` (preserve any surrounding whitespace exactly).

- [ ] **Step 3: Run the full test suite one last time**

```bash
npx wp-env run tests-wordpress --env-cwd=wp-content/themes/wp-starter-theme ./vendor/bin/phpunit
```

Expected: every PHPUnit test passes, including the two new HeroTest assertions and the eight SocialLinksTest cases.

- [ ] **Step 4: Run the JS linter as a sanity check**

```bash
npm run lint:js
```

Expected: no errors. (If there are pre-existing warnings unrelated to this change, note them in your report but don't fix.)

- [ ] **Step 5: Commit the version bump**

```bash
git add style.css
git commit -m "chore(release): bump theme version to 0.1.2

Bugfix + additive feature release:
- fix(hero): remove unimplemented split variant
- feat(social-links): brand-wide social links block

No breaking changes. Existing content with variant=\"split\" hero
saves render unchanged (was identical to default already)."
```

---

## Manual smoke verification (handed off after merge)

Not a commit-producing task. Run the spec's Section "Verification" checklist after the worktree branch merges back. Failures here mean returning to the relevant task and fixing, not bolting fixes on top.

The full checklist lives in [the spec](../specs/2026-05-13-bug-pass-hero-social-links-design.md#verification). Highlights:

- Hero variant dropdown shows only Default / Centered / Media BG.
- Empty Brand.social_links → footer shows no extra row, no `<ul>` in source.
- Configured Brand.social_links → centred icon row appears under title+copyright.
- Unknown platform → text-pill fallback, not broken icon.
- Keyboard tab through footer; accent-coloured focus ring on each link.
- All PHPUnit assertions still pass on the merged result.

---

## Done criteria

- All four task commits land on the working branch.
- Full PHPUnit suite green (existing + 2 new HeroTest + 8 new SocialLinksTest).
- `npm run build` produces a clean editor bundle, no console errors when activating the block in the editor.
- Manual smoke checklist passes against wp-env.
- Theme version is `0.1.2` in `style.css`.
