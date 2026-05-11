<?php
/**
 * Auto-registers every block in build/blocks/<name>/.
 *
 * @package Starter
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_filter(
	'block_categories_all',
	function ( array $categories ) {
		array_unshift(
			$categories,
			array(
				'slug'  => 'starter',
				'title' => __( 'Starter blocks', 'starter' ),
			)
		);
		return $categories;
	}
);

/**
 * Register all blocks in the given directory.
 *
 * @param string|null $base_dir Directory containing block subfolders. Defaults to theme's build/blocks.
 */
function starter_register_blocks( $base_dir = null ) {
	if ( null === $base_dir || '' === $base_dir ) {
		$base_dir = STARTER_THEME_DIR . '/build/blocks';
	}

	if ( ! is_dir( $base_dir ) ) {
		return;
	}

	$registry = WP_Block_Type_Registry::get_instance();
	foreach ( glob( $base_dir . '/*', GLOB_ONLYDIR ) as $block_dir ) {
		$manifest = $block_dir . '/block.json';
		if ( ! file_exists( $manifest ) ) {
			continue;
		}
		$meta = json_decode( file_get_contents( $manifest ), true );
		if ( is_array( $meta ) && isset( $meta['name'] ) && $registry->is_registered( $meta['name'] ) ) {
			continue;
		}
		register_block_type( $block_dir );
	}
}

add_action(
	'init',
	function () {
		starter_register_blocks();
	}
);
