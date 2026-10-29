# Vision

## What this is

Pediment is the shared WordPress site engine. Pages are built from core blocks
and named block styles (the design layer). The plugin supplies the styles, 7
blocks core lacks (forms, mega menu, slider, social links, icon, blog index),
templates, patterns, default design tokens, assets, seeding, and an MCP server
through which AI agents build pages on the live site.
Every client site pairs it with a standalone client theme that owns that site's
brand and customizations.

| Piece | Repo | Role |
| --- | --- | --- |
| **Pediment plugin** | `pediment` (this repo) | Shared runtime and presentation. Releases as `pediment-plugin.zip`. |
| **Client theme** | Client repository | Standalone theme with client tokens, content, and `client/*` extensions. |

The plugin is durable and shared; client-specific work remains downstream. The
plugin must never require a client theme to patch its source.

## Who it's for

1. **Agency developers** need a predictable standalone theme starting point,
   token overrides, and a reliable block contract.
2. **Site editors** need solid blocks and sensible defaults in Gutenberg.
3. **AI agents** build and change pages through the MCP tools and need the
   server to reject anything that breaks the content and design rules.

## Principles

- **Design tokens are law.** `plugin/tokens/theme.json` is the default token
  layer; client `theme.json` entries override matching preset slugs.
- **Looks come from named styles.** Page content never carries literal design
  values; it names a style from the plugin, client-theme or site layer.
- **Server-side rendering.** Every shared block renders through `render.php`.
- **AI-consumable by construction.** `pediment-get-vocabulary` gives an agent
  the same blocks, styles, tokens and rules the site uses, and every write is
  checked against them on the server.
- **Independent client themes.** Themes use ordinary WordPress extension points
  without inheriting from or modifying Pediment.
- **Quality is non-negotiable.** PHPUnit and the merged Playwright suite gate
  the plugin against the fixture client theme.

## Non-goals

- A general-purpose marketplace theme.
- A page builder outside WordPress blocks.
- Client-specific copy, palettes, or content in the shared plugin.
