# Converting legacy blocks

Pediment 4 replaced 18 presentation blocks (`pediment/hero`, `cta`,
`feature-grid`, `faq` and the others in `Policy::LEGACY`) with core blocks and
named styles. Pediment 5 removed the 18 blocks. A post that still holds one
shows it empty or unstyled, and administrators see a notice that links to the
Convert tab. The converter rewrites stored content. It stays until Pediment 6,
so a site can also convert after the update to 5.0.

## What the converter does

Each legacy block becomes a fixed tree of core blocks that uses the plugin's
default styles (see [design-layer.md](design-layer.md)):

| Legacy block | Becomes |
| --- | --- |
| `section-head` | constrained group with the plain class `pediment-section-head` (preset bottom margin) → `eyebrow` paragraph, heading, `lead` paragraph |
| `hero` default / centered | section group → h1, `lead` paragraph, `pill` button |
| `hero` media-bg | `media-card` cover (the image, primary overlay) → h1, `lead`, `pill` button |
| `hero` stat-card | columns: copy, `pill` + `pill-ghost` buttons, ticks list · `media-card` cover with a `card` group that holds a `stat` group and the metrics |
| `cta` | `panel` section group → h2, paragraph, `pill` + `pill-ghost` buttons |
| `feature-grid` + `feature` | grid group (up to 3 columns, 2 for four cards) of `card` groups → image or `tile` icon (`pediment/icon`), h3, text, link paragraph with the plain class `pediment-card-link` |
| `stat-grid` + `stat` | grid group (one column per stat, up to 4) of `stat` groups → value, label, context (only the parts that are set) |
| `steps` + `step` | `steps` grid group (up to 3 columns, 2 for four steps; numbers the steps) of groups → h3, text |
| `media-text` | `media-row` media & text block; inner blocks move unchanged |
| `testimonial-grid` + `testimonial` | grid group (up to 2 columns) of `quote-card` groups → quote with the byline, or a rounded avatar and byline |
| `logo-cloud` | `logos` section group → caption, a wrapping row with the logo images |
| `pull-quote` | `pull-quote` quote |
| `image-caption` | `framed` image with its caption |
| `prose` | `prose` constrained group; inner blocks move unchanged |
| `faq` + `faq-item` | vertical group of `accordion` details blocks; an answer made of `<p>` elements gives one paragraph per element |
| `testimonial` quote | a quote made of `<p>` elements gives one paragraph per element |

Grids collapse on phones. The converter writes `minimumColumnWidth` as
`max(<floor>, <90/N>%)` and no `columnCount`: WordPress 6.9 ignores the
minimum when `columnCount` is set, and the grid would never collapse. On wide
screens the grid holds N columns; below about N times the floor it drops to
fewer columns, down to one.

The plain classes `pediment-section-head` and `pediment-card-link` let a
client theme select a converted section head and a card's link paragraph
without a structural selector such as `> p:last-child`.

The `card` style centres the card's content. The converter writes no
alignment on the card's heading, paragraphs or image, so a client theme's
`card` style with `"typography": { "textAlign": "left" }` left-aligns the
whole card, the icon and the image included. The centring is the style
property `typography.textAlign`, and theme partials merge property by
property: a theme `card` partial keeps the centring unless it sets
`typography.textAlign` itself, even when it has its own `css`. The hero
stat card is a `card` group too: its `stat` group is centred, and each metric
paragraph is aligned left (value over label), as the legacy block rendered it.

