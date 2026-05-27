<?php

class HoneypotTest extends WP_UnitTestCase {
	private $server;

	public function set_up(): void {
		parent::set_up();
		global $wp_rest_server;
		$wp_rest_server = new WP_REST_Server();
		$this->server   = $wp_rest_server;
		do_action( 'rest_api_init' );
	}

	private function submit( array $body ): WP_REST_Response {
		$request = new WP_REST_Request( 'POST', '/pediment/v1/contact' );
		foreach ( $body as $k => $v ) {
			$request->set_param( $k, $v );
		}
		return $this->server->dispatch( $request );
	}

	public function test_filled_honeypot_returns_400() {
		$r = $this->submit(
			array(
				'name'     => 'A',
				'email'    => 'a@b.com',
				'message'  => 'x',
				'hp_field' => 'bot was here',
				'_t'       => time() - 10,
			)
		);
		$this->assertSame( 400, $r->get_status() );
	}

	public function test_submission_within_5_seconds_returns_400() {
		$r = $this->submit(
			array(
				'name'     => 'A',
				'email'    => 'a@b.com',
				'message'  => 'x',
				'hp_field' => '',
				'_t'       => time() - 2,
			)
		);
		$this->assertSame( 400, $r->get_status() );
	}

	public function test_submission_with_future_timestamp_returns_400() {
		$r = $this->submit(
			array(
				'name'     => 'A',
				'email'    => 'a@b.com',
				'message'  => 'x',
				'hp_field' => '',
				'_t'       => time() + 60,
			)
		);
		$this->assertSame( 400, $r->get_status() );
	}
}
