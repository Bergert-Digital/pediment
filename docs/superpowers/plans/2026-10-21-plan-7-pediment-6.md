# Plan 7 — Pediment 6.0.0: Remove the Legacy-Block Converter

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Remove the legacy-block converter and `pediment_hero_variants()` from the plugin and release 6.0.0. Pediment 5 removed the 18 legacy blocks; the converter stayed only so a site could convert after the update to 5.0. All three client sites converted in Plan 5.

**Architecture:** The converter is self-contained. `plugin/src/Convert/` (13 classes, 13 mappers) is used from outside the directory only by:
- `plugin/src/Bootstrap.php:36-37` (`LegacyContentNotice::register()`, `Convert\AdminTab::register()`);
- `plugin/src/Abilities/Registrar.php:13-15,57` (the `pediment/convert-legacy` ability);
- `plugin/src/Content/LegacyContentNotice.php:12,49,71` (it counts `PostConverter::candidates()`);
- `plugin/wp-cli/ConvertCommand.php`, registered at `plugin/plugin.php:119-120`;
- `plugin/tests/polylang/ConvertLanguageTest.php` and `plugin/tests/phpunit/Content/LegacyContentNoticeTest.php`.

`pediment_hero_variants()` (`plugin/inc/hero-variants.php`, required at `plugin/plugin.php:51`) has one reader: `HeroMapper.php:38`. The content Policy does not depend on the converter. Its `Policy::LEGACY` list and `legacyBlocksIn()` stay (D1).

**Tech Stack:** PHP 8.1, WordPress 6.9 minimum (production sites run 7.1.3), `@wordpress/scripts`, PHPUnit via wp-env (default, Polylang and WPML suites), Playwright e2e, `node:test` for client-kit, release-please.

**Roadmap:** `docs/superpowers/plans/2026-10-14-roadmap.md`, "Later candidates", first entry. The inventory below comes from a grep pass on 2026-10-21 against `main` at 5.0.0 (`d6dcf09`). Each task repeats the lines it needs.

## Gates

G0 — Before the 6.0.0 release PR is merged, Jonas opens Settings → Pediment Theme → Convert legacy blocks on each production site and confirms that the preview lists no posts:
- straight-solutions-website (production)
- workationcastle.com
- pedimenttheme.com

G1 — Jonas confirms that no other site still has to move from the retired Pediment parent theme or from Pediment 4 content. Such a site needs the converter ("Moving an existing site onto Pediment" in `docs/client-sites.md`).

After 6.0, a late site still has a way: install the 5.x zip from its GitHub release, convert, then update to 6.x. Task 5 documents this. Building, testing and the PR do not wait for G0 or G1. Only the release merge does.

State on 2026-10-21, for information:
- The client repos still pin Pediment 4.x in `.wp-env.json`: pediment-website `v4.3.0`, straight-solutions-website `v4.2.0`, workation-castle-website `v4.3.0`. This does not block 6.0: their content and pattern files were converted in Plan 5. A client moves its pin when it next updates.
- No client repo calls `pediment_hero_variants`, `wp pediment convert` or `pediment-convert-legacy` in code. GitHub code search covered pediment-website and straight-solutions-website; workation-castle-website was not covered (rate limit). Only plan documents and a docblock in straight-solutions-website's `tests/phpunit/SeedManifestTest.php:5` name the command. Task 0 repeats the check for workation-castle-website.

## Global Constraints

- Work on the workspace branch. Never check out `main`. Never push, never open or merge a PR, never cut a release.
- PHP 8.1, `declare(strict_types=1);`, `ABSPATH` guard, text domain `pediment`.
- Tests:
  - PHPUnit: `npx wp-env run tests-wordpress --env-cwd=wp-content/plugins/pediment-ai ./vendor/bin/phpunit`. Add a path or `--filter` to focus. For the Polylang suite add `-c phpunit-polylang.xml.dist`. The WPML suite runs in CI only; say so in the report.
  - Lint: `composer lint -d plugin` (0 errors), `npm run lint:js`, `npm run lint:blocks`, `npm run lint:colors`.
  - Kit: `npm run test:kit`.
  - Validator: `PEDIMENT_VALIDATOR_E2E=1 node --test client-kit/tests/validate-blocks.test.mjs` and the corpus check from Task 2.
  - WPML config: `node tools/generate-wpml-config.mjs --check`.
  - MCP smoke: `node tools/mcp-smoke.mjs` (needs wp-env up).
  - e2e: `cd plugin && npm run e2e`.
