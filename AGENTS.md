# AGENTS.md — Pediment

Read `docs/STANDARDS.md` before changing code.

## Project

This repository ships one WordPress plugin: Pediment. It owns the shared
blocks, templates, patterns, tokens, forms, assets, and seeding.
Client sites pair the plugin with a standalone client theme; the root fixture
theme exists only for wp-env and integration tests.

The repo also ships `client-template/` (the tokenised client theme template a
new site is scaffolded from) and `client-kit/` (the Claude Code plugin
carrying the `/pediment:start` and `/pediment:port-page` skills and the
scaffolder). See [docs/client-sites.md](docs/client-sites.md).

## Hard rules

- Prefer WordPress APIs, hooks, filters, and block APIs.
- In `plugin/src/blocks/`, use `var(--wp--preset--…)`; color literals fail CI.
- Render blocks server-side via `render.php`; containers persist
  `<InnerBlocks.Content />`.
- Sanitize output with the appropriate WordPress escaping function.
- Every block keeps `block.json`, `index.tsx`, `edit.tsx`, `render.php`, and
  `style.scss`, plus PHPUnit coverage for valid and empty attributes.
- Client themes extend through normal WordPress theme facilities. Never depend
  on editing plugin files for client-specific behavior.
- Seeding is bootstrap-only (see `docs/seeding.md`): `wp pediment seed`
  creates what the client theme's `seed/manifest.php` and `patterns/*.php`
  declare and the site lacks. It never changes an existing page's content,
  title or slug, never rewrites a menu, and keeps reading settings and a logo
  a person chose; it only fills empty settings, missing language tags and the
  translation links of pages it creates. Never write pages with `wp post create` / `wp post update` — the
  seeder owns identity (`_pediment_seed_key`), a hand-written post has none,
  and the next seed creates a duplicate. Change an existing page through the
  Pediment MCP tools.
- Languages are declared in the manifest's `languages` section and configured
  with `wp pediment languages` before seeding, never after — see
  [docs/seeding.md#languages](docs/seeding.md#languages).
- AI page and style changes on a live site go through the Pediment MCP tools (see
  [docs/mcp.md](docs/mcp.md)); validate markup with client-kit's validate-blocks before writing.
- Page content never carries literal design values. Looks come from named
  block styles in the design layer — see [docs/design-layer.md](docs/design-layer.md).
- Pediment 5 removed the 18 legacy presentation blocks (`Policy::LEGACY`),
  and Pediment 6 removed their converter. The content rules reject them.
  Never put them in new content or patterns; rebuild old content with core
  blocks and named styles — see
  [docs/blocks.md](docs/blocks.md#what-to-build-instead).

## Environment and verification

Use the one root wp-env at `localhost:8888`. It mounts the fixture client theme
at `wp-content/themes/pediment-fixture` and the plugin locally at
`wp-content/plugins/pediment-ai`.

```bash
composer lint -d plugin
npm run lint:js
npm run lint:blocks
npm run lint:colors
npx wp-env run tests-wordpress --env-cwd=wp-content/plugins/pediment-ai ./vendor/bin/phpunit
cd plugin && npm run e2e
npm run test:kit
```

For layout or typography changes, also run `node tools/audit-landing.mjs` and
check 375px, 768px, and 1440px plus the block editor. Before a WordPress-side
fix, read `docs/WORDPRESS_TRAPS.md` and record any newly discovered trap.

Install dependencies with `npm ci`, never `npm install`. CI runs npm 10, and a
local npm 11 install rewrites the lockfiles into a form npm 10 rejects. Change
a lockfile only on purpose, with npm 10: `npx -y npm@10 install <pkg>`. If
`git status` shows an unexpected `package-lock.json` change, discard it.

## Commits and releases

Use conventional commits, imperative summaries of 60 characters or fewer, and
stage files by name. Never push or use `gh` remote actions without explicit
user approval. release-please is the shipping gate and publishes the sole
artifact, `pediment-plugin.zip`.
