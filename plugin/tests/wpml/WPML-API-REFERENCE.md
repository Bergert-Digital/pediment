# WPML API Reference (captured against WPML 4.9.7)

Ground truth for the WPML adapter, captured from the real plugin build shipped
in `plugin/wpml/wpml.zip` (inner dir `sitepress-multilingual-cms/`, entry
`sitepress.php`) running in the wp-env `tests-wordpress`/`cli` containers on
2026-08-28. Everything below is confirmed against the running plugin, not
inferred from docs.

## Language switcher block

WPML ships a **native block-editor block**. Two, in fact:

| Block name                          | Purpose                                              |
| ----------------------------------- | ---------------------------------------------------- |
| `wpml/language-switcher`            | Standalone language switcher (footer/content style). |
| `wpml/navigation-language-switcher` | Switcher designed to live inside a `core/navigation`. |

Registered server-side in
`classes/block-editor/Blocks/LanguageSwitcher.php` via
`register_block_type( 'wpml/language-switcher', [ 'api_version' => '3',
'render_callback' => [ Render, 'render_block' ] ] )`. It is a **dynamic block**
with no server-side attribute schema on the plain variant. (The navigation
variant declares three attrs, all with defaults: `navigationLsHasSubMenuInSameBlock=false`,
`layoutOpenOnClick=false`, `layoutShowArrow=true`.)

### The bare self-closing comment FATALS — do not emit it (corrects the Task-3 note)

An earlier revision of this file claimed a default insert serializes to a bare
`<!-- wp:wpml/language-switcher /-->` and that "the dynamic render_callback
still renders it on the front end." **That was wrong** and Task 11's e2e proved
it. WPML's `render_callback` is a *template filler*, not a generator:

- `Render::render_block()` calls `Parser::parse( $attrs, $savedHTML, ... )`.
- `Parser::parse()` **returns `null` the moment `$savedHTML` is empty**
  (`Parser.php:37`).
- `Render.php:39` then dereferences that null
  (`$languageSwitcherTemplate->getCurrentLanguageItemTemplate()`) → **Fatal
  error: Call to a member function getCurrentLanguageItemTemplate() on null**.

So the bare comment crashes the WPML front end wherever it renders (the block IS
registered and rendered on the front end — confirmed below, contradicting the
old "not registered on the front end" note too). Reproduced on the live WPML env
(WordPress 6.9, WPML 4.9.7) by publishing a page whose content was the bare
comment and requesting it over HTTP: HTTP body carried the fatal above, thrown
from `Render.php:39`.

### The renderable form: the native block WITH its saved `data-wpml` template

The block only renders when its **saved HTML carries the `data-wpml` item
template** that `Render` clones once per active language, filling in each
language's `href`, native name and `aria-label` from WPML's own Repository. That
template is language- and settings-agnostic — two languages or ten, en+de or any
pair, the same markup renders the live switcher.

`Parser` needs at least one `data-wpml="current-language-item"` and one
`data-wpml="language-item"`, each inside a container, each holding a
`data-wpml="link"` and a labelled `data-wpml="label"` (the `data-wpml-label-type="native"`
attr makes `Render` fill in the native language name; the link/label are found by
a `//` descendant XPath, so they may be nested). The placeholder `href="#"` is
overwritten per language at render time. **Order matters**: `Parser::parse()`
processes `current-language-item` first and *removes its subtree from the DOM*
before it queries `language-item`. So `language-item` must NOT be a descendant of
`current-language-item` — if it is, the removal strips it and non-current
languages never render. Keep the two templates siblings.

#### Default: hover-to-reveal dropdown that lists EVERY language

`WpmlProvider::languageSwitcherBlock()` emits a **dropdown** by default: the
current language is the always-visible toggle, and hovering opens a menu card
listing EVERY configured language (with en+de: both Deutsch and English; scales
to N).

##### The WPML Render limitation (why it is one list, not a toggle + sub-menu)

WPML's `Render::render_block()` iterates all active languages and, per language,
fills the `data-wpml="current-language-item"` node for the current language or
clones the `data-wpml="language-item"` node for a non-current one. **The current
language is filled exactly once** — into the single container `Parser` recorded
for `current-language-item`. You **cannot** place the current language in two
spots (e.g. a toggle AND a sub-menu): two `data-wpml="current-language-item"`
nodes in different parents FATAL in `Parser::getTemplateNode()`, whose
`$container->removeChild($item)` throws `DOMException: Not Found Error` when a
matched node is not a child of the last node's parent (reproduced live:
`Parser.php:92`). So a native "toggle + sub-menu-of-others" can only ever list the
OTHER languages, never the current one.

