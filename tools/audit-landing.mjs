#!/usr/bin/env node
// Visual audit: rendered :8890 landing page vs docs/design/pediment-mockup.html.
// Per-band screenshots + bounding-box metrics so we can see WHERE things diverge,
// not just guess from CSS.
//
// Usage: node tools/audit-landing.mjs
// Outputs: test-results/audit/{rendered,mockup}/*.png + metrics.json + index.html

import { chromium } from '@playwright/test';
import { mkdir, writeFile, rm } from 'node:fs/promises';
import { existsSync } from 'node:fs';
import { fileURLToPath, pathToFileURL } from 'node:url';
import { dirname, resolve } from 'node:path';

const __dirname = dirname(fileURLToPath(import.meta.url));
const repoRoot = resolve(__dirname, '..');
const outDir = resolve(repoRoot, 'test-results/audit');
const renderedDir = resolve(outDir, 'rendered');
const mockupDir = resolve(outDir, 'mockup');

const RENDERED_URL = 'http://localhost:8890/';
const MOCKUP_FILE = resolve(repoRoot, 'docs/design/pediment-mockup.html');
const VIEWPORT = { width: 1440, height: 900 };

const BAND_LABELS = [
  'hero',
  'services',
  'approach',
  'stats',
  'testimonial',
  'faq',
  'cta',
  'insights',
];

async function ensureDirs() {
  if (existsSync(outDir)) await rm(outDir, { recursive: true });
  await mkdir(renderedDir, { recursive: true });
  await mkdir(mockupDir, { recursive: true });
}

// Returns metrics for each top-level band on the rendered front page.
async function auditRendered(page) {
  await page.goto(RENDERED_URL, { waitUntil: 'networkidle' });
  // Top-level bands: direct children of .wp-site-blocks with class .starter-band.
  const bands = await page.$$('.entry-content > .starter-band');
  const results = [];
  for (let i = 0; i < bands.length; i++) {
    const band = bands[i];
    const label = BAND_LABELS[i] ?? `band-${i}`;
    const box = await band.boundingBox();
    if (!box) continue;

    // Scroll so screenshot includes the full band, then clip.
    await band.scrollIntoViewIfNeeded();
    const file = resolve(renderedDir, `${String(i).padStart(2, '0')}-${label}.png`);
    await band.screenshot({ path: file });

    // Pull useful inner-element metrics: anything that should be width-constrained.
    const inner = await band.evaluate((el) => {
      const out = {};
      const pick = (node, key) => {
        if (!node) return;
        const r = node.getBoundingClientRect();
        const cs = window.getComputedStyle(node);
        out[key] = {
          x: Math.round(r.left),
          y: Math.round(r.top + window.scrollY),
          w: Math.round(r.width),
          h: Math.round(r.height),
          maxWidth: cs.maxWidth,
          marginLeft: cs.marginLeft,
          marginRight: cs.marginRight,
          paddingLeft: cs.paddingLeft,
          paddingRight: cs.paddingRight,
          boxSizing: cs.boxSizing,
          width: cs.width,
          classes: node.className,
        };
      };
      pick(el, 'band');
      pick(el.querySelector(':scope > .head, :scope .head'), 'head');
      pick(el.querySelector(':scope > .alignwide:not(.head), :scope .alignwide:not(.head)'), 'firstAlignwide');
      pick(el.querySelector(':scope .starter-feature-grid'), 'featureGrid');
      pick(el.querySelector(':scope .wp-block-columns'), 'columns');
      pick(el.querySelector(':scope h2'), 'h2');
      pick(el.querySelector(':scope .kicker'), 'kicker');
      return out;
    });

    results.push({ index: i, label, file, box, inner, viewport: VIEWPORT });
  }
  return results;
}

