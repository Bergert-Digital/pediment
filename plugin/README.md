# Pediment

WordPress site engine with shared Gutenberg blocks, templates, patterns, design
tokens, forms and seeding.

## Requirements

- WordPress 6.9+, PHP 8.1+
- A standalone client block theme

## Install

Upload `pediment-plugin.zip` from the latest [GitHub Release](https://github.com/Bergert-Digital/pediment/releases) via **Plugins → Add New → Upload Plugin**, then activate. It installs as `pediment`; after the first install, updates arrive through the normal wp-admin Updates screen.

Upgrading from 3.x removes the retired AI chat's tables and settings on the first request.

The REST API uses the `pediment/v1` namespace.

## Seeding

Client themes declare their pages, posts, media, navigation, and custom post types in a
`seed/manifest.php` file; content itself lives in the theme's `patterns/` directory.
`wp pediment seed` bootstraps a site: it creates what the manifest declares and the site
does not have yet, and never changes a page, menu or setting that exists. Live pages
change in the editor or through the MCP tools; `wp pediment adopt <key>` exports a live
page's markup into its pattern file so new environments match. A manifest that declares a
`languages` section makes the site multilingual (via Polylang); `wp pediment languages`
configures Polylang from that section before the first seed, and
`wp pediment adopt <key> --language=de` adopts one language's translation. See
[docs/seeding.md](../docs/seeding.md) for the full manifest reference, what a seed does
on a site that is already seeded, the languages section, and how to read a dry-run plan.

## AI tools (MCP)

With the MCP Adapter plugin active, Pediment serves an MCP server at
`/wp-json/pediment/v1/mcp` with nine tools to read, write and convert pages and to change design
styles under Pediment's rules. See [docs/mcp.md](../docs/mcp.md).

## Local dev

This plugin is part of the `pediment` monorepo and shares its **one root wp-env** — there is
no standalone wp-env for the plugin. Run everything below from the **repo root**, not from
`plugin/`.

### First-time setup

```bash
npm install
composer install -d plugin
( cd plugin && npm install && npm run build )
```

### Start wp-env

```bash
npm run env:start
```

The root wp-env mounts a minimal fixture client theme at
`wp-content/themes/pediment-fixture` and this plugin at
`wp-content/plugins/pediment-ai`, both served from http://localhost:8888.

If this is the very first boot of wp-env, activate the fixture theme and plugin once:

```bash
npx wp-env run cli wp theme activate pediment-fixture
npx wp-env run cli wp plugin activate pediment-ai
```

### Stop wp-env

```bash
npm run env:stop
```

### Day-to-day commands

```bash
# Rebuild the blocks after JS/TS changes (from plugin/)
cd plugin && npm run build

# Watch + rebuild on save (from plugin/)
cd plugin && npm run start

# Run PHPUnit (from the repo root)
npx wp-env run tests-wordpress --env-cwd=wp-content/plugins/pediment-ai ./vendor/bin/phpunit

# Filter a single test class
npx wp-env run tests-wordpress --env-cwd=wp-content/plugins/pediment-ai ./vendor/bin/phpunit --filter SmokeTest

# Run Playwright E2E (needs wp-env running, from plugin/)
cd plugin && npm run e2e

# PHP lint (from plugin/)
composer lint
composer lint:fix
```
