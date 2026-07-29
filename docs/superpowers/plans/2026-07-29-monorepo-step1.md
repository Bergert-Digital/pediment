# Monorepo + Single Version Line + Main-Only (Migration Step 1) Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Merge `pediment-ai` into the `pediment` repo as `plugin/` with history, unify both artifacts on one release line starting at v2.4.0, and retire the `development` branch — implementing migration step 1 of `docs/superpowers/specs/2026-07-29-pediment-dev-flow-design.md`.

**Architecture:** The theme stays at the repo root (step 2 dissolves it into `plugin/` later); `pediment-ai` arrives under `plugin/` via a subtree merge that preserves its history. One root release-please package stamps both `style.css` and `plugin/plugin.php`; one `build-release-zip.yml` attaches `pediment.zip` and `pediment-ai.zip` to the same tag. The plugin keeps its own nested `composer.json`/`package.json` for step 1 (consolidation happens in step 2 when the code merges) — step 1 unifies repo, release, and branch, not build systems.

**Tech Stack:** git subtree merge, release-please v4, GitHub Actions, wp-env, Plugin Update Checker (PUC) v5.

## Global Constraints

- **Never push without explicit user approval** (user policy). All git work is local until the single gated push in Task 8.
- **Work lands on `main`** (spec decision 5). No new long-lived branches. Execution happens on the current branch `pediment-dev-flow-review` rebased onto `origin/main`; the gated push is `git push origin HEAD:main`.
- **WP floor:** `Requires at least: 6.9`, wp-env core pin `WordPress/WordPress#6.9` — do not change.
- **The plugin slug stays `pediment-ai`** in step 1. The rename to `pediment` is step 2 (v3.0.0).
- **Unified version line starts at exactly `2.4.0`** (theme is at v2.3.0, plugin at v0.6.0; plugin jumps upward, which PUC handles).
- **Conventional commits** (release-please parses them). The merge commit must be `feat:` scoped so the minor bumps.
- **Both repos' `origin/development` contains 0 commits that `main` lacks** (verified 2026-07-29; Task 1 re-verifies). If that has changed, STOP and report — do not improvise a reconciliation.
- **If GitHub Actions doesn't fire after a push, check the Bergert-Digital org spending limit before debugging** — a $0 budget drops events with no UI signal (known failure mode).
- Working directory: `/Users/jonas/conductor/workspaces/pediment/west-monroe`. The pediment-ai source repo is `/Users/jonas/Entwicklung/pediment-ai` (used read-only until Task 10).

## File Structure

```
(root = theme, unchanged locations)     plugin/  (new — was pediment-ai root)
  style.css                               plugin.php
  functions.php, inc/, src/, ...          src/, editor/, wp-cli/, assets/
  .wp-env.json          REWRITTEN         tests/, build/
  .distignore           MODIFIED          composer.json, package.json   KEPT nested
  release-please-config.json  MODIFIED    phpcs.xml.dist, phpunit.xml.dist  KEPT
  .release-please-manifest.json KEPT      .distignore                   KEPT
  .github/workflows/ci.yml    REWRITTEN   playwright.config.ts          MODIFIED (port)
  .github/workflows/build-release-zip.yml REWRITTEN
  .github/workflows/release-please.yml  KEPT (verify chain)
  .github/workflows/release.yml         MODIFIED (build both zips)
  AGENTS.md, README.md        MODIFIED    DELETED from plugin/: .github/,
  .claude/commands/*.md       MODIFIED      .wp-env.json, release-please-config.json,
  .conductor/settings.toml    MODIFIED      .release-please-manifest.json, .mcp.json,
                                            .claude/ (if present), .conductor/ (if present)
```

---

### Task 1: Preflight — verify branch state and rebase onto origin/main

**Files:** none created; the working branch `pediment-dev-flow-review` is rebased.

**Interfaces:**
- Produces: a local HEAD = `origin/main` + the 8 docs commits (spec + this plan), with version files showing 2.3.0. Every later task commits on top of this.

- [ ] **Step 1: Verify the divergence assumption still holds in both repos**

