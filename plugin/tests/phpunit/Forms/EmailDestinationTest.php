<?php

class EmailDestinationTest extends WP_UnitTestCase {

	public function set_up(): void {
		parent::set_up();
		reset_phpmailer_instance();
		// The test site runs on localhost, and PHPMailer rejects wordpress@localhost.
		add_filter( 'wp_mail_from', static fn() => 'wordpress@example.com' );
		update_option(
			PEDIMENT_FORM_DESTINATIONS_OPTION,
			array(
				array(
					'id'             => 'mail',
					'type'           => 'email',
					'label'          => 'Sales mailbox',
					'to'             => array( 'sales@example.com', 'office@example.com' ),
					'subject'        => 'Request from {{ field:name }}',
					'reply_to_field' => 'email',
					'body_template'  => '',
				),
			)
		);
	}

	public function tear_down(): void {
		delete_option( PEDIMENT_FORM_DESTINATIONS_OPTION );
		delete_option( PEDIMENT_FORM_DEFAULT_DEST_OPTION );
		remove_all_filters( 'pre_wp_mail' );
		remove_all_filters( 'wp_mail_from' );
		remove_all_filters( 'pre_http_request' );
		remove_all_filters( 'pediment_form_warn_no_destination' );
		reset_phpmailer_instance();
		parent::tear_down();
	}

	private function make_submission( array $fields, string $destination = 'mail' ): int {
		$id = self::factory()->post->create( array( 'post_type' => PEDIMENT_FORM_CPT ) );
		update_post_meta( $id, '_fields', wp_slash( wp_json_encode( $fields ) ) );
		update_post_meta( $id, '_source_post_id', 0 );
		update_post_meta( $id, '_destination', $destination );
		update_post_meta( $id, '_delivery_status', 'pending' );
		return $id;
	}

	private function default_fields(): array {
		return array(
			'name'    => array( 'label' => 'Name', 'value' => 'Ada' ),
			'email'   => array( 'label' => 'E-Mail', 'value' => 'ada@example.org' ),
			'message' => array( 'label' => 'Nachricht', 'value' => "Line one\nLine two" ),
		);
	}

	public function test_record_without_type_is_http() {
		$this->assertSame( 'http', pediment_form_destination_type( array( 'id' => 'x' ) ) );
		$this->assertSame( 'http', pediment_form_destination_type( array( 'type' => 'bogus' ) ) );
		$this->assertSame( 'email', pediment_form_destination_type( array( 'type' => 'email' ) ) );
	}

	public function test_delivery_sends_mail_to_all_recipients_and_records_sent() {
		$http_called = false;
		add_filter(
			'pre_http_request',
			function () use ( &$http_called ) {
				$http_called = true;
				return new WP_Error( 'blocked', 'no http' );
			}
		);
		$id  = $this->make_submission( $this->default_fields() );
		$res = pediment_form_deliver( $id );

		$this->assertSame( 'sent', $res['status'] );
		$this->assertFalse( $http_called );
		$this->assertSame( 'sent', get_post_meta( $id, '_delivery_status', true ) );
		$this->assertSame( '', get_post_meta( $id, '_delivery_http_status', true ) );
		$this->assertStringContainsString( 'sales@example.com', (string) get_post_meta( $id, '_delivery_response', true ) );

		$mail = tests_retrieve_phpmailer_instance()->get_sent();
		$this->assertSame( array( 'sales@example.com', 'office@example.com' ), array_column( $mail->to, 0 ) );
		$this->assertSame( 'Request from Ada', $mail->subject );
		$this->assertStringContainsString( 'Reply-To: ada@example.org', $mail->header );
	}

	public function test_default_body_lists_fields_and_links_the_submission() {
		$id = $this->make_submission( $this->default_fields() );
		pediment_form_deliver( $id );

		$body = str_replace( "\r\n", "\n", tests_retrieve_phpmailer_instance()->get_sent()->body );
		$this->assertStringContainsString( 'Name: Ada', $body );
		$this->assertStringContainsString( 'E-Mail: ada@example.org', $body );
		$this->assertStringContainsString( "Nachricht:\nLine one\nLine two", $body );
		$this->assertStringContainsString( 'post.php?post=' . $id . '&action=edit', $body );
	}

