# Generator-Emitted Section Groups Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** wp-starter-ai emits each page section as a `core/group` (`<section class="starter-section">`), guaranteed by a deterministic client-side normalizer; the theme spaces those real sections and drops the separator hack.

**Architecture:** Allowlist `core/group` + prompt nudge so the model groups sections; a deterministic `normalizeSections` pass in `editor/applyToolCalls.ts` (the only point that shapes saved content) wraps any ungrouped separator-delimited run into a section group and removes separators; theme `theme.json` spaces `section.starter-section` groups.

**Tech Stack:** WordPress plugin (PHP 8, PHPUnit), TypeScript editor (Jest via `wp-scripts test-unit-js`, `@wordpress/data`/`blocks`), block theme `theme.json`, child-theme wp-env `:8890`, Playwright.

**Spec:** `docs/superpowers/specs/2026-05-15-generator-section-groups-design.md`

**Repos:** Phase 1 in `/Users/jonas/Entwicklung/wp-starter-ai`; Phase 2 in `/Users/jonas/Entwicklung/wp-starter-theme`. Both on `development`, main checkout (no worktree — integration uses the live `:8890` env which mounts the main checkouts).

**RELEASE-ORDERING CONSTRAINT:** Phase 2 (theme) removes the separator rule. Do not merge/deploy Phase 2 until Phase 1 is effective and at least one archetype regenerated and verified. Within this plan, Phase 1 Tasks 1–6 complete before Phase 2 Tasks 7–8.

---

## Phase 1 — wp-starter-ai

### Task 1: Allowlist `core/group`

**Files:**
- Modify: `/Users/jonas/Entwicklung/wp-starter-ai/src/Anthropic/SchemaBuilder.php` (CORE_ALLOWLIST, ends ~line 61)
- Test: `/Users/jonas/Entwicklung/wp-starter-ai/tests/phpunit/Anthropic/SchemaBuilderTest.php`

- [ ] **Step 1: Add a failing test**

Append to `SchemaBuilderTest.php` inside the test class:

```php
public function test_core_group_is_allowlisted_with_inner_blocks(): void {
	$schema = ( new \StarterAi\Anthropic\SchemaBuilder() )->build( true );
	$this->assertArrayHasKey( 'core/group', $schema['blocks'] );
	$this->assertTrue( $schema['blocks']['core/group']['allowsInnerBlocks'] );
}
```

- [ ] **Step 2: Run it, expect fail**

Run: `cd /Users/jonas/Entwicklung/wp-starter-ai && composer test -- --filter test_core_group_is_allowlisted_with_inner_blocks`
Expected: FAIL (`core/group` key absent).

- [ ] **Step 3: Add `core/group` to `CORE_ALLOWLIST`**

In `SchemaBuilder.php`, add this entry immediately after the `core/separator` entry (before the closing `];` of `CORE_ALLOWLIST`):

```php
		'core/group' => [
			'description'       => 'A section container. Wrap each distinct page section in one.',
			'attributes'        => [
				'tagName'   => [ 'type' => 'string', 'default' => 'section' ],
				'className' => [ 'type' => 'string' ],
			],
			'allowsInnerBlocks' => true,
		],
```

- [ ] **Step 4: Run it, expect pass**

