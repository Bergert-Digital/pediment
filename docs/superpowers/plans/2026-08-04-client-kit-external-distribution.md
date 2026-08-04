# Client-Kit External Distribution Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Let an external developer install the Pediment Claude Code kit without cloning the
monorepo and run `/pediment:start` against version-matched release assets from any client workspace.

**Architecture:** Move marketplace discovery to the repository root while keeping the actual
Claude Code plugin under `client-kit/`. Release-please owns one version across the monorepo,
marketplace, and kit; each installed skill derives bundled resource paths from Claude Code's
injected skill directory, while the existing scaffolder downloads the matching template and pins
the matching WordPress plugin.

**Tech Stack:** Claude Code plugin manifests and skills, Node.js 20 ESM tests, release-please v4,
GitHub Releases, WordPress wp-env

## Global Constraints

- Work in the existing Conductor checkout and current branch; do not create another branch or
  worktree.
- Preserve the existing uncommitted `plugin/package-lock.json` and `.agents/` changes; stage every
  task's files explicitly by name.
- Do not change WordPress runtime behavior, `plugin/`, the seeding engine, or the scaffolder's CLI.
- Do not add npm dependencies, a website artifact upload, an npm package, or another version line.
- Node.js 20 or newer remains the client-kit runtime floor.
- The value in `.release-please-manifest.json["."]`, the kit manifest version, and the marketplace
  entry version must be identical on a released commit.
- Every installed resource path must derive from the injected skill directory; do not use
  `CLAUDE_PLUGIN_ROOT` and do not resolve a bundled file from the client repo's working directory.
- `/pediment:start` must use the installed kit version for both `plugin.version` and
  `template.version`, and must omit `--template` in the normal installed flow.
- Keep `--template <dir>` only as the existing maintainer/manual scaffolding override.
- Never push, merge a release PR, or create a GitHub release without explicit user approval.
- Use conventional commit summaries of at most 60 characters and include the Codex co-author
  trailer.

---

## File Structure

### Create

- `.claude-plugin/marketplace.json` — repository-root marketplace that maps the public source to
  `./client-kit`.

### Delete

- `client-kit/.claude-plugin/marketplace.json` — the nested marketplace works only when the caller
  already has a checkout and adds `./client-kit`.

### Modify

- `release-please-config.json` — update both Claude Code version fields with the monorepo release.
- `client-kit/.claude-plugin/plugin.json` — initialize the kit on the current monorepo version;
  release-please owns later bumps.
- `client-kit/tests/kit.test.mjs` — enforce root discovery, version lockstep, installed-safe
  resource references, installed start semantics, and external documentation commands.
- `client-kit/tests/scaffold.test.mjs` — characterize the existing missing-release-asset error.
- `client-kit/skills/start/SKILL.md` — derive the kit root and release version from the installed
  skill, then download the release template by default.
- `client-kit/skills/port-page/SKILL.md` — resolve critic and visual-QA prompts from the installed
  kit.
- `client-kit/README.md` — document external installation and maintainer-only local scaffolding.
- `docs/client-sites.md` — document the full external developer flow and its first-release gate.
- `docs/BACKLOG.md` — close the path and guard defects and explicitly decline test exclusion.

### Interfaces

- The root marketplace entry produces `source: "./client-kit"`; Claude Code consumes it when the
  user adds `Bergert-Digital/pediment`.
- `release-please-config.json` updates
  `client-kit/.claude-plugin/plugin.json:$.version` and
  `.claude-plugin/marketplace.json:$.plugins[0].version`.
- Each skill receives `Base directory for this skill: <absolute path>` from Claude Code. Call that
  directory `<skill-dir>` in prose; the kit root is `<skill-dir>/../..`.
- Skill frontmatter may use `${CLAUDE_SKILL_DIR}`. The start skill's allowed Bash command is
  `node ${CLAUDE_SKILL_DIR}/../../scripts/scaffold.mjs ...`.
