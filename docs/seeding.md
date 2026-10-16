# Seeding

The plugin ships a declarative seeding engine: a client theme declares the pages,
posts, media, navigation, and custom post types a site needs in a single
`seed/manifest.php` file, and `wp pediment seed` creates whatever of it the
site does not have yet.
Content itself (block markup) lives in the theme's `patterns/` directory, not in
the manifest — the manifest is structure, patterns are content.

This is the reference for a client-theme author who has never touched the
engine: what you declare, what the seeder creates, why it never changes a page,
menu or setting that already exists, and what to do when a run reports a
problem.
Every snippet below is copied from the plugin's own reference fixture,
[`tests/fixtures/client-theme/seed/manifest.php`](../tests/fixtures/client-theme/seed/manifest.php)
— the file the e2e suite seeds and asserts against on every CI run.

## Bootstrap only, in one paragraph

The seeder bootstraps a site; it does not maintain one. On a fresh site,
`wp pediment seed` creates every page, post, menu and media file the manifest
declares, sets the front page, the posts page and the site logo, and gives
every row its identity (`_pediment_seed_key`). On a site that is already
seeded, it creates only what is missing — a page or a language the manifest
added, a menu or a media file that does not exist yet — and never changes a
row that exists: not its title, content, slug, parent, order or status, not a
menu's links, not a translation, reading setting or logo a person chose. (It
does tag a row that has no language yet, and fills a reading setting or logo
that is empty — see "On a site that is already seeded".) A live site
belongs to the people and the AI agent who edit it: pages change in the block
editor or through the Pediment MCP tools ([mcp.md](mcp.md)), never through a
re-seed. Pattern files stay in the theme for one job: building the same pages
in a new environment.

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
| `slug` | no | the last `/`-segment of the key | Must already be a valid slug (`sanitize_title($slug) === $slug`) and non-empty, so a key may not end in `/`. Used when the entry is created; a later change on the site stays. |
| `parent` | no | none | Another entry's key. Must be declared; parent cycles are rejected at load time. |
| `front_page` | no | `false` | At most one entry site-wide may set this. |
| `posts_page` | no | `false` | At most one entry site-wide may set this. |
| `menu_order` | no | `0` | Used when the entry is created. |
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
(e.g. its own write failed earlier in the same run) is reported, and the menu
is not created until every link resolves — a menu that quietly comes out one
item short is worse than one that visibly fails.

The `navs` section describes a menu at the moment it is created. After that
the menu is edited in the Site Editor, and a change to `navs` reaches only
environments where that menu does not exist yet.

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
  menu is not created yet.

#### Mega menus

An item may declare `mega`, which serializes as a self-closing
`pediment/mega-menu` block — the panel of icon-and-description link columns
the header renders for it:

```php
array(
	'mega' => array(
		'label'   => 'Products',
		'columns' => array(
			array(
				'heading' => 'Banking',
				'icon'    => 'bank', // optional pediment icon slug
				'links'   => array(
					array( 'entry' => 'features', 'description' => 'Everyday account' ),
					array( 'label' => 'Savings', 'url' => '/savings/', 'description' => 'Earn more' ),
				),
			),
		),
	),
),
```

Each link follows the same rule as a top-level item — `entry` (resolved to the
live per-language permalink, label falling back to the post title) or
`url` + `label` — plus an optional `description`. A `mega` item is a leaf: it
may not declare `children`, may not appear inside `children`, and may not
combine with the other item types. All of it is validated at parse time with
exact paths (`navs.primary.items.1.mega.columns.0.links.2`).

Like every menu, a mega menu is written once, when its menu is created. After
that its links, labels and descriptions are edited in the Site Editor, and the
seeder never touches them again.

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

Names a `media` key; must already be declared there. That attachment becomes
the site's custom logo (`theme_mod: custom_logo`) while the site has no logo
(`0`, or a file that no longer exists or is in the trash). A logo a person
chose later stays, even when the seeder uploads the seeded logo again.

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
other slug collision — and `Verifier::verify()` reports a mismatch on the run
that creates the page (see "Failure modes" below).
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

