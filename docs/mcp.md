# Building pages with AI through MCP

Pediment exposes nine tools to AI agents through the WordPress MCP Adapter.
Agents build and change pages on the live site; Pediment enforces the content
and design rules on the server, so a page that breaks a rule is never written.

## Set up a site

1. Install and activate the **MCP Adapter** plugin (Plugins → Add New →
   search "MCP Adapter", or upload `mcp-adapter.0.7.0.zip` from
   `https://downloads.wordpress.org/plugin/mcp-adapter.0.7.0.zip`). Pediment
   needs WordPress 6.9 or later. Without the adapter Pediment still works; the
   MCP server is simply not there.
2. Create an application password: Users → Profile → Application Passwords →
   name it "Claude", copy the password. WordPress only offers application
   passwords over HTTPS (or on a local environment).
3. Connect Claude Code (one line; replace SITE, USER and the password):

       claude mcp add --transport http pediment "https://SITE/wp-json/pediment/v1/mcp" --header "Authorization: Basic $(printf 'USER:APP PASSWORD' | base64)"

   On a site with plain permalinks use `https://SITE/?rest_route=/pediment/v1/mcp`.
   `claude mcp list` shows whether it connected.

The server speaks JSON-RPC over HTTP and authenticates every request with the
`Authorization: Basic` header. It runs as the user who owns the application
password, so that user's capabilities decide what the tools may do.

Local dev: the root wp-env installs the adapter automatically. Read the site
URL with `npx wp-env run cli wp option get siteurl` (the port differs per
workspace) and create a password with
`npx wp-env run cli wp user application-password create admin claude --porcelain`.
`node tools/mcp-smoke.mjs` runs the whole path against the running wp-env: it
lists the tools, writes and checks a page, converts a legacy page, and confirms that a write breaking
two rules comes back as one tool error that names both.

## Tools

| Tool | Does |
| --- | --- |
| `pediment-get-vocabulary` | Tokens, named styles (with layer), allowed blocks, rules, WordPress version, languages. Call it first. |
| `pediment-list-pages` | Pages of every language. Filters: language, parent, status. At most 500 pages; `truncated` is true when more matched. |
| `pediment-read-page` | One page with raw block markup and translations. |
| `pediment-check-page` | Content-rule errors and warnings, blocks the editor reported invalid, legacy blocks still on the page (errors since Pediment 5). |
| `pediment-write-page` | Create (draft by default) or update a page. Rejected with every violation listed if a rule fails. Keeps a revision. |
| `pediment-rollback-page` | Restore one of the page's own revisions. |
| `pediment-upsert-style` | Create or change a site-layer style (see [design-layer.md](design-layer.md)). |
| `pediment-delete-style` | Delete a site-layer style. |
| `pediment-convert-legacy` | Replace legacy Pediment blocks with core blocks and named styles in the given posts (`ids`) or everywhere (`all: true`, 25 posts per call). Dry run unless `dry_run` is false; the dry run returns a diff per post. See [convert.md](convert.md). |

### Permissions

The tool list is fixed: the server exposes these nine abilities and nothing
else. Each tool checks WordPress capabilities on every call.

- Page tools need `edit_pages`. A tool that takes a page `id` also needs
  `edit_post` on that page.
- `pediment-write-page` sets `status` to `publish` or `private` only for users
  with `publish_pages`. New pages are drafts unless `status` says otherwise.
- With `translation_of`, the user also needs `edit_post` on that page, and on
  any page already in the target language slot of the translation group that
  the write would replace. Otherwise nothing is written.
- Style tools need `edit_theme_options`.
- `pediment-convert-legacy` needs `edit_pages` and `edit_post` on every given
  post, and `edit_theme_options` for templates and template parts. With
  `all: true` it skips the posts the user cannot edit.
- `pediment-rollback-page` refuses a revision that belongs to another page.

### Converting legacy blocks

With `all: true`, one call handles at most 25 posts (the
`pediment_convert_batch_size` filter changes the number), because shared
hosting stops long requests. `remaining` says how many posts with legacy
blocks come after this batch. After a real run, call again: the converted
posts drop out. To move past posts that failed, or to page through a dry run,
pass `after` with the last post id of the previous call. With `ids`, every
given post is handled and `remaining` is 0.

`pediment-convert-legacy` refuses a post (status `failed`, nothing written,
also in a dry run) in these cases, so a dry run shows them before a real run:

- A legacy container (prose, FAQ, feature grid, media & text, …) holds HTML
  outside its inner blocks. The converter would drop it. Edit the HTML into
  blocks first.