- `assertSafeKitReferences(body, skillName)` is a test-only helper inside `kit.test.mjs`. It returns
  nothing and throws an assertion when a bundled reference lacks the required `../../` anchor or
  its target does not exist below `client-kit/`.
- `scaffold.mjs` continues consuming `answers.template.version`; no new CLI flag or exported
  function is introduced.

---

### Task 1: Publish the Marketplace at the Repository Root

**Files:**

- Create: `.claude-plugin/marketplace.json`
- Delete: `client-kit/.claude-plugin/marketplace.json`
- Modify: `client-kit/.claude-plugin/plugin.json:4`
- Modify: `release-please-config.json:11`
- Test: `client-kit/tests/kit.test.mjs:8-25`

**Interfaces:**

- Consumes: `.release-please-manifest.json["."]` as the current released version.
- Produces: a root marketplace entry named `pediment`, sourced from `./client-kit`, whose version
  matches `client-kit/.claude-plugin/plugin.json` and the release manifest.

- [ ] **Step 1: Replace the nested-marketplace test with a failing root-discovery and version test**

Add `repo` beside the existing `kit` constant and replace the test at lines 17-25:

```js
const kit = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '..');
const repo = path.resolve(kit, '..');

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
```

- [ ] **Step 2: Run the focused test and verify the repository-root assumption fails**

Run:

```bash
node --test --test-name-pattern="root marketplace" client-kit/tests/kit.test.mjs
```

Expected: FAIL with `ENOENT` for `.claude-plugin/marketplace.json` at the repository root.

- [ ] **Step 3: Create the root manifest, remove the nested manifest, and align the kit version**

Create `.claude-plugin/marketplace.json` with:

```json
{
  "name": "pediment",
  "description": "Pediment's client-site toolkit for Claude Code.",
  "owner": { "name": "Bergert Digital", "email": "jonas@bergert.digital" },
  "plugins": [
    {
      "name": "pediment",
      "description": "Create and build Pediment WordPress client sites: scaffold a standalone client theme, seed it, and port existing pages into it.",
      "version": "3.0.0",
      "source": "./client-kit",
      "author": { "name": "Bergert Digital", "email": "jonas@bergert.digital" }
    }
  ]
}
```

Delete `client-kit/.claude-plugin/marketplace.json` with `apply_patch`. Change
`client-kit/.claude-plugin/plugin.json`'s version from `0.1.0` to `3.0.0`, matching
`.release-please-manifest.json`.

- [ ] **Step 4: Put both JSON fields under release-please management**

Replace `extra-files` in `release-please-config.json` with:

```json
"extra-files": [
  "plugin/plugin.php",
  {
    "type": "json",
    "path": "client-kit/.claude-plugin/plugin.json",
    "jsonpath": "$.version"
  },
  {
    "type": "json",
    "path": ".claude-plugin/marketplace.json",
    "jsonpath": "$.plugins[0].version"
  }
],
```

- [ ] **Step 5: Run the focused test and strict manifest validation**

Run:

```bash
node --test --test-name-pattern="root marketplace" client-kit/tests/kit.test.mjs
claude plugin validate . --strict
```

Expected: the Node test passes and Claude reports `Validation passed` without warnings.

- [ ] **Step 6: Commit the marketplace and version contract**

```bash
git add .claude-plugin/marketplace.json \
  client-kit/.claude-plugin/marketplace.json \
  client-kit/.claude-plugin/plugin.json \
  client-kit/tests/kit.test.mjs \
  release-please-config.json
git commit -F - <<'EOF'
fix(kit): expose marketplace at repo root

Let external developers install the client kit without first cloning the
Pediment monorepo, and keep its metadata on the release version line.

Co-Authored-By: Codex <codex@openai.com>
EOF
```

---

### Task 2: Enforce and Use Installed-Safe Resource Paths

**Files:**

