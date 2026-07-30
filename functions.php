<?php
/**
 * Pediment theme functions.
 *
 * Templates, patterns, the footer pattern, tokens, and global asset enqueues
 * moved into the plugin (Task 6 of the plugin-absorbs-theme migration); only
 * the theme-support declarations and the theme's own updater remain here.
 * Task 7 deletes this theme entirely.
 *
 * @package Pediment
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// One-click theme updates from GitHub Releases (no manual zip uploads).
if ( file_exists( __DIR__ . '/vendor/autoload.php' ) ) {
	require_once __DIR__ . '/vendor/autoload.php';
}
require_once __DIR__ . '/inc/ThemeUpdater.php';
\Pediment\ThemeUpdater::register();

add_action(
	'after_setup_theme',
	function () {
		load_theme_textdomain( 'pediment', get_template_directory() . '/languages' );
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
