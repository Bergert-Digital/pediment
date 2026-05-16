# Section Vertical-Padding Rhythm Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Give every `section.starter-section` symmetric fluid top/bottom padding and remove the inter-section margin, so backgrounded sections breathe and bands stack flush.

**Architecture:** A two-substring edit to the `styles.css` string in the parent `wp-starter-theme/theme.json`. No template/JS/generator changes. Guarded by an env-free assertion on the file content; final acceptance is a real front-end visual check at `:8890` (per the project's "verify on real pages, not fixtures" rule and the spec's verification section).

**Tech Stack:** WordPress block theme `theme.json` (`styles.css`); `python3` for the content assertion (no WP/browser needed).

**Spec:** `docs/superpowers/specs/2026-05-16-section-vertical-padding-rhythm-design.md`

---

## File Structure

- Modify: `wp-starter-theme/theme.json` — the `styles.css` string only (one line). One responsibility: global theme CSS. No new files; the change is too small and too coupled to `styles.css` to warrant one.

The exact `styles.css` value transitions from:

```
.wp-site-blocks{display:flex;flex-direction:column;min-height:100vh;min-height:100dvh}.wp-site-blocks > main{flex:1 0 auto}.entry-content.wp-block-post-content > section.starter-section + section.starter-section{margin-block-start:clamp(3.25rem, 2rem + 6vw, 6rem)}section.starter-section{padding-inline:var(--wp--preset--spacing--30)}section.starter-section > :where(:not(.alignfull):not(.alignwide)){max-width:var(--wp--style--global--content-size, 720px);margin-inline:auto}
```

to:

```
.wp-site-blocks{display:flex;flex-direction:column;min-height:100vh;min-height:100dvh}.wp-site-blocks > main{flex:1 0 auto}section.starter-section{padding-block:clamp(3rem, 2rem + 5vw, 5.5rem);padding-inline:var(--wp--preset--spacing--30)}section.starter-section > :where(:not(.alignfull):not(.alignwide)){max-width:var(--wp--style--global--content-size, 720px);margin-inline:auto}
```

i.e. **delete** the `.entry-content.wp-block-post-content > section.starter-section + section.starter-section{margin-block-start:clamp(3.25rem, 2rem + 6vw, 6rem)}` rule and **prepend** `padding-block:clamp(3rem, 2rem + 5vw, 5.5rem);` inside the `section.starter-section{…}` rule. The `:where()` inner-width rule and `padding-inline` are unchanged.

---

### Task 1: Apply the theme.json rhythm change

**Files:**
- Modify: `wp-starter-theme/theme.json` (the `styles.css` string)

- [ ] **Step 1: Write the failing assertion**

Save as `/tmp/assert_rhythm.py`:

```python
import json, sys
css = json.load(open("theme.json"))["styles"]["css"]
old = ".entry-content.wp-block-post-content > section.starter-section + section.starter-section{margin-block-start:clamp(3.25rem, 2rem + 6vw, 6rem)}"
new = "section.starter-section{padding-block:clamp(3rem, 2rem + 5vw, 5.5rem);padding-inline:var(--wp--preset--spacing--30)}"
inner = "section.starter-section > :where(:not(.alignfull):not(.alignwide)){max-width:var(--wp--style--global--content-size, 720px);margin-inline:auto}"
errs = []
if old in css: errs.append("inter-section margin rule still present")
if new not in css: errs.append("padding-block section rule missing/incorrect")
if inner not in css: errs.append("inner-width :where() rule was altered (must stay)")
if errs:
    print("FAIL:", "; ".join(errs)); sys.exit(1)
print("PASS"); sys.exit(0)
```

- [ ] **Step 2: Run it to verify it fails**

Run: `cd /Users/jonas/Entwicklung/wp-starter-theme && python3 /tmp/assert_rhythm.py`
Expected: `FAIL: inter-section margin rule still present; padding-block section rule missing/incorrect`

- [ ] **Step 3: Apply the edit to `theme.json`**

In `wp-starter-theme/theme.json`, in the `"css"` value, replace exactly:

