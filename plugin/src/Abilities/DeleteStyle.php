<?php
/**
 * Ability pediment/delete-style.
 *
 * @package Pediment
 */

declare(strict_types=1);

namespace Pediment\Abilities;

use Pediment\Design\SiteStyles;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Deletes a site-layer style (plugin and theme styles cannot be deleted here).
 */
final class DeleteStyle extends Ability {

	/**
	 * Constructor.
	 *
	 * @param SiteStyles $store Store.
	 */
	public function __construct( private SiteStyles $store ) {}

	public function name(): string {
		return 'pediment/delete-style';
	}

	protected function label(): string {
		return __( 'Delete style', 'pediment' );
	}

	protected function description(): string {
		return __( 'Deletes a site-layer style. A plugin or theme style with the same slug then applies again. Plugin and theme styles cannot be deleted.', 'pediment' );
	}

	protected function inputSchema(): array {
		return array(
			'type'                 => 'object',
			'properties'           => array( 'slug' => array( 'type' => 'string' ) ),
			'required'             => array( 'slug' ),
			'additionalProperties' => false,
		);
	}

	protected function outputSchema(): array {
		return array(
			'type'       => 'object',
			'properties' => array(
				'slug'    => array( 'type' => 'string' ),
				'deleted' => array( 'type' => 'boolean' ),
			),
			'required'   => array( 'slug', 'deleted' ),
		);
	}

	protected function annotations(): array {
		return array( 'readonly' => false, 'destructive' => true, 'idempotent' => false );
	}

	public function permission( $input ): bool {
		return current_user_can( 'edit_theme_options' );
	}

	public function execute( $input ) {
		$slug = (string) $input['slug'];
		if ( ! $this->store->delete( $slug ) ) {
			return self::errorList( 'pediment_style_not_found', __( 'No site-layer style with that slug.', 'pediment' ), array( "\"{$slug}\" is not a site-layer style (plugin and theme styles cannot be deleted here)" ), 404 );
		}
		return array( 'slug' => $slug, 'deleted' => true );
	}
}
