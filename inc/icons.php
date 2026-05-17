<?php
/**
 * Phosphor icon sprite + helper.
 *
 * @package Starter
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Return an inline SVG that references a sprite symbol.
 *
 * @param string $name        Phosphor icon name (without the ph- prefix).
 * @param string $extra_class Optional extra CSS class.
 * @return string Safe HTML.
 */
function starter_icon( $name, $extra_class = '' ) {
	$slug  = preg_replace( '/[^a-z0-9-]/', '', strtolower( (string) $name ) );
	$class = 'i' . ( '' !== $extra_class ? ' ' . sanitize_html_class( $extra_class ) : '' );
	return sprintf(
		'<svg class="%s" aria-hidden="true" focusable="false"><use href="#ph-%s"></use></svg>',
		esc_attr( $class ),
		esc_attr( $slug )
	);
}

/**
 * Print the Phosphor sprite once, as early as possible in <body>.
 */
function starter_print_icon_sprite() {
	static $printed = false;
	if ( $printed ) {
		return;
	}
	$printed = true;
	$file    = get_theme_file_path( 'assets/icons/phosphor-sprite.svg' );
	if ( is_readable( $file ) ) {
		// Static, theme-controlled SVG sprite; safe to output verbatim.
		echo file_get_contents( $file ); // phpcs:ignore WordPress.Security.EscapeOutput
	}
}
add_action( 'wp_body_open', 'starter_print_icon_sprite', 1 );