Run: `cd /Users/jonas/Entwicklung/wp-starter-ai && composer test -- --filter test_core_group_is_allowlisted_with_inner_blocks`
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
cd /Users/jonas/Entwicklung/wp-starter-ai && git add src/Anthropic/SchemaBuilder.php tests/phpunit/Anthropic/SchemaBuilderTest.php && git commit -m "feat(schema): allowlist core/group as section container"
```

---

### Task 2: System-prompt section guidance

**Files:**
- Modify: `/Users/jonas/Entwicklung/wp-starter-ai/src/Chat/PromptBuilder.php` (`systemPrompt()`, ~lines 22–35)
- Test: `/Users/jonas/Entwicklung/wp-starter-ai/tests/phpunit/Chat/PromptBuilderTest.php`

- [ ] **Step 1: Add a failing test**

Append to `PromptBuilderTest.php` inside the test class:

```php
public function test_system_prompt_instructs_section_grouping(): void {
	$pb = new \StarterAi\Chat\PromptBuilder( [ 'core/group' => [ 'description' => 'A section container.' ] ] );
	$prompt = $pb->systemPrompt();
	$this->assertStringContainsString( 'starter-section', $prompt );
	$this->assertStringContainsString( 'core/group', $prompt );
}
```

- [ ] **Step 2: Run it, expect fail**

Run: `cd /Users/jonas/Entwicklung/wp-starter-ai && composer test -- --filter test_system_prompt_instructs_section_grouping`
Expected: FAIL (no `starter-section` text).

- [ ] **Step 3: Add the guidance line**

In `PromptBuilder.php::systemPrompt()`, immediately after the line
`$lines[] = 'Write naturally and concisely in your prose. Do not over-explain. Do not apologize. If you are not changing the post, simply answer the question.';`
add:

```php
		$lines[] = '';
		$lines[] = 'Page structure: compose a page as a sequence of distinct sections. Wrap each section\'s blocks in a core/group with attributes {"tagName":"section","className":"starter-section"}. Do not emit a flat list of top-level paragraphs or headings — group them into their section. If you do not wrap a section in a group, place a core/separator between sections.';
```

- [ ] **Step 4: Run it, expect pass**

Run: `cd /Users/jonas/Entwicklung/wp-starter-ai && composer test -- --filter test_system_prompt_instructs_section_grouping`
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
cd /Users/jonas/Entwicklung/wp-starter-ai && git add src/Chat/PromptBuilder.php tests/phpunit/Chat/PromptBuilderTest.php && git commit -m "feat(prompt): instruct section grouping with core/group"
```

---

### Task 3: Pure `planSections` partition logic

**Files:**
- Create: `/Users/jonas/Entwicklung/wp-starter-ai/editor/normalizeSections.ts`
- Create: `/Users/jonas/Entwicklung/wp-starter-ai/editor/test/normalizeSections.test.ts`

- [ ] **Step 1: Write the failing test**

Create `editor/test/normalizeSections.test.ts`:

```ts
import { planSections, type BlockLike } from '../normalizeSections';

const b = (name: string, className?: string): BlockLike => ({
  name,
  attributes: className ? { className } : {},
});

describe('planSections', () => {
  it('wraps a separator-delimited run into one section, separators dropped', () => {
    const blocks = [b('starter/hero'), b('core/separator'), b('core/heading'), b('core/paragraph')];
    expect(planSections(blocks)).toEqual([
      { kind: 'wrap', indices: [0] },
      { kind: 'wrap', indices: [2, 3] },
    ]);
  });

  it('keeps an existing starter-section group untouched (idempotent)', () => {
    const blocks = [b('core/group', 'starter-section'), b('core/separator'), b('starter/faq')];
    expect(planSections(blocks)).toEqual([
      { kind: 'keep', index: 0 },
      { kind: 'wrap', indices: [2] },
    ]);
  });

  it('no separators, no groups → single section', () => {
    expect(planSections([b('core/heading'), b('core/paragraph')])).toEqual([
      { kind: 'wrap', indices: [0, 1] },
    ]);
  });

  it('all already sections → unchanged', () => {
    const blocks = [b('core/group', 'starter-section'), b('core/group', 'x starter-section y')];
    expect(planSections(blocks)).toEqual([
      { kind: 'keep', index: 0 },
      { kind: 'keep', index: 1 },
    ]);
  });

  it('ignores empty segments from leading/consecutive/trailing separators', () => {
    const blocks = [b('core/separator'), b('starter/hero'), b('core/separator'), b('core/separator'), b('starter/cta'), b('core/separator')];
    expect(planSections(blocks)).toEqual([
      { kind: 'wrap', indices: [1] },
      { kind: 'wrap', indices: [4] },
    ]);
  });
});
```

- [ ] **Step 2: Run it, expect fail**

Run: `cd /Users/jonas/Entwicklung/wp-starter-ai && npm run test:js -- normalizeSections`
Expected: FAIL (module not found).

