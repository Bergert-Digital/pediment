<?php

class MailTest extends WP_UnitTestCase {
	private array $mail_calls = array();

	public function set_up(): void {
		parent::set_up();
		$this->mail_calls = array();
		add_filter(
			'pre_wp_mail',
			function ( $short_circuit, $atts ) {
				$this->mail_calls[] = $atts;
				return true;
			},
			10,
			2
		);

		\Pediment\Brand::set( 'contact_email', 'owner@example.com' );
		\Pediment\Brand::set( 'brand_name', 'Acme Co' );
	}

	public function test_sends_mail_to_brand_email_by_default() {
		do_action(
			'pediment_contact_submitted',
			array(
				'name'    => 'Bob',
				'email'   => 'bob@example.com',
				'phone'   => '',
				'message' => 'Hi',
			),
			null
		);

		$this->assertCount( 1, $this->mail_calls );
		$atts = $this->mail_calls[0];
		$this->assertSame( array( 'owner@example.com' ), (array) $atts['to'] );
		$this->assertStringContainsString( 'Acme Co', $atts['subject'] );
		$this->assertStringContainsString( 'Bob', $atts['message'] );
		$this->assertStringContainsString( 'Hi', $atts['message'] );
		$this->assertContains( 'Reply-To: bob@example.com', $atts['headers'] );
	}

	public function test_recipient_override_via_request_data() {
		$request = new WP_REST_Request( 'POST', '/pediment/v1/contact' );
		$request->set_param( '_recipient_override', 'other@example.com' );

		do_action(
			'pediment_contact_submitted',
			array(
				'name'    => 'B',
				'email'   => 'b@b.com',
				'phone'   => '',
				'message' => 'm',
			),
			$request
		);

		$this->assertSame( array( 'other@example.com' ), (array) $this->mail_calls[0]['to'] );
	}
}
