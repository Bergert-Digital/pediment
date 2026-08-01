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

## Registered plugin templates can return associative query results

**Symptom.** Opening the block editor emits `block-editor.php` notices and leaves its REST responses malformed.
**Cause.** In WP 6.9, `WP_Block_Templates_Registry::get_by_query()` preserves a registered template's namespaced key. `wp_get_post_content_block_attributes()` then incorrectly reads `$current_template[0]`.
**Fix.** Reindex `get_block_templates` results through `Pediment\Templates\Registrar::normalize_template_query_results()`.
**Catch it early.** `HomeTemplateTest::test_template_query_results_are_numerically_indexed()` and the plugin E2E suite exercise the affected editor request.

---

## Font-size slugs that start with a digit get hyphenated in the emitted CSS variable

**Symptom.** Headings render at the browser default (`18px`) despite the plugin token file declaring
larger fluid clamps; `getComputedStyle(root).getPropertyValue('--wp--preset--font-size--4xl')`
returns an empty string.
**Cause.** WordPress sanitizes preset slugs that begin with a digit by inserting a hyphen
between the digit and the letter that follows. A slug declared as `"4xl"` is emitted as
`--wp--preset--font-size--4-xl` (with hyphen). Any rule referencing `var(--wp--preset--font-size--4xl)`
(without hyphen) resolves to an undefined variable and falls back to the inherited size.
**Fix.** Match the slug to its sanitized form: rename the plugin token slug to `"4-xl"` (or
`"2-xl"` / `"3-xl"`) AND update every reference in `plugin/tokens/theme.json`'s
`styles.elements` and in `plugin/src/blocks/*/style.scss`. Don't mix the two forms.
**Catch it early.** When adding or editing a `settings.typography.fontSizes` entry, grep:
`grep -rn 'var(--wp--preset--font-size--' plugin/assets/ plugin/src/blocks/ plugin/tokens/theme.json` — every
reference must use the hyphenated form for any leading-digit slug.

---

## `has-global-padding` is auto-added and gets zeroed on nested instances

**Symptom.** Content sits edge-to-edge on narrow viewports despite the plugin token file declaring
`styles.spacing.padding` and the band's class list including `has-global-padding`.
Measuring `getComputedStyle(band).paddingLeft` returns `0px`.
**Cause.** WordPress automatically adds `has-global-padding` to `<main>` AND to every
full-bleed group inside a constrained layout. It then emits the reset rule
`.has-global-padding :where(.has-global-padding:not(.wp-block-block)) { padding-right: 0; padding-left: 0; }`
to prevent double-padding on nested instances. Because our bands sit inside `<main>`, the
band's own `has-global-padding` is suppressed by the nested-reset.
**Fix.** Apply `padding-inline` directly to the inner element using the root padding
variables, bypassing the `has-global-padding` mechanism. See
[plugin/assets/css/theme.css](../plugin/assets/css/theme.css) `.starter-band`:
```css
padding-inline: var(--wp--style--root--padding-left);
```
**Catch it early.** When adding a new band style, measure `paddingLeft/paddingRight` on
the band at 375 / 768 / 1440 px viewports. If it's `0` at any width, the nested-reset is
biting.

---

## Layout containers zero the last child's bottom margin

**Symptom.** A closing block before the footer computes `margin-bottom: 0` even after a
class rule sets bottom spacing, so the footer butts directly against the last line.
**Cause.** WordPress layout CSS normalizes block margins inside constrained/flow
containers, including the last child's block-end margin.
**Fix.** Put the closing gutter on padding instead of bottom margin. See
[plugin/assets/css/theme.css](../plugin/assets/css/theme.css) `.back-to-blog`.
**Catch it early.** Measure the gap from the last article block to the footer at
375 / 768 / 1440 px; also inspect computed `marginBottom` on the last child.

---

## `theme.json` `styles.spacing.padding` must be set, or root padding variables are empty

