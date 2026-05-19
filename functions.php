<?php
/**
 * Starter Theme bootstrap.
 *
 * @package Starter
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! defined( 'STARTER_THEME_DIR' ) ) {
	define( 'STARTER_THEME_DIR', __DIR__ );
}
if ( ! defined( 'STARTER_THEME_VERSION' ) ) {
	define( 'STARTER_THEME_VERSION', '0.1.0' );
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
		if ( ! has_block( 'starter/contact-form' ) ) {
			return;
		}
		$rel = 'assets/js/frontend-contact-form.js';
		wp_enqueue_script(
			'starter-frontend-contact-form',
			get_theme_file_uri( $rel ),
			array(),
			(string) filemtime( get_theme_file_path( $rel ) ),
			true
		);
	}
);

add_action(
	'wp_enqueue_scripts',
	function () {
		$css = 'assets/css/theme.css';
		wp_enqueue_style(
			'starter-theme',
			get_theme_file_uri( $css ),
			array(),
			(string) filemtime( get_theme_file_path( $css ) )
		);
		$js = 'assets/js/reveal.js';
		wp_enqueue_script(
			'starter-reveal',
			get_theme_file_uri( $js ),
			array(),
			(string) filemtime( get_theme_file_path( $js ) ),
			true
		);
	}
);

// No-FOUC: add the .anim class before first paint.
add_action(
	'wp_head',
	function () {
		echo "<script>document.documentElement.classList.add('anim')</script>\n";
	},
	0
);

add_action( 'after_switch_theme', 'starter_contact_schedule_cleanup' );
add_action( 'switch_theme', 'starter_contact_unschedule_cleanup' );
