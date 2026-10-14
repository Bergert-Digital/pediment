# Plan 3 — MCP Abilities, Content Rules and Block Validator Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Let an AI agent build and change pages and design styles on a live Pediment site through MCP, with server-enforced content rules, a block-validity check before writes, and an editor-side safety net.

**Architecture:** Eight WordPress Abilities (`pediment/*`, WordPress 6.9 core Abilities API) wrap the design layer from Plan 2 and a new content `Policy`. A Pediment MCP server exposes exactly those abilities when the canonical MCP Adapter plugin (v0.7.0) is active; without it the abilities still work over core REST. Static-block validity is checked client-side by a new `client-kit` CLI (`validate-blocks`) that runs `@wordpress/blocks` in Node from a pinned, cached runtime; the editor reports any remaining invalid blocks back to the site.

**Tech Stack:** PHP 8.1, WordPress 6.9 Abilities API, WordPress MCP Adapter 0.7.0, PHPUnit via wp-env, Node 20+ (`node:test`, jsdom 26, `@wordpress/blocks` 15.6.3 / `@wordpress/block-library` 9.33.10 for WP 6.9), GitHub Actions.

**Spec:** `docs/superpowers/specs/2026-10-14-core-blocks-mcp-design.md` (§3 MCP tools and content rules, §5 Testing, §6 Documentation). Roadmap: `docs/superpowers/plans/2026-10-14-roadmap.md`. Research notes (local, gitignored, read them when a task points there): `.context/plan3-research-abilities.md`, `.context/plan3-research-validator.md`, pinned validator lockfiles in `.context/validator-lock/`.

## Global Constraints

- Builds on Plans 1–2 (branch `bergjs/elementor-pediment-necessity`). `Requires at least: 6.9`. PHP 8.1, `declare(strict_types=1);`, `ABSPATH` guard, text domain `pediment`, `_doing_it_wrong` version `'4.0.0'`.
- Ability names match `^[a-z0-9-]+/[a-z0-9-]+$` (exactly one slash, dashes only). The eight names are fixed: `pediment/get-vocabulary`, `pediment/list-pages`, `pediment/read-page`, `pediment/check-page`, `pediment/write-page`, `pediment/rollback-page`, `pediment/upsert-style`, `pediment/delete-style`. Category slug `pediment`. The spec's ninth tool, `pediment/convert-legacy`, belongs to Plan 4 (it needs the converter).
- Register categories only on `wp_abilities_api_categories_init` and abilities only on `wp_abilities_api_init` (core rejects anything else with `_doing_it_wrong`). Add those `add_action` calls at plugin load (`Bootstrap::register()`).
- `permission_callback` returns a plain `bool`, never `WP_Error` (core turns a WP_Error into `_doing_it_wrong`). Page abilities need `edit_pages` (and `edit_post` for an existing page, `publish_pages` to publish); style abilities need `edit_theme_options`.
- Never set `meta.public` or `meta.mcp.public`: abilities are exposed only through the Pediment MCP server's explicit list (no second route through the adapter's default server). Set `meta.show_in_rest => true` and honest `annotations`.
- The MCP Adapter shows a tool error's **message only** (code and data are dropped). Every `WP_Error` an ability returns must carry the actionable details (each rule violation) in its message.
- MCP Adapter is a soft dependency: install it as a plugin (wordpress.org slug `mcp-adapter`, v0.7.0, `https://downloads.wordpress.org/plugin/mcp-adapter.0.7.0.zip`); never bundle it via Composer. Pediment only hooks `mcp_adapter_init`.
- MCP server: id `pediment`, REST namespace `pediment/v1`, route `mcp` → `/wp-json/pediment/v1/mcp` (`/?rest_route=/pediment/v1/mcp` on plain permalinks). Tool names are ability names with `/` → `-` (`pediment-write-page`).
- Everything multilingual goes through `Pediment\Language\LanguageRegistry::provider()`; never call `pll_*` or WPML filters outside `plugin/src/Language/` (docs/STANDARDS.md).
- `client-kit` stays dependency-free at install time: no `package.json` in `client-kit/`; the WordPress editor stack is installed on first use into a cache keyed by WP line + lockfile hash. Node 20 is the floor.
- The root wp-env port is not fixed in this workspace (currently 55280) and permalinks are plain: get the site URL with `npx wp-env run cli wp option get siteurl` and use `?rest_route=` URLs. Tests: `npx wp-env run tests-wordpress --env-cwd=wp-content/plugins/pediment-ai ./vendor/bin/phpunit --filter <Name>`; Polylang suite: add `-c phpunit-polylang.xml.dist`. Lint: `composer lint -d plugin` (0 errors). Kit tests: `npm run test:kit`.
- npm: never run `npm install` with the local npm 11 in tracked package dirs; use `npx -y npm@10 …`. Stage files by name; conventional commits; never push.
- Playwright e2e cannot run in this Conductor workspace (the suite hard-codes theme slug `pediment`); browser checks are headless Playwright scripts against the dev site, reported in the task report.

## Review Focus

