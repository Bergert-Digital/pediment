# AGENTS.md — __PEDIMENT_NAME__

A standalone Pediment client theme. The engine lives in the Pediment plugin, not here.

## Hard rules

- **Never write pages with `wp post create` or `wp post update`.** The seeder owns identity
  (`_pediment_seed_key`); a hand-written post has none, and the next seed creates a duplicate.
- A new page goes in `seed/manifest.php` plus `patterns/<key>.php`; `npm run seed` creates it.
  Run `npm run seed:plan` before `npm run seed`, always. A seed only adds what is missing and
  never changes a page, menu, translation or setting a person made.
- Change a page that exists with the `pediment-write-page` MCP tool (validate the markup with
  client-kit's `validate-blocks` first) or in the block editor. To make new environments match
  it, run `npm run adopt -- <key>` — never copy markup out of the database by hand.
- Never edit plugin files to get client-specific behaviour. Use theme.json, patterns, template
  overrides in `templates/`, and the plugin's filters.
- Colours come from `theme.json` presets. Use `var(--wp--preset--color--…)`, never literals.
- Build every block and pattern for the person who edits it in the block editor. Every text a
  person may change must be reachable by a click in the editor canvas. Content hidden by state
  (tabs, panels, slides, accordions, mega menus, scroll or animation states) shows in the editor
  when it, or its trigger, is selected. For one-of-several panels, use the classes
  `pediment-panels`, `pediment-panel` and `pediment-panel-tab` and key the front end on
  `data-state`: the plugin then shows the selected panel in the editor, with no editor CSS in the
  theme (Pediment's `docs/blocks.md`, "Panel sets"). Test a new block or pattern of this kind in
  the editor before it ships: select the hidden text, and its trigger, and check that it shows.
- Languages are declared in the manifest and configured with `npm run languages` **before**
  seeding, never after.
- **Before launch, run `/pediment:launch`.** Launch means the first production deploy (the
  first `v*` tag that goes to the live site), a domain switch, or "go live". Its checklist is
  `docs/launch.md`: legal pages, consent, forms, SEO, operations and handover. Do not tag a
  first release or switch the domain while an item in `docs/launch.md` is `[ ]`. Remind the
  user when they ask for one of these steps and items are open.

## Verification

```bash
npm run env:start
npm run seed:plan
npm run seed
```

Then load http://localhost:8888 and check the pages you changed at 375px, 768px and 1440px.