```bash
cd /Users/jonas/conductor/workspaces/pediment/west-monroe
git fetch origin
git rev-list --count origin/main..origin/development     # expect: 0
git -C /Users/jonas/Entwicklung/pediment-ai fetch origin
git -C /Users/jonas/Entwicklung/pediment-ai rev-list --count origin/main..origin/development   # expect: 0
```

Expected: both print `0`. If either is non-zero, STOP — report which commits `development` has that `main` lacks and wait for the user.

- [ ] **Step 2: Verify the working tree is clean enough to rebase**

```bash
git status --porcelain
```

Expected: at most `M package-lock.json` (pre-existing). If it is modified, restore it: `git checkout -- package-lock.json`.

- [ ] **Step 3: Rebase this branch onto origin/main**

```bash
git rebase origin/main
```

Expected: clean rebase (our commits touch only `docs/superpowers/`). If conflicts appear in anything other than docs, STOP and report.

- [ ] **Step 4: Verify version files now reflect main's release state**

```bash
grep -m1 "^Version" style.css          # expect: Version: 2.3.0
cat .release-please-manifest.json      # expect: { ".": "2.3.0" }
git log --oneline origin/main..HEAD | wc -l   # expect: 6 (5 spec commits + the committed plan)
```

The exact count matters less than this invariant: `git log origin/main..HEAD` must show ONLY `docs(...)` commits touching `docs/superpowers/`. Anything else on the branch is unexpected — STOP and report.

---

### Task 2: Subtree-merge pediment-ai into plugin/ with history

**Files:**
- Create: `plugin/**` (entire pediment-ai tree at its `origin/main`)

**Interfaces:**
- Consumes: Task 1's rebased HEAD.
- Produces: `plugin/plugin.php`, `plugin/src/Updater.php`, `plugin/composer.json`, `plugin/package.json`, `plugin/.distignore`, `plugin/tests/fixtures/mu-activate-theme.php` — paths every later task edits. History reachable: `git log plugin/plugin.php` shows pediment-ai commits.

- [ ] **Step 1: Fetch pediment-ai main into this repo**

```bash
git remote add pediment-ai https://github.com/Bergert-Digital/pediment-ai.git 2>/dev/null || true
git fetch pediment-ai main
```

