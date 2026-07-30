# AGENTS.md — Pediment

Read `docs/STANDARDS.md` before changing code.

## Project

This repository ships one WordPress plugin: Pediment. It owns the shared
blocks, templates, patterns, tokens, forms, assets, and AI editor experience.
Client sites pair the plugin with a standalone client theme; the root fixture
theme exists only for wp-env and integration tests.

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
```

For layout or typography changes, also run `node tools/audit-landing.mjs` and
check 375px, 768px, and 1440px plus the block editor. Before a WordPress-side
fix, read `docs/WORDPRESS_TRAPS.md` and record any newly discovered trap.

## Commits and releases

Use conventional commits, imperative summaries of 60 characters or fewer, and
stage files by name. Never push or use `gh` remote actions without explicit
user approval. release-please is the shipping gate and publishes the sole
artifact, `pediment-plugin.zip`.
