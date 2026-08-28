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
}