- Modify: `client-kit/tests/kit.test.mjs:44-60`
- Modify: `client-kit/skills/start/SKILL.md:1-14,68-71,122-126`
- Modify: `client-kit/skills/port-page/SKILL.md:6-16,94-101`

**Interfaces:**

- Consumes: Claude Code's injected skill base and `${CLAUDE_SKILL_DIR}` frontmatter substitution.
- Produces: `assertSafeKitReferences(body, skillName)` plus skill prose in which every bundled
  reference contains `../../<resource>` from the skill directory.

- [ ] **Step 1: Replace the suffix-only guard with a failing installed-path validator**

Replace the test at `client-kit/tests/kit.test.mjs:44-60` with:

```js
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
```

- [ ] **Step 2: Run the guard tests and verify both current skills fail**

Run:

```bash
node --test --test-name-pattern="resource guard|pre-authorizes|installed-path safe" client-kit/tests/kit.test.mjs
```

Expected: the helper's own cases pass; the skill scan fails on
`client-kit/scripts/scaffold.mjs` or a bare `shared/*.md` reference.

- [ ] **Step 3: Give the start skill an explicit installed-resource contract**

Add this frontmatter field below `description`:

```yaml
allowed-tools: Bash(node ${CLAUDE_SKILL_DIR}/../../scripts/scaffold.mjs:*)
```

Add this paragraph after the opening description:

```markdown
Claude Code prepends `Base directory for this skill: <absolute path>` when this skill loads. Call
that absolute directory `<skill-dir>` for this run. Resolve every bundled file from it:

- kit manifest: `<skill-dir>/../../.claude-plugin/plugin.json`
- reference answers: `<skill-dir>/../../tests/fixtures/answers-greenfield.json`
- scaffolder: `<skill-dir>/../../scripts/scaffold.mjs`

Never resolve those paths from the client repo's working directory, and do not expect
`CLAUDE_PLUGIN_ROOT` to exist in a Bash tool call.
```

Replace the reference-instance path at current line 71 with
`<skill-dir>/../../tests/fixtures/answers-greenfield.json`. Replace the Phase 3 command path with:

```bash
node "<skill-dir>/../../scripts/scaffold.mjs" --answers .context/start/answers.json --target <absolute path> --template client-template
```

Do not remove `--template` in this task; Task 3 owns release-version behavior.

- [ ] **Step 4: Anchor both port-page shared prompts**

Add this paragraph after the port-page opening description:

```markdown
Claude Code prepends `Base directory for this skill: <absolute path>` when this skill loads. Call
that directory `<skill-dir>` and resolve the bundled review instructions as
`<skill-dir>/../../shared/fidelity-critic-prompt.md` and
`<skill-dir>/../../shared/visual-qa.md`. Never resolve them from the client theme's working
directory.
```

At current lines 96-101, replace both bare `shared/*.md` references with those two anchored paths.

- [ ] **Step 5: Run the focused and complete kit tests**

Run:

```bash
node --test --test-name-pattern="resource guard|pre-authorizes|installed-path safe" client-kit/tests/kit.test.mjs
npm run test:kit
```

Expected: all resource cases pass; the full kit and tools suite reports zero failures.

- [ ] **Step 6: Commit the installed-path repair**

```bash
git add client-kit/tests/kit.test.mjs \
  client-kit/skills/start/SKILL.md \
  client-kit/skills/port-page/SKILL.md
git commit -F - <<'EOF'
fix(kit): anchor installed skill resources

Resolve bundled scripts, fixtures, and review prompts from Claude Code's
injected skill directory so the kit works outside its source checkout.

Co-Authored-By: Codex <codex@openai.com>
EOF
```

---

### Task 3: Pin `/pediment:start` to the Installed Release

**Files:**

- Modify: `client-kit/tests/kit.test.mjs`
- Modify: `client-kit/tests/scaffold.test.mjs:182-188`
- Modify: `client-kit/skills/start/SKILL.md:16-27,68-131,178-194`

**Interfaces:**

