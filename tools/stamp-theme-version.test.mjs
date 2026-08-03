import { test } from 'node:test';
import assert from 'node:assert/strict';
import { stampStyleCss, stampPackageJson } from './stamp-theme-version.mjs';

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