- npm: never run `npm install` with the local npm 11 in tracked package directories. Use `npx -y npm@10 …`.
- Stage files by name. Conventional commits, summaries of 60 characters or fewer. End every commit message with the attribution line from the session.
- Task 3's deletion commit carries a `BREAKING CHANGE:` footer, so release-please proposes 6.0.0. No other commit uses `!` or that footer.

## Decisions

- D1 — `Policy::LEGACY` and `Policy::legacyBlocksIn()` stay. An agent that writes `pediment/hero` (from old docs, an old site or its training data) gets an error that names the cause, not only "block type is not registered". The error text changes to "`<name>` was removed in Pediment 5; rebuild it with core blocks and named styles (pediment-get-vocabulary)". `check-page` keeps its `legacyBlocks` output field, so the MCP output schema does not change. `IconTest.php:64` (`pediment/icon` is not in `LEGACY`) stays. **Recommended; Jonas can overrule before Task 1.**
- D2 — Delete the converter completely:
  - the classes: `plugin/src/Convert/`, `LegacyContentNotice`, `ConvertLegacy`, `ConvertCommand`;
  - the ways in: the Convert tab, the `pediment/convert-legacy` ability, `wp pediment convert`;
  - the extension points: the `pediment_convert_batch_size` filter, `pediment_hero_variants()` and its filter.

  No stubs. A client theme that still calls `add_filter( 'pediment_hero_variants', … )` keeps working; the filter has no effect. A theme that calls the function itself fatals; no known theme does (Gates).
- D3 — The admin notice goes with the converter. Without a converter it has nothing to link to; `check-page` still reports legacy blocks per page (D1). Its transient `pediment_legacy_content_count` (12 hours) and the tab's `pediment_convert_report_<user>` transient (10 minutes) expire on their own, so 6.0 needs no upgrade routine.
- D4 — The 19 `*.expected.html` converter outputs stay as a validator corpus. They are valid core markup in every default named style, checked on WordPress 6.9, 7.0 and 7.1, and they guard the validator when a new WordPress line arrives (`client-kit/validator/README.md:62`). They move to `client-kit/tests/fixtures/core-trees/`. The `*.input.html` legacy inputs are deleted. `tools/validate-convert-fixtures.mjs` becomes `tools/validate-core-trees.mjs`. **Recommended; Jonas can overrule before Task 2. If he does, delete the expected files and the CI step.**
- D5 — `docs/convert.md` is deleted. Its mapping table ("Legacy block → Becomes", lines 15-32) moves to the "Removed blocks" section of `docs/blocks.md`. It stays useful when an agent rebuilds an old page by hand: `client-kit/shared/visual-qa.md:30` and `port-page` point there. Every link to `convert.md` is fixed (Task 5).
- D6 — The historical plan and spec documents under `docs/superpowers/` are not edited, except the roadmap. `CHANGELOG.md` is release-please's.

---

### Task 0: Inventory check

- [ ] Step 1: Run `git grep -nE "Pediment\\\\Convert|convert-legacy|ConvertLegacy|ConvertCommand|pediment convert|LegacyContentNotice|hero_variants|hero-variants|pediment_convert_|convert\.md|validate-convert-fixtures"` and compare the hits with the files that Tasks 1-5 name. Add any missing file to the right task before you change code. Ignore `CHANGELOG.md` and `docs/superpowers/` except the roadmap.
- [ ] Step 2: Check workation-castle-website for `pediment_hero_variants`, `pediment convert` and `convert-legacy` (`gh api -X GET search/code -f q='"pediment_hero_variants" repo:Bergert-Digital/workation-castle-website'`, one query per term). Report what you find; do not change the client repo.

### Task 1: Change the legacy error text (D1)

**Files:**
- Modify: `plugin/src/Content/Policy.php`
  - Lines 182-188: the comment names `PostConverter::addedErrors()`. Rewrite it: the branch is checked before the registry so that the message names the cause.
  - Line 187: the new error text from D1.
  - Check `RULES` (from line 75) for a converter reference.
- Modify tests:
  - `plugin/tests/phpunit/Content/PolicyTest.php:61-66`: rename the test to `test_a_legacy_block_is_an_error_that_names_the_cause` and assert the new text.
  - `plugin/tests/phpunit/Abilities/ReadAbilitiesTest.php:140`: the new text.
  - `plugin/tests/phpunit/Abilities/WriteAbilitiesTest.php:99`: the new text.
