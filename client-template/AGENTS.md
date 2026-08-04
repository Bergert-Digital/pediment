# AGENTS.md — __PEDIMENT_NAME__

A standalone Pediment client theme. The engine lives in the Pediment plugin, not here.

## Hard rules

- **Never write pages with `wp post create` or `wp post update`.** The seeder owns identity
  (`_pediment_seed_key`) and the content-arbitration hashes; a hand-written post bypasses both.
- Structure goes in `seed/manifest.php`; content goes in `patterns/*.php`. Run
  `npm run seed:plan` before `npm run seed`, always.
- To capture an edit made in the block editor, run `npm run adopt -- <key>` — never copy markup
  out of the database by hand.
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
