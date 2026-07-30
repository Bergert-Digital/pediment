<?php
/**
 * Hero variant registry — the fork-friendly extension point.
 *
 * The parent ships an opinionated set of hero variants. A child theme can
 * remove one with a single line, e.g.:
 *
 *   add_filter( 'pediment_hero_variants', fn( $v ) => array_diff( $v, [ 'stat-card' ] ) );
 *
 * render.php normalizes any variant not in this list to "default", and the
 * block editor only offers the filtered list.
 *
 * @package Pediment
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The allowed hero variants (filterable).
 *
 * @return string[] Re-indexed list of variant slugs.
 */
function pediment_hero_variants() {
	$defaults = array( 'default', 'centered', 'media-bg', 'stat-card' );
	$variants = apply_filters( 'pediment_hero_variants', $defaults );
	$variants = is_array( $variants ) ? array_values( array_filter( array_map( 'strval', $variants ) ) ) : $defaults;
	if ( ! in_array( 'default', $variants, true ) ) {
		array_unshift( $variants, 'default' );
	}
	return $variants;
}

/**
 * Expose the filtered variant list to the block editor so the Hero
 * inspector only offers variants the site actually ships.
 */
add_action(
	'enqueue_block_editor_assets',
	function () {
		wp_add_inline_script(
			'wp-blocks',
			'window.pedimentHeroVariants = ' . wp_json_encode( pediment_hero_variants() ) . ';',
			'after'
		);
	}
);
