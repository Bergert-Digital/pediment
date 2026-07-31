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

Because rule 2 also fires for a row the engine has simply never touched yet
(no `_pediment_seed_hash` at all), running `wp pediment seed` for the first
time against an already-live site is safe — every existing page is treated as
"edited" and only gets structure applied, never a silent content overwrite.

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