Notices are about pages the run creates. A page that already exists is never
written again, so its translation gaps are not reported; translate it on the
site.

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

## On a site that is already seeded

Every run, for every row the manifest declares:

- **Missing** (no row carries its key) → `create`, with its language, terms,
  slug, parent and menu order. A new child finds a parent that already exists.
- **Exists** → `unchanged`. Title, content, slug, parent, menu order, status
  and terms stay as they are, however far they are from the manifest.
- **In the trash** → `unchanged`, with a note. The seeder neither restores it
  nor creates a second row under the same key. Restore it yourself, or empty
  the trash: once the row is gone for good, the next seed creates it again.
- **Not declared any more** → `orphan`, left in place.

Menus and media follow the same rule. A menu that is not published gets a
note too, because it does not render, and a media file in the trash stays
there: delete it permanently and the next seed uploads it again.

A menu the run creates is the one place the trash matters to a link. A page in
the trash is left out of the new menu, and the run says so in a `MENUS`
section (next to `TRANSLATIONS`, in the text report of a real run):

```
MENUS
  - menu primary|de: contact is in the trash, left out
```

Each line names the menu as `key|language`, so a five-language site can tell
its copies apart (on a monolingual site the language is empty: `primary|`).

It is a notice, not an error: the run stays clean, and the menu is edited in
the Site Editor like any other. A page that has no post at all yet (its own
create failed) is different: the menu is not created, and the run reports an
error.

Three things the seeder still does on a site that is already seeded, because
they are identity, not content:

