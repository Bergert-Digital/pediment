# Session Log

Rolling log. /dev-cycle keeps only the most recent prior session entry plus the current one.

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
