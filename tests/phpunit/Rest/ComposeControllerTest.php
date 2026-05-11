<?php
namespace StarterAi\Tests\Rest;

class ComposeControllerTest extends \WP_UnitTestCase {
	private \WP_REST_Server $server;

	public function setUp(): void {
		parent::setUp();
		\starter_ai_install_tables();
		global $wp_rest_server;
		$wp_rest_server = new \WP_REST_Server();
		$this->server   = $wp_rest_server;
		do_action( 'rest_api_init' );
		wp_set_current_user( $this->factory->user->create( [ 'role' => 'editor' ] ) );
	}

	public function test_returns_job_id_on_success(): void {
		$req = new \WP_REST_Request( 'POST', '/starter-ai/v1/compose' );
		$req->set_param( 'prompt',    'Hello' );
		$req->set_param( 'page_type', 'landing' );
		$res = $this->server->dispatch( $req );
		$this->assertSame( 202, $res->get_status() );
		$this->assertGreaterThan( 0, $res->get_data()['job_id'] );
	}

	public function test_rejects_unauthenticated(): void {
		wp_set_current_user( 0 );
		$req = new \WP_REST_Request( 'POST', '/starter-ai/v1/compose' );
		$req->set_param( 'prompt', 'x' );
		$res = $this->server->dispatch( $req );
		$this->assertSame( 401, $res->get_status() );
	}

	public function test_rejects_empty_prompt(): void {
		$req = new \WP_REST_Request( 'POST', '/starter-ai/v1/compose' );
		$req->set_param( 'prompt', '' );
		$res = $this->server->dispatch( $req );
		$this->assertSame( 400, $res->get_status() );
	}
}
