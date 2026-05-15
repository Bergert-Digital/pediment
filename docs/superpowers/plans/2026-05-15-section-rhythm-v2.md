# Section Rhythm v2 (Separator-as-Boundary) Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Make `core/separator` an invisible, airy section gap so AI-generated pages get correct rhythm, with no visible rules and no change to intra-section spacing.

**Architecture:** One CSS rule appended to `theme.json` › `styles.css`: separator becomes zero-height/invisible with a large fluid `margin-block`. Everything else keeps the default `blockGap`. A Playwright script measuring computed margins on the **real generated pages** in the child-theme env is the executable verification (the v1 defect was hidden by testing a non-representative hand-built fixture).

**Tech Stack:** WordPress block theme (`theme.json`), `@playwright/test` (devDependency), child-theme wp-env at `http://localhost:8890`.

**Spec:** `docs/superpowers/specs/2026-05-15-section-rhythm-v2-design.md`

---

### Task 1: Write the verification script and capture the baseline

The script measures, for every top-level post-content child on the real generated pages, its computed vertical margins, height, and whether it renders a visible rule. Running it BEFORE the change documents current (reverted) behavior and proves the script discriminates.

**Files:**
- Create: `_verify-rhythm.mjs` (repo root; scratch — removed in Task 3, never committed)

- [ ] **Step 1: Confirm the child-theme env is up and serves the real pages**

Run: `curl -s -o /dev/null -w "%{http_code}\n" "http://localhost:8890/?page_id=20"`
Expected: `200`. If not, in `/Users/jonas/Entwicklung/wp-starter-child-theme` run `npx wp-env start` (Docker must be running). Per project setup the child-theme env (`:8890`) is the only test base — do NOT start wp-env from `wp-starter-theme`.

- [ ] **Step 2: Create the verification script**

Create `/Users/jonas/Entwicklung/wp-starter-theme/_verify-rhythm.mjs`:

```js
import { chromium } from '@playwright/test';

const PAGES = [8, 12, 16, 20]; // Coffee Shop, Hair Salon, Mechanic x2
const BASE = 'http://localhost:8890';

function approx(px, target, tol = 12) { return Math.abs(px - target) <= tol; }

const browser = await chromium.launch();
let failures = [];

for (const width of [1280, 375]) {
  const page = await browser.newPage();
  await page.setViewportSize({ width, height: 900 });
  for (const id of PAGES) {
    await page.goto(`${BASE}/?page_id=${id}`, { waitUntil: 'networkidle' });
    const data = await page.$$eval('.entry-content.wp-block-post-content > *', els =>
      els.map((e, i) => {
        const r = e.getBoundingClientRect();
        const next = e.nextElementSibling;
        const cs = getComputedStyle(e);
        return {
          i,
          tag: e.tagName.toLowerCase(),
          isSep: e.classList.contains('wp-block-separator'),
          cls: (e.className || '').split(' ').filter(c => /starter-|separator|block-heading|block-list/.test(c)).join(',') || '(bare)',
          h: Math.round(r.height),
          borderTop: cs.borderTopWidth,
          bg: cs.backgroundColor,
          // effective gap between this element's box and the next element's box
          gapToNext: next ? Math.round(next.getBoundingClientRect().top - r.bottom) : null,
        };
      })
    );
    console.log(`\n=== page ${id} @ ${width}px ===`);
    for (const d of data) {
      console.log(`  [${d.i}] ${d.tag.padEnd(10)} h=${String(d.h).padEnd(4)} gapToNext=${String(d.gapToNext).padEnd(5)} ${d.isSep ? 'SEP' : '   '} ${d.cls}`);
    }
    // Assertions only meaningful AFTER the change; collected for Task 3.
    for (const d of data) {
      if (d.isSep) {
        if (d.h !== 0) failures.push(`p${id}@${width}: separator height ${d.h} != 0`);
        if (d.borderTop !== '0px') failures.push(`p${id}@${width}: separator borderTop ${d.borderTop} != 0px`);
        const target = width === 1280 ? 96 : 53;
        // effective gap the separator introduces = its own box gap to next + gap from prev.
      }
    }
  }
  await page.close();
}
await browser.close();
console.log('\n--- collected hard failures ---');
console.log(failures.length ? failures.join('\n') : '(none)');
```

- [ ] **Step 3: Run the script against current (reverted) state and record baseline**

Run: `cd /Users/jonas/Entwicklung/wp-starter-theme && node _verify-rhythm.mjs 2>&1 | tail -60`
Expected (current/reverted behavior): separators have non-zero height and a visible `borderTop` (e.g. `1px`/`4px`), and the "collected hard failures" list reports the separator height/border failures — confirming the script correctly detects the un-fixed state. Record the printed `gapToNext` values around separators and around the "Services We Offer" heading→prose pair for before/after comparison.

- [ ] **Step 4: Commit the baseline note (script itself is NOT committed)**

No commit in this task (no tracked files changed). Proceed to Task 2.

---

### Task 2: Apply the separator-as-boundary CSS

**Files:**
- Modify: `theme.json` (the `styles.css` string, line ~75)

- [ ] **Step 1: Read the current `styles.css` value**

Run: `grep -n '"css":' theme.json`
Expected (post-revert original):
`"css": ".wp-site-blocks{display:flex;flex-direction:column;min-height:100vh;min-height:100dvh}.wp-site-blocks > main{flex:1 0 auto}",`

- [ ] **Step 2: Append the separator rule to the `css` string**