	public function test_custom_body_template_renders_tokens_once() {
		$dest                  = pediment_form_get_destination( 'mail' );
		$dest['body_template'] = "Von {{ field:name }}\n{{ all_fields }}";
		update_option( PEDIMENT_FORM_DESTINATIONS_OPTION, array( $dest ) );

		$fields                  = $this->default_fields();
		$fields['name']['value'] = '{{ field:email }}';
		pediment_form_deliver( $this->make_submission( $fields ) );

		$body = tests_retrieve_phpmailer_instance()->get_sent()->body;
		$this->assertStringContainsString( 'Von {{ field:email }}', $body );
		$this->assertStringContainsString( 'E-Mail: ada@example.org', $body );
	}

	public function test_header_injection_in_subject_and_reply_to_is_blocked() {
		$fields = array(
			'name'  => array( 'label' => 'Name', 'value' => "Evil\r\nBcc: x@evil.test" ),
			'email' => array( 'label' => 'E-Mail', 'value' => "a@example.org\r\nBcc: y@evil.test" ),
		);
		$this->assertSame( 'sent', pediment_form_deliver( $this->make_submission( $fields ) )['status'] );

		$mail = tests_retrieve_phpmailer_instance()->get_sent();
		$this->assertSame( 'Request from Evil Bcc: x@evil.test', $mail->subject );
		$this->assertSame( array(), $mail->bcc );
		$this->assertStringNotContainsString( 'Reply-To', $mail->header );
		$this->assertStringNotContainsString( "\nBcc:", $mail->header );
	}

	public function test_invalid_reply_to_value_is_skipped() {
		$fields                   = $this->default_fields();
		$fields['email']['value'] = 'not-an-address';
		pediment_form_deliver( $this->make_submission( $fields ) );

		$this->assertStringNotContainsString( 'Reply-To', tests_retrieve_phpmailer_instance()->get_sent()->header );
	}

	public function test_empty_subject_uses_the_default() {
		$dest            = pediment_form_get_destination( 'mail' );
		$dest['subject'] = '';
		update_option( PEDIMENT_FORM_DESTINATIONS_OPTION, array( $dest ) );
		pediment_form_deliver( $this->make_submission( $this->default_fields() ) );

		$this->assertSame( pediment_form_default_mail_subject(), tests_retrieve_phpmailer_instance()->get_sent()->subject );
	}

	public function test_transport_failure_records_the_error_and_can_be_retried() {
		add_filter(
			'pre_wp_mail',
			function () {
				do_action( 'wp_mail_failed', new WP_Error( 'wp_mail_failed', 'SMTP connect() failed.' ) );
				return false;
			}
		);
		$id  = $this->make_submission( $this->default_fields() );
		$res = pediment_form_deliver( $id );

		$this->assertSame( 'failed', $res['status'] );
		$this->assertSame( 'SMTP connect() failed.', get_post_meta( $id, '_delivery_response', true ) );
		$this->assertSame( 'Failed', pediment_form_delivery_label( $id ) );

		remove_all_filters( 'pre_wp_mail' );
		$this->assertSame( 'sent', pediment_form_deliver( $id )['status'] );
	}

	public function test_no_valid_recipient_fails_without_sending() {
		update_option(
			PEDIMENT_FORM_DESTINATIONS_OPTION,
			array(
				array(
					'id'   => 'mail',
					'type' => 'email',
					'to'   => 'broken',
				),
			)
		);
		$id = $this->make_submission( $this->default_fields() );
		$this->assertSame( 'failed', pediment_form_deliver( $id )['status'] );
		$this->assertFalse( tests_retrieve_phpmailer_instance()->get_sent() );
	}

	public function test_email_destination_can_be_the_default() {
		update_option( PEDIMENT_FORM_DEFAULT_DEST_OPTION, 'mail' );
		$this->assertSame( 'sent', pediment_form_deliver( $this->make_submission( $this->default_fields(), '' ) )['status'] );
	}

