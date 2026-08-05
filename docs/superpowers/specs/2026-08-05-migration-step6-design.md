# Migration step 6 — moving a live site onto the plugin

**Date:** 2026-08-05
**Status:** Design approved, pending implementation plan
**Scope:** Migration step 6 of `2026-07-29-pediment-dev-flow-design.md` — the Workation cutover,
and the engine and tooling gaps that cutover exposes.

---

## 1. Why this spec exists

The parent spec treats step 6 as a proof rather than a build: "safe by construction … on first run
against Workation's existing database, no page has a `_pediment_seed_hash`. Treat missing hash as
edited, so the first run touches no content, adopts everything into git, and sets hashes."

Two of that sentence's premises do not hold against the engine as shipped, and a third
assumption about the client template does not hold either. Each was checked against the working
tree and against `Bergert-Digital/workation-castle-website@origin/main` (278 commits, v0.12.0 —
note the local checkout at `~/Entwicklung/workation-castle-website` is a stale one-commit stub).

### 1.1 A live site is invisible to the engine, so the first run duplicates it

`StateReader::query()` (`plugin/src/Seeder/StateReader.php:63-66`) resolves actual state with
`meta_key => Meta::KEY, meta_compare => EXISTS`. That is deliberate and correct — slug lookups are
what produced `primary-2` and duplicate pages (`7d7ca30`, `45c9ca5`). The consequence for a
pre-existing site is that every row is invisible: Workation's pages were written by its own
`inc/seed.php`, which identified content by slug and never wrote `_pediment_seed_key`.

So the Differ's rule 1 fires for every entry — "no row exists → create it" — and a first run
against Workation would create roughly 19 pages × 5 languages of duplicates beside the live ones,
with WordPress appending `-2` to each colliding slug. The hash-arbitration safety the spec relies
on only protects rows the engine can *see*, and it can see none of them.

`wp pediment adopt` cannot rescue this: it resolves the same way, and fails with "No seeded post
carries the key". Nothing under `plugin/src/`, `plugin/wp-cli/` or `plugin/inc/` backfills identity.

### 1.2 The client template cannot express a site like Workation

Workation ships 20 bespoke blocks under the `pediment-child/*` namespace and about 2,700 lines of
`inc/`: two public CPTs (`Photos.php`, `Activities.php`), a private check-in CPT with a REST
endpoint and Brevo mail, a consent manager, an estate map, an availability form, legacy-URL
redirects and Polylang nav glue.

`client-template/` has no `functions.php`, no `inc/`, and no build step. The `--with-blocks` flag
step 5 deferred (`2026-08-03-scaffolder-and-start-step5-design.md`, decision 5) was never built —
`scaffold.mjs:359-373` parses `--answers`, `--target`, `--template` and `--no-git`, nothing else.
Workation is precisely the first client that needs it.

### 1.3 A branded header has no home

`pediment_bootstrap_header_template_part()` (`plugin/inc/bootstrap.php:35-102`) inserts one generic
`header` part per theme and skips when a part already exists. Template *parts* cannot ship from a
plugin (parent spec §4.1), and the client template ships no `parts/` — so a client's branded header
lives only in the database, is not in git, and no seeder phase touches it. Workation's header
(logo, transparent-on-scroll, language switcher) currently exists as `parts/header.html` plus two
filters in `functions.php` that fight the parent theme's DB part off. Both sides of that fight
disappear with the parent theme; what replaces them is undecided.

### 1.4 What is *not* a problem

- **`contact-form`.** v3.0.0 removed the block with no shim, and the migration was expected to be
  painful. Workation never used it: `git grep wp:pediment/contact-form origin/main` matches only a
  documentation file. Its four forms are `pediment/form`, which survives.
- **Plugin blocks.** The patterns use `pediment/prose`, `faq`, `faq-item`, `feature`,
  `feature-grid`, `step`, `steps`, `form` and `form-field` — all still shipping.
- **Language configuration.** Polylang is already live with five languages; `wp pediment languages`
  and the Runner's language gate (`Runner::languageMismatch()`) work against exactly that shape.

---

## 2. Decisions