- [ ] **Step 2: Subtree merge under plugin/**

```bash
git merge -s ours --no-commit --allow-unrelated-histories pediment-ai/main
git read-tree --prefix=plugin/ -u pediment-ai/main
git commit -m "feat: merge pediment-ai into the monorepo as plugin/

The pediment-ai repository joins pediment as plugin/, with full history
preserved via subtree merge. Spec: docs/superpowers/specs/2026-07-29-
pediment-dev-flow-design.md, migration step 1.

Release-As: 2.4.0"
```

The `Release-As: 2.4.0` footer pins the first unified release version.

- [ ] **Step 3: Verify tree parity and history**

```bash
git diff --stat pediment-ai/main HEAD:plugin/ | tail -1   # expect: no output (identical trees)
git log --oneline pediment-ai/main | head -3              # note top hash
git log --oneline HEAD -- plugin/plugin.php | head -3     # expect pediment-ai commit subjects
test -f plugin/plugin.php && test -f plugin/src/Updater.php && echo OK
```

Expected: `OK`, and `git diff` between `pediment-ai/main` and `HEAD:plugin/` is empty.

---

### Task 3: Prune duplicated scaffolding and unify wp-env

**Files:**
- Delete: `plugin/.github/` (4 workflows), `plugin/.wp-env.json`, `plugin/release-please-config.json`, `plugin/.release-please-manifest.json`, `plugin/.mcp.json`; also `plugin/.claude/` and `plugin/.conductor/` if present
- Modify: `.wp-env.json` (root — full rewrite below)

**Interfaces:**
- Consumes: `plugin/**` from Task 2.
- Produces: a single root wp-env where the theme mounts at `wp-content/themes/pediment` and the plugin at `wp-content/plugins/pediment-ai` regardless of the checkout folder's name. CI (Task 6) and all local verification depend on these two mount paths.

- [ ] **Step 1: Delete the duplicated scaffolding**

```bash
git rm -r plugin/.github plugin/.wp-env.json plugin/release-please-config.json plugin/.release-please-manifest.json plugin/.mcp.json
git rm -r plugin/.claude plugin/.conductor 2>/dev/null || true
```

- [ ] **Step 2: Rewrite the root .wp-env.json**

Replace the entire file with:

```json
{
	"core": "WordPress/WordPress#6.9",
	"phpVersion": "8.1",
	"themes": [],
	"plugins": [],
	"config": {
		"WP_DEBUG": true,
		"WP_DEBUG_LOG": true,
		"SCRIPT_DEBUG": true,
		"PEDIMENT_AI_MOCK": true,
		"PEDIMENT_AI_LOOPBACK_URL": "http://127.0.0.1"
	},
	"mappings": {
		"wp-content/themes/pediment": ".",
		"wp-content/plugins/pediment-ai": "./plugin",
		"wp-content/uploads": "./tests/fixtures/uploads",
		"wp-content/mu-plugins/activate-pediment.php": "./plugin/tests/fixtures/mu-activate-theme.php"
	}
}
```

Why mappings instead of `themes`/`plugins` arrays: wp-env derives mount names from the directory basename, so in a Conductor workspace named `west-monroe` the theme would mount as `themes/west-monroe` and every `--env-cwd=wp-content/themes/pediment` invocation breaks (a documented recurring failure). Explicit mappings pin the mount names everywhere. The cost: wp-env no longer auto-activates the plugin — activation becomes an explicit `wp plugin activate pediment-ai` step (CI does this in Task 6; the mu-plugin fixture keeps the theme active for plugin tests).

- [ ] **Step 3: Boot and verify both mounts**

```bash
npm run env:start
npx wp-env run cli wp theme activate pediment
npx wp-env run cli wp plugin activate pediment-ai
npx wp-env run cli wp theme list --fields=name,status
npx wp-env run cli wp plugin list --fields=name,status
```

Expected: `pediment` active theme, `pediment-ai` active plugin. If `env:start` fails on the folder-name check (`tools/check-folder-name.mjs`), the checkout dir has whitespace — fix the dir, not the tool.

- [ ] **Step 4: Run both PHPUnit suites against the root env**

```bash
composer install --prefer-dist --no-progress
composer install --prefer-dist --no-progress -d plugin
npx wp-env run tests-wordpress --env-cwd=wp-content/themes/pediment vendor/bin/phpunit
npx wp-env run tests-wordpress --env-cwd=wp-content/plugins/pediment-ai ./vendor/bin/phpunit
```

Expected: both suites green. Note: the tests container mounts mirror the mappings, so `--env-cwd` paths match Step 2 exactly.

- [ ] **Step 5: Commit**

```bash
git add -A
git commit -m "feat(env): one wp-env for theme and plugin with pinned mount names

Deletes the plugin's duplicated repo scaffolding (.github, wp-env,
release-please files, .mcp.json). Root wp-env mounts the theme at
themes/pediment and the plugin at plugins/pediment-ai via explicit
mappings, so mounts no longer depend on the checkout folder's basename."
```

---

### Task 4: Point the plugin updater at the monorepo; fix the e2e port

**Files:**
- Modify: `plugin/src/Updater.php:24`
- Modify: `plugin/playwright.config.ts` (port 8898 → 8888)

**Interfaces:**
- Consumes: `plugin/src/Updater.php` with `REPO_URL = 'https://github.com/Bergert-Digital/Pediment-AI/'`.
- Produces: `REPO_URL = 'https://github.com/Bergert-Digital/pediment/'` — Task 9's release assets on the pediment repo are what this checker will find. Slug stays `pediment-ai`; asset regex stays `/pediment-ai\.zip$/` (it cannot match `pediment.zip`: that string does not end in `pediment-ai.zip` and vice versa).

- [ ] **Step 1: Change REPO_URL**

In `plugin/src/Updater.php` line 24, change:

```php
	private const REPO_URL = 'https://github.com/Bergert-Digital/Pediment-AI/';
```

to:

```php
	private const REPO_URL = 'https://github.com/Bergert-Digital/pediment/';
```

Leave `buildUpdateChecker(..., 'pediment-ai')`, `setBranch('main')`, and `enableReleaseAssets('/pediment-ai\.zip$/')` untouched.

- [ ] **Step 2: Update the plugin's Playwright baseURL to the root env port**

```bash
grep -n "8898" plugin/playwright.config.ts plugin/tests -r
```

Change every `8898` to `8888` (the root wp-env port). If the config reads a port from `.wp-env.override.json` or an env var, adjust the default only.

- [ ] **Step 3: Verify the plugin's updater test still passes**

```bash
npx wp-env run tests-wordpress --env-cwd=wp-content/plugins/pediment-ai ./vendor/bin/phpunit --filter Updater
```

Expected: PASS (if a test asserts the old URL, update the assertion to the new URL — that is the point of the change).

- [ ] **Step 4: Commit**

```bash
git add plugin/src/Updater.php plugin/playwright.config.ts
git commit -m "feat(updates): plugin updater follows monorepo releases

PUC now points at Bergert-Digital/pediment, where pediment-ai.zip is
attached from this repo's unified release. Slug and asset regex are
unchanged. Plugin e2e targets the root wp-env port 8888."
```

---

### Task 5: Unified release plumbing — one version line, two zips

**Files:**
- Modify: `release-please-config.json` (add `plugin/plugin.php` to extra-files)
- Modify: `.distignore` (exclude `plugin` and agent/infra cruft from the theme zip)
- Rewrite: `.github/workflows/build-release-zip.yml` (build + attach both zips)
- Modify: `.github/workflows/release.yml` (dispatch fallback builds both)

**Interfaces:**
- Consumes: `.release-please-manifest.json` at `{ ".": "2.3.0" }` (from Task 1); `plugin/plugin.php` with existing `x-release-please-version` markers; `plugin/.distignore`.
- Produces: on tag `vX.Y.Z`, release assets `pediment.zip` (theme, no `plugin/` inside) and `pediment-ai.zip` (plugin, `vendor/` + `build/` inside). Task 9 verifies these.

- [ ] **Step 1: Extend release-please extra-files**

In `release-please-config.json`, change:

```json
      "extra-files": [
        "style.css"
      ],
```

to:

```json
      "extra-files": [
        "style.css",
        "plugin/plugin.php"
      ],
```

`plugin/plugin.php` already carries `x-release-please-version` annotations from its old repo, so release-please stamps both its header and `PEDIMENT_AI_VERSION`. The manifest stays `{ ".": "2.3.0" }` — the next release is 2.4.0 via the `Release-As` footer from Task 2.

- [ ] **Step 2: Extend the theme .distignore**

Append to `.distignore`:

```
plugin
.claude
.conductor
.context
AGENTS.md
pediment.code-workspace
release-please-config.json
.release-please-manifest.json
CHANGELOG.md
```

(`plugin` keeps the plugin out of the theme zip; the rest is the documented release-zip cruft — agent instructions and IDE files currently ship to client sites.)

- [ ] **Step 3: Rewrite build-release-zip.yml to build both artifacts**

Keep the existing workflow's trigger/permissions/inputs block and its theme steps exactly as they are (checkout of the tag, PHP + Node setup, `composer install --no-dev`, `npm ci && npm run build`, the `sed` version patch into `style.css` and `package.json`, `rsync --exclude-from=.distignore` into a `pediment/` stage dir, zip, `gh release upload --clobber pediment.zip`). Then add a plugin sequence to the same job, after the theme upload:

```yaml
      # ---- plugin: pediment-ai.zip ----
      - name: Build plugin
        run: |
          composer install --no-dev --prefer-dist --no-progress -d plugin
          cd plugin && npm ci && npm run build
      - name: Stamp plugin version
        run: |
          VERSION="${TAG#v}"
          sed -i "s/^ \* Version: .*/ * Version:           ${VERSION}/" plugin/plugin.php
          sed -i "s/define( 'PEDIMENT_AI_VERSION', '[^']*' )/define( 'PEDIMENT_AI_VERSION', '${VERSION}' )/" plugin/plugin.php
          cd plugin && npm pkg set version="${VERSION}"
      - name: Stage and zip plugin
        run: |
          mkdir -p stage-plugin/pediment-ai
          rsync -a --exclude-from=plugin/.distignore plugin/ stage-plugin/pediment-ai/
          cd stage-plugin && zip -rq ../pediment-ai.zip pediment-ai
      - name: Attach plugin zip
        run: gh release upload "$TAG" pediment-ai.zip --clobber
        env:
          GH_TOKEN: ${{ github.token }}
