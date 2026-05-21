# Mega Menu Entity-Edit Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Move `starter/mega-menu` authoring into the `wp_navigation` entity (edited via Site Editor → Navigation). Render it read-only everywhere else. Unblocks the destroy-on-attr-change failure that fires when an uncontrolled `core/navigation` with inline children is mutated from the page editor.

**Architecture:** The block stays a `core/navigation` child (`block.json` `parent` and `inc/mega-menu.php` listable-blocks filter unchanged). `edit.tsx` detects whether the current document is the `wp_navigation` entity (`select(editorStore).getCurrentPostType() === 'wp_navigation'`). In that single context, the existing sidebar form runs. Everywhere else, `useBlockEditingMode('disabled')` neutralizes the block (no toolbar, no sidebar, no `setAttributes` path) — confirmed via context7 as the official Gutenberg API for this. The seeded `wp_navigation` entity ships with a sample mega-menu so users have a real artifact to edit.

**Tech Stack:** WordPress 6.5+, `@wordpress/block-editor` (`useBlockEditingMode`), `@wordpress/data` (`useSelect`, `core/editor`), PHP for seed extension, Playwright + PHPUnit.

**Worktree:** Plan-driven (8 tasks) — execute in a short-lived `.worktrees/mega-menu-entity-edit` off `development`, per the user-level worktree policy. No schema tasks; no migration lane to serialize.

**Test-execution constraint (from session memory):** `wp-env` mounts the main `wp-starter-child-theme` checkout (`:8890`), not worktrees. Inside the worktree: typecheck + JS build only. PHPUnit + Playwright run **after** merging the worktree back to `development`. Each task therefore writes the test code as part of its commit; actual test execution is gated in Task 7.

---

## File Structure

**Modify (production):**
- `src/blocks/mega-menu/edit.tsx` — add context detection + `useBlockEditingMode` gating
- `inc/nav-seed.php` — extend `starter_nav_menu_blocks()` to include the mega-menu
- `patterns/mega-menu-header.php` — drop the inline mega-menu; rely on entity binding

**Modify (tests):**
- `tests/e2e/utils.ts` — add `createNavigationEntityWithContent` / `deleteNavigationEntityById`
- `tests/e2e/mega-menu-editor.spec.ts` — full rewrite (3 tests)
- `tests/phpunit/NavSeed/SeedNavTest.php` — one new assertion

**Verify-only (must remain green, no changes):**
- `parts/header.html`, `src/blocks/mega-menu/{block.json,render.php,style.scss,view.ts}`, `inc/mega-menu.php`
- `tests/phpunit/BlockRender/MegaMenuTest.php`, `tests/phpunit/MegaMenu/ListableFilterTest.php`
- `tests/phpunit/Patterns/PatternsTest.php`, `tests/e2e/mega-menu.spec.ts`

---

## Task 1: Linchpin de-risk — clientId stability in `wp_navigation` entity context

**This task is a gate.** If the linchpin test FAILS, halt the plan and escalate — the design is invalidated and the fallback is the bespoke `starter/site-nav` custom block (out of scope here). Do not start Task 2 until Task 1 passes after merge to `development` (Task 7).

**Files:**
- Modify: `tests/e2e/utils.ts`
- Create: `tests/e2e/mega-menu-editor.spec.ts` (overwrites existing — back up the old content in the commit message body for reference)

- [ ] **Step 1: Add navigation-entity helpers to `tests/e2e/utils.ts`**

Append below `deletePageBySlug`:

```ts
/**
 * Create a `wp_navigation` entity via wp-cli with given content and a stable
 * slug for later lookup. Mirrors createPageWithContent in resolving the new
 * post ID by slug (wp-env's wp-cli wrapper decorates stdout, so --porcelain
 * last-line parsing is unsafe).
 */
export function createNavigationEntityWithContent(
  slug: string,
  title: string,
  content: string
): number {
  const escapedContent = content.replace(/'/g, "'\\''");
  const escapedTitle = title.replace(/'/g, "'\\''");
  execSync(
    `npx wp-env run cli wp post create --post_type=wp_navigation --post_status=publish --post_title='${escapedTitle}' --post_name='${slug}' --post_content='${escapedContent}'`,
    { stdio: 'ignore', cwd: WP_ENV_CWD }
  );
  const out = execSync(
    `bash -c "npx wp-env run cli wp post list --post_type=wp_navigation --name='${slug}' --field=ID --format=ids 2>/dev/null | grep -E '^[0-9]+$' | head -n 1"`,
    { encoding: 'utf8', cwd: WP_ENV_CWD }
  );
  const id = parseInt(out.trim(), 10);
  if (!id) {
    throw new Error(`Failed to create/resolve wp_navigation '${slug}'; output: ${out}`);
  }
  return id;
}

export function deleteNavigationEntityById(id: number): void {
  if (!id) return;
  try {
    execSync(
      `npx wp-env run cli wp post delete ${id} --force >/dev/null 2>&1`,
      { stdio: 'ignore', cwd: WP_ENV_CWD }
    );
  } catch {
    // ignore
  }
}
```

- [ ] **Step 2: Rewrite `tests/e2e/mega-menu-editor.spec.ts` with the linchpin probe**

Replace the entire file contents with:

```ts
import { test, expect } from '@playwright/test';
import {
  login,
  createNavigationEntityWithContent,
  deleteNavigationEntityById,
  createPageWithContent,
  deletePageBySlug,
} from './utils';

const NAV_CONTENT =
  '<!-- wp:starter/mega-menu {"label":"Products","columns":[{"heading":"Product","links":[{"label":"Pricing","url":"/pricing","description":"Plans","icon":"tag"}]}]} /-->' +
  '<!-- wp:navigation-link {"label":"About","url":"/about","kind":"custom"} /-->';

// Walk the block tree to find a starter/mega-menu (it lives inside a
// core/navigation; controlled inner-blocks mode keeps the tree shallow here).
const FIND_MEGA_FN = `(() => {
  const sel = window.wp.data.select('core/block-editor');
  const walk = (list) => {
    for (const b of list) {
      if (b.name === 'starter/mega-menu') return b;
      const hit = walk(b.innerBlocks || []);
      if (hit) return hit;
    }
    return null;
  };
  return walk(sel.getBlocks());
})()`;

test.describe('mega-menu editor (entity context)', () => {
  let navId = 0;

  test.afterEach(() => {
    if (navId) {
      deleteNavigationEntityById(navId);
      navId = 0;
    }
  });

  test('LINCHPIN: updateBlockAttributes preserves mega-menu clientId in wp_navigation entity', async ({
    page,
  }) => {
    navId = createNavigationEntityWithContent(
      'mega-linchpin',
      'Mega Linchpin Fixture',
      NAV_CONTENT
    );
    await login(page);
    await page.goto(
      `/wp-admin/site-editor.php?postType=wp_navigation&postId=${navId}&canvas=edit`
    );

    const canvas = page.frameLocator('iframe[name="editor-canvas"]');
    await expect(canvas.locator('.starter-mega-menu').first()).toBeVisible({
      timeout: 20000,
    });

    // Read initial clientId + select the block (so the linchpin also verifies
    // selection survives the mutation).
    const initialClientId: string = await page.evaluate(`
      (() => {
        const target = ${FIND_MEGA_FN};
        if (!target) throw new Error('mega-menu block not found in entity');
        window.wp.data.dispatch('core/block-editor').selectBlock(target.clientId);
        return target.clientId;
      })()
    `);
    expect(initialClientId).toBeTruthy();

    // Perform a pure attribute update — the exact mutation shape the sidebar
    // form triggers via setAttributes.
    await page.evaluate(`
      window.wp.data
        .dispatch('core/block-editor')
        .updateBlockAttributes('${initialClientId}', { label: 'Edited' })
    `);

    const after = await page.evaluate(`
      (() => {
        const target = ${FIND_MEGA_FN};
        const sel = window.wp.data.select('core/block-editor');
        return {
          clientId: target?.clientId ?? null,
          label: target?.attributes?.label ?? null,
          selected: sel.getSelectedBlockClientId(),
        };
      })()
    `) as { clientId: string | null; label: string | null; selected: string | null };

    expect(after.clientId).toBe(initialClientId);
    expect(after.label).toBe('Edited');
    expect(after.selected).toBe(initialClientId);
  });
});
```

