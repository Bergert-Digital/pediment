<?php
/**
 * The languages the WPML adapter suite configures: en (default) + de.
 * Mirrors tests/polylang/language-definitions.php so both suites assert the
 * same shape.
 *
 * @return array<int, array{code:string,name:string,locale:string,default:int}>
 */
function pediment_wpml_test_language_definitions(): array {
	return [
		[ 'code' => 'en', 'name' => 'English', 'locale' => 'en_US', 'default' => 1 ],
		[ 'code' => 'de', 'name' => 'German',  'locale' => 'de_DE', 'default' => 0 ],
	];
}

/** @return string[] */
function pediment_wpml_test_languages(): array {
	return array_map( static fn( array $l ): string => $l['code'], pediment_wpml_test_language_definitions() );
}

/**
 * Activate en + de in WPML headlessly. The confirmed-working sequence — see
 * tests/wpml/WPML-API-REFERENCE.md. Called once from bootstrap.php for the
 * whole process, and again per test class from WpmlTestCase, because WP core's
 * tear_down_after_class() commits a _delete_all_data() that wipes the rows.
 *
 * This is the ground truth this suite exists to pin down; the body is settled
 * against the real WPML build during Step 7.
 */
function pediment_wpml_activate_languages(): void {
	global $sitepress;

	// Seed the settings WPML's setup routine reads before it can activate a
	// language: the default language must be known before set_active_languages()
	// refreshes its cache against it.
	$settings                           = get_option( 'icl_sitepress_settings', [] );
	$settings['default_language']       = 'en';
	$settings['admin_default_language'] = 'en';
	update_option( 'icl_sitepress_settings', $settings );
	$GLOBALS['sitepress_settings']      = $settings;

	if ( ! function_exists( 'wpml_get_setup_instance' ) || ! is_object( $sitepress ) ) {
		return;
	}

	// Drive WPML's own installation API — the same calls its setup wizard's
	// FinishStep endpoint makes, minus the TM/roles/telemetry side effects.
	// set_active_languages() flips the `active` flag in wp_icl_languages (the
	// source of truth `wpml_active_languages` reads) and refreshes the caches.
	$setup = wpml_get_setup_instance();
	$setup->finish_step1( 'en' );
	$setup->set_active_languages( [ 'en', 'de' ] );
	$setup->finish_installation();

	if ( function_exists( 'wpml_reload_active_languages_setting' ) ) {
		wpml_reload_active_languages_setting( true );
	}
}
