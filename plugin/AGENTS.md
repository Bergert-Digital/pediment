# Agent instructions — Pediment

Pediment is the shared WordPress site engine. It ships blocks, templates,
patterns, tokens, forms, assets, and AI-assisted Gutenberg authoring as one
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

## Conventions

- PHP uses the `Pediment\` namespace and PSR-4 autoloading from `src/`.
- Stored option keys, table names, transient keys, and constants retain their
  historical `pediment_ai_*` / `PEDIMENT_AI_*` names for migration safety.
- Text domain and REST namespace are `pediment` and `pediment/v1`.
- Blocks render server-side and use plugin token custom properties; see the
  root `AGENTS.md` and `docs/STANDARDS.md` for the full contract.
- Do not reintroduce streaming, extra AI modals, or client-theme dependencies.

Use conventional commits and do not push without explicit user approval.
