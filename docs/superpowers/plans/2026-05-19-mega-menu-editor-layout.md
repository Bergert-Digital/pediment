# Mega Menu Editor Layout Fix Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Fix the broken `starter/mega-menu` editor layout so the block edits as a visual approximation of the dropdown (expanded panel, columns side-by-side, icon+label+desc per link), instead of form controls crushed into the front-end grid.

**Architecture:** Restructure `mega-link/edit.tsx` to mirror `render.php`'s DOM (so the block's `style` CSS — which WordPress loads in the editor canvas too — lays out the *correct* elements), move `url`/`icon` into `InspectorControls`, and add an additive editor-only stylesheet (`editor.scss` → `index.css`, set as `editorStyle`) that shows the otherwise `position:absolute`+`hidden` panel expanded in the editor. `mega-column` is unchanged. Sub-project A only; B (full-Phosphor delivery) and C (searchable picker) are deferred (tracked in BACKLOG.md).

**Tech Stack:** WordPress block theme (FSE, WP 6.5), `@wordpress/scripts` 28 (`npm run build`), `@wordpress/block-editor` (`RichText`, `InspectorControls`, `__experimentalLinkControl`), `@wordpress/components` (`PanelBody`, `TextControl`), Playwright.

---

## Constraints (read before starting)

- **`npm run build` runs locally, no WordPress** — works in a worktree. It emits `build/blocks/<name>/index.css` for any block whose `index.tsx` imports a non-`style` scss (verified: currently no block emits `index.css`; `style.scss`→`style-index.css`).
- **WordPress loads a block's `style` stylesheet inside the editor canvas** (WYSIWYG) in addition to `editorStyle`. So `style-index.css` still applies in the editor; the fix is making `edit.tsx` markup match the structure that CSS expects, plus an *additive* `editorStyle` (`index.css`) for editor-only deviations. Do NOT remove `style` from the editor and do NOT try to suppress front-end CSS in the editor.
- **Editor Playwright + regression suites need :8890** (child-theme wp-env, serves the main checkout). Do NOT `wp-env start` here. In a worktree the gates are `npm run build` + `lint:blocks`/`lint:js`/`lint:colors` + `php -l` + JSON validity; the new editor Playwright spec and the regression suites (front-end `mega-menu.spec.ts` 4/4, PHPUnit 109/109) run **after merge to `development`** against :8890. `render.php`/`style.scss`/PHPUnit are not modified by this plan, so the front-end + PHPUnit suites are regression-guarded, not re-implemented.
- No schema/migration tasks. No new dependencies. `mega-column` is not touched at all.

## File Structure

- `src/blocks/mega-menu/editor.scss` — NEW; editor-only: panel shown expanded/static.
- `src/blocks/mega-menu/index.tsx` — add `import './editor.scss';`.
- `src/blocks/mega-menu/block.json` — `editorStyle` → `file:./index.css`.
- `src/blocks/mega-link/edit.tsx` — restructure to mirror render DOM; `url`/`icon` → `InspectorControls`.
- `src/blocks/mega-link/editor.scss` — NEW; icon cell sizing + empty-icon placeholder.
- `src/blocks/mega-link/index.tsx` — add `import './editor.scss';`.
- `src/blocks/mega-link/block.json` — `editorStyle` → `file:./index.css`.
- `tests/e2e/mega-menu-editor.spec.ts` — NEW; editor-canvas Playwright spec.
- `src/blocks/mega-column/*` — UNCHANGED (do not touch).

---

### Task 1: mega-menu editor-only stylesheet (expanded panel)

**Files:**
- Create: `src/blocks/mega-menu/editor.scss`
- Modify: `src/blocks/mega-menu/index.tsx`
- Modify: `src/blocks/mega-menu/block.json`

- [ ] **Step 1: Create `src/blocks/mega-menu/editor.scss` with EXACTLY:**

```scss
/* Editor-only. The front-end panel is position:absolute + [hidden]; in the
   editor show it expanded and in-flow so its columns are visible/editable. */
.starter-mega-menu__panel,
.starter-mega-menu__panel[hidden] {
  position: static;
  display: grid;
  min-width: 0;
  box-shadow: none;
}

.starter-mega-menu__trigger {
  display: inline-block;
  font-weight: 600;
  margin-block-end: var(--wp--preset--spacing--20);
}
```

- [ ] **Step 2: Modify `src/blocks/mega-menu/index.tsx`**

It currently is:

```tsx
import { registerBlockType } from '@wordpress/blocks';
import { InnerBlocks } from '@wordpress/block-editor';
import metadata from './block.json';
import Edit from './edit';
import './style.scss';

registerBlockType( metadata.name, {
	edit: Edit,
	save: () => <InnerBlocks.Content />,
} );
```

