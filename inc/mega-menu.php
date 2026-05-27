<?php
/**
 * Mega menu: allow pediment/mega-menu to render as a core/navigation item.
 *
 * Editor insertion is handled by the block's own "parent": ["core/navigation"]
 * declaration. Render-time, core only wraps known blocks in the nav <li>; we
 * add ours to that set via the core filter (WP 6.5.0+).
 *
 * @package Pediment
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_filter(
	'block_core_navigation_listable_blocks',
	function ( $blocks ) {
		$blocks   = is_array( $blocks ) ? $blocks : array();
		$blocks[] = 'pediment/mega-menu';
		return array_values( array_unique( $blocks ) );
	}
);
