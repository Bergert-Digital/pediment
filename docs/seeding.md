# Seeding

The plugin ships a declarative seeding engine: a client theme declares the pages,
posts, media, navigation, and custom post types a site needs in a single
`seed/manifest.php` file, and `wp pediment seed` makes the database match it.
Content itself (block markup) lives in the theme's `patterns/` directory, not in
the manifest — the manifest is structure, patterns are content.

This is the reference for a client-theme author who has never touched the
engine: what you declare, what the seeder owns forever, what your client can
safely edit once the site is live, and what to do when a run reports a problem.
Every snippet below is copied from the plugin's own reference fixture,
[`tests/fixtures/client-theme/seed/manifest.php`](../tests/fixtures/client-theme/seed/manifest.php)
— the file the e2e suite seeds and asserts against on every CI run.

## The arbitration contract, in one paragraph

Git owns structure — which entries exist, their slugs, their nesting, which
page is the front page, which menu a page belongs to, which custom post types
are registered, which media files exist. The database owns content once a
human has edited it in the block editor. A stored hash decides which regime
applies to a given row: if the hash the seeder wrote still matches what's in
the database, git also still owns the content and a manifest/pattern change
gets written on the next run; the moment the row's hash stops matching (an
editor changed the title or body), content and title freeze permanently and
only structure keeps being enforced.

## The manifest

`seed/manifest.php` returns a plain PHP array. It is loaded from the *active*
theme's stylesheet directory, validated strictly, and the whole run refuses to
write anything if validation fails — a manifest typo must never become a
half-seeded database. The engine also reads it fresh (`Manifest::resetCache()`)
at the start of every `wp pediment seed` / `wp pediment adopt` run, so an
operator who just edited the file always sees current results.

"Strictly" includes rejecting keys it doesn't recognise, at both levels: the
top-level sections are exactly `version`, `pages`, `posts`, `entries`, `media`,
`navs`, `post_types`, `site`, and the per-entry keys are exactly the ones in
the table below. `'page' =>` instead of `'pages' =>`, or `'front-page' =>`
instead of `'front_page' =>`, is a `ManifestError` naming the offending key —
never a section that silently seeds nothing.

```php
return array(
	'version' => 1,
	'site'    => array( 'logo' => 'logo' ),
	'media'   => array(
		'logo' => array( 'file' => 'seed/media/logo.svg', 'title' => 'Pediment e2e logo' ),
	),
	'pages'   => array(
		'home'      => array( 'title' => 'Home', 'pattern' => 'pediment/pediment-landing', 'front_page' => true ),
		'about'     => array( 'title' => 'About', 'pattern' => 'pediment-fixture/about' ),
		'contact'   => array( 'title' => 'Contact', 'content' => '' ),
		'blog'      => array( 'title' => 'Blog', 'content' => '', 'posts_page' => true ),
		'mega-demo' => array( 'title' => 'Mega Menu Demo', 'pattern' => 'pediment/mega-menu-header' ),
	),
	'posts'   => array(
		'sample-insight-one'  => array( 'title' => 'A practical insight on getting started', 'pattern' => 'pediment-fixture/sample-post', 'terms' => array( 'category' => array( 'insights' ) ) ),
		// … five more sample posts, one manifest entry each …
	),
	'navs'    => array(
		'primary' => array(
			'title' => 'Header Navigation',
			'items' => array(
				array( 'entry' => 'about', 'label' => 'About' ),
				array( 'entry' => 'blog', 'label' => 'Blog' ),
				array( 'entry' => 'contact', 'label' => 'Contact' ),
			),
		),
	),
);
```

### `pages`, `posts`, `entries`

All three sections share one entry format and one identity space — a key
declared in `pages.about` and again in `posts.about` is a **duplicate seed
key**, rejected before anything runs (see Failure modes below). `pages`
defaults `post_type` to `page`, `posts` defaults it to `post`; `entries` has no
default, so every `entries.*` item must declare `post_type` explicitly (this is
how a client theme seeds its own custom post types' content once
`post_types` — below — has registered them).

Per-entry keys:

| Key | Required | Default | Notes |
|---|---|---|---|
| `title` | yes | — | Non-empty string. |
| `pattern` **or** `content` | yes, exactly one | — | `pattern` is a registered block-pattern slug; `content` is literal block markup (an empty string is valid, e.g. the fixture's `contact` and `blog` pages). Declaring both, or neither, is a validation error. |
| `post_type` | `pages`/`posts`: no; `entries`: yes | `page` / `post` | Never rewritten once a row exists — a mismatch between the manifest and the database is a hard error (see below). |
| `slug` | no | the last `/`-segment of the key | Must already be a valid slug (`sanitize_title($slug) === $slug`) and non-empty, so a key may not end in `/`; it is structure and gets reverted if an editor changes it. |
| `parent` | no | none | Another entry's key. Must be declared; parent cycles are rejected at load time. |
| `front_page` | no | `false` | At most one entry site-wide may set this. |
| `posts_page` | no | `false` | At most one entry site-wide may set this. |
| `menu_order` | no | `0` | Structure; enforced. |
| `terms` | no | `[]` | `taxonomy => [slug, …]`. Create-only — see Limitations. |

### `media`

```php
'media' => array(
	'logo' => array( 'file' => 'seed/media/logo.svg', 'title' => 'Pediment e2e logo' ),
),
```

`file` is required and resolved relative to the theme directory (or an
absolute path). Two different checks can stop the run before anything is
written, at two different times: a `file` that isn't readable fails manifest
validation itself — the whole manifest refuses to load, the same as any other
`ManifestError`. A `file` whose extension isn't one of
`jpg jpeg png gif webp avif svg pdf` loads fine but fails at plan time — the
manifest is valid, but that media item's plan carries an error, which blocks
the run the same way a duplicate seed key does. `title` defaults to the media
key. Media is identified the same way everything else is — by a stable key,
via `_pediment_seed_key` on the attachment — never by filename.

### `navs`

```php
'navs' => array(
	'primary' => array(
		'title' => 'Header Navigation',
		'items' => array(
			array( 'entry' => 'about', 'label' => 'About' ),
			array( 'entry' => 'blog', 'label' => 'Blog' ),
			array( 'entry' => 'contact', 'label' => 'Contact' ),
		),
	),
),
```

Each item is either `{ entry, label }` — a link to a manifest entry, resolved
to that entry's live post ID and permalink at write time — or `{ url, label }`
for an external/custom link. An `entry` that names an undeclared key fails
validation immediately; an `entry` that's declared but has no live post yet
(e.g. its own write failed earlier in the same run) is reported on every run,
and the whole navigation is left exactly as it is rather than written without
that link — a menu that quietly comes out one item short is worse than one
that visibly fails.

`label` is optional on an `{ entry, … }` item. When it is omitted,
`NavSeeder::serialize()` falls back to the linked entry's own `post_title` —
which is already resolved per language — instead of a fixed string. On a
multilingual site this is the difference between a header nav whose items
translate and one that renders the same English text in every language: a
declared `label` is a fixed string written into every language's menu
verbatim, so a *bilingual* menu should omit `label` on every `entry` item and
let each language's own title carry it. `{ url, … }` items have no linked
entry to fall back to, so `label` is effectively required there.

The header template part's ref-less `core/navigation` block binds to
whichever nav is keyed `primary` (`plugin/inc/nav-language.php`,
`pediment_seeded_nav_id()`) — this is the one nav key the engine treats as a
contract, not a coincidence: nothing else in `Manifest`/`NavSpec` requires it,
so a theme is free to key its other menus however it likes, but the menu it
wants bound to the header's ref-less block must be called `primary`, or must
opt out via the `pediment_primary_nav_key` filter.

#### Submenus

An item may declare `children`, which serializes it as a
`wp:navigation-submenu` wrapping their `wp:navigation-link` blocks:

```php
'navs' => array(
	'primary' => array(
		'title' => 'Primary',
		'items' => array(
			array( 'entry' => 'activities' ),
			array(
				'entry'    => 'guide',
				'children' => array(
					array( 'entry' => 'arrival' ),
					array( 'entry' => 'faq' ),
				),
			),
		),
	),
),
```

Children take the same shape as top-level items — `{ entry, label }` or
`{ url, label }`, with `label` optional on an `entry` item and falling back to
that entry's own per-language title — and the same validation. **Nesting stops
there:** `children` inside `children` is a `ManifestError`, not a silently
flattened menu.

Two consequences worth knowing before you declare one:

- **The nav tree is not the page tree.** A submenu child needs no `parent` in
  the `pages` section, and a page with a `parent` need not appear under it in a
  menu. They are independent.
- **A submenu parent takes its children with it.** If the parent's entry has no
  live post in a language, the whole submenu is omitted from that language's
  serialized menu rather than its children being promoted to the top level —
  and, as with any unresolved link, `unresolvedEntries()` reports it and the
  navigation is left exactly as it is rather than written short.

### `post_types`

Not exercised by the fixture manifest above — client themes that need a custom
post type declare it here, e.g.:

```php
'post_types' => array(
	'guide' => array( 'has_archive' => true, 'label' => 'Guides' ),
),
```

Registered on every `init` (not only during seeding — a CPT that only existed
while `wp pediment seed` ran would leave its already-seeded entries
unreachable). Defaults layered under whatever you pass:
`public => true`, `show_in_rest => true`, `has_archive => false`,
`supports => ['title','editor','excerpt','thumbnail','custom-fields']`,
`label => ucfirst($slug)`. If something else already registered the same slug,
the manifest's settings are silently not applied — the Verifier catches and
reports this (see below), it doesn't fail silently.

### `site.logo`

```php
'site' => array( 'logo' => 'logo' ),
```

Names a `media` key; must already be declared there. On apply, that
attachment becomes the site's custom logo (`theme_mod: custom_logo`) — and
keeps becoming it: this is structure, re-asserted on every run, not a
one-time default (see Limitations).

## Languages

A monolingual manifest declares no `languages` section and nothing below
applies — `Manifest::languages()` returns `[]`, `DesiredState` crosses every
entry with exactly one (empty) language, and every command's behavior is
unchanged. The moment a `languages` section exists, the site is multilingual
and the rules in this section become load-bearing.

```php
'languages' => array(
	'en' => array( 'name' => 'English', 'locale' => 'en_US', 'flag' => 'gb', 'default' => true ),
	'de' => array( 'name' => 'Deutsch', 'locale' => 'de_DE', 'flag' => 'de' ),
),
```

Every language declares `locale` (e.g. `de_DE`); `name` and `flag` are
optional and default to `strtoupper($slug)` / `''`. Exactly one language may
set `default => true`; if none does, the first one declared becomes the
default. **Declaration order matters even when a `default` key is present**:
the engine re-orders the parsed list so the default is always first, and
everything downstream depends on a language having a well-defined position —
`Applier::linkTranslationGroups()` and `NavSeeder`'s equivalent build one
translation group per seed key from whichever languages exist, and the
Applier resolves a child's `post_parent` and the front-page option from the
*default* language's IDs specifically, so a default that was somehow written
after its children would try to parent them onto a post that doesn't exist
yet. A language slug must already be a valid `sanitize_title()` slug (`en`,
`pt-br`) — Polylang builds URL prefixes from it directly, and it is also the
literal suffix a derived per-language slug carries ("The derived slug rule,
and why," below).

### Per-entry overrides

Any `pages`/`posts`/`entries` item may declare a `languages` sub-section
keyed by language code, overriding `title`, `slug`, and/or `pattern` for that
one language:

```php
'about' => array(
	'title'     => 'About',
	'pattern'   => 'pediment-fixture/about',
	'languages' => array(
		'de' => array( 'title' => 'Über uns', 'slug' => 'ueber-uns' ),
	),
),
```

Two shapes are rejected outright, both because they would otherwise fail
silently:

- **An empty `title` override.** `EntrySpec::titleFor()` falls back to the
  default language's title with `??`, which only catches a missing key, not
  an empty string — a stored `''` would pass straight through as the post
  title with no warning anywhere.
- **A `pattern` override on an entry declared with literal `content`.** That
  entry has no pattern to translate; the resolver would discard the override
  unconditionally and the operator would never learn their translation was
  thrown away.

Omitting a language's override entirely is not an error — the page renders
the default language's title/content/slug and the run reports it as a
notice, not a failure (see "Reading a plan" below).

### The derived slug rule, and why

A non-default language with no declared `slug` override does **not** reuse
the default language's slug. It gets `<slug>-<lang>` instead. None of the
fixture manifest's six sample posts
(`tests/fixtures/client-theme/seed/manifest.php`) declare a `languages`
override at all, so `sample-insight-one`'s German slug is entirely derived:

```
en: sample-insight-one        (declared)
de: sample-insight-one-de     (derived — this post declares no `languages` override)
```

The reason is structural, not cosmetic: **Polylang does not hook
`wp_unique_post_slug`.** WordPress's own slug-uniquification only fires
within one language's rows; all top-level pages across every language still
share one `post_name` namespace regardless of which language owns them. If
`slugFor()` instead reused the default's slug verbatim, two languages both
asking for the literal slug `sample-insight-one` would land as
`sample-insight-one` and `sample-insight-one-2` — indistinguishable from any
other slug collision — and once that happens `Verifier::verify()` reports a
mismatch on every run forever, because retrying would just get
re-uniquified identically on the next write (see "Failure modes" below).
`EntrySpec::slugFor()` (`plugin/src/Seeder/EntrySpec.php`) and `NavSeeder`'s
private `slugFor()` (`plugin/src/Seeder/NavSeeder.php`) both apply the same
`<key>-<lang>` idiom for the same reason — the derived suffix is the
language *code*, which is what actually keeps `sample-insight-one-de`
distinct from `sample-insight-one-2`.

### The pattern file convention

A non-default language's pattern, absent a `pattern` override, is looked up
by the same `<pattern>-<lang>` convention:

```
patterns/about.php       Slug: pediment-fixture/about
patterns/about.de.php    Slug: pediment-fixture/about-de
```

**The filename suffix and the `Slug:` header suffix must agree.** The
filename (`about.de.php`) is how a developer finds the file; the `Slug:`
header (`pediment-fixture/about-de`) is what `WP_Block_Patterns_Registry`
actually indexes on and what `EntrySpec::patternFor()` computes and asks for.
A file with the right name but a header that doesn't carry the `-de` suffix
registers under the wrong slug, and the translated pattern is reported
missing exactly as if the file didn't exist.

### `wp pediment languages`

```
wp pediment languages [--dry-run]
```

Configures Polylang itself from the manifest's `languages` section — creates
missing languages, sets `default_lang`, makes `wp_navigation` translatable,
and locks `media_support`/`taxonomies` off and `redirect_lang`/`hide_default`
on (see the Polylang traps in `docs/WORDPRESS_TRAPS.md` for why each of
those). This is deliberately its own command, run **before** seeding, not a
phase inside `wp pediment seed`: phase 4 of a seed run has to stay
inspectable by `--dry-run`, and writing another plugin's settings inside it
would not be.

**`wp pediment seed` hard-errors if the manifest's languages and Polylang's
configured languages disagree** — it will not seed content into a language
set the site doesn't actually have. The exact message
(`Runner::languageMismatch()`, `plugin/src/Seeder/Runner.php`):

```
Language mismatch: the manifest declares [de, en] but this site has [en]
configured. Run `wp pediment languages` first — seeding into the wrong
language set writes content no translation lookup can find.
```

Run `wp pediment languages` (without `--dry-run`) to close the gap, then
re-run `wp pediment seed`. A manifest with no `languages` section imposes
nothing here — a site may run Polylang for its own reasons and still seed a
single-language theme.

### The `TRANSLATIONS` section of a dry-run plan

A language that's missing a title or a pattern is not a validation error —
the page is real, navigable, and renders the default language's content in
the meantime. `wp pediment seed --dry-run` reports it as a notice instead:

```
TRANSLATIONS
  - about (fr): no title declared — the page carries the default language title "About".
  - contact (fr): no pattern `pediment-fixture/contact-fr` is registered — the page
    carries the default language content. Create patterns/contact.fr.php with
    `Slug: pediment-fixture/contact-fr`, or run
    `wp pediment adopt contact --language=fr` once it is translated in the editor.
```

**Notices never fail the run.** `RunResult::ok()` only inspects `errors` and
`problems`; a fresh site that just added a language would otherwise fail its
very first seed on the exact gap the notice exists to describe. Read the
`TRANSLATIONS` section as a translation to-do list, not as something to fix
before `wp pediment seed` will run.

### `wp pediment adopt <key> --language=de`

```
wp pediment adopt <key> [--language=<code>] [--dry-run]
```

Same export as the monolingual case, scoped to one language: it reads the
live `<key>|<language>` post and writes (or refreshes) exactly the pattern
file the convention above expects —
`patterns/<stem>.<lang>.php` with `Slug: <pattern>-<lang>` — using whichever
per-language title the manifest already declares (`adopt` never writes
titles). For the default language, or on a monolingual site, `--language` is
omitted or matches the default and the file has no suffix, same as before
this step existed.

## What "structure" means, concretely

Everything the seeder re-asserts on every run, regardless of what an editor
has done to a row:

- **Existence** — a manifest entry with no matching row gets created.
- **Slug** — reverted to the manifest's value if changed (see the slug-collision
  failure mode below for the one case this can't do).
- **Nesting** — `post_parent`, resolved from the `parent` key to the parent's
  live post ID.
- **Front page / posts page** — `show_on_front` / `page_on_front` /
  `page_for_posts`, enforced for the default-language entry.
- **Nav membership** — which entries a navigation menu links to, and in what
  order (see Limitations: this one *does* revert an editor's menu edit). A
  trashed menu is restored in place, not replaced by a second entity.
- **Custom post type registration** — the CPT exists and is reachable.
- **Media presence** — a `media` key resolves to exactly one live attachment;
  trashed attachments are restored rather than re-uploaded.
- **The site logo** — `site.logo` is re-asserted on every run whenever
  `custom_logo` has drifted, including away from a value a client chose in the
  Customizer (see Limitations).
- **Editorial state** — only `trash` is reverted (restored to `publish`).
  `draft` and `pending` are left alone: a client taking a page offline, or
  holding a revision for review, is not overruled by the next seed.

Title and body content are explicitly *not* on this list once a row has been
edited — that's the arbitration contract above.

## The two hashes

`Pediment\Seeder\Meta` defines the identity and arbitration keys:

- `_pediment_seed_key` — stable identity. The engine never looks up seeded
  content by slug or title; duplicate keys are the one thing it treats as
  fatal (below).
- `_pediment_seed_hash` — a hash of the **persisted row**
  (`ContentHash::forPost()`, title + `post_content` as WordPress actually
  stored them, *after* WordPress's own normalization). This is what decides
  "has the client edited this?" — hashing the intended input instead would
  make WordPress's own markup normalization look like an edit on literally
  every page, on the very first re-seed.
- `_pediment_seed_source` — a hash of the **git-side input** (the resolved
  pattern or literal content) the seeder last wrote. This is what decides "has
  the manifest changed since the last write?"

On every entry, the Differ applies one rule, in order:

1. No row exists → **create** it.
2. The stored hash is missing, from an older hash version, or no longer
   matches the row → the row is treated as edited: title and content are
   never touched; only structure is enforced. The dry-run plan's note tells
   you which of the three it was — see "Reading a plan" below.
3. Otherwise → content is up for grabs: write it if `_pediment_seed_source`
   shows the manifest/pattern changed, leave it alone if not.

Because rule 2 also fires for a row the engine has simply never written a
hash to yet, a *claimed* row's very first `wp pediment seed` is safe — it is
treated as edited and only gets structure applied, never a silent content
overwrite. That safety net only covers rows the engine can see in the first
place: `StateReader::read()` only reads posts that already carry
`_pediment_seed_key` at all (`meta_key => Meta::KEY, meta_compare =>
'EXISTS'`), so an *unclaimed* legacy row has no `$actual` entry whatsoever.
For that row `Differ::diff()` never reaches rule 2 — `$have` is `null`, rule
1 fires, and `wp pediment seed` plans a **create**, duplicating the row
instead of protecting it. Rule 2's protection is conditional on the row
already carrying a key; `wp pediment claim` (below) is what gives an existing
site's content that key in the first place, before its first seed.

## Reading a plan (`wp pediment seed --dry-run`)

A clean, up-to-date site:

```
Pediment seed — dry run
manifest: /var/www/html/wp-content/themes/pediment-fixture/seed/manifest.php

MEDIA
  unchanged   logo

PAGES & POSTS
  unchanged   home
  unchanged   about
  unchanged   contact
  unchanged   blog
  unchanged   mega-demo
  unchanged   sample-insight-one
  unchanged   sample-insight-two
  unchanged   sample-briefing-one
  unchanged   sample-briefing-two
  unchanged   sample-note-one
  unchanged   sample-note-two

NAV
  unchanged   primary

0 to write, 0 protected, 0 orphan, 13 unchanged. Nothing was written (--dry-run).
```

That's the state every commit to a client theme's `seed/manifest.php` /
`patterns/` should converge to before merging: `0 to write`. `docs/STANDARDS.md`
makes this a requirement, not a suggestion.

A row that needs a structural fix (here, an editor renamed a page's slug)
shows the field, its current and desired value, and rolls up into the
`N to write` count:

```
PAGES & POSTS
  update      about            slug "about-clientslug" -> "about"

NAV
  update      primary          items 3 -> 3

2 to write, 0 protected, 0 orphan, 11 unchanged.
```

(The nav shows `update` too here because the nav's stored links reference the
page's numeric ID; the *count* of items doesn't change, but this is a case
where a stale reference would be invisible if the report only counted items —
so treat "items 3 -> 3" as "the membership no longer matches the manifest",
not as "nothing to see here.")

A row whose content is genuinely protected — the manifest/pattern changed
*and* an editor separately changed the same row — carries a `^ protected:`
line naming the frozen fields and why:

```
  update      guide/pricing    slug "pricing-old" -> "pricing"
              ^ protected: title, content (edited in the editor — content and title left alone)
```

The three notes you'll see there, and what each means:

- `edited in the editor — content and title left alone` — a real client edit;
  the fix (if you want git to own it again) is `wp pediment adopt`.
- `never seeded by this engine — content left alone; run wp pediment adopt to
  take it into git` — this row predates the seeder (e.g. migrating an existing
  site onto it). Same fix.
- `seeded by an older hash version — content left alone; re-adopt to refresh
  it` — the engine's hash format changed since this row was last written.

**A plan-only edit that does not collide with a manifest change never shows
up as "protected" at all.** If nobody has touched the manifest since a client
edited a page, the Differ has nothing it wants to write, so the item reports
`unchanged` — correctly, since there's genuinely no pending action, but it
means a dry run cannot answer "has anyone edited this page in the editor?" on
its own.

Rows the manifest no longer declares, but that still carry a seed key, are
listed as `orphan` and left in place — nothing ever deletes a post on your
behalf.

Two things a dry-run plan is silent about, because the Applier — not the
Differ — owns them and they never produce plan items: the front-page/posts-page
options, and taxonomy term drift on an existing entry (see Limitations).

## `wp pediment seed`

```
wp pediment seed [--dry-run] [--json]
```

Runs the same five phases WP-CLI and wp-admin both go through: resolve desired
state → resolve actual state → diff into a plan → apply → verify. `--dry-run`
stops after the plan and writes nothing. `--json` emits the same information
machine-readably (`applied`, `ok`, `counts`, one object per plan item,
`errors`, `problems`) instead of the text report above.

Rewrite rules are flushed once, at the very end, and only softly
(`flush_rewrite_rules(false)`) — the engine never sets `permalink_structure`
itself. Forcing pretty permalinks on activation breaks REST under wp-env /
containerized Apache installs (`.htaccess` isn't honored there); see
`plugin/inc/bootstrap.php`. A real host opts into pretty permalinks via
Settings → Permalinks, which flushes correctly there.

## `wp pediment adopt`

```
wp pediment adopt <key> [--language=<code>] [--dry-run]
```

The inverse of seeding: exports a live entry's current block markup back into
its `patterns/<slug>.php` file, and resets both hashes so the next seed treats
the page as up to date rather than protected. Concretely, it:

- refuses to run against an entry declared with literal `content` — only
  `pattern`-backed entries have a file to adopt into;
- converts attachment URLs and numeric attachment IDs it recognizes back into
  `{{media_url:<key>}}` / `{{media_id:<key>}}` placeholders, so the committed
  file doesn't carry an environment-specific URL;
- preserves an existing pattern file's header (Description, Keywords, etc.) if
  one is already there, rather than regenerating a bare one;
- keeps a `.bak` sibling when it's about to overwrite a file whose contents
  differ from what it's about to write;
- reads the file back the way the pattern registry will, verifies the
  written `Slug:` header still matches what the manifest expects, and rolls
  back to the backup (or deletes the new file) if it doesn't — a mismatched
  header would mean the next seed can't find the pattern at all;
- stores the source hash in exactly the shape the next seed will compute it —
  the *manifest's* title crossed with the pattern output *after* media
  placeholders expand. Hashing the file's raw bytes instead would leave every
  media-bearing page mismatched, and hashing the live title would make the
  next run write the manifest's old title back over a client's rename.

**Adopt takes the body into git, never the title.** The manifest is a
hand-edited file and the engine does not rewrite it, so adopting a page a
client renamed prints a warning naming both titles and leaves the manifest
alone. The next seed will not revert the rename (the source hash matches), but
git and the database now disagree about the name until you edit the manifest
yourself.

**Known limitation:** sized image variants (`hero-300x200.jpg`) and `srcset`
attributes are *not* rewritten to placeholders — only the exact attachment URL
and bare `"id":<n>` occurrences are. Adopting a page with responsive images
will commit environment-specific srcset URLs; review the diff before
committing.

## wp pediment claim

```
wp pediment claim [--dry-run]
```

`StateReader` resolves actual state purely from `_pediment_seed_key` —
nothing else — so a first `wp pediment seed` against a site whose content
predates the seeding engine sees no existing rows at all and plans a
`CREATE` for every manifest entry, duplicating the whole site instead of
adopting it. A claim is the one-time bridge: it matches existing rows to
manifest entries by the things a legacy row and a manifest entry can still
agree on, and writes exactly one meta key, `_pediment_seed_key`
(`Pediment\Seeder\Claimer`, `plugin/src/Seeder/Claimer.php`).

That's the only thing it writes. It never writes `_pediment_seed_hash` — and
the omission is the point, not an oversight: the Differ's rule 2 above ("The
two hashes") treats a missing hash exactly like an edited row, so a claimed
row comes out of the very next seed *protected* — structure is enforced,
title and content are left alone. Bringing a page under git's control after
that is `wp pediment adopt <key>`, the same command that adopts any other row.

### Precondition: configure languages before you claim

On a multilingual site, run [`wp pediment languages`](#wp-pediment-languages)
**before** the first claim, not after. `ClaimRunner::run()` refuses to plan
anything while the manifest's declared languages and the site's configured
ones disagree — the same `LanguageGate::mismatch()` check `wp pediment seed`
applies, with the same message — because claiming with the languages
unconfigured is a mistake a re-run cannot repair. With Polylang inactive or
unconfigured, `LanguageRegistry` hands back `NullProvider`, whose language
list is a single empty string: the claim would key only the default-slug rows
and report every other language's live page as `no-match`. Configure the
languages afterwards, seed, and each of those unclaimed pages trips the
Differ's rule 1 and is created again alongside the live one — the exact
duplication a claim exists to prevent.

`wp pediment languages` is WP-CLI only; it has **no wp-admin equivalent**.
The option that makes `wp_navigation` translatable, in particular, can never
be ticked by hand (`PolylangSetup::configure()`). So on admin-only hosting a
multilingual claim is not self-service: arrange CLI access for that one
command first.

### What can be claimed

`Claimer::STATUSES` never includes `trash`; a trashed post is not a candidate
for anything a claim does. Beyond that, `Claimer::planOne()` walks each
unresolved `(key, language)` pair in `Manifest::entriesInDependencyOrder()`
(a parent is claimed before the children whose match depends on it) and
applies these checks in the order the code applies them — a precondition
first, then the candidate filters:

**Precondition — parent.** If the spec declares a `parent`, that parent's
key must already be resolved *in this language* before anything else runs.
An unresolved parent is an immediate `no-match` — `Claimer::candidates()` is
never even called for this pair — because a nested match can't be verified
against a parent with no live post yet.

Once that precondition passes (or there is no parent to check), `candidates()`
runs one query and then filters its results, in order:

1. **Type and slug.** The query asks for the entry's `post_type` and the
   language-specific slug `EntrySpec::slugFor()` computes — the derived
   `<slug>-<lang>` suffix on a non-default language, same rule seeding itself
   uses (see "The derived slug rule" above).
2. **Never trash.** The same query's `post_status` list — publish, draft,
   pending, private, future only.
3. **Never already keyed.** A row carrying any `_pediment_seed_key` is
   filtered out next — a claim never steals a row that belongs to another
   key.
4. **Parent.** A candidate's `post_parent` must equal the already-resolved
   parent's post ID (`0` when the spec declares none) — a same-slug page
   nested somewhere else is a different page.
5. **Language.** `Claimer::languageMatches()` has three branches: on a
   monolingual site (`$language === ''`) every candidate matches, since
   there is only one language to compare against. On a multilingual site, a
   candidate's own language must equal the language being claimed for
   (`translationOf($postId, $language) === $postId`), except that a post
   carrying no language at all (`LanguageProvider::hasLanguage()` false) is a
   candidate for the *default* language only. That's the
   monolingual-site-adopting-Polylang case; claiming an untagged post into a
   non-default language would silently move it between languages.

Exactly one surviving candidate is claimed. Zero is reported `no-match` — the
next `wp pediment seed` will create the page, which is the correct outcome
for something that genuinely doesn't exist yet. Two or more is reported
`ambiguous`, and **nothing is written** for that entry: the claim can't tell
which row is the real one, and guessing wrong would permanently key the
wrong post as that entry's identity while the page you actually meant sits
unclaimed and orphaned.

Navs are matched differently, because a legacy navigation entity's slug is
whatever the site's previous seeder happened to give it — slug alone is not
reliable evidence there (`Claimer::planNav()`). When the manifest declares
exactly one nav and the language holds exactly one unclaimed `wp_navigation`
entity, that pairing is unambiguous and is claimed without even checking the
slug. Otherwise the fallback is the derived slug `NavSeeder`'s own
`slugFor()` computes, and a nav is claimed only if exactly one unclaimed
entity's slug matches it. The reporting split is *not* the same as entries':
`no-match` is reported only when there are **zero** unclaimed navigation
entities in that language at all. A single unclaimed nav whose slug does not
match the derived one is reported `ambiguous`, not `no-match` — the claim can
see a menu there and will not guess.

> **Check every nav line in a claim preview by hand.** Navs are the one place
> where the claim's "writes nothing but the identity" promise does not carry
> through to the seed that follows. There is no hash arbitration for navs at
> all: menu membership is git-owned, so `NavSeeder::plan()` compares the
> serialized item list and emits an `update` whose note reads *"membership is
> git-owned; editor changes to this menu are reverted."* A claimed nav's links
> are therefore **rewritten by the very next seed** — unlike a claimed page,
> which comes out `protected`.
>
> That matters because of the slug-blind rule above. On a live site whose only
> Site-Editor menu is, say, a footer menu, one declared nav plus one unclaimed
> `wp_navigation` is enough: the claim keys that footer menu as `primary`
> without comparing slug or title, and the next seed replaces its links with
> the manifest's. The rule is deliberate — legacy menus carry slugs like
> `primary-2` that cannot slug-match, which is the case it was built for — so
> the safeguard is you, reading the `navigation "<title>" (ID <n>)` note in
> `wp pediment claim --dry-run` and confirming it names the menu you meant.

### Re-running claim

Claiming is idempotent and safe to re-run. A row that already carries a key
is excluded from `Claimer::candidates()` on the next run, and for entries
`Claimer::plan()` never even calls `planOne()` for a `(key, language)` pair
`StateReader::read()` already resolves — it's simply absent from the plan.

Navs need their own bookkeeping for the same guarantee, because
`StateReader::EXCLUDED_TYPES` excludes `wp_navigation` — the `$resolved` map
that protects entries has no opinion on navs at all. `Claimer::plan()` builds
a second lookup for exactly this, `NavSeeder::keyed()`, and skips any nav key
it already reports before calling `planNav()`. Without that skip, a re-run
over a site with one already-claimed nav and one stray, unrelated
`wp_navigation` post would find the stray as the *only* unclaimed candidate
and claim it under the key the first nav already carries — two posts sharing
one `_pediment_seed_key`, the one state the engine treats as fatal
everywhere else. `9cfc10f` closed exactly this gap; if you're working from an
older checkout, update first.

### Worked example

Copied from an actual run against a fixture site where `about` had lost its
seed key, `contact`'s underlying page had been renamed out from under its
slug, and two unclaimed pages both carried the slug `mega-demo`:

```
Pediment claim — dry run
manifest: /var/www/html/wp-content/themes/pediment-fixture/seed/manifest.php

PAGES & POSTS
  claim      about (en)       page "about" (ID 7)
  no-match   contact (en)     no unclaimed page with slug "contact" — the next seed will create it.
  ambiguous  mega-demo (en)   2 unclaimed page posts share the slug "mega-demo" (IDs 10, 32) — claim nothing until one is deleted or re-slugged.

1 to claim, 1 without a match, 1 ambiguous. Nothing was written (--dry-run).
Success: Dry run complete — nothing was written.
```

`--dry-run` prints this and writes nothing. The same command without it calls
`Claimer::apply()`, which writes `_pediment_seed_key` for every `claim` line
and stops there — `no-match` and `ambiguous` lines are report-only either
way.

### Worked example: claim through to protected

The example above stops at the claim's own report. This one continues one
step further, onto the *next* `wp pediment seed`, to show the payoff the
claim exists for: a claimed row reads back as `protected`, not `unchanged`,
because `Claimer::apply()` never wrote a hash. Captured from a rehearsal
against three pages (`about` ID 46, `contact` ID 8, `blog` ID 9) with every
seed meta key — `_pediment_seed_key`, `_pediment_seed_hash`,
`_pediment_seed_source` — stripped, so the setup actually models a page that
predates the seeding engine (see the note below for why that distinction
matters):

```
Pediment claim — dry run
manifest: /var/www/html/wp-content/themes/pediment-fixture/seed/manifest.php

PAGES & POSTS
  claim      about (en)       page "about" (ID 46)
  claim      contact (en)     page "contact" (ID 8)
  claim      blog (en)        page "blog" (ID 9)

3 to claim, 0 without a match, 0 ambiguous. Nothing was written (--dry-run).
Success: Dry run complete — nothing was written.
```

```
Pediment claim
manifest: /var/www/html/wp-content/themes/pediment-fixture/seed/manifest.php

PAGES & POSTS
  claim      about (en)       page "about" (ID 46)
  claim      contact (en)     page "contact" (ID 8)
  claim      blog (en)        page "blog" (ID 9)

3 to claim, 0 without a match, 0 ambiguous.
Success: Claim applied.
```

Nothing so far has touched content — both runs above only name and then
write a `_pediment_seed_key`. The next seed's dry run is where the claim's
promise shows up:

```
Pediment seed — dry run
manifest: /var/www/html/wp-content/themes/pediment-fixture/seed/manifest.php

MEDIA
  unchanged   logo

PAGES & POSTS
  unchanged   home
  protected   about            never seeded by this engine — content left alone; run `wp pediment adopt` to take it into git
  protected   contact          never seeded by this engine — content left alone; run `wp pediment adopt` to take it into git
  protected   blog             never seeded by this engine — content left alone; run `wp pediment adopt` to take it into git
  unchanged   mega-demo
  ... (remaining unchanged rows and the TRANSLATIONS block elided — same shape as the "Reading a plan" example above)

0 to write, 3 protected, 0 orphan, 22 unchanged. Nothing was written (--dry-run).
Success: Dry run complete — nothing was written.
```

`--dry-run` again, so this still wrote nothing — it only reports what a real
`wp pediment seed` would do. Three claimed rows, three `protected` lines, the
summary's `protected` count matching, and the note text is exactly rule 2's
`'' === $have->storedHash` branch (see "The two hashes" above): the row was
never seeded by this engine, so title and content are left alone.

**Building a rehearsal or test fixture for this sequence, deleting only
`_pediment_seed_key` from a page that has already been through a real seed
is not enough** — the stored hash and source hash survive untouched, still
match the untouched content, and the claim only restores the identity key,
so the next seed's Differ reaches rule 3 (in sync) before it ever gets to
rule 2, and reports `unchanged` instead of `protected`. Both outcomes are
safe and neither writes content; the weaker setup just can't demonstrate the
protection property, because the surviving hash makes the row look
already-in-sync rather than never-seeded. A faithful rehearsal of a legacy
page has to delete `_pediment_seed_hash` and `_pediment_seed_source` too, not
just the identity key.

### The wp-admin path

Admin-only hosting has no WP-CLI, so Settings → Pediment Theme → Seeding
carries the identical claim under two buttons beneath "Claim existing
content": **Preview claim** and **Claim content**
(`plugin/inc/seeding-admin.php`). Preview first, the same way "Apply plan"
below has no confirmation step — **Claim content** runs on click.

## The wp-admin tab

Settings → Pediment Theme → Seeding runs the identical `Runner` WP-CLI does —
one code path, not two — with PHP's time limit and memory limit lifted first
(`set_time_limit(0)`, `wp_raise_memory_limit('admin')`), because a large site's
seed can generate hundreds of rows and image sizes, and the same code silently
hitting the default PHP limits in wp-admin used to die with a generic critical
error instead of the report the CLI gets. **"Apply plan" runs immediately on
click** — there is no second confirmation step, so preview first on anything
you're not sure about. An expired nonce or a submission from a user without
`manage_options` is rejected with an explicit, visible notice rather than
silently doing nothing.

## Failure modes

### Duplicate seed key

Two shapes:

- **In the manifest itself** — the same key declared twice (e.g. under both
  `pages` and `posts`) fails validation before the run even loads a plan:
  `Duplicate seed key 'about' (declared more than once across
  pages/posts/entries).`
- **In the database** — two existing rows carry the same
  `_pediment_seed_key`. This blocks the whole run (`Plan::hasErrors()`), not
  just that one entry:
  `Seed key "about" is carried by 2 posts (IDs 12, 47). Identity must be
  unique — delete or re-key the extras before seeding.`

  Fix: decide which row is canonical, delete or re-key (edit its
  `_pediment_seed_key` meta, or delete the post) the extra, then re-run.
  Media and navs report the identical shape for their own kinds
  (`media.<key> is carried by N attachments…`, `navs.<key> is carried by N
  navigation entities…`).

  A specific route into the nav case: removing a configured language in
  Polylang deletes that language's term relationship outright
  (`Languages::delete()`), so a nav that was seeded in the removed language
  becomes byte-identical, database-wise, to a legacy nav that was never
  language-tagged — both resolve to the default-language bucket
  (`NavSeeder::languageOf()`). The next seed then collides the orphan with
  the real default-language nav and reports, verbatim
  (`NavSeeder::duplicates()`):

  `navs.primary is carried by 2 navigation entities (IDs 5, 12). Identity
  must be unique — delete or re-key the extras.`

  This is deliberate, not a regression (see docs/BACKLOG.md): a plan with
  any error writes nothing at all — media, entries, and navs alike
  (`Runner::run()` returns before any of the three `apply()` calls run) — so
  the failure is loud rather than a silent loss of the menu's translation
  link. Fix: delete or re-key the orphaned navigation entity, then re-run.
  Telling the two apart is easy at this point even though no automated rule
  can: the orphan is the one with **no language assigned** — the drop
  deleted its term — while the nav the seeder still manages carries the
  default language. Its slug is also the giveaway, still derived from the
  language you removed (`primary-fr`). Until you resolve it, every
  subsequent `wp pediment seed` on that site is blocked outright, not just
  its nav phase.

A related, always-fatal case: a manifest entry's `post_type` doesn't match
what's already in the database under that key (`post_type` is never rewritten)
— `Seed key "about" is a post in the database but a page in the manifest
(post ID 12). post_type is never rewritten — re-key one of them.` Fix by
re-keying one side; there is no automatic migration between post types.

### Verification problems

Phase 5 re-reads the database after every apply and reports anything it
claims to own that doesn't actually hold — this is deliberately paranoid,
because the incident that justified writing it was a seed run reporting
success while the live header rendered nothing. A `VERIFICATION FAILED`
section names the specific problem per entry (keys are printed in the
`key|language` form, so a multilingual site can tell its five copies of a menu
apart), e.g.:

- `home: is declared front page but the front page setting points elsewhere.`
- `about: slug is "about-2" but the manifest says "about" (WordPress
  uniquifies colliding slugs).` — see below.
- `guide/pricing: parent "guide" has no post — this entry landed at the site
  root.`
- `about: references media key "hreo", which the manifest does not declare —
  the placeholder was written out unresolved.` — a typo in a
  `{{media_url:…}}` / `{{media_id:…}}` placeholder. Nothing else catches it:
  the media plan has no such key, so the literal sentinel would otherwise be
  written into the page and hashed as if it were correct.
- `navs.primary: stored membership does not match the manifest.`
- `post_types.guide: already registered by something else — the manifest's
  settings (show_in_rest, supports, rewrite) were not applied.`

**The slug-collision case is the one structural rule the seeder cannot force
through.** If another post already occupies the slug the manifest wants,
`wp_unique_post_slug()` silently uniquifies it (`contact` → `contact-2`)
instead of erroring, and Applier / Verifier both report it rather than
retrying forever (a retry would rewrite the row every single run without ever
converging). Fix: free the slug (rename or delete whatever's squatting on it),
or declare a different `slug` in the manifest for this entry, then re-run.

A run's exit reflects both errors and verification problems — `RunResult::ok()`
is false if either is non-empty, and WP-CLI's `wp pediment seed` exits non-zero
in that case even though it may have applied a valid partial write. Reading
the full report matters: "the run continued" and "nothing was applied" are
distinguished in the ERRORS heading, but a `VERIFICATION FAILED` section can
appear on top of either.

### Ambiguous claim

`wp pediment claim` reports `ambiguous`, and writes nothing for that key,
when more than one unclaimed row survives every matching rule in "What can be
claimed" above. For an entry:

`2 unclaimed page posts share the slug "mega-demo" (IDs 10, 32) — claim
nothing until one is deleted or re-slugged.`

A nav reports the same shape for a different reason — the fallback slug match
found more than one unclaimed navigation entity and none of them carries the
derived slug:

`2 unclaimed navigation entities (IDs 4, 9) and none whose slug is "primary"
— re-slug the right one, or claim it by hand.`

Fix: delete or re-slug the extra row — for a nav, re-slug (or re-title, so
the derived slug lands correctly) the one you don't want matched — so exactly
one candidate remains, then re-run `wp pediment claim`. An ambiguous line
never wrote anything, so re-running after the fix is exactly as safe as any
other claim re-run (see "Re-running claim" above).

## Limitations, by design

- **Nav membership is git-owned, not client-editable.** Unlike page content,
  a navigation menu's links are structure: the seeder rewrites the whole
  entity whenever its serialized membership differs from the manifest, so an
  editor who adds, removes, or reorders a link in the Site Editor's
  navigation block will see that change reverted on the next
  `wp pediment seed`. An `entry`-type link the manifest references but that
  has no seeded post yet is reported as unresolved on *every* run (not just
  the one that changes the nav), so a missing link doesn't silently disappear
  from the report once the nav itself stops needing a rewrite.
- **The site logo is git-owned, not client-editable.** Like nav membership,
  `site.logo` is re-asserted whenever `custom_logo` differs from the manifest's
  media key, so a client who picks a different logo in the Customizer will see
  it reverted on the next `wp pediment seed`. Change the manifest, not the
  Customizer.
- **Terms are create-only.** `wp_set_object_terms()` replaces a taxonomy's
  assignments rather than merging into them; re-applying the manifest's terms
  on every run would strip a category a client added by hand in the editor.
  So terms are assigned once, at creation, and never again — a manifest-side
  term change on an *existing* entry is not enforced. This is a documented
  gap, not an oversight; retagging an existing entry today means editing it by
  hand or re-keying it to force a recreate.
- **`post_author` is `0`** for everything the seeder creates under WP-CLI —
  there's no current user in that context, and the engine doesn't set one.
  If your theme or a plugin displays "by {author}", account for a `0` author
  on seeded content.
- **A dry-run plan is silent about front-page/posts-page drift.** The
  Applier — not the Differ — owns `show_on_front` / `page_on_front` /
  `page_for_posts`, and enforces them unconditionally on every real apply, but
  neither the reassignment nor the fact that one is pending produces a plan
  item, so `--dry-run` cannot tell you "the front page option is about to
  change." Only an actual `wp pediment seed` (or the Verifier, after the
  fact) will show it.
- **A dry-run plan is silent about term drift too, but for a different
  reason: nothing ever fixes it.** Terms are create-only (above) on *every*
  run, dry or real, so a manifest-side term change on an existing entry never
  produces a plan item and never gets applied — `--dry-run` isn't hiding a
  pending write here, there simply isn't one.
- **The wp-admin "Apply plan" button has no confirmation step.** It runs on
  click, same as the CLI without `--dry-run`. Preview first.
- **Media and taxonomies are not translated.** One attachment and one term
  set serve every language — `wp pediment languages` locks Polylang's
  `media_support` off and `taxonomies` to `[]` for exactly this reason
  (`PolylangSetup::configure()`). `MediaMap` keys media globally, and the
  engine's terms are create-only (above); per-language copies of either would
  have nothing to reconcile them against and would drift on the first edit.
- **Only Polylang is implemented.** Everything Polylang-specific lives behind
  the `LanguageProvider` interface (`plugin/src/Language/LanguageProvider.php`)
  — `PolylangProvider`, `PolylangSetup`, and the two files under `plugin/inc/`
  that touch the front end are the only code that may call a `pll_*`
  function. That seam is where a WPML adapter would go; nothing in the
  seeding engine itself assumes Polylang.
- **Translation *content* is not generated.** The seeder never writes prose —
  it resolves what the manifest and pattern files already declare, reports
  what's missing in the `TRANSLATIONS` section of a dry-run plan, and
  `wp pediment adopt <key> --language=<code>` is how an editor's translation,
  once written in the block editor, comes back into git as a pattern file.