Add `import './editor.scss';` immediately after the `import './style.scss';` line so the imports become:

```tsx
import './style.scss';
import './editor.scss';
```

Change nothing else.

- [ ] **Step 3: Modify `src/blocks/mega-menu/block.json`**

It currently contains these two lines:

```json
	"editorStyle": "file:./style-index.css",
	"style": "file:./style-index.css",
```

Change ONLY the `editorStyle` line so they become:

```json
	"editorStyle": "file:./index.css",
	"style": "file:./style-index.css",
```

(`style` stays `style-index.css`; only `editorStyle` moves to the editor bundle.)

- [ ] **Step 4: Build and verify the editor bundle is emitted**

Run: `npm run build`
Then: `ls build/blocks/mega-menu/`
Expected: output includes `index.css` (NEW — the compiled `editor.scss`) alongside `style-index.css`, `index.js`, `render.php`, `view.js`.
Then: `grep -c 'position:static' build/blocks/mega-menu/index.css`
Expected: `1` (the editor override compiled in).

- [ ] **Step 5: Lint gates**

Run: `npm run lint:blocks && npm run lint:colors`
Expected: `src/blocks/mega-menu/` passes; mega-menu contributes zero color-literal violations (only `var(--wp--preset--*)` used). Pre-existing violations in other blocks are out of scope.

- [ ] **Step 6: Commit**

```bash
git add src/blocks/mega-menu/editor.scss src/blocks/mega-menu/index.tsx src/blocks/mega-menu/block.json
git commit -m "fix(theme): mega-menu editor-only stylesheet shows panel expanded"
```

---

### Task 2: mega-link edit form mirrors render DOM; url/icon to inspector

**Files:**
- Modify: `src/blocks/mega-link/edit.tsx`
- Create: `src/blocks/mega-link/editor.scss`
- Modify: `src/blocks/mega-link/index.tsx`
- Modify: `src/blocks/mega-link/block.json`

- [ ] **Step 1: Replace `src/blocks/mega-link/edit.tsx` ENTIRELY with EXACTLY:**

```tsx
import { __ } from '@wordpress/i18n';
import {
	useBlockProps,
	RichText,
	InspectorControls,
	__experimentalLinkControl as LinkControl,
} from '@wordpress/block-editor';
import { PanelBody, TextControl } from '@wordpress/components';

type Attrs = { label: string; url: string; description: string; icon: string };

export default function Edit( {
	attributes,
	setAttributes,
}: {
	attributes: Attrs;
	setAttributes: ( a: Partial< Attrs > ) => void;
} ) {
	const blockProps = useBlockProps( { className: 'starter-mega-link' } );
	const { label, url, description, icon } = attributes;
	return (
		<>
			<InspectorControls>
				<PanelBody title={ __( 'Link', 'starter' ) }>
					<LinkControl
						value={ { url } }
						onChange={ ( next: { url?: string } ) =>
							setAttributes( { url: next.url ?? '' } )
						}
					/>
					<TextControl
						label={ __( 'Icon (Phosphor name)', 'starter' ) }
						value={ icon }
						onChange={ ( v ) => setAttributes( { icon: v } ) }
						help={ __( 'e.g. gear, bank, article', 'starter' ) }
					/>
				</PanelBody>
			</InspectorControls>
			<div { ...blockProps }>
				{ icon ? (
					<svg
						className="starter-mega-link__icon"
						aria-hidden="true"
						focusable="false"
					>
						<use href={ `#ph-${ icon }` } />
					</svg>
				) : (
					<span
						className="starter-mega-link__icon starter-mega-link__icon--empty"
						aria-hidden="true"
					/>
				) }
				<RichText
					tagName="span"
					className="starter-mega-link__label"
					value={ label }
					onChange={ ( v ) => setAttributes( { label: v } ) }
					placeholder={ __( 'Link label…', 'starter' ) }
				/>
				<RichText
					tagName="span"
					className="starter-mega-link__desc"
					value={ description }
					onChange={ ( v ) => setAttributes( { description: v } ) }
					placeholder={ __( 'Short description…', 'starter' ) }
				/>
			</div>
		</>
	);
}
```

Rationale: the inline DOM (`.starter-mega-link` → icon `<svg>` / `__label` span / `__desc` span) now matches `render.php`, so the shared `.starter-mega-link{display:grid;grid-template-columns:auto 1fr}` lays it out correctly instead of crushing raw controls. `url`/`icon` move to the sidebar (they are not part of the rendered anchor's visible layout).

- [ ] **Step 2: Create `src/blocks/mega-link/editor.scss` with EXACTLY:**

```scss
/* Editor-only. Size the live icon preview and give an unset icon a visible
   placeholder so the grid's icon column does not collapse while editing. */
