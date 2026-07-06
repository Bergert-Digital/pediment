<?php

namespace Pediment\Tests\Forms;

class SubmissionTest extends \WP_UnitTestCase {
	private $server;
	private int $post_id;
	private string $form_key;

	public function set_up(): void {
		parent::set_up();
		global $wp_rest_server;
		$wp_rest_server = new \WP_REST_Server();
		$this->server   = $wp_rest_server;
		do_action( 'rest_api_init' );

		$markup = '<!-- wp:pediment/form {"destination":"sales"} -->'
			. '<!-- wp:pediment/form-field {"label":"Name","fieldName":"name","required":true} /-->'
			. '<!-- wp:pediment/form-field {"fieldType":"email","label":"Email","fieldName":"email","required":true} /-->'
			. '<!-- /wp:pediment/form -->';
		$this->post_id  = (int) self::factory()->post->create( array( 'post_content' => $markup ) );
		$inner          = parse_blocks( $markup )[0]['innerBlocks'];
		$this->form_key = pediment_form_form_key( pediment_form_collect_fields( $inner ) );
	}

	private function submit( array $body ): \WP_REST_Response {
		$request = new \WP_REST_Request( 'POST', '/pediment/v1/forms' );
		$request->set_header( 'Content-Type', 'application/json' );
		$request->set_body( (string) wp_json_encode( $body ) );
		return $this->server->dispatch( $request );
	}

	private function base( array $fields ): array {
		return array(
			'post_id'  => $this->post_id,
			'form_key' => $this->form_key,
			'hp_field' => '',
			'_t'       => time() - 10,
			'fields'   => $fields,
		);
	}

	public function test_valid_submission_returns_200_and_fires_action() {
		$captured = null;
		add_action( 'pediment_form_submitted', function ( $s ) use ( &$captured ) { $captured = $s; }, 5 );

		$r = $this->submit( $this->base( array( 'name' => 'Alice', 'email' => 'alice@example.com' ) ) );

		$this->assertSame( 200, $r->get_status() );
		$this->assertTrue( $r->get_data()['ok'] );
		$this->assertSame( 'sales', $captured['destination'] );
		$this->assertSame( 'Alice', $captured['fields']['name']['value'] );
	}

	public function test_missing_required_returns_400() {
		$r = $this->submit( $this->base( array( 'name' => '', 'email' => 'a@b.com' ) ) );
		$this->assertSame( 400, $r->get_status() );
	}

	public function test_unknown_field_returns_400() {
		$r = $this->submit( $this->base( array( 'name' => 'A', 'email' => 'a@b.com', 'evil' => 'x' ) ) );
		$this->assertSame( 400, $r->get_status() );
	}

	public function test_honeypot_returns_400() {
		$body             = $this->base( array( 'name' => 'A', 'email' => 'a@b.com' ) );
		$body['hp_field'] = 'bot';
		$this->assertSame( 400, $this->submit( $body )->get_status() );
	}

	public function test_unknown_form_key_returns_400() {
		$body             = $this->base( array( 'name' => 'A', 'email' => 'a@b.com' ) );
		$body['form_key'] = 'deadbeef0000';
		$this->assertSame( 400, $this->submit( $body )->get_status() );
	}
}
