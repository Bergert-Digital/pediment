# Icon Picker Discoverability & Swappable Sets — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Make all ~1,500 icons discoverable in the IconPicker (browse-all by scrolling, tag-aware search, category filter) and isolate every icon-set specific assumption into generated data so the set is swappable with zero theme-code changes.

**Architecture:** A build step emits four committed files that form a documented contract — `icon-markup.{php,json}` (slug → inner SVG), `icon-meta.json` (slug → categories+tags, optional), `icon-set.json` (render manifest: `viewBox` + `svgAttrs`). PHP and the React picker read only that contract; no hardcoded coordinate system or fill/stroke assumption remains. The picker filters by category then query (slug+tags) and renders matches progressively via an IntersectionObserver sentinel.

**Tech Stack:** Bash + Node (build), PHP (`inc/icons.php`, PHPUnit), TypeScript/React with `@wordpress/components` (`wp-scripts test-unit-js` → Jest), Playwright (e2e).

**Spec:** [docs/superpowers/specs/2026-05-31-icon-picker-discoverability-design.md](../specs/2026-05-31-icon-picker-discoverability-design.md)

---

## File Structure

| File | Responsibility |
| --- | --- |
| `tools/build-phosphor-data.sh` | Reference builder: download Phosphor, emit the four contract files |
| `tools/extract-icon-meta.mjs` | Node helper: read Phosphor `dist/index.mjs`, emit `icon-meta.json` shape |
| `tools/lint-icons.mjs` | Offline contract validator (meta keys ⊆ markup keys; manifest shape) |
| `assets/icons/icon-markup.{php,json}` | Generated slug → inner-SVG-markup map |
| `assets/icons/icon-meta.json` | Generated slug → `{c:[categories], t:[tags]}` |
| `assets/icons/icon-set.json` | Generated render manifest `{name,version,viewBox,svgAttrs,license}` |
| `inc/icons.php` | Set-agnostic render (`pediment_icon`, `pediment_icon_map`, `pediment_icon_set`); editor URL enqueue |
| `src/components/icon-picker/catalog.ts` | Fetch + cache the bundle `{markup, meta, set}` |
| `src/components/icon-picker/filter.ts` | Pure filter: category narrow + slug/tag query |
| `src/components/icon-picker/categories.ts` | Pure helpers: derive category list + label |
| `src/components/icon-picker/index.tsx` | Picker UI: category select, progressive grid, manifest-driven preview |
| `src/components/icon-picker/IconPreview.tsx` | Inline single-icon preview, manifest-driven viewBox/attrs |

---

## Task 1: Build pipeline emits the four contract files

**Files:**
- Create: `tools/extract-icon-meta.mjs`
- Create: `tools/lint-icons.mjs`
- Modify: `tools/build-phosphor-data.sh`
- Modify: `package.json` (add `lint:icons` script)
- Generated (by running the build): `assets/icons/icon-markup.php`, `assets/icons/icon-markup.json`, `assets/icons/icon-meta.json`, `assets/icons/icon-set.json`
- Delete after rename: `assets/icons/phosphor-icons.php`, `assets/icons/phosphor-icons.json`

- [ ] **Step 1: Write the Node metadata extractor**

Create `tools/extract-icon-meta.mjs`:

```js
// Reads Phosphor core's icon metadata and emits the icon-meta.json contract:
//   { "<slug>": { "c": ["<category>", …], "t": ["<tag>", …] }, … }
// Only slugs that have a regular-weight SVG are included (set intersection),
// so meta can never drift from the markup map. The "*new*" marker tag is dropped.
//
// Usage: node tools/extract-icon-meta.mjs <packageDir> <regularSvgDir>
import { readdirSync } from 'node:fs';
import { pathToFileURL } from 'node:url';

const [ , , pkgDir, svgDir ] = process.argv;
if ( ! pkgDir || ! svgDir ) {
	console.error( 'usage: extract-icon-meta.mjs <packageDir> <regularSvgDir>' );
	process.exit( 1 );
}

const valid = new Set(
	readdirSync( svgDir )
		.filter( ( f ) => f.endsWith( '.svg' ) )
		.map( ( f ) => f.slice( 0, -4 ) )
);

const { icons } = await import(
	pathToFileURL( `${ pkgDir }/dist/index.mjs` ).href
);

const out = {};
for ( const icon of icons ) {
	if ( ! valid.has( icon.name ) ) {
		continue;
	}
	const tags = ( icon.tags || [] ).filter( ( t ) => t !== '*new*' );
	out[ icon.name ] = { c: [ ...icon.categories ], t: tags };
}

process.stdout.write( JSON.stringify( out ) );
```

- [ ] **Step 2: Write the offline contract validator**

Create `tools/lint-icons.mjs`:

