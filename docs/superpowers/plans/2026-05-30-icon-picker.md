# Icon Picker Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Replace the plain text icon inputs in the `feature` and `mega-menu` blocks with a shared, searchable grid icon picker backed by the full ~1,500-icon regular Phosphor catalog, rendered inline at render time with no sprite and no per-edit sync.

**Architecture:** A build-once script generates two committed data files (`phosphor-icons.php` + `.json`) mapping each Phosphor slug to its inner SVG markup. `pediment_icon()` is rewritten to look a slug up in the PHP map and inline its SVG (with a stable `data-icon` attribute), replacing the old `<use href="#ph-…">` sprite reference; the sprite file and its print/iframe-injection machinery are deleted. A shared React `IconPicker` component (button + popover + search + grid) lazy-fetches the JSON catalog and writes the chosen slug to the block attribute. Both consuming blocks switch from `TextControl` to `IconPicker`.

**Tech Stack:** WordPress block theme, `@wordpress/scripts` (webpack/wp-scripts), TypeScript/TSX, `@wordpress/components`, PHP 8.1, PHPUnit 9.6 via `wp-env`.

**Design spec:** `docs/superpowers/specs/2026-05-30-icon-picker-design.md`

---

## Refinements over the spec (apply these as written here)

Two implementation details refine the approved spec; this plan is authoritative for them:

1. **Generation method:** `tools/build-phosphor-data.sh` uses `npm pack @phosphor-icons/core@2.1.1` (download + extract once), not ~1,500 individual HTTP requests. Slug = filename of each `package/assets/regular/*.svg` without the extension.
2. **`data-icon` attribute:** the inline `<svg>` emitted by `pediment_icon()` carries `data-icon="<slug>"`. This gives tests and CSS a stable hook instead of matching raw SVG path data, and replaces the old `#ph-<slug>` assertion target.

---

## File Structure

| File | Responsibility |
| --- | --- |
| `tools/build-phosphor-data.sh` | **new** — one-shot generator; downloads Phosphor core, writes the two data files |
| `assets/icons/phosphor-icons.php` | **new (generated, committed)** — `return ['slug' => '<inner svg>', …]` |
| `assets/icons/phosphor-icons.json` | **new (generated, committed)** — same map as JSON for the editor |
| `inc/icons.php` | rewrite `pediment_icon()`; add `pediment_icon_map()`; delete sprite read/print/enqueue; add editor catalog-URL inline script |
| `src/components/icon-picker/filter.ts` | **new** — pure `filterIcons()` helper (unit-tested) |
| `src/components/icon-picker/index.tsx` | **new** — `<IconPicker>` React component |
| `src/blocks/feature/edit.tsx` | swap `TextControl` → `<IconPicker>`; inline canvas preview |
| `src/blocks/feature/render.php` | switch the "more" link's raw `<use>` arrow to `pediment_icon()` |
| `src/blocks/feature/block.json` | drop `enum`; clean `description` |
| `src/blocks/mega-menu/edit.tsx` | swap per-column `TextControl` → `<IconPicker>`; drop stale help text; inline preview |
| `src/blocks/hero/render.php` | switch raw `<use href="#ph-check-circle">` to `pediment_icon()` |
| `tools/build-phosphor-sprite.sh` | **delete** |
| `assets/icons/phosphor-sprite.svg` | **delete** |
| `tests/phpunit/IconsTest.php` | rewrite for inline-render + map behaviour |
| `tests/phpunit/BlockRender/FeatureGridTest.php` | update icon assertions to `data-icon` |
| `tests/phpunit/BlockRender/BlogIndexTest.php` | update icon assertion to `data-icon` |
| `tests/phpunit/BlockRender/MegaMenuTest.php` | update icon regex to `data-icon` |

**How to run the test suites (used throughout this plan):**
- PHP: `npx wp-env run tests-wordpress --env-cwd=wp-content/themes/pediment vendor/bin/phpunit`
- A single PHP test: append `--filter <TestClassOrMethod>` to the command above.
- JS unit: `npm run test:js` (wp-scripts/jest; jsdom preset ships with `@wordpress/scripts`).
- Build: `npm run build`
- Typecheck: `npx tsc --noEmit`
- JS lint: `npm run lint:js`

> **Note on environment:** `wp-env` must be running (`npm run env:start`). Per project convention, manual/UI verification happens in the **child theme's** wp-env on port 8890; the automated commands above run against this theme's own `wp-env`.

---

## Task 1: Generate the Phosphor icon data files

**Files:**
- Create: `tools/build-phosphor-data.sh`
- Create (generated): `assets/icons/phosphor-icons.php`, `assets/icons/phosphor-icons.json`

- [ ] **Step 1: Write the generator script**

Create `tools/build-phosphor-data.sh`:

```bash
#!/usr/bin/env bash
# Regenerates assets/icons/phosphor-icons.{php,json} from Phosphor core
# (regular weight, MIT). Run manually when bumping the Phosphor version.
set -euo pipefail

VER="2.1.1"
OUT_PHP="assets/icons/phosphor-icons.php"
OUT_JSON="assets/icons/phosphor-icons.json"

tmp="$(mktemp -d)"
trap 'rm -rf "$tmp"' EXIT

echo "Downloading @phosphor-icons/core@${VER} via npm pack…"
( cd "$tmp" && npm pack "@phosphor-icons/core@${VER}" >/dev/null )
tar -xzf "$tmp"/*.tgz -C "$tmp"

src="$tmp/package/assets/regular"
if [ ! -d "$src" ]; then
  echo "✗ expected directory not found: $src" >&2
  exit 1
fi

php_body=""
json_body=""
first=1
count=0
for f in "$src"/*.svg; do
  slug="$(basename "$f" .svg)"
  # Extract the inner markup (everything between the outer <svg …> and </svg>).
  inner="$(tr -d '\n' < "$f" | sed -E 's#.*<svg[^>]*>(.*)</svg>.*#\1#')"
  # PHP single-quoted string: escape backslashes then single quotes.
  php_esc="${inner//\\/\\\\}"
  php_esc="${php_esc//\'/\\\'}"
  php_body+=$'\t'"'${slug}' => '${php_esc}',"$'\n'
  # JSON string: escape backslashes then double quotes.
  json_esc="${inner//\\/\\\\}"
  json_esc="${json_esc//\"/\\\"}"
  if [ "$first" -eq 1 ]; then first=0; else json_body+=","; fi
  json_body+="\"${slug}\":\"${json_esc}\""
  count=$((count + 1))
done

{
  printf '<?php\n'
  printf '// Generated by tools/build-phosphor-data.sh from @phosphor-icons/core@%s. Do not edit.\n' "$VER"
  printf 'return array(\n'
  printf '%s' "$php_body"
  printf ");\n"
} > "$OUT_PHP"

printf '{%s}\n' "$json_body" > "$OUT_JSON"

echo "wrote $OUT_PHP and $OUT_JSON ($count icons, $(wc -c < "$OUT_JSON") JSON bytes)"
```

- [ ] **Step 2: Make it executable and run it**

Run:
```bash
chmod +x tools/build-phosphor-data.sh
./tools/build-phosphor-data.sh
```
Expected: prints `wrote assets/icons/phosphor-icons.php and assets/icons/phosphor-icons.json (NNNN icons, …)` with `NNNN` around 1,500.

- [ ] **Step 3: Sanity-check the generated PHP is valid and contains known slugs**

Run:
```bash
php -l assets/icons/phosphor-icons.php
php -r '$m = require "assets/icons/phosphor-icons.php"; echo count($m), " icons\n"; foreach (["trend-up","gear","arrow-right","rocket","check-circle"] as $s) { echo $s, ": ", isset($m[$s]) ? "ok" : "MISSING", "\n"; }'
```
Expected: `No syntax errors detected`, a count ~1,500, and every listed slug prints `ok`.

- [ ] **Step 4: Sanity-check the JSON is valid and matches**

Run:
```bash
php -r '$j = json_decode(file_get_contents("assets/icons/phosphor-icons.json"), true); $p = require "assets/icons/phosphor-icons.php"; echo "json=", count($j), " php=", count($p), "\n"; echo ($j === $p) ? "MATCH\n" : "MISMATCH\n";'
```
Expected: `json=NNNN php=NNNN` (equal) and `MATCH`.

- [ ] **Step 5: Commit**

```bash
git add tools/build-phosphor-data.sh assets/icons/phosphor-icons.php assets/icons/phosphor-icons.json
git commit -m "feat(icons): generate full Phosphor catalog data files"
```

---

## Task 2: Rewrite `pediment_icon()` for inline rendering

**Files:**
- Modify: `inc/icons.php`
- Test: `tests/phpunit/IconsTest.php`

- [ ] **Step 1: Rewrite the test file**

Replace the entire contents of `tests/phpunit/IconsTest.php` with:

```php
<?php

class IconsTest extends WP_UnitTestCase {
	public function test_pediment_icon_returns_inline_svg_with_data_icon() {
		$html = pediment_icon( 'arrow-right' );
		$this->assertStringContainsString( '<svg class="i"', $html );
		$this->assertStringContainsString( 'data-icon="arrow-right"', $html );
		$this->assertStringContainsString( 'viewBox="0 0 256 256"', $html );
		// Inner markup from the catalog is inlined (no sprite <use> reference).
		$this->assertStringContainsString( '<path', $html );
		$this->assertStringNotContainsString( '<use', $html );
	}

	public function test_pediment_icon_accepts_extra_class() {
		$html = pediment_icon( 'bank', 'brand-mark' );
		$this->assertStringContainsString( 'class="i brand-mark"', $html );
	}

	public function test_pediment_icon_sanitizes_name() {
		$html = pediment_icon( 'arrow-right"/><script>' );
		// Sanitized to "arrow-rightscript" (non [a-z0-9-] stripped), which is
		// not a real slug, so the helper returns nothing rather than injecting.
		$this->assertSame( '', $html );
		$this->assertStringNotContainsString( '<script>', $html );
	}

	public function test_pediment_icon_returns_empty_for_unknown_slug() {
		$this->assertSame( '', pediment_icon( 'definitely-not-a-real-icon' ) );
	}

	public function test_pediment_icon_returns_empty_for_empty_name() {
		$this->assertSame( '', pediment_icon( '' ) );
	}

	public function test_icon_map_contains_expected_slugs() {
		$map = pediment_icon_map();
		$this->assertIsArray( $map );
		$this->assertArrayHasKey( 'trend-up', $map );
		$this->assertArrayHasKey( 'gear', $map );
	}
}
```