- Consumes: `<skill-dir>/../../.claude-plugin/plugin.json:version` and the existing
  `resolveTemplate({ version })` behavior.
- Produces: an answers file with identical `plugin.version` and `template.version`, and a normal
  scaffold command with no `--template` override.

- [ ] **Step 1: Add a failing structural test for installed-version semantics**

Append to `client-kit/tests/kit.test.mjs`:

```js
test('start pins both release inputs to the installed kit version', async () => {
  const body = await readFile(path.join(kit, 'skills', 'start', 'SKILL.md'), 'utf8');
  assert.match(body, /<skill-dir>\/\.\.\/\.\.\/\.claude-plugin\/plugin\.json/);
  assert.match(body, /Set both `plugin\.version` and `template\.version` to `V`/);
  assert.doesNotMatch(body, /gh release list/);

  const command = body.match(/node "<skill-dir>\/\.\.\/\.\.\/scripts\/scaffold\.mjs"[^\n]*/);
  assert.ok(command, 'start must show the installed scaffolder command');
  assert.doesNotMatch(command[0], /--template/);
});
```

- [ ] **Step 2: Run the focused test and verify the old release selection fails**

Run:

```bash
node --test --test-name-pattern="pins both release inputs" client-kit/tests/kit.test.mjs
```

Expected: FAIL because the skill still mentions `gh release list`, does not state the `V` contract,
and passes `--template client-template`.

- [ ] **Step 3: Read the installed version during Phase 0**

After the Docker, Node, and git checks, add:

```markdown
Read `<skill-dir>/../../.claude-plugin/plugin.json` and keep its `version` field as `V` for this
run. Stop with "The installed Pediment client kit has no valid semantic version" if the file is
missing, invalid JSON, or `version` does not match `^\d+\.\d+\.\d+$`. Do not query GitHub for a
different version and do not ask the user to choose one.
```

- [ ] **Step 4: Make the answers file use `V` for both artifacts**

Change the example's last two fields to:

```json
"plugin": { "version": "<V from the installed kit manifest>" },
"template": { "version": "<V from the installed kit manifest>" }
```

Replace the current version-selection rule with:

```markdown
- `plugin.version` / `template.version`: Set both `plugin.version` and `template.version` to `V`,
  the exact version read from the installed kit manifest in Phase 0. They identify one monorepo
  release and must never diverge.
```

- [ ] **Step 5: Make release download the default scaffold path**

Change the Phase 3 command to:

```bash
node "<skill-dir>/../../scripts/scaffold.mjs" --answers .context/start/answers.json --target <absolute path>
```

Replace the old missing-asset warning below it with:

```markdown
Do not pass `--template` in the installed flow. The scaffolder downloads
`pediment-client-template.zip` from release `vV`; the generated `.wp-env.json` pins
`pediment-plugin.zip` from the same release. `--template <dir>` remains a maintainer-only manual
override when testing an unreleased local template checkout.
```

Replace the old "run without `--template`" failure bullet with:

```markdown
- **The template download fails** → report the exact release URL and HTTP status from the
  scaffolder. The installed kit, template, and WordPress plugin are one release unit; do not fall
  forward to `main` or another tag. A Pediment maintainer may retry with `--template <local dir>`
  while developing an unreleased version, but that is not an external-developer recovery path.
```

- [ ] **Step 6: Characterize the existing missing-asset error**

Add after the local-template tests in `client-kit/tests/scaffold.test.mjs`:

```js
test('resolveTemplate reports the exact missing release asset and local override', async () => {
  const dir = await temp();
  const originalFetch = globalThis.fetch;
  globalThis.fetch = async () => new Response('', { status: 404 });

  try {
    await assert.rejects(resolveTemplate({ version: '9.9.9', cacheDir: dir }), (error) => {
      assert.match(
        error.message,
        /releases\/download\/v9\.9\.9\/pediment-client-template\.zip/,
      );
      assert.match(error.message, /HTTP 404/);
      assert.match(error.message, /--template <dir>/);
      return true;
    });
  } finally {
    globalThis.fetch = originalFetch;
    await rm(dir, { recursive: true, force: true });
  }
});
```

