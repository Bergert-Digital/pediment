# `starter/section-head` Block + Landing Layout Fixes — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Replace the `.head` CSS shim with a real `starter/section-head` block, migrate the Services and Insights bands to use it, and fix three theme-level layout bugs the audit surfaced (CTA box-sizing, Testimonial width, Services head left-edge).

**Architecture:** Server-side block with three RichText attributes (eyebrow/headline/lead) plus `alignment` ('start' | 'center') and `level` (2 | 3). Outer wrapper rides at `alignwide`; inner column has the `max-width` constraint — no `!important`, no fighting WordPress's constrained layout. Three independent CSS fixes (global `border-box`, pull-quote testimonial cap, removal of dead `.head`/`.lead` rules) round out the work. Each behavior is locked in by a Playwright assertion in `tests/e2e/landing-layout.spec.ts`.

**Tech Stack:** PHP (server-side render), TypeScript + `@wordpress/block-editor` (edit UI), SCSS (block + theme styles), PHPUnit (block render tests), Playwright (front-end layout regression).

**Spec:** [docs/superpowers/specs/2026-05-20-section-head-block-and-layout-fixes-design.md](docs/superpowers/specs/2026-05-20-section-head-block-and-layout-fixes-design.md)

**Audit baseline:** Before starting, run `node tools/audit-landing.mjs` and verify it prints the same 4 divergences the spec records. After every task that touches layout, re-run the audit and look at `test-results/audit/index.html` to confirm the targeted band now matches the mockup.

**Build note:** Whenever block source under `src/blocks/` changes, run `npm run build` before any E2E or PHPUnit test that exercises that block — the test runtime reads from `build/blocks/`.

**E2E note:** `playwright.config.ts` defaults `baseURL` to `http://localhost:8888`. Tests run `npx wp-env run cli …` from this theme's directory, which targets the same env. If your active wp-env is on `:8890` (child-theme env), set `PLAYWRIGHT_BASE_URL=http://localhost:8890` before `npm run e2e`.

---

## File Structure

| File | Status | Responsibility |
| --- | --- | --- |
| `src/blocks/section-head/block.json` | new | Block manifest (name, attributes, supports) |
| `src/blocks/section-head/index.tsx` | new | `registerBlockType` entry; imports edit + style |
| `src/blocks/section-head/edit.tsx` | new | Editor UI: 3 RichText inputs + InspectorControls |
| `src/blocks/section-head/render.php` | new | Server-side render of front-end HTML |
| `src/blocks/section-head/style.scss` | new | Block layout + typography (front + editor share) |
| `tests/phpunit/BlockRender/SectionHeadTest.php` | new | Render tests (attributes, alignment classes, suppression) |
| `tests/e2e/landing-layout.spec.ts` | new | 4 layout invariants (cta, testimonial, services head, insights head) |
| `tests/e2e/editor-blocks.spec.ts` | modify | Add section-head to the kitchen-sink list |
| `tests/phpunit/Patterns/PedimentLandingTest.php` | modify | Update services + insights band shape assertions |
| `patterns/pediment-landing.php` | modify | Replace `<group className:head>` in services; prepend section-head to insights |
| `assets/css/theme.css` | modify | Add global `box-sizing: border-box`; remove `.head` + `.lead` rules |
| `src/blocks/pull-quote/style.scss` | modify | Cap `.is-variant-testimonial` to 880px centered |

---

## Phase 1: Theme-level layout fixes

These are independent of the new block. Quick wins; do them first so the audit shows progress.

### Task 1: Global `box-sizing: border-box` reset (fixes CTA width)

**Files:**
- Create: `tests/e2e/landing-layout.spec.ts`
- Modify: `assets/css/theme.css` (top of file)

- [ ] **Step 1: Write the failing E2E test**

Create `tests/e2e/landing-layout.spec.ts`:

```ts
import { test, expect } from '@playwright/test';

test.describe('landing layout (1440×900)', () => {
  test.use({ viewport: { width: 1440, height: 900 } });

  test('cta: bounding box width matches max-width (border-box)', async ({ page }) => {
    await page.goto('/');
    const cta = page.locator('.starter-cta').first();
    await cta.scrollIntoViewIfNeeded();
    const { box, maxWidth } = await cta.evaluate((el) => {
      const r = el.getBoundingClientRect();
      return { box: { w: Math.round(r.width) }, maxWidth: window.getComputedStyle(el).maxWidth };
    });
    // alignwide → max-width: 1200px (wide-size). With border-box, the outer box
    // is exactly that. Without border-box (current bug), padding-inline adds
    // ~120px and the box swells to ~1320px.
    expect(maxWidth).toBe('1200px');
    expect(box.w).toBeLessThanOrEqual(1200);
  });
});
```

- [ ] **Step 2: Run the test to confirm it fails**

Run: `npm run e2e -- landing-layout.spec.ts`
Expected: FAIL — `Expected: <= 1200, Received: 1320`

- [ ] **Step 3: Add the global box-sizing reset**

