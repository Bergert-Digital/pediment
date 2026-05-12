<?php
namespace StarterAi\Tests\Jobs;

use StarterAi\Anthropic\ProviderInterface;
use StarterAi\Jobs\ComposeJob;
use StarterAi\Jobs\JobStore;

class StubProvider implements ProviderInterface {
	public array $sentArgs = [];
	public function __construct( private readonly array $response ) {}
	public function messages( array $args ) {
		$this->sentArgs = $args;
		return $this->response;
	}
	public function stream_messages( array $args ) {
		return new \WP_Error( 'starter_ai_stub_not_implemented', 'stream_messages not implemented in stub' );
	}
}

class ComposeJobTest extends \WP_UnitTestCase {
	public function setUp(): void {
		parent::setUp();
		\starter_ai_install_tables();
		global $wpdb;
		$wpdb->query( "TRUNCATE {$wpdb->prefix}starter_ai_jobs" );
		\StarterAi\Anthropic\SchemaBuilder::invalidate();
	}

	public function test_successful_compose_writes_result_and_urls(): void {
		$store    = new JobStore();
		$provider = new StubProvider( [
			'content' => [
				[ 'type' => 'server_tool_use', 'id' => 'st_1', 'name' => 'web_fetch', 'input' => [ 'url' => 'https://acme.example/' ] ],
				[ 'type' => 'tool_use', 'id' => 'tu', 'name' => 'emit_page',
				  'input' => [ 'blocks' => [ [ 'name' => 'starter/hero', 'attributes' => [ 'headline' => 'Hi' ], 'innerBlocks' => [] ] ] ] ],
			],
			'usage' => [ 'input_tokens' => 100, 'output_tokens' => 50 ],
			'model' => 'claude-sonnet-4-6',
		] );

		register_block_type( 'starter/hero', [ 'attributes' => [ 'headline' => [ 'type' => 'string' ] ], 'description' => 'Hero' ] );

		$id = $store->create( 1, 'compose', [ 'prompt' => 'Make a landing page', 'page_type' => 'landing' ] );
		( new ComposeJob( $store, $provider ) )->run( $id );

		$job = $store->getById( $id );
		$this->assertSame( 'complete', $job['status'] );
		$this->assertSame( 'starter/hero', $job['result']['blocks'][0]['name'] );
		$this->assertContains( 'https://acme.example/', array_column( $job['events'], 'url_fetched' ) );

		unregister_block_type( 'starter/hero' );
	}

	public function test_failed_validation_writes_error(): void {
		$store    = new JobStore();
		$provider = new StubProvider( [
			'content' => [
				[ 'type' => 'tool_use', 'id' => 'tu', 'name' => 'emit_page',
				  'input' => [ 'blocks' => [ [ 'name' => 'starter/nope', 'attributes' => [], 'innerBlocks' => [] ] ] ] ],
			],
			'usage' => [ 'input_tokens' => 1, 'output_tokens' => 1 ],
			'model' => 'claude-sonnet-4-6',
		] );

		$id = $store->create( 1, 'compose', [ 'prompt' => 'x' ] );
		( new ComposeJob( $store, $provider ) )->run( $id );

		$job = $store->getById( $id );
		$this->assertSame( 'error', $job['status'] );
		$this->assertStringContainsString( 'starter/nope', $job['error_message'] );
	}

	public function test_wp_error_from_provider_marks_job_error(): void {
		$store    = new JobStore();
		$provider = new class implements ProviderInterface {
			public function messages( array $args ) { return new \WP_Error( 'down', 'API down' ); }
			public function stream_messages( array $args ) { return new \WP_Error( 'starter_ai_stub_not_implemented', 'stream_messages not implemented in stub' ); }
		};

		$id = $store->create( 1, 'compose', [ 'prompt' => 'x' ] );
		( new ComposeJob( $store, $provider ) )->run( $id );

		$job = $store->getById( $id );
		$this->assertSame( 'error', $job['status'] );
		$this->assertStringContainsString( 'API down', $job['error_message'] );
	}
}
