# Section Rhythm & Divider Removal Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Generated pages get airy ~6rem section rhythm and no horizontal dividers, via theme.json only.

**Architecture:** Append two CSS rules to the existing `theme.json` › `styles.css` string: one large fluid `clamp()` margin between top-level post-content children (section boundaries), one that renders any `core/separator` invisible. No spacing-settings change, no block SCSS change, no block rebuild.

**Tech Stack:** WordPress block theme (`theme.json`), wp-env for verification.

**Spec:** `docs/superpowers/specs/2026-05-15-section-rhythm-design.md`

---

### Task 1: Confirm the post-content wrapper selector in wp-env

The spec's selector is provisional. WordPress markup around `<!-- wp:post-content /-->` varies by version; we must confirm the real DOM before writing the rule, so the section gap targets top-level page sections and never nested blocks.

**Files:**
- None modified in this task (investigation only).

- [ ] **Step 1: Ensure wp-env is running**

Run: `npm run env:start`
Expected: wp-env reports the site URL (typically `http://localhost:8888`). If Docker is not running, start Docker Desktop first.

- [ ] **Step 2: Open the generated coffee-shop landing page and inspect the DOM**

In a browser, open the generated landing page (the "Grounds & Grace" page, or any page produced via wp-starter-ai). Open devtools and inspect the element wrapping the page's top-level sections (hero, prose, faq, etc.).

Determine the exact ancestor → child relationship. Record which of these matches:
- `.entry-content.wp-block-post-content > *` (post content is the direct parent of sections)
- `main.wp-block-group > .entry-content > *` (an intermediate wrapper)
- something else (record the literal selector)

- [ ] **Step 3: Write the confirmed selector down**

Note the confirmed selector for use in Task 2. It MUST select only the page's top-level section blocks (direct children of post content) and MUST NOT match blocks nested inside a section. Verify by hovering candidate selectors in devtools and confirming only section-level elements highlight.

---

### Task 2: Add the section-gap and separator-neutralize CSS

**Files:**
- Modify: `theme.json` (the `styles.css` string, currently line 75)

- [ ] **Step 1: Read the current `styles.css` value**

Run: `grep -n '"css":' theme.json`
Expected: one line, currently:
`"css": ".wp-site-blocks{display:flex;flex-direction:column;min-height:100vh;min-height:100dvh}.wp-site-blocks > main{flex:1 0 auto}",`

- [ ] **Step 2: Append the two rules to the `css` string**

Edit `theme.json`. Replace the existing `css` value with the version below, substituting `__CONFIRMED_SELECTOR__` with the selector confirmed in Task 1 (use `.entry-content.wp-block-post-content > * + *` if Task 1 confirmed that exact relationship):

```
".wp-site-blocks{display:flex;flex-direction:column;min-height:100vh;min-height:100dvh}.wp-site-blocks > main{flex:1 0 auto}__CONFIRMED_SELECTOR__{margin-block-start:clamp(3.25rem, 2rem + 6vw, 6rem)}.wp-block-separator{border:0!important;background:transparent!important;height:0!important;margin:0!important}"
```

Notes:
- The section-gap selector must end in `> * + *` so the first section gets no extra top margin.
- Keep it a single-line JSON string (no literal newlines inside the value).
- Do not change `styles.spacing.blockGap` or `settings.spacing`.

- [ ] **Step 3: Validate theme.json is still valid JSON**

Run: `node -e "JSON.parse(require('fs').readFileSync('theme.json','utf8')); console.log('valid')"`
Expected: `valid`

- [ ] **Step 4: Commit**

```bash
git add theme.json
git commit -m "fix(theme): airy section rhythm + neutralize separators"
```

---

### Task 3: Verify rendering in wp-env

**Files:**
- None (verification only). If a defect is found, return to Task 2.

- [ ] **Step 1: Reload the generated landing page**

wp-env reads `theme.json` live; hard-reload the "Grounds & Grace" page (no rebuild needed). If styles do not update, run `npm run env:start` to re-sync.

- [ ] **Step 2: Confirm desktop appearance**

At a desktop viewport width, confirm:
- No horizontal rules anywhere on the page.
- Approximately 6rem of vertical space between sections (hero ↔ welcome ↔ offer ↔ quote ↔ faq).
- Paragraph/heading spacing *within* a section is unchanged (~1.5rem, noticeably tighter than the section gap).

- [ ] **Step 3: Confirm the selector does not leak**

In devtools, inspect a block nested inside a section (e.g. a paragraph inside the welcome section). Confirm it does NOT have the large `margin-block-start` from the section rule — only top-level sections do.

- [ ] **Step 4: Confirm mobile appearance**

Narrow the viewport to ~375px. Confirm the section gap compresses to roughly 3.25rem and the page is not cramped or overly sparse.

- [ ] **Step 5: Confirm no regression on a hand-built pattern**

Create or open a page using the `hero-cta-faq` pattern (`patterns/hero-cta-faq.php`). Confirm sections are spaced airily, no dividers, and internal block spacing is unchanged.

- [ ] **Step 6: Run lint to confirm no new violations**

Run: `npm run lint:colors && npm run lint:blocks`
Expected: both pass (changes are spacing-only; no color or block-structure changes).

---

## Self-Review

- **Spec coverage:** Change 1 (section gap) → Task 2 Step 2 + Task 1 (selector confirmation) + Task 3 Steps 2–4. Change 2 (separator neutralize) → Task 2 Step 2 + Task 3 Step 2. Non-goals respected (no spacing-settings/block-SCSS/AI changes). Verification items 1–4 → Task 2 Step 3 + Task 3. All spec sections covered.
- **Placeholder scan:** `__CONFIRMED_SELECTOR__` is an intentional, explicitly-resolved substitution (resolved in Task 1, with a concrete default given) — not an open TODO.
- **Type consistency:** N/A (CSS-string change only); selector name is consistent across Task 1 → Task 2 → Task 3.