Open `assets/css/theme.css`. Insert at the very top, before `:root`:

```css
*, *::before, *::after { box-sizing: border-box; }

```

- [ ] **Step 4: Run the test to confirm it passes**

Run: `npm run e2e -- landing-layout.spec.ts`
Expected: PASS — box width now 1200.

- [ ] **Step 5: Re-run the audit to see the CTA band fix visually**

Run: `node tools/audit-landing.mjs`
Open `test-results/audit/index.html` and confirm the CTA row now shows the rendered card at the same width as the mockup card (`x≈130, w≈1180–1200`).

- [ ] **Step 6: Commit**

```bash
git add assets/css/theme.css tests/e2e/landing-layout.spec.ts
git commit -m "fix(layout): global border-box reset (fixes CTA bounding width)"
```

---

### Task 2: Pull-quote testimonial 880px cap

**Files:**
- Modify: `tests/e2e/landing-layout.spec.ts`
- Modify: `src/blocks/pull-quote/style.scss`

- [ ] **Step 1: Add a failing test for the testimonial width**

Append inside the existing `test.describe` block in `tests/e2e/landing-layout.spec.ts`:

```ts
test('testimonial: pull-quote bounding box ≤ 900px wide', async ({ page }) => {
  await page.goto('/');
  const quote = page.locator('.starter-pull-quote.is-variant-testimonial').first();
  await quote.scrollIntoViewIfNeeded();
  const w = await quote.evaluate((el) => Math.round(el.getBoundingClientRect().width));
  // Mockup is 880px; allow 20px slack for the gutter rounding.
  expect(w).toBeLessThanOrEqual(900);
});
```

- [ ] **Step 2: Run the test to confirm it fails**

Run: `npm run e2e -- landing-layout.spec.ts -g "testimonial"`
Expected: FAIL — width ≈ 1200.

- [ ] **Step 3: Add the max-width cap to the testimonial variant**

Open `src/blocks/pull-quote/style.scss`. Find the `&.is-variant-testimonial` rule (or the equivalent selector for the testimonial variant). Add these two lines inside it:

```scss
max-width: 880px;
margin-inline: auto;
```

If no `.is-variant-testimonial` rule exists yet, create it as a sibling of the base `.starter-pull-quote` rule:

```scss
.starter-pull-quote.is-variant-testimonial {
  max-width: 880px;
  margin-inline: auto;
}
```

- [ ] **Step 4: Build, then run the test to confirm it passes**

Run: `npm run build && npm run e2e -- landing-layout.spec.ts -g "testimonial"`
Expected: PASS — width ≤ 900.

- [ ] **Step 5: Re-run the audit**

Run: `node tools/audit-landing.mjs`
Confirm the Testimonial row now shows the quote wrapping across multiple lines like the mockup.

- [ ] **Step 6: Commit**

```bash
git add src/blocks/pull-quote/style.scss tests/e2e/landing-layout.spec.ts
git commit -m "fix(pull-quote): cap testimonial variant to 880px centered"
```

---

## Phase 2: Build the section-head block

### Task 3: Scaffold the block (block.json + index.tsx + minimal render.php)

**Files:**
- Create: `src/blocks/section-head/block.json`
- Create: `src/blocks/section-head/index.tsx`
- Create: `src/blocks/section-head/edit.tsx` (placeholder)
- Create: `src/blocks/section-head/render.php` (placeholder)
- Create: `src/blocks/section-head/style.scss` (empty)
- Create: `tests/phpunit/BlockRender/SectionHeadTest.php`

- [ ] **Step 1: Write the failing PHPUnit test**

Create `tests/phpunit/BlockRender/SectionHeadTest.php`:

```php
<?php

class SectionHeadTest extends WP_UnitTestCase {
	private function render( array $attrs ): string {
		return do_blocks( '<!-- wp:starter/section-head ' . wp_json_encode( $attrs ) . ' /-->' );
	}

	public function test_block_is_registered() {
		do_action( 'init' );
		$this->assertTrue(
			WP_Block_Type_Registry::get_instance()->is_registered( 'starter/section-head' ),
			'starter/section-head must auto-register from build/blocks/'
		);
	}

	public function test_renders_root_class() {
		$html = $this->render( array( 'eyebrow' => 'X', 'headline' => 'Y', 'lead' => 'Z' ) );
		$this->assertStringContainsString( 'starter-section-head', $html );
	}
}
```

- [ ] **Step 2: Run the test to confirm it fails**

Run: `./vendor/bin/phpunit tests/phpunit/BlockRender/SectionHeadTest.php`
Expected: FAIL — block not registered.

- [ ] **Step 3: Create `src/blocks/section-head/block.json`**

