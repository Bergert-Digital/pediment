# Pediment AI Plugin

WordPress plugin that adds AI-powered authoring to the [pediment](https://github.com/bergert/pediment): Compose a page from a prompt, Edit an existing page, Refine a single block.

## Requirements

- WordPress 6.4+, PHP 8.1+
- `pediment` (Plan A) installed and active
- Anthropic API key

## Install

Upload `pediment-ai.zip` from the latest [GitHub Release](https://github.com/Bergert-Digital/pediment/releases) via **Plugins → Add New → Upload Plugin**, then activate. After the first install, updates arrive one-click through the normal wp-admin Updates screen.

Define `ANTHROPIC_API_KEY` in `wp-config.php` — the plugin reads the constant when set; otherwise it falls back to the key in Settings → Pediment AI, where it is stored encrypted.

## Three flows

- **Compose.** Document sidebar → "Compose with AI" → prompt + page type → fresh page generated from registered blocks.
- **Edit.** Document sidebar → "Edit with AI" → instruction → page content replaced (use Undo to revert).
- **Refine.** Select any Pediment block → Inspector → "AI refine" → quick actions or custom instruction → attributes update.

Compose and Edit run as background jobs (Action Scheduler); the editor polls `/wp-json/pediment-ai/v1/jobs/{id}` every 750ms. Refine is synchronous.

## Web fetch

The model has access to Anthropic's `web_fetch_20250910` server tool during Compose and Edit. It may fetch URLs the user mentions or that it decides are relevant. Fetched URLs appear as pills in the editor.

## Models

Defaults (configurable in Settings):

- Compose / Edit: `claude-sonnet-4-6`
- Refine: `claude-haiku-4-5`

## Rate limits

Per-user, per-hour defaults (configurable in Settings):

- Compose: 30
- Edit: 30
- Refine: 200

## Local dev

This plugin is part of the `pediment` monorepo and shares its **one root wp-env** — there is
no standalone wp-env for the plugin. Run everything below from the **repo root**, not from
`plugin/`.

### First-time setup

```bash
composer install
npm install
npm run build
composer install -d plugin
( cd plugin && npm install && npm run build )
```

### Start wp-env

```bash
npm run env:start
```

The root wp-env mounts the theme at `wp-content/themes/pediment` and this plugin at
`wp-content/plugins/pediment-ai`, both served from http://localhost:8888.

### Stop wp-env

```bash
npm run env:stop
```

### Day-to-day commands

```bash
# Rebuild the editor bundle after JS/TS changes (from plugin/)
cd plugin && npm run build

# Watch + rebuild on save (from plugin/)
cd plugin && npm run start

# Run PHPUnit (from the repo root)
npx wp-env run tests-wordpress --env-cwd=wp-content/plugins/pediment-ai ./vendor/bin/phpunit

# Filter a single test class
npx wp-env run tests-wordpress --env-cwd=wp-content/plugins/pediment-ai ./vendor/bin/phpunit --filter ComposeJobTest

# Run Playwright E2E (needs wp-env running, from plugin/)
cd plugin && npm run e2e

# PHP lint (from plugin/)
composer lint
composer lint:fix
```

Mock mode is on by default in `.wp-env.json` (`PEDIMENT_AI_MOCK=true`), so the plugin returns fixture responses instead of calling Anthropic. Toggle off in plugin settings to test against real Anthropic.

See [docs/prompts.md](docs/prompts.md) for prompt tuning and [docs/privacy.md](docs/privacy.md) for data-handling disclosures clients should include in their privacy policies.
# pediment-ai
