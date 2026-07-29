# Agent instructions — pediment-ai

Project-level guidance for coding agents working in this repo. User-level instructions in `~/.claude/CLAUDE.md` apply first; this file overrides only what is project-specific.

## What this project is

WordPress plugin that adds AI-powered authoring to `pediment` via three flows: **Compose** (generate a fresh page from a prompt), **Edit** (rewrite an existing page), **Refine** (mutate a single block). All flows are moving into a single **chat sidebar** in the Gutenberg editor — see `docs/VISION.md`.

Distribution: GitHub Release zip (`pediment-ai.zip`) installed via wp-admin, with one-click self-updates (plugin-update-checker). Not a public WordPress.org plugin.

## Branches

Main-only. This plugin lives in the `pediment` monorepo (root = theme, `plugin/` = this
plugin) and follows the monorepo's branch model: work lands on `main`; there is no
`development` integration branch. release-please's release PR is the shipping gate —
merging it tags the monorepo and ships both `pediment.zip` and `pediment-ai.zip`.

Default working branch for /dev-cycle and ad-hoc work: `main`.

## Schema and migrations

Tables live in [src/Schema/tables.php](src/Schema/tables.php) and are created by `pediment_ai_install_tables()` on plugin activation and on `plugins_loaded` if `pediment_ai_db_version` ≠ `PEDIMENT_AI_VERSION`. Bumping schema means:

1. Edit `tables.php`.
2. Bump `PEDIMENT_AI_VERSION` in `plugin.php`.
3. The activation hook re-runs `dbDelta` next page load.

Schema-touching changes must run on the working branch, not in a worktree (per user-level worktree policy).

## Local dev

- One root wp-env for the whole monorepo (no standalone wp-env for this plugin) — boot it
  from the repo root with `npm run env:start`. It mounts this plugin at
  `wp-content/plugins/pediment-ai`, served at `localhost:8888`.
- `npm run build` / `npm run start` (from `plugin/`) — build / watch the editor bundle.
- `PEDIMENT_AI_MOCK=true` (set in the root `.wp-env.json`, on by default for local) makes the plugin return canned fixture responses instead of hitting Anthropic. Toggle off in Settings → Pediment AI to test live.

## Testing

- **PHP unit tests** — from the repo root:
  `npx wp-env run tests-wordpress --env-cwd=wp-content/plugins/pediment-ai ./vendor/bin/phpunit`.
- **E2E** — Playwright against `localhost:8888`. `cd plugin && npm run e2e`.
- **PHP lint** — `composer lint` (phpcs) / `composer lint:fix`.

Always run **both** unit and E2E before claiming a flow works end-to-end. The editor UI sits on top of WP's React build, and TS-level checks alone don't catch enqueue/registration issues.

## File layout

```
plugin.php           — bootstrap, version constant, activation hook
src/Bootstrap.php    — wires services on plugins_loaded
src/Anthropic/       — HTTP client + provider interface + SSE parser
src/BlockTree/       — block parsing, serialization, validation
src/Chat/            — ConversationStore, TurnRunner, Tools, VirtualTree, PromptBuilder
src/Jobs/            — (legacy, being removed) Action Scheduler workers
src/Mock/            — MockProvider + fixtures
src/Rest/            — REST controllers
src/Schema/          — DB table definitions
src/Settings/        — admin settings + encrypted options
src/Usage/           — rate limiting + token accounting
editor/              — Gutenberg sidebar / panels (TSX)
editor/hooks/        — React hooks
tests/phpunit/       — PHP unit tests (mirrors src/ tree)
tests/e2e/           — Playwright specs
wp-cli/              — WP-CLI commands
```

## Conventions

- **PHP**: PSR-4 under `\PedimentAi\…`. phpcs rules in `phpcs.xml.dist`. WordPress sniffs relaxed where they conflict with our style — don't reintroduce them.
- **TypeScript**: strict mode (`tsconfig.json`). `@wordpress/scripts` build pipeline. No standalone React — we use the WP-provided React build via the `react-jsx-runtime` shim (see [editor/index.tsx](editor/index.tsx)).
- **Commits**: Conventional Commits — `feat(scope):`, `fix(scope):`, `refactor(scope):`, `chore:`, `docs:`, `test:`. Include `Co-Authored-By: Claude Opus 4.7 (1M context) <noreply@anthropic.com>` when an agent authored the commit.
- **Naming**: PHP classes are `StudlyCase`; methods + properties are `camelCase`; DB columns are `snake_case`; React components are `StudlyCase.tsx`; hooks are `useCamelCase.ts`.

## Don'ts

- Don't reintroduce SSE on the wire. Streaming flows through a DB-backed accumulator that the client polls. This is deliberate — keeps the plugin portable across shared WordPress hosts (nginx FastCGI buffering, Apache mod_proxy buffering, Cloudflare edge buffering).
- Don't add new modals for AI flows. Chat sidebar is the single UI surface.
- Don't add validation, error handling, or feature flags for scenarios that can't happen. Trust internal code.
- Don't write comments that restate the code. Use comments only for hidden constraints (e.g. "Action Scheduler's procedural API needs this require, autoloader doesn't run it" in [plugin.php](plugin.php#L31)).
- Don't push to `main` from an agent session without explicit user instruction.

## /dev-cycle expectations

When working inside a `/dev-cycle` session: commit each finished task with the auto-commit procedure described in the skill. Outside of `/dev-cycle`, commit only when the user asks.