```json
{
	"$schema": "https://schemas.wp.org/trunk/block.json",
	"apiVersion": 3,
	"name": "starter/section-head",
	"title": "Section Head",
	"category": "starter",
	"description": "Eyebrow + headline + lead intro for a section/band. Use at align:wide above content blocks.",
	"textdomain": "starter",
	"supports": { "html": false, "align": [ "wide" ] },
	"attributes": {
		"eyebrow":   { "type": "string", "default": "" },
		"headline":  { "type": "string", "default": "" },
		"lead":      { "type": "string", "default": "" },
		"alignment": { "type": "string", "default": "start", "enum": [ "start", "center" ] },
		"level":     { "type": "number", "default": 2, "enum": [ 2, 3 ] }
	},
	"editorScript": "file:./index.js",
	"editorStyle": "file:./style-index.css",
	"style": "file:./style-index.css",
	"render": "file:./render.php"
}
```

- [ ] **Step 4: Create `src/blocks/section-head/index.tsx`**

```tsx
import { registerBlockType } from '@wordpress/blocks';
import metadata from './block.json';
import Edit from './edit';
import './style.scss';

registerBlockType( metadata.name, { edit: Edit } );
```

- [ ] **Step 5: Create `src/blocks/section-head/edit.tsx` (minimal placeholder)**

```tsx
import { useBlockProps } from '@wordpress/block-editor';

export default function Edit() {
	const blockProps = useBlockProps( { className: 'starter-section-head' } );
	return <div { ...blockProps }>Section Head (placeholder)</div>;
}
```

- [ ] **Step 6: Create `src/blocks/section-head/render.php` (minimal placeholder)**

```php
<?php
/**
 * Server-side render for starter/section-head.
 *
 * @var array $attributes
 */

$wrapper = get_block_wrapper_attributes( array( 'class' => 'starter-section-head' ) );
?>
<div <?php echo $wrapper; // phpcs:ignore WordPress.Security.EscapeOutput ?>></div>
```

- [ ] **Step 7: Create empty `src/blocks/section-head/style.scss`**

```scss
// Layout + typography filled in in Task 5.
```

- [ ] **Step 8: Build and run the test to confirm it passes**

Run: `npm run build && ./vendor/bin/phpunit tests/phpunit/BlockRender/SectionHeadTest.php`
Expected: PASS — both `test_block_is_registered` and `test_renders_root_class` green.

- [ ] **Step 9: Commit**

```bash
git add src/blocks/section-head/ tests/phpunit/BlockRender/SectionHeadTest.php
git commit -m "feat(block): scaffold starter/section-head"
```

---

### Task 4: Implement full `render.php` (attributes + alignment + suppression)

**Files:**
- Modify: `src/blocks/section-head/render.php`
- Modify: `tests/phpunit/BlockRender/SectionHeadTest.php`

- [ ] **Step 1: Add the full set of failing render tests**

Replace the body of `tests/phpunit/BlockRender/SectionHeadTest.php` (keep the `render()` helper and the first two tests, append everything else):

```php
<?php

class SectionHeadTest extends WP_UnitTestCase {
	private function render( array $attrs ): string {
		return do_blocks( '<!-- wp:starter/section-head ' . wp_json_encode( $attrs ) . ' /-->' );
	}

	public function test_block_is_registered() {
		do_action( 'init' );
		$this->assertTrue(
			WP_Block_Type_Registry::get_instance()->is_registered( 'starter/section-head' )
		);
	}

	public function test_renders_root_class() {
		$html = $this->render( array( 'eyebrow' => 'X', 'headline' => 'Y', 'lead' => 'Z' ) );
		$this->assertStringContainsString( 'starter-section-head', $html );
	}

	public function test_renders_all_three_fields() {
		$html = $this->render(
			array(
				'eyebrow'  => 'What we do',
				'headline' => 'Our services',
				'lead'     => 'A short description.',
			)
		);
		$this->assertStringContainsString( '<p class="starter-section-head__eyebrow">What we do</p>', $html );
		$this->assertStringContainsString( '<h2 class="starter-section-head__headline">Our services</h2>', $html );
		$this->assertStringContainsString( '<p class="starter-section-head__lead">A short description.</p>', $html );
	}

	public function test_level_3_renders_h3() {
		$html = $this->render( array( 'headline' => 'Sub', 'level' => 3 ) );
		$this->assertStringContainsString( '<h3 class="starter-section-head__headline">Sub</h3>', $html );
	}

	public function test_alignment_start_emits_is_alignment_start() {
		$html = $this->render( array( 'headline' => 'X', 'alignment' => 'start' ) );
		$this->assertStringContainsString( 'is-alignment-start', $html );
		$this->assertStringNotContainsString( 'is-alignment-center', $html );
	}

	public function test_alignment_center_emits_is_alignment_center() {
		$html = $this->render( array( 'headline' => 'X', 'alignment' => 'center' ) );
		$this->assertStringContainsString( 'is-alignment-center', $html );
		$this->assertStringNotContainsString( 'is-alignment-start', $html );
	}

	public function test_empty_eyebrow_is_suppressed() {
		$html = $this->render( array( 'eyebrow' => '', 'headline' => 'Y', 'lead' => 'Z' ) );
		$this->assertStringNotContainsString( 'starter-section-head__eyebrow', $html );
		$this->assertStringContainsString( 'starter-section-head__headline', $html );
	}

	public function test_empty_headline_is_suppressed() {
		$html = $this->render( array( 'eyebrow' => 'X', 'headline' => '', 'lead' => 'Z' ) );
		$this->assertStringNotContainsString( 'starter-section-head__headline', $html );
		$this->assertStringContainsString( 'starter-section-head__lead', $html );
	}

	public function test_empty_lead_is_suppressed() {
		$html = $this->render( array( 'eyebrow' => 'X', 'headline' => 'Y', 'lead' => '' ) );
		$this->assertStringNotContainsString( 'starter-section-head__lead', $html );
		$this->assertStringContainsString( 'starter-section-head__headline', $html );
	}

	public function test_inner_column_wraps_fields() {
		$html = $this->render( array( 'headline' => 'X' ) );
		$this->assertStringContainsString( '<div class="starter-section-head__inner">', $html );
	}
}
```

