# Session Log

Rolling log. /dev-cycle keeps only the most recent prior session entry plus the current one.

---

## Session 2026-08-06 — claim path, header ownership, client blocks, step 6a (Tasks 1–14)

[09:54] ✅ The claim path: `Pediment\Seeder\Claimer` (`plugin/src/Seeder/Claimer.php`)
backfills `_pediment_seed_key` onto a legacy site's pre-existing, unkeyed rows by
matching the manifest's declared identity — post type, slug, parent, language —
against unclaimed candidates, one new `PlanItem` action per outcome (`claim`,
`no-match`, `ambiguous`). It writes exactly one meta key and never a hash, so a
claimed row stays protected under the Differ's existing rule 2 (missing hash =
treat as edited) until an explicit `wp pediment adopt`. Navs are claimed too,
language-aware, using their own already-carries-the-key source of truth, since
`StateReader::EXCLUDED_TYPES` excludes `wp_navigation` from the map entries use.
Both front doors share one orchestration path, `ClaimRunner`/`ClaimResult`
(`plugin/src/Seeder/`), mirroring `Runner`/`RunResult`: `wp pediment claim
[--dry-run]` (`plugin/wp-cli/ClaimCommand.php`) and two buttons — preview,
apply — on Settings → Pediment Theme → Seeding (`plugin/inc/seeding-admin.php`),
the latter being the only path that exists on Pediment's admin-only Hetzner
hosting.
[09:54] ✅ The header bootstrap now seeds a fresh `header` template part from a
theme-registered `<stylesheet>/header` block pattern when one exists, falling
back to the old generic markup otherwise (`plugin/inc/bootstrap.php`). The
lookup runs on a flagged `init` pass at priority 100, after core's own
`_register_theme_block_patterns()`, so a client theme's own patterns are
already registered when it reads. Still create-only — an existing part is
never touched.
[09:54] ✅ `client-template/` gained an optional client-blocks layer —
`functions.php`, `src/blocks/example-notice/`, a `@wordpress/scripts` build —
scaffolded behind `client-kit`'s new `--with-blocks` flag and pruned from the
scaffold when not requested. CI: `seed-check` builds client blocks when
`src/blocks/` exists and can assert a block registered before wp-env teardown;
the `scaffold` matrix gained a second leg that scaffolds, builds, boots and
seeds a real with-blocks client theme; `client-release.yml` builds blocks and
keeps `src/` out of the release zip.
[09:54] 📚 `docs/seeding.md` documents the claim path end to end — what gets
matched and in what order, `Claimer::apply()`'s idempotency, the gap in its
own nav protection (an already-claimed nav plus one stray unrelated
`wp_navigation` post can be claimed under the wrong key), a worked dry-run
example — and corrects a pre-existing false claim that a first seed against an
already-live site was safe on its own: that safety only covers rows the engine
can already see by `_pediment_seed_key`, which an unclaimed legacy row never
carries; `wp pediment claim` is what gives it one.
[09:54] 🔍 Three follow-ups from this branch's review loop went to
`docs/BACKLOG.md` rather than being fixed here: `ClaimRunner::run()` does not
catch `ManifestError` the way `Runner::run()` does, so a malformed manifest is
an uncaught critical error on both claim front doors rather than a report —
worse on admin-only hosting, where wp-admin is the only door; a real
(non-dry-run) `wp pediment claim` against a site with no manifest still prints
"Pediment claim — dry run" / "Nothing was written (--dry-run)", accurate in
effect (nothing to apply) but misleading in wording; and, lowest severity, the
wp-admin missing-manifest message is untranslated, matching `Runner`'s
existing precedent for the identical string.
[09:54] 🔍 This log jumps from 2026-08-01 (step 4) straight to today: step 5
(the scaffolder and `/start`), the client-kit external-distribution pass, and
the licensing pass all shipped in between with no session-log entry for any
of them. Not reconstructed here — the gap is being flagged, not silently
backfilled.

