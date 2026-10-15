# Converting legacy blocks

Pediment 4 replaces 18 presentation blocks (`pediment/hero`, `cta`,
`feature-grid`, `faq` and the others in `Policy::LEGACY`) with core blocks and
named styles. The converter rewrites stored content. The legacy blocks still
render, but the block inserter no longer offers them, and Pediment 5 deletes
them.

## What the converter does

Each legacy block becomes a fixed tree of core blocks that uses the plugin's
default styles (see [design-layer.md](design-layer.md)):

| Legacy block | Becomes |
| --- | --- |
| `section-head` | constrained group (preset bottom margin) → `eyebrow` paragraph, heading, `lead` paragraph |
| `hero` default / centered | section group → h1, `lead` paragraph, `pill` button |
| `hero` media-bg | `media-card` cover (the image, primary overlay) → h1, `lead`, `pill` button |
| `hero` stat-card | columns: copy, `pill` + `pill-ghost` buttons, ticks list · `media-card` cover with a `card` group that holds a `stat` group and the metrics |
| `cta` | `panel` section group → h2, paragraph, `pill` + `pill-ghost` buttons |
| `feature-grid` + `feature` | grid group (up to 3 columns, 2 for four cards) of `card` groups → image, h3, text, link |
| `stat-grid` + `stat` | grid group (one column per stat, up to 4) of `stat` groups → value, label, context |
| `steps` + `step` | `steps` grid group (up to 3 columns, 2 for four steps; numbers the steps) of groups → h3, text |
| `media-text` | `media-row` media & text block; inner blocks move unchanged |
| `testimonial-grid` + `testimonial` | grid group (up to 2 columns) of `quote-card` groups → quote with the byline, or a rounded avatar and byline |
| `logo-cloud` | `logos` section group → caption, a wrapping row with the logo images |
| `pull-quote` | `pull-quote` quote |
| `image-caption` | `framed` image with its caption |
| `prose` | `prose` constrained group; inner blocks move unchanged |
| `faq` + `faq-item` | vertical group of `accordion` details blocks |

Grids collapse on phones. The converter writes `minimumColumnWidth` as
`max(<floor>, <90/N>%)` and no `columnCount`: WordPress 6.9 ignores the
minimum when `columnCount` is set, and the grid would never collapse. On wide
screens the grid holds N columns; below about N times the floor it drops to
fewer columns, down to one.

A child block outside its parent (a lone FAQ item, stat, step, feature or
testimonial) converts on its own. A legacy block inside another block, at any
depth, converts too. A block that rendered nothing (a FAQ item without
question and answer, an image caption whose image is gone) is removed, and the
report says so.

Some things have no core equivalent and are dropped:

- Feature icons. The report names each one per page ("pediment/feature: icon
  dropped …"). A feature with an image keeps the image.
- Hero tick icons and the FAQ toggle icon. The new blocks use the default list
  bullet and the details arrow. The report does not name these.
- Hover effects (card lift, link arrow).
- `maxWidth` of a section head. The report names it.
- An `is-style-*` class on a legacy block that is not registered for the new
  block. The report names it ("dropped style is-style-align-center on
  pediment/feature → core/group"). A client theme can register that style for
  the new block before the conversion; then the class stays.

Plain classes (for example `guide-cards`), the list-view name and the block
lock move to the new outer block. A client theme that styled the legacy
`starter-*` classes needs matching styles for the new blocks.

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
  unless the agent sets it to false. See [mcp.md](mcp.md).
- **WP-CLI** (dev environments):

      wp pediment convert --dry-run
      wp pediment convert
      wp pediment convert 12 14 --dry-run
      wp pediment convert --format=json

The converter looks at pages, posts, synced patterns (`wp_block`) and
templates and template parts that are stored in the database, in every
language. It does not change template files in a theme folder.

## Results

Each post gets a status:

| Status | Meaning |
| --- | --- |
| `preview` | Dry run: the post would be converted. |
| `converted` | Written. `restoreRevision` holds the old content. |
| `unchanged` | No legacy block to convert. |
| `failed` | Nothing written (one exception below). `errors` says why. |
| `skipped` | Not a page, post, synced pattern or template, or you may not edit it. |

A conversion is refused (`failed`, nothing written, in a dry run too) when:

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
editor's Revisions screen.

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
the block validator on WordPress 6.9 and 7.0 (CI job `validator`). The
mappers read parsed markup only, so they keep working after Pediment 5
deletes the legacy block registrations.

`PostConverter` is the one service behind the ability, the wp-admin tab and
WP-CLI; `PatternFiles` handles pattern files. The end-to-end check is
`plugin/tests/e2e/convert-legacy.spec.ts`.