- [ ] **Step 2: Run the tests to confirm they fail**

Run: `./vendor/bin/phpunit tests/phpunit/BlockRender/SectionHeadTest.php`
Expected: 9 tests, 7 of them FAIL (only the first two pass — registration + root class).

- [ ] **Step 3: Implement `render.php`**

Replace the entire contents of `src/blocks/section-head/render.php`:

```php
<?php
/**
 * Server-side render for starter/section-head.
 *
 * @var array $attributes
 */

$eyebrow   = isset( $attributes['eyebrow'] ) ? (string) $attributes['eyebrow'] : '';
$headline  = isset( $attributes['headline'] ) ? (string) $attributes['headline'] : '';
$lead      = isset( $attributes['lead'] ) ? (string) $attributes['lead'] : '';
$alignment = isset( $attributes['alignment'] ) && 'center' === $attributes['alignment'] ? 'center' : 'start';
$level     = isset( $attributes['level'] ) && 3 === (int) $attributes['level'] ? 3 : 2;
$h_tag     = 'h' . $level;

$wrapper = get_block_wrapper_attributes(
	array(
		'class' => 'starter-section-head is-alignment-' . $alignment,
	)
);

ob_start();
?>
<div <?php echo $wrapper; // phpcs:ignore WordPress.Security.EscapeOutput ?>>
	<div class="starter-section-head__inner">
		<?php if ( '' !== $eyebrow ) : ?>
			<p class="starter-section-head__eyebrow"><?php echo wp_kses_post( $eyebrow ); ?></p>
		<?php endif; ?>
		<?php if ( '' !== $headline ) : ?>
			<<?php echo $h_tag; // phpcs:ignore WordPress.Security.EscapeOutput ?> class="starter-section-head__headline"><?php echo wp_kses_post( $headline ); ?></<?php echo $h_tag; // phpcs:ignore WordPress.Security.EscapeOutput ?>>
		<?php endif; ?>
		<?php if ( '' !== $lead ) : ?>
			<p class="starter-section-head__lead"><?php echo wp_kses_post( $lead ); ?></p>
		<?php endif; ?>
	</div>
</div>
<?php
echo ob_get_clean();
```

- [ ] **Step 4: Build and run the tests to confirm they pass**

Run: `npm run build && ./vendor/bin/phpunit tests/phpunit/BlockRender/SectionHeadTest.php`
Expected: PASS — all 9 tests green.

> **If a test fails on whitespace** (e.g. the assertion sees `<p class="…__eyebrow"\n>…</p>` because of the heredoc indentation): tighten the template by removing the leading tab/newline before the opening tag in the `if` blocks, or change the assertion to use `assertMatchesRegularExpression`. Prefer fixing the template.

- [ ] **Step 5: Commit**

```bash
git add src/blocks/section-head/render.php tests/phpunit/BlockRender/SectionHeadTest.php
git commit -m "feat(section-head): full render (3 fields, alignment, level, suppression)"
```

---

### Task 5: Implement `style.scss` (layout contract)

**Files:**
- Modify: `src/blocks/section-head/style.scss`

This task adds the CSS. The layout assertions that prove it works are in Task 7 (services migration) and Task 8 (insights migration) — they need the block in a real band to measure. Here we just check the build compiles.

- [ ] **Step 1: Replace the placeholder `style.scss` with the full styles**

Replace `src/blocks/section-head/style.scss`:

```scss
// The outer wrapper rides at alignwide (parent constrained band centers it at
// wide-size). The __inner element carries the column constraint. No !important
// because we're not fighting WordPress's auto-centering — we let it do its job
// on the outer, and constrain the inner ourselves.

.starter-section-head {
  margin-bottom: var(--head-gap, clamp(40px, 5vw, 60px));

  &__inner {
    max-width: 600px;
  }

  &.is-alignment-start &__inner {
    margin-inline: 0 auto;
  }

  &.is-alignment-center &__inner {
    max-width: 620px;
    margin-inline: auto;
    text-align: center;
  }

  &__eyebrow {
    margin: 0;
    font-size: 13px;
    font-weight: 700;
    letter-spacing: 0.14em;
    text-transform: uppercase;
    color: var(--wp--preset--color--accent);
  }

  &__headline {
    margin: 14px 0 0;
  }

  &__lead {
    margin: 16px 0 0;
    font-size: 1.15rem;
    color: var(--wp--preset--color--text-muted);
    line-height: 1.6;
  }
}
```

