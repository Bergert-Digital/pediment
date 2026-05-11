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

require_once __DIR__ . '/inc/Brand.php';
require_once __DIR__ . '/inc/register-blocks.php';
require_once __DIR__ . '/inc/brand-settings.php';
require_once __DIR__ . '/inc/contact-form.php';
require_once __DIR__ . '/inc/patterns.php';

if ( defined( 'WP_CLI' ) && WP_CLI ) {
	require_once __DIR__ . '/inc/seed.php';
}

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

add_action( 'after_switch_theme', 'starter_contact_schedule_cleanup' );
add_action( 'switch_theme',       'starter_contact_unschedule_cleanup' );
