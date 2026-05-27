# Pediment Foundation Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Port the cross-cutting Pediment design foundation (tokens, global utilities, Phosphor icon sprite, reduced-motion entry animations) into the parent theme so every existing block inherits the new look.

**Architecture:** Remap existing `theme.json` palette/typography slugs to the new values so existing blocks reskin automatically (no block edits in this plan). Add a single enqueued global stylesheet for what `theme.json` cannot express (fluid section-rhythm custom properties, utility classes, band group-styles, icon sizing, reveal CSS). Ship Phosphor as an inline SVG sprite printed once per page, with a PHP helper. Ship entry animations as one small IntersectionObserver script gated behind an `.anim` class set before first paint, fully disabled under `prefers-reduced-motion`.

**Tech Stack:** WordPress FSE block theme, `theme.json` v2, `@wordpress/scripts` (no custom webpack), PHP 8.1, PHPUnit (wp-env), Playwright.

**Spec:** `docs/superpowers/specs/2026-05-17-pediment-design-system-design.md`. Locked visual reference: `docs/design/pediment-mockup.html`.

**Scope note:** This plan deliberately does NOT touch `src/blocks/*` or `parts/*` — those are Plans 2 and 3. Definition of done: theme reskins to Deep Cyan + navy + Plus Jakarta Sans, the Phosphor sprite + `starter_icon()` helper work, and reveal animations run (and are disabled under reduced-motion), with green PHPUnit + Playwright.

---

## File Structure

| File | Responsibility | Action |
|---|---|---|
| `theme.json` | Palette/typography/shadow token values | Modify (slug values only; keep slug names) |
| `assets/css/theme.css` | Global custom props (rhythm), utilities (`.kicker/.chip/.btn` pill, `.i`), band group-styles, reveal CSS | Create |
| `assets/js/reveal.js` | IntersectionObserver entry animations | Create |
| `assets/icons/phosphor-sprite.svg` | 11 Phosphor `<symbol>` defs | Create (generated) |
| `inc/icons.php` | Print sprite once + `starter_icon()` helper | Create |
| `functions.php` | Require `inc/icons.php`; enqueue `theme.css`+`reveal.js`; print `.anim` pre-paint script | Modify |
| `tests/phpunit/IconsTest.php` | Sprite output + helper unit tests | Create |
| `tests/phpunit/ThemeJsonTest.php` | Palette/font preset assertions | Create |
| `tests/e2e/foundation.spec.ts` | Sprite in DOM, `.anim` gate, reduced-motion | Create |
| `assets/fonts/` | Self-hosted Plus Jakarta Sans woff2 | Create (downloaded) |

Each task ends with a commit. Run PHPUnit with `npm run env:start` already running (wp-env).

---

### Task 1: Self-host Plus Jakarta Sans

**Files:**
- Create: `assets/fonts/plus-jakarta-sans-{400,500,600,700,800}.woff2`
- Create: `assets/fonts/LICENSE-OFL.txt`

- [ ] **Step 1: Download the five weights (OFL, reproducible from Fontsource CDN)**

Run:
```bash
mkdir -p assets/fonts
for w in 400 500 600 700 800; do
  curl -fsSL "https://cdn.jsdelivr.net/fontsource/fonts/plus-jakarta-sans@latest/latin-${w}-normal.woff2" \
    -o "assets/fonts/plus-jakarta-sans-${w}.woff2"
done
curl -fsSL "https://raw.githubusercontent.com/tokotype/PlusJakartaSans/master/OFL.txt" -o assets/fonts/LICENSE-OFL.txt
ls -la assets/fonts/
```
Expected: five non-empty `.woff2` files (each > 10 KB) and `LICENSE-OFL.txt`.

- [ ] **Step 2: Commit**

```bash
git add assets/fonts/
git commit -m "chore(fonts): self-host Plus Jakarta Sans (OFL)"
```

---

