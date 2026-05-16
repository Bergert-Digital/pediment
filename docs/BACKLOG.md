# Backlog

Prioritized work. Groups: 🔴 critical · 🟡 high · 🟢 medium · 🔵 ideas/later.
Checked items are removed during the next /dev-cycle tidy pass.

> Scaffolded 2026-05-15 by inferring from the codebase + recent plans. The distribution
> direction (child-theme repo, retiring wp-client-template, zip pipelines, section rhythm)
> appears **shipped** — child repo is live on GitHub with CI/release, wp-starter-ai has its
> release pipeline, wp-client-template is gone locally. These items below are verification,
> drift-hunting, and hygiene — not the big build. Re-validate each per Step 5 before picking up.

## 🔴 Critical

_(none currently known — verify by running a user-journey audit)_

## 🟡 High

- [ ] **Verify the release/zip pipeline end-to-end.** Plan task D5 (throwaway
  `0.0.0-rc.test` release on the child repo) may never have been run. Confirm a
  `workflow_dispatch` release produces an installable zip that installs in a clean WP and
  lists with the right parent. Remote action — pause for user go-ahead before triggering.
- [ ] **Confirm the child repo has the `STARTER_THEME_PAT` secret.** The child's
  `ci.yml` phpunit/e2e jobs do a cross-repo checkout of the parent using
  `secrets.STARTER_THEME_PAT`. If it's unset those jobs fail. Check
  `gh secret list --repo Bergert-Digital/wp-starter-child-theme` and flag to the user if missing.
- [ ] **Hunt repo-name drift.** Parent remote is `Bergert-Digital/WP-Starter` but
  `style.css` Theme URI and some docs say `github.com/bergert/wp-starter-theme`. Distribution
  README/banner links may point at a non-existent `Bergert-Digital/wp-starter-theme`. Audit
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

## 🔵 Ideas / later

- [ ] Pattern coverage: only 3 patterns exist (`contact-page`, `hero-cta-faq`,
  `prose-article`). Consider a small library that showcases every block.
- [ ] Brand Settings: a "preview" affordance so editors see logo/colors applied without
  leaving the settings page.
- [ ] Accessibility pass: keyboard/focus and contrast audit across blocks against the
  default token palette.