- [ ] **Step 3: Stage the changes (test execution is deferred to Task 7)**

No in-worktree check meaningful here: Playwright cannot run against worktree code (wp-env mounts main checkout), the project's `tsc` baseline is broken (extends missing `@wordpress/scripts/tsconfig.json`), and `lint:js` carries 14 unrelated pre-existing errors. Visually re-read the diff to confirm the helper signatures and test code match the plan, then commit.

- [ ] **Step 4: Commit**

```bash
git add tests/e2e/utils.ts tests/e2e/mega-menu-editor.spec.ts
git commit -m "test(mega-menu): linchpin probe — clientId stability in wp_navigation entity"
```

---

## Task 2: Seed `starter/mega-menu` inside the curated nav entity

**Files:**
- Modify: `inc/nav-seed.php`
- Modify: `tests/phpunit/NavSeed/SeedNavTest.php`

- [ ] **Step 1: Write the failing PHPUnit assertion**

In `tests/phpunit/NavSeed/SeedNavTest.php`, insert this method **before** `test_pristine_fallback_detection`:

```php
public function test_menu_blocks_include_starter_mega_menu() {
    $blocks = starter_nav_menu_blocks();
    $this->assertStringContainsString( 'wp:starter/mega-menu', $blocks );
    $this->assertStringContainsString( '"label":"Products"', $blocks );
    $this->assertSame( 1, substr_count( $blocks, 'wp:starter/mega-menu' ) );
}
```

Note: the existing assertion `assertSame( 3, substr_count( $blocks, 'wp:navigation-link' ) )` continues to hold — the mega-menu does not contain `wp:navigation-link` substring.

- [ ] **Step 2: Extend `starter_nav_menu_blocks()` in `inc/nav-seed.php`**

Replace the current function body (lines 29–38) with:

```php
function starter_nav_menu_blocks(): string {
	$mega = wp_json_encode(
		array(
			'label'   => 'Products',
			'columns' => array(
				array(
					'heading' => 'Product',
					'links'   => array(
						array(
							'label'       => 'Pricing',
							'url'         => '/pricing',
							'description' => 'Plans',
							'icon'        => 'tag',
						),
						array(
							'label'       => 'Docs',
							'url'         => '/docs',
							'description' => 'Guides',
							'icon'        => 'book',
						),
					),
				),
			),
		)
	);

	return implode(
		"\n",
		array(
			'<!-- wp:starter/mega-menu ' . $mega . ' /-->',
			'<!-- wp:navigation-link {"label":"About","url":"/about","kind":"custom"} /-->',
			'<!-- wp:navigation-link {"label":"Blog","url":"/blog","kind":"custom"} /-->',
			'<!-- wp:navigation-link {"label":"Contact","url":"/contact","kind":"custom"} /-->',
		)
	);
}
```

Leave the existing function docblock (lines 20–28) in place; update only the body.

- [ ] **Step 3: Typecheck (defer PHPUnit run to Task 7)**

Run: `php -l inc/nav-seed.php`
Expected: `No syntax errors detected in inc/nav-seed.php`

- [ ] **Step 4: Commit**

```bash
git add inc/nav-seed.php tests/phpunit/NavSeed/SeedNavTest.php
git commit -m "feat(nav-seed): include starter/mega-menu in seeded entity"
```

---

## Task 3: Context-aware `edit.tsx` — `useBlockEditingMode` gating

**Files:**
- Modify: `src/blocks/mega-menu/edit.tsx`

- [ ] **Step 1: Update the imports**

Replace lines 1–3 of `src/blocks/mega-menu/edit.tsx` with:

```tsx
import { __ } from '@wordpress/i18n';
import {
	useBlockProps,
	InspectorControls,
	useBlockEditingMode,
} from '@wordpress/block-editor';
import { useSelect } from '@wordpress/data';
import { store as editorStore } from '@wordpress/editor';
import { PanelBody, TextControl, Button } from '@wordpress/components';
```