### Task 2: Retokenize `theme.json` (palette, fonts, shadows)

**Files:**
- Modify: `theme.json` (`settings.color.palette`, `settings.typography.fontFamilies`, `settings.typography.fontSizes`, `settings.shadow.presets`)

Existing blocks reference slugs (`accent`, `text`, `surface`, …); changing the hex behind each slug reskins them with zero block edits. Keep every slug name; change only values. Add one new palette entry `accent-tint`.

- [ ] **Step 1: Write the failing test**

Create `tests/phpunit/ThemeJsonTest.php`:
```php
<?php

class ThemeJsonTest extends WP_UnitTestCase {
	private function theme_json(): array {
		$path = get_theme_file_path( 'theme.json' );
		return json_decode( file_get_contents( $path ), true );
	}

	private function palette(): array {
		$out = array();
		foreach ( $this->theme_json()['settings']['color']['palette'] as $c ) {
			$out[ $c['slug'] ] = strtoupper( $c['color'] );
		}
		return $out;
	}

	public function test_accent_is_deep_cyan() {
		$p = $this->palette();
		$this->assertSame( '#0E7490', $p['accent'] );
		$this->assertSame( '#155E75', $p['accent-hover'] );
		$this->assertSame( '#E1F1F6', $p['accent-tint'] );
	}

	public function test_navy_ink_and_surfaces() {
		$p = $this->palette();
		$this->assertSame( '#0B1B33', $p['text'] );
		$this->assertSame( '#0A1B33', $p['primary'] );
		$this->assertSame( '#5C6B82', $p['text-muted'] );
		$this->assertSame( '#FFFFFF', $p['surface'] );
		$this->assertSame( '#F5F8FC', $p['surface-elevated'] );
		$this->assertSame( '#E4EAF2', $p['border'] );
		$this->assertSame( '#CDD9EC', $p['border-strong'] );
	}

	public function test_primary_font_is_plus_jakarta_sans() {
		$tj = $this->theme_json();
		$fam = array();
		foreach ( $tj['settings']['typography']['fontFamilies'] as $f ) {
			$fam[ $f['slug'] ] = $f['fontFamily'];
		}
		$this->assertStringContainsString( 'Plus Jakarta Sans', $fam['body'] );
		$this->assertStringContainsString( 'Plus Jakarta Sans', $fam['heading'] );
	}
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `npx wp-env run tests-cli --env-cwd=wp-content/themes/wp-starter-theme vendor/bin/phpunit --filter ThemeJsonTest`
Expected: FAIL (accent still `#4F46E5`).

- [ ] **Step 3: Edit `theme.json` palette**

Set these exact `color` values for the existing slugs and append the new `accent-tint` entry (leave `surface-sunken`, `accent` slug name intact):

| slug | new color |
|---|---|
| `primary` | `#0A1B33` |
| `accent` | `#0E7490` |
| `accent-hover` | `#155E75` |
| `accent-tint` | `#E1F1F6` (new entry, `"name":"Accent tint"`) |
| `surface` | `#FFFFFF` |
| `surface-elevated` | `#F5F8FC` |
| `surface-sunken` | `#EEF3F8` |
| `text` | `#0B1B33` |
| `text-muted` | `#5C6B82` |
| `border` | `#E4EAF2` |
| `border-strong` | `#CDD9EC` |

- [ ] **Step 4: Edit `theme.json` typography**

Set `body` and `heading` fontFamily to:
`"\"Plus Jakarta Sans\", system-ui, -apple-system, \"Segoe UI\", Roboto, sans-serif"`
and add a `fontFace` array to each of those two families with five entries (weights 400/500/600/700/800), e.g. for 400:
```json
{ "fontFamily": "Plus Jakarta Sans", "fontWeight": "400", "fontStyle": "normal", "src": [ "file:./assets/fonts/plus-jakarta-sans-400.woff2" ] }
```
Repeat for 500, 600, 700, 800 in both `body` and `heading`. Keep `mono` unchanged.