- Modify: `plugin/src/Abilities/CheckPage.php:40` if the description names the converter.

- [ ] Step 1: Change the three tests to the new text; run them and see them fail.
- [ ] Step 2: Change `Policy.php`; run the Content and Abilities suites; all pass.
- [ ] Step 3: Commit `refactor(policy): stop naming the converter`.

### Task 2: Keep the converter outputs as a validator corpus (D4)

**Files:**
- Move: `plugin/tests/phpunit/Convert/fixtures/*.expected.html` → `client-kit/tests/fixtures/core-trees/` (`git mv`, so history follows).
- Move: `tools/validate-convert-fixtures.mjs` → `tools/validate-core-trees.mjs`. Change the directory at line 20 and the texts at lines 3-7 and 41.
- Modify: `.github/workflows/ci.yml:50`, the new script name.
- Modify: `client-kit/validator/README.md:7` and `:62`, the new script name.
- Keep for now: `plugin/tests/phpunit/Convert/MapperFixturesTest.php` reads the expected files. Point it at the new directory until Task 3 deletes it, so this commit stays green.

- [ ] Step 1: Move the files and the script; fix the paths.
- [ ] Step 2: Run `node tools/validate-core-trees.mjs` (all files pass on 6.9, 7.0 and 7.1) and `--filter MapperFixturesTest` (passes).
- [ ] Step 3: Commit `test: keep converter outputs as a validator corpus`.

### Task 3: Delete the converter (D2, D3)

**Files:**
- Delete code:
  - `plugin/src/Convert/` (whole directory).
  - `plugin/src/Abilities/ConvertLegacy.php`.
  - `plugin/src/Content/LegacyContentNotice.php`.
  - `plugin/wp-cli/ConvertCommand.php`.
  - `plugin/inc/hero-variants.php`.
- Delete tests:
  - `plugin/tests/phpunit/Convert/` (whole directory, the `*.input.html` files included).
  - `plugin/tests/phpunit/Abilities/ConvertLegacyTest.php`.
  - `plugin/tests/phpunit/Content/LegacyContentNoticeTest.php`.
  - `plugin/tests/polylang/ConvertLanguageTest.php`.
  - `plugin/tests/e2e/convert-legacy.spec.ts`. Its test at line 42, "a legacy block is not registered", moves into `plugin/tests/e2e/editor-blocks.spec.ts`: it is the only e2e proof that 5.0's removal holds.
- Modify:
  - `plugin/plugin.php:51` (the `hero-variants.php` require) and `:119-120` (the WP-CLI command).
  - `plugin/src/Bootstrap.php:36-37`.
  - `plugin/src/Abilities/Registrar.php:13-15,57`.
  - `plugin/tests/phpunit/Mcp/ServerTest.php:28`: assert that `pediment/convert-legacy` is **not** in the tool list.
  - `plugin/tests/phpunit/Abilities/WriteAbilitiesTest.php:30` (the ability list) and `:141` (the `convert-legacy` permission case).
  - `plugin/tests/phpunit/Settings/FormsTabsTest.php:21-23`: the tab list ends with `design`.
  - `tools/mcp-smoke.mjs`: remove `pediment-convert-legacy` from the tool list (line 24) and the legacy page block (lines 115-131). Keep the assertion that `write-page` rejects a legacy block (lines 117-122), with the new text from Task 1. Fix the header comment (lines 6-7) and the summary line 148.
- Commit message: `feat!: remove the legacy-block converter`. Body: what is removed. Footer: `BREAKING CHANGE: the legacy-block converter is removed: the Convert tab, the pediment/convert-legacy ability, wp pediment convert, the legacy content notice, and pediment_hero_variants() with its filter. Convert leftover content on Pediment 5.x before you update.`

- [ ] Step 1: Delete and modify as listed. The autoloader is PSR-4 (`plugin/composer.json:19-23`), so no dump is needed.
- [ ] Step 2: Check that `npx wp-env run cli wp pediment convert` fails with "not a registered subcommand". Check that `wp eval 'echo function_exists("pediment_hero_variants") ? "yes" : "no";'` prints `no`.
- [ ] Step 3: Run every gate in Global Constraints. The Polylang suite must still pass without `ConvertLanguageTest`.
- [ ] Step 4: Commit with the message above.

### Task 4: Move the mapping table to blocks.md (D5)

