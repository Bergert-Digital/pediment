# Pediment development system — architecture review and redesign

**Date:** 2026-07-29
**Status:** Design approved, pending implementation plan
**Scope:** All four Pediment repos — parent theme, child template, AI plugin, and the first client fork

---

## 1. Why this review

Pediment works. Building a site on it takes longer than it should. This review looked at all
four repos and their combined git history (1,080 mainline commits, 2026-05-11 to 2026-07-29)
to find the recurring failure modes empirically rather than from memory.

Goals the system must serve:

- Develop WordPress sites quickly using local AI
- Release a client theme based on the child template
- Seed most content once
- Let clients edit pages in Gutenberg with Pediment AI
- Make changes locally and publish them as a new theme version
- Eventually sell Pediment as a paid theme and development system

Target audience, decided during this review: **internal use first, productized for outside
developers within roughly six months.** The boundaries are therefore designed now so that
productizing later is not a second migration.

---

## 2. Diagnosis

### 2.1 Pediment is a copy-paste pattern, not an installable dependency

Four repos each carry their own copy of the release workflow, the updater, wp-env setup, CI,
phpcs config, e2e helpers, seeding, and the agent skills. Consequences visible in history:

- The same defect — the released artifact's version header not moving, so production never
  sees the update — was found and fixed independently in all four repos: `9c9af20`,
  `22f0024`, `432faf6`, workation #22.
