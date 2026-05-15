# Generator-Emitted Section Groups — Design

**Date:** 2026-05-15
**Status:** Awaiting user review
**Related:** supersedes the theme-only separator approach (`2026-05-15-section-rhythm-v2-design.md`, shipped as commit `2d5003c`, to be reverted here)

## Problem

AI-generated pages have no section structure: a flat top-level block stream
(`hero, stat, stat, stat, separator, heading, prose, separator, …`). Pure-CSS
attempts to infer sections from sibling adjacency are unreliable (two reverted
cycles) because the same adjacency means different things. The robust fix is
structural: each section is a real container (`core/group`), produced by the
generator, so the theme spaces real boundaries instead of guessing.

## Decisions (from re-brainstorming, user-confirmed)

1. **Fix layer:** the generator (wp-starter-ai), not the theme heuristics.
2. **Reliability:** prompt guidance **plus** a deterministic normalizer. The
   prompt is a nudge; the normalizer is the guarantee (do not trust unenforced
   LLM behavior — the lesson from the reverted cycles).
3. **Boundary signal:** `core/separator`, **consumed**. Each separator-delimited
   run of ungrouped top-level blocks becomes one section group; separators are
   removed (the group *is* the boundary). Already-grouped blocks pass through.
4. **Section markup:** `core/group` with `tagName=section`,
   `className=starter-section`, **flow** layout (full-width; section manages no
   inner width constraint itself).
5. **Separator CSS:** the shipped `.wp-block-separator` margin rule
   (commit `2d5003c`) is **removed** in this work.
6. **Scope:** applies at generation/edit-turn time via the editor. Existing
   saved pages are **not** migrated; with the separator rule removed they will
   look cramped and show rules until regenerated. Accepted.

## Pipeline facts (verified)

- wp-starter-ai never writes `post_content`. WordPress core saves whatever is
  in the Gutenberg editor when the user clicks Save/Publish.
- The server `VirtualTree` (`TurnRunner`) is ephemeral reasoning state; never
  serialized to a post. `Serializer::serialize` is test-only.
- The model's `tool_calls` are returned by `/starter-ai/v1/chat/*` and applied
  client-side in `editor/applyToolCalls.ts` (`pollTurn.ts` →
  `applyToolCalls`). Blocks land in the editor; there is no post-apply
  processing of the top-level list today.
- The full stack (`Tools` schema `innerBlocks`, `VirtualTree`, `Serializer`,
  `createBlockFromSpec`) already supports arbitrarily nested blocks. Serializing
  a `core/group` with children needs no infra change.
- `core/group` is NOT in `SchemaBuilder::CORE_ALLOWLIST`; the system prompt has
  no layout/section guidance.

**Consequence:** the deterministic normalizer MUST run in
`editor/applyToolCalls.ts` after the apply loop and before the function
returns — the only point that shapes the final editor state before the user
sees or saves it. Server-side is too early (ephemeral); a save hook is too
late (user already saw a different structure; chat history would mismatch).

## Components

### A. wp-starter-ai — schema

`src/Anthropic/SchemaBuilder.php`: add to `CORE_ALLOWLIST`:

```php
'core/group' => [
    'description'       => 'A section container. Wrap each page section in one.',
    'attributes'        => [
        'tagName'   => [ 'type' => 'string', 'default' => 'section' ],
        'className' => [ 'type' => 'string' ],
    ],
    'allowsInnerBlocks' => true,
],
```

### B. wp-starter-ai — prompt

`src/Chat/PromptBuilder.php::systemPrompt()`: append guidance (before the
`apply_filters('starter_ai_system_prompt', …)`), e.g.:

> "Compose a page as a sequence of distinct sections. Wrap each section's
> blocks in a `core/group` with `tagName: "section"` and
> `className: "starter-section"`. Do not emit a flat list of top-level
> paragraphs/headings; group them into their section. If you do not group,
> place a `core/separator` between sections."

This is a nudge only; correctness is guaranteed by C.

### C. wp-starter-ai — deterministic normalizer

New function in `editor/applyToolCalls.ts`, invoked after the apply loop,
before `applyToolCalls` returns. Operates on the `core/block-editor` root
block order.

Algorithm:

