# Standards

Non-negotiable quality bars. CI enforces most of these; the rest are review gates.

## Block authoring contract (see `docs/blocks.md`)

Every block in `plugin/src/blocks/<name>/` has exactly: `block.json`, `index.tsx`, `edit.tsx`,
`render.php`, `style.scss`. Enforced by `npm run lint:blocks`.

- `block.json` has an explicit one-sentence `description` and fully-typed `attributes` with
  defaults. Namespace `pediment/` (plugin) or `client/` (client theme). API version 3.
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

## Reachable in the editor

Every text a person may change is reachable by a click in the editor canvas, on the text or on
the block whose sidebar holds it. Content hidden by state (tabs, panels, slides, accordions, mega
menus, scroll or animation states) shows in the editor when it, or its trigger, is selected. A
new block or pattern of this kind is tested in the editor before it ships. This is a review gate
on every block and pattern change; see [blocks.md](blocks.md#reachable-in-the-editor).

## Tests

- **PHPUnit:** every block has `plugin/tests/phpunit/BlockRender/<Name>Test.php` covering valid +
  edge-case (empty) attributes. Forms, patterns, and templates have suites.
  Run: `npx wp-env run tests-wordpress --env-cwd=wp-content/plugins/pediment-ai ./vendor/bin/phpunit`
- **Seeding:** a manifest-format, applier, differ, or verifier change needs a test in
  `plugin/tests/phpunit/Seeder/` (see `docs/seeding.md`). The seeder is bootstrap-only:
  it creates missing rows and never writes to a row that exists — a change that makes it
  write to one is a regression. The one exception is WordPress's own unedited draft
  "Privacy Policy" page, which goes to the trash when a created page needs its slug
  ([seeding.md](seeding.md#on-a-site-that-is-already-seeded)). `wp pediment seed --dry-run` must plan zero writes
  (`0 to write`) once everything the manifest declares exists.
- **Languages:** a manifest declaring `languages` makes a site multilingual; see
  [docs/seeding.md#languages](seeding.md#languages) for the manifest shape, the derived
  slug rule, and `wp pediment languages` / `wp pediment adopt --language`. A
  Polylang-specific change needs a test in `plugin/tests/polylang/` (its own PHPUnit
  config, `plugin/phpunit-polylang.xml.dist`), and everything Polylang-specific stays
  behind the `LanguageProvider` seam (`plugin/src/Language/`).
- **Playwright:** editor block insertion, front-page render, and forms.
  Run: `cd plugin && npm run e2e` (requires `npm run env:start`).
- A feature or fix is not done until its test passes and the relevant screenshot looks right.

## Linting & CI

`composer lint` (WPCS 3.4 + PHPCompatibilityWP, PHP 8.1+), `npm run lint:js`,
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
