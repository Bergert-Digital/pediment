# Plan 6 — Pediment 5.0.0: Delete the 18 Legacy Blocks

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Remove the 18 legacy presentation blocks (`Policy::LEGACY`) from the plugin and release 5.0.0. Keep the converter working until 6.0.0, so a site that still has legacy content can convert it after the update.

**Architecture:** Blocks are discovered from `plugin/src/blocks/*` (`plugin/inc/register-blocks.php:34-62`, `wp-scripts build-blocks-manifest`), so deleting a block directory unregisters the block. The converter (`plugin/src/Convert/`, the wp-admin tab, the `pediment/convert-legacy` ability, `wp pediment convert`) only parses markup and never reads the block registry or a legacy `render.php`. It survives the deletion unchanged. Three things outside the block directories need care first:
- the `pediment/accent` RichText format, which lives in `hero/accent-format.tsx`;
- `pediment_hero_variants()`, which `HeroMapper` reads;
- the content Policy, which today accepts legacy blocks with a warning.

**Tech Stack:** PHP 8.1, WordPress 6.9 minimum (production sites run 7.1.3), `@wordpress/scripts`, PHPUnit via wp-env (default, Polylang and WPML suites), Playwright e2e, `node:test` for client-kit, release-please.

**Roadmap:** `docs/superpowers/plans/2026-10-14-roadmap.md`, "Plan 6". The inventory of every reference, with file and line numbers, comes from an Explore pass on 2026-10-19. Each task below repeats the lines it needs.

## Gate

G0 — Before the 5.0.0 release PR is merged, Jonas opens Settings → Pediment Theme → Convert legacy blocks on each production site and confirms that the preview lists no posts:
- straight-solutions-website (production)
- workationcastle.com
- pedimenttheme.com

The agent-channel threads report public HTML only (0 legacy blocks on the converted pages). The tab also covers drafts, private posts, synced patterns and templates. Building, testing and the PR do not wait for G0. Only the release merge does.

## Global Constraints

- Branch `bergjs/pediment-5` from `origin/main` (4.4.0). Never check out `main`. Never push, never open or merge a PR, never cut a release.
- PHP 8.1, `declare(strict_types=1);`, `ABSPATH` guard, text domain `pediment`, `_doing_it_wrong` version `'5.0.0'`.
- Keep the converter and its fixtures, mappers, tests, the wp-admin tab, the ability and the WP-CLI command working. `Policy::LEGACY` stays: it is the converter's list of names (`MapperRegistryTest` asserts `Policy::LEGACY === Converter::withDefaults()->mappedNames()`).
- Tests:
  - PHPUnit: `npx wp-env run tests-wordpress --env-cwd=wp-content/plugins/pediment-ai ./vendor/bin/phpunit`. Add a path or `--filter` to focus. For the Polylang suite add `-c phpunit-polylang.xml.dist`. The WPML suite runs in CI only; say so in the report.
  - Lint: `composer lint -d plugin` (0 errors), `npm run lint:js`, `npm run lint:blocks`, `npm run lint:colors`.
  - Kit: `npm run test:kit`.
  - Fixtures: `node tools/validate-convert-fixtures.mjs`.
  - WPML config: `node tools/generate-wpml-config.mjs --check`.
  - e2e: `cd plugin && npm run e2e` (it works in this workspace).
- Rebuild after deleting a block directory: `cd plugin && npm run build`. A stale `plugin/build/blocks/<legacy>` directory keeps the block registered in wp-env. Delete `plugin/build/` before the build if a removed block still shows up.
- npm: never run `npm install` with the local npm 11 in tracked package directories. Use `npx -y npm@10 …`.
- Stage files by name. Conventional commits, summaries of 60 characters or fewer. End every commit message with the attribution line from the session.
- Task 5's deletion commit carries a `BREAKING CHANGE:` footer, so release-please proposes 5.0.0. No other commit uses `!` or that footer.

## Decisions

