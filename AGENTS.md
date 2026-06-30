# AGENTS.md — pediment

Project-level agent instructions. User-level `~/.claude/CLAUDE.md` and explicit user requests
take precedence over this file.

## What this project is

A forkable WordPress FSE block theme — the parent layer of a three-repo agency stack
(`pediment` parent · `pediment-child-theme` per-client child · `pediment-ai`
plugin). See `docs/VISION.md`. Read `docs/STANDARDS.md` before changing code.

## Hard rules

- **Stick to WordPress standards.** Prefer official WordPress APIs, hooks, filters, block
  APIs, and conventions over custom solutions. Only invent a custom mechanism when
  WordPress offers no official extension point for what's needed — and flag that choice
  in the PR so it can be reviewed.
- **No color literals in `src/blocks/`.** Use `var(--wp--preset--…)` from `theme.json`.
  `lint:colors` + the `Starter.NoColorLiteralSniff` PHPCS sniff will fail CI otherwise.
- **Server-side render only.** Blocks render via `render.php`. Atomic blocks emit no `save()`
  markup; InnerBlocks containers persist `<InnerBlocks.Content />`.
- **Sanitize all output:** `wp_kses_post()` / `esc_html()` / `esc_url()` / `esc_attr()`.
- **Every block keeps its 5 files** (`block.json`, `index.tsx`, `edit.tsx`, `render.php`,
  `style.scss`) and a PHPUnit render test covering valid + empty attributes.
- **Don't validate for scenarios that can't happen.** Delete unreachable defensive code
  rather than "polishing" it.
- **Parent is read-only from a child's view.** Child themes extend the parent through theme
  features (theme.json, template parts, block filters); never assume a child can patch a
  parent file.

## Environment

- Local dev: wp-env (Docker). The shared test base is the **child-theme** env at
  `localhost:8890`; do **not** start wp-env from `pediment` or `pediment-ai`
  independently. "Dev server down" → check the Docker daemon first.
- PHP 8.1+, WordPress 6.4+. `@wordpress/scripts` build (`npm run build` → `build/blocks/`).

## Verifying work

1. `composer lint` · `npm run lint:js` · `npm run lint:blocks` · `npm run lint:colors`
2. PHPUnit: `npx wp-env run tests-wordpress --env-cwd=wp-content/themes/pediment vendor/bin/phpunit`
3. Playwright: `npm run e2e`
4. **Layout / typography / style changes**: also run `node tools/audit-landing.mjs` and
   verify the affected band at **375px, 768px, and 1440px** viewports, AND in the block
   editor (post.php?action=edit). A change that looks right at desktop but breaks the
   mobile gutter, the editor canvas, or a band's measured x/width is not done. The audit
   script's `test-results/audit/index.html` is the source of truth for "matches the
   mockup".
5. **Before claiming any WordPress-side fix**: consult @docs/WORDPRESS_TRAPS.md for known
   platform quirks (slug sanitization, has-global-padding nested-reset, wp_update_post
   un-slashing, edit.tsx ↔ render.php parity, REST nonce theatre, etc.). If a fix touches
   one of those areas, add a brief note or new entry to the traps doc so the next
   session inherits the knowledge.

No success claims without running the relevant command and seeing it pass.

## Commits & pushes

Conventional commits, imperative, ≤60-char summary, stage by name (never `git add -A`),
Co-Authored-By trailer. `git push` and any `gh` remote action require explicit user
go-ahead — show the exact command and stop.

## /dev-cycle docs

`docs/VISION.md`, `docs/BACKLOG.md`, `docs/PRODUCT_SENSE.md`, `docs/STANDARDS.md`,
`docs/SESSION_LOG.md`. Keep BACKLOG and SESSION_LOG current as work progresses.
