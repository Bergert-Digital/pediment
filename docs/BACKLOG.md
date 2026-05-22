# Backlog

Prioritized work. Groups: 🔴 critical · 🟡 high · 🟢 medium · 🔵 ideas/later.
Checked items are removed during the next /dev-cycle tidy pass.

> Scaffolded 2026-05-15 by inferring from the codebase + recent plans. The distribution
> direction (child-theme repo, retiring wp-client-template, zip pipelines, section rhythm)
> appears **shipped** — child repo is live on GitHub with CI/release, pediment-ai has its
> release pipeline, wp-client-template is gone locally. These items below are verification,
> drift-hunting, and hygiene — not the big build. Re-validate each per Step 5 before picking up.

## 🔴 Critical

_(none currently known — verify by running a user-journey audit)_

## 🟡 High

- [ ] **Verify the release/zip pipeline end-to-end.** Plan task D5 (throwaway
  `0.0.0-rc.test` release on the child repo) may never have been run. Confirm a
  `workflow_dispatch` release produces an installable zip that installs in a clean WP and
  lists with the right parent. Remote action — pause for user go-ahead before triggering.
- [ ] **Confirm the child repo has the `PEDIMENT_THEME_PAT` secret.** The child's
  `ci.yml` phpunit/e2e jobs do a cross-repo checkout of the parent using
  `secrets.PEDIMENT_THEME_PAT`. If it's unset those jobs fail. Check
  `gh secret list --repo Bergert-Digital/pediment-child-theme` and flag to the user if missing.
- [ ] **Hunt repo-name drift.** Parent remote is `Bergert-Digital/WP-Starter` but
  `style.css` Theme URI and some docs say `github.com/bergert/pediment`. Distribution
  README/banner links may point at a non-existent `Bergert-Digital/pediment`. Audit
  every cross-repo link and the child `ci.yml` checkout path for consistency; fix dead links.
- [ ] **Doc-vs-code drift audit (`docs/blocks.md`, `docs/client-blocks.md`,
  `docs/brand-settings.md`).** Spot-check each documented contract against current code:
  required block files, namespace rules, brand filter signatures, sanitizer null-contract.
  Strike or correct anything the code no longer honors.

## 🟢 Medium

- [ ] **`npm run test:js` has no tests.** Jest is wired via wp-scripts but there are no JS
  unit tests. Either add coverage for the few bits of editor logic worth unit-testing, or
  drop the script so CI/devs aren't misled. Decide and act.
- [ ] **WordPress compatibility refresh.** `style.css` says "Tested up to: 6.5" and
  `.wp-env.json` pins WP 6.5. Bump to a current WP, run the full PHPUnit + Playwright suite,
  and update the header only if green.
- [ ] **Block empty-state sweep.** Walk all 11 blocks in the editor with empty attributes
  and as a visitor; confirm no broken markup, no `<a href="">`, no PHP notices. File any
  failures as 🔴.
- [ ] **Sub-project B — full-Phosphor icon delivery.** Replace the hand-built ~11-symbol
  sprite in [inc/icons.php](inc/icons.php) (printed per page via `wp_body_open`) with a
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
- [ ] Brand Settings: a "preview" affordance so editors see logo/colors applied without
  leaving the settings page.
- [ ] Accessibility pass: keyboard/focus and contrast audit across blocks against the
  default token palette.