- [ ] **Step 2: Build and confirm no SCSS errors**

Run: `npm run build`
Expected: clean build, no errors.

- [ ] **Step 3: Smoke-check the compiled stylesheet contains the rules**

Run: `grep -c "starter-section-head__inner" build/blocks/section-head/style-index.css`
Expected: `>= 1` (the inner selector landed in the compiled CSS).

- [ ] **Step 4: Commit**

```bash
git add src/blocks/section-head/style.scss
git commit -m "feat(section-head): layout + typography styles"
```

---

### Task 6: Implement `edit.tsx` (RichText inputs + Inspector controls)

**Files:**
- Modify: `src/blocks/section-head/edit.tsx`
- Modify: `tests/e2e/editor-blocks.spec.ts`

- [ ] **Step 1: Add section-head to the kitchen-sink editor smoke test**

Open `tests/e2e/editor-blocks.spec.ts`. Inside `BLOCKS_TO_VERIFY`, add this line (preserve the existing alphabetical-ish order — put it after `pull-quote`):

```ts
{ name: 'section-head', cls: 'starter-section-head', markup: '<!-- wp:starter/section-head {"eyebrow":"E","headline":"H","lead":"L"} /-->' },
```

- [ ] **Step 2: Run the smoke test to confirm it currently passes**

Run: `npm run e2e -- editor-blocks.spec.ts`
Expected: PASS — the front-end already renders the block (from Task 4); the editor side will hit the placeholder Edit component but the front-end check is what this spec asserts.

> If this fails because of a stale build, run `npm run build` first.

- [ ] **Step 3: Replace the placeholder `edit.tsx` with the real editor UI**

Replace `src/blocks/section-head/edit.tsx`:

```tsx
import { __ } from '@wordpress/i18n';
import {
	useBlockProps,
	RichText,
	InspectorControls,
} from '@wordpress/block-editor';
import {
	PanelBody,
	ToggleGroupControl,
	ToggleGroupControlOption,
} from '@wordpress/components';

type Attrs = {
	eyebrow: string;
	headline: string;
	lead: string;
	alignment: 'start' | 'center';
	level: 2 | 3;
};

export default function Edit( {
	attributes,
	setAttributes,
}: {
	attributes: Attrs;
	setAttributes: ( a: Partial< Attrs > ) => void;
} ) {
	const blockProps = useBlockProps( {
		className: `starter-section-head is-alignment-${ attributes.alignment }`,
	} );
	const HeadingTag = `h${ attributes.level }` as 'h2' | 'h3';
	return (
		<>
			<InspectorControls>
				<PanelBody title={ __( 'Section head', 'starter' ) }>
					<ToggleGroupControl
						label={ __( 'Alignment', 'starter' ) }
						value={ attributes.alignment }
						onChange={ ( v ) =>
							setAttributes( { alignment: ( v as 'start' | 'center' ) ?? 'start' } )
						}
						isBlock
					>
						<ToggleGroupControlOption value="start" label={ __( 'Start', 'starter' ) } />
						<ToggleGroupControlOption value="center" label={ __( 'Center', 'starter' ) } />
					</ToggleGroupControl>
					<ToggleGroupControl
						label={ __( 'Heading level', 'starter' ) }
						value={ String( attributes.level ) }
						onChange={ ( v ) =>
							setAttributes( { level: v === '3' ? 3 : 2 } )
						}
						isBlock
					>
						<ToggleGroupControlOption value="2" label="H2" />
						<ToggleGroupControlOption value="3" label="H3" />
					</ToggleGroupControl>
				</PanelBody>
			</InspectorControls>
			<div { ...blockProps }>
				<div className="starter-section-head__inner">
					<RichText
						tagName="p"
						className="starter-section-head__eyebrow"
						value={ attributes.eyebrow }
						onChange={ ( v ) => setAttributes( { eyebrow: v } ) }
						placeholder={ __( 'Eyebrow…', 'starter' ) }
						allowedFormats={ [] }
					/>
					<RichText
						tagName={ HeadingTag }
						className="starter-section-head__headline"
						value={ attributes.headline }
						onChange={ ( v ) => setAttributes( { headline: v } ) }
						placeholder={ __( 'Headline…', 'starter' ) }
						allowedFormats={ [] }
					/>
					<RichText
						tagName="p"
						className="starter-section-head__lead"
						value={ attributes.lead }
						onChange={ ( v ) => setAttributes( { lead: v } ) }
						placeholder={ __( 'Lead…', 'starter' ) }
						allowedFormats={ [] }
					/>
				</div>
			</div>
		</>
	);
}
```

- [ ] **Step 4: Build and run the smoke test again**

Run: `npm run build && npm run e2e -- editor-blocks.spec.ts`
Expected: PASS — `.starter-section-head` visible on the kitchen-sink page.

