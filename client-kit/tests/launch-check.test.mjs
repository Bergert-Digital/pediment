import { test } from 'node:test';
import assert from 'node:assert/strict';
import {
  attrs,
  externalHosts,
  formatReport,
  launchCheck,
  parsePage,
  robotsBlocksSite,
  sitemapLocs,
} from '../scripts/launch-check.mjs';

const BASE = 'https://acme.test/';

function page({ title, description = 'One or two sentences about this page.', head = '', body = '' }) {
  return `<!doctype html><html><head>
<title>${title}</title>
${description === null ? '' : `<meta name="description" content="${description}" />`}
<meta property="og:image" content="https://acme.test/wp-content/uploads/share.jpg" />
<link rel="icon" href="https://acme.test/wp-content/uploads/icon.png" sizes="32x32" />
<link rel="stylesheet" href="/wp-content/themes/acme/style.css" />
${head}
</head><body>${body}
<footer><a href="https://acme.test/impressum/">Impressum</a> · <a href="https://acme.test/datenschutz/">Datenschutz</a></footer>
</body></html>`;
}

function goodSite() {
  return {
    'https://acme.test/': page({ title: 'Acme Roofing', body: '<h1>Roofs</h1>' }),
    'http://acme.test/': { status: 301, headers: { location: 'https://acme.test/' } },
    'https://acme.test/robots.txt': 'User-agent: *\nDisallow: /wp-admin/\nAllow: /wp-admin/admin-ajax.php\n',
    'https://acme.test/wp-sitemap.xml':
      '<?xml version="1.0"?><sitemapindex><sitemap><loc>https://acme.test/wp-sitemap-posts-page-1.xml</loc></sitemap>' +
      '<sitemap><loc>https://acme.test/wp-sitemap-users-1.xml</loc></sitemap></sitemapindex>',
    'https://acme.test/wp-sitemap-posts-page-1.xml':
      '<?xml version="1.0"?><urlset><url><loc>https://acme.test/</loc></url>' +
      '<url><loc>https://acme.test/about/</loc></url>' +
      '<url><loc>https://acme.test/impressum/</loc></url>' +
      '<url><loc>https://acme.test/datenschutz/</loc></url></urlset>',
    'https://acme.test/about/': page({ title: 'About – Acme Roofing' }),
    'https://acme.test/impressum/': page({ title: 'Impressum – Acme Roofing', body: '<p>Acme Roofing GmbH, Musterstraße 1</p>' }),
    'https://acme.test/datenschutz/': page({ title: 'Datenschutz – Acme Roofing', body: '<p>Verantwortlich ist …</p>' }),
    'https://acme.test/wp-content/themes/acme/style.css': 'body{font-family:Inter}@font-face{src:url(/fonts/inter.woff2)}',
  };
}

/** A fetch that serves an in-memory site; unknown URLs are 404. */
function fakeFetch(routes) {
  return async (url, { redirect } = {}) => {
    const route = routes[url];
    if (route === undefined) return new Response('<title>Not found</title>', { status: 404 });
    if (typeof route === 'string') return new Response(route, { status: 200 });
    if (route.status >= 300 && route.status < 400 && redirect !== 'manual') {
      return fakeFetch(routes)(route.headers.location, { redirect });
    }
    return new Response(route.body || '', { status: route.status, headers: route.headers });
  };
}

const byId = (report) => Object.fromEntries(report.results.map((r) => [r.id, r]));

test('a site that is ready passes every automatic check', async () => {
  const report = await launchCheck(BASE, { fetchImpl: fakeFetch(goodSite()) });
  const failing = report.results.filter((r) => r.status !== 'pass');
  assert.deepEqual(failing, [], formatReport(report));
  assert.ok(report.pages.includes('https://acme.test/about/'));
  assert.ok(!report.pages.some((u) => /users/.test(u)), 'the users sitemap is skipped');
});