- [ ] **Step 3: Implement the pure function**

Create `editor/normalizeSections.ts`:

```ts
export type BlockLike = { name: string; attributes?: { className?: string } };

export type SectionPlan =
  | { kind: 'keep'; index: number }
  | { kind: 'wrap'; indices: number[] };

const SECTION_CLASS = 'starter-section';

function isSectionGroup(b: BlockLike): boolean {
  return (
    b.name === 'core/group' &&
    typeof b.attributes?.className === 'string' &&
    b.attributes.className.split(/\s+/).includes(SECTION_CLASS)
  );
}

/**
 * Partition a flat top-level block list into a deterministic section plan.
 * Runs split on core/separator (separators are dropped). A segment that is
 * exactly one already-correct section group is kept as-is (idempotent).
 */
export function planSections(blocks: BlockLike[]): SectionPlan[] {
  const out: SectionPlan[] = [];
  let segment: number[] = [];

  const flush = () => {
    if (segment.length === 0) return;
    if (segment.length === 1 && isSectionGroup(blocks[segment[0]])) {
      out.push({ kind: 'keep', index: segment[0] });
    } else {
      out.push({ kind: 'wrap', indices: segment.slice() });
    }
    segment = [];
  };

  blocks.forEach((blk, i) => {
    if (blk.name === 'core/separator') {
      flush();
    } else {
      segment.push(i);
    }
  });
  flush();
  return out;
}
```

- [ ] **Step 4: Run it, expect pass**

Run: `cd /Users/jonas/Entwicklung/wp-starter-ai && npm run test:js -- normalizeSections`
Expected: PASS (5 tests).

- [ ] **Step 5: Commit**

```bash
cd /Users/jonas/Entwicklung/wp-starter-ai && git add editor/normalizeSections.ts editor/test/normalizeSections.test.ts && git commit -m "feat(editor): pure planSections section-partition logic"
```

---

### Task 4: `normalizeSections` applier + wire into `applyToolCalls`

**Files:**
- Modify: `/Users/jonas/Entwicklung/wp-starter-ai/editor/normalizeSections.ts` (add applier)
- Modify: `/Users/jonas/Entwicklung/wp-starter-ai/editor/applyToolCalls.ts`
- Modify: `/Users/jonas/Entwicklung/wp-starter-ai/editor/test/normalizeSections.test.ts` (applier test)

- [ ] **Step 1: Write the failing applier test**

Append to `editor/test/normalizeSections.test.ts`:

```ts
import { normalizeSections } from '../normalizeSections';

describe('normalizeSections applier', () => {
  it('replaces root with section groups and drops separators', () => {
    const root = [
      { clientId: 'h', name: 'starter/hero', attributes: {}, innerBlocks: [] },
      { clientId: 's', name: 'core/separator', attributes: {}, innerBlocks: [] },
      { clientId: 'g', name: 'core/heading', attributes: {}, innerBlocks: [] },
      { clientId: 'p', name: 'core/paragraph', attributes: {}, innerBlocks: [] },
    ];
    const replaceBlocks = jest.fn();
    const created: any[] = [];
    normalizeSections(
      { getBlocks: () => root, replaceBlocks },
      (name, attrs, inner) => {
        const blk = { name, attributes: attrs, innerBlocks: inner, clientId: 'new-' + created.length };
        created.push(blk);
        return blk;
      }
    );
    expect(replaceBlocks).toHaveBeenCalledTimes(1);
    const [ids, blocks] = replaceBlocks.mock.calls[0];
    expect(ids).toEqual(['h', 's', 'g', 'p']);
    expect(blocks).toHaveLength(2);
    expect(blocks[0]).toMatchObject({ name: 'core/group', attributes: { tagName: 'section', className: 'starter-section' } });
    expect(blocks[0].innerBlocks.map((x: any) => x.clientId)).toEqual(['h']);
    expect(blocks[1].innerBlocks.map((x: any) => x.clientId)).toEqual(['g', 'p']);
  });

  it('is a no-op-shaped result when already all sections (keeps same blocks)', () => {
    const root = [
      { clientId: 'a', name: 'core/group', attributes: { className: 'starter-section' }, innerBlocks: [] },
      { clientId: 'b', name: 'core/group', attributes: { className: 'starter-section' }, innerBlocks: [] },
    ];
    const replaceBlocks = jest.fn();
    normalizeSections({ getBlocks: () => root, replaceBlocks }, (n, a, i) => ({ name: n, attributes: a, innerBlocks: i }));
    const [, blocks] = replaceBlocks.mock.calls[0];
    expect(blocks).toEqual(root);
  });
});
```

