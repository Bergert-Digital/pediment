# Brand Settings Field Registry Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Replace the hardcoded field list, defaults, and sanitize whitelist in Brand Settings with a registry that lets child themes add/modify fields and sections via filters.

**Architecture:** A new `Starter\BrandRegistry` class owns the canonical list of field and section definitions, each wrapped by an `apply_filters()` hook. `Brand::all()` derives defaults from the registry. `starter_brand_sanitize()` iterates registered fields and applies each field's sanitize callable. The `admin_init` callback iterates the registry to call `add_settings_field()` per field, with a renderer chosen by `type`.

**Field definition shape** (keyed by field key, output of `BrandRegistry::fields()`):

```php
[
  'label'    => string,
  'section'  => string,                 // section slug
  'type'     => 'text'|'textarea'|'email'|'image'|'social'|'integer',
  'default'  => mixed,
  'sanitize' => callable|null,          // null → use the type's default sanitize
  'renderer' => callable|null,          // null → use the type's default renderer
]
```

**Section definition shape** (keyed by section slug, output of `BrandRegistry::sections()`):

```php
[
  'title' => string,
]
```

**Type → default sanitize/renderer mapping** (used when a field's `sanitize`/`renderer` is null):

| type     | default sanitize         | default renderer                |
| -------- | ------------------------ | ------------------------------- |
| text     | `sanitize_text_field`    | `starter_brand_field_text`      |
| textarea | `sanitize_textarea_field`| `starter_brand_field_textarea`  |
| email    | (custom: text + is_email check, adds settings_error on invalid) | `starter_brand_field_text` (with type='email') |
| image    | `absint`                 | `starter_brand_field_image`     |
| social   | (custom: keep existing array-of-{platform,url} sanitize) | `starter_brand_field_social` |
| integer  | `absint`                 | `starter_brand_field_text` (with type='number') |

**Tech Stack:** PHP 8.1+, WordPress 6.5+, PHPUnit + `WP_UnitTestCase`.

---

## File Structure

- **Create:** `inc/BrandRegistry.php` — new class. Holds canonical field/section arrays, exposes them via `fields()` and `sections()` static methods that each apply a filter.
- **Modify:** `inc/Brand.php` — change `DEFAULTS` from a private const to a private static method that derives the array from `BrandRegistry::fields()`. Keep the `OPTION`, `all()`, `get()`, `set()` API identical.
- **Modify:** `inc/brand-settings.php` — replace the hardcoded `add_settings_field()` calls with a loop over `BrandRegistry::fields()`. Replace `starter_brand_sanitize()` with a registry-driven implementation. Sections likewise loop `BrandRegistry::sections()`.
- **Modify:** `functions.php` — require the new `inc/BrandRegistry.php` file before `inc/Brand.php` (Brand depends on the registry).
- **Create:** `tests/phpunit/BrandSettings/RegistryTest.php` — new test class covering the registry contract and filter behavior.
- **Existing tests:** `tests/phpunit/BrandSettings/AdminPageTest.php` and `tests/phpunit/BrandSettings/StorageTest.php` MUST continue to pass without modification.

---

## Task 1: Pin the registry contract with tests (red)

**Files:**
- Test: `tests/phpunit/BrandSettings/RegistryTest.php` (new)

- [ ] **Step 1: Create the test file with the registry-shape tests**

```php
<?php

class RegistryTest extends WP_UnitTestCase {
    public function test_fields_returns_all_parent_fields_with_expected_shape() {
        $fields = \Starter\BrandRegistry::fields();

        $expected_keys = array(
            'brand_name', 'brand_tagline', 'voice_tone', 'logo_id',
            'contact_email', 'phone', 'address',
            'social_links',
            'og_image_id',
        );
        foreach ( $expected_keys as $key ) {
            $this->assertArrayHasKey( $key, $fields, "Field {$key} should be in the registry" );
            $this->assertArrayHasKey( 'label', $fields[ $key ] );
            $this->assertArrayHasKey( 'section', $fields[ $key ] );
            $this->assertArrayHasKey( 'type', $fields[ $key ] );
            $this->assertArrayHasKey( 'default', $fields[ $key ] );
        }

        $this->assertSame( 'identity', $fields['brand_name']['section'] );
        $this->assertSame( 'text',     $fields['brand_name']['type'] );
        $this->assertSame( '',         $fields['brand_name']['default'] );

        $this->assertSame( 'image',    $fields['logo_id']['type'] );
        $this->assertSame( 0,          $fields['logo_id']['default'] );

        $this->assertSame( 'social',   $fields['social_links']['type'] );
        $this->assertSame( array(),    $fields['social_links']['default'] );
    }

    public function test_sections_returns_all_parent_sections() {
        $sections = \Starter\BrandRegistry::sections();

        foreach ( array( 'identity', 'contact', 'social', 'og' ) as $slug ) {
            $this->assertArrayHasKey( $slug, $sections );
            $this->assertArrayHasKey( 'title', $sections[ $slug ] );
        }
    }
}
```

- [ ] **Step 2: Run the new tests to verify they fail with "class not found"**

Run: `vendor/bin/phpunit --filter RegistryTest`
Expected: FAIL — `Class "Starter\BrandRegistry" not found`.

---

## Task 2: Implement BrandRegistry to make the contract test pass (green)

**Files:**
- Create: `inc/BrandRegistry.php`
- Modify: `functions.php` (require the new file before `inc/Brand.php` — check the exact current loading order before editing)

- [ ] **Step 1: Create `inc/BrandRegistry.php`**

```php
<?php
/**
 * Brand settings field & section registry.
 *
 * @package Starter
 */

namespace Starter;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class BrandRegistry {
    /**
     * @return array<string,array<string,mixed>> Keyed by field key.
     */
    public static function fields(): array {
        $fields = array(
            'brand_name'    => array(
                'label'   => __( 'Brand name', 'starter' ),
                'section' => 'identity',
                'type'    => 'text',
                'default' => '',
            ),
            'brand_tagline' => array(
                'label'   => __( 'Tagline', 'starter' ),
                'section' => 'identity',
                'type'    => 'text',
                'default' => '',
            ),
            'voice_tone'    => array(
                'label'   => __( 'Voice / tone', 'starter' ),
                'section' => 'identity',
                'type'    => 'textarea',
                'default' => '',
            ),
            'logo_id'       => array(
                'label'   => __( 'Logo', 'starter' ),
                'section' => 'identity',
                'type'    => 'image',
                'default' => 0,
            ),
            'contact_email' => array(
                'label'   => __( 'Contact email', 'starter' ),
                'section' => 'contact',
                'type'    => 'email',
                'default' => '',
            ),
            'phone'         => array(
                'label'   => __( 'Phone', 'starter' ),
                'section' => 'contact',
                'type'    => 'text',
                'default' => '',
            ),
            'address'       => array(
                'label'   => __( 'Address', 'starter' ),
                'section' => 'contact',
                'type'    => 'textarea',
                'default' => '',
            ),
            'social_links'  => array(
                'label'   => __( 'Social links', 'starter' ),
                'section' => 'social',
                'type'    => 'social',
                'default' => array(),
            ),
            'og_image_id'   => array(
                'label'   => __( 'Default OG image', 'starter' ),
                'section' => 'og',
                'type'    => 'image',
                'default' => 0,
            ),
        );

        /**
         * Filter the Brand Settings field registry.
         *
         * @param array<string,array<string,mixed>> $fields Fields keyed by field key.
         */
        $fields = (array) apply_filters( 'starter_brand_fields', $fields );

        // Fill in nulls so consumers can assume every field has sanitize/renderer.
        foreach ( $fields as $key => $def ) {
            $fields[ $key ] = array_merge(
                array( 'sanitize' => null, 'renderer' => null ),
                $def
            );
        }
        return $fields;
    }

    /**
     * @return array<string,array<string,string>> Keyed by section slug.
     */
    public static function sections(): array {
        $sections = array(
            'identity' => array( 'title' => __( 'Identity', 'starter' ) ),
            'contact'  => array( 'title' => __( 'Contact', 'starter' ) ),
            'social'   => array( 'title' => __( 'Social', 'starter' ) ),
            'og'       => array( 'title' => __( 'OG / SEO', 'starter' ) ),
        );

        /**
         * Filter the Brand Settings section registry.
         *
         * @param array<string,array<string,string>> $sections Sections keyed by section slug.
         */
        return (array) apply_filters( 'starter_brand_sections', $sections );
    }
}
```

- [ ] **Step 2: Wire `inc/BrandRegistry.php` into theme loading**

Open `functions.php` and find the line that requires `inc/Brand.php` (or wherever `inc/` files are loaded). Add a require for `inc/BrandRegistry.php` BEFORE the existing `inc/Brand.php` require.

If `functions.php` loads `inc/*.php` via a glob/loop, BrandRegistry.php will be picked up automatically — but verify the load order is alphabetical (BrandRegistry comes before Brand) or that nothing in BrandRegistry depends on Brand. In our case BrandRegistry has no Brand dependency, so glob loading is fine.

- [ ] **Step 3: Run tests to verify Registry contract test passes**

Run: `vendor/bin/phpunit --filter RegistryTest`
Expected: PASS — both `test_fields_returns_all_parent_fields_with_expected_shape` and `test_sections_returns_all_parent_sections`.

- [ ] **Step 4: Run the full suite to confirm no regressions**

Run: `vendor/bin/phpunit`
Expected: All existing tests still pass.

- [ ] **Step 5: Commit**

```bash
git add inc/BrandRegistry.php functions.php tests/phpunit/BrandSettings/RegistryTest.php
git commit -m "feat(brand): introduce BrandRegistry for filterable fields and sections"
```

---

## Task 3: Add child-extension tests (red)

**Files:**
- Modify: `tests/phpunit/BrandSettings/RegistryTest.php`

- [ ] **Step 1: Append child-extension tests to RegistryTest.php**

Add inside the `RegistryTest` class:

```php
public function test_starter_brand_fields_filter_appends_child_fields() {
    add_filter( 'starter_brand_fields', function ( $fields ) {
        $fields['newsletter_form_id'] = array(
            'label'    => 'Newsletter form ID',
            'section'  => 'contact',
            'type'     => 'integer',
            'default'  => 0,
            'sanitize' => 'absint',
        );
        return $fields;
    } );

    $fields = \Starter\BrandRegistry::fields();
    $this->assertArrayHasKey( 'newsletter_form_id', $fields );
    $this->assertSame( 0,        $fields['newsletter_form_id']['default'] );
    $this->assertSame( 'absint', $fields['newsletter_form_id']['sanitize'] );
}

public function test_starter_brand_sections_filter_adds_sections() {
    add_filter( 'starter_brand_sections', function ( $sections ) {
        $sections['legal'] = array( 'title' => 'Legal' );
        return $sections;
    } );

    $sections = \Starter\BrandRegistry::sections();
    $this->assertArrayHasKey( 'legal', $sections );
    $this->assertSame( 'Legal', $sections['legal']['title'] );
}
```

- [ ] **Step 2: Run new tests — they should pass without further code (filters already wired)**

Run: `vendor/bin/phpunit --filter RegistryTest`
Expected: PASS for both new tests.

- [ ] **Step 3: Commit**

```bash
git add tests/phpunit/BrandSettings/RegistryTest.php
git commit -m "test(brand): verify filter extension surface for fields and sections"
```

---

## Task 4: Refactor `Brand::all()` to derive defaults from the registry (TDD-protected by existing StorageTest)

**Files:**
- Modify: `inc/Brand.php`

- [ ] **Step 1: Read `tests/phpunit/BrandSettings/StorageTest.php` to confirm what it asserts about defaults and `all()` / `get()` behavior**

Skim the file. Note the assertions. These tests are your safety net for the refactor.

- [ ] **Step 2: Replace the `DEFAULTS` const with a registry-derived method**

Open `inc/Brand.php`. Replace the class body with:

```php
final class Brand {
    public const OPTION = 'starter_theme_brand';

    private static function defaults(): array {
        $defaults = array();
        foreach ( BrandRegistry::fields() as $key => $def ) {
            $defaults[ $key ] = $def['default'] ?? '';
        }
        return $defaults;
    }

    public static function all(): array {
        $stored = get_option( self::OPTION, array() );
        if ( ! is_array( $stored ) ) {
            $stored = array();
        }
        return array_merge( self::defaults(), $stored );
    }

    /**
     * @param string $key     Setting key.
     * @param mixed  $default Returned when the key is missing or empty.
     * @return mixed
     */
    public static function get( string $key, $default = null ) {
        $all = self::all();
        if ( ! array_key_exists( $key, $all ) ) {
            return $default;
        }
        $value = $all[ $key ];
        if ( '' === $value || ( is_array( $value ) && array() === $value ) ) {
            return $default ?? $value;
        }
        return $value;
    }

    /**
     * @param string $key   Setting key.
     * @param mixed  $value Value to persist.
     */
    public static function set( string $key, $value ): void {
        $all         = self::all();
        $all[ $key ] = $value;
        update_option( self::OPTION, $all );
    }
}
```

- [ ] **Step 3: Run the storage tests**

Run: `vendor/bin/phpunit --filter StorageTest`
Expected: PASS — defaults derive from the registry but yield the same values as before.

- [ ] **Step 4: Add a new test for filter-driven defaults**

In `tests/phpunit/BrandSettings/StorageTest.php`, add:

```php
public function test_all_includes_filter_added_defaults() {
    add_filter( 'starter_brand_fields', function ( $fields ) {
        $fields['legal_page_id'] = array(
            'label'   => 'Legal page',
            'section' => 'identity',
            'type'    => 'integer',
            'default' => 42,
        );
        return $fields;
    } );

    $all = \Starter\Brand::all();
    $this->assertArrayHasKey( 'legal_page_id', $all );
    $this->assertSame( 42, $all['legal_page_id'] );
}
```

- [ ] **Step 5: Run the storage tests including the new one**

Run: `vendor/bin/phpunit --filter StorageTest`
Expected: PASS — including the new filter-defaults test.

- [ ] **Step 6: Commit**

```bash
git add inc/Brand.php tests/phpunit/BrandSettings/StorageTest.php
git commit -m "refactor(brand): derive defaults from BrandRegistry, support filter-added fields"
```

---

## Task 5: Refactor `admin_init` to iterate the registry (regression-protected by existing AdminPageTest)

**Files:**
- Modify: `inc/brand-settings.php` (lines ~28-69)

- [ ] **Step 1: Replace the `admin_init` callback with a registry-driven loop**

Open `inc/brand-settings.php`. Replace the entire `add_action( 'admin_init', function () { … } )` block (currently lines 28-69) with:

```php
add_action(
    'admin_init',
    function () {
        register_setting(
            STARTER_BRAND_OPTION_GROUP,
            \Starter\Brand::OPTION,
            array(
                'type'              => 'array',
                'sanitize_callback' => 'starter_brand_sanitize',
                'default'           => array(),
            )
        );

        foreach ( \Starter\BrandRegistry::sections() as $slug => $section ) {
            add_settings_section( $slug, $section['title'], '__return_false', STARTER_BRAND_PAGE );
        }

        $renderers = array(
            'text'     => 'starter_brand_field_text',
            'textarea' => 'starter_brand_field_textarea',
            'email'    => 'starter_brand_field_text',
            'image'    => 'starter_brand_field_image',
            'social'   => 'starter_brand_field_social',
            'integer'  => 'starter_brand_field_text',
        );

        foreach ( \Starter\BrandRegistry::fields() as $key => $field ) {
            $type     = $field['type'];
            $renderer = $field['renderer'] ?? $renderers[ $type ] ?? 'starter_brand_field_text';

            $args = array( 'key' => $key );
            if ( 'email' === $type ) {
                $args['type'] = 'email';
            } elseif ( 'integer' === $type ) {
                $args['type'] = 'number';
            }

            add_settings_field(
                $key,
                $field['label'],
                $renderer,
                STARTER_BRAND_PAGE,
                $field['section'],
                $args
            );
        }
    }
);
```

- [ ] **Step 2: Run all BrandSettings tests**

Run: `vendor/bin/phpunit --filter BrandSettings`
Expected: PASS — existing `test_settings_are_registered` and `test_admin_menu_is_registered` still green, plus the registry/storage tests.

- [ ] **Step 3: Add a new test verifying filter-added fields appear as settings fields**

Append to `tests/phpunit/BrandSettings/AdminPageTest.php`:

```php
public function test_filter_added_field_registers_a_settings_field() {
    add_filter( 'starter_brand_fields', function ( $fields ) {
        $fields['newsletter_form_id'] = array(
            'label'    => 'Newsletter form ID',
            'section'  => 'contact',
            'type'     => 'integer',
            'default'  => 0,
            'sanitize' => 'absint',
        );
        return $fields;
    } );

    $this->setExpectedIncorrectUsage( 'wp_add_privacy_policy_content' );
    ob_start();
    @do_action( 'admin_init' );
    ob_end_clean();

    global $wp_settings_fields;
    $this->assertArrayHasKey(
        'newsletter_form_id',
        $wp_settings_fields[ STARTER_BRAND_PAGE ]['contact'] ?? array(),
        'Filter-added field should appear in $wp_settings_fields under its section.'
    );
}
```

- [ ] **Step 4: Run the new test**

Run: `vendor/bin/phpunit --filter AdminPageTest`
Expected: PASS — including the new `test_filter_added_field_registers_a_settings_field`.

- [ ] **Step 5: Commit**

```bash
git add inc/brand-settings.php tests/phpunit/BrandSettings/AdminPageTest.php
git commit -m "refactor(brand): iterate registry in admin_init, support filter-added fields/sections"
```

---

## Task 6: Refactor `starter_brand_sanitize()` to iterate the registry (regression-protected by existing sanitize test)

**Files:**
- Modify: `inc/brand-settings.php` (lines ~161-198, the `starter_brand_sanitize` function)

- [ ] **Step 1: Replace `starter_brand_sanitize()` with a registry-driven implementation**

Replace the existing `starter_brand_sanitize` function body in `inc/brand-settings.php` with:

```php
function starter_brand_sanitize( $input ): array {
    if ( ! is_array( $input ) ) {
        return array();
    }
    $clean = array();

    $type_sanitizers = array(
        'text'     => 'sanitize_text_field',
        'textarea' => 'sanitize_textarea_field',
        'integer'  => 'absint',
        'image'    => 'absint',
    );

    foreach ( \Starter\BrandRegistry::fields() as $key => $field ) {
        $type     = $field['type'];
        $custom   = $field['sanitize'] ?? null;
        $raw      = $input[ $key ] ?? null;

        if ( is_callable( $custom ) ) {
            $clean[ $key ] = call_user_func( $custom, $raw );
            continue;
        }

        if ( 'email' === $type ) {
            $value = isset( $raw ) ? sanitize_text_field( wp_unslash( (string) $raw ) ) : '';
            if ( '' !== $value && ! is_email( $value ) ) {
                add_settings_error( \Starter\Brand::OPTION, 'invalid_' . $key, sprintf( __( '%s is invalid.', 'starter' ), $field['label'] ) );
                $value = '';
            }
            $clean[ $key ] = $value;
            continue;
        }

        if ( 'social' === $type ) {
            $clean[ $key ] = array();
            if ( is_array( $raw ) ) {
                foreach ( $raw as $row ) {
                    if ( ! is_array( $row ) ) {
                        continue;
                    }
                    $platform  = isset( $row['platform'] ) ? sanitize_key( $row['platform'] ) : '';
                    $raw_url   = isset( $row['url'] ) ? (string) $row['url'] : '';
                    $url       = $raw_url ? esc_url_raw( $raw_url ) : '';
                    $valid_url = $url && wp_http_validate_url( $url );
                    if ( '' !== $platform && $valid_url ) {
                        $clean[ $key ][] = array(
                            'platform' => $platform,
                            'url'      => $url,
                        );
                    }
                }
            }
            continue;
        }

        $sanitizer = $type_sanitizers[ $type ] ?? 'sanitize_text_field';
        if ( 'absint' === $sanitizer ) {
            $clean[ $key ] = isset( $raw ) ? absint( $raw ) : 0;
        } else {
            $clean[ $key ] = isset( $raw ) ? call_user_func( $sanitizer, wp_unslash( (string) $raw ) ) : '';
        }
    }

    return $clean;
}
```

- [ ] **Step 2: Run the existing sanitize regression test**

Run: `vendor/bin/phpunit --filter test_sanitize_callback_coerces_social_links_into_clean_array`
Expected: PASS — existing behavior preserved.

- [ ] **Step 3: Add a sanitize test for filter-added field with custom callable**

Append to `tests/phpunit/BrandSettings/AdminPageTest.php`:

```php
public function test_sanitize_runs_field_custom_sanitize_callable() {
    add_filter( 'starter_brand_fields', function ( $fields ) {
        $fields['newsletter_form_id'] = array(
            'label'    => 'Newsletter form ID',
            'section'  => 'contact',
            'type'     => 'integer',
            'default'  => 0,
            'sanitize' => 'absint',
        );
        return $fields;
    } );

    $clean = starter_brand_sanitize( array( 'newsletter_form_id' => '-42abc' ) );
    $this->assertSame( 42, $clean['newsletter_form_id'] );
}

public function test_sanitize_applies_type_default_sanitize_when_callable_is_null() {
    add_filter( 'starter_brand_fields', function ( $fields ) {
        $fields['legal_text'] = array(
            'label'   => 'Legal text',
            'section' => 'identity',
            'type'    => 'textarea',
            'default' => '',
        );
        return $fields;
    } );

    $clean = starter_brand_sanitize( array( 'legal_text' => "  hello\n<script>alert(1)</script>  " ) );
    $this->assertStringContainsString( 'hello', $clean['legal_text'] );
    $this->assertStringNotContainsString( '<script>', $clean['legal_text'] );
}
```

- [ ] **Step 4: Run the new tests**

Run: `vendor/bin/phpunit --filter AdminPageTest`
Expected: PASS — both new sanitize tests green, plus existing.

- [ ] **Step 5: Full suite run**

Run: `vendor/bin/phpunit`
Expected: All tests pass across BlockLoader, BlockRender, BrandSettings, ContactForm, Patterns, Seed, SmokeTest.

- [ ] **Step 6: Commit**

```bash
git add inc/brand-settings.php tests/phpunit/BrandSettings/AdminPageTest.php
git commit -m "refactor(brand): registry-driven sanitize with per-field callables"
```

---

## Task 7: Document the extension surface

**Files:**
- Create: `docs/brand-settings.md` (or modify if it exists — check first)

- [ ] **Step 1: Check whether `docs/brand-settings.md` already exists**

Run: `ls docs/ | grep -i brand`

- [ ] **Step 2: Write the extension docs**

Create `docs/brand-settings.md` (or extend the existing file) with two sections:

````markdown
# Brand Settings

Brand Settings is a WordPress admin page (Settings → Brand Settings) that stores brand-level configuration in a single option (`starter_theme_brand`). Values are accessible at runtime via `\Starter\Brand::get( $key )`.

## Extending from a child theme

A child theme can add fields and sections to Brand Settings without editing the parent. Filters: `starter_brand_fields` and `starter_brand_sections`.

### Add a field

```php
add_filter( 'starter_brand_fields', function ( $fields ) {
    $fields['newsletter_form_id'] = array(
        'label'    => __( 'Newsletter form ID', 'acme' ),
        'section'  => 'contact',                  // 'identity'|'contact'|'social'|'og'|<custom>
        'type'     => 'integer',                  // 'text'|'textarea'|'email'|'image'|'social'|'integer'
        'default'  => 0,
        'sanitize' => 'absint',                   // null = use the type's default
    );
    return $fields;
} );

// Read at runtime:
$form_id = (int) \Starter\Brand::get( 'newsletter_form_id', 0 );
```

### Add a section

```php
add_filter( 'starter_brand_sections', function ( $sections ) {
    $sections['legal'] = array( 'title' => __( 'Legal', 'acme' ) );
    return $sections;
} );
```

Fields with `'section' => 'legal'` will render under that heading.

### Custom renderer

If the built-in types don't fit, pass a `'renderer'` callable. The renderer receives `array( 'key' => $key )` and is responsible for echoing the field HTML, reading the current value via `\Starter\Brand::get()` and writing to `<input name="starter_theme_brand[<key>]" …>`.

### Removing a field

```php
add_filter( 'starter_brand_fields', function ( $fields ) {
    unset( $fields['address'] );
    return $fields;
} );
```
````

- [ ] **Step 3: Commit**

```bash
git add docs/brand-settings.md
git commit -m "docs(brand): document field-registry extension surface for child themes"
```

---

## Self-review checklist (run before handing off)

- [ ] Every reference to `BrandRegistry::fields()` returns an array where every value has `label`, `section`, `type`, `default`, `sanitize`, `renderer` keys present (nulls allowed for the last two).
- [ ] No remaining hardcoded field arrays in `inc/brand-settings.php` or `inc/Brand.php`.
- [ ] Existing tests `test_settings_are_registered`, `test_admin_menu_is_registered`, `test_sanitize_callback_coerces_social_links_into_clean_array` pass unmodified.
- [ ] New tests cover: filter-added field, filter-added section, filter-added default, filter-added field's settings-field registration, custom sanitize callable, type-default sanitize fallback.
- [ ] No mention of `wp-client-template` or Bedrock anywhere — this lives in the parent theme.