test('every result names a checklist item that exists and is marked [auto]', async () => {
  const { readFile } = await import('node:fs/promises');
  const checklist = await readFile(new URL('../shared/launch-checklist.md', import.meta.url), 'utf8');
  const report = await launchCheck(BASE, { fetchImpl: fakeFetch(goodSite()) });
  for (const { id } of report.results) {
    assert.match(checklist, new RegExp(`\\*\\*${id}\\*\\* \`\\[auto\\]\``), `${id} is not an [auto] item in the checklist`);
  }
  const autoIds = [...checklist.matchAll(/\*\*([A-Z]+-\d+)\*\* `\[auto\]`/g)].map(([, id]) => id);
  assert.deepEqual([...autoIds].sort(), report.results.map((r) => r.id).sort(), 'an [auto] item has no check');
});

test('the classic launch mistakes fail', async () => {
  const site = goodSite();
  site['https://acme.test/'] = page({
    title: 'Acme Roofing',
    head: "<meta name='robots' content='noindex, nofollow' />" +
      '<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter" />',
    body: '<p>Lorem ipsum dolor sit amet. Write to hello@example.com.</p><img src="http://localhost:8888/wp-content/uploads/a.jpg">',
  });
  site['https://acme.test/about/'] = page({ title: 'About – Acme Roofing', description: null, body: '<p>This is an example page. It is different from a blog post.</p>' });
  site['https://acme.test/impressum/'] = page({ title: 'Impressum', body: '<p>[TODO: paste the Impressum]</p>' });
  site['https://acme.test/robots.txt'] = 'User-agent: *\nDisallow: /\n';
  site['http://acme.test/'] = { status: 200, body: 'plain' };

  const r = byId(await launchCheck(BASE, { fetchImpl: fakeFetch(site) }));
  assert.equal(r['SEO-1'].status, 'fail');
  assert.deepEqual(r['SEO-1'].details, ['https://acme.test/']);
  assert.equal(r['SEO-2'].status, 'fail');
  assert.equal(r['SEO-4'].status, 'fail');
  assert.match(r['SEO-4'].details.join('\n'), /no description: https:\/\/acme\.test\/about\//);
  assert.equal(r['PRIV-2'].status, 'fail');
  assert.equal(r['PRIV-1'].status, 'review');
  assert.ok(r['PRIV-1'].details.includes('fonts.googleapis.com'));
  assert.equal(r['LEGAL-1'].status, 'fail');
  assert.match(r['LEGAL-1'].message, /\[TODO\]/);
  assert.equal(r['LEGAL-2'].status, 'pass');
  assert.equal(r['QA-1'].status, 'fail');
  assert.match(r['QA-1'].details.join('\n'), /Lorem ipsum/);
  assert.match(r['QA-1'].details.join('\n'), /example\.com/);
  assert.match(r['QA-1'].details.join('\n'), /sample page or first post: https:\/\/acme\.test\/about\//);
  assert.equal(r['QA-6'].status, 'fail');
  assert.equal(r['OPS-1'].status, 'fail');
});

test('missing legal links, sitemap, icon, share image and 404 fail', async () => {
  const bare = '<!doctype html><html><head><title>Acme</title><meta name="description" content="x"></head><body><p>Hi</p></body></html>';
  const site = {
    'https://acme.test/': bare,
    'http://acme.test/': { status: 301, headers: { location: 'https://acme.test/' } },
    'https://acme.test/robots.txt': 'User-agent: *\nDisallow:\n',
  };
  const fetchImpl = async (url, opts) => {
    if (url.includes('pediment-launch-check-')) return new Response(bare, { status: 200 });
    return fakeFetch(site)(url, opts);
  };

  const r = byId(await launchCheck(BASE, { fetchImpl }));
  assert.equal(r['LEGAL-1'].status, 'fail');
  assert.equal(r['LEGAL-2'].status, 'fail');
  assert.equal(r['SEO-3'].status, 'fail');
  assert.equal(r['SEO-5'].status, 'fail');
  assert.equal(r['SEO-6'].status, 'fail');
  assert.equal(r['SEO-7'].status, 'fail');
  assert.equal(r['SEO-2'].status, 'pass', 'an empty Disallow allows everything');
});

test('a page without the legal links fails even when another page has them', async () => {
  const site = goodSite();
  site['https://acme.test/about/'] = '<!doctype html><html><head><title>About</title><meta name="description" content="x"></head><body></body></html>';
  const r = byId(await launchCheck(BASE, { fetchImpl: fakeFetch(site) }));
  assert.equal(r['LEGAL-1'].status, 'fail');
  assert.deepEqual(r['LEGAL-1'].details, ['https://acme.test/about/']);
});

test('the site fails to load: launchCheck throws with the status', async () => {
  await assert.rejects(launchCheck(BASE, { fetchImpl: fakeFetch({}) }), /status 404/);
});

test('robotsBlocksSite reads only the * group', () => {
  assert.equal(robotsBlocksSite('User-agent: *\nDisallow: /'), true);
  assert.equal(robotsBlocksSite('User-agent: BadBot\nDisallow: /\n\nUser-agent: *\nDisallow: /wp-admin/'), false);
  assert.equal(robotsBlocksSite('User-agent: Googlebot\nUser-agent: *\nDisallow: / # all'), true);
  assert.equal(robotsBlocksSite(''), false);
});

test('externalHosts ignores links, same-site hosts and data URLs, and finds hosts in scripts', () => {
  const html = `
    <a href="https://www.instagram.com/acme">Instagram</a>
    <img src="https://www.acme.test/logo.png" srcset="https://cdn.other.test/a.jpg 2x, /b.jpg 1x">
    <img src="data:image/png;base64,AAAA">
    <link rel="canonical" href="https://elsewhere.test/">
    <link rel="preconnect" href="https://fonts.gstatic.com">
    <iframe src="https://www.youtube.com/embed/xyz"></iframe>
    <script>window._wpemojiSettings = {"baseUrl":"https:\\/\\/s.w.org\\/images\\/core\\/emoji\\/"}</script>
    <div style="background-image:url('https://img.other.test/bg.jpg')"></div>`;
  assert.deepEqual(externalHosts(html, BASE), [
    'cdn.other.test', 'fonts.gstatic.com', 'img.other.test', 's.w.org', 'www.youtube.com',
  ]);
});

test('a link to a third party is not a request to it', () => {
  const html = `<a href="https://www.google.com/maps/dir/?api=1&amp;destination=Acme">Directions</a>
    <link rel="alternate" hreflang="en" href="https://www.google.com/">`;
  assert.deepEqual(externalHosts(html, BASE), []);
});

test('a site checked on its own local host does not flag that host as a development URL', async () => {
  const local = {};
  for (const [url, body] of Object.entries(goodSite())) {
    local[url.replace('https://acme.test', 'http://acme.local')] =
      typeof body === 'string' ? body.replaceAll('https://acme.test', 'http://acme.local') : body;
  }
  local['http://acme.local/about/'] = local['http://acme.local/about/'].replace('</body>', '<img src="http://localhost:8888/a.jpg"></body>');
  const r = byId(await launchCheck('http://acme.local/', { fetchImpl: fakeFetch(local) }));
  assert.equal(r['QA-6'].status, 'fail');
  assert.deepEqual(r['QA-6'].details, ['localhost: http://acme.local/about/']);
});

test('parsePage and attrs read WordPress-shaped markup', () => {
  assert.deepEqual(attrs(`<meta name='robots' content="max-image-preview:large" />`), {
    name: 'robots',
    content: 'max-image-preview:large',
  });
  const parsed = parsePage(`<title>A &amp; B &#8211; Site</title><link rel="shortcut icon" href="/i.png"><a href="/x/"><span>Impressum</span></a>`);
  assert.equal(parsed.title, 'A & B – Site');
  assert.equal(parsed.hasIcon, true);
  assert.deepEqual(parsed.anchors, [{ href: '/x/', text: 'Impressum' }]);
  assert.deepEqual(sitemapLocs('<loc> https://a.test/?p=1&amp;x=2 </loc>'), ['https://a.test/?p=1&x=2']);
});
