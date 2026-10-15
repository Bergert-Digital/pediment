<?php

use Pediment\Abilities\Registrar;
use Pediment\Mcp\Server;

class ServerTest extends WP_UnitTestCase {

	public function test_creates_one_server_listing_exactly_the_pediment_abilities(): void {
		$adapter = new class() {
			public array $calls = array();
			public function create_server( ...$args ) {
				$this->calls[] = $args;
				return $this;
			}
		};

		Server::create( $adapter );

		$this->assertCount( 1, $adapter->calls );
		$args = $adapter->calls[0];
		$this->assertSame( 'pediment', $args[0] );
		$this->assertSame( 'pediment/v1', $args[1] );
		$this->assertSame( 'mcp', $args[2] );
		$this->assertSame( array( 'WP\MCP\Transport\HttpTransport' ), $args[6] );
		$this->assertSame( 'WP\MCP\Infrastructure\ErrorHandling\ErrorLogMcpErrorHandler', $args[7] );
		$this->assertSame( 'WP\MCP\Infrastructure\Observability\NullMcpObservabilityHandler', $args[8] );
		$this->assertSame( Registrar::names(), $args[9] );
		$this->assertContains( 'pediment/convert-legacy', $args[9] );
		$this->assertCount( 9, $args[9] );
	}

	public function test_is_hooked_on_the_adapter_init(): void {
		$this->assertNotFalse( has_action( 'mcp_adapter_init', array( Server::class, 'create' ) ) );
	}
}
