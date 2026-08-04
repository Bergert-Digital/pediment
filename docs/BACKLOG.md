# Backlog

Prioritized work. Groups: 🔴 critical · 🟡 high · 🟢 medium · 🔵 ideas/later.
Checked items are removed during the next /dev-cycle tidy pass.

> The shared engine now ships as one plugin artifact with a fixture client theme for
> local verification. Keep client-theme and release checks focused on that boundary.

## 🔴 Critical

_(none currently known — verify by running a user-journey audit)_

## 🟡 High

- [ ] **Verify the v3 release pipeline end-to-end.** With user approval, confirm a
  release produces only `pediment-plugin.zip`, installs as `plugins/pediment`,
  and does not publish either legacy asset name.
- [ ] **Archive `pediment-child-theme`.** Migration step 5 replaced it with `client-template/` in
  this monorepo. The old repo still exists and still describes a parent/child world that no longer
  ships. Archive it on GitHub with a README pointing at `docs/client-sites.md`. Needs an explicit
  go-ahead — it is an outward-facing, hard-to-reverse action.
- [x] **Header template part scoping was a `WP_Query` singularity bug, not a tagging-order race.**
  Found during migration step 5's Task 15 full-verification rehearsal; fixed on the same branch in
  `plugin/inc/bootstrap.php`. The original diagnosis (activation-order tagging skipping recreation
  of an already-existing part) was refuted: `pediment_bootstrap_header_template_part()`'s lookup
  used the singular `name` query var, which makes `WP_Query::parse_query()` set `is_singular` —
  and `WP_Query::get_posts()` only applies `tax_query` when the query is *not* singular. So the
  theme-scoping check never actually ran the tax query and matched **any** existing "header" part
  regardless of theme, on every site, not just fresh ones. Switching the lookup to
  `post_name__in` (which keeps the query non-singular) makes the tax_query apply for real, so each
  theme gets its own correctly-scoped header part; covered by
  `BootstrapTest::test_bootstrap_scopes_header_part_to_each_active_theme`.
  `.github/actions/seed-check/action.yml`'s front-page assertion remains as a standing regression
  guard, checking for the exact missing-template-part string a recurrence would show.

## 🟢 Medium

- [ ] **`client-release.yml` has never run.** The reusable client release workflow is written and
  its version-stamping is unit-tested (`tools/stamp-theme-version.test.mjs`), but no client repo
  has pushed a `v*` tag through it yet. Verify it end to end on the first real client site, and
  treat a failure there as expected rather than surprising.
- [x] **`/pediment:port-page` now says how to derive the theme slug.** Found during migration step
  5's Task 12 review; fixed in `client-kit/skills/port-page/SKILL.md` Step 3, which now says to
  derive it from `package.json`'s `name` or an existing `patterns/*.php` file's `Slug:` header.
  Correction to the original framing: a wrong namespace is not silent for the default language —
  `ContentResolver::resolve()` (`plugin/src/Seeder/ContentResolver.php:56-57`) throws
  `ManifestError` when the default-language pattern for a page is not registered, aborting the run
  loudly. Only a *translation* pattern (a non-default-language pattern that is missing) degrades
  quietly, falling back to the default language's content. Still worth the line — a wrong
  namespace is easy to type and easy to miss either way.
- [ ] **`/pediment:start`'s script path only resolves from a monorepo checkout.** The skill invokes
  `node client-kit/scripts/scaffold.mjs`, which works when run from a clone of this repo — the
  internal path, and the only one step 5 ships. Once the kit is installed as a Claude Code plugin
  (the productisation path decided in the step-5 design), the working directory is the client's
  parent folder and that relative path resolves to nothing. Claude Code exposes a plugin-root
  variable for exactly this; confirm its name and availability for skills before relying on it,
  then make the skill prefer it and fall back to the checkout-relative path. Found during
  migration step 5's Task 11 and deliberately not guessed at.
- [ ] **The scaffold CI job asserts convergence, not completeness.** `seed-check` proves the front
  page renders and that a second dry run reports `0 to write`, but nothing asserts an expected
  page or nav count — a scaffolder regression that silently emitted three pages instead of four
  would converge on three and pass. Found during migration step 5's Task 9 review. Cheap fix:
  assert the plan's create count on the first run against the fixture's declared page count.