**Symptom.** `--wp--style--root--padding-left` resolves to an empty string at the document
root; every `has-global-padding` consumer renders with no gutter regardless of viewport.
**Cause.** The root padding CSS variables only exist if the effective token data declares
`styles.spacing.padding.{left,right,top,bottom}`. Without explicit values, the
`has-global-padding` rule's `padding-right: var(--wp--style--root--padding-right)` resolves
to nothing.
**Fix.** Declare in [plugin/tokens/theme.json](../plugin/tokens/theme.json):
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
comment like `<!-- wp:pediment/hero {"headline":"<span class=\"accent\">…</span>"} -->`
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
`$this->assertSame( pediment_pediment_landing_content(), $home->post_content )` fails on
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
[plugin/inc/icons.php](../plugin/inc/icons.php) `pediment_enqueue_editor_icon_sprite()` for the
canonical implementation.
**Catch it early.** Open a page with any `<use href="#ph-…">` block in the editor.
Inspect `document.querySelector('iframe[name="editor-canvas"]').contentDocument.getElementById('starter-icon-sprite')`
— it should be a `<div>` containing the sprite SVG. If null, injection isn't reaching
the iframe.

---

## RichText `allowedFormats={[]}` strips ALL inline formats — including custom ones

**Symptom.** A custom `registerFormatType` (e.g., `pediment/accent`) doesn't appear in the
toolbar for a specific RichText field, even though it's registered correctly globally.
**Cause.** `allowedFormats={[]}` on a RichText component is an *exclusive* allowlist —
empty array means no formats. Custom formats are not auto-included.
**Fix.** Either omit `allowedFormats` entirely (allows all registered formats), or pass an
explicit list including the custom: `allowedFormats={['core/bold', 'core/italic', 'pediment/accent']}`.
For the section-head block's eyebrow / headline / lead, we keep `allowedFormats={[]}`
intentionally — those fields should stay plaintext. The hero `headline` omits it so the
Accent button is reachable.
**Catch it early.** When registering a new format, grep for `allowedFormats={[]}` in the
blocks you expect it to appear in — those are the ones suppressing it.

---

## Editor canvas narrows with the inspector open; alignwide content fills it

**Symptom.** In the block editor, an `align:wide` block (e.g., the hero) spans the entire
canvas with no visible margins, contradicting the effective token `wideSize: 1200px`.
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

## Public REST endpoint with `__return_true` + ceremonial nonce

**Symptom.** A block (e.g. contact form) renders `data-rest-nonce` and its front-end JS
sends `X-WP-Nonce`, suggesting CSRF protection. But `curl -X POST` with no nonce — or a
deliberately wrong one — still returns 200. A reviewer flags the route as missing CSRF;
another points at the nonce and says it's fine. Both are partly right.
**Cause.** `permission_callback => '__return_true'` short-circuits authentication. WP's
`rest_cookie_check_errors` only rejects bad nonces for *logged-in* users; anonymous
requests with no nonce, an invalid nonce, or the right nonce are all accepted identically.
The nonce in the rendered HTML is theatre against anonymous clients.
**Fix.** Pick one and add an inline comment at the `register_rest_route` call so the next
reader knows the choice was deliberate:
- True public endpoint (contact form, public webhook): keep `__return_true`, **delete**
  the unused `data-rest-nonce` + `X-WP-Nonce` plumbing from the block's `render.php` and
  view JS. Rely on honeypot + time-trap + rate-limiting.
- Nonce-required endpoint: replace `__return_true` with a callback that calls
  `wp_verify_nonce( $request->get_header( 'x_wp_nonce' ), 'wp_rest' )`.
**Catch it early.** From a logged-out shell:
`curl -i -X POST -H 'Content-Type: application/json' -d '{}' http://localhost:8888/wp-json/<ns>/<route>`
If it returns 200 (or a validation 400, not a 401/403) and the front-end *also* sends
`X-WP-Nonce`, the nonce isn't doing anything — fix the gap or strip the nonce.

---

## Front-end block JS: prefer `viewScript` over `has_block()` + manual enqueue