- [ ] **Step 2: Run the tests to verify they fail**

Run: `npx wp-env run tests-wordpress --env-cwd=wp-content/themes/pediment vendor/bin/phpunit --filter IconsTest`
Expected: FAIL — `pediment_icon_map()` is undefined and `pediment_icon()` still emits `<use>`.

- [ ] **Step 3: Rewrite `inc/icons.php`**

Replace the entire contents of `inc/icons.php` with:

```php
<?php
/**
 * Phosphor icon helper.
 *
 * Icons are rendered inline from a generated slug → SVG-markup map
 * (assets/icons/phosphor-icons.php), produced by tools/build-phosphor-data.sh.
 *
 * @package Pediment
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Return the slug → inner-SVG-markup map, loaded once per request.
 *
 * @return array<string,string> Map of icon slug to inner SVG markup, or [] if missing.
 */
function pediment_icon_map(): array {
	static $map = null;
	if ( null === $map ) {
		$file = get_theme_file_path( 'assets/icons/phosphor-icons.php' );
		$map  = is_readable( $file ) ? (array) require $file : array();
	}
	return $map;
}

/**
 * Return an inline SVG for a Phosphor icon slug.
 *
 * @param string $name        Phosphor icon slug (without the ph- prefix).
 * @param string $extra_class Optional extra CSS class.
 * @return string Safe HTML, or '' if the slug is unknown.
 */
function pediment_icon( $name, $extra_class = '' ) {
	$slug = preg_replace( '/[^a-z0-9-]/', '', strtolower( (string) $name ) );
	$map  = pediment_icon_map();
	if ( '' === $slug || ! isset( $map[ $slug ] ) ) {
		return '';
	}
	$class = 'i' . ( '' !== $extra_class ? ' ' . sanitize_html_class( $extra_class ) : '' );
	return sprintf(
		'<svg class="%s" viewBox="0 0 256 256" data-icon="%s" aria-hidden="true" focusable="false">%s</svg>',
		esc_attr( $class ),
		esc_attr( $slug ),
		$map[ $slug ] // Theme-controlled trusted markup (same trust model as the old sprite).
	);
}
```

- [ ] **Step 4: Run the tests to verify they pass**

Run: `npx wp-env run tests-wordpress --env-cwd=wp-content/themes/pediment vendor/bin/phpunit --filter IconsTest`
Expected: PASS (all 6 tests).

- [ ] **Step 5: Commit**

```bash
git add inc/icons.php tests/phpunit/IconsTest.php
git commit -m "feat(icons): render icons inline from generated map; drop sprite use"
```

---

## Task 3: Switch raw `#ph-` consumers to `pediment_icon()` and update render tests

The sprite is gone after Task 2, so the two render templates that still hardcode `<use href="#ph-…">` would render broken icons. Fix them, and update the render tests that assert the old `#ph-` markup.

**Files:**
- Modify: `src/blocks/feature/render.php`, `src/blocks/hero/render.php`
- Test: `tests/phpunit/BlockRender/FeatureGridTest.php`, `tests/phpunit/BlockRender/BlogIndexTest.php`, `tests/phpunit/BlockRender/MegaMenuTest.php`

- [ ] **Step 1: Update the render tests to assert `data-icon`**

In `tests/phpunit/BlockRender/FeatureGridTest.php`, replace these two lines:
```php
		$this->assertStringContainsString( 'href="#ph-gear"', $html );
		$this->assertStringContainsString( 'href="#ph-stack"', $html );
```
with:
```php
		$this->assertStringContainsString( 'data-icon="gear"', $html );
		$this->assertStringContainsString( 'data-icon="stack"', $html );
```

In `tests/phpunit/BlockRender/BlogIndexTest.php`, replace:
```php
		$this->assertStringContainsString( '#ph-arrow-right', $html );
```
with:
```php
		$this->assertStringContainsString( 'data-icon="arrow-right"', $html );
```

In `tests/phpunit/BlockRender/MegaMenuTest.php`, replace the regex assertion:
```php
		$this->assertMatchesRegularExpression(
			'/<p class="starter-mega-column__heading">[^<]*<svg[^<]*<use[^<]*ph-tag/',
			$html
		);
```
with:
```php
		$this->assertMatchesRegularExpression(
			'/<p class="starter-mega-column__heading">[^<]*<svg[^>]*data-icon="tag"/',
			$html
		);
```

> The MegaMenu test uses `'icon' => 'tag'`; confirm `tag` exists in the catalog (`php -r '$m=require "assets/icons/phosphor-icons.php"; var_dump(isset($m["tag"]));'` → `true`). It is a standard Phosphor regular icon.

- [ ] **Step 2: Run the three render tests to verify they now fail**

