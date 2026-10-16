<?php
/**
 * Ability pediment/rollback-page.
 *
 * @package Pediment
 */

declare(strict_types=1);

namespace Pediment\Abilities;

use Pediment\Content\InvalidBlockLog;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Restores an earlier revision of a page.
 */
final class RollbackPage extends Ability {

	public function name(): string {
		return 'pediment/rollback-page';
	}

	protected function label(): string {
		return __( 'Roll back page', 'pediment' );
	}

	protected function description(): string {
		return __( 'Restores the page with the given id to one of its own revisions (revision ids come from write-page results). Restoring saves a new revision that holds the restored content; the content it replaced stays available as an earlier revision.', 'pediment' );
	}

	protected function inputSchema(): array {
		return array(
			'type'                 => 'object',
			'properties'           => array(
				'id'       => array( 'type' => 'integer', 'minimum' => 1 ),
				'revision' => array( 'type' => 'integer', 'minimum' => 1 ),
			),
			'required'             => array( 'id', 'revision' ),
			'additionalProperties' => false,
		);
	}

	protected function outputSchema(): array {
		return array(
			'type'       => 'object',
			'properties' => array(
				'id'               => array( 'type' => 'integer' ),
				'restoredRevision' => array( 'type' => 'integer' ),
				'revision'         => array( 'type' => 'integer' ),
			),
			'required'   => array( 'id', 'restoredRevision', 'revision' ),
		);
	}

	protected function annotations(): array {
		return array( 'readonly' => false, 'destructive' => true, 'idempotent' => false );
	}

	public function permission( $input ): bool {
		return PageData::canEditInput( $input );
	}

	public function execute( $input ) {
		$id   = (int) $input['id'];
		$page = PageData::find( $id );
		if ( ! $page instanceof \WP_Post ) {
			return $page;
		}
		$revision_id = (int) $input['revision'];
		// wp_get_post_revision() takes its argument by reference.
		$revision = wp_get_post_revision( $revision_id );
		if ( ! $revision instanceof \WP_Post || (int) $revision->post_parent !== $id ) {
			return self::errorList( 'pediment_revision_mismatch', __( 'That revision does not belong to this page.', 'pediment' ), array( "revision {$input['revision']} is not a revision of page {$id}" ), 404 );
		}
		if ( ! wp_restore_post_revision( $revision->ID ) ) {
			return new \WP_Error( 'pediment_rollback_failed', __( 'The revision could not be restored.', 'pediment' ), array( 'status' => 500 ) );
		}
		InvalidBlockLog::clear( $id );
		return array(
			'id'               => $id,
			'restoredRevision' => $revision->ID,
			'revision'         => PageData::latestRevision( $id ),
		);
	}
}
