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
    const wrapper = canvas.locator('.starter-mega-menu').first();
    await expect(wrapper).toBeVisible({ timeout: 20000 });

    // The wp_navigation entity wraps its inner blocks in a controlled-inner-
    // blocks navigation, so `core/block-editor.getBlocks()` returns the
    // outer navigation only — the tree walk for `pediment/mega-menu` lives
    // under that root. Grab the clientId from the rendered wrapper's
    // `data-block` attribute (Gutenberg writes it on every BlockListBlock)
    // instead of round-tripping through the data store.
    const initialClientId: string = (await wrapper.getAttribute('data-block')) ?? '';
    expect(initialClientId).toBeTruthy();
    await page.evaluate(`
      window.wp.data
        .dispatch('core/block-editor')
        .selectBlock('${initialClientId}')
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
        const sel = window.wp.data.select('core/block-editor');
        const block = sel.getBlock('${initialClientId}');
        return {
          clientId: block?.clientId ?? null,
          label: block?.attributes?.label ?? null,
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
		const wrapper = canvas.locator('.starter-mega-menu').first();
		await expect(wrapper).toBeVisible({ timeout: 20000 });
		// In the page editor the parent `.wp-block-navigation__container` is
		// a drop zone and intercepts pointer events on its children, so a
		// real click never reaches the disabled wrapper. `force: true`
		// bypasses Playwright's hit-testing — Gutenberg still selects the
		// block from the synthetic event reaching the BlockListBlock root.
		await wrapper.click({ force: true });

		// The Inspector "Menu label" field must not be reachable.
		await expect(page.getByLabel('Menu label')).toHaveCount(0);

		// Cross-check via wp.data: editing mode for this block is 'disabled'.
		// Read the clientId from the rendered wrapper instead of walking
		// `getBlocks()`, which in the page editor's controlled-inner-blocks
		// navigation only exposes the outer nav.
		const clientId = (await wrapper.getAttribute('data-block')) ?? '';
		expect(clientId).toBeTruthy();
		const mode: string | null = await page.evaluate(`
			(() => {
				const sel = window.wp.data.select('core/block-editor');
				return sel.getBlockEditingMode
					? sel.getBlockEditingMode('${clientId}')
					: null;
			})()
		`);
		expect(mode).toBe('disabled');

		deletePageBySlug(slug);
	});
});