- **Language repair.** A row with no language at all (a page that predates the
  site's languages) is tagged with the language it was found under. A row that
  already has a language is never moved.
- **Translation groups.** When a run creates or language-tags at least one row
  of a seed key, it links every language of that key into one translation
  group. So a language the manifest adds later creates its own pages and
  joins the groups that already exist. A key whose rows all exist and keep
  their language is left alone: a translation a person linked by hand (or
  through `pediment-write-page` with `translation_of`) stays linked. A row in
  the trash is never linked into a group, and a group short of one configured
  language is left as it is. Menus follow the same rule.
- **Empty settings.** The front page, the posts page and the site logo are set
  only while the site has none: `page_on_front`, `page_for_posts` or
  `custom_logo` is `0`, or names a post or file that no longer exists or is in
  the trash. A choice a person made stays, even when they deleted the seeded
  page or logo for good and the next seed creates it again. A page in the
  trash is never made the front page or the posts page, and an attachment in
  the trash is never made the logo.

The header template part is not part of the seeder: plugin activation and a
theme switch create it once (`plugin/inc/bootstrap.php`), and `wp pediment
seed` never touches it.

Patterns are read only for rows the run creates. A theme may delete the
pattern file of a page that now lives only on the site; the next seed does not
need it. A *new* entry whose pattern is not registered still stops the run.

## Identity

`_pediment_seed_key` (`Pediment\Seeder\Meta::KEY`) is the only meta the
seeder writes. It is the stable identity: the engine never looks seeded content
up by slug or title, and duplicate keys are the one thing it treats as fatal
(below).

Pediment 3.x also stored two content hashes (`_pediment_seed_hash`,
`_pediment_seed_source`) and a mega-menu hash (`_pediment_seed_mega_hash`) to
decide when a re-seed could overwrite a page. 4.0 removes them: on the first
admin request after the update, `Pediment\Upgrade\SeedHashCleanup` deletes
all three from every post, once.

`StateReader::read()` only sees posts that already carry a key, so on a site
whose content predates the engine a first seed plans a `create` for every
entry and duplicates the site. `wp pediment claim` (below) gives that content
its keys first.

## Reading a plan (`wp pediment seed --dry-run`)

A seeded site where nothing is missing:

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

0 to write, 0 orphan, 13 unchanged. Nothing was written (--dry-run).
```

That's the state every commit to a client theme's `seed/manifest.php` /
`patterns/` should converge to before merging: `0 to write`. `docs/STANDARDS.md`
makes this a requirement, and the seed-check action in client CI greps for
`0 to write,` — the words and the comma are a contract.

A page a person rewrote, renamed and moved still reads `unchanged`: the plan
does not compare a row that exists with the manifest at all, so a dry run
cannot answer "has anyone edited this page?".

A manifest that added a page (`team`), on a site where someone trashed
`contact`:

```
PAGES & POSTS
  unchanged   home
  unchanged   about
  unchanged   contact          in the trash — left alone; restore it in the editor, or empty the trash and the next seed creates it again
  create      team             title=Team; content -> 11 bytes; slug=team; parent_key=; status=publish; menu_order=0

1 to write, 0 orphan, 13 unchanged.
```

Rows the manifest no longer declares, but that still carry a seed key, are
listed as `orphan` and left in place — nothing ever deletes a post on your
behalf.

## `wp pediment seed`

```
wp pediment seed [--dry-run] [--format=<format>]
```

Runs the same five phases WP-CLI and wp-admin both go through: resolve desired
state → resolve actual state → diff into a plan → apply → verify. `--dry-run`
stops after the plan and writes nothing. `--format=json` emits the same information
machine-readably (`applied`, `ok`, `counts`, one object per plan item,
`errors`, `problems`) instead of the text report above. Since 4.0, `counts`
has exactly the keys `create`, `unchanged` and `orphan`, and an item has no
`protected` field.

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

Exports a live entry's current block markup into its `patterns/<slug>.php`
file. Use it when a page was built or changed on the site and a new
environment (a fresh local wp-env, CI, a staging site) should get the same
page from its first seed. It writes the file only — no post meta, no post —
and the next seed on the site it came from still plans `unchanged`.
Concretely, it:

- refuses to run against an entry declared with literal `content` — only
  `pattern`-backed entries have a file to adopt into;
- converts attachment URLs and numeric attachment IDs it recognizes back into
  `{{media_url:<key>}}` / `{{media_id:<key>}}` placeholders, so the committed
  file doesn't carry an environment-specific URL;
- preserves an existing pattern file's header (Description, Keywords, etc.) if
  one is already there, rather than regenerating a bare one;
- keeps a `.bak` sibling when it's about to overwrite a file whose contents
  differ from what it's about to write;
- reads the written `Slug:` header back the way the pattern registry will, and
  rolls back to the backup (or deletes the new file) if it does not match the
  manifest — a mismatched header would mean a seed in a new environment cannot
  find the pattern.

**Adopt takes the body into git, never the title.** The manifest is a
hand-edited file and the engine does not rewrite it, so adopting a page a
client renamed prints a warning naming both titles and leaves the manifest
alone. Update the manifest by hand if new environments should get the new
name.

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

That's the only thing it writes. The seed after a claim finds every claimed
row by its key and plans it `unchanged`: content, title, slug and status stay
exactly as they were. Claimed menus are left as they are too, and a claimed
attachment is never uploaded again (see [Media](#media) below).

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
languages afterwards, seed, and each of those unclaimed pages has no key, so
the seed creates it again alongside the live one — the exact duplication a
claim exists to prevent.

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

> **Check every nav line in a claim preview by hand.** On a live site whose
> only Site-Editor menu is, say, a footer menu, one declared nav plus one
> unclaimed `wp_navigation` is enough: the claim keys that footer menu as
> `primary` without comparing slug or title. The seed after it does not
> rewrite a claimed menu's links, but the header's ref-less navigation block
> binds to whichever menu is keyed `primary` (`pediment_seeded_nav_id()`), so
> the footer menu would render in the header. The rule is deliberate — legacy
> menus carry slugs like `primary-2` that cannot slug-match, which is the case
> it was built for — so the safeguard is you, reading the
> `navigation "<title>" (ID <n>)` note in `wp pediment claim --dry-run` and
> confirming it names the menu you meant.

### Media

Without a media claim, the seed after a claim plans a `create` for every
`media` entry, and applying it uploads every image a second time. So
`Claimer::planMedia()` matches media too, by **file name**: the basename of
the manifest `file` against the name an attachment was uploaded under.

- **The names an attachment answers to.** The basename of its
  `_wp_attached_file`, and, when WordPress stored a `-scaled` (or `-rotated`)
  copy of a big upload, the uploaded original's name from the metadata's
  `original_image` (the file `wp_get_original_image_path()` resolves). So
  `seed/media/team.jpg` matches an attachment stored as
  `2026/06/team-scaled.jpg`.
- **How names compare.** Both sides go through `sanitize_file_name()` (what
  an upload does to a name) and lower case: `Team Photo.JPG` matches
  `team-photo.jpg`. The folder never counts: `2026/05/hero.jpg` and
  `2026/06/hero.jpg` are both `hero.jpg`.
- **Numbered copies never match.** When WordPress uploads a file whose name
  is taken, it stores `hero-1.jpg`, `hero-2.jpg`; those are other names and
  never match `hero.jpg`.
- **Never trash, never keyed.** Only `inherit` and `private` attachments
  are candidates (`Claimer::STATUSES_MEDIA`), and only those that carry no
  `_pediment_seed_key`. `MediaSeeder` finds a key on `inherit`, `private`
  and `trash` attachments, so a claimed attachment is never uploaded again.
  A media key that an attachment already carries (trash included, the same
  lookup `MediaSeeder` uses) is absent from the plan.
- **One attachment, one key.** An attachment claimed for one media key in a
  run is not a candidate for a later key in the same run.
- **No language.** Media identity has no language (`PolylangSetup` keeps
  Polylang's `media_support` off), so each media key is planned once, with no
  language code, also on a multilingual site.

Exactly one candidate is claimed: the claim writes `_pediment_seed_key` =
the media key, which is exactly the identity `MediaSeeder` gives an
attachment it uploads, and nothing else (no file, title or metadata change).
None is `no-match`, and the next seed uploads the file. Two or more is
`ambiguous` and nothing is written. The line lists each candidate's ID and
stored file. Do not delete one at random: page content often points at an
attachment by ID, so removing the one in use breaks live images. Find the one
your pages use and keep it. Then make the other one stop matching, for
example by renaming its file (WP-CLI, SFTP or a media-rename plugin; the
Media Library itself cannot rename a file), or remove it only if nothing
uses it. Then claim again.

A `claim` line names the attachment's stored file (`_wp_attached_file`, for
example `2026/06/team-scaled.jpg`), so you can find it in the Media Library.
Read every MEDIA line of `wp pediment claim --dry-run` (or **Preview claim**)
before you apply.

After the claim, `wp pediment seed --dry-run` plans every claimed media key
`unchanged`, with 0 media creates. From the fixture site, with the seed key
removed from the logo attachment first:

```
$ wp pediment seed --dry-run
MEDIA
  create      logo             file=logo.svg
1 to write, 0 orphan, 24 unchanged. Nothing was written (--dry-run).

$ wp pediment claim
MEDIA
  claim      logo             attachment "2026/10/logo.svg" (ID 5)
1 to claim, 0 without a match, 0 ambiguous.
Success: Claim applied.

$ wp pediment seed --dry-run
MEDIA
  unchanged   logo
0 to write, 0 orphan, 25 unchanged. Nothing was written (--dry-run).
```

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

### Worked example: claim, then seed

One step past the claim's own report: the next seed's dry run finds the
claimed rows by their keys and plans them `unchanged`. The shape for three
claimed pages (`about`, `contact`, `blog`) on the fixture site:

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
  ... (remaining unchanged rows elided)

0 to write, 0 orphan, 13 unchanged. Nothing was written (--dry-run).
```

A seed never writes a claimed page, so its live content, title and slug are
safe from the first run on. Anything the claim reported `no-match` is created
by the next seed — read those lines before you apply.

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
owns that doesn't actually hold (seed keys, languages and translation groups,
media, post types, and for rows this run created also slug, parent, reading
settings and menus). This is deliberately paranoid,
because the incident that justified writing it was a seed run reporting
success while the live header rendered nothing. A `VERIFICATION FAILED`
section names the specific problem per entry (keys are printed in the
`key|language` form, so a multilingual site can tell its five copies of a menu
apart), e.g.:

- `home: is declared front page but the front page setting was not set.`
  (A front page a person chose stays, and is not a problem.)
- `about: slug is "about-2" but the manifest says "about" (WordPress
  uniquifies colliding slugs).` — see below.
- `guide/pricing: parent "guide" has no post — this entry landed at the site
  root.`
- `about: references media key "hreo", which the manifest does not declare —
  the placeholder was written out unresolved.` — a typo in a
  `{{media_url:…}}` / `{{media_id:…}}` placeholder. Nothing else catches it:
  the media plan has no such key. Reported on the run that creates the page;
  fix the live page in the editor, and the pattern file for new environments.
- `navs.primary: stored membership does not match the manifest.` — for a menu
  this run created.
- `post_types.guide: already registered by something else — the manifest's
  settings (show_in_rest, supports, rewrite) were not applied.`

Slug, parent, trash, the front-page and posts-page settings, and a menu's
links and status are checked only for rows this run created. On a row that
existed before the run they are a person's decision, not a problem.

**A slug collision is the one thing a create cannot force through.** If
another post already occupies the slug the manifest wants,
`wp_unique_post_slug()` silently uniquifies it (`contact` → `contact-2`)
instead of erroring, and Applier and Verifier both report it on the run that
creates the page. Later runs leave the page alone. Fix: rename the new page on
the site (or free the slug and rename it), then declare the slug you want in
the manifest so new environments match.

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