**Symptom.** A `wp_enqueue_scripts` callback in `plugin/inc/assets.php` does
`if ( ! has_block( 'pediment/<name>' ) ) return;` and then `wp_enqueue_script(...)` by
hand. Works, but the pattern is duplicated per block; deps and version stay hand-rolled
and drift from what `@wordpress/scripts` would emit.
**Cause.** Two valid mechanisms exist. The manual one was added before `viewScript`
matured. As of API v3, declaring `"viewScript": "file:./view.js"` in `block.json` makes WP
(a) load the script only when the block is rendered, (b) wire deps + version from
`build/blocks/<name>/view.asset.php` automatically, and (c) respect `strategy => 'defer'`
when registered with the WP 6.3+ args.
**Fix.** Add `"viewScript": "file:./view.js"` to the block's `block.json`, move the JS
into `plugin/src/blocks/<name>/view.{ts,js}` so the build emits a sibling `.asset.php`, and
delete the matching `wp_enqueue_scripts` + `has_block` block in `plugin/inc/assets.php`. To
defer, hook `wp_script_attributes` and set `defer => true` on the generated handle, or
pass `'strategy' => 'defer'` via a `register_block_type_args` filter.
**Catch it early.** `grep -nE 'has_block\(' plugin/inc/` — every match is a
candidate for `viewScript` migration unless it's gating CSS or a shared library.

---

## Inline `echo "<script>"` in `wp_head` breaks under strict CSP

**Symptom.** The site works fine until a host or security plugin enables a strict
Content-Security-Policy header. Browser console then reports
`Refused to execute inline script because it violates the following CSP directive`, and
the no-FOUC `.anim` class never lands — a flash of unstyled content appears, or
animations never trigger.
**Cause.** `echo "<script>...</script>"` writes the tag without a `nonce` attribute. WP's
`wp_print_inline_script_tag()` (5.7+) emits the same script but auto-attaches the CSP
nonce registered by a security plugin via the `wp_inline_script_attributes` filter.
**Fix.** Replace `echo "<script>...</script>";` in `plugin/inc/assets.php`/`plugin/inc/icons.php` with
`wp_print_inline_script_tag( 'document.documentElement.classList.add("anim")' )` (or
`wp_get_inline_script_tag()` if you need the string). The same fix applies to the editor
icon-sprite injector in `plugin/inc/icons.php`.
**Catch it early.** `grep -rnE 'echo .*<script' plugin/inc/` — each match needs the
helper instead. Periodically smoke-test with a strict CSP header injected via
`.wp-env.override.json` and confirm the home page paints correctly.

---

## `theme.json` `styles.css` is inlined into every page's `<head>`

**Symptom.** PageSpeed flags "render-blocking inline CSS"; even a minimal page emits a
~3KB `<style>` block in `<head>` containing rules that have nothing to do with the
rendered blocks (Navigation submenu colors, CTA-button hovers, etc.). Caching is
impossible because the rules are inline, not enqueued.
**Cause.** Anything inside the effective token data's `styles.css` is concatenated into WP's global
stylesheet, which is emitted inline on every request. It's an escape hatch for selectors
the schema can't express, not a general-purpose stylesheet location.
**Fix.** Triage each rule in [plugin/tokens/theme.json](../plugin/tokens/theme.json) `styles.css`:
1. Block-scoped → move to `styles.blocks.<ns/name>` (where the Site Editor can override
   it) or to that block's `style.scss` / `viewStyle`.
2. True global layout (`.wp-site-blocks`, `<main>` flex chain) → keep in `plugin/tokens/theme.json`.
3. Theme chrome that doesn't belong to a block (header layout, custom utility classes) →
   move to [plugin/assets/css/theme.css](../plugin/assets/css/theme.css) and enqueue normally.
**Catch it early.** `curl -s http://localhost:8888/ | tr '<' '\n' | grep -c '^style'` —
the count, and the size of the first `<style>…</style>` block, should shrink after the
triage. Anything > ~500 bytes inline is a smell.

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

---

## Hashing seeded content before the write disables all future updates

