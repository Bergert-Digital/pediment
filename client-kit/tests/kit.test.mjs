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

const KIT_RESOURCE = /([^\s`"'()]*)((?:\.claude-plugin\/plugin\.json)|(?:scripts\/[A-Za-z0-9._-]+\.mjs)|(?:shared\/[A-Za-z0-9._-]+\.md)|(?:tests\/fixtures\/[A-Za-z0-9._-]+\.json))/g;

function assertSafeKitReferences(body, skillName, kitRoot = kit) {
  const references = [...body.matchAll(KIT_RESOURCE)];
  assert.ok(references.length > 0, `${skillName}: references no bundled kit resource`);

  for (const [, prefix, rel] of references) {
    assert.ok(
      prefix.endsWith('../../'),
      `${skillName}: ${prefix}${rel} must resolve from the injected skill directory with ../../`,
    );
    assert.ok(existsSync(path.join(kitRoot, rel)), `${skillName}: references missing ${rel}`);
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

test('resource guard rejects checkout-relative plugin manifest prefix', () => {
  assert.throws(
    () => assertSafeKitReferences(
      'read client-kit/.claude-plugin/plugin.json',
      'bad-manifest-prefix',
    ),
    /must resolve from the injected skill directory/,
  );
});

test('resource guard accepts installed plugin manifest prefix', () => {
  assert.doesNotThrow(() => assertSafeKitReferences(
    'read <skill-dir>/../../.claude-plugin/plugin.json',
    'safe-manifest-prefix',
  ));
});

test('resource guard rejects missing installed plugin manifest target', () => {
  const missingKit = path.join(kit, 'tests', 'fixtures', 'missing-kit');
  assert.throws(
    () => assertSafeKitReferences(
      'read <skill-dir>/../../.claude-plugin/plugin.json',
      'missing-manifest-target',
      missingKit,
    ),
    /references missing \.claude-plugin\/plugin\.json/,
  );
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

test('start pins both release inputs to the installed kit version', async () => {
  const body = await readFile(path.join(kit, 'skills', 'start', 'SKILL.md'), 'utf8');
  assert.match(body, /<skill-dir>\/\.\.\/\.\.\/\.claude-plugin\/plugin\.json/);
  assert.match(body, /Set both `plugin\.version` and `template\.version` to `V`/);
  assert.doesNotMatch(body, /gh release list/);

  const command = body.match(/node "<skill-dir>\/\.\.\/\.\.\/scripts\/scaffold\.mjs"[^\n]*/);
  assert.ok(command, 'start must show the installed scaffolder command');
  assert.doesNotMatch(command[0], /--template/);
});

test('external docs use the public marketplace and explicit plugin name', async () => {
  const docs = [
    await readFile(path.join(kit, 'README.md'), 'utf8'),
    await readFile(path.join(repo, 'docs', 'client-sites.md'), 'utf8'),
  ];

  for (const body of docs) {
    assert.match(body, /\/plugin marketplace add Bergert-Digital\/pediment/);
    assert.match(body, /\/plugin install pediment@pediment/);
    assert.doesNotMatch(body, /\/plugin marketplace add \.\/client-kit/);
  }
});

test('port-page and start send changes to an existing page through MCP, not a re-seed', async () => {
  const port = await readFile(path.join(kit, 'skills', 'port-page', 'SKILL.md'), 'utf8');
  const start = await readFile(path.join(kit, 'skills', 'start', 'SKILL.md'), 'utf8');

  assert.match(port, /pediment-write-page/);
  assert.match(port, /npm run adopt -- <key>/);
  assert.doesNotMatch(port, /re-validate \(step 5\), re-seed/);
  assert.match(start, /pediment-write-page/);
  assert.doesNotMatch(start, /`npm run seed` re-applies them/);
});

test('port-page finds seeded pages by identity and updates every language by id', async () => {
  const port = await readFile(path.join(kit, 'skills', 'port-page', 'SKILL.md'), 'utf8');
  const multilingual = port.slice(port.indexOf('## Multilingual pages'));

  assert.doesNotMatch(port, /--name=<key>/, 'a slug lookup misses nested keys, custom slugs and other languages');
  assert.match(port, /wp pediment seed --dry-run --format=json/);
  assert.match(port, /one `create` line for `<key>` per declared language/);
  assert.match(multilingual, /pediment-write-page/);
  assert.match(multilingual, /`id`/);
  assert.doesNotMatch(multilingual, /`translation_of` = the default-language page ID/);
  assert.match(multilingual, /`translation_of` only for a language the seed did not create/);
});

test('port-page says how to add the MCP Adapter to an older scaffold', async () => {
  const port = await readFile(path.join(kit, 'skills', 'port-page', 'SKILL.md'), 'utf8');
  const preconditions = port.slice(0, port.indexOf('## 2. Capture the source'));

  assert.match(preconditions, /mcp-adapter\.0\.7\.0\.zip/);
  assert.match(preconditions, /\.wp-env\.json/);
  assert.match(preconditions, /npm run env:start/);
  assert.match(preconditions, /wp plugin is-active mcp-adapter/);
});

test('visual-qa no longer recommends the legacy cta block', async () => {
  const body = await readFile(path.join(kit, 'shared', 'visual-qa.md'), 'utf8');
  assert.doesNotMatch(body, /A conversion band → `cta`/);
  assert.match(body, /`panel`/);
});
