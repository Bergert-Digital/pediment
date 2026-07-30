# Pediment

Pediment is a WordPress plugin that ships the shared site engine: 24 Gutenberg
blocks, templates, patterns, design tokens, forms, and AI-assisted authoring.
Each site uses its own standalone client theme for branding and client-owned
customizations. The release artifact is `pediment-plugin.zip`.

## Requirements

- WordPress 6.9+, PHP 8.1+
- A standalone block client theme
- Docker, Node 20+, and Composer for local development

## Local development

```bash
npm install
composer install -d plugin
( cd plugin && npm install && npm run build )
npm run env:start
```

wp-env mounts the test client theme at `wp-content/themes/pediment-fixture` and
Pediment at `wp-content/plugins/pediment-ai`. Activate them once after a fresh
environment:

```bash
npx wp-env run cli wp theme activate pediment-fixture
npx wp-env run cli wp plugin activate pediment-ai
```

The fixture is intentionally minimal. Templates, patterns, tokens, blocks, and
shared assets are supplied by the plugin.

## Useful commands

| Command | What it does |
| --- | --- |
| `npm run build` | Builds the plugin editor bundle and blocks. |
| `npm run lint:blocks` | Checks the plugin block file contract. |
| `npm run lint:colors` | Rejects color literals in plugin block styles. |
| `npm run lint:js` | Runs JavaScript linting. |
| `composer lint -d plugin` | Runs PHP coding standards checks. |
| `npx wp-env run tests-wordpress --env-cwd=wp-content/plugins/pediment-ai ./vendor/bin/phpunit` | Runs plugin PHPUnit tests. |
| `cd plugin && npm run e2e` | Runs the merged Playwright suite. |

## Releases

Main-only development uses release-please. A release creates exactly one asset:
`pediment-plugin.zip`, which installs as `wp-content/plugins/pediment/`. It
does not include a client theme; install or keep a site-specific standalone
theme alongside it.

## Architecture

- `plugin/src/blocks/` — server-rendered shared blocks.
- `plugin/templates/` and `plugin/patterns/` — plugin-served site presentation.
- `plugin/tokens/theme.json` — default tokens, injected beneath client-theme
  overrides.
- `plugin/inc/` — procedural WordPress integrations, forms, and assets.
- `tests/fixtures/client-theme/` — the minimal standalone client theme used by
  wp-env and end-to-end tests.

See [docs/blocks.md](docs/blocks.md) for the block authoring contract.