**Symptom.** Every page reports "protected" on the first re-seed after the initial seed,
even though nothing was ever edited in the editor.
**Cause.** WordPress normalizes markup on write (KSES, wptexturize, block-comment
round-trips), so a hash computed from the intended input never matches a hash computed
from the persisted row — the two diverge on the very first write, and every page looks
client-edited forever after.
**Fix.** Hash the row WordPress actually stored, after the write:
`ContentHash::forPost()` (`plugin/src/Seeder/ContentHash.php`). A separate hash,
`_pediment_seed_source`, is computed from the git-side input and answers a different
question ("did the manifest change?") — never conflate the two.
**Catch it early.** `ApplierTest::test_reseeding_unchanged_content_plans_nothing`.

---

## KSES is active under WP-CLI and mangles block-comment JSON

**Symptom.** Seeded pages render as raw markup on the front end, or the request fatals
in `block-supports/align.php`.
**Cause.** `kses_init_filters()` is normally only active for logged-in users without
`unfiltered_html`; under WP-CLI there is no current user, so it runs on every
`wp_insert_post()` / `wp_update_post()` call and strips the backslashes out of
block-attribute JSON.
**Fix.** `Applier::apply()` suspends KSES for the duration of the write
(`Kses::suspend()` / `Kses::restore()`, `plugin/src/Seeder/Kses.php`), and every write
goes through `wp_slash()` first — seeded content is git-authored, not user input.
**Catch it early.** `ApplierTest::test_block_attribute_json_survives_the_write`.

---

## WordPress uniquifies a colliding post slug and the seeder looks successful

**Symptom.** A page seeded with slug `about` ends up served at `/about-2/`, with no
error anywhere in the seed report.
**Cause.** `wp_unique_post_slug()` silently renames a colliding slug instead of
erroring; a plan/apply step that only checks for a WP_Error return sees success.
**Fix.** The Verifier re-reads `post_name` after the write and compares it against the
manifest's declared slug, reporting a problem instead of retrying forever (a retry would
rewrite the row — and get uniquified again — on every single run without converging).
See `Applier::assertSlug()` and `Verifier::verify()` in `plugin/src/Seeder/`.
**Catch it early.** `VerifierTest::test_a_uniquified_slug_is_a_problem`.

---

## `get_post_status()` resolves an unattached attachment's raw status to `publish`

**Symptom.** A test asserts an attachment was "restored from trash" by checking
`get_post_status()`, and the assertion passes even when the restore silently failed to
flip the row back to `inherit`.
**Cause.** `get_post_status()` has special-case logic for attachments: an `inherit`
status with no living parent post resolves to `publish` for display purposes. That
resolution hides the real stored value from anything that trusts the helper.
**Fix.** Read the raw column instead: `get_post_field( 'post_status', $id )`. Restoring
a trashed attachment writes `post_status = 'inherit'` directly
(`plugin/src/Seeder/MediaSeeder.php`); assert against that literal value, not the
resolved one.
**Catch it early.** `MediaSeederTest::test_a_trashed_attachment_is_restored_rather_than_re_uploaded`.

---

## A theme's `patterns/` header metadata is cached for 30 minutes

**Symptom.** `wp pediment adopt` writes a brand-new pattern file to the theme's
`patterns/` directory, but the very next request (including the next `wp pediment seed`
in the same test run) still can't find it — `ContentResolver` reports the pattern as
unregistered.
**Cause.** WordPress caches a theme's scanned pattern-file headers in a site transient
for 30 minutes so it isn't re-reading every pattern file's docblock on every request. A
file written after that scan populated is invisible until the transient expires or is
explicitly cleared.
**Fix.** `Adopter::adopt()` calls `wp_get_theme()->delete_pattern_cache()` right after
writing the file, before returning.
**Catch it early.** `AdopterTest::test_the_next_seed_sees_an_adopted_page_as_unchanged`
models a fresh request's pattern re-scan and asserts the follow-up `Runner` run plans
nothing. PHPUnit runs the write and the re-seed in one process, so it can't reproduce the
real cross-process cache staleness — after touching `Adopter`, also manually verify with
two separate `wp` invocations: `wp pediment adopt <key>` then `wp pediment seed --dry-run`
and confirm the adopted pattern resolves instead of reporting "not registered".

