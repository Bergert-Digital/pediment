# Migration step 6b — Workation becomes a Pediment client theme

**Date:** 2026-08-06
**Status:** Design approved, pending implementation plan
**Scope:** The first half of the step 6 cutover split (`2026-08-05-migration-step6-design.md`
decision 4): converting `Bergert-Digital/workation-castle-website` from a Pediment *child theme*
into a standalone Pediment *client theme*, authoring its seed manifest, and closing the one engine
gap that conversion exposes. The cutover against the live staging database is step 6c.

---

## 1. Why this spec exists

Step 6a shipped the capability half — `Pediment\Seeder\Claimer`, header markup from a
`<stylesheet>/header` pattern, and the `--with-blocks` client-theme build (`5b8d360`, PR #78). It
was proven against fixtures and CI. Nothing has yet been pointed at a real legacy site.

Three of the parent spec's premises were checked against the actual artefacts — the repository at
`Bergert-Digital/workation-castle-website@main` (v0.12.0) and a WordPress XML export of the site's
pages taken 2026-08-06 — and three did not hold.

### 1.1 The site is far less translated than step 6's design assumed

The parent spec priced the migration at "roughly 19 pages × 5 languages". The export says
otherwise. It contains 26 pages:

- **18 seeded English pages**, exactly matching the map in `inc/seed.php`.
- **2 WordPress defaults that were never seeded** — the Sample Page (`beispiel-seite`, ID 2) and the
  auto-created draft privacy page (`datenschutzerklaerung`, ID 3). Neither appears in the manifest,
  so both stay unkeyed and outside the engine, which is the correct outcome.
- **6 translated pages in total.** `de`: `startseite`, `kontakt`. `nl`: `home`, `contact`.
  `fr`: `home`. `it`: `home`.

Translation is roughly 7% complete, not 100%. That inverts the migration's shape: there are 24
claimable rows, not 90, and the dominant effect of seeding is *creation*, not adoption.

Two slug facts from the same export shape the manifest. The `fr`, `it` and `nl` home pages all
literally carry `post_name = home`, sharing it with the English one — so on this site Polylang is
uniquifying slugs per language, which contradicts the premise recorded under "The derived slug
rule, and why" in `docs/seeding.md` ("Polylang does not hook `wp_unique_post_slug`"). And the Dutch
contact page is `contact` where the English one is `contact-us`. Both need explicit `slug`
overrides or the claim reports `no-match` and the seed creates duplicates.

**The export is from `staging.workationcastle.com`.** Every `<link>` carries the staging host.
Production does not exist yet; staging is the migration target, and the site that will later be
promoted to production is the one this work produces.

### 1.2 A manifest's `languages` section does not control what gets seeded

The obvious response to 1.1 — declare an English-only manifest and leave the translations alone —
does not work, and the reason is worth stating precisely because the documentation's phrasing
invites the mistake.

`DesiredState::build()` (`plugin/src/Seeder/DesiredState.php:43`) and `Claimer::plan()`
(`plugin/src/Seeder/Claimer.php:47,75`) both iterate `$this->lang->languages()` — the *provider's*
list. `LanguageRegistry::provider()` returns `PolylangProvider` whenever Polylang is active and
configured, regardless of the manifest, and `PolylangProvider::languages()` reads
`pll_languages_list()`. The manifest's `languages` section feeds only `LanguageGate::mismatch()`
and `wp pediment languages`; `LanguageGate` returns `null` for a manifest declaring none, on the
stated grounds that "a monolingual manifest imposes nothing."

So on a site where Polylang is configured with five languages, an English-only manifest still
crosses 18 entries × 5 languages = 90 desired entries and still creates the 66 missing pages. It
merely does so with the gate switched off.

Forcing `NullProvider` through the `pediment_language_provider` filter is worse, and
`LanguageGate`'s own docblock names it: with one empty language `Claimer::languageMatches()`
accepts every candidate, the four pages sharing `post_name = home` come back **ambiguous**, nothing
is claimed for `home`, and the next seed hits Differ rule 1 and creates a duplicate front page —
"the catastrophe the claim step exists to prevent."

The manifest therefore declares all five languages, and the 66 creations are accepted deliberately
(decision 2).

### 1.3 The nav schema cannot express Workation's header

`NavSeeder::serialize()` (`plugin/src/Seeder/NavSeeder.php`) emits a flat list of
`wp:navigation-link` blocks. `NavSpec::$items` is typed `array{entry?:string,url?:string,label?:string}`
with no children, and nothing anywhere emits `wp:navigation-submenu`.

Workation's header (`inc/PrimaryNav.php:191-205`) is two levels: submenus for **Ways to stay**
(3 children) and **Guest Guide** (5 children). Because navs have no hash arbitration — menu
membership is git-owned, so a claimed nav's links are rewritten by the very next seed — claiming
`primary` and seeding would flatten a two-level menu into roughly thirteen top-level links. That is
decision 7 of the parent spec ("recreating navigation on a live site is the most visible possible
failure") arriving through the front door.

Declaring no nav is not an escape. `pediment_seeded_nav_id()` would return 0 for every candidate,
`pediment_bind_navigation_ref()` would bail, and core's own fallback picks a navigation entity
language-blind — which is the precise failure `inc/PrimaryNav.php` was written to prevent.

### 1.4 What is *not* a problem

- **Block inventory.** 23 blocks under `src/blocks/` (the parent spec said 20) and 18 pattern files,
  all of which survive the conversion unchanged apart from their namespace.
- **Client PHP.** ~2,880 lines under `inc/` are genuinely client-owned and come across untouched.
- **Language configuration.** Polylang is already configured with exactly the five languages the
  manifest will declare, so `LanguageGate::mismatch()` passes and `wp pediment languages` — WP-CLI
  only, and the subject of a standing backlog entry — is not needed to close a gap that does not
  exist. It is still the tool of record if the sets ever diverge.
- **Risk profile.** There is no production site. A staging site with no public traffic is the
  target, which is what makes decision 2's 66 created pages and decision 6's broken-render window
  acceptable choices rather than reckless ones.

---

## 2. Decisions

| # | Decision | Rejected alternative | Why |
|---|---|---|---|
| 1 | **Convert the repo in place.** `workation-castle-website` keeps its history and its 278 commits; the conversion is mostly deletion plus one new file. A throwaway `--with-blocks` scaffold is generated once and diffed against the result as a checklist, not kept as a branch. | Scaffold a fresh repo and port into it | The history is the record of every design decision in 23 bespoke blocks. The CI wiring that a fresh scaffold would give for free is a `.github/workflows/` swap either way. |
| 2 | **The manifest declares all five languages, and the first seed creates the 66 missing translation rows.** | An English-only manifest; or forcing `NullProvider` | Neither alternative exists as described (§1.2). Since there is no production, 66 English-content skeleton pages on staging cost nothing, become the visible translation to-do list, and are deletable if unwanted. |
| 3 | **Nav submenus are added to the plugin**, so `NavSpec` accepts `items[].children` and `NavSeeder::serialize()` emits `wp:navigation-submenu`. Ships as a plugin minor in the monorepo, before the theme conversion. | Keep `inc/PrimaryNav.php` + `inc/NavTranslations.php` and declare no nav; or flatten the menu | Keeping them leaves ~830 lines of bespoke nav machinery in a client repo — the copy-paste drift the monorepo exists to end. Flattening is a navigation redesign, not a migration step. Every future client with a two-level menu hits this same gap. |
| 4 | **The five custom nav labels are dropped**; every `entry` item omits `label` and takes the linked entry's own per-language `post_title`. | Add per-language nav label overrides to the schema | A declared `label` is written verbatim into every language, so keeping the labels would need a second schema extension replicating `NavTranslations.php`'s 489-line map. `docs/seeding.md` already prescribes omitting `label` on a multilingual menu. The cost is cosmetic. |
| 5 | **The theme directory is renamed `pediment-child-theme` → `workation`,** matching the block namespace rename `pediment-child/*` → `workation/*`. | Keep the directory name | The header pattern must be named `<stylesheet>/header`, so one string has to serve as stylesheet slug, block namespace and pattern namespace. The rename also defuses the stale-header-part trap (§3.4). |
| 6 | **A one-shot `post_content` rewrite tool ships in the client theme,** run once from wp-admin at cutover and deleted in the release that follows. | Let the seeder push renamed content over the live rows | Claimed rows are content-protected by design (parent spec decision 5); unprotecting them would risk every page's live copy to fix a namespace string. |
| 7 | **`inc/LegacyBlockCopy.php` survives 6b.** Its deletion is a 6c decision made against the real database. | Delete it with the rest of the retired code | It supplies hero and section copy for pages stored before that copy moved into the patterns, and claimed pages keep their live `post_content`. Whether production's rows still need it is answerable only from real data. |
| 8 | **The CPT registrations stay in PHP.** `inc/Photos.php` and `inc/Activities.php` are not moved into the manifest's `post_types` section. | Declare them in the manifest | They carry taxonomies and rewrite specifics the manifest's flat arg array does not express, and moving them risks the 74-photo library's URLs for no gain. |

---

## 3. Design

### 3.1 Nav submenus (plugin, monorepo)

`NavSpec::$items` gains an optional `children` key on `{entry|url, label}` items:

```php
'navs' => array(
	'primary' => array(
		'title' => 'Primary',
		'items' => array(
			array( 'entry' => 'activities' ),
			array( 'entry' => 'photos' ),
			array(
				'entry'    => 'ways-to-stay',
				'children' => array(
					array( 'entry' => 'team-retreats' ),
					array( 'entry' => 'workations' ),
					array( 'entry' => 'family-and-groups' ),
				),
			),
			array(
				'entry'    => 'guide',
				'children' => array(
					array( 'entry' => 'arrival' ),
					array( 'entry' => 'check-in' ),
					array( 'entry' => 'map' ),
					array( 'entry' => 'faq' ),
				),
			),
			array( 'entry' => 'contact-us' ),
		),
	),
),
```

The nav tree is not the page tree, and the example above is correct where the two disagree:
`check-in` is a top-level page that appears inside the Guide submenu, and `casa-galbiga` is a child
page of `guide` that appears in no menu at all. Both match the live header
(`inc/PrimaryNav.php:191-205`) and neither is a mistake to be tidied up.

An item with `children` serializes as `wp:navigation-submenu` wrapping its children's
`wp:navigation-link` blocks; an item without them is unchanged. Nesting is **one level only** —
`children` inside `children` fails validation, because two levels is what a header menu is and an
unbounded tree invites a recursion the serializer would have to guard on every run.

The existing rules carry over unchanged to children: an `entry` naming an undeclared key fails
validation immediately, and an `entry` with no live post in that language is reported by
`unresolvedEntries()` and leaves the *whole* navigation untouched rather than writing it short.
A submenu parent that is itself unresolved takes its children with it — a submenu whose parent link
is missing is not a menu anyone meant.

Membership comparison, and therefore `update` detection, operates on the serialized string as it
does today, so nesting needs no separate diffing.

### 3.2 The manifest

`seed/manifest.php` declares 18 entries keyed flat, with `parent` on the seven nested ones —
`team-retreats`, `workations`, `family-and-groups` under `ways-to-stay`; `arrival`, `map`,
`casa-galbiga`, `faq` under `guide`. `home` carries `front_page => true`. Each entry's content
comes from a `pattern` naming one of the 18 existing pattern files.

`languages` declares `en` (default), `de`, `nl`, `fr`, `it`, matching `pll_languages_list()`
exactly. Six per-language overrides claim the translations that exist:

| entry | language | slug | title |
|---|---|---|---|
| `home` | `de` | `startseite` | Startseite |
| `home` | `nl` | `home` | Home - Nederlands |
| `home` | `fr` | `home` | Home - Français |
| `home` | `it` | `home` | Home - Italiano |
| `contact-us` | `de` | `kontakt` | Kontakt |
| `contact-us` | `nl` | `contact` | Contact |

The titles matter even though a claimed page's content and title are never written: nav item labels
fall back to the manifest's per-language `post_title`, so without them the German menu would read
"Contact". Every other entry declares no override, takes the derived `<slug>-<lang>` slug and the
English title, and appears in the seed's `TRANSLATIONS` section as a standing to-do item.

No `media` is declared beyond what the patterns already reference by absolute URL, so the 74-photo
library stays client-owned (parent spec decision 6).

`tools/manifest-from-wxr.mjs` generates the entry list and the override table from a WordPress XML
export. It is committed so it can be re-run against a fresh export immediately before the 6c
cutover — the cheapest available check that staging has not drifted since this manifest was
written. It emits PHP for review, never writes to a database.

### 3.3 What the repository becomes

**Deleted — 2,349 lines under `inc/`, plus their tests:** `seed.php` (846), `NavTranslations.php`
(489), `PrimaryNav.php` (339), `Polylang.php` (103), `ThemeUpdater.php` (126), `UpdateToken.php`
(175), `settings-updates.php` (271). Also `parts/header.html` and the two `functions.php` filters
that hide the parent theme's database header part, and the PHPUnit cases whose subject is deleted
code — `NavTranslationsTest`, and whatever in `ActivitySeedTest`, `CheckInSeedTest`, `GuideSeedTest`
and `MapSeedTest` exercises `Seed::upsert_pages()` or the nav source. Those four files mix page
seeding with activity and photo data assertions that must survive, so the implementation plan
triages them case by case rather than deleting files wholesale.

Workation becomes the first site to lose its theme auto-updater (step 5 decision 8) and update by
admin zip upload instead. The standing backlog entry asks whether that hurts; 6c is where the
answer comes from.

**Added:** `seed/manifest.php`; `templates/index.html`, without which `is_block_theme()` fails once
the parent theme is gone — the same contract the fixture client theme follows;
`patterns/header.php` registering `workation/header`; `tools/manifest-from-wxr.mjs`; and the one-shot
namespace rewrite tool of decision 6.

**Changed:** `style.css` drops `Template:` and `Update URI:` and moves its text domain from
`pediment-child` to `workation`; `.wp-env.json` drops the parent theme and pins the plugin release
zip; `.github/workflows/` calls the monorepo's reusable `client-theme.yml` and `client-release.yml`
while keeping the repo's own phpcs, PHPUnit and Playwright jobs; the `pediment-template` git remote
is dropped.

**Kept unchanged — ~2,880 lines:** `CheckIn.php`, `Brevo.php`, `Consent.php`, `EstateMap.php`,
`AvailabilityForm.php`, `Photos.php`, `Activities.php`, `Redirects.php`, `WorkationSections.php`,
`activities-manifest.php`, `photos-manifest.php`, and `LegacyBlockCopy.php` (decision 7).

### 3.4 Theme slug, block namespace, and the header

The live stylesheet slug is `pediment-child-theme`, inferred from the top directory the release zip
stages (`.github/workflows/build-release-zip.yml:81`); 6c confirms it against the site before
activating anything. The inference is low-risk either way, because what defuses the header trap is
that the new slug *differs* from the current one, not what the current one happens to be.

It becomes `workation`, and the 23 blocks move from
`pediment-child/*` to `workation/*`, so a single string serves as stylesheet slug, block namespace
and pattern namespace. The header pattern is therefore `workation/header`, which is what
`pediment_bootstrap_header_markup()` looks up as `get_stylesheet() . '/header'`.

The rename also resolves a trap that would otherwise be discovered live. The stale generic `header`
template part already in the database is scoped to the theme term `pediment-child-theme`. Since
`pediment_bootstrap_header_template_part()` is create-only, activating a theme still called
`pediment-child-theme` would find that part, skip seeding, and — with `parts/header.html` and its
two filters now deleted — leave the site showing the generic header. Under the new slug the
bootstrap sees no part for `workation` and seeds a fresh one from the branded pattern.

Its cost, which 6c carries: a theme switch resets theme mods, so the **custom logo and site icon
must be re-set by hand** after activation.

### 3.5 The rewrite window

Stored `post_content` still names `wp:pediment-child/*`, so between activating the converted theme
and running the rewrite tool, 23 blocks render as "block not found". The tool rewrites
`wp:pediment-child/`, the closing `/wp:pediment-child/`, and `wp-block-pediment-child-` class
occurrences across the seeded post types, from wp-admin, in one pass.

On a staging site with no traffic that window is acceptable, and the two steps run back to back. It
would not be acceptable on a site with traffic, and any future client migration doing this rename
needs a maintenance window or a dual-registration shim instead.

### 3.6 What 6b proves

On a clean wp-env with the plugin and the converted theme, Polylang configured to the five
languages, `wp pediment seed` builds the entire site from the manifest — 18 English pages, five
per-language navigations with both submenus intact, and the branded header — and CI is green,
including `client-theme.yml`'s `seed-check` job booting the theme and asserting a front page.

That is a *fresh-install* proof. 6b deliberately never touches a legacy database; `Claimer` meeting
real unkeyed rows is 6c's job.

### 3.7 Versioning

Two repositories release independently. The nav-submenu work (§3.1) adds a schema key and removes
nothing, so it ships from the monorepo as an ordinary **plugin minor** — conventional
`feat:`/`test:`/`docs:` commits, no `!`, no `Release-As:` footer, and release-please owns the
version files.

The theme conversion is breaking by any reading — the stylesheet slug changes, the block namespace
changes, and the theme stops being a child — so `workation-castle-website` goes from 0.12.0 to
**1.0.0**, matching the major-for-breaking convention the Pediment theme itself adopted. The plugin
minor must be released before the theme's CI can go green, since `seed-check` runs against the
published plugin zip.

---

## 4. Out of scope, and handed to step 6c

- The claim rehearsal against a staging import — the first time `Claimer` meets real unkeyed rows.
- The `wp_navigation` inventory. A Pages export contains none, and the claim's nav rule is
  deliberately slug-blind, so `docs/seeding.md` instructs reading every nav line of a claim preview
  by hand. That inventory has to be taken before the claim runs.
- The `inc/LegacyBlockCopy.php` deletion call (decision 7).
- Re-setting the custom logo and site icon after the theme switch (§3.4).
- Running the namespace rewrite tool, and deleting it in the release that follows.
- What to do with the 66 created translation skeletons, and whether live content gets adopted into
  git page by page afterwards.
- Whether losing the theme auto-updater hurts in practice.

Out of scope entirely:

- Per-language nav labels (decision 4) and nav nesting beyond one level (§3.1).
- Claiming media or terms — unchanged from the parent spec's decision 6.
- Migrating any site other than Workation.

---

## 5. Recorded for the backlog

- **`docs/seeding.md`'s derived-slug rationale is contradicted by a live site.** The stated premise
  is that Polylang does not hook `wp_unique_post_slug`; staging has four pages sharing
  `post_name = home`, one per language. The derived `<slug>-<lang>` rule is still correct as a
  default — a per-language slug that collides is a real failure mode — but the reason given for it
  does not hold universally, and the doc should say "may not" rather than "does not".
- **The two WordPress default pages** (`beispiel-seite`, and the draft `datenschutzerklaerung`
  whose title duplicates the seeded privacy page's purpose) are unkeyed and will stay so. Deleting
  them is a housekeeping call for 6c, not an engine concern.
