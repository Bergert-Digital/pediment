# Default Top Navigation Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Ship a styled, sticky default top navigation with About/Blog/Contact items (Contact as a CTA button), core mobile overlay, and active/hover/focus/submenu styling.

**Architecture:** Menu items are baked as `wp:navigation-link` inner blocks into the header template part (WordPress-native, zero PHP, auto-promotes to an editable Navigation entity on first Site Editor load). All visual treatment lives in `theme.json` `styles.css`, the theme's established home for global CSS. Verified with a Playwright e2e spec.

**Tech Stack:** WordPress block theme (FSE), `theme.json`, Playwright e2e (`@playwright/test`).

---

## Constraints (read before starting)

- **Do NOT run `wp-env start` / `npm run env:start` in this directory.** Per the user's single-test-env rule, the only wp-env in use is the child-theme env at `http://localhost:8890`, which loads this starter theme as its parent — so the header part under test renders there. Assume that env is already running; if it is not, stop and ask the user to start it.
- All e2e assertions in the new spec use relative `page.goto()` paths and **no `wp-env` CLI helpers**, so they run against whatever `baseURL` is configured. Verification targets `:8890` via the `PLAYWRIGHT_BASE_URL` env var (added in Task 1). The repo default stays `:8888` so existing CI behavior is unchanged.

## File Structure

- **Modify** `playwright.config.ts` — make `baseURL` overridable via `PLAYWRIGHT_BASE_URL` (enables verifying against `:8890` without changing the CI default).
- **Create** `tests/e2e/navigation.spec.ts` — e2e spec: items render, sticky, CTA button, mobile overlay, active page. Owns all nav verification.
- **Modify** `parts/header.html` — add `site-header` class to the header group; add three `wp:navigation-link` inner blocks.
- **Modify** `theme.json` — append nav CSS (sticky, active, focus, CTA, submenu, overlay) to the existing `styles.css` string.

Tasks are ordered so each ends green and committed. The spec is written test-first per behavior; markup/CSS is added to make each test pass.

---

### Task 1: Make Playwright baseURL overridable

**Files:**
- Modify: `playwright.config.ts:11`

- [ ] **Step 1: Edit the config**

In `playwright.config.ts`, replace:

```ts
    baseURL: 'http://localhost:8888',
```

with:

```ts
    baseURL: process.env.PLAYWRIGHT_BASE_URL ?? 'http://localhost:8888',
```

- [ ] **Step 2: Verify it parses**

Run: `npx tsc --noEmit -p tsconfig.json`
Expected: no errors (or the same baseline errors as before this change — the edit introduces none).

- [ ] **Step 3: Commit**

```bash
git add playwright.config.ts
git commit -m "test(e2e): allow PLAYWRIGHT_BASE_URL override"
```

---

### Task 2: Menu items render

**Files:**
- Create: `tests/e2e/navigation.spec.ts`
- Modify: `parts/header.html`

- [ ] **Step 1: Write the failing test**

Create `tests/e2e/navigation.spec.ts`:

```ts
import { test, expect } from '@playwright/test';

test.describe('top navigation', () => {
  test('renders About, Blog and Contact items', async ({ page }) => {
    await page.goto('/');
    const nav = page.locator('header .wp-block-navigation').first();
    await expect(nav.getByRole('link', { name: 'About', exact: true })).toBeVisible();
    await expect(nav.getByRole('link', { name: 'Blog', exact: true })).toBeVisible();
    await expect(nav.getByRole('link', { name: 'Contact', exact: true })).toBeVisible();
  });
});
```

- [ ] **Step 2: Run the test to verify it fails**

Run: `PLAYWRIGHT_BASE_URL=http://localhost:8890 npx playwright test tests/e2e/navigation.spec.ts -g "renders About"`
Expected: FAIL — the bare `<!-- wp:navigation /-->` has no such links.

- [ ] **Step 3: Add the inner blocks**

In `parts/header.html`, replace this line:

```html
    <!-- wp:navigation {"layout":{"type":"flex","orientation":"horizontal"},"style":{"spacing":{"blockGap":"var:preset|spacing|30"}}} /-->
```

with:

```html
    <!-- wp:navigation {"overlayMenu":"mobile","layout":{"type":"flex","orientation":"horizontal"},"style":{"spacing":{"blockGap":"var:preset|spacing|30"}}} -->
    <!-- wp:navigation-link {"label":"About","url":"/about","kind":"custom"} /-->
    <!-- wp:navigation-link {"label":"Blog","url":"/blog","kind":"custom"} /-->
    <!-- wp:navigation-link {"label":"Contact","url":"/contact","kind":"custom","className":"nav-cta"} /-->
    <!-- /wp:navigation -->
```

- [ ] **Step 4: Run the test to verify it passes**

Run: `PLAYWRIGHT_BASE_URL=http://localhost:8890 npx playwright test tests/e2e/navigation.spec.ts -g "renders About"`
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add tests/e2e/navigation.spec.ts parts/header.html
git commit -m "feat(theme): seed default top-nav items in header part"
```

---

### Task 3: Header is sticky

**Files:**
- Modify: `tests/e2e/navigation.spec.ts`
- Modify: `parts/header.html`
- Modify: `theme.json`

- [ ] **Step 1: Add the failing test**

Append inside the `test.describe('top navigation', ...)` block in `tests/e2e/navigation.spec.ts`:

```ts
  test('header is sticky-positioned', async ({ page }) => {
    await page.goto('/');
    const header = page.locator('header.site-header').first();
    await expect(header).toHaveCSS('position', 'sticky');
    await expect(header).toHaveCSS('top', '0px');
  });
```

- [ ] **Step 2: Run the test to verify it fails**

Run: `PLAYWRIGHT_BASE_URL=http://localhost:8890 npx playwright test tests/e2e/navigation.spec.ts -g "sticky-positioned"`
Expected: FAIL — no `.site-header` element / not sticky.

- [ ] **Step 3: Add the `site-header` class to the header group**

In `parts/header.html`, replace the opening group comment:

```html
<!-- wp:group {"tagName":"header","style":{"spacing":{"padding":{"top":"var:preset|spacing|30","bottom":"var:preset|spacing|30"},"blockGap":"0"},"border":{"bottom":{"color":"var:preset|color|border","width":"1px"}}},"layout":{"type":"constrained"}} -->
```

with (adds `"className":"site-header"`):

```html
<!-- wp:group {"tagName":"header","className":"site-header","style":{"spacing":{"padding":{"top":"var:preset|spacing|30","bottom":"var:preset|spacing|30"},"blockGap":"0"},"border":{"bottom":{"color":"var:preset|color|border","width":"1px"}}},"layout":{"type":"constrained"}} -->
```

Then replace the `<header ...>` opening tag:

```html
<header class="wp-block-group has-border-color" style="border-bottom-color:var(--wp--preset--color--border);border-bottom-width:1px;padding-top:var(--wp--preset--spacing--30);padding-bottom:var(--wp--preset--spacing--30)">
```

with (adds ` site-header` to the class list):

```html
<header class="wp-block-group site-header has-border-color" style="border-bottom-color:var(--wp--preset--color--border);border-bottom-width:1px;padding-top:var(--wp--preset--spacing--30);padding-bottom:var(--wp--preset--spacing--30)">
```

- [ ] **Step 4: Add the sticky CSS**

In `theme.json`, the `styles.css` value is a single-line string ending with:

```
section.starter-section > :where(:not(.alignfull):not(.alignwide)){max-width:var(--wp--style--global--content-size, 720px);margin-inline:auto}
```

Append this fragment immediately before the closing `"` of that string (no line breaks — keep it one line):

```
.site-header{position:sticky;top:0;z-index:50;background:var(--wp--preset--color--surface)}
```

- [ ] **Step 5: Run the test to verify it passes**

Run: `PLAYWRIGHT_BASE_URL=http://localhost:8890 npx playwright test tests/e2e/navigation.spec.ts -g "sticky-positioned"`
Expected: PASS.

- [ ] **Step 6: Commit**

```bash
git add tests/e2e/navigation.spec.ts parts/header.html theme.json
git commit -m "feat(theme): always-sticky site header"
```

---

### Task 4: Contact renders as a CTA button

**Files:**
- Modify: `tests/e2e/navigation.spec.ts`
- Modify: `theme.json`

- [ ] **Step 1: Add the failing test**

Append inside the `test.describe` block:

```ts
  test('Contact item is styled as a filled CTA button', async ({ page }) => {
    await page.goto('/');
    const cta = page.locator('header .wp-block-navigation-item.nav-cta a').first();
    await expect(cta).toBeVisible();
    // Filled accent background (#4F46E5) and a non-zero border radius.
    await expect(cta).toHaveCSS('background-color', 'rgb(79, 70, 229)');
    const radius = await cta.evaluate((el) => getComputedStyle(el).borderTopLeftRadius);
    expect(parseFloat(radius)).toBeGreaterThan(0);
  });
```

