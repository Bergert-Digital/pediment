# Backlog

Prioritized work. Groups: 🔴 critical · 🟡 high · 🟢 medium · 🔵 ideas/later.
Checked items are removed during the next /dev-cycle tidy pass.

> The shared engine now ships as one plugin artifact with a fixture client theme for
> local verification. Keep client-theme and release checks focused on that boundary.

## 🔴 Critical

_(none currently known — verify by running a user-journey audit)_

## 🟡 High

- [ ] **Commercial protection — licence keys gate updates, a server gates capability.** Needs its
  own spec; the design rationale is in
  [2026-08-05-licensing-and-hygiene-design.md](superpowers/specs/2026-08-05-licensing-and-hygiene-design.md#35-backlog-entry-for-commercial-protection).
  A licence check inside shipped PHP is a speed bump — it gates updates and support (which is what
  drives renewals) and cannot gate execution, which is how every premium WordPress plugin works. The in-plugin AI chat was removed in 4.0.0
  (spec 2026-10-14-core-blocks-mcp-design), so there are no shipped prompts left to protect;
  revisit this entry when the MCP tools gain server-side logic worth gating. Platform decision
  unmade — Freemius, EDD + Software Licensing,
  SureCart, or a merchant of record such as Paddle, the last of which absorbs EU VAT filing.

## 🟢 Medium

- [ ] **`client-release.yml` has never run.** The reusable client release workflow is written and
  its version-stamping is unit-tested (`tools/stamp-theme-version.test.mjs`), but no client repo
  has pushed a `v*` tag through it yet. Verify it end to end on the first real client site, and
  treat a failure there as expected rather than surprising.
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
- [ ] **The client theme has no auto-updater.** Step 5 decision 8: `ThemeUpdater`/`UpdateToken` did
  not come across, so client themes update by admin zip upload. Revisit if step 6 shows it hurts.
- [ ] **Seeding follow-up from the step-3 final review.** Parked deliberately: a site that
  already carries a trashed nav *and* a re-created one under the same seed key aborts the
  whole run on duplicate identity until an operator deletes the trashed row — correct
  behaviour, but an upgrade surprise worth a release note.
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
- [ ] **Media and terms are never claimed** (migration step 6a design decision 6).
  `Pediment\Seeder\Claimer` backfills `_pediment_seed_key` onto pre-existing entries and
  navs, but attachments and terms have no equivalent — `MediaSeeder::keyed()` resolves
  existing attachments only by `_pediment_seed_key`, so a manifest declaring media that
  already exists on the site as unkeyed attachments (a pre-existing logo or hero image,
  say) will upload a duplicate rather than adopt the original. Deliberate for Workation's
  74 client-owned photos, which the manifest never declares; the same gap applies to any
  media a manifest does declare on a site being claimed.
- [ ] **The wp-admin missing-manifest message is untranslated.** `ClaimRunner::run()`'s "No seed
  manifest found. Create …" string (`plugin/src/Seeder/ClaimRunner.php`) has no `__()` wrapper,
  following `Runner::run()`'s existing precedent for the identical message, so it renders in
  English on a non-English admin install. Deliberate and narrow, not worth fixing on its own.
- [ ] **History purge of the removed `docs/images/*.jpg`.** The 11 tracked Unsplash JPEGs
  (~46 MB) were `git rm`'d going forward (monorepo step 1, Task 7) but still bloat every
  clone via history. A full purge (`git filter-repo` or similar) would force-push and
  invalidate every Conductor workspace's shared object store — deferred until no
  workspaces are live. Do this as a deliberate, coordinated one-off, not casually.
- [ ] **Keep the WordPress floor current.** `.wp-env.json` currently pins 6.9;
  upgrade only with a full PHPUnit and Playwright run.
- [ ] **Block empty-state sweep.** Walk the 7 remaining Pediment blocks (`blog-index`, `form`,
  `form-field`, `icon`, `mega-menu`, `slider`, `social-links`) in the editor with empty attributes
  and as a visitor; confirm no broken markup, no `<a href="">`, no PHP notices. File any
  failures as 🔴.
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
  MCP tools (Plan 3) would close the loop end to end.
- [ ] **Language-aware `Verifier` post-conditions.** The Verifier re-reads the database
  after every apply and reports what it claims to own that doesn't actually hold (see
  `docs/seeding.md`'s "Verification problems"), but nothing today checks that a
  language's root URL actually serves that language's front page — a language root
  serving the wrong (or the default language's) front page produces no seed-time
  problem. Since 2026-10-29 the `seed-check` action asserts it in CI (scaffold job and
  every Polylang client's CI); a seed on a live site still does not.
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

- [ ] **`LanguageGate::mismatch()` sorts language codes with plain `sort()`.**
  Found by the final review of migration step 4. Byte-order comparison, not
  locale-aware (`SORT_LOCALE_STRING`/`Collator`) — harmless today because every
  language slug is lowercase ASCII (`Manifest::parseLanguages()` requires
  `sanitize_title($slug) === $slug`), but worth a second look if that
  constraint ever loosens (e.g. a slug containing digits or extra hyphens
  sorting unintuitively next to a plain two-letter code).
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
- [ ] **Decided: a nav orphaned by a dropped Polylang language collides with the default
  language and hard-blocks the seed, rather than being silently ignored.** Was recorded as an
  open design question by the untagged-nav fix in `NavSeeder::languageOf()` (migration step 6a
  final review, item 1). The human partner has since ruled: take the block, it is the wanted
  behaviour. `Languages::delete()` → `TranslatableObject::delete_language()` calls
  `wp_delete_object_term_relationships()`, so a nav orphaned by removing a language is left
  genuinely **untagged** — byte-identical in the database to a legacy nav that was never
  language-tagged — and no rule reading only the language taxonomy can tell the two apart.
  Reasoning: an errored plan writes nothing for the whole seed, not just navs (`Runner::run()`
  returns on `Plan::hasErrors()` before `MediaSeeder::apply()`, `Applier::apply()`, and
  `NavSeeder::apply()` all run), so the failure is loud and lossless — and the alternative already
  exists as a known, silently-lossy parked defect (the entry above, "A coordinated language
  removal silently unlinks that language's translation groups"); blocking here stops the nav half
  of that from staying silent instead of growing a second quiet-data-loss path alongside it. Cost,
  stated plainly: a site that has dropped a language has **every subsequent seed hard-blocked** —
  not just its nav phase — until an operator deletes or re-keys the orphaned navigation post
  (`NavSeeder::duplicates()`'s "carried by 2 navigation entities" error; see docs/seeding.md's
  "Duplicate seed key" failure mode). Pinned by
  `NavLanguageTest::test_a_nav_orphaned_by_a_dropped_language_is_reported_not_ignored`. The
  seeder-marker alternative that would separate the two cases cleanly is recorded next, and
  remains genuinely open.
- [ ] **Open: a seeder-written `_pediment_seed_lang` marker would separate a legacy untagged
  nav from a language-orphaned one — for navs created after the marker exists.** Identified by a
  later review of the decision above; not decided against, just not built. Stamping a
  `_pediment_seed_lang` meta key in `NavSeeder::apply()`'s CREATE branch, alongside `Meta::KEY`
  and `setLanguage()`, would let `languageOf()` tell the two untagged cases apart: no marker at
  all means legacy (default-language bucket, as today); a marker naming a language no longer in
  `$this->lang->languages()` means orphan. It does not violate this branch's core property that a
  claim writes exactly one meta key — the marker would be written only on CREATE, never by
  `Claimer`. Its limit: it cannot retro-classify navs created before the marker existed, so it
  fixes the future, not the rows already on a site being migrated. **Do not substitute a slug
  heuristic for it:** a seeder-created nav's slug is `slugFor()`'s derived form (`primary-fr`), so
  "untagged and slug matches `<key>-<unconfigured language>`" looks like a cheap stand-in — but
  the production migration target's legacy menus are literally named `primary-2`, which parses
  identically to `primary-<language "2">`. Adopting that heuristic would misclassify the exact
  legacy-nav case the slug-blind claim rule exists to handle.
- [ ] **Race window in the deferred header seed.** `pediment_bootstrap_maybe_seed_header()`
  (`plugin/inc/bootstrap.php`) does `get_option()` then `delete_option()` non-atomically, so two
  concurrent requests arriving right after a theme switch could both pass the flag check and both
  create a `header` template part. Real, but narrow and self-limiting: the window is one theme
  switch wide, and `pediment_bootstrap_header_template_part()` is itself idempotent per theme on
  every subsequent request. Ruled out of scope by the migration step 6a final review; fix with an
  atomic claim (e.g. `add_option()` as the lock) if it ever actually bites.
- [ ] **Two CLAIM plan items can target the same post ID and the preview does not say so.**
  `Claimer::plan()` (`plugin/src/Seeder/Claimer.php`) can emit two `claim` lines pointing at one
  post — the entry pass and the nav pass do not cross-check, and neither do two entries whose
  candidate queries converge. No data is harmed: `Claimer::apply()`'s already-carries-a-key guard
  catches the second write and returns it as an error, so exactly one key lands. Only the preview
  is less honest than it could be, showing two claims where one will happen. Ruled out of scope
  by the migration step 6a final review. The fix is a dedupe pass over `$plan` that reports the
  collision as `ambiguous` before the operator runs the real thing.
- [ ] **Decision 8's producer side has no example: `client-template/patterns/` ships no
  `header.php`.** The plugin reads a `<stylesheet>/header` block pattern to supply the initial
  markup for the seeded header template part (step 6a decision 8), but no scaffolded client theme
  actually provides one. So the consumer branch is exercised only by an inline
  `register_block_pattern()` in `BootstrapTest`, and CI proves the generic-fallback branch and
  nothing else — every real scaffolded theme takes the fallback. A genuine gap, but new feature
  work rather than a fix, so ruled out of scope by the migration step 6a final review. Shipping a
  `client-template/patterns/header.php` would close it and give the scaffolder a brandable header
  out of the box.
- [ ] **Client-blocks template nits, all ruled out of scope by the migration step 6a final
  review.** (a) `client-template/src/blocks/example-notice/render.php` wraps
  `get_block_wrapper_attributes()` in `wp_kses_data()`, which is the wrong escaper for an
  already-escaped attribute string and could mangle it. (b) `client-template/functions.php` calls
  `esc_html__()` with no `load_theme_textdomain()`, so the strings are never actually
  translatable. (c) `client-release.yml`'s `--exclude 'src'` matches a directory named `src` at
  any depth, not just the theme root, so a legitimately shipped nested `src/` would be dropped
  from the zip. None of the three affects a shipping client site today.
- [ ] **Nav submenus are one level deep, and nav labels are not translatable.** Both are
  deliberate (migration step 6b design decisions 3 and 4,
  [2026-08-06-workation-client-theme-step6b-design.md](superpowers/specs/2026-08-06-workation-client-theme-step6b-design.md)).
  A `children` key inside `children` is rejected by `Manifest::navItem()` rather than flattened,
  because an unbounded tree needs recursion guards on a serializer that runs on every seed. A
  declared `label` is still written verbatim into every language, so a multilingual menu must omit
  `label` and let each entry's per-language title carry it — a site that genuinely needs a menu
  label differing from its page title in more than one language has no way to express it, which is
  what Workation's retired `NavTranslations.php` did with a 489-line map. Revisit only if a client
  actually needs it.
- [ ] **The derived-slug rule's stated reason does not hold universally.**
  [docs/seeding.md](seeding.md)'s "The derived slug rule, and why" asserts that Polylang does not
  hook `wp_unique_post_slug`, and derives the `<slug>-<lang>` default from that. Workation's
  staging site has four pages all carrying `post_name = home`, one per language, so on that
  configuration Polylang does uniquify per language. The rule itself stays correct as a default — a
  per-language slug that collides really does produce the permanent `Verifier` mismatch described
  there — but the explanation should say Polylang *may not* hook it rather than that it does not,
  and note that an explicit per-language `slug` override is how a site that does allow shared slugs
  declares them. Found while writing the migration step 6b design.

## 🔵 Ideas / later

- [ ] Pattern coverage: 6 plugin patterns exist. Since Pediment 5 pages are built from core
  blocks and named styles; consider a small library that shows every default named style.
- [ ] Accessibility pass: keyboard/focus and contrast audit across blocks against the
  default token palette.