- [ ] **Step 2: Run it, expect fail**

Run: `cd /Users/jonas/Entwicklung/wp-starter-ai && npm run test:js -- normalizeSections`
Expected: FAIL (`normalizeSections` not exported).

- [ ] **Step 3: Implement the applier**

Append to `editor/normalizeSections.ts`:

```ts
type RootBlock = { clientId: string; name: string; attributes: any; innerBlocks: any[] };

export type NormalizeDeps = {
  getBlocks: () => RootBlock[];
  replaceBlocks: (clientIds: string[], blocks: any[]) => void;
};

export type CreateBlock = (name: string, attributes: any, innerBlocks: any[]) => any;

/**
 * Deterministically rewrite the editor root into section groups.
 * Idempotent: already-correct section groups are reused unchanged.
 */
export function normalizeSections(deps: NormalizeDeps, create: CreateBlock): void {
  const root = deps.getBlocks();
  if (root.length === 0) return;

  const plan = planSections(root);
  // Nothing to do if every root block is already a kept section group.
  if (plan.length === root.length && plan.every((p) => p.kind === 'keep')) return;

  const next = plan.map((p) =>
    p.kind === 'keep'
      ? root[p.index]
      : create(
          'core/group',
          { tagName: 'section', className: SECTION_CLASS, layout: { type: 'default' } },
          p.indices.map((i) => root[i])
        )
  );

  deps.replaceBlocks(
    root.map((b) => b.clientId),
    next
  );
}
```

- [ ] **Step 4: Run it, expect pass**

Run: `cd /Users/jonas/Entwicklung/wp-starter-ai && npm run test:js -- normalizeSections`
Expected: PASS (7 tests total).

- [ ] **Step 5: Wire into `applyToolCalls.ts`**

In `editor/applyToolCalls.ts`:

Change the import line 2 from:
`import { createBlock } from '@wordpress/blocks';`
to:
`import { createBlock } from '@wordpress/blocks';\nimport { normalizeSections } from './normalizeSections';`

Then replace the closing of the function (lines 25–69, the `runBatch(() => { ... });` block followed by `}`) so that the normalizer runs inside the same batch, immediately after the `for` loop, before `runBatch` closes:

Locate:
```ts
      }
    }
  });
}
```
Replace with:
```ts
      }
    }
    normalizeSections(
      {
        getBlocks: () => blockSelect.getBlocks() as any[],
        replaceBlocks: (ids: string[], blocks: any[]) => blockEditor.replaceBlocks(ids, blocks),
      },
      createBlock
    );
  });
}
```

- [ ] **Step 6: Lint, typecheck, build**

Run: `cd /Users/jonas/Entwicklung/wp-starter-ai && npm run lint:js && npx tsc --noEmit -p . && npm run build`
Expected: lint clean, no TS errors, build succeeds.

- [ ] **Step 7: Commit**

```bash
cd /Users/jonas/Entwicklung/wp-starter-ai && git add editor/normalizeSections.ts editor/applyToolCalls.ts editor/test/normalizeSections.test.ts && git commit -m "feat(editor): deterministic section-group normalizer in applyToolCalls"
```

---

### Task 5: Reconcile mock fixtures & full test suites

**Files:**
- Inspect/Modify: `/Users/jonas/Entwicklung/wp-starter-ai/src/Mock/fixtures/compose-*.json`
- Inspect: `/Users/jonas/Entwicklung/wp-starter-ai/tests/phpunit/Mock/FixturesTest.php`

- [ ] **Step 1: Run the full PHP suite**

