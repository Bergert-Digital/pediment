# Brand Settings

Brand Settings is a WordPress admin page (Settings → Brand Settings) that stores brand-level configuration in a single option (`pediment_theme_brand`). Values are accessible at runtime via `\Pediment\Brand::get( $key )`.

## Built-in fields

| key             | section  | type     | default |
| --------------- | -------- | -------- | ------- |
| `brand_name`    | identity | text     | `''`    |
| `brand_tagline` | identity | text     | `''`    |
| `voice_tone`    | identity | textarea | `''`    |
| `logo_id`       | identity | image    | `0`     |
| `contact_email` | contact  | email    | `''`    |
| `phone`         | contact  | text     | `''`    |
| `address`       | contact  | textarea | `''`    |
| `social_links`  | social   | social   | `[]`    |
| `og_image_id`   | og       | image    | `0`     |

Built-in sections: `identity`, `contact`, `social`, `og`.

## Extending from a child theme

A child theme can add fields and sections to Brand Settings without editing the parent. Two filters: `pediment_brand_fields` and `pediment_brand_sections`.

### Add a field

```php
add_filter( 'pediment_brand_fields', function ( $fields ) {
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
$form_id = (int) \Pediment\Brand::get( 'newsletter_form_id', 0 );
```

The field appears in the admin UI, its default participates in `\Pediment\Brand::all()`, and submitted values pass through your `sanitize` callable before being saved.

### Add a section

```php
add_filter( 'pediment_brand_sections', function ( $sections ) {
    $sections['legal'] = array( 'title' => __( 'Legal', 'acme' ) );
    return $sections;
} );
```

Fields with `'section' => 'legal'` will render under that heading.

### Field types

| type       | input                              | default sanitize           |
| ---------- | ---------------------------------- | -------------------------- |
| `text`     | single-line `<input type="text">`  | `sanitize_text_field`      |
| `textarea` | multi-line `<textarea>`            | `sanitize_textarea_field`  |
| `email`    | `<input type="email">` with `is_email` validation | text + is_email check |
| `integer`  | `<input type="number">`            | `absint`                   |
| `image`    | media picker, stores attachment ID | `absint`                   |
| `social`   | repeating `{platform, url}` rows   | platform key + URL validation |

Set `'sanitize' => 'my_callable'` (or a closure) to override the default. The callable receives the raw value or `null` if the field is absent from the submission.

### Custom renderer

If the built-in types don't fit, pass a `'renderer'` callable. The renderer receives `array( 'key' => $key )` and is responsible for echoing the field HTML, reading the current value via `\Pediment\Brand::get()` and writing to `<input name="pediment_theme_brand[<key>]" …>`.

```php
add_filter( 'pediment_brand_fields', function ( $fields ) {
    $fields['theme_color'] = array(
        'label'    => __( 'Theme colour', 'acme' ),
        'section'  => 'identity',
        'type'     => 'text',
        'default'  => '#000000',
        'sanitize' => 'sanitize_hex_color',
        'renderer' => 'acme_brand_field_color',
    );
    return $fields;
} );

function acme_brand_field_color( array $args ): void {
    $key   = $args['key'];
    $value = (string) \Pediment\Brand::get( $key, '#000000' );
    printf(
        '<input type="color" name="%1$s[%2$s]" value="%3$s" />',
        esc_attr( \Pediment\Brand::OPTION ),
        esc_attr( $key ),
        esc_attr( $value )
    );
}
```

### Removing a field

```php
add_filter( 'pediment_brand_fields', function ( $fields ) {
    unset( $fields['address'] );
    return $fields;
} );
```
