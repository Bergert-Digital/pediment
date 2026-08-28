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

#### Default: hover-to-reveal dropdown

`WpmlProvider::languageSwitcherBlock()` emits a **dropdown** by default: the
current language is a toggle, the other languages live in a sub-menu hidden until
hover. The dropdown is expressed purely in the saved markup's structure + CSS
classes (the block has no dropdown *attribute*):

```html
<!-- wp:wpml/language-switcher --><div class="wpml-language-switcher-block wpml-ls"><div class="wpml-ls-dropdown open-on-hover-click"><ul class="wp-block-navigation__container"><li class="wp-block-navigation-item has-child wp-block-navigation-submenu open-on-hover-click"><div class="wp-block-navigation-item__content wp-block-navigation-submenu__toggle" aria-expanded="false" aria-haspopup="true" aria-controls="wpml-ls-submenu-default" tabindex="0"><span data-wpml="current-language-item" class="wpml-ls-item wpml-ls-current-language current-language-item"><a data-wpml="link" href="#"><span data-wpml="label" data-wpml-label-type="native"></span></a></span></div><ul id="wpml-ls-submenu-default" class="wp-block-navigation__submenu-container"><li data-wpml="language-item" class="wpml-ls-item wp-block-navigation-item"><a data-wpml="link" href="#"><span data-wpml="label" data-wpml-label-type="native"></span></a></li></ul></li></ul></div></div><!-- /wp:wpml/language-switcher -->
```

The hover behaviour is the block's OWN front-end CSS (injected by
`Loader::frontendPrintStyleIfBlockIsUsed` whenever the block renders), NOT the
legacy-dropdown template CSS. It hides the sub-menu by default
(`.wpml-language-switcher-block .wpml-ls-dropdown .has-child .wp-block-navigation__submenu-container{display:none}`)
and reveals it on hover
(`.wpml-language-switcher-block .wpml-ls-dropdown .has-child:not(.open-on-click):hover > .wp-block-navigation__submenu-container{...visible}`).
Because the reveal selector is a *descendant* chain, `wpml-language-switcher-block`
must be an ANCESTOR of `wpml-ls-dropdown`, not the same node — hence the two
nested wrapper `<div>`s. The `current-language-item` (in the toggle) and the
`language-item` (in the sub-menu `<ul>`) are siblings, so Parser finds both.

**Render evidence** (live WPML env, `do_blocks()` on the string above, styles
stripped), current language `de` — toggle = current (Deutsch), sub-menu = other
(English):

```html
<div class="wpml-language-switcher-block wpml-ls"><div class="wpml-ls-dropdown open-on-hover-click"><ul class="wp-block-navigation__container"><li class="wp-block-navigation-item has-child wp-block-navigation-submenu open-on-hover-click"><div class="wp-block-navigation-item__content wp-block-navigation-submenu__toggle" aria-expanded="false" aria-haspopup="true" aria-controls="wpml-ls-submenu-default" tabindex="0"><span data-wpml="current-language-item" class="wpml-ls-item wpml-ls-current-language current-language-item"><a data-wpml="link" href="http://localhost:8920/de/" aria-label="Switch to Deutsch"><span data-wpml="label" data-wpml-label-type="native">Deutsch</span></a></span></div><ul id="wpml-ls-submenu-default" class="wp-block-navigation__submenu-container"><li data-wpml="language-item" class="wpml-ls-item wp-block-navigation-item"><a data-wpml="link" href="http://localhost:8920/" aria-label="Switch to English"><span data-wpml="label" data-wpml-label-type="native">English</span></a></li></ul></li></ul></div></div>
```

Rendering with the current language `en` mirrors it: toggle = English, sub-menu =
Deutsch. No fatal; real per-language URLs; current language in the toggle. The
same holds inside a `core/navigation` (the seeded header).

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
