# WordPress Traps

A running list of platform-side quirks that bit us, with the symptom → cause → fix chain.
Read before debugging anything that smells like "but I wrote that correctly"; add to it
whenever a session uncovers a new one. Entries are ordered roughly by how much time they've
cost when missed.

## How to add to this file

For each new trap, follow the four-line shape:

```
### Title (one line, ≤ 70 chars)

**Symptom.** What the user / tester observes on screen or in a tool result.
**Cause.** The WordPress behavior or convention that produces it.
**Fix.** The concrete code action — file:line if possible.
**Catch it early.** The verification step (audit, grep, computed-style check) that
would surface it before merge.
```

Keep entries terse. If an entry needs paragraphs, it's two entries.

---

## Font-size slugs that start with a digit get hyphenated in the emitted CSS variable

**Symptom.** Headings render at the browser default (`18px`) despite theme.json declaring
larger fluid clamps; `getComputedStyle(root).getPropertyValue('--wp--preset--font-size--4xl')`
returns an empty string.
**Cause.** WordPress sanitizes preset slugs that begin with a digit by inserting a hyphen
between the digit and the letter that follows. A slug declared as `"4xl"` is emitted as
`--wp--preset--font-size--4-xl` (with hyphen). Any rule referencing `var(--wp--preset--font-size--4xl)`
(without hyphen) resolves to an undefined variable and falls back to the inherited size.
**Fix.** Match the slug to its sanitized form: rename the theme.json slug to `"4-xl"` (or
`"2-xl"` / `"3-xl"`) AND update every reference in `theme.json`'s `styles.elements` and in
`src/blocks/*/style.scss`. Don't mix the two forms.
**Catch it early.** When adding or editing a `settings.typography.fontSizes` entry, grep:
`grep -rn 'var(--wp--preset--font-size--' assets/ src/blocks/ theme.json` — every
reference must use the hyphenated form for any leading-digit slug.

---

## `has-global-padding` is auto-added and gets zeroed on nested instances

**Symptom.** Content sits edge-to-edge on narrow viewports despite `theme.json` declaring
`styles.spacing.padding` and the band's class list including `has-global-padding`.
Measuring `getComputedStyle(band).paddingLeft` returns `0px`.
**Cause.** WordPress automatically adds `has-global-padding` to `<main>` AND to every
full-bleed group inside a constrained layout. It then emits the reset rule
`.has-global-padding :where(.has-global-padding:not(.wp-block-block)) { padding-right: 0; padding-left: 0; }`
to prevent double-padding on nested instances. Because our bands sit inside `<main>`, the
band's own `has-global-padding` is suppressed by the nested-reset.
**Fix.** Apply `padding-inline` directly to the inner element using the root padding
variables, bypassing the `has-global-padding` mechanism. See
[assets/css/theme.css](../assets/css/theme.css) `.starter-band`:
```css
padding-inline: var(--wp--style--root--padding-left);
```
**Catch it early.** When adding a new band style, measure `paddingLeft/paddingRight` on
the band at 375 / 768 / 1440 px viewports. If it's `0` at any width, the nested-reset is
biting.

---

## `theme.json` `styles.spacing.padding` must be set, or root padding variables are empty

**Symptom.** `--wp--style--root--padding-left` resolves to an empty string at the document
root; every `has-global-padding` consumer renders with no gutter regardless of viewport.
**Cause.** The root padding CSS variables only exist if `theme.json` declares
`styles.spacing.padding.{left,right,top,bottom}`. Without explicit values, the
`has-global-padding` rule's `padding-right: var(--wp--style--root--padding-right)` resolves
to nothing.
**Fix.** Declare in [theme.json](../theme.json):
```json
"spacing": {
  "blockGap": "var(--wp--preset--spacing--40)",
  "padding": {
    "left":  "clamp(20px, 4vw, 40px)",
    "right": "clamp(20px, 4vw, 40px)"
  }
}
```
**Catch it early.** `npx wp-env run cli wp eval 'echo wp_get_global_stylesheet();' | grep -- '--wp--style--root--padding'` —
the four `--wp--style--root--padding-*` declarations must have non-empty values.

---

## `edit.tsx` DOM tree must mirror `render.php`'s structure (parity contract)

