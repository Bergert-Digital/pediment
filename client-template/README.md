# __PEDIMENT_NAME__

A standalone WordPress block theme built on [Pediment](https://github.com/Bergert-Digital/pediment).
The design system, blocks, templates and seeding engine ship in the **Pediment plugin**; this repo
holds only what is specific to __PEDIMENT_NAME__: brand tokens, page content, and the seed manifest.

## Local development

```bash
npm install
npm run env:start     # http://localhost:8888
npm run seed:plan     # see what a seed would create
npm run seed
```

## Changing content

The seed bootstraps the site: it creates the pages, posts, menus and media that
`seed/manifest.php` and `patterns/*.php` declare and the site does not have yet. It never
changes the content, title or slug of a page that exists, never rewrites a menu, and keeps the
reading settings and logo you chose. It only fills gaps: an empty front page, posts page or
logo, a missing language tag, and the translation links of pages it creates.

- **New page:** declare it in `seed/manifest.php`, add `patterns/<key>.php`, run `npm run seed`.
- **Existing page:** change it in the block editor, or with the Pediment MCP tools (see the
  Pediment repo's `docs/mcp.md`). Editing its pattern file does not change the site.
- **New environments should match a live page:** run `npm run adopt -- <page-key>` and commit
  the pattern file.

## Deploying

Push a `v*` tag. The release workflow builds `__PEDIMENT_SLUG__.zip` with the version header
stamped, and attaches it to the GitHub release. Install it via Appearance → Themes → Add New →
Upload, then use Settings → Pediment Theme → Seeding → Apply plan to create any page or menu
the release added. It adds only what is missing (pages, menus, media, missing language tags,
empty reading settings) and never changes anything a person made.

The Pediment plugin updates itself through wp-admin; this theme does not, by design.