- [ ] **Step 7: Run the focused tests and the whole kit suite**

Run:

```bash
node --test --test-name-pattern="pins both release inputs" client-kit/tests/kit.test.mjs
node --test --test-name-pattern="exact missing release asset" client-kit/tests/scaffold.test.mjs
npm run test:kit
```

Expected: all three commands exit zero; the existing `.wp-env.json` and `package.json` scaffold
tests continue proving the same plugin/template version is written into the client repo.

- [ ] **Step 8: Commit the installed-release behavior**

```bash
git add client-kit/tests/kit.test.mjs \
  client-kit/tests/scaffold.test.mjs \
  client-kit/skills/start/SKILL.md
git commit -F - <<'EOF'
fix(kit): pin scaffolds to installed version

Use the installed kit version for both release artifacts and make the
versioned template download the normal external-developer path.

Co-Authored-By: Codex <codex@openai.com>
EOF
```

---

### Task 4: Document the External Developer Contract

**Files:**

- Modify: `client-kit/tests/kit.test.mjs`
- Modify: `client-kit/README.md:1-37`
- Modify: `docs/client-sites.md:1-78,171-191`
- Modify: `docs/BACKLOG.md:51-75`

**Interfaces:**

- Consumes: the public marketplace source and installed flow from Tasks 1-3.
- Produces: one consistent external onboarding contract and a maintainer-only local override.

- [ ] **Step 1: Add a failing documentation contract test**

Append to `client-kit/tests/kit.test.mjs`:

```js
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
```

- [ ] **Step 2: Run the focused test and verify both documents fail**

Run:

```bash
node --test --test-name-pattern="external docs" client-kit/tests/kit.test.mjs
```

Expected: FAIL because both files still instruct the developer to add `./client-kit`.

- [ ] **Step 3: Rewrite the client-kit README around the two plugin types**

Replace `client-kit/README.md` with:

````markdown
# Pediment client kit

Pediment itself is a WordPress plugin installed in WordPress from `pediment-plugin.zip`. This
directory is a separate Claude Code developer kit: it provides `/pediment:start`,
`/pediment:port-page`, and the deterministic scaffolder that creates standalone client-theme
repositories. The kit is installed once in Claude Code and is not copied into client repos.

## Install

In Claude Code, from any directory:

```text
/plugin marketplace add Bergert-Digital/pediment
/plugin install pediment@pediment
```

Claude Code fetches the public Pediment repository over HTTPS. The developer does not clone or
work inside the monorepo.

## Use

In the empty directory where the client project should be created:

```text
/pediment:start
```

The skill reads the installed kit version, asks the greenfield or porting questionnaire, downloads
the matching `pediment-client-template.zip`, scaffolds a standalone theme repo, boots wp-env with
the matching `pediment-plugin.zip`, seeds it, and reports the local URL.

The v3.0.0 release predates both the seeding engine and the client-template release asset. The
complete external flow begins with the first later release that contains this distribution work.

## Maintainer-only local scaffolding

Pediment maintainers can bypass the release download while testing an unreleased template from a
monorepo checkout:

```bash
node client-kit/scripts/scaffold.mjs \
  --answers answers.json \
  --target ~/Entwicklung/acme-roofing \
  --template client-template
```

`client-kit/tests/fixtures/answers-greenfield.json` is the reference answers file. This local
override is not part of external onboarding.

## Maintainer smoke checks

Before release, run `npm run test:kit`, `claude plugin validate . --strict`, and add the repository
root as a local-scope marketplace from a disposable directory. After release, repeat installation
with `Bergert-Digital/pediment`, run `/pediment:start` outside the monorepo, and require a second
`npm run seed:plan` to report `0 to write`. The exact commands and cleanup guards live in the
approved implementation plan.
````