- [ ] **Step 2: Run the test to verify it fails**

Run: `PLAYWRIGHT_BASE_URL=http://localhost:8890 npx playwright test tests/e2e/navigation.spec.ts -g "CTA button"`
Expected: FAIL — `.nav-cta a` has no accent background / no radius.

- [ ] **Step 3: Add the CTA CSS**

In `theme.json`, append this fragment to the `styles.css` string (one line, immediately after the `.site-header{...}` rule added in Task 3):

```
.wp-block-navigation .wp-block-navigation-item.nav-cta a{background:var(--wp--preset--color--accent);color:var(--wp--preset--color--surface);border-radius:.5rem;padding:var(--wp--preset--spacing--20) var(--wp--preset--spacing--30);text-decoration:none}.wp-block-navigation .wp-block-navigation-item.nav-cta a:hover{background:var(--wp--preset--color--accent-hover);color:var(--wp--preset--color--surface)}
```

- [ ] **Step 4: Run the test to verify it passes**

Run: `PLAYWRIGHT_BASE_URL=http://localhost:8890 npx playwright test tests/e2e/navigation.spec.ts -g "CTA button"`
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add tests/e2e/navigation.spec.ts theme.json
git commit -m "feat(theme): style Contact nav item as CTA button"
```

---

### Task 5: Mobile overlay toggles

**Files:**
- Modify: `tests/e2e/navigation.spec.ts`
- Modify: `theme.json`

The hamburger + overlay is core Navigation behavior (`overlayMenu:"mobile"` set in Task 2). This task adds the overlay background styling and locks the behavior with a test at a mobile viewport.

- [ ] **Step 1: Add the failing test**

Append inside the `test.describe` block:

```ts
  test('mobile overlay opens and closes', async ({ page }) => {
    await page.setViewportSize({ width: 375, height: 800 });
    await page.goto('/');
    const openBtn = page.locator('header .wp-block-navigation__responsive-container-open').first();
    await expect(openBtn).toBeVisible();
    await openBtn.click();
    const overlay = page.locator('header .wp-block-navigation__responsive-container.is-menu-open').first();
    await expect(overlay).toBeVisible();
    await expect(overlay).toHaveCSS('background-color', 'rgb(255, 255, 255)');
    await page.locator('header .wp-block-navigation__responsive-container-close').first().click();
    await expect(overlay).toBeHidden();
  });
```

- [ ] **Step 2: Run the test to verify it fails**

Run: `PLAYWRIGHT_BASE_URL=http://localhost:8890 npx playwright test tests/e2e/navigation.spec.ts -g "mobile overlay"`
Expected: FAIL on the background assertion — the open overlay has no explicit `surface` background yet (open/close toggling itself is core behavior and may already pass; the background assertion is what fails).

- [ ] **Step 3: Add the overlay CSS**

In `theme.json`, append to the `styles.css` string (one line, after the CTA rules from Task 4):

```
.wp-block-navigation__responsive-container.is-menu-open{background:var(--wp--preset--color--surface)}.wp-block-navigation__responsive-container-open,.wp-block-navigation__responsive-container-close{color:var(--wp--preset--color--text)}
```

- [ ] **Step 4: Run the test to verify it passes**

Run: `PLAYWRIGHT_BASE_URL=http://localhost:8890 npx playwright test tests/e2e/navigation.spec.ts -g "mobile overlay"`
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add tests/e2e/navigation.spec.ts theme.json
git commit -m "feat(theme): style mobile nav overlay surface"
```

---

### Task 6: Active page indicator, focus ring, submenu styling

**Files:**
- Modify: `tests/e2e/navigation.spec.ts`
- Modify: `theme.json`

Precondition: the `:8890` site must have the seeded pages so `/about/` resolves. If `/about/` 404s, run once from the child-theme dir (this uses the already-running child env, not a new one):
`(cd /Users/jonas/Entwicklung/wp-starter-child-theme && npx wp-env run cli wp starter-theme seed)`

- [ ] **Step 1: Add the failing test**

Append inside the `test.describe` block:

```ts
  test('current page nav item gets the active indicator', async ({ page }) => {
    const resp = await page.goto('/about/');
    expect(resp?.status(), 'About page must exist (run `wp starter-theme seed`)').toBe(200);
    const active = page.locator(
      'header .wp-block-navigation a[aria-current="page"], header .wp-block-navigation .current-menu-item > a'
    ).first();
    await expect(active).toBeVisible();
    await expect(active).toHaveText('About');
    // Active links use the accent color (#4F46E5).
    await expect(active).toHaveCSS('color', 'rgb(79, 70, 229)');
  });
