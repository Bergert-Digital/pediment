<?php
/**
 * __PEDIMENT_NAME__ theme bootstrap.
 *
 * A Pediment client theme has almost no PHP: blocks, templates, tokens,
 * seeding and the AI editor all ship in the Pediment plugin. What lives here
 * is what is genuinely specific to this client — bespoke blocks under
 * src/blocks/, and the stylesheet.
 *
 * @package __PEDIMENT_SLUG__
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Register every built client block.
 *
 * Blocks are authored in src/blocks/ and built into build/blocks/ by
 * `npm run build`. A missing build directory means the release zip was built
 * without that step: every client block then renders as raw markup, which is
 * silent on the front end, so it is surfaced in wp-admin instead.
 */
add_action(
	'init',
	function () {
		$dir = __DIR__ . '/build/blocks';

		if ( ! is_dir( $dir ) ) {
			// A closure, not a named function: the theme slug is hyphenated and
			// would not make a valid PHP identifier after token replacement.
			add_action(
				'admin_notices',
				function () {
					if ( ! current_user_can( 'switch_themes' ) ) {
						return;
					}
					echo '<div class="notice notice-error"><p>'
						. esc_html__( 'This theme was packaged without its built blocks, so its custom sections render as raw markup. Rebuild with `npm run build` and re-upload the release zip.', '__PEDIMENT_SLUG__' )
						. '</p></div>';
				}
			);
			return;
		}

		foreach ( (array) glob( $dir . '/*', GLOB_ONLYDIR ) as $block ) {
			if ( file_exists( $block . '/block.json' ) ) {
				register_block_type( $block );
			}
		}
	}
);

add_action(
	'wp_enqueue_scripts',
	function () {
		wp_enqueue_style(
			'__PEDIMENT_SLUG__',
			get_stylesheet_uri(),
			array(),
			(string) filemtime( __DIR__ . '/style.css' )
		);
	}
);