- [ ] **Step 2: Add context detection + editing-mode gate at the top of `Edit()`**

Find the `export default function Edit(...)` body (line 30). Immediately after the opening `{` and before the existing `const blockProps = useBlockProps(...)`, insert:

```tsx
	// Editing is only allowed when the wp_navigation entity is the open
	// document (Site Editor → Navigation). Everywhere else (page editor
	// preview, template editor) useBlockEditingMode('disabled') neutralizes
	// the block — no toolbar, no Inspector, no setAttributes path. This is
	// the destroy-on-attr-change escape hatch: uncontrolled core/navigation
	// children get re-instantiated when mutated, which collapses the form
	// mid-edit. Disabled mode prevents the mutation from ever firing.
	const isEntityContext = useSelect(
		( select ) =>
			( select( editorStore ) as { getCurrentPostType?: () => string } )
				?.getCurrentPostType?.() === 'wp_navigation',
		[]
	);
	useBlockEditingMode( isEntityContext ? 'default' : 'disabled' );

```

The remainder of the function body is unchanged. `InspectorControls` and the static preview render the same JSX as before — the framework hides the Inspector and toolbar when `useBlockEditingMode` returns `'disabled'`, per the Gutenberg block-editor API.

- [ ] **Step 3: Build (the only meaningful in-worktree check for TS changes)**

Run: `npm run build`
Expected: PASS — `build/blocks/mega-menu/index.js` emitted, no webpack errors. wp-scripts/Babel catches syntax/import errors here even though raw `tsc` is broken for this project.

- [ ] **Step 4: Commit**

```bash
git add src/blocks/mega-menu/edit.tsx
git commit -m "feat(mega-menu): gate editing to wp_navigation entity context"
```

---

## Task 4: Sidebar form happy-path test (entity context)

**Files:**
- Modify: `tests/e2e/mega-menu-editor.spec.ts`

- [ ] **Step 1: Append the test inside the existing `describe('mega-menu editor (entity context)')` block**

Insert after the LINCHPIN test, before the closing `} );` of the describe:

```ts
	test('sidebar form: Add column reveals Column 2 panel', async ({ page }) => {
		navId = createNavigationEntityWithContent(
			'mega-form',
			'Mega Form Fixture',
			NAV_CONTENT
		);
		await login(page);
		await page.goto(
			`/wp-admin/site-editor.php?postType=wp_navigation&postId=${navId}&canvas=edit`
		);

		const canvas = page.frameLocator('iframe[name="editor-canvas"]');
		await expect(canvas.locator('.starter-mega-menu').first()).toBeVisible({
			timeout: 20000,
		});
		await canvas.locator('.starter-mega-menu').first().click();

		// Sidebar form is visible with the seeded label.
		await expect(page.getByLabel('Menu label')).toHaveValue('Products');

		// Add column → Column 2 panel appears.
		await page.getByRole('button', { name: 'Add column' }).click();
		await expect(
			page.getByRole('button', { name: /^Column 2/ })
		).toBeVisible();
	});
```

- [ ] **Step 2: Re-read the diff**

No in-worktree check is meaningful for added Playwright tests (see Task 1 Step 3 note). Visually confirm the test compiles by reading it back.

- [ ] **Step 3: Commit**

```bash
git add tests/e2e/mega-menu-editor.spec.ts
git commit -m "test(mega-menu): sidebar form happy-path in entity context"
```

---

## Task 5: Page editor read-only test

**Files:**
- Modify: `tests/e2e/mega-menu-editor.spec.ts`

- [ ] **Step 1: Add a second describe block for the page-editor context**

Append at the bottom of `tests/e2e/mega-menu-editor.spec.ts`, after the existing describe:

```ts
test.describe('mega-menu editor (page context — read-only)', () => {
	test('Inspector form is hidden and editing mode is disabled', async ({ page }) => {
		// Legacy authoring surface: a page with an inline navigation containing
		// a mega-menu. Editing must be blocked here — useBlockEditingMode is
		// the gate that prevents the destroy-on-attr-change failure mode.
		const slug = 'mega-pageeditor';
		deletePageBySlug(slug);
		const url = createPageWithContent(
			slug,
			'Mega Page Editor',
			'<!-- wp:navigation {"overlayMenu":"never"} -->' +
				'<!-- wp:starter/mega-menu {"label":"Products","columns":[]} /-->' +
				'<!-- /wp:navigation -->'
		);
		const id = url.replace(/[^0-9]/g, '');
		await login(page);
		await page.goto(`/wp-admin/post.php?post=${id}&action=edit`);

		const canvas = page.frameLocator('iframe[name="editor-canvas"]');
		await expect(canvas.locator('.starter-mega-menu').first()).toBeVisible({
			timeout: 20000,
		});
		await canvas.locator('.starter-mega-menu').first().click();

		// The Inspector "Menu label" field must not be reachable.
		await expect(page.getByLabel('Menu label')).toHaveCount(0);

		// Cross-check via wp.data: editing mode for this block is 'disabled'.
		const mode: string | null = await page.evaluate(`
			(() => {
				const sel = window.wp.data.select('core/block-editor');
				const walk = (list) => {
					for (const b of list) {
						if (b.name === 'starter/mega-menu') return b;
						const hit = walk(b.innerBlocks || []);
						if (hit) return hit;
					}
					return null;
				};
				const t = walk(sel.getBlocks());
				return t && sel.getBlockEditingMode
					? sel.getBlockEditingMode(t.clientId)
					: null;
			})()
		`);
		expect(mode).toBe('disabled');

		deletePageBySlug(slug);
	});
});
```

- [ ] **Step 2: Re-read the diff**

No in-worktree check is meaningful for added Playwright tests (see Task 1 Step 3 note). Visually confirm the test compiles by reading it back.

- [ ] **Step 3: Commit**

```bash
git add tests/e2e/mega-menu-editor.spec.ts
git commit -m "test(mega-menu): page-editor surface is read-only via useBlockEditingMode"
```

---

## Task 6: Pattern cleanup — drop inline mega-menu

**Files:**
- Modify: `patterns/mega-menu-header.php`

After the seed change in Task 2, the seeded `wp_navigation` entity owns the mega-menu. The `/mega-demo/` pattern can therefore render a bare `core/navigation` (which `inc/nav-seed.php::starter_nav_bind_ref` binds to the seeded entity at `render_block_data`), eliminating the broken inline-children path.

- [ ] **Step 1: Replace `patterns/mega-menu-header.php` contents**

Overwrite the file with:

```php
<?php
/**
 * Title: Mega Menu Demo Header
 * Slug: starter/mega-menu-header
 * Categories: starter
 * Inserter: true
 */
?>
<!-- wp:group {"className":"mega-demo","layout":{"type":"constrained"}} -->
<div class="wp-block-group mega-demo">
	<!-- wp:navigation {"overlayMenu":"mobile","layout":{"type":"flex","orientation":"horizontal"}} /-->
</div>
<!-- /wp:group -->
```

The `.mega-demo` wrapper class is preserved so `tests/e2e/mega-menu.spec.ts`'s scoping (`root = page.locator('.mega-demo')`) continues to find the right nav (now resolved from the seeded entity, which contains the `Products` mega-menu after Task 2).

- [ ] **Step 2: Syntax check**

Run: `php -l patterns/mega-menu-header.php`
Expected: `No syntax errors detected`.

- [ ] **Step 3: Commit**

```bash
git add patterns/mega-menu-header.php
git commit -m "refactor(pattern): mega-menu-header uses bound entity, not inline children"
```

---

## Task 7: Merge worktree → development; run full test suite

Per session memory: `wp-env` mounts the main checkout (`wp-starter-child-theme` at `:8890`). PHPUnit and Playwright must run from `development` after merge.

- [ ] **Step 1: Merge worktree into `development`**

From `/Users/jonas/Entwicklung/wp-starter-theme` (main checkout):

```bash
git checkout development
git merge --no-ff .worktrees/mega-menu-entity-edit
```

If the worktree was created from up-to-date `development`, this fast-forwards or produces a clean merge commit.

- [ ] **Step 2: Run full PHPUnit**

