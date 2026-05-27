# Pediment Brand Icon + Nav Reconciliation + Green E2E Baseline (Plan 3)

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans. Steps use checkbox (`- [ ]`).

**Goal:** Add the Pediment bank-icon brand mark to the header & footer, resolve the double-CTA (seeded `nav-cta` Contact vs. the Plan-2 pill button), and make the Playwright suite fully green by seeding the e2e site deterministically + reconciling `navigation.spec.ts` to the Pediment design.

**Architecture:** The Phosphor sprite is already printed at `wp_body_open`, so a `core/html` block inside the template parts can reference `<use href="#ph-bank">` and it resolves. Brand styling lives in the Plan-1 global stylesheet `assets/css/theme.css`. The double-CTA is resolved by dropping the `nav-cta` class from the seeded Contact link in `inc/nav-seed.php` (the single pill CTA is the Plan-2 `wp:button`). The pre-existing e2e flakiness (unseeded DB, plain permalinks, stale indigo assertions) is fixed by a Playwright `globalSetup` that seeds + sets pretty permalinks + the static front page, plus a rewrite of `navigation.spec.ts` to the Pediment reality (Deep Cyan accent, pill CTA, no `nav-cta`).

**Tech Stack:** WordPress FSE block theme, theme.json v2, `@wordpress/scripts`, PHP 8.1, PHPUnit (wp-env), Playwright (baseURL `http://localhost:8888`, no webServer, workers:1).

**Spec:** `docs/superpowers/specs/2026-05-17-pediment-design-system-design.md`. Visual ref: `docs/design/pediment-mockup.html`. Builds on merged Plans 1 & 2.

**Scope (decided):** Brand icon (header+footer) + nav-CTA reconciliation + deterministic green e2e baseline ONLY. NOT here (→ later plans): new blocks `logo-cloud`/`feature-grid`/`steps` (Plan 4); structural extensions hero photo+glass / pull-quote→testimonial / blog-index→Insights (Plan 5).

**Key facts (verified at HEAD `7ce2423`):**
- `theme.json` `styles.css` already sets `.site-header{position:sticky;top:0;z-index:50;background:var(--wp--preset--color--surface)}` — header is already sticky; do NOT duplicate, only layer a backdrop-blur via a higher-specificity `header.site-header` rule in `theme.css`.
- Accent is Deep Cyan `#0E7490` = `rgb(14, 116, 144)`. The stale `navigation.spec.ts` asserts indigo `rgb(79, 70, 229)` — must change.
- `inc/nav-seed.php` `starter_nav_menu_blocks()` emits the Contact link with `"className":"nav-cta"`. theme.json styles `.wp-block-navigation .wp-block-navigation-item.nav-cta a{...}` will become dead CSS after removal — leave theme.json untouched (harmless, lower risk).
- `starter_icon('bank')` → `<svg class="i" aria-hidden="true" focusable="false"><use href="#ph-bank"></use></svg>`; sprite printed on `wp_body_open` (priority 1, self-deregistering).
- `templates/404.html` renders `<h1>Page not found</h1>` — the front-page 404 test passes once routing returns 404 (needs pretty permalinks).
- `inc/seed.php` (`wp starter-theme seed`) is idempotent (skips existing pages) and creates Home/About/Blog/Contact + binds the nav entity, but sets NO permalink structure and NO static front page.

**Verification constraint:** Worktree NOT mounted in wp-env. Per task: env-independent gates only (`npm run build`, `php -l`, `npx tsc --noEmit --skipLibCheck <file>`, `npx playwright test <spec> --list`, block-comment balance). Full PHPUnit + Playwright (with the new globalSetup actually executing) run POST-MERGE in the `:8888`/`:8889` main checkout. **Definition of done: post-merge `npx playwright test` is 0 failures (all specs incl. navigation + front-page), PHPUnit stays green.**

---

## File Structure

| File | Action |
|---|---|
| `tests/e2e/global-setup.ts` | Create — seed + permalinks + static front page |
| `playwright.config.ts` | Modify — add `globalSetup` |
| `assets/css/theme.css` | Modify — append `.brand`/`.brand .mark`/`header.site-header` blur |
| `parts/header.html` | Modify — wrap site-title in `.brand` group + `wp:html` mark |
| `parts/footer.html` | Modify — add `.brand` mark before footer site-title |
| `inc/nav-seed.php` | Modify — drop `nav-cta` className from Contact |
| `tests/e2e/navigation.spec.ts` | Rewrite — Pediment reality |