**Symptom.** A block looks correct on the front end but the editor preview shows the
wrong layout — fields appear in wrong columns/rows, RichText classes are missing,
non-editable visual chrome (figures, glass overlays, badge pills) is absent.
**Cause.** WordPress's block API runs two independent render paths: `render.php` produces
the visitor's HTML, `edit.tsx` produces the editor canvas. There is no auto-derivation
between them — every structural element, every BEM class on a RichText, and every
non-editable wrapper has to be hand-mirrored in `edit.tsx`. The moment one side gets a
new wrapper div or class, the other silently drifts.
**Fix.** Match the DOM structure in `edit.tsx` to `render.php` exactly. That includes
(a) wrapper divs that CSS grid/flex layouts depend on (`.starter-hero__col`,
`.starter-section-head__inner`), (b) BEM classNames on RichText tags
(`className="starter-cta__title"` on the title RichText, etc.), and (c) non-editable
visual chrome rendered via stateful JSX that reads the same attributes `render.php`
reads (figure → glass card with stat/metrics, image preview via `useSelect` against
`core` entities).
**Catch it early.** [tests/e2e/edit-render-parity.spec.ts](../tests/e2e/edit-render-parity.spec.ts)
asserts that a curated list of BEM selectors is present in both the editor canvas iframe
AND the front-end HTML for every block on the home page. Add new selectors there
whenever a block grows new visible structure; the test fails if a class lands on one
side and not the other.

---

## `wp_update_post` un-slashes `post_content`, corrupting block-attribute JSON

**Symptom.** Calling `wp_update_post` with `post_content` containing a block markup
comment like `<!-- wp:starter/hero {"headline":"<span class=\"accent\">…</span>"} -->`
results in stored content with stripped backslashes (`u003c` literal text or unbalanced
quotes), and the page crashes with
`PHP Fatal error: array_key_exists(): Argument #2 ($array) must be of type array, null given in block-supports/align.php`.
**Cause.** WordPress treats `post_content` as form input and applies `wp_unslash` before
saving. Backslashes intended as JSON escapes (`\"`, `<`) get stripped, breaking the
block-comment's JSON. `parse_blocks` then returns `attrs => null`, and any block-support
function (`wp_apply_alignment_support`, etc.) that calls `array_key_exists($key, $attrs)`
fatals on PHP 8.1+.
**Fix.** Never inject block-comment JSON into `post_content` via wp-cli/wp_update_post. To
update a page from a pattern, use `wp_update_post` with the pattern's *registered* content
(which is already correctly serialized by WP), or use `serialize_block( $block )` to
re-emit a block array with proper escaping. For demo content that needs inline HTML in
attributes, ship it inside the pattern's `.php` source — that file is plain text, not
form-submitted.
**Catch it early.** After any wp_update_post call that touches block markup,
`curl :8888/ | head -200` and look for `error-page` or `Critical error`. If wp-env logs
show `array_key_exists`, the JSON broke.

---

## `@wordpress/components` runtime still wears the `__experimental` prefix

**Symptom.** Editor shows "This block has encountered an error and cannot be previewed" on
a block that uses `ToggleGroupControl` / `ToggleGroupControlOption`. Console shows
`React.createElement: type is invalid — expected a string … but got: undefined`, pointing
into the offending block's `edit` bundle.
**Cause.** Several `@wordpress/components` exports graduated from `__experimental*` to a
public name in newer versions of the npm package, but the **runtime exposed by
`wp.components`** (the global the editor uses) still only has the experimental name in WP
6.x. A TypeScript build resolves `import { ToggleGroupControl } from '@wordpress/components'`
to a real symbol from the npm package, but at runtime the editor JS dependency extraction
maps `@wordpress/components` to `wp.components` — and `wp.components.ToggleGroupControl`
is `undefined`. React tries to render `undefined`, blows up the entire Edit component,
and the editor catches the throw at the block boundary.
**Fix.** Import via the experimental alias, even if your IDE flags it as deprecated:
```ts
import {
  __experimentalToggleGroupControl as ToggleGroupControl,
  __experimentalToggleGroupControlOption as ToggleGroupControlOption,
} from '@wordpress/components';
```
**Catch it early.** Before using any "newer" `@wordpress/components` API, probe the
runtime: `typeof window.wp.components.<ComponentName>`. If it's `'undefined'` but
`window.wp.components.__experimental<ComponentName>` is `'object'`, use the experimental
alias. Affected components observed at various points: `ToggleGroupControl`,
`ToolsPanel`, `HStack`, `VStack`, `Heading`, `NumberControl` (some have already
graduated; check per component, per WP version).

---

## WordPress normalizes self-closing void tags in pattern source

**Symptom.** A pattern PHPUnit assertion like
`$this->assertSame( starter_pediment_landing_content(), $home->post_content )` fails on
nothing but a one-character whitespace diff inside an `<img>` tag — `<img alt=""/>`
shows in the pattern source, `<img alt="" />` shows in the seeded `post_content`.
**Cause.** WordPress's content normalization (KSES / wptexturize / serialize_block round-
trips) inserts a space between the last attribute and the self-closing slash on void
elements like `<img>` and `<br>`. The pattern source is read verbatim, but stored
content goes through the normalization, so the two strings diverge.
**Fix.** Write self-closing void tags with the space in the pattern source from the
start: `<img alt="" />`, not `<img alt=""/>`. The same applies to other void elements
authored inline in pattern markup.
**Catch it early.** Run `phpunit --filter test_home_content_is_the_pattern` after any
pattern edit that adds `<img>` / `<br>` / `<hr>` etc. — if it fails on whitespace, this
is the cause.