---

## `suppress_filters` does not escape Polylang's query scoping

**Symptom.** A `WP_Query`/`get_posts()` call built with `suppress_filters => true`,
intended to read rows across every language in one pass, still comes back scoped to
whatever language Polylang thinks is current.
**Cause.** Polylang doesn't scope queries the way `suppress_filters` is designed to
block (the `posts_where`/`posts_join` filter chain). It hooks `parse_query` and mutates
`query_vars['tax_query']` directly; `WP_Query::get_posts()` re-parses that stored tax
query on a branch gated on `! $this->is_singular`, and nothing on that branch consults
`suppress_filters`. What Polylang *does* honour is the `lang` query var:
`PLL_Query::is_already_filtered()` treats `isset( $qvars['lang'] )` — not its value —
as "the caller already decided the scope."
**Fix.** Pass `'lang' => ''` explicitly (not omitted) alongside `suppress_filters =>
true` — the empty string satisfies `isset()` and tells Polylang to stand down; omitting
the key leaves it unscoped and Polylang re-applies its own current-language filtering.
See `LanguageProvider::unscopedQuery()` in `plugin/src/Language/PolylangProvider.php`.
**Catch it early.** `PolylangProviderTest::test_suppress_filters_alone_does_not_escape_the_scoping`
and `::test_unscoped_query_sees_every_language` (`plugin/tests/polylang/PolylangProviderTest.php`).

---

## Polylang holds its options in memory until `shutdown`

**Symptom.** Writing one of Polylang's settings mid-request appears to succeed —
no error, no exception — but the very next read in the same request returns the old
value, and the write never reaches the database at all.
**Cause.** Since Polylang 3.7, its settings object holds every option in a plain PHP
array and only flushes it to the `wp_options` row on the `shutdown` hook. A raw
`update_option()` call is invisible to the rest of the same request (Polylang never
re-reads the row) AND gets overwritten by that same stale in-memory copy when
`shutdown` fires — the correct write and the wrong one race, and the wrong one is
scheduled to run last.
**Fix.** Write through Polylang's own options object — `PLL()->options->merge( [...] )`
then `PLL()->options->save()` — never `update_option()`. Follow with
`PLL()->model->clean_languages_cache()`: language objects cache home URLs derived from
those options, and that cache outlives the write, so skipping it makes a genuinely
correct write look like it silently failed. See `PolylangSetup::configure()` in
`plugin/src/Language/PolylangSetup.php`.
**Catch it early.** `PolylangSetupTest::test_an_already_configured_site_reports_no_changes`
and `::test_language_roots_serve_the_front_page` (`plugin/tests/polylang/PolylangSetupTest.php`)
both fail if the write path regresses to `update_option()` or the cache-clean is dropped.

---

## `pll_save_post_translations()` replaces the whole translation group

**Symptom.** Linking a post's translations one language at a time — call the linking
function once per language, right after each is written — leaves only the languages
from the last call or two actually linked; earlier ones fall silently out of the group.
**Cause.** `pll_save_post_translations()` doesn't add to a translation group, it
REPLACES it with exactly the map handed to it. A per-language call's map only ever
contains the language just written, so every language written before it gets unlinked
by the next call. Invisible with two languages (the second call just looks like the
first "didn't work yet"); silent data loss with five.
**Fix.** Accumulate every language's post ID first, then call the link function exactly
once with the complete map, after every language has been written. See
`Applier::linkTranslationGroups()` and `NavSeeder::linkTranslationGroups()` (both in
`plugin/src/Seeder/`) and `LanguageProvider::linkTranslations()` in
`plugin/src/Language/PolylangProvider.php`.
**Catch it early.** `PolylangProviderTest::test_link_translations_makes_each_side_findable_from_the_other`.
Also see `docs/BACKLOG.md` (Medium) for the residual hazard this creates even when the
full map is always passed: dropping a language from the manifest and Polylang's own
config in the same run still unlinks it from every group site-wide, because the map
that run builds never contains that language for anyone.

