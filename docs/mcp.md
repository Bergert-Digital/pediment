# Building pages with AI through MCP

Pediment exposes eight tools to AI agents through the WordPress MCP Adapter.
Agents build and change pages, posts and custom post types on the live site; Pediment enforces the content
and design rules on the server, so a page that breaks a rule is never written.

## Set up a site

1. Install and activate the **MCP Adapter** plugin (Plugins → Add New →
   search "MCP Adapter", or upload `mcp-adapter.0.7.0.zip` from
   `https://downloads.wordpress.org/plugin/mcp-adapter.0.7.0.zip`). Pediment
   needs WordPress 6.9 or later. Without the adapter Pediment still works; the
   MCP server is simply not there.
2. Open Settings → Pediment → **AI agents**. The tab checks both
   requirements (MCP Adapter active, application passwords available) and
   shows the endpoint. **Create password and show setup** creates an
   application password for the signed-in user and shows, once:
   one tab per client:
   - **Claude Code**: a `claude mcp add --scope user …` command with the
     credentials filled in.
   - **ChatGPT / Codex**: a `[mcp_servers.…]` block for `~/.codex/config.toml`
     (read by the Codex CLI, app and IDE extension). `codex mcp add` only
     sends Bearer tokens, so the Basic header goes into `http_headers`.
     ChatGPT in the browser or desktop app connects custom MCP servers only
     with OAuth or without authentication, so it cannot use this server.
   - **Other clients**: the URL and `Authorization` header.

   WordPress only offers application passwords over HTTPS (or on a local
   environment). Revoke a connection under Users → Profile → Application
   Passwords. The tab needs `manage_options`; an Editor who wants a
   connection without style tools follows the manual steps below.

Manual setup, without the tab: create an application password (Users →
Profile → Application Passwords), then run (replace SITE, USER and the
password):

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
lists the tools, writes and checks a page, writes and reads a blog post, checks that a stored legacy page is reported, and confirms that a write breaking
two rules comes back as one tool error that names both.

## Tools

| Tool | Does |
| --- | --- |
| `pediment-get-vocabulary` | Tokens, named styles (with layer), allowed blocks, rules, WordPress version, languages, served post types. Call it first. |
| `pediment-list-pages` | Pages (or posts of `post_type`) of every language. Filters: language, parent, status. At most 500; `truncated` is true when more matched. |
| `pediment-read-page` | One page or post with raw block markup, translations, excerpt, menu order and featured image. |
| `pediment-check-page` | Content-rule errors and warnings, blocks the editor reported invalid, legacy blocks still on the page (errors since Pediment 5). |
| `pediment-write-page` | Create (draft by default) or update a page or post, with optional excerpt, menu order and featured image. Rejected with every violation listed if a rule fails. Keeps a revision. |
| `pediment-rollback-page` | Restore one of the page's or post's own revisions. |
| `pediment-upsert-style` | Create or change a site-layer style (see [design-layer.md](design-layer.md)). |
| `pediment-delete-style` | Delete a site-layer style. |

### Posts and custom post types

The five page tools take an optional `post_type` input. Without it they work
on pages, as before. `pediment-get-vocabulary` lists the served types under
`postTypes`, each with `hierarchical`, `revisions` and `translated`.

- Served types: `page`, `post`, and every type that a theme or plugin
  registers with `show_in_rest`, `editor` support and the block editor. A
  custom post type from the manifest's `post_types` qualifies. Other
  built-in types (`wp_block`, `wp_template`, `wp_navigation`, `attachment`)
  and `pediment_style` are never served.
- The filter `pediment_mcp_post_types` receives the list of slugs. It can
  remove a type, or add a type that meets the rules above. It cannot add one
  that breaks them.
- The type is never guessed. A tool that takes an `id` refuses a post of
  another type, and the error names the type to pass ("It is a guide; pass
  post_type \"guide\"").
- `parent` works only on hierarchical types, and the parent must have the
  same type. `language` and `translation_of` work only on types the language
  plugin translates (Polylang: Languages → Settings → Custom post types).
- A type without `revisions` support can be written, but the write returns
  `revision: 0` and a warning that it cannot be rolled back. The seeder's
  manifest `post_types` add `revisions` to `supports` by default.
- `excerpt` (plain text) needs `excerpt` support, `menu_order` needs
  `page-attributes` support. Pages have no excerpt by default; posts have no
  menu order. A field the type does not support is refused, with every such
  field listed in one error.
- Meta fields and taxonomies are not exposed yet.

### Featured images

`pediment-write-page` takes `featured_image` on types with `thumbnail`
support:

| Input | Effect |
| --- | --- |
| `{ "url": "https://…", "alt": "…" }` | Imports the image into the media library and sets it. The attachment remembers the source URL (`_pediment_source_url`); a later write with the same URL reuses it and downloads nothing. |
| `{ "id": 123, "alt": "…" }` | Sets an image that is already in the media library. |
| `{ "id": 0 }` | Removes the featured image. |

Leave `featured_image` out to keep the current image. `pediment-read-page`
returns it as `featured_image` (`id`, `url`, `alt`, `source`) or `null`.

- The alt text belongs to the post, not to the attachment. Translations
  share one attachment and each keeps its own alt text: Pediment keeps
  Polylang's media translation off, so an attachment has no language. The
  post's alt text replaces the attachment's wherever the theme prints the
  featured image through `get_the_post_thumbnail()` (which
  `core/post-featured-image` uses). The attachment gets the first alt text
  it is given, for the media library.
- Imports accept JPEG, PNG, GIF, WebP and AVIF. A URL without a file
  extension (many image CDNs) is named by the real image type.
- The download runs through `download_url()`, which refuses local and
  private addresses. Wikimedia serves thumbnails only in its standard
  widths (for example 330px or 640px); another width returns "Bad Request".
- The image is imported before the post is written, so a failed download
  writes nothing.

### Permissions

The tool list is fixed: the server exposes these eight abilities and nothing
else. Each tool checks WordPress capabilities on every call.

- Page tools need the post type's `edit_posts` capability: `edit_pages` for
  pages, `edit_posts` for posts, the mapped capability for a custom post
  type. A tool that takes an `id` also needs `edit_post` on that post.
- `pediment-write-page` sets `status` to `publish` or `private` only for users
  with the type's `publish_posts` capability (`publish_pages` for pages).
  New posts are drafts unless `status` says otherwise.
- `pediment-get-vocabulary` needs `edit_posts` of at least one served type.
- A `featured_image` with `url` needs `upload_files`. One with `id` does not.
- With `translation_of`, the user also needs `edit_post` on that page, and on
  any page already in the target language slot of the translation group that
  the write would replace. Otherwise nothing is written.
- Style tools need `edit_theme_options`.
- `pediment-rollback-page` refuses a revision that belongs to another page.

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
  error ("`pediment/hero` was removed in Pediment 5; rebuild it with core
  blocks and named styles (pediment-get-vocabulary)"), so `write-page` rejects
  them and `check-page` reports a stored page that still holds one. Pediment 6
  removed the converter: rebuild that part of the page with the tree in
  [blocks.md](blocks.md#what-to-build-instead).
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
  blocks for what varies (for example the band colour). A block converted
  in Pediment 4 or 5 can carry two: the converter's style class and a legacy
  `is-style-*` class that a theme registered for the new block (for example `is-style-card
  is-style-align-center`). Keep both; removing either one loses part of the
  look.
- WordPress puts style `css` selectors inside `:where()`, so they have low
  specificity. A theme rule or a core rule can win over them. A style cannot
  reliably restyle a nested block that has its own style.