- [ ] **`lint:colors` cannot see `client-template/`.** `tools/lint-colors.mjs` hardcodes its scan
  root to `plugin/src/blocks/` and only walks `.scss`/`.css`, so no colour literal reaching the
  client template — or a scaffolded client repo — is ever caught. Confirmed during migration
  step 5's Task 5 review; the template carries no literals today, so the gap is latent rather
  than active. Widening the scan root is the obvious fix; decide whether client repos should be
  linted at all first, since they are the ones that multiply.
- [ ] **Brand voice is captured but not consumed.** `/pediment:start` writes positioning and tone
  into `docs/brief.md`; `PromptBuilder` still builds a fully static prompt and reads no options.
  Deliberate (step-5 design decision 7) — close the loop when the AI side is next worked on, and
  until then keep the skill honest about it.
- [ ] **The client theme has no auto-updater.** Step 5 decision 8: `ThemeUpdater`/`UpdateToken` did
  not come across, so client themes update by admin zip upload. Revisit if step 6 shows it hurts.
- [ ] **Seeding follow-ups from the step-3 final review.** Neither blocks a merge, both
  were parked deliberately: (a) a site that already carries a trashed nav *and* a
  re-created one under the same seed key now aborts the whole run on duplicate identity
  until an operator deletes the trashed row — correct behaviour, but an upgrade surprise
  worth a release note; (b) `--dry-run` prints unresolved-media problems under a
  `VERIFICATION FAILED` heading even though phase 5 never ran — the finding is real and
  pre-write, the heading is not.
- [ ] **A coordinated language removal silently unlinks that language's translation
  groups.** Found by review during migration step 4, Task 10, for entries; the same
  outcome was confirmed for navigation entities during Task 11 review, reached by a
  different route. Both share the same root mechanism: `pll_save_post_translations()`
  REPLACES the whole group, so Polylang's own `save_translations()` diffs the new map
  against the group's *current* membership and deletes anything absent from it — a post
  whose row never changed can still be unlinked just because it was left out of the map
  handed to `linkTranslations()` on a run that touched its siblings.
  - **Entries** (`Applier::apply()`): excludes `ORPHAN` plan items from the ID map it hands
    to `linkTranslationGroups()`, even when their `postId` is still valid; `DesiredState`
    crosses every seed key with every configured language, so a single language cannot go
    missing for one isolated page — but dropping a language from both the manifest and
    Polylang's own config in the same run passes the Runner's language gate (the two sets
    still match), and every post in that language becomes `ORPHAN` everywhere at once. Each
    key's map then omits that language, and the group-replacing write unlinks those posts
    site-wide — even though the plan reports them as `orphan`, i.e. "left in place."
  - **Navigation entities** (`NavSeeder::keyed()` / `linkTranslationGroups()`): has no
    `ORPHAN` concept at all — `keyed()` detects a nav's language by matching it against the
    *currently configured* language list, so a nav in a just-dropped language matches no
    candidate and is filed under the empty-language key (`navKey|''`) instead of its real
    one. `linkTranslationGroups()`'s own `'' === $language` guard (there to skip malformed
    map keys) then excludes it from the map, and the same still-configured-siblings write
    unlinks it from its group, exactly as for entries.
  - A `--dry-run` does not surface either path: it shows the same `orphan` line (entries) or
    no line at all (navs — a nav that stops matching any configured language is not
    reported as changing) it always shows, which reads as "nothing happened," not "this post
    is about to lose its translation-group membership." Fix, if wanted, is either to
    preserve group membership for no-longer-configured languages, or to report the unlinking
    as an explicit plan item, for both paths. Narrow and deliberate-removal-only, not a
    routine-editing risk — not fixed here on purpose.
- [ ] **Seeding gaps the plan deferred.** A dry run says nothing about front-page, term,
  or site-logo drift (the Applier owns those and they produce no plan items); terms are
  create-only by design, so a manifest-side term change is never applied to an existing
  entry; `post_author` is 0 for entries created under WP-CLI; `adopt` does not map sized
  image variants or `srcset` entries back to `{{media_*}}` placeholders. All are
  documented in [docs/seeding.md](seeding.md) — revisit when migration step 6 shows which
  actually hurt.
