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
			PEDIMENT_AI_PLUGIN_URL . $rel,
			array( 'wp-blocks', 'wp-dom-ready' ),
			(string) filemtime( PEDIMENT_AI_PLUGIN_DIR . '/' . $rel ),
			true
		);
	}
);
