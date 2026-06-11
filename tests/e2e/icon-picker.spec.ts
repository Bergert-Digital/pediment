import { test, expect } from '@playwright/test';
import { login, createPageWithContent, deletePageBySlug } from './utils';

const SLUG = 'icon-picker-e2e';

test.describe('icon picker discoverability', () => {
  test.afterEach(() => {
    deletePageBySlug(SLUG);
  });

  test('tag search and category filter surface deep icons', async ({
    page,
  }) => {
    deletePageBySlug(SLUG);
    const url = createPageWithContent(
      SLUG,
      'Icon Picker E2E',
      '<!-- wp:pediment/feature {"icon":"trend-up","title":"T","text":"x"} /-->'
    );
    const id = url.replace(/[^0-9]/g, '');
    await login(page);
    await page.goto(`/wp-admin/post.php?post=${id}&action=edit`);

    // Select the feature block so its inspector panel renders.
    const canvas = page.frameLocator('iframe[name="editor-canvas"]');
    const block = canvas.locator('.starter-feature').first();
    await expect(block).toBeVisible({ timeout: 20000 });
    await block.click();

    // The settings sidebar may be collapsed by default; open it so the
    // inspector panel (and the picker toggle) is in the DOM and visible.
    const toggle = page.locator('.pediment-icon-picker__toggle');
    if (!(await toggle.isVisible().catch(() => false))) {
      await page
        .getByRole('button', { name: 'Settings', exact: true })
        .first()
        .click();
    }
    await expect(toggle).toBeVisible();

    // Open the picker popover from the inspector toggle.
    await toggle.click();

    // Wait for the async catalog fetch to resolve: the category select and
    // the grid cells only render once the catalog is loaded.
    const content = page.locator('.pediment-icon-picker__content');
    await expect(content.locator('select')).toBeVisible();
    await expect(
      content.locator('.pediment-icon-picker__grid [role="option"]').first()
    ).toBeVisible();

    // Tag search: "delete" is a tag of "trash" (the slug has no "delete").
    await page.getByPlaceholder('Search icons…').fill('delete');
    await expect(
      page.locator(
        '.pediment-icon-picker__grid [role="option"][aria-label="trash"]'
      )
    ).toBeVisible();

    // Clear search, filter by category "Maps & travel": "rocket" is in it.
    await page.getByPlaceholder('Search icons…').fill('');
    await page
      .locator('.pediment-icon-picker__content select')
      .selectOption('maps & travel');

    // Switching category resets the progressive window to the first chunk
    // (120 cells), and "rocket" sits past it in this category. Scroll the
    // grid to trip its IntersectionObserver sentinel and load more cells
    // until the rocket cell renders.
    const grid = page.locator('.pediment-icon-picker__grid');
    const rocket = grid.locator('[role="option"][aria-label="rocket"]');
    await expect
      .poll(
        async () => {
          if (await rocket.count()) {
            return true;
          }
          await grid.evaluate((el) => {
            el.scrollTop = el.scrollHeight;
          });
          return false;
        },
        { timeout: 15000 }
      )
      .toBe(true);
    await expect(rocket).toBeVisible();

    // Picking writes the slug back to the trigger.
    await rocket.click();
    await expect(toggle).toContainText('rocket');
  });
});
