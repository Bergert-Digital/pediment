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
- [x] **Archive `pediment-child-theme`.** Done 2026-08-05 with explicit go-ahead. `Bergert-Digital/Pediment-Child-Theme`
  now serves a deprecation README pointing at `docs/client-sites.md` (commit `c6e198b`) and is
  archived; it stays public so existing links keep resolving. `Bergert-Digital/pediment-ai` was
  closed in the same pass — unarchived, set private, re-archived — since it carried the plugin's
  pre-merge history with no consumers.
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
- [x] **The `--with-blocks` flag step 5 deferred is built, closing the "client theme has no
  bespoke-block tooling" gap.** Step 5 decision 5 (`2026-08-03-scaffolder-and-start-step5-design.md`)
  punted client-block tooling until a client needed one; migration step 6a's design
  (`2026-08-05-migration-step6-design.md` decision 2) built it. `client-template/` gains an
  optional `functions.php`, `src/blocks/example-notice/` and a `@wordpress/scripts` build;
  `client-kit`'s scaffolder emits them behind `--with-blocks` and prunes them when not requested;
  `seed-check` builds client blocks when `src/blocks/` exists and asserts one registers before
  wp-env teardown, the `scaffold` CI matrix gained a second leg that scaffolds, builds, boots and
  seeds a real with-blocks client theme, and `client-release.yml` builds blocks while keeping
  `src/` out of the zip. Done 2026-08-06.
- [ ] **The cutover plan is not written.** Migration step 6a built the capability — claim, header
  ownership, client blocks — but the Workation production migration itself is a separate plan by
  design (spec decision 4, `2026-08-05-migration-step6-design.md`). Workation still runs the
  retired parent/child theme stack, and the claim path (`Pediment\Seeder\Claimer`) has only ever
  run against fixtures and CI, never a real legacy database. Write the cutover plan, and rehearse
  claim against a copy of Workation's actual content, before touching production.
- [ ] **A multilingual claim is impossible on admin-only hosting without one-off CLI access.**
  The claim path's language gate (`docs/seeding.md#precondition-configure-languages-before-you-claim`)
  refuses to plan anything while the manifest's declared languages and Polylang's configured set
  diverge, and `wp pediment languages` — the only way to configure them — is WP-CLI only with no
  wp-admin equivalent. The cutover plan (entry above) must budget for arranging one-off CLI access
  before a multilingual claim; it cannot be run entirely through wp-admin.
- [ ] **Commercial protection — licence keys gate updates, a server gates capability.** Needs its
  own spec; the design rationale is in
  [2026-08-05-licensing-and-hygiene-design.md](superpowers/specs/2026-08-05-licensing-and-hygiene-design.md#35-backlog-entry-for-commercial-protection).
  A licence check inside shipped PHP is a speed bump — it gates updates and support (which is what
  drives renewals) and cannot gate execution, which is how every premium WordPress plugin works. The
  part that is actually defensible is moving `plugin/src/Chat/PromptBuilder.php`,
  `plugin/src/Chat/TurnRunner.php` and `plugin/src/Anthropic/SchemaBuilder.php` behind an API so
  they stop shipping: today `plugin/src/Anthropic/Client.php:30` calls `api.anthropic.com` directly
  from the customer's server, so 100% of the prompt tuning lands on their disk. Protection is
  proportional to the work the server does — a proxy that forwards prompts the plugin already
  contains protects nothing. Weigh two costs: the token bill moves to Bergert Digital unless
  bring-your-own-key is kept with only the prompts gated, and the endpoint becomes an availability
  dependency for client sites. Platform decision unmade — Freemius, EDD + Software Licensing,
  SureCart, or a merchant of record such as Paddle, the last of which absorbs EU VAT filing.

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
- [ ] **Media and terms are never claimed** (migration step 6a design decision 6).
  `Pediment\Seeder\Claimer` backfills `_pediment_seed_key` onto pre-existing entries and
  navs, but attachments and terms have no equivalent — `MediaSeeder::keyed()` resolves
  existing attachments only by `_pediment_seed_key`, so a manifest declaring media that
  already exists on the site as unkeyed attachments (a pre-existing logo or hero image,
  say) will upload a duplicate rather than adopt the original. Deliberate for Workation's
  74 client-owned photos, which the manifest never declares; the same gap applies to any
  media a manifest does declare on a site being claimed.
- [ ] **A real, non-dry-run `wp pediment claim` against a site with no manifest still prints
  "Pediment claim — dry run" and "Nothing was written (--dry-run)".** `Reporter::claimText()`'s
  wording comes from `ClaimResult->applied`, and `ClaimRunner::run()` returns `applied: false`
  whenever no manifest is found, regardless of whether `--dry-run` was passed. The branch that
  hardcodes `applied: false` is in `plugin/src/Seeder/ClaimRunner.php`, not in
  `plugin/wp-cli/ClaimCommand.php` — the command only renders what the runner already decided.
  The same is now true of the malformed-manifest and language-mismatch branches beside it.
  Accurate in effect — nothing was applied because there was nothing to apply — but misleading
  in wording for an operator who ran the command for real.
- [ ] **The wp-admin missing-manifest message is untranslated.** `ClaimRunner::run()`'s "No seed
  manifest found. Create …" string (`plugin/src/Seeder/ClaimRunner.php`) has no `__()` wrapper,
  following `Runner::run()`'s existing precedent for the identical message, so it renders in
  English on a non-English admin install. Deliberate and narrow, not worth fixing on its own.
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
- [x] **`npx wp-env start` failed to boot on macOS with Docker's containerd image store.** The
  start failed with an OCI runtime error mounting the mu-plugin fixture to
  `wp-content/mu-plugins/activate-pediment.php` (`mountpoint … is outside of rootfs`): Docker
  Desktop's containerd image store cannot bind-mount a single host file over virtiofs. The failed
  start also left MariaDB half-initialized, so retries then failed with "Error establishing a
  database connection". **Fixed** by mounting the mu-plugin as a directory
  (`plugin/tests/fixtures/mu-plugins/`) instead of a single file — see the commit that moved
  `mu-activate-theme.php` to `mu-plugins/activate-pediment.php`. macOS Docker Desktop only; CI runs
  on Linux runners and was unaffected.
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

- [ ] Pattern coverage: only 3 patterns exist (`contact-page`, `hero-cta-faq`,
  `prose-article`). Consider a small library that showcases every block.
- [ ] Accessibility pass: keyboard/focus and contrast audit across blocks against the
  default token palette.
