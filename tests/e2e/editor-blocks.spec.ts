import { test, expect } from '@playwright/test';
import { createPageWithContent, deletePageBySlug } from './utils';

const BLOCKS_TO_VERIFY: Array<{ name: string; cls: string; markup: string }> = [
  { name: 'hero',         cls: 'starter-hero',         markup: '<!-- wp:starter/hero {"headline":"H","subheadline":"S"} /-->' },
  { name: 'cta',          cls: 'starter-cta',          markup: '<!-- wp:starter/cta {"title":"T","body":"B","primaryText":"P","primaryUrl":"/p"} /-->' },
  { name: 'faq',          cls: 'starter-faq',          markup: '<!-- wp:starter/faq --><!-- wp:starter/faq-item {"question":"Q","answer":"A"} /--><!-- /wp:starter/faq -->' },
  { name: 'prose',        cls: 'starter-prose',        markup: '<!-- wp:starter/prose --><!-- wp:paragraph --><p>x</p><!-- /wp:paragraph --><!-- /wp:starter/prose -->' },
  { name: 'pull-quote',   cls: 'starter-pull-quote',   markup: '<!-- wp:starter/pull-quote {"quote":"Q","citation":"C"} /-->' },
  { name: 'stat',         cls: 'starter-stat',         markup: '<!-- wp:starter/stat {"value":"V","label":"L"} /-->' },
  { name: 'blog-index',   cls: 'starter-blog-index',   markup: '<!-- wp:starter/blog-index {"count":3} /-->' },
  { name: 'contact-form', cls: 'starter-contact-form', markup: '<!-- wp:starter/contact-form /-->' },
];

const SLUG = 'e2e-block-kitchen-sink';

test('every starter block renders on the front end', async ({ page }) => {
  deletePageBySlug(SLUG);
  const content = BLOCKS_TO_VERIFY.map((b) => b.markup).join('\n\n');
  const url = createPageWithContent(SLUG, 'Block kitchen sink', content);

  await page.goto(url);

  for (const { cls } of BLOCKS_TO_VERIFY) {
    await expect(page.locator(`.${cls}`).first()).toBeVisible();
  }

  deletePageBySlug(SLUG);
});