- [ ] **History purge of the removed `docs/images/*.jpg`.** The 11 tracked Unsplash JPEGs
  (~46 MB) were `git rm`'d going forward (monorepo step 1, Task 7) but still bloat every
  clone via history. A full purge (`git filter-repo` or similar) would force-push and
  invalidate every Conductor workspace's shared object store — deferred until no
  workspaces are live. Do this as a deliberate, coordinated one-off, not casually.
- [ ] **`npm run test:js` has no tests.** Jest is wired via wp-scripts but there are no JS
  unit tests. Either add coverage for the few bits of editor logic worth unit-testing, or
  drop the script so CI/devs aren't misled. Decide and act.
- [ ] **Keep the WordPress floor current.** `.wp-env.json` currently pins 6.9;
  upgrade only with a full PHPUnit and Playwright run.
- [ ] **Block empty-state sweep.** Walk all 11 blocks in the editor with empty attributes
  and as a visitor; confirm no broken markup, no `<a href="">`, no PHP notices. File any
  failures as 🔴.
- [ ] **Sub-project B — full-Phosphor icon delivery.** Replace the hand-built sprite in
  [plugin/inc/icons.php](../plugin/inc/icons.php) (printed per page via `wp_body_open`) with a
  scalable mechanism for the full ~9000-icon Phosphor set — Phosphor webfont, or per-icon
  inline SVG via `@phosphor-icons/core` emitting only icons actually used. Theme-wide:
  touches `inc/icons.php`, build tooling, page weight, every block calling
  `pediment_icon()`. Needs its own brainstorm/spec. Prereq for C. Deferred from the
  2026-05-19 mega-menu-editor-layout spec (sub-project A).
