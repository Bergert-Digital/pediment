<?php
/**
 * Ability pediment/upsert-style.
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
 * Creates or updates a site-layer style.
 */
final class UpsertStyle extends Ability {

	/**
	 * Constructor.
	 *
	 * @param SiteStyles $store Store.
	 */
	public function __construct( private SiteStyles $store ) {}

	public function name(): string {
		return 'pediment/upsert-style';
	}

	protected function label(): string {
		return __( 'Create or update style', 'pediment' );
	}

	protected function description(): string {
		return __( 'Creates or updates a named block style in the site layer (it overrides plugin and theme styles with the same slug, property by property). Values must be tokens; css may only use &-rules, no colours, no "<". Every change keeps a revision. Pages use it with className "is-style-<slug>".', 'pediment' );
	}

	protected function inputSchema(): array {
		return array(
			'type'                 => 'object',
			'properties'           => array(
				'slug'       => array( 'type' => 'string' ),
				'label'      => array( 'type' => 'string' ),
				'blockTypes' => array( 'type' => 'array', 'items' => array( 'type' => 'string' ), 'minItems' => 1 ),
				'styles'     => array( 'type' => 'object' ),
			),
			'required'             => array( 'slug', 'label', 'blockTypes', 'styles' ),
			'additionalProperties' => false,
		);
	}

	protected function outputSchema(): array {
		return array(
			'type'       => 'object',
			'properties' => array(
				'slug'     => array( 'type' => 'string' ),
				'id'       => array( 'type' => 'integer' ),
				'revision' => array( 'type' => 'integer' ),
			),
			'required'   => array( 'slug', 'id', 'revision' ),
		);
	}

	protected function annotations(): array {
		return array( 'readonly' => false, 'destructive' => false, 'idempotent' => true );
	}

	public function permission( $input ): bool {
		return current_user_can( 'edit_theme_options' );
	}

	public function execute( $input ) {
		$result = $this->store->save(
			array(
				'slug'       => (string) $input['slug'],
				'label'      => (string) $input['label'],
				'blockTypes' => array_values( (array) $input['blockTypes'] ),
				'styles'     => (array) $input['styles'],
			)
		);
		if ( is_wp_error( $result ) ) {
			$errors = (array) ( $result->get_error_data()['errors'] ?? array( $result->get_error_message() ) );
			return self::errorList( 'pediment_style_invalid', __( 'The style breaks the design rules. Nothing was saved.', 'pediment' ), $errors );
		}
		return array(
			'slug'     => (string) $input['slug'],
			'id'       => $result['id'],
			'revision' => $result['revision'],
		);
	}
}
