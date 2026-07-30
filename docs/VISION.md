# Vision

## What this is

Pediment is the shared WordPress site engine. The plugin supplies 24 blocks,
templates, patterns, default design tokens, forms, assets, and AI authoring.
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
2. **Site editors** need solid blocks, sensible defaults, and optional AI help
   to compose or refine content in Gutenberg.

## Principles

- **Design tokens are law.** `plugin/tokens/theme.json` is the default token
  layer; client `theme.json` entries override matching preset slugs.
- **Server-side rendering.** Every shared block renders through `render.php`.
- **AI-consumable by construction.** Complete `block.json` metadata lets the
  editor discover the same registered blocks that sites use.
- **Independent client themes.** Themes use ordinary WordPress extension points
  without inheriting from or modifying Pediment.
- **Quality is non-negotiable.** PHPUnit and the merged Playwright suite gate
  the plugin against the fixture client theme.

## Non-goals

- A general-purpose marketplace theme.
- A page builder outside WordPress blocks.
- Client-specific copy, palettes, or content in the shared plugin.
