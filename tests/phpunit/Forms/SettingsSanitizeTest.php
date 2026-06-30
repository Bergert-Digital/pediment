<?php

class SettingsSanitizeTest extends WP_UnitTestCase {
	public function tear_down(): void {
		delete_option( PEDIMENT_FORM_DESTINATIONS_OPTION );
		delete_option( PEDIMENT_FORM_SECRETS_OPTION );
		parent::tear_down();
	}

	private function raw(): array {
		return array(
			'id'            => 'My Brevo!',
			'label'         => '  Brevo main  ',
			'method'        => 'post',
			'url'           => 'https://api.brevo.com/v3/smtp/email',
			'content_type'  => 'application/json',
			'body_template' => '{"data":"{{ all_fields }}"}',
			'header_keys'   => array( 'api-key', '' ),
			'header_values' => array( '{{ secret:brevo_api_key }}', 'ignored' ),
		);
	}

	public function test_sanitize_normalizes_id_method_and_headers() {
		pediment_form_secret_set( 'brevo_api_key', 'sk' );
		$out  = pediment_form_sanitize_destination( $this->raw() );
		$dest = $out['dest'];

		$this->assertSame( array(), $out['errors'] );
		$this->assertSame( 'my_brevo', $dest['id'] );
		$this->assertSame( 'Brevo main', $dest['label'] );
		$this->assertSame( 'POST', $dest['method'] );
		$this->assertSame( array( 'api-key' => '{{ secret:brevo_api_key }}' ), $dest['headers'] );
		$this->assertContains( 'brevo_api_key', $dest['secret_refs'] );
	}

	public function test_sanitize_surfaces_validation_errors() {
		$raw        = $this->raw();
		$raw['url'] = 'http://insecure.example.com';
		$out        = pediment_form_sanitize_destination( $raw );
		$this->assertArrayHasKey( 'url', $out['errors'] );
	}

	public function test_save_and_delete_roundtrip() {
		pediment_form_secret_set( 'brevo_api_key', 'sk' );
		$dest = pediment_form_sanitize_destination( $this->raw() )['dest'];
		pediment_form_save_destination( $dest );
		$this->assertArrayHasKey( 'my_brevo', pediment_form_destinations() );

		pediment_form_delete_destination( 'my_brevo' );
		$this->assertArrayNotHasKey( 'my_brevo', pediment_form_destinations() );
	}

	public function test_save_upserts_by_id() {
		pediment_form_secret_set( 'brevo_api_key', 'sk' );
		$dest = pediment_form_sanitize_destination( $this->raw() )['dest'];
		pediment_form_save_destination( $dest );
		$dest['label'] = 'Renamed';
		pediment_form_save_destination( $dest );

		$all = pediment_form_destinations();
		$this->assertCount( 1, $all );
		$this->assertSame( 'Renamed', $all['my_brevo']['label'] );
	}
}
