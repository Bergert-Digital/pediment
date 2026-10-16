# Plan 2 — Design Layer Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Give Pediment a three-layer design vocabulary (plugin defaults → client theme → site layer) of token-bound block style variations, with a validator, a revisioned site layer, a merged vocabulary view, and an export back to the theme.

**Architecture:** Every style is a WordPress block style variation. Plugin defaults are JSON files registered with `register_block_style( …, 'style_data' )` (lowest priority in core). Client themes ship `styles/blocks/*.json` partials, which core loads natively. The site layer lives in a private post type `pediment_style` and is injected into the **user** global-styles origin, so it overrides both. Core renders variation CSS per block instance at render time. `StyleValidator` enforces the token rules everywhere.

**Tech Stack:** PHP 8.1 (strict types), WordPress 6.9+ (block style variations, theme.json v3), PHPUnit via wp-env, WP-CLI, PHP `ZipArchive`.

**Spec:** `docs/superpowers/specs/2026-10-14-core-blocks-mcp-design.md` (§2 Design layer). Roadmap: `docs/superpowers/plans/2026-10-14-roadmap.md`.

## Global Constraints

- Plan 1 must be done first: it raises `Requires at least:` to `6.9` and strips `Bootstrap::register()` to three lines. This plan appends to that method.
- New PHP classes live in `plugin/src/Design/` under namespace `Pediment\Design`; every file starts with `declare(strict_types=1);` and the `ABSPATH` guard, like `plugin/src/Tokens/Injector.php`.
- Text domain: `pediment`. Version tag in `_doing_it_wrong()`: `'4.0.0'`.
- Token rule (verbatim from spec): colours, font sizes, font families, spacing (padding, margin, gap), border radius and shadows must reference tokens: `var:preset|…`, `var(--wp--preset--…)` or `var(--wp--custom--…)`. `0`, `inherit`, `currentColor`, `transparent` and percentages are allowed. No `!important`. `css` selectors must start with `&`.
- Do not touch the existing style names `band-surface`, `band-elevated`, `band-navy`, `insights-grid` (`plugin/inc/block-styles.php`); live content uses them.
- Run every command from the repo root. Tests: `npx wp-env run tests-wordpress --env-cwd=wp-content/plugins/pediment-ai ./vendor/bin/phpunit --filter <Name>`. Lint: `composer lint -d plugin`. If port 8888 is taken by a sibling workspace, start wp-env with `WP_ENV_PORT=8898 WP_ENV_TESTS_PORT=8899 npx wp-env start`.
- Commit after each task with a conventional-commit message, staging files by name.

## Verified WordPress behaviour (probed on WP 7.0, 2026-10-14)

- `register_block_style( $block, array( 'name', 'label', 'style_data' ) )` data is injected into theme data at the lowest priority; theme partials and theme.json `styles.blocks.*.variations` override it.
- Data added through the `wp_theme_json_data_user` filter under `styles.blocks.<block>.variations.<slug>` overrides plugin and theme values **property by property** (deep merge). A `css` string in a higher layer replaces the lower layer's `css` string entirely.
- Variation CSS is **not** in `wp_get_global_stylesheet()`. Core emits it per block instance during render (`wp_render_block_style_variation_support_styles`) into the inline style `block-style-variation-styles`, as `:root :where(.wp-block-group.is-style-card--1){…}`. `css` with `&` is scoped to that selector.
- A variation name must be registered in `WP_Block_Styles_Registry` before global styles are computed, or theme.json sanitising drops its data.
- `WP_Theme_JSON_Resolver::get_style_variations( 'block' )` reads `styles/**/*.json` recursively, so `styles/blocks/*.json` works.

## Review Focus

1. A site-layer write whose `css` contains `>`, `<`, `&` or quotes (child selectors, `content:"→"`) must round-trip unchanged even for a user without `unfiltered_html` — Task 3 pins it.
2. Saving a site style with a slug that already exists must update, not duplicate, and must create a new revision — Task 3 pins it.
3. A site style that overrides a plugin default must win on render, while the plugin default's untouched properties survive — Task 4 pins it.
4. A theme partial with literal values must be reported with its file's slug, never silently dropped — Task 5 pins it.
5. Export when there are no active site styles must succeed with zero files, not fail — Task 6 pins it.

---

### Task 1: StyleValidator

**Files:**
- Create: `plugin/src/Design/StyleValidator.php`
- Test: `plugin/tests/phpunit/Design/StyleValidatorTest.php`

**Interfaces:**
- Produces: `Pediment\Design\StyleValidator::validate( array $spec ): array` — returns `list<string>` of error messages; empty list means valid. A *spec* is `array{ slug: string, label: string, blockTypes: list<string>, styles: array }`, where `styles` is a theme.json style node that may carry a `css` string.

- [ ] **Step 1: Write the failing test**

```php
<?php

use Pediment\Design\StyleValidator;

class StyleValidatorTest extends WP_UnitTestCase {

	private function spec( array $styles, array $overrides = array() ): array {
		return array_merge(
			array(
				'slug'       => 'card',
				'label'      => 'Card',
				'blockTypes' => array( 'core/group' ),
				'styles'     => $styles,
			),
			$overrides
		);
	}

	private function errors( array $spec ): array {
		return ( new StyleValidator() )->validate( $spec );
	}

	public function test_token_values_are_valid(): void {
		$this->assertSame(
			array(),
			$this->errors(
				$this->spec(
					array(
						'color'      => array( 'background' => 'var:preset|color|surface', 'text' => 'currentColor' ),
						'spacing'    => array( 'padding' => array( 'top' => 'var:preset|spacing|50', 'left' => '0' ) ),
						'border'     => array( 'radius' => 'var(--wp--custom--radius--md)', 'width' => '1px', 'style' => 'solid', 'color' => 'var:preset|color|border' ),
						'shadow'     => 'var:preset|shadow|subtle',
						'typography' => array( 'fontSize' => 'var:preset|font-size|lg', 'letterSpacing' => '0.14em' ),
						'dimensions' => array( 'minHeight' => '50%' ),
					)
				)
			)
		);
	}

	public function test_ref_objects_are_valid(): void {
		$this->assertSame( array(), $this->errors( $this->spec( array( 'color' => array( 'text' => array( 'ref' => 'styles.color.text' ) ) ) ) ) );
	}

	public function test_literal_padding_is_rejected_with_its_path(): void {
		$errors = $this->errors( $this->spec( array( 'spacing' => array( 'padding' => array( 'top' => '78px' ) ) ) ) );
		$this->assertCount( 1, $errors );
		$this->assertStringContainsString( 'styles.spacing.padding.top', $errors[0] );
		$this->assertStringContainsString( '78px', $errors[0] );
	}

	public function test_literal_colour_radius_shadow_and_font_size_are_rejected(): void {
		$errors = $this->errors(
			$this->spec(
				array(
					'color'      => array( 'background' => '#ffffff' ),
					'border'     => array( 'radius' => '22px' ),
					'shadow'     => '0 2px 4px rgba(0,0,0,.2)',
					'typography' => array( 'fontSize' => '1.2rem' ),
				)
			)
		);
		$this->assertCount( 4, $errors );
	}

	public function test_nested_element_and_pseudo_state_values_are_checked(): void {
		$errors = $this->errors(
			$this->spec(
				array(
					'elements' => array(
						'link' => array(
							'color'  => array( 'text' => '#a9621f' ),
							':hover' => array( 'color' => array( 'text' => 'red' ) ),
						),
					),
				)
			)
		);
		$this->assertCount( 2, $errors );
		$this->assertStringContainsString( 'styles.elements.link.color.text', $errors[0] );
		$this->assertStringContainsString( 'styles.elements.link.:hover.color.text', $errors[1] );
	}

	public function test_css_rules(): void {
		$this->assertSame( array(), $this->errors( $this->spec( array( 'css' => '& .x::before{content:"";width:26px;background:var(--wp--preset--color--accent)} @media (min-width:600px){&:hover{transform:translateY(-4px)}} &:is(.a, .b){opacity:.9}' ) ) ) );

		$important = $this->errors( $this->spec( array( 'css' => '&{color:var(--wp--preset--color--accent) !important}' ) ) );
		$this->assertStringContainsString( '!important', $important[0] );

		$colour = $this->errors( $this->spec( array( 'css' => '&:hover{background:#fff}' ) ) );
		$this->assertStringContainsString( 'colour', $colour[0] );

		$rgb = $this->errors( $this->spec( array( 'css' => '&{box-shadow:0 1px 2px rgba(0,0,0,.1)}' ) ) );
		$this->assertCount( 1, $rgb );

		$scope = $this->errors( $this->spec( array( 'css' => '.site-header{display:none}' ) ) );
		$this->assertStringContainsString( 'must start with &', $scope[0] );
	}

	public function test_spec_shape(): void {
		$this->assertNotEmpty( $this->errors( $this->spec( array(), array( 'slug' => 'Card' ) ) ) );
		$this->assertNotEmpty( $this->errors( $this->spec( array(), array( 'label' => '' ) ) ) );
		$this->assertNotEmpty( $this->errors( $this->spec( array(), array( 'blockTypes' => array() ) ) ) );
		$unknown = $this->errors( $this->spec( array(), array( 'blockTypes' => array( 'acme/nope' ) ) ) );
		$this->assertStringContainsString( 'not registered', $unknown[0] );
		$this->assertNotEmpty( $this->errors( array( 'slug' => 'card', 'label' => 'Card', 'blockTypes' => array( 'core/group' ) ) ) );
	}
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `npx wp-env run tests-wordpress --env-cwd=wp-content/plugins/pediment-ai ./vendor/bin/phpunit --filter StyleValidatorTest`
Expected: FAIL — `Class "Pediment\Design\StyleValidator" not found`.

- [ ] **Step 3: Write the implementation**

```php
<?php
/**
 * Validates design-layer style specs against Pediment's token rules.
 *
 * @package Pediment
 */

