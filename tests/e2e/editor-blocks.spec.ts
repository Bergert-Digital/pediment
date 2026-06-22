import { test, expect } from '@playwright/test';
import { createPageWithContent, deletePageBySlug } from './utils';

const BLOCKS_TO_VERIFY: Array<{ name: string; cls: string; markup: string }> = [
  { name: 'hero',         cls: 'starter-hero',         markup: '<!-- wp:pediment/hero {"headline":"H","subheadline":"S"} /-->' },
  { name: 'cta',          cls: 'starter-cta',          markup: '<!-- wp:pediment/cta {"title":"T","body":"B","primaryText":"P","primaryUrl":"/p"} /-->' },
  { name: 'faq',          cls: 'starter-faq',          markup: '<!-- wp:pediment/faq --><!-- wp:pediment/faq-item {"question":"Q","answer":"A"} /--><!-- /wp:pediment/faq -->' },
  { name: 'prose',        cls: 'starter-prose',        markup: '<!-- wp:pediment/prose --><!-- wp:paragraph --><p>x</p><!-- /wp:paragraph --><!-- /wp:pediment/prose -->' },
  { name: 'pull-quote',   cls: 'starter-pull-quote',   markup: '<!-- wp:pediment/pull-quote {"quote":"Q","citation":"C"} /-->' },
  { name: 'section-head', cls: 'starter-section-head', markup: '<!-- wp:pediment/section-head {"eyebrow":"E","headline":"H","lead":"L"} /-->' },
  { name: 'stat',         cls: 'starter-stat',         markup: '<!-- wp:pediment/stat {"value":"V","label":"L"} /-->' },
  { name: 'blog-index',   cls: 'starter-blog-index',   markup: '<!-- wp:pediment/blog-index {"count":3} /-->' },
  { name: 'contact-form', cls: 'starter-contact-form', markup: '<!-- wp:pediment/contact-form /-->' },
  { name: 'slider', cls: 'starter-slider', markup: '<!-- wp:pediment/slider {"slides":[{"heading":"x"}]} /-->' },
];

const SLUG = 'e2e-block-kitchen-sink';

test('every pediment block renders on the front end', async ({ page }) => {
  deletePageBySlug(SLUG);
  const content = BLOCKS_TO_VERIFY.map((b) => b.markup).join('\n\n');
  const url = createPageWithContent(SLUG, 'Block kitchen sink', content);

  await page.goto(url);

  for (const { cls } of BLOCKS_TO_VERIFY) {
    await expect(page.locator(`.${cls}`).first()).toBeVisible();
  }

  deletePageBySlug(SLUG);
});
