# Standards

Non-negotiable quality bars. CI enforces most of these; the rest are review gates.

## Block authoring contract (see `docs/blocks.md`)

Every block in `src/blocks/<name>/` has exactly: `block.json`, `index.tsx`, `edit.tsx`,
`render.php`, `style.scss`. Enforced by `npm run lint:blocks`.

- `block.json` has an explicit one-sentence `description` and fully-typed `attributes` with
  defaults. Namespace `starter/` (parent) or `client/` (child). API version 3.
- Rendered server-side via `render.php`. No `save()` markup for atomic blocks;
  `<InnerBlocks.Content />` for containers.
- Output is sanitized: `wp_kses_post()` for rich text, `esc_html()` for strings, `esc_url()`
  for URLs, `esc_attr()` for attributes. No exceptions.

## Design tokens

No hex / rgb / hsl literals anywhere under `src/blocks/`. Use `var(--wp--preset--…)` from
`theme.json`. Enforced by `npm run lint:colors` **and** the `Starter.NoColorLiteralSniff`
PHPCS sniff. The only place a hex literal is allowed is a child theme's `theme.json` palette.

## Empty / partial / hostile states

Every block must render correctly with default/empty attributes and with partial input. No
broken markup (`<a href="">`), no PHP notices, no unsanitized output. This is a review gate
on every block change, not just new blocks.

## Tests

- **PHPUnit:** every block has `tests/phpunit/BlockRender/<Name>Test.php` covering valid +
  edge-case (empty) attributes. Contact form, patterns, seed, and brand registry have suites.
  Run: `npx wp-env run tests-wordpress --env-cwd=wp-content/themes/wp-starter-theme vendor/bin/phpunit`
- **Playwright:** editor block insertion, front-page render, brand settings, contact form.
  Run: `npm run e2e` (requires `npm run env:start`).
- A feature or fix is not done until its test passes and the relevant screenshot looks right.

## Linting & CI

`composer lint` (WPCS 3.1 + PHPCompatibilityWP, PHP 8.1+), `npm run lint:js`,
`npm run lint:blocks`, `npm run lint:colors`. CI (`.github/workflows/ci.yml`) runs phpcs,
lint-blocks, phpunit, and e2e on every PR and push to main. Red CI never merges.

## Extensibility discipline

Parent files are read-only from a child theme's perspective. Brand Settings extends only via
`starter_brand_fields` / `starter_brand_sections`. A change that forces a child to edit a
parent file is a parent-API gap to fix upstream, not a child workaround.

## Distribution

Releases go out as installable zips via each repo's `workflow_dispatch` `release.yml`, with a
`.distignore` controlling what ships. The parent zip excludes `src/`/`tools/`/`docs`; the
child zip stays forkable (keeps `src/`). Version metadata is patched in the release commit —
never hand-bump `style.css` / `functions.php` / `package.json` out of band.

## Commits

Conventional commits (`feat`/`fix`/`refactor`/`chore`/`docs`/`test`/`style`/`ux`),
imperative, ≤60-char summary. Stage by name, never `git add -A`. Co-Authored-By trailer.
`git push` only on explicit user instruction.