declare(strict_types=1);

namespace Pediment\Design;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Checks one style spec: array{slug, label, blockTypes, styles}. `styles` is a
 * theme.json style node and may carry a `css` string.
 */
final class StyleValidator {

	private const SLUG_PATTERN  = '/^[a-z][a-z0-9-]{0,39}$/';
	private const BLOCK_PATTERN = '/^[a-z0-9-]+\/[a-z0-9-]+$/';

	/**
	 * Style-node paths whose values must be tokens.
	 */
	private const TOKEN_PATHS = array(
		'color.text',
		'color.background',
		'color.gradient',
		'typography.fontSize',
		'typography.fontFamily',
		'spacing.padding',
		'spacing.margin',
		'spacing.blockGap',
		'border.radius',
		'border.color',
		'border.top.color',
		'border.right.color',
		'border.bottom.color',
		'border.left.color',
		'shadow',
	);

	private const KEYWORDS = array( '0', 'none', 'inherit', 'initial', 'unset', 'transparent', 'currentcolor' );

	/**
	 * Validates a spec.
	 *
	 * @param array $spec Style spec.
	 * @return list<string> Error messages; empty when valid.
	 */
	public function validate( array $spec ): array {
		$errors = array();

		$slug = $spec['slug'] ?? null;
		if ( ! is_string( $slug ) || 1 !== preg_match( self::SLUG_PATTERN, $slug ) ) {
			$errors[] = 'slug: must be lowercase letters, digits and dashes, starting with a letter (max 40)';
		}

		$label = $spec['label'] ?? null;
		if ( ! is_string( $label ) || '' === trim( $label ) ) {
			$errors[] = 'label: must be a non-empty string';
		}

		$types = $spec['blockTypes'] ?? null;
		if ( ! is_array( $types ) || array() === $types ) {
			$errors[] = 'blockTypes: must list at least one block type';
		} else {
			foreach ( $types as $type ) {
				if ( ! is_string( $type ) || 1 !== preg_match( self::BLOCK_PATTERN, $type ) ) {
					$errors[] = 'blockTypes: invalid block type ' . wp_json_encode( $type );
				} elseif ( ! \WP_Block_Type_Registry::get_instance()->is_registered( $type ) ) {
					$errors[] = "blockTypes: block type {$type} is not registered";
				}
			}
		}

		$styles = $spec['styles'] ?? null;
		if ( ! is_array( $styles ) ) {
			$errors[] = 'styles: must be an object';
			return $errors;
		}

		return array_merge( $errors, $this->validate_node( $styles, 'styles' ) );
	}

	/**
	 * Validates one style node and its nested elements, blocks and pseudo states.
	 *
	 * @param array  $node Style node.
	 * @param string $path Dotted path for messages.
	 * @return list<string>
	 */
	private function validate_node( array $node, string $path ): array {
		$errors = array();

		foreach ( self::TOKEN_PATHS as $token_path ) {
			$value = _wp_array_get( $node, explode( '.', $token_path ), null );
			if ( null === $value ) {
				continue;
			}
			foreach ( $this->leaves( $value, "{$path}.{$token_path}" ) as $leaf_path => $leaf ) {
				if ( ! $this->is_token_value( $leaf ) ) {
					$errors[] = "{$leaf_path}: literal value " . wp_json_encode( $leaf ) . ' — use a preset or custom token';
				}
			}
		}

		if ( array_key_exists( 'css', $node ) ) {
			$errors = array_merge( $errors, $this->validate_css( $node['css'], "{$path}.css" ) );
		}

		foreach ( array( 'elements', 'blocks' ) as $group ) {
			$children = $node[ $group ] ?? array();
			if ( ! is_array( $children ) ) {
				continue;
			}
			foreach ( $children as $name => $child ) {
				if ( is_array( $child ) ) {
					$errors = array_merge( $errors, $this->validate_node( $child, "{$path}.{$group}.{$name}" ) );
				}
			}
		}

		foreach ( $node as $key => $child ) {
			if ( is_string( $key ) && str_starts_with( $key, ':' ) && is_array( $child ) ) {
				$errors = array_merge( $errors, $this->validate_node( $child, "{$path}.{$key}" ) );
			}
		}

		return $errors;
	}

	/**
	 * Flattens a value into path => scalar leaves. `{ref: …}` objects count as tokens.
	 *
	 * @param mixed  $value Value.
	 * @param string $path  Path.
	 * @return array<string, mixed>
	 */
	private function leaves( $value, string $path ): array {
		if ( ! is_array( $value ) ) {
			return array( $path => $value );
		}
		if ( array_key_exists( 'ref', $value ) ) {
			return array();
		}
		$out = array();
		foreach ( $value as $key => $child ) {
			$out += $this->leaves( $child, "{$path}.{$key}" );
		}
		return $out;
	}

	/**
	 * Whether a scalar is an allowed token value.
	 *
	 * @param mixed $value Value.
	 */
	private function is_token_value( $value ): bool {
		if ( is_int( $value ) || is_float( $value ) ) {
			return 0.0 === (float) $value;
		}
		if ( ! is_string( $value ) ) {
			return false;
		}
		$v = trim( $value );
		if ( in_array( strtolower( $v ), self::KEYWORDS, true ) ) {
			return true;
		}
		if ( 1 === preg_match( '/^-?\d+(\.\d+)?%$/', $v ) ) {
			return true;
		}
		if ( 1 === preg_match( '/^var:preset\|[a-z-]+\|[a-z0-9-]+$/', $v ) ) {
			return true;
		}
		return 1 === preg_match( '/^var\(--wp--(preset|custom)--[a-z0-9-]+(--[a-z0-9-]+)*\)$/', $v );
	}

	/**
	 * Validates a `css` string.
	 *
	 * @param mixed  $css  CSS.
	 * @param string $path Path.
	 * @return list<string>
	 */
	private function validate_css( $css, string $path ): array {
		if ( ! is_string( $css ) ) {
			return array( "{$path}: must be a string" );
		}
		$errors = array();
		$clean  = (string) preg_replace( '#/\*.*?\*/#s', '', $css );

		if ( false !== stripos( $clean, '!important' ) ) {
			$errors[] = "{$path}: !important is not allowed";
		}

		$without_vars = (string) preg_replace( '/var\([^()]*\)/', '', $clean );
		if ( 1 === preg_match( '/#[0-9a-f]{3,8}\b|\b(?:rgba?|hsla?|hwb|lab|lch|oklab|oklch)\s*\(/i', $without_vars ) ) {
			$errors[] = "{$path}: literal colour — use var(--wp--preset--color--…)";
		}

		foreach ( $this->selectors( $clean ) as $selector ) {
			if ( ! str_starts_with( $selector, '&' ) ) {
				$errors[] = "{$path}: selector " . wp_json_encode( $selector ) . ' must start with &';
			}
		}

		return $errors;
	}

	/**
	 * Extracts rule selectors (not at-rule preludes) from CSS, at any nesting depth.
	 *
	 * @param string $css CSS without comments.
	 * @return list<string>
	 */
	private function selectors( string $css ): array {
		$out    = array();
		$buffer = '';
		$length = strlen( $css );
		for ( $i = 0; $i < $length; $i++ ) {
			$ch = $css[ $i ];
			if ( '{' === $ch ) {
				$prelude = trim( $buffer );
				$buffer  = '';
				if ( '' !== $prelude && '@' !== $prelude[0] ) {
					foreach ( $this->split_top_level_commas( $prelude ) as $selector ) {
						$out[] = $selector;
					}
				}
			} elseif ( '}' === $ch || ';' === $ch ) {
				$buffer = '';
			} else {
				$buffer .= $ch;
			}
		}
		return $out;
	}

