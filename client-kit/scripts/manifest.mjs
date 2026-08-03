/**
 * Renders a client theme's seed/manifest.php from the /start answers file.
 *
 * The format is validated strictly by the plugin — unrecognised top-level or
 * per-entry keys are a hard ManifestError, never a silently-skipped section.
 * See docs/seeding.md in the pediment monorepo for the contract this emits.
 */

const INDENT = '\t';

export function phpString(value) {
  return "'" + String(value).replace(/\\/g, '\\\\').replace(/'/g, "\\'") + "'";
}

/** Neutralise a comment terminator so a value cannot close the docblock it sits in. */
export function phpComment(value) {
  return String(value).replace(/\*\//g, '* /');
}

/** Default language first — the engine re-orders anyway, and order is load-bearing. */
function orderedLanguages(languages) {
  const list = [...(languages || [])];
  const defaultIndex = list.findIndex((l) => l.default);
  if (defaultIndex > 0) list.unshift(list.splice(defaultIndex, 1)[0]);
  return list;
}

function renderLanguages(languages) {
  const lines = [`${INDENT}'languages' => array(`];
  for (const lang of languages) {
    const parts = [`'name' => ${phpString(lang.name || lang.slug.toUpperCase())}`,
      `'locale' => ${phpString(lang.locale)}`];
    if (lang.flag) parts.push(`'flag' => ${phpString(lang.flag)}`);
    if (lang.default) parts.push("'default' => true");
    lines.push(`${INDENT}${INDENT}${phpString(lang.slug)} => array( ${parts.join(', ')} ),`);
  }
  lines.push(`${INDENT}),`);
  return lines;
}

function renderPages(pages, slug) {
  const lines = [`${INDENT}'pages' => array(`];
  for (const page of pages) {
    const parts = [`'title' => ${phpString(page.title)}`];
    parts.push(page.postsPage
      ? "'content' => ''"
      : `'pattern' => ${phpString(`${slug}/${page.key}`)}`);
    if (page.frontPage) parts.push("'front_page' => true");
    if (page.postsPage) parts.push("'posts_page' => true");
    lines.push(`${INDENT}${INDENT}${phpString(page.key)} => array( ${parts.join(', ')} ),`);
  }
  lines.push(`${INDENT}),`);
  return lines;
}

function renderNav(nav) {
  return [
    `${INDENT}'navs' => array(`,
    `${INDENT}${INDENT}'primary' => array(`,
    `${INDENT}${INDENT}${INDENT}'title' => 'Header Navigation',`,
    // No 'label': NavSeeder::serialize() falls back to the linked entry's own
    // post_title, which is already per-language. A fixed label would render the
    // same text in every language.
    `${INDENT}${INDENT}${INDENT}'items' => array(`,
    ...nav.map((key) => `${INDENT}${INDENT}${INDENT}${INDENT}array( 'entry' => ${phpString(key)} ),`),
    `${INDENT}${INDENT}${INDENT}),`,
    `${INDENT}${INDENT}),`,
    `${INDENT}),`,
  ];
}

export function renderManifest(answers) {
  const slug = answers.client.slug;
  const pages = answers.pages || [];
  const nav = answers.nav || [];

  if (pages.filter((p) => p.frontPage).length > 1) {
    throw new Error('At most one page may be the front page.');
  }
  if (pages.filter((p) => p.postsPage).length > 1) {
    throw new Error('At most one page may be the posts page.');
  }
  const keys = new Set(pages.map((p) => p.key));
  for (const item of nav) {
    if (!keys.has(item)) {
      throw new Error(`Nav item "${item}" is not a declared page.`);
    }
  }

  const languages = orderedLanguages(answers.languages);
  const lines = [
    '<?php',
    '/**',
    ` * Seed manifest for ${phpComment(answers.client.name)}.`,
    ' *',
    ' * Structure lives here; content lives in patterns/. Run `npm run seed:plan`',
    ' * to see what a seed would change before running `npm run seed`.',
    ' *',
    ` * @package ${phpComment(slug)}`,
    ' */',
    '',
    'return array(',
    `${INDENT}'version' => 1,`,
  ];

  if (languages.length > 1) lines.push(...renderLanguages(languages));

  if (answers.logo && answers.logo.file) {
    lines.push(
      `${INDENT}'media' => array(`,
      `${INDENT}${INDENT}'logo' => array( 'file' => ${phpString('seed/media/' + answers.logo.file)}, 'title' => ${phpString(answers.client.name + ' logo')} ),`,
      `${INDENT}),`,
      `${INDENT}'site' => array( 'logo' => 'logo' ),`,
    );
  }

  lines.push(...renderPages(pages, slug));
  if (nav.length) lines.push(...renderNav(nav));
  lines.push(');', '');

  return lines.join('\n');
}
