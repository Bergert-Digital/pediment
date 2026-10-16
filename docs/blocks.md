# Authoring starter blocks

Every shared block in `plugin/src/blocks/<name>/` must contain exactly these files:

```
plugin/src/blocks/<name>/
  block.json     metadata + attributes
  index.tsx      entry: registers the block with @wordpress/blocks
  edit.tsx       editor UI (React)
  render.php     server-side render
  style.scss     styles (optional but expected)
```

The `lint:blocks` script enforces the three required files.

## block.json conventions

For AI agents to compose with a block, `block.json` must include:

- **`description`** — a one-sentence description of what the block does and when to use it.
- **Explicit `attributes`** — every attribute must be declared with a `type` and ideally a `default`. Don't rely on `supports` to declare implicit attributes.
- **`name` namespaced under `pediment/`** — for starter blocks. Client themes use `client/`.

Example:

```json
{
  "name": "pediment/my-block",
  "title": "My Block",
  "category": "pediment",
  "description": "Short, action-oriented description of what the block does.",
  "attributes": {
    "title": { "type": "string", "default": "" },
    "url":   { "type": "string", "default": "" }
  }
}
```

## Design tokens

No hex / rgb / hsl literals are allowed in `plugin/src/blocks/`. Use CSS custom properties from `plugin/tokens/theme.json`:

```scss
.starter-my-block {
  color: var(--wp--preset--color--text);
  background: var(--wp--preset--color--surface-elevated);
  padding: var(--wp--preset--spacing--30);
}
```

PHPCS sniff `Starter.NoColorLiteralSniff` and the `lint:colors` script will fail CI if you slip a literal in.

## Server-side rendering

Always render via `render.php`. For non-InnerBlocks blocks, the `save()` function returns no markup (handled by `registerBlockType` with no `save`). For InnerBlocks containers (`faq`, `prose`), `save` returns `<InnerBlocks.Content />` so child markup persists. This keeps content compatible across attribute changes and makes server-side variations (date formats, queries) trivial.

```php
// render.php
<?php
$wrapper = get_block_wrapper_attributes( array( 'class' => 'starter-my-block' ) );
?>
<div <?php echo $wrapper; ?>>
    <?php echo wp_kses_post( (string) ( $attributes['title'] ?? '' ) ); ?>
</div>
```

Sanitize aggressively: `wp_kses_post()` for rich text, `esc_html()` for plain strings, `esc_url()` for URLs, `esc_attr()` for attributes.

## Tests

Every block needs a test in `plugin/tests/phpunit/BlockRender/<Name>Test.php`. The minimum:

- Render with valid attributes → output contains expected substrings.
- Render with edge-case attributes (empty fields) → output handled gracefully (no PHP errors, no empty `<a href="">`).

## Slider panel colour

`pediment/slider` takes its panel background from `panelColor`, in one of
two forms:

- **A palette slug** (`"accent"`, `"surface-elevated"`): the slug must match
  `^[a-z0-9-]+$` and name a colour in the merged global palette
  (`wp_get_global_settings( array( 'color', 'palette' ) )`). The panel
  paints `var(--wp--preset--color--<slug>)` (kebab-cased the way WordPress
  names the var), so it follows the palette. The text colour is computed
  from the colour the slug resolves to, with the same luminance rule as for
  a hex (`pediment_slider_panel_fg()`): light text on a dark colour, dark
  text on a light one. When one slug is in several origins, the custom
  (Global Styles) colour wins over the theme's, and the theme's over core's
  default, which is the order the preset vars cascade in.
- **A colour** (`"#0A1B33"`, the default): used as it is, as before.

Anything else, including a slug that is not in the palette, is passed
through unchanged with light text, which is how an invalid value always
rendered. Prefer the slug in patterns: it keeps colour literals out of
content.

In the editor, picking a palette swatch stores its slug; a custom colour
stays hex. The preview resolves both forms the same way `render.php` does
(`panelColors()` in `plugin/src/blocks/slider/panel-fg.ts`).

## Legacy blocks

The 18 presentation blocks in `Pediment\Content\Policy::LEGACY` (hero, cta,
feature-grid, faq and the others) are legacy. They still render, so existing
pages keep working, but the inserter hides them (`supports.inserter` is set
to false when they register) and Pediment 5 deletes them. Do not use them in
new pages or patterns and do not add features to them. Build with core blocks
and named styles ([design-layer.md](design-layer.md)), and convert existing
content with the converter ([convert.md](convert.md)).

## Client blocks

Standalone client themes may register `client/*` blocks for client-owned
behavior. They must follow the same rendering, sanitization, token, and test
contract, but must not modify Pediment's `plugin/` files.