- `chore(wp-env): bump parent/plugin refs to latest tags` was opened five separate times in
  the child template (#5, #18, #34, #39, #58) and reverted once outright (`4134dea`). The pin
  sits three minors behind, knowingly.
- Forking a client site means hand-editing roughly 300 occurrences of `pediment-child` across
  about 30 files, driven by a prose checklist. It does not get done reliably:
  `workation-castle-website/style.css` still reads `Theme Name: Pediment Child Theme`.
- Template-to-fork updates are a negotiated merge. The `update` skill diffs and asks at
  runtime, and once destroyed curated docs (`383cc0d`). Fork-to-template promotion has no path
  at all, which is why roughly 1,000 lines of reusable multilingual work are stranded in
  Workation.

### 2.2 The parent theme's boundaries are in the wrong place

2,145 of 2,873 lines in `inc/` are a forms and webhook engine that loads on every request of
every client site. There are two parallel form stacks (`contact-form.*` and `forms.*`), both
registered, both shipping, neither marked deprecated.

Meanwhile 8 of the 11 extension hooks are forms-specific. The things a child actually needs to
change — header, footer, nav, seeding, i18n — have almost none, so children fork parent files.
`parts/footer.html` hardcodes placeholder client copy (`hello@example.com`,
`+44 20 7946 0000`), forcing every site to fork it. The header is defined twice, in
`parts/header.html` and `inc/bootstrap.php:57-79`, and the two have already drifted.

### 2.3 Seeding is imperative replay against unknown database state

Rewrite-rule flushing was rediscovered five times (`972d8ba`, `c532ffe`, `231db03`, `41bde91`,
`fe67436`). The parent's seeder was removed entirely (`8460d33`) and the README still documents
commands that no longer exist. The one genuinely idempotent seeder in the parent —
`tests/e2e/fixtures.php`, which sources markup from registered patterns — is excluded from the
release zip by `.distignore`.

### 2.4 Identity, not Polylang, caused the multilingual crisis

Workation's Polylang cluster was 23 commits in four days, about 1,480 lines, with at least
three live outages where the header vanished while the seeder reported success. The initial
reading — that Polylang's content model conflicts with a git-authored, seeded one — does not
survive the evidence:

| Commit | What broke | Root cause |
|---|---|---|
| `8593c73` | Seeder's `wp_insert_post()` never called `pll_set_post_language()` | Seeder skipped the API |
| `7d7ca30` | Menu identified by slug; a stray post held `primary`, replacements became `primary-2` | Slug identity |
| `45c9ca5` | Create-vs-update decided by translation-group membership, so reruns duplicated | Slug identity |
| `dd23712` | `suppress_filters` does not escape Polylang's `parse_query` scoping; needs `lang => ''` | Learn-once idiom |
| `3847af3` | `redirect_lang` default; `PLL()->options` must be written via the API | Learn-once config |

Four of six are the seeder's own identity model, which would have produced orphans on a
monolingual site too. Two are idioms paid for once. **Polylang is not the problem. Looking
content up by slug instead of a stable key is the problem.** Language only made it fail loudly
and in public.

### 2.5 The real conflict: re-seeding versus client editing

Content is authored in git as pattern files and seeded into the database. Clients also edit
those same pages in Gutenberg. Today the seeder overwrites pages by slug, so the next seed run
destroys client work. Workation hit this from the other direction: the `port-page` skill grew
an entire "Step 9: Persist to version control" section because pages built live in wp-env were
being destroyed by the next re-seed.

Two writers, one database, no arbitration. This is the architectural problem underneath the
multilingual symptoms.

---

## 3. Decisions

| # | Decision | Rejected alternative | Why |
|---|---|---|---|
| 1 | Framework runtime moves into a plugin, merged with the AI plugin, in a monorepo, on a single version line. *(Topology amended by decision 6: the presentation layer joins the plugin too; there is no parent theme.)* | Composer package; automate the current shape | A package ships inside the theme zip and cannot be one-click updated. Automation leaves drift structural. |
| 2 | Multilingual is first-class, via a `LanguageProvider` interface with `Null` and `Polylang` implementations | Build our own translation layer | The bug history does not justify owning routing, hreflang, sitemaps, search, feeds, REST scoping and editor UX forever. The seam remains if product reasons later demand it. |
| 3 | Git owns structure, database owns content, arbitrated by a content hash | Seed-once-then-stop; git-always-wins with round-trip export | Preserves shipping content improvements to live sites without destroying client edits. Round-trip export becomes an explicit per-page command rather than a default. |
| 4 | `/start` is a Claude Code skill that sequences the existing skills | A command layer inside the WP plugin | The plugin has no command system; building one is greenfield work for no benefit. |
| 5 | Single branch: work lands on `main`, release-please's release PR is the shipping gate. Applies to the monorepo, the child template, and client forks | Keep `development` -> `main` | The two-branch flow was the #4 ranked pain point: it doubled every PR (release PRs were 22-35% of all PRs in every repo), produced five duplicate-titled PR pairs, and forced a manual merge to resolve release-please divergence. A monorepo doubles the version-file churn on `main`, making the split structurally worse. The release PR already provides the staging gate `development` was meant to be. Accepted cost: no parking lane for "done but not ready to ship" — merging the release PR ships everything on `main`. |
| 6 | **The parent theme is retired.** Two artifacts: the `pediment` plugin (engine + AI + blocks + templates + default tokens + patterns) and one standalone theme per client. Validated by spike on 2026-07-29 (see 4.1) | Parent + child themes (the original decision 1 topology) | Deletes three complications this spec itself had to carry: the theme-plugin runtime contract (blocks and engine now ship in one artifact), the load-bearing `pediment.zip` filename / `Template: pediment` folder-name coupling, and the header-defined-twice problem. One less artifact for clients to install and update. Blocks-in-plugin is the idiomatic WordPress position — content survives a theme switch. Costs: plugin-registered templates are a WP 6.7+ API and less battle-tested than parent/child; template *parts* cannot come from plugins; style variations (`styles/*.json`) remain theme-territory. |

---

## 4. Design

### 4.1 Artifact topology

Two repos, two artifacts, one version line. **There is no parent theme** (decision 6): the
plugin carries everything shared, and each client site runs one standalone theme scaffolded
from the template.

```
pediment/                    monorepo -> pediment-plugin.zip (installs as plugins/pediment)
  plugin/
    src/blocks/              all 25 blocks
    templates/               registered via register_block_template()
    patterns/                incl. the footer (parts cannot come from plugins)
    tokens/                  default theme.json data, injected via filter
    inc/                     engine: seeding, forms, language, media, consent,
                             redirects, brand/site config, updater, AI
  .github/workflows/         one copy
  tools/                     one copy
  tests/e2e/helpers/         one copy

pediment-client-template/    scaffolded per client -> <client>.zip
  style.css                  no Template: header — standalone
  theme.json                 client tokens, merged OVER plugin defaults
  templates/*.html           optional file overrides of plugin templates
  patterns/, assets/         client content
  src/blocks/                client-specific blocks
```

**Asset naming (decided 2026-07-30, during step-2 planning).** The plugin ships as
`pediment-plugin.zip`, NOT `pediment.zip`, even though retiring the theme frees that name.
Reason: every 2.4.x client site's ThemeUpdater watches this repo's releases for an asset
matching `/pediment\.zip$/`. A v3.0.0 `pediment.zip` containing a plugin would be offered to
those sites as a *theme* update and install a broken theme on click. With
`pediment-plugin.zip`, old theme updaters find no matching asset and stay quietly pinned at
2.4.x until each site is migrated (step 6) — the intended behavior. Old plugin installs
(watching `pediment-ai.zip`) likewise stop updating until their manual slug swap.

**Forms reconciliation (decided 2026-07-30).** The `forms.*` stack survives (destinations,
encrypted secrets, SSRF allow-list, presets, retention — the newer, better-tested engine).
The parallel `contact-form.*` stack — `inc/contact-form.php`, the `pediment/contact-form`
block, and its tests — is **removed outright in v3.0.0**, no compatibility shim. Existing
content using the block renders nothing after the upgrade; affected pages are migrated to
the `pediment/form` block as part of each site's step-6 migration.

**Spike-validated (2026-07-29, WP 7.0.2; all mechanisms are WP 6.7+ APIs).** Throwaway wp-env,
minimal plugin + standalone client theme; artifacts in `.context/spike-plugin-theme/`:

1. `register_block_template()` templates render on the front end, appear in the Site Editor's
   template list (`source=plugin`, namespaced under the active theme), and a Site Editor edit
   (via the same REST route the editor uses) persists and wins over the registration.
2. A client theme template *file* (`templates/page.html`) beats the plugin registration — the
   child-override path works unchanged.
3. A footer shipped as a plugin-registered *pattern* renders inside plugin templates (template
   parts cannot come from plugins; the header is DB-seeded by the seeder anyway).
4. Token injection works with parent/child override semantics — **but only via
   `wp_theme_json_data_theme`**, constructing the plugin's defaults as a base and merging the
   client's data over it. Injecting into `wp_theme_json_data_default` is a trap: core's
   preset prevent-override rule (`defaultPalette`) silently *strips* theme presets whose slug
   collides with a default-origin preset, inverting the override direction.
5. Because the plugin controls that merge in PHP, it can merge presets **per slug** — the
   client declares only the tokens it changes and everything else survives. This is strictly
   better than parent/child `theme.json` semantics, where declaring `color.palette` forces the
   child to re-paste the entire parent array (the documented wart in the child README).

**Runtime contract.** Blocks and engine now ship in one artifact, so the cross-artifact fatal
risk this section previously had to design around is gone. What remains is simpler: the client
theme must degrade gracefully when the plugin is deactivated (unregistered blocks render as
raw markup; the theme's own `templates/index.html` still resolves). The plugin is a hard
requirement of a Pediment site and says so via an activation notice in the theme.

**Single version line.** The plugin is the one shared artifact and carries the version. Client
themes version independently per site (as today). An AI-only fix still ships a plugin update to
every site — accepted; release notes say what changed.

**Why a monorepo still.** One copy of the release workflow, CI, wp-env config, and e2e helpers
— the version-header bug that was fixed four times gets exactly one place to live. The merge of
`pediment` + `pediment-ai` proceeds as planned; the theme repo's presentation layer moves into
`plugin/` instead of a sibling `theme/`.

**What this deletes from the previous design:** the guarded-service-accessor machinery (blocks
and engine are one artifact), the load-bearing `pediment.zip` filename rules and the
`Template: pediment` folder-name coupling (no parent to find), the wp-env slug-derivation
hacks, and the header-defined-twice problem.

**Costs, accepted:** plugin-registered templates are two majors old versus fifteen years of
parent/child — Site Editor edge cases are likelier here than anywhere else in this design;
style variations (`styles/*.json`) can't ship from the plugin (unused today); the spike ran on
WP 7.0.2, so step 2 of the migration re-verifies the five spike claims on the 6.9 production
floor before anything moves.

### 4.2 Seeding engine

Declarative desired-state diff, not imperative replay. A manifest declares structure; pattern
files supply content.

```php
'pages' => [
  'home'      => [ 'pattern' => 'home', 'front_page' => true ],
  'guide'     => [ 'pattern' => 'guide' ],
  'guide/faq' => [ 'pattern' => 'guide-faq', 'parent' => 'guide' ],
],
```

Five phases, always in this order:

1. Resolve desired state — manifest crossed with configured languages
2. Resolve actual state — query by `_pediment_seed_key`, unscoped by language
3. Diff into a plan
4. Apply, with language assigned in the same write as creation, never after
5. Verify post-conditions per language, and fail loudly

Required properties, each tied to a specific past failure:

- **Identity is `_pediment_seed_key`, never slug.** Retires `220e0b7`, `7d7ca30`, `45c9ca5`.
- **`_pediment_seed_hash` arbitrates content.** On re-seed, hash the page's current
  `post_content` and compare with the stored hash. Match means nobody edited it, so write the
  new pattern content and update the hash. Mismatch means the client edited it, so never touch
  content and reconcile structure only.
- **The stored hash is computed from the persisted row, never from the input.** WordPress
  mutates markup on write — KSES, void-tag normalization, `wp_update_post` un-slashing (all
  documented in `WORDPRESS_TRAPS.md`) — and the seeder rewrites media URLs. Hash the intended
  content instead of reading it back after the write, and every page mismatches on the first
  re-seed, silently disabling content updates system-wide while reporting nothing wrong.
- **`post_title` is content, arbitrated by the same hash** (hash covers title + content).
  **Slug is structure**: a client slug change is reverted on re-seed by design — renaming a
  page is a repo-level operation — and the dry-run plan surfaces the revert before it happens.
- **Structure the seeder always owns:** page existence, slug, parent/child nesting, front-page
  and posts-page settings, nav membership, CPT registration and rewrite rules,
  translation-group links, media presence.
- **`--dry-run` prints the plan.** The most expensive failure in the history was the seeder
  reporting `skipped (exists, published)` while the live header rendered nothing. A readable
  plan makes that class of failure visible.
- **Rewrite rules flush once, at the end, after all CPTs are registered.**
- **One code path for WP-CLI and wp-admin**, with PHP time and memory limits lifted in the
  admin runner (the lesson from `bfd550f`, where identical code passed under CLI and died with
  a generic critical error in wp-admin).

Consequences worth stating: the seeder becomes safe to run against production, which is what
actually enables "make changes locally and publish as a new theme version". And `port-page`'s
Step 9 problem inverts — instead of live-built pages being silently destroyed, an explicit
`adopt` command exports a live page's block markup back to `patterns/<slug>.php` and resets the
hash.

### 4.3 Multilingual

`LanguageProvider` with two implementations, `Null` and `Polylang`, so the seeder has one code
path whether or not a site is multilingual. That is the real value: monolingual sites exercise
the same logic, which makes the multilingual path testable.

```php
languages()            default_language()
set_language($id, $l)  link_translations(array $lang => $id)
translation_of($id,$l) unscoped_query($args)
```

`unscoped_query()` encapsulates the `lang => ''` idiom exactly once, in one file, with the
comment explaining why `suppress_filters` does not work.

Supporting decisions:

- **Languages are configured before any content is written.** `npm run env:setup` currently
  runs `setupPolylang()` after the seed, which guarantees language-less content. That inverts.
- **Per-language patterns.** `patterns/<slug>.php` is the default language;
  `patterns/<slug>.<lang>.php` overrides. The seeder reports which are missing, which is the
  hook for an AI `translate` command that writes them.
- **`wpml-config.xml` is generated** from `block.json` attribute types. The hand-maintained one
  is currently missing four shipping blocks (`form`, `form-field`, `social-links`,
  `blog-index`). Polylang reads this format too, so generating it serves both.
- **Nav is resolved by `(seed_key, language)`**, the same mechanism as everything else rather
  than a special case.

The `Polylang` adapter absorbs the reusable code stranded in Workation: `inc/Polylang.php`, the
generic half of `PrimaryNav.php`, and `tools/polylang-setup.php` (which exists only because
Polylang's free build ships no WP-CLI, and every multilingual dev env needs bootstrapping).

A WPML adapter is explicitly not built until someone needs it. The interface buys the option;
the adapter is roughly 150 lines when required.

### 4.4 The `/start` flow

A Claude Code skill and the single front door. It sequences the existing skills (`initialize`,
`discover`, `port-site`, `build-header`, `port-page`, `create-seed-content`) rather than
replacing them.

Questionnaire principle: **ask only what cannot be derived.** One branching question first —
existing site to port, or starting fresh — then:

| Port (~5 prompts, mostly confirmations) | Greenfield (~6 prompts) |
|---|---|
| URL | Business name |
| Confirm scraped palette and fonts (shown, not asked) | What they do, for whom (drives brand voice) |
| Which pages to port (pre-checked from sitemap) | Languages |
| Confirm languages (pre-filled from `hreflang`) | Sitemap (offer Home/About/Services/Contact) |
| Client name and repo slug | Logo and palette (offer 3 generated options) |
| | Tone |

Both branches converge on the same automated tail: scaffold the repo, write `docs/brief.md`,
populate the plugin's brand and site config, start wp-env, seed, report the local URL and next
steps.

`docs/brief.md` becomes the durable artifact, and the answers populate the plugin's site config
which `PromptBuilder` reads. This closes a loop the review found broken: brand voice is
documented as existing in four places (`pediment-ai/docs/privacy.md`, `docs/prompts.md`, both
`VISION.md` files) and implemented in none.

Prerequisite checks run first — Docker running, node version, `gh` authenticated. Cheap, and it
is the difference between an outside developer succeeding and filing a support request.

Target: `/start` to a running local site with real branding and seeded pages in one session,
with no manual steps.

### 4.5 Scaffolding

Most of the rename disappears on its own. With everything shared living in the plugin
(decision 6), the client theme has almost no PHP: no `inc/seed.php` (808 lines in Workation),
no `ThemeUpdater`, no `UpdateToken`, no `settings-updates.php` (91 occurrences by itself), and
no `Template:` header to keep consistent. What remains is `style.css` headers, `package.json`,
`composer.json`, `release-please-config.json`, and the text domain in client-shipped blocks.

Scaffolding derives the rest from two inputs, client name and repo slug: writes headers, resets
`CHANGELOG.md` and the release manifest, sets the text domain, deletes the example
`promo-banner` block, generates a placeholder screenshot.

Two supporting fixes:

- **CI stops hardcoding the slug.** `ci.yml` has 21 hardcoded `pediment-child-theme`
  occurrences, so CI tests a theme whose slug differs from production's. Derive it from the
  `style.css` Text Domain, which the release workflow already does (`e4705f0`).
- **A `pediment doctor` command** checking slug consistency across the five files, the plugin
  pin, folder-name whitespace (which breaks Site Editor URLs), and that languages are
  configured before seeding.

---

## 5. Migration sequence

Ordered by dependency. Each step is shippable.

1. **Monorepo and single version line.** Merge `pediment-ai` in as `plugin/`. One release
   workflow, one `.wp-env.json`, one phpcs config. This step also retires `development`:
   the monorepo starts on `main` only (decision 5), which means reconciling the current
   `development`-ahead-of-`main` state in both source repos as part of the merge. Mechanical,
   and unblocks everything.
2. **Move everything shared into the plugin — runtime and presentation.** First, re-verify the
   five spike claims of 4.1 on the WP 6.9 production floor (the spike ran on 7.0.2). Then:
   blocks, templates, patterns, and default tokens move from the theme into `plugin/`; forms
   reconcile from two stacks to one; the parent theme is retired. Breaking for every existing
   site, so **Pediment 3.0.0**. Client themes drop their `Template:` header and gain the
   activation-notice dependency on the plugin.
3. **Seeding engine.** Identity keys, hash arbitration, dry-run. Port Workation's
   `inc/seed.php` as the reference implementation; it is the most battle-tested one available.
4. **`LanguageProvider` and the Polylang adapter.**
5. **Scaffolder and `/start`.** Depends on 1-4 existing, since its job is to drive them.
6. **Migrate Workation, as the proof.** The hardest case: 19 pages, 5 languages, 74 photos,
   2 CPTs. If it moves cleanly and re-seeds with zero content changes, the system works.

Steps 1 and 6 are small, 2 and 5 medium. **Steps 3 and 4 are the real work and where the payoff
lives.**

Step 6 is safe by construction: on first run against Workation's existing database, no page has
a `_pediment_seed_hash`. Treat missing hash as edited, so the first run touches no content,
adopts everything into git, and sets hashes.

Housekeeping for step 1: `docs/images/` holds 11 tracked Unsplash JPEGs, roughly 46 MB, present
in every clone and every Conductor worktree.

---

## 6. Out of scope

- A WPML adapter (build when needed)
- A custom translation layer replacing Polylang (revisit only for product reasons)
- WooCommerce or third-party plugin compatibility in the language layer
- Per-language domains or subdomains; prefix routing only
- Automated deployment to client hosting. Production remains admin-only zip upload, which is a
  constraint of the current hosting rather than a design choice.

---

## 7. Open questions

- Does the child template keep its own CI, or consume a reusable workflow from the monorepo?
  Leaning reusable, decided during step 1. The same question applies to the shared e2e helpers:
  "one copy in the monorepo" only solves the problem for the monorepo. The child template is a
  separate, forked-per-client repo and needs the same helpers — the history shows the identical
  three e2e fixes (welcome-modal dismissal, persisted-publish waits, permalink resolution)
  copy-pasted across 3-4 repos. Without a consumption mechanism (an npm package published from
  the monorepo, plus the reusable workflow), the copy-paste pattern survives in exactly the
  repo that multiplies per client.
- Where does `pediment doctor` live — the plugin as WP-CLI, or the template as a node script?
  It needs to run before WordPress exists, which argues for node.
- Should AI features be license-gated inside the merged plugin from the start, or added when
  productizing? Deferring, since gating is additive.
- **Plugin slug continuity.** Existing sites run `pediment-ai`. Renaming the merged plugin to
  `pediment` changes the installed directory, and Plugin Update Checker keys updates off the
  slug, so those installs would silently stop receiving updates rather than fail visibly. The
  rename is decided; what remains is the transition. Two options: ship one final `pediment-ai`
  release whose only job is to install and activate `pediment` and deactivate itself, or accept
  a manual one-time swap on the small number of existing sites. Given how few sites are
  affected today, and how often this project has been bitten by update-detection failures
  (`9c9af20`, `22f0024`, `432faf6`), the manual swap is probably safer than automating a
  self-replacing plugin. Decide during step 1. The same one-time visit also cleans up the
  parent theme: once a site's client theme updates to its 3.0 build (standalone, no
  `Template:` header), the installed `pediment` parent theme is inert and gets deleted.
