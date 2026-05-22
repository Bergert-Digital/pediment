<?php
/**
 * Block pattern category registration. Patterns themselves are auto-loaded
 * by WordPress from the theme patterns/ directory (each file has a header
 * with Title/Slug/Categories).
 *
 * @package Pediment
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action(
	'init',
	function () {
		$cats      = WP_Block_Pattern_Categories_Registry::get_instance()->get_all_registered();
		$cat_slugs = wp_list_pluck( $cats, 'name' );
		if ( ! in_array( 'pediment', $cat_slugs, true ) ) {
			register_block_pattern_category(
				'pediment',
				array(
					'label' => __( 'Pediment', 'pediment' ),
				)
			);
		}
	}
);