---

## `wp_navigation` cannot be made translatable by clicking

**Symptom.** Polylang's Languages → Settings screen never lists `wp_navigation` among
the post types that can be made translatable — there's no checkbox to enable, no matter
what else is configured.
**Cause.** Polylang's settings screen only offers post types registered
`public => true` AND `_builtin => false`. WordPress core registers `wp_navigation` as
`public => false, _builtin => true` — it fails both conditions — and Polylang ships no
`wp_navigation`-specific handling of its own; its menu-translation UI targets classic
`nav_menu` terms, which a block theme's Site Editor doesn't use.
**Fix.** Filter `pll_get_post_types` and add `wp_navigation` only when
`$is_settings === false` — Polylang's "programmatically active" path. This shows up in
the UI as an always-on, disabled checkbox rather than one a site owner could untick and
silently lose every translated menu to. See
`pediment_polylang_translate_navigation_menus()` in `plugin/inc/polylang-compat.php`.
**Catch it early.** `PolylangSetupTest::test_wp_navigation_is_translatable` asserts
`wp_navigation` is present in Polylang's stored `post_types` option after
`wp pediment languages` runs.

---

## A ref-less `core/navigation` block resolves to the newest `wp_navigation` post

**Symptom.** A header built from `<!-- wp:navigation /-->` with no `ref` attribute
renders a menu nobody chose for that page — not necessarily the wrong language's menu,
but an unrelated one in the SAME language, most often right after a client creates a
new menu in the Site Editor.
**Cause.** Core resolves a ref-less `core/navigation` block through
`block_core_navigation_get_fallback_ref()`, which picks the most-recently-created
`wp_navigation` post by date, with no language awareness of its own. On a multilingual
site this looks safer than it is: Polylang's own query scoping already restricts that
fallback to the CURRENT language, so the danger is not "the wrong language's navigation
shows up everywhere" — it's a same-language collision. Any newer `wp_navigation` post
in the same language outranks the one the seeder wrote, and the most realistic source
of a newer one is a client making an unrelated menu in the Site Editor.
**Fix.** Bind the header's navigation block explicitly, per language, before core's own
fallback ever runs: filter `render_block_data` for a ref-less `core/navigation` block
and set `attrs.ref` to the seeded nav's post ID for the current language, falling back
to the default language and then to an unscoped lookup — never to nothing, since an
empty header is worse than a wrong-but-present menu. See
`pediment_bind_navigation_ref()` in `plugin/inc/nav-language.php`.
**Catch it early.** `NavBindingTest::test_german_gets_the_german_menu` and
`::test_english_gets_the_english_menu` (`plugin/tests/polylang/NavBindingTest.php`)
cover the language-scoped resolution; the Playwright fixture's same-language decoy-menu
test (added Task 15, `plugin/tests/e2e/`) proves the binding still holds against a
same-language `wp_navigation` post created after the seeded one, and was shown RED
without the binding and GREEN with it.

---

## Polylang does not hook `wp_unique_post_slug`

**Symptom.** Two languages declaring the same top-level slug (e.g. both wanting
`about`) land as `about` and `about-2` — a real slug collision the seeder cannot
distinguish from any other — and a slug-enforcing engine rewrites the row on every
single run without ever converging, because the rewrite gets re-uniquified identically
every time.
**Cause.** WordPress's slug uniquification (`wp_unique_post_slug()`) checks across the
whole `post_name` namespace, not scoped per language. Polylang gives every language its
own URL prefix but does not extend slug uniqueness checks to be language-aware, so
top-level pages in different languages still compete for one shared slug space.
**Fix.** Derive a non-default language's slug as `<slug>-<lang>` (the language *code*,
not a numeric suffix) rather than reusing the default language's slug, unless a
per-language `slug` override is declared. See `EntrySpec::slugFor()`
(`plugin/src/Seeder/EntrySpec.php`) and `NavSeeder`'s private `slugFor()`
(`plugin/src/Seeder/NavSeeder.php`) — same idiom, same reason, in both places identity
needs a `post_name` no other language's row will ever ask for.
**Catch it early.** `ManifestTest::test_a_missing_slug_derives_a_distinct_one`
(`plugin/tests/phpunit/Seeder/ManifestTest.php`); `VerifierTest::test_a_uniquified_slug_is_a_problem`
covers the same-slug-collision failure mode this rule exists to avoid in the first place.

