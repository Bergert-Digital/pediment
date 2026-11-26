#!/usr/bin/env node
/**
 * Pre-launch checks for a Pediment client site, run against its public URL.
 *
 * It covers the `[auto]` items of shared/launch-checklist.md with plain HTTP requests: no
 * browser, no WordPress access, no dependencies. Each result carries the checklist item ID,
 * so /pediment:launch can write it back into the client repo's docs/launch.md.
 *
 * A result is `pass`, `fail` (the site is not ready), or `review` (a person must decide, for
 * example whether an external host is covered by consent). HTML is read with regular
 * expressions on purpose: the checks look for tags WordPress prints in a known shape, and a
 * missed edge case shows up as `review` or `fail`, never as a silent pass.
 */

const USER_AGENT = 'pediment-launch-check';
const TIMEOUT_MS = 15000;

/** Text that only a page still carrying template or demo copy contains. */
const PLACEHOLDERS = [
  { label: 'Lorem ipsum', pattern: /lorem ipsum/i },
  { label: 'example.com', pattern: /\bexample\.(?:com|org|net)\b/i },
  { label: '[TODO', pattern: /\[TODO/ },
  { label: "Pediment's old demo footer copy", pattern: /Senior-led work for leaders navigating change|\+44 20 7946 0000/ },
  { label: "WordPress's sample page or first post", pattern: /This is an example page\.|Welcome to WordPress\. This is your first post\./ },
];

/** Hosts that only exist on a developer's machine. */
const DEV_HOST = /\/\/(localhost|127\.0\.0\.1|[a-z0-9-]+\.local)(?::\d+)?(?![a-z0-9.-])/gi;

/** Hosts that matter for consent even when a script, not markup, makes the request. */
const KNOWN_THIRD_PARTIES = [
  'fonts.googleapis.com', 'fonts.gstatic.com', 'use.typekit.net', 'fonts.bunny.net',
  'www.googletagmanager.com', 'www.google-analytics.com', 'google-analytics.com',
  'connect.facebook.net', 'www.facebook.com', 'static.hotjar.com', 'www.clarity.ms',
  'www.youtube.com', 'www.youtube-nocookie.com', 'player.vimeo.com',
  'maps.googleapis.com', 'maps.google.com', 'www.google.com', 'www.gstatic.com',
  's.w.org', 'cdn.jsdelivr.net', 'unpkg.com', 'cdnjs.cloudflare.com', 'ajax.googleapis.com',
];

const FONT_HOSTS = ['fonts.googleapis.com', 'fonts.gstatic.com', 'use.typekit.net', 'fonts.bunny.net'];

const LEGAL_NOTICE = /impressum|imprint|legal[\s_-]*notice|mentions[\s_-]*l[ée]gales|colofon/i;
const PRIVACY = /datenschutz|privacy|privacidad|confidentialit[ée]|privacybeleid/i;

const RESOURCE_TAGS = /<(script|link|iframe|img|source|video|audio|embed|object)\b[^>]*>/gi;
const LOADING_RELS = /\b(stylesheet|preconnect|dns-prefetch|preload|modulepreload|prefetch|icon|apple-touch-icon|manifest)\b/i;

export function decodeEntities(text) {
  return String(text)
    .replace(/&amp;/g, '&')
    .replace(/&#0*38;/g, '&')
    .replace(/&quot;/g, '"')
    .replace(/&#0*39;|&apos;/g, "'")
    .replace(/&lt;/g, '<')
    .replace(/&gt;/g, '>')
    .replace(/&nbsp;/g, ' ')
    .replace(/&#x([0-9a-f]+);/gi, (_, hex) => String.fromCodePoint(parseInt(hex, 16)))
    .replace(/&#(\d+);/g, (_, dec) => String.fromCodePoint(Number(dec)));
}

export function attrs(tag) {
  const out = {};
  const re = /([a-zA-Z_:][-a-zA-Z0-9_:.]*)\s*=\s*(?:"([^"]*)"|'([^']*)'|([^\s"'>]+))/g;
  for (const [, name, dq, sq, bare] of tag.matchAll(re)) {
    out[name.toLowerCase()] = decodeEntities(dq ?? sq ?? bare ?? '');
  }
  return out;
}

function stripTags(html) {
  return decodeEntities(html.replace(/<[^>]*>/g, ' ')).replace(/\s+/g, ' ').trim();
}

/** Visible text and attribute-free markup: scripts and styles are not page copy. */
function pageText(html) {
  return html
    .replace(/<script\b[\s\S]*?<\/script>/gi, ' ')
    .replace(/<style\b[\s\S]*?<\/style>/gi, ' ');
}

export function parsePage(html) {
  const metas = [...html.matchAll(/<meta\b[^>]*>/gi)].map(([tag]) => attrs(tag));
  const meta = (key) => {
    const found = metas.find((m) => (m.name || m.property || '').toLowerCase() === key);
    return found ? (found.content || '').trim() : null;
  };
  const titleMatch = html.match(/<title\b[^>]*>([\s\S]*?)<\/title>/i);
  const links = [...html.matchAll(/<link\b[^>]*>/gi)].map(([tag]) => attrs(tag));
  const anchors = [...html.matchAll(/<a\b([^>]*)>([\s\S]*?)<\/a>/gi)].map(([, attrText, inner]) => ({
    href: attrs(`<a ${attrText}>`).href || '',
    text: stripTags(inner),
  }));

  return {
    title: titleMatch ? stripTags(titleMatch[1]) : '',
    robots: (meta('robots') || '').toLowerCase(),
    description: meta('description'),
    ogImage: meta('og:image'),
    hasIcon: links.some((l) => /(^|\s)(shortcut\s+)?icon(\s|$)/i.test(l.rel || '')),
    stylesheets: links.filter((l) => /stylesheet/i.test(l.rel || '')).map((l) => l.href).filter(Boolean),
    anchors,
  };
}

/** Every URL the browser fetches on its own while it renders the page. */
export function resourceUrls(html) {
  const urls = [];
  for (const [tag, name] of html.matchAll(RESOURCE_TAGS)) {
    const a = attrs(tag);
    if (name.toLowerCase() === 'link' && !LOADING_RELS.test(a.rel || '')) continue;
    for (const key of ['src', 'href', 'data']) {
      if (a[key]) urls.push(a[key]);
    }
    if (a.srcset) {
      for (const part of a.srcset.split(',')) {
        const url = part.trim().split(/\s+/)[0];
        if (url) urls.push(url);
      }
    }
  }
  for (const [, url] of html.matchAll(/url\(\s*["']?([^"')\s]+)["']?\s*\)/gi)) urls.push(url);
  for (const [, url] of html.matchAll(/@import\s+(?:url\()?\s*["']([^"']+)["']/gi)) urls.push(url);
  return urls;
}

function hostOf(url, base) {
  try {
    return new URL(url, base).hostname.toLowerCase();
  } catch {
    return null;
  }
}

/** `www.example.org` and `example.org` are the same site. */
function sameSite(host, siteHost) {
  const bare = (h) => h.replace(/^www\./, '');
  return bare(host) === bare(siteHost);
}

export function externalHosts(html, base) {
  const siteHost = new URL(base).hostname.toLowerCase();
  const hosts = new Set();
  for (const url of resourceUrls(html)) {
    if (/^(data|blob|about|javascript|mailto|tel):/i.test(url)) continue;
    const host = hostOf(url, base);
    if (host && !sameSite(host, siteHost)) hosts.add(host);
  }
  // A link the visitor may click loads nothing; drop links before the text scan.
  const scannable = html
    .replace(/<a\b[^>]*>/gi, '<a>')
    .replace(/<link\b[^>]*>/gi, (tag) => (LOADING_RELS.test(attrs(tag).rel || '') ? tag : ''));
  for (const host of KNOWN_THIRD_PARTIES) {
    const escaped = host.replace(/\./g, '\\.');
    if (new RegExp(`(?:^|[^a-z0-9.-])${escaped}(?:[/"'\\\\:]|$)`, 'i').test(scannable) && !sameSite(host, siteHost)) {
      hosts.add(host);
    }
  }
  return [...hosts].sort();
}

/** Allow rules for `User-agent: *` that block the whole site. */
export function robotsBlocksSite(text) {
  let applies = false;
  let inAgents = false;
  for (const raw of String(text).split(/\r?\n/)) {
    const line = raw.replace(/#.*/, '').trim();
    if (!line) continue;
    const [field, ...rest] = line.split(':');
    const key = field.trim().toLowerCase();
    const value = rest.join(':').trim();
    if (key === 'user-agent') {
      if (!inAgents) applies = false;
      inAgents = true;
      if (value === '*') applies = true;
      continue;
    }
    inAgents = false;
    if (applies && key === 'disallow' && value === '/') return true;
  }
  return false;
}

export function sitemapLocs(xml) {
  return [...String(xml).matchAll(/<loc>\s*([^<\s]+)\s*<\/loc>/gi)].map(([, loc]) => decodeEntities(loc));
}

async function get(url, { redirect = 'follow', fetchImpl = fetch } = {}) {
  try {
    const response = await fetchImpl(url, {
      redirect,
      headers: { 'user-agent': USER_AGENT },
      signal: AbortSignal.timeout(TIMEOUT_MS),
    });
    const body = redirect === 'manual' ? '' : await response.text();
    return { ok: response.ok, status: response.status, headers: response.headers, url: response.url || url, body };
  } catch (error) {
    return { ok: false, status: 0, error: error.cause?.code || error.message, url, body: '' };
  }
}

function result(id, status, message, details = []) {
  return { id, status, message, details };
}

async function findSitemap(base, fetchImpl) {
  for (const path of ['/wp-sitemap.xml', '/sitemap_index.xml', '/sitemap.xml']) {
    const res = await get(new URL(path, base).href, { fetchImpl });
    if (res.ok && /<(sitemapindex|urlset)\b/i.test(res.body)) return { path, ...res };
  }
  return null;
}

/** Page URLs from a sitemap (index), pages before posts, same site only. */
async function pagesFromSitemap(sitemap, base, limit, fetchImpl) {
  const siteHost = new URL(base).hostname;
  const sameSiteUrl = (u) => {
    const host = hostOf(u, base);
    return host && sameSite(host, siteHost);
  };
  if (!/<sitemapindex\b/i.test(sitemap.body)) return sitemapLocs(sitemap.body).filter(sameSiteUrl).slice(0, limit);

  const children = sitemapLocs(sitemap.body)
    .filter(sameSiteUrl)
    .filter((u) => !/users|author/i.test(u))
    .sort((a, b) => Number(!/page/i.test(a)) - Number(!/page/i.test(b)));
  const urls = [];
  for (const child of children) {
    if (urls.length >= limit) break;
    const res = await get(child, { fetchImpl });
    if (res.ok) urls.push(...sitemapLocs(res.body).filter(sameSiteUrl));
  }
  return urls.slice(0, limit);
}

function findLegalLink(page, pattern, base) {
  const siteHost = new URL(base).hostname;
  return page.anchors.find((a) => {
    if (!a.href || a.href.startsWith('#')) return false;
    const host = hostOf(a.href, base);
    if (!host || !sameSite(host, siteHost)) return false;
    let path = '';
    try {
      path = decodeURIComponent(new URL(a.href, base).pathname);
    } catch {
      path = a.href;
    }
    return pattern.test(path) || pattern.test(a.text);
  });
}

async function checkLegalPage(id, label, pattern, pages, base, fetchImpl) {
  const missing = pages.filter((p) => !findLegalLink(p.parsed, pattern, base)).map((p) => p.url);
  const link = pages.map((p) => findLegalLink(p.parsed, pattern, base)).find(Boolean);
  if (!link) return result(id, 'fail', `No page links to the ${label}.`);

  const target = new URL(link.href, base).href;
  const res = await get(target, { fetchImpl });
  if (!res.ok) return result(id, 'fail', `The ${label} link (${target}) returns status ${res.status || res.error}.`);
  if (/\[TODO/.test(pageText(res.body))) {
    return result(id, 'fail', `The ${label} (${target}) still holds [TODO] placeholder text.`);
  }
  if (missing.length) {
    return result(id, 'fail', `The ${label} exists (${target}), but ${missing.length} page(s) do not link to it.`, missing);
  }
  return result(id, 'pass', `The ${label} (${target}) has content, and every checked page links to it.`);
}

export async function launchCheck(baseUrl, { maxPages = 30, fetchImpl = fetch } = {}) {
  const base = new URL(baseUrl).href;
  const results = [];

  const home = await get(base, { fetchImpl });
  if (!home.ok) {
    throw new Error(`Could not load ${base}: ${home.status ? `status ${home.status}` : home.error}.`);
  }

  // OPS-1
  if (new URL(home.url).protocol !== 'https:') {
    results.push(result('OPS-1', 'fail', `The site answers over plain HTTP (${home.url}). Launch on HTTPS.`));
  } else {
    const plain = new URL(home.url);
    plain.protocol = 'http:';
    const res = await get(plain.href, { redirect: 'manual', fetchImpl });
    const location = res.headers?.get('location') || '';
    if (res.status >= 300 && res.status < 400 && location.startsWith('https://')) {
      results.push(result('OPS-1', 'pass', `HTTPS works, and ${plain.origin} redirects to it.`));
    } else if (res.status === 0) {
      results.push(result('OPS-1', 'review', `HTTPS works. ${plain.origin} did not answer (${res.error}); check the redirect by hand.`));
    } else {
      results.push(result('OPS-1', 'fail', `${plain.origin} answers with status ${res.status} instead of redirecting to HTTPS.`));
    }
  }

  // Pages to check: the front page plus the sitemap's pages.
  const sitemap = await findSitemap(base, fetchImpl);
  const urls = [home.url];
  const listed = sitemap ? await pagesFromSitemap(sitemap, base, maxPages, fetchImpl) : [];
  if (sitemap) {
    for (const url of listed) {
      if (!urls.includes(url)) urls.push(url);
    }
  }
  const pages = [{ url: home.url, res: home }];
  for (const url of urls.slice(1, maxPages)) {
    const res = await get(url, { fetchImpl });
    if (res.ok) pages.push({ url, res });
  }
  for (const page of pages) page.parsed = parsePage(page.res.body);

  // LEGAL-1, LEGAL-2
  results.push(await checkLegalPage('LEGAL-1', 'Impressum (legal notice)', LEGAL_NOTICE, pages, base, fetchImpl));
  results.push(await checkLegalPage('LEGAL-2', 'privacy policy', PRIVACY, pages, base, fetchImpl));

  // PRIV-1, PRIV-2 — the pages plus their same-site stylesheets (fonts are often loaded there).
  const hosts = new Set();
  const fontHits = new Set();
  const seenCss = new Set();
  for (const page of pages) {
    for (const host of externalHosts(page.res.body, page.url)) hosts.add(host);
    for (const href of page.parsed.stylesheets) {
      const cssUrl = new URL(href, page.url).href;
      const host = hostOf(cssUrl, base);
      if (!host || !sameSite(host, new URL(base).hostname) || seenCss.has(cssUrl) || seenCss.size >= 20) continue;
      seenCss.add(cssUrl);
      const css = await get(cssUrl, { fetchImpl });
      if (css.ok) for (const h of externalHosts(css.body, cssUrl)) hosts.add(h);
    }
  }
  for (const host of hosts) if (FONT_HOSTS.includes(host)) fontHits.add(host);
  results.push(hosts.size
    ? result('PRIV-1', 'review', `The pages load from ${hosts.size} external host(s). Each needs consent first, or a reason it does not (and a line in the privacy policy).`, [...hosts].sort())
    : result('PRIV-1', 'pass', 'The checked pages and their stylesheets load nothing from another host.'));
  results.push(fontHits.size
    ? result('PRIV-2', 'fail', 'Fonts load from a third-party font service. Host them on the site.', [...fontHits].sort())
    : result('PRIV-2', 'pass', 'No font loads from a third-party font service.'));

  // SEO-1
  const noindex = pages.filter((p) => /noindex/i.test(p.parsed.robots) || /noindex/i.test(p.res.headers?.get?.('x-robots-tag') || ''));
  results.push(noindex.length
    ? result('SEO-1', 'fail', `${noindex.length} page(s) tell search engines not to index them. Check Settings → Reading → "Discourage search engines".`, noindex.map((p) => p.url))
    : result('SEO-1', 'pass', 'No checked page has noindex.'));

  // SEO-2
  const robots = await get(new URL('/robots.txt', base).href, { fetchImpl });
  if (!robots.ok) {
    results.push(result('SEO-2', 'review', `robots.txt returns status ${robots.status || robots.error}. WordPress serves one by default; check what replaced it.`));
  } else if (robotsBlocksSite(robots.body)) {
    results.push(result('SEO-2', 'fail', 'robots.txt blocks the whole site for every crawler (Disallow: /).'));
  } else {
    results.push(result('SEO-2', 'pass', 'robots.txt does not block the site.'));
  }

  // SEO-3
  results.push(sitemap
    ? result('SEO-3', listed.length ? 'pass' : 'review', listed.length
      ? `The sitemap ${sitemap.path} loads and lists ${listed.length}${listed.length >= maxPages ? ' or more' : ''} page URL(s).`
      : `The sitemap ${sitemap.path} loads but lists no page of this site.`)
    : result('SEO-3', 'fail', 'No sitemap at /wp-sitemap.xml, /sitemap_index.xml or /sitemap.xml.'));

  // SEO-4
  const noTitle = pages.filter((p) => !p.parsed.title).map((p) => p.url);
  const noDescription = pages.filter((p) => !p.parsed.description).map((p) => p.url);
  const byTitle = new Map();
  for (const p of pages) if (p.parsed.title) byTitle.set(p.parsed.title, [...(byTitle.get(p.parsed.title) || []), p.url]);
  const duplicates = [...byTitle].filter(([, list]) => list.length > 1).map(([title, list]) => `"${title}": ${list.join(', ')}`);
  if (noTitle.length || noDescription.length) {
    results.push(result('SEO-4', 'fail',
      `${noTitle.length} page(s) without a <title>, ${noDescription.length} without a meta description (set the page excerpt).`,
      [...noTitle.map((u) => `no title: ${u}`), ...noDescription.map((u) => `no description: ${u}`)]));
  } else if (duplicates.length) {
    results.push(result('SEO-4', 'review', 'Every page has a title and a description, but some titles repeat.', duplicates));
  } else {
    results.push(result('SEO-4', 'pass', `All ${pages.length} checked page(s) have their own title and a meta description.`));
  }

  // SEO-5, SEO-6
  const homeParsed = pages[0].parsed;
  results.push(homeParsed.ogImage
    ? result('SEO-5', 'pass', `The front page shares an image (${homeParsed.ogImage}).`)
    : result('SEO-5', 'fail', 'The front page has no og:image. Set a featured image or the default share image.'));
  results.push(homeParsed.hasIcon
    ? result('SEO-6', 'pass', 'The site has a site icon.')
    : result('SEO-6', 'fail', 'The front page links no site icon (favicon).'));

  // SEO-7
  const missingUrl = new URL(`/pediment-launch-check-${Date.now().toString(36)}/`, base).href;
  const notFound = await get(missingUrl, { fetchImpl });
  results.push(notFound.status === 404
    ? result('SEO-7', 'pass', 'An unknown URL returns status 404.')
    : result('SEO-7', 'fail', `An unknown URL returns status ${notFound.status || notFound.error}, not 404.`, [missingUrl]));

  // QA-1, QA-6
  const placeholders = [];
  const devUrls = [];
  for (const page of pages) {
    const text = pageText(page.res.body);
    for (const { label, pattern } of PLACEHOLDERS) if (pattern.test(text)) placeholders.push(`${label}: ${page.url}`);
    const siteHost = new URL(page.url).hostname.toLowerCase();
    const found = new Set([...page.res.body.matchAll(DEV_HOST)].map(([, host]) => host.toLowerCase()));
    for (const host of found) if (host !== siteHost) devUrls.push(`${host}: ${page.url}`);
  }
  results.push(placeholders.length
    ? result('QA-1', 'fail', 'Placeholder text remains on the site.', placeholders)
    : result('QA-1', 'pass', 'No placeholder text found on the checked pages.'));
  results.push(devUrls.length
    ? result('QA-6', 'fail', 'Development URLs remain in the pages.', devUrls)
    : result('QA-6', 'pass', 'No localhost or .local URL found. Search for the staging host by hand.'));

  return { url: base, pages: pages.map((p) => p.url), results };
}

export function formatReport(report) {
  const lines = [`Launch check for ${report.url} (${report.pages.length} page(s) checked)`, ''];
  for (const r of report.results) {
    lines.push(`${r.status.toUpperCase().padEnd(7)} ${r.id.padEnd(8)} ${r.message}`);
    for (const detail of r.details) lines.push(`${' '.repeat(17)}- ${detail}`);
  }
  const count = (s) => report.results.filter((r) => r.status === s).length;
  lines.push('', `${count('fail')} fail, ${count('review')} review, ${count('pass')} pass`);
  return lines.join('\n');
}

function parseArgs(argv) {
  const out = { json: false, maxPages: 30 };
  for (let i = 0; i < argv.length; i += 1) {
    const arg = argv[i];
    if (arg === '--url') out.url = argv[++i];
    else if (arg === '--json') out.json = true;
    else if (arg === '--max-pages') out.maxPages = Number(argv[++i]);
    else throw new Error(`Unknown argument: ${arg}`);
  }
  if (!out.url || !/^https?:\/\//.test(out.url)) {
    throw new Error('Usage: launch-check.mjs --url <https://site> [--max-pages <n>] [--json]');
  }
  if (!Number.isInteger(out.maxPages) || out.maxPages < 1) throw new Error('--max-pages needs a whole number of 1 or more.');
  return out;
}

if (process.argv[1] && process.argv[1].endsWith('launch-check.mjs')) {
  try {
    const args = parseArgs(process.argv.slice(2));
    const report = await launchCheck(args.url, { maxPages: args.maxPages });
    console.log(args.json ? JSON.stringify(report, null, 2) : formatReport(report));
    process.exitCode = report.results.some((r) => r.status === 'fail') ? 1 : 0;
  } catch (error) {
    console.error(error.message);
    process.exitCode = 2;
  }
}