Each task commits. Restyle/markup tasks must not change any block `render.php`/`*.tsx`/`block.json`.

---

### Task 1: Playwright globalSetup — deterministic seeded e2e site

**Files:** Create `tests/e2e/global-setup.ts`; Modify `playwright.config.ts`.

The navigation + front-page-404 failures are an unseeded-DB / plain-permalink environment gap. A `globalSetup` makes the suite deterministic.

- [ ] **Step 1: Create `tests/e2e/global-setup.ts`:**
```typescript
import { execSync } from 'node:child_process';

/**
 * Prepare the wp-env site so the e2e suite is deterministic:
 * pretty permalinks, seeded pages/nav, static front page.
 * Idempotent — safe to run repeatedly.
 */
export default async function globalSetup(): Promise<void> {
  const wp = (cmd: string) =>
    execSync(`npx wp-env run cli wp ${cmd}`, { stdio: 'pipe' }).toString().trim();

  wp(`rewrite structure '/%postname%/' --hard`);
  wp(`starter-theme seed`);
  const homeId = wp(`post list --post_type=page --name=home --field=ID --posts_per_page=1`);
  if (homeId) {
    wp(`option update show_on_front page`);
    wp(`option update page_on_front ${homeId}`);
  }
  wp(`rewrite flush --hard`);
}
```

- [ ] **Step 2: Wire into `playwright.config.ts`** — add the `globalSetup` key to the `defineConfig({...})` object (path relative to config). The file currently is:
```typescript
export default defineConfig({
  testDir: './tests/e2e',
  fullyParallel: false,
  ...
```
Add `globalSetup: './tests/e2e/global-setup.ts',` immediately after `testDir: './tests/e2e',`. Change nothing else.

- [ ] **Step 3: Env-independent verify** — `npx tsc --noEmit --skipLibCheck tests/e2e/global-setup.ts` (clean); `npx playwright test --list` still enumerates all specs (globalSetup is not itself a test; `--list` must not error). Do NOT run the full suite here (no wp-env in worktree).

- [ ] **Step 4: Commit**
```bash
git add tests/e2e/global-setup.ts playwright.config.ts
git commit -m "test(e2e): globalSetup seeds site (permalinks + pages + front page)"
```

---

### Task 2: Pediment brand/header CSS

**Files:** Modify `assets/css/theme.css` (append at END; do not alter existing rules).

