<?php

class DestinationsTest extends WP_UnitTestCase {
	public function tear_down(): void {
		delete_option( PEDIMENT_FORM_DESTINATIONS_OPTION );
		delete_option( PEDIMENT_FORM_DEFAULT_DEST_OPTION );
		delete_option( PEDIMENT_FORM_SECRETS_OPTION );
		remove_all_filters( 'pediment_form_destinations' );
		parent::tear_down();
	}

	private function valid_dest(): array {
		return array(
			'id'            => 'brevo_main',
			'label'         => 'Brevo main',
			'method'        => 'POST',
			'url'           => 'https://api.brevo.com/v3/smtp/email',
			'headers'       => array( 'api-key' => '{{ secret:brevo_api_key }}' ),
			'content_type'  => 'application/json',
			'body_template' => '{"subject":"Hi {{ field:name }}","data":"{{ all_fields }}"}',
			'secret_refs'   => array( 'brevo_api_key' ),
		);
	}

	public function test_valid_destination_passes() {
		pediment_form_secret_set( 'brevo_api_key', 'sk' );
		$this->assertSame( array(), pediment_form_validate_destination( $this->valid_dest() ) );
	}

	public function test_rejects_non_https_url() {
		$d        = $this->valid_dest();
		$d['url'] = 'http://api.brevo.com/v3/smtp/email';
		$this->assertArrayHasKey( 'url', pediment_form_validate_destination( $d ) );
	}

	public function test_rejects_unknown_method() {
		$d           = $this->valid_dest();
		$d['method'] = 'DELETE';
		$this->assertArrayHasKey( 'method', pediment_form_validate_destination( $d ) );
	}

	public function test_rejects_invalid_json_body() {
		pediment_form_secret_set( 'brevo_api_key', 'sk' );
		$d                  = $this->valid_dest();
		$d['body_template'] = '{"broken": }';
		$this->assertArrayHasKey( 'body_template', pediment_form_validate_destination( $d ) );
	}

	public function test_rejects_secret_token_without_stored_secret() {
		// brevo_api_key not stored.
		$this->assertArrayHasKey( 'secret_refs', pediment_form_validate_destination( $this->valid_dest() ) );
	}

	public function test_rejects_unknown_meta_token() {
		pediment_form_secret_set( 'brevo_api_key', 'sk' );
		$d                  = $this->valid_dest();
		$d['body_template'] = '{"x":"{{ meta:secret_field }}"}';
		$this->assertArrayHasKey( 'body_template', pediment_form_validate_destination( $d ) );
	}

	public function test_registry_merges_filter_and_option_option_wins() {
		update_option( PEDIMENT_FORM_DESTINATIONS_OPTION, array( array( 'id' => 'shared', 'label' => 'From option' ) + $this->valid_dest() ) );
		add_filter( 'pediment_form_destinations', fn( $d ) => array_merge( $d, array( array( 'id' => 'shared', 'label' => 'From code' ), array( 'id' => 'codeonly', 'label' => 'Code only' ) ) ) );
		$all = pediment_form_destinations();
		$this->assertSame( 'From option', $all['shared']['label'] );
		$this->assertArrayHasKey( 'codeonly', $all );
	}

	public function test_resolve_falls_back_to_default() {
		update_option( PEDIMENT_FORM_DESTINATIONS_OPTION, array( $this->valid_dest() ) );
		update_option( PEDIMENT_FORM_DEFAULT_DEST_OPTION, 'brevo_main' );
		$this->assertSame( 'brevo_main', pediment_form_resolve_destination_id( '' ) );
		$this->assertSame( 'brevo_main', pediment_form_resolve_destination_id( 'does_not_exist' ) );

		delete_option( PEDIMENT_FORM_DEFAULT_DEST_OPTION );
		$this->assertSame( '', pediment_form_resolve_destination_id( '' ) );
	}
}
