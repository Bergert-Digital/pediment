<?php
/**
 * PHPUnit bootstrap for the WPML adapter suite. Separate process from the
 * monolingual and Polylang suites: WPML adds its own taxonomies and query
 * scoping to the whole process. One world per process.
 */

$_tests_dir = getenv( 'WP_TESTS_DIR' );
if ( ! $_tests_dir ) {
	$_tests_dir = rtrim( sys_get_temp_dir(), '/\\' ) . '/wordpress-tests-lib';
}

if ( ! defined( 'WP_TESTS_PHPUNIT_POLYFILLS_PATH' ) ) {
	define( 'WP_TESTS_PHPUNIT_POLYFILLS_PATH', dirname( __DIR__, 2 ) . '/vendor/yoast/phpunit-polyfills' );
}

require_once $_tests_dir . '/includes/functions.php';
require_once __DIR__ . '/language-definitions.php';

tests_add_filter( 'muplugins_loaded', function () {
	$wpml = WP_PLUGIN_DIR . '/sitepress-multilingual-cms/sitepress.php';
	if ( ! is_readable( $wpml ) ) {
		echo "WPML is not installed at {$wpml}. Provide plugin/wpml/wpml.zip and start the WPML env.\n";
		exit( 1 );
	}

	require $wpml;

	require dirname( __DIR__, 2 ) . '/vendor/autoload.php';
	require dirname( __DIR__, 2 ) . '/plugin.php';
} );

require $_tests_dir . '/includes/bootstrap.php';

require __DIR__ . '/WpmlTestCase.php';

/*
 * Activate en + de headlessly. VERIFY that this is sufficient for
 * `apply_filters('wpml_active_languages', null)` to return both, and record
 * the confirmed call in WPML-API-REFERENCE.md.
 */
pediment_wpml_activate_languages();