	/**
	 * Splits a selector list on commas outside parentheses.
	 *
	 * @param string $list Selector list.
	 * @return list<string>
	 */
	private function split_top_level_commas( string $list ): array {
		$parts   = array();
		$depth   = 0;
		$current = '';
		$length  = strlen( $list );
		for ( $i = 0; $i < $length; $i++ ) {
			$ch = $list[ $i ];
			if ( '(' === $ch ) {
				++$depth;
			} elseif ( ')' === $ch ) {
				$depth = max( 0, $depth - 1 );
			}
			if ( ',' === $ch && 0 === $depth ) {
				$parts[] = trim( $current );
				$current = '';
				continue;
			}
			$current .= $ch;
		}
		$parts[] = trim( $current );
		return array_values( array_filter( $parts, static fn( string $p ): bool => '' !== $p ) );
	}
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `npx wp-env run tests-wordpress --env-cwd=wp-content/plugins/pediment-ai ./vendor/bin/phpunit --filter StyleValidatorTest`
Expected: PASS (7 tests).

- [ ] **Step 5: Lint and commit**

```bash
composer lint -d plugin
git add plugin/src/Design/StyleValidator.php plugin/tests/phpunit/Design/StyleValidatorTest.php
git commit -m "feat(design): add token-rule style validator"
```

---

### Task 2: Plugin default styles and radius tokens

**Files:**
- Create: `plugin/src/Design/DefaultStyles.php`
- Create: `plugin/styles/band.json`, `band-alt.json`, `card.json`, `eyebrow.json`, `lead.json`, `pill.json`, `pill-ghost.json`
- Modify: `plugin/tokens/theme.json` (`settings.custom`: add `radius`)
- Modify: `plugin/src/Bootstrap.php` (`register()`: add one line)
- Test: `plugin/tests/phpunit/Design/DefaultStylesTest.php`

**Interfaces:**
- Consumes: `StyleValidator::validate()` (Task 1).
- Produces: `Pediment\Design\DefaultStyles::specs(): array` (`list<spec>`, one per `plugin/styles/*.json`), `DefaultStyles::register(): void` (hooks `registerAll` on `init` priority 20), `DefaultStyles::registerAll(): void`. Token CSS variables `--wp--custom--radius--sm|md|lg|pill`.

- [ ] **Step 1: Write the failing test**

```php
<?php

use Pediment\Design\DefaultStyles;
use Pediment\Design\StyleValidator;

class DefaultStylesTest extends WP_UnitTestCase {

	private function render_variation_css( string $markup ): string {
		wp_deregister_style( 'block-style-variation-styles' );
		wp_clean_theme_json_cache();
		do_blocks( $markup );
		$after = wp_styles()->get_data( 'block-style-variation-styles', 'after' );
		return is_array( $after ) ? implode( "\n", $after ) : '';
	}

	public function test_every_default_style_passes_the_validator(): void {
		$specs = DefaultStyles::specs();
		$this->assertCount( 7, $specs );
		foreach ( $specs as $spec ) {
			$this->assertSame( array(), ( new StyleValidator() )->validate( $spec ), $spec['slug'] );
		}
	}

	public function test_defaults_are_registered_with_style_data(): void {
		DefaultStyles::registerAll();
		$styles = WP_Block_Styles_Registry::get_instance()->get_registered_styles_for_block( 'core/group' );
		$this->assertArrayHasKey( 'card', $styles );
		$this->assertNotEmpty( $styles['card']['style_data'] );
	}

	public function test_card_renders_token_css(): void {
		DefaultStyles::registerAll();
		$css = $this->render_variation_css( '<!-- wp:group {"className":"is-style-card"} --><div class="wp-block-group is-style-card"><p>x</p></div><!-- /wp:group -->' );
		$this->assertStringContainsString( 'is-style-card--', $css );
		$this->assertStringContainsString( 'border-radius: var(--wp--custom--radius--md)', $css );
	}

	public function test_radius_tokens_exist(): void {
		wp_clean_theme_json_cache();
		$this->assertSame( '12px', wp_get_global_settings( array( 'custom', 'radius', 'md' ) ) );
		$this->assertSame( '999px', wp_get_global_settings( array( 'custom', 'radius', 'pill' ) ) );
	}
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `npx wp-env run tests-wordpress --env-cwd=wp-content/plugins/pediment-ai ./vendor/bin/phpunit --filter DefaultStylesTest`
Expected: FAIL — `Class "Pediment\Design\DefaultStyles" not found`.

- [ ] **Step 3: Add the radius tokens**

In `plugin/tokens/theme.json`, replace the `settings.custom` object with (the `color` entries are the existing ones, unchanged):

```json
"custom": {
	"color": {
		"text-on-dark": "#9DB6E6",
		"text-on-dark-soft": "#C9D6EC",
		"text-on-accent-muted": "#B9C8E6"
	},
	"radius": { "sm": "6px", "md": "12px", "lg": "20px", "pill": "999px" }
}
```

- [ ] **Step 4: Add the seven style files**

`plugin/styles/band.json`:
```json
{
	"$schema": "https://schemas.wp.org/trunk/theme.json",
	"version": 3,
	"title": "Band",
	"slug": "band",
	"blockTypes": [ "core/group" ],
	"styles": {
		"color": { "background": "var:preset|color|surface" },
		"spacing": { "padding": { "top": "var:preset|spacing|70", "bottom": "var:preset|spacing|70" } }
	}
}
```

`plugin/styles/band-alt.json`:
```json
{
	"$schema": "https://schemas.wp.org/trunk/theme.json",
	"version": 3,
	"title": "Band (alternate)",
	"slug": "band-alt",
	"blockTypes": [ "core/group" ],
	"styles": {
		"color": { "background": "var:preset|color|surface-sunken" },
		"spacing": { "padding": { "top": "var:preset|spacing|70", "bottom": "var:preset|spacing|70" } }
	}
}
```

`plugin/styles/card.json`:
```json
{
	"$schema": "https://schemas.wp.org/trunk/theme.json",
	"version": 3,
	"title": "Card",
	"slug": "card",
	"blockTypes": [ "core/group" ],
	"styles": {
		"color": { "background": "var:preset|color|surface-elevated" },
		"border": { "radius": "var(--wp--custom--radius--md)", "width": "1px", "style": "solid", "color": "var:preset|color|border" },
		"shadow": "var:preset|shadow|subtle",
		"spacing": { "padding": { "top": "var:preset|spacing|50", "right": "var:preset|spacing|50", "bottom": "var:preset|spacing|50", "left": "var:preset|spacing|50" } }
	}
}
```

`plugin/styles/eyebrow.json`:
```json
{
	"$schema": "https://schemas.wp.org/trunk/theme.json",
	"version": 3,
	"title": "Eyebrow",
	"slug": "eyebrow",
	"blockTypes": [ "core/paragraph" ],
	"styles": {
		"color": { "text": "var:preset|color|primary" },
		"typography": { "fontSize": "var:preset|font-size|xs", "fontWeight": "700", "textTransform": "uppercase", "letterSpacing": "0.14em" },
		"css": "&::before{content:\"\";display:inline-block;width:1.5em;height:2px;margin-inline-end:.6em;vertical-align:middle;background:currentColor}"
	}
}
```

`plugin/styles/lead.json`:
```json
{
	"$schema": "https://schemas.wp.org/trunk/theme.json",
	"version": 3,
	"title": "Lead",
	"slug": "lead",
	"blockTypes": [ "core/paragraph" ],
	"styles": {
		"color": { "text": "var:preset|color|foreground-muted" },
		"typography": { "fontSize": "var:preset|font-size|lg" }
	}
}
```

`plugin/styles/pill.json`:
```json
{
	"$schema": "https://schemas.wp.org/trunk/theme.json",
	"version": 3,
	"title": "Pill",
	"slug": "pill",
	"blockTypes": [ "core/button" ],
	"styles": {
		"color": { "background": "var:preset|color|accent", "text": "var:preset|color|foreground" },
		"border": { "radius": "var(--wp--custom--radius--pill)" }
	}
}
```

`plugin/styles/pill-ghost.json`:
```json
{
	"$schema": "https://schemas.wp.org/trunk/theme.json",
	"version": 3,
	"title": "Pill (outline)",
	"slug": "pill-ghost",
	"blockTypes": [ "core/button" ],
	"styles": {
		"color": { "background": "transparent", "text": "currentColor" },
		"border": { "radius": "var(--wp--custom--radius--pill)", "width": "1px", "style": "solid", "color": "currentColor" }
	}
}
```

- [ ] **Step 5: Write `DefaultStyles`**

```php
<?php
/**
 * Registers the plugin's default design-layer styles from plugin/styles/*.json.
 *
 * @package Pediment
 */

declare(strict_types=1);

namespace Pediment\Design;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Lowest design layer: token-only block style variations shipped by the plugin.
 */
final class DefaultStyles {

	/**
	 * Hooks registration after core blocks exist.
	 */
	public static function register(): void {
		add_action( 'init', array( self::class, 'registerAll' ), 20 );
	}

	/**
	 * Reads every plugin style file as a spec.
	 *
	 * @return list<array{slug:string,label:string,blockTypes:list<string>,styles:array}>
	 */
	public static function specs(): array {
		$files = glob( PEDIMENT_AI_PLUGIN_DIR . '/styles/*.json' );
		$specs = array();
		foreach ( false === $files ? array() : $files as $file ) {
			$data = json_decode( (string) file_get_contents( $file ), true );
			if ( ! is_array( $data ) ) {
				continue;
			}
			$specs[] = array(
				'slug'       => (string) ( $data['slug'] ?? basename( $file, '.json' ) ),
				'label'      => (string) ( $data['title'] ?? '' ),
				'blockTypes' => array_values( (array) ( $data['blockTypes'] ?? array() ) ),
				'styles'     => (array) ( $data['styles'] ?? array() ),
			);
		}
		return $specs;
	}

	/**
	 * Registers every valid spec; skips and reports invalid ones.
	 */
	public static function registerAll(): void {
		$validator = new StyleValidator();
		foreach ( self::specs() as $spec ) {
			$errors = $validator->validate( $spec );
			if ( array() !== $errors ) {
				_doing_it_wrong( __METHOD__, esc_html( "Pediment style {$spec['slug']} skipped: " . implode( '; ', $errors ) ), '4.0.0' );
				continue;
			}
			foreach ( $spec['blockTypes'] as $block_type ) {
				register_block_style(
					$block_type,
					array(
						'name'       => $spec['slug'],
						'label'      => $spec['label'],
						'style_data' => $spec['styles'],
					)
				);
			}
		}
	}
}
```

- [ ] **Step 6: Wire it**

In `plugin/src/Bootstrap.php`, inside `register()`, after `\Pediment\Tokens\Injector::register();` add:

```php
		\Pediment\Design\DefaultStyles::register();
```

- [ ] **Step 7: Run tests to verify they pass**

Run: `npx wp-env run tests-wordpress --env-cwd=wp-content/plugins/pediment-ai ./vendor/bin/phpunit --filter 'DefaultStylesTest|BlockStylesTest|InjectorTest'`
Expected: PASS. (`BlockStylesTest` and `InjectorTest` guard the existing styles and the token merge.)

- [ ] **Step 8: Lint and commit**

```bash
composer lint -d plugin
git add plugin/src/Design/DefaultStyles.php plugin/styles plugin/tokens/theme.json plugin/src/Bootstrap.php plugin/tests/phpunit/Design/DefaultStylesTest.php
git commit -m "feat(design): ship token-only default block styles"
```

---

### Task 3: Site-layer store

**Files:**
- Create: `plugin/src/Design/SiteStyles.php`
- Modify: `plugin/src/Bootstrap.php` (`register()`: one line)
- Test: `plugin/tests/phpunit/Design/SiteStylesTest.php`

**Interfaces:**
- Consumes: `StyleValidator::validate()` (Task 1).
- Produces (class `Pediment\Design\SiteStyles`):
  - constants `POST_TYPE = 'pediment_style'`, `STATE_META = '_pediment_style_state'`, `STATE_ACTIVE = 'active'`, `STATE_PROMOTED = 'promoted'`
  - `static registerPostType(): void` (hooked on `init`)
  - `__construct( StyleValidator $validator )`
  - `save( array $spec ): array|\WP_Error` → `array{ id: int, revision: int }`; error code `pediment_style_invalid` with `data['errors']` = `list<string>`. Upserts by slug; sets state `active`.
  - `get( string $slug ): ?array` → spec plus `state` and `id`
  - `all( ?string $state = null ): array` → `list<spec + state + id>`, ordered by slug
  - `setState( string $slug, string $state ): bool`
  - `delete( string $slug ): bool`

- [ ] **Step 1: Write the failing test**

```php
<?php

use Pediment\Design\SiteStyles;
use Pediment\Design\StyleValidator;

class SiteStylesTest extends WP_UnitTestCase {

