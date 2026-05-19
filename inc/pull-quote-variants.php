<?php
/**
 * Pull-quote variant registry — the fork-friendly extension point.
 *
 * The parent ships an opinionated set of pull-quote variants. A child theme
 * can remove one with a single line, e.g.:
 *
 *   add_filter( 'starter_pull_quote_variants', fn( $v ) => array_diff( $v, [ 'testimonial' ] ) );
 *
 * render.php normalizes any variant not in this list to "default", and the
 * block editor only offers the filtered list.
 *
 * @package Starter
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The allowed pull-quote variants (filterable).
 *
 * @return string[] Re-indexed list of variant slugs.
 */
function starter_pull_quote_variants() {
	$defaults = array( 'default', 'testimonial' );
	$variants = apply_filters( 'starter_pull_quote_variants', $defaults );
	$variants = is_array( $variants ) ? array_values( array_filter( array_map( 'strval', $variants ) ) ) : $defaults;
	if ( ! in_array( 'default', $variants, true ) ) {
		array_unshift( $variants, 'default' );
	}
	return $variants;
}

/**
 * Expose the filtered variant list to the block editor so the Pull Quote
 * inspector only offers variants the site actually ships.
 */
add_action(
	'enqueue_block_editor_assets',
	function () {
		wp_add_inline_script(
			'wp-blocks',
			'window.starterPullQuoteVariants = ' . wp_json_encode( starter_pull_quote_variants() ) . ';',
			'after'
		);
	}
);