Run: `cd /Users/jonas/Entwicklung/wp-starter-ai && composer test`
Expected: all green. If `FixturesTest` or others fail, read the failure: fixtures are mock Anthropic responses (flat block lists are fine — the client normalizer groups them at apply time), so a failure here means a test asserts top-level structure that the normalizer now changes. Only adjust fixtures/tests if a test encodes the OLD flat expectation; do not weaken unrelated assertions.

- [ ] **Step 2: If fixtures/tests needed changes, re-run**

Run: `cd /Users/jonas/Entwicklung/wp-starter-ai && composer test && npm run test:js`
Expected: both suites green.

- [ ] **Step 3: Commit (only if changes were made)**

```bash
cd /Users/jonas/Entwicklung/wp-starter-ai && git add -A src/Mock/fixtures tests/phpunit && git commit -m "test(fixtures): reconcile with section-group normalization"
```
If no changes were needed, skip this commit and note "no fixture changes required".

---

### Task 6: Integration verification on real `:8890` (Playwright + mock provider)

**Files:**
- Create: `/Users/jonas/Entwicklung/wp-starter-ai/_section-e2e.mjs` (scratch — removed in Step 5, never committed)

- [ ] **Step 1: Ensure the child-theme env is up and the built plugin is loaded**

Run: `curl -s -o /dev/null -w "%{http_code}\n" "http://localhost:8890/wp-admin/"` → expect `200` or `302`. If down: `cd /Users/jonas/Entwicklung/wp-starter-child-theme && npx wp-env start`. The plugin build from Task 4 Step 6 must be present (re-run `npm run build` in wp-starter-ai if unsure).

- [ ] **Step 2: Drive a real compose turn through the REST API with the mock provider**

The mock provider returns fixture tool_calls; the normalizer runs in the browser, so a pure REST call will NOT exercise it. Use Playwright to load the editor, trigger a compose, and read the resulting editor blocks. Create `/Users/jonas/Entwicklung/wp-starter-ai/_section-e2e.mjs`:

```js
import { chromium } from '@playwright/test';

const ADMIN = 'http://localhost:8890/wp-admin';
const browser = await chromium.launch();
const page = await browser.newPage();

// Log in (default wp-env creds admin/password).
await page.goto(`${ADMIN}/`);
if (await page.locator('#user_login').count()) {
  await page.fill('#user_login', 'admin');
  await page.fill('#user_pass', 'password');
  await page.click('#wp-submit');
}
// New page in the block editor.
await page.goto(`${ADMIN}/post-new.php?post_type=page`);
await page.waitForSelector('.block-editor');

// Open the Starter AI sidebar and send a compose message.
// (Selectors target the plugin's BlockChatPanel; adjust if the panel uses different labels.)
await page.getByRole('button', { name: /starter ai/i }).click().catch(() => {});
const input = page.getByPlaceholder(/ask|message|compose/i).first();
await input.fill('Compose a landing page for a coffee shop.');
await input.press('Enter');

// Wait for the turn to complete and the normalizer to run.
await page.waitForTimeout(8000);

const structure = await page.evaluate(() => {
  const sel = window.wp.data.select('core/block-editor');
  return sel.getBlocks().map(b => ({
    name: b.name,
    tag: b.attributes?.tagName,
    cls: b.attributes?.className,
    children: b.innerBlocks.length,
  }));
});
console.log(JSON.stringify(structure, null, 2));

const allSections = structure.every(b => b.name === 'core/group' && /starter-section/.test(b.cls || ''));
const noSeparators = !structure.some(b => b.name === 'core/separator');
console.log('ALL_SECTIONS=' + allSections + ' NO_SEPARATORS=' + noSeparators);
await browser.close();
process.exit(allSections && noSeparators ? 0 : 1);
```

- [ ] **Step 3: Run it**