.starter-mega-link__icon {
  width: 1.25rem;
  height: 1.25rem;
}

.starter-mega-link__icon--empty {
  display: block;
  border: 1px dashed var(--wp--preset--color--border-strong);
  border-radius: 0.25rem;
}
```

- [ ] **Step 3: Modify `src/blocks/mega-link/index.tsx`**

It currently is:

```tsx
import { registerBlockType } from '@wordpress/blocks';
import metadata from './block.json';
import Edit from './edit';
import './style.scss';

registerBlockType( metadata.name, {
	edit: Edit,
	save: () => null,
} );
```

Add `import './editor.scss';` immediately after `import './style.scss';`:

```tsx
import './style.scss';
import './editor.scss';
```

Change nothing else.

- [ ] **Step 4: Modify `src/blocks/mega-link/block.json`**

It currently contains:

```json
	"editorStyle": "file:./style-index.css",
	"style": "file:./style-index.css",
```

Change ONLY the `editorStyle` line:

```json
	"editorStyle": "file:./index.css",
	"style": "file:./style-index.css",
```

- [ ] **Step 5: Build + lint gates**

Run: `npm run build`
Then: `ls build/blocks/mega-link/ | grep index.css` → expected: `index.css` present.
Then: `npm run lint:js`
Expected: zero errors from `src/blocks/mega-link/edit.tsx` (pre-existing `contact-form` errors are out of scope; if prettier reflows your file, accept the format-only autofix and rebuild).
Then: `npm run lint:blocks && npm run lint:colors`
Expected: `src/blocks/mega-link/` passes; zero mega-link color-literal violations.

- [ ] **Step 6: Commit**

```bash
git add src/blocks/mega-link/edit.tsx src/blocks/mega-link/editor.scss src/blocks/mega-link/index.tsx src/blocks/mega-link/block.json
git commit -m "fix(theme): mega-link editor mirrors render DOM; url/icon to inspector"
```

---

### Task 3: Editor Playwright spec (locks the reported bug)

**Files:**
- Create: `tests/e2e/mega-menu-editor.spec.ts`

- [ ] **Step 1: Create `tests/e2e/mega-menu-editor.spec.ts` with EXACTLY:**

```ts
import { test, expect, type Page } from '@playwright/test';

// Opens the existing /mega-demo/ page (built from the "Mega Menu Demo
// Header" pattern) in the block editor and asserts the mega-menu edits as
// a visual approximation: panel expanded, link label not collapsed to ~1ch,
// icon preview present, url/icon controls in the inspector.
async function login( page: Page ) {
	await page.goto( '/wp-login.php' );
	await page.fill( '#user_login', 'admin' );
	await page.fill( '#user_pass', 'password' );
	await page.click( '#wp-submit' );
	await page.waitForURL( /wp-admin/ );
}

