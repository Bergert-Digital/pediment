<?php
namespace StarterAi\Tests\Rest;

use StarterAi\Anthropic\ProviderInterface;

class RefineControllerTest extends \WP_UnitTestCase {
	private \WP_REST_Server $server;

	public function setUp(): void {
		parent::setUp();
		global $wp_rest_server;
		$wp_rest_server = new \WP_REST_Server();
		$this->server   = $wp_rest_server;
		\StarterAi\Anthropic\SchemaBuilder::invalidate();

		add_filter( 'starter_ai_provider', function () {
			return new class implements ProviderInterface {
				public function messages( array $args ) {
					return [
						'content' => [
							[ 'type' => 'tool_use', 'id' => 't', 'name' => 'emit_block',
							  'input' => [ 'attributes' => [ 'headline' => 'Refined!' ], 'innerBlocks' => [] ] ],
						],
						'usage' => [ 'input_tokens' => 5, 'output_tokens' => 3 ],
						'model' => 'claude-haiku-4-5',
					];
				}
				public function stream_messages( array $args ) {
					return new \WP_Error( 'starter_ai_stub_not_implemented', 'stream_messages not implemented in stub' );
				}
			};
		} );

		register_block_type( 'starter/hero', [
			'attributes'  => [ 'headline' => [ 'type' => 'string' ] ],
			'description' => 'Hero',
		] );

		do_action( 'rest_api_init' );
		wp_set_current_user( $this->factory->user->create( [ 'role' => 'editor' ] ) );
	}

	public function tearDown(): void {
		unregister_block_type( 'starter/hero' );
		remove_all_filters( 'starter_ai_provider' );
		parent::tearDown();
	}

	public function test_returns_refined_attributes(): void {
		$req = new \WP_REST_Request( 'POST', '/starter-ai/v1/refine' );
		$req->set_param( 'blockName',    'starter/hero' );
		$req->set_param( 'attributes',   [ 'headline' => 'Old' ] );
		$req->set_param( 'innerBlocks',  [] );
		$req->set_param( 'instruction',  'Punchier' );

		$res = $this->server->dispatch( $req );
		$this->assertSame( 200, $res->get_status() );
		$this->assertSame( 'Refined!', $res->get_data()['attributes']['headline'] );
	}

	public function test_rejects_unknown_block(): void {
		$req = new \WP_REST_Request( 'POST', '/starter-ai/v1/refine' );
		$req->set_param( 'blockName',   'starter/unknown' );
		$req->set_param( 'attributes',  [] );
		$req->set_param( 'instruction', 'x' );
		$this->assertSame( 400, $this->server->dispatch( $req )->get_status() );
	}
}
