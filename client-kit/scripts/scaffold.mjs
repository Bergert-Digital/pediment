#!/usr/bin/env node
/**
 * Scaffold a standalone Pediment client theme repo from the client template.
 *
 * A pure function of one JSON answers file, by design: the /start skill owns
 * everything that needs judgment, this owns everything that must be identical
 * every time. That boundary is what makes scaffolding unit-testable without a
 * browser, Docker or wp-env.
 *
 * Rewriting is token-driven, never knowledge-driven — the template ships
 * literal __PEDIMENT_*__ placeholders and this replaces all of them blindly,
 * so a new template file carrying a token needs no change here.
 */

import { cp, mkdir, readdir, readFile, rm, stat, writeFile } from 'node:fs/promises';
import { existsSync, readdirSync, statSync } from 'node:fs';
import { execFileSync } from 'node:child_process';
import { tmpdir } from 'node:os';
import path from 'node:path';
import { themeJsonSettings } from './brand.mjs';
import { renderManifest } from './manifest.mjs';

export const TOKENS = Object.freeze([
  '__PEDIMENT_SLUG__',
  '__PEDIMENT_NAME__',
  '__PEDIMENT_DESCRIPTION__',
  '__PEDIMENT_PLUGIN_VERSION__',
  '__PEDIMENT_TEMPLATE_VERSION__',
]);

const RELEASE_BASE = 'https://github.com/Bergert-Digital/pediment/releases';
/** Files copied verbatim — token replacement would corrupt them. */
const BINARY = /\.(png|jpe?g|gif|webp|avif|ico|woff2?|ttf|otf|zip|pdf)$/i;

export function replaceTokens(text, values) {
  let out = text;
  for (const token of TOKENS) {
    if (Object.hasOwn(values, token)) {
      out = out.split(token).join(values[token]);
    }
  }
  return out;
}

export function validateSlug(slug) {
  if (!/^[a-z0-9]+(-[a-z0-9]+)*$/.test(String(slug || ''))) {
    throw new Error(
      `"${slug}" is not a usable theme slug. Use lowercase letters, digits and single hyphens ` +
      '(e.g. "acme-roofing") — WordPress derives the stylesheet identifier from it.',
    );
  }
}

export function validateTarget(target) {
  if (/\s/.test(target)) {
    throw new Error(
      `Target path "${target}" contains whitespace.\n\n` +
      '  WordPress derives the theme stylesheet identifier from the directory name, and the\n' +
      "  Site Editor's template-part edit URLs are built as ?p=<stylesheet>//<slug>, which\n" +
      '  WordPress\'s JS routing cannot parse when <stylesheet> contains a space.\n\n' +
      '  Use a lowercase-hyphenated directory name instead.',
    );
  }
  if (existsSync(target)) {
    if (!statSync(target).isDirectory()) {
      throw new Error(`Target "${target}" exists and is not a directory.`);
    }
    if (readdirSync(target).length > 0) {
      throw new Error(`Target directory "${target}" is not empty. Refusing to scaffold into it.`);
    }
  }
}

/**
 * WordPress derives the in-container theme directory from the mounted path's basename
 * (client-template/.wp-env.json sets "themes": ["."]), and package.json's env:start script runs
 * `wp theme activate <slug>`. A target whose basename differs from the slug boots wp-env and then
 * fails theme activation, after the repo has already been committed.
 */
export function validateTargetMatchesSlug(target, slug) {
  const base = path.basename(target);
  if (base !== slug) {
    throw new Error(
      `Target directory "${base}" does not match client slug "${slug}".\n\n` +
      '  wp-env derives the theme directory it mounts inside the container from the target\n' +
      '  directory\'s own basename, and package.json runs `wp theme activate <slug>` against\n' +
      '  that container — so a mismatched directory name boots wp-env and then fails to\n' +
      '  activate the theme, after the repo is already committed.\n\n' +
      `  Name the target directory exactly "${slug}", or change client.slug to match it.`,
    );
  }
}

/**
 * `client.name` and `client.description` are interpolated as free text into JSON (package.json)
 * and into block-pattern attribute JSON (patterns/*.php, e.g. the hero's "headline"/"subheadline")
 * with no escaping. A double quote breaks both: it invalidates package.json outright, and it
 * makes WordPress's block parser fail to json_decode the attribute blob, silently nulling the
 * attributes instead of erroring. A backslash does the same. Rejecting is simpler and more honest
 * than per-context escaping — the questionnaire can just ask again.
 */
