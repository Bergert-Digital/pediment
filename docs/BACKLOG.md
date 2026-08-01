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
- [ ] **Update the client-theme template repo.** Replace its parent-theme
  inheritance with the standalone Pediment plugin flow (migration steps 3–5).

## 🟢 Medium

- [ ] **Seeding follow-ups from the step-3 final review.** Neither blocks a merge, both
  were parked deliberately: (a) a site that already carries a trashed nav *and* a
  re-created one under the same seed key now aborts the whole run on duplicate identity
  until an operator deletes the trashed row — correct behaviour, but an upgrade surprise
  worth a release note; (b) `--dry-run` prints unresolved-media problems under a
  `VERIFICATION FAILED` heading even though phase 5 never ran — the finding is real and
  pre-write, the heading is not.
- [ ] **A coordinated language removal silently unlinks that language's translation
  groups.** Found by review during migration step 4, Task 10. `Applier::apply()` excludes
  `ORPHAN` plan items from the ID map it hands to `linkTranslationGroups()`, even when
  their `postId` is still valid; `DesiredState` crosses every seed key with every
  configured language, so a single language cannot go missing for one isolated page — but
  dropping a language from both the manifest and Polylang's own config in the same run
  passes the Runner's language gate (the two sets still match), and every post in that
  language becomes `ORPHAN` everywhere at once. Each key's map then omits that language,
  and `pll_save_post_translations()` replaces group membership, unlinking those posts
  site-wide — even though the plan reports them as `orphan`, i.e. "left in place." A
  `--dry-run` does not surface this: it shows the same `orphan` line it always shows for a
  manifest-dropped key, which reads as "nothing happened," not "this post is about to lose
  its translation-group membership." Fix, if wanted, is either to preserve group
  membership for no-longer-configured languages, or to report the unlinking as an explicit
  plan item. Narrow and deliberate-removal-only, not a routine-editing risk — not fixed
  here on purpose.
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

## 🔵 Ideas / later

- [ ] Pattern coverage: only 3 patterns exist (`contact-page`, `hero-cta-faq`,
  `prose-article`). Consider a small library that showcases every block.
- [ ] Accessibility pass: keyboard/focus and contrast audit across blocks against the
  default token palette.
