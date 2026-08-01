# Standards

Non-negotiable quality bars. CI enforces most of these; the rest are review gates.

## Block authoring contract (see `docs/blocks.md`)

Every block in `plugin/src/blocks/<name>/` has exactly: `block.json`, `index.tsx`, `edit.tsx`,
`render.php`, `style.scss`. Enforced by `npm run lint:blocks`.

- `block.json` has an explicit one-sentence `description` and fully-typed `attributes` with
  defaults. Namespace `pediment/` (parent) or `client/` (child). API version 3.
- Rendered server-side via `render.php`. No `save()` markup for atomic blocks;
  `<InnerBlocks.Content />` for containers.
- Output is sanitized: `wp_kses_post()` for rich text, `esc_html()` for strings, `esc_url()`
  for URLs, `esc_attr()` for attributes. No exceptions.

## Design tokens

No hex / rgb / hsl literals anywhere under `plugin/src/blocks/`. Use `var(--wp--preset--…)` from
`plugin/tokens/theme.json`. Enforced by `npm run lint:colors` **and** the `Starter.NoColorLiteralSniff`
PHPCS sniff. Shared defaults live in `plugin/tokens/theme.json`; client palette literals belong
in the standalone client theme's `theme.json`.

## Empty / partial / hostile states

Every block must render correctly with default/empty attributes and with partial input. No
broken markup (`<a href="">`), no PHP notices, no unsanitized output. This is a review gate
on every block change, not just new blocks.

## Tests

- **PHPUnit:** every block has `plugin/tests/phpunit/BlockRender/<Name>Test.php` covering valid +
  edge-case (empty) attributes. Forms, patterns, templates, and AI have suites.
  Run: `npx wp-env run tests-wordpress --env-cwd=wp-content/plugins/pediment-ai ./vendor/bin/phpunit`
- **Seeding:** a manifest-format, applier, differ, or verifier change needs a test in
  `plugin/tests/phpunit/Seeder/` (see `docs/seeding.md`). `wp pediment seed --dry-run`
  must plan zero writes (`0 to write`) against an unchanged manifest — a dry run that
  wants to write something nobody changed is a regression, not a quirk.
- **Languages:** a manifest declaring `languages` makes a site multilingual; see
  [docs/seeding.md#languages](seeding.md#languages) for the manifest shape, the derived
  slug rule, and `wp pediment languages` / `wp pediment adopt --language`. A
  Polylang-specific change needs a test in `plugin/tests/polylang/` (its own PHPUnit
  config, `plugin/phpunit-polylang.xml.dist`), and everything Polylang-specific stays
  behind the `LanguageProvider` seam (`plugin/src/Language/`).
- **Playwright:** editor block insertion, front-page render, forms, and AI flows.
  Run: `cd plugin && npm run e2e` (requires `npm run env:start`).
- A feature or fix is not done until its test passes and the relevant screenshot looks right.

## Linting & CI

`composer lint` (WPCS 3.1 + PHPCompatibilityWP, PHP 8.1+), `npm run lint:js`,
`npm run lint:blocks`, `npm run lint:colors`. CI (`.github/workflows/ci.yml`) runs phpcs,
lint-blocks, phpunit, and e2e on every PR and push to main. Red CI never merges.

## Extensibility discipline

Client themes are independent and extend Pediment through `theme.json` overrides,
template parts, block filters, and `client/*` blocks. A change that forces a client
theme to edit a plugin file is a product API gap to fix upstream, not a workaround.

## Distribution

Releases ship one installable `pediment-plugin.zip`, staged as `plugins/pediment`.
It includes templates, patterns, tokens, assets, forms, and blocks; `plugin/.distignore`
excludes development-only files. Version metadata is patched by release-please —
never hand-bump `plugin/plugin.php` or `plugin/package.json` out of band.

## Commits

Conventional commits (`feat`/`fix`/`refactor`/`chore`/`docs`/`test`/`style`/`ux`),
imperative, ≤60-char summary. Stage by name, never `git add -A`. Co-Authored-By trailer.
`git push` only on explicit user instruction.