- D1 — The `pediment/accent` format moves to a shared editor script, built from `plugin/src/editor/index.ts` into `plugin/build/editor/`. It is enqueued on `enqueue_block_editor_assets` with the generated `index.asset.php` dependencies, so editors can still apply accents to core headings and paragraphs (Task 1).
- D2 — `pediment_hero_variants()` and its filter stay until 6.0.0, because `HeroMapper` reads them. The inline `window.pedimentHeroVariants` editor script goes with the hero block. The filter's test moves from `BlockRender/HeroTest` to the converter tests (Task 2).
- D3 — From 5.0, the Policy makes a legacy block an **error**, not a warning: "`<name>` was removed in Pediment 5; convert it with pediment-convert-legacy". So `write-page` rejects new legacy markup, which would render empty, and `check-page` reports legacy pages as errors. The converter is not affected: `PostConverter` and `PatternFiles` compare the errors before and after a conversion and refuse only on *added* errors (Task 3).
- D4 — An admin notice tells site admins when posts still hold legacy blocks after the update. They render empty or unstyled now. The notice links to the Convert tab. The count is cached and cleared when a post is saved and after a conversion (Task 4).
- D5 — Delete the 18 block directories, `Content/LegacyBlocks.php` (the inserter-hiding filter) and their tests. Remove the legacy-only selectors from `reveal.js`, regenerate `wpml-config.xml`, and fix the stale block counts (Task 5).
- D6 — Docs: an "Upgrade to 5.0" section in `docs/client-sites.md`, plus every stale line in `docs/convert.md`, `docs/blocks.md`, `docs/mcp.md`, `docs/WORDPRESS_TRAPS.md`, `AGENTS.md`, `README.md` and the roadmap (Task 6).
- D7 — The `InvalidBlockLog` is not extended to `core/missing`: the notice from D4 covers leftover legacy content.

---

### Task 1: Move the accent format to a shared editor script

**Files:**
- Create: `plugin/src/editor/index.ts`, `plugin/src/editor/accent-format.ts` (moved from `plugin/src/blocks/hero/accent-format.tsx`)
- Modify:
  - `plugin/package.json`: the `build` script gets a `build:editor` step, `wp-scripts build src/editor/index.ts --output-path=build/editor`. Keep the existing `build:blocks` step unchanged.
  - `plugin/inc/assets.php`: enqueue `build/editor/index.js` on `enqueue_block_editor_assets` with the deps and version from `build/editor/index.asset.php`. If the build is missing, skip quietly.
  - `plugin/src/blocks/hero/index.tsx`: remove `import './accent-format';` (the hero is deleted in Task 5; this task keeps it working until then).
  - `plugin/assets/css/theme.css:3-6`: update the comment that names the old path.
- Test: a PHPUnit test that the script handle is enqueued on `enqueue_block_editor_assets`. Extend the e2e coverage: in the editor, `wp.data.select('core/rich-text').getFormatType('pediment/accent')` is defined while no hero block is on the page.

- [ ] Step 1: Write the failing PHPUnit test (handle enqueued) and the e2e assertion (format type registered on a page with only a core paragraph).
- [ ] Step 2: Move the format, add the build entry and the enqueue. Run `cd plugin && npm run build`; `build/editor/index.js` and `index.asset.php` exist.
- [ ] Step 3: Run the tests; both pass. Run `npm run lint:js`.
- [ ] Step 4: Commit `refactor(editor): load the accent format for all blocks`.

### Task 2: Keep hero variants for the converter only

**Files:**
- Modify: `plugin/inc/hero-variants.php` (required at `plugin/plugin.php:51`). Keep `pediment_hero_variants()` and the `pediment_hero_variants` filter. Remove the inline `window.pedimentHeroVariants` script and its hook; `hero/edit.tsx:48` is the only reader, and it goes in Task 5. Add a docblock saying that the converter's `HeroMapper` (`plugin/src/Convert/Mappers/HeroMapper.php:38`) reads it and that it goes in 6.0.0 with the converter.
- Move test: the filter coverage in `plugin/tests/phpunit/BlockRender/HeroTest.php:134-160` moves into `plugin/tests/phpunit/Convert/HeroCtaMappersTest.php`. A variant added by the filter and mapped by the converter keeps its mapping; an unknown variant is noted (the existing `test_a_site_variant_without_a_mapping_is_noted`, lines 23-30, must still pass).

- [ ] Step 1: Move the test, run it, and see it pass against the current code.
- [ ] Step 2: Remove the inline script and hook. Run the converter tests and the hero render tests; both pass.
- [ ] Step 3: Commit `refactor: keep hero variants for the converter only`.

### Task 3: Reject legacy blocks in page writes