| # | Decision | Rejected alternative | Why |
|---|---|---|---|
| 1 | **A claim path in the plugin.** Existing rows are matched by (post type, slug, language, parent) against the manifest's desired state, and the *only* thing written is `_pediment_seed_key`. Runs from WP-CLI and from the wp-admin Seeding tab. | A one-shot local WP-CLI mapping script; or rebuilding content on a fresh install and cutting over | Production is admin-only Hetzner shared hosting, so any mechanism that needs a shell cannot run where it matters. Claiming is also not Workation-specific: every future site that predates the engine needs it exactly once. |
| 2 | **Build the deferred `--with-blocks` path.** `client-template/` gains an optional `functions.php`, `src/blocks/` and a wp-scripts build; the scaffolder emits them behind a flag; the reusable CI and release workflows build blocks when they are present. | Leave Workation's tooling hand-maintained in its own repo | The copy-paste drift §2.1 of the parent spec catalogued starts again the moment one client repo carries tooling the template does not. |
| 3 | **Rename `pediment-child/*` to `workation/*`,** rewriting both the pattern files and the stored `post_content`. | Keep the namespace | "child" names a topology that no longer exists, and the rename is a one-time cost paid while the site is already being touched. Mechanics in §3.4. |
| 4 | **Step 6 splits in two.** A capability plan (claim, header ownership, client blocks) proven locally, then a cutover plan for the production migration itself. | One plan | The capability half is testable without production and ships as an ordinary plugin minor; the cutover half is a one-way operation against a live site and deserves its own rehearsal, checklist and rollback. |
| 5 | **Claiming never writes hashes.** A claimed row has `_pediment_seed_key` and nothing else, so the Differ's rule 2 treats it as edited: structure is enforced, title and content are never touched. | Write `_pediment_seed_hash` during the claim so git wins immediately | The whole point of the first run is that it cannot destroy live content. Bringing a page under git's control stays an explicit, per-page `wp pediment adopt`. |
| 6 | **Media is not claimed, and Workation's 74 photos stay client-owned.** The manifest declares only structural media (logo, default hero); the photo library remains what its own manifest already calls it — "after seeding, photos are managed in wp-admin". | Commit 74 photos to the client repo and claim attachments by filename | It would add a whole matching subsystem for content the client, not git, owns. Attachments carrying no seed key are simply not the engine's business. |
| 7 | **Navs are claimed.** Workation's per-language Primary menus predate the engine, and an unclaimed `wp_navigation` is the historical `primary-2` failure repeating itself. | Let the seeder create fresh navs and rebind the header | Recreating navigation on a live site is the most visible possible failure, and it is the one this project has already shipped twice. |
| 8 | **The header part is seeded from a theme-provided pattern when one exists.** `pediment_bootstrap_header_template_part()` looks for a registered pattern named `<stylesheet>/header` and uses its content as the initial markup, falling back to today's generic markup. Still create-only, still never overwriting an existing part. | Let a client theme ship `parts/header.html` and skip the DB part | A file part is not editable in the Site Editor, which is the property this project deliberately chose (and the DB part would shadow it anyway). A pattern keeps the initial markup in git and the live markup editable. |

---

## 3. Design

### 3.1 What a claim is

One class, `Pediment\Seeder\Claimer`, with the same plan-then-apply shape as the rest of the engine.

```php
$claimer = new Claimer( $languageProvider );
$plan    = $claimer->plan( $manifest, $desiredEntries );  // Plan of PlanItem::CLAIM items
$result  = $claimer->apply( $plan );                      // writes Meta::KEY only
```

For each desired `(key, language)` that no row already carries, candidates are the posts that:

1. have the desired `post_type` and `post_name`;
2. are not in the trash (`publish`, `draft`, `pending`, `private`, `future` only);
3. carry **no** `_pediment_seed_key` — a row belonging to another key is never stolen;
4. match on parent, when the manifest declares one, against the parent's already-resolved ID;
5. match on language: the candidate's language equals the desired language, except that an
   untagged post (`LanguageProvider::hasLanguage()` false) is a candidate for the default language
   only.

Exactly one candidate is claimed. Zero is reported as `no match` (the next seed will create the
page, which is the correct outcome for a page that genuinely does not exist). Two or more is
reported as `ambiguous` and nothing is written — the operator decides.

