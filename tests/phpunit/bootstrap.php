<?php
/**
 * PHPUnit bootstrap: loads WP test harness and the theme.
 *
 * Runs inside wp-env's tests-wordpress container.
 */

$_tests_dir = getenv( 'WP_TESTS_DIR' );
if ( ! $_tests_dir ) {
	$_tests_dir = rtrim( sys_get_temp_dir(), '/\\' ) . '/wordpress-tests-lib';
}

if ( ! defined( 'WP_TESTS_PHPUNIT_POLYFILLS_PATH' ) ) {
	define( 'WP_TESTS_PHPUNIT_POLYFILLS_PATH', dirname( __DIR__, 2 ) . '/vendor/yoast/phpunit-polyfills' );
}

require_once $_tests_dir . '/includes/functions.php';

tests_add_filter(
	'muplugins_loaded',
	function () {
		switch_theme( 'pediment' );
		// The forms engine, settings hub, blocks, and block build all moved into
		// the plugin (Tasks 4 + 5 of the plugin-absorbs-theme migration). Blocks
		// are registered from the plugin now (see plugin/inc/register-blocks.php)
		// regardless of which theme is active, and the remaining theme tests
		// (Patterns/, Templates/) render patterns whose content is pediment/*
		// blocks, so the plugin has to be loaded alongside the theme here too —
		// matching how both artifacts run together in production.
		require_once dirname( __DIR__, 2 ) . '/plugin/plugin.php';
		require_once dirname( __DIR__, 2 ) . '/functions.php';
	}
);

require $_tests_dir . '/includes/bootstrap.php';
