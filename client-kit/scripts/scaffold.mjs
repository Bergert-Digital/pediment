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
