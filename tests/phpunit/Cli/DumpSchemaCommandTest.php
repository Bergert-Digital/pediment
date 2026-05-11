<?php
namespace StarterAi\Tests\Cli;

use StarterAi\Cli\DumpSchemaCommand;

class DumpSchemaCommandTest extends \WP_UnitTestCase {
	public function setUp(): void {
		parent::setUp();
		\StarterAi\Anthropic\SchemaBuilder::invalidate();
		register_block_type( 'starter/test', [ 'attributes' => [ 'x' => [ 'type' => 'string' ] ], 'description' => 'T' ] );
	}

	public function tearDown(): void {
		unregister_block_type( 'starter/test' );
		parent::tearDown();
	}

	public function test_writes_schema_to_specified_path(): void {
		$path = sys_get_temp_dir() . '/starter-ai-schema-' . uniqid() . '.json';
		( new DumpSchemaCommand() )->__invoke( [], [ 'output' => $path ] );

		$this->assertFileExists( $path );
		$data = json_decode( (string) file_get_contents( $path ), true );
		$this->assertArrayHasKey( 'starter/test', $data['blocks'] );
		unlink( $path );
	}
}