Run:
```bash
npx wp-env run tests-wordpress --env-cwd=wp-content/themes/pediment vendor/bin/phpunit --filter 'FeatureGridTest|BlogIndexTest|MegaMenuTest'
```
Expected: FAIL — `feature/render.php` and the mega/blog renders still emit `#ph-` for the consumers fixed below (FeatureGrid/Blog actually pass already since they go through `pediment_icon`, but feature's "more" arrow and hero are raw). Note which assertions fail; the data-icon assertions for icons routed through `pediment_icon()` should already pass after Task 2.

> Rationale: `feature-grid`, `blog-index`, and `mega-menu` column icons already call `pediment_icon()`, so their `data-icon` assertions pass once Task 2 landed. This step mainly confirms the updated assertions match the new output. The raw `<use>` sites fixed below are the "more" arrow in `feature/render.php` and the tick in `hero/render.php`.

- [ ] **Step 3: Fix the raw `<use>` in `feature/render.php`**

In `src/blocks/feature/render.php`, replace this line (inside the link block, currently line ~34):
```php
			<svg class="i" aria-hidden="true" focusable="false"><use href="#ph-arrow-right"></use></svg>
```
with:
```php
			<?php echo pediment_icon( 'arrow-right' ); // phpcs:ignore WordPress.Security.EscapeOutput -- theme-controlled icon markup ?>
```

- [ ] **Step 4: Fix the raw `<use>` in `hero/render.php`**

In `src/blocks/hero/render.php`, replace the tick icon (currently line ~61):
```php
<svg class="starter-hero__tick-icon" aria-hidden="true" focusable="false"><use href="#ph-check-circle"></use></svg>
```
with:
```php
<?php echo pediment_icon( 'check-circle', 'starter-hero__tick-icon' ); // phpcs:ignore WordPress.Security.EscapeOutput -- theme-controlled icon markup ?>
```

> Note: the old hero tick used class `starter-hero__tick-icon` directly on the `<svg>`. `pediment_icon()` emits `class="i starter-hero__tick-icon"`. If `assets/css/theme.css` styles `.starter-hero__tick-icon` as the svg, the rule still matches (the class is present). Verify visually in Step 7; adjust the CSS selector only if the icon sizing regresses.

- [ ] **Step 5: Confirm no raw `#ph-` references remain in PHP renders**

Run: `grep -rn '#ph-' src/blocks/ inc/ || echo "none remaining"`
Expected: `none remaining` (the only remaining `#ph-` will be in `src/blocks/feature/edit.tsx`, handled in Task 6).

- [ ] **Step 6: Run the render tests to verify they pass**

Run:
```bash
npx wp-env run tests-wordpress --env-cwd=wp-content/themes/pediment vendor/bin/phpunit --filter 'FeatureGridTest|BlogIndexTest|MegaMenuTest|HeroTest'
```
Expected: PASS.

- [ ] **Step 7: Run the full PHP suite to catch any other icon assertions**

Run: `npx wp-env run tests-wordpress --env-cwd=wp-content/themes/pediment vendor/bin/phpunit`
Expected: PASS. If any other test asserts `#ph-` or `<symbol`/`<use>`, update it to the `data-icon` form the same way (search: `grep -rn '#ph-\|<use\|<symbol' tests/phpunit/`).

- [ ] **Step 8: Commit**

```bash
git add src/blocks/feature/render.php src/blocks/hero/render.php tests/phpunit/
git commit -m "fix(icons): route remaining icon output through pediment_icon()"
```

---

## Task 4: Delete the sprite machinery

Now that nothing references the sprite or `#ph-` symbols, remove the dead code and asset.

**Files:**
- Delete: `tools/build-phosphor-sprite.sh`, `assets/icons/phosphor-sprite.svg`
- (Already removed in Task 2: `pediment_icon_sprite_contents()`, `pediment_print_icon_sprite()`, `pediment_enqueue_editor_icon_sprite()` — they were dropped when `inc/icons.php` was rewritten.)

- [ ] **Step 1: Delete the sprite files**

Run:
```bash
git rm tools/build-phosphor-sprite.sh assets/icons/phosphor-sprite.svg
```

- [ ] **Step 2: Confirm nothing references the removed functions or file**

Run:
```bash
grep -rn 'pediment_print_icon_sprite\|pediment_enqueue_editor_icon_sprite\|pediment_icon_sprite_contents\|phosphor-sprite' . --include='*.php' --include='*.tsx' --include='*.ts' --include='*.js' | grep -v node_modules | grep -v build/ || echo "no references remaining"
```
Expected: `no references remaining`.

- [ ] **Step 3: Run the full PHP suite**

Run: `npx wp-env run tests-wordpress --env-cwd=wp-content/themes/pediment vendor/bin/phpunit`
Expected: PASS (no test references the sprite after Task 2/3).

- [ ] **Step 4: Commit**

```bash
git add -A
git commit -m "chore(icons): remove obsolete sprite generator and asset"
```

---

## Task 5: Expose the catalog JSON URL to the block editor

**Files:**
- Modify: `inc/icons.php`

- [ ] **Step 1: Add the editor inline-script enqueue**

Append to `inc/icons.php` (after the `pediment_icon()` function):

```php
/**
 * Expose the icon catalog JSON URL to the block editor so the IconPicker
 * component can lazy-fetch the full slug → markup map on first use.
 */
add_action(
	'enqueue_block_editor_assets',
	function () {
		$url = get_theme_file_uri( 'assets/icons/phosphor-icons.json' );
		wp_add_inline_script(
			'wp-blocks',
			'window.pedimentIcons = ' . wp_json_encode( array( 'catalogUrl' => $url ) ) . ';',
			'after'
		);
	}
);
```

> Pattern mirrors `inc/hero-variants.php`, which already attaches an inline script to the `wp-blocks` handle in `enqueue_block_editor_assets`.

- [ ] **Step 2: Verify PHP lints**

Run: `composer lint -- inc/icons.php` (or `npx wp-env run tests-wordpress --env-cwd=wp-content/themes/pediment vendor/bin/phpcs inc/icons.php`)
Expected: no errors. (If `composer lint` reports unrelated pre-existing warnings elsewhere, scope to `inc/icons.php`.)

- [ ] **Step 3: Commit**

```bash
git add inc/icons.php
git commit -m "feat(icons): expose catalog JSON url to the block editor"
```

---

## Task 6: Build the shared IconPicker component

**Files:**
- Create: `src/components/icon-picker/filter.ts`, `src/components/icon-picker/index.tsx`
- Test: `src/components/icon-picker/filter.test.ts`

- [ ] **Step 1: Write the failing unit test for the pure filter helper**

Create `src/components/icon-picker/filter.test.ts`:

```ts
import { filterIcons } from './filter';

describe( 'filterIcons', () => {
	const slugs = [ 'arrow-right', 'gear', 'gear-six', 'trend-up', 'bank' ];

	it( 'returns all slugs when the query is empty', () => {
		expect( filterIcons( slugs, '' ) ).toEqual( slugs );
	} );

	it( 'trims and lowercases the query', () => {
		expect( filterIcons( slugs, '  GEAR ' ) ).toEqual( [
			'gear',
			'gear-six',
		] );
	} );

	it( 'matches a substring anywhere in the slug', () => {
		expect( filterIcons( slugs, 'up' ) ).toEqual( [ 'trend-up' ] );
	} );

	it( 'returns an empty array when nothing matches', () => {
		expect( filterIcons( slugs, 'zzz' ) ).toEqual( [] );
	} );
} );
```

- [ ] **Step 2: Run the test to verify it fails**

Run: `npm run test:js -- filter`
Expected: FAIL — `./filter` module / `filterIcons` not found.

- [ ] **Step 3: Implement the pure filter helper**

Create `src/components/icon-picker/filter.ts`:

```ts
/**
 * Filter a list of icon slugs by a search query (case-insensitive substring).
 *
 * @param slugs Full list of icon slugs.
 * @param query Raw search input.
 * @return The matching slugs, in original order; the full list when query is blank.
 */
export function filterIcons( slugs: string[], query: string ): string[] {
	const q = query.trim().toLowerCase();
	if ( ! q ) {
		return slugs;
	}
	return slugs.filter( ( slug ) => slug.includes( q ) );
}
```

- [ ] **Step 4: Run the test to verify it passes**

Run: `npm run test:js -- filter`
Expected: PASS (4 tests).

- [ ] **Step 5: Implement the IconPicker component**

Create `src/components/icon-picker/index.tsx`:

```tsx
import { __, sprintf } from '@wordpress/i18n';
import {
	Button,
	Dropdown,
	SearchControl,
	Spinner,
	Notice,
} from '@wordpress/components';
import { useState, useEffect, useMemo } from '@wordpress/element';
import { filterIcons } from './filter';

type Catalog = Record< string, string >;

// Module-level cache shared across every IconPicker instance: the catalog is
// fetched at most once per editor session.
let catalogCache: Catalog | null = null;
let catalogPromise: Promise< Catalog > | null = null;

function getCatalogUrl(): string | undefined {
	return ( window as unknown as {
		pedimentIcons?: { catalogUrl?: string };
	} ).pedimentIcons?.catalogUrl;
}

function loadCatalog(): Promise< Catalog > {
	if ( catalogCache ) {
		return Promise.resolve( catalogCache );
	}
	if ( catalogPromise ) {
		return catalogPromise;
	}
	const url = getCatalogUrl();
	if ( ! url ) {
		return Promise.reject( new Error( 'Icon catalog URL is unavailable.' ) );
	}
	catalogPromise = fetch( url )
		.then( ( res ) => {
			if ( ! res.ok ) {
				throw new Error( `Failed to load icons (${ res.status }).` );
			}
			return res.json();
		} )
		.then( ( data: Catalog ) => {
			catalogCache = data;
			return data;
		} )
		.catch( ( err ) => {
			catalogPromise = null; // allow a retry on next open
			throw err;
		} );
	return catalogPromise;
}

// Cap how many icons render with no active search, so opening the popover does
// not mount ~1,500 DOM nodes at once. A search narrows below this instantly.
const NO_QUERY_LIMIT = 150;

function IconGlyph( { markup }: { markup: string } ) {
	return (
		<svg
			viewBox="0 0 256 256"
			width={ 24 }
			height={ 24 }
			aria-hidden="true"
			focusable="false"
			dangerouslySetInnerHTML={ { __html: markup } }
		/>
	);
}

export default function IconPicker( {
	value,
	onChange,
	label = __( 'Icon', 'pediment' ),
}: {
	value: string;
	onChange: ( slug: string ) => void;
	label?: string;
} ) {
	const [ catalog, setCatalog ] = useState< Catalog | null >( catalogCache );
	const [ error, setError ] = useState< string | null >( null );
	const [ query, setQuery ] = useState( '' );

	useEffect( () => {
		let active = true;
		if ( ! catalog ) {
			loadCatalog()
				.then( ( data ) => active && setCatalog( data ) )
				.catch( ( err: Error ) => active && setError( err.message ) );
		}
		return () => {
			active = false;
		};
	}, [ catalog ] );

	const allSlugs = useMemo(
		() => ( catalog ? Object.keys( catalog ) : [] ),
		[ catalog ]
	);
	const matches = useMemo(
		() => filterIcons( allSlugs, query ),
		[ allSlugs, query ]
	);
	const truncated = ! query.trim() && matches.length > NO_QUERY_LIMIT;
	const visible = truncated ? matches.slice( 0, NO_QUERY_LIMIT ) : matches;

	const currentMarkup = catalog && value ? catalog[ value ] : undefined;

	return (
		<div className="pediment-icon-picker">
			<span className="pediment-icon-picker__label">{ label }</span>
			<Dropdown
				className="pediment-icon-picker__dropdown"
				contentClassName="pediment-icon-picker__popover"
				popoverProps={ { placement: 'bottom-start' } }
				renderToggle={ ( { isOpen, onToggle } ) => (
					<Button
						variant="secondary"
						onClick={ onToggle }
						aria-expanded={ isOpen }
						className="pediment-icon-picker__toggle"
					>
						{ currentMarkup ? (
							<IconGlyph markup={ currentMarkup } />
						) : null }
						<span>{ value || __( 'Choose…', 'pediment' ) }</span>
					</Button>
				) }
				renderContent={ () => (
					<div className="pediment-icon-picker__content">
						{ error && (
							<Notice status="error" isDismissible={ false }>
								{ error }
							</Notice>
						) }
						{ ! catalog && ! error && <Spinner /> }
						{ catalog && (
							<>
								<SearchControl
									value={ query }
									onChange={ setQuery }
									placeholder={ __(
										'Search icons…',
										'pediment'
									) }
									__nextHasNoMarginBottom
								/>
								<div
									className="pediment-icon-picker__grid"
									role="listbox"
									aria-label={ __(
										'Icons',
										'pediment'
									) }
								>
									{ visible.map( ( slug ) => (
										<Button
											key={ slug }
											className={
												slug === value
													? 'pediment-icon-picker__cell is-selected'
													: 'pediment-icon-picker__cell'
											}
											aria-label={ slug }
											aria-selected={ slug === value }
											role="option"
											onClick={ () => onChange( slug ) }
										>
											<IconGlyph
												markup={ catalog[ slug ] }
											/>
										</Button>
									) ) }
								</div>
								{ matches.length === 0 && (
									<p className="pediment-icon-picker__empty">
										{ __(
											'No icons match.',
											'pediment'
										) }
									</p>
								) }
								{ truncated && (
									<p className="pediment-icon-picker__hint">
										{ sprintf(
											/* translators: %d: number of icons shown. */
											__(
												'Showing first %d. Search to narrow.',
												'pediment'
											),
											NO_QUERY_LIMIT
										) }
									</p>
								) }
							</>
						) }
					</div>
				) }
			/>
		</div>
	);
}
```

- [ ] **Step 6: Typecheck the component**

Run: `npx tsc --noEmit`
Expected: no errors. (If `@wordpress/components` type exports differ in this version — e.g. `__nextHasNoMarginBottom` — adjust to satisfy the compiler; the prop is optional and can be removed if it errors.)

- [ ] **Step 7: Lint**

Run: `npm run lint:js`
Expected: no errors in `src/components/icon-picker/`.

- [ ] **Step 8: Commit**

```bash
git add src/components/icon-picker/
git commit -m "feat(icons): add shared IconPicker editor component"
```

---

## Task 7: Wire IconPicker into the feature block

**Files:**
- Modify: `src/blocks/feature/edit.tsx`, `src/blocks/feature/block.json`

- [ ] **Step 1: Update `feature/edit.tsx`**

Replace the entire contents of `src/blocks/feature/edit.tsx` with:

```tsx
import { __ } from '@wordpress/i18n';
import {
	useBlockProps,
	RichText,
	InspectorControls,
} from '@wordpress/block-editor';
import { PanelBody, TextControl } from '@wordpress/components';
import IconPicker from '../../components/icon-picker';

type Attrs = {
	icon: string;
	title: string;
	text: string;
	linkText: string;
	linkUrl: string;
};

export default function Edit( {
	attributes,
	setAttributes,
}: {
	attributes: Attrs;
	setAttributes: ( a: Partial< Attrs > ) => void;
} ) {
	const blockProps = useBlockProps( { className: 'starter-feature' } );
	return (
		<>
			<InspectorControls>
				<PanelBody title={ __( 'Feature', 'pediment' ) }>
					<IconPicker
						label={ __( 'Icon', 'pediment' ) }
						value={ attributes.icon }
						onChange={ ( icon ) => setAttributes( { icon } ) }
					/>
					<TextControl
						label={ __( 'Link URL', 'pediment' ) }
						value={ attributes.linkUrl }
						onChange={ ( v ) => setAttributes( { linkUrl: v } ) }
					/>
				</PanelBody>
			</InspectorControls>
			<div { ...blockProps }>
				<div className="starter-feature__ic" aria-hidden="true">
					{ attributes.icon && (
						<IconPreview slug={ attributes.icon } />
					) }
				</div>
				<RichText
					tagName="h3"
					className="starter-feature__title"
					value={ attributes.title }
					onChange={ ( v ) => setAttributes( { title: v } ) }
					placeholder={ __( 'Title…', 'pediment' ) }
				/>
				<RichText
					tagName="p"
					className="starter-feature__text"
					value={ attributes.text }
					onChange={ ( v ) => setAttributes( { text: v } ) }
					placeholder={ __( 'Description…', 'pediment' ) }
				/>
				<RichText
					tagName="span"
					className="starter-feature__more"
					value={ attributes.linkText }
					onChange={ ( v ) => setAttributes( { linkText: v } ) }
					placeholder={ __( 'Link text (optional)…', 'pediment' ) }
				/>
			</div>
		</>
	);
}
```

- [ ] **Step 2: Add the shared canvas preview helper**

The canvas preview must inline the chosen icon's SVG (the sprite is gone). Add a small shared preview that reads the same cached catalog. Create `src/components/icon-picker/IconPreview.tsx`:

```tsx
import { useState, useEffect } from '@wordpress/element';
import { getCatalog } from './catalog';

/**
 * Render a single icon inline by slug, using the cached editor catalog.
 * Renders nothing until the catalog has loaded or if the slug is unknown.
 */
export default function IconPreview( {
	slug,
	className = 'i',
}: {
	slug: string;
	className?: string;
} ) {
	const [ markup, setMarkup ] = useState< string | undefined >( undefined );

	useEffect( () => {
		let active = true;
		getCatalog()
			.then( ( cat ) => active && setMarkup( cat[ slug ] ) )
			.catch( () => active && setMarkup( undefined ) );
		return () => {
			active = false;
		};
	}, [ slug ] );

	if ( ! markup ) {
		return null;
	}
	return (
		<svg
			className={ className }
			viewBox="0 0 256 256"
			data-icon={ slug }
			aria-hidden="true"
			focusable="false"
			dangerouslySetInnerHTML={ { __html: markup } }
		/>
	);
}
```

- [ ] **Step 3: Extract the catalog loader into a shared module**

So both `index.tsx` and `IconPreview.tsx` share one cache, move the loader into `src/components/icon-picker/catalog.ts`:

```ts
export type Catalog = Record< string, string >;

let catalogCache: Catalog | null = null;
let catalogPromise: Promise< Catalog > | null = null;

function getCatalogUrl(): string | undefined {
	return ( window as unknown as {
		pedimentIcons?: { catalogUrl?: string };
	} ).pedimentIcons?.catalogUrl;
}

export function getCatalog(): Promise< Catalog > {
	if ( catalogCache ) {
		return Promise.resolve( catalogCache );
	}
	if ( catalogPromise ) {
		return catalogPromise;
	}
	const url = getCatalogUrl();
	if ( ! url ) {
		return Promise.reject(
			new Error( 'Icon catalog URL is unavailable.' )
		);
	}
	catalogPromise = fetch( url )
		.then( ( res ) => {
			if ( ! res.ok ) {
				throw new Error( `Failed to load icons (${ res.status }).` );
			}
			return res.json();
		} )
		.then( ( data: Catalog ) => {
			catalogCache = data;
			return data;
		} )
		.catch( ( err ) => {
			catalogPromise = null;
			throw err;
		} );
	return catalogPromise;
}
```

Then in `src/components/icon-picker/index.tsx`, delete the inlined `Catalog` type, `catalogCache`, `catalogPromise`, `getCatalogUrl`, and `loadCatalog` definitions, and instead import:
```tsx
import { getCatalog, type Catalog } from './catalog';
```
and replace the `loadCatalog()` call in the `useEffect` with `getCatalog()`. Initialise `useState` from the shared cache by calling `getCatalog()`'s synchronous cache is not exposed, so keep `useState< Catalog | null >( null )` (the effect populates it; the module cache still prevents a second fetch).

- [ ] **Step 4: Clean `feature/block.json`**

In `src/blocks/feature/block.json`:
- Remove the entire `"enum": [ … ]` array from the `icon` attribute, leaving:
  ```json
  "icon": { "type": "string", "default": "trend-up" },
  ```
- Replace the `description` value with one that no longer lists icons:
  ```json
  "description": "A single icon + title + text + optional link card.",
  ```

- [ ] **Step 5: Typecheck and build**

Run:
```bash
npx tsc --noEmit && npm run build
```
Expected: no type errors; build completes and writes `build/blocks/feature/`.

- [ ] **Step 6: Commit**

```bash
git add src/blocks/feature/ src/components/icon-picker/
git commit -m "feat(feature): use IconPicker; drop icon enum and text input"
```

---

## Task 8: Wire IconPicker into the mega-menu block

**Files:**
- Modify: `src/blocks/mega-menu/edit.tsx`

- [ ] **Step 1: Import IconPicker and the preview**

At the top of `src/blocks/mega-menu/edit.tsx`, add after the existing imports:
```tsx
import IconPicker from '../../components/icon-picker';
import IconPreview from '../../components/icon-picker/IconPreview';
```

- [ ] **Step 2: Replace the column icon TextControl**

Replace the icon `TextControl` block (the one labelled `'Icon (Phosphor name)'`, currently lines ~146–159) with:
```tsx
								<IconPicker
									label={ __( 'Icon', 'pediment' ) }
									value={ column.icon ?? '' }
									onChange={ ( icon ) =>
										updateColumn( ci, { icon } )
									}
								/>
```

- [ ] **Step 3: Replace the inline `<use>` preview in the canvas**

In the preview render (currently the `<svg … ><use href={ \`#ph-${ colIcon }\` } /></svg>` inside the heading), replace:
```tsx
												<svg
													className="i starter-mega-column__icon"
													width="24"
													height="24"
													viewBox="0 0 256 256"
													aria-hidden="true"
													focusable="false"
												>
													<use
														href={ `#ph-${ colIcon }` }
													/>
												</svg>
```
with:
```tsx
												<IconPreview
													slug={ colIcon }
													className="i starter-mega-column__icon"
												/>
```

> `colIcon` is already the sanitized slug from `iconSlug( column.icon )`; keep that. The `hasIcon` guard around it stays as-is.

- [ ] **Step 4: Typecheck, lint, build**

Run:
```bash
npx tsc --noEmit && npm run lint:js && npm run build
```
Expected: no errors; build writes `build/blocks/mega-menu/`.

- [ ] **Step 5: Commit**

```bash
git add src/blocks/mega-menu/edit.tsx
git commit -m "feat(mega-menu): use IconPicker for column icons"
```

---

## Task 9: Full verification

- [ ] **Step 1: Full PHP suite**

Run: `npx wp-env run tests-wordpress --env-cwd=wp-content/themes/pediment vendor/bin/phpunit`
Expected: PASS, no failures.

- [ ] **Step 2: JS unit + lint + typecheck + build**

Run:
```bash
npm run test:js && npm run lint:js && npx tsc --noEmit && npm run build
```
Expected: all pass.

- [ ] **Step 3: Confirm no stale references**

Run:
```bash
grep -rn '#ph-\|phosphor-sprite\|pediment_print_icon_sprite' src/ inc/ tests/ assets/ | grep -v node_modules || echo "clean"
```
Expected: `clean`.

- [ ] **Step 4: Manual verification (child-theme wp-env, port 8890)**

Per project convention, do manual UI checks in the child theme's wp-env (`http://localhost:8890`), not a parent-only env. Steps:
1. Start the canonical dev env if not running.
2. Edit a page; insert a Feature Grid → Feature block.
3. In the Feature inspector, open the Icon picker, search e.g. `rocket`, select it. Confirm the canvas preview updates.
4. Save and view the page on the frontend; confirm the rocket icon renders (inline `<svg data-icon="rocket">`).
5. In the Site Editor → Navigation, edit a Mega Menu column; pick a non-original icon; save; confirm it renders on the frontend.
6. Confirm a previously-saved feature/mega-menu icon (e.g. `trend-up`, `gear`) still renders — no regression for existing content.

- [ ] **Step 5: Finalize**

If all green, the branch is ready for review/PR per `superpowers:finishing-a-development-branch`.

---

## Self-Review notes (author)

- **Spec coverage:** Data pipeline → Task 1. Render-time lookup + map → Task 2. Sprite removal → Task 4 (+ rewrite in Task 2). Catalog URL exposure → Task 5. Shared component → Task 6 (+ catalog/preview split in Task 7). Feature consumer + block.json → Task 7. Mega-menu consumer → Task 8. Testing strategy → Tasks 2,3,6,9. Error handling (unknown slug → '', fetch failure → Notice, missing map → []) → Tasks 2 and 6.
- **Refinements vs spec:** `npm pack` generation and `data-icon` attribute are documented at the top and used consistently in Tasks 1–3.
- **Type consistency:** catalog type `Catalog = Record<string,string>` and `getCatalog()` are shared via `catalog.ts` (Task 7 Step 3) and consumed identically in `index.tsx` and `IconPreview.tsx`. `pediment_icon_map()` defined in Task 2 is used in Task 2's `pediment_icon()` and asserted in Task 2 Step 1.
- **Known follow-ups (out of scope):** grid virtualization beyond the `NO_QUERY_LIMIT` cap; optional CSS in `assets/css/theme.css` / an editor stylesheet for `.pediment-icon-picker*` (the component is functional unstyled; add styling if the inspector layout needs it during manual verification).
