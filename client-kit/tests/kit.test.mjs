import { test } from 'node:test';
import assert from 'node:assert/strict';
import { readFile, readdir } from 'node:fs/promises';
import { existsSync } from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const kit = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '..');

test('plugin.json declares a name, description and version', async () => {
  const manifest = JSON.parse(await readFile(path.join(kit, '.claude-plugin', 'plugin.json'), 'utf8'));
  assert.equal(manifest.name, 'pediment');
  assert.ok(manifest.description.length > 20);
  assert.match(manifest.version, /^\d+\.\d+\.\d+$/);
});

test('marketplace.json points at this directory and agrees with plugin.json', async () => {
  const market = JSON.parse(await readFile(path.join(kit, '.claude-plugin', 'marketplace.json'), 'utf8'));
  const manifest = JSON.parse(await readFile(path.join(kit, '.claude-plugin', 'plugin.json'), 'utf8'));
  assert.equal(market.plugins.length, 1);
  assert.equal(market.plugins[0].name, manifest.name);
  assert.equal(market.plugins[0].version, manifest.version);
  assert.equal(market.plugins[0].source, './');
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
    for (const [, rel] of body.matchAll(/\b(scripts\/[a-z0-9-]+\.mjs)\b/g)) {
      assert.ok(existsSync(path.join(kit, rel)), `${name}: references missing ${rel}`);
    }
    for (const [, rel] of body.matchAll(/\b(shared\/[a-z0-9-]+\.md)\b/g)) {
      assert.ok(existsSync(path.join(kit, rel)), `${name}: references missing ${rel}`);
    }
  }
});
