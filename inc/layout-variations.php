<?php
/**
 * Default the Row and Grid group variations to wide alignment in the editor.
 *
 * @package Pediment
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action(
	'enqueue_block_editor_assets',
	function () {
		$rel = 'assets/js/layout-variations.js';
		wp_enqueue_script(
			'pediment-layout-variations',
			get_theme_file_uri( $rel ),
			array( 'wp-blocks', 'wp-dom-ready' ),
			(string) filemtime( get_theme_file_path( $rel ) ),
			true
		);
	}
);
