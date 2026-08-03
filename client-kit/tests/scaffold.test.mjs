import { test } from 'node:test';
import assert from 'node:assert/strict';
import { mkdtemp, mkdir, readFile, writeFile, rm } from 'node:fs/promises';
import { tmpdir } from 'node:os';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import {
  TOKENS, replaceTokens, validateTarget, validateSlug,
  copyTemplate, assertNoTokens, resolveTemplate,
} from '../scripts/scaffold.mjs';

const here = path.dirname(fileURLToPath(import.meta.url));
const MINI = path.join(here, 'fixtures', 'mini-template');
const values = {
  __PEDIMENT_SLUG__: 'acme-roofing',
  __PEDIMENT_NAME__: 'Acme Roofing',
  __PEDIMENT_DESCRIPTION__: 'Roofing for the North East.',
  __PEDIMENT_PLUGIN_VERSION__: '3.3.0',
  __PEDIMENT_TEMPLATE_VERSION__: '3.3.0',
};

const temp = () => mkdtemp(path.join(tmpdir(), 'pediment-scaffold-'));

test('replaceTokens replaces every known token, everywhere', () => {
  const out = replaceTokens('__PEDIMENT_SLUG__ and __PEDIMENT_SLUG__ and __PEDIMENT_NAME__', values);
  assert.equal(out, 'acme-roofing and acme-roofing and Acme Roofing');
});

test('replaceTokens leaves unknown tokens alone so assertNoTokens can catch them', () => {
  assert.equal(replaceTokens('__PEDIMENT_MYSTERY__', values), '__PEDIMENT_MYSTERY__');
});

test('TOKENS lists every token the templates may use', () => {
  assert.deepEqual([...TOKENS].sort(), Object.keys(values).sort());
});

test('validateSlug accepts lowercase-hyphenated slugs and rejects everything else', () => {
  validateSlug('acme-roofing');
  validateSlug('acme2');
  assert.throws(() => validateSlug('Acme Roofing'), /slug/i);
  assert.throws(() => validateSlug('acme_roofing'), /slug/i);
  assert.throws(() => validateSlug('-acme'), /slug/i);
  assert.throws(() => validateSlug(''), /slug/i);
});

test('validateTarget refuses a path containing whitespace', () => {
  assert.throws(() => validateTarget('/tmp/my client/site'), /whitespace/i);
});

test('validateTarget refuses a non-empty existing directory', async () => {
  const dir = await temp();
  await writeFile(path.join(dir, 'occupied.txt'), 'x');
  assert.throws(() => validateTarget(dir), /not empty/i);
  await rm(dir, { recursive: true, force: true });
});

test('validateTarget accepts a missing directory and an empty one', async () => {
  const dir = await temp();
  validateTarget(dir);
  validateTarget(path.join(dir, 'does-not-exist-yet'));
  await rm(dir, { recursive: true, force: true });
});

test('copyTemplate writes the tree with tokens replaced', async () => {
  const dir = await temp();
  const dest = path.join(dir, 'out');
  const written = await copyTemplate(MINI, dest, values);

  assert.ok(written.includes('style.css'));
  assert.ok(written.includes(path.join('patterns', 'home.php')));

  const css = await readFile(path.join(dest, 'style.css'), 'utf8');
  assert.match(css, /Theme Name: Acme Roofing/);
  assert.match(css, /Text Domain: acme-roofing/);

  const home = await readFile(path.join(dest, 'patterns', 'home.php'), 'utf8');
  assert.match(home, /Slug: acme-roofing\/home/);

  await rm(dir, { recursive: true, force: true });
});

test('copyTemplate prunes pattern files for pages that were not chosen', async () => {
  const dir = await temp();
  const dest = path.join(dir, 'out');
  const written = await copyTemplate(MINI, dest, values, { keepPages: ['home'] });

  assert.ok(written.includes(path.join('patterns', 'home.php')));
  assert.ok(!written.includes(path.join('patterns', 'services.php')));
  await assert.rejects(readFile(path.join(dest, 'patterns', 'services.php'), 'utf8'));

  await rm(dir, { recursive: true, force: true });
});

test('copyTemplate returns the token-replaced path for a file whose name carries a token', async () => {
  const dir = await temp();
  const dest = path.join(dir, 'out');
  const written = await copyTemplate(MINI, dest, values);

  const replacedRel = path.join('patterns', 'acme-roofing-note.php');
  assert.ok(written.includes(replacedRel));
  assert.ok(!written.includes(path.join('patterns', '__PEDIMENT_SLUG__-note.php')));

  const note = await readFile(path.join(dest, replacedRel), 'utf8');
  assert.match(note, /Slug: acme-roofing\/note/);

  await rm(dir, { recursive: true, force: true });
});

test('copyTemplate refuses a token value that would write outside destDir', async () => {
  const dir = await temp();
  const dest = path.join(dir, 'out');
  // The token-bearing fixture lives one directory down, at patterns/__PEDIMENT_SLUG__-note.php,
  // so a single ".." only cancels the "patterns/" segment and lands back inside destDir. A second
  // ".." is what actually walks out past destDir's own boundary — that is what must be refused.
  const escaping = { ...values, __PEDIMENT_SLUG__: '../../escape' };

  await assert.rejects(copyTemplate(MINI, dest, escaping), /Refusing to write outside the target directory/);

  await rm(dir, { recursive: true, force: true });
});

test('assertNoTokens passes on a fully-replaced tree', async () => {
  const dir = await temp();
  const dest = path.join(dir, 'out');
  await copyTemplate(MINI, dest, values);
  await assertNoTokens(dest);
  await rm(dir, { recursive: true, force: true });
});

test('assertNoTokens names the file when a token survives', async () => {
  const dir = await temp();
  const dest = path.join(dir, 'out');
  await mkdir(dest, { recursive: true });
  await writeFile(path.join(dest, 'leftover.txt'), 'hello __PEDIMENT_MYSTERY__ world');

  await assert.rejects(assertNoTokens(dest), (err) => {
    assert.match(err.message, /leftover\.txt/);
    assert.match(err.message, /__PEDIMENT_MYSTERY__/);
    return true;
  });

  await rm(dir, { recursive: true, force: true });
});

test('resolveTemplate returns a local directory unchanged', async () => {
  assert.equal(await resolveTemplate({ template: MINI }), MINI);
});

test('resolveTemplate refuses a local template path that does not exist', async () => {
  await assert.rejects(resolveTemplate({ template: '/nope/not/here' }), /not a directory/i);
});
