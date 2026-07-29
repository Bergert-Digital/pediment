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
| 1 | Framework runtime moves into a plugin, merged with the AI plugin, in a monorepo with the parent theme, on a single version line | Composer package; automate the current shape | A package ships inside the theme zip and cannot be one-click updated. Automation leaves drift structural. |
| 2 | Multilingual is first-class, via a `LanguageProvider` interface with `Null` and `Polylang` implementations | Build our own translation layer | The bug history does not justify owning routing, hreflang, sitemaps, search, feeds, REST scoping and editor UX forever. The seam remains if product reasons later demand it. |
| 3 | Git owns structure, database owns content, arbitrated by a content hash | Seed-once-then-stop; git-always-wins with round-trip export | Preserves shipping content improvements to live sites without destroying client edits. Round-trip export becomes an explicit per-page command rather than a default. |
| 4 | `/start` is a Claude Code skill that sequences the existing skills | A command layer inside the WP plugin | The plugin has no command system; building one is greenfield work for no benefit. |

---

## 4. Design

### 4.1 Artifact topology

Two repos, three artifacts, one version line.

```
pediment/                    monorepo
  theme/                     -> pediment.zip         (installs as themes/pediment)
  plugin/                    -> pediment-plugin.zip  (installs as plugins/pediment)
  .github/workflows/         one copy, releases both
  tools/                     one copy
  tests/e2e/helpers/         one copy
  phpcs.xml.dist             one copy

pediment-child-template/     forked per client
```

**Naming.** Both artifacts are called Pediment and both install under the slug `pediment`.
WordPress keeps theme and plugin slugs in separate namespaces, so `themes/pediment` and
`plugins/pediment` coexist without conflict.

The release *asset filenames* must still differ, because both are published to the same release
tag. The theme keeps `pediment.zip`: wp-env derives the mounted theme slug from the URL
basename, and children declare `Template: pediment`, so that filename is load-bearing
(`build-release-zip.yml:52-58`). The plugin ships as `pediment-plugin.zip`, which is plumbing
only — the installed slug comes from the directory inside the zip, and PUC's
`enableReleaseAssets()` matches on the asset name independently of the slug.

One consequence to accept: in the child template's wp-env, a downloaded
`pediment-plugin.zip` mounts as `plugins/pediment-plugin` rather than `plugins/pediment`. That
is harmless (nothing hardcodes the plugin path; `plugins_url()` resolves dynamically), but it
is a dev/production difference worth knowing about. It does not arise in the monorepo's own
wp-env, which mounts `theme/` and `plugin/` as local paths.

The theme/plugin cut follows one rule: **blocks and tokens stay in the theme; anything that
touches the database, the network, or the filesystem moves to the plugin.**

| Stays in theme | Moves to plugin |
|---|---|
| `theme.json`, `templates/`, `parts/`, `patterns/` | seeding engine (from `inc/bootstrap.php`) |
| all 25 blocks in `src/blocks/` | forms engine: `forms-*.php` + `contact-form.php`, 2,145 LOC |
| `block-styles`, `hero-variants`, `layout-variations` | `ThemeUpdater`, updating both artifacts |
| `icons`, `nav-active`, `mega-menu` | language provider, media resolver, brand/site config |
| | consent, redirects, AI |

The rule resolves forms cleanly. The form *block* is design and stays. The webhook engine —
destinations, encrypted secrets, SSRF allow-list, retention cron, the 637-line admin UI — is
machinery and goes. The two parallel form stacks reconcile to one during the move.

**Single version line.** Tag once, publish two zips, both stamped `X.Y.Z`. The child pins one
number. This deletes the compatibility matrix that produced seven bump commits, five duplicate
PRs, and one revert.

**Why a monorepo.** CI already behaves like one: `pediment-ai`'s CI checks out and builds
`Bergert-Digital/pediment@development` before it can run, and the child's CI checks out both.
One copy of the release workflow means one place the version-header bug can live.

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

Most of the rename disappears on its own. Once framework code lives in the plugin, the child
theme has almost no PHP: no `inc/seed.php` (808 lines in Workation), no `ThemeUpdater`, no
`UpdateToken`, no `settings-updates.php` (91 occurrences by itself). What remains is
`style.css` headers, `package.json`, `composer.json`, `release-please-config.json`, and the
text domain in client-shipped blocks.

Scaffolding derives the rest from two inputs, client name and repo slug: writes headers, resets
`CHANGELOG.md` and the release manifest, sets the text domain, deletes the example
`promo-banner` block, generates a placeholder screenshot.

Two supporting fixes:

- **CI stops hardcoding the slug.** `ci.yml` has 21 hardcoded `pediment-child-theme`
  occurrences, so CI tests a theme whose slug differs from production's. Derive it from the
  `style.css` Text Domain, which the release workflow already does (`e4705f0`).
- **A `pediment doctor` command** checking slug consistency across the five files, the parent
  pin, folder-name whitespace (which breaks Site Editor URLs), and that languages are
  configured before seeding.

---

## 5. Migration sequence

Ordered by dependency. Each step is shippable.

1. **Monorepo and single version line.** Merge `pediment-ai` in as `plugin/`. One release
   workflow, one `.wp-env.json`, one phpcs config. Mechanical, and unblocks everything.
2. **Move framework runtime into the plugin.** Theme shrinks to presentation; forms reconcile
   from two stacks to one. Breaking for children, so **Pediment 3.0.0**.
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
  Leaning reusable, decided during step 1.
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
  self-replacing plugin. Decide during step 1.
