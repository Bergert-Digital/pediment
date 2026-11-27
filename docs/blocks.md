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

Always render via `render.php`. For non-InnerBlocks blocks, the `save()` function returns no markup (handled by `registerBlockType` with no `save`). For InnerBlocks containers (`form`), `save` returns `<InnerBlocks.Content />` so child markup persists. This keeps content compatible across attribute changes and makes server-side variations (date formats, queries) trivial.

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

## Reachable in the editor

Build every block and pattern for the person who edits it in the block editor,
not only for the visitor. Every text a person may change must be reachable by
a click in the editor canvas: on the text itself, or on the block whose
settings sidebar holds it.

### Content hidden by state

Tabs, panels, slides, accordions, mega menus, and scroll or animation states
hide content on the front end. In the editor, that content shows when it, or
its trigger, is selected. Do not let front-end-only JavaScript decide what the
canvas shows: the canvas runs no view script, so a rule such as "invisible
until the script adds `is-in`" hides the content there for good.

Pediment's own blocks follow the rule:

- `pediment/slider` edits its slides in the sidebar. Opening a slide's panel,
  or using the arrows and dots in the canvas, shows that slide.
- `pediment/mega-menu` shows its panel open in the canvas (the "Show panel
  preview" toggle) and edits it in the sidebar. It is editable only in
  Site Editor → Navigation; elsewhere it is a read-only preview.
- `pediment/form` edits its success heading and message in the sidebar; the
  canvas shows the form.
- The `accordion` style is core Details. In the editor, a click on its
  summary opens it, and it stays open while a block inside it is selected.
- The entry animation (`data-reveal`, `assets/js/reveal.js`) runs on the
  front end only; the editor never hides content for it.

A new block or pattern of this kind is tested in the editor before it ships:
select the hidden content's text, and its trigger, and check that it shows.

### Panel sets

A pattern that shows one of several panels at a time (customer voices, tabs,
before/after) marks its blocks with three plain classes. The shared editor
script (`plugin/src/editor/panel-set.tsx`) then shows the right panel in the
canvas, and the theme needs no editor CSS:

| Class | On |
| --- | --- |
| `pediment-panels` | the block around the whole set |
| `pediment-panel` | each panel, in order |
| `pediment-panel-tab` | each trigger (tab, thumbnail, dot), in the panels' order; optional |

Panels and tabs can sit at any depth inside the set; a nested set counts its
own. While panel n, tab n, or a block inside one of them is selected, the
editor sets `data-state="n"` (zero-based) on the set. With no such selection
the set has no `data-state`.

The front end uses the same contract. The theme's script sets
`data-state="n"` on the set when a visitor picks panel n, and the theme's CSS
shows panel n for `[data-state="n"]` and the first panel while there is no
`data-state`:

```css
.pediment-panels .pediment-panel { display: none; }
.pediment-panels:not([data-state]) .pediment-panel:nth-child(1),
.pediment-panels[data-state="0"] .pediment-panel:nth-child(1),
.pediment-panels[data-state="1"] .pediment-panel:nth-child(2) { display: block; }
```

The attribute exists only in the editor canvas; it is never saved.
`plugin/tests/e2e/panel-set.spec.ts` covers it.

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
- **A hex colour** (`#rgb` or `#rrggbb`, the default is `"#0A1B33"`): hex
  values work as before.

Anything else is invalid: a slug that is not in the palette, a named colour
(`red`), an 8-digit hex, or a value such as `red;position:fixed`. An invalid
value gets the default panel, `var(--wp--preset--color--primary)`, with the
text colour computed from primary's palette colour. Nothing else is written
into the `style` attribute. Prefer the slug in patterns: it keeps colour
literals out of content.

In the editor, picking a palette swatch stores its slug; a custom colour
stays hex. A palette pick stores the **first** slug with that colour value
(custom, then theme, then default origin): when two slugs share one hex, the
first one wins, even if you clicked the other swatch. The preview resolves
every form the same way `render.php` does (`panelColors()` in
`plugin/src/blocks/slider/panel-fg.ts`).

## Icon

`pediment/icon` shows one icon from the plugin's Phosphor set (the slugs in
`plugin/assets/icons/`, the set `pediment_icon()` renders). It has two
attributes: `icon` (the slug) and `label`. An empty or unknown slug renders
nothing. Without a label the icon is decorative (`aria-hidden="true"`); with
a label the glyph gets `role="img"` and the label as `aria-label`.