**Files:**
- Modify: `docs/blocks.md:131-139`, the "Removed blocks" section. Pediment 5 removed the blocks; Pediment 6 removed the converter. Add the mapping table from `docs/convert.md:15-32` under the heading "What to build instead". Add one sentence: a page that still holds a legacy block renders it empty, and `pediment-check-page` reports it; rebuild that part with the blocks in the table.
- Modify: `docs/design-layer.md:48-49`: the named styles are what the removed blocks map to (link the new blocks.md section).

- [ ] Step 1: Write the section and the link.
- [ ] Step 2: Commit `docs: keep the legacy block mapping in blocks.md`.

### Task 5: Docs, roadmap and "Upgrade to 6.0"

**Files:**
- Delete: `docs/convert.md`.
- `docs/client-sites.md`:
  - A new "Upgrade to 6.0" section after "Upgrade to 5.0":
    - Pediment 6 removes the converter.
    - Before the update, the Convert tab must list no posts.
    - If it lists posts, convert them on 5.x first. A site on 4.x or older installs the 5.x zip from its GitHub release, converts, and then updates to 6.x.
    - A client theme that uses the `pediment_hero_variants` filter removes that code.
  - "Upgrade to 4.0", step 3 (lines 214-229): say that the converter exists on 4.x and 5.x only.
  - "Upgrade to 5.0", step 6 (lines 289-296): mark the converter as removed in 6.0 and link the new section.
  - "Moving an existing site onto Pediment" (lines 327-329): a site on the retired parent theme installs Pediment 5.x, converts, and then updates to 6.x.
- `docs/mcp.md`:
  - Line 33: the smoke test no longer converts a page.
  - Line 48: remove the `pediment-convert-legacy` row.
  - Line 63: remove the permission line.
  - Lines 68-99: remove "Converting legacy blocks".
  - Lines 128-133: the new error text; there is no conversion tool.
  - Lines 259-260: check the converter wording.
- `docs/WORDPRESS_TRAPS.md`:
  - Lines 715-731, the REST-route string-booleans trap: keep it. Change the example to a remaining destructive ability (`pediment/rollback-page` with `id` and `revision` as integers that arrive as strings). Check that `RollbackPage` and `DeleteStyle` read such input correctly, and fix them if they do not. Re-point "Catch it early" to a remaining test, or add one.
  - Lines 774-775: remove the `convert --patterns` / `PatternFiles::convertText()` sentence; the seeder rule stays.
  - Lines 126, 141, 246 and 756: they say "which Pediment 5 removed" and stay true; change only what names the converter.
- `docs/blocks.md:139` (if Task 4 left it): the converter is gone; `Policy::LEGACY` stays (D1).
- `AGENTS.md:44-47`: Pediment 5 removed the 18 legacy blocks and Pediment 6 removed the converter. The content rules still reject the blocks. Rebuild old content with core blocks; `docs/blocks.md` has the mapping.
- `client-kit/shared/visual-qa.md:30`: link `docs/blocks.md` instead of `docs/convert.md`.
- `client-kit/skills/port-page/SKILL.md:107-108`: "4.0 hides them, 5.0 removes them" stays true; add nothing unless it names the converter.
- `client-kit/validator/README.md:64-65`: remove `docs/convert.md` from the list.
- `docs/superpowers/plans/2026-10-14-roadmap.md`: row 7 is **built** and waits for G0, G1 and the release. Update the Plan 7 notes the same way.

- [ ] Step 1: Write the docs.
- [ ] Step 2: `git grep -nE "convert\.md|convert-legacy|pediment convert|until (Pediment )?6|stays until 6|hero_variants" -- . ':!CHANGELOG.md' ':!docs/superpowers'` prints nothing except the intended 5.x history lines in `client-sites.md`.
- [ ] Step 3: Run `npm run test:kit` (the `visual-qa` test at `client-kit/tests/kit.test.mjs:205` still passes).
- [ ] Step 4: Commit `docs: Pediment 6 removes the converter`.

### Task 6: Final verification and stop

- [ ] Run every gate in Global Constraints from a clean build (`rm -rf plugin/build && cd plugin && npm run build`). Report each command and its result. Say that the WPML suite runs in CI only.
- [ ] Probe in wp-env:
  - Settings → Pediment Theme shows no Convert tab, and `…&tab=convert` falls back to the first tab without an error.
  - No admin notice about legacy content.
  - The MCP tool list has no `pediment-convert-legacy`.
  - `pediment-write-page` with `<!-- wp:pediment/hero /-->` returns the new error text.
- [ ] Stop. Report to the controller. G0, G1, push, the PR (one branch per PR, see the user rules) and the release are Jonas's decisions.