To list ALL languages we therefore emit a **single list** — one
`current-language-item` and one `language-item` as siblings in one `ul.wpml-ls-menu`.
Render fills the current node and clones the language node once per non-current
language, so the `<ul>` ends up with every active language:

```html
<!-- wp:wpml/language-switcher --><div class="wpml-language-switcher-block wpml-ls"><div class="wpml-ls-dropdown open-on-hover-click"><ul class="wpml-ls-menu"><li data-wpml="current-language-item" class="wpml-ls-item wpml-ls-current-language"><a data-wpml="link" href="#"><span data-wpml="label" data-wpml-label-type="native"></span></a></li><li data-wpml="language-item" class="wpml-ls-item"><a data-wpml="link" href="#"><span data-wpml="label" data-wpml-label-type="native"></span></a></li></ul></div></div><!-- /wp:wpml/language-switcher -->
```

The dropdown BEHAVIOUR (toggle + reveal + spacing) is the PLUGIN's own front-end
CSS in `plugin/assets/css/theme.css`, scoped to `.wpml-language-switcher-block`,
NOT WPML's block CSS (the single-list markup does not carry the
`wp-block-navigation__submenu-container` the block CSS keys off). The current
language (`.wpml-ls-current-language`) is `order:-1` and always visible (the
toggle); the other `.wpml-ls-item`s are `display:none` until
`.wpml-ls-dropdown:hover`/`:focus-within`, at which point the whole list opens as
a bordered, padded, shadowed menu card listing every language.

**Render evidence** (live WPML env, `do_blocks()` on the string above, styles
stripped), current language `de` — the one `<ul>` lists BOTH languages, the
current marked `wpml-ls-current-language`:

```html
<div class="wpml-language-switcher-block wpml-ls"><div class="wpml-ls-dropdown open-on-hover-click"><ul class="wpml-ls-menu"><li data-wpml="language-item" class="wpml-ls-item"><a data-wpml="link" href="http://localhost:8920/" aria-label="Switch to English"><span data-wpml="label" data-wpml-label-type="native">English</span></a></li><li data-wpml="current-language-item" class="wpml-ls-item wpml-ls-current-language"><a data-wpml="link" href="http://localhost:8920/de/" aria-label="Switch to Deutsch"><span data-wpml="label" data-wpml-label-type="native">Deutsch</span></a></li></ul></div></div>
```

Rendering with current language `en` mirrors it (Deutsch becomes the non-current
`language-item`, English the `current-language-item`). No fatal; real per-language
URLs; current language marked. It scales to N because Render clones the
`language-item` once per non-current language into the same `<ul>`. Verified
visually in the seeded header: collapsed shows the current language; hover opens a
roomy card listing Deutsch + English.

#### Opt-out: flat horizontal list

`['dropdown' => false]` emits the original flat list instead (all languages
shown inline):

```html
<!-- wp:wpml/language-switcher --><div class="wpml-ls wpml-ls-legacy-list-horizontal"><ul><li data-wpml="current-language-item" class="wpml-ls-item wpml-ls-current-language"><a data-wpml="link" href="#"><span data-wpml="label" data-wpml-label-type="native"></span></a></li><li data-wpml="language-item" class="wpml-ls-item"><a data-wpml="link" href="#"><span data-wpml="label" data-wpml-label-type="native"></span></a></li></ul></div><!-- /wp:wpml/language-switcher -->
```

`true`, any truthy non-array config, `['dropdown' => true]`, and any array
without a `dropdown` key all emit the dropdown; only `['dropdown' => false]`
selects this list.

### Why NOT the shortcode block (option (b) rejected)

WPML registers a `wpml_language_switcher` shortcode
(`WPML_LS_Shortcodes::LS`, gated by `setup_complete`) that expands to a full
`.wpml-ls` switcher. But `<!-- wp:shortcode -->[wpml_language_switcher]<!-- /wp:shortcode -->`
is **not viable in our placement**. The seeder puts the switcher inside a
`wp_navigation` referenced by the header **template part**, which renders via
`do_blocks()` — the core/shortcode block only runs `wpautop` on its content;
shortcode *expansion* happens in the separate `the_content` → `do_shortcode`
(priority 11) pass, which does NOT run over template-part / navigation output.
Verified: `do_blocks( "<!-- wp:navigation --><!-- wp:shortcode -->[wpml_language_switcher]<!-- /wp:shortcode --><!-- /wp:navigation -->" )`
returns the literal `[wpml_language_switcher]` text, unexpanded. It only expands
when placed in post content (where `the_content` applies `do_shortcode`), which
is not where the switcher lives. The native-block-with-template form has no such
dependency, so it is the chosen form.