`.entry-content.wp-block-post-content > section.starter-section + section.starter-section{margin-block-start:clamp(3.25rem, 2rem + 6vw, 6rem)}section.starter-section{padding-inline:var(--wp--preset--spacing--30)}`

with:

`section.starter-section{padding-block:clamp(3rem, 2rem + 5vw, 5.5rem);padding-inline:var(--wp--preset--spacing--30)}`

(That single replacement both deletes the margin rule and adds `padding-block`. Nothing else in the `css` string changes.)

- [ ] **Step 4: Run the assertion to verify it passes**

Run: `cd /Users/jonas/Entwicklung/wp-starter-theme && python3 /tmp/assert_rhythm.py`
Expected: `PASS`

- [ ] **Step 5: Verify theme.json is still valid JSON**

Run: `cd /Users/jonas/Entwicklung/wp-starter-theme && python3 -c "import json; json.load(open('theme.json')); print('valid json')"`
Expected: `valid json`

- [ ] **Step 6: Commit**

```bash
cd /Users/jonas/Entwicklung/wp-starter-theme
git add theme.json
git commit -m "fix(theme): section padding-block rhythm; drop inter-section margin

Every section now owns symmetric fluid top/bottom padding
(clamp(3rem, 2rem + 5vw, 5.5rem)); the inter-section margin-block-start
clamp is removed so backgrounded bands stack flush and content breathes
inside its background. Implements
docs/superpowers/specs/2026-05-16-section-vertical-padding-rhythm-design.md.

Co-Authored-By: Claude Opus 4.7 (1M context) <noreply@anthropic.com>"
```

---

### Task 2: Front-end verification gate (manual — required before "done")

**Files:** none (verification only)

This is the spec's real acceptance criterion. It is intentionally manual: the project rule is "verify layout on real `:8890` pages, never on hand-built fixtures," and the running env is the child-theme env at `localhost:8890` (which renders this parent `theme.json`). Do **not** mark the work complete on the assertion alone.

- [ ] **Step 1: Reload the regenerated multi-section page**

Open the existing AI-generated Bike Mechanic page on the front end at `http://localhost:8890` (View/Preview, not the editor). A normal reload suffices — `theme.json` is not browser-cached and there is no DB template override (verified: zero `wp_template` posts).

- [ ] **Step 2: Confirm the spec's acceptance checklist**

Verify all of:
- Backgrounded sections (hero, CTA) show clear, symmetric top/bottom breathing room inside their background.
- Adjacent backgrounded bands meet flush — no visible gap between them.
- First section sits below the post-title with its own top padding; the last section has bottom padding before the footer.
- No horizontal/inner-width regression: inner content still ≈720px centered; section backgrounds still full-bleed (the earlier `align:full` fix).
- Narrow viewport (~375px): padding scales down toward the 3rem floor; no overflow or clipped backgrounds.

- [ ] **Step 3: Record the outcome**

If all pass: the work is complete; note it in the session.
If any fail: stop, capture a screenshot, and treat it as a new debugging cycle (do not stack speculative CSS — diagnose against the rendered computed styles).

---

## Self-Review

**1. Spec coverage:**
- Spec "Change" §1 (remove margin) → Task 1 Step 3 (deletion). ✓
- Spec "Change" §2 (add padding-block, keep padding-inline) → Task 1 Step 3 (replacement keeps `padding-inline`). ✓
- Spec "inner-width rule unchanged" → Task 1 Step 1 assertion checks `inner` substring still present. ✓
- Spec "Verification" (real front end, backgrounded breathing room, flush bands, first/last, no h-regression, narrow viewport) → Task 2 Step 2 checklist, item-for-item. ✓
- Spec scope/non-goals (theme.json only, no template/JS, no regeneration) → File Structure + Architecture state this; no other files touched. ✓

**2. Placeholder scan:** No TBD/TODO/"handle edge cases"/vague steps. Every code/command step shows exact content and expected output. ✓

**3. Type consistency:** The three exact CSS substrings (`old`, `new`, `inner`) in the Task 1 assertion match the File Structure before/after blocks and the Step 3 replacement strings verbatim. ✓

No gaps found.