- **The manifest describes a site at creation time.** A change to an
  existing page's title, slug, parent or content in the manifest or a pattern
  file never reaches a site where that page exists. Change the live page in
  the editor or through the MCP tools, and run `wp pediment adopt` if new
  environments should match.
  For plugin developers: an already seeded wp-env database no longer picks up
  pattern changes through `wp pediment seed`. Run `npx wp-env clean all` and
  start wp-env again to re-seed from scratch.
- **Menus are written once.** A change to `navs` reaches only environments
  where that menu does not exist yet. A page the manifest adds later is
  created, but it is not added to an existing menu — add it in the Site
  Editor.
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
- **A dry-run plan is silent about the reading settings and the logo.** They
  are set only while the site has none, and neither produces a plan item.
- **A dry-run plan is silent about term drift too.** Terms are create-only
  (above), so a manifest-side term change on an existing entry never produces
  a plan item and never gets applied.
- **The wp-admin "Apply plan" button has no confirmation step.** It runs on
  click, same as the CLI without `--dry-run`. Preview first.
- **Media and taxonomies are not translated.** One attachment and one term
  set serve every language — `wp pediment languages` locks Polylang's
  `media_support` off and `taxonomies` to `[]` for exactly this reason
  (`PolylangSetup::configure()`). `MediaMap` keys media globally, and the
  engine's terms are create-only (above); per-language copies of either would
  have nothing to reconcile them against and would drift on the first edit.
- **Both Polylang and WPML are implemented.** Everything plugin-specific lives
  behind the `LanguageProvider` / `LanguageSetup` seam
  (`plugin/src/Language/LanguageProvider.php`,
  `plugin/src/Language/LanguageSetup.php`) — `PolylangProvider`,
  `PolylangSetup`, and the two files under `plugin/inc/` that touch the front
  end are the only code that may call a `pll_*` function; `WpmlProvider`,
  `WpmlSetup`, and `plugin/inc/wpml-compat.php` are the only code that may
  call a `wpml_*`/`icl_*` function or read an `ICL_*` constant. Nothing in the
  seeding engine itself assumes either plugin. `LanguageRegistry` detects
  which one is active — Polylang first, then WPML, then monolingual
  (`NullProvider`/`PolylangSetup`'s own "not active" error) — so a site
  running neither still seeds successfully with no language behaviour.
  `wp pediment languages` configures whichever plugin is detected the same
  way it always configured Polylang; per-language nav menu binding and the
  front-end language switcher both go through the active provider, so a WPML
  site gets WPML's own native `wpml/language-switcher` block seeded into its
  navigation instead of Polylang's.
- **Translation *content* is not generated.** The seeder never writes prose —
  it resolves what the manifest and pattern files already declare, reports
  what's missing in the `TRANSLATIONS` section of a dry-run plan, and
  `wp pediment adopt <key> --language=<code>` is how an editor's translation,
  once written in the block editor, comes back into git as a pattern file.
