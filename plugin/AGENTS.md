# Agent instructions — Pediment

Pediment is the shared WordPress site engine. It ships blocks, templates,
patterns, tokens, forms, assets and seeding as one
plugin artifact, `pediment-plugin.zip`.

## Local development

Run wp-env from the repository root. It mounts the fixture client theme at
`wp-content/themes/pediment-fixture` and this plugin at
`wp-content/plugins/pediment-ai` for local compatibility. Production release
archives install the plugin directory as `pediment`.

```bash
composer install -d plugin
cd plugin && npm install && npm run build
npx wp-env run tests-wordpress --env-cwd=wp-content/plugins/pediment-ai ./vendor/bin/phpunit
cd plugin && npm run e2e
```

An already seeded wp-env database no longer picks up pattern changes through
`wp pediment seed`: the seeder only creates missing pages. After you change a
seeded pattern or the fixture manifest, run `npx wp-env clean all` and start
wp-env again to seed from scratch.

## Conventions

- PHP uses the `Pediment\` namespace and PSR-4 autoloading from `src/`.
- Stored option keys, table names, transient keys, and constants retain their
  historical `pediment_ai_*` / `PEDIMENT_AI_*` names for migration safety.
- Text domain and REST namespace are `pediment` and `pediment/v1`.
- Blocks render server-side and use plugin token custom properties; see the
  root `AGENTS.md` and `docs/STANDARDS.md` for the full contract.
- Do not add client-theme dependencies.
- Seeding is bootstrap-only: the seeder creates missing rows and never writes
  to a row that exists. The content-arbitration hashes are gone; do not add
  per-row hash meta or a re-sync path (see `docs/seeding.md`).

Use conventional commits and do not push without explicit user approval.
