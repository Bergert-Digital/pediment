# AGENTS.md — __PEDIMENT_NAME__

A standalone Pediment client theme. The engine lives in the Pediment plugin, not here.

## Hard rules

- **Never write pages with `wp post create` or `wp post update`.** The seeder owns identity
  (`_pediment_seed_key`); a hand-written post has none, and the next seed creates a duplicate.
- A new page goes in `seed/manifest.php` plus `patterns/<key>.php`; `npm run seed` creates it.
  Run `npm run seed:plan` before `npm run seed`, always. A seed never changes a page that
  exists.
- Change a page that exists with the `pediment-write-page` MCP tool (validate the markup with
  client-kit's `validate-blocks` first) or in the block editor. To make new environments match
  it, run `npm run adopt -- <key>` — never copy markup out of the database by hand.
- Never edit plugin files to get client-specific behaviour. Use theme.json, patterns, template
  overrides in `templates/`, and the plugin's filters.
- Colours come from `theme.json` presets. Use `var(--wp--preset--color--…)`, never literals.
- Languages are declared in the manifest and configured with `npm run languages` **before**
  seeding, never after.

## Verification

```bash
npm run env:start
npm run seed:plan
npm run seed
```

Then load http://localhost:8888 and check the pages you changed at 375px, 768px and 1440px.
