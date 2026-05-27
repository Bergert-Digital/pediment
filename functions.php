<?php
/**
 * Pediment bootstrap.
 *
 * @package Pediment
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! defined( 'PEDIMENT_THEME_DIR' ) ) {
	define( 'PEDIMENT_THEME_DIR', __DIR__ );
}

require_once __DIR__ . '/inc/BrandRegistry.php';
require_once __DIR__ . '/inc/Brand.php';
require_once __DIR__ . '/inc/register-blocks.php';
require_once __DIR__ . '/inc/icons.php';
require_once __DIR__ . '/inc/block-styles.php';
require_once __DIR__ . '/inc/hero-variants.php';
require_once __DIR__ . '/inc/pull-quote-variants.php';
require_once __DIR__ . '/inc/brand-settings.php';
require_once __DIR__ . '/inc/contact-form.php';
require_once __DIR__ . '/inc/patterns.php';

require_once __DIR__ . '/inc/seed.php';
require_once __DIR__ . '/inc/nav-active.php';
require_once __DIR__ . '/inc/nav-seed.php';
require_once __DIR__ . '/inc/mega-menu.php';

add_action(
	'wp_enqueue_scripts',
	function () {
		$css = 'assets/css/theme.css';
		wp_enqueue_style(
			'pediment-theme',
			get_theme_file_uri( $css ),
			array(),
			(string) filemtime( get_theme_file_path( $css ) )
		);
		$js = 'assets/js/reveal.js';
		wp_enqueue_script(
			'pediment-reveal',
			get_theme_file_uri( $js ),
			array(),
			(string) filemtime( get_theme_file_path( $js ) ),
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
		load_theme_textdomain( 'pediment', get_template_directory() . '/languages' );
		add_editor_style( 'assets/css/theme.css' );
		add_theme_support(
			'custom-logo',
			array(
				'flex-width'  => true,
				'flex-height' => true,
				'header-text' => array( 'site-title', 'site-description' ),
			)
		);
	}
);

add_action( 'after_switch_theme', 'pediment_contact_schedule_cleanup' );
add_action( 'switch_theme', 'pediment_contact_unschedule_cleanup' );
