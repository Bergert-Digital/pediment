<?php
/**
 * Editor-side safety net: the block editor reports blocks it could not
 * validate on a page; the check-page ability reads the report.
 *
 * @package Pediment
 */

declare(strict_types=1);

namespace Pediment\Content;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Stores the last editor validity report per page in post meta.
 */
final class InvalidBlockLog {

	public const META = '_pediment_invalid_blocks';

	/**
	 * Hooks the REST route and the editor script.
	 */
	public static function register(): void {
		add_action( 'rest_api_init', array( self::class, 'registerRoute' ) );
		add_action( 'enqueue_block_editor_assets', array( self::class, 'enqueue' ) );
	}

	/**
	 * POST /pediment/v1/invalid-blocks/<id>.
	 */
	public static function registerRoute(): void {
		register_rest_route(
			'pediment/v1',
			'/invalid-blocks/(?P<id>\d+)',
			array(
				'methods'             => 'POST',
				'permission_callback' => static fn( \WP_REST_Request $request ): bool => current_user_can( 'edit_post', (int) $request['id'] ),
				'args'                => array(
					'blocks' => array(
						'type'     => 'array',
						'required' => true,
						'maxItems' => 500,
						'items'    => array(
							'type'    => 'string',
							'pattern' => '^[a-z0-9-]+/[a-z0-9-]+$',
						),
					),
				),
				'callback'            => static function ( \WP_REST_Request $request ) {
					$id = (int) $request['id'];
					if ( 'page' !== get_post_type( $id ) ) {
						return new \WP_Error( 'pediment_not_a_page', __( 'Not a page.', 'pediment' ), array( 'status' => 404 ) );
					}
					return rest_ensure_response( self::record( $id, (array) $request['blocks'] ) );
				},
			)
		);
	}

	/**
	 * Enqueues the reporter in the block editor.
	 */
	public static function enqueue(): void {
		$rel = 'assets/js/invalid-blocks.js';
		wp_enqueue_script(
			'pediment-invalid-blocks',
			PEDIMENT_AI_PLUGIN_URL . $rel,
			array( 'wp-data', 'wp-api-fetch', 'wp-editor', 'wp-block-editor' ),
			(string) filemtime( PEDIMENT_AI_PLUGIN_DIR . '/' . $rel ),
			true
		);
	}

	/**
	 * Stores a report.
	 *
	 * @param int          $post_id Page ID.
	 * @param list<string> $blocks  Invalid block names.
	 * @return array{checkedAt:string,blocks:list<string>}
	 */
	public static function record( int $post_id, array $blocks ): array {
		$entry = array(
			'checkedAt' => gmdate( 'c' ),
			'blocks'    => array_values( array_unique( array_map( 'strval', $blocks ) ) ),
		);
		update_post_meta( $post_id, self::META, $entry );
		return $entry;
	}

	/**
	 * The last report, or null when none exists.
	 *
	 * @param int $post_id Page ID.
	 */
	public static function get( int $post_id ): ?array {
		$entry = get_post_meta( $post_id, self::META, true );
		return is_array( $entry ) ? $entry : null;
	}

	/**
	 * Removes the report (content changed, so it is stale).
	 *
	 * @param int $post_id Page ID.
	 */
	public static function clear( int $post_id ): void {
		delete_post_meta( $post_id, self::META );
	}
}
