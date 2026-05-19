# Mega Menu "Add column" Button Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** `starter/mega-menu` defaults to one column and exposes an explicit, always-visible "Add column" button instead of the undiscoverable native ⊕ appender.

**Architecture:** Editor-only change in `mega-menu/edit.tsx`: `TEMPLATE` 3→1 column, and `useInnerBlocksProps`' `renderAppender` returns a `@wordpress/components` `Button` that calls `insertBlock(createBlock('starter/mega-column'), undefined, clientId)` (public `@wordpress/data` + `@wordpress/blocks` APIs). Small editor-only `editor.scss` rule positions the button. No front-end / `render.php` / `block.json` changes.

**Tech Stack:** WordPress block theme (FSE, WP 6.5), `@wordpress/scripts` 28, `@wordpress/block-editor`/`data`/`blocks`/`components`, Playwright.

---

## Constraints (read before starting)

- `npm run build` runs locally (no WordPress) — works in a worktree; it emits `build/blocks/mega-menu/{index.js,index.css,style-index.css}`.
- The editor Playwright spec runs **post-merge against the :8890 child-theme env** (serves the main checkout). Do NOT `wp-env start` here. In a worktree the gates are `npm run build` + `lint:js`/`lint:blocks`/`lint:colors` + JSON validity; the e2e spec and any regression suites run after merge to `development`.
- `render.php`, front-end `style.scss`, `block.json`, `inc/`, PHPUnit, and `mega-column`/`mega-link` are NOT modified — front-end output and PHPUnit are regression-guarded by being untouched.
- No new dependencies. No schema/migration tasks.

## File Structure

- `src/blocks/mega-menu/edit.tsx` — TEMPLATE→1, `renderAppender` button, `clientId`/`insertBlock`/`createBlock` wiring. (Editor-only; sole behavioral file.)
- `src/blocks/mega-menu/editor.scss` — one rule positioning `.starter-mega-menu__add-col` (editor-only stylesheet; already exists).
- `tests/e2e/mega-menu-editor.spec.ts` — one appended test (fresh-insert → default 1 col → Add column → 2 cols).

---

### Task 1: One-column default + "Add column" button

**Files:**
- Modify: `src/blocks/mega-menu/edit.tsx`
- Modify: `src/blocks/mega-menu/editor.scss`

- [ ] **Step 1: Replace `src/blocks/mega-menu/edit.tsx` ENTIRELY with EXACTLY:**

```tsx
import { __ } from '@wordpress/i18n';
import {
	useBlockProps,
	useInnerBlocksProps,
	RichText,
	store as blockEditorStore,
} from '@wordpress/block-editor';
import { useDispatch } from '@wordpress/data';
import { createBlock } from '@wordpress/blocks';
import { Button } from '@wordpress/components';

const ALLOWED = [ 'starter/mega-column' ];
const TEMPLATE: [ string, Record< string, unknown >, unknown[] ][] = [
	[ 'starter/mega-column', {}, [] ],
];

type Attrs = { label: string };

export default function Edit( {
	attributes,
	setAttributes,
	clientId,
}: {
	attributes: Attrs;
	setAttributes: ( a: Partial< Attrs > ) => void;
	clientId: string;
} ) {
	const blockProps = useBlockProps( { className: 'starter-mega-menu' } );
	const { insertBlock } = useDispatch( blockEditorStore );
	const addColumn = () =>
		insertBlock(
			createBlock( 'starter/mega-column' ),
			undefined,
			clientId
		);
	const innerBlocksProps = useInnerBlocksProps(
		{ className: 'starter-mega-menu__panel' },
		{
			allowedBlocks: ALLOWED,
			template: TEMPLATE,
			templateLock: false,
			renderAppender: () => (
				<Button
					variant="secondary"
					icon="plus"
					className="starter-mega-menu__add-col"
					onClick={ addColumn }
				>
					{ __( 'Add column', 'starter' ) }
				</Button>
			),
		}
	);
	return (
		<div { ...blockProps }>
			<RichText
				tagName="span"
				className="starter-mega-menu__trigger"
				value={ attributes.label }
				onChange={ ( v ) => setAttributes( { label: v } ) }
				placeholder={ __( 'Menu label…', 'starter' ) }
			/>
			<div { ...innerBlocksProps } />
		</div>
	);
}
```