---

## SVG sprites injected by `wp_body_open` don't reach the editor iframe

**Symptom.** A block that renders `<svg><use href="#ph-icon-name"></use></svg>` shows the
icon correctly on the front end but renders as an empty box (or text fallback) in the
block editor canvas, even though `edit.tsx` emits the same `<use>` reference.
**Cause.** The Phosphor sprite is printed via `add_action( 'wp_body_open', ... )`, which
fires on the public front-end HTML render. The block editor canvas is a separate iframe
whose document never invokes `wp_body_open`. `<use href="#id">` only resolves to symbols
in the same document, so the iframe's blocks can't reach the parent admin window's
sprite.
**Fix.** Add an `enqueue_block_editor_assets` action that ships an inline script which
injects the sprite into both the outer admin document AND the editor canvas iframe's
contentDocument, using a `MutationObserver` to handle the iframe being created
asynchronously and a `load` listener to handle its document being replaced. See
[inc/icons.php](../inc/icons.php) `starter_enqueue_editor_icon_sprite()` for the
canonical implementation.
**Catch it early.** Open a page with any `<use href="#ph-…">` block in the editor.
Inspect `document.querySelector('iframe[name="editor-canvas"]').contentDocument.getElementById('starter-icon-sprite')`
— it should be a `<div>` containing the sprite SVG. If null, injection isn't reaching
the iframe.

---

## RichText `allowedFormats={[]}` strips ALL inline formats — including custom ones

**Symptom.** A custom `registerFormatType` (e.g., `starter/accent`) doesn't appear in the
toolbar for a specific RichText field, even though it's registered correctly globally.
**Cause.** `allowedFormats={[]}` on a RichText component is an *exclusive* allowlist —
empty array means no formats. Custom formats are not auto-included.
**Fix.** Either omit `allowedFormats` entirely (allows all registered formats), or pass an
explicit list including the custom: `allowedFormats={['core/bold', 'core/italic', 'starter/accent']}`.
For the section-head block's eyebrow / headline / lead, we keep `allowedFormats={[]}`
intentionally — those fields should stay plaintext. The hero `headline` omits it so the
Accent button is reachable.
**Catch it early.** When registering a new format, grep for `allowedFormats={[]}` in the
blocks you expect it to appear in — those are the ones suppressing it.

---

## Editor canvas narrows with the inspector open; alignwide content fills it

**Symptom.** In the block editor, an `align:wide` block (e.g., the hero) spans the entire
canvas with no visible margins, contradicting the theme.json `wideSize: 1200px`.
**Cause.** With the right Page/Block inspector open, the editor canvas iframe is often
narrower than 1200 px (measured: ~999 px on a 1440-wide viewport). `alignwide` is "up to
wideSize" — when the canvas is narrower than wideSize, content stretches to fill the
canvas rather than shrinking. This is WordPress's intended behavior, not a bug.
**Fix.** No fix needed. Close the inspector to widen the canvas past 1200 and the content
will visibly center. Front-end is unaffected because the browser viewport is always wider
than 1200 on desktop.
**Catch it early.** Before reporting a "constrained layout broken in editor" bug,
measure: `document.querySelector('iframe[name="editor-canvas"]').getBoundingClientRect().width`.
If it's < 1200, the symptom is expected.

---

## The audit tool is verification, not optional research

**Symptom.** Layout fixes ship, the front page looks right at the author's viewport,
and the user reports new visual regressions on a different viewport, in the editor, or
on the testimonial / CTA / insights band that wasn't checked.
**Cause.** Single-viewport, single-band visual review misses ~80% of layout bugs. Padding
stacks, alignwide cascades, and `box-sizing` regressions only show up in specific
viewport ranges or specific bands.
**Fix.** Run [tools/audit-landing.mjs](../tools/audit-landing.mjs) after every layout
change. It captures per-band screenshots at 1440×900 next to the mockup sections, and
emits a JSON of computed `x / w / maxWidth / margin / padding / box-sizing` for every
band's wrapper, head, alignwide, feature grid, columns, h2, kicker. The side-by-side
`test-results/audit/index.html` is the verification source of truth.
**Catch it early.** Re-run the audit when you finish a fix; compare metrics.json
before/after; only claim "matches the mockup" when the screenshots agree visually AND
the metrics agree numerically.