	private SiteStyles $store;

	public function set_up(): void {
		parent::set_up();
		$this->store = new SiteStyles( new StyleValidator() );
	}

	private function spec( array $styles = array(), string $slug = 'promo' ): array {
		return array(
			'slug'       => $slug,
			'label'      => 'Promo',
			'blockTypes' => array( 'core/group' ),
			'styles'     => $styles + array( 'color' => array( 'background' => 'var:preset|color|accent' ) ),
		);
	}

	public function test_post_type_is_private_and_revisioned(): void {
		$type = get_post_type_object( SiteStyles::POST_TYPE );
		$this->assertNotNull( $type );
		$this->assertFalse( $type->public );
		$this->assertFalse( $type->show_in_rest );
		$this->assertTrue( post_type_supports( SiteStyles::POST_TYPE, 'revisions' ) );
	}

	public function test_save_and_get_round_trip(): void {
		$result = $this->store->save( $this->spec() );
		$this->assertIsArray( $result );
		$stored = $this->store->get( 'promo' );
		$this->assertSame( 'Promo', $stored['label'] );
		$this->assertSame( array( 'core/group' ), $stored['blockTypes'] );
		$this->assertSame( SiteStyles::STATE_ACTIVE, $stored['state'] );
	}

	public function test_css_with_special_characters_survives_without_unfiltered_html(): void {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'editor' ) ) );
		add_filter( 'map_meta_cap', static fn( $caps, $cap ) => 'unfiltered_html' === $cap ? array( 'do_not_allow' ) : $caps, 10, 2 );
		kses_init();
		$this->assertFalse( current_user_can( 'unfiltered_html' ) );
		$css = '& > .x::after{content:"→ <b> & \'q\'";width:26px}';
		$this->store->save( $this->spec( array( 'css' => $css ) ) );
		$this->assertSame( $css, $this->store->get( 'promo' )['styles']['css'] );
	}

	public function test_saving_the_same_slug_updates_and_adds_a_revision(): void {
		$first  = $this->store->save( $this->spec() );
		$second = $this->store->save( $this->spec( array( 'shadow' => 'var:preset|shadow|subtle' ) ) );
		$this->assertSame( $first['id'], $second['id'] );
		$this->assertCount( 1, $this->store->all() );
		$this->assertNotSame( $first['revision'], $second['revision'] );
		$this->assertGreaterThan( 0, $second['revision'] );
	}

	public function test_invalid_spec_is_rejected_with_errors(): void {
		$result = $this->store->save( $this->spec( array( 'border' => array( 'radius' => '22px' ) ) ) );
		$this->assertWPError( $result );
		$this->assertSame( 'pediment_style_invalid', $result->get_error_code() );
		$this->assertNotEmpty( $result->get_error_data()['errors'] );
		$this->assertNull( $this->store->get( 'promo' ) );
	}

	public function test_state_filter_and_delete(): void {
		$this->store->save( $this->spec() );
		$this->store->save( $this->spec( array(), 'other' ) );
		$this->assertTrue( $this->store->setState( 'other', SiteStyles::STATE_PROMOTED ) );
		$this->assertSame( array( 'promo' ), array_column( $this->store->all( SiteStyles::STATE_ACTIVE ), 'slug' ) );
		$this->assertTrue( $this->store->delete( 'promo' ) );
		$this->assertNull( $this->store->get( 'promo' ) );
		$this->assertFalse( $this->store->delete( 'missing' ) );
	}
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `npx wp-env run tests-wordpress --env-cwd=wp-content/plugins/pediment-ai ./vendor/bin/phpunit --filter SiteStylesTest`
Expected: FAIL — `Class "Pediment\Design\SiteStyles" not found`.

- [ ] **Step 3: Write `SiteStyles`**

```php
<?php
/**
 * The site layer of the design vocabulary: revisioned, validated styles stored
 * as private posts.
 *
 * @package Pediment
 */

declare(strict_types=1);

namespace Pediment\Design;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Stores one style per `pediment_style` post. post_name = slug, post_title =
 * label, post_content = JSON spec encoded with JSON_HEX_* flags so kses never
 * sees a tag, entity or quote to rewrite.
 */
final class SiteStyles {

	public const POST_TYPE      = 'pediment_style';
	public const STATE_META     = '_pediment_style_state';
	public const STATE_ACTIVE   = 'active';
	public const STATE_PROMOTED = 'promoted';

	private const JSON_FLAGS = JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE;

	/**
	 * Constructor.
	 *
	 * @param StyleValidator $validator Validator.
	 */
	public function __construct( private StyleValidator $validator ) {}

	/**
	 * Registers the private, revisioned post type.
	 */
	public static function registerPostType(): void {
		register_post_type(
			self::POST_TYPE,
			array(
				'label'           => __( 'Design styles', 'pediment' ),
				'public'          => false,
				'show_ui'         => false,
				'show_in_rest'    => false,
				'rewrite'         => false,
				'query_var'       => false,
				'supports'        => array( 'title', 'revisions' ),
				'capability_type' => 'page',
				'map_meta_cap'    => true,
			)
		);
	}

	/**
	 * Creates or updates a style by slug.
	 *
	 * @param array $spec Style spec.
	 * @return array{id:int,revision:int}|\WP_Error
	 */
	public function save( array $spec ) {
		$errors = $this->validator->validate( $spec );
		if ( array() !== $errors ) {
			return new \WP_Error( 'pediment_style_invalid', __( 'The style breaks the design rules.', 'pediment' ), array( 'errors' => $errors ) );
		}

		$payload = array(
			'slug'       => $spec['slug'],
			'label'      => $spec['label'],
			'blockTypes' => array_values( $spec['blockTypes'] ),
			'styles'     => $spec['styles'],
		);

		$post     = $this->find( $spec['slug'] );
		$postarr  = array(
			'post_type'    => self::POST_TYPE,
			'post_status'  => 'publish',
			'post_name'    => $spec['slug'],
			'post_title'   => $spec['label'],
			'post_content' => wp_slash( (string) wp_json_encode( $payload, self::JSON_FLAGS ) ),
		);
		if ( null !== $post ) {
			$postarr['ID'] = $post->ID;
		}

		$id = null === $post ? wp_insert_post( $postarr, true ) : wp_update_post( $postarr, true );
		if ( is_wp_error( $id ) ) {
			return $id;
		}

		update_post_meta( (int) $id, self::STATE_META, self::STATE_ACTIVE );
		wp_clean_theme_json_cache();

		$revisions = wp_get_post_revisions( (int) $id, array( 'posts_per_page' => 1 ) );
		return array(
			'id'       => (int) $id,
			'revision' => (int) ( array_key_first( $revisions ) ?? 0 ),
		);
	}

	/**
	 * Returns a stored style.
	 *
	 * @param string $slug Slug.
	 * @return array|null
	 */
	public function get( string $slug ): ?array {
		$post = $this->find( $slug );
		return null === $post ? null : $this->to_spec( $post );
	}

	/**
	 * Lists stored styles, optionally by state, ordered by slug.
	 *
	 * @param string|null $state State filter.
	 * @return list<array>
	 */
	public function all( ?string $state = null ): array {
		$args = array(
			'post_type'      => self::POST_TYPE,
			'post_status'    => 'publish',
			'posts_per_page' => -1,
			'orderby'        => 'name',
			'order'          => 'ASC',
			'no_found_rows'  => true,
		);
		if ( null !== $state ) {
			$args['meta_key']   = self::STATE_META; // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
			$args['meta_value'] = $state; // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
		}
		$specs = array();
		foreach ( get_posts( $args ) as $post ) {
			$spec = $this->to_spec( $post );
			if ( null !== $spec ) {
				$specs[] = $spec;
			}
		}
		return $specs;
	}

	/**
	 * Sets a style's state.
	 *
	 * @param string $slug  Slug.
	 * @param string $state One of the STATE_* constants.
	 */
	public function setState( string $slug, string $state ): bool {
		$post = $this->find( $slug );
		if ( null === $post || ! in_array( $state, array( self::STATE_ACTIVE, self::STATE_PROMOTED ), true ) ) {
			return false;
		}
		update_post_meta( $post->ID, self::STATE_META, $state );
		wp_clean_theme_json_cache();
		return true;
	}

	/**
	 * Deletes a style permanently.
	 *
	 * @param string $slug Slug.
	 */
	public function delete( string $slug ): bool {
		$post = $this->find( $slug );
		if ( null === $post ) {
			return false;
		}
		$deleted = false !== wp_delete_post( $post->ID, true );
		wp_clean_theme_json_cache();
		return $deleted;
	}

	/**
	 * Finds the post for a slug.
	 *
	 * @param string $slug Slug.
	 */
	private function find( string $slug ): ?\WP_Post {
		$posts = get_posts(
			array(
				'post_type'      => self::POST_TYPE,
				'post_status'    => 'publish',
				'post_name__in'  => array( $slug ),
				'posts_per_page' => 1,
				'no_found_rows'  => true,
			)
		);
		return $posts[0] ?? null;
	}

	/**
	 * Decodes a post into a spec.
	 *
	 * @param \WP_Post $post Post.
	 */
	private function to_spec( \WP_Post $post ): ?array {
		$data = json_decode( $post->post_content, true );
		if ( ! is_array( $data ) ) {
			return null;
		}
		$state = (string) get_post_meta( $post->ID, self::STATE_META, true );
		return array(
			'id'         => $post->ID,
			'slug'       => (string) ( $data['slug'] ?? $post->post_name ),
			'label'      => (string) ( $data['label'] ?? $post->post_title ),
			'blockTypes' => array_values( (array) ( $data['blockTypes'] ?? array() ) ),
			'styles'     => (array) ( $data['styles'] ?? array() ),
			'state'      => '' === $state ? self::STATE_ACTIVE : $state,
		);
	}
}
```

