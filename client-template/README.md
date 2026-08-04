# __PEDIMENT_NAME__

A standalone WordPress block theme built on [Pediment](https://github.com/Bergert-Digital/pediment).
The design system, blocks, templates and seeding engine ship in the **Pediment plugin**; this repo
holds only what is specific to __PEDIMENT_NAME__: brand tokens, page content, and the seed manifest.

## Local development

```bash
npm install
npm run env:start     # http://localhost:8888
npm run seed:plan     # see what a seed would change
npm run seed
```

## Changing content

Structure — which pages exist, their slugs, nesting, nav membership — lives in
`seed/manifest.php`. Content lives in `patterns/*.php`. Edit either, then run `npm run seed`.

A page a client has edited in the editor is protected: the seeder compares a content hash and
leaves edited pages alone, reconciling structure only. To bring a client's live edit back into
git, run `npm run adopt -- <page-key>`.

## Deploying

Push a `v*` tag. The release workflow builds `__PEDIMENT_SLUG__.zip` with the version header
stamped, and attaches it to the GitHub release. Install it via Appearance → Themes → Add New →
Upload, then re-seed from Settings → Pediment Theme → Seeding.

The Pediment plugin updates itself through wp-admin; this theme does not, by design.