A feature's icon becomes a `pediment/icon` block in the `tile` style, the
first block of the card. A feature with an image keeps the image and gets no
icon, as the legacy block rendered it. A feature without an `icon` attribute
gets `trend-up`, the legacy default; a feature with an empty icon gets none.
An icon that is not in the icon set stays in the block, and the report names
it ("pediment/feature: icon … is not in the icon set, the card shows no
icon"). A client theme restyles the tile with its own `tile` style for
`pediment/icon` (see [design-layer.md](design-layer.md)).

A child block outside its parent (a lone FAQ item, stat, step, feature or
testimonial) converts on its own. A legacy block inside another block, at any
depth, converts too. A block that rendered nothing (a FAQ item without
question and answer, an image caption whose image is gone, a stat-card hero
with no text, stat and image) is removed, and the report says so.

The `stat` style styles a stat's children by position: number, label,
context. A stat without a value or without a label gets no empty paragraph;
the label or context that is not in its position carries the class
`pediment-stat-label` or `pediment-stat-context`, which the style reads.

A hero `variant` that the site does not register converts as the default
variant, as the legacy block rendered it. When a client theme registers a
variant with the `pediment_hero_variants` filter and the converter has no
mapping for it, the hero converts as the default variant too, and the report
says so ("pediment/hero: variant … has no mapping, converted as default").
The `pediment_hero_variants()` function and its filter stay in Pediment 5
only for the converter, which reads them. The block editor no longer does.
They go in Pediment 6, together with the converter.

Some things have no core equivalent and are dropped:

- Hero tick icons and the FAQ toggle icon. The new blocks use the default list
  bullet and the details arrow. The report does not name these.
- Hover effects (card lift, link arrow).
- The initials badge of a testimonial without an avatar. The byline keeps
  the name. The report names it ("pediment/testimonial: initials badge
  dropped …").
- `maxWidth` of a section head. The report names it.
- An `is-style-*` class on a legacy block that is not registered for the new
  block. The report names it ("dropped style is-style-align-center on
  pediment/feature → core/group"). A client theme can register that style for
  the new block before the conversion; then the class stays, after the
  converter's own style class. The block then has two `is-style-*` classes
  (for example `is-style-card is-style-align-center`). Keep both: each one
  carries part of the look.
- Block bindings (`metadata.bindings`). They name legacy attributes. The
  report says so.

Plain classes (for example `guide-cards`), the block lock and the block
metadata (the list-view name, the "Hide" setting `blockVisibility`, a pattern
name) move to the new outer block, so a hidden block stays hidden. A client
theme that styled the legacy `starter-*` classes needs matching styles for
the new blocks.

The scroll-in animation stays: `reveal.js` animates elements with the
`card`, `stat`, `accordion`, `media-card` and `panel` styles, the children of a
`steps` group and the images in a `logos` group, as it animated the legacy
feature cards, steps, stats, logos, FAQ items, media cards and CTAs. Since
Pediment 5 it names only these converted styles and the children of a
`starter-band`; the legacy `starter-*` selectors are gone. The converted
hero's children do not animate. Visitors who prefer reduced motion get no
animation.

A post whose text was inside legacy blocks (for example a `prose` block) can
get an automatic excerpt after the conversion. WordPress builds an automatic
excerpt only from some core blocks (paragraphs, headings, lists, quotes and
others), also inside groups and columns (`excerpt_remove_blocks()`). It
skipped the legacy blocks and everything inside them. After the conversion
the text is in core blocks inside a group, so a blog list or search result
that shows excerpts now shows one for that post. Set a manual excerpt if the
automatic one is wrong.

## Before you convert production

The new blocks look like the plugin's default styles, not like the client
theme's styles for the legacy blocks. Convert a site in this order:

1. Convert a dev copy of the site (WP-CLI or the wp-admin tab).
2. Where the client theme styled a legacy block (`starter-*` classes, a
   legacy `is-style-*` class), add client-theme styles for the new blocks.
3. Compare the converted pages with the old ones (screenshots at phone,
   tablet and desktop width). On Pediment 5 the old pages no longer render,
   so take the "before" screenshots on a copy that still runs Pediment 4.
4. Only then convert production through wp-admin.

The wp-admin tab shows this order above the preview.

## Run it

Always preview first. The preview shows every post that holds a legacy block
and a line diff of the change. Nothing is written until you confirm.

- **wp-admin** (production): Settings → Pediment Theme → **Convert legacy
  blocks**. The tab works in batches of 25 posts (filter
  `pediment_convert_batch_size`, for hosts with short request limits). It
  lists the preview of one batch. **Convert N posts** converts exactly the
  posts in that preview, then shows the result. **Next batch** moves on to the
  next 25 posts (a cursor, so a post that fails does not block the batches
  after it); **Start from the beginning** goes back. You need the
  `manage_options` and `edit_theme_options` capabilities.
- **AI agents**: the `pediment-convert-legacy` MCP tool. `dry_run` is true
  unless the agent sets it to false. With `all: true` one call handles at
  most 25 posts (filter `pediment_convert_batch_size`); `remaining` says how many come after the
  batch, and `after` (a post id) moves past it. See [mcp.md](mcp.md).
- **WP-CLI** (dev environments):

      wp pediment convert --dry-run
      wp pediment convert
      wp pediment convert 12 14 --dry-run
      wp pediment convert --format=json

The converter looks at pages, posts, synced patterns (`wp_block`) and
templates and template parts that are stored in the database, in every
language, with the status `publish`, `draft`, `pending`, `private` or
`future`. It does not look at trashed posts, auto-drafts or revisions, and it
does not change template files in a theme folder.

WP-CLI exits with an error after the report when a post failed (and, with
`--patterns`, when a file failed).

## Results

Each post gets a status:

| Status | Meaning |
| --- | --- |
| `preview` | Dry run: the post would be converted. |
| `converted` | Written. `restoreRevision` holds the old content. |
| `unchanged` | No legacy block to convert. |
| `failed` | Nothing written (one exception below). `errors` says why. |
| `skipped` | Not a page, post, synced pattern or template, or you may not edit it (then the report does not show its title). |

Each report also lists `converted` (the legacy block names it replaced),
`unmapped` (legacy block names it left in place because no mapper exists;
every one of the 18 blocks has a mapper, so this list is normally empty) and
`notes`.

A conversion is refused (`failed`, nothing written, in a dry run too) when:

- A legacy container (`prose`, `faq`, `feature-grid`, `stat-grid`, `steps`,
  `testimonial-grid`, `logo-cloud`, `media-text`) holds HTML outside its
  inner blocks, for example HTML written in the code editor. The legacy block
  showed that HTML; the new block would drop it. The error quotes the start
  of the HTML ("pediment/prose holds HTML outside blocks; edit it into blocks
  first (…)"). Move the HTML into blocks, then convert.
- A FAQ answer or a testimonial quote holds block HTML other than paragraphs
  (a list, a heading, a table, …). Paragraph blocks cannot hold it. Edit the
  answer or quote by hand first.
- A legacy block comment does not parse (for example its attributes are
  written by inline PHP), so the block would stay legacy after the
  conversion.
- The new content would break a content rule that the old content did not
  break. Example: the text `javascript:` in a legacy attribute was hidden in
  the block comment and becomes visible text. Problems that were already in
  the page (`existingErrors`) are reported and do not stop the conversion.
- Revisions are disabled for the post, or limited to fewer than 2
  (`WP_POST_REVISIONS`). The converter needs a backup revision.
- Saving as the current user would strip content outside the converted blocks.
  Without the `unfiltered_html` capability WordPress runs kses on save, which
  removes things such as iframes. The error quotes the first difference. Ask an
  administrator or a user with `unfiltered_html` to run the conversion.
  WP-CLI is trusted: it has no user, so it suspends kses for the backup and the
  write, the way the seeder does.

`failed` can also mean the post was written but the backup revision was
pruned afterwards (the revision limit). The error says so. The content is
converted, and the old version is only in the diff or a database backup.

Before it writes, the converter saves a revision of the current content. To
undo a page, restore `restoreRevision` with `pediment-rollback-page` or the
editor's Revisions screen. The wp-admin result table links each converted
post to that revision (**Restore**).

## Pattern files

In a client theme repository, convert the pattern files so that new
environments get the same core blocks as production:

    npx wp-env run cli wp pediment convert --patterns=wp-content/themes/<theme>/patterns --dry-run
    npx wp-env run cli wp pediment convert --patterns=wp-content/themes/<theme>/patterns

The command works on the `*.php` files in that directory, and only inside it
(paths are resolved with `realpath`; a symlink that leaves the directory is
skipped).

- A file without a pattern header (a `Title:` or `Slug:` line in the leading
  PHP comment) is skipped.
- The PHP header of each file, inline PHP (`<?php echo … ?>`) and the seeder's
  `{{media_id:…}}` and `{{media_url:…}}` placeholders stay as they are.
- A file whose inline PHP would be lost (for example PHP that prints a number
  into a block attribute) is not written and is listed as `failed`; convert
  that file by hand. A file that holds a number or URL that clashes with a
  media stand-in fails the same way.
- A file whose conversion would add a content-rule error fails too, and so
  does a file the converter refuses for the reasons in [Results](#results)
  (HTML outside blocks in a legacy container, block HTML in a FAQ answer or
  quote, a legacy block comment that does not parse).
- A converted block that needs alt text from a media placeholder cannot look
  it up. The report adds a note ("alt text not resolvable for
  {{media_id:key}}; set it by hand").
- A write goes to a temporary file in the same directory and is renamed over
  the pattern, so an interrupted write cannot cut a file short.
- One failing file does not stop the others. The command exits with an error
  at the end if any file failed.

Run `validate-blocks` on the converted files before you commit.

## For developers

Mappers live in `plugin/src/Convert/Mappers/`, one class per legacy block or
parent/child pair. Each output shape has a fixture pair in
`plugin/tests/phpunit/Convert/fixtures/` (`<name>.input.html` →
`<name>.expected.html`). PHPUnit checks that the converter produces the
expected markup and that it passes the content rules;
`node tools/validate-convert-fixtures.mjs` checks every expected file with
the block validator on WordPress 6.9, 7.0 and 7.1 (CI job `validator`). The
mappers read parsed markup only, so they work without the legacy block
registrations, which Pediment 5 removed. `HeroMapper` reads
`pediment_hero_variants()` (`plugin/inc/hero-variants.php`). The converter,
its mappers, fixtures and tests and that function go in Pediment 6.

`PostConverter` is the one service behind the ability, the wp-admin tab and
WP-CLI; `PatternFiles` handles pattern files. The end-to-end check is
`plugin/tests/e2e/convert-legacy.spec.ts`.
