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

	public function name(): string {
		return 'pediment/list-pages';
	}

	protected function label(): string {
		return __( 'List pages', 'pediment' );
	}

	protected function description(): string {
		return __( 'Lists pages of every language with id, title, slug, parent, status, language and link. Optional filters: language code, parent page id, status (default any).', 'pediment' );
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
				'pages' => array( 'type' => 'array', 'items' => self::pageSummarySchema() ),
			),
			'required'   => array( 'pages' ),
		);
	}

	protected function annotations(): array {
		return array( 'readonly' => true, 'destructive' => false, 'idempotent' => true );
	}

	public function permission( $input ): bool {
		return current_user_can( 'edit_pages' );
	}

	public function execute( $input ) {
		$input  = (array) $input;
		$status = (string) ( $input['status'] ?? 'any' );
		$args   = array(
			'post_type'      => 'page',
			'post_status'    => 'any' === $status ? self::STATUSES : $status,
			'posts_per_page' => 500,
			'orderby'        => 'menu_order title',
			'order'          => 'ASC',
			'no_found_rows'  => true,
			'perm'           => 'readable',
		);
		if ( isset( $input['parent'] ) ) {
			$args['post_parent'] = (int) $input['parent'];
		}
		$pages = array_map( array( PageData::class, 'summary' ), get_posts( LanguageRegistry::provider()->unscopedQuery( $args ) ) );
		if ( isset( $input['language'] ) ) {
			$pages = array_values( array_filter( $pages, static fn( array $page ): bool => $page['language'] === $input['language'] ) );
		}
		return array( 'pages' => $pages );
	}
}