Note: `post_name__in` keeps the query non-singular (see `docs/WORDPRESS_TRAPS.md` and the header-part bug in `docs/BACKLOG.md`).

- [ ] **Step 4: Wire the post type**

In `plugin/src/Bootstrap.php` `register()`, add:

```php
		add_action( 'init', array( \Pediment\Design\SiteStyles::class, 'registerPostType' ) );
```

- [ ] **Step 5: Run tests to verify they pass**

Run: `npx wp-env run tests-wordpress --env-cwd=wp-content/plugins/pediment-ai ./vendor/bin/phpunit --filter SiteStylesTest`
Expected: PASS (6 tests).

- [ ] **Step 6: Lint and commit**

```bash
composer lint -d plugin
git add plugin/src/Design/SiteStyles.php plugin/src/Bootstrap.php plugin/tests/phpunit/Design/SiteStylesTest.php
git commit -m "feat(design): add revisioned site-layer style store"
```

---

### Task 4: Site layer at runtime

**Files:**
- Create: `plugin/src/Design/SiteLayer.php`
- Modify: `plugin/src/Bootstrap.php` (`register()`: one line)
- Test: `plugin/tests/phpunit/Design/SiteLayerTest.php`

**Interfaces:**
- Consumes: `SiteStyles` (Task 3), `DefaultStyles::registerAll()` (Task 2).
- Produces (class `Pediment\Design\SiteLayer`):
  - `__construct( SiteStyles $store )`
  - `static register( SiteLayer $layer ): void` — hooks `registerNames` on `init` (priority 21), `injectUserData` on `wp_theme_json_data_user`, `promoteShippedStyles` on `admin_init`
  - `registerNames(): void`
  - `injectUserData( \WP_Theme_JSON_Data $data ): \WP_Theme_JSON_Data`
  - `promoteShippedStyles( ?array $theme_slugs = null ): list<string>` — returns the slugs it promoted
  - `static themeSlugs(): list<string>` — slugs of the active theme's block style partials

- [ ] **Step 1: Write the failing test**

```php
<?php

use Pediment\Design\DefaultStyles;
use Pediment\Design\SiteLayer;
use Pediment\Design\SiteStyles;
use Pediment\Design\StyleValidator;

class SiteLayerTest extends WP_UnitTestCase {

	private SiteStyles $store;
	private SiteLayer $layer;

	public function set_up(): void {
		parent::set_up();
		$this->store = new SiteStyles( new StyleValidator() );
		$this->layer = new SiteLayer( $this->store );
		add_filter( 'wp_theme_json_data_user', array( $this->layer, 'injectUserData' ) );
	}

	public function tear_down(): void {
		remove_filter( 'wp_theme_json_data_user', array( $this->layer, 'injectUserData' ) );
		wp_clean_theme_json_cache();
		parent::tear_down();
	}

	private function render_variation_css( string $markup ): string {
		wp_deregister_style( 'block-style-variation-styles' );
		wp_clean_theme_json_cache();
		do_blocks( $markup );
		$after = wp_styles()->get_data( 'block-style-variation-styles', 'after' );
		return is_array( $after ) ? implode( "\n", $after ) : '';
	}

	public function test_new_site_style_is_registered_and_renders(): void {
		$this->store->save(
			array(
				'slug'       => 'promo',
				'label'      => 'Promo',
				'blockTypes' => array( 'core/group' ),
				'styles'     => array( 'color' => array( 'background' => 'var:preset|color|accent' ) ),
			)
		);
		$this->layer->registerNames();

		$registered = WP_Block_Styles_Registry::get_instance()->get_registered_styles_for_block( 'core/group' );
		$this->assertArrayHasKey( 'promo', $registered );
		$this->assertSame( 'Promo', $registered['promo']['label'] );

		$css = $this->render_variation_css( '<!-- wp:group {"className":"is-style-promo"} --><div class="wp-block-group is-style-promo"><p>x</p></div><!-- /wp:group -->' );
		$this->assertStringContainsString( 'background-color: var(--wp--preset--color--accent)', $css );
	}

	public function test_site_style_overrides_plugin_default_and_keeps_other_properties(): void {
		DefaultStyles::registerAll();
		$this->store->save(
			array(
				'slug'       => 'card',
				'label'      => 'Card',
				'blockTypes' => array( 'core/group' ),
				'styles'     => array( 'border' => array( 'radius' => 'var(--wp--custom--radius--sm)' ) ),
			)
		);
		$this->layer->registerNames();

		$css = $this->render_variation_css( '<!-- wp:group {"className":"is-style-card"} --><div class="wp-block-group is-style-card"><p>x</p></div><!-- /wp:group -->' );
		$this->assertStringContainsString( 'border-radius: var(--wp--custom--radius--sm)', $css );
		$this->assertStringNotContainsString( 'radius--md', $css );
		$this->assertStringContainsString( 'var(--wp--preset--shadow--subtle)', $css );
	}

	public function test_promoted_styles_stop_loading(): void {
		$this->store->save(
			array(
				'slug'       => 'promo',
				'label'      => 'Promo',
				'blockTypes' => array( 'core/group' ),
				'styles'     => array( 'color' => array( 'background' => 'var:preset|color|accent' ) ),
			)
		);
		$this->assertSame( array( 'promo' ), $this->layer->promoteShippedStyles( array( 'promo', 'unrelated' ) ) );
		$this->assertSame( SiteStyles::STATE_PROMOTED, $this->store->get( 'promo' )['state'] );

		$data = $this->layer->injectUserData( new WP_Theme_JSON_Data( array( 'version' => 3 ), 'custom' ) );
		$this->assertArrayNotHasKey( 'blocks', $data->get_data()['styles'] ?? array() );
	}
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `npx wp-env run tests-wordpress --env-cwd=wp-content/plugins/pediment-ai ./vendor/bin/phpunit --filter SiteLayerTest`
Expected: FAIL — `Class "Pediment\Design\SiteLayer" not found`.

- [ ] **Step 3: Write `SiteLayer`**

```php
<?php
/**
 * Applies the site layer: registers style names and injects style data into
 * the user global-styles origin, which overrides plugin and theme layers.
 *
 * @package Pediment
 */

declare(strict_types=1);

namespace Pediment\Design;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Runtime side of the site layer.
 */
final class SiteLayer {

	/**
	 * Constructor.
	 *
	 * @param SiteStyles $store Store.
	 */
	public function __construct( private SiteStyles $store ) {}

	/**
	 * Hooks the layer into WordPress.
	 *
	 * @param SiteLayer $layer Instance.
	 */
	public static function register( SiteLayer $layer ): void {
		add_action( 'init', array( $layer, 'registerNames' ), 21 );
		add_filter( 'wp_theme_json_data_user', array( $layer, 'injectUserData' ) );
		add_action( 'admin_init', array( $layer, 'promoteShippedStyles' ) );
	}

	/**
	 * Registers each active site style's name so theme.json keeps its data and
	 * the Styles panel lists it. Names already registered (plugin defaults)
	 * are left alone; the user origin overrides their data.
	 */
	public function registerNames(): void {
		$registry = \WP_Block_Styles_Registry::get_instance();
		foreach ( $this->store->all( SiteStyles::STATE_ACTIVE ) as $spec ) {
			foreach ( $spec['blockTypes'] as $block_type ) {
				if ( ! $registry->is_registered( $block_type, $spec['slug'] ) ) {
					register_block_style(
						$block_type,
						array(
							'name'  => $spec['slug'],
							'label' => $spec['label'],
						)
					);
				}
			}
		}
	}

	/**
	 * Adds active site styles to the user origin.
	 *
	 * @param \WP_Theme_JSON_Data $data User-origin data.
	 * @return \WP_Theme_JSON_Data
	 */
	public function injectUserData( $data ) {
		$blocks = array();
		foreach ( $this->store->all( SiteStyles::STATE_ACTIVE ) as $spec ) {
			foreach ( $spec['blockTypes'] as $block_type ) {
				$blocks[ $block_type ]['variations'][ $spec['slug'] ] = $spec['styles'];
			}
		}
		if ( array() === $blocks ) {
			return $data;
		}
		return $data->update_with(
			array(
				'version' => \WP_Theme_JSON::LATEST_SCHEMA,
				'styles'  => array( 'blocks' => $blocks ),
			)
		);
	}

	/**
	 * Marks active site styles as promoted once the theme ships the same slug.
	 *
	 * @param list<string>|null $theme_slugs Theme partial slugs; read from the active theme when null.
	 * @return list<string> Promoted slugs.
	 */
	public function promoteShippedStyles( ?array $theme_slugs = null ): array {
		$theme_slugs = $theme_slugs ?? self::themeSlugs();
		$promoted    = array();
		foreach ( $this->store->all( SiteStyles::STATE_ACTIVE ) as $spec ) {
			if ( in_array( $spec['slug'], $theme_slugs, true ) && $this->store->setState( $spec['slug'], SiteStyles::STATE_PROMOTED ) ) {
				$promoted[] = $spec['slug'];
			}
		}
		return $promoted;
	}

