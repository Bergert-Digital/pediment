# Vision

## What this is

**wp-starter-theme** is a forkable WordPress full-site-editing block theme that serves as the
foundation of a three-part agency stack:

| Piece | Repo | Role |
|---|---|---|
| **Parent theme** | `wp-starter-theme` (this repo) | Blocks, design tokens, Brand Settings, contact form. Read-only in production. |
| **Child theme** | `wp-starter-child-theme` | Per-client fork: `theme.json` overrides + `client/*` blocks. |
| **AI plugin** | `wp-starter-ai` | Composes/edits pages from prompts using the registered blocks. |

The parent theme is the durable, shared layer. Everything client-specific lives downstream in a
child theme so the parent improves for every client at once.

## Who it's for

Two distinct users, both of whom must succeed:

1. **The agency developer** who forks the child theme to stand up a new client site. They care
   about: fast setup, a clear fork checklist, never having to edit parent files, predictable
   `theme.json` overrides, and a block contract that the AI plugin can consume.
2. **The site editor / client** who builds pages in the WordPress block editor, configures
   identity once in **Settings → Brand Settings**, and (optionally) drafts pages with the AI
   plugin. They care about: blocks that render correctly, sane defaults, no broken states, and
   content that survives attribute changes.

## Principles

- **Design tokens are law.** `theme.json` is the single source of truth. No hex/rgb/hsl literals
  in `src/blocks/` — enforced by `lint:colors` and a custom PHPCS sniff. The only place hex is
  allowed is a child theme's `theme.json` palette.
- **Server-side rendering.** Every block renders via `render.php`. Atomic blocks emit no
  `save()` markup; InnerBlocks containers persist `<InnerBlocks.Content />`. Content survives
  attribute changes and server-side variation stays trivial.
- **AI-consumable by construction.** Every `block.json` carries an explicit one-sentence
  `description` and fully-typed `attributes` so the AI plugin can compose with it at runtime
  via `WP_Block_Type_Registry` — no per-block registration on the plugin side.
- **Extend, don't fork the parent.** Child themes extend Brand Settings via the
  `starter_brand_fields` / `starter_brand_sections` filters and add `client/*` blocks. Wanting
  to edit a parent file means opening an upstream PR instead.
- **Quality is non-negotiable.** PHPUnit covers every block's render; Playwright covers the
  editor, front page, brand settings, and contact form; CI gates merges.

## Non-goals

- Not a general-purpose marketplace theme. It targets agency-built client sites.
- Not a page builder. Composition is blocks + patterns + (optionally) the AI plugin.
- No client-specific content, copy, or palette in the parent — that belongs in the child.