- [ ] **Step 4: Update the durable client-sites guide**

Replace the opening description and "The three units" introduction with:

```markdown
A client site is a standalone WordPress theme repo that pairs with the Pediment WordPress plugin.
Pediment also ships a Claude Code developer kit that creates and maintains those theme repos. The
two plugins have different runtimes: WordPress installs `pediment-plugin.zip`; developers install
`client-kit/` once in Claude Code.

## The three units

- **The Pediment WordPress plugin** — the product. It supplies blocks, templates, tokens, forms,
  seeding, and AI features to WordPress and ships as `pediment-plugin.zip`.
- **The Pediment client kit** — the Claude Code tooling under `client-kit/`. It carries
  `/pediment:start`, `/pediment:port-page`, and `scripts/scaffold.mjs`. It is installed globally in
  Claude Code and is never copied into a client theme.
- **`pediment-client-template.zip`** — the tokenised standalone theme template. The scaffolder
  downloads the release asset and rewrites it into a client-owned repo.

This monorepo owns all three sources and the reusable client CI/release workflows. An external
client developer never clones it.
```

Replace the installation block under "Making a site" with:

````markdown
Install the developer kit once in Claude Code:

```text
/plugin marketplace add Bergert-Digital/pediment
/plugin install pediment@pediment
```
````

Replace the automated-tail command block and following release warning with:

````markdown
Either way, the skill writes `.context/start/answers.json`, reads version `V` from its installed
`.claude-plugin/plugin.json`, and uses `V` for both `plugin.version` and `template.version`. It then
runs the bundled scaffolder from the absolute injected skill directory:

```bash
node "<skill-dir>/../../scripts/scaffold.mjs" --answers .context/start/answers.json --target <path>
npm install
npm run env:start
npx wp-env run cli wp pediment seed --help
npm run languages    # only if the manifest has a languages section
npm run seed:plan    # shown before anything is applied
npm run seed
```

The scaffolder downloads `pediment-client-template.zip` from release `vV`; the generated
`.wp-env.json` downloads `pediment-plugin.zip` from that same release. The v3.0.0 release predates
the seeding engine and template asset, so it cannot complete this flow. The first later release
containing this distribution work is the minimum supported external version.
````

Rename "Scaffolding without Claude Code" to "Maintainer-only local scaffolding" and replace its
first paragraph with:

```markdown
This section is for Pediment maintainers working from a monorepo checkout. External developers use
`/pediment:start`; they do not need this override. `scaffold.mjs` remains a pure function of one
answers file, and maintainers can pass `--template client-template` to test an unreleased local
template without depending on a release asset.
```

Keep the existing manual command, reference-fixture explanation, and refusal behavior. Delete the
paragraphs claiming `--template` is required because no release asset exists; the versioned
download is now the normal installed path.

- [ ] **Step 5: Resolve the three related backlog entries**

Replace the three entries at `docs/BACKLOG.md:51-75` with:

```markdown
- [x] **Installed kit resources resolve from the skill directory.** Claude Code prepends an
  absolute base directory to each loaded skill. `/pediment:start` now anchors its manifest,
  fixture, and scaffolder at that directory; `/pediment:port-page` anchors both shared review
  prompts there. `CLAUDE_PLUGIN_ROOT` remains intentionally unused because Bash does not receive
  it. Fixed by the external-distribution work designed on 2026-08-04.
- [x] **The kit reference guard validates the complete path form.** It rejects checkout-prefixed
  references even when their `scripts/...` tail exists, verifies every target, and carries the
  original `client-kit/scripts/scaffold.mjs` form as a regression case.
- [x] **Shipping `client-kit/tests/` is accepted.** Claude Code's marketplace schema provides no
  supported file-exclusion field. The files are harmless, and moving them outside the plugin only
  to reduce install size is not warranted.
```

- [ ] **Step 6: Run the documentation contract and complete kit suite**

