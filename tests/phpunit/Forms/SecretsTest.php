<?php

class SecretsTest extends WP_UnitTestCase {
	public function tear_down(): void {
		delete_option( PEDIMENT_FORM_SECRETS_OPTION );
		parent::tear_down();
	}

	public function test_set_and_get_roundtrips() {
		pediment_form_secret_set( 'brevo_api_key', 'xkeysib-secret-123' );
		$this->assertSame( 'xkeysib-secret-123', pediment_form_secret_get( 'brevo_api_key' ) );
	}

	public function test_value_is_not_stored_in_plaintext() {
		pediment_form_secret_set( 'brevo_api_key', 'xkeysib-secret-123' );
		$raw = get_option( PEDIMENT_FORM_SECRETS_OPTION );
		$this->assertArrayHasKey( 'brevo_api_key', $raw );
		$this->assertStringNotContainsString( 'xkeysib-secret-123', (string) wp_json_encode( $raw ) );
	}

	public function test_empty_value_deletes_entry() {
		pediment_form_secret_set( 'tmp', 'value' );
		pediment_form_secret_set( 'tmp', '' );
		$this->assertSame( '', pediment_form_secret_get( 'tmp' ) );
		$this->assertNotContains( 'tmp', pediment_form_secret_names() );
	}

	public function test_names_are_sanitized_and_sorted() {
		pediment_form_secret_set( 'Zeta Key!', 'a' );
		pediment_form_secret_set( 'alpha', 'b' );
		$this->assertSame( array( 'alpha', 'zeta_key' ), pediment_form_secret_names() );
	}

	public function test_missing_secret_returns_empty_string() {
		$this->assertSame( '', pediment_form_secret_get( 'nope' ) );
	}
}