**Files:**
- Modify: `plugin/src/Content/Policy.php`. In `walk()` (lines 183-189), the `Policy::LEGACY` branch adds an error instead of a warning: `"{$here}: {$name} was removed in Pediment 5; convert it with pediment-convert-legacy"`. Keep the branch before the "not registered" check, so the message names the cause. Update `RULES` if it mentions legacy blocks.
- Check, and change only if needed:
  - `plugin/src/Convert/PostConverter.php:188-189` and `plugin/src/Convert/PatternFiles.php:187` (they diff `errors` before and after the conversion; a legacy error exists only before).
  - `plugin/src/Abilities/CheckPage.php:40,59-61,83`.
  - `plugin/src/Abilities/WritePage.php` (around line 128).
- Tests:
  - `plugin/tests/phpunit/Content/PolicyTest.php:54-59,69,269-272`: legacy is now an error.
  - `plugin/tests/phpunit/Abilities/ReadAbilitiesTest.php:128-149`: check-page reports the error.
  - A new test: write-page rejects `<!-- wp:pediment/pull-quote {"quote":"Q"} /-->`.
  - A converter test: a post with legacy blocks still converts (no added errors).
- Rewrite: `plugin/tests/e2e/convert-legacy.spec.ts:55-117` writes legacy markup through `write-page`. Seed that page with `createPageWithContent` from `plugin/tests/e2e/utils.ts` instead. Check `tools/mcp-smoke.mjs:118` the same way.
- Docs: `docs/mcp.md:128-129` ("The 18 legacy Pediment blocks only produce a warning").

- [ ] Step 1: Change the tests to expect an error; run them and see them fail.
- [ ] Step 2: Change `walk()`; run the Content, Abilities and Convert suites; all pass.
- [ ] Step 3: Fix the e2e spec and `mcp-smoke.mjs`; run `npm run e2e -- tests/e2e/convert-legacy.spec.ts`.
- [ ] Step 4: Commit `feat(policy): reject legacy blocks in page writes`.

### Task 4: Admin notice for leftover legacy content

