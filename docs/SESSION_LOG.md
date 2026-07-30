# Session Log

Rolling log. /dev-cycle keeps only the most recent prior session entry plus the current one.

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
