<?php

use Pediment\Language\WpmlSetup;
use Pediment\Seeder\LanguageSpec;

class WpmlSetupTest extends WpmlTestCase {

	/** @return array<string,LanguageSpec> */
	private function manifestLanguages(): array {
		return [
			'en' => new LanguageSpec( 'en', 'English', 'en_US', 'gb', true ),
			'de' => new LanguageSpec( 'de', 'German', 'de_DE', 'de', false ),
		];
	}

	public function test_already_configured_reports_no_changes() {
		// The harness already activated en + de with default en.
		$result = ( new WpmlSetup() )->configure( $this->manifestLanguages(), 'en' );
		$this->assertSame( [], $result['changes'] );
		$this->assertSame( [], $result['errors'] );
	}

	public function test_dry_run_reports_a_missing_language_without_writing() {
		$langs = $this->manifestLanguages();
		$langs['fr'] = new LanguageSpec( 'fr', 'French', 'fr_FR', 'fr', false );

		$result = ( new WpmlSetup() )->configure( $langs, 'en', true );

		$this->assertNotEmpty( $result['changes'] );
		$active = apply_filters( 'wpml_active_languages', null );
		$this->assertArrayNotHasKey( 'fr', $active ); // nothing written.
	}

	public function test_errors_when_wpml_inactive_is_not_reachable_here() {
		// WPML is active in this suite; this asserts the happy path returns the
		// documented array shape.
		$result = ( new WpmlSetup() )->configure( $this->manifestLanguages(), 'en' );
		$this->assertArrayHasKey( 'changes', $result );
		$this->assertArrayHasKey( 'errors', $result );
	}

	/**
	 * The headless-deploy guarantee (Finding 3 / Task 17): after configure()
	 * activates the languages it must fire WPML's config parse, so a purely
	 * CLI `wp pediment languages` makes wp_navigation translatable — the state a
	 * seed's nav translation group depends on — WITHOUT anyone visiting wp-admin
	 * and WITHOUT a manual custom_posts_sync_option write. Translatability here
	 * can only arrive from WPML consuming inc/wpml-compat.php's
	 * `wpml_config_array` filter inside configure()'s load_config_run() call.
	 */
	public function test_configure_makes_navigation_translatable_via_config_parse() {
		global $sitepress;

		// Snapshot every piece of global state this test disturbs, and restore it
		// in a finally so a failure cannot poison sibling tests in the process.
		$original_sync    = $sitepress->get_setting( 'custom_posts_sync_option', [] );
		$original_has_run = WPML_Config::$has_run;
		$force_diff       = static fn() => 'zz';

		try {
			// Precondition: wp_navigation must start NON-translatable, so the
			// assertion below can only pass because configure() parsed the config.
			$sync = (array) $original_sync;
			unset( $sync['wp_navigation'] );
			$sitepress->set_setting( 'custom_posts_sync_option', $sync, true );
			$this->assertFalse(
				(bool) $sitepress->is_translated_post_type( 'wp_navigation' ),
				'precondition: wp_navigation starts non-translatable'
			);

			// WPML_Config::load_config_run() no-ops once per request via its own
			// $has_run guard; reset it so configure()'s call actually re-parses
			// (in a real CLI process this is always a fresh request).
			WPML_Config::$has_run = false;

			// The harness already has en+de active with default en, so configure()
			// would short-circuit at the no-changes gate BEFORE the activation
			// block (and thus before the parse). Force a non-empty diff by faking
			// the *read* of the default language; the activation sequence it then
			// runs (finish_step1/set_active_languages/finish_installation) is the
			// exact idempotent sequence the suite bootstrap already runs, and the
			// filter only intercepts apply_filters('wpml_default_language').
			add_filter( 'wpml_default_language', $force_diff, 99 );
			$result = ( new WpmlSetup() )->configure( $this->manifestLanguages(), 'en' );
			remove_filter( 'wpml_default_language', $force_diff, 99 );

			// No manual custom_posts_sync_option write happened between the
			// precondition and here — only configure() ran.
			$translatable = (bool) $sitepress->is_translated_post_type( 'wp_navigation' );
		} finally {
			remove_filter( 'wpml_default_language', $force_diff, 99 );
			$sitepress->set_setting( 'custom_posts_sync_option', $original_sync, true );
			WPML_Config::$has_run = $original_has_run;
		}

		$this->assertSame( [], $result['errors'] );
		$this->assertNotEmpty(
			$result['changes'],
			'configure() must reach the activation block for this to prove the parse'
		);
		$this->assertTrue(
			$translatable,
			'configure() must make wp_navigation WPML-translatable via load_config_run() — no manual write'
		);
	}
}