	/**
	 * Slugs of the active theme's block style partials (styles/**.json).
	 *
	 * @return list<string>
	 */
	public static function themeSlugs(): array {
		$slugs = array();
		foreach ( \WP_Theme_JSON_Resolver::get_style_variations( 'block' ) as $variation ) {
			$slugs[] = (string) ( $variation['slug'] ?? _wp_to_kebab_case( (string) ( $variation['title'] ?? '' ) ) );
		}
		return $slugs;
	}
}
```

- [ ] **Step 4: Wire it**

In `plugin/src/Bootstrap.php` `register()`, add:

```php
		\Pediment\Design\SiteLayer::register(
			new \Pediment\Design\SiteLayer( new \Pediment\Design\SiteStyles( new \Pediment\Design\StyleValidator() ) )
		);
```

- [ ] **Step 5: Run tests to verify they pass**

Run: `npx wp-env run tests-wordpress --env-cwd=wp-content/plugins/pediment-ai ./vendor/bin/phpunit --filter 'SiteLayerTest|DefaultStylesTest|SiteStylesTest'`
Expected: PASS.

- [ ] **Step 6: Check the editor manually**

Start the root env, create a site style with `npx wp-env run cli wp eval '( new Pediment\Design\SiteStyles( new Pediment\Design\StyleValidator() ) )->save( array( "slug" => "promo", "label" => "Promo", "blockTypes" => array( "core/group" ), "styles" => array( "color" => array( "background" => "var:preset|color|accent" ) ) ) );'`, open a page in the block editor at http://localhost:8888/wp-admin, select a Group, and confirm "Promo", "Card" and "Band" appear in the Styles panel and change the canvas. Delete it afterwards with `npx wp-env run cli wp eval '( new Pediment\Design\SiteStyles( new Pediment\Design\StyleValidator() ) )->delete( "promo" );'`.

- [ ] **Step 7: Lint and commit**

```bash
composer lint -d plugin
git add plugin/src/Design/SiteLayer.php plugin/src/Bootstrap.php plugin/tests/phpunit/Design/SiteLayerTest.php
git commit -m "feat(design): apply site-layer styles over theme and plugin"
```

---

### Task 5: Vocabulary

**Files:**
- Create: `plugin/src/Design/Vocabulary.php`
- Test: `plugin/tests/phpunit/Design/VocabularyTest.php`

**Interfaces:**
- Consumes: `DefaultStyles::specs()` (Task 2), `SiteStyles::all()` (Task 3), `StyleValidator` (Task 1).
- Produces: `Pediment\Design\Vocabulary::__construct( StyleValidator $validator, SiteStyles $store )` and `styles( ?array $theme_variations = null ): array` → `list<array{ slug: string, label: string, blockTypes: list<string>, layer: 'plugin'|'theme'|'site', overrides: ?string, styles: array, warnings: list<string> }>`, sorted by slug. `$theme_variations` defaults to `WP_Theme_JSON_Resolver::get_style_variations( 'block' )`. Plan 3's `get-vocabulary` ability uses this.

- [ ] **Step 1: Write the failing test**

```php
<?php

use Pediment\Design\SiteStyles;
use Pediment\Design\StyleValidator;
use Pediment\Design\Vocabulary;

class VocabularyTest extends WP_UnitTestCase {

	public function test_layers_merge_by_slug_with_the_top_layer_winning(): void {
		$store = new SiteStyles( new StyleValidator() );
		$store->save(
			array(
				'slug'       => 'card',
				'label'      => 'Card (site)',
				'blockTypes' => array( 'core/group' ),
				'styles'     => array( 'border' => array( 'radius' => 'var(--wp--custom--radius--sm)' ) ),
			)
		);
		$theme = array(
			array(
				'title'      => 'Band',
				'slug'       => 'band',
				'blockTypes' => array( 'core/group' ),
				'styles'     => array( 'color' => array( 'background' => 'var:preset|color|primary' ) ),
			),
		);

		$by_slug = array_column( ( new Vocabulary( new StyleValidator(), $store ) )->styles( $theme ), null, 'slug' );

		$this->assertSame( 'site', $by_slug['card']['layer'] );
		$this->assertSame( 'plugin', $by_slug['card']['overrides'] );
		$this->assertSame( 'Card (site)', $by_slug['card']['label'] );
		$this->assertSame( 'theme', $by_slug['band']['layer'] );
		$this->assertSame( 'plugin', $by_slug['band']['overrides'] );
		$this->assertSame( 'plugin', $by_slug['eyebrow']['layer'] );
		$this->assertNull( $by_slug['eyebrow']['overrides'] );
		$list = ( new Vocabulary( new StyleValidator(), $store ) )->styles( $theme );
		$this->assertCount( count( $by_slug ), $list, 'one entry per slug' );
	}

	public function test_invalid_theme_style_is_reported_not_dropped(): void {
		$theme = array(
			array(
				'title'      => 'Hero box',
				'blockTypes' => array( 'core/group' ),
				'styles'     => array( 'spacing' => array( 'padding' => array( 'top' => '78px' ) ) ),
			),
		);
		$by_slug = array_column( ( new Vocabulary( new StyleValidator(), new SiteStyles( new StyleValidator() ) ) )->styles( $theme ), null, 'slug' );

		$this->assertArrayHasKey( 'hero-box', $by_slug );
		$this->assertSame( 'theme', $by_slug['hero-box']['layer'] );
		$this->assertStringContainsString( 'styles.spacing.padding.top', $by_slug['hero-box']['warnings'][0] );
	}
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `npx wp-env run tests-wordpress --env-cwd=wp-content/plugins/pediment-ai ./vendor/bin/phpunit --filter VocabularyTest`
Expected: FAIL — `Class "Pediment\Design\Vocabulary" not found`.

- [ ] **Step 3: Write `Vocabulary`**

```php
<?php
/**
 * The merged design vocabulary: every style the site can use, with the layer
 * that provides it.
 *
 * @package Pediment
 */

declare(strict_types=1);

namespace Pediment\Design;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Reads plugin, theme and site layers into one list.
 */
final class Vocabulary {

	/**
	 * Constructor.
	 *
	 * @param StyleValidator $validator Validator (theme warnings).
	 * @param SiteStyles     $store     Site layer.
	 */
	public function __construct( private StyleValidator $validator, private SiteStyles $store ) {}

	/**
	 * Lists effective styles.
	 *
	 * @param array|null $theme_variations Theme partials; read from the active theme when null.
	 * @return list<array>
	 */
	public function styles( ?array $theme_variations = null ): array {
		$theme_variations = $theme_variations ?? \WP_Theme_JSON_Resolver::get_style_variations( 'block' );

		$out = array();
		foreach ( DefaultStyles::specs() as $spec ) {
			$out[ $spec['slug'] ] = $this->entry( $spec, 'plugin', array(), null );
		}
		foreach ( $theme_variations as $variation ) {
			$spec = array(
				'slug'       => (string) ( $variation['slug'] ?? _wp_to_kebab_case( (string) ( $variation['title'] ?? '' ) ) ),
				'label'      => (string) ( $variation['title'] ?? '' ),
				'blockTypes' => array_values( (array) ( $variation['blockTypes'] ?? array() ) ),
				'styles'     => (array) ( $variation['styles'] ?? array() ),
			);
			$out[ $spec['slug'] ] = $this->entry( $spec, 'theme', $this->validator->validate( $spec ), $out[ $spec['slug'] ]['layer'] ?? null );
		}
		foreach ( $this->store->all( SiteStyles::STATE_ACTIVE ) as $spec ) {
			$out[ $spec['slug'] ] = $this->entry( $spec, 'site', array(), $out[ $spec['slug'] ]['layer'] ?? null );
		}
		ksort( $out );
		return array_values( $out );
	}

	/**
	 * Builds one entry.
	 *
	 * @param array       $spec      Spec.
	 * @param string      $layer     Layer.
	 * @param list<string> $warnings Warnings.
	 * @param string|null $overrides Layer this entry replaces.
	 */
	private function entry( array $spec, string $layer, array $warnings, ?string $overrides ): array {
		return array(
			'slug'       => $spec['slug'],
			'label'      => $spec['label'],
			'blockTypes' => $spec['blockTypes'],
			'layer'      => $layer,
			'overrides'  => $overrides,
			'styles'     => $spec['styles'],
			'warnings'   => $warnings,
		);
	}
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `npx wp-env run tests-wordpress --env-cwd=wp-content/plugins/pediment-ai ./vendor/bin/phpunit --filter VocabularyTest`
Expected: PASS (2 tests).

- [ ] **Step 5: Lint and commit**

```bash
composer lint -d plugin
git add plugin/src/Design/Vocabulary.php plugin/tests/phpunit/Design/VocabularyTest.php
git commit -m "feat(design): list the merged design vocabulary"
```

---

### Task 6: Export to the theme (CLI and wp-admin)

**Files:**
- Create: `plugin/src/Design/Exporter.php`
- Create: `plugin/src/Design/AdminTab.php`
- Create: `plugin/wp-cli/DesignCommand.php`
- Modify: `plugin/plugin.php` (WP-CLI block, after the `pediment seed` registration)
- Modify: `plugin/src/Bootstrap.php` (`register()`: one line)
- Modify: `plugin/tests/phpunit/Settings/FormsTabsTest.php` and `plugin/tests/phpunit/ChatRemovedTest.php` (tab lists gain `design`)
- Test: `plugin/tests/phpunit/Design/ExporterTest.php`

**Interfaces:**
- Consumes: `SiteStyles::all()` (Task 3), `Vocabulary::styles()` (Task 5).
- Produces:
  - `Pediment\Design\Exporter::__construct( SiteStyles $store )`
  - `Exporter::files(): array<string, string>` — `"<slug>.json" => JSON` for every **active** site style
  - `Exporter::writeTo( string $dir ): list<string>` — written paths; creates `$dir`
  - `Exporter::zip(): string|\WP_Error` — path of a temporary zip
  - `Pediment\Design\AdminTab::register(): void` — "Design" tab (registered on `admin_menu`, priority 40, so it sorts last) in Settings → Pediment Theme, plus `admin_post_pediment_design_export`
  - WP-CLI `wp pediment design list [--format=<table|json>]` and `wp pediment design export [--dir=<dir>]`

- [ ] **Step 1: Write the failing test**

```php
<?php

use Pediment\Design\Exporter;
use Pediment\Design\SiteStyles;
use Pediment\Design\StyleValidator;

class ExporterTest extends WP_UnitTestCase {

