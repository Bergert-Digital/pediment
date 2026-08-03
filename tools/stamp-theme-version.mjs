#!/usr/bin/env node
/**
 * Stamp a client theme's released version into style.css and package.json.
 *
 * The released artifact's version header failing to move — so production never
 * sees the update — was found and fixed independently in all four Pediment
 * repos (9c9af20, 22f0024, 432faf6, workation #22). Inline sed in a workflow
 * cannot carry a test; this can, and does.
 */

import { readFile, writeFile } from 'node:fs/promises';
import path from 'node:path';

export function stampStyleCss(css, version) {
  if (!/^Version:.*$/m.test(css)) {
    throw new Error('style.css has no Version header to stamp. Add "Version: 0.1.0" to the theme header block.');
  }
  return css.replace(/^Version:.*$/m, `Version: ${version}`);
}

export function stampPackageJson(json, version) {
  const parsed = JSON.parse(json);
  parsed.version = version;
  return JSON.stringify(parsed, null, 2) + '\n';
}

if (process.argv[1] && process.argv[1].endsWith('stamp-theme-version.mjs')) {
  const [dir, raw] = process.argv.slice(2);
  if (!dir || !raw) {
    console.error('Usage: stamp-theme-version.mjs <themeDir> <version|vX.Y.Z>');
    process.exit(1);
  }
  const version = raw.replace(/^v/, '');

  const cssPath = path.join(dir, 'style.css');
  await writeFile(cssPath, stampStyleCss(await readFile(cssPath, 'utf8'), version));

  const pkgPath = path.join(dir, 'package.json');
  await writeFile(pkgPath, stampPackageJson(await readFile(pkgPath, 'utf8'), version));

  console.log(`Stamped ${version} into ${cssPath} and ${pkgPath}`);
}
