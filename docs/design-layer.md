# Design layer

Pages get their look from **named block styles**, never from values typed into
blocks. A style is a WordPress block style variation: a slug, a label, the
block types it applies to, theme.json style data, and optional `css`.

## Layers

A higher layer overrides a lower one with the same slug, property by property.
A `css` string in a higher layer replaces the lower layer's `css` completely.

| Layer | Where | Who changes it |
| --- | --- | --- |
| Plugin defaults | `plugin/styles/*.json` | Pediment releases |
| Client theme | `<theme>/styles/blocks/*.json` | Developer, in git |
| Site | private `pediment_style` posts (revisioned) | AI through MCP; `wp eval` in dev |

The site layer is not in the user origin. The plugin injects it through the
`wp_theme_json_data_theme` filter, after core adds plugin (registry) styles and
theme `styles/**.json` partials, so precedence is plugin < theme < site. A
person's own Site Editor (user-origin) edits sit above all three.

## Rules (checked on every layer)

- Colours, font sizes, font families, padding, margin, gap, border radius and
  shadows use tokens: `var:preset|color|accent`, `var(--wp--preset--…)`,
  `var(--wp--custom--…)`. `0`, `inherit`, `currentColor`, `transparent` and
  percentages are allowed. Other values (line widths, letter spacing,
  transforms) are free.
- In `css`: no `!important` and no literal colours (hex, rgb()/hsl()/color-mix()…,
  named colours, or colour fallbacks inside `var()`). Spacing, radius and font
  sizes inside `css` are not checked — `css` is the escape hatch for what
  theme.json cannot express; prefer the JSON properties, which are checked.
- `css` follows the grammar core renders (`process_blocks_custom_css()`):
  optional bare declarations first (they apply to the style itself), then
  rules `&<selector>{declarations}`, for example `&::before{…}`,
  `&:hover{…}`, `& > .x{…}` or `&{…}`. One `&` rule per selector: no
  at-rules (`@media` and the like), no nested rules, no selector lists
  (`&.a, &.b`; commas inside `:is(…)` are fine), and no `&` anywhere except
  at the start of a rule.
- No `<` anywhere in a style, in `css` or in any JSON value: core prints
  styles into an inline `<style>` tag.
- Slugs `feed`, `rss`, `rss2`, `rdf`, `atom` and `embed` are reserved.

Invalid plugin styles are skipped. Invalid theme styles still load but are
listed with warnings under Settings → Pediment Theme → Design. Invalid site
styles are rejected on save.

## Tokens

Radius: `--wp--custom--radius--sm|md|lg|pill`. Spacing: `var:preset|spacing|20…80`.
Font sizes: `xs, sm, base, lg, xl, 2-xl, 3-xl, 4-xl`. Shadows: `subtle, medium, lifted, focus`.

## Moving site styles into the theme

1. Settings → Pediment Theme → Design → **Download site styles as theme files**,
   or `wp pediment design export` in a dev environment.
   When the theme already ships a partial with the same slug, the exported
   file keeps that partial's properties and block types and layers the site
   values on top.
2. Commit the files to the client theme under `styles/blocks/`, release it.
3. After the update, the site layer marks those styles as *promoted* and stops
   loading them. A site style is promoted only when the theme partial with
   its slug covers its block types and already holds every site value; a site
   override that differs from the theme stays active. Promotion runs on the next wp-admin page load by a user who can
   edit theme options (not on ajax requests). Nothing is deleted.

## Commands

    wp pediment design list [--format=json]
    wp pediment design export [--dir=<dir>]