```

Use the same `TAG` variable/env the existing theme steps use (read it from the workflow's input or `github.ref_name` exactly as the current file does — keep that mechanism, don't invent a new one).

- [ ] **Step 4: Point release.yml (dispatch fallback) at the same dual build**

`release.yml` re-invokes the reusable zip build for a given tag. Verify it calls `build-release-zip.yml` with the tag input and needs no changes beyond what Step 3 already made shared. If it duplicates build steps instead of calling the reusable workflow, replace the duplication with a `uses: ./.github/workflows/build-release-zip.yml` call with the same inputs.

- [ ] **Step 5: Local dry-run of both zip builds**

```bash
npm run build
composer install --no-dev --prefer-dist --no-progress
mkdir -p /tmp/stage-theme/pediment
rsync -a --exclude-from=.distignore ./ /tmp/stage-theme/pediment/
composer install --no-dev --prefer-dist --no-progress -d plugin
( cd plugin && npm ci && npm run build )
mkdir -p /tmp/stage-plugin/pediment-ai
rsync -a --exclude-from=plugin/.distignore plugin/ /tmp/stage-plugin/pediment-ai/
ls /tmp/stage-theme/pediment/ | head -20
ls /tmp/stage-plugin/pediment-ai/
```

Expected: theme stage contains `style.css`, `build/`, `inc/`, `vendor/` — and **no `plugin/`, no `.claude/`, no `AGENTS.md`**. Plugin stage contains `plugin.php`, `build/`, `src/`, `vendor/`, `wp-cli/` — and **no `editor/`, no `tests/`**. Afterwards restore dev deps: `composer install && composer install -d plugin`.

- [ ] **Step 6: Commit**

```bash
git add release-please-config.json .distignore .github/workflows/build-release-zip.yml .github/workflows/release.yml
git commit -m "feat(release): one version line, two artifacts per tag

