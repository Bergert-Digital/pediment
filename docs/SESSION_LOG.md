# Session Log

Rolling log. /dev-cycle keeps only the most recent prior session entry plus the current one.

---

## Session 2026-10-29 — product sense, backlog tidy

[--:--] ✅ Wrote a new `docs/PRODUCT_SENSE.md` for the plugin-only product: four
journeys (client-site developer, MCP agent, site editor, admin-only operator). The
old one was deleted in 354df7f because it described the retired parent/child theme.

[--:--] ✅ Tidied `docs/BACKLOG.md`: removed 9 checked items and 6 moot ones (the v3
release check — 3.0 to 6.1 all shipped as `pediment-plugin.zip`; the Workation
cutover plan and its admin-only claim caveat — Workation runs the plugin since
October and G1 confirmed no site is left to move; `test:js` — it has 4 test files;
icon sub-projects B and C — the Phosphor data and the searchable picker exist).
Re-scoped the empty-state sweep to the 7 remaining blocks, pattern coverage to
named styles, and the sort item to `LanguageGate::mismatch()`. The previous
session's "Planned next" (step 6b theme half) is done and is not carried over.

[--:--] ✅ A real `wp pediment seed` or `claim` (and the wp-admin Apply buttons) that
stopped before writing — no manifest, malformed manifest, language mismatch, errored
plan — reported itself as a dry run. `RunResult` and `ClaimResult` now carry an
explicit `dryRun` flag; the report says "Nothing was written." for a real run that
wrote nothing, and `--format=json` gained `dry_run`. Backlog listed only the claim
half; the seed half was the same bug on the operator's main button.

[--:--] ✅ Fixed drift in `docs/VISION.md` (it still said 24 blocks and did not name
the design layer, the MCP server or AI agents as users) and in `docs/STANDARDS.md`
(block namespaces still said parent/child).

[--:--] ✅ A dry run (or a real run stopped before it wrote) printed undeclared-media
problems under `VERIFICATION FAILED`, though phase 5 never ran. They now appear under
`PROBLEMS (found while planning)`; an applied run keeps the old heading.

[--:--] ✅ `wp pediment adopt --language=<code>` now rejects a code the site has not
configured and names the configured ones. Before, a typo failed later as "No seeded
post carries the key", which pointed at the key.

[--:--] 🔍 This log jumps from 2026-08-06 to today. Plans 1–8 of the
2026-10-14 roadmap (Pediment 4.0 to 6.1) shipped with no entry here; the roadmap
(`docs/superpowers/plans/2026-10-14-roadmap.md`) is their record.

[--:--] ✅ Journey B check: `node tools/mcp-smoke.mjs` passes against the local wp-env
(8 tools; page and post written, read and checked; legacy page and both rule violations
reported).

[--:--] ✅ The `scaffold` CI job gained a multilingual leg (`answers-multilingual.json`,
de + en): it boots Polylang, runs `wp pediment languages` and seeds translations.
`seed-check` now asserts that every Polylang language's home URL returns 200 with its
own `<html lang>` and its own front-page translation; client CI inherits the check.
Proven locally on ports 8898/8899 (passes; fails when the expected page ID is wrong);
not yet proven on GitHub Actions.

### Check-in

#### Done this session
- New `docs/PRODUCT_SENSE.md` (four journeys for the plugin-only product).
- Backlog and session log tidied; 15 done or moot items removed, 3 re-scoped.
- Seed and claim reports no longer call a real run that wrote nothing a dry run
  (CLI, wp-admin Apply buttons, `--format=json` gains `dry_run`).
- Planning problems print under `PROBLEMS (found while planning)`, not
  `VERIFICATION FAILED`.
- `adopt --language` rejects an unconfigured language by name.
- `VISION.md` and `STANDARDS.md` brought up to Pediment 6.

#### What I noticed
- `plugin/package-lock.json` is modified in this workspace by the setup's
  `npm install`, not by this session. Left unstaged; CI needs an npm-10 lockfile.
- wp-env pins WordPress 6.9 while client-kit validates against 7.1 (backlog:
  "Keep the WordPress floor current").

#### Planned next
- Block empty-state sweep of the 7 remaining blocks, in the editor and as a visitor.
- Add the multilingual leg to the `scaffold` CI matrix (needs a push to prove).
- Assert the expected create count in `seed-check`, not only convergence.

#### Need a decision on
_(none)_

---

## Session 2026-08-06 — nav submenus, step 6b plugin half (Tasks 1–5)

[--:--] ✅ `NavSpec::$items` accepts an optional `children` key, validated by a new
recursive `Manifest::navItem()` that permits exactly one level of nesting and
applies the identical entry/url rules at both levels. `children` inside
`children` is a `ManifestError`, not a silently flattened menu — an unbounded
tree would need recursion guards on a serializer that runs on every seed. The
top-level error strings are byte-identical to before, because the new `$path`
argument reproduces them.

[--:--] ✅ `NavSeeder::serialize()` emits `wp:navigation-submenu` for an item
with children, wrapping their `wp:navigation-link` blocks. The per-item
attribute building moved into `linkAttrs()`, with key order and
`JSON_UNESCAPED_SLASHES` preserved byte-for-byte — `plan()` decides UPDATE by
string-comparing stored `post_content` against a fresh serialize, so reordering
them would have made every nav on every existing site rewrite itself once.
`test_an_unchanged_nav_is_not_rewritten` is the tripwire and stayed green
throughout.

[--:--] 🔍 Adding submenus opened a real hole that Task 3 closed:
`unresolvedEntries()` walked only top-level items, so a nav whose *child* entry
failed to resolve was written one link short — proven by a failing test that
showed the child link stripped out of a live menu. It now recurses through
`unresolvedItem()`, and the plan's `items` count goes through `countLinks()` so
both sides of the diff count the same things.

[--:--] ✅ Suites: plugin 632 tests / 1673 assertions (up from a 621/1645
baseline, 1 pre-existing skip unchanged), Polylang 65/132. `lint:colors`,
`lint:js` and `phpcs` clean.

[--:--] 🔍 Recorded two deliberate limits in the backlog: one-level nesting and
untranslatable nav labels (step 6b design decisions 3 and 4). Also recorded that
`docs/seeding.md`'s derived-slug rationale — "Polylang does not hook
`wp_unique_post_slug`" — is contradicted by Workation's staging site, which has
four pages sharing `post_name = home`, one per language. The rule stands as a
default; the reason given for it does not hold universally.

### Planned next
- Step 6b's theme half (`docs/superpowers/plans/2026-08-06-workation-client-theme-step6b.md`),
  which is blocked until this ships as a plugin minor — client CI runs `seed-check`
  against the published plugin zip.

### Need a decision on
_(none)_