// Mockup sections — every `<section>` directly under <body>.
async function auditMockup(page) {
  await page.goto(pathToFileURL(MOCKUP_FILE).href, { waitUntil: 'networkidle' });
  const sections = await page.$$('body > section, body > div.logos, body > header, body > footer');
  // Trim to the same logical bands as rendered (skip header/footer/logo-strip if you want;
  // we keep them all so labels align by position).
  const results = [];
  for (let i = 0; i < sections.length; i++) {
    const sec = sections[i];
    const cls = await sec.evaluate((el) => el.className);
    const tag = await sec.evaluate((el) => el.tagName.toLowerCase());
    const label = `${i}-${tag}-${cls.split(/\s+/).slice(0, 2).join('-') || 'section'}`;
    const box = await sec.boundingBox();
    if (!box) continue;
    await sec.scrollIntoViewIfNeeded();
    const file = resolve(mockupDir, `${String(i).padStart(2, '0')}-${label}.png`);
    await sec.screenshot({ path: file });

    const inner = await sec.evaluate((el) => {
      const out = {};
      const pick = (node, key) => {
        if (!node) return;
        const r = node.getBoundingClientRect();
        const cs = window.getComputedStyle(node);
        out[key] = {
          x: Math.round(r.left),
          y: Math.round(r.top + window.scrollY),
          w: Math.round(r.width),
          h: Math.round(r.height),
          maxWidth: cs.maxWidth,
          marginLeft: cs.marginLeft,
          marginRight: cs.marginRight,
          paddingLeft: cs.paddingLeft,
          paddingRight: cs.paddingRight,
          classes: node.className,
        };
      };
      pick(el, 'section');
      pick(el.querySelector('.wrap'), 'wrap');
      pick(el.querySelector('.head'), 'head');
      pick(el.querySelector('.grid3'), 'grid3');
      pick(el.querySelector('h2'), 'h2');
      pick(el.querySelector('.kicker'), 'kicker');
      pick(el.querySelector('.lead'), 'lead');
      return out;
    });

    results.push({ index: i, label, file, box, inner });
  }
  return results;
}

async function writeIndexHtml(rendered, mockup) {
  // Pair by sequential index — the rendered top-level bands map ~1:1 to
  // mockup sections in document order (header, hero, logos, services, …).
  const rows = [];
  const max = Math.max(rendered.length, mockup.length);
  for (let i = 0; i < max; i++) {
    const r = rendered[i];
    const m = mockup[i];
    rows.push(`
      <tr>
        <td><strong>${r?.label ?? '—'}</strong><br><small>${r ? `${r.inner.band.w}×${r.inner.band.h}` : ''}</small></td>
        <td>${r ? `<img src="${r.file.replace(outDir + '/', '')}" />` : '—'}</td>
        <td>${m ? `<img src="${m.file.replace(outDir + '/', '')}" />` : '—'}</td>
        <td><strong>${m?.label ?? '—'}</strong></td>
      </tr>
    `);
  }
  const html = `<!doctype html><meta charset="utf-8"><title>Landing audit</title>
<style>
  body{font:14px system-ui;padding:24px;background:#0a1b33;color:#e6eefb}
  table{border-collapse:collapse;width:100%}
  td{border:1px solid #234;padding:10px;vertical-align:top}
  img{max-width:540px;height:auto;display:block;background:#fff;border:1px solid #ccc}
  small{color:#9ab}
  th{position:sticky;top:0;background:#0a1b33}
</style>
<h1>Landing audit — rendered (:8890) vs mockup</h1>
<p>Viewport: ${VIEWPORT.width}×${VIEWPORT.height}</p>
<table>
  <thead><tr><th>Rendered label</th><th>Rendered (:8890)</th><th>Mockup</th><th>Mockup label</th></tr></thead>
  <tbody>${rows.join('')}</tbody>
</table>`;
  await writeFile(resolve(outDir, 'index.html'), html);
}

async function main() {
  await ensureDirs();
  const browser = await chromium.launch();
  const ctx = await browser.newContext({ viewport: VIEWPORT, deviceScaleFactor: 1 });
  const page = await ctx.newPage();

  console.log('Auditing rendered :8890 …');
  const rendered = await auditRendered(page);

  console.log('Auditing mockup …');
  const mockup = await auditMockup(page);

  await writeFile(
    resolve(outDir, 'metrics.json'),
    JSON.stringify({ viewport: VIEWPORT, rendered, mockup }, null, 2)
  );
  await writeIndexHtml(rendered, mockup);

  await browser.close();

  console.log(`\nDone.`);
  console.log(`  - ${rendered.length} rendered bands → ${renderedDir}`);
  console.log(`  - ${mockup.length} mockup sections → ${mockupDir}`);
  console.log(`  - Side-by-side: ${resolve(outDir, 'index.html')}`);
}

main().catch((e) => {
  console.error(e);
  process.exit(1);
});