- A FAQ answer or a testimonial quote holds block HTML other than paragraphs
  (a list, a heading, …). Paragraphs are split into paragraph blocks; other
  block HTML must be edited by hand first.
- A legacy block comment does not parse, so the block would stay legacy.

- Revisions are disabled for the post or limited to fewer than 2. The tool
  needs a backup revision first.
- The user lacks `unfiltered_html`, and saving as that user would strip
  content outside the converted blocks (kses removes iframes and similar
  markup). Run the conversion as an administrator, or use WP-CLI, which is
  trusted.
- The converted content would add a content-rule error.

`failed` can also mean the write happened but the revision limit pruned the
backup afterwards; the error message says so. Details and the results table:
[convert.md](convert.md).

### Revisions

WordPress saves no revision when it creates a page, so `pediment-write-page`
saves a first one itself. Every write returns the id of the newest revision
(never an autosave, which holds a person's unsaved editor work).
`pediment-rollback-page` restores a revision of that page. Restoring saves a
new revision that holds the restored content; the content it replaced stays
available as an earlier revision, so a rollback can be undone.

### Errors

The MCP Adapter shows an agent only the message of a tool error. For that
reason a rejected write lists every violation in the message, one per line,
so an agent can fix all of them in one pass.

## Rules an agent must follow

`pediment-write-page` and `pediment-check-page` run these rules (the
`Pediment\Content\Policy` class). Errors reject a write; warnings come back
with the result.

- Only allowed blocks. `get-vocabulary` lists them under `allowedBlocks`:
  a set of core blocks, Pediment's functional blocks (form, slider, mega menu,
  blog index, social links, icon), and every block a client theme or another plugin
  registers. `core/html` is never allowed, even if the
  `pediment_allowed_blocks` filter returns it. Text outside a block is an
  error, and so is a block comment whose JSON does not parse.
