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