Run: `composer test`
Expected: All tests green, including:
- `SeedNavTest::test_menu_blocks_include_starter_mega_menu` (new, Task 2)
- `SeedNavTest::test_menu_blocks_contain_curated_items` (existing, still passes — assertion counts only `wp:navigation-link`)
- `BlockRender/MegaMenuTest` (existing 5 tests)
- `MegaMenu/ListableFilterTest` (existing)
- `Patterns/PatternsTest` (existing — verifies the simplified pattern still parses)

If any existing test fails, diagnose before continuing. Common cause for `PatternsTest` failure: the simplified pattern outputs a different DOM than before — update the test if it asserts on the inline mega-menu output (it shouldn't, but verify).

- [ ] **Step 3: Run full Playwright (LINCHPIN is the gate)**

Run: `npm run e2e -- tests/e2e/mega-menu-editor.spec.ts -g "LINCHPIN"`

**Expected: PASS.** If the LINCHPIN test FAILS, halt — the entity-edit design is invalidated. Revert the merge:

```bash
git reset --hard ORIG_HEAD   # only if no other work landed since the merge
```

Then escalate: the fallback is to introduce a bespoke `starter/site-nav` block (separate plan).

If LINCHPIN passes, run the full suite:

Run: `npm run e2e`
Expected: All green, including:
- `mega-menu-editor.spec.ts` — 3 tests (LINCHPIN, sidebar happy-path, page-editor read-only)
- `mega-menu.spec.ts` — 4 tests (front-end, unchanged behavior)
- All other suites untouched

Known flake (per session memory): `navigation.spec.ts` and 404 specs may fail due to unseeded DB — pre-existing, not a regression from this work. Note in the PR if observed.

- [ ] **Step 4: Delete the worktree**

```bash
git worktree remove .worktrees/mega-menu-entity-edit
git branch -d <worktree-branch-name>
```

(The using-git-worktrees skill handles the worktree branch naming when it was created.)

---

## Task 8: Open PR to `main`

Per session memory: never local-merge to `main`; PR-only. Use the `/pr` skill.

- [ ] **Step 1: Push `development` to `origin`**

```bash
git push origin development
```

- [ ] **Step 2: Open the PR**

Invoke the `/pr` skill. It derives title + body from the commits on `development` since `main`. Expected commits on the PR:

1. `test(mega-menu): linchpin probe — clientId stability in wp_navigation entity`
2. `feat(nav-seed): include starter/mega-menu in seeded entity`
3. `feat(mega-menu): gate editing to wp_navigation entity context`
4. `test(mega-menu): sidebar form happy-path in entity context`
5. `test(mega-menu): page-editor surface is read-only via useBlockEditingMode`
6. `refactor(pattern): mega-menu-header uses bound entity, not inline children`

- [ ] **Step 3: Return the PR URL**

---

## Self-review checklist (run by the engineer before starting Task 1)

- **Spec coverage:** Each of the 5 design sections from the brainstorm maps to tasks — section 1 (architecture) → Tasks 3, 6; section 2 (context-aware edit) → Task 3; section 3 (seed/migration) → Tasks 2, 6; section 4 (tests) → Tasks 1, 4, 5, 7; section 5 (risks/linchpin) → Tasks 1, 7. ✓
- **No placeholders:** Every code block above is committable as-is. No `TODO`, no `implement later`, no `similar to Task N`. ✓
- **Type consistency:** `createNavigationEntityWithContent(slug, title, content)` signature is identical across Tasks 1 & 4. `FIND_MEGA_FN` (Task 1) and the inline walker in Task 5 are independent string-form expressions — duplication is intentional (each test is self-contained; the walker is 7 lines and DRY-ing it would require exporting a serialized helper from utils.ts, which is overkill). ✓
- **Gate semantics:** Task 1 commits the linchpin test code without running it; Task 7 runs it and gates Tasks 4–6's value on the result. If the linchpin fails at Task 7, Tasks 2–6 have still produced harmless changes (the seeded mega-menu renders correctly on the front end either way) — but the editing-mode gating becomes moot since editing is broken even in entity context. The plan-halt is "revert the merge, open a follow-up plan for the bespoke block fallback." ✓