- The 18 legacy Pediment blocks were removed in Pediment 5. Each one is an
  error ("`pediment/hero` was removed in Pediment 5; convert it with
  pediment-convert-legacy"), so `write-page` rejects them and `check-page`
  reports a stored page that still holds one. `pediment-convert-legacy`
  replaces them with core blocks. The legacy error does not stop a
  conversion: the converter refuses only errors that the conversion adds.
- No literal design values in block attributes. Every value under
  `attrs.style.color` and `attrs.style.spacing` must be a token, and so must
  font size, font family, border radius and shadow. Use presets
  (`var:preset|color|accent`) or a named style (`is-style-<slug>`). Tokens
  are `var:preset|…`, `var(--wp--preset--…)` and `var(--wp--custom--…)`; the
  values `0`, percentages, `inherit`, `currentColor` and `transparent` are
  also fine.
- The same rule applies to inline `style=""` attributes in the markup:
  colours, backgrounds, font size and family, padding, margin, gap, radius
  and shadow must hold tokens. A background image URL is content and passes.
- The saved markup is scanned the way WordPress outputs it for static blocks.
  The server-side output of dynamic blocks (`render.php` of a plugin or theme
  block) is not scanned; that code is trusted. Any `script`, `style`, `iframe`, `object`, `embed`, `noscript`, `svg`,
  `math`, `form`, `base`, `meta`, `link`, `template`, `frame`, `frameset` or
  `applet` tag and any `on…=` event handler is an error, wherever it appears
  (including inside comments and attribute values). Shortcode attributes are
  also checked after their escapes are decoded, because WordPress decodes
  `\x3c` to `<` when it runs the shortcode. Use the `pediment/form` block
  instead of a raw form tag.
- The text `javascript:` is an error anywhere, even in prose: "Learn
  JavaScript: a guide" is rejected. Write "JavaScript –" or rephrase.
- The page title is plain text. A title that contains markup is rejected.
- Every `is-style-<slug>` class must name a style registered for that block.
- Warnings: a container block (`core/group`, `core/columns`, `core/cover`)
  without `metadata.name`, so the editor's list view stays readable; and an
  image that points to another host, which belongs in the media library.

## Validate before you write

The server rules do not check that a static block matches the markup the
editor generates for it. A block that does not match opens in the editor as
"This block contains unexpected or invalid content". The client-kit validator
runs the editor's own check in Node:

    node <client-kit>/scripts/validate-blocks.mjs --wp <version from get-vocabulary> --file page.html

| Option | Meaning |
| --- | --- |
| `--wp` | WordPress version or line of the target site (default 6.9). Lines 6.9, 7.0 and 7.1 are supported. |
| `--file` | Markup file. A `.php` pattern file has its leading `<?php … ?>` header removed. Without it the validator reads stdin. |
| `--pattern` | The markup is a seeder pattern. A `.php` file is always treated as one. |
| `--json` | Machine-readable report. |

Seeder media placeholders in a pattern (`{{media_id:<key>}}` and
`{{media_url:<key>}}`, as `wp pediment adopt` writes them) are replaced by
stand-ins before the check: one numeric id and one URL per key, used
everywhere the key occurs. The `wp-image-<id>` class of an image follows the
stand-in id. Other errors in the file are still reported, and a message may
quote a stand-in id such as 900001 where the file has the placeholder.

Exit codes: 0 valid, 1 problems found, 2 the tool itself failed (bad
arguments, no network on first run, unsupported WordPress line).

The report lists these problems:

- `INVALID`: the block does not match its `save()` output. An unknown
  `core/*` block name is reported here too, because the validator knows every
  core block of that WordPress line; it is almost always a typo.
- `STALE`: the block is only valid through a deprecation. Re-save it in its
  current form.
- `LOOSE`: content outside a block.
- `JSON`: a block comment whose attributes are not valid JSON.

Blocks listed under "not checked" come from a plugin or theme, so the
validator cannot know them. That is expected, and the server rules still apply
to them.

The first run per WordPress line installs a pinned runtime (the editor's
block packages and jsdom, about 700 MB) into `~/.cache/pediment/validator`
(`$XDG_CACHE_HOME/pediment/validator` when `XDG_CACHE_HOME` is set). Set
`PEDIMENT_VALIDATOR_CACHE` to use another folder. The install needs `npm`
on the PATH and network access, and it runs in a temporary directory that is
renamed into place, so two parallel first runs do not break each other. Later
runs reuse the cache. Node 20 or later is required. Where each line's
package versions come from, and how to add a line, is in
`client-kit/validator/README.md`.

After writing, open the page in the block editor once (or ask a person to).
`pediment-check-page` then also reports any block the editor rejected under
`invalidBlocks`. It is `null` until the page has been opened in the editor
after its last write.

## Acceptance test

Repeat the 2026-10-14 experiment through these tools on the Workation dev
environment: rebuild the home page from screenshots with core blocks and named
styles, then apply three design changes (card radius, eyebrow style, section
padding). Each change must take exactly one `pediment-upsert-style` call and
zero page writes. Record the result in this file with the date.

Result, 2026-10-15 (Workation clone, WordPress 7.0, Pediment from PR #117,
MCP Adapter 0.7.0): **passed**.

- Rebuild: one agent built the home page as a new page through the MCP tools
  only. It took 3 rounds and about 16 minutes. The agent made 19 tool calls:
  1 get-vocabulary, 14 upsert-style, 3 write-page, 1 check-page.
- The page has 158 blocks: 157 core blocks and the theme's booking form block.
  It has no `core/html`, no legacy block and no literal design values.
  validate-blocks exits 0. check-page reports 0 errors and 1 warning (three
  photos come from another host).
- The agent estimates the visual match at about 9/10 at 1440 px and 8.5/10
  at 390 px. The header is solid instead of transparent: the theme shows a
  transparent header only above its own hero block.
- The rebuild created 13 site styles. All looks live in named styles, so no
  custom CSS goes into global styles. The 2026-10-14 experiment wrote 13.5 KB.
- Design changes: each change took exactly one `pediment-upsert-style` call
  and zero page writes. The page markup hash, modified date and revision
  count did not change.

| Change | Style | Calls |
| --- | --- | --- |
| Card radius lg → sm (13 cards) | `card` | 1 |
| Eyebrow letter spacing and weight (8 eyebrows) | `eyebrow` | 1 |
| Section padding 80 → 50 (8 sections) | `section` | 1 |

The 2026-10-14 experiment needed 3 global CSS writes plus page edits for the
same three changes on 2 pages. Section padding alone took 22 page edits.

Lessons for agents and authors:

- `pediment-upsert-style` replaces the whole `styles` object of a site
  style. To change one property, send the style's other properties too. Read
  them from `pediment-get-vocabulary` first.
- Give a new block one `is-style-*` class. To change a look in one call, let
  one style own it (for example section padding), and use presets on the
  blocks for what varies (for example the band colour). A converted block can
  carry two: the converter's style class and a legacy `is-style-*` class that
  a theme registered for the new block (for example `is-style-card
  is-style-align-center`). Keep both; removing either one loses part of the
  look.
- WordPress puts style `css` selectors inside `:where()`, so they have low
  specificity. A theme rule or a core rule can win over them. A style cannot
  reliably restyle a nested block that has its own style.
