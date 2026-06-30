<?php

class DeliveryTest extends WP_UnitTestCase {
	private array $captured = array();

	public function set_up(): void {
		parent::set_up();
		$this->captured = array();
		pediment_form_secret_set( 'brevo_api_key', 'sk-live' );
		update_option(
			PEDIMENT_FORM_DESTINATIONS_OPTION,
			array(
				array(
					'id'            => 'brevo_main',
					'label'         => 'Brevo',
					'method'        => 'POST',
					'url'           => 'https://api.brevo.com/v3/smtp/email',
					'headers'       => array( 'api-key' => '{{ secret:brevo_api_key }}' ),
					'content_type'  => 'application/json',
					'body_template' => '{"subject":"Hi {{ field:name }}","data":"{{ all_fields }}"}',
					'secret_refs'   => array( 'brevo_api_key' ),
				),
			)
		);
	}

	public function tear_down(): void {
		delete_option( PEDIMENT_FORM_DESTINATIONS_OPTION );
		delete_option( PEDIMENT_FORM_DEFAULT_DEST_OPTION );
		delete_option( PEDIMENT_FORM_SECRETS_OPTION );
		remove_all_filters( 'pre_http_request' );
		parent::tear_down();
	}

	private function make_submission( string $destination = 'brevo_main' ): int {
		$id = self::factory()->post->create( array( 'post_type' => PEDIMENT_FORM_CPT ) );
		update_post_meta( $id, '_fields', wp_json_encode( array( 'name' => array( 'label' => 'Name', 'value' => 'Ada' ) ) ) );
		update_post_meta( $id, '_source_post_id', 0 );
		update_post_meta( $id, '_destination', $destination );
		update_post_meta( $id, '_delivery_status', 'pending' );
		return $id;
	}

	private function stub_http( int $code, string $body = '{"ok":true}' ): void {
		add_filter(
			'pre_http_request',
			function ( $pre, $args, $url ) use ( $code, $body ) {
				$this->captured = array( 'args' => $args, 'url' => $url );
				return array(
					'response' => array( 'code' => $code, 'message' => 'x' ),
					'body'     => $body,
					'headers'  => array(),
				);
			},
			10,
			3
		);
	}

	public function test_successful_delivery_marks_sent_and_sends_rendered_body() {
		$this->stub_http( 200 );
		$id  = $this->make_submission();
		$res = pediment_form_deliver( $id );

		$this->assertSame( 'sent', $res['status'] );
		$this->assertSame( 'sent', get_post_meta( $id, '_delivery_status', true ) );
		$this->assertSame( 200, (int) get_post_meta( $id, '_delivery_http_status', true ) );

		$sent = json_decode( (string) $this->captured['args']['body'], true );
		$this->assertSame( 'Hi Ada', $sent['subject'] );
		$this->assertSame( array( 'name' => 'Ada' ), $sent['data'] );
		$this->assertSame( 'sk-live', $this->captured['args']['headers']['api-key'] );
	}

	public function test_non_2xx_marks_failed_with_snippet() {
		$this->stub_http( 422, '{"message":"bad"}' );
		$id  = $this->make_submission();
		$res = pediment_form_deliver( $id );

		$this->assertSame( 'failed', $res['status'] );
		$this->assertSame( 422, (int) get_post_meta( $id, '_delivery_http_status', true ) );
		$this->assertStringContainsString( 'bad', (string) get_post_meta( $id, '_delivery_response', true ) );
	}

	public function test_no_destination_is_recorded() {
		$id  = $this->make_submission( '' ); // empty + no default configured
		$res = pediment_form_deliver( $id );
		$this->assertSame( 'no_destination', $res['status'] );
		$this->assertSame( 'no_destination', get_post_meta( $id, '_delivery_status', true ) );
	}

	public function test_storing_a_submission_triggers_delivery() {
		$this->stub_http( 200 );
		$submission = array(
			'post_id'     => 0,
			'form_key'    => 'abc',
			'destination' => 'brevo_main',
			'fields'      => array( 'name' => array( 'label' => 'Name', 'value' => 'Ada' ) ),
		);
		do_action( 'pediment_form_submitted', $submission, null );

		$found = get_posts( array( 'post_type' => PEDIMENT_FORM_CPT, 'posts_per_page' => 1, 'fields' => 'ids' ) );
		$this->assertSame( 'sent', get_post_meta( $found[0], '_delivery_status', true ) );
	}
}
