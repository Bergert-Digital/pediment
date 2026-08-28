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
'render_callback' => [ Render, 'render_block' ] ] )`. It is a **dynamic block**:
`supports.html = false`, no `save`, no server-side attribute schema. The plain
`wpml/language-switcher` block has **no block attributes at all** — its
appearance is driven by WPML's own Language-Switcher settings, not by block
attrs. (The navigation variant does declare three attrs, all with defaults:
`navigationLsHasSubMenuInSameBlock=false`, `layoutOpenOnClick=false`,
`layoutShowArrow=true`.)

Because there are no attributes, a default insert serializes to a bare
self-closing block comment. Confirmed by round-tripping through core's
`serialize_blocks()` in the container:

```
<!-- wp:wpml/language-switcher /-->
```

That is exactly the markup Task 6 must emit for the default switcher. (With any
non-default attribute the comment would carry a JSON payload, e.g.
`<!-- wp:wpml/navigation-language-switcher {"layoutShowArrow":false} /-->`, but
the plain switcher never does.)

### How this was found
- Block name / registration: `grep -rn "register_block_type" ` and reading
  `classes/block-editor/Blocks/LanguageSwitcher.php` (const
  `BLOCK_LANGUAGE_SWITCHER = 'wpml/language-switcher'`).
- No attributes: the compiled editor bundle `dist/js/blocks/app.js` contains
  zero `attributes:{` for this block, and its transform inserts it as
  `createBlock("wpml/language-switcher", {})`.
- Serialized markup: `wp eval 'serialize_blocks([...])'` in the `cli`
  container returned `<!-- wp:wpml/language-switcher /-->`.

### Registration context (relevant to Task 6)
The blocks are registered by `WPML\BlockEditor\Loader`, which is an
`IWPML_Backend_Action` / `IWPML_REST_Action`, on the `init` hook, gated by
`WPML_Block_Editor_Helper::is_active()`. So the block type is only *registered*
in admin / block-editor / REST requests — it is **not** registered on a plain
front-end or WP-CLI load (a bare `wp eval` shows it as unregistered). The
dynamic `render_callback` still renders it on the front end. Serializing the
markup does not require the block to be registered, so Task 6 can emit the
comment string directly without depending on editor context.

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