- [ ] **Step 1: Append to `assets/css/theme.css`:**
```css

/* Brand mark (header/footer logo badge) + header polish */
.brand{ display:flex; align-items:center; gap:10px; }
.brand .mark{
  width:30px; height:30px; border-radius:9px;
  background:linear-gradient(140deg, var(--wp--preset--color--accent), var(--wp--preset--color--accent-hover));
  display:flex; align-items:center; justify-content:center; color:#fff; flex:none;
}
.brand .mark .i{ width:18px; height:18px; }
/* layer a frosted backdrop on the (already sticky, theme.json) header */
header.site-header{
  background:color-mix(in srgb, var(--wp--preset--color--surface) 86%, transparent);
  backdrop-filter:blur(10px);
  -webkit-backdrop-filter:blur(10px);
}
```
(Higher-specificity `header.site-header` intentionally overrides theme.json's `.site-header` background while keeping its `position:sticky;top:0;z-index:50`. `color-mix` has full support in the evergreen Chromium Playwright targets; it degrades to opaque surface elsewhere — acceptable.)

- [ ] **Step 2: Verify** — `npm run build` compiles; brace-balance check (`node -e` open==close); `git diff` shows append-only (0 `-` lines) to `assets/css/theme.css`; only that file changed.

- [ ] **Step 3: Commit**
```bash
git add assets/css/theme.css
git commit -m "style(theme): Pediment brand mark + frosted header"
```

---

### Task 3: Header part — brand group + bank-icon mark

**Files:** Modify `parts/header.html`.

Wrap the existing `wp:site-title` together with a new `wp:html` mark in a `.brand` flex group. Everything else (the right group: navigation + buttons CTA) stays exactly as-is.

- [ ] **Step 1:** In `parts/header.html`, replace exactly this line:
```html
    <!-- wp:site-title {"level":0,"style":{"typography":{"fontWeight":"800","textDecoration":"none","fontSize":"1.2rem","letterSpacing":"-0.02em"}}} /-->
```
with this block (a `.brand` flex group containing the icon mark + the unchanged site-title):
```html
    <!-- wp:group {"className":"brand","layout":{"type":"flex","flexWrap":"nowrap"}} -->
    <div class="wp-block-group brand">
      <!-- wp:html -->
      <span class="mark"><svg class="i" aria-hidden="true" focusable="false"><use href="#ph-bank"></use></svg></span>
      <!-- /wp:html -->
      <!-- wp:site-title {"level":0,"style":{"typography":{"fontWeight":"800","textDecoration":"none","fontSize":"1.2rem","letterSpacing":"-0.02em"}}} /-->
    </div>
    <!-- /wp:group -->
```
Change nothing else.

- [ ] **Step 2: Verify** — block-comment balance: openers now `wp:group`×4 (header, inner flex, brand, right flex) + `wp:html`×1 + `wp:buttons`×1 + `wp:button`×1 = 7 paired ⇒ exactly 7 `<!-- /wp:` closers; self-closing `/-->`: `wp:site-title`, `wp:navigation` (2). Confirm equal counts. `git status` shows only `parts/header.html`. (`wp:html` is core; its saved content is raw HTML — the inline `<use href="#ph-bank">` resolves against the wp_body_open sprite.)

- [ ] **Step 3: Commit**
```bash
git add parts/header.html
git commit -m "feat(parts): header bank-icon brand mark"
```

---

### Task 4: Footer part — brand mark

**Files:** Modify `parts/footer.html`.

- [ ] **Step 1:** In `parts/footer.html`, replace exactly this line (the footer column-1 site-title):
```html
      <!-- wp:site-title {"level":0,"style":{"typography":{"fontWeight":"800","textDecoration":"none","fontSize":"1.2rem"}}} /-->
```
with:
```html
      <!-- wp:group {"className":"brand","layout":{"type":"flex","flexWrap":"nowrap"}} -->
      <div class="wp-block-group brand">
        <!-- wp:html -->
        <span class="mark"><svg class="i" aria-hidden="true" focusable="false"><use href="#ph-bank"></use></svg></span>
        <!-- /wp:html -->
        <!-- wp:site-title {"level":0,"style":{"typography":{"fontWeight":"800","textDecoration":"none","fontSize":"1.2rem"}}} /-->
      </div>
      <!-- /wp:group -->
```
Change nothing else.

- [ ] **Step 2: Verify** — block-comment balance recomputed (added 1 `wp:group` pair + 1 `wp:html` pair; site-title still self-closing): openers == closers. Only `parts/footer.html` changed. The existing `tests/e2e/parts.spec.ts` footer test (asserts footer + "All rights reserved.") still holds.

- [ ] **Step 3: Commit**
```bash
git add parts/footer.html
git commit -m "feat(parts): footer bank-icon brand mark"
```

---

### Task 5: Resolve double-CTA — drop `nav-cta` from seeded Contact

**Files:** Modify `inc/nav-seed.php`.

The header now has a single pill CTA (the Plan-2 `wp:button`). The seeded Contact nav-link must become a normal link (no `nav-cta`), removing the duplicate accent button.

- [ ] **Step 1:** First confirm nothing else depends on it: `grep -rn "nav-cta" tests/ inc/` — expect matches only in `inc/nav-seed.php` and `tests/e2e/navigation.spec.ts` (the latter is rewritten in Task 6). If a `tests/phpunit/*` asserts `nav-cta`, STOP and report (would need its own step).

- [ ] **Step 2:** In `inc/nav-seed.php` `starter_nav_menu_blocks()`, replace exactly:
```php
			'<!-- wp:navigation-link {"label":"Contact","url":"/contact","kind":"custom","className":"nav-cta"} /-->',
```
with:
```php
			'<!-- wp:navigation-link {"label":"Contact","url":"/contact","kind":"custom"} /-->',
```
Change nothing else (theme.json's now-dead `.nav-cta a` rule is intentionally left in place — harmless, avoids editing the minified styles string).

- [ ] **Step 3: Verify** — `php -l inc/nav-seed.php`; `git diff` shows exactly one line changed (the className removed); only `inc/nav-seed.php` changed. (Existing PHPUnit: grep `tests/phpunit` for `nav-seed`/`starter_nav_menu_blocks`; if a test asserts the block string it may need the same removal — if so, do it in this commit and note it.)

- [ ] **Step 4: Commit**
```bash
git add inc/nav-seed.php
git commit -m "fix(nav): drop nav-cta from seeded Contact (single pill CTA)"
```

---

### Task 6: Reconcile `tests/e2e/navigation.spec.ts` to Pediment

**Files:** Rewrite `tests/e2e/navigation.spec.ts`.

Replace the stale indigo/`nav-cta` assertions with the Pediment reality: Deep Cyan accent `rgb(14, 116, 144)`, the pill CTA is the header `wp:button`, Contact is a normal nav link. Keep the still-valid sticky + mobile-overlay tests.

- [ ] **Step 1: Replace the ENTIRE `tests/e2e/navigation.spec.ts` with:**
```typescript
import { test, expect } from '@playwright/test';

// Pediment accent (Deep Cyan #0E7490).
const ACCENT = 'rgb(14, 116, 144)';

test.describe('top navigation', () => {
  test('renders About, Blog and Contact items', async ({ page }) => {
    await page.goto('/');
    const nav = page.locator('header .wp-block-navigation').first();
    await expect(nav.getByRole('link', { name: 'About', exact: true })).toBeVisible();
    await expect(nav.getByRole('link', { name: 'Blog', exact: true })).toBeVisible();
    await expect(nav.getByRole('link', { name: 'Contact', exact: true })).toBeVisible();
  });

  test('header is sticky-positioned', async ({ page }) => {
    await page.goto('/');
    const header = page.locator('header.site-header').first();
    await expect(header).toHaveCSS('position', 'sticky');
    await expect(header).toHaveCSS('top', '0px');
  });

  test('header has a single accent pill CTA button', async ({ page }) => {
    await page.goto('/');
    const header = page.locator('header.site-header').first();
    const cta = header.getByRole('link', { name: 'Book a consultation' });
    await expect(cta).toBeVisible();
    await expect(cta).toHaveCSS('background-color', ACCENT);
    const radius = await cta.evaluate(
      (el) => getComputedStyle(el).borderTopLeftRadius
    );
    expect(parseFloat(radius)).toBeGreaterThan(0);
    // Contact is now a normal nav link, not a second CTA button.
    await expect(
      header.locator('.wp-block-navigation-item.nav-cta')
    ).toHaveCount(0);
  });

  test('mobile overlay opens and closes', async ({ page }) => {
    await page.setViewportSize({ width: 375, height: 800 });
    await page.goto('/');
    const openBtn = page
      .locator('header .wp-block-navigation__responsive-container-open')
      .first();
    await expect(openBtn).toBeVisible();
    await openBtn.click();
    const overlay = page
      .locator('header .wp-block-navigation__responsive-container.is-menu-open')
      .first();
    await expect(overlay).toBeVisible();
    await page
      .locator('header .wp-block-navigation__responsive-container-close')
      .first()
      .click();
    await expect(overlay).toBeHidden();
  });

  test('current page nav item gets the active indicator', async ({ page }) => {
    const resp = await page.goto('/about/');
    expect(resp?.status()).toBe(200);
    const active = page
      .locator(
        "header .wp-block-navigation a[aria-current=\"page\"], header .wp-block-navigation .current-menu-item > a"
      )
      .first();
    await expect(active).toBeVisible();
    await expect(active).toHaveText('About');
    await expect(active).toHaveCSS('color', ACCENT);
  });

  test('header shows the bank brand mark', async ({ page }) => {
    await page.goto('/');
    const mark = page.locator('header .brand .mark use[href="#ph-bank"]');
    await expect(mark).toHaveCount(1);
  });
});
```
(Removed: the indigo `nav-cta` filled-button test and the indigo active-color expectation; the overlay test no longer asserts a specific overlay bg since that is theme.json-controlled and not Pediment-critical. Added: single-pill-CTA assertion + brand-mark presence. The `/about/` 200 + active indicator now rely on Task-1 globalSetup seeding.)

- [ ] **Step 2: Verify (env-independent)** — `npx tsc --noEmit --skipLibCheck tests/e2e/navigation.spec.ts` clean; `npx playwright test tests/e2e/navigation.spec.ts --list` enumerates 6 tests; `git status` shows only this file. Cross-check selectors against the Task-3 header markup (`.brand .mark use[href="#ph-bank"]`, `header a` "Book a consultation", `.wp-block-navigation-item.nav-cta` count 0 after Task 5).

- [ ] **Step 3: Commit**
```bash
git add tests/e2e/navigation.spec.ts
git commit -m "test(e2e): reconcile navigation spec to Pediment (cyan, pill CTA, brand mark)"
```

---

### Task 7: Build + cumulative regression guard

**Files:** none (verification only).

- [ ] **Step 1:** `npm run build` — compiles successfully.
- [ ] **Step 2:** `git diff <branch-base>..HEAD --name-only` — confirm changed set is exactly: `tests/e2e/global-setup.ts`, `playwright.config.ts`, `assets/css/theme.css`, `parts/header.html`, `parts/footer.html`, `inc/nav-seed.php`, `tests/e2e/navigation.spec.ts`. NO `src/blocks/**` and NO block `render.php`/`*.tsx`/`block.json` (existing PHPUnit BlockRender stays an untouched gate).
- [ ] **Step 3:** `php -l inc/nav-seed.php`; block-comment balance OK in both parts; `git status --porcelain` clean (besides pre-existing untracked `docs/images/`).

**Post-merge (main checkout `:8888`/`:8889`, controller — NOT a worktree step):**
`npm run build` → `npx wp-env run cli wp theme activate wp-starter-theme` → full `vendor/bin/phpunit` (expect green; `nav-cta` className removal doesn't break PHPUnit unless a unit test asserts it — Task 5 Step 1 verified) → `npx playwright test` (globalSetup seeds; **expect 0 failures across ALL specs**: navigation 6/6, front-page 2/2 incl. 404, parts 2/2, foundation 4/4). If front-page 404 still fails, diagnose: confirm `wp rewrite` ran and `/this-page-does-not-exist-12345` returns 404 with the `templates/404.html` "Page not found" heading.

---

## Self-Review

**Spec / goal coverage:**
- Bank brand mark in header + footer → Tasks 2,3,4 ✓
- Frosted sticky Pediment header (sticky already in theme.json; blur layered) → Task 2 ✓
- Double-CTA resolved (single pill; Contact normal link) → Task 5 ✓
- `navigation.spec.ts` reconciled to Pediment (Deep Cyan, pill CTA, brand mark, no indigo/`nav-cta`) → Task 6 ✓
- Green e2e baseline (seeded, pretty permalinks, static front page ⇒ navigation `/about/` 200 + front-page 404 deterministic) → Task 1 ✓
- Deferred (new blocks; hero/pull-quote/blog structural) → explicitly Plans 4 & 5; not gaps.

**Placeholder scan:** none — all code complete.

**Type/name consistency:** `.brand`/`.brand .mark`/`.brand .mark .i` consistent across theme.css (Task 2), header (Task 3), footer (Task 4), and the nav spec brand-mark assertion (Task 6). `use[href="#ph-bank"]` matches the sprite symbol id and the parts markup. `ACCENT = rgb(14, 116, 144)` = `#0E7490` (theme.json accent) used for both the CTA bg (Plan-2 `has-accent-background-color`) and the active-link color (theme.json `.wp-block-navigation ... a[aria-current]` rule). globalSetup `wp` commands match the verified `inc/seed.php` command name (`starter-theme seed`) and page slug (`home`). Header markup keeps the Plan-2 `wp:button` (the only CTA) and the now-class-free Contact link from Task 5.

**Regression safety:** No `src/blocks/**` touched (Task 7 Step 2 asserts) ⇒ PHPUnit BlockRender suite unchanged. `parts.spec.ts`/`foundation.spec.ts` unaffected (header still has `site-header`, footer still has the rights text). theme.json left untouched (dead `.nav-cta` CSS is harmless).
