# Authoring starter blocks

Every block in `src/blocks/<name>/` must contain exactly these files:

```
src/blocks/<name>/
  block.json     metadata + attributes
  index.tsx      entry: registers the block with @wordpress/blocks
  edit.tsx       editor UI (React)
  render.php     server-side render
  style.scss     styles (optional but expected)
```

The `lint:blocks` script enforces the three required files.

## block.json conventions

For the AI plugin (Plan B) to compose with a block, `block.json` must include:

- **`description`** — a one-sentence description of what the block does and when to use it. Goes directly into the AI tool schema.
- **Explicit `attributes`** — every attribute must be declared with a `type` and ideally a `default`. Don't rely on `supports` to declare implicit attributes.
- **`name` namespaced under `pediment/`** — for starter blocks. Client themes use `client/`.

Example:

```json
{
  "name": "pediment/my-block",
  "title": "My Block",
  "category": "pediment",
  "description": "Short, action-oriented description used by the AI composer.",
  "attributes": {
    "title": { "type": "string", "default": "" },
    "url":   { "type": "string", "default": "" }
  }
}
```

## Design tokens

No hex / rgb / hsl literals are allowed in `src/blocks/`. Use CSS custom properties from `theme.json`:

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

Every block needs a test in `tests/phpunit/BlockRender/<Name>Test.php`. The minimum:

- Render with valid attributes → output contains expected substrings.
- Render with edge-case attributes (empty fields) → output handled gracefully (no PHP errors, no empty `<a href="">`).
