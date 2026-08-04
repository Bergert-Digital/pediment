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

export async function stampThemeDir(dir, rawVersion) {
  const version = rawVersion.replace(/^v/, '');

  // Validate version is semver-shaped before touching any files.
  const semverRegex = /^\d+\.\d+\.\d+(?:-[0-9A-Za-z.-]+)?(?:\+[0-9A-Za-z.-]+)?$/;
  if (!semverRegex.test(version)) {
    throw new Error(`Invalid version "${rawVersion}". Expected a semver string like "1.2.3" or "v1.2.3"`);
  }

  const cssPath = path.join(dir, 'style.css');
  const pkgPath = path.join(dir, 'package.json');

  // Read and validate both files before writing either one.
  const cssContent = await readFile(cssPath, 'utf8');
  const pkgContent = await readFile(pkgPath, 'utf8');

  // Compute both outputs (this will throw if validation fails).
  const newCssContent = stampStyleCss(cssContent, version);
  const newPkgContent = stampPackageJson(pkgContent, version);

  // Write both files.
  await writeFile(cssPath, newCssContent);
  await writeFile(pkgPath, newPkgContent);

  return { cssPath, pkgPath, version };
}

if (process.argv[1] && process.argv[1].endsWith('stamp-theme-version.mjs')) {
  const [dir, raw] = process.argv.slice(2);
  if (!dir || !raw) {
    console.error('Usage: stamp-theme-version.mjs <themeDir> <version|vX.Y.Z>');
    process.exit(1);
  }

  try {
    const { cssPath, pkgPath, version } = await stampThemeDir(dir, raw);
    console.log(`Stamped ${version} into ${cssPath} and ${pkgPath}`);
  } catch (err) {
    console.error(err.message);
    process.exit(1);
  }
}