- [ ] **Step 5: Commit**

```bash
git add src/blocks/section-head/edit.tsx tests/e2e/editor-blocks.spec.ts
git commit -m "feat(section-head): editor UI (3 RichText fields + alignment/level controls)"
```

---

## Phase 3: Pattern migration

### Task 7: Migrate the Services band to use `starter/section-head`

**Files:**
- Modify: `tests/e2e/landing-layout.spec.ts`
- Modify: `tests/phpunit/Patterns/PedimentLandingTest.php`
- Modify: `patterns/pediment-landing.php`

- [ ] **Step 1: Add the failing layout-invariant E2E test for the Services band**

Append inside the `test.describe` block in `tests/e2e/landing-layout.spec.ts`:

```ts
test('services: section-head and feature-grid share the same left edge', async ({ page }) => {
  await page.goto('/');
  // Services band is the 2nd starter-band (index 1) in pediment-landing.
  const band = page.locator('.entry-content > .starter-band').nth(1);
  await band.scrollIntoViewIfNeeded();
  const head = band.locator('.starter-section-head');
  const grid = band.locator('.starter-feature-grid');
  const headX = await head.evaluate((el) => Math.round(el.getBoundingClientRect().left));
  const gridX = await grid.evaluate((el) => Math.round(el.getBoundingClientRect().left));
  // The whole point of the new block: head and grid sit on the same alignwide
  // left edge. Allow 1px rounding slack.
  expect(Math.abs(headX - gridX)).toBeLessThanOrEqual(1);
});
```

- [ ] **Step 2: Update the pattern-shape assertion in PHPUnit**

Open `tests/phpunit/Patterns/PedimentLandingTest.php` and read it fully. Find any test that asserts the services band's innerBlocks. The existing version (from commit `3f86028`) expects them to be `[core/group (className=head), starter/feature-grid]`. Change that expectation to `[starter/section-head, starter/feature-grid]`.

If no such test exists yet, add this one inside the class:

```php
public function test_services_band_uses_section_head_block() {
	$content = $this->pattern()['content'];
	$blocks  = parse_blocks( $content );
	$top     = array_values(
		array_filter(
			$blocks,
			static fn( $b ) => ! empty( $b['blockName'] )
		)
	);
	// 2nd top-level band (index 1) is the Services band.
	$services_band = $top[1];
	$inner_names   = array_values(
		array_filter(
			array_map( static fn( $b ) => $b['blockName'], $services_band['innerBlocks'] )
		)
	);
	$this->assertSame(
		array( 'starter/section-head', 'starter/feature-grid' ),
		$inner_names,
		'services band should be [section-head, feature-grid]'
	);
}
```

- [ ] **Step 3: Run both tests to confirm they fail**

Run:
```bash
npm run build
./vendor/bin/phpunit tests/phpunit/Patterns/PedimentLandingTest.php
npm run e2e -- landing-layout.spec.ts -g "services"
```
Expected: PHPUnit FAIL (still sees `core/group`); E2E FAIL (`.starter-section-head` not found in services band).

- [ ] **Step 4: Migrate the Services band in the pattern**

Open `patterns/pediment-landing.php`. Find the services band — the 2nd top-level `<!-- wp:group {"align":"full","className":"starter-band …` group. Inside it, locate the inner `<!-- wp:group {"className":"head" … --> … <!-- /wp:group -->` block (containing the kicker paragraph, `wp:heading`, and lead paragraph). Replace that entire inner group (open comment through close comment, inclusive) with a single line:

```html
<!-- wp:starter/section-head {"align":"wide","alignment":"start","eyebrow":"What we do","headline":"A short headline framing the services you offer","lead":"One sentence describing how your services fit together and the outcome you deliver."} /-->
```

Leave the surrounding band group (`<!-- wp:group {"align":"full","className":"starter-band …`) and the `<!-- wp:starter/feature-grid … -->` block untouched.

- [ ] **Step 5: Run both tests to confirm they pass**

Run:
```bash
npm run build
./vendor/bin/phpunit tests/phpunit/Patterns/PedimentLandingTest.php
npm run e2e -- landing-layout.spec.ts -g "services"
```
Expected: PASS on both.

- [ ] **Step 6: Re-run the audit and look at the Services row**

Run: `node tools/audit-landing.mjs`
Open `test-results/audit/index.html`. Services row: head and grid should now both start at x ≈ 120 (same left edge), matching the mockup's "head and grid both start at x ≈ 170" relationship.

- [ ] **Step 7: Commit**

```bash
git add patterns/pediment-landing.php tests/phpunit/Patterns/PedimentLandingTest.php tests/e2e/landing-layout.spec.ts
git commit -m "feat(pattern): services band uses starter/section-head"
```

---

### Task 8: Add `starter/section-head` to the Insights band

**Files:**
- Modify: `tests/e2e/landing-layout.spec.ts`
- Modify: `tests/phpunit/Patterns/PedimentLandingTest.php`
- Modify: `patterns/pediment-landing.php`