Edit `theme.json` — replace the `css` value with (single-line JSON string, inner quotes escaped as `\"`; this rule has none so no escaping needed):

```
".wp-site-blocks{display:flex;flex-direction:column;min-height:100vh;min-height:100dvh}.wp-site-blocks > main{flex:1 0 auto}.wp-block-separator{border:0!important;background:transparent!important;height:0!important;margin-block:clamp(3.25rem, 2rem + 6vw, 6rem)!important}"
```

Do not change `settings.spacing` or `styles.spacing.blockGap`.

- [ ] **Step 3: Validate JSON**

Run: `node -e "const c=JSON.parse(require('fs').readFileSync('theme.json','utf8')).styles.css; console.log(c.includes('margin-block:clamp')?'ok':'MISSING')"`
Expected: `ok`

- [ ] **Step 4: Confirm the child-theme env serves the updated rule**

Run: `curl -s "http://localhost:8890/?page_id=20" | grep -c 'margin-block:clamp(3.25rem'`
Expected: `1` or more. If `0`, the child-theme env is not picking up the parent `theme.json` — stop and inspect `/Users/jonas/Entwicklung/wp-starter-child-theme/.wp-env.override.json` to confirm the parent theme is mounted from `/Users/jonas/Entwicklung/wp-starter-theme`; do not proceed until the rule is served.

- [ ] **Step 5: Commit**

```bash
git add theme.json
git commit -m "fix(theme): separator-as-boundary section rhythm (v2)"
```

---

### Task 3: Verify against real generated pages and clean up

**Files:**
- Modify: none. Delete: `_verify-rhythm.mjs` at the end.

- [ ] **Step 1: Re-run the verification script (post-change)**

Run: `cd /Users/jonas/Entwicklung/wp-starter-theme && node _verify-rhythm.mjs 2>&1 | tail -60`

- [ ] **Step 2: Assert separator behavior (desktop, 1280px), all pages 8/12/16/20**

In the `@ 1280px` output for each page, confirm for every `SEP` row:
- `h=0` (zero height)
- `borderTop` reported `0px` and the "collected hard failures" list prints `(none)`
- the effective gap a separator introduces between its neighbouring sections ≈ 96px (6rem). Compute it as the distance between the section box *before* the separator and the section box *after* it (separator box is zero-height; sum the `gapToNext` of the pre-separator element and of the separator). Confirm ≈ 96px within ±12px. If margin-collapse makes the effective gap materially off 96px, switch the rule to `padding-block` instead of `margin-block` in Task 2 Step 2 and re-run from Task 2 Step 3.

- [ ] **Step 3: Assert intra-section tightness (desktop), the v1 regression cases**

In the `@ 1280px` output:
- On page 20, the bare `wp-block-heading` "Services We Offer" row's `gapToNext` to the following `starter-prose` row ≈ default blockGap (~24px), NOT ~96px.
- The three consecutive `starter-stat` rows have `gapToNext` ≈ blockGap (~24px) between them, NOT ~96px.
- No row reports a visible rule (every `SEP` row has `h=0`, `borderTop:0px`).

- [ ] **Step 4: Assert mobile compression (375px)**

In the `@ 375px` output: the effective separator gap compresses to ≈ 52–55px (~3.25rem); the heading→prose and stat–stat `gapToNext` values are unchanged from desktop (intra-section spacing does not scale with the section gap).

- [ ] **Step 5: Regression check on a hand-built pattern**

Create a page from the `hero-cta-faq` pattern in the child-theme env:
Run: `cd /Users/jonas/Entwicklung/wp-starter-child-theme && npx wp-env run cli wp post create --post_type=page --post_title="RhythmRegression" --post_status=publish --post_content='<!-- wp:pattern {"slug":"starter/hero-cta-faq"} /-->' --porcelain`
Open that page id at `http://localhost:8890/?page_id=<id>`. Confirm: it has no separators, so it flows at normal tight rhythm with no visible rules — unchanged from the pre-v1 baseline and not broken. Then delete it:
`npx wp-env run cli wp post delete <id> --force`

- [ ] **Step 6: Lint**

Run: `cd /Users/jonas/Entwicklung/wp-starter-theme && npm run lint:colors && npm run lint:blocks`
Expected: both pass.

- [ ] **Step 7: Remove the scratch script**

Run: `rm -f /Users/jonas/Entwicklung/wp-starter-theme/_verify-rhythm.mjs`
Confirm `git status --porcelain` shows no `_verify-rhythm.mjs` and theme.json is already committed.

---

## Self-Review

- **Spec coverage:** Mechanism (separator invisible + fluid `margin-block`) → Task 2. Verify on real `:8890` pages 8/12/16/20 via Playwright computed margins → Task 1 (baseline) + Task 3 Steps 1–4. Effective-gap / margin-collapse handling → Task 3 Step 2 (with padding-block fallback). v1 regression cases (heading→prose, stat band, no visible rule) → Task 3 Step 3. Mobile compression → Task 3 Step 4. Hand-built pattern non-goal/regression → Task 3 Step 5. Lint → Task 3 Step 6. Non-goals (no spacing settings/SCSS/PHP/AI changes) respected — only the `css` string changes. All spec sections covered.
- **Placeholder scan:** No TBD/TODO; the only `<id>` placeholder (Task 3 Step 5) is a runtime value the command prints, with explicit create/delete commands — not an unfilled gap.
- **Type/Name consistency:** Script filename `_verify-rhythm.mjs`, page set `[8,12,16,20]`, base `http://localhost:8890`, and the CSS rule string are identical across Tasks 1–3.