```js
// Validates the committed icon data against the swap contract. Offline; no network.
//   - icon-meta.json keys must be a subset of icon-markup.json keys
//   - icon-set.json must have a viewBox string and an svgAttrs object
import { readFileSync } from 'node:fs';

const dir = 'assets/icons/';
const read = ( name ) => JSON.parse( readFileSync( dir + name, 'utf8' ) );

const markup = read( 'icon-markup.json' );
const meta = read( 'icon-meta.json' );
const set = read( 'icon-set.json' );

const markupKeys = new Set( Object.keys( markup ) );
const stray = Object.keys( meta ).filter( ( k ) => ! markupKeys.has( k ) );
if ( stray.length ) {
	console.error(
		`✗ icon-meta.json has ${ stray.length } slug(s) absent from icon-markup.json, e.g. ${ stray
			.slice( 0, 5 )
			.join( ', ' ) }`
	);
	process.exit( 1 );
}

if ( typeof set.viewBox !== 'string' || typeof set.svgAttrs !== 'object' || set.svgAttrs === null ) {
	console.error( '✗ icon-set.json must have a string viewBox and an object svgAttrs' );
	process.exit( 1 );
}

console.log(
	`✓ icons ok: ${ markupKeys.size } markup, ${ Object.keys( meta ).length } meta, set ${ set.name }@${ set.version }`
);
```

- [ ] **Step 3: Add the lint script to package.json**

In `package.json` `"scripts"`, add after `"lint:colors"`:

```json
    "lint:icons": "node tools/lint-icons.mjs",
```

- [ ] **Step 4: Run the validator to verify it fails (files not generated yet)**

Run: `npm run lint:icons`
Expected: FAIL — `ENOENT` opening `assets/icons/icon-markup.json` (current files are still named `phosphor-icons.*`).

- [ ] **Step 5: Update the build script to emit all four files with generic names**

Edit `tools/build-phosphor-data.sh`. Change the output path vars near the top:

```bash
OUT_PHP="assets/icons/icon-markup.php"
OUT_JSON="assets/icons/icon-markup.json"
OUT_META="assets/icons/icon-meta.json"
OUT_SET="assets/icons/icon-set.json"
```

The existing per-SVG loop that builds `php_body`/`json_body` is unchanged. After the block that writes `$OUT_PHP` and `$OUT_JSON`, append the meta + manifest emission (uses the unpacked package dir `$tmp/package` and the regular SVG dir `$src`):

```bash
echo "Extracting category/tag metadata…"
node tools/extract-icon-meta.mjs "$tmp/package" "$src" > "$OUT_META"

cat > "$OUT_SET" <<JSON
{"name":"phosphor","version":"${VER}","viewBox":"0 0 256 256","svgAttrs":{"fill":"currentColor"},"license":"MIT"}
JSON

echo "wrote $OUT_META and $OUT_SET"
```

Update the final summary `echo` to reference the renamed files:

```bash
echo "wrote $OUT_PHP, $OUT_JSON, $OUT_META, $OUT_SET ($count icons)"
```

- [ ] **Step 6: Regenerate the data files and remove the old-named ones**

Run:
```bash
bash tools/build-phosphor-data.sh
git rm --cached assets/icons/phosphor-icons.php assets/icons/phosphor-icons.json
rm -f assets/icons/phosphor-icons.php assets/icons/phosphor-icons.json
```
Expected: four files appear under `assets/icons/` (`icon-markup.php`, `icon-markup.json`, `icon-meta.json`, `icon-set.json`); the two `phosphor-icons.*` files are gone.

- [ ] **Step 7: Run the validator to verify it passes**

Run: `npm run lint:icons`
Expected: PASS — e.g. `✓ icons ok: 1512 markup, 1512 meta, set phosphor@2.1.1`.

- [ ] **Step 8: Commit**

```bash
git add tools/extract-icon-meta.mjs tools/lint-icons.mjs tools/build-phosphor-data.sh package.json assets/icons/
git commit -m "feat(icons): generate icon-meta + icon-set manifest; rename data to icon-markup.*"
```

---

## Task 2: PHP render becomes set-agnostic (manifest-driven)

**Files:**
- Modify: `inc/icons.php`
- Test: `tests/phpunit/IconsTest.php`

- [ ] **Step 1: Write failing tests for manifest-driven rendering**

In `tests/phpunit/IconsTest.php`, update the existing first test's path expectation is unaffected (Phosphor manifest keeps `0 0 256 256`). Add two new tests:

```php
	public function test_pediment_icon_applies_manifest_svg_attrs() {
		// Phosphor manifest carries fill="currentColor" on the wrapper.
		$html = pediment_icon( 'arrow-right' );
		$this->assertStringContainsString( 'fill="currentColor"', $html );
	}

	public function test_pediment_icon_renders_a_swapped_stroke_set() {
		// A different icon set (e.g. Lucide) is expressed purely through the
		// manifest: a 24x24 viewBox and stroke-based svgAttrs. No code change.
		$filter = static function () {
			return array(
				'viewBox'  => '0 0 24 24',
				'svgAttrs' => array(
					'fill'           => 'none',
					'stroke'         => 'currentColor',
					'stroke-width'   => '2',
					'stroke-linecap' => 'round',
				),
			);
		};
		add_filter( 'pediment_icon_set', $filter );
		$html = pediment_icon( 'arrow-right' );
		remove_filter( 'pediment_icon_set', $filter );

		$this->assertStringContainsString( 'viewBox="0 0 24 24"', $html );
		$this->assertStringContainsString( 'fill="none"', $html );
		$this->assertStringContainsString( 'stroke="currentColor"', $html );
		$this->assertStringContainsString( 'stroke-width="2"', $html );
		$this->assertStringContainsString( 'stroke-linecap="round"', $html );
	}
```

