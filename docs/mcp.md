# Building pages with AI through MCP

Pediment exposes eight tools to AI agents through the WordPress MCP Adapter.
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
lists the tools, writes and checks a page, and confirms that a rule violation
comes back as a tool error.

## Tools

| Tool | Does |
| --- | --- |
| `pediment-get-vocabulary` | Tokens, named styles (with layer), allowed blocks, rules, WordPress version, languages. Call it first. |
| `pediment-list-pages` | Pages of every language. Filters: language, parent, status. |
| `pediment-read-page` | One page with raw block markup and translations. |
| `pediment-check-page` | Content-rule errors and warnings, blocks the editor reported invalid, legacy blocks still on the page. |
| `pediment-write-page` | Create (draft by default) or update a page. Rejected with every violation listed if a rule fails. Keeps a revision. |
| `pediment-rollback-page` | Restore one of the page's own revisions. |
| `pediment-upsert-style` | Create or change a site-layer style (see [design-layer.md](design-layer.md)). |
| `pediment-delete-style` | Delete a site-layer style. |

### Permissions

The tool list is fixed: the server exposes these eight abilities and nothing
else. Each tool checks WordPress capabilities on every call.

- Page tools need `edit_pages`. A tool that takes a page `id` also needs
  `edit_post` on that page.
- `pediment-write-page` sets `status` to `publish` or `private` only for users
  with `publish_pages`. New pages are drafts unless `status` says otherwise.
- With `translation_of`, the user also needs `edit_post` on that page, and on
  any page already in the target language slot of the translation group that
  the write would replace. Otherwise nothing is written.
- Style tools need `edit_theme_options`.
- `pediment-rollback-page` refuses a revision that belongs to another page.

### Revisions

WordPress saves no revision when it creates a page, so `pediment-write-page`
saves a first one itself. Every write returns the id of the newest revision.
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
  blog index, social links), and every block a client theme or another plugin
  registers. `core/html` is never allowed, even if the
  `pediment_allowed_blocks` filter returns it. Text outside a block is an
  error, and so is a block comment whose JSON does not parse.
- The 18 legacy Pediment blocks only produce a warning, because the converter
  in a later release replaces them with core blocks. Do not use them in new
  pages.
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
- The markup is scanned as it renders, which is what the browser receives.
  Any `script`, `style`, `iframe`, `object`, `embed`, `noscript`, `svg`,
  `math`, `form`, `base`, `meta`, `link`, `template`, `frame`, `frameset` or
  `applet` tag, any `on…=` event handler, and any `javascript:` URL is an
  error, wherever it appears (including inside comments and attribute values).
  Use the `pediment/form` block instead of a raw form tag.
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
| `--wp` | WordPress version or line of the target site (default 6.9). Lines 6.9 and 7.0 are supported. |
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
block packages and jsdom, about 700 MB) into `~/.cache/pediment/validator` (`$XDG_CACHE_HOME/pediment/validator` when
`XDG_CACHE_HOME` is set). Set `PEDIMENT_VALIDATOR_CACHE` to use another folder. The install needs `npm`
on the PATH and network access, and it runs in a temporary directory that is
renamed into place, so two parallel first runs do not break each other. Later
runs reuse the cache. Node 20 or later is required.

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

Result: not yet recorded.
