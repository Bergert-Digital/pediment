<?php

namespace Pediment\Tests\Forms;

/**
 * Confirmation mail to the sender: content guard, headers, rate limit and
 * the record on the submission.
 */
class ConfirmationTest extends \WP_UnitTestCase {
	private array $mails = array();
	private string $ip   = '203.0.113.7';

	public function set_up(): void {
		parent::set_up();
		$this->mails = array();
		add_filter( 'wp_mail_from', static fn() => 'wordpress@example.com' );
		add_filter(
			'wp_mail',
			function ( $args ) {
				$this->mails[] = $args;
				return $args;
			}
		);
		add_filter( 'pediment_form_client_ip', fn() => $this->ip );
		update_option(
			PEDIMENT_FORM_DESTINATIONS_OPTION,
			array(
				array(
					'id'           => 'jobs',
					'type'         => 'email',
					'label'        => 'Applications',
					'to'           => array( 'team@example.com' ),
					'confirmation' => array(
						'to_field' => 'email',
						'reply_to' => 'jobs@example.com',
						'subject'  => 'Deine Bewerbung, {{ field:name }}',
						'body'     => "Hallo {{ field:name }},\n\ndanke.\n{{ meta:page_url }}",
					),
				),
			)
		);
		reset_phpmailer_instance();
	}

	public function tear_down(): void {
		delete_option( PEDIMENT_FORM_DESTINATIONS_OPTION );
		remove_all_filters( 'wp_mail' );
		remove_all_filters( 'wp_mail_from' );
		remove_all_filters( 'pediment_form_client_ip' );
		remove_all_filters( 'pediment_form_confirmation_limit_ip' );
		global $wpdb;
		$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '%pediment_fc_%'" ); // phpcs:ignore WordPress.DB
		wp_cache_flush();
		reset_phpmailer_instance();
		parent::tear_down();
	}

	private function submission( array $values ): int {
		$fields = array();
		foreach ( $values as $name => $value ) {
			$fields[ $name ] = array(
				'label' => ucfirst( $name ),
				'value' => $value,
			);
		}
		$id = self::factory()->post->create( array( 'post_type' => PEDIMENT_FORM_CPT ) );
		update_post_meta( $id, '_fields', wp_slash( wp_json_encode( $fields ) ) );
		update_post_meta( $id, '_source_post_id', 0 );
		update_post_meta( $id, '_destination', 'jobs' );
		return $id;
	}

	private function confirmation_mail(): ?array {
		foreach ( $this->mails as $mail ) {
			if ( in_array( 'ada@example.org', (array) $mail['to'], true ) ) {
				return $mail;
			}
		}
		return null;
	}

	public function test_stored_submission_sends_team_mail_and_confirmation() {
		$id = $this->submission( array( 'name' => 'Ada', 'email' => 'ada@example.org', 'message' => 'Secret long text' ) );
		do_action( 'pediment_form_stored', $id, array() );

		$this->assertCount( 2, $this->mails );
		$mail = $this->confirmation_mail();
		$this->assertSame( 'Deine Bewerbung, Ada', $mail['subject'] );
		$this->assertStringStartsWith( "Hallo Ada,\n\ndanke.", $mail['message'] );
		$this->assertStringNotContainsString( 'Secret long text', $mail['message'] );
		$this->assertContains( 'Reply-To: jobs@example.com', $mail['headers'] );
		$this->assertSame( array(), $mail['attachments'] );
		$this->assertSame( 'sent', get_post_meta( $id, '_confirmation_status', true ) );
		$this->assertSame( 'sent', get_post_meta( $id, '_delivery_status', true ) );
	}

	public function test_visitor_values_are_one_line_without_tags_and_short() {
		$name = "<b>Ada</b>\r\nBcc: victim@example.com " . str_repeat( 'x', 200 );
		$id   = $this->submission( array( 'name' => $name, 'email' => 'ada@example.org' ) );

		pediment_form_send_confirmation( $id );

		$mail  = $this->confirmation_mail();
		$first = strtok( $mail['message'], "\n" );
		$this->assertStringNotContainsString( '<b>', $mail['message'] );
		$this->assertStringContainsString( 'Ada Bcc: victim@example.com', $first );
		$this->assertLessThanOrEqual( mb_strlen( 'Hallo ,' ) + PEDIMENT_FORM_CONFIRM_VALUE_MAX, mb_strlen( $first ) );
		$this->assertStringNotContainsString( "\n", $mail['subject'] );
	}