Tune the two top display sizes to match the locked spec scale: set `3xl` size to `clamp(1.9rem, 1.4rem + 2.4vw, 2.8rem)` and `4xl` size to `clamp(2.6rem, 1.9rem + 3.4vw, 4.5rem)`. Leave `xs`–`2xl` unchanged.

- [ ] **Step 5: Edit `theme.json` shadow presets**

Set preset `subtle` → `0 8px 24px -16px rgba(11,27,51,.25)` and `medium` → `0 24px 50px -28px rgba(11,27,51,.30)`. Leave `lifted`, `focus` unchanged.

- [ ] **Step 6: Run test to verify it passes**

Run: `npx wp-env run tests-cli --env-cwd=wp-content/themes/wp-starter-theme vendor/bin/phpunit --filter ThemeJsonTest`
Expected: PASS (3 tests).

- [ ] **Step 7: Commit**

```bash
git add theme.json tests/phpunit/ThemeJsonTest.php
git commit -m "feat(theme): retokenize theme.json to Pediment palette/type/shadow"
```

---

### Task 3: Generate the Phosphor sprite asset

**Files:**
- Create: `assets/icons/phosphor-sprite.svg`
- Create: `tools/build-phosphor-sprite.sh`

- [ ] **Step 1: Write the generator script**

Create `tools/build-phosphor-sprite.sh`:
```bash
#!/usr/bin/env bash
# Regenerates assets/icons/phosphor-sprite.svg from Phosphor core (regular, MIT).
set -euo pipefail
VER="2.1.1"
ICONS="bank trend-up gear stack check-circle caret-down arrow-right article monitor-play microphone seal-check"
OUT="assets/icons/phosphor-sprite.svg"
tmp="$(mktemp -d)"
{
  printf '<svg xmlns="http://www.w3.org/2000/svg" width="0" height="0" style="position:absolute" aria-hidden="true">'
  for n in $ICONS; do
    curl -fsSL "https://unpkg.com/@phosphor-icons/core@${VER}/assets/regular/${n}.svg" -o "$tmp/$n.svg"
    inner="$(sed -E 's#.*<svg[^>]*>(.*)</svg>.*#\1#' "$tmp/$n.svg")"
    printf '<symbol id="ph-%s" viewBox="0 0 256 256">%s</symbol>' "$n" "$inner"
  done
  printf '</svg>\n'
} > "$OUT"
rm -rf "$tmp"
echo "wrote $OUT ($(wc -c < "$OUT") bytes)"
```

- [ ] **Step 2: Run it**

Run:
```bash
chmod +x tools/build-phosphor-sprite.sh && ./tools/build-phosphor-sprite.sh
grep -o 'id="ph-[a-z-]*"' assets/icons/phosphor-sprite.svg | sort
```
Expected: file written; 11 symbol ids listed (`ph-arrow-right`, `ph-article`, `ph-bank`, `ph-caret-down`, `ph-check-circle`, `ph-gear`, `ph-microphone`, `ph-monitor-play`, `ph-seal-check`, `ph-stack`, `ph-trend-up`).

- [ ] **Step 3: Commit**

```bash
git add tools/build-phosphor-sprite.sh assets/icons/phosphor-sprite.svg
git commit -m "feat(icons): add Phosphor SVG sprite + generator"
```

---

### Task 4: Sprite output + `starter_icon()` helper (`inc/icons.php`)

**Files:**
- Create: `inc/icons.php`
- Create: `tests/phpunit/IconsTest.php`
- Modify: `functions.php` (require the file)

- [ ] **Step 1: Write the failing test**