### How this was verified
- Fatal: published a page with the bare comment, `curl` the permalink → HTTP
  body shows `Fatal error … getCurrentLanguageItemTemplate() on null … Render.php:39`.
- Render: `wp eval 'echo do_blocks( "<!-- wp:wpml/language-switcher -->…template…<!-- /wp:wpml/language-switcher -->" );'`
  in the `cli` container returned the populated `.wpml-ls` markup above.
- Shortcode-in-nav literal: `do_blocks()` of the nav+shortcode string returned
  the raw `[wpml_language_switcher]` text.
- Block registration on the front end: confirmed via the fatal stack trace,
  which runs through `WP_Block->render()` → `Render::render_block()` on a plain
  front-end request.

## Headless language activation

The confirmed-working activation used by `tests/wpml/bootstrap.php` (and
re-applied per class by `WpmlTestCase`), implemented in
`pediment_wpml_activate_languages()` in `tests/wpml/language-definitions.php`.

Setting only `icl_sitepress_settings['active_languages']` is **not** enough:
`apply_filters('wpml_active_languages', null)` reads the `active` flag column of
the `wp_icl_languages` table (which WPML seeds with every language row at
`active = 0`), so the flag must be flipped there. The reliable way to do that
headlessly is WPML's own installation API — the same calls its setup-wizard
`FinishStep` endpoint makes, minus the TM / roles / telemetry side effects:

```php
// 1. Seed the default language before activation; set_active_languages()
//    refreshes its cache against 'default_language'.
$settings = get_option( 'icl_sitepress_settings', [] );
$settings['default_language']       = 'en';
$settings['admin_default_language'] = 'en';
update_option( 'icl_sitepress_settings', $settings );
$GLOBALS['sitepress_settings'] = $settings;

// 2. Drive WPML's installation object (global $sitepress must exist).
$setup = wpml_get_setup_instance();      // new WPML_Installation( $wpdb, $sitepress )
$setup->finish_step1( 'en' );            // sets default lang, prepopulates translations, locale
$setup->set_active_languages( [ 'en', 'de' ] );
                                         // UPDATE wp_icl_languages SET active=1 WHERE code IN (...)
                                         // + locale map + refresh_active_lang_cache + reload
$setup->finish_installation();           // setup_complete=1, store_frontend_cookie, wpml_start_version

// 3. Belt-and-braces cache reload.
if ( function_exists( 'wpml_reload_active_languages_setting' ) ) {
    wpml_reload_active_languages_setting( true );
}
```

After this, in the same process:
- `defined('ICL_SITEPRESS_VERSION')` is true (WPML loaded).
- `apply_filters('wpml_active_languages', null)` returns an array keyed by
  `en` and `de`.
- `apply_filters('wpml_default_language', null)` returns `'en'`.

### Why per-class re-activation is needed
WP core's `WP_UnitTestCase_Base::tear_down_after_class()` commits a
`_delete_all_data()`. That does not touch WPML's custom `wp_icl_*` tables, but
the activation is cheap and idempotent, so `WpmlTestCase::wpSetUpBeforeClass()`
re-runs `pediment_wpml_activate_languages()` for every class to stay robust.

## Environment facts
- Real installed plugin directory name: **`sitepress-multilingual-cms`**
  (`wp plugin list` reports it `active`, version `4.9.7`).
- The env is provisioned from a **directory** mount
  (`./plugin/wpml/sitepress-multilingual-cms`, extracted from `wpml.zip`), not
  the `.zip` directly: a single-file `.zip` plugin mount fails on this Docker
  setup with "mountpoint is outside of rootfs". `plugin/wpml/` is git-ignored.
- Switching `.wp-env.override.json` to the WPML config cleanly **replaces**
  Polylang — `wp plugin list` shows no Polylang, only WPML. No in-bootstrap
  Polylang deactivation was necessary.
- WP-CLI lives in the `cli` / `tests-cli` containers, not `tests-wordpress`.