Run:

```bash
node --test --test-name-pattern="external docs" client-kit/tests/kit.test.mjs
npm run test:kit
```

Expected: both commands exit zero and no external installation block uses a local checkout path.

- [ ] **Step 7: Commit the documentation contract**

```bash
git add client-kit/tests/kit.test.mjs \
  client-kit/README.md \
  docs/client-sites.md \
  docs/BACKLOG.md
git commit -F - <<'EOF'
docs(kit): document external developer flow

Distinguish the WordPress product from its Claude Code tooling and explain
the remote install, version pin, local override, and first-release gate.

Co-Authored-By: Codex <codex@openai.com>
EOF
```

---

### Task 5: Run Branch Verification and a Local-Scope Install Smoke

**Files:** None.

**Interfaces:**

- Consumes: all branch changes from Tasks 1-4.
- Produces: evidence that tests, lint, manifest validation, and a live marketplace install work
  before any remote release action.

- [ ] **Step 1: Run the repository verification commands**

```bash
npm run test:kit
npm run lint:js
claude plugin validate . --strict
git diff --check origin/main...
```

Expected: every command exits zero. `test:kit` reports zero failed tests; Claude reports
`Validation passed`; `git diff --check` prints nothing.

- [ ] **Step 2: Confirm the diff contains only in-scope files**

```bash
git status --short
git diff --stat origin/main...
git diff --name-status origin/main...
```

Expected: the branch diff contains the marketplace, release metadata, client-kit tests/skills/docs,
backlog, spec, and plan. The pre-existing unstaged `plugin/package-lock.json` and `.agents/` remain
outside every task commit.

- [ ] **Step 3: Install from the root marketplace at local scope**

Use a disposable directory so the marketplace declaration is project-local:

```bash
PEDIMENT_REPO_ROOT="$(pwd -P)"
PEDIMENT_SMOKE_DIR="$(mktemp -d "${TMPDIR%/}/pediment-kit-smoke.XXXXXX")"
cd "$PEDIMENT_SMOKE_DIR"
claude plugin marketplace add "$PEDIMENT_REPO_ROOT" --scope local
claude plugin install pediment@pediment --scope local
claude plugin details pediment
```

Expected: add and install succeed; details lists exactly the `start` and `port-page` skills. This
tests root discovery and packaging without requiring the unpublished branch to exist on GitHub.

- [ ] **Step 4: Clean up only the disposable local-scope installation**

```bash
claude plugin uninstall pediment@pediment --scope local -y
claude plugin marketplace remove pediment --scope local
cd "$PEDIMENT_REPO_ROOT"
case "$PEDIMENT_SMOKE_DIR" in
  "${TMPDIR%/}"/pediment-kit-smoke.*) rm -rf -- "$PEDIMENT_SMOKE_DIR" ;;
  *) echo "Refusing to remove unexpected path: $PEDIMENT_SMOKE_DIR" >&2; exit 1 ;;
esac
```

Expected: the locally scoped plugin and marketplace are removed, and no user-scoped Claude setting
is changed.

- [ ] **Step 5: Record the verification evidence in the implementation handoff**

Report the test count, lint result, manifest validation result, installed skill inventory, commit
hashes, and the still-untouched unrelated working-tree changes. Do not create an empty verification
commit.

---

### Task 6: Run the Post-Release External Smoke Gate

**Files:** None on this branch.

**Interfaces:**

- Consumes: a merged release-please PR and a public tag newer than v3.0.0.
- Produces: the first proof that an external developer can install from GitHub and reach a
  converged seeded site without the monorepo.

This task is a rollout gate, not normal branch execution. Stop before it until the user explicitly
authorizes push/merge/release actions and the release workflow has finished.

- [ ] **Step 1: Verify release authorization and identify the public tag**

After explicit approval and release publication, run:

```bash
PEDIMENT_RELEASE_TAG="$(gh release view --repo Bergert-Digital/pediment --json tagName --jq .tagName)"
test "$PEDIMENT_RELEASE_TAG" != "v3.0.0"
gh release view "$PEDIMENT_RELEASE_TAG" --repo Bergert-Digital/pediment
```

Expected: the tag is newer than v3.0.0 and the release is published, not draft.

- [ ] **Step 2: Verify both versioned release assets exist**

```bash
curl --fail --silent --show-error --location --head \
  "https://github.com/Bergert-Digital/pediment/releases/download/${PEDIMENT_RELEASE_TAG}/pediment-plugin.zip"
curl --fail --silent --show-error --location --head \
  "https://github.com/Bergert-Digital/pediment/releases/download/${PEDIMENT_RELEASE_TAG}/pediment-client-template.zip"
```

Expected: both commands end at HTTP 200.

- [ ] **Step 3: Install from the public repository in a clean local scope**

```bash
PEDIMENT_EXTERNAL_DIR="$(mktemp -d "${TMPDIR%/}/pediment-external-smoke.XXXXXX")"
cd "$PEDIMENT_EXTERNAL_DIR"
claude plugin marketplace add Bergert-Digital/pediment --scope local
claude plugin install pediment@pediment --scope local
claude plugin details pediment
```

Expected: install succeeds without a checkout path and details lists `start` and `port-page` at the
same semantic version as `${PEDIMENT_RELEASE_TAG#v}`.

- [ ] **Step 4: Run `/pediment:start` interactively from the clean directory**

Start a normal Claude Code session in `$PEDIMENT_EXTERNAL_DIR`, run `/pediment:start`, choose the
greenfield path, and use these deterministic smoke answers:

```text
Business name: Pediment External Smoke
What it does: A temporary site used to validate the Pediment developer flow
Audience: Pediment maintainers
Tone: Plain and factual
Languages: English only
Pages: Home, About, Services, Contact
Accent: #B91C1C
Logo: none
Target basename: pediment-external-smoke
```

Expected: the skill downloads the template for the installed kit version, scaffolds the target,
boots wp-env, confirms `wp pediment seed --help`, shows the plan, seeds, and reports
`http://localhost:8888`.

- [ ] **Step 5: Prove convergence and version integrity**

From the generated client repo:

```bash
npm run seed:plan
node -e '
  const fs = require("node:fs");
  const pkg = JSON.parse(fs.readFileSync("package.json", "utf8"));
  const env = JSON.parse(fs.readFileSync(".wp-env.json", "utf8"));
  const expected = process.argv[1].replace(/^v/, "");
  if (pkg.pediment.plugin !== expected || pkg.pediment.template !== expected) process.exit(1);
  if (!env.plugins[0].includes(`/v${expected}/pediment-plugin.zip`)) process.exit(1);
' "$PEDIMENT_RELEASE_TAG"
```

Expected: the dry run reports `0 to write`; the version check exits zero.

- [ ] **Step 6: Clean up the disposable environment**

Stop wp-env inside the generated repo, uninstall the locally scoped plugin and marketplace, return
outside the temporary directory, then remove only the validated temporary path:

```bash
npm run env:stop
cd "$PEDIMENT_EXTERNAL_DIR"
claude plugin uninstall pediment@pediment --scope local -y
claude plugin marketplace remove pediment --scope local
cd /tmp
case "$PEDIMENT_EXTERNAL_DIR" in
  "${TMPDIR%/}"/pediment-external-smoke.*) rm -rf -- "$PEDIMENT_EXTERNAL_DIR" ;;
  *) echo "Refusing to remove unexpected path: $PEDIMENT_EXTERNAL_DIR" >&2; exit 1 ;;
esac
```

- [ ] **Step 7: Report external availability**

Record the public tag, both HTTP 200 asset checks, installed kit version, successful seed preflight,
and `0 to write` convergence. Only after this evidence may the external flow be described as
available; no repository commit is needed for the smoke itself.