- [ ] **Step 2: Run the tests to verify they fail**

Run: `composer test -- --filter IconsTest`
Expected: FAIL — `fill="currentColor"` absent (wrapper hardcodes no fill) and `pediment_icon_set` filter has no effect (function does not exist).

- [ ] **Step 3: Implement `pediment_icon_set()` and the manifest-driven wrapper**

In `inc/icons.php`, update the doc header path reference (`assets/icons/phosphor-icons.php` → `assets/icons/icon-markup.php`) and change the map path in `pediment_icon_map()`:

```php
		$file = get_theme_file_path( 'assets/icons/icon-markup.php' );
```

Add a new function after `pediment_icon_map()`:

```php
/**
 * Return the render manifest for the active icon set, loaded once per request.
 *
 * Captures everything set-specific about rendering — the coordinate system
 * (viewBox) and the presentation attributes that must live on the wrapper
 * <svg> (e.g. fill for filled sets, stroke/stroke-width for stroke sets).
 * Falls back to Phosphor-shaped defaults when the manifest is absent.
 *
 * @return array{viewBox:string,svgAttrs:array<string,string>}
 */
function pediment_icon_set(): array {
	static $set = null;
	if ( null === $set ) {
		$file = get_theme_file_path( 'assets/icons/icon-set.json' );
		$data = is_readable( $file ) ? json_decode( (string) file_get_contents( $file ), true ) : null;
		$set  = array(
			'viewBox'  => ( is_array( $data ) && ! empty( $data['viewBox'] ) )
				? (string) $data['viewBox']
				: '0 0 256 256',
			'svgAttrs' => ( is_array( $data ) && isset( $data['svgAttrs'] ) && is_array( $data['svgAttrs'] ) )
				? $data['svgAttrs']
				: array( 'fill' => 'currentColor' ),
		);
	}

	/**
	 * Filter the icon render manifest. Lets tests and integrations swap the
	 * coordinate system / presentation attributes without regenerating data.
	 *
	 * @param array{viewBox:string,svgAttrs:array<string,string>} $set
	 */
	return apply_filters( 'pediment_icon_set', $set );
}
```

Replace the body of `pediment_icon()` (keep the slug sanitisation and the unknown-slug early return) with a manifest-driven wrapper. `class` stays the first attribute so existing assertions hold:

```php
	$set   = pediment_icon_set();
	$class = 'i' . ( '' !== $extra_class ? ' ' . sanitize_html_class( $extra_class ) : '' );

	$attrs  = sprintf( ' class="%s"', esc_attr( $class ) );
	$attrs .= sprintf( ' viewBox="%s"', esc_attr( $set['viewBox'] ) );
	foreach ( $set['svgAttrs'] as $key => $value ) {
		$key = preg_replace( '/[^a-z0-9-]/', '', strtolower( (string) $key ) );
		if ( '' === $key ) {
			continue;
		}
		$attrs .= sprintf( ' %s="%s"', $key, esc_attr( (string) $value ) );
	}
	$attrs .= sprintf( ' data-icon="%s" aria-hidden="true" focusable="false"', esc_attr( $slug ) );

	return sprintf(
		'<svg%s>%s</svg>',
		$attrs,
		$map[ $slug ] // Theme-controlled trusted markup (same trust model as the old sprite).
	);
```

- [ ] **Step 4: Run the tests to verify they pass**

Run: `composer test -- --filter IconsTest`
Expected: PASS — all IconsTest cases green (existing `viewBox="0 0 256 256"`, `class="i"`, `data-icon`, sanitisation, plus the two new manifest tests).

- [ ] **Step 5: Update the editor asset enqueue to expose all three URLs**

In `inc/icons.php`, inside the `enqueue_block_editor_assets` callback, replace the single `catalogUrl` inline script with the markup/meta/set URLs:

```php
			$icons = array(
				'markupUrl' => get_theme_file_uri( 'assets/icons/icon-markup.json' ),
				'metaUrl'   => get_theme_file_uri( 'assets/icons/icon-meta.json' ),
				'setUrl'    => get_theme_file_uri( 'assets/icons/icon-set.json' ),
			);
			wp_add_inline_script(
				'wp-blocks',
				'window.pedimentIcons = ' . wp_json_encode( $icons ) . ';',
				'after'
			);
```

- [ ] **Step 6: Run the full PHP suite to confirm nothing else broke**

Run: `composer test`
Expected: PASS (no failures introduced).

- [ ] **Step 7: Commit**

```bash
git add inc/icons.php tests/phpunit/IconsTest.php
git commit -m "feat(icons): drive svg viewBox/attrs from icon-set manifest (set-agnostic render)"
```

---

## Task 3: Catalog fetches the bundle `{markup, meta, set}`

**Files:**
- Modify: `src/components/icon-picker/catalog.ts`
- Modify: `src/components/icon-picker/IconPreview.tsx`
- Modify: `src/components/icon-picker/index.tsx` (call-site only — keep current behavior)
- Test: `src/components/icon-picker/catalog.test.ts` (new)

- [ ] **Step 1: Write failing tests for the bundle loader**

Create `src/components/icon-picker/catalog.test.ts`:

```ts
import { getCatalog, __resetCatalogForTests } from './catalog';

type FetchMap = Record< string, unknown >;

function mockFetch( map: FetchMap, failUrls: string[] = [] ) {
	( global as unknown as { fetch: unknown } ).fetch = jest.fn(
		( url: string ) => {
			if ( failUrls.includes( url ) ) {
				return Promise.resolve( { ok: false, status: 500 } );
			}
			return Promise.resolve( {
				ok: true,
				status: 200,
				json: () => Promise.resolve( map[ url ] ),
			} );
		}
	);
}

const URLS = {
	markupUrl: '/markup.json',
	metaUrl: '/meta.json',
	setUrl: '/set.json',
};

beforeEach( () => {
	__resetCatalogForTests();
	( window as unknown as { pedimentIcons?: unknown } ).pedimentIcons = URLS;
} );

it( 'returns markup, meta and set when all fetches succeed', async () => {
	mockFetch( {
		'/markup.json': { gear: '<path/>' },
		'/meta.json': { gear: { c: [ 'system' ], t: [ 'settings' ] } },
		'/set.json': { viewBox: '0 0 24 24', svgAttrs: { fill: 'none' } },
	} );
	const data = await getCatalog();
	expect( data.markup.gear ).toBe( '<path/>' );
	expect( data.meta?.gear.t ).toEqual( [ 'settings' ] );
	expect( data.set.viewBox ).toBe( '0 0 24 24' );
} );

it( 'degrades to null meta when the meta fetch fails', async () => {
	mockFetch(
		{
			'/markup.json': { gear: '<path/>' },
			'/set.json': { viewBox: '0 0 256 256', svgAttrs: { fill: 'currentColor' } },
		},
		[ '/meta.json' ]
	);
	const data = await getCatalog();
	expect( data.markup.gear ).toBe( '<path/>' );
	expect( data.meta ).toBeNull();
} );

it( 'rejects when the markup fetch fails', async () => {
	mockFetch( {}, [ '/markup.json' ] );
	await expect( getCatalog() ).rejects.toThrow();
} );
```

- [ ] **Step 2: Run the tests to verify they fail**

Run: `npm run test:js -- catalog`
Expected: FAIL — `__resetCatalogForTests` / bundle shape do not exist (current `getCatalog` resolves a flat `Record<string,string>`).

- [ ] **Step 3: Rewrite `catalog.ts` to fetch and cache the bundle**

Replace the full contents of `src/components/icon-picker/catalog.ts`:

```ts
export type IconMeta = { c: string[]; t: string[] };
export type IconSet = { viewBox: string; svgAttrs: Record< string, string > };
export type IconData = {
	markup: Record< string, string >;
	meta: Record< string, IconMeta > | null;
	set: IconSet;
};

const DEFAULT_SET: IconSet = {
	viewBox: '0 0 256 256',
	svgAttrs: { fill: 'currentColor' },
};

let cache: IconData | null = null;
let promise: Promise< IconData > | null = null;

function urls(): {
	markupUrl?: string;
	metaUrl?: string;
	setUrl?: string;
} {
	return (
		(
			window as unknown as {
				pedimentIcons?: {
					markupUrl?: string;
					metaUrl?: string;
					setUrl?: string;
				};
			}
		 ).pedimentIcons ?? {}
	);
}

async function fetchJson( url?: string ): Promise< unknown > {
	if ( ! url ) {
		return null;
	}
	const res = await fetch( url );
	if ( ! res.ok ) {
		throw new Error( `Failed to load icons (${ res.status }).` );
	}
	return res.json();
}

export function getCatalog(): Promise< IconData > {
	if ( cache ) {
		return Promise.resolve( cache );
	}
	if ( promise ) {
		return promise;
	}

	const { markupUrl, metaUrl, setUrl } = urls();
	if ( ! markupUrl ) {
		return Promise.reject(
			new Error( 'Icon catalog URL is unavailable.' )
		);
	}

	promise = ( async () => {
		const markup = ( await fetchJson( markupUrl ) ) as
			| Record< string, string >
			| null;
		if ( ! markup ) {
			throw new Error( 'Icon catalog URL is unavailable.' );
		}
		// Meta and the manifest are optional: failures degrade gracefully.
		const meta = ( await fetchJson( metaUrl ).catch( () => null ) ) as
			| Record< string, IconMeta >
			| null;
		const setRaw = ( await fetchJson( setUrl ).catch( () => null ) ) as
			| Partial< IconSet >
			| null;
		const set: IconSet =
			setRaw && typeof setRaw.viewBox === 'string'
				? {
						viewBox: setRaw.viewBox,
						svgAttrs: setRaw.svgAttrs ?? {},
				  }
				: DEFAULT_SET;

		cache = { markup, meta, set };
		return cache;
	} )().catch( ( err ) => {
		promise = null;
		throw err;
	} );

	return promise;
}

// Test-only: clear the module-level cache between cases.
export function __resetCatalogForTests(): void {
	cache = null;
	promise = null;
}
```

- [ ] **Step 4: Update `IconPreview.tsx` to use the bundle + manifest**

Replace the full contents of `src/components/icon-picker/IconPreview.tsx`:

```tsx
import { useState, useEffect } from '@wordpress/element';
import { getCatalog, type IconData } from './catalog';

/*
 * Render a single icon inline by slug, using the cached editor catalog.
 * The wrapper viewBox + presentation attributes come from the set manifest,
 * so stroke-based sets render correctly. Renders nothing until the catalog
 * has loaded or if the slug is unknown.
 */
export default function IconPreview( {
	slug,
	className = 'i',
}: {
	slug: string;
	className?: string;
} ) {
	const [ data, setData ] = useState< IconData | undefined >( undefined );

	useEffect( () => {
		let active = true;
		getCatalog()
			.then( ( d ) => active && setData( d ) )
			.catch( () => active && setData( undefined ) );
		return () => {
			active = false;
		};
	}, [ slug ] );

	const markup = data?.markup[ slug ];
	if ( ! markup || ! data ) {
		return null;
	}
	return (
		<svg
			className={ className }
			viewBox={ data.set.viewBox }
			data-icon={ slug }
			aria-hidden="true"
			focusable="false"
			{ ...data.set.svgAttrs }
			dangerouslySetInnerHTML={ { __html: markup } }
		/>
	);
}
```

- [ ] **Step 5: Update `index.tsx` call sites to the new shape (behavior unchanged)**

In `src/components/icon-picker/index.tsx`, change the import and the two places that read the catalog so the file still compiles with the bundle shape. Update the import line:

```tsx
import { getCatalog, type IconData } from './catalog';
```

Change the state type and setter:

```tsx
	const [ catalog, setCatalog ] = useState< IconData | null >( null );
```

The `getCatalog().then( ( data ) => active && setCatalog( data ) )` call is unchanged. Update the derived values that previously indexed the catalog directly:

```tsx
	const allSlugs = useMemo(
		() => ( catalog ? Object.keys( catalog.markup ) : [] ),
		[ catalog ]
	);
```

and the markup lookups (`catalog[ value ]` → `catalog.markup[ value ]`, `catalog[ slug ]` → `catalog.markup[ slug ]`). Leave the existing `IconGlyph` (hardcoded viewBox) for now — Task 6 replaces it.

- [ ] **Step 6: Run the JS tests + a production build to verify compile**

Run: `npm run test:js -- catalog && npm run build`
Expected: catalog tests PASS; build completes with no TypeScript errors.

- [ ] **Step 7: Commit**

```bash
git add src/components/icon-picker/catalog.ts src/components/icon-picker/catalog.test.ts src/components/icon-picker/IconPreview.tsx src/components/icon-picker/index.tsx
git commit -m "feat(icons): catalog loads {markup, meta, set} bundle; manifest-driven preview"
```

---

## Task 4: Tag-aware, category-aware filtering

**Files:**
- Modify: `src/components/icon-picker/filter.ts`
- Test: `src/components/icon-picker/filter.test.ts`

- [ ] **Step 1: Write the failing tests**

Replace the full contents of `src/components/icon-picker/filter.test.ts`:

```ts
import { filterIcons } from './filter';

describe( 'filterIcons', () => {
	const slugs = [ 'arrow-right', 'gear', 'gear-six', 'trend-up', 'trash' ];
	const meta = {
		'arrow-right': { c: [ 'arrows' ], t: [ 'east' ] },
		gear: { c: [ 'system' ], t: [ 'settings', 'preferences' ] },
		'gear-six': { c: [ 'system' ], t: [ 'settings' ] },
		'trend-up': { c: [ 'finances', 'office' ], t: [ 'charts', 'analysis' ] },
		trash: { c: [ 'office', 'system' ], t: [ 'delete', 'garbage' ] },
	};

	it( 'returns all slugs when query is empty and category is "all"', () => {
		expect( filterIcons( slugs, '', '', meta ) ).toEqual( slugs );
	} );

	it( 'trims and lowercases the query', () => {
		expect( filterIcons( slugs, '  GEAR ', '', meta ) ).toEqual( [
			'gear',
			'gear-six',
		] );
	} );

	it( 'narrows by category', () => {
		expect( filterIcons( slugs, '', 'system', meta ) ).toEqual( [
			'gear',
			'gear-six',
			'trash',
		] );
	} );

	it( 'matches a tag the slug does not contain', () => {
		// "trash" has no "delete" in its slug, but it is a tag.
		expect( filterIcons( slugs, 'delete', '', meta ) ).toEqual( [
			'trash',
		] );
	} );

	it( 'combines category and query', () => {
		expect( filterIcons( slugs, 'chart', 'office', meta ) ).toEqual( [
			'trend-up',
		] );
	} );

	it( 'falls back to slug-only search when meta is null', () => {
		expect( filterIcons( slugs, 'delete', '', null ) ).toEqual( [] );
		expect( filterIcons( slugs, 'gear', '', null ) ).toEqual( [
			'gear',
			'gear-six',
		] );
	} );

	it( 'ignores category filtering when meta is null', () => {
		expect( filterIcons( slugs, '', 'system', null ) ).toEqual( slugs );
	} );
} );
```

- [ ] **Step 2: Run the tests to verify they fail**

Run: `npm run test:js -- filter`
Expected: FAIL — `filterIcons` currently takes only `(slugs, query)` and has no category/meta handling.

- [ ] **Step 3: Implement the extended filter**

Replace the full contents of `src/components/icon-picker/filter.ts`:

```ts
import type { IconMeta } from './catalog';

/**
 * Filter icon slugs by category then query.
 *
 * - Category narrows first: '' or 'all' keeps everything; otherwise keep slugs
 *   whose meta categories include the selected one. Skipped when meta is null.
 * - Query then matches (case-insensitive substring) against the slug and, when
 *   meta is present, the slug's tags. Falls back to slug-only when meta is null.
 *
 * @param slugs    Full list of icon slugs.
 * @param query    Raw search input.
 * @param category Selected category ('' / 'all' = no category filter).
 * @param meta     Slug → { categories, tags }, or null when unavailable.
 * @return Matching slugs, in original order.
 */
export function filterIcons(
	slugs: string[],
	query: string,
	category = '',
	meta: Record< string, IconMeta > | null = null
): string[] {
	const cat = category.trim().toLowerCase();
	const q = query.trim().toLowerCase();
	let result = slugs;

	if ( cat && cat !== 'all' && meta ) {
		result = result.filter( ( slug ) => meta[ slug ]?.c.includes( cat ) );
	}

	if ( q ) {
		result = result.filter( ( slug ) => {
			if ( slug.includes( q ) ) {
				return true;
			}
			const tags = meta?.[ slug ]?.t;
			return tags ? tags.some( ( t ) => t.includes( q ) ) : false;
		} );
	}

	return result;
}
```

- [ ] **Step 4: Run the tests to verify they pass**

Run: `npm run test:js -- filter`
Expected: PASS — all `filterIcons` cases green.

- [ ] **Step 5: Commit**

```bash
git add src/components/icon-picker/filter.ts src/components/icon-picker/filter.test.ts
git commit -m "feat(icons): filter by category and match tags, not just slugs"
```

---

## Task 5: Category list + label helpers

**Files:**
- Create: `src/components/icon-picker/categories.ts`
- Test: `src/components/icon-picker/categories.test.ts`

- [ ] **Step 1: Write the failing tests**

Create `src/components/icon-picker/categories.test.ts`:

```ts
import { categoriesFromMeta, categoryLabel } from './categories';

describe( 'categoriesFromMeta', () => {
	it( 'returns sorted unique categories', () => {
		const meta = {
			gear: { c: [ 'system' ], t: [] },
			trash: { c: [ 'office', 'system' ], t: [] },
			'trend-up': { c: [ 'finances', 'office' ], t: [] },
		};
		expect( categoriesFromMeta( meta ) ).toEqual( [
			'finances',
			'office',
			'system',
		] );
	} );

	it( 'returns an empty array when meta is null', () => {
		expect( categoriesFromMeta( null ) ).toEqual( [] );
	} );
} );

describe( 'categoryLabel', () => {
	it( 'capitalises the first letter', () => {
		expect( categoryLabel( 'maps & travel' ) ).toBe( 'Maps & travel' );
		expect( categoryLabel( 'system' ) ).toBe( 'System' );
	} );
} );
```

- [ ] **Step 2: Run the tests to verify they fail**

Run: `npm run test:js -- categories`
Expected: FAIL — module `./categories` does not exist.

- [ ] **Step 3: Implement the helpers**

Create `src/components/icon-picker/categories.ts`:

```ts
import type { IconMeta } from './catalog';

/**
 * Derive the sorted, de-duplicated list of categories present in the metadata.
 *
 * @param meta Slug → { categories, tags }, or null.
 * @return Sorted unique category keys; [] when meta is null.
 */
export function categoriesFromMeta(
	meta: Record< string, IconMeta > | null
): string[] {
	if ( ! meta ) {
		return [];
	}
	const set = new Set< string >();
	for ( const slug in meta ) {
		for ( const category of meta[ slug ].c ) {
			set.add( category );
		}
	}
	return [ ...set ].sort();
}

/**
 * Human label for a category key (Phosphor keys are lower-case, e.g.
 * "maps & travel"). Capitalises the first letter only — full title-casing
 * mangles the ampersand phrases.
 *
 * @param category Category key.
 * @return Display label.
 */
export function categoryLabel( category: string ): string {
	return category.charAt( 0 ).toUpperCase() + category.slice( 1 );
}
```

- [ ] **Step 4: Run the tests to verify they pass**

Run: `npm run test:js -- categories`
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add src/components/icon-picker/categories.ts src/components/icon-picker/categories.test.ts
git commit -m "feat(icons): derive category list + display labels from meta"
```

---

## Task 6: Picker UI — category select, progressive grid, manifest preview

**Files:**
- Modify: `src/components/icon-picker/index.tsx`

- [ ] **Step 1: Rewrite the picker component**

Replace the full contents of `src/components/icon-picker/index.tsx`:

```tsx
import { __, _n, sprintf } from '@wordpress/i18n';
import {
	Button,
	Dropdown,
	SearchControl,
	SelectControl,
	Spinner,
	Notice,
} from '@wordpress/components';
import {
	useState,
	useEffect,
	useMemo,
	useRef,
} from '@wordpress/element';
import { filterIcons } from './filter';
import { categoriesFromMeta, categoryLabel } from './categories';
import { getCatalog, type IconData, type IconSet } from './catalog';

// How many icons to add to the grid each time the scroll sentinel appears.
const CHUNK = 120;

