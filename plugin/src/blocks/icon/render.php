<?php
/**
 * Server-side render for pediment/icon.
 *
 * A block-level wrapper holds an inline glyph, so the glyph follows the
 * parent's text-align. Named styles (for example `tile`) style the glyph.
 *
 * @var array $attributes
 */

$icon_slug  = isset( $attributes['icon'] ) && is_string( $attributes['icon'] ) ? $attributes['icon'] : '';
$icon_label = isset( $attributes['label'] ) && is_string( $attributes['label'] ) ? trim( $attributes['label'] ) : '';
$icon_svg   = '' !== $icon_slug && function_exists( 'pediment_icon' ) ? pediment_icon( $icon_slug ) : '';

if ( '' === $icon_svg ) {
	return '';
}

// pediment_icon() marks the svg aria-hidden; the glyph carries the meaning.
$icon_a11y = '' === $icon_label
	? 'aria-hidden="true"'
	: 'role="img" aria-label="' . esc_attr( $icon_label ) . '"';

printf(
	'<div %1$s><span class="wp-block-pediment-icon__glyph" %2$s>%3$s</span></div>',
	get_block_wrapper_attributes(), // phpcs:ignore WordPress.Security.EscapeOutput -- core-escaped wrapper attributes
	$icon_a11y, // phpcs:ignore WordPress.Security.EscapeOutput -- built above with esc_attr()
	$icon_svg // phpcs:ignore WordPress.Security.EscapeOutput -- plugin-controlled icon markup from pediment_icon()
);