	public function test_email_destination_is_offered_in_the_editor_dropdown() {
		$this->assertContains(
			array(
				'id'    => 'mail',
				'label' => 'Sales mailbox',
			),
			pediment_form_destination_choices()
		);
	}

	public function test_validation() {
		$this->assertSame( array(), pediment_form_validate_destination( pediment_form_get_destination( 'mail' ) ) );
		$this->assertArrayHasKey( 'to', pediment_form_validate_destination( array( 'type' => 'email' ) ) );
		$this->assertArrayHasKey(
			'to',
			pediment_form_validate_destination(
				array(
					'type' => 'email',
					'to'   => 'ok@example.com, nope',
				)
			)
		);
		$this->assertArrayHasKey(
			'body_template',
			pediment_form_validate_destination(
				array(
					'type'          => 'email',
					'to'            => 'ok@example.com',
					'body_template' => 'key {{ secret:api_key }}',
				)
			)
		);
		$this->assertArrayHasKey(
			'reply_to_field',
			pediment_form_validate_destination(
				array(
					'type'           => 'email',
					'to'             => 'ok@example.com',
					'reply_to_field' => 'e mail',
				)
			)
		);
	}

	public function test_sanitize_reads_email_fields_from_the_settings_form() {
		$result = pediment_form_sanitize_destination(
			array(
				'type'               => 'email',
				'label'              => 'Vertrieb',
				'to'                 => 'vertrieb@example.com; ok@example.com',
				'subject'            => 'Anfrage: {{ field:name }}',
				'reply_to_field'     => 'email',
				'body_template'      => '{"ignored":"http body"}',
				'mail_body_template' => '{{ all_fields }}',
				'method'             => 'POST',
			)
		);
		$this->assertSame( array(), $result['errors'] );
		$this->assertSame(
			array(
				'id'             => '',
				'type'           => 'email',
				'label'          => 'Vertrieb',
				'to'             => array( 'vertrieb@example.com', 'ok@example.com' ),
				'subject'        => 'Anfrage: {{ field:name }}',
				'reply_to_field' => 'email',
				'body_template'  => '{{ all_fields }}',
			),
			$result['dest']
		);
	}

	public function test_form_values_for_an_email_destination() {
		$v = pediment_form_destination_form_values( 'mail' );
		$this->assertTrue( $v['is_edit'] );
		$this->assertSame( 'email', $v['type'] );
		$this->assertSame( 'sales@example.com, office@example.com', $v['to'] );
		$this->assertSame( 'email', $v['reply_to_field'] );
	}

	public function test_test_send_mails_a_sample() {
		$res = pediment_form_test_destination( pediment_form_get_destination( 'mail' ) );
		$this->assertTrue( $res['ok'], $res['message'] );
		$this->assertSame( '[Test] Request from sample', tests_retrieve_phpmailer_instance()->get_sent()->subject );
	}

	public function test_no_destination_notice() {
		$this->assertSame( '', pediment_form_no_destination_notice() );

		$id = $this->make_submission( $this->default_fields(), '' );
		pediment_form_deliver( $id );
		$this->assertSame( 'no_destination', get_post_meta( $id, '_delivery_status', true ) );
		$this->assertStringContainsString( '1 form submission', pediment_form_no_destination_notice() );

		add_filter( 'pediment_form_warn_no_destination', '__return_false' );
		$this->assertSame( '', pediment_form_no_destination_notice() );
		remove_all_filters( 'pediment_form_warn_no_destination' );

		update_option( PEDIMENT_FORM_DEFAULT_DEST_OPTION, 'mail' );
		$this->assertSame( '', pediment_form_no_destination_notice() );
	}

	public function test_bulk_retry_delivers_unsent_submissions() {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		$id = $this->make_submission( $this->default_fields(), '' );
		pediment_form_deliver( $id );
		update_option( PEDIMENT_FORM_DEFAULT_DEST_OPTION, 'mail' );

		$url = pediment_form_handle_bulk_retry( 'edit.php', 'pediment_retry', array( $id ) );
		$this->assertStringContainsString( 'pediment_retried=1', $url );
		$this->assertSame( 'sent', get_post_meta( $id, '_delivery_status', true ) );
	}
}
