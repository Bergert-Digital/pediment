<?php
/**
 * Global front-end assets: theme.css, reveal.js, the editor stylesheet, and
 * the no-FOUC inline script that adds the .anim class before first paint.
 * Moved from the theme's functions.php (Task 6 of the plugin-absorbs-theme
 * migration) now that assets/ ships from the plugin.
 *
 * @package Pediment
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action(
	'wp_enqueue_scripts',
	function () {
		$css_path = PEDIMENT_AI_PLUGIN_DIR . '/assets/css/theme.css';
		wp_enqueue_style(
			'pediment-theme',
			PEDIMENT_AI_PLUGIN_URL . 'assets/css/theme.css',
			array(),
			(string) filemtime( $css_path )
		);

		$js_path = PEDIMENT_AI_PLUGIN_DIR . '/assets/js/reveal.js';
		wp_enqueue_script(
			'pediment-reveal',
			PEDIMENT_AI_PLUGIN_URL . 'assets/js/reveal.js',
			array(),
			(string) filemtime( $js_path ),
			true
		);
	}
);

// No-FOUC: add the .anim class before first paint. Use wp_print_inline_script_tag
// so security plugins / hosts that emit a CSP nonce can attach it automatically.
add_action(
	'wp_head',
	function () {
		wp_print_inline_script_tag( "document.documentElement.classList.add('anim')" );
	},
	0
);

add_action(
	'after_setup_theme',
	function () {
		// add_editor_style() accepts an absolute URL as-is (see
		// get_editor_stylesheets() in wp-includes/theme.php), so this works
		// regardless of which theme is active.
		add_editor_style( PEDIMENT_AI_PLUGIN_URL . 'assets/css/theme.css' );
	}
);