What changed vs current: `TEMPLATE` is now a single column; added `useDispatch`/`createBlock`/`Button`/`blockEditorStore` imports; `clientId` prop typed and used; `renderAppender` returns the labeled "Add column" button calling `addColumn`.

- [ ] **Step 2: Append to `src/blocks/mega-menu/editor.scss`**

The file currently ends with the `.starter-mega-menu__trigger { … }` rule. Append at the END of the file EXACTLY:

```scss
.starter-mega-menu__add-col {
  grid-column: 1 / -1;
  justify-self: start;
  margin-block-start: var(--wp--preset--spacing--20);
}
```

(Editor-only stylesheet; tokens / non-color values only — palette-only policy.)

- [ ] **Step 3: Build + verify the change compiled**

Run: `npm run build`
Expected: `webpack … compiled successfully`. Then:
`grep -c 'Add column' build/blocks/mega-menu/index.js` → expected `1` (the label string bundled).
`grep -c 'starter-mega-menu__add-col' build/blocks/mega-menu/index.css` → expected `1` (the editor rule compiled).

- [ ] **Step 4: Lint gates**

Run: `npm run lint:js`
Expected: ZERO errors from `src/blocks/mega-menu/edit.tsx`. If prettier reflows it, accept the format-only autofix and rebuild; logic/imports/JSX must stay identical to Step 1. (Pre-existing errors in other files are out of scope.)
Run: `npm run lint:blocks && npm run lint:colors`
Expected: `src/blocks/mega-menu/` passes; zero mega-menu color-literal violations.

- [ ] **Step 5: Commit**

```bash
git add src/blocks/mega-menu/edit.tsx src/blocks/mega-menu/editor.scss
git commit -m "feat(theme): mega-menu defaults to 1 column + Add column button"
```

Run `git show --stat HEAD` — confirm ONLY those 2 files (no `build/`, no `node_modules`).

---

### Task 2: Editor e2e — default 1 column, Add column adds a second

**Files:**
- Modify: `tests/e2e/mega-menu-editor.spec.ts`

- [ ] **Step 1: Append this test inside the existing `test.describe( 'mega menu editor', … )` block** in `tests/e2e/mega-menu-editor.spec.ts`, immediately before the describe's closing `} );` (keep the existing tests and the `login`/`openMegaDemoInEditor`/`openSettingsSidebar` helpers unchanged):

```ts
	test( 'a fresh mega-menu has one column and an Add column button', async ( {
		page,
	} ) => {
		await login( page );
		await page.goto( '/wp-admin/post-new.php?post_type=page' );
		// Dismiss the welcome guide if present.
		await page
			.getByRole( 'button', { name: 'Close', exact: true } )
			.click()
			.catch( () => {} );

		const canvas = page.frameLocator( 'iframe[name="editor-canvas"]' );
		// Insert the Mega Menu block via the inserter.
		await page
			.getByRole( 'button', { name: 'Toggle block inserter' } )
			.click();
		await page
			.getByRole( 'searchbox', { name: 'Search' } )
			.fill( 'Mega Menu' );
		await page
			.getByRole( 'option', { name: 'Mega Menu', exact: true } )
			.click();

		const columns = canvas.locator( '.starter-mega-column' );
		await expect( columns ).toHaveCount( 1 );

		const addBtn = canvas.getByRole( 'button', {
			name: 'Add column',
		} );
		await expect( addBtn ).toBeVisible();
		await addBtn.click();
		await expect( columns ).toHaveCount( 2 );
	} );
```

- [ ] **Step 2: Confirm helper reuse (in-worktree)**

Run: `grep -n "async function login\|import { login }\|openMegaDemoInEditor" tests/e2e/mega-menu-editor.spec.ts`
Expected: the file imports `login` from `./utils` (added in the prior sub-project) and the new test reuses it; no duplicate `login` defined. If the spec currently defines `login` locally instead of importing it, leave that as-is and the new test still calls `login( page )` — it resolves to whichever `login` the file already uses. Do NOT add a second `login`.

- [ ] **Step 3: Read back**

