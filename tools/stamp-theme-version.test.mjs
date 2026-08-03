import { test } from 'node:test';
import assert from 'node:assert/strict';
import { stampStyleCss, stampPackageJson, stampThemeDir } from './stamp-theme-version.mjs';
import { mkdtemp, writeFile, readFile } from 'node:fs/promises';
import { tmpdir } from 'node:os';
import path from 'node:path';

const css = `/*
Theme Name: Acme Roofing
Description: Roofing.
Version: 0.1.0
Text Domain: acme-roofing
*/
`;

test('stampStyleCss rewrites the Version header', () => {
  const out = stampStyleCss(css, '1.4.0');
  assert.match(out, /^Version: 1\.4\.0$/m);
  assert.doesNotMatch(out, /0\.1\.0/);
});

test('stampStyleCss leaves other headers untouched', () => {
  const out = stampStyleCss(css, '1.4.0');
  assert.match(out, /^Theme Name: Acme Roofing$/m);
  assert.match(out, /^Text Domain: acme-roofing$/m);
});

test('stampStyleCss throws when there is no Version header to move', () => {
  assert.throws(() => stampStyleCss('/*\nTheme Name: X\n*/\n', '1.4.0'), /Version header/);
});

test('stampStyleCss is idempotent', () => {
  assert.equal(stampStyleCss(stampStyleCss(css, '1.4.0'), '1.4.0'), stampStyleCss(css, '1.4.0'));
});

test('stampPackageJson rewrites version and preserves everything else', () => {
  const out = stampPackageJson('{\n  "name": "acme-roofing",\n  "version": "0.1.0"\n}\n', '1.4.0');
  const parsed = JSON.parse(out);
  assert.equal(parsed.version, '1.4.0');
  assert.equal(parsed.name, 'acme-roofing');
  assert.match(out, /\n$/);
});

test('stampThemeDir rejects a version that is just "v"', async () => {
  const dir = await mkdtemp(path.join(tmpdir(), 'stamp-test-'));
  await writeFile(path.join(dir, 'style.css'), css);
  await writeFile(path.join(dir, 'package.json'), '{"name":"test","version":"0.1.0"}\n');

  await assert.rejects(
    () => stampThemeDir(dir, 'v'),
    /Invalid version/
  );
});

test('stampThemeDir rejects a non-semver version', async () => {
  const dir = await mkdtemp(path.join(tmpdir(), 'stamp-test-'));
  await writeFile(path.join(dir, 'style.css'), css);
  await writeFile(path.join(dir, 'package.json'), '{"name":"test","version":"0.1.0"}\n');

  await assert.rejects(
    () => stampThemeDir(dir, 'v1.2'),
    /Invalid version/
  );
});

test('stampThemeDir accepts a valid semver version with pre-release suffix', async () => {
  const dir = await mkdtemp(path.join(tmpdir(), 'stamp-test-'));
  await writeFile(path.join(dir, 'style.css'), css);
  await writeFile(path.join(dir, 'package.json'), '{"name":"test","version":"0.1.0"}\n');

  const result = await stampThemeDir(dir, 'v1.2.3-beta.1+build.42');
  assert.equal(result.version, '1.2.3-beta.1+build.42');

  const newCss = await readFile(path.join(dir, 'style.css'), 'utf8');
  assert.match(newCss, /^Version: 1\.2\.3-beta\.1\+build\.42$/m);
});

test('stampThemeDir does not modify style.css when package.json is malformed', async () => {
  const dir = await mkdtemp(path.join(tmpdir(), 'stamp-test-'));
  const cssPath = path.join(dir, 'style.css');
  const pkgPath = path.join(dir, 'package.json');

  await writeFile(cssPath, css);
  await writeFile(pkgPath, '{invalid json}');

  await assert.rejects(
    () => stampThemeDir(dir, 'v1.2.3'),
    /JSON/
  );

  // Verify style.css was not modified.
  const unchanged = await readFile(cssPath, 'utf8');
  assert.equal(unchanged, css);
});