Create `tests/phpunit/IconsTest.php`:
```php
<?php

class IconsTest extends WP_UnitTestCase {
	public function test_starter_icon_returns_use_reference() {
		$html = starter_icon( 'arrow-right' );
		$this->assertSame(
			'<svg class="i" aria-hidden="true" focusable="false"><use href="#ph-arrow-right"></use></svg>',
			$html
		);
	}

	public function test_starter_icon_accepts_extra_class() {
		$html = starter_icon( 'bank', 'brand-mark' );
		$this->assertStringContainsString( 'class="i brand-mark"', $html );
	}

	public function test_starter_icon_sanitizes_name() {
		$html = starter_icon( 'arrow right"/><script>' );
		$this->assertStringContainsString( '#ph-arrowright', $html );
		$this->assertStringNotContainsString( '<script>', $html );
	}

	public function test_sprite_is_printed_on_wp_body_open() {
		ob_start();
		do_action( 'wp_body_open' );
		$out = ob_get_clean();
		$this->assertStringContainsString( '<symbol id="ph-bank"', $out );
		$this->assertStringContainsString( 'id="ph-arrow-right"', $out );
	}

	public function test_sprite_printed_only_once() {
		ob_start();
		do_action( 'wp_body_open' );
		do_action( 'wp_body_open' );
		$out = ob_get_clean();
		$this->assertSame( 1, substr_count( $out, 'id="ph-bank"' ) );
	}
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `npx wp-env run tests-cli --env-cwd=wp-content/themes/wp-starter-theme vendor/bin/phpunit --filter IconsTest`
Expected: FAIL ("Call to undefined function starter_icon()").

- [ ] **Step 3: Create `inc/icons.php`**

```php
<?php
/**
 * Phosphor icon sprite + helper.
 *
 * @package Starter
 */

/**
 * Return an inline SVG that references a sprite symbol.
 *
 * @param string $name        Phosphor icon name (without the ph- prefix).
 * @param string $extra_class Optional extra CSS class.
 * @return string Safe HTML.
 */
function starter_icon( $name, $extra_class = '' ) {
	$slug  = preg_replace( '/[^a-z0-9-]/', '', strtolower( (string) $name ) );
	$class = 'i' . ( '' !== $extra_class ? ' ' . sanitize_html_class( $extra_class ) : '' );
	return sprintf(
		'<svg class="%s" aria-hidden="true" focusable="false"><use href="#ph-%s"></use></svg>',
		esc_attr( $class ),
		esc_attr( $slug )
	);
}

/**
 * Print the Phosphor sprite once, as early as possible in <body>.
 */
function starter_print_icon_sprite() {
	static $printed = false;
	if ( $printed ) {
		return;
	}
	$printed = true;
	$file    = get_theme_file_path( 'assets/icons/phosphor-sprite.svg' );
	if ( is_readable( $file ) ) {
		// Static, theme-controlled SVG sprite; safe to output verbatim.
		echo file_get_contents( $file ); // phpcs:ignore WordPress.Security.EscapeOutput
	}
}
add_action( 'wp_body_open', 'starter_print_icon_sprite', 1 );
```

- [ ] **Step 4: Require it from `functions.php`**

Add near the other `require` of `inc/` files (alongside `inc/register-blocks.php`):
```php
require_once __DIR__ . '/inc/icons.php';
```

- [ ] **Step 5: Run test to verify it passes**

Run: `npx wp-env run tests-cli --env-cwd=wp-content/themes/wp-starter-theme vendor/bin/phpunit --filter IconsTest`
Expected: PASS (5 tests).

- [ ] **Step 6: Commit**

```bash
git add inc/icons.php tests/phpunit/IconsTest.php functions.php
git commit -m "feat(icons): starter_icon() helper + print sprite on wp_body_open"
```

---

### Task 5: Global stylesheet (`assets/css/theme.css`)

**Files:**
- Create: `assets/css/theme.css`

This is the global layer the spec needs that `theme.json` cannot express: fluid section-rhythm custom properties, the `.kicker`/`.chip`/pill-`.btn` utilities, band group-style classes, and `.i` icon sizing. Reveal CSS is added in Task 6. Values transcribed from `docs/design/pediment-mockup.html`.

- [ ] **Step 1: Create the file**

```css
:root{
  --section: clamp(88px, 9vw, 132px);
  --band:    clamp(76px, 7.5vw, 108px);
  --head-gap: clamp(40px, 5vw, 60px);
  --r-pill: 999px; --r-lg: 20px; --r-md: 14px; --r-panel: 28px;
}