- [ ] **Step 1: Add the failing layout-invariant E2E test for the Insights head**

Append inside the `test.describe` block in `tests/e2e/landing-layout.spec.ts`:

```ts
test('insights: section-head is centered', async ({ page }) => {
  await page.goto('/');
  // Insights is the last starter-band on the landing page.
  const bands = page.locator('.entry-content > .starter-band');
  const insightsBand = bands.last();
  await insightsBand.scrollIntoViewIfNeeded();
  const head = insightsBand.locator('.starter-section-head');
  await expect(head).toBeVisible();
  const { textAlign, leftMargin, rightMargin, parentWidth, boxWidth, boxLeft } =
    await head.evaluate((el) => {
      const inner = el.querySelector('.starter-section-head__inner') as HTMLElement;
      const cs = window.getComputedStyle(inner);
      const r = inner.getBoundingClientRect();
      const parentR = (el as HTMLElement).getBoundingClientRect();
      return {
        textAlign: cs.textAlign,
        leftMargin: cs.marginLeft,
        rightMargin: cs.marginRight,
        parentWidth: Math.round(parentR.width),
        boxWidth: Math.round(r.width),
        boxLeft: Math.round(r.left - parentR.left),
      };
    });
  expect(textAlign).toBe('center');
  // is-alignment-center → margin-inline: auto → symmetric leading/trailing space.
  const trailing = parentWidth - boxLeft - boxWidth;
  expect(Math.abs(boxLeft - trailing)).toBeLessThanOrEqual(2);
});
```

- [ ] **Step 2: Add a PHPUnit assertion for the insights band shape**

In `tests/phpunit/Patterns/PedimentLandingTest.php`, add inside the class:

```php
public function test_insights_band_starts_with_section_head() {
	$content = $this->pattern()['content'];
	$blocks  = parse_blocks( $content );
	$top     = array_values(
		array_filter(
			$blocks,
			static fn( $b ) => ! empty( $b['blockName'] )
		)
	);
	$insights_band   = end( $top );
	$first_inner     = $insights_band['innerBlocks'][0];
	$this->assertSame( 'starter/section-head', $first_inner['blockName'] );
	$this->assertSame( 'center', $first_inner['attrs']['alignment'] );
}
```

> **Pre-flight check before running:** parse the current pattern with `parse_blocks` mentally. If the last top-level group (insights band) currently contains *only* `starter/blog-index`, the assertion above will fail until we add the section-head. If something else is in there (e.g. another wrapper), open `patterns/pediment-landing.php` and locate the insights band manually — it's the last `<!-- wp:group {"align":"full","className":"starter-band …` block in the file.

- [ ] **Step 3: Run both tests to confirm they fail**

Run:
```bash
./vendor/bin/phpunit tests/phpunit/Patterns/PedimentLandingTest.php
npm run e2e -- landing-layout.spec.ts -g "insights"
```
Expected: PHPUnit FAIL; E2E FAIL (`.starter-section-head` not present in insights band).

- [ ] **Step 4: Prepend section-head to the Insights band**

Open `patterns/pediment-landing.php`. Find the insights band (the last band group in the file). Inside that band group, immediately before the `<!-- wp:starter/blog-index … -->` block, insert:

```html
<!-- wp:starter/section-head {"align":"wide","alignment":"center","eyebrow":"Insights","headline":"A short headline introducing the insights grid","lead":"One sentence framing what readers will find here."} /-->
```

Do not change the blog-index block itself.

- [ ] **Step 5: Run both tests to confirm they pass**

Run:
```bash
./vendor/bin/phpunit tests/phpunit/Patterns/PedimentLandingTest.php
npm run e2e -- landing-layout.spec.ts -g "insights"
```
Expected: PASS.

- [ ] **Step 6: Re-run the audit**

Run: `node tools/audit-landing.mjs`
Open `test-results/audit/index.html`. Insights row: rendered should now show a centered eyebrow/headline/lead above the blog grid, matching the mockup.

- [ ] **Step 7: Commit**

```bash
git add patterns/pediment-landing.php tests/phpunit/Patterns/PedimentLandingTest.php tests/e2e/landing-layout.spec.ts
git commit -m "feat(pattern): centered section-head above insights grid"
```

---

### Task 9: Delete the dead `.head` and `.lead` rules

**Files:**
- Modify: `assets/css/theme.css`

- [ ] **Step 1: Run the layout suite to confirm everything's green before the cleanup**

Run: `npm run e2e -- landing-layout.spec.ts`
Expected: all 4 tests PASS. If any fail, fix before deleting CSS.

- [ ] **Step 2: Open `assets/css/theme.css` and delete lines 29–42**

Lines to delete (verify the content before removing):