Read `tests/e2e/mega-menu-editor.spec.ts`; confirm the new test is inside the single `test.describe`, braces balanced, existing tests unchanged, no truncation.

- [ ] **Step 4: Commit**

```bash
git add tests/e2e/mega-menu-editor.spec.ts
git commit -m "test(e2e): mega-menu default 1 column + Add column button"
```

Run `git show --stat HEAD` — confirm ONLY `tests/e2e/mega-menu-editor.spec.ts`.

---

### Task 3: Verification

**Files:** none (verification only)

- [ ] **Step 1: Build + static gates (in-worktree)**

Run: `npm run build && npm run lint:js && npm run lint:blocks && npm run lint:colors`
Expected: build OK; `build/blocks/mega-menu/index.css` contains `starter-mega-menu__add-col`; zero new lint:js errors from `mega-menu/edit.tsx`; lint:blocks pass; zero mega-menu color violations.

- [ ] **Step 2: Scope confirm (in-worktree)**

Run: `git diff --name-only <merge-base>..HEAD`
Expected: ONLY `src/blocks/mega-menu/edit.tsx`, `src/blocks/mega-menu/editor.scss`, `tests/e2e/mega-menu-editor.spec.ts` (+ this plan/spec docs). NO `render.php`, `block.json`, `style.scss`, `inc/`, `tests/phpunit/`, `mega-column`, `mega-link`.

- [ ] **Step 3: Post-merge verification (against :8890; NOT in worktree)**

Run (post-merge on `development`):
`PLAYWRIGHT_BASE_URL=http://localhost:8890 npx playwright test tests/e2e/mega-menu-editor.spec.ts`
Expected: all tests pass, including the new "fresh mega-menu has one column and an Add column button".
Run: `PLAYWRIGHT_BASE_URL=http://localhost:8890 npx playwright test tests/e2e/mega-menu.spec.ts`
Expected: 4/4 — unchanged (front-end untouched).
Run: `cd ~/Entwicklung/wp-starter-child-theme && npx wp-env run tests-wordpress --env-cwd=wp-content/themes/wp-starter-theme vendor/bin/phpunit`
Expected: OK, 0 failures — unchanged (no PHP touched).

- [ ] **Step 4: Commit any fixups**

If Steps 1–3 needed fixes, commit them:

```bash
git add -A
git commit -m "fix(theme): mega-menu Add column verification follow-ups"
```

(If nothing needed fixing, no commit.)

---

## Self-Review

**Spec coverage:**
- Default 1 column → Task 1 Step 1 (`TEMPLATE` single entry). ✓
- Explicit labeled "Add column" button replacing native ⊕ → Task 1 Step 1 (`renderAppender` `Button`). ✓
- Public APIs only (`useDispatch`/`createBlock`/block-editor store) → Task 1 Step 1; no `__experimental`/`__unstable`. ✓
- Editor-only positioning rule, tokens-only → Task 1 Step 2 + lint:colors gate. ✓
- No front-end/`render.php`/`block.json`/`inc`/`mega-column`/`mega-link` changes → not in any task; Task 3 Step 2 asserts. ✓
- e2e: fresh-insert → default 1 col → Add column → 2 cols (not against the pre-built fixture) → Task 2 Step 1 (inserts a fresh Mega Menu in a new draft). ✓
- Regression (front-end 4/4, PHPUnit) guarded by being untouched → Task 3 Step 3. ✓

**Placeholder scan:** every code/markup/test step has complete content; commands have expected output. No TBD/TODO. ✓

**Type/name consistency:** `Attrs = { label: string }` unchanged from current block.json `label` attribute; added `clientId: string` to the typed `Edit` props (WordPress passes it to block `edit`). Class `starter-mega-menu__add-col` is identical in edit.tsx (`className`), editor.scss (selector), and the build grep. `addColumn`/`insertBlock`/`createBlock('starter/mega-column')` consistent; `ALLOWED`/`TEMPLATE` reference `starter/mega-column` consistently. ✓

## Notes / accepted limitations

- A mega-menu with every column deleted renders an empty panel front-end (unchanged; `render.php` already guards empty content).
- Editor remains an approximation (no hover/Interactivity) — unchanged, expected.