---

## Polylang's option sanitizer silently strips `_builtin` post types

**Symptom.** Writing `wp_navigation` into Polylang's `post_types` option via
`PLL()->options->merge()` reports success — no error, `is_wp_error()` is false — but
reading the option straight back afterward shows `wp_navigation` is simply not there.
**Cause.** `Options\Business\Post_Types::get_object_types()`, which every `merge()`/
`set()` call is routed through (not only the settings screen), intersects whatever is
passed against `get_post_types( [ '_builtin' => false ] )`. WordPress core registers
`wp_navigation` with `_builtin => true` (`wp-includes/post.php`,
`create_initial_post_types()`), so it fails that filter and gets silently dropped from
the value being stored — the write "succeeds" and stores everything except the one
entry the caller actually needed. The RUNTIME read path Polylang uses to decide whether
a post type is translatable does not gate on `_builtin`, so once a value is actually
stored, `wp_navigation` is genuinely treated as translatable — the bug is purely in the
write path's sanitizer, not in how the setting is later honoured.
**Fix.** Flip WordPress's own registered post-type object's `_builtin` flag to `false`
for the span of the single `merge()` call, then restore it immediately — nothing else
observes the flip, and every other `_builtin` check core makes for `wp_navigation`
elsewhere in the same request (admin menus, capability mapping, REST) keeps seeing
`true`. See `PolylangSetup::configure()` (`plugin/src/Language/PolylangSetup.php`,
around the `$navigationType->_builtin` lines).
**Catch it early.** `PolylangSetupTest::test_wp_navigation_is_translatable` reads the
option back after `configure()` runs, which is the only way to catch a sanitizer that
accepts a write and then quietly discards part of it.

---

## Polylang's boolean options return PHP `bool` from `get()`, not `0`/`1`

