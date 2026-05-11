<?php
/**
 * Auto-registers every block in build/blocks/<name>/.
 *
 * @package Starter
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Register all blocks in the given directory.
 *
 * @param string|null $base_dir Directory containing block subfolders. Defaults to theme's build/blocks.
 */
function starter_register_blocks( $base_dir = null ) {
	if ( null === $base_dir ) {
		$base_dir = STARTER_THEME_DIR . '/build/blocks';
	}

	if ( ! is_dir( $base_dir ) ) {
		return;
	}

	foreach ( glob( $base_dir . '/*', GLOB_ONLYDIR ) as $block_dir ) {
		$manifest = $block_dir . '/block.json';
		if ( file_exists( $manifest ) ) {
			register_block_type( $block_dir );
		}
	}
}

add_action( 'init', 'starter_register_blocks' );
