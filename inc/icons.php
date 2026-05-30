<?php
/**
 * Phosphor icon helper.
 *
 * Icons are rendered inline from a generated slug → SVG-markup map
 * (assets/icons/phosphor-icons.php), produced by tools/build-phosphor-data.sh.
 *
 * @package Pediment
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Return the slug → inner-SVG-markup map, loaded once per request.
 *
 * @return array<string,string> Map of icon slug to inner SVG markup, or [] if missing.
 */
function pediment_icon_map(): array {
	static $map = null;
	if ( null === $map ) {
		$file = get_theme_file_path( 'assets/icons/phosphor-icons.php' );
		$map  = is_readable( $file ) ? (array) require $file : array();
	}
	return $map;
}

/**
 * Return an inline SVG for a Phosphor icon slug.
 *
 * @param string $name        Phosphor icon slug (without the ph- prefix).
 * @param string $extra_class Optional extra CSS class.
 * @return string Safe HTML, or '' if the slug is unknown.
 */
function pediment_icon( $name, $extra_class = '' ) {
	$slug = preg_replace( '/[^a-z0-9-]/', '', strtolower( (string) $name ) );
	$map  = pediment_icon_map();
	if ( '' === $slug || ! isset( $map[ $slug ] ) ) {
		return '';
	}
	$class = 'i' . ( '' !== $extra_class ? ' ' . sanitize_html_class( $extra_class ) : '' );
	return sprintf(
		'<svg class="%s" viewBox="0 0 256 256" data-icon="%s" aria-hidden="true" focusable="false">%s</svg>',
		esc_attr( $class ),
		esc_attr( $slug ),
		$map[ $slug ] // Theme-controlled trusted markup (same trust model as the old sprite).
	);
}

/**
 * Expose the icon catalog JSON URL to the block editor so the IconPicker
 * component can lazy-fetch the full slug → markup map on first use.
 */
add_action(
	'enqueue_block_editor_assets',
	function () {
		$url = get_theme_file_uri( 'assets/icons/phosphor-icons.json' );
		wp_add_inline_script(
			'wp-blocks',
			'window.pedimentIcons = ' . wp_json_encode( array( 'catalogUrl' => $url ) ) . ';',
			'after'
		);
	}
);