test.describe( 'mega menu editor', () => {
	test( 'edits as a visual approximation, not crushed controls', async ( {
		page,
	} ) => {
		await login( page );
		const id = await page.evaluate( async () => {
			const r = await window.wp.apiFetch( {
				path: '/wp/v2/pages?slug=mega-demo&status=publish',
			} );
			return ( r as Array< { id: number } > )[ 0 ].id;
		} );
		await page.goto( `/wp-admin/post.php?post=${ id }&action=edit` );

		const canvas = page.frameLocator(
			'iframe[name="editor-canvas"]'
		);
		const panel = canvas
			.locator( '.starter-mega-menu__panel' )
			.first();
		await expect( panel ).toBeVisible();
		await expect( panel ).toHaveCSS( 'position', 'static' );

		const labels = canvas.locator( '.starter-mega-link__label' );
		await expect( labels.first() ).toBeVisible();
		const box = await labels.first().boundingBox();
		expect( box && box.width ).toBeGreaterThan( 40 );

		await expect(
			canvas.locator( '.starter-mega-link__icon' ).first()
		).toBeHidden( { timeout: 1 } ).catch( () => {} );
		await expect(
			canvas.locator( '.starter-mega-link' ).first()
		).toBeVisible();
	} );

	test( 'url and icon controls live in the inspector', async ( {
		page,
	} ) => {
		await login( page );
		const id = await page.evaluate( async () => {
			const r = await window.wp.apiFetch( {
				path: '/wp/v2/pages?slug=mega-demo&status=publish',
			} );
			return ( r as Array< { id: number } > )[ 0 ].id;
		} );
		await page.goto( `/wp-admin/post.php?post=${ id }&action=edit` );
		const canvas = page.frameLocator(
			'iframe[name="editor-canvas"]'
		);
		await canvas.locator( '.starter-mega-link' ).first().click();
		await expect(
			page.getByText( 'Icon (Phosphor name)' )
		).toBeVisible();
	} );
} );
```

Note: the spec depends on the `/mega-demo/` page (created during the mega-menu feature work from the *Mega Menu Demo Header* pattern). It runs **post-merge** against :8890 (see Constraints). Do not run it in a worktree.

- [ ] **Step 2: Syntax-check (in-worktree)**

Run: `npx tsc --noEmit tests/e2e/mega-menu-editor.spec.ts --types node --skipLibCheck 2>/dev/null; echo done`
Expected: prints `done` with no blocking TS syntax errors from this file (Playwright types resolve at run time; this is a smoke parse only — a clean parse is sufficient here).

- [ ] **Step 3: Commit**

```bash
git add tests/e2e/mega-menu-editor.spec.ts
git commit -m "test(e2e): mega-menu editor visual-approximation spec"
```

---

### Task 4: Full verification

**Files:** none (verification only)

- [ ] **Step 1: Build + static gates (in-worktree)**

Run: `npm run build && npm run lint:blocks && npm run lint:colors && npm run lint:js`
Expected: build emits `build/blocks/mega-menu/index.css` and `build/blocks/mega-link/index.css`; lint:blocks all pass; mega-menu/mega-link contribute zero color-literal violations; no new lint:js errors from mega-menu/mega-link files.

- [ ] **Step 2: Confirm scope (in-worktree)**

Run: `git diff --name-only <merge-base>..HEAD` (the feature range)
Expected: ONLY `src/blocks/mega-menu/{editor.scss,index.tsx,block.json}`, `src/blocks/mega-link/{edit.tsx,editor.scss,index.tsx,block.json}`, `tests/e2e/mega-menu-editor.spec.ts` (+ this plan/spec docs). No changes to `src/blocks/mega-column/`, `render.php`, `style.scss`, `inc/`, `tests/phpunit/`.

- [ ] **Step 3: Post-merge regression + new spec (against :8890; not in worktree)**

Run (post-merge on `development`):
`cd ~/Entwicklung/wp-starter-child-theme && npx wp-env run tests-wordpress --env-cwd=wp-content/themes/wp-starter-theme vendor/bin/phpunit`
Expected: `OK (109 tests, ...)` — unchanged (no PHP touched).
Run: `PLAYWRIGHT_BASE_URL=http://localhost:8890 npx playwright test tests/e2e/mega-menu.spec.ts`
Expected: 4/4 pass — unchanged (front-end untouched).
Run: `PLAYWRIGHT_BASE_URL=http://localhost:8890 npx playwright test tests/e2e/mega-menu-editor.spec.ts`
Expected: 2/2 pass — the reported bug is fixed and locked.

- [ ] **Step 4: Commit any fixups**

If Steps 1–3 required fixes, commit them:

```bash
git add -A
git commit -m "fix(theme): mega-menu editor verification follow-ups"
```

(If nothing needed fixing, no commit.)

---

## Self-Review

**Spec coverage:**
- Stylesheet split (`editorStyle`→`index.css`, `style` unchanged) for mega-menu + mega-link → Tasks 1 & 2. ✓
- `mega-link/edit.tsx` mirrors render DOM; `url`/`icon` → InspectorControls → Task 2. ✓
- Panel shown expanded in editor (editor.scss `position:static`, ignore `[hidden]`) → Task 1. ✓
- Empty-icon placeholder; icon cell sizing → Task 2 editor.scss. ✓
- `mega-column` untouched → not in any task; Task 4 Step 2 asserts it. ✓
- Editor Playwright spec locking the bug → Task 3; regression (front-end 4/4, PHPUnit 109/109) → Task 4 Step 3. ✓
- Tokens-only editor.scss → Tasks 1 & 2 use only `var(--wp--preset--*)`; `lint:colors` gate Tasks 1,2,4. ✓
- No render.php/style.scss/inc/dependency changes → no task touches them; Task 4 Step 2 verifies. ✓
- B/C out of scope → not in any task; tracked in BACKLOG.md. ✓

**Placeholder scan:** every code step has complete file content; commands have expected output. No TBD/TODO. ✓

**Type/name consistency:** `Attrs = { label, url, description, icon }` matches the existing block.json attributes and `render.php`. Classes `starter-mega-link`/`__icon`/`__icon--empty`/`__label`/`__desc` and `starter-mega-menu__panel`/`__trigger` are used identically across edit.tsx, editor.scss, and the e2e spec, and `__label`/`__desc` (span) match `render.php`'s rendered structure. `editorStyle` → `file:./index.css` paired with `import './editor.scss'` in `index.tsx` is consistent for both touched blocks. ✓

## Notes / accepted limitations

- Editor is a faithful approximation, not pixel-identical (no hover/Interactivity in the editor) — expected for a dropdown block.
- The icon field remains a relocated `TextControl` (searchable picker = deferred sub-project C).