	private SiteStyles $store;

	public function set_up(): void {
		parent::set_up();
		$this->store = new SiteStyles( new StyleValidator() );
	}

	private function save( string $slug ): void {
		$this->store->save(
			array(
				'slug'       => $slug,
				'label'      => ucfirst( $slug ),
				'blockTypes' => array( 'core/group' ),
				'styles'     => array(
					'color' => array( 'background' => 'var:preset|color|accent' ),
					'css'   => '& > .x::after{content:"→"}',
				),
			)
		);
	}

	public function test_files_are_theme_partials_for_active_styles_only(): void {
		$this->save( 'promo' );
		$this->save( 'old' );
		$this->store->setState( 'old', SiteStyles::STATE_PROMOTED );

		$files = ( new Exporter( $this->store ) )->files();

		$this->assertSame( array( 'promo.json' ), array_keys( $files ) );
		$data = json_decode( $files['promo.json'], true );
		$this->assertSame( 3, $data['version'] );
		$this->assertSame( 'promo', $data['slug'] );
		$this->assertSame( 'Promo', $data['title'] );
		$this->assertSame( array( 'core/group' ), $data['blockTypes'] );
		$this->assertSame( '& > .x::after{content:"→"}', $data['styles']['css'] );
	}

	public function test_no_active_styles_exports_nothing_without_error(): void {
		$exporter = new Exporter( $this->store );
		$this->assertSame( array(), $exporter->files() );
		$this->assertSame( array(), $exporter->writeTo( get_temp_dir() . 'pediment-export-empty-' . wp_rand() ) );
	}

	public function test_write_to_and_zip(): void {
		$this->save( 'promo' );
		$exporter = new Exporter( $this->store );

		$dir   = get_temp_dir() . 'pediment-export-' . wp_rand();
		$paths = $exporter->writeTo( $dir );
		$this->assertSame( array( $dir . '/promo.json' ), $paths );
		$this->assertFileExists( $dir . '/promo.json' );

		$zip = $exporter->zip();
		$this->assertIsString( $zip );
		$archive = new ZipArchive();
		$this->assertTrue( $archive->open( $zip ) );
		$this->assertNotFalse( $archive->locateName( 'styles/blocks/promo.json' ) );
		$archive->close();
	}
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `npx wp-env run tests-wordpress --env-cwd=wp-content/plugins/pediment-ai ./vendor/bin/phpunit --filter ExporterTest`
Expected: FAIL — `Class "Pediment\Design\Exporter" not found`.

- [ ] **Step 3: Write `Exporter`**

```php
<?php
/**
 * Exports active site-layer styles as client-theme block style partials.
 *
 * @package Pediment
 */

declare(strict_types=1);

namespace Pediment\Design;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Turns site styles into `styles/blocks/<slug>.json` files. Never deletes.
 */
final class Exporter {

	/**
	 * Constructor.
	 *
	 * @param SiteStyles $store Store.
	 */
	public function __construct( private SiteStyles $store ) {}

	/**
	 * File name => JSON for every active site style.
	 *
	 * @return array<string, string>
	 */
	public function files(): array {
		$files = array();
		foreach ( $this->store->all( SiteStyles::STATE_ACTIVE ) as $spec ) {
			$partial = array(
				'$schema'    => 'https://schemas.wp.org/trunk/theme.json',
				'version'    => 3,
				'title'      => $spec['label'],
				'slug'       => $spec['slug'],
				'blockTypes' => $spec['blockTypes'],
				'styles'     => $spec['styles'],
			);
			$files[ $spec['slug'] . '.json' ] = (string) wp_json_encode( $partial, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) . "\n";
		}
		return $files;
	}

	/**
	 * Writes the files into a directory.
	 *
	 * @param string $dir Target directory.
	 * @return list<string> Written paths.
	 */
	public function writeTo( string $dir ): array {
		$files = $this->files();
		if ( array() === $files ) {
			return array();
		}
		wp_mkdir_p( $dir );
		$paths = array();
		foreach ( $files as $name => $json ) {
			$path = rtrim( $dir, '/' ) . '/' . $name;
			file_put_contents( $path, $json ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
			$paths[] = $path;
		}
		return $paths;
	}

	/**
	 * Builds a zip with styles/blocks/*.json.
	 *
	 * @return string|\WP_Error Zip path.
	 */
	public function zip() {
		if ( ! class_exists( '\ZipArchive' ) ) {
			return new \WP_Error( 'pediment_zip_unavailable', __( 'This server has no ZipArchive. Use `wp pediment design export` instead.', 'pediment' ) );
		}
		$path    = wp_tempnam( 'pediment-styles.zip' );
		$archive = new \ZipArchive();
		if ( true !== $archive->open( $path, \ZipArchive::CREATE | \ZipArchive::OVERWRITE ) ) {
			return new \WP_Error( 'pediment_zip_failed', __( 'Could not create the zip file.', 'pediment' ) );
		}
		foreach ( $this->files() as $name => $json ) {
			$archive->addFromString( 'styles/blocks/' . $name, $json );
		}
		$archive->close();
		return $path;
	}
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `npx wp-env run tests-wordpress --env-cwd=wp-content/plugins/pediment-ai ./vendor/bin/phpunit --filter ExporterTest`
Expected: PASS (3 tests). (Run `--filter 'FormsTabsTest|ChatRemovedTest'` after Step 6.)

- [ ] **Step 5: Write the WP-CLI command**

`plugin/wp-cli/DesignCommand.php`:

```php
<?php
/**
 * WP-CLI: `wp pediment design`.
 *
 * @package Pediment
 */

declare(strict_types=1);

namespace Pediment\Cli;

use Pediment\Design\Exporter;
use Pediment\Design\SiteStyles;
use Pediment\Design\StyleValidator;
use Pediment\Design\Vocabulary;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Lists and exports design-layer styles.
 */
final class DesignCommand {

	/**
	 * Lists every style the site can use and its layer.
	 *
	 * ## OPTIONS
	 *
	 * [--format=<format>]
	 * : Output format.
	 * ---
	 * default: table
	 * options:
	 *   - table
	 *   - json
	 * ---
	 *
	 * @subcommand list
	 *
	 * @param array<int,string>    $args      Positional args (unused).
	 * @param array<string,string> $assocArgs Associative args.
	 */
	public function list_( array $args, array $assocArgs ): void {
		$validator = new StyleValidator();
		$rows      = array();
		foreach ( ( new Vocabulary( $validator, new SiteStyles( $validator ) ) )->styles() as $style ) {
			$rows[] = array(
				'slug'       => $style['slug'],
				'label'      => $style['label'],
				'blockTypes' => implode( ',', $style['blockTypes'] ),
				'layer'      => $style['layer'],
				'overrides'  => (string) $style['overrides'],
				'warnings'   => count( $style['warnings'] ),
			);
		}
		\WP_CLI\Utils\format_items( $assocArgs['format'] ?? 'table', $rows, array( 'slug', 'label', 'blockTypes', 'layer', 'overrides', 'warnings' ) );
	}

	/**
	 * Writes active site styles as theme partials.
	 *
	 * ## OPTIONS
	 *
	 * [--dir=<dir>]
	 * : Target directory. Defaults to the active theme's styles/blocks.
	 *
	 * ## EXAMPLES
	 *
	 *     wp pediment design export
	 *     wp pediment design export --dir=/tmp/styles
	 *
	 * @param array<int,string>    $args      Positional args (unused).
	 * @param array<string,string> $assocArgs Associative args.
	 */
	public function export( array $args, array $assocArgs ): void {
		$dir   = $assocArgs['dir'] ?? get_stylesheet_directory() . '/styles/blocks';
		$paths = ( new Exporter( new SiteStyles( new StyleValidator() ) ) )->writeTo( $dir );
		foreach ( $paths as $path ) {
			\WP_CLI::log( $path );
		}
		\WP_CLI::success( sprintf( '%d style file(s) written.', count( $paths ) ) );
	}
}
```

In `plugin/plugin.php`, inside the `if ( defined( 'WP_CLI' ) && WP_CLI )` block, after the `pediment seed` line, add:

```php
	require_once __DIR__ . '/wp-cli/DesignCommand.php';
	\WP_CLI::add_command( 'pediment design', \Pediment\Cli\DesignCommand::class );
```

- [ ] **Step 6: Write the Design tab**

`plugin/src/Design/AdminTab.php`:

```php
<?php
/**
 * "Design" tab in Settings → Pediment Theme: lists the vocabulary and offers
 * the theme-file download.
 *
 * @package Pediment
 */

declare(strict_types=1);

namespace Pediment\Design;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Admin UI for the design layer.
 */
final class AdminTab {

	private const ACTION = 'pediment_design_export';

	/**
	 * Registers the tab and the download handler.
	 */
	public static function register(): void {
		add_action(
			'admin_menu',
			static function () {
				pediment_settings_register_tab( 'design', __( 'Design', 'pediment' ), array( self::class, 'render' ), 40 );
			}
		);
		add_action( 'admin_post_' . self::ACTION, array( self::class, 'download' ) );
	}

