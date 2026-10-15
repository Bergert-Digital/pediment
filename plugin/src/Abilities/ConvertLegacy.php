<?php
/**
 * Ability pediment/convert-legacy.
 *
 * @package Pediment
 */

declare(strict_types=1);

namespace Pediment\Abilities;

use Pediment\Convert\PostConverter;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Converts legacy Pediment blocks to core blocks with named styles.
 */
final class ConvertLegacy extends Ability {

	/**
	 * Constructor.
	 *
	 * @param PostConverter $converter Conversion service.
	 */
	public function __construct( private PostConverter $converter ) {}

	public function name(): string {
		return 'pediment/convert-legacy';
	}

	protected function label(): string {
		return __( 'Convert legacy blocks', 'pediment' );
	}

	protected function description(): string {
		return __( 'Replaces the 18 legacy Pediment blocks (hero, cta, feature-grid, faq, …) with core blocks and named styles. Pass ids (pages, posts, synced patterns, templates) or all: true (the posts in every language that hold a legacy block, at most 25 per call; remaining says how many come after this batch; pass after: <last id> to move on past posts that failed). dry_run defaults to true and returns a diff per post without writing; run again with dry_run false to write. Every written post keeps a revision first (restoreRevision; use rollback-page to undo a page). A post that cannot be converted safely is not written (status failed, errors says why). notes lists what changed beyond the swap, such as dropped icons or styles.', 'pediment' );
	}

	protected function inputSchema(): array {
		return array(
			'type'                 => 'object',
			'properties'           => array(
				'ids'     => array(
					'type'     => 'array',
					'items'    => array(
						'type'    => 'integer',
						'minimum' => 1,
					),
					'minItems' => 1,
				),
				'all'     => array( 'type' => 'boolean' ),
				'after'   => array(
					'type'    => 'integer',
					'minimum' => 0,
				),
				'dry_run' => array(
					'type'    => 'boolean',
					'default' => true,
				),
			),
			'additionalProperties' => false,
			'default'              => array(),
		);
	}

	protected function outputSchema(): array {
		$list = array(
			'type'  => 'array',
			'items' => array( 'type' => 'string' ),
		);
		return array(
			'type'       => 'object',
			'properties' => array(
				'dryRun'    => array( 'type' => 'boolean' ),
				'posts'     => array(
					'type'  => 'array',
					'items' => array(
						'type'       => 'object',
						'properties' => array(
							'id'              => array( 'type' => 'integer' ),
							'type'            => array( 'type' => 'string' ),
							'title'           => array( 'type' => 'string' ),
							'language'        => array( 'type' => 'string' ),
							'status'          => array(
								'type' => 'string',
								'enum' => array( 'preview', 'converted', 'unchanged', 'failed', 'skipped' ),
							),
							'converted'       => $list,
							'blocks'          => array( 'type' => 'integer' ),
							'unmapped'        => $list,
							'notes'           => $list,
							'errors'          => $list,
							'existingErrors'  => $list,
							'diff'            => array( 'type' => 'string' ),
							'revision'        => array( 'type' => 'integer' ),
							'restoreRevision' => array( 'type' => 'integer' ),
						),
						'required'   => array( 'id', 'type', 'title', 'language', 'status', 'converted', 'blocks', 'unmapped', 'notes', 'errors', 'existingErrors', 'diff', 'revision', 'restoreRevision' ),
					),
				),
				'summary'   => array(
					'type'       => 'object',
					'properties' => array_fill_keys( array( 'posts', 'blocks', 'converted', 'preview', 'unchanged', 'failed', 'skipped' ), array( 'type' => 'integer' ) ),
				),
				'remaining' => array( 'type' => 'integer' ),
			),
			'required'   => array( 'dryRun', 'posts', 'summary', 'remaining' ),
		);
	}

	protected function annotations(): array {
		return array(
			'readonly'    => false,
			'destructive' => true,
			'idempotent'  => true,
		);
	}

	public function permission( $input ): bool {
		if ( ! current_user_can( 'edit_pages' ) ) {
			return false;
		}
		foreach ( (array) ( ( (array) $input )['ids'] ?? array() ) as $id ) {
			$post = get_post( (int) $id );
			if ( ! $post instanceof \WP_Post ) {
				continue;
			}
			if ( ! current_user_can( 'edit_post', $post->ID ) ) {
				return false;
			}
			if ( in_array( $post->post_type, PostConverter::TEMPLATE_TYPES, true ) && ! current_user_can( 'edit_theme_options' ) ) {
				return false;
			}
		}
		return true;
	}

	public function execute( $input ) {
		$input   = (array) $input;
		// The REST route passes DELETE query values as strings ("false").
		$all     = rest_sanitize_boolean( $input['all'] ?? false );
		$dry_run = rest_sanitize_boolean( $input['dry_run'] ?? true );
		$ids     = array_map( 'intval', (array) ( $input['ids'] ?? array() ) );
		if ( $all === ( array() !== $ids ) ) {
			return self::errorList( 'pediment_convert_scope', __( 'Say which posts to convert.', 'pediment' ), array( 'pass either ids (a list of post ids) or all: true, not both and not neither' ) );
		}
		$can = static function ( int $id ): bool {
			$post = get_post( $id );
			return $post instanceof \WP_Post
				&& current_user_can( 'edit_post', $id )
				&& ( ! in_array( $post->post_type, PostConverter::TEMPLATE_TYPES, true ) || current_user_can( 'edit_theme_options' ) );
		};
		if ( ! $all ) {
			return $this->converter->runMany( $ids, $dry_run, $can ) + array( 'remaining' => 0 );
		}
		// Shared hosting limits request time: one batch per call, like the wp-admin tab.
		$after = absint( $input['after'] ?? 0 );
		$rest  = array_values( array_filter( $this->converter->candidates(), static fn( int $id ): bool => $id > $after ) );
		$batch = array_slice( $rest, 0, PostConverter::batchSize() );
		return $this->converter->runMany( $batch, $dry_run, $can ) + array( 'remaining' => count( $rest ) - count( $batch ) );
	}
}