1. Read root top-level blocks in order.
2. Partition into segments split on `core/separator` blocks.
3. For each segment:
   - empty (leading/trailing/consecutive separators) → skip.
   - exactly one block that is a `core/group` whose `className` includes
     `starter-section` → keep as-is (idempotent).
   - otherwise → create `core/group` with attributes
     `{ tagName:'section', className:'starter-section', layout:{type:'default'} }`
     and the segment's blocks as `innerBlocks`.
4. Remove all top-level `core/separator` blocks.
5. Replace root order with the resulting section groups (via
   `core/block-editor` dispatch — `createBlock('core/group', attrs, inner)` +
   `replaceBlocks`/`insertBlocks` + `removeBlocks`, consistent with existing
   `createBlockFromSpec`).

Properties: deterministic (depends only on the block list), idempotent
(re-running / edit-turns on already-grouped pages are no-ops, no nested
double-wrap), model-cooperative (correct groups preserved).

Non-goal: nested section detection, heading-based splitting, migrating
existing posts.

### D. wp-starter-theme — CSS

`theme.json` › `styles.css`:

1. Remove the `.wp-block-separator{… margin-block:clamp …}` rule; restore the
   `css` string to its pre-`2d5003c` value:
   `".wp-site-blocks{display:flex;flex-direction:column;min-height:100vh;min-height:100dvh}.wp-site-blocks > main{flex:1 0 auto}"`
2. Append:

```css
.entry-content.wp-block-post-content > section.starter-section + section.starter-section{margin-block-start:clamp(3.25rem, 2rem + 6vw, 6rem)}
section.starter-section{padding-inline:var(--wp--preset--spacing--30)}
section.starter-section > :where(:not(.alignfull):not(.alignwide)){max-width:var(--wp--style--global--content-size, 720px);margin-inline:auto}
```

- Section gap: airy clamp (validated value), only between consecutive
  sections; first flush, no trailing gap.
- Sections stay full-width (flow) so full-bleed backgrounds remain possible
  later; normal content centred at the 720px content width;
  `alignwide`/`alignfull` and self-constraining `starter/*` blocks unaffected.
- The inner-width `:where(...)` rule is the one nuance requiring real-page
  verification (some `starter/*` blocks already self-constrain — confirm no
  double-constrain / visual regression).

## Verification

**Automated:**
- PHP unit: `SchemaBuilder` output includes `core/group`,
  `allowsInnerBlocks === true`.
- TS unit for the normalizer (pure over a block-list fixture): separator runs
  → one `starter-section` group each; separators removed; existing correct
  groups untouched; no-separator body → single section; output re-run is a
  no-op (idempotent).
- Update affected mock-fixture / structure tests in wp-starter-ai.
- `npm run lint` (both repos) / PHP lint pass.

**Integration — real, not fixtures (verification discipline from prior
cycles):**
- In the child-theme env (`localhost:8890`), regenerate one page per archetype
  through the actual chat flow. Confirm saved `post_content`: top-level is all
  `<section class="… starter-section">`, zero `core/separator`, content nested
  correctly, idempotent across a follow-up edit-turn.
- Playwright computed-margin check on regenerated pages: ~6rem between sections
  desktop / ~3.25rem mobile; tight intra-section; normal text ≈720px wide;
  `starter/*` blocks not double-constrained; no visible horizontal rules.
- Confirm an un-regenerated legacy page is knowingly degraded (expected).

## Non-goals

- Migrating/normalizing existing saved pages.
- Section backgrounds, alternating tints, contained-vs-fullbleed controls
  (flow layout leaves this open for later).
- Heading-based or nested section inference.
- Changing the incremental `insert_block` tool contract / schema-enforced page
  structure.
- `starter/stat` band horizontal layout, `starter/prose` internal rhythm.

## Decomposition note

Two components in two repos but one coupled feature (theme CSS depends on the
`starter-section` class the normalizer emits). Implementation order: A→B→C
(wp-starter-ai produces grouped output), then D (theme spaces it), then
integration verification. The implementation plan may split this into two
sequenced plans (wp-starter-ai, then wp-starter-theme); call that at
writing-plans time.

**Release ordering constraint:** D removes the separator rule. If D ships
before A–C are effective and pages regenerated, every page (legacy and
"current") degrades simultaneously. D must not be merged/released until A–C
are in place and at least one archetype regenerated and verified.