**Files:**
- Create: `plugin/src/Content/LegacyContentNotice.php`, hooked in `plugin/src/Bootstrap.php`.
  - It counts posts that hold a legacy block. Use the same candidate rule as `PostConverter` (post types, statuses and `Policy::legacyBlocksIn` or the converter's `LEGACY_DELIMITER`). A `LIKE '%<!-- wp:pediment/%'` SQL pre-filter followed by a parse is fine.
  - The count is cached in a transient for 12 hours. It is cleared on `save_post` and after a conversion (find the converter's write path and clear it there).
  - Show the notice only to users with `manage_options`, and not on the Convert tab itself. Text: "N posts still contain blocks that Pediment 5 removed. They no longer render. Convert them: Settings → Pediment Theme → Convert legacy blocks." Link to the tab.
- Test: `plugin/tests/phpunit/Content/LegacyContentNoticeTest.php`, with valid and empty cases:
  - 0 posts → no notice;
  - 2 posts with legacy blocks → the notice with the count;
  - a non-admin user → no notice;
  - after a conversion → the cached count is cleared.

- [ ] Step 1: Write the failing tests.
- [ ] Step 2: Implement; tests pass; `composer lint -d plugin`.
- [ ] Step 3: Commit `feat: warn admins about leftover legacy content`.

### Task 5: Delete the 18 legacy blocks

**Files:**
- Delete:
  - `plugin/src/blocks/{hero,cta,feature,feature-grid,stat,stat-grid,step,steps,section-head,media-text,testimonial,testimonial-grid,logo-cloud,pull-quote,image-caption,prose,faq,faq-item}/`
  - `plugin/src/Content/LegacyBlocks.php` and its hook at `plugin/src/Bootstrap.php:36`.
- Delete tests:
  - `plugin/tests/phpunit/BlockRender/{Cta,Faq,FeatureGrid,Hero,ImageCaption,LogoCloud,Prose,PullQuote,SectionHead,StatGrid,Stat,Steps,TestimonialGrid}Test.php`
  - `plugin/tests/phpunit/Content/LegacyBlocksTest.php`
- Rewrite e2e:
  - `plugin/tests/e2e/editor-blocks.spec.ts:5-41`: keep blog-index and slider.
  - `plugin/tests/e2e/icon-picker.spec.ts:18,26`: test the picker on `pediment/icon`.
  - `plugin/tests/e2e/convert-legacy.spec.ts:39-53`: the "hidden from the inserter" test becomes "a legacy block is not registered": `getBlockType('pediment/hero')` is `undefined`. Do not wait on the block type.
- Modify:
  - `plugin/assets/js/reveal.js:3-20`: remove the legacy-only selectors `.starter-hero > *`, `.starter-feature-grid .starter-feature`, `.starter-steps .starter-step`, `.starter-stat`, `.starter-faq-item`, `.starter-cta`, and the dead `.starter-logo-cloud .starter-logo` and `.starter-media-card`. Keep `.starter-band > *` and every `.is-style-*` selector.
  - `plugin/assets/css/theme.css`: fix the stale comments (lines 39-42, 51). Keep `.accent`, `.starter-band`, `.starter-section` and the `.is-style-band-*` rules. Do not remove `.chip` or `.btn*` in this plan.
  - `tools/generate-wpml-config.mjs:43,48,49`: remove the stale `NON_PROSE` entries. Regenerate `plugin/wpml-config.xml`.
  - Block counts: `plugin/plugin.php:46` and `README.md:3` (count `plugin/src/blocks/*` after the deletion).
  - `plugin/src/Abilities/ConvertLegacy.php:39`: the description says the blocks were removed in 5.0.
- Commit message: `feat!: remove the 18 legacy blocks`. Body: what is removed, and the footer `BREAKING CHANGE: the 18 legacy pediment/* presentation blocks are removed. Convert leftover content with Settings → Pediment Theme → Convert legacy blocks before or after updating.`

- [ ] Step 1: Delete, rewrite and modify as listed. Then `rm -rf plugin/build && cd plugin && npm run build`.
- [ ] Step 2: Check `npx wp-env run cli wp eval 'echo WP_Block_Type_Registry::get_instance()->is_registered("pediment/hero") ? "yes" : "no";'`; it prints `no`.
- [ ] Step 3: Run every gate in Global Constraints. All converter tests and all 19 fixtures still pass.
- [ ] Step 4: Commit with the message above.

### Task 6: Docs, roadmap and "Upgrade to 5.0"

**Files:**
- `docs/client-sites.md`: a new "Upgrade to 5.0" section after "Upgrade to 4.0". It says:
  - check the Convert tab first (it must list no posts);
  - then update the plugin;
  - a post that still holds a legacy block shows the admin notice, and the converter still works;
  - a client theme pattern with a legacy block is rejected by `check-page`, and the seeder writes it as it is, so convert patterns with `wp pediment convert --patterns=…` first.
  
  Also fix lines 214-222 and 279.
- `docs/convert.md`: lines 3-7, 73-77 (the hero variants filter stays for the converter), 100-109 (reveal now names only the converted styles), 261-264.
- `docs/blocks.md`: lines 55 and 131-139.
- `docs/mcp.md`: everything Task 3 did not cover.
- `docs/WORDPRESS_TRAPS.md`: 111-134, 139, 232-245, 749-750. Keep the traps; mark the legacy examples as historical, or swap them for kept blocks. The accent trap now names the shared editor script.
- `AGENTS.md:44-47`: the legacy blocks are removed; the converter stays until 6.0.
- `docs/superpowers/plans/2026-10-14-roadmap.md`: Plan 6 status, and the converter removal as a 6.0.0 candidate.
- Check `client-kit/skills/port-page/SKILL.md:107-108` and `client-kit/shared/visual-qa.md:30`; change the wording only where it says the blocks still exist.

- [ ] Step 1: Write the docs.
- [ ] Step 2: `grep -rn "Pediment 5\|5\.0 removes\|still render" docs AGENTS.md README.md client-kit` shows no stale claim.
- [ ] Step 3: Commit `docs: Pediment 5 removes the legacy blocks`.

### Task 7: Final verification and stop

- [ ] Run every gate in Global Constraints from a clean build (`rm -rf plugin/build`). Report each command and its result.
- [ ] Probe in wp-env: insert a page with a legacy block through `createPageWithContent` or `wp eval`. Check that:
  - the front end renders without an error;
  - the admin notice shows the count;
  - the wp-admin Convert tab converts the page;
  - the notice disappears.
  
  Delete the probe page.
- [ ] Stop. Report to the controller. Push, PR, G0 and the release are Jonas's decisions.