```css
/* Section head (kicker + h2 + lead). Mockup: max-width:600px + bottom gap,
   anchored to the band's left edge. WordPress's constrained-layout child
   rule (margin-left:auto !important; margin-right:auto !important) centers
   anything narrower than the content-size box, so we have to override with
   !important here to anchor the head to the band's inline-start edge. */
.is-layout-constrained > .head,
.head{
  max-width:600px !important;
  margin-inline-start:0 !important;
  margin-inline-end:auto !important;
  margin-bottom:var(--head-gap);
}
.head h2{ margin-top:14px; }
.lead{ font-size:1.15rem; color:var(--wp--preset--color--text-muted); line-height:1.6; }
```

Delete the entire block (the 5-line comment + 8 lines of rules). The new block owns these styles now.

- [ ] **Step 3: Re-run the full layout suite to confirm nothing regresses**

Run: `npm run e2e -- landing-layout.spec.ts`
Expected: all 4 tests still PASS.

- [ ] **Step 4: Confirm `.head` and `.lead` are gone from the codebase**

Run:
```bash
grep -rn '"head"\|\.head\b\|className.*\\bhead\\b\|\.lead\b' assets/ patterns/ src/blocks/ || echo "clean"
```
Expected: prints `clean` (or only matches inside `starter-section-head` / inside the block's internal `__lead` class, both of which are namespaced and fine — none of the bare `.head` / `.lead` / `className:head` should remain).

Manually inspect any remaining matches. Acceptable matches: `__lead`, `__head…`, `starter-section-head`. Unacceptable: bare `.head`, bare `.lead`, `className:"head"`, `className:"lead"`.

- [ ] **Step 5: Commit**

```bash
git add assets/css/theme.css
git commit -m "chore(theme): remove dead .head/.lead rules (replaced by starter/section-head)"
```

---

## Phase 4: Final verification

### Task 10: Full audit and full test pass

**Files:** (verification only — no changes expected)

- [ ] **Step 1: Build clean**

Run: `npm run build`
Expected: clean output, no warnings.

- [ ] **Step 2: Run the full PHPUnit suite**

Run: `./vendor/bin/phpunit`
Expected: all tests green. The pre-existing PedimentLanding tests may need pattern-shape numbers updated (e.g. innerBlock counts in the services and insights bands changed from 4 → 2 and from 1 → 2 respectively). If any assertion fails on a stale count, fix it inline — those are spec-aware updates, not bugs.

- [ ] **Step 3: Run the full Playwright E2E suite**

Run: `npm run e2e`
Expected: all tests green.

- [ ] **Step 4: Run the audit one final time and screenshot the side-by-side**

Run: `node tools/audit-landing.mjs && open test-results/audit/index.html`
Visually verify:
- Hero — unchanged
- **Services — head and grid share left edge** (was the user-reported bug)
- Approach — unchanged
- Stats — unchanged
- **Testimonial — quote narrow, multi-line, centered** (was 1200px single-line)
- FAQ — unchanged
- **CTA — card matches mockup width** (was 60px wider on each side)
- **Insights — centered head above grid** (was missing)

- [ ] **Step 5: Final grep guard**

Run:
```bash
grep -rn 'margin-inline-start:\s*0\s*!important' assets/ src/blocks/ || echo "no anchor hacks"
```
Expected: prints `no anchor hacks`. We're not using `!important` to anchor layout anywhere anymore.

- [ ] **Step 6: Commit any documentation or stragglers (optional)**

If anything cleanup-y came up during the audit, commit it now with `chore: post-audit cleanup`. Otherwise nothing to commit at this step.

- [ ] **Step 7: Summary message to the user**

Report: 4 layout bugs fixed (services head, testimonial width, CTA box-sizing, insights head added), 1 new block (`starter/section-head`) replacing the `.head` CSS shim, 4 Playwright invariants in `landing-layout.spec.ts` to keep this from regressing. Audit script (`tools/audit-landing.mjs`) is reusable for any future band drift.

---

## Risks and notes for the implementer

- **Pattern uncached:** WordPress caches registered block patterns. If a PHPUnit test asserts on the pattern after you edit `patterns/pediment-landing.php`, do `do_action( 'init' )` again or restart wp-env to clear. The existing `PedimentLandingTest` already does `do_action( 'init' )` inside its `pattern()` helper — follow that idiom.
- **`build/blocks/section-head/`** must exist before the PHPUnit `is_registered` test runs. The plan calls out `npm run build` at every relevant step, but if you see "block not registered" mid-task, suspect a stale build.
- **CSS variable fallback in `style.scss`:** `--head-gap` is defined in `assets/css/theme.css:4` (`--head-gap: clamp(40px, 5vw, 60px)`). The block's `style.scss` uses `var(--head-gap, clamp(40px, 5vw, 60px))` so it still renders sensibly if loaded outside the theme (e.g. in the block editor iframe before theme.css attaches). Don't simplify this away.
- **Insights band copy is placeholder.** "A short headline introducing the insights grid" is meant to be rebrandable, matching the rest of the pattern's tone. Don't try to write final marketing copy; the pattern is generic by design.
- **Editor visual:** the section-head editor experience won't show the alignwide centering effect because the editor canvas doesn't replicate the front-end constrained layout exactly. Don't chase it — the front-end is the source of truth, and the Playwright layout spec covers it.