function IconGlyph( { markup, set }: { markup: string; set: IconSet } ) {
	return (
		<svg
			viewBox={ set.viewBox }
			width={ 24 }
			height={ 24 }
			aria-hidden="true"
			focusable="false"
			{ ...set.svgAttrs }
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
	const [ catalog, setCatalog ] = useState< IconData | null >( null );
	const [ error, setError ] = useState< string | null >( null );
	const [ query, setQuery ] = useState( '' );
	const [ category, setCategory ] = useState( '' );
	const [ visibleCount, setVisibleCount ] = useState( CHUNK );

	useEffect( () => {
		let active = true;
		if ( ! catalog ) {
			getCatalog()
				.then( ( data ) => active && setCatalog( data ) )
				.catch( ( err: Error ) => active && setError( err.message ) );
		}
		return () => {
			active = false;
		};
	}, [ catalog ] );

	const allSlugs = useMemo(
		() => ( catalog ? Object.keys( catalog.markup ) : [] ),
		[ catalog ]
	);
	const categories = useMemo(
		() => categoriesFromMeta( catalog?.meta ?? null ),
		[ catalog ]
	);
	const matches = useMemo(
		() => filterIcons( allSlugs, query, category, catalog?.meta ?? null ),
		[ allSlugs, query, category, catalog ]
	);

	// Reset the progressive window whenever the filter changes.
	useEffect( () => {
		setVisibleCount( CHUNK );
	}, [ query, category ] );

	const visible = matches.slice( 0, visibleCount );
	const hasMore = visibleCount < matches.length;

	const sentinelRef = useRef< HTMLDivElement | null >( null );
	useEffect( () => {
		if ( ! hasMore ) {
			return;
		}
		const el = sentinelRef.current;
		if ( ! el ) {
			return;
		}
		const observer = new IntersectionObserver( ( entries ) => {
			if ( entries.some( ( e ) => e.isIntersecting ) ) {
				setVisibleCount( ( c ) => c + CHUNK );
			}
		} );
		observer.observe( el );
		return () => observer.disconnect();
	}, [ hasMore, matches ] );

	const currentMarkup =
		catalog && value ? catalog.markup[ value ] : undefined;

	return (
		<div className="pediment-icon-picker">
			<span className="pediment-icon-picker__label">{ label }</span>
			<Dropdown
				className="pediment-icon-picker__dropdown"
				contentClassName="pediment-icon-picker__popover"
				popoverProps={ { placement: 'bottom-start' } }
				renderToggle={ ( {
					isOpen,
					onToggle,
				}: {
					isOpen: boolean;
					onToggle: () => void;
				} ) => (
					<Button
						variant="secondary"
						onClick={ onToggle }
						aria-expanded={ isOpen }
						className="pediment-icon-picker__toggle"
					>
						{ currentMarkup && catalog ? (
							<IconGlyph
								markup={ currentMarkup }
								set={ catalog.set }
							/>
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
								{ categories.length > 0 && (
									<SelectControl
										label={ __( 'Category', 'pediment' ) }
										value={ category }
										options={ [
											{
												label: __(
													'All categories',
													'pediment'
												),
												value: '',
											},
											...categories.map( ( c ) => ( {
												label: categoryLabel( c ),
												value: c,
											} ) ),
										] }
										onChange={ setCategory }
										__nextHasNoMarginBottom
									/>
								) }
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
									aria-label={ __( 'Icons', 'pediment' ) }
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
												markup={ catalog.markup[ slug ] }
												set={ catalog.set }
											/>
										</Button>
									) ) }
									{ hasMore && (
										<div
											ref={ sentinelRef }
											className="pediment-icon-picker__sentinel"
											aria-hidden="true"
										/>
									) }
								</div>
								{ matches.length === 0 && (
									<p className="pediment-icon-picker__empty">
										{ __( 'No icons match.', 'pediment' ) }
									</p>
								) }
								{ matches.length > 0 && (
									<p className="pediment-icon-picker__count">
										{ sprintf(
											/* translators: %d: number of matching icons. */
											_n(
												'%d icon',
												'%d icons',
												matches.length,
												'pediment'
											),
											matches.length
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

- [ ] **Step 2: Add styles for the scroll sentinel and count line**

Open `assets/css/icon-picker-editor.css` and append (a 1px sentinel that sits just inside the scroll area so the observer fires before the user hits the bottom; the grid must be the scroll container):

```css
.pediment-icon-picker__sentinel {
	flex-basis: 100%;
	height: 1px;
}

.pediment-icon-picker__count {
	margin: 4px 0 0;
	font-size: 11px;
	opacity: 0.7;
}
```

- [ ] **Step 3: Run the JS unit tests and a build**

Run: `npm run test:js && npm run build`
Expected: PASS — filter/categories/catalog tests green; build completes with no TypeScript errors.

- [ ] **Step 4: Lint the changed JS**

Run: `npm run lint:js`
Expected: PASS (no lint errors in `src/components/icon-picker/`).

- [ ] **Step 5: Commit**

```bash
git add src/components/icon-picker/index.tsx assets/css/icon-picker-editor.css
git commit -m "feat(icons): category filter + progressive scroll in the IconPicker"
```

---

## Task 7: End-to-end — discoverability in the editor

**Files:**
- Create: `tests/e2e/icon-picker.spec.ts`

The picker renders in the admin document (not the canvas iframe), so its controls are reachable on `page` directly. The feature block places the picker in an open `PanelBody` ("Feature") in the inspector.

- [ ] **Step 1: Write the e2e spec**

Create `tests/e2e/icon-picker.spec.ts`:

```ts
import { test, expect } from '@playwright/test';
import { login, createPageWithContent, deletePageBySlug } from './utils';

const SLUG = 'icon-picker-e2e';

test.describe( 'icon picker discoverability', () => {
	test.afterEach( () => {
		deletePageBySlug( SLUG );
	} );

	test( 'tag search and category filter surface deep icons', async ( {
		page,
	} ) => {
		deletePageBySlug( SLUG );
		const url = createPageWithContent(
			SLUG,
			'Icon Picker E2E',
			'<!-- wp:pediment/feature {"icon":"trend-up","title":"T","text":"x"} /-->'
		);
		const id = url.replace( /[^0-9]/g, '' );
		await login( page );
		await page.goto( `/wp-admin/post.php?post=${ id }&action=edit` );

		// Select the feature block so its inspector panel renders.
		const canvas = page.frameLocator( 'iframe[name="editor-canvas"]' );
		const block = canvas.locator( '.starter-feature' ).first();
		await expect( block ).toBeVisible( { timeout: 20000 } );
		await block.click();

		// Open the picker popover from the inspector toggle.
		const toggle = page.locator( '.pediment-icon-picker__toggle' );
		await expect( toggle ).toBeVisible();
		await toggle.click();

		// Tag search: "delete" is a tag of "trash" (the slug has no "delete").
		await page.getByPlaceholder( 'Search icons…' ).fill( 'delete' );
		await expect(
			page.locator(
				'.pediment-icon-picker__grid [role="option"][aria-label="trash"]'
			)
		).toBeVisible();

		// Clear search, filter by category "Maps & travel": "rocket" is in it.
		await page.getByPlaceholder( 'Search icons…' ).fill( '' );
		await page
			.locator( '.pediment-icon-picker__content select' )
			.selectOption( 'maps & travel' );
		await expect(
			page.locator(
				'.pediment-icon-picker__grid [role="option"][aria-label="rocket"]'
			)
		).toBeVisible();

		// Picking writes the slug back to the trigger.
		await page
			.locator(
				'.pediment-icon-picker__grid [role="option"][aria-label="rocket"]'
			)
			.click();
		await expect( toggle ).toContainText( 'rocket' );
	} );
} );
```

- [ ] **Step 2: Run the e2e spec (requires wp-env running on the theme's env)**

Run: `npm run e2e -- icon-picker`
Expected: PASS — the `trash` cell appears for the "delete" tag search, the `rocket` cell appears under "Maps & travel", and the toggle text becomes `rocket`.

If wp-env is not running, start it first per the project's env (`npm run env:start`) and re-run.

- [ ] **Step 3: Commit**

```bash
git add tests/e2e/icon-picker.spec.ts
git commit -m "test(icons): e2e for tag search + category filter in the picker"
```

---

## Task 8: Documentation contract note

**Files:**
- Modify: `tools/build-phosphor-data.sh` (header comment documenting the contract)

- [ ] **Step 1: Document the swap contract in the builder header**

Replace the top comment block of `tools/build-phosphor-data.sh` with:

```bash
#!/usr/bin/env bash
# Reference builder for the icon-set data contract. Regenerates four committed
# files under assets/icons/ from Phosphor core (regular weight, MIT):
#
#   icon-markup.php   — return array( 'slug' => '<inner svg>' ); read by PHP render
#   icon-markup.json  — { "slug": "<inner svg>" };            read by the editor grid
#   icon-meta.json    — { "slug": { "c": [cats], "t": [tags] } };  OPTIONAL (search + categories)
#   icon-set.json     — { name, version, viewBox, svgAttrs, license };  render manifest
#
# To swap icon sets: write a builder that emits these four files in these shapes.
# No theme code changes are needed — pediment_icon() and the IconPicker read only
# this contract. icon-meta.json is optional (the picker degrades to slug-only
# search with no category filter when it is absent). svgAttrs must carry every
# presentation attribute the wrapper <svg> needs (fill for filled sets;
# stroke/stroke-width/… for stroke sets like Lucide).
#
# Run manually when bumping the Phosphor version or swapping the set.
set -euo pipefail
```

- [ ] **Step 2: Re-run the validator and commit**

Run: `npm run lint:icons`
Expected: PASS.

```bash
git add tools/build-phosphor-data.sh
git commit -m "docs(icons): document the icon-set data contract in the builder header"
```

---

## Final verification

- [ ] `composer test` — all PHP tests pass
- [ ] `npm run test:js` — all JS unit tests pass (catalog, filter, categories)
- [ ] `npm run lint:js` — clean
- [ ] `npm run lint:icons` — contract holds
- [ ] `npm run build` — production build succeeds with no TypeScript errors
- [ ] `npm run e2e -- icon-picker` — discoverability e2e passes (wp-env running)
- [ ] Manual (canonical wp-env, port 8890): add a Feature block, search a tag (e.g. "delete" → trash), filter a category, scroll past 150 icons, pick a deep icon, confirm it renders on the frontend.