**Symptom.** An idempotency check that compares Polylang's current option value
against the integer `0` or `1` (Polylang's own historic wire format for these settings)
never reports "no change" — it reports a pending write on every single call, including
immediately after applying that exact write.
**Cause.** `media_support`, `redirect_lang`, and `hide_default` round-trip through
Polylang's `Options\Primitive\Abstract_Boolean` type, which normalizes the stored
`0`/`1` into a real PHP `bool` on the way out of `get()`. `(array) false !== (array) 0`
is true in PHP forever, so a diff written against integers treats an already-configured
site as different from itself on every check — not a rare edge case, but the return
value of `get()` for these three keys unconditionally.
**Fix.** Cast the current value to `int` before comparing, whenever the current value
is a `bool`:  `if ( is_bool( $current ) ) { $current = (int) $current; }`. See
`PolylangSetup::configure()` (`plugin/src/Language/PolylangSetup.php`) — the diff loop
just above the `merge()` call.
**Catch it early.** `PolylangSetupTest::test_an_already_configured_site_reports_no_changes`
— configures the site twice and asserts the second call's `changes` array is empty; it
fails immediately if this cast regresses.

---

## Polylang auto-tags a translated post type's posts on save

**Symptom.** A test fixture built to model an untagged, pre-Polylang legacy post (no
language assigned) behaves exactly like a normal English post instead — a code path
meant to exercise "no language tag at all" is never actually reached, and the test
passes for the wrong reason.
**Cause.** `PLL_CRUD_Posts::save_post()` assigns the site's default language to ANY
post of a translated post type that's saved without one. Once `wp_navigation` is made
translatable (see "`wp_navigation` cannot be made translatable by clicking," above),
every `wp_navigation` post created through the normal factory/insert path — including
test fixtures meant to be untagged — is auto-tagged the default language on creation,
silently. This makes a test non-discriminating without making it fail: the assertion
still passes, but it's exercising the wrong branch of the code under test.
**Fix.** To model a genuinely untagged post, explicitly strip the language term after
creation: `wp_delete_object_term_relationships( $id, 'language' )`. See
`NavBindingTest::test_an_untagged_legacy_nav_is_found_by_the_unscoped_fallback`
(`plugin/tests/polylang/NavBindingTest.php`).
**Catch it early.** There is no automated catch for this one — it's a silent test-gap,
not a runtime failure. When writing a Polylang PHPUnit fixture that needs to model "no
language assigned," always call `wp_delete_object_term_relationships()` after creation
and confirm with a white-box assertion (query with `lang => ''`) that the post is
actually unscoped, rather than trusting that omitting the language on create left it
that way.

---

## `pll_current_language()` fires no filter

**Symptom.** `add_filter( 'pll_current_language', $callback )` in a test, meant to pin
the "current" language for the duration of an assertion, has no effect at all — the
function under test keeps behaving as though the site's default language is current, no
matter what the filter returns.
**Cause.** `pll_current_language()` reads `PLL()->curlang` directly and never routes
its return value through `apply_filters()`. It looks like every other WordPress
accessor, but the filter hook of the same name simply doesn't exist on the read path —
`add_filter()` registers a callback that's never called.
**Fix.** Set the property directly, and restore it afterward:
`PLL()->curlang = PLL()->model->get_language( $language );` — this is also what every
real request path (frontend, REST, admin) does to establish the current language, so
it's the correct simulation, not a workaround. See
`NavBindingTest::switchTo()` (`plugin/tests/polylang/NavBindingTest.php`).
**Catch it early.** Any test asserting language-scoped behavior that appears to pass
regardless of which language it "sets" via `add_filter( 'pll_current_language', ... )`
is a red flag — rewrite it to set `PLL()->curlang` and confirm the test goes RED for the
wrong language before it goes GREEN for the right one.

---

## An `enum` inside a block attribute's `items.properties` makes core drop the whole array

**Symptom.** A block attribute holding an array of objects (e.g. a list of social links,
each `{ platform, url }`) renders completely empty — not just the one entry with an
unexpected value, every entry in the array — the moment any single item's value falls
outside a declared `enum`, even an empty string.
**Cause.** For attributes not sourced from markup, `WP_Block_Type::prepare_attributes_for_render()`
validates the ENTIRE attribute value against its `block.json` schema via
`rest_validate_value_from_schema()`. If validation fails anywhere inside a nested
array/object — one item's `platform` not matching the declared `enum` — WordPress
`unset()`s the whole top-level attribute and reverts it to its schema default (`[]` for
an array), not just the offending item. `items` in `block.json` is NOT inert metadata:
core actively validates against it for any attribute that round-trips through post meta
or block attributes rather than parsed markup.
**Fix.** Don't declare `enum` on an `items.properties` sub-key unless the value is
genuinely closed — if the block's own `render.php` supports arbitrary values with a
fallback (as `pediment/social-links` does for `platform`, rendering a text-label
fallback for any unrecognized value), omit that property from `items.properties`
entirely rather than constraining it. `rest_validate_object_value_from_schema()` skips
properties present in the runtime value but not listed in the schema — omission is
silently safe; a mismatched `enum` is silently catastrophic. See
`plugin/src/blocks/social-links/block.json` (only `url` is declared; `platform` is
deliberately absent — a first attempt declared it with an `enum` and was reverted after
this exact regression surfaced in `SocialLinksTest`).
**Corollary.** Nothing about `items` being "just JSON Schema metadata for tooling" is
true in WordPress specifically — treat any new `items.properties` sub-key as something
core will enforce at render time, not merely document.
**Catch it early.** `SocialLinksTest::test_unknown_platform_renders_text_label_fallback_with_ucfirst`
and `::test_skips_entries_with_empty_platform_or_url` — both regressed to a fully empty
`links` array the one time `platform` briefly carried an `enum` during development; run
the full PHPUnit suite after adding or changing any `items.properties` entry, not just a
lint pass, since no linter in this repo checks block-attribute schemas for this hazard.
