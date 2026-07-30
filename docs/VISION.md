# Vision

## What this is

**pediment** is a forkable WordPress full-site-editing block theme that serves as the
foundation of a two-repo agency stack:

| Piece | Repo | Role |
|---|---|---|
| **Parent theme + AI plugin** | `pediment` (this repo, a monorepo) | The theme lives at the repo root; the AI plugin lives in `plugin/`. Both ship together on one version line and release as two artifacts from a single tag. Blocks, design tokens, contact form, and page composition/editing all live here; the theme is read-only in production. |
| **Child theme** | `pediment-child-theme` | Per-client fork: `theme.json` overrides + `client/*` blocks. |

The parent theme is the durable, shared layer. Everything client-specific lives downstream in a
child theme so the parent improves for every client at once.

A future step (see `docs/superpowers/specs/2026-07-29-pediment-dev-flow-design.md` §4.1) plans to
dissolve the parent theme into the plugin entirely, leaving one plugin artifact plus one
standalone theme per client — but that has not happened yet; the layout above is current.

## Who it's for

Two distinct users, both of whom must succeed:

1. **The agency developer** who forks the child theme to stand up a new client site. They care
   about: fast setup, a clear fork checklist, never having to edit parent files, predictable
   `theme.json` overrides, and a block contract that the AI plugin can consume.
2. **The site editor / client** who builds pages in the WordPress block editor and (optionally)
   drafts pages with the AI plugin, where brand identity/voice is configured. They care about:
   blocks that render correctly, sane defaults, no broken states, and content that survives
   attribute changes.

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
- **Extend, don't fork the parent.** Child themes extend the parent via `theme.json` overrides,
  template parts, block filters, and `client/*` blocks. Wanting to edit a parent file means
  opening an upstream PR instead.
- **Quality is non-negotiable.** PHPUnit covers every block's render; Playwright covers the
  editor, front page, and contact form; CI gates merges.

## Non-goals

- Not a general-purpose marketplace theme. It targets agency-built client sites.
- Not a page builder. Composition is blocks + patterns + (optionally) the AI plugin.
- No client-specific content, copy, or palette in the parent — that belongs in the child.
