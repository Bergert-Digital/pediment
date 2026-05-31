<?php
/**
 * Inline icon helper.
 *
 * Icons are rendered inline from a generated slug → SVG-markup map
 * (assets/icons/icon-markup.php), produced by tools/build-phosphor-data.sh.
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
		$file = get_theme_file_path( 'assets/icons/icon-markup.php' );
		$map  = is_readable( $file ) ? (array) require $file : array();
	}
	return $map;
}

/**
 * Return the render manifest for the active icon set, loaded once per request.
 *
 * Captures everything set-specific about rendering — the coordinate system
 * (viewBox) and the presentation attributes that must live on the wrapper
 * <svg> (e.g. fill for filled sets, stroke/stroke-width for stroke sets).
 * Falls back to Phosphor-shaped defaults when the manifest is absent.
 *
 * @return array{viewBox:string,svgAttrs:array<string,string>}
 */
function pediment_icon_set(): array {
	static $set = null;
	if ( null === $set ) {
		$file = get_theme_file_path( 'assets/icons/icon-set.json' );
		$data = is_readable( $file ) ? json_decode( (string) file_get_contents( $file ), true ) : null;
		$set  = array(
			'viewBox'  => ( is_array( $data ) && ! empty( $data['viewBox'] ) )
				? (string) $data['viewBox']
				: '0 0 256 256',
			'svgAttrs' => ( is_array( $data ) && isset( $data['svgAttrs'] ) && is_array( $data['svgAttrs'] ) )
				? $data['svgAttrs']
				: array( 'fill' => 'currentColor' ),
		);
	}

	/**
	 * Filter the icon render manifest. Lets tests and integrations swap the
	 * coordinate system / presentation attributes without regenerating data.
	 *
	 * @param array{viewBox:string,svgAttrs:array<string,string>} $set
	 */
	return apply_filters( 'pediment_icon_set', $set );
}

/**
 * Return an inline SVG for an icon slug.
 *
 * @param string $name        Icon slug.
 * @param string $extra_class Optional extra CSS class.
 * @return string Safe HTML, or '' if the slug is unknown.
 */
function pediment_icon( $name, $extra_class = '' ) {
	$slug = preg_replace( '/[^a-z0-9-]/', '', strtolower( (string) $name ) );
	$map  = pediment_icon_map();
	if ( '' === $slug || ! isset( $map[ $slug ] ) ) {
		return '';
	}
	$set   = pediment_icon_set();
	$class = 'i' . ( '' !== $extra_class ? ' ' . sanitize_html_class( $extra_class ) : '' );

	$attrs  = sprintf( ' class="%s"', esc_attr( $class ) );
	$attrs .= sprintf( ' viewBox="%s"', esc_attr( $set['viewBox'] ) );
	foreach ( $set['svgAttrs'] as $key => $value ) {
		$key = preg_replace( '/[^a-z0-9-]/', '', strtolower( (string) $key ) );
		if ( '' === $key || ! is_scalar( $value ) ) {
			continue;
		}
		$attrs .= sprintf( ' %s="%s"', $key, esc_attr( (string) $value ) );
	}
	$attrs .= sprintf( ' data-icon="%s" aria-hidden="true" focusable="false"', esc_attr( $slug ) );

	return sprintf(
		'<svg%s>%s</svg>',
		$attrs,
		$map[ $slug ] // Theme-controlled trusted markup (same trust model as the old sprite).
	);
}

/**
 * Block-editor assets for the shared IconPicker component:
 *  - expose the catalog JSON URL so it can lazy-fetch the slug → markup map;
 *  - load the picker's editor-only stylesheet (the picker UI renders in the
 *    admin document, not the canvas iframe, so add_editor_style cannot reach
 *    it).
 */
add_action(
	'enqueue_block_editor_assets',
	function () {
		$icons = array(
			'markupUrl' => get_theme_file_uri( 'assets/icons/icon-markup.json' ),
			'metaUrl'   => get_theme_file_uri( 'assets/icons/icon-meta.json' ),
			'setUrl'    => get_theme_file_uri( 'assets/icons/icon-set.json' ),
		);
		wp_add_inline_script(
			'wp-blocks',
			'window.pedimentIcons = ' . wp_json_encode( $icons ) . ';',
			'after'
		);

		$css = 'assets/css/icon-picker-editor.css';
		wp_enqueue_style(
			'pediment-icon-picker-editor',
			get_theme_file_uri( $css ),
			array( 'wp-edit-blocks' ),
			(string) filemtime( get_theme_file_path( $css ) )
		);
	}
);
