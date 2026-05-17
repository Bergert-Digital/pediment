# Pediment Restyles + Parts Implementation Plan (Plan 2)

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Restyle the existing `starter/*` blocks and template parts to the locked Pediment look, register the band block-styles the Plan-1 CSS already targets, and retokenize the focus shadow — all without changing block render markup so existing PHPUnit stays a true regression gate.

**Architecture:** SCSS-only restyles per block (render.php/edit.tsx untouched ⇒ existing `tests/phpunit/BlockRender/*Test.php` are the regression suite). New `inc/block-styles.php` registers `is-style-band-surface` / `is-style-band-navy` for `core/group` (CSS shipped in Plan 1's `assets/css/theme.css`). `theme.json` focus shadow recolored to the accent. `parts/header.html` / `parts/footer.html` updated at the block-markup level (theme files, exact markup given).

**Tech Stack:** WordPress FSE block theme, `theme.json` v2, `@wordpress/scripts` SCSS build, PHP 8.1, PHPUnit (wp-env), Playwright.

**Spec:** `docs/superpowers/specs/2026-05-17-pediment-design-system-design.md`. Visual reference: `docs/design/pediment-mockup.html`. Builds on Plan 1 (merged): tokens, `assets/css/theme.css` (`--r-lg/--r-md/--r-pill/--r-panel/--section/--band`, `.is-style-band-*` rules, `.btn*`, `.i`), Phosphor sprite + `starter_icon()`.

**Scope (decided):** Markup-safe restyles only. NOT in this plan (→ Plan 3): hero photo + frosted-stats overlay, pull-quote→testimonial avatar/role, blog-index→Insights filter/badges/featured-image, the bank logo icon, and the 3 new blocks (logo-cloud, feature-grid, steps). Plan-2 hero/pull-quote/blog-index get the *type/spacing/card* polish only, with identical render markup.

**Verification constraint:** Worktree is NOT mounted into the running wp-env. Per task, run env-independent gates only: `npm run build` (SCSS must compile — this is the primary SCSS gate), `php -l`, and confirm `git diff` shows NO change to any `render.php`/`edit.tsx`/`index.tsx` for restyle tasks (that guarantees existing PHPUnit stays green). Author test files as committed deliverables. Full PHPUnit + Playwright run post-merge in the `:8888`/`:8889` main checkout.

---

## File Structure

| File | Action |
|---|---|
| `inc/block-styles.php` | Create — register 2 core/group block styles |
| `functions.php` | Modify — require inc/block-styles.php |
| `theme.json` | Modify — recolor `shadow.presets.focus` to accent |
| `src/blocks/stat/style.scss` | Rewrite |
| `src/blocks/cta/style.scss` | Rewrite |
| `src/blocks/faq/style.scss` | Rewrite |
| `src/blocks/pull-quote/style.scss` | Rewrite |
| `src/blocks/hero/style.scss` | Rewrite |
| `src/blocks/blog-index/style.scss` | Rewrite |
| `src/blocks/social-links/style.scss` | Rewrite |
| `parts/header.html` | Rewrite (block markup) |
| `parts/footer.html` | Rewrite (block markup) |
| `tests/phpunit/BlockStylesTest.php` | Create |
| `tests/phpunit/ThemeJsonTest.php` | Modify — append focus-shadow assertion |
| `tests/e2e/parts.spec.ts` | Create |

Every task ends with a commit. Restyle tasks must NOT touch any `.php`/`.tsx` in the block dir.

---

### Task 1: Register band block-styles

**Files:** Create `inc/block-styles.php`; Modify `functions.php`; Create `tests/phpunit/BlockStylesTest.php`.

- [ ] **Step 1: Write the failing test** `tests/phpunit/BlockStylesTest.php`:
```php
<?php

class BlockStylesTest extends WP_UnitTestCase {
	private function styles_for( string $block ): array {
		$reg = WP_Block_Styles_Registry::get_instance();
		return array_keys( $reg->get_registered_styles_for_block( $block ) );
	}

	public function test_group_band_styles_registered() {
		do_action( 'init' );
		$names = $this->styles_for( 'core/group' );
		$this->assertContains( 'band-surface', $names );
		$this->assertContains( 'band-navy', $names );
	}
}
```

- [ ] **Step 2: Run test to verify it fails** (post-merge only — env-independent: confirm `php -l tests/phpunit/BlockStylesTest.php` clean; the red/green runs in the :8889 env post-merge). Expected post-merge: FAIL (styles not registered).

- [ ] **Step 3: Create `inc/block-styles.php`**:
```php
<?php
/**
 * Register theme block styles.
 *
 * @package Starter
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action(
	'init',
	function () {
		register_block_style(
			'core/group',
			array(
				'name'  => 'band-surface',
				'label' => __( 'Band — surface', 'starter' ),
			)
		);
		register_block_style(
			'core/group',
			array(
				'name'  => 'band-navy',
				'label' => __( 'Band — navy', 'starter' ),
			)
		);
	}
);
```

- [ ] **Step 4: Require from `functions.php`** — add next to the other `inc/` requires (match exact idiom, e.g. directly after `require_once __DIR__ . '/inc/icons.php';`):
```php
require_once __DIR__ . '/inc/block-styles.php';
```

- [ ] **Step 5: Env-independent verify** — `php -l inc/block-styles.php && php -l functions.php`; `npm run build` still compiles. (`register_block_style` name `band-surface` ⇒ body class `is-style-band-surface`, which Plan 1's `theme.css` already styles.)

- [ ] **Step 6: Commit**
```bash
git add inc/block-styles.php functions.php tests/phpunit/BlockStylesTest.php
git commit -m "feat(theme): register band-surface/band-navy group block styles"
```

---

### Task 2: Retokenize focus shadow

**Files:** Modify `theme.json`; Modify `tests/phpunit/ThemeJsonTest.php`.

- [ ] **Step 1: Append failing test** to `class ThemeJsonTest` (new method, don't alter others):
```php
	public function test_focus_shadow_uses_accent() {
		$tj = $this->theme_json();
		$focus = '';
		foreach ( $tj['settings']['shadow']['presets'] as $p ) {
			if ( 'focus' === $p['slug'] ) {
				$focus = $p['shadow'];
			}
		}
		$this->assertStringContainsString( '14,116,144', $focus );
		$this->assertStringNotContainsString( '79,70,229', $focus );
	}
```

- [ ] **Step 2:** Open `theme.json` → `settings.shadow.presets`, find the entry with `"slug": "focus"`. Keep its geometry (offsets/spread) exactly; change ONLY the color portion from the legacy indigo `rgba(79, 70, 229, …)` to `rgba(14, 116, 144, 0.35)` (Deep Cyan accent). Do not touch `subtle`/`medium`/`lifted`.

- [ ] **Step 3: Verify** — `python3 -c "import json;json.load(open('theme.json'));print('JSON-OK')"`; `php -l tests/phpunit/ThemeJsonTest.php`; grep confirms `14, 116, 144` present in the focus preset and no `79, 70, 229` remains anywhere in `shadow.presets`.

- [ ] **Step 4: Commit**
```bash
git add theme.json tests/phpunit/ThemeJsonTest.php
git commit -m "feat(theme): recolor focus shadow to accent (drop legacy indigo)"
```

---

### Task 3: Restyle `stat`

**Files:** Rewrite `src/blocks/stat/style.scss` ONLY.

Mockup `.stat` (in a navy band): centered, oversized 800-weight figure, muted label; works white-on-navy via `.is-style-band-navy` and standalone on surface. Render markup is `.starter-stat` > `.starter-stat__value` / `__label` / `__context` (unchanged).

- [ ] **Step 1: Replace `src/blocks/stat/style.scss` with:**
```scss
.starter-stat {
  display: flex;
  flex-direction: column;
  gap: var(--wp--preset--spacing--10);
  text-align: center;
  padding: var(--wp--preset--spacing--30);

  &__value {
    font-size: var(--wp--preset--font-size--4xl);
    line-height: 1;
    letter-spacing: -0.03em;
    font-weight: 800;
    color: var(--wp--preset--color--accent);
  }

  &__label {
    font-size: var(--wp--preset--font-size--base);
    font-weight: 600;
    color: var(--wp--preset--color--text);
  }

  &__context {
    font-size: var(--wp--preset--font-size--sm);
    color: var(--wp--preset--color--text-muted);
  }
}

/* On the navy band: white figures, slate sub-text */
.is-style-band-navy .starter-stat {
  &__value { color: #fff; }
  &__label { color: #fff; }
  &__context { color: #9DB6E6; }
}
```

- [ ] **Step 2: Verify** — `npm run build` compiles (no Sass error); `git status --porcelain src/blocks/stat` shows ONLY `style.scss` modified (no render.php/tsx). `git diff --stat` confirms one file.

- [ ] **Step 3: Commit**
```bash
git add src/blocks/stat/style.scss
git commit -m "style(stat): Pediment restyle (band-aware figure)"
```

---

### Task 4: Restyle `cta`

**Files:** Rewrite `src/blocks/cta/style.scss` ONLY.

Mockup CTA: navy rounded panel (`--r-panel`), centered white text, generous padding, primary button = white-on-navy pill (`.btn--light` look). Render markup unchanged: `.starter-cta` > `__title`/`__body`/`__actions` > `__btn--primary`/`__btn--secondary`.

- [ ] **Step 1: Replace `src/blocks/cta/style.scss` with:**
```scss
.starter-cta {
  background: var(--wp--preset--color--primary);
  color: #fff;
  border-radius: var(--r-panel, 28px);
  padding: clamp(56px, 7vw, 84px) clamp(28px, 5vw, 60px);
  text-align: center;
  background-image: radial-gradient(90% 120% at 50% 0%, rgba(14,116,144,.5), transparent 60%);

  &__title {
    font-size: var(--wp--preset--font-size--3xl);
    line-height: 1.12;
    letter-spacing: -0.02em;
    font-weight: 800;
    margin: 0 0 var(--wp--preset--spacing--20);
    color: #fff;
  }

  &__body {
    color: #B9C8E6;
    margin: 0 auto var(--wp--preset--spacing--40);
    max-width: 46ch;
  }

  &__actions {
    display: flex;
    gap: var(--wp--preset--spacing--20);
    flex-wrap: wrap;
    justify-content: center;
  }

  &__btn {
    display: inline-flex;
    align-items: center;
    gap: 9px;
    padding: 16px 26px;
    border-radius: var(--r-pill, 999px);
    text-decoration: none;
    font-weight: 700;
    border: 1.5px solid transparent;
    transition: background-color .15s ease, color .15s ease, border-color .15s ease;

    &:focus-visible {
      outline: 2px solid #fff;
      outline-offset: 3px;
    }
  }

  &__btn--primary {
    background: #fff;
    color: var(--wp--preset--color--accent-hover);
    &:hover { background: #fff; color: var(--wp--preset--color--accent); }
  }

  &__btn--secondary {
    background: transparent;
    color: #fff;
    border-color: rgba(255,255,255,.45);
    &:hover { border-color: #fff; }
  }
}
```

- [ ] **Step 2: Verify** — `npm run build` compiles; only `src/blocks/cta/style.scss` changed.

- [ ] **Step 3: Commit**
```bash
git add src/blocks/cta/style.scss
git commit -m "style(cta): Pediment navy-panel restyle"
```

---

### Task 5: Restyle `faq` / `faq-item`

**Files:** Rewrite `src/blocks/faq/style.scss` ONLY (faq-item has no scss; it's styled here).

Mockup accordion: surface tiles (`--r-md`), bold question, a circular toggle that is navy when closed and accent when open, chevron rotates. Render is `<details class="starter-faq-item">` > `<summary class="starter-faq-item__question">` + `<div class="starter-faq-item__answer">` (UNCHANGED — chevron drawn in CSS, no icon markup, keeps FaqTest green).

- [ ] **Step 1: Replace `src/blocks/faq/style.scss` with:**
```scss
.starter-faq {
  display: flex;
  flex-direction: column;
  gap: 14px;
}

.starter-faq-item {
  background: var(--wp--preset--color--surface-elevated);
  border: 1px solid var(--wp--preset--color--border);
  border-radius: 16px;
  overflow: hidden;
  transition: border-color .18s ease, box-shadow .18s ease;

  &[open] {
    border-color: var(--wp--preset--color--border-strong);
    box-shadow: var(--wp--preset--shadow--subtle);
  }

  &__question {
    list-style: none;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 20px;
    padding: 22px 24px;
    font-weight: 700;
    font-size: 1.02rem;
    color: var(--wp--preset--color--text);

    &::-webkit-details-marker { display: none; }

    /* circular toggle */
    &::after {
      content: "";
      flex: none;
      width: 34px;
      height: 34px;
      border-radius: 50%;
      background: var(--wp--preset--color--primary);
      /* chevron drawn with a rotated border box, centered */
      background-image:
        linear-gradient(45deg, transparent 42%, #fff 42%, #fff 58%, transparent 58%),
        linear-gradient(-45deg, transparent 42%, #fff 42%, #fff 58%, transparent 58%);
      background-size: 9px 2px, 9px 2px;
      background-position: calc(50% - 3px) 55%, calc(50% + 3px) 55%;
      background-repeat: no-repeat;
      transition: transform .2s ease, background-color .2s ease;
    }
  }

  &[open] &__question {
    color: var(--wp--preset--color--accent-hover);
    &::after {
      background-color: var(--wp--preset--color--accent);
      transform: rotate(180deg);
    }
  }

  &__answer {
    padding: 0 24px 24px;
    color: var(--wp--preset--color--text-muted);
    line-height: 1.65;
    font-size: .98rem;
    max-width: 62ch;
  }
}
```
(The two-color chevron uses background gradients so no markup/pseudo-extra is needed; `[open]` rotates the whole disc and recolors it accent.)

- [ ] **Step 2: Verify** — `npm run build` compiles; ONLY `src/blocks/faq/style.scss` changed (no faq/faq-item render.php/tsx touched ⇒ FaqTest stays green post-merge).

- [ ] **Step 3: Commit**
```bash
git add src/blocks/faq/style.scss
git commit -m "style(faq): Pediment surface-tile accordion + circular toggle"
```

---

### Task 6: Restyle `pull-quote`

**Files:** Rewrite `src/blocks/pull-quote/style.scss` ONLY.

Mockup testimonial look: large centered italic-free quote, accent-tinted, muted citation. Markup unchanged: `<blockquote class="starter-pull-quote">` > `p.starter-pull-quote__quote` + `cite.starter-pull-quote__citation`. (Avatar/name/role variant = Plan 3.)

- [ ] **Step 1: Replace `src/blocks/pull-quote/style.scss` with:**
```scss
.starter-pull-quote {
  max-width: 880px;
  margin-inline: auto;
  text-align: center;
  padding-block: var(--wp--preset--spacing--40);
  border: 0;
  background: none;

  &__quote {
    font-size: clamp(1.5rem, 2.6vw, 2.1rem);
    font-weight: 700;
    line-height: 1.35;
    letter-spacing: -0.02em;
    color: var(--wp--preset--color--text);
    margin: 0;
  }

  &__citation {
    display: block;
    margin-top: var(--wp--preset--spacing--30);
    font-style: normal;
    font-weight: 600;
    color: var(--wp--preset--color--text-muted);
    font-size: var(--wp--preset--font-size--sm);
  }
}

.is-style-band-navy .starter-pull-quote {
  &__quote { color: #fff; }
  &__citation { color: #9DB6E6; }
}
```

- [ ] **Step 2: Verify** — `npm run build`; only `src/blocks/pull-quote/style.scss` changed.

- [ ] **Step 3: Commit**
```bash
git add src/blocks/pull-quote/style.scss
git commit -m "style(pull-quote): Pediment centered testimonial restyle"
```

---

### Task 7: Restyle `hero`

**Files:** Rewrite `src/blocks/hero/style.scss` ONLY.

Pediment hero typography/spacing + pill CTA. Render markup unchanged: `<section class="starter-hero is-variant-…">` > `h1.starter-hero__headline`, `p.starter-hero__subheadline`, `a.starter-hero__cta` (the photo + glass-stats variant is Plan 3 — keep all 3 existing variants working).

- [ ] **Step 1: Replace `src/blocks/hero/style.scss` with:**
```scss
.starter-hero {
  padding-block: clamp(64px, 7vw, 96px) var(--section, 110px);
  color: var(--wp--preset--color--text);

  &.is-variant-centered { text-align: center; }

  &.is-variant-media-bg {
    background-size: cover;
    background-position: center;
    color: #fff;
    position: relative;
    isolation: isolate;
    border-radius: var(--r-lg, 20px);
    padding-inline: clamp(24px, 5vw, 56px);

    &::before {
      content: "";
      position: absolute;
      inset: 0;
      background: var(--wp--preset--color--primary);
      opacity: 0.55;
      border-radius: inherit;
      z-index: -1;
    }
  }

  &__headline {
    font-size: var(--wp--preset--font-size--4xl);
    line-height: 1.05;
    letter-spacing: -0.03em;
    font-weight: 800;
    margin: 0 0 var(--wp--preset--spacing--20);
  }

  &__subheadline {
    font-size: var(--wp--preset--font-size--lg);
    color: var(--wp--preset--color--text-muted);
    line-height: 1.6;
    margin: 0 0 var(--wp--preset--spacing--40);
    max-width: 40ch;
  }

  &.is-variant-centered &__subheadline { margin-inline: auto; }
  &.is-variant-media-bg &__subheadline { color: rgba(255,255,255,.9); }

  &__cta {
    display: inline-flex;
    align-items: center;
    gap: 9px;
    padding: 16px 26px;
    background: var(--wp--preset--color--accent);
    color: #fff;
    border-radius: var(--r-pill, 999px);
    text-decoration: none;
    font-weight: 700;
    transition: background-color .15s ease, transform .15s ease;

    &:hover { background: var(--wp--preset--color--accent-hover); color: #fff; transform: translateY(-1px); }
    &:focus-visible { outline: 2px solid var(--wp--preset--color--accent); outline-offset: 3px; }
  }

  &.is-variant-media-bg &__cta { background: #fff; color: var(--wp--preset--color--accent-hover); }
}
```

- [ ] **Step 2: Verify** — `npm run build`; only `src/blocks/hero/style.scss` changed (HeroTest stays green post-merge — markup identical, all 3 variants present).

- [ ] **Step 3: Commit**
```bash
git add src/blocks/hero/style.scss
git commit -m "style(hero): Pediment type/spacing/pill-cta restyle"
```

---

### Task 8: Restyle `blog-index` (card polish only)

**Files:** Rewrite `src/blocks/blog-index/style.scss` ONLY.

Pediment card grid (the Insights filter/badge/featured-image is Plan 3). Markup unchanged: `.starter-blog-index` > `ul.__list` > `li.__item` ( `a.__link` > `h3.__title`, `time.__date`, `p.__excerpt` ), `p.__empty`.

- [ ] **Step 1: Replace `src/blocks/blog-index/style.scss` with:**
```scss
.starter-blog-index {
  &__list {
    list-style: none;
    padding: 0;
    margin: 0;
    display: grid;
    gap: 24px;
    grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
  }

  &__item {
    display: flex;
    flex-direction: column;
    gap: var(--wp--preset--spacing--10);
    padding: 26px;
    background: var(--wp--preset--color--surface);
    border: 1px solid var(--wp--preset--color--border);
    border-radius: var(--r-lg, 20px);
    transition: transform .18s ease, box-shadow .18s ease, border-color .18s ease;

    &:hover {
      transform: translateY(-3px);
      box-shadow: var(--wp--preset--shadow--subtle);
      border-color: var(--wp--preset--color--border-strong);
    }
  }

  &__date {
    order: -1;
    font-size: var(--wp--preset--font-size--sm);
    font-weight: 600;
    color: var(--wp--preset--color--text-muted);
  }

  &__link { text-decoration: none; color: inherit;
    &:hover .starter-blog-index__title { color: var(--wp--preset--color--accent); }
  }

  &__title {
    margin: 0;
    font-size: 1.18rem;
    line-height: 1.28;
    letter-spacing: -0.01em;
    color: var(--wp--preset--color--text);
    transition: color .15s ease;
  }

  &__excerpt {
    margin: 0;
    color: var(--wp--preset--color--text-muted);
    line-height: 1.55;
    font-size: .96rem;
  }

  &__empty {
    color: var(--wp--preset--color--text-muted);
    text-align: center;
    padding: var(--wp--preset--spacing--40);
  }
}
```

- [ ] **Step 2: Verify** — `npm run build`; only `src/blocks/blog-index/style.scss` changed (BlogIndexTest stays green post-merge).

- [ ] **Step 3: Commit**
```bash
git add src/blocks/blog-index/style.scss
git commit -m "style(blog-index): Pediment card-grid polish"
```

---

### Task 9: Restyle `social-links`

**Files:** Rewrite `src/blocks/social-links/style.scss` ONLY.

Keep behavior; ensure it reads well in the footer and on a navy band. Markup unchanged.

- [ ] **Step 1: Replace `src/blocks/social-links/style.scss` with:**
```scss
.starter-social-links {
  display: flex;
  gap: var(--wp--preset--spacing--20);
  list-style: none;
  padding: 0;
  margin: 0;

  &__item { display: inline-block; }

  a {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 2.25rem;
    height: 2.25rem;
    color: var(--wp--preset--color--text-muted);
    border-radius: 9999px;
    text-decoration: none;
    transition: color .15s ease, background-color .15s ease;

    &:hover, &:focus-visible { color: var(--wp--preset--color--accent); }
    &:focus-visible { outline: 2px solid var(--wp--preset--color--accent); outline-offset: 2px; }
  }

  &__icon svg { width: 1.125rem; height: 1.125rem; display: block; fill: currentColor; }

  &__label {
    font-size: var(--wp--preset--font-size--xs);
    padding: 0 var(--wp--preset--spacing--10);
    color: var(--wp--preset--color--text-muted);
  }
}

.is-style-band-navy .starter-social-links a {
  color: #9DB6E6;
  &:hover, &:focus-visible { color: #fff; }
}
```

- [ ] **Step 2: Verify** — `npm run build`; only `src/blocks/social-links/style.scss` changed.

- [ ] **Step 3: Commit**
```bash
git add src/blocks/social-links/style.scss
git commit -m "style(social-links): Pediment + navy-band variant"
```

---

### Task 10: Restyle `parts/header.html`

**Files:** Rewrite `parts/header.html`; Create `tests/e2e/parts.spec.ts`.

Pediment nav: constrained group, space-between, site-title (left), navigation (center/right), a pill CTA button (right). The bank logo icon is Plan 3 — keep site-title text for now. Use the existing palette/spacing presets and a button styled via the `is-style-fill` core button + accent color.

- [ ] **Step 1: Replace `parts/header.html` with:**
```html
<!-- wp:group {"tagName":"header","className":"site-header","style":{"spacing":{"padding":{"top":"var:preset|spacing|20","bottom":"var:preset|spacing|20"},"blockGap":"0"},"border":{"bottom":{"color":"var:preset|color|border","width":"1px"}}},"backgroundColor":"surface","layout":{"type":"constrained"}} -->
<header class="wp-block-group site-header has-border-color has-surface-background-color has-background" style="border-bottom-color:var(--wp--preset--color--border);border-bottom-width:1px;padding-top:var(--wp--preset--spacing--20);padding-bottom:var(--wp--preset--spacing--20)">
  <!-- wp:group {"layout":{"type":"flex","justifyContent":"space-between","flexWrap":"nowrap"},"style":{"spacing":{"blockGap":"var:preset|spacing|40"}}} -->
  <div class="wp-block-group">
    <!-- wp:site-title {"level":0,"style":{"typography":{"fontWeight":"800","textDecoration":"none","fontSize":"1.2rem","letterSpacing":"-0.02em"}}} /-->
    <!-- wp:group {"layout":{"type":"flex","justifyContent":"right","flexWrap":"nowrap"},"style":{"spacing":{"blockGap":"var:preset|spacing|40"}}} -->
    <div class="wp-block-group">
      <!-- wp:navigation {"overlayMenu":"mobile","layout":{"type":"flex","orientation":"horizontal","flexWrap":"nowrap"},"style":{"spacing":{"blockGap":"var:preset|spacing|30"},"typography":{"fontWeight":"600"}}} /-->
      <!-- wp:buttons -->
      <div class="wp-block-buttons">
        <!-- wp:button {"backgroundColor":"accent","textColor":"surface","style":{"border":{"radius":"999px"},"typography":{"fontWeight":"700","fontSize":"0.9rem"},"spacing":{"padding":{"left":"20px","right":"20px","top":"11px","bottom":"11px"}}}} -->
        <div class="wp-block-button"><a class="wp-block-button__link has-surface-color has-accent-background-color has-text-color has-background wp-element-button" style="border-radius:999px;padding-top:11px;padding-right:20px;padding-bottom:11px;padding-left:20px;font-size:0.9rem;font-weight:700">Book a consultation</a></div>
        <!-- /wp:button -->
      </div>
      <!-- /wp:buttons -->
    </div>
    <!-- /wp:group -->
  </div>
  <!-- /wp:group -->
</header>
<!-- /wp:group -->
```

- [ ] **Step 2: Create `tests/e2e/parts.spec.ts`:**
```typescript
import { test, expect } from '@playwright/test';

test.describe('Pediment parts', () => {
  test('header renders site title + nav CTA pill', async ({ page }) => {
    await page.goto('/');
    const header = page.locator('header.site-header').first();
    await expect(header).toBeVisible();
    const cta = header.getByRole('link', { name: 'Book a consultation' });
    await expect(cta).toBeVisible();
  });

  test('footer renders columns and bottom bar', async ({ page }) => {
    await page.goto('/');
    const footer = page.locator('footer').first();
    await expect(footer).toBeVisible();
    await expect(footer.getByText(/All rights reserved\./)).toBeVisible();
  });
});
```

- [ ] **Step 3: Verify** — env-independent: the file is valid block markup (balanced `<!-- wp:* -->` / `<!-- /wp:* -->` comments — count opens == closes); `npx playwright test tests/e2e/parts.spec.ts --list` enumerates 2 tests (no webServer in config, safe). Live run deferred post-merge.

- [ ] **Step 4: Commit**
```bash
git add parts/header.html tests/e2e/parts.spec.ts
git commit -m "feat(parts): Pediment header (compact nav + pill CTA)"
```

---

### Task 11: Restyle `parts/footer.html`

**Files:** Rewrite `parts/footer.html`.

Mockup footer: 4-column (brand + about | Services | Company | Contact), then a bottom bar (copyright | legal) separated by a hairline. Use a constrained group; columns via `core/columns`; keep `starter/social-links`.

- [ ] **Step 1: Replace `parts/footer.html` with:**
```html
<!-- wp:group {"tagName":"footer","style":{"spacing":{"padding":{"top":"var:preset|spacing|60","bottom":"var:preset|spacing|40"}},"border":{"top":{"color":"var:preset|color|border","width":"1px"}}},"backgroundColor":"surface-elevated","layout":{"type":"constrained"}} -->
<footer class="wp-block-group has-border-color has-surface-elevated-background-color has-background" style="border-top-color:var(--wp--preset--color--border);border-top-width:1px;padding-top:var(--wp--preset--spacing--60);padding-bottom:var(--wp--preset--spacing--40)">
  <!-- wp:columns {"style":{"spacing":{"blockGap":{"top":"var:preset|spacing|40","left":"var:preset|spacing|50"}}}} -->
  <div class="wp-block-columns">
    <!-- wp:column {"width":"40%"} -->
    <div class="wp-block-column" style="flex-basis:40%">
      <!-- wp:site-title {"level":0,"style":{"typography":{"fontWeight":"800","textDecoration":"none","fontSize":"1.2rem"}}} /-->
      <!-- wp:paragraph {"fontSize":"sm","textColor":"text-muted","style":{"spacing":{"margin":{"top":"var:preset|spacing|20"}}}} -->
      <p class="has-text-muted-color has-text-color has-sm-font-size" style="margin-top:var(--wp--preset--spacing--20)">Senior-led work for leaders navigating change.</p>
      <!-- /wp:paragraph -->
      <!-- wp:group {"style":{"spacing":{"margin":{"top":"var:preset|spacing|30"}}},"layout":{"type":"flex"}} -->
      <div class="wp-block-group" style="margin-top:var(--wp--preset--spacing--30)"><!-- wp:starter/social-links /--></div>
      <!-- /wp:group -->
    </div>
    <!-- /wp:column -->
    <!-- wp:column -->
    <div class="wp-block-column">
      <!-- wp:heading {"level":4,"fontSize":"sm","style":{"typography":{"textTransform":"uppercase","letterSpacing":"0.1em"},"spacing":{"margin":{"bottom":"var:preset|spacing|20"}}}} -->
      <h4 class="wp-block-heading has-sm-font-size" style="margin-bottom:var(--wp--preset--spacing--20);text-transform:uppercase;letter-spacing:0.1em">Services</h4>
      <!-- /wp:heading -->
      <!-- wp:navigation {"overlayMenu":"never","layout":{"type":"flex","orientation":"vertical"},"style":{"spacing":{"blockGap":"var:preset|spacing|10"},"typography":{"fontSize":"0.95rem"}}} /-->
    </div>
    <!-- /wp:column -->
    <!-- wp:column -->
    <div class="wp-block-column">
      <!-- wp:heading {"level":4,"fontSize":"sm","style":{"typography":{"textTransform":"uppercase","letterSpacing":"0.1em"},"spacing":{"margin":{"bottom":"var:preset|spacing|20"}}}} -->
      <h4 class="wp-block-heading has-sm-font-size" style="margin-bottom:var(--wp--preset--spacing--20);text-transform:uppercase;letter-spacing:0.1em">Company</h4>
      <!-- /wp:heading -->
      <!-- wp:paragraph {"fontSize":"sm","textColor":"text-muted"} -->
      <p class="has-text-muted-color has-text-color has-sm-font-size">About<br>Careers<br>Insights<br>Contact</p>
      <!-- /wp:paragraph -->
    </div>
    <!-- /wp:column -->
    <!-- wp:column -->
    <div class="wp-block-column">
      <!-- wp:heading {"level":4,"fontSize":"sm","style":{"typography":{"textTransform":"uppercase","letterSpacing":"0.1em"},"spacing":{"margin":{"bottom":"var:preset|spacing|20"}}}} -->
      <h4 class="wp-block-heading has-sm-font-size" style="margin-bottom:var(--wp--preset--spacing--20);text-transform:uppercase;letter-spacing:0.1em">Contact</h4>
      <!-- /wp:heading -->
      <!-- wp:paragraph {"fontSize":"sm","textColor":"text-muted"} -->
      <p class="has-text-muted-color has-text-color has-sm-font-size">hello@example.com<br>+44 20 7946 0000<br>London</p>
      <!-- /wp:paragraph -->
    </div>
    <!-- /wp:column -->
  </div>
  <!-- /wp:columns -->
  <!-- wp:group {"style":{"spacing":{"margin":{"top":"var:preset|spacing|50"},"padding":{"top":"var:preset|spacing|30"}},"border":{"top":{"color":"var:preset|color|border","width":"1px"}}},"layout":{"type":"flex","justifyContent":"space-between","flexWrap":"wrap"}} -->
  <div class="wp-block-group has-border-color" style="border-top-color:var(--wp--preset--color--border);border-top-width:1px;margin-top:var(--wp--preset--spacing--50);padding-top:var(--wp--preset--spacing--30)">
    <!-- wp:paragraph {"fontSize":"sm","textColor":"text-muted"} -->
    <p class="has-text-muted-color has-text-color has-sm-font-size">© All rights reserved.</p>
    <!-- /wp:paragraph -->
    <!-- wp:paragraph {"fontSize":"sm","textColor":"text-muted"} -->
    <p class="has-text-muted-color has-text-color has-sm-font-size">Privacy · Terms</p>
    <!-- /wp:paragraph -->
  </div>
  <!-- /wp:group -->
</footer>
<!-- /wp:group -->
```

- [ ] **Step 2: Verify** — balanced block-comment count (opens == closes); no `starter/social-links` typo; `npm run build` unaffected (no SCSS). The `tests/e2e/parts.spec.ts` footer test (Task 10) covers this post-merge.

- [ ] **Step 3: Commit**
```bash
git add parts/footer.html
git commit -m "feat(parts): Pediment 4-column footer + bottom bar"
```

---

### Task 12: Build + cumulative regression

**Files:** none (verification only).

- [ ] **Step 1:** `npm run build` — webpack compiles successfully (all rewritten SCSS).
- [ ] **Step 2:** Confirm NO `render.php`/`*.tsx`/`block.json` changed in this branch: `git diff <branch-base>..HEAD --name-only | grep -E 'src/blocks/.*(render\.php|\.tsx|block\.json)$'` → empty. (Guarantees existing `tests/phpunit/BlockRender/*` are an unchanged regression suite.)
- [ ] **Step 3:** `git status --porcelain` clean (besides pre-existing untracked `docs/images/`).
- [ ] **Step 4: Commit** (only if any verification fixup needed; otherwise skip).

**Post-merge (main checkout, :8888/:8889 — done by controller, not a worktree step):** `npm run build` → `npx wp-env run cli wp theme activate wp-starter-theme` → full `vendor/bin/phpunit` (expect green incl. new BlockStylesTest + ThemeJsonTest focus assertion; existing BlockRender tests green since markup unchanged) → `npx playwright test` (foundation + parts specs green; the pre-existing seed-dependent nav/404 failures remain unrelated).

---

## Self-Review

**Spec coverage (Plan-2 portion of the spec's "Section inventory"):**
- stat / cta / faq+faq-item / pull-quote / hero / blog-index / social-links restyled to Pediment → Tasks 3–9 ✓
- Band block-styles registered (CSS shipped Plan 1) → Task 1 ✓
- Focus shadow retokenized (Plan-1 review follow-up) → Task 2 ✓
- header / footer parts → Tasks 10–11 ✓
- Deferred-by-decision (hero photo/glass, pull-quote avatar, blog→Insights filter/badges, logo icon, new blocks) → explicitly Plan 3; stated in Scope. ✓ (intentional, not a gap)

**Placeholder scan:** none — every SCSS/PHP/HTML block is complete and final.

**Type/name consistency:** SCSS targets the exact existing render classes (`.starter-stat__value`, `.starter-cta__btn--primary`, `.starter-faq-item__question`, `.starter-pull-quote__quote`, `.starter-hero__headline`, `.starter-blog-index__item`, `.starter-social-links`). Band hooks use `.is-style-band-navy` (matches Plan 1 `theme.css` + Task 1 `register_block_style` name `band-navy`). `--r-*`/`--section` custom props match Plan 1's `theme.css`. e2e selectors (`header.site-header`, `footer`, "Book a consultation", "All rights reserved.") match the Task 10/11 markup.

**Regression safety:** Restyle tasks change only `style.scss`; Task 12 Step 2 asserts no render/markup files changed, so the existing per-block PHPUnit suite is an untouched regression gate. Parts changes are covered by the new `parts.spec.ts` and existing `front-page.spec.ts` (header/footer visible).