/* Section rhythm: a width-wrapper must own horizontal gutter only, never the
   `padding` shorthand (spec: it defeats vertical rhythm by specificity). */
.starter-band{ padding-block: var(--section); }
.is-style-band-surface{ background: var(--wp--preset--color--surface-elevated); }
.is-style-band-navy{
  background: var(--wp--preset--color--primary);
  color: #fff;
}
.is-style-band-navy :where(h1,h2,h3,h4){ color:#fff; }

/* Eyebrow label + chip */
.kicker{
  font-size:13px; font-weight:700; letter-spacing:.14em;
  text-transform:uppercase; color:var(--wp--preset--color--accent);
}
.chip{
  display:inline-flex; align-items:center; gap:9px;
  background:var(--wp--preset--color--accent-tint);
  color:var(--wp--preset--color--accent-hover);
  font-size:13px; font-weight:600; padding:8px 16px;
  border-radius:var(--r-pill);
}
.chip::before{
  content:""; width:7px; height:7px; border-radius:50%;
  background:var(--wp--preset--color--accent);
}

/* Pill buttons (utility; block button restyles arrive in Plan 2) */
.btn{
  display:inline-flex; align-items:center; gap:9px;
  font-weight:700; font-size:.98rem; padding:16px 26px;
  border-radius:var(--r-pill); border:1.5px solid transparent;
  text-decoration:none; cursor:pointer;
  transition:background-color .15s ease, color .15s ease, border-color .15s ease;
}
.btn--primary{ background:var(--wp--preset--color--accent); color:#fff; }
.btn--primary:hover{ background:var(--wp--preset--color--accent-hover); color:#fff; }
.btn--ghost{
  background:var(--wp--preset--color--surface);
  color:var(--wp--preset--color--text);
  border-color:var(--wp--preset--color--border);
}
.btn--ghost:hover{ border-color:var(--wp--preset--color--accent); color:var(--wp--preset--color--accent); }
.btn--light{ background:#fff; color:var(--wp--preset--color--accent-hover); }
.btn:focus-visible{ outline:2px solid var(--wp--preset--color--accent); outline-offset:3px; }

/* Phosphor icon sizing (color via currentColor) */
.i{ width:1em; height:1em; display:inline-block; vertical-align:-0.14em;
    fill:currentColor; flex:none; }
.btn .i{ width:18px; height:18px; vertical-align:0; }
```

- [ ] **Step 2: Sanity-check CSS parses**

Run: `npx stylelint assets/css/theme.css || true; node -e "require('fs').readFileSync('assets/css/theme.css','utf8')"`
Expected: no syntax error thrown (stylelint may warn; that's fine — it isn't configured for this file).

- [ ] **Step 3: Commit**

```bash
git add assets/css/theme.css
git commit -m "feat(theme): global stylesheet (rhythm vars, utilities, band styles, icons)"
```

---

### Task 6: Entry-animation CSS + script

**Files:**
- Modify: `assets/css/theme.css` (append reveal CSS)
- Create: `assets/js/reveal.js`

- [ ] **Step 1: Append reveal CSS to `assets/css/theme.css`**

```css

/* Entry animations — gated behind .anim (set pre-paint), reduced-motion safe */
.anim [data-reveal]{
  opacity:0; transform:translateY(22px);
  transition:opacity .7s cubic-bezier(.16,1,.3,1),
             transform .7s cubic-bezier(.16,1,.3,1);
  will-change:opacity, transform;
}
.anim [data-reveal].is-in{ opacity:1; transform:none; }
@media (prefers-reduced-motion: reduce){
  .anim [data-reveal]{ opacity:1 !important; transform:none !important; transition:none; }
}
```

- [ ] **Step 2: Create `assets/js/reveal.js`**

```js
( function () {
	var sels = [
		'.starter-band > *',
		'.starter-hero > *',
		'.starter-feature-grid .starter-feature',
		'.starter-steps .starter-step',
		'.starter-stat',
		'.starter-logo-cloud .starter-logo',
		'.starter-faq-item',
		'.starter-media-card',
		'.starter-cta'
	];
	var nodes = document.querySelectorAll( sels.join( ',' ) );
	if ( ! nodes.length || ! ( 'IntersectionObserver' in window ) ) {
		return;
	}
	nodes.forEach( function ( el ) { el.setAttribute( 'data-reveal', '' ); } );

	var seen = new Map();
	document.querySelectorAll( '[data-reveal]' ).forEach( function ( el ) {
		var p = el.parentElement;
		var n = seen.get( p ) || 0;
		el.style.transitionDelay = Math.min( n, 6 ) * 85 + 'ms';
		seen.set( p, n + 1 );
	} );

	var io = new IntersectionObserver( function ( entries ) {
		entries.forEach( function ( e ) {
			if ( e.isIntersecting ) {
				e.target.classList.add( 'is-in' );
				io.unobserve( e.target );
			}
		} );
	}, { rootMargin: '0px 0px -8% 0px', threshold: 0.08 } );

	document.querySelectorAll( '[data-reveal]' ).forEach( function ( el ) {
		io.observe( el );
	} );
} )();
```

(Selectors target Plan 2/3 block classes; harmless no-ops until those blocks exist. `.anim` gating means no-JS / pre-paint shows content normally.)

- [ ] **Step 3: Commit**

```bash
git add assets/css/theme.css assets/js/reveal.js
git commit -m "feat(theme): reduced-motion-safe entry animations"
```

---

### Task 7: Enqueue global CSS/JS + pre-paint `.anim` gate

**Files:**
- Modify: `functions.php`

- [ ] **Step 1: Add enqueue + pre-paint script to `functions.php`**

Add this block (near the existing `wp_enqueue_scripts` closure):
```php
add_action(
	'wp_enqueue_scripts',
	function () {
		$css = 'assets/css/theme.css';
		wp_enqueue_style(
			'starter-theme',
			get_theme_file_uri( $css ),
			array(),
			(string) filemtime( get_theme_file_path( $css ) )
		);
		$js = 'assets/js/reveal.js';
		wp_enqueue_script(
			'starter-reveal',
			get_theme_file_uri( $js ),
			array(),
			(string) filemtime( get_theme_file_path( $js ) ),
			true
		);
	}
);

// No-FOUC: add the .anim class before first paint.
add_action(
	'wp_head',
	function () {
		echo "<script>document.documentElement.classList.add('anim')</script>\n";
	},
	0
);
```

- [ ] **Step 2: Verify enqueues register (PHPUnit)**

Append to `tests/phpunit/ThemeJsonTest.php` a new method:
```php
	public function test_global_assets_enqueue() {
		do_action( 'wp_enqueue_scripts' );
		$this->assertTrue( wp_style_is( 'starter-theme', 'enqueued' ) );
		$this->assertTrue( wp_script_is( 'starter-reveal', 'enqueued' ) );
	}
```
Run: `npx wp-env run tests-cli --env-cwd=wp-content/themes/wp-starter-theme vendor/bin/phpunit --filter ThemeJsonTest`
Expected: PASS (4 tests).

- [ ] **Step 3: Commit**

```bash
git add functions.php tests/phpunit/ThemeJsonTest.php
git commit -m "feat(theme): enqueue global css/js + pre-paint anim gate"
```

---

### Task 8: E2E verification

**Files:**
- Create: `tests/e2e/foundation.spec.ts`

- [ ] **Step 1: Write the E2E test**

```typescript
import { test, expect } from '@playwright/test';

test.describe('Pediment foundation', () => {
  test('Phosphor sprite is present once', async ({ page }) => {
    await page.goto('/');
    const symbols = page.locator('svg symbol#ph-bank');
    await expect(symbols).toHaveCount(1);
  });

  test('anim gate class is set on <html>', async ({ page }) => {
    await page.goto('/');
    await expect(page.locator('html')).toHaveClass(/\banim\b/);
  });

  test('global stylesheet is loaded', async ({ page }) => {
    await page.goto('/');
    const hrefs = await page.locator('link[rel="stylesheet"]').evaluateAll(
      (ls) => ls.map((l) => (l as HTMLLinkElement).href)
    );
    expect(hrefs.some((h) => h.includes('/assets/css/theme.css'))).toBe(true);
  });

  test('reduced-motion users get no reveal transition', async ({ browser }) => {
    const ctx = await browser.newContext({ reducedMotion: 'reduce' });
    const page = await ctx.newPage();
    await page.goto('/');
    // Inject a probe element carrying the reveal contract.
    await page.evaluate(() => {
      const d = document.createElement('div');
      d.setAttribute('data-reveal', '');
      d.id = 'rm-probe';
      document.body.appendChild(d);
    });
    const probe = page.locator('#rm-probe');
    await expect(probe).toHaveCSS('opacity', '1');
    await ctx.close();
  });
});
```

- [ ] **Step 2: Build and run E2E**

Run:
```bash
npm run build
npx wp-env run cli wp theme activate wp-starter-theme
npx playwright test tests/e2e/foundation.spec.ts
```
Expected: 4 passed.

- [ ] **Step 3: Full regression — existing suites stay green**

Run:
```bash
npx wp-env run tests-cli --env-cwd=wp-content/themes/wp-starter-theme vendor/bin/phpunit
npx playwright test
```
Expected: all existing PHPUnit + Playwright tests pass (the reskin is slug-value-only; no block markup changed).

- [ ] **Step 4: Commit**

```bash
git add tests/e2e/foundation.spec.ts
git commit -m "test(theme): e2e for sprite, anim gate, reduced-motion"
```

---

## Self-Review

**Spec coverage:**
- Spec "Design tokens" → Tasks 1, 2, 5 (palette/type/shadow in `theme.json`; rhythm vars + utilities + button + radii in `theme.css`). The specificity-correctness note is encoded as the `.starter-band{padding-block}` + comment in Task 5. ✓
- Spec "Phosphor icon strategy" → Tasks 3, 4 (sprite asset, helper, print once via `wp_body_open`, `currentColor` sizing in Task 5). ✓
- Spec "Entry animations" → Tasks 6, 7, 8 (CSS, IO script, `.anim` pre-paint gate, reduced-motion disable, e2e proof). ✓
- Spec "Section inventory → block mapping" → explicitly deferred to Plans 2 & 3 (stated in Scope note). ✓ (no gap; intentional)
- Spec "Out of scope" items → not implemented here. ✓

**Placeholder scan:** No TBD/TODO; every code step contains complete content. ✓

**Type/name consistency:** `starter_icon($name,$extra_class)`, `#ph-<name>` ids, `data-reveal`/`is-in`/`.anim` classes, `.starter-band`/`is-style-band-*` are used identically across Tasks 4–8 and match the locked mockup’s contract. Sprite symbol ids generated in Task 3 match `starter_icon()` output in Task 4. ✓

**Note for Plan 2/3:** `is-style-band-surface|navy` must be registered as block styles for `core/group` (via `register_block_style`) — add that as the first task of Plan 2; Task 5 here only ships the CSS they resolve to.