```

- [ ] **Step 2: Run the test to verify it fails**

Run: `PLAYWRIGHT_BASE_URL=http://localhost:8890 npx playwright test tests/e2e/navigation.spec.ts -g "active indicator"`
Expected: FAIL — no active-state CSS yet (color assertion fails).

- [ ] **Step 3: Add the active / focus / submenu CSS**

In `theme.json`, append to the `styles.css` string (one line, after the overlay rules from Task 5). Note the selector uses single quotes so the JSON string needs no escaping:

```
.wp-block-navigation .current-menu-item > a,.wp-block-navigation a[aria-current='page']{color:var(--wp--preset--color--accent);text-decoration:underline;text-underline-offset:.35em}.wp-block-navigation a:focus-visible{outline:none;box-shadow:var(--wp--preset--shadow--focus);border-radius:.25rem}.wp-block-navigation__submenu-container{background:var(--wp--preset--color--surface);border:1px solid var(--wp--preset--color--border);border-radius:.5rem;box-shadow:var(--wp--preset--shadow--lifted);padding:var(--wp--preset--spacing--20)}
```

- [ ] **Step 4: Run the test to verify it passes**

Run: `PLAYWRIGHT_BASE_URL=http://localhost:8890 npx playwright test tests/e2e/navigation.spec.ts -g "active indicator"`
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add tests/e2e/navigation.spec.ts theme.json
git commit -m "feat(theme): active-page, focus and submenu nav styling"
```

---

### Task 7: Full-suite green + theme.json sanity

**Files:** none (verification only)

- [ ] **Step 1: Run the whole nav spec against :8890**

Run: `PLAYWRIGHT_BASE_URL=http://localhost:8890 npx playwright test tests/e2e/navigation.spec.ts`
Expected: all 5 tests PASS.

- [ ] **Step 2: Validate theme.json is still valid JSON**

Run: `node -e "JSON.parse(require('fs').readFileSync('theme.json','utf8')); console.log('theme.json OK')"`
Expected: prints `theme.json OK` (catches any stray quote/escape from the appended CSS).

- [ ] **Step 3: Lint colors (theme uses a palette-only policy)**

Run: `npm run lint:colors`
Expected: passes — all added CSS uses `var(--wp--preset--color--*)` / shadow tokens, no raw hex.

- [ ] **Step 4: Final commit if anything was touched**

If Steps 1–3 required no fixes, nothing to commit. If a fix was needed, commit it:

```bash
git add -A
git commit -m "fix(theme): nav verification follow-ups"
```

---

## Self-Review

**Spec coverage:**
- Sticky (always, full size) → Task 3. ✓
- Core mobile overlay → Task 2 (`overlayMenu:"mobile"`) + Task 5 (styling/lock). ✓
- Seeded menu via inner blocks (Approach 1) → Task 2. ✓
- Items About/Blog/Contact, Contact = CTA, no Home → Task 2 + Task 4. ✓
- Active page indicator → Task 6. ✓
- Hover/focus states → existing theme.json hover + Task 6 focus-visible. ✓
- CTA button item → Task 4. ✓
- Submenu styling → Task 6. ✓
- Verify on localhost:8890, never start wp-env here → Constraints section + Task 1 + per-test commands. ✓
- No PHP / no PHPUnit changes → confirmed; only `parts/header.html`, `theme.json`, `tests/e2e/`, `playwright.config.ts`. ✓

**Placeholder scan:** No TBD/TODO; every CSS/markup/test step contains the exact content. ✓

**Type/selector consistency:** `.site-header` class, `.nav-cta` class, `aria-current="page"` / `.current-menu-item`, and `PLAYWRIGHT_BASE_URL` are used identically across tasks. The active-state test uses the double-quoted `[aria-current="page"]` (Playwright selector string), while the CSS rule uses the single-quoted `[aria-current='page']` (JSON-safe) — both match the same DOM attribute; this divergence is intentional and noted in Task 6. ✓
