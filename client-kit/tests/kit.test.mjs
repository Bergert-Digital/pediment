import { test } from 'node:test';
import assert from 'node:assert/strict';
import { readFile, readdir } from 'node:fs/promises';
import { existsSync } from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const kit = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '..');
const repo = path.resolve(kit, '..');

test('plugin.json declares a name, description and version', async () => {
  const manifest = JSON.parse(await readFile(path.join(kit, '.claude-plugin', 'plugin.json'), 'utf8'));
  assert.equal(manifest.name, 'pediment');
  assert.ok(manifest.description.length > 20);
  assert.match(manifest.version, /^\d+\.\d+\.\d+$/);
});

test('root marketplace points at the client kit and versions stay in lockstep', async () => {
  const market = JSON.parse(
    await readFile(path.join(repo, '.claude-plugin', 'marketplace.json'), 'utf8'),
  );
  const manifest = JSON.parse(
    await readFile(path.join(kit, '.claude-plugin', 'plugin.json'), 'utf8'),
  );
  const releases = JSON.parse(
    await readFile(path.join(repo, '.release-please-manifest.json'), 'utf8'),
  );

  assert.equal(market.plugins.length, 1);
  assert.equal(market.plugins[0].name, manifest.name);
  assert.equal(market.plugins[0].description, manifest.description);
  assert.equal(market.plugins[0].source, './client-kit');
  assert.equal(manifest.version, releases['.']);
  assert.equal(market.plugins[0].version, releases['.']);
  assert.equal(existsSync(path.join(kit, '.claude-plugin', 'marketplace.json')), false);
});

test('every skill has YAML frontmatter with a name and description', async () => {
  const skillsDir = path.join(kit, 'skills');
  const names = await readdir(skillsDir);
  assert.ok(names.length > 0, 'expected at least one skill');

  for (const name of names) {
    const file = path.join(skillsDir, name, 'SKILL.md');
    assert.ok(existsSync(file), `${name} has no SKILL.md`);
    const body = await readFile(file, 'utf8');
    const front = body.match(/^---\n([\s\S]*?)\n---\n/);
    assert.ok(front, `${name}: SKILL.md has no frontmatter block`);
    assert.match(front[1], /^name:\s*\S+/m, `${name}: frontmatter has no name`);
    assert.match(front[1], /^description:\s*\S+/m, `${name}: frontmatter has no description`);
    assert.match(front[1], new RegExp(`^name:\\s*${name}\\s*$`, 'm'), `${name}: frontmatter name must match its directory`);
  }
});

test('every script a skill references actually exists', async () => {
  const skillsDir = path.join(kit, 'skills');
  for (const name of await readdir(skillsDir)) {
    const body = await readFile(path.join(skillsDir, name, 'SKILL.md'), 'utf8');
    const references = [
      ...body.matchAll(/\b(scripts\/[A-Za-z0-9._-]+\.mjs)\b/g),
      ...body.matchAll(/\b(shared\/[A-Za-z0-9._-]+\.md)\b/g),
    ];
    assert.ok(
      references.length > 0,
      `${name}: references no kit file. Either it should, or its references are written in a form this guard cannot see — write them as scripts/<file>.mjs or shared/<file>.md.`,
    );
    for (const [, rel] of references) {
      assert.ok(existsSync(path.join(kit, rel)), `${name}: references missing ${rel}`);
    }
  }
});