export function validateFreeText(field, value) {
  if (value == null) return;
  if (/["\\]/.test(String(value))) {
    throw new Error(
      `${field} "${value}" contains a double quote (") or backslash (\\).\n\n` +
      '  This value is interpolated verbatim into JSON (package.json) and into block-pattern\n' +
      "  attribute JSON (patterns/*.php) with no escaping. Either character breaks both: it's\n" +
      '  invalid JSON in package.json, and it makes WordPress\'s block parser fail to decode the\n' +
      '  pattern\'s attributes, silently rendering with none of them.\n\n' +
      '  Remove it and try again.',
    );
  }
}

async function walk(dir, base = dir) {
  const out = [];
  for (const entry of await readdir(dir, { withFileTypes: true })) {
    const full = path.join(dir, entry.name);
    if (entry.isDirectory()) {
      out.push(...await walk(full, base));
    } else {
      out.push(path.relative(base, full));
    }
  }
  return out;
}

export async function copyTemplate(srcDir, destDir, values, opts = {}) {
  const keepPages = opts.keepPages || null;
  const written = [];

  for (const rel of await walk(srcDir)) {
    if (keepPages) {
      const match = rel.match(/^patterns[/\\]([^/\\.]+)\.php$/);
      if (match && !keepPages.includes(match[1])) continue;
    }

    const src = path.join(srcDir, rel);
    const destRel = replaceTokens(rel, values);
    const dest = path.join(destDir, destRel);

    const relativeToDestDir = path.relative(destDir, dest);
    if (relativeToDestDir.startsWith('..') || path.isAbsolute(relativeToDestDir)) {
      throw new Error(`Refusing to write outside the target directory: ${rel} resolves to ${dest}`);
    }

    await mkdir(path.dirname(dest), { recursive: true });

    if (BINARY.test(rel)) {
      await cp(src, dest);
    } else {
      await writeFile(dest, replaceTokens(await readFile(src, 'utf8'), values));
    }
    written.push(destRel);
  }

  return written;
}

/**
 * `renderPages()` in manifest.mjs emits `'pattern' => '<slug>/<key>'` for every non-postsPage
 * page, and the seeder throws `ManifestError` — aborting the *entire* seed run, including pages
 * that would have been fine — the first time it looks up a pattern slug that never registered
 * because the template shipped no `patterns/<key>.php` for it. Catching that here, before any
 * file is written, turns a clean-looking scaffold that dies on the first seed into an honest
 * refusal naming exactly which page keys need a pattern file.
 */
export function validatePagesHavePatterns(srcDir, pages) {
  const missing = (pages || [])
    .filter((page) => !page.postsPage)
    .filter((page) => !existsSync(path.join(srcDir, 'patterns', `${page.key}.php`)))
    .map((page) => page.key);

  if (missing.length) {
    throw new Error(
      `No patterns/<key>.php in the template for page key(s): ${missing.join(', ')}.\n\n` +
      '  Every non-blog page needs a matching patterns/<key>.php in the template, or the seeder\n' +
      '  throws on that page and the whole seed run aborts — including the pages that were fine.\n\n' +
      '  Add the missing pattern file(s) with `/pediment:port-page` after the first seed, or drop\n' +
      '  the page from the answers file.',
    );
  }
}

export async function assertNoTokens(destDir) {
  const offenders = [];
  for (const rel of await walk(destDir)) {
    if (BINARY.test(rel)) continue;
    const found = (await readFile(path.join(destDir, rel), 'utf8')).match(/__[A-Z0-9_]+__/g);
    if (found) offenders.push(`${rel}: ${[...new Set(found)].join(', ')}`);
  }
  if (offenders.length) {
    throw new Error(
      'Unreplaced template tokens survived scaffolding:\n  ' + offenders.join('\n  ') +
      '\n\nAdd the token to TOKENS in scaffold.mjs, or remove it from the template.',
    );
  }
}

export async function resolveTemplate({ template, version, cacheDir } = {}) {
  if (template) {
    let info;
    try {
      info = await stat(template);
    } catch {
      throw new Error(`--template "${template}" is not a directory.`);
    }
    if (!info.isDirectory()) throw new Error(`--template "${template}" is not a directory.`);
    return template;
  }

  if (!version) {
    throw new Error('Either --template <dir> or --plugin-version <x.y.z> is required.');
  }

  try {
    execFileSync('unzip', ['-v'], { stdio: 'ignore' });
  } catch {
    throw new Error('`unzip` is required to download the client template. Install it, or pass --template <dir>.');
  }

  const dir = cacheDir || path.join(tmpdir(), `pediment-template-${version}`);
  await mkdir(dir, { recursive: true });
  const zip = path.join(dir, 'client-template.zip');
  const url = `${RELEASE_BASE}/download/v${version}/pediment-client-template.zip`;

  const response = await fetch(url);
  if (!response.ok) {
    throw new Error(`Could not download ${url} (HTTP ${response.status}). Pass --template <dir> to scaffold from a local checkout.`);
  }
  await writeFile(zip, Buffer.from(await response.arrayBuffer()));
  await rm(path.join(dir, 'client-template'), { recursive: true, force: true });
  execFileSync('unzip', ['-q', zip, '-d', dir], { stdio: 'inherit' });

  return path.join(dir, 'client-template');
}

const POLYLANG = 'https://downloads.wordpress.org/plugin/polylang.3.8.6.zip';

function briefMarkdown(answers) {
  const { client, brief, brand, languages, pages } = answers;
  return [
    `# ${client.name} — brief`,
    '',
    'Written by `/pediment:start`. This is the durable record of what the site is for.',
    'It is read by humans and by agents working in this repo; **nothing reads this file ' +
      'programmatically**, so editing it changes documentation, not behaviour.',
    '',
    '## What they do',
    '',
    brief.does,
    '',
    '## Who for',
    '',
    brief.audience,
    '',
    '## Tone',
    '',
    brief.tone,
    '',
    '## Languages',
    '',
    ...languages.map((l) => `- ${l.name} (\`${l.slug}\`, ${l.locale})${l.default ? ' — default' : ''}`),
    '',
    '## Pages at launch',
    '',
    ...pages.map((p) => `- ${p.title} (\`${p.key}\`)`),
    '',
    '## Brand',
    '',
    `- Accent: \`${brand.accent}\``,
    `- Primary: \`${brand.primary || 'Pediment default'}\``,
    `- Type: ${brand.font && brand.font.family ? brand.font.family : 'Pediment default'}`,
    `- Source: ${brand.source === 'chosen' ? 'chosen during /start' : brand.source}`,
    '',
  ].join('\n');
}

export async function scaffold(answers, opts) {
  const { target, template, git = true } = opts;

  validateSlug(answers.client.slug);
  validateTarget(target);
  validateTargetMatchesSlug(target, answers.client.slug);
  validateFreeText('client.name', answers.client.name);
  validateFreeText('client.description', answers.client.description);

  const values = {
    __PEDIMENT_SLUG__: answers.client.slug,
    __PEDIMENT_NAME__: answers.client.name,
    __PEDIMENT_DESCRIPTION__: answers.client.description || `${answers.client.name} — built with Pediment.`,
    __PEDIMENT_PLUGIN_VERSION__: answers.plugin.version,
    __PEDIMENT_TEMPLATE_VERSION__: (answers.template && answers.template.version) || answers.plugin.version,
  };

  const srcDir = await resolveTemplate({
    template,
    version: answers.template && answers.template.version,
  });

  validatePagesHavePatterns(srcDir, answers.pages);

  const files = await copyTemplate(srcDir, target, values, {
    keepPages: answers.pages.filter((p) => !p.postsPage).map((p) => p.key),
  });

  await writeFile(
    path.join(target, 'theme.json'),
    JSON.stringify(themeJsonSettings(answers.brand), null, 2) + '\n',
  );

  await mkdir(path.join(target, 'seed'), { recursive: true });
  await writeFile(path.join(target, 'seed', 'manifest.php'), renderManifest(answers));

  if (answers.logo && answers.logo.sourcePath) {
    await mkdir(path.join(target, 'seed', 'media'), { recursive: true });
    await cp(answers.logo.sourcePath, path.join(target, 'seed', 'media', answers.logo.file));
  }

  if (answers.languages.length > 1) {
    const envPath = path.join(target, '.wp-env.json');
    const env = JSON.parse(await readFile(envPath, 'utf8'));
    env.plugins = [...env.plugins, POLYLANG];
    await writeFile(envPath, JSON.stringify(env, null, 2) + '\n');
  }

  await mkdir(path.join(target, 'docs'), { recursive: true });
  await writeFile(path.join(target, 'docs', 'brief.md'), briefMarkdown(answers));

  await assertNoTokens(target);

  if (git) {
    const run = (...args) => execFileSync('git', ['-C', target, ...args], { stdio: 'pipe' });
    execFileSync('git', ['init', '-b', 'main', target], { stdio: 'pipe' });
    run('add', '-A');
    run('commit', '-m',
      `chore: scaffold ${answers.client.name} from the Pediment client template v${values.__PEDIMENT_TEMPLATE_VERSION__}`);
  }

  return { target, files };
}

function parseArgs(argv) {
  const out = { git: true };
  for (let i = 0; i < argv.length; i += 1) {
    const arg = argv[i];
    if (arg === '--no-git') out.git = false;
    else if (arg === '--answers') out.answers = argv[++i];
    else if (arg === '--target') out.target = argv[++i];
    else if (arg === '--template') out.template = argv[++i];
    else throw new Error(`Unknown argument: ${arg}`);
  }
  if (!out.answers || !out.target) {
    throw new Error('Usage: scaffold.mjs --answers <file> --target <dir> [--template <dir>] [--no-git]');
  }
  return out;
}

if (process.argv[1] && process.argv[1].endsWith('scaffold.mjs')) {
  const args = parseArgs(process.argv.slice(2));
  const answers = JSON.parse(await readFile(args.answers, 'utf8'));
  const result = await scaffold(answers, {
    target: path.resolve(args.target),
    template: args.template,
    git: args.git,
  });
  console.log(`Scaffolded ${answers.client.name} into ${result.target} (${result.files.length} template files).`);
}
