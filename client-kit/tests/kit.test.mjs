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

const KIT_RESOURCE = /([^\s`"'()]*)((?:scripts\/[A-Za-z0-9._-]+\.mjs)|(?:shared\/[A-Za-z0-9._-]+\.md)|(?:tests\/fixtures\/[A-Za-z0-9._-]+\.json))/g;

function assertSafeKitReferences(body, skillName) {
  const references = [...body.matchAll(KIT_RESOURCE)];
  assert.ok(references.length > 0, `${skillName}: references no bundled kit resource`);

  for (const [, prefix, rel] of references) {
    assert.ok(
      prefix.endsWith('../../'),
      `${skillName}: ${prefix}${rel} must resolve from the injected skill directory with ../../`,
    );
    assert.ok(existsSync(path.join(kit, rel)), `${skillName}: references missing ${rel}`);
  }
}

test('resource guard rejects checkout-relative prefixes and missing files', () => {
  assert.throws(
    () => assertSafeKitReferences('node client-kit/scripts/scaffold.mjs', 'bad-prefix'),
    /must resolve from the injected skill directory/,
  );
  assert.throws(
    () => assertSafeKitReferences('read <skill-dir>/../../shared/missing.md', 'missing'),
    /references missing shared\/missing\.md/,
  );
  assert.doesNotThrow(() => assertSafeKitReferences(
    'node <skill-dir>/../../scripts/scaffold.mjs',
    'anchored',
  ));
});

test('start pre-authorizes its installed scaffolder command', async () => {
  const body = await readFile(path.join(kit, 'skills', 'start', 'SKILL.md'), 'utf8');
  const front = body.match(/^---\n([\s\S]*?)\n---\n/);
  assert.ok(front, 'start must have frontmatter');
  assert.match(
    front[1],
    /^allowed-tools: Bash\(node \$\{CLAUDE_SKILL_DIR\}\/\.\.\/\.\.\/scripts\/scaffold\.mjs:\*\)$/m,
  );
});

test('every bundled skill reference is installed-path safe and exists', async () => {
  const skillsDir = path.join(kit, 'skills');
  for (const name of await readdir(skillsDir)) {
    const body = await readFile(path.join(skillsDir, name, 'SKILL.md'), 'utf8');
    assertSafeKitReferences(body, name);
  }
});

test('shared critic dispatch is installed-path safe after rubric substitution', async () => {
  const prompt = await readFile(path.join(kit, 'shared', 'fidelity-critic-prompt.md'), 'utf8');
  const rubricPath = path.join(kit, 'shared', 'visual-qa.md');
  assert.ok(prompt.includes('{{RUBRIC_PATH}}'), 'critic prompt has no rubric path placeholder');

  const dispatched = prompt.replaceAll('{{RUBRIC_PATH}}', rubricPath);
  const rubricMentions = [...dispatched.matchAll(/([^\s`"'()]*)visual-qa\.md/g)]
    .map(([, prefix]) => `${prefix}visual-qa.md`);
  assert.ok(rubricMentions.length > 0, 'dispatched critic prompt has no rubric reference');
  for (const reference of rubricMentions) {
    assert.equal(reference, rubricPath, `critic rubric reference is not absolute: ${reference}`);
  }
  assert.ok(existsSync(rubricPath), 'critic rubric does not exist');
});