release-please stamps style.css and plugin/plugin.php from a single
manifest; every tag attaches pediment.zip (theme, plugin/ excluded) and
pediment-ai.zip (plugin). Theme zip stops shipping agent/infra files."
```

---

### Task 6: Unified CI

**Files:**
- Rewrite: `.github/workflows/ci.yml`

**Interfaces:**
- Consumes: root wp-env mappings from Task 3 (`wp-content/themes/pediment`, `wp-content/plugins/pediment-ai`).
- Produces: one workflow, six jobs: `phpcs`, `phpcs-plugin`, `lint-blocks`, `phpunit`, `phpunit-plugin`, `e2e`, `e2e-plugin`. (The two old repos ran these split across two workflows with a cross-repo checkout that no longer exists.)

- [ ] **Step 1: Rewrite ci.yml**

Preserve the existing theme jobs (`phpcs`, `lint-blocks`, `phpunit`, `e2e`) verbatim — same steps, same invocations (`npx wp-env run tests-wordpress --env-cwd=wp-content/themes/pediment vendor/bin/phpunit` still matches the pinned mount). Then add the plugin jobs, which are the old pediment-ai jobs with the dual-checkout deleted (the theme is now `..`) and the root env substituted:

```yaml
  phpcs-plugin:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v4
      - uses: shivammathur/setup-php@v2
        with:
          php-version: '8.1'
          tools: composer
      - run: composer install --prefer-dist --no-progress -d plugin
      - run: composer lint -d plugin

  phpunit-plugin:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v4
      - uses: actions/setup-node@v4
        with:
          node-version: 20
          cache: npm
      - uses: shivammathur/setup-php@v2
        with:
          php-version: '8.1'
          tools: composer
      - run: npm ci && npm run build
      - run: composer install --prefer-dist --no-progress -d plugin
      - run: cd plugin && npm ci && npm run build
      - run: npx wp-env start
      - run: npx wp-env run cli wp plugin activate pediment-ai
      - run: npx wp-env run tests-wordpress --env-cwd=wp-content/plugins/pediment-ai ./vendor/bin/phpunit

  e2e-plugin:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v4
      - uses: actions/setup-node@v4
        with:
          node-version: 20
          cache: npm
      - uses: shivammathur/setup-php@v2
        with:
          php-version: '8.1'
          tools: composer
      - run: npm ci && npm run build
      - run: composer install --prefer-dist --no-progress -d plugin
      - run: cd plugin && npm ci && npm run build
      - run: cd plugin && npx playwright install --with-deps chromium
      - run: npx wp-env start
      - run: npx wp-env run cli wp theme activate pediment
      - run: npx wp-env run cli wp plugin activate pediment-ai
      - run: cd plugin && npm run e2e
      - if: failure()
        uses: actions/upload-artifact@v4
        with:
          name: plugin-playwright-report
          path: plugin/playwright-report/