	/**
	 * Renders the tab body.
	 */
	public static function render(): void {
		$validator = new StyleValidator();
		$store     = new SiteStyles( $validator );
		$styles    = ( new Vocabulary( $validator, $store ) )->styles();
		$active    = count( $store->all( SiteStyles::STATE_ACTIVE ) );
		?>
		<table class="widefat striped">
			<thead><tr>
				<th><?php esc_html_e( 'Style', 'pediment' ); ?></th>
				<th><?php esc_html_e( 'Blocks', 'pediment' ); ?></th>
				<th><?php esc_html_e( 'Layer', 'pediment' ); ?></th>
				<th><?php esc_html_e( 'Warnings', 'pediment' ); ?></th>
			</tr></thead>
			<tbody>
			<?php foreach ( $styles as $style ) : ?>
				<tr>
					<td><strong><?php echo esc_html( $style['label'] ); ?></strong> <code><?php echo esc_html( $style['slug'] ); ?></code></td>
					<td><?php echo esc_html( implode( ', ', $style['blockTypes'] ) ); ?></td>
					<td><?php echo esc_html( $style['layer'] . ( null !== $style['overrides'] ? ' → ' . $style['overrides'] : '' ) ); ?></td>
					<td><?php echo esc_html( implode( ' · ', $style['warnings'] ) ); ?></td>
				</tr>
			<?php endforeach; ?>
			</tbody>
		</table>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="<?php echo esc_attr( self::ACTION ); ?>" />
			<?php wp_nonce_field( self::ACTION ); ?>
			<p>
				<?php submit_button( __( 'Download site styles as theme files', 'pediment' ), 'secondary', 'submit', false, 0 === $active ? array( 'disabled' => 'disabled' ) : array() ); ?>
				<span class="description"><?php echo esc_html( sprintf( /* translators: %d: number of styles */ _n( '%d active site style.', '%d active site styles.', $active, 'pediment' ), $active ) ); ?></span>
			</p>
		</form>
		<?php
	}

	/**
	 * Streams the zip.
	 */
	public static function download(): void {
		if ( ! current_user_can( 'edit_theme_options' ) ) {
			wp_die( esc_html__( 'You are not allowed to export styles.', 'pediment' ), 403 );
		}
		check_admin_referer( self::ACTION );
		$zip = ( new Exporter( new SiteStyles( new StyleValidator() ) ) )->zip();
		if ( is_wp_error( $zip ) ) {
			wp_die( esc_html( $zip->get_error_message() ) );
		}
		nocache_headers();
		header( 'Content-Type: application/zip' );
		header( 'Content-Disposition: attachment; filename="pediment-styles.zip"' );
		header( 'Content-Length: ' . (string) filesize( $zip ) );
		readfile( $zip ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_readfile
		wp_delete_file( $zip );
		exit;
	}
}
```

In `plugin/src/Bootstrap.php` `register()`, add:

```php
		\Pediment\Design\AdminTab::register();
```

Two existing tests assert the exact tab list. In `plugin/tests/phpunit/Settings/FormsTabsTest.php` (`test_forms_registers_three_tabs_in_order`) and `plugin/tests/phpunit/ChatRemovedTest.php` (`test_ai_settings_tab_is_gone_and_the_rest_remain`), change the expected list to:

```php
array( 'general', 'seed', 'destinations', 'secrets', 'design' )
```

and add `// 'design' comes from Pediment\Design\AdminTab (priority 40).` above the assertion in `FormsTabsTest`.

- [ ] **Step 7: Verify CLI and tab manually**

```bash
npx wp-env run cli wp pediment design list
npx wp-env run cli wp eval '( new Pediment\Design\SiteStyles( new Pediment\Design\StyleValidator() ) )->save( array( "slug" => "promo", "label" => "Promo", "blockTypes" => array( "core/group" ), "styles" => array( "color" => array( "background" => "var:preset|color|accent" ) ) ) );'
npx wp-env run cli wp pediment design export --dir=/tmp/pediment-styles
npx wp-env run cli cat /tmp/pediment-styles/promo.json
```

Expected: `list` shows the seven plugin styles plus `promo` (layer `site`); `export` writes one file. Open Settings → Pediment Theme → Design at http://localhost:8888/wp-admin/options-general.php?page=pediment-theme&tab=design, click the download button, and confirm the zip holds `styles/blocks/promo.json`. Delete `promo` afterwards.

- [ ] **Step 8: Lint and commit**

```bash
composer lint -d plugin
git add plugin/src/Design/Exporter.php plugin/src/Design/AdminTab.php plugin/wp-cli/DesignCommand.php plugin/plugin.php plugin/src/Bootstrap.php plugin/tests/phpunit/Design/ExporterTest.php plugin/tests/phpunit/Settings/FormsTabsTest.php plugin/tests/phpunit/ChatRemovedTest.php
git commit -m "feat(design): export site styles to the client theme"
```

---

### Task 7: Client template, docs and spec note

**Files:**
- Create: `client-template/styles/blocks/example-panel.json`
- Create: `docs/design-layer.md`
- Modify: `docs/superpowers/specs/2026-10-14-core-blocks-mcp-design.md` (§2 "Style rules", last bullet)
- Modify: `AGENTS.md` (Hard rules: one bullet)
- Test: `npm run test:kit` (the scaffolder copies `client-template/`)

**Interfaces:**
- Consumes: the layer model from Tasks 2–6.
- Produces: nothing other tasks consume.

- [ ] **Step 1: Add the template example**

`client-template/styles/blocks/example-panel.json`:

```json
{
	"$schema": "https://schemas.wp.org/trunk/theme.json",
	"version": 3,
	"title": "Panel",
	"slug": "panel",
	"blockTypes": [ "core/group" ],
	"styles": {
		"color": { "background": "var:preset|color|surface-sunken" },
		"border": { "radius": "var(--wp--custom--radius--lg)" },
		"spacing": { "padding": { "top": "var:preset|spacing|60", "right": "var:preset|spacing|60", "bottom": "var:preset|spacing|60", "left": "var:preset|spacing|60" } }
	}
}
```

- [ ] **Step 2: Run the kit tests**

Run: `npm run test:kit`
Expected: PASS. If a scaffolder test asserts the exact template file list, add `styles/blocks/example-panel.json` to that expectation and re-run.

- [ ] **Step 3: Write `docs/design-layer.md`**

```markdown
# Design layer

Pages get their look from **named block styles**, never from values typed into
blocks. A style is a WordPress block style variation: a slug, a label, the
block types it applies to, theme.json style data, and optional `css`.

## Layers

A higher layer overrides a lower one with the same slug, property by property.
A `css` string in a higher layer replaces the lower layer's `css` completely.

| Layer | Where | Who changes it |
| --- | --- | --- |
| Plugin defaults | `plugin/styles/*.json` | Pediment releases |
| Client theme | `<theme>/styles/blocks/*.json` | Developer, in git |
| Site | private `pediment_style` posts (revisioned) | AI through MCP; `wp eval` in dev |

## Rules (checked on every layer)

- Colours, font sizes, font families, padding, margin, gap, border radius and
  shadows use tokens: `var:preset|color|accent`, `var(--wp--preset--…)`,
  `var(--wp--custom--…)`. `0`, `inherit`, `currentColor`, `transparent` and
  percentages are allowed. Other values (line widths, letter spacing,
  transforms) are free.
- No `!important`. No literal colours in `css`.
- Every `css` selector starts with `&` (the style itself).

Invalid plugin styles are skipped. Invalid theme styles still load but are
listed with warnings under Settings → Pediment Theme → Design. Invalid site
styles are rejected on save.

## Tokens

Radius: `--wp--custom--radius--sm|md|lg|pill`. Spacing: `var:preset|spacing|20…80`.
Font sizes: `xs, sm, base, lg, xl, 2-xl, 3-xl, 4-xl`. Shadows: `subtle, medium, lifted`.

## Moving site styles into the theme

1. Settings → Pediment Theme → Design → **Download site styles as theme files**,
   or `wp pediment design export` in a dev environment.
2. Commit the files to the client theme under `styles/blocks/`, release it.
3. After the update, the site layer marks those styles as *promoted* (on the
   next wp-admin load) and stops loading them. Nothing is deleted.

## Commands

    wp pediment design list [--format=json]
    wp pediment design export [--dir=<dir>]
```

- [ ] **Step 4: Record the spec decision**

In `docs/superpowers/specs/2026-10-14-core-blocks-mcp-design.md`, replace the bullet

`- Invalid plugin/theme styles are skipped with a `_doing_it_wrong` notice; an invalid site-layer write is rejected with an error list.`

with

`- Invalid plugin styles are skipped with a `_doing_it_wrong` notice. Invalid theme styles still load (dropping them could change live sites whose themes predate the rules) and are reported as warnings in the vocabulary and the Design tab. An invalid site-layer write is rejected with an error list.`

- [ ] **Step 5: Add the hard rule**

In `AGENTS.md`, under "Hard rules", add:

`- Page content never carries literal design values. Looks come from named block styles in the design layer — see [docs/design-layer.md](docs/design-layer.md).`

- [ ] **Step 6: Commit**

```bash
git add client-template/styles/blocks/example-panel.json docs/design-layer.md docs/superpowers/specs/2026-10-14-core-blocks-mcp-design.md AGENTS.md
git commit -m "docs(design): document the design layer"
```

---

## Final verification

- [ ] Run the full suite and the linters:

```bash
npx wp-env run tests-wordpress --env-cwd=wp-content/plugins/pediment-ai ./vendor/bin/phpunit
composer lint -d plugin
npm run lint:js
npm run lint:blocks
npm run lint:colors
npm run test:kit
```

Expected: all pass. Any pre-existing failure must be shown with output, not hidden.
