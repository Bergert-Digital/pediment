<?php

class SubmissionTest extends WP_UnitTestCase {
	private $server;

	public function set_up(): void {
		parent::set_up();
		global $wp_rest_server;
		$wp_rest_server = new WP_REST_Server();
		$this->server   = $wp_rest_server;
		do_action( 'rest_api_init' );
	}

	private function submit( array $body ): WP_REST_Response {
		$request = new WP_REST_Request( 'POST', '/starter/v1/contact' );
		foreach ( $body as $k => $v ) {
			$request->set_param( $k, $v );
		}
		return $this->server->dispatch( $request );
	}

	public function test_valid_submission_returns_200() {
		$r = $this->submit(
			array(
				'name'     => 'Alice',
				'email'    => 'alice@example.com',
				'message'  => 'Hello!',
				'hp_field' => '',
				'_t'       => time() - 10,
			)
		);
		$this->assertSame( 200, $r->get_status() );
		$data = $r->get_data();
		$this->assertTrue( $data['ok'] );
	}

	public function test_missing_name_returns_400() {
		$r = $this->submit(
			array(
				'name'    => '',
				'email'   => 'a@b.com',
				'message' => 'x',
				'_t'      => time() - 10,
			)
		);
		$this->assertSame( 400, $r->get_status() );
	}

	public function test_invalid_email_returns_400() {
		$r = $this->submit(
			array(
				'name'    => 'A',
				'email'   => 'not-an-email',
				'message' => 'x',
				'_t'      => time() - 10,
			)
		);
		$this->assertSame( 400, $r->get_status() );
	}

	public function test_missing_message_returns_400() {
		$r = $this->submit(
			array(
				'name'    => 'A',
				'email'   => 'a@b.com',
				'message' => '',
				'_t'      => time() - 10,
			)
		);
		$this->assertSame( 400, $r->get_status() );
	}
}
