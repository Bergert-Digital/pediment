import { test, expect } from '@playwright/test';

test.describe('blog landing (/blog/)', () => {
  test('renders heading band, insight cards, and pagination block', async ({ page }) => {
    await page.goto('/blog/');

    // Heading band
    await expect(
      page.getByRole('heading', { level: 1, name: /latest thinking from the team/i })
    ).toBeVisible();

    // Grid: at least one post card
    const firstCard = page.locator('.is-style-insights-grid .wp-block-post-template > li').first();
    await expect(firstCard).toBeVisible();

    // The post title inside that card is a real link to a post permalink
    const titleLink = firstCard.locator('.wp-block-post-title a').first();
    await expect(titleLink).toBeVisible();
    const href = await titleLink.getAttribute('href');
    expect(href).toMatch(/\/.+\/$/);
    const titleText = (await titleLink.innerText()).trim();
    expect(titleText.length).toBeGreaterThan(0);

    // Category badge is positioned absolute over the media wrapper
    const badge = firstCard.locator('.wp-block-post-terms').first();
    await expect(badge).toBeVisible();
    const position = await badge.evaluate(
      (el) => window.getComputedStyle(el).position
    );
    expect(position).toBe('absolute');

    // Pagination block exists in the DOM (no `next` link if the seeded post
    // count fits on one page — that's fine).
    const pagination = page.locator('.is-style-insights-grid .wp-block-query-pagination');
    await expect(pagination).toHaveCount(1);
  });

  test('no client-side filter row on the paginated landing', async ({ page }) => {
    await page.goto('/blog/');
    // The curated block's filter — must not appear here.
    await expect(page.locator('.starter-blog-index__filter')).toHaveCount(0);
  });
});
