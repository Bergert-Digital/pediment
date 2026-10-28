<?php
/**
 * Ability pediment/read-page.
 *
 * @package Pediment
 */

declare(strict_types=1);

namespace Pediment\Abilities;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Returns one page (or post of another served type) with its raw block markup.
 */
final class ReadPage extends Ability {

	public function name(): string {
		return 'pediment/read-page';
	}

	protected function label(): string {
		return __( 'Read page', 'pediment' );
	}

	protected function description(): string {
		return __( 'Returns one page, or a post of the given post_type (default page): id, type, title, slug, parent, status, language, translations (language code → page id), raw block markup and revision count.', 'pediment' );
	}

	protected function inputSchema(): array {
		return array(
			'type'                 => 'object',
			'properties'           => array(
				'id'        => array( 'type' => 'integer', 'minimum' => 1 ),
				'post_type' => PageData::postTypeSchema(),
			),
			'required'             => array( 'id' ),
			'additionalProperties' => false,
		);
	}

	protected function outputSchema(): array {
		$schema                              = self::pageSummarySchema();
		$schema['properties']['translations'] = array( 'type' => 'object' );
		$schema['properties']['markup']       = array( 'type' => 'string' );
		$schema['properties']['revisions']    = array( 'type' => 'integer' );
		$schema['required']                   = array_merge( $schema['required'], array( 'translations', 'markup', 'revisions' ) );
		return $schema;
	}

	protected function annotations(): array {
		return array( 'readonly' => true, 'destructive' => false, 'idempotent' => true );
	}

	public function permission( $input ): bool {
		return PageData::canEditInput( $input );
	}

	public function execute( $input ) {
		$type    = PostTypes::fromInput( $input );
		$refused = PostTypes::refusal( $type );
		if ( null !== $refused ) {
			return $refused;
		}
		$page = PageData::find( (int) $input['id'], $type );
		return $page instanceof \WP_Post ? PageData::full( $page ) : $page;
	}
}