```html
<div class="wp-block-pediment-icon is-style-tile">
  <span class="wp-block-pediment-icon__glyph" aria-hidden="true"><svg …></svg></span>
</div>
```

The wrapper is block-level and the glyph is inline, so the glyph follows the
parent's `text-align`: centred in a centred card, left in a left-aligned
one. Size and look belong on the glyph, not on the wrapper: a constrained
layout centres a block child with a fixed width. Without a style the glyph
is about 1.5em in the text colour. The plugin's `tile` style puts it on a
46px accent-tint square; a client theme overrides it with a `tile` partial
for `pediment/icon` in its `styles/blocks/` that targets
`&>.wp-block-pediment-icon__glyph`.

## Legal links

`pediment/legal-links` links the pages whose seed keys are in its `keys`
attribute, in that order (default: `legal-notice`, `privacy`, the Impressum
and the privacy policy every scaffold seeds). Each link shows the page's own
title. The block finds each page by `_pediment_seed_key` in the visitor's
language (`Pediment\Seeder\SeededPage`): the current language, else the
default language, else the oldest page with that key. So one footer part
serves every language, and the links work in every environment, whatever
the post IDs are. A draft or missing page is left out; with no page at all,
the block renders nothing.

```html
<nav class="wp-block-pediment-legal-links" aria-label="Legal">
  <ul class="wp-block-pediment-legal-links__list"><li><a href="…/impressum/">Impressum</a></li>…</ul>
</nav>
```

The default footer (`pediment/footer-default`) carries it. The editor's
sidebar takes other seed keys, comma-separated, for example `terms` for an
AGB page.

## Form success state

After a successful send, `pediment/form` swaps its fields for a confirmation
panel: a check icon, a heading and the `successMessage`. The heading comes from
the `successHeading` attribute; empty means "Message sent" (translatable). The
form keeps its height, so the content below it does not move, and the panel is
centred in that box. Focus moves to the heading, and the page scrolls only when
the panel is off-screen. "Send another message" empties the form and restores
the fields. Errors stay inline under the submit button, with the fields kept.

A client theme restyles the panel through `.pediment-form__success`,
`__success-icon`, `__success-heading`, `__success-message` and `__reset`, or
targets `.pediment-form[data-state='success']`.

## Form file fields

A `pediment/form-field` with `"fieldType":"file"` lets a visitor attach
documents, for example a CV:

```html
<!-- wp:pediment/form-field {"fieldType":"file","label":"CV","fieldName":"cv","required":true,"fileTypes":"pdf, docx","maxSize":10} /-->
```

- `fileTypes`: extensions, comma-separated. Default `pdf`. Only `pdf`, `doc`,
  `docx`, `odt`, `rtf`, `txt`, `jpg`, `jpeg` and `png` are possible (filter
  `pediment_form_file_extensions`); other values drop out.
- `maxSize`: limit per file in MB. Default 10. PHP's own limit
  (`upload_max_filesize`, `post_max_size`) applies when it is lower. A web
  server limit (nginx `client_max_body_size`, default 1 MB) must allow the
  size too; the form then shows "too large" instead of a result.
- `multiple`: up to 5 files in one field. Default false.

A form with a file field posts `multipart/form-data`. The server checks the
count, the size, the real file type (`wp_check_filetype_and_ext()`) and the
extension, and cleans the file name. The field's value in the submission, in
mails and in HTTP destinations is the list of file names.

Files are private. They go to `uploads/pediment-forms-<random>/<submission>-<random>/`
with a deny-all `.htaccess`; on nginx the random names keep the URL secret.
The filter `pediment_form_files_dir` moves the folder, for example outside the
web root. People open the files only from the submission in wp-admin, through
a download that checks `edit_post` on the submission. Deleting a submission
deletes its files; the retention setting (Settings → Pediment → General,
default 90 days, filter `pediment_form_retention_days`) deletes both.

An e-mail destination attaches the files. With `attach_files` off, or when
the files are larger than 10 MB together (filter
`pediment_form_mail_attachment_limit`), the mail links to the submission in
wp-admin instead. HTTP destinations get the file names only.

## Form confirmation mail

An e-mail destination can also send a confirmation to the sender. Set it
under Settings → Pediment → Form Destinations, or in the manifest:

```php
'confirmation' => array(
	'to_field' => 'email',             // form field with the sender's address
	'reply_to' => 'jobs@example.com',  // optional: a reply reaches the team
	'subject'  => 'Your application',
	'body'     => "Hello {{ field:name }},\n\nthank you. We reply within 5 days.",
),
```