### Planned next
- Task 15 of this plan: full verification (PHPUnit, phpcs, lint, Playwright)
  and the gated push — not yet run.

### Need a decision on
_(none)_

---

## Session 2026-08-01 — LanguageProvider and Polylang, step 4 (Tasks 1–16)

[19:10] ✅ Migration step 4 SHIPPED: a Pediment site becomes multilingual via
Polylang, entirely behind the `LanguageProvider` seam
(`plugin/src/Language/LanguageProvider.php`). A manifest's `languages` section
declares the site's languages (default first, order load-bearing);
`wp pediment languages` configures Polylang from it before the first seed
(languages, `default_lang`, `wp_navigation` translatability, media/taxonomy
translation locked off, language-rooted URLs); `wp pediment seed` hard-errors
if the manifest and Polylang's own configuration disagree, rather than
writing content no translation lookup can find. Per-entry `languages`
overrides (`title`/`slug`/`pattern`) resolve through `EntrySpec::titleFor()` /
`slugFor()` / `patternFor()`; a non-default language with no declared slug
derives `<slug>-<lang>` (Polylang does not hook `wp_unique_post_slug`, so all
languages share one slug namespace). Translation groups are linked once,
after every language is written, never per language
(`pll_save_post_translations()` replaces the whole group). Navigation menus
are seeded per language and the header's ref-less `core/navigation` block is
bound explicitly to the current language's menu, closing the gap where
core's own newest-post fallback would otherwise pick whichever menu (in
whichever language) was created last. `wp pediment adopt <key>
--language=<code>` exports one language's live markup into
`patterns/<stem>.<lang>.php`. `tools/generate-wpml-config.mjs` now generates
`plugin/wpml-config.xml` from every block's `block.json`, checked in CI
(`--check`), replacing a hand file that had silently drifted (three shipping
blocks were missing from it entirely).
[19:10] ✅ Full verification, confirmed directly rather than copied from the
plan: monolingual PHPUnit **588 tests / 1541 assertions, 1 pre-existing
skip**; Polylang PHPUnit (`phpunit-polylang.xml.dist`) **50 tests / 85
assertions**, both re-run clean on this branch. `composer lint`: **0 errors**
(6 pre-existing warnings, unrelated to this step). `npm run lint:blocks` and
`npm run lint:colors`: clean. `node tools/generate-wpml-config.mjs --check`:
up to date. Playwright **53/53** — the count matches a fresh `grep` over
every `test()` in `plugin/tests/e2e/*.spec.ts`, and the full suite's
run-twice-from-a-destroyed-environment property (no residue between runs) was
proven during Task 15's review and re-confirmed here rather than re-run,
since Task 17 (full verification + gated push) repeats it end to end before
this branch ships.
[19:10] 📚 Documented in `docs/seeding.md` (new `## Languages` section: the
manifest shape, the derived-slug rule and why, the pattern-file convention,
`wp pediment languages`, the `TRANSLATIONS` notices, `adopt --language`;
`## Limitations, by design` extended for media/taxonomies, the
Polylang-only seam, and translation content not being generated), eleven new
entries in `docs/WORDPRESS_TRAPS.md` (six from the original plan, five more
found paying for them: Polylang's `post_types` option silently stripping
`_builtin` types, Polylang's boolean options round-tripping through PHP
`bool` instead of `0`/`1`, Polylang auto-tagging a translated post type's
posts on save, `pll_current_language()` firing no filter, and an `enum`
inside a block attribute's `items.properties` making core drop the whole
array), plus pointers from `docs/STANDARDS.md`, `plugin/README.md`, and
`AGENTS.md`, and four new `docs/BACKLOG.md` entries (Medium: a WPML adapter,
per-language media/taxonomy translation, `wp pediment translate`,
language-aware `Verifier` post-conditions).
[19:10] 🔍 Documented, not fixed: a coordinated language removal (dropping a
language from both the manifest and Polylang's own config in the same run)
still unlinks that language's translation groups site-wide, for both entries
and navigation entities — reached by two different routes to the same
`pll_save_post_translations()`-replaces-the-group mechanism; already in
`docs/BACKLOG.md`, not repeated here. `tools/generate-wpml-config.mjs`'s
`NON_PROSE` denylist lives out-of-band in the generator rather than in each
block's own `block.json`, and its `isReference()` heuristic (`/ids?$/i`)
matches any string attribute ending in `id`/`ids`, not only a camelCase
`Id`/`Ids` suffix — both also already in `docs/BACKLOG.md`. `wp pediment seed
--json` is unreachable: WP-CLI 2.12 rewrites any `--json` assoc-arg to
`--format=json` before per-command synopsis validation, and `SeedCommand`
never declares `--format`; predates step 4 (commit e7ea6ce) and was never
caught because PHPUnit calls `SeedCommand::render()` directly, bypassing
WP-CLI's own dispatch. A separate task (16b) fixes it before this branch
ships — deliberately not added to `docs/BACKLOG.md`, since a backlog entry
for something already scheduled to be fixed on this branch would be stale on
arrival.

### Planned next
- Migration step 5: the scaffolder and `/start`.

### Need a decision on
_(none)_

---

## Session 2026-07-31 — declarative seeding engine, step 3 (Tasks 1–17)

[23:05] ✅ Migration step 3 SHIPPED: the declarative seeding engine described in
the approved spec, built across 17 tasks. Identity is `_pediment_seed_key`
(never slug); two hashes arbitrate content (`_pediment_seed_hash` from the
persisted row, `_pediment_seed_source` from the git-side input) so a missing,
foreign, or mismatched hash means "leave content and title alone, enforce
structure only" — which is also what makes a first run against an existing
site safe. Structure (existence, slug, nesting, front/posts page, nav
membership, CPT registration, media presence) is always enforced; only
`trash` is restored, `draft`/`pending` are left alone; terms are create-only
by design (`wp_set_object_terms()` replaces rather than merges). Surfaces:
`wp pediment seed [--dry-run] [--json]`, `wp pediment adopt <key>
[--language] [--dry-run]` (exports live markup back to the theme's pattern
file, converts media refs back to `{{media_url:}}`/`{{media_id:}}`
placeholders, keeps a `.bak`, rolls back on a `Slug:` header mismatch), and
Settings → Pediment Theme → Seeding running the identical `Runner` with PHP
limits lifted. Rewrite rules flush once, softly, at the end; the seeder never
touches `permalink_structure`. The fixture theme
(`tests/fixtures/client-theme/seed/manifest.php`) now seeds itself in CI,
replacing the old hand-written `tests/e2e/fixtures.php`.
[23:05] ✅ Full verification green on wp-env (WP 6.9): plugin PHPUnit 545
tests / 1453 assertions (1 pre-existing skip), phpcs 0 errors, lint:js /
lint:colors / lint:blocks clean, Playwright 44/44 (including the new
`seeding.spec.ts`), and `wp pediment seed --dry-run` against the seeded
fixture reports `0 to write, 0 protected, 0 orphan, 13 unchanged`.
[23:05] 📚 Documented in `docs/seeding.md` (new — manifest reference, the
arbitration contract, reading a dry-run plan, `adopt`, the wp-admin tab, and
the two failure modes), five new entries in `docs/WORDPRESS_TRAPS.md`, plus
pointers from `docs/STANDARDS.md`, `plugin/README.md`, and `AGENTS.md`.
[23:05] 🔍 Documented, not fixed: terms are create-only (a manifest-side term
change on an existing entry is not enforced); `post_author` is `0` for
everything the seeder creates under WP-CLI; a dry-run plan is silent about
front-page/posts-page and taxonomy-term drift (the Applier owns both, neither
produces a plan item); the wp-admin "Apply plan" button has no confirmation
step.

### Planned next
- Migration step 4 (deferred by design in this step): the Polylang adapter,
  per-language pattern files (`patterns/<slug>.<lang>.php`),
  missing-translation reporting, a generated `wpml-config.xml`, and
  translation-group linking (`linkTranslations()` exists today as a no-op).

### Need a decision on
_(none)_

---

## Session 2026-07-30 — plugin absorbs theme, step 2 (Tasks 7–8)

[16:53] 🔍 Tasks 7–8 (theme retirement + fixture client theme/e2e repoint) were
found as an uncommitted 118-file tree from an untracked agent — no report, no
ledger entry, nothing executed. Adversarial review verdict: adopt-and-fix.
[16:53] ✅ Fix wave applied on top of the adopted tree: the Task 6 Critical
(`theme_file_path` shim hijacking block-theme detection for any theme without
`templates/index.html`) is resolved by deleting the shim — the fixture client
theme ships its own minimal `templates/index.html`, which is also the contract
real client themes will follow; `chat-abort.spec.ts` restored.
[16:53] ✅ The ~14 new `@wordpress/*` devDependencies the review flagged as
unjustified turned out to be load-bearing: `plugin/tsconfig.json` extended
`@wordpress/scripts/tsconfig.json`, which wp-scripts 32 no longer ships, so
CI's `lint:js` gate was failing at HEAD with 154 resolver errors; the inlined
tsconfig plus declaring exactly the imported packages makes it green.
[16:53] 🔍 The footer pattern retains its placeholder copy intentionally; client
content belongs to the forthcoming client-theme/seeding work.
[17:23] ✅ Everything proven on a fresh wp-env (WP 6.9, fixture theme active via
mu-plugin): plugin PHPUnit 398 tests / 1096 assertions (1 pre-existing skip),
merged Playwright suite 41/41, JS units 49/49, lint:js / phpcs / lint-blocks /
lint-colors / lint-icons all green. Manual smoke: plugin `page` template
renders (footer pattern present, no fixture `pediment-fixture-index` class),
fixture `foreground` override `#1f2937` wins, plugin-only `primary` `#0A1B33`
survives. Release staging dry-run: `pediment-plugin.zip` top dir `pediment/`
ships plugin.php, build/, templates/, patterns/, tokens/, src/, vendor/,
wpml-config.xml; no editor/, tests/, node_modules.

[18:05] ✅ v3.0.0 SHIPPED. Push → CI green (after one lockfile fix: npm 11
writes lockfiles npm 10 rejects; regenerated with npm 10) → release PR #66
verified (plugin.php header + PEDIMENT_AI_VERSION + manifest all 3.0.0) →
merged → tag carries exactly one asset, `pediment-plugin.zip`: single top
dir `pediment/`, stamped 3.0.0, ships src/build/templates/patterns/tokens/
vendor/wpml-config, zero editor/tests/node_modules entries. Neither legacy
asset name published — 2.4.x theme updaters and old pediment-ai installs
stay pinned by design.

### Planned next
- Migration step 3: the declarative seeding engine (`_pediment_seed_key` +
  `_pediment_seed_hash`), per the approved spec.

### Need a decision on
_(none)_

---

## Session 2026-06-22 — single post polish

[14:47] ✅ Tightened the single-post template after screenshot review: the masthead keeps top padding but uses a smaller article-specific bottom pad, the featured image is capped below wide-size, and post content now uses a constrained layout so normal prose aligns to the reading column.

### Planned next
_(none)_

### Need a decision on
_(none)_

## Session 2026-06-23 — block translation config

[17:05] ✅ Added root `wpml-config.xml` for Polylang Pro/WPML block translation support. It declares translatable text/link attributes for Pediment's custom content blocks, including wildcard paths for hero metrics/ticks, slider slides, and mega-menu columns/links.
[17:05] 🔍 Root XML is not excluded by `.distignore`, so it should be included in parent theme release zips.

### Planned next
- Validate on staging with Polylang Pro + DeepL that parent-theme `wpml-config.xml` is discovered while a child theme is active, especially for wildcard array attributes in `hero`, `slider`, and `mega-menu`.

### Need a decision on
_(none)_
