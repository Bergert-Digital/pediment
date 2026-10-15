<?php
/**
 * Ability pediment/list-pages.
 *
 * @package Pediment
 */

declare(strict_types=1);

namespace Pediment\Abilities;

use Pediment\Language\LanguageRegistry;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Lists pages in every language.
 */
final class ListPages extends Ability {

	private const STATUSES = array( 'publish', 'draft', 'pending', 'private', 'future' );

	/**
	 * Most pages one call returns; `truncated` says when more matched.
	 */
	public const LIMIT = 500;

	public function name(): string {
		return 'pediment/list-pages';
	}

	protected function label(): string {
		return __( 'List pages', 'pediment' );
	}

	protected function description(): string {
		/* translators: %d: maximum number of pages returned. */
		return sprintf( __( 'Lists pages of every language with id, title, slug, parent, status, language and link. Optional filters: language code, parent page id, status (default any). Returns at most %d pages; truncated is true when more pages matched, so narrow the filters before you conclude a page does not exist.', 'pediment' ), self::LIMIT );
	}

	protected function inputSchema(): array {
		return array(
			'type'                 => 'object',
			'properties'           => array(
				'language' => array( 'type' => 'string' ),
				'parent'   => array( 'type' => 'integer', 'minimum' => 0 ),
				'status'   => array( 'type' => 'string', 'enum' => array_merge( array( 'any' ), self::STATUSES ) ),
			),
			'additionalProperties' => false,
			'default'              => array(),
		);
	}

	protected function outputSchema(): array {
		return array(
			'type'       => 'object',
			'properties' => array(
				'pages'     => array( 'type' => 'array', 'items' => self::pageSummarySchema() ),
				'truncated' => array( 'type' => 'boolean' ),
			),
			'required'   => array( 'pages', 'truncated' ),
		);
	}

	protected function annotations(): array {
		return array( 'readonly' => true, 'destructive' => false, 'idempotent' => true );
	}

	public function permission( $input ): bool {
		return current_user_can( 'edit_pages' );
	}

	public function execute( $input ) {
		$input    = (array) $input;
		$status   = (string) ( $input['status'] ?? 'any' );
		$language = isset( $input['language'] ) ? (string) $input['language'] : null;
		$provider = LanguageRegistry::provider();
		$args     = array(
			'post_type'      => 'page',
			'post_status'    => 'any' === $status ? self::STATUSES : $status,
			// One more than the limit tells whether the list was cut. The
			// language filter runs on IDs below, so it needs every match.
			'posts_per_page' => null === $language ? self::LIMIT + 1 : -1,
			'fields'         => 'ids',
			'orderby'        => 'menu_order title',
			'order'          => 'ASC',
			'no_found_rows'  => true,
			'perm'           => 'readable',
		);
		if ( isset( $input['parent'] ) ) {
			$args['post_parent'] = (int) $input['parent'];
		}
		$ids = array_map( 'intval', get_posts( $provider->unscopedQuery( $args ) ) );
		if ( null !== $language ) {
			$ids = array_values( array_filter( $ids, static fn( int $id ): bool => $provider->languageOf( $id ) === $language ) );
		}
		$slice = array_slice( $ids, 0, self::LIMIT );
		_prime_post_caches( $slice, false, false );
		$pages = array();
		foreach ( $slice as $id ) {
			$post = get_post( $id );
			if ( $post instanceof \WP_Post ) {
				$pages[] = PageData::summary( $post );
			}
		}
		return array(
			'pages'     => $pages,
			'truncated' => count( $ids ) > self::LIMIT,
		);
	}
}
