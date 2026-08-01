<?php
/**
 * PHPUnit bootstrap for the Polylang adapter suite.
 *
 * Separate from tests/phpunit/bootstrap.php on purpose: loading Polylang adds a
 * `language` taxonomy and a parse_query filter to every query in the process,
 * which would change the meaning of the 545 monolingual tests. One process per
 * world.
 */

$_tests_dir = getenv( 'WP_TESTS_DIR' );
if ( ! $_tests_dir ) {
	$_tests_dir = rtrim( sys_get_temp_dir(), '/\\' ) . '/wordpress-tests-lib';
}

if ( ! defined( 'WP_TESTS_PHPUNIT_POLYFILLS_PATH' ) ) {
	define( 'WP_TESTS_PHPUNIT_POLYFILLS_PATH', dirname( __DIR__, 2 ) . '/vendor/yoast/phpunit-polyfills' );
}

require_once $_tests_dir . '/includes/functions.php';

tests_add_filter( 'muplugins_loaded', function () {
	$polylang = WP_PLUGIN_DIR . '/polylang/polylang.php';
	if ( ! is_readable( $polylang ) ) {
		echo "Polylang is not installed at {$polylang}. Run `npm run env:start` first.\n";
		exit( 1 );
	}

	/*
	 * The WP core test suite reinstalls a bare database for every run (a
	 * separate `install.php` process, before this one even starts), so
	 * `active_plugins` is empty even though Polylang is force-required
	 * below. Polylang's `Options` class treats itself as inactive unless
	 * `pll_is_plugin_active()` sees its basename in that option, and an
	 * "inactive" option silently no-ops every write to it — so the
	 * `PLL()->options->merge()` call further down would do nothing without
	 * this line, without raising any error.
	 */
	update_option( 'active_plugins', array( 'polylang/polylang.php' ) );

	require $polylang;

	/*
	 * Polylang only builds its `$GLOBALS['polylang']` context (PLL_Frontend,
	 * in a CLI/test process — see `Polylang::init()`) when
	 * `$model->has_languages()` is true, and that check runs on the *first*
	 * `plugins_loaded` firing — before this script otherwise gets a chance
	 * to configure anything. Without languages yet, `$class` stays empty,
	 * `init_context()` is never called, `src/api.php` is never loaded, and
	 * `PLL()` is left undefined for the rest of the process.
	 *
	 * Seed the language terms now, through a throwaway `PLL_Model`, so they
	 * already exist in the DB by the time Polylang's own `init()` runs on
	 * `plugins_loaded`. Constructing a `PLL_Model` is also what registers
	 * the `language` taxonomy as a side effect (`PLL_Translatable_Object`'s
	 * constructor calls `register_taxonomy()` "as soon as possible"), so
	 * this is safe to do here, before `init`.
	 */
	add_action( 'pll_init_options_for_blog', array( \WP_Syntex\Polylang\Options\Registry::class, 'register' ) );
	$seed_model = new PLL_Model( new \WP_Syntex\Polylang\Options\Options() );

	foreach ( [
		[ 'slug' => 'en', 'name' => 'English', 'locale' => 'en_US', 'flag' => 'gb', 'rtl' => 0, 'term_group' => 0 ],
		[ 'slug' => 'de', 'name' => 'Deutsch', 'locale' => 'de_DE', 'flag' => 'de', 'rtl' => 0, 'term_group' => 1 ],
	] as $language ) {
		$seed_model->add_language( $language );
	}

	require dirname( __DIR__, 2 ) . '/vendor/autoload.php';
	require dirname( __DIR__, 2 ) . '/plugin.php';
} );

require $_tests_dir . '/includes/bootstrap.php';

do_action( 'rest_api_init' );

/**
 * The languages were seeded above, before Polylang's own PLL_Frontend
 * context was created on `plugins_loaded`, so `PLL()` is already backed by
 * a model that sees both of them. This just asserts the options the rest of
 * the suite relies on and makes sure the languages cache is fresh.
 *
 * Written through PLL()->options->merge() + save(), never update_option():
 * since 3.7 Polylang holds its options in memory and flushes them on shutdown,
 * so a raw option write is both invisible to this process and overwritten at
 * the end of it.
 */
PLL()->options->merge( [ 'default_lang' => 'en', 'hide_default' => 1, 'force_lang' => 1, 'media_support' => 0 ] );
PLL()->options->save();
PLL()->model->clean_languages_cache();

/** @return string[] The slugs this harness configured. */
function pediment_test_languages(): array {
	return [ 'en', 'de' ];
}