The confirmation goes out once, when the submission is stored; "Retry
delivery" does not send it again. Its result is shown in the submission
(`_confirmation_status`) and never changes the delivery status.

A form that mails an address a visitor types must not become a spam relay:

- The mail carries only the owner's text. A `{{ field:… }}` token renders on
  one line, without tags, cut to 80 characters. `{{ all_fields }}` and
  secrets are refused; files are never attached.
- Every header except `To` comes from the settings. `To` must pass
  `is_email()` and carry no line break.
- At most 3 confirmations per address and 10 per IP address in one hour
  (filters `pediment_form_confirmation_limit_recipient`,
  `pediment_form_confirmation_limit_ip`; `pediment_form_client_ip` for a site
  behind a proxy).

## Legacy blocks

Pediment 5 removed the 18 presentation blocks in
`Pediment\Content\Policy::LEGACY` (hero, cta, feature-grid, faq and the
others). They are no longer registered. Pediment 6 removed the converter that
rewrote them. A post that still holds one shows it empty or unstyled. The
content rules treat it as an error ("`pediment/hero` was removed in Pediment 5;
rebuild it with core blocks and named styles"), so `pediment-write-page`
rejects it and `pediment-check-page` reports it, also in `legacyBlocks`.
`Policy::LEGACY` stays only to name the cause. Build with core blocks and
named styles ([design-layer.md](design-layer.md)).

### What to build instead

Rebuild a part of a page that still holds a legacy block with the tree in this
table. It is the tree the converter wrote in Pediment 4 and 5, with the
plugin's default styles:

| Legacy block | Build instead |
| --- | --- |
| `section-head` | constrained group with the plain class `pediment-section-head` (preset bottom margin) → `eyebrow` paragraph, heading, `lead` paragraph |
| `hero` default / centered | section group → h1, `lead` paragraph, `pill` button |
| `hero` media-bg | `media-card` cover (the image, primary overlay) → h1, `lead`, `pill` button |
| `hero` stat-card | columns: copy, `pill` + `pill-ghost` buttons, ticks list · `media-card` cover with a `card` group that holds a `stat` group and the metrics |
| `cta` | `panel` section group → h2, paragraph, `pill` + `pill-ghost` buttons |
| `feature-grid` + `feature` | grid group (up to 3 columns, 2 for four cards) of `card` groups → image or `tile` icon (`pediment/icon`), h3, text, link paragraph with the plain class `pediment-card-link` |
| `stat-grid` + `stat` | grid group (one column per stat, up to 4) of `stat` groups → value, label, context (only the parts that are set) |
| `steps` + `step` | `steps` grid group (up to 3 columns, 2 for four steps; numbers the steps) of groups → h3, text |
| `media-text` | `media-row` media & text block; inner blocks move unchanged |
| `testimonial-grid` + `testimonial` | grid group (up to 2 columns) of `quote-card` groups → quote with the byline, or a rounded avatar and byline |
| `logo-cloud` | `logos` section group → caption, a wrapping row with the logo images |
| `pull-quote` | `pull-quote` quote |
| `image-caption` | `framed` image with its caption |
| `prose` | `prose` constrained group; inner blocks move unchanged |
| `faq` + `faq-item` | vertical group of `accordion` details blocks; an answer made of `<p>` elements gives one paragraph per element |
| `testimonial` quote | a quote made of `<p>` elements gives one paragraph per element |

On a converted page, and in a rebuild that follows the table:

- Grids collapse on phones. Write `minimumColumnWidth` as
  `max(<floor>, <90/N>%)` and no `columnCount`: WordPress 6.9 ignores the
  minimum when `columnCount` is set, and the grid never collapses.
- The plain classes `pediment-section-head` and `pediment-card-link` let a
  client theme select a section head and a card's link paragraph without a
  structural selector such as `> p:last-child`.
- The `stat` style styles a stat's children by position: value, label,
  context. A label or context that is not in its position carries the class
  `pediment-stat-label` or `pediment-stat-context`, which the style reads.
- A feature's icon is a `pediment/icon` block in the `tile` style, the first
  block of the card.

## Client blocks

Standalone client themes may register `client/*` blocks for client-owned
behavior. They must follow the same rendering, sanitization, token, and test
contract, but must not modify Pediment's `plugin/` files.