Run: `cd /Users/jonas/Entwicklung/wp-starter-ai && node _section-e2e.mjs`
Expected: printed structure shows only `core/group` top-level blocks with `starter-section` in `cls`, each with `children > 0`, and `ALL_SECTIONS=true NO_SEPARATORS=true`; exit 0.
If selectors for the chat panel/input don't match, inspect the editor DOM (the plugin renders `editor/BlockChatPanel.tsx`) and correct the `getByRole`/`getByPlaceholder` locators — the structural assertion logic stays the same.

- [ ] **Step 4: Idempotency check**

In the same browser session (or a second run that sends a follow-up like "shorten the hero"), re-read `getBlocks()` and confirm the top-level is still only `starter-section` groups (no nested `core/group` inside a section, no re-wrap). Add to the script a second message + re-assert, or run the script twice against the same draft. Confirm exit 0 again.

- [ ] **Step 5: Cleanup**

Run: `rm -f /Users/jonas/Entwicklung/wp-starter-ai/_section-e2e.mjs` and confirm `cd /Users/jonas/Entwicklung/wp-starter-ai && git status --porcelain` does not list it.

- [ ] **Step 6: Phase 1 gate**

Confirm: `composer test` green, `npm run test:js` green, `npm run build` succeeds, and the Step 3/4 structural assertions passed on the real `:8890` editor. Only then proceed to Phase 2.

---

## Phase 2 — wp-starter-theme

### Task 7: Replace separator rule with section-group spacing

**Files:**
- Modify: `/Users/jonas/Entwicklung/wp-starter-theme/theme.json` (`styles.css`, line 75)

- [ ] **Step 1: Confirm current value**

Run: `grep -n '"css":' /Users/jonas/Entwicklung/wp-starter-theme/theme.json`
Expected: the line contains the `.wp-block-separator{...margin-block:clamp...}` rule (commit `2d5003c`).

- [ ] **Step 2: Replace the `css` value**

Set the `styles.css` value in `theme.json` to exactly (single-line JSON string):

```
".wp-site-blocks{display:flex;flex-direction:column;min-height:100vh;min-height:100dvh}.wp-site-blocks > main{flex:1 0 auto}.entry-content.wp-block-post-content > section.starter-section + section.starter-section{margin-block-start:clamp(3.25rem, 2rem + 6vw, 6rem)}section.starter-section{padding-inline:var(--wp--preset--spacing--30)}section.starter-section > :where(:not(.alignfull):not(.alignwide)){max-width:var(--wp--style--global--content-size, 720px);margin-inline:auto}"
```

This removes the `.wp-block-separator` rule and adds the three section rules from the spec.

- [ ] **Step 3: Validate JSON**

Run: `node -e "const c=JSON.parse(require('fs').readFileSync('/Users/jonas/Entwicklung/wp-starter-theme/theme.json','utf8')).styles.css; console.log(!c.includes('wp-block-separator') && c.includes('section.starter-section')?'ok':'BAD')"`
Expected: `ok`.

- [ ] **Step 4: Lint**

Run: `cd /Users/jonas/Entwicklung/wp-starter-theme && npm run lint:colors && npm run lint:blocks`
Expected: both pass.

- [ ] **Step 5: Commit**

```bash
cd /Users/jonas/Entwicklung/wp-starter-theme && git add theme.json && git commit -m "feat(theme): space starter-section groups; drop separator hack"
```

---

### Task 8: Verify spacing on real regenerated grouped pages

**Files:**
- Create: `/Users/jonas/Entwicklung/wp-starter-theme/_verify-sections.mjs` (scratch — removed in Step 4, never committed)

- [ ] **Step 1: Produce a grouped page in `:8890`**

Using the Phase 1 verified flow (Task 6 approach), compose/regenerate at least two archetype pages in the `:8890` editor and **Save/Publish** them so `post_content` persists as `section.starter-section` groups. Note their page IDs.

- [ ] **Step 2: Create the verification script**

Create `/Users/jonas/Entwicklung/wp-starter-theme/_verify-sections.mjs` (replace `IDS` with the published page IDs from Step 1):

