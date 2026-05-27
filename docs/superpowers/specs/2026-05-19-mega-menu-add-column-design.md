# Mega Menu — "Add column" button & single-column default — Design

**Date:** 2026-05-19
**Status:** Approved
**Scope:** Small editor-UX change to `starter/mega-menu`: default to **one**
column instead of three, and replace the undiscoverable native InnerBlocks
appender (⊕) with an explicit, always-visible **"Add column"** button.

## Context

`src/blocks/mega-menu/edit.tsx` seeds three `starter/mega-column` blocks via
its InnerBlocks `TEMPLATE`. Adding/removing columns relies on the native
WordPress block appender, which only appears when the mega-menu block itself is
selected and renders as a small unlabeled ⊕ tucked after the last column in the
panel grid — users can't find it (reported). The mega-menu uses
`save: () => <InnerBlocks.Content />` (dynamic, `render.php`), so column count
is purely author-driven and has no front-end logic impact.

## Decision (from brainstorming)

| Topic | Decision |
|-------|----------|
| Default columns | **1** (a usable starting point; not 0/empty, not 3) |
| Add affordance | Explicit labeled **"Add column"** `Button` replacing the native ⊕ |
| Approach | A1 — `renderAppender` + `insertBlock(createBlock(...))` (public APIs) |

## Architecture (Approach A1)

Single behavioral file: **`src/blocks/mega-menu/edit.tsx`**.

- `TEMPLATE` → exactly one `[ 'starter/mega-column', {}, [] ]`.
- Read `clientId` from the block `Edit` props.
- `const { insertBlock } = useDispatch( blockEditorStore )` (from
  `@wordpress/data` + the `@wordpress/block-editor` store).
- `addColumn = () => insertBlock( createBlock( 'starter/mega-column' ),
  undefined, clientId )` (`createBlock` from `@wordpress/blocks`;
  `undefined` index appends).
- `useInnerBlocksProps( …, { allowedBlocks: ALLOWED, template: TEMPLATE,
  templateLock: false, renderAppender: () => (
  <Button variant="secondary" icon="plus"
  className="starter-mega-menu__add-col" onClick={ addColumn }>
  { __( 'Add column', 'starter' ) }</Button> ) } )` — `Button` from
  `@wordpress/components`.

No experimental/`__unstable`/`__experimental` APIs.

**`src/blocks/mega-menu/editor.scss`** (editor-only, already exists): add a
small rule so the appender button sits clearly below the columns:
`.starter-mega-menu__add-col { grid-column: 1 / -1; justify-self: start;
margin-block-start: var(--wp--preset--spacing--20); }`. Tokens / non-color
values only (palette-only policy; `lint:colors`).

**Unchanged:** `render.php`, front-end `style.scss`, `block.json`
(`save: () => <InnerBlocks.Content />` already supports any column count — one
default column vs three is purely an editor template change with no front-end
logic difference). `mega-column` and `mega-link` untouched. `inc/`, PHPUnit
untouched.

## Testing

Extend `tests/e2e/mega-menu-editor.spec.ts` (the existing editor spec) with one
test: open `/mega-demo/`-style fixture in the editor and assert (a) exactly
**1** `.starter-mega-column` by default for a freshly-inserted mega-menu,
(b) the **"Add column"** button is visible, (c) clicking it produces **2**
`.starter-mega-column`. Runs deferred post-merge vs :8890 (same env-constraint
pattern as prior sub-projects). PHPUnit and the front-end `mega-menu.spec.ts`
are unaffected (no PHP/front-end touched) — regression-guarded by being
untouched.

Note: the existing `/mega-demo` fixture page contains an *already-built*
mega-menu (multiple columns); the new test must insert a **fresh**
`starter/mega-menu` (e.g. via the inserter into a scratch context) to assert
the *default* is one column — asserting against the pre-built fixture would not
test the default.

## Files touched

- `src/blocks/mega-menu/edit.tsx` — TEMPLATE 3→1, `renderAppender` button,
  `insertBlock`/`createBlock`/`clientId`.
- `src/blocks/mega-menu/editor.scss` — `.starter-mega-menu__add-col` styling.
- `tests/e2e/mega-menu-editor.spec.ts` — default-1-column + add-button test.

## Out of scope (YAGNI)

- No maximum-column cap / no min-column enforcement.
- No custom column reordering/removal UI beyond native block controls.
- No change to `mega-column`'s own link appender or `TEMPLATE`.
- No front-end/`render.php`/`block.json`/`inc` changes; no new dependencies.

## Known limitations (accepted)

- A mega-menu with all columns deleted renders an empty panel on the front end
  (same as today; `render.php` already guards the empty-content case).