	public function test_invalid_or_multiline_address_sends_nothing() {
		$id = $this->submission( array( 'name' => 'Ada', 'email' => "ada@example.org\nBcc: x@example.com" ) );
		$this->assertSame( 'no_address', pediment_form_send_confirmation( $id ) );

		$id = $this->submission( array( 'name' => 'Ada', 'email' => 'not-an-address' ) );
		$this->assertSame( 'no_address', pediment_form_send_confirmation( $id ) );
		$this->assertSame( array(), $this->mails );
	}

	public function test_rate_limit_per_recipient() {
		$statuses = array();
		for ( $i = 0; $i < 4; $i++ ) {
			$this->ip   = '203.0.113.' . ( 10 + $i );
			$statuses[] = pediment_form_send_confirmation( $this->submission( array( 'name' => 'Ada', 'email' => 'Ada@Example.org' ) ) );
		}
		$this->assertSame( array( 'sent', 'sent', 'sent', 'rate_limited' ), $statuses );
	}

	public function test_rate_limit_per_ip() {
		add_filter( 'pediment_form_confirmation_limit_ip', static fn() => 2 );
		$statuses = array();
		for ( $i = 0; $i < 3; $i++ ) {
			$statuses[] = pediment_form_send_confirmation( $this->submission( array( 'name' => 'Ada', 'email' => "a{$i}@example.org" ) ) );
		}
		$this->assertSame( array( 'sent', 'sent', 'rate_limited' ), $statuses );
	}

	public function test_retry_delivery_does_not_resend_the_confirmation() {
		$id = $this->submission( array( 'name' => 'Ada', 'email' => 'ada@example.org' ) );
		pediment_form_deliver( $id );
		$this->assertNull( $this->confirmation_mail() );
	}

	public function test_destination_without_confirmation_sends_none() {
		update_option( PEDIMENT_FORM_DESTINATIONS_OPTION, array( array( 'id' => 'jobs', 'type' => 'email', 'to' => array( 'team@example.com' ) ) ) );
		$this->assertSame( '', pediment_form_send_confirmation( $this->submission( array( 'email' => 'ada@example.org' ) ) ) );
	}

	public function test_validation_refuses_all_fields_secrets_and_missing_text() {
		$errors = pediment_form_validate_confirmation(
			array(
				'to_field' => 'email',
				'reply_to' => 'nope',
				'subject'  => '',
				'body'     => '{{ all_fields }}',
			)
		);
		$this->assertArrayHasKey( 'confirmation_reply_to', $errors );
		$this->assertArrayHasKey( 'confirmation_subject', $errors );
		$this->assertArrayHasKey( 'confirmation_body', $errors );

		$errors = pediment_form_validate_confirmation( array( 'to_field' => 'email', 'subject' => 'x', 'body' => '{{ secret:key }}' ) );
		$this->assertArrayHasKey( 'confirmation_body', $errors );

		$this->assertArrayHasKey( 'confirmation', pediment_form_validate_confirmation( array( 'bcc' => 'x' ) ) );
		$this->assertSame( array(), pediment_form_validate_confirmation( array() ) );
	}

	public function test_settings_form_saves_the_confirmation() {
		$saved = pediment_form_sanitize_destination(
			array(
				'type'         => 'email',
				'to'           => 'team@example.com',
				'confirmation' => array(
					'to_field' => 'email',
					'reply_to' => 'jobs@example.com',
					'subject'  => 'Danke',
					'body'     => "Hallo {{ field:name }}\nbis bald",
				),
			)
		);
		$this->assertSame( array(), $saved['errors'] );
		$this->assertSame( 'email', $saved['dest']['confirmation']['to_field'] );
		$this->assertSame( "Hallo {{ field:name }}\nbis bald", $saved['dest']['confirmation']['body'] );

		$off = pediment_form_sanitize_destination( array( 'type' => 'email', 'to' => 'team@example.com', 'confirmation' => array( 'to_field' => '' ) ) );
		$this->assertArrayNotHasKey( 'confirmation', $off['dest'] );
	}

	public function test_meta_box_shows_the_confirmation_status() {
		$id = $this->submission( array( 'name' => 'Ada', 'email' => 'ada@example.org' ) );
		pediment_form_send_confirmation( $id );

		ob_start();
		pediment_form_render_submission_box( get_post( $id ) );
		$html = (string) ob_get_clean();

		$this->assertStringContainsString( 'Confirmation', $html );
	}
}