1. An author (or any user who cannot edit a given page) calling `write-page` with that page's `id` must be refused, even though they can edit their own pages — Task 6 pins it.
2. `write-page` must reject `core/html`, a raw `<script>` outside blocks, and literal design values for every role, including administrators — Tasks 3 and 6 pin it.
3. On a Polylang site, `list-pages` must return pages of every language (not only the request's current language), each with its language code — Task 5 pins it (Polylang suite).
4. When `write-page` or `upsert-style` rejects input, the MCP-visible error message must name each violation (the adapter drops `WP_Error` data) — Tasks 6 and 7 pin it.
5. `rollback-page` with a revision ID that belongs to a different page must be refused without changing either page — Task 6 pins it.

---

### Task 1: Close the dotted-key bypass in StyleValidator

The final review of Plans 1–2 found that `StyleValidator::strings()` and `leaves()` key their results by dotted path and merge with `+=`, so a decoy key such as `"typography.fontWeight"` hides the real nested `typography → fontWeight` leaf. A `<` then reaches the inline `<style>` (stored XSS, reproduced end to end).

**Files:**
- Modify: `plugin/src/Design/StyleValidator.php` (`validate()` loop over `strings()`, `strings()`, `validate_node()` loop over `leaves()`, `leaves()`)
- Test: `plugin/tests/phpunit/Design/StyleValidatorTest.php`, `plugin/tests/phpunit/Design/SiteStylesTest.php`

**Interfaces:**
- Consumes: nothing new.
- Produces: `strings()` and `leaves()` return `list<array{0: string, 1: mixed}>` (path, value pairs) instead of path-keyed maps. Public API of `StyleValidator` is unchanged.

- [ ] **Step 1: Write the failing tests**

Append to `plugin/tests/phpunit/Design/StyleValidatorTest.php` (inside the class; reuse its existing `spec()` / `errors()` helpers):

```php
	public function test_dotted_decoy_key_cannot_hide_a_tag(): void {
		$errors = $this->errors(
			$this->spec(
				array(
					'typography.fontWeight' => '700',
					'typography'            => array( 'fontWeight' => '700;}</style><script>alert(1)</script><style>' ),
				)
			)
		);
		$this->assertNotEmpty( array_filter( $errors, static fn( string $e ): bool => str_contains( $e, 'styles.typography.fontWeight: "<" is not allowed' ) ) );
	}

	public function test_dotted_decoy_key_cannot_hide_a_literal_value(): void {
		$errors = $this->errors(
			$this->spec(
				array(
					'spacing' => array(
						'padding' => array(
							'top.left' => 'var:preset|spacing|50',
							'top'      => array( 'left' => '78px' ),
						),
					),
				)
			)
		);
		$this->assertNotEmpty( array_filter( $errors, static fn( string $e ): bool => str_contains( $e, '78px' ) ) );
	}

	public function test_a_tag_in_a_key_is_rejected(): void {
		$errors = $this->errors( $this->spec( array( 'elements' => array( '</style><script>' => array( 'color' => array( 'text' => 'currentColor' ) ) ) ) ) );
		$this->assertNotEmpty( array_filter( $errors, static fn( string $e ): bool => str_contains( $e, '"<" is not allowed' ) ) );
	}
```

Append to `plugin/tests/phpunit/Design/SiteStylesTest.php` (inside the class):

```php
	public function test_decoy_key_payload_is_not_stored(): void {
		$result = $this->store->save(
			array(
				'slug'       => 'decoy',
				'label'      => 'Decoy',
				'blockTypes' => array( 'core/group' ),
				'styles'     => array(
					'typography.fontWeight' => '700',
					'typography'            => array( 'fontWeight' => '700;}</style><script>alert(1)</script><style>' ),
				),
			)
		);
		$this->assertWPError( $result );
		$this->assertNull( $this->store->get( 'decoy' ) );
	}
```

- [ ] **Step 2: Run the tests to verify they fail**

Run: `npx wp-env run tests-wordpress --env-cwd=wp-content/plugins/pediment-ai ./vendor/bin/phpunit --filter 'StyleValidatorTest|SiteStylesTest'`
Expected: FAIL — the decoy tests get no `<` / literal error and the store saves the payload.

- [ ] **Step 3: Make both collectors list-based**

In `plugin/src/Design/StyleValidator.php`, replace `strings()` with:

```php
	/**
	 * Every string leaf (and every string key containing "<") of a tree, as
	 * [path, value] pairs. A list, not a map keyed by path: keys may contain
	 * dots, so a decoy key such as "typography.fontWeight" would otherwise
	 * shadow the real nested leaf. Core prints style values into inline
	 * `<style>` tags, so nothing may open a tag.
	 *
	 * @param array  $tree Tree.
	 * @param string $path Path.
	 * @return list<array{0:string,1:string}>
	 */
	private function strings( array $tree, string $path ): array {
		$out = array();
		foreach ( $tree as $key => $value ) {
			if ( is_string( $key ) && str_contains( $key, '<' ) ) {
				$out[] = array( "{$path}.{$key}", $key );
			}
			if ( is_string( $value ) ) {
				$out[] = array( "{$path}.{$key}", $value );
			} elseif ( is_array( $value ) ) {
				array_push( $out, ...$this->strings( $value, "{$path}.{$key}" ) );
			}
		}
		return $out;
	}
```

and change its caller in `validate()` to:

```php
		foreach ( $this->strings( $styles, 'styles' ) as list( $leaf_path, $leaf ) ) {
			if ( str_contains( $leaf, '<' ) ) {
				$errors[] = "{$leaf_path}: \"<\" is not allowed";
			}
		}
```

Replace `leaves()` with:

```php
	/**
	 * Flattens a value into [path, scalar] pairs. A pure `{ref: "…"}` object
	 * counts as a token; in a mixed object the `ref` key is skipped. A list,
	 * not a map, for the same reason as strings().
	 *
	 * @param mixed  $value Value.
	 * @param string $path  Path.
	 * @return list<array{0:string,1:mixed}>
	 */
	private function leaves( $value, string $path ): array {
		if ( ! is_array( $value ) ) {
			return array( array( $path, $value ) );
		}
		if ( array_keys( $value ) === array( 'ref' ) && is_string( $value['ref'] ) && '' !== trim( $value['ref'] ) ) {
			return array();
		}
		$out = array();
		foreach ( $value as $key => $child ) {
			if ( 'ref' === $key && count( $value ) > 1 ) {
				continue;
			}
			array_push( $out, ...$this->leaves( $child, "{$path}.{$key}" ) );
		}
		return $out;
	}
```

and change its caller in `validate_node()` to:

```php
			foreach ( $this->leaves( $value, "{$path}.{$token_path}" ) as list( $leaf_path, $leaf ) ) {
```

(keep the loop body unchanged). Search the file for any other caller of `strings(` or `leaves(` and convert it the same way.

- [ ] **Step 4: Run the tests to verify they pass**

Run: `npx wp-env run tests-wordpress --env-cwd=wp-content/plugins/pediment-ai ./vendor/bin/phpunit --filter 'StyleValidatorTest|SiteStylesTest|DefaultStylesTest|SiteLayerTest|VocabularyTest|ExporterTest'`
Expected: PASS (all existing Design tests still green plus 4 new).

- [ ] **Step 5: Full suite, lint, commit**

```bash
npx wp-env run tests-wordpress --env-cwd=wp-content/plugins/pediment-ai ./vendor/bin/phpunit
composer lint -d plugin
git add plugin/src/Design/StyleValidator.php plugin/tests/phpunit/Design/StyleValidatorTest.php plugin/tests/phpunit/Design/SiteStylesTest.php
git commit -m "fix(design): stop dotted keys hiding style values from checks"
```

---

### Task 2: `languageOf()` on the language seam

The abilities must report each page's language. `LanguageProvider` has no getter for a post's language yet.

**Files:**
- Modify: `plugin/src/Language/LanguageProvider.php`, `PolylangProvider.php`, `WpmlProvider.php`, `NullProvider.php`
- Test: `plugin/tests/phpunit/Language/LanguageProviderTest.php`, `plugin/tests/polylang/PolylangProviderTest.php`, `plugin/tests/wpml/WpmlProviderReadTest.php`

**Interfaces:**
- Produces: `LanguageProvider::languageOf( int $postId ): string` — the post's language code, `''` when it has none or the site is monolingual.

- [ ] **Step 1: Write the failing tests**

`plugin/tests/phpunit/Language/LanguageProviderTest.php` (add to the class):

```php
	public function test_null_provider_language_of_is_empty(): void {
		$this->assertSame( '', ( new NullProvider() )->languageOf( 12345 ) );
	}
```

`plugin/tests/polylang/PolylangProviderTest.php` (add to the class):

```php
	public function test_language_of_returns_the_tagged_language() {
		$id = self::factory()->post->create( [ 'post_type' => 'page' ] );
		$this->provider->setLanguage( $id, 'de' );

		$this->assertSame( 'de', $this->provider->languageOf( $id ) );
	}

	public function test_language_of_an_untagged_post_is_empty() {
		$id = self::factory()->post->create( [ 'post_type' => 'page' ] );
		wp_delete_object_term_relationships( $id, 'language' );

		$this->assertSame( '', $this->provider->languageOf( $id ) );
	}
```

`plugin/tests/wpml/WpmlProviderReadTest.php` (add to the class; this suite runs only in CI's WPML job):

```php
	public function test_language_of_returns_the_assigned_language() {
		$id = self::factory()->post->create( [ 'post_type' => 'page' ] );
		( new WpmlProvider() )->setLanguage( $id, 'de' );

		$this->assertSame( 'de', ( new WpmlProvider() )->languageOf( $id ) );
	}

	public function test_language_of_a_post_without_assignment_is_empty() {
		$id = self::factory()->post->create( [ 'post_type' => 'page' ] );

		$this->assertSame( '', ( new WpmlProvider() )->languageOf( $id ) );
	}
```

- [ ] **Step 2: Run to verify failure**

Run: `npx wp-env run tests-wordpress --env-cwd=wp-content/plugins/pediment-ai ./vendor/bin/phpunit --filter LanguageProviderTest` and `npx wp-env run tests-wordpress --env-cwd=wp-content/plugins/pediment-ai ./vendor/bin/phpunit -c phpunit-polylang.xml.dist --filter PolylangProviderTest`
Expected: FAIL — `Call to undefined method …::languageOf()`.

- [ ] **Step 3: Implement**

`LanguageProvider.php` — add after `hasLanguage()`:

```php
	/** The post's language code; '' when it has none or the site is monolingual. */
	public function languageOf( int $postId ): string;
```

`NullProvider.php`:

```php
	public function languageOf( int $postId ): string {
		return '';
	}
```

`PolylangProvider.php` (next to `hasLanguage()`):

```php
	public function languageOf( int $postId ): string {
		if ( $postId <= 0 ) {
			return '';
		}
		$code = pll_get_post_language( $postId );
		return is_string( $code ) ? $code : '';
	}
```

`WpmlProvider.php` (next to `hasLanguage()`; same filter call `hasLanguage()` uses):

```php
	public function languageOf( int $postId ): string {
		if ( $postId <= 0 ) {
			return '';
		}
		$code = apply_filters(
			'wpml_element_language_code',
			null,
			[ 'element_id' => $postId, 'element_type' => 'post_' . get_post_type( $postId ) ]
		);
		return null === $code ? '' : (string) $code;
	}
```

- [ ] **Step 4: Run to verify they pass**

Run both commands from Step 2, then the full default suite.
Expected: PASS. (The WPML tests are verified by CI's `wpml` job; say so in the report.)

- [ ] **Step 5: Lint and commit**

```bash
composer lint -d plugin
git add plugin/src/Language/LanguageProvider.php plugin/src/Language/PolylangProvider.php plugin/src/Language/WpmlProvider.php plugin/src/Language/NullProvider.php plugin/tests/phpunit/Language/LanguageProviderTest.php plugin/tests/polylang/PolylangProviderTest.php plugin/tests/wpml/WpmlProviderReadTest.php
git commit -m "feat(language): read a post's language through the seam"
```

---

### Task 3: Shared token rule and the content Policy

**Files:**
- Create: `plugin/src/Design/TokenValue.php`
- Create: `plugin/src/Content/Policy.php`
- Modify: `plugin/src/Design/StyleValidator.php` (use `TokenValue`)
- Test: `plugin/tests/phpunit/Design/TokenValueTest.php`, `plugin/tests/phpunit/Content/PolicyTest.php`

**Interfaces:**
- Produces:
  - `Pediment\Design\TokenValue::isToken( mixed $value ): bool` and `TokenValue::leaves( mixed $value, string $path ): list<array{0:string,1:mixed}>` (moved from `StyleValidator`, identical behaviour).
  - `Pediment\Content\Policy` with constants `ALLOWED_CORE`, `FUNCTIONAL`, `LEGACY` (all `list<string>` of block names), `RULES` (`list<string>`), and methods `static allowedBlocks(): list<string>` (sorted), `check( string $markup ): array{errors: list<string>, warnings: list<string>}`, `legacyBlocksIn( string $markup ): list<string>`. Filter `pediment_allowed_blocks` (`list<string>` of core block names).

- [ ] **Step 1: Write the failing tests**

`plugin/tests/phpunit/Design/TokenValueTest.php`:

```php
<?php

use Pediment\Design\TokenValue;

class TokenValueTest extends WP_UnitTestCase {

	public function test_tokens_keywords_and_percentages(): void {
		foreach ( array( 'var:preset|spacing|50', 'var(--wp--preset--color--accent)', 'var(--wp--custom--radius--md)', '0', 0, 'inherit', 'currentColor', 'transparent', '50%' ) as $ok ) {
			$this->assertTrue( TokenValue::isToken( $ok ), wp_json_encode( $ok ) );
		}
		foreach ( array( '78px', '#fff', '1.2rem', 'red', 12, true, null ) as $bad ) {
			$this->assertFalse( TokenValue::isToken( $bad ), wp_json_encode( $bad ) );
		}
	}

	public function test_leaves_are_a_list_and_skip_pure_refs(): void {
		$this->assertSame(
			array( array( 'p.top', '1px' ), array( 'p.top.x', '2px' ) ),
			TokenValue::leaves( array( 'top' => '1px', 'top.x' => '2px', 'r' => array( 'ref' => 'styles.x' ) ), 'p' )
		);
	}
}
```

Note: `leaves()` on that input yields `p.top`, `p.top.x`, and nothing for the pure ref `r`.

`plugin/tests/phpunit/Content/PolicyTest.php`:

```php
<?php

use Pediment\Content\Policy;

class PolicyTest extends WP_UnitTestCase {

	private function check( string $markup ): array {
		return ( new Policy() )->check( $markup );
	}

	private function has( array $messages, string $needle ): bool {
		return array() !== array_filter( $messages, static fn( string $m ): bool => str_contains( $m, $needle ) );
	}

	public function test_clean_core_markup_passes(): void {
		$result = $this->check(
			"<!-- wp:group {\"metadata\":{\"name\":\"Intro\"},\"className\":\"is-style-band\",\"style\":{\"spacing\":{\"padding\":{\"top\":\"var:preset|spacing|50\"}}}} -->\n<div class=\"wp-block-group is-style-band\" style=\"padding-top:var(--wp--preset--spacing--50)\"><!-- wp:paragraph -->\n<p>Hi</p>\n<!-- /wp:paragraph --></div>\n<!-- /wp:group -->"
		);
		$this->assertSame( array(), $result['errors'] );
		$this->assertSame( array(), $result['warnings'] );
	}

	public function test_html_block_and_unregistered_blocks_are_errors(): void {
		$result = $this->check( "<!-- wp:html --><script>alert(1)</script><!-- /wp:html --><!-- wp:acme/nope /-->" );
		$this->assertTrue( $this->has( $result['errors'], 'core/html: block is not allowed' ) );
		$this->assertTrue( $this->has( $result['errors'], 'acme/nope: block type is not registered' ) );
	}

	public function test_loose_content_is_an_error(): void {
		$result = $this->check( "<script>alert(1)</script>\n<!-- wp:paragraph --><p>x</p><!-- /wp:paragraph -->" );
		$this->assertTrue( $this->has( $result['errors'], 'content outside a block' ) );
	}

	public function test_literal_design_values_are_errors(): void {
		$result = $this->check(
			'<!-- wp:group {"style":{"spacing":{"padding":{"top":"78px"}},"color":{"background":"#ffffff"},"border":{"radius":"22px"},"elements":{"link":{"color":{"text":"#a9621f"},":hover":{"color":{"text":"red"}}}}}} --><div class="wp-block-group"></div><!-- /wp:group -->'
		);
		foreach ( array( 'style.spacing.padding.top', 'style.color.background', 'style.border.radius', 'style.elements.link.color.text', 'style.elements.link.:hover.color.text' ) as $path ) {
			$this->assertTrue( $this->has( $result['errors'], $path ), $path );
		}
	}

	public function test_unknown_style_class_is_an_error_and_known_ones_pass(): void {
		$result = $this->check( '<!-- wp:group {"className":"is-style-nope"} --><div class="wp-block-group is-style-nope"></div><!-- /wp:group --><!-- wp:button {"className":"is-style-outline"} --><div class="wp-block-button is-style-outline"><a class="wp-block-button__link wp-element-button">Go</a></div><!-- /wp:button -->' );
		$this->assertTrue( $this->has( $result['errors'], 'is-style-nope is not a registered style for core/group' ) );
		$this->assertFalse( $this->has( $result['errors'], 'is-style-outline' ) );
	}

	public function test_bad_comment_json_is_an_error(): void {
		$result = $this->check( '<!-- wp:group {"a":} --><div class="wp-block-group"></div><!-- /wp:group -->' );
		$this->assertTrue( $this->has( $result['errors'], 'core/group: block comment attributes are not valid JSON' ) );
	}

	public function test_warnings_for_unnamed_containers_external_images_and_legacy_blocks(): void {
		$result = $this->check( '<!-- wp:group --><div class="wp-block-group"><!-- wp:image --><figure class="wp-block-image"><img src="https://elsewhere.example/a.jpg" alt=""/></figure><!-- /wp:image --></div><!-- /wp:group --><!-- wp:pediment/section-head /-->' );
		$this->assertSame( array(), $result['errors'] );
		$this->assertTrue( $this->has( $result['warnings'], 'core/group: give it a readable metadata.name' ) );
		$this->assertTrue( $this->has( $result['warnings'], 'elsewhere.example' ) );
		$this->assertTrue( $this->has( $result['warnings'], 'pediment/section-head: legacy block' ) );
	}

	public function test_allowed_blocks_filter_and_functional_blocks(): void {
		add_filter( 'pediment_allowed_blocks', static fn( array $core ): array => array_merge( $core, array( 'core/table' ) ) );
		$allowed = Policy::allowedBlocks();
		$this->assertContains( 'core/table', $allowed );
		$this->assertContains( 'core/paragraph', $allowed );
		$this->assertContains( 'pediment/form', $allowed );
		$this->assertNotContains( 'core/html', $allowed );
		$this->assertNotContains( 'pediment/hero', $allowed );
	}

	public function test_legacy_blocks_in_markup(): void {
		$this->assertSame(
			array( 'pediment/faq', 'pediment/hero' ),
			( new Policy() )->legacyBlocksIn( '<!-- wp:pediment/hero /--><!-- wp:group --><div class="wp-block-group"><!-- wp:pediment/faq /--></div><!-- /wp:group --><!-- wp:pediment/hero /-->' )
		);
	}
}
```

- [ ] **Step 2: Run to verify failure**

Run: `npx wp-env run tests-wordpress --env-cwd=wp-content/plugins/pediment-ai ./vendor/bin/phpunit --filter 'TokenValueTest|PolicyTest'`
Expected: FAIL — classes not found.

- [ ] **Step 3: Write `TokenValue`**

`plugin/src/Design/TokenValue.php`:

```php
<?php
/**
 * The token rule shared by the design layer and the content Policy.
 *
 * @package Pediment
 */

declare(strict_types=1);

namespace Pediment\Design;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Decides whether a style value is a design token.
 */
final class TokenValue {

	private const KEYWORDS = array( '0', 'none', 'inherit', 'initial', 'unset', 'transparent', 'currentcolor' );

	/**
	 * Whether a scalar is an allowed token value: a preset/custom variable,
	 * `0`, a CSS-wide keyword, `transparent`, `currentColor` or a percentage.
	 *
	 * @param mixed $value Value.
	 */
	public static function isToken( $value ): bool {
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
	 * Flattens a value into [path, scalar] pairs (a list: keys may contain
	 * dots). A pure `{ref: "…"}` object is a token; in a mixed object the
	 * `ref` key is skipped.
	 *
	 * @param mixed  $value Value.
	 * @param string $path  Path.
	 * @return list<array{0:string,1:mixed}>
	 */
	public static function leaves( $value, string $path ): array {
		if ( ! is_array( $value ) ) {
			return array( array( $path, $value ) );
		}
		if ( array_keys( $value ) === array( 'ref' ) && is_string( $value['ref'] ) && '' !== trim( $value['ref'] ) ) {
			return array();
		}
		$out = array();
		foreach ( $value as $key => $child ) {
			if ( 'ref' === $key && count( $value ) > 1 ) {
				continue;
			}
			array_push( $out, ...self::leaves( $child, "{$path}.{$key}" ) );
		}
		return $out;
	}
}
```

In `plugin/src/Design/StyleValidator.php`: delete the private `leaves()` and `is_token_value()` methods, call `TokenValue::leaves(...)` and `TokenValue::isToken(...)` instead, and delete the `KEYWORDS` constant only if nothing else in the file uses it (check with a search).

- [ ] **Step 4: Write `Policy`**

`plugin/src/Content/Policy.php`:

```php
<?php
/**
 * Content rules every page write must pass (spec §3).
 *
 * @package Pediment
 */

declare(strict_types=1);

namespace Pediment\Content;

use Pediment\Design\TokenValue;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Checks serialized block markup. Errors reject a write; warnings are returned.
 */
final class Policy {

	public const ALLOWED_CORE = array(
		'core/group',
		'core/columns',
		'core/column',
		'core/cover',
		'core/image',
		'core/gallery',
		'core/heading',
		'core/paragraph',
		'core/list',
		'core/list-item',
		'core/buttons',
		'core/button',
		'core/details',
		'core/quote',
		'core/separator',
		'core/spacer',
		'core/media-text',
		'core/embed',
	);

	public const FUNCTIONAL = array(
		'pediment/form',
		'pediment/form-field',
		'pediment/slider',
		'pediment/mega-menu',
		'pediment/blog-index',
		'pediment/social-links',
	);

	public const LEGACY = array(
		'pediment/hero',
		'pediment/cta',
		'pediment/feature',
		'pediment/feature-grid',
		'pediment/stat',
		'pediment/stat-grid',
		'pediment/step',
		'pediment/steps',
		'pediment/section-head',
		'pediment/media-text',
		'pediment/testimonial',
		'pediment/testimonial-grid',
		'pediment/logo-cloud',
		'pediment/pull-quote',
		'pediment/image-caption',
		'pediment/prose',
		'pediment/faq',
		'pediment/faq-item',
	);

	public const RULES = array(
		'Use only the blocks in allowedBlocks. core/html and content outside blocks are not allowed.',
		'Never put literal colours, font sizes, font families, spacing, radius or shadows in block attributes. Use presets (var:preset|…) or a named style (className is-style-<slug>).',
		'Every is-style-<slug> class must name a style registered for that block (see styles).',
		'Give container blocks (group, columns, cover) a readable metadata.name.',
		'Static blocks must match their save() output: run the client-kit validate-blocks tool before writing.',
	);

	private const CONTAINERS = array( 'core/group', 'core/columns', 'core/cover' );

	private const STYLE_TOKEN_PATHS = array(
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

	private const BLOCK_COMMENT = '/<!--\s+(\/)?wp:([a-z][a-z0-9_-]*(?:\/[a-z][a-z0-9_-]*)?)(?:\s+(\{.*?\}))?\s+(\/)?-->/s';

	/**
	 * Blocks a page may use: the (filterable) core list, Pediment's functional
	 * blocks, and every registered block outside the core and pediment
	 * namespaces (client-theme and plugin blocks).
	 *
	 * @return list<string>
	 */
	public static function allowedBlocks(): array {
		$core  = (array) apply_filters( 'pediment_allowed_blocks', self::ALLOWED_CORE );
		$other = array();
		foreach ( array_keys( \WP_Block_Type_Registry::get_instance()->get_all_registered() ) as $name ) {
			$namespace = strstr( $name, '/', true );
			if ( 'core' !== $namespace && 'pediment' !== $namespace ) {
				$other[] = $name;
			}
		}
		$list = array_values( array_unique( array_map( 'strval', array_merge( $core, self::FUNCTIONAL, $other ) ) ) );
		sort( $list );
		return $list;
	}

	/**
	 * Checks markup.
	 *
	 * @param string $markup Serialized blocks.
	 * @return array{errors:list<string>,warnings:list<string>}
	 */
	public function check( string $markup ): array {
		// Registers theme style-variation names (core does it while building theme data).
		\WP_Theme_JSON_Resolver::get_theme_data();

		$errors   = $this->comment_json_errors( $markup );
		$warnings = array();
		$this->walk( parse_blocks( $markup ), 'blocks', self::allowedBlocks(), $errors, $warnings );
		foreach ( $this->external_image_hosts( $markup ) as $host ) {
			$warnings[] = "image from external host {$host} — upload it to the media library";
		}

		return array(
			'errors'   => array_values( array_unique( $errors ) ),
			'warnings' => array_values( array_unique( $warnings ) ),
		);
	}

	/**
	 * Legacy Pediment block names used in markup, sorted and unique.
	 *
	 * @param string $markup Serialized blocks.
	 * @return list<string>
	 */
	public function legacyBlocksIn( string $markup ): array {
		$found = array();
		$visit = static function ( array $blocks ) use ( &$visit, &$found ): void {
			foreach ( $blocks as $block ) {
				if ( in_array( $block['blockName'], self::LEGACY, true ) ) {
					$found[] = $block['blockName'];
				}
				$visit( $block['innerBlocks'] ?? array() );
			}
		};
		$visit( parse_blocks( $markup ) );
		$found = array_values( array_unique( $found ) );
		sort( $found );
		return $found;
	}

	/**
	 * Recursive block walk.
	 *
	 * @param array        $blocks   Parsed blocks.
	 * @param string       $path     Path prefix.
	 * @param list<string> $allowed  Allowed block names.
	 * @param list<string> $errors   Errors (by reference).
	 * @param list<string> $warnings Warnings (by reference).
	 */
	private function walk( array $blocks, string $path, array $allowed, array &$errors, array &$warnings ): void {
		foreach ( $blocks as $i => $block ) {
			$name = $block['blockName'];
			if ( null === $name ) {
				if ( '' !== trim( (string) $block['innerHTML'] ) ) {
					$errors[] = "{$path}[{$i}]: content outside a block — wrap it in a block";
				}
				continue;
			}
			$here = "{$path}[{$i}] {$name}";

			if ( in_array( $name, self::LEGACY, true ) ) {
				$warnings[] = "{$here}: legacy block — it will be converted to core blocks";
			} elseif ( ! in_array( $name, $allowed, true ) ) {
				$errors[] = \WP_Block_Type_Registry::get_instance()->is_registered( $name )
					? "{$here}: block is not allowed"
					: "{$here}: block type is not registered";
			}

			$attrs = is_array( $block['attrs'] ?? null ) ? $block['attrs'] : array();
			foreach ( $this->literal_values( $attrs['style'] ?? array(), 'style' ) as list( $attr_path, $value ) ) {
				$errors[] = "{$here}: attrs.{$attr_path} has the literal value " . wp_json_encode( $value ) . ' — use a preset or a named style';
			}
			foreach ( $this->unknown_styles( $name, (string) ( $attrs['className'] ?? '' ) ) as $slug ) {
				$errors[] = "{$here}: is-style-{$slug} is not a registered style for {$name}";
			}
			if ( in_array( $name, self::CONTAINERS, true ) && '' === trim( (string) ( $attrs['metadata']['name'] ?? '' ) ) ) {
				$warnings[] = "{$here}: give it a readable metadata.name";
			}

			$this->walk( $block['innerBlocks'] ?? array(), "{$here} > ", $allowed, $errors, $warnings );
		}
	}

	/**
	 * Literal design values in a block's `style` attribute.
	 *
	 * @param mixed  $style Style attribute (or an element node).
	 * @param string $path  Path.
	 * @return list<array{0:string,1:mixed}>
	 */
	private function literal_values( $style, string $path ): array {
		if ( ! is_array( $style ) ) {
			return array();
		}
		$out = array();
		foreach ( self::STYLE_TOKEN_PATHS as $token_path ) {
			$value = _wp_array_get( $style, explode( '.', $token_path ), null );
			if ( null === $value ) {
				continue;
			}
			foreach ( TokenValue::leaves( $value, "{$path}.{$token_path}" ) as list( $leaf_path, $leaf ) ) {
				if ( ! TokenValue::isToken( $leaf ) ) {
					$out[] = array( $leaf_path, $leaf );
				}
			}
		}
		$elements = $style['elements'] ?? array();
		foreach ( is_array( $elements ) ? $elements : array() as $element => $node ) {
			if ( ! is_array( $node ) ) {
				continue;
			}
			array_push( $out, ...$this->literal_values( $node, "{$path}.elements.{$element}" ) );
			foreach ( $node as $key => $state ) {
				if ( is_string( $key ) && str_starts_with( $key, ':' ) ) {
					array_push( $out, ...$this->literal_values( $state, "{$path}.elements.{$element}.{$key}" ) );
				}
			}
		}
		return $out;
	}

	/**
	 * `is-style-*` slugs in a class list that are not registered for the block.
	 *
	 * @param string $block      Block name.
	 * @param string $class_name className attribute.
	 * @return list<string>
	 */
	private function unknown_styles( string $block, string $class_name ): array {
		if ( ! preg_match_all( '/(?:^|\s)is-style-([a-z0-9-]+)/', $class_name, $matches ) ) {
			return array();
		}
		$known = array_keys( \WP_Block_Styles_Registry::get_instance()->get_registered_styles_for_block( $block ) );
		$type  = \WP_Block_Type_Registry::get_instance()->get_registered( $block );
		if ( $type && is_array( $type->styles ) ) {
			foreach ( $type->styles as $style ) {
				if ( isset( $style['name'] ) ) {
					$known[] = (string) $style['name'];
				}
			}
		}
		$known[] = 'default';
		return array_values( array_diff( $matches[1], $known ) );
	}

	/**
	 * Block comments whose attribute JSON does not decode (core's parser drops it silently).
	 *
	 * @param string $markup Markup.
	 * @return list<string>
	 */
	private function comment_json_errors( string $markup ): array {
		$errors = array();
		if ( ! preg_match_all( self::BLOCK_COMMENT, $markup, $matches, PREG_SET_ORDER ) ) {
			return $errors;
		}
		foreach ( $matches as $match ) {
			if ( '' !== $match[1] || empty( $match[3] ) ) {
				continue;
			}
			json_decode( $match[3] );
			if ( JSON_ERROR_NONE !== json_last_error() ) {
				$name     = str_contains( $match[2], '/' ) ? $match[2] : 'core/' . $match[2];
				$errors[] = "{$name}: block comment attributes are not valid JSON";
			}
		}
		return $errors;
	}

	/**
	 * Hosts of `<img src>` URLs that are not this site.
	 *
	 * @param string $markup Markup.
	 * @return list<string>
	 */
	private function external_image_hosts( string $markup ): array {
		$site = wp_parse_url( home_url(), PHP_URL_HOST );
		if ( ! preg_match_all( '/<img\b[^>]*\bsrc="([^"]+)"/i', $markup, $matches ) ) {
			return array();
		}
		$hosts = array();
		foreach ( $matches[1] as $src ) {
			$host = wp_parse_url( html_entity_decode( $src ), PHP_URL_HOST );
			if ( is_string( $host ) && $host !== $site ) {
				$hosts[] = $host;
			}
		}
		return array_values( array_unique( $hosts ) );
	}
}
```

- [ ] **Step 5: Run to verify they pass**

Run: `npx wp-env run tests-wordpress --env-cwd=wp-content/plugins/pediment-ai ./vendor/bin/phpunit --filter 'TokenValueTest|PolicyTest|StyleValidatorTest'`
Expected: PASS. If `test_unknown_style_class_is_an_error_and_known_ones_pass` fails because `outline` is not registered for `core/button` server-side, check `WP_Block_Type_Registry::get_instance()->get_registered( 'core/button' )->styles` in the container and report what core provides; do not weaken the test without saying so.

- [ ] **Step 6: Full suite, lint, commit**

```bash
npx wp-env run tests-wordpress --env-cwd=wp-content/plugins/pediment-ai ./vendor/bin/phpunit
composer lint -d plugin
git add plugin/src/Design/TokenValue.php plugin/src/Design/StyleValidator.php plugin/src/Content/Policy.php plugin/tests/phpunit/Design/TokenValueTest.php plugin/tests/phpunit/Content/PolicyTest.php
git commit -m "feat(content): add the content policy for page writes"
```

---

### Task 4: Invalid-block log (editor safety net)

**Files:**
- Create: `plugin/src/Content/InvalidBlockLog.php`
- Create: `plugin/assets/js/invalid-blocks.js`
- Modify: `plugin/src/Bootstrap.php` (`register()`: one line)
- Test: `plugin/tests/phpunit/Content/InvalidBlockLogTest.php`

**Interfaces:**
- Produces: `Pediment\Content\InvalidBlockLog` with `META = '_pediment_invalid_blocks'`, `static register(): void`, `static registerRoute(): void`, `static enqueue(): void`, `static record( int $postId, array $blocks ): array{checkedAt: string, blocks: list<string>}`, `static get( int $postId ): ?array`, `static clear( int $postId ): void`. REST: `POST /pediment/v1/invalid-blocks/<id>` with JSON `{ "blocks": ["core/group", …] }`.

- [ ] **Step 1: Write the failing test**

`plugin/tests/phpunit/Content/InvalidBlockLogTest.php`:

```php
<?php

use Pediment\Content\InvalidBlockLog;

class InvalidBlockLogTest extends WP_UnitTestCase {

	private function post( int $page_id, array $body ): WP_REST_Response {
		$request = new WP_REST_Request( 'POST', '/pediment/v1/invalid-blocks/' . $page_id );
		$request->set_header( 'Content-Type', 'application/json' );
		$request->set_body( wp_json_encode( $body ) );
		return rest_do_request( $request );
	}

	public function test_editor_can_record_and_get_reads_it(): void {
		$page = self::factory()->post->create( array( 'post_type' => 'page' ) );
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'editor' ) ) );

		$response = $this->post( $page, array( 'blocks' => array( 'core/group', 'core/group', 'core/cover' ) ) );

		$this->assertSame( 200, $response->get_status() );
		$this->assertSame( array( 'core/group', 'core/cover' ), InvalidBlockLog::get( $page )['blocks'] );
		$this->assertNotEmpty( InvalidBlockLog::get( $page )['checkedAt'] );
	}

	public function test_empty_list_is_recorded_and_clear_removes_it(): void {
		$page = self::factory()->post->create( array( 'post_type' => 'page' ) );
		InvalidBlockLog::record( $page, array() );
		$this->assertSame( array(), InvalidBlockLog::get( $page )['blocks'] );
		InvalidBlockLog::clear( $page );
		$this->assertNull( InvalidBlockLog::get( $page ) );
	}

	public function test_subscriber_and_bad_input_are_refused(): void {
		$page = self::factory()->post->create( array( 'post_type' => 'page' ) );
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'subscriber' ) ) );
		$this->assertSame( 403, $this->post( $page, array( 'blocks' => array() ) )->get_status() );

		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		$this->assertSame( 400, $this->post( $page, array( 'blocks' => array( 'Not A Block' ) ) )->get_status() );

		$post = self::factory()->post->create( array( 'post_type' => 'post' ) );
		$this->assertSame( 404, $this->post( $post, array( 'blocks' => array() ) )->get_status() );
	}

	public function test_editor_script_is_enqueued(): void {
		do_action( 'enqueue_block_editor_assets' );
		$this->assertTrue( wp_script_is( 'pediment-invalid-blocks', 'enqueued' ) );
	}
}
```

- [ ] **Step 2: Run to verify failure**

Run: `npx wp-env run tests-wordpress --env-cwd=wp-content/plugins/pediment-ai ./vendor/bin/phpunit --filter InvalidBlockLogTest`
Expected: FAIL — class/route missing.

- [ ] **Step 3: Write the class**

`plugin/src/Content/InvalidBlockLog.php`:

```php
<?php
/**
 * Editor-side safety net: the block editor reports blocks it could not
 * validate on a page; the check-page ability reads the report.
 *
 * @package Pediment
 */

declare(strict_types=1);

namespace Pediment\Content;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Stores the last editor validity report per page in post meta.
 */
final class InvalidBlockLog {

	public const META = '_pediment_invalid_blocks';

	/**
	 * Hooks the REST route and the editor script.
	 */
	public static function register(): void {
		add_action( 'rest_api_init', array( self::class, 'registerRoute' ) );
		add_action( 'enqueue_block_editor_assets', array( self::class, 'enqueue' ) );
	}

	/**
	 * POST /pediment/v1/invalid-blocks/<id>.
	 */
	public static function registerRoute(): void {
		register_rest_route(
			'pediment/v1',
			'/invalid-blocks/(?P<id>\d+)',
			array(
				'methods'             => 'POST',
				'permission_callback' => static fn( \WP_REST_Request $request ): bool => current_user_can( 'edit_post', (int) $request['id'] ),
				'args'                => array(
					'blocks' => array(
						'type'     => 'array',
						'required' => true,
						'maxItems' => 500,
						'items'    => array(
							'type'    => 'string',
							'pattern' => '^[a-z0-9-]+/[a-z0-9-]+$',
						),
					),
				),
				'callback'            => static function ( \WP_REST_Request $request ) {
					$id = (int) $request['id'];
					if ( 'page' !== get_post_type( $id ) ) {
						return new \WP_Error( 'pediment_not_a_page', __( 'Not a page.', 'pediment' ), array( 'status' => 404 ) );
					}
					return rest_ensure_response( self::record( $id, (array) $request['blocks'] ) );
				},
			)
		);
	}

	/**
	 * Enqueues the reporter in the block editor.
	 */
	public static function enqueue(): void {
		$rel = 'assets/js/invalid-blocks.js';
		wp_enqueue_script(
			'pediment-invalid-blocks',
			PEDIMENT_AI_PLUGIN_URL . $rel,
			array( 'wp-data', 'wp-api-fetch', 'wp-editor', 'wp-block-editor' ),
			(string) filemtime( PEDIMENT_AI_PLUGIN_DIR . '/' . $rel ),
			true
		);
	}

	/**
	 * Stores a report.
	 *
	 * @param int          $post_id Page ID.
	 * @param list<string> $blocks  Invalid block names.
	 * @return array{checkedAt:string,blocks:list<string>}
	 */
	public static function record( int $post_id, array $blocks ): array {
		$entry = array(
			'checkedAt' => gmdate( 'c' ),
			'blocks'    => array_values( array_unique( array_map( 'strval', $blocks ) ) ),
		);
		update_post_meta( $post_id, self::META, $entry );
		return $entry;
	}

	/**
	 * The last report, or null when none exists.
	 *
	 * @param int $post_id Page ID.
	 */
	public static function get( int $post_id ): ?array {
		$entry = get_post_meta( $post_id, self::META, true );
		return is_array( $entry ) ? $entry : null;
	}

	/**
	 * Removes the report (content changed, so it is stale).
	 *
	 * @param int $post_id Page ID.
	 */
	public static function clear( int $post_id ): void {
		delete_post_meta( $post_id, self::META );
	}
}
```

- [ ] **Step 4: Write the editor script**

`plugin/assets/js/invalid-blocks.js` (plain script, no build step, like `assets/js/layout-variations.js`):

```js
/**
 * Reports blocks the editor could not validate on this page to
 * POST /pediment/v1/invalid-blocks/<id>, once per editor load, so AI agents
 * see them through the pediment/check-page ability. An empty list is sent
 * too: it replaces an earlier report once the page is fixed.
 */
( function ( wp ) {
	if ( ! wp || ! wp.data || ! wp.apiFetch ) {
		return;
	}
	var done = false;
	var unsubscribe = wp.data.subscribe( function () {
		if ( done ) {
			return;
		}
		var editor = wp.data.select( 'core/editor' );
		if ( ! editor || ! editor.__unstableIsEditorReady || ! editor.__unstableIsEditorReady() ) {
			return;
		}
		done = true;
		unsubscribe();
		var id = editor.getCurrentPostId();
		if ( ! id || 'page' !== editor.getCurrentPostType() ) {
			return;
		}
		var invalid = [];
		( function walk( blocks ) {
			blocks.forEach( function ( block ) {
				if ( false === block.isValid ) {
					invalid.push( block.name );
				}
				walk( block.innerBlocks || [] );
			} );
		} )( wp.data.select( 'core/block-editor' ).getBlocks() );
		wp.apiFetch( {
			path: '/pediment/v1/invalid-blocks/' + id,
			method: 'POST',
			data: { blocks: invalid },
		} ).catch( function () {} );
	} );
} )( window.wp );
```

In `plugin/src/Bootstrap.php` `register()`, append:

```php
		\Pediment\Content\InvalidBlockLog::register();
```

- [ ] **Step 5: Run to verify they pass**

Run: `npx wp-env run tests-wordpress --env-cwd=wp-content/plugins/pediment-ai ./vendor/bin/phpunit --filter InvalidBlockLogTest`
Expected: PASS (4 tests).

- [ ] **Step 6: Headless editor check**

Create a page with one invalid block, open it headlessly, read the meta:

```bash
npx wp-env run cli wp post create --post_type=page --post_status=draft --post_title="Invalid probe" --post_content='<!-- wp:group --><div class="wp-block-nope"><!-- wp:paragraph --><p>x</p><!-- /wp:paragraph --></div><!-- /wp:group -->' --porcelain
```

(This is a throwaway probe page on the dev env, not seeded client content.) With headless Playwright from `plugin/node_modules`, log in (admin/password at the site URL from `wp option get siteurl`), open `/wp-admin/post.php?post=<id>&action=edit`, wait until `wp.data.select('core/editor').__unstableIsEditorReady()` is true plus 2 s, then run `npx wp-env run cli wp post meta get <id> _pediment_invalid_blocks --format=json`.
Expected: `{"checkedAt":"…","blocks":["core/group"]}`. If `__unstableIsEditorReady` does not exist in this WordPress, report it and switch the readiness check to "`getBlocks().length > 0` or the post content is empty", then re-run. Delete the probe page afterwards: `npx wp-env run cli wp post delete <id> --force`.

- [ ] **Step 7: Lint and commit**

```bash
composer lint -d plugin
npm run lint:js
git add plugin/src/Content/InvalidBlockLog.php plugin/assets/js/invalid-blocks.js plugin/src/Bootstrap.php plugin/tests/phpunit/Content/InvalidBlockLogTest.php
git commit -m "feat(content): editor reports invalid blocks per page"
```

(If `npm run lint:js` does not cover `plugin/assets/js/`, say so in the report; do not change the lint config.)

---

### Task 5: Abilities framework and the read abilities

**Files:**
- Create: `plugin/src/Abilities/Ability.php`, `Registrar.php`, `PageData.php`, `GetVocabulary.php`, `ListPages.php`, `ReadPage.php`, `CheckPage.php`
- Modify: `plugin/src/Bootstrap.php` (`register()`: one line)
- Test: `plugin/tests/phpunit/Abilities/AbilityTestCase.php`, `plugin/tests/phpunit/Abilities/ReadAbilitiesTest.php`, `plugin/tests/polylang/AbilitiesLanguageTest.php`

**Interfaces:**
- Consumes: `Policy` (Task 3), `InvalidBlockLog::get()` (Task 4), `LanguageProvider::languageOf()` (Task 2), `Pediment\Design\Vocabulary::styles()`, `SiteStyles`, `StyleValidator` (Plan 2).
- Produces:
  - `abstract class Pediment\Abilities\Ability` with `abstract public function name(): string`, `abstract public function permission( $input ): bool`, `abstract public function execute( $input )` (returns `array|\WP_Error`), protected `label()`, `description()`, `inputSchema()`, `outputSchema()`, `annotations()`, and `public function args(): array` (the `wp_register_ability()` args).
  - `final class Pediment\Abilities\Registrar` with `CATEGORY = 'pediment'`, `static abilities(): list<Ability>`, `static names(): list<string>`, `static register(): void`, `static registerCategory(): void`, `static registerAbilities(): void`.
  - `final class Pediment\Abilities\PageData` with `static summary( \WP_Post $post ): array{id:int,title:string,slug:string,parent:int,status:string,language:string,link:string,modified:string}` and `static full( \WP_Post $post ): array` (summary + `translations` (code → id), `markup`, `revisions`).
  - Ability outputs: `get-vocabulary` → `{wordpress, tokens:{colors,fontSizes,fontFamilies,spacing,shadows,radius}, styles, allowedBlocks, rules, languages, defaultLanguage}`; `list-pages` → `{pages: list<summary>}`; `read-page` → full page; `check-page` → `{errors, warnings, invalidBlocks: ?{checkedAt, blocks}, legacyBlocks}`.

- [ ] **Step 1: Write the test base and failing tests**

`plugin/tests/phpunit/Abilities/AbilityTestCase.php` (not a test itself; required by the ability tests):

```php
<?php

/**
 * Resets the Abilities API registries around each test so the plugin's
 * load-time hooks re-register its abilities through the real init actions.
 */
abstract class AbilityTestCase extends WP_UnitTestCase {

	public function set_up(): void {
		parent::set_up();
		$this->reset_registries();
	}

	public function tear_down(): void {
		$this->reset_registries();
		parent::tear_down();
	}

	private function reset_registries(): void {
		foreach ( array( 'WP_Abilities_Registry', 'WP_Ability_Categories_Registry' ) as $class ) {
			$prop = new ReflectionProperty( $class, 'instance' );
			$prop->setAccessible( true );
			$prop->setValue( null, null );
		}
	}

	protected function user( string $role ): int {
		return self::factory()->user->create( array( 'role' => $role ) );
	}

	/**
	 * Runs an ability as a user.
	 *
	 * @param int    $user  User ID.
	 * @param string $name  Ability name.
	 * @param mixed  $input Input.
	 * @return mixed|WP_Error
	 */
	protected function run_as( int $user, string $name, $input = null ) {
		wp_set_current_user( $user );
		return wp_get_ability( $name )->execute( $input );
	}
}
```

`plugin/tests/phpunit/Abilities/ReadAbilitiesTest.php`:

```php
<?php

require_once __DIR__ . '/AbilityTestCase.php';

use Pediment\Abilities\Registrar;
use Pediment\Content\InvalidBlockLog;

class ReadAbilitiesTest extends AbilityTestCase {

	public function test_category_and_read_abilities_are_registered_without_public_mcp_flag(): void {
		$this->assertTrue( wp_has_ability_category( 'pediment' ) );
		foreach ( array( 'pediment/get-vocabulary', 'pediment/list-pages', 'pediment/read-page', 'pediment/check-page' ) as $name ) {
			$this->assertTrue( wp_has_ability( $name ), $name );
			$meta = wp_get_ability( $name )->get_meta();
			$this->assertTrue( $meta['show_in_rest'] );
			$this->assertTrue( $meta['annotations']['readonly'] );
			$this->assertArrayNotHasKey( 'mcp', $meta );
			$this->assertArrayNotHasKey( 'public', $meta );
		}
		$this->assertContains( 'pediment/check-page', Registrar::names() );
	}

	public function test_get_vocabulary(): void {
		$result = $this->run_as( $this->user( 'editor' ), 'pediment/get-vocabulary', array() );

		$this->assertIsArray( $result );
		$this->assertSame( get_bloginfo( 'version' ), $result['wordpress'] );
		$this->assertContains( 'card', array_column( $result['styles'], 'slug' ) );
		$this->assertContains( 'core/paragraph', $result['allowedBlocks'] );
		$this->assertNotEmpty( $result['tokens']['colors'] );
		$this->assertSame( '12px', $result['tokens']['radius']['md'] );
		$this->assertNotEmpty( $result['rules'] );
	}

	public function test_subscriber_cannot_read(): void {
		$result = $this->run_as( $this->user( 'subscriber' ), 'pediment/get-vocabulary', array() );
		$this->assertWPError( $result );
		$this->assertSame( 'ability_invalid_permissions', $result->get_error_code() );
	}

	public function test_list_and_read_pages(): void {
		$parent = self::factory()->post->create( array( 'post_type' => 'page', 'post_title' => 'Parent', 'post_status' => 'publish' ) );
		$child  = self::factory()->post->create( array( 'post_type' => 'page', 'post_title' => 'Child', 'post_parent' => $parent, 'post_status' => 'draft', 'post_content' => '<!-- wp:paragraph --><p>x</p><!-- /wp:paragraph -->' ) );
		$editor = $this->user( 'editor' );

		$all = $this->run_as( $editor, 'pediment/list-pages', array() );
		$this->assertEqualsCanonicalizing( array( $parent, $child ), array_column( $all['pages'], 'id' ) );

		$children = $this->run_as( $editor, 'pediment/list-pages', array( 'parent' => $parent ) );
		$this->assertSame( array( $child ), array_column( $children['pages'], 'id' ) );

		$page = $this->run_as( $editor, 'pediment/read-page', array( 'id' => $child ) );
		$this->assertSame( 'Child', $page['title'] );
		$this->assertSame( $parent, $page['parent'] );
		$this->assertSame( 'draft', $page['status'] );
		$this->assertStringContainsString( '<p>x</p>', $page['markup'] );
	}

	public function test_read_page_refuses_posts_and_missing_ids(): void {
		$post = self::factory()->post->create( array( 'post_type' => 'post' ) );
		$this->assertWPError( $this->run_as( $this->user( 'administrator' ), 'pediment/read-page', array( 'id' => $post ) ) );
		$this->assertWPError( $this->run_as( $this->user( 'administrator' ), 'pediment/read-page', array( 'id' => 999999 ) ) );
	}

	public function test_check_page_reports_rules_invalid_blocks_and_legacy_blocks(): void {
		$page = self::factory()->post->create(
			array(
				'post_type'    => 'page',
				'post_content' => '<!-- wp:group {"style":{"spacing":{"padding":{"top":"78px"}}}} --><div class="wp-block-group" style="padding-top:78px"></div><!-- /wp:group --><!-- wp:pediment/hero /-->',
			)
		);
		InvalidBlockLog::record( $page, array( 'core/group' ) );

		$result = $this->run_as( $this->user( 'editor' ), 'pediment/check-page', array( 'id' => $page ) );

		$this->assertNotEmpty( array_filter( $result['errors'], static fn( string $e ): bool => str_contains( $e, '78px' ) ) );
		$this->assertSame( array( 'core/group' ), $result['invalidBlocks']['blocks'] );
		$this->assertSame( array( 'pediment/hero' ), $result['legacyBlocks'] );
	}
}
```

`plugin/tests/polylang/AbilitiesLanguageTest.php` (Polylang suite; check `plugin/tests/polylang/` for the base class name — `PolylangTestCase` — and how other tests there create users):

```php
<?php

require_once dirname( __DIR__ ) . '/phpunit/Abilities/AbilityTestCase.php';

/**
 * list-pages must see every language, not only the request's current one.
 */
class AbilitiesLanguageTest extends PolylangTestCase {

	public function set_up(): void {
		parent::set_up();
		foreach ( array( 'WP_Abilities_Registry', 'WP_Ability_Categories_Registry' ) as $class ) {
			$prop = new ReflectionProperty( $class, 'instance' );
			$prop->setAccessible( true );
			$prop->setValue( null, null );
		}
	}

	public function test_list_pages_returns_every_language_with_its_code() {
		$provider = \Pediment\Language\LanguageRegistry::provider();
		$en       = self::factory()->post->create( [ 'post_type' => 'page', 'post_status' => 'publish' ] );
		$de       = self::factory()->post->create( [ 'post_type' => 'page', 'post_status' => 'publish' ] );
		$provider->setLanguage( $en, 'en' );
		$provider->setLanguage( $de, 'de' );
		$provider->linkTranslations( [ 'en' => $en, 'de' => $de ] );

		wp_set_current_user( self::factory()->user->create( [ 'role' => 'editor' ] ) );
		$result = wp_get_ability( 'pediment/list-pages' )->execute( [] );

		$languages = array_column( $result['pages'], 'language', 'id' );
		$this->assertSame( 'en', $languages[ $en ] );
		$this->assertSame( 'de', $languages[ $de ] );

		$page = wp_get_ability( 'pediment/read-page' )->execute( [ 'id' => $en ] );
		$this->assertSame( [ 'de' => $de ], $page['translations'] );
	}
}
```

(If requiring `AbilityTestCase.php` is unnecessary in this file, drop that line; the reset is inlined.)

- [ ] **Step 2: Run to verify failure**

Run: `npx wp-env run tests-wordpress --env-cwd=wp-content/plugins/pediment-ai ./vendor/bin/phpunit --filter ReadAbilitiesTest`
Expected: FAIL — abilities not registered.

- [ ] **Step 3: Write the framework**

`plugin/src/Abilities/Ability.php`:

```php
<?php
/**
 * Base class for Pediment abilities (WordPress Abilities API).
 *
 * @package Pediment
 */

declare(strict_types=1);

namespace Pediment\Abilities;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * One ability: its registration args plus permission and execute callbacks.
 */
abstract class Ability {

	/**
	 * Ability name, e.g. pediment/read-page.
	 */
	abstract public function name(): string;

	/**
	 * Permission check. Must return a plain bool (core rejects WP_Error here).
	 *
	 * @param mixed $input Validated input.
	 */
	abstract public function permission( $input ): bool;

	/**
	 * Runs the ability.
	 *
	 * @param mixed $input Validated input.
	 * @return array|\WP_Error
	 */
	abstract public function execute( $input );

	/**
	 * Human label.
	 */
	abstract protected function label(): string;

	/**
	 * Description shown to agents.
	 */
	abstract protected function description(): string;

	/**
	 * JSON schema of the input.
	 */
	abstract protected function inputSchema(): array;

	/**
	 * JSON schema of the output.
	 */
	abstract protected function outputSchema(): array;

	/**
	 * Annotations: readonly, destructive, idempotent.
	 *
	 * @return array{readonly:bool,destructive:bool,idempotent:bool}
	 */
	abstract protected function annotations(): array;

	/**
	 * Arguments for wp_register_ability().
	 */
	public function args(): array {
		return array(
			'label'               => $this->label(),
			'description'         => $this->description(),
			'category'            => Registrar::CATEGORY,
			'input_schema'        => $this->inputSchema(),
			'output_schema'       => $this->outputSchema(),
			'execute_callback'    => array( $this, 'execute' ),
			'permission_callback' => array( $this, 'permission' ),
			'meta'                => array(
				'show_in_rest' => true,
				'annotations'  => $this->annotations(),
			),
		);
	}

	/**
	 * Shared schema fragment: a page summary.
	 */
	protected static function pageSummarySchema(): array {
		return array(
			'type'       => 'object',
			'properties' => array(
				'id'       => array( 'type' => 'integer' ),
				'title'    => array( 'type' => 'string' ),
				'slug'     => array( 'type' => 'string' ),
				'parent'   => array( 'type' => 'integer' ),
				'status'   => array( 'type' => 'string' ),
				'language' => array( 'type' => 'string' ),
				'link'     => array( 'type' => 'string' ),
				'modified' => array( 'type' => 'string' ),
			),
			'required'   => array( 'id', 'title', 'slug', 'parent', 'status', 'language', 'link', 'modified' ),
		);
	}

	/**
	 * A WP_Error whose message lists every problem (the MCP adapter shows the message only).
	 *
	 * @param string       $code     Error code.
	 * @param string       $headline First line.
	 * @param list<string> $problems Problems.
	 * @param int          $status   HTTP status.
	 */
	protected static function errorList( string $code, string $headline, array $problems, int $status = 422 ): \WP_Error {
		return new \WP_Error( $code, $headline . "\n- " . implode( "\n- ", $problems ), array( 'status' => $status, 'errors' => $problems ) );
	}
}
```

`plugin/src/Abilities/PageData.php`:

```php
<?php
/**
 * Page shapes returned by the page abilities.
 *
 * @package Pediment
 */

declare(strict_types=1);

namespace Pediment\Abilities;

use Pediment\Language\LanguageRegistry;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Builds page summaries and full page records.
 */
final class PageData {

	/**
	 * Short record for lists.
	 *
	 * @param \WP_Post $post Page.
	 */
	public static function summary( \WP_Post $post ): array {
		return array(
			'id'       => $post->ID,
			'title'    => $post->post_title,
			'slug'     => $post->post_name,
			'parent'   => (int) $post->post_parent,
			'status'   => $post->post_status,
			'language' => LanguageRegistry::provider()->languageOf( $post->ID ),
			'link'     => (string) get_permalink( $post ),
			'modified' => (string) mysql_to_rfc3339( $post->post_modified_gmt ),
		);
	}

	/**
	 * Full record including markup and translations.
	 *
	 * @param \WP_Post $post Page.
	 */
	public static function full( \WP_Post $post ): array {
		$provider     = LanguageRegistry::provider();
		$translations = array();
		foreach ( $provider->languages() as $code ) {
			if ( '' === $code ) {
				continue;
			}
			$other = $provider->translationOf( $post->ID, $code );
			if ( $other > 0 && $other !== $post->ID ) {
				$translations[ $code ] = $other;
			}
		}
		return self::summary( $post ) + array(
			'translations' => $translations,
			'markup'       => $post->post_content,
			'revisions'    => count( wp_get_post_revisions( $post->ID, array( 'fields' => 'ids' ) ) ),
		);
	}

	/**
	 * The page for an ID, or null when the ID is not a page.
	 *
	 * @param int $id Post ID.
	 */
	public static function page( int $id ): ?\WP_Post {
		$post = get_post( $id );
		return ( $post instanceof \WP_Post && 'page' === $post->post_type ) ? $post : null;
	}
}
```

`plugin/src/Abilities/Registrar.php`:

```php
<?php
/**
 * Registers Pediment's ability category and abilities.
 *
 * @package Pediment
 */

declare(strict_types=1);

namespace Pediment\Abilities;

use Pediment\Content\Policy;
use Pediment\Design\SiteStyles;
use Pediment\Design\StyleValidator;
use Pediment\Design\Vocabulary;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Wires the Abilities API hooks.
 */
final class Registrar {

	public const CATEGORY = 'pediment';

	/**
	 * Hooks registration (call at plugin load).
	 */
	public static function register(): void {
		add_action( 'wp_abilities_api_categories_init', array( self::class, 'registerCategory' ) );
		add_action( 'wp_abilities_api_init', array( self::class, 'registerAbilities' ) );
	}

	/**
	 * Every Pediment ability.
	 *
	 * @return list<Ability>
	 */
	public static function abilities(): array {
		$validator = new StyleValidator();
		$store     = new SiteStyles( $validator );
		$policy    = new Policy();
		return array(
			new GetVocabulary( new Vocabulary( $validator, $store ) ),
			new ListPages(),
			new ReadPage(),
			new CheckPage( $policy ),
		);
	}

	/**
	 * Ability names, in registration order.
	 *
	 * @return list<string>
	 */
	public static function names(): array {
		return array_map( static fn( Ability $ability ): string => $ability->name(), self::abilities() );
	}

	/**
	 * On wp_abilities_api_categories_init.
	 */
	public static function registerCategory(): void {
		wp_register_ability_category(
			self::CATEGORY,
			array(
				'label'       => __( 'Pediment', 'pediment' ),
				'description' => __( 'Build and change pages and design styles under Pediment’s content and design rules.', 'pediment' ),
			)
		);
	}

	/**
	 * On wp_abilities_api_init.
	 */
	public static function registerAbilities(): void {
		foreach ( self::abilities() as $ability ) {
			wp_register_ability( $ability->name(), $ability->args() );
		}
	}
}
```

In `plugin/src/Bootstrap.php` `register()`, append:

```php
		\Pediment\Abilities\Registrar::register();
```

- [ ] **Step 4: Write the four read abilities**

`plugin/src/Abilities/GetVocabulary.php`:

```php
<?php
/**
 * pediment/get-vocabulary.
 *
 * @package Pediment
 */

declare(strict_types=1);

namespace Pediment\Abilities;

use Pediment\Content\Policy;
use Pediment\Design\Vocabulary;
use Pediment\Language\LanguageRegistry;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Everything an agent needs before writing: tokens, styles, blocks, rules.
 */
final class GetVocabulary extends Ability {

	/**
	 * Constructor.
	 *
	 * @param Vocabulary $vocabulary Vocabulary.
	 */
	public function __construct( private Vocabulary $vocabulary ) {}

	public function name(): string {
		return 'pediment/get-vocabulary';
	}

	protected function label(): string {
		return __( 'Get design vocabulary', 'pediment' );
	}

	protected function description(): string {
		return __( 'Read this first. Returns the design tokens, every named block style (with the layer that provides it), the blocks a page may use, the content rules, the WordPress version (pass it to the client-kit validate-blocks tool) and the site languages.', 'pediment' );
	}

	protected function inputSchema(): array {
		return array(
			'type'                 => 'object',
			'properties'           => array(),
			'additionalProperties' => false,
			'default'              => array(),
		);
	}

	protected function outputSchema(): array {
		return array(
			'type'       => 'object',
			'properties' => array(
				'wordpress'       => array( 'type' => 'string' ),
				'tokens'          => array( 'type' => 'object' ),
				'styles'          => array( 'type' => 'array' ),
				'allowedBlocks'   => array( 'type' => 'array', 'items' => array( 'type' => 'string' ) ),
				'rules'           => array( 'type' => 'array', 'items' => array( 'type' => 'string' ) ),
				'languages'       => array( 'type' => 'array', 'items' => array( 'type' => 'string' ) ),
				'defaultLanguage' => array( 'type' => 'string' ),
			),
			'required'   => array( 'wordpress', 'tokens', 'styles', 'allowedBlocks', 'rules', 'languages', 'defaultLanguage' ),
		);
	}

	protected function annotations(): array {
		return array( 'readonly' => true, 'destructive' => false, 'idempotent' => true );
	}

	public function permission( $input ): bool {
		return current_user_can( 'edit_pages' );
	}

	public function execute( $input ) {
		$provider = LanguageRegistry::provider();
		return array(
			'wordpress'       => (string) get_bloginfo( 'version' ),
			'tokens'          => array(
				'colors'       => $this->slugs( array( 'color', 'palette' ) ),
				'fontSizes'    => $this->slugs( array( 'typography', 'fontSizes' ) ),
				'fontFamilies' => $this->slugs( array( 'typography', 'fontFamilies' ) ),
				'spacing'      => $this->slugs( array( 'spacing', 'spacingSizes' ) ),
				'shadows'      => $this->slugs( array( 'shadow', 'presets' ) ),
				'radius'       => (array) wp_get_global_settings( array( 'custom', 'radius' ) ),
			),
			'styles'          => $this->vocabulary->styles(),
			'allowedBlocks'   => Policy::allowedBlocks(),
			'rules'           => Policy::RULES,
			'languages'       => array_values( array_filter( $provider->languages(), static fn( string $code ): bool => '' !== $code ) ),
			'defaultLanguage' => $provider->defaultLanguage(),
		);
	}

	/**
	 * Preset slugs at a settings path, across origins.
	 *
	 * @param list<string> $path Settings path.
	 * @return list<string>
	 */
	private function slugs( array $path ): array {
		$slugs = array();
		foreach ( (array) wp_get_global_settings( $path ) as $items ) {
			foreach ( (array) $items as $item ) {
				if ( is_array( $item ) && isset( $item['slug'] ) ) {
					$slugs[] = (string) $item['slug'];
				}
			}
		}
		return array_values( array_unique( $slugs ) );
	}
}
```

`plugin/src/Abilities/ListPages.php`:

```php
<?php
/**
 * pediment/list-pages.
 *
 * @package Pediment
 */

declare(strict_types=1);

namespace Pediment\Abilities;

use Pediment\Language\LanguageRegistry;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Lists pages in every language.
 */
final class ListPages extends Ability {

	private const STATUSES = array( 'publish', 'draft', 'pending', 'private', 'future' );

	public function name(): string {
		return 'pediment/list-pages';
	}

	protected function label(): string {
		return __( 'List pages', 'pediment' );
	}

	protected function description(): string {
		return __( 'Lists pages of every language with id, title, slug, parent, status, language and link. Optional filters: language code, parent page id, status (default any).', 'pediment' );
	}

	protected function inputSchema(): array {
		return array(
			'type'                 => 'object',
			'properties'           => array(
				'language' => array( 'type' => 'string' ),
				'parent'   => array( 'type' => 'integer', 'minimum' => 0 ),
				'status'   => array( 'type' => 'string', 'enum' => array_merge( array( 'any' ), self::STATUSES ) ),
			),
			'additionalProperties' => false,
			'default'              => array(),
		);
	}

	protected function outputSchema(): array {
		return array(
			'type'       => 'object',
			'properties' => array(
				'pages' => array( 'type' => 'array', 'items' => self::pageSummarySchema() ),
			),
			'required'   => array( 'pages' ),
		);
	}

	protected function annotations(): array {
		return array( 'readonly' => true, 'destructive' => false, 'idempotent' => true );
	}

	public function permission( $input ): bool {
		return current_user_can( 'edit_pages' );
	}

	public function execute( $input ) {
		$input  = (array) $input;
		$status = (string) ( $input['status'] ?? 'any' );
		$args   = array(
			'post_type'      => 'page',
			'post_status'    => 'any' === $status ? self::STATUSES : $status,
			'posts_per_page' => 500,
			'orderby'        => 'menu_order title',
			'order'          => 'ASC',
			'no_found_rows'  => true,
		);
		if ( isset( $input['parent'] ) ) {
			$args['post_parent'] = (int) $input['parent'];
		}
		$pages = array_map( array( PageData::class, 'summary' ), get_posts( LanguageRegistry::provider()->unscopedQuery( $args ) ) );
		if ( isset( $input['language'] ) ) {
			$pages = array_values( array_filter( $pages, static fn( array $page ): bool => $page['language'] === $input['language'] ) );
		}
		return array( 'pages' => $pages );
	}
}
```

`plugin/src/Abilities/ReadPage.php`:

```php
<?php
/**
 * pediment/read-page.
 *
 * @package Pediment
 */

declare(strict_types=1);

namespace Pediment\Abilities;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Returns one page with its raw block markup.
 */
final class ReadPage extends Ability {

	public function name(): string {
		return 'pediment/read-page';
	}

	protected function label(): string {
		return __( 'Read page', 'pediment' );
	}

	protected function description(): string {
		return __( 'Returns one page: id, title, slug, parent, status, language, translations (language code → page id), raw block markup and revision count.', 'pediment' );
	}

	protected function inputSchema(): array {
		return array(
			'type'                 => 'object',
			'properties'           => array( 'id' => array( 'type' => 'integer', 'minimum' => 1 ) ),
			'required'             => array( 'id' ),
			'additionalProperties' => false,
		);
	}

	protected function outputSchema(): array {
		$schema                              = self::pageSummarySchema();
		$schema['properties']['translations'] = array( 'type' => 'object' );
		$schema['properties']['markup']       = array( 'type' => 'string' );
		$schema['properties']['revisions']    = array( 'type' => 'integer' );
		$schema['required']                   = array_merge( $schema['required'], array( 'translations', 'markup', 'revisions' ) );
		return $schema;
	}

	protected function annotations(): array {
		return array( 'readonly' => true, 'destructive' => false, 'idempotent' => true );
	}

	public function permission( $input ): bool {
		$id = (int) ( ( (array) $input )['id'] ?? 0 );
		return current_user_can( 'edit_pages' ) && ( null === PageData::page( $id ) || current_user_can( 'edit_post', $id ) );
	}

	public function execute( $input ) {
		$page = PageData::page( (int) $input['id'] );
		if ( null === $page ) {
			return new \WP_Error( 'pediment_page_not_found', __( 'No page with that id.', 'pediment' ), array( 'status' => 404 ) );
		}
		return PageData::full( $page );
	}
}
```

`plugin/src/Abilities/CheckPage.php`:

```php
<?php
/**
 * pediment/check-page.
 *
 * @package Pediment
 */

declare(strict_types=1);

namespace Pediment\Abilities;

use Pediment\Content\InvalidBlockLog;
use Pediment\Content\Policy;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Runs the content rules on a stored page and returns the editor's last validity report.
 */
final class CheckPage extends Ability {

	/**
	 * Constructor.
	 *
	 * @param Policy $policy Policy.
	 */
	public function __construct( private Policy $policy ) {}

	public function name(): string {
		return 'pediment/check-page';
	}

	protected function label(): string {
		return __( 'Check page', 'pediment' );
	}

	protected function description(): string {
		return __( 'Checks a stored page: content-rule errors and warnings, the blocks the block editor last reported as invalid (null when the page was not opened in the editor since its last write), and legacy Pediment blocks still on the page.', 'pediment' );
	}

	protected function inputSchema(): array {
		return array(
			'type'                 => 'object',
			'properties'           => array( 'id' => array( 'type' => 'integer', 'minimum' => 1 ) ),
			'required'             => array( 'id' ),
			'additionalProperties' => false,
		);
	}

	protected function outputSchema(): array {
		return array(
			'type'       => 'object',
			'properties' => array(
				'errors'        => array( 'type' => 'array', 'items' => array( 'type' => 'string' ) ),
				'warnings'      => array( 'type' => 'array', 'items' => array( 'type' => 'string' ) ),
				'invalidBlocks' => array( 'type' => array( 'object', 'null' ) ),
				'legacyBlocks'  => array( 'type' => 'array', 'items' => array( 'type' => 'string' ) ),
			),
			'required'   => array( 'errors', 'warnings', 'invalidBlocks', 'legacyBlocks' ),
		);
	}

	protected function annotations(): array {
		return array( 'readonly' => true, 'destructive' => false, 'idempotent' => true );
	}

	public function permission( $input ): bool {
		$id = (int) ( ( (array) $input )['id'] ?? 0 );
		return current_user_can( 'edit_pages' ) && ( null === PageData::page( $id ) || current_user_can( 'edit_post', $id ) );
	}

	public function execute( $input ) {
		$page = PageData::page( (int) $input['id'] );
		if ( null === $page ) {
			return new \WP_Error( 'pediment_page_not_found', __( 'No page with that id.', 'pediment' ), array( 'status' => 404 ) );
		}
		$result = $this->policy->check( $page->post_content );
		return array(
			'errors'        => $result['errors'],
			'warnings'      => $result['warnings'],
			'invalidBlocks' => InvalidBlockLog::get( $page->ID ),
			'legacyBlocks'  => $this->policy->legacyBlocksIn( $page->post_content ),
		);
	}
}
```

- [ ] **Step 5: Run to verify they pass**

```bash
npx wp-env run tests-wordpress --env-cwd=wp-content/plugins/pediment-ai ./vendor/bin/phpunit --filter ReadAbilitiesTest
npx wp-env run tests-wordpress --env-cwd=wp-content/plugins/pediment-ai ./vendor/bin/phpunit -c phpunit-polylang.xml.dist --filter AbilitiesLanguageTest
```

Expected: PASS. If output-schema validation rejects a real value (core validates output), fix the schema to match the real shape and say so in the report.

- [ ] **Step 6: Full suites, lint, commit**

```bash
npx wp-env run tests-wordpress --env-cwd=wp-content/plugins/pediment-ai ./vendor/bin/phpunit
npx wp-env run tests-wordpress --env-cwd=wp-content/plugins/pediment-ai ./vendor/bin/phpunit -c phpunit-polylang.xml.dist
composer lint -d plugin
git add plugin/src/Abilities plugin/src/Bootstrap.php plugin/tests/phpunit/Abilities plugin/tests/polylang/AbilitiesLanguageTest.php
git commit -m "feat(abilities): read vocabulary and pages through abilities"
```

---

### Task 6: Write abilities

**Files:**
- Create: `plugin/src/Abilities/WritePage.php`, `RollbackPage.php`, `UpsertStyle.php`, `DeleteStyle.php`
- Modify: `plugin/src/Abilities/Registrar.php` (`abilities()`)
- Test: `plugin/tests/phpunit/Abilities/WriteAbilitiesTest.php`

**Interfaces:**
- Consumes: `Ability`, `PageData`, `Registrar` (Task 5), `Policy` (Task 3), `InvalidBlockLog::clear()` (Task 4), `SiteStyles::save()/delete()` (Plan 2), `LanguageProvider` (`languages`, `setLanguage`, `translationOf`, `linkTranslations`).
- Produces: `write-page` → `{id, status, url, revision, warnings}`; `rollback-page` → `{id, restoredRevision, revision}`; `upsert-style` → `{slug, id, revision}`; `delete-style` → `{slug, deleted}`. Rejections: `WP_Error` codes `pediment_content_invalid` (422), `pediment_language_unknown` (422), `pediment_revision_mismatch` (404), `pediment_style_invalid` (422), `pediment_style_not_found` (404); every message lists the problems.

- [ ] **Step 1: Write the failing tests**

`plugin/tests/phpunit/Abilities/WriteAbilitiesTest.php`:

```php
<?php

require_once __DIR__ . '/AbilityTestCase.php';

use Pediment\Abilities\Registrar;
use Pediment\Content\InvalidBlockLog;
use Pediment\Design\SiteStyles;
use Pediment\Design\StyleValidator;

class WriteAbilitiesTest extends AbilityTestCase {

	private const GOOD = "<!-- wp:paragraph -->\n<p>Hello</p>\n<!-- /wp:paragraph -->";

	public function test_all_eight_abilities_are_registered(): void {
		$this->assertSame(
			array( 'pediment/get-vocabulary', 'pediment/list-pages', 'pediment/read-page', 'pediment/check-page', 'pediment/write-page', 'pediment/rollback-page', 'pediment/upsert-style', 'pediment/delete-style' ),
			Registrar::names()
		);
		foreach ( Registrar::names() as $name ) {
			$this->assertTrue( wp_has_ability( $name ), $name );
		}
	}

	public function test_new_page_is_a_draft_and_update_keeps_a_revision(): void {
		$editor = $this->user( 'editor' );
		$new    = $this->run_as( $editor, 'pediment/write-page', array( 'title' => 'About', 'markup' => self::GOOD ) );
		$this->assertIsArray( $new );
		$this->assertSame( 'draft', get_post_status( $new['id'] ) );

		InvalidBlockLog::record( $new['id'], array( 'core/group' ) );
		$updated = $this->run_as( $editor, 'pediment/write-page', array( 'id' => $new['id'], 'title' => 'About us', 'markup' => "<!-- wp:paragraph -->\n<p>Changed</p>\n<!-- /wp:paragraph -->" ) );

		$this->assertSame( $new['id'], $updated['id'] );
		$this->assertSame( 'About us', get_the_title( $new['id'] ) );
		$this->assertGreaterThan( 0, $updated['revision'] );
		$this->assertNull( InvalidBlockLog::get( $new['id'] ), 'a write clears the stale editor report' );
	}

	public function test_rule_violations_are_rejected_for_admins_with_every_problem_in_the_message(): void {
		$result = $this->run_as(
			$this->user( 'administrator' ),
			'pediment/write-page',
			array(
				'title'  => 'Bad',
				'markup' => '<!-- wp:html --><script>alert(1)</script><!-- /wp:html --><!-- wp:group {"style":{"spacing":{"padding":{"top":"78px"}}}} --><div class="wp-block-group" style="padding-top:78px"></div><!-- /wp:group -->',
			)
		);
		$this->assertWPError( $result );
		$this->assertSame( 'pediment_content_invalid', $result->get_error_code() );
		$this->assertStringContainsString( 'core/html: block is not allowed', $result->get_error_message() );
		$this->assertStringContainsString( '78px', $result->get_error_message() );
		$this->assertSame( 0, (int) ( new WP_Query( array( 'post_type' => 'page', 'title' => 'Bad', 'post_status' => 'any' ) ) )->found_posts );
	}

	public function test_author_cannot_overwrite_someone_elses_page(): void {
		$page   = self::factory()->post->create( array( 'post_type' => 'page', 'post_author' => $this->user( 'administrator' ), 'post_content' => self::GOOD ) );
		$result = $this->run_as( $this->user( 'author' ), 'pediment/write-page', array( 'id' => $page, 'title' => 'Mine now', 'markup' => self::GOOD ) );
		$this->assertWPError( $result );
		$this->assertSame( 'ability_invalid_permissions', $result->get_error_code() );
		$this->assertNotSame( 'Mine now', get_the_title( $page ) );
	}

	public function test_contributor_cannot_publish(): void {
		$result = $this->run_as( $this->user( 'contributor' ), 'pediment/write-page', array( 'title' => 'X', 'markup' => self::GOOD, 'status' => 'publish' ) );
		$this->assertWPError( $result );
	}

	public function test_unknown_language_is_rejected_before_writing(): void {
		$result = $this->run_as( $this->user( 'editor' ), 'pediment/write-page', array( 'title' => 'Lang', 'markup' => self::GOOD, 'language' => 'xx' ) );
		$this->assertWPError( $result );
		$this->assertSame( 'pediment_language_unknown', $result->get_error_code() );
	}

	public function test_rollback_restores_and_refuses_foreign_revisions(): void {
		$editor = $this->user( 'editor' );
		$first  = $this->run_as( $editor, 'pediment/write-page', array( 'title' => 'R', 'markup' => "<!-- wp:paragraph -->\n<p>One</p>\n<!-- /wp:paragraph -->" ) );
		$second = $this->run_as( $editor, 'pediment/write-page', array( 'id' => $first['id'], 'title' => 'R', 'markup' => "<!-- wp:paragraph -->\n<p>Two</p>\n<!-- /wp:paragraph -->" ) );
		$other  = $this->run_as( $editor, 'pediment/write-page', array( 'title' => 'Other', 'markup' => self::GOOD ) );
		$other2 = $this->run_as( $editor, 'pediment/write-page', array( 'id' => $other['id'], 'title' => 'Other', 'markup' => "<!-- wp:paragraph -->\n<p>Other two</p>\n<!-- /wp:paragraph -->" ) );

		$revisions = array_keys( wp_get_post_revisions( $first['id'] ) );
		$oldest    = end( $revisions );

		$restored = $this->run_as( $editor, 'pediment/rollback-page', array( 'id' => $first['id'], 'revision' => $oldest ) );
		$this->assertSame( $oldest, $restored['restoredRevision'] );
		$this->assertStringContainsString( 'One', get_post( $first['id'] )->post_content );

		$foreign = $this->run_as( $editor, 'pediment/rollback-page', array( 'id' => $first['id'], 'revision' => $other2['revision'] ) );
		$this->assertWPError( $foreign );
		$this->assertSame( 'pediment_revision_mismatch', $foreign->get_error_code() );
		$this->assertStringContainsString( 'One', get_post( $first['id'] )->post_content );
		$this->assertStringContainsString( 'Other two', get_post( $other['id'] )->post_content );
		$this->assertIsArray( $second );
	}

	public function test_upsert_and_delete_style(): void {
		$admin = $this->user( 'administrator' );
		$saved = $this->run_as(
			$admin,
			'pediment/upsert-style',
			array(
				'slug'       => 'promo',
				'label'      => 'Promo',
				'blockTypes' => array( 'core/group' ),
				'styles'     => array( 'color' => array( 'background' => 'var:preset|color|accent' ) ),
			)
		);
		$this->assertSame( 'promo', $saved['slug'] );
		$this->assertNotNull( ( new SiteStyles( new StyleValidator() ) )->get( 'promo' ) );

		$bad = $this->run_as( $admin, 'pediment/upsert-style', array( 'slug' => 'bad', 'label' => 'Bad', 'blockTypes' => array( 'core/group' ), 'styles' => array( 'border' => array( 'radius' => '22px' ) ) ) );
		$this->assertWPError( $bad );
		$this->assertStringContainsString( 'styles.border.radius', $bad->get_error_message() );

		$this->assertSame( array( 'slug' => 'promo', 'deleted' => true ), $this->run_as( $admin, 'pediment/delete-style', array( 'slug' => 'promo' ) ) );
		$missing = $this->run_as( $admin, 'pediment/delete-style', array( 'slug' => 'card' ) );
		$this->assertWPError( $missing );
		$this->assertSame( 'pediment_style_not_found', $missing->get_error_code() );
	}

	public function test_editor_without_theme_rights_cannot_change_styles(): void {
		$result = $this->run_as( $this->user( 'editor' ), 'pediment/upsert-style', array( 'slug' => 'promo', 'label' => 'Promo', 'blockTypes' => array( 'core/group' ), 'styles' => array() ) );
		$this->assertWPError( $result );
		$this->assertSame( 'ability_invalid_permissions', $result->get_error_code() );
	}
}
```

- [ ] **Step 2: Run to verify failure**

Run: `npx wp-env run tests-wordpress --env-cwd=wp-content/plugins/pediment-ai ./vendor/bin/phpunit --filter WriteAbilitiesTest`
Expected: FAIL — write abilities not registered.

- [ ] **Step 3: Write the four abilities**

`plugin/src/Abilities/WritePage.php`:

```php
<?php
/**
 * pediment/write-page.
 *
 * @package Pediment
 */

declare(strict_types=1);

namespace Pediment\Abilities;

use Pediment\Content\InvalidBlockLog;
use Pediment\Content\Policy;
use Pediment\Language\LanguageRegistry;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Creates or updates a page after the content rules pass.
 */
final class WritePage extends Ability {

	/**
	 * Constructor.
	 *
	 * @param Policy $policy Policy.
	 */
	public function __construct( private Policy $policy ) {}

	public function name(): string {
		return 'pediment/write-page';
	}

	protected function label(): string {
		return __( 'Write page', 'pediment' );
	}

	protected function description(): string {
		return __( 'Creates a page (status draft unless given) or updates the page with the given id. The markup must pass the content rules (see get-vocabulary) or nothing is written and every violation is listed. Validate static blocks with the client-kit validate-blocks tool first. Every update keeps a revision (see rollback-page). Optional: slug, parent, status, language code and translation_of (id of the page this one translates).', 'pediment' );
	}

	protected function inputSchema(): array {
		return array(
			'type'                 => 'object',
			'properties'           => array(
				'id'             => array( 'type' => 'integer', 'minimum' => 1 ),
				'title'          => array( 'type' => 'string', 'minLength' => 1 ),
				'slug'           => array( 'type' => 'string' ),
				'parent'         => array( 'type' => 'integer', 'minimum' => 0 ),
				'status'         => array( 'type' => 'string', 'enum' => array( 'draft', 'pending', 'private', 'publish' ) ),
				'language'       => array( 'type' => 'string' ),
				'translation_of' => array( 'type' => 'integer', 'minimum' => 1 ),
				'markup'         => array( 'type' => 'string' ),
			),
			'required'             => array( 'title', 'markup' ),
			'additionalProperties' => false,
		);
	}

	protected function outputSchema(): array {
		return array(
			'type'       => 'object',
			'properties' => array(
				'id'       => array( 'type' => 'integer' ),
				'status'   => array( 'type' => 'string' ),
				'url'      => array( 'type' => 'string' ),
				'revision' => array( 'type' => 'integer' ),
				'warnings' => array( 'type' => 'array', 'items' => array( 'type' => 'string' ) ),
			),
			'required'   => array( 'id', 'status', 'url', 'revision', 'warnings' ),
		);
	}

	protected function annotations(): array {
		return array( 'readonly' => false, 'destructive' => false, 'idempotent' => false );
	}

	public function permission( $input ): bool {
		$input = (array) $input;
		$id    = (int) ( $input['id'] ?? 0 );
		if ( $id > 0 ) {
			if ( null === PageData::page( $id ) || ! current_user_can( 'edit_post', $id ) ) {
				return false;
			}
		} elseif ( ! current_user_can( 'edit_pages' ) ) {
			return false;
		}
		return 'publish' !== ( $input['status'] ?? '' ) || current_user_can( 'publish_pages' );
	}

	public function execute( $input ) {
		$provider = LanguageRegistry::provider();
		$language = (string) ( $input['language'] ?? '' );
		if ( '' !== $language && ! in_array( $language, $provider->languages(), true ) ) {
			return self::errorList( 'pediment_language_unknown', __( 'Unknown language code.', 'pediment' ), array( "language \"{$language}\" is not configured; use one of: " . implode( ', ', array_filter( $provider->languages() ) ) ) );
		}
		if ( isset( $input['translation_of'] ) && '' === $language ) {
			return self::errorList( 'pediment_language_unknown', __( 'translation_of needs a language.', 'pediment' ), array( 'pass the language code of this translation together with translation_of' ) );
		}

		$check = $this->policy->check( (string) $input['markup'] );
		if ( array() !== $check['errors'] ) {
			return self::errorList( 'pediment_content_invalid', __( 'The page breaks the content rules. Nothing was written.', 'pediment' ), $check['errors'] );
		}

		$id      = (int) ( $input['id'] ?? 0 );
		$postarr = array(
			'post_type'    => 'page',
			'post_title'   => (string) $input['title'],
			'post_content' => (string) $input['markup'],
		);
		if ( isset( $input['slug'] ) ) {
			$postarr['post_name'] = sanitize_title( (string) $input['slug'] );
		}
		if ( isset( $input['parent'] ) ) {
			$postarr['post_parent'] = (int) $input['parent'];
		}
		if ( isset( $input['status'] ) ) {
			$postarr['post_status'] = (string) $input['status'];
		} elseif ( 0 === $id ) {
			$postarr['post_status'] = 'draft';
		}

		if ( $id > 0 ) {
			$postarr['ID'] = $id;
			$result        = wp_update_post( wp_slash( $postarr ), true );
		} else {
			$result = wp_insert_post( wp_slash( $postarr ), true );
		}
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		$id = (int) $result;

		if ( '' !== $language ) {
			$provider->setLanguage( $id, $language );
			if ( isset( $input['translation_of'] ) ) {
				$map = array();
				foreach ( $provider->languages() as $code ) {
					$other = '' === $code ? 0 : $provider->translationOf( (int) $input['translation_of'], $code );
					if ( $other > 0 ) {
						$map[ $code ] = $other;
					}
				}
				$map[ $language ] = $id;
				$provider->linkTranslations( $map );
			}
		}

		InvalidBlockLog::clear( $id );
		$revisions = wp_get_post_revisions( $id, array( 'posts_per_page' => 1, 'fields' => 'ids' ) );

		return array(
			'id'       => $id,
			'status'   => (string) get_post_status( $id ),
			'url'      => (string) get_permalink( $id ),
			'revision' => (int) ( reset( $revisions ) ?: 0 ),
			'warnings' => $check['warnings'],
		);
	}
}
```

`plugin/src/Abilities/RollbackPage.php`:

```php
<?php
/**
 * pediment/rollback-page.
 *
 * @package Pediment
 */

declare(strict_types=1);

namespace Pediment\Abilities;

use Pediment\Content\InvalidBlockLog;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Restores an earlier revision of a page.
 */
final class RollbackPage extends Ability {

	public function name(): string {
		return 'pediment/rollback-page';
	}

	protected function label(): string {
		return __( 'Roll back page', 'pediment' );
	}

	protected function description(): string {
		return __( 'Restores the page with the given id to one of its own revisions (revision ids come from write-page results). The current content is kept as a new revision.', 'pediment' );
	}

	protected function inputSchema(): array {
		return array(
			'type'                 => 'object',
			'properties'           => array(
				'id'       => array( 'type' => 'integer', 'minimum' => 1 ),
				'revision' => array( 'type' => 'integer', 'minimum' => 1 ),
			),
			'required'             => array( 'id', 'revision' ),
			'additionalProperties' => false,
		);
	}

	protected function outputSchema(): array {
		return array(
			'type'       => 'object',
			'properties' => array(
				'id'               => array( 'type' => 'integer' ),
				'restoredRevision' => array( 'type' => 'integer' ),
				'revision'         => array( 'type' => 'integer' ),
			),
			'required'   => array( 'id', 'restoredRevision', 'revision' ),
		);
	}

	protected function annotations(): array {
		return array( 'readonly' => false, 'destructive' => true, 'idempotent' => false );
	}

	public function permission( $input ): bool {
		$id = (int) ( ( (array) $input )['id'] ?? 0 );
		return null !== PageData::page( $id ) && current_user_can( 'edit_post', $id );
	}

	public function execute( $input ) {
		$id       = (int) $input['id'];
		$revision = wp_get_post_revision( (int) $input['revision'] );
		if ( ! $revision instanceof \WP_Post || (int) $revision->post_parent !== $id ) {
			return self::errorList( 'pediment_revision_mismatch', __( 'That revision does not belong to this page.', 'pediment' ), array( "revision {$input['revision']} is not a revision of page {$id}" ), 404 );
		}
		$restored = wp_restore_post_revision( $revision->ID );
		if ( ! $restored ) {
			return new \WP_Error( 'pediment_rollback_failed', __( 'The revision could not be restored.', 'pediment' ), array( 'status' => 500 ) );
		}
		InvalidBlockLog::clear( $id );
		$revisions = wp_get_post_revisions( $id, array( 'posts_per_page' => 1, 'fields' => 'ids' ) );
		return array(
			'id'               => $id,
			'restoredRevision' => $revision->ID,
			'revision'         => (int) ( reset( $revisions ) ?: 0 ),
		);
	}
}
```

`plugin/src/Abilities/UpsertStyle.php`:

```php
<?php
/**
 * pediment/upsert-style.
 *
 * @package Pediment
 */

declare(strict_types=1);

namespace Pediment\Abilities;

use Pediment\Design\SiteStyles;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Creates or updates a site-layer style.
 */
final class UpsertStyle extends Ability {

	/**
	 * Constructor.
	 *
	 * @param SiteStyles $store Store.
	 */
	public function __construct( private SiteStyles $store ) {}

	public function name(): string {
		return 'pediment/upsert-style';
	}

	protected function label(): string {
		return __( 'Create or update style', 'pediment' );
	}

	protected function description(): string {
		return __( 'Creates or updates a named block style in the site layer (it overrides plugin and theme styles with the same slug, property by property). Values must be tokens; css may only use &-rules, no colours, no "<". Every change keeps a revision. Pages use it with className "is-style-<slug>".', 'pediment' );
	}

	protected function inputSchema(): array {
		return array(
			'type'                 => 'object',
			'properties'           => array(
				'slug'       => array( 'type' => 'string' ),
				'label'      => array( 'type' => 'string' ),
				'blockTypes' => array( 'type' => 'array', 'items' => array( 'type' => 'string' ), 'minItems' => 1 ),
				'styles'     => array( 'type' => 'object' ),
			),
			'required'             => array( 'slug', 'label', 'blockTypes', 'styles' ),
			'additionalProperties' => false,
		);
	}

	protected function outputSchema(): array {
		return array(
			'type'       => 'object',
			'properties' => array(
				'slug'     => array( 'type' => 'string' ),
				'id'       => array( 'type' => 'integer' ),
				'revision' => array( 'type' => 'integer' ),
			),
			'required'   => array( 'slug', 'id', 'revision' ),
		);
	}

	protected function annotations(): array {
		return array( 'readonly' => false, 'destructive' => false, 'idempotent' => true );
	}

	public function permission( $input ): bool {
		return current_user_can( 'edit_theme_options' );
	}

	public function execute( $input ) {
		$result = $this->store->save(
			array(
				'slug'       => (string) $input['slug'],
				'label'      => (string) $input['label'],
				'blockTypes' => array_values( (array) $input['blockTypes'] ),
				'styles'     => (array) $input['styles'],
			)
		);
		if ( is_wp_error( $result ) ) {
			$errors = (array) ( $result->get_error_data()['errors'] ?? array( $result->get_error_message() ) );
			return self::errorList( 'pediment_style_invalid', __( 'The style breaks the design rules. Nothing was saved.', 'pediment' ), $errors );
		}
		return array(
			'slug'     => (string) $input['slug'],
			'id'       => $result['id'],
			'revision' => $result['revision'],
		);
	}
}
```

`plugin/src/Abilities/DeleteStyle.php`:

```php
<?php
/**
 * pediment/delete-style.
 *
 * @package Pediment
 */

declare(strict_types=1);

namespace Pediment\Abilities;

use Pediment\Design\SiteStyles;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Deletes a site-layer style (plugin and theme styles cannot be deleted here).
 */
final class DeleteStyle extends Ability {

	/**
	 * Constructor.
	 *
	 * @param SiteStyles $store Store.
	 */
	public function __construct( private SiteStyles $store ) {}

	public function name(): string {
		return 'pediment/delete-style';
	}

	protected function label(): string {
		return __( 'Delete style', 'pediment' );
	}

	protected function description(): string {
		return __( 'Deletes a site-layer style. A plugin or theme style with the same slug then applies again. Plugin and theme styles cannot be deleted.', 'pediment' );
	}

	protected function inputSchema(): array {
		return array(
			'type'                 => 'object',
			'properties'           => array( 'slug' => array( 'type' => 'string' ) ),
			'required'             => array( 'slug' ),
			'additionalProperties' => false,
		);
	}

	protected function outputSchema(): array {
		return array(
			'type'       => 'object',
			'properties' => array(
				'slug'    => array( 'type' => 'string' ),
				'deleted' => array( 'type' => 'boolean' ),
			),
			'required'   => array( 'slug', 'deleted' ),
		);
	}

	protected function annotations(): array {
		return array( 'readonly' => false, 'destructive' => true, 'idempotent' => false );
	}

	public function permission( $input ): bool {
		return current_user_can( 'edit_theme_options' );
	}

	public function execute( $input ) {
		$slug = (string) $input['slug'];
		if ( ! $this->store->delete( $slug ) ) {
			return self::errorList( 'pediment_style_not_found', __( 'No site-layer style with that slug.', 'pediment' ), array( "\"{$slug}\" is not a site-layer style (plugin and theme styles cannot be deleted here)" ), 404 );
		}
		return array( 'slug' => $slug, 'deleted' => true );
	}
}
```

In `plugin/src/Abilities/Registrar.php` `abilities()`, extend the returned list (keep the four read abilities first):

```php
		return array(
			new GetVocabulary( new Vocabulary( $validator, $store ) ),
			new ListPages(),
			new ReadPage(),
			new CheckPage( $policy ),
			new WritePage( $policy ),
			new RollbackPage(),
			new UpsertStyle( $store ),
			new DeleteStyle( $store ),
		);
```

- [ ] **Step 4: Run to verify they pass**

Run: `npx wp-env run tests-wordpress --env-cwd=wp-content/plugins/pediment-ai ./vendor/bin/phpunit --filter 'WriteAbilitiesTest|ReadAbilitiesTest'`
Expected: PASS. If `wp_get_post_revisions()` ordering makes `$oldest` the newest in this WordPress, read the IDs and pick the revision whose content contains "One"; describe what you found.

- [ ] **Step 5: Full suites, lint, commit**

```bash
npx wp-env run tests-wordpress --env-cwd=wp-content/plugins/pediment-ai ./vendor/bin/phpunit
npx wp-env run tests-wordpress --env-cwd=wp-content/plugins/pediment-ai ./vendor/bin/phpunit -c phpunit-polylang.xml.dist
composer lint -d plugin
git add plugin/src/Abilities plugin/tests/phpunit/Abilities/WriteAbilitiesTest.php
git commit -m "feat(abilities): write pages and styles through abilities"
```

---

### Task 7: Pediment MCP server, adapter in wp-env, smoke test in CI

**Files:**
- Create: `plugin/src/Mcp/Server.php`
- Create: `tools/mcp-smoke.mjs`
- Modify: `plugin/src/Bootstrap.php` (`register()`: one line), `.wp-env.json` (`plugins`), `.github/workflows/ci.yml` (`phpunit` job)
- Test: `plugin/tests/phpunit/Mcp/ServerTest.php`

**Interfaces:**
- Consumes: `Registrar::names()` (Tasks 5–6).
- Produces: `Pediment\Mcp\Server` with `ID = 'pediment'`, `REST_NAMESPACE = 'pediment/v1'`, `ROUTE = 'mcp'`, `static register(): void`, `static create( $adapter ): void`. Endpoint `/wp-json/pediment/v1/mcp`.

- [ ] **Step 1: Write the failing test**

`plugin/tests/phpunit/Mcp/ServerTest.php`:

```php
<?php

use Pediment\Abilities\Registrar;
use Pediment\Mcp\Server;

class ServerTest extends WP_UnitTestCase {

	public function test_creates_one_server_listing_exactly_the_pediment_abilities(): void {
		$adapter = new class() {
			public array $calls = array();
			public function create_server( ...$args ) {
				$this->calls[] = $args;
				return $this;
			}
		};

		Server::create( $adapter );

		$this->assertCount( 1, $adapter->calls );
		$args = $adapter->calls[0];
		$this->assertSame( 'pediment', $args[0] );
		$this->assertSame( 'pediment/v1', $args[1] );
		$this->assertSame( 'mcp', $args[2] );
		$this->assertSame( array( 'WP\MCP\Transport\HttpTransport' ), $args[6] );
		$this->assertSame( 'WP\MCP\Infrastructure\ErrorHandling\ErrorLogMcpErrorHandler', $args[7] );
		$this->assertSame( 'WP\MCP\Infrastructure\Observability\NullMcpObservabilityHandler', $args[8] );
		$this->assertSame( Registrar::names(), $args[9] );
	}

	public function test_is_hooked_on_the_adapter_init(): void {
		$this->assertNotFalse( has_action( 'mcp_adapter_init', array( Server::class, 'create' ) ) );
	}
}
```

- [ ] **Step 2: Run to verify failure**

Run: `npx wp-env run tests-wordpress --env-cwd=wp-content/plugins/pediment-ai ./vendor/bin/phpunit --filter ServerTest`
Expected: FAIL — class not found.

- [ ] **Step 3: Write the server**

`plugin/src/Mcp/Server.php`:

```php
<?php
/**
 * The Pediment MCP server (needs the MCP Adapter plugin; inert without it).
 *
 * @package Pediment
 */

declare(strict_types=1);

namespace Pediment\Mcp;

use Pediment\Abilities\Registrar;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Exposes exactly the Pediment abilities as MCP tools at /wp-json/pediment/v1/mcp.
 */
final class Server {

	public const ID             = 'pediment';
	public const REST_NAMESPACE = 'pediment/v1';
	public const ROUTE          = 'mcp';

	/**
	 * Hooks the adapter. `mcp_adapter_init` only fires when the MCP Adapter plugin is active.
	 */
	public static function register(): void {
		add_action( 'mcp_adapter_init', array( self::class, 'create' ) );
	}

	/**
	 * Creates the server.
	 *
	 * @param object $adapter \WP\MCP\Core\McpAdapter.
	 */
	public static function create( $adapter ): void {
		$result = $adapter->create_server(
			self::ID,
			self::REST_NAMESPACE,
			self::ROUTE,
			'Pediment',
			__( 'Build and change pages and design styles on this site under Pediment’s content and design rules. Call pediment-get-vocabulary first.', 'pediment' ),
			PEDIMENT_AI_VERSION,
			array( 'WP\MCP\Transport\HttpTransport' ),
			'WP\MCP\Infrastructure\ErrorHandling\ErrorLogMcpErrorHandler',
			'WP\MCP\Infrastructure\Observability\NullMcpObservabilityHandler',
			Registrar::names()
		);
		if ( is_wp_error( $result ) ) {
			error_log( 'Pediment MCP server: ' . $result->get_error_message() ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
		}
	}
}
```

(String class names keep the file loadable and testable without the adapter.)

In `plugin/src/Bootstrap.php` `register()`, append:

```php
		\Pediment\Mcp\Server::register();
```

- [ ] **Step 4: Run to verify it passes**

Run: `npx wp-env run tests-wordpress --env-cwd=wp-content/plugins/pediment-ai ./vendor/bin/phpunit --filter ServerTest`
Expected: PASS.

- [ ] **Step 5: Add the adapter to wp-env and write the smoke test**

In `.wp-env.json` `plugins`, add `"https://downloads.wordpress.org/plugin/mcp-adapter.0.7.0.zip"` after the Polylang entry. Apply it: `npx wp-env start` (it reinstalls plugins when the config changed) and confirm `npx wp-env run cli wp plugin list` shows `mcp-adapter` active.

`tools/mcp-smoke.mjs`:

```js
#!/usr/bin/env node
/**
 * MCP smoke test. Talks to the Pediment MCP server of the running root
 * wp-env exactly like Claude Code does (JSON-RPC over HTTP, Basic auth with
 * an application password) and checks: the tool list, get-vocabulary, a
 * write-page → check-page round trip, and that a rule violation comes back
 * as a tool error naming the violation. Removes the page and the
 * application password it created.
 *
 * Usage: node tools/mcp-smoke.mjs   (wp-env must be running)
 */
import { execFileSync } from 'node:child_process';

const EXPECTED = [
	'pediment-get-vocabulary',
	'pediment-list-pages',
	'pediment-read-page',
	'pediment-check-page',
	'pediment-write-page',
	'pediment-rollback-page',
	'pediment-upsert-style',
	'pediment-delete-style',
];

function wp( ...args ) {
	const out = execFileSync( 'npx', [ 'wp-env', 'run', 'cli', 'wp', ...args ], {
		encoding: 'utf8',
		stdio: [ 'ignore', 'pipe', 'pipe' ],
	} );
	return out
		.split( '\n' )
		.filter( ( line ) => ! /^\s*(ℹ|✔)/.test( line ) )
		.join( '\n' )
		.trim();
}

function assert( condition, message ) {
	if ( ! condition ) {
		throw new Error( message );
	}
}

const text = ( result ) => ( result.content || [] ).map( ( c ) => c.text ).join( ' ' );

async function main() {
	const site = wp( 'option', 'get', 'siteurl' );
	const password = wp( 'user', 'application-password', 'create', 'admin', 'mcp-smoke', '--porcelain' );
	const endpoint = `${ site }/?rest_route=/pediment/v1/mcp`;
	const auth = 'Basic ' + Buffer.from( `admin:${ password }` ).toString( 'base64' );
	let session = null;
	let nextId = 0;
	let pageId = 0;

	const rpc = async ( method, params ) => {
		const headers = { 'Content-Type': 'application/json', Authorization: auth };
		if ( session ) {
			headers[ 'Mcp-Session-Id' ] = session;
			headers[ 'MCP-Protocol-Version' ] = '2025-11-25';
		}
		const notification = method.startsWith( 'notifications/' );
		const body = { jsonrpc: '2.0', method, ...( params ? { params } : {} ) };
		if ( ! notification ) {
			body.id = ++nextId;
		}
		const response = await fetch( endpoint, { method: 'POST', headers, body: JSON.stringify( body ) } );
		session = session || response.headers.get( 'mcp-session-id' );
		if ( notification ) {
			return null;
		}
		const json = await response.json();
		if ( json.error ) {
			throw new Error( `${ method }: ${ json.error.message }` );
		}
		return json.result;
	};
	const call = ( name, args ) => rpc( 'tools/call', { name, arguments: args } );

	try {
		await rpc( 'initialize', {
			protocolVersion: '2025-11-25',
			capabilities: {},
			clientInfo: { name: 'pediment-mcp-smoke', version: '1' },
		} );
		await rpc( 'notifications/initialized' );

		const { tools } = await rpc( 'tools/list' );
		const names = tools.map( ( tool ) => tool.name );
		for ( const name of EXPECTED ) {
			assert( names.includes( name ), `tools/list lacks ${ name }` );
		}

		const vocabulary = await call( 'pediment-get-vocabulary', {} );
		assert( ! vocabulary.isError, `get-vocabulary failed: ${ text( vocabulary ) }` );
		assert( vocabulary.structuredContent.styles.some( ( s ) => s.slug === 'card' ), 'get-vocabulary has no card style' );

		const written = await call( 'pediment-write-page', {
			title: 'MCP smoke',
			markup: '<!-- wp:paragraph -->\n<p>Hello from MCP.</p>\n<!-- /wp:paragraph -->',
		} );
		assert( ! written.isError, `write-page failed: ${ text( written ) }` );
		pageId = written.structuredContent.id;

		const checked = await call( 'pediment-check-page', { id: pageId } );
		assert( ! checked.isError && checked.structuredContent.errors.length === 0, `check-page reported errors: ${ text( checked ) }` );

		const rejected = await call( 'pediment-write-page', {
			title: 'MCP smoke bad',
			markup: '<!-- wp:group {"style":{"spacing":{"padding":{"top":"78px"}}}} -->\n<div class="wp-block-group" style="padding-top:78px"></div>\n<!-- /wp:group -->',
		} );
		assert( rejected.isError && /78px/.test( text( rejected ) ), `write-page accepted or hid a literal padding: ${ text( rejected ) }` );

		console.log( `MCP smoke OK — ${ names.length } tools, page ${ pageId } written and checked, violation reported.` );
	} finally {
		if ( pageId ) {
			wp( 'post', 'delete', String( pageId ), '--force' );
		}
		const uuids = wp( 'user', 'application-password', 'list', 'admin', '--name=mcp-smoke', '--field=uuid' );
		for ( const uuid of uuids.split( '\n' ).filter( Boolean ) ) {
			wp( 'user', 'application-password', 'delete', 'admin', uuid );
		}
	}
}

main().catch( ( error ) => {
	console.error( `MCP smoke FAILED: ${ error.message }` );
	process.exit( 1 );
} );
```

- [ ] **Step 6: Run the smoke test**

Run: `node tools/mcp-smoke.mjs`
Expected: `MCP smoke OK — 8 tools, page <id> written and checked, violation reported.` Then confirm cleanup: `npx wp-env run cli wp user application-password list admin` shows no `mcp-smoke` entry.

- [ ] **Step 7: Run it in CI**

In `.github/workflows/ci.yml`, job `phpunit`, add after the Polylang PHPUnit step and before the `env:stop` step:

```yaml
      - run: node tools/mcp-smoke.mjs
```

- [ ] **Step 8: Full suite, lint, commit**

```bash
npx wp-env run tests-wordpress --env-cwd=wp-content/plugins/pediment-ai ./vendor/bin/phpunit
composer lint -d plugin
git add plugin/src/Mcp/Server.php plugin/src/Bootstrap.php plugin/tests/phpunit/Mcp/ServerTest.php .wp-env.json tools/mcp-smoke.mjs .github/workflows/ci.yml
git commit -m "feat(mcp): expose Pediment abilities as an MCP server"
```

---

### Task 8: `client-kit` block validator

Read `.context/plan3-research-validator.md` first (it explains every trap below).

**Files:**
- Create: `client-kit/validator/wp-6.9/package.json`, `client-kit/validator/wp-6.9/package-lock.json` (from `.context/validator-lock/wp-6.9/`), and the same two for `wp-7.0` (from `.context/validator-lock/wp-7.0/`)
- Create: `client-kit/scripts/lib/block-check.mjs`, `client-kit/scripts/lib/wp-runtime.mjs`, `client-kit/scripts/validate-blocks.mjs`
- Modify: `.github/workflows/ci.yml` (new `validator` job)
- Test: `client-kit/tests/validate-blocks.test.mjs`

**Interfaces:**
- Produces: CLI `node client-kit/scripts/validate-blocks.mjs [--wp 6.9] [--file <path>] [--json]` (markup from `--file` or stdin; a `.php` file has its leading `<?php … ?>` header stripped). Exit 0 = valid, 1 = problems found, 2 = tool failure. Report JSON: `{ ok: boolean, wordpress: string, checked: number, invalid: list<{path, name, issues: list<string>}>, migrated: list<{path, name}>, notChecked: list<string>, jsonErrors: list<string>, loose: list<string> }`.
- `block-check.mjs` exports `commentJsonErrors( markup )`, `looseContent( markup )`, `walkBlocks( blocks, validate )`, `issueMessages( issues )`, `quietly( fn )`, `buildReport( markup, parse, validate, wordpress )`, `formatReport( report )`.
- `wp-runtime.mjs` exports `wpLine( version )`, `supportedLines()`, `ensureRuntime( line, options )`, `loadWordPress( dir )`.

- [ ] **Step 1: Add the pinned runtimes**

```bash
mkdir -p client-kit/validator/wp-6.9 client-kit/validator/wp-7.0
cp .context/validator-lock/wp-6.9/package.json .context/validator-lock/wp-6.9/package-lock.json client-kit/validator/wp-6.9/
cp .context/validator-lock/wp-7.0/package.json .context/validator-lock/wp-7.0/package-lock.json client-kit/validator/wp-7.0/
```

In each copied `package.json` and `package-lock.json`, rename `"name": "validator-probe"` (and the 7.0 equivalent) to `"pediment-block-validator-wp-6.9"` / `"pediment-block-validator-wp-7.0"` — in `package.json`, in the lockfile's top-level `name`, and in the lockfile's `packages[""].name`. Change nothing else. Verify each lockfile still installs: `cd client-kit/validator/wp-6.9 && npx -y npm@10 ci --ignore-scripts --no-audit --no-fund --dry-run && cd -` (same for 7.0); do not commit any `node_modules`.

- [ ] **Step 2: Write the failing tests**

`client-kit/tests/validate-blocks.test.mjs`:

```js
import { test } from 'node:test';
import assert from 'node:assert/strict';
import { execFileSync } from 'node:child_process';
import { fileURLToPath } from 'node:url';
import path from 'node:path';
import {
	commentJsonErrors,
	looseContent,
	walkBlocks,
	issueMessages,
	buildReport,
	formatReport,
} from '../scripts/lib/block-check.mjs';
import { wpLine, supportedLines } from '../scripts/lib/wp-runtime.mjs';
import { parseArgs } from '../scripts/validate-blocks.mjs';

const here = path.dirname( fileURLToPath( import.meta.url ) );
const cli = path.join( here, '..', 'scripts', 'validate-blocks.mjs' );

test( 'comment JSON errors name the block', () => {
	assert.deepEqual( commentJsonErrors( '<!-- wp:group {"a":} --><div></div><!-- /wp:group -->' ), [ 'core/group: block comment attributes are not valid JSON' ] );
	assert.deepEqual( commentJsonErrors( '<!-- wp:group {"a":{"b":1}} --><div></div><!-- /wp:group --><!-- wp:acme/x {"y":2} /-->' ), [] );
} );

test( 'loose content is only text outside top-level blocks', () => {
	assert.deepEqual( looseContent( '<!-- wp:paragraph --><p>x</p><!-- /wp:paragraph -->\n\n' ), [] );
	assert.deepEqual( looseContent( '<p>loose</p><!-- wp:paragraph --><p>x</p><!-- /wp:paragraph -->tail' ), [ '<p>loose</p>', 'tail' ] );
} );

test( 'walkBlocks separates invalid, migrated and unknown blocks', () => {
	const blocks = [
		{ name: 'core/group', isValid: true, innerBlocks: [ { name: 'core/paragraph', isValid: true, innerBlocks: [] } ] },
		{ name: 'core/paragraph', isValid: true, innerBlocks: [] },
		{ name: 'core/cover', isValid: false, validationIssues: [ { args: [ 'Expected attribute `class` of value `%s`, saw `%s`.', 'a', 'b' ] }, { args: [ 'Block validation failed for `%s` (%o).', 'core/cover', { name: 'core/cover' } ] } ], innerBlocks: [] },
		{ name: 'core/missing', isValid: true, attributes: { originalName: 'pediment/form' }, innerBlocks: [] },
	];
	const migrated = new Set( [ blocks[ 1 ] ] );
	const result = walkBlocks( blocks, ( block ) => ! migrated.has( block ) );
	assert.equal( result.checked, 5 );
	assert.deepEqual( result.invalid, [ { path: '[2] core/cover', name: 'core/cover', issues: [ 'Expected attribute `class` of value `a`, saw `b`.' ] } ] );
	assert.deepEqual( result.migrated, [ { path: '[1] core/paragraph', name: 'core/paragraph' } ] );
	assert.deepEqual( result.notChecked, [ 'pediment/form' ] );
} );

test( 'issueMessages drops the summary line', () => {
	assert.deepEqual( issueMessages( [ { args: [ 'Block validation failed for `%s` (%o).', 'x', {} ] } ] ), [] );
} );

test( 'buildReport and formatReport', () => {
	const parse = () => [ { name: 'core/paragraph', isValid: true, innerBlocks: [] } ];
	const report = buildReport( '<!-- wp:paragraph --><p>x</p><!-- /wp:paragraph -->', parse, () => true, '6.9' );
	assert.equal( report.ok, true );
	assert.match( formatReport( report ), /valid/i );

	const bad = buildReport( 'loose<!-- wp:paragraph --><p>x</p><!-- /wp:paragraph -->', parse, () => true, '6.9' );
	assert.equal( bad.ok, false );
	assert.match( formatReport( bad ), /outside/i );
} );

test( 'version lines and arguments', () => {
	assert.equal( wpLine( '6.9.4' ), '6.9' );
	assert.equal( wpLine( '7.0' ), '7.0' );
	assert.throws( () => wpLine( 'trunk' ) );
	assert.deepEqual( supportedLines(), [ '6.9', '7.0' ] );
	assert.deepEqual( parseArgs( [ '--wp', '7.0', '--file', 'a.html', '--json' ] ), { wp: '7.0', file: 'a.html', json: true } );
	assert.deepEqual( parseArgs( [] ), { wp: '6.9', file: null, json: false } );
} );

const e2e = process.env.PEDIMENT_VALIDATOR_E2E === '1';

test( 'end to end against the real WordPress 6.9 runtime', { skip: ! e2e }, () => {
	const run = ( markup ) => {
		try {
			return { code: 0, out: execFileSync( 'node', [ cli, '--json' ], { input: markup, encoding: 'utf8' } ) };
		} catch ( error ) {
			return { code: error.status, out: error.stdout };
		}
	};
	const valid = run( '<!-- wp:group {"className":"is-style-card","layout":{"type":"constrained"}} --><div class="wp-block-group is-style-card"><!-- wp:paragraph --><p>x</p><!-- /wp:paragraph --></div><!-- /wp:group -->' );
	assert.equal( valid.code, 0, valid.out );

	const migrated = run( '<!-- wp:paragraph --><div>Hi</div><!-- /wp:paragraph -->' );
	assert.equal( migrated.code, 1 );
	assert.equal( JSON.parse( migrated.out ).migrated.length, 1 );

	const invalid = run( '<!-- wp:group --><div class="wp-block-nope"><!-- wp:paragraph --><p>x</p><!-- /wp:paragraph --></div><!-- /wp:group -->' );
	assert.equal( invalid.code, 1 );
	assert.equal( JSON.parse( invalid.out ).invalid[ 0 ].name, 'core/group' );

	const unknown = run( '<!-- wp:pediment/form {"id":"x"} /-->' );
	assert.equal( unknown.code, 0 );
	assert.deepEqual( JSON.parse( unknown.out ).notChecked, [ 'pediment/form' ] );
} );
```

- [ ] **Step 3: Run to verify failure**

Run: `node --test client-kit/tests/validate-blocks.test.mjs`
Expected: FAIL — modules not found.

- [ ] **Step 4: Write `block-check.mjs`**

`client-kit/scripts/lib/block-check.mjs`:

```js
/**
 * Dependency-free checks and report logic for validate-blocks.mjs. The
 * WordPress-backed parse/validate functions are injected, so this module
 * runs under `npm run test:kit` without the editor stack.
 */

const TOKEN = /<!--\s+(\/)?wp:([a-z][a-z0-9_-]*(?:\/[a-z][a-z0-9_-]*)?)(?:\s+(\{[\s\S]*?\}))?\s+(\/)?-->/g;

const fullName = ( name ) => ( name.includes( '/' ) ? name : `core/${ name }` );

/**
 * Block comments whose attribute JSON does not parse (WordPress drops it silently).
 *
 * @param {string} markup
 * @return {string[]}
 */
export function commentJsonErrors( markup ) {
	const errors = [];
	for ( const match of markup.matchAll( TOKEN ) ) {
		if ( match[ 1 ] || ! match[ 3 ] ) {
			continue;
		}
		try {
			JSON.parse( match[ 3 ] );
		} catch {
			errors.push( `${ fullName( match[ 2 ] ) }: block comment attributes are not valid JSON` );
		}
	}
	return errors;
}

/**
 * Non-whitespace text outside top-level block comments (first 80 chars each).
 *
 * @param {string} markup
 * @return {string[]}
 */
export function looseContent( markup ) {
	const loose = [];
	let depth = 0;
	let last = 0;
	const take = ( from, to ) => {
		const piece = markup.slice( from, to ).trim();
		if ( piece ) {
			loose.push( piece.slice( 0, 80 ) );
		}
	};
	for ( const match of markup.matchAll( TOKEN ) ) {
		if ( depth === 0 ) {
			take( last, match.index );
		}
		if ( match[ 1 ] ) {
			depth = Math.max( 0, depth - 1 );
		} else if ( ! match[ 4 ] ) {
			depth++;
		}
		last = match.index + match[ 0 ].length;
	}
	if ( depth === 0 ) {
		take( last, markup.length );
	}
	return loose;
}

function format( template, values ) {
	const rest = [ ...values ];
	return String( template ).replace( /%[sdo]/g, () => {
		const value = rest.shift();
		return value && typeof value === 'object' ? value.name ?? '[object]' : String( value );
	} );
}

/**
 * Readable messages from a block's validationIssues ({log, args} entries).
 *
 * @param {Array<{args: any[]}>} issues
 * @return {string[]}
 */
export function issueMessages( issues = [] ) {
	return issues
		.map( ( { args = [] } ) => format( args[ 0 ], args.slice( 1 ) ).split( '\n' )[ 0 ] )
		.filter( ( message ) => ! message.startsWith( 'Block validation failed' ) );
}

/**
 * Walks parsed blocks. `validate( block )` is the strict re-check
 * (@wordpress/blocks validateBlock(...)[0]): false means parse() only got a
 * valid block by migrating it through a deprecation.
 *
 * @param {object[]} blocks
 * @param {(block: object) => boolean} validate
 */
export function walkBlocks( blocks, validate ) {
	const result = { checked: 0, invalid: [], migrated: [], notChecked: [] };
	const visit = ( list, prefix ) => {
		list.forEach( ( block, index ) => {
			const missing = block.name === 'core/missing';
			const label = missing ? block.attributes?.originalName ?? 'core/missing' : block.name;
			const here = `${ prefix }[${ index }] ${ label }`;
			result.checked++;
			if ( missing ) {
				result.notChecked.push( block.attributes?.originalName ?? '(unknown)' );
			} else if ( block.isValid === false ) {
				result.invalid.push( { path: here, name: block.name, issues: issueMessages( block.validationIssues ) } );
			} else if ( validate( block ) === false ) {
				result.migrated.push( { path: here, name: block.name } );
			}
			visit( block.innerBlocks ?? [], `${ here } > ` );
		} );
	};
	visit( blocks, '' );
	return result;
}

/**
 * Runs fn with console output captured and discarded (block validation logs
 * hundreds of lines per invalid block).
 *
 * @template T
 * @param {() => T} fn
 * @return {T}
 */
export function quietly( fn ) {
	const saved = { ...console };
	const noop = () => {};
	Object.assign( console, { error: noop, warn: noop, info: noop, log: noop, groupCollapsed: noop, group: noop, groupEnd: noop } );
	try {
		return fn();
	} finally {
		Object.assign( console, saved );
	}
}

/**
 * The full report.
 *
 * @param {string} markup
 * @param {(markup: string) => object[]} parse
 * @param {(block: object) => boolean} validate
 * @param {string} wordpress WP line used.
 */
export function buildReport( markup, parse, validate, wordpress ) {
	const jsonErrors = commentJsonErrors( markup );
	const loose = looseContent( markup );
	const walked = quietly( () => walkBlocks( parse( markup ), validate ) );
	const ok = jsonErrors.length === 0 && loose.length === 0 && walked.invalid.length === 0 && walked.migrated.length === 0;
	return { ok, wordpress, ...walked, jsonErrors, loose };
}

/**
 * Human-readable report.
 *
 * @param {ReturnType<typeof buildReport>} report
 */
export function formatReport( report ) {
	const lines = [];
	lines.push( report.ok ? `✔ ${ report.checked } block(s) valid for WordPress ${ report.wordpress }.` : `✖ Problems found (WordPress ${ report.wordpress }):` );
	for ( const error of report.jsonErrors ) {
		lines.push( `  JSON  ${ error }` );
	}
	for ( const piece of report.loose ) {
		lines.push( `  LOOSE content outside a block: ${ piece }` );
	}
	for ( const block of report.invalid ) {
		lines.push( `  INVALID ${ block.path }` );
		for ( const issue of block.issues ) {
			lines.push( `      ${ issue }` );
		}
	}
	for ( const block of report.migrated ) {
		lines.push( `  STALE ${ block.path } — only valid through a deprecation; re-save it in its current form` );
	}
	if ( report.notChecked.length ) {
		lines.push( `  not checked (unknown to the validator): ${ [ ...new Set( report.notChecked ) ].join( ', ' ) }` );
	}
	return lines.join( '\n' ) + '\n';
}
```

- [ ] **Step 5: Write `wp-runtime.mjs`**

`client-kit/scripts/lib/wp-runtime.mjs`:

```js
/**
 * Installs (once, into a cache) and loads the WordPress block runtime used
 * to validate markup in Node. The runtime is pinned per WordPress line by a
 * committed package-lock.json under client-kit/validator/wp-<line>/.
 */
import { createHash } from 'node:crypto';
import { execFileSync } from 'node:child_process';
import { copyFileSync, existsSync, mkdirSync, readdirSync, readFileSync, writeFileSync } from 'node:fs';
import { createRequire } from 'node:module';
import { homedir } from 'node:os';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const VALIDATOR_DIR = path.join( path.dirname( fileURLToPath( import.meta.url ) ), '..', '..', 'validator' );

/**
 * "6.9.4" → "6.9".
 *
 * @param {string} version
 */
export function wpLine( version ) {
	const match = /^(\d+)\.(\d+)/.exec( String( version ) );
	if ( ! match ) {
		throw new Error( `Not a WordPress version: ${ version }` );
	}
	return `${ match[ 1 ] }.${ match[ 2 ] }`;
}

/** WordPress lines with a pinned runtime, sorted. */
export function supportedLines() {
	return readdirSync( VALIDATOR_DIR )
		.filter( ( entry ) => entry.startsWith( 'wp-' ) && existsSync( path.join( VALIDATOR_DIR, entry, 'package-lock.json' ) ) )
		.map( ( entry ) => entry.slice( 3 ) )
		.sort();
}

/**
 * Returns the cache dir holding an installed runtime for a WP line,
 * installing it on first use.
 *
 * @param {string} line
 * @param {{cacheRoot?: string, log?: (message: string) => void}} options
 */
export function ensureRuntime( line, options = {} ) {
	const source = path.join( VALIDATOR_DIR, `wp-${ line }` );
	if ( ! existsSync( path.join( source, 'package-lock.json' ) ) ) {
		throw new Error( `No validator runtime for WordPress ${ line }. Supported: ${ supportedLines().join( ', ' ) }` );
	}
	const cacheRoot =
		options.cacheRoot ??
		process.env.PEDIMENT_VALIDATOR_CACHE ??
		path.join( process.env.XDG_CACHE_HOME ?? path.join( homedir(), '.cache' ), 'pediment', 'validator' );
	const log = options.log ?? ( ( message ) => process.stderr.write( `${ message }\n` ) );
	const hash = createHash( 'sha256' ).update( readFileSync( path.join( source, 'package-lock.json' ) ) ).digest( 'hex' ).slice( 0, 8 );
	const dir = path.join( cacheRoot, `wp-${ line }-${ hash }` );
	if ( ! existsSync( path.join( dir, '.ok' ) ) ) {
		log( `Installing the block validator runtime for WordPress ${ line } (one time, about 700 MB)…` );
		mkdirSync( dir, { recursive: true } );
		copyFileSync( path.join( source, 'package.json' ), path.join( dir, 'package.json' ) );
		copyFileSync( path.join( source, 'package-lock.json' ), path.join( dir, 'package-lock.json' ) );
		execFileSync( 'npm', [ 'ci', '--ignore-scripts', '--no-audit', '--no-fund' ], { cwd: dir, stdio: [ 'ignore', 'ignore', 'inherit' ] } );
		writeFileSync( path.join( dir, '.ok' ), new Date().toISOString() );
	}
	return dir;
}

/**
 * Loads @wordpress/blocks with core blocks registered, from a runtime dir.
 * Globals must exist before the first @wordpress require; the CJS builds
 * are used because the ESM builds use directory imports Node rejects.
 *
 * @param {string} dir
 * @return {{parse: Function, validateBlock: Function}}
 */
export function loadWordPress( dir ) {
	const require = createRequire( path.join( dir, 'index.js' ) );
	const { JSDOM, VirtualConsole } = require( 'jsdom' );
	const { window } = new JSDOM( '<!doctype html><html><body></body></html>', {
		url: 'http://localhost/',
		virtualConsole: new VirtualConsole(),
	} );
	Object.defineProperty( globalThis, 'navigator', { value: window.navigator, configurable: true } );
	for ( const key of Object.getOwnPropertyNames( window ) ) {
		if ( key in globalThis ) {
			continue;
		}
		Object.defineProperty( globalThis, key, { configurable: true, get: () => window[ key ] } );
	}
	window.matchMedia ??= () => ( { matches: false, addListener() {}, removeListener() {}, addEventListener() {}, removeEventListener() {} } );
	globalThis.requestAnimationFrame ??= ( callback ) => setTimeout( callback, 0 );

	const { registerCoreBlocks } = require( '@wordpress/block-library' );
	const { parse, validateBlock } = require( '@wordpress/blocks' );
	const quiet = { ...console };
	Object.assign( console, { error() {}, warn() {}, info() {} } );
	try {
		registerCoreBlocks();
	} finally {
		Object.assign( console, quiet );
	}
	return { parse, validateBlock };
}
```

- [ ] **Step 6: Write the CLI**

`client-kit/scripts/validate-blocks.mjs`:

```js
#!/usr/bin/env node
/**
 * Validates Gutenberg block markup the way the block editor does, before an
 * agent writes it to a site (pediment-write-page) or a pattern file.
 *
 * Usage:
 *   node validate-blocks.mjs [--wp 6.9] [--file page.html] [--json]
 *   cat page.html | node validate-blocks.mjs --wp 7.0
 *
 * --wp     WordPress version or line of the target site (get-vocabulary reports it). Default 6.9.
 * --file   Markup file; a .php pattern file has its leading <?php … ?> header removed.
 * --json   Machine-readable report.
 *
 * Exit codes: 0 valid, 1 problems found, 2 the tool itself failed.
 * First run per WordPress line installs a pinned runtime into
 * ~/.cache/pediment/validator (override with PEDIMENT_VALIDATOR_CACHE).
 */
import { readFileSync } from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import { buildReport, formatReport } from './lib/block-check.mjs';
import { ensureRuntime, loadWordPress, wpLine } from './lib/wp-runtime.mjs';

/**
 * @param {string[]} argv
 * @return {{wp: string, file: string|null, json: boolean}}
 */
export function parseArgs( argv ) {
	const options = { wp: '6.9', file: null, json: false };
	for ( let i = 0; i < argv.length; i++ ) {
		const arg = argv[ i ];
		if ( arg === '--wp' ) {
			options.wp = argv[ ++i ];
		} else if ( arg === '--file' ) {
			options.file = argv[ ++i ];
		} else if ( arg === '--json' ) {
			options.json = true;
		} else {
			throw new Error( `Unknown argument: ${ arg }` );
		}
	}
	return options;
}

function readMarkup( file ) {
	const raw = file ? readFileSync( file, 'utf8' ) : readFileSync( 0, 'utf8' );
	return file && file.endsWith( '.php' ) ? raw.replace( /^<\?php[\s\S]*?\?>\s*/, '' ) : raw;
}

function main() {
	const options = parseArgs( process.argv.slice( 2 ) );
	const line = wpLine( options.wp );
	const markup = readMarkup( options.file );
	const wordpress = loadWordPress( ensureRuntime( line ) );
	const report = buildReport( markup, wordpress.parse, ( block ) => wordpress.validateBlock( block )[ 0 ], line );
	process.stdout.write( options.json ? `${ JSON.stringify( report, null, 2 ) }\n` : formatReport( report ) );
	process.exit( report.ok ? 0 : 1 );
}

if ( process.argv[ 1 ] && path.resolve( process.argv[ 1 ] ) === fileURLToPath( import.meta.url ) ) {
	try {
		main();
	} catch ( error ) {
		process.stderr.write( `validate-blocks: ${ error.message }\n` );
		process.exit( 2 );
	}
}
```

- [ ] **Step 7: Run the offline tests, then the real runtime**

```bash
node --test client-kit/tests/validate-blocks.test.mjs
npm run test:kit
PEDIMENT_VALIDATOR_E2E=1 node --test client-kit/tests/validate-blocks.test.mjs
```

Expected: offline tests PASS (e2e skipped); `test:kit` PASS; with `PEDIMENT_VALIDATOR_E2E=1` the first run installs the runtime (~15–60 s) and the e2e test PASSES. Also run `echo '<!-- wp:paragraph --><p>x</p><!-- /wp:paragraph -->' | node client-kit/scripts/validate-blocks.mjs --wp 7.0` once and paste the output. If the real runtime behaves differently from the research notes (duplicate stores, wrong verdicts), stop and report BLOCKED with the output — do not regenerate the lockfiles.

- [ ] **Step 8: CI job**

Add to `.github/workflows/ci.yml` under `jobs:`:

```yaml
  validator:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v4
      - uses: actions/setup-node@v4
        with:
          node-version: "20"
      - uses: actions/cache@v4
        with:
          path: ~/.cache/pediment/validator
          key: validator-${{ hashFiles('client-kit/validator/**/package-lock.json') }}
      - run: PEDIMENT_VALIDATOR_E2E=1 node --test client-kit/tests/validate-blocks.test.mjs
```

- [ ] **Step 9: Commit**

```bash
git add client-kit/validator client-kit/scripts/lib/block-check.mjs client-kit/scripts/lib/wp-runtime.mjs client-kit/scripts/validate-blocks.mjs client-kit/tests/validate-blocks.test.mjs .github/workflows/ci.yml
git commit -m "feat(kit): validate block markup like the editor does"
```

---

### Task 9: Documentation, skill step and roadmap

**Files:**
- Create: `docs/mcp.md`
- Modify: `client-kit/skills/port-page/SKILL.md` (frontmatter `allowed-tools` + one step), `AGENTS.md` (Hard rules), `plugin/README.md` (one section), `docs/superpowers/plans/2026-10-14-roadmap.md` (status), `docs/design-layer.md` (link to mcp.md)
- Test: `npm run test:kit`

**Interfaces:** none.

- [ ] **Step 1: Write `docs/mcp.md`**

```markdown
# Building pages with AI through MCP

Pediment exposes eight tools to AI agents through the WordPress MCP Adapter.
Agents build and change pages on the live site; Pediment enforces the content
and design rules on the server.

## Set up a site

1. Install and activate the **MCP Adapter** plugin (Plugins → Add New →
   search "MCP Adapter", or upload `mcp-adapter.0.7.0.zip`). Pediment needs
   WordPress 6.9 or later.
2. Create an application password: Users → Profile → Application Passwords →
   name it "Claude", copy the password.
3. Connect Claude Code (one line; replace SITE, USER and the password):

       claude mcp add --transport http pediment "https://SITE/wp-json/pediment/v1/mcp" --header "Authorization: Basic $(printf 'USER:APP PASSWORD' | base64)"

   On a site with plain permalinks use `https://SITE/?rest_route=/pediment/v1/mcp`.
   `claude mcp list` shows whether it connected.

Local dev: the root wp-env installs the adapter automatically. Get the URL with
`npx wp-env run cli wp option get siteurl` and create a password with
`npx wp-env run cli wp user application-password create admin claude --porcelain`.

## Tools

| Tool | Does |
| --- | --- |
| `pediment-get-vocabulary` | Tokens, named styles (with layer), allowed blocks, rules, WordPress version, languages. Call it first. |
| `pediment-list-pages` | Pages of every language. Filters: language, parent, status. |
| `pediment-read-page` | One page with raw block markup and translations. |
| `pediment-check-page` | Content-rule errors and warnings, blocks the editor reported invalid, legacy blocks still on the page. |
| `pediment-write-page` | Create (draft by default) or update a page. Rejected with every violation listed if a rule fails. Keeps a revision. |
| `pediment-rollback-page` | Restore one of the page's own revisions. |
| `pediment-upsert-style` | Create or change a site-layer style (see design-layer.md). |
| `pediment-delete-style` | Delete a site-layer style. |

Page tools need the `edit_pages` capability (and rights on the page); style
tools need `edit_theme_options`.

## Rules an agent must follow

- Only allowed blocks (`get-vocabulary` → `allowedBlocks`); no `core/html`, no
  content outside blocks.
- No literal colours, font sizes, families, spacing, radius or shadows in block
  attributes: use presets or a named style (`is-style-<slug>`).
- Every `is-style-<slug>` must exist for that block.
- Name container blocks (`metadata.name`) so the editor's list view is readable.
- Validate static markup before writing:

      node <client-kit>/scripts/validate-blocks.mjs --wp <version from get-vocabulary> --file page.html

  Exit 0 means the editor will accept it. The first run downloads a pinned
  WordPress runtime (~700 MB, once per WordPress line).
- After writing, open the page in the block editor once (or ask a person to);
  `check-page` then also reports any block the editor rejected.

## Acceptance test

Repeat the 2026-10-14 experiment through these tools on the Workation dev
environment: rebuild the home page from screenshots with core blocks and named
styles, then apply three design changes (card radius, eyebrow style, section
padding). Each change must take exactly one `pediment-upsert-style` call and
zero page writes. Record the result in this file with the date.
```

- [ ] **Step 2: Skill step**

In `client-kit/skills/port-page/SKILL.md`:
- Extend the frontmatter with `allowed-tools: Bash(node ${CLAUDE_SKILL_DIR}/../../scripts/validate-blocks.mjs:*)` (if `allowed-tools` already exists, append this entry; keep its existing format).
- Before the step that seeds the pattern, add a step: "Validate the pattern before seeding: `node "<skill-dir>/../../scripts/validate-blocks.mjs" --file patterns/<key>.php`. Fix every INVALID, STALE, LOOSE and JSON line and re-run until it exits 0. Blocks listed under "not checked" are Pediment or theme blocks the validator does not know; that is expected."

- [ ] **Step 3: Hard rule, README, design-layer link, roadmap**

`AGENTS.md`, Hard rules — add:

`- AI page and style changes on a live site go through the Pediment MCP tools (see [docs/mcp.md](docs/mcp.md)); validate markup with client-kit's validate-blocks before writing.`

`plugin/README.md` — add a section after "Seeding":

```markdown
## AI tools (MCP)

With the MCP Adapter plugin active, Pediment serves an MCP server at
`/wp-json/pediment/v1/mcp` with eight tools to read and write pages and
design styles under Pediment's rules. See [docs/mcp.md](../docs/mcp.md).
```

`docs/design-layer.md` — at the end of the "Layers" section add: `AI agents change the site layer with the pediment-upsert-style tool — see [mcp.md](mcp.md).`

`docs/superpowers/plans/2026-10-14-roadmap.md` — in the table, set Plan 1 and Plan 2 status to `**done**` (branch `bergjs/elementor-pediment-necessity`), Plan 3 to `**written** — 2026-10-14-plan-3-mcp-abilities.md`; under "Notes for the plans still to write", replace the Plan 3 bullet list with: `Plan 3 is written. The live-first rewrite of /pediment:port-page (build through MCP instead of patterns + seed) moves to Plan 5.`

- [ ] **Step 4: Verify and commit**

```bash
npm run test:kit
git add docs/mcp.md client-kit/skills/port-page/SKILL.md AGENTS.md plugin/README.md docs/design-layer.md docs/superpowers/plans/2026-10-14-roadmap.md
git commit -m "docs(mcp): document the MCP tools and validator"
```

---

## Final verification

- [ ] Run everything on the final tree:

```bash
npx wp-env run tests-wordpress --env-cwd=wp-content/plugins/pediment-ai ./vendor/bin/phpunit
npx wp-env run tests-wordpress --env-cwd=wp-content/plugins/pediment-ai ./vendor/bin/phpunit -c phpunit-polylang.xml.dist
composer lint -d plugin
npm run lint:js
npm run lint:blocks
npm run lint:colors
npm run test:kit
PEDIMENT_VALIDATOR_E2E=1 node --test client-kit/tests/validate-blocks.test.mjs
node tools/mcp-smoke.mjs
```

Expected: all pass. The WPML suite and Playwright e2e run only in CI; say so.
