# AGENTS.md — wp-starter-theme

Project-level agent instructions. User-level `~/.claude/CLAUDE.md` and explicit user requests
take precedence over this file.

## What this project is

A forkable WordPress FSE block theme — the parent layer of a three-repo agency stack
(`wp-starter-theme` parent · `wp-starter-child-theme` per-client child · `wp-starter-ai`
plugin). See `docs/VISION.md`. Read `docs/STANDARDS.md` before changing code.

## Hard rules

- **No color literals in `src/blocks/`.** Use `var(--wp--preset--…)` from `theme.json`.
  `lint:colors` + the `Starter.NoColorLiteralSniff` PHPCS sniff will fail CI otherwise.
- **Server-side render only.** Blocks render via `render.php`. Atomic blocks emit no `save()`
  markup; InnerBlocks containers persist `<InnerBlocks.Content />`.
- **Sanitize all output:** `wp_kses_post()` / `esc_html()` / `esc_url()` / `esc_attr()`.
- **Every block keeps its 5 files** (`block.json`, `index.tsx`, `edit.tsx`, `render.php`,
  `style.scss`) and a PHPUnit render test covering valid + empty attributes.
- **Don't validate for scenarios that can't happen.** Delete unreachable defensive code
  rather than "polishing" it.
- **Parent is read-only from a child's view.** Extend Brand Settings via the
  `starter_brand_fields` / `starter_brand_sections` filters; never assume a child can patch a
  parent file.

## Environment

- Local dev: wp-env (Docker). The shared test base is the **child-theme** env at
  `localhost:8890`; do **not** start wp-env from `wp-starter-theme` or `wp-starter-ai`
  independently. "Dev server down" → check the Docker daemon first.
- PHP 8.1+, WordPress 6.4+. `@wordpress/scripts` build (`npm run build` → `build/blocks/`).

## Verifying work

1. `composer lint` · `npm run lint:js` · `npm run lint:blocks` · `npm run lint:colors`
2. PHPUnit: `npx wp-env run tests-wordpress --env-cwd=wp-content/themes/wp-starter-theme vendor/bin/phpunit`
3. Playwright: `npm run e2e`

No success claims without running the relevant command and seeing it pass.

## Commits & pushes

Conventional commits, imperative, ≤60-char summary, stage by name (never `git add -A`),
Co-Authored-By trailer. `git push` and any `gh` remote action require explicit user
go-ahead — show the exact command and stop.

## /dev-cycle docs

`docs/VISION.md`, `docs/BACKLOG.md`, `docs/PRODUCT_SENSE.md`, `docs/STANDARDS.md`,
`docs/SESSION_LOG.md`. Keep BACKLOG and SESSION_LOG current as work progresses.