```

Adjust `node-version` / `php-version` values to match whatever the existing theme jobs pin (copy, don't guess). Trigger: keep the existing `on:` block but ensure it covers `push` to `main` and `pull_request` (the old plugin CI skipped `development` pushes — irrelevant now, but `main` must be covered).

- [ ] **Step 2: Validate workflow syntax**

```bash
python3 -c "import yaml,sys; yaml.safe_load(open('.github/workflows/ci.yml')); print('yaml ok')"
npx --yes @action-validator/cli .github/workflows/ci.yml 2>/dev/null || echo "action-validator unavailable — YAML check only"
```

Expected: `yaml ok`.

- [ ] **Step 3: Commit**

```bash
git add .github/workflows/ci.yml
git commit -m "feat(ci): one workflow for theme and plugin

Plugin jobs lose the cross-repo checkout of the theme (it is the same
repo now) and run against the root wp-env's pinned mounts."
```

---

### Task 7: Docs, agent commands, Conductor config, image cruft

**Files:**
- Modify: `AGENTS.md`, `README.md`, `.claude/commands/serve-changes.md`, `.claude/commands/mount-parent.md`, `.conductor/settings.toml`
- Delete: `docs/images/*.jpg` (11 Unsplash JPEGs, ~46 MB)

**Interfaces:**
- Consumes: everything landed in Tasks 2–6.
- Produces: repo docs that describe the monorepo, not the three-repo stack.

- [ ] **Step 1: Update AGENTS.md**

In the stack description, replace the three-repo framing (`pediment` parent / `pediment-child-theme` / `pediment-ai` plugin) with: this repo contains the theme (root) and the plugin (`plugin/`); the client-theme template lives in its own repo. Replace every mention of the `development` branch with `main` (work lands on `main`; release-please's release PR is the shipping gate). Add the two suite commands from Task 3 Step 4 as the canonical PHPUnit invocations.

- [ ] **Step 2: Update README.md**

Same branch-model replacement. Add a `plugin/` section: `composer install -d plugin`, `cd plugin && npm install && npm run build`, and the plugin test commands. Remove or rewrite any instruction that references the standalone pediment-ai repo for development.

- [ ] **Step 3: Update the agent commands that assume `development`**

`.claude/commands/serve-changes.md` fast-forwards a local `development` branch and `.claude/commands/mount-parent.md` syncs a checkout to the committed branch HEAD. In both, replace `development` with `main` wherever the branch is named. Do not otherwise restructure them.

- [ ] **Step 4: Extend .conductor/settings.toml setup**

Add the plugin to the setup/run scripts, preserving what is there:

```toml
# in the setup script, after the existing composer/npm/build lines:
# composer install -d plugin && (cd plugin && npm install && npm run build)
```

(Match the file's actual script syntax — it currently chains `composer install && npm install && npm run build`; extend that chain.)

- [ ] **Step 5: Remove the tracked Unsplash JPEGs going forward**

```bash
du -sh docs/images/
git rm docs/images/*.jpg
```

This removes them from future checkouts; the ~46 MB stays in history. A full history purge (`git filter-repo`) would force-push and invalidate every Conductor workspace — explicitly deferred; note it in `docs/BACKLOG.md` under 🟢 with the reason.

- [ ] **Step 6: Verify no stale `development` references remain in operative files**

```bash
grep -rn "development" .github/workflows/ .claude/commands/ .conductor/ AGENTS.md README.md | grep -v "node_modules" | grep -iv "local dev\|dev env\|developer\|development system"
```

Expected: no lines naming a `development` *branch*. (Prose about "development" as an activity is fine.)

- [ ] **Step 7: Commit**

```bash
git add -A
git commit -m "docs: describe the monorepo and the main-only flow

AGENTS/README cover the plugin/ layout and unified commands; agent
commands and Conductor setup stop referencing the development branch;
tracked Unsplash JPEGs removed going forward (history purge deferred)."
```

---

### Task 8: GATE — user push, then watch CI

**Files:** none.

**Interfaces:**
- Consumes: the full local commit stack.
- Produces: `origin/main` at this HEAD with green CI; release-please opens the v2.4.0 release PR.

- [ ] **Step 1: Present the stack for review and STOP for approval**

```bash
git log --oneline origin/main..HEAD
git diff --stat origin/main..HEAD | tail -5
```

Show both to the user. **Do not push without an explicit go-ahead.** The push is:

```bash
git push origin HEAD:main
```

- [ ] **Step 2: Watch CI**

```bash
gh run list --repo Bergert-Digital/pediment --limit 3
gh run watch --repo Bergert-Digital/pediment $(gh run list --repo Bergert-Digital/pediment --branch main --limit 1 --json databaseId -q '.[0].databaseId')
```

Expected: the unified CI run goes green. If no run appears within two minutes, check the org's Actions spending limit before anything else (known silent failure). If a job fails, fix forward on the same branch flow (local commit → gated push).

- [ ] **Step 3: Confirm the release PR appeared with version 2.4.0**

```bash
gh pr list --repo Bergert-Digital/pediment --search "release" --state open
```

Expected: a release-please PR titled for 2.4.0 (the `Release-As` footer from Task 2 pins it). Inspect its diff: it must bump `style.css`, `plugin/plugin.php` (header **and** `PEDIMENT_AI_VERSION`), `.release-please-manifest.json`, and `CHANGELOG.md`. If `plugin/plugin.php` is missing from the diff, the extra-files entry from Task 5 Step 1 is wrong — fix before merging.

---

### Task 9: Release v2.4.0 and verify both artifacts

**Files:** none (release automation).

**Interfaces:**
- Consumes: the release PR from Task 8.
- Produces: tag `v2.4.0` with assets `pediment.zip` and `pediment-ai.zip` — the URLs the client-template pins and both PUC checkers consume.

- [ ] **Step 1: User merges the release PR** (gated — ask, don't merge autonomously).

- [ ] **Step 2: Verify tag and assets**

```bash
gh release view v2.4.0 --repo Bergert-Digital/pediment --json tagName,assets -q '.tagName + " " + (.assets | map(.name) | join(", "))'
```

Expected: `v2.4.0 pediment.zip, pediment-ai.zip`. If assets are missing, check the chained `attach-zip` job's run; the dispatch fallback is `gh workflow run release.yml -f tag=v2.4.0`.

- [ ] **Step 3: Inspect both zips**

```bash
cd /tmp && rm -f pediment.zip pediment-ai.zip
gh release download v2.4.0 --repo Bergert-Digital/pediment -p '*.zip'
unzip -l pediment.zip    | grep -cE "plugin/|\.claude|AGENTS\.md"   # expect: 0
unzip -l pediment.zip    | grep -m1 "pediment/style.css"            # expect: present
unzip -l pediment-ai.zip | grep -m1 "pediment-ai/plugin.php"        # expect: present
unzip -l pediment-ai.zip | grep -m1 "pediment-ai/vendor/"           # expect: present
unzip -p pediment.zip pediment/style.css | grep -m1 "^Version"      # expect: Version: 2.4.0
unzip -p pediment-ai.zip pediment-ai/plugin.php | grep -m1 "Version:" # expect: 2.4.0
```

All expectations must hold before Task 10 — existing sites will be pointed at these assets.

---

### Task 10: Transition the old pediment-ai repo, retire development, archive

**Files (in `/Users/jonas/Entwicklung/pediment-ai`, on its `main`):**
- Modify: `src/Updater.php:24` (same one-line change as Task 4)

**Interfaces:**
- Consumes: v2.4.0 assets verified in Task 9.
- Produces: existing plugin installs (at 0.6.0) get offered 0.6.1, whose only change is that the update checker now watches `Bergert-Digital/pediment` — from which they'll next be offered 2.4.0. Then both `development` branches die and the old repo is archived.

- [ ] **Step 1: Prepare the old repo's working copy**

```bash
cd /Users/jonas/Entwicklung/pediment-ai
git status --porcelain          # expect clean; if dirty, STOP and report
git checkout main && git pull origin main
```

- [ ] **Step 2: Apply the updater redirect and pin the version**

Same edit as Task 4 Step 1 (`REPO_URL` → `https://github.com/Bergert-Digital/pediment/`). Commit:

```bash
git add src/Updater.php
git commit -m "fix(updates): follow releases from the pediment monorepo

pediment-ai now lives in Bergert-Digital/pediment; this final release
from the standalone repo points the update checker there so existing
installs keep receiving updates.

Release-As: 0.6.1"
```

- [ ] **Step 3: GATE — user approves, then push and release**

```bash
git push origin main
# wait for release-please PR, user merges it, then:
gh release view v0.6.1 --repo Bergert-Digital/pediment-ai --json assets -q '.assets | map(.name) | join(", ")'
```

Expected: `pediment-ai.zip` attached to v0.6.1 on the OLD repo.

- [ ] **Step 4: Delete the development branches (gated)**

```bash
git push origin --delete development                                  # in pediment-ai
git -C /Users/jonas/conductor/workspaces/pediment/west-monroe push origin --delete development   # in pediment
```

Before deleting pediment's: confirm no open PRs target `development` (`gh pr list --repo Bergert-Digital/pediment --base development`).

- [ ] **Step 5: Archive the old repo (gated)**

```bash
gh repo archive Bergert-Digital/pediment-ai --yes
```

Archive, don't delete — v0.6.1's release asset must stay downloadable for the transition.

- [ ] **Step 6: Tell the user what changed outside git**

Report explicitly: (a) Conductor workspaces for this project target `origin/development`, which no longer exists — the project's target branch must be switched to `main` in Conductor's settings; (b) the `pediment-ai` and `pediment-child-theme` Conductor projects reference repos that are now archived/stale; (c) the child template's `.wp-env.json` pins still point at old release URLs — that bump is a later migration step, but note the new asset URLs: `https://github.com/Bergert-Digital/pediment/releases/download/v2.4.0/pediment.zip` and `.../pediment-ai.zip`.

---

## Out of scope for this plan

- Moving theme code into `plugin/` (spec migration step 2, v3.0.0)
- The plugin slug rename to `pediment` (step 2)
- Child-template changes of any kind, including its CI, pins, and main-only switch (steps 3–5)
- Consolidating composer/npm into a single root install (step 2, when code merges)
- Merging the two phpcs rulesets — theme code is WPCS-file-naming, plugin is PSR-4; one command runs both (`composer lint` + `composer lint -d plugin`); a single ruleset only becomes sensible when the code merges in step 2 (deliberate narrowing of the spec's "one phpcs config")
- History purge of `docs/images/` (force-push; deferred to a moment when no worktrees are live)
