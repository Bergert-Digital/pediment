import { test, expect } from '@playwright/test';
import {
  login,
  createNavigationEntityWithContent,
  deleteNavigationEntityById,
  createPageWithContent,
  deletePageBySlug,
} from './utils';

const NAV_CONTENT =
  '<!-- wp:pediment/mega-menu {"label":"Products","columns":[{"heading":"Product","links":[{"label":"Pricing","url":"/pricing","description":"Plans","icon":"tag"}]}]} /-->' +
  '<!-- wp:navigation-link {"label":"About","url":"/about","kind":"custom"} /-->';

// Walk the block tree to find a pediment/mega-menu (it lives inside a
// core/navigation; controlled inner-blocks mode keeps the tree shallow here).
const FIND_MEGA_FN = `(() => {
  const sel = window.wp.data.select('core/block-editor');
  const walk = (list) => {
    for (const b of list) {
      if (b.name === 'pediment/mega-menu') return b;
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

    // The wp_navigation entity loads its inner blocks (controlled-inner-blocks
    // mode) asynchronously: the DOM wrapper appears as soon as the entity's
    // template renders, but `core/block-editor.getBlocks()` only sees the
    // child blocks after the entity record resolves. Poll until the tree
    // walk finds the block before reading the initial clientId.
    await page.waitForFunction(FIND_MEGA_FN, undefined, { timeout: 20000 });

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
});

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
				'<!-- wp:pediment/mega-menu {"label":"Products","columns":[]} /-->' +
				'<!-- /wp:navigation -->'
		);
		const id = url.replace(/[^0-9]/g, '');
		await login(page);
		await page.goto(`/wp-admin/post.php?post=${id}&action=edit`);

		const canvas = page.frameLocator('iframe[name="editor-canvas"]');
		await expect(canvas.locator('.starter-mega-menu').first()).toBeVisible({
			timeout: 20000,
		});
		// Select via wp.data, not a DOM click: in the page editor the parent
		// `wp-block-navigation__container` is a drop zone and intercepts
		// pointer events on its children, so a real click can't reach the
		// disabled mega-menu wrapper.
		await page.waitForFunction(`
			(() => {
				const sel = window.wp.data.select('core/block-editor');
				const walk = (list) => {
					for (const b of list) {
						if (b.name === 'pediment/mega-menu') return b;
						const hit = walk(b.innerBlocks || []);
						if (hit) return hit;
					}
					return null;
				};
				const t = walk(sel.getBlocks());
				if (!t) return false;
				window.wp.data.dispatch('core/block-editor').selectBlock(t.clientId);
				return true;
			})()
		`, undefined, { timeout: 20000 });

		// The Inspector "Menu label" field must not be reachable.
		await expect(page.getByLabel('Menu label')).toHaveCount(0);

		// Cross-check via wp.data: editing mode for this block is 'disabled'.
		const mode: string | null = await page.evaluate(`
			(() => {
				const sel = window.wp.data.select('core/block-editor');
				const walk = (list) => {
					for (const b of list) {
						if (b.name === 'pediment/mega-menu') return b;
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