Entries are walked in `Manifest::entriesInDependencyOrder()`, so a parent is claimed before the
children whose match depends on it. Navs are matched by `NavSeeder::slugFor()` and title within the
same language rules.

The report is its own renderer (`Reporter::claimText()`), not a seed report: a claim writes no
content, and folding it into the seed summary line would print "0 to write" over a run that
changed 95 rows' identity.

### 3.2 Why claiming is safe to leave in place

It is idempotent — a claimed row carries the key, so the next run sees it in actual state and plans
nothing. It writes one meta key and never post fields. It cannot cross-link two manifest keys,
because a row that already carries any key is excluded from every candidate set. The failure mode
it *can* produce is claiming the wrong post when two unkeyed posts share a slug in the same
language and parent, which is exactly the case it refuses to resolve.

### 3.3 The order of operations on a live site

```
1. install pediment-plugin.zip, activate            (wp-admin)
2. upload and activate the standalone client theme  (wp-admin)
3. Settings → Pediment → Seeding → Claim (preview)  read the plan
4. Claim (apply)                                    identity only
5. Seed (preview)                                   expect: 0 to write, N protected
6. Seed (apply)                                     structure only
```

Step 5 is the gate. A preview that reports anything other than protected pages and the structural
changes the operator expects means the claim was incomplete, and the answer is to fix the manifest
or the claim, never to apply anyway.

### 3.4 The namespace rename

Renaming `pediment-child/*` to `workation/*` moves in three places, and the order matters because a
claimed row is protected from content writes:

1. **Block sources and pattern files** — `src/blocks/*/block.json` and the 18 `patterns/*.php` are
   rewritten in git before the theme zip is built.
2. **Stored `post_content`** — a one-shot admin tool in the client theme rewrites
   `wp:pediment-child/` to `wp:workation/` (and the closing `/wp:` and `wp-block-` class
   occurrences) across posts of the seeded types, run once from wp-admin after step 4 above and
   deleted in the release that follows.
3. **Nothing in the plugin** — the rename is client-side; `pediment/*` blocks are untouched.

The alternative of letting the seeder push renamed content over the live rows is not available:
claimed rows are protected by decision 5, and unprotecting them would put every page's live copy at
risk to fix a namespace string.

### 3.5 Client blocks in the template

`--with-blocks` adds to a scaffolded repo: `functions.php` (registers `build/blocks`, enqueues
`style.css`), `src/blocks/` with one worked example, the wp-scripts `build`/`start` scripts and
devDependency, and `build/` in `.gitignore`. The reusable workflows (`client-theme.yml`,
`client-release.yml`) run `npm ci && npm run build` when `src/blocks/` is present, so a repo without
blocks keeps today's zero-build CI.

This is the seam the parent spec's §4.5 anticipated ("`src/blocks/` client-specific blocks") and
step 5 deliberately deferred until a client needed it.

### 3.6 What the cutover plan will have to answer

Deferred to the second plan, listed here so they are not lost:

- Where the rehearsal database comes from. Booting Workation's own repo in its pinned wp-env
  (parent 2.2.0 + plugin 0.5.0) and running its old seeder reproduces the pre-migration shape
  without touching production, and is the cheapest honest rehearsal. A production export, if the
  hosting panel offers one, is better but unverified.
- Whether live content is adopted into git page by page after the cutover, or left protected
  indefinitely. Adopting is what makes future content changes shippable; it is also 19 × 5 files.
- `ThemeUpdater`, `UpdateToken` and `settings-updates.php` disappear with the child theme (step 5
  decision 8), so the client theme updates by admin zip upload. Workation is the first site to feel
  that, and the backlog item asks whether it hurts.
- Retiring the two header filters in `functions.php` and the parent-theme deletion.

---

## 4. Out of scope

- Claiming attachments or terms (decision 6).
- Any generic "namespace rewrite" facility in the plugin — Workation's rename is a one-off in its
  own repo (§3.4).
- Migrating any site other than Workation. The capability half is generic; the cutover half is not.
- A WPML adapter, per-language media, and everything else the backlog already parks.