```js
import { chromium } from '@playwright/test';
const IDS = [/* e.g. 30, 31 */];
const BASE = 'http://localhost:8890';
const browser = await chromium.launch();
let fail = [];
for (const width of [1280, 375]) {
  const page = await browser.newPage();
  await page.setViewportSize({ width, height: 900 });
  for (const id of IDS) {
    await page.goto(`${BASE}/?page_id=${id}`, { waitUntil: 'networkidle' });
    const rows = await page.$$eval('.entry-content.wp-block-post-content > *', els =>
      els.map((e, i) => {
        const r = e.getBoundingClientRect();
        const n = e.nextElementSibling;
        const cs = getComputedStyle(e);
        return {
          i, tag: e.tagName.toLowerCase(),
          cls: (e.className||'').includes('starter-section') ? 'starter-section' : (e.className||'(none)'),
          mt: cs.marginBlockStart,
          gapToNext: n ? Math.round(n.getBoundingClientRect().top - r.bottom) : null,
          isSep: e.classList.contains('wp-block-separator'),
        };
      })
    );
    console.log(`\n=== page ${id} @ ${width}px ===`);
    rows.forEach(r => console.log(`  [${r.i}] ${r.tag} ${r.cls} mt=${r.mt} gap=${r.gapToNext}`));
    if (rows.some(r => r.isSep)) fail.push(`p${id}@${width}: visible separator present`);
    if (!rows.every(r => r.tag === 'section')) fail.push(`p${id}@${width}: non-section top-level child`);
    const gaps = rows.slice(1).map(r => parseFloat(r.mt));
    const target = width === 1280 ? 96 : 53;
    gaps.forEach((g, k) => { if (Math.abs(g - target) > 14) fail.push(`p${id}@${width}: section ${k+1} gap ${g} != ~${target}`); });
  }
  await page.close();
}
await browser.close();
console.log('\n--- failures ---\n' + (fail.length ? fail.join('\n') : '(none)'));
process.exit(fail.length ? 1 : 0);
```

- [ ] **Step 3: Run and assert**

Run: `cd /Users/jonas/Entwicklung/wp-starter-theme && node _verify-sections.mjs`
Expected: every top-level child is a `<section>` with `starter-section`; no `wp-block-separator`; inter-section `margin-block-start` ≈ 96px @1280 / ≈ 53px @375; failures `(none)`; exit 0. Also spot-check in a browser that normal text is centred at ~720px and `starter/*` blocks are not visibly double-constrained (the `:where` inner-width nuance from the spec); if double-constrain is visible, narrow the selector to exclude `[class*="wp-block-starter-"]` and re-run.

- [ ] **Step 4: Cleanup & lint**

Run: `rm -f /Users/jonas/Entwicklung/wp-starter-theme/_verify-sections.mjs && cd /Users/jonas/Entwicklung/wp-starter-theme && npm run lint:colors && npm run lint:blocks && git status --porcelain`
Expected: script gone, lints pass, no scratch file tracked.

---

## Self-Review

- **Spec coverage:** Component A (allowlist core/group) → Task 1. B (prompt) → Task 2. C (deterministic normalizer in applyToolCalls; partition + applier; idempotent) → Tasks 3–4. Fixtures/tests reconcile → Task 5. Integration on real `:8890`, idempotency → Task 6. D (theme: remove separator rule + section spacing + inner-width) → Task 7. Real grouped-page verification incl. inner-width nuance → Task 8. Release-ordering constraint → header + Phase 1 gate (Task 6 Step 6) before Phase 2. Non-goals (no post migration, no schema-contract change, no nested/heading inference) — nothing in the plan does these. All spec sections covered.
- **Placeholder scan:** No TBD/TODO. `IDS`/page-id values in Tasks 6/8 are runtime values produced by prior steps with explicit instructions to fill them; not unfilled gaps. Fixture step (Task 5) is conditional with explicit decision criteria, not a vague "handle it".
- **Type/name consistency:** `planSections`, `normalizeSections`, `BlockLike`, `SectionPlan` (`kind:'keep'|'wrap'`), `SECTION_CLASS='starter-section'`, attributes `{tagName:'section',className:'starter-section',layout:{type:'default'}}`, and the theme selector `section.starter-section` are identical across Tasks 3, 4, 7, 8 and match the spec.
