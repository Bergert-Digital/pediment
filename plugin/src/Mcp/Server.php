<?php
/**
 * The Pediment MCP server (needs the MCP Adapter plugin; inert without it).
 *
 * @package Pediment
 */

declare(strict_types=1);

namespace Pediment\Mcp;

use Pediment\Abilities\Registrar;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Exposes exactly the Pediment abilities as MCP tools at /wp-json/pediment/v1/mcp.
 */
final class Server {

	public const ID             = 'pediment';
	public const REST_NAMESPACE = 'pediment/v1';
	public const ROUTE          = 'mcp';

	/**
	 * Hooks the adapter. `mcp_adapter_init` only fires when the MCP Adapter plugin is active.
	 */
	public static function register(): void {
		add_action( 'mcp_adapter_init', array( self::class, 'create' ) );
	}

	/**
	 * Creates the server.
	 *
	 * @param object $adapter \WP\MCP\Core\McpAdapter.
	 */
	public static function create( $adapter ): void {
		$result = $adapter->create_server(
			self::ID,
			self::REST_NAMESPACE,
			self::ROUTE,
			'Pediment',
			__( 'Build and change pages and design styles on this site under Pediment’s content and design rules. Call pediment-get-vocabulary first.', 'pediment' ),
			PEDIMENT_AI_VERSION,
			array( 'WP\MCP\Transport\HttpTransport' ),
			'WP\MCP\Infrastructure\ErrorHandling\ErrorLogMcpErrorHandler',
			'WP\MCP\Infrastructure\Observability\NullMcpObservabilityHandler',
			Registrar::names()
		);
		if ( is_wp_error( $result ) ) {
			error_log( 'Pediment MCP server: ' . $result->get_error_message() ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
		}
	}
}