- [ ] **Sub-project C — searchable icon picker.** Block-editor picker over the full
  Phosphor catalog (virtualized list + search from B's icon-name manifest), wired into
  `pediment/mega-link`'s `icon` attribute and reusable across blocks. Depends on B; until
  then the field is a relocated `TextControl`. Deferred from sub-project A.
- [ ] **A WPML adapter for the `LanguageProvider` seam.** Migration step 4 built the
  Polylang implementation only; `LanguageProvider`
  (`plugin/src/Language/LanguageProvider.php`) is the interface the seeding engine
  actually depends on, and everything Polylang-specific lives behind it
  (`PolylangProvider`, `PolylangSetup`, and the two `inc/` files that call `pll_*`
  directly). A WPML implementation is roughly 150 lines against the same interface —
  build it when a client site actually needs WPML, not speculatively now.
- [ ] **Per-language media and taxonomy translation.** Migration step 4 deliberately
  ships one attachment and one term set per site, locked off in Polylang's own config
  (`media_support => 0`, `taxonomies => []`, see `docs/seeding.md#languages`). Revisit
  only if step 6 shows a real client site (Workation is the currently planned one)
  actually needs per-language images or taxonomies — building it speculatively risks
  guessing wrong about the shape client themes need.
- [ ] **`wp pediment translate` — an AI command that writes the missing pattern file a
  seed notice names.** `wp pediment seed --dry-run`'s `TRANSLATIONS` section already
  names exactly which `patterns/<slug>.<lang>.php` file is missing and what `Slug:`
  header it needs (`docs/seeding.md#the-translations-section-of-a-dry-run-plan`); today
  closing that gap is manual (write the file, or translate in the editor and
  `wp pediment adopt --language`). A command that drafts the missing file via the
  existing AI editor plumbing would close the loop end to end.
- [ ] **Language-aware `Verifier` post-conditions.** The Verifier re-reads the database
  after every apply and reports what it claims to own that doesn't actually hold (see
  `docs/seeding.md`'s "Verification problems"), but nothing today checks that a
  language's root URL actually serves that language's front page — a language root
  serving the wrong (or the default language's) front page produces no seed-time
  problem, only an e2e failure once someone happens to check the rendered page.
- [ ] **`tools/generate-wpml-config.mjs`'s translatability heuristics need a first-party
  declaration mechanism.** Found by review during migration step 4, Task 14. Two related
  gaps, both accepted for now because the task's edit scope forbade touching block.json
  attributes outside adding `items` to arrays: (a) `isReference()`'s `/ids?$/i` matches
  any string attribute ending in `id`/`ids`, not just a camelCase `Id`/`Ids` suffix —
  nothing currently triggers this, but a future attribute named e.g. `grid` or `valid`
  would be silently excluded from translation with no explanation; (b) the `NON_PROSE`
  denylist of known-non-prose scalar attributes (icon slugs, layout tokens, colors, CSS
  lengths, reference ids that don't match the `Id`/`Ids` suffix) lives in the generator
  script itself rather than in each block's own `block.json` — correct under the current
  constraint, but a future block with a similar non-prose string attribute will be
  silently marked translatable until someone remembers to update a list in a file block
  authors have no reason to open. Once the edit-scope constraint is lifted, prefer a
  first-party declaration in `block.json` itself (e.g. an `x-translatable: false` flag on
  the attribute) over both the regex heuristic and the out-of-band list.

- [ ] **`Runner::languageMismatch()` sorts language codes with plain `sort()`.**
  Found by the final review of migration step 4. Byte-order comparison, not
  locale-aware (`SORT_LOCALE_STRING`/`Collator`) — harmless today because every
  language slug is lowercase ASCII (`Manifest::parseLanguages()` requires
  `sanitize_title($slug) === $slug`), but worth a second look if that
  constraint ever loosens (e.g. a slug containing digits or extra hyphens
  sorting unintuitively next to a plain two-letter code).
- [ ] **`wp pediment adopt --language=<code>` does not validate the code
  against the site's configured languages.** Found by the final review of
  migration step 4. Today an unrecognised code fails downstream, inside
  `Adopter::adopt()`, with "No seeded post carries the key" — technically
  correct but not the clearest message for what is actually a bad `--language`
  value; validating against `LanguageRegistry::provider()->languages()` before
  calling `Adopter` would fail faster and say so directly.
- [ ] **`linkTranslationGroups()` sits on opposite sides of the Kses-suspended
  region in `Applier` and `NavSeeder`.** Found by the final review of
  migration step 4. `NavSeeder::apply()` calls it INSIDE the `try` block,
  before `Kses::restore()` runs in `finally`; `Applier::apply()` calls it
  AFTER the `finally` block has already restored Kses. `pll_save_post_translations()`
  can call `wp_insert_term()` with a serialized-array `description`, and term
  descriptions go through the same `kses_init_filters()`-installed filtering
  under WP-CLI (no current user) that motivates suspending Kses around
  `post_content` writes elsewhere in both classes. Not shown to cause an
  actual corruption in either position by this review — the description is
  `maybe_serialize()`d PHP, not block-comment markup, so this may be a
  non-issue — but the two classes disagree on placement for what is
  documented as "the same rule" in both docblocks, and that alone is worth
  resolving one way or the other rather than leaving accidental.
- [ ] **No end-to-end CI coverage of the multilingual path.** The `scaffold` job in
  [.github/workflows/ci.yml](../.github/workflows/ci.yml) runs a single fixture,
  `answers-ci.json`, whose `languages` array holds one entry — so the
  `answers.languages.length > 1` branch at `client-kit/scripts/scaffold.mjs:321`,
  Polylang's activation in the booted site, and translated-page seeding never execute
  in CI. `scaffold.test.mjs:245` covers the scaffold-side half (Polylang lands in
  `.wp-env.json`, `'languages' => array(` lands in the manifest), but nothing boots a
  two-language site and asserts it seeds and renders. Called the largest untested seam
  left by review. `client-kit/tests/fixtures/answers-multilingual.json` (de + en)
  already exists, so a second matrix entry over the fixture path — feeding both the
  scaffold step and the `seed-check` action — is the whole fix.

## 🔵 Ideas / later

- [ ] Pattern coverage: only 3 patterns exist (`contact-page`, `hero-cta-faq`,
  `prose-article`). Consider a small library that showcases every block.
- [ ] Accessibility pass: keyboard/focus and contrast audit across blocks against the
  default token palette.
