<?php
/**
 * Base class for Pediment abilities (WordPress Abilities API).
 *
 * @package Pediment
 */

declare(strict_types=1);

namespace Pediment\Abilities;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * One ability: its registration args plus permission and execute callbacks.
 */
abstract class Ability {

	/**
	 * Ability name, e.g. pediment/read-page.
	 */
	abstract public function name(): string;

	/**
	 * Permission check. Must return a plain bool (core rejects WP_Error here).
	 *
	 * @param mixed $input Validated input.
	 */
	abstract public function permission( $input ): bool;

	/**
	 * Runs the ability.
	 *
	 * @param mixed $input Validated input.
	 * @return array|\WP_Error
	 */
	abstract public function execute( $input );

	/**
	 * Human label.
	 */
	abstract protected function label(): string;

	/**
	 * Description shown to agents.
	 */
	abstract protected function description(): string;

	/**
	 * JSON schema of the input.
	 */
	abstract protected function inputSchema(): array;

	/**
	 * JSON schema of the output.
	 */
	abstract protected function outputSchema(): array;

	/**
	 * Annotations: readonly, destructive, idempotent.
	 *
	 * @return array{readonly:bool,destructive:bool,idempotent:bool}
	 */
	abstract protected function annotations(): array;

	/**
	 * Arguments for wp_register_ability().
	 */
	public function args(): array {
		return array(
			'label'               => $this->label(),
			'description'         => $this->description(),
			'category'            => Registrar::CATEGORY,
			'input_schema'        => $this->inputSchema(),
			'output_schema'       => $this->outputSchema(),
			'execute_callback'    => array( $this, 'execute' ),
			'permission_callback' => array( $this, 'permission' ),
			'meta'                => array(
				'show_in_rest' => true,
				'annotations'  => $this->annotations(),
			),
		);
	}

	/**
	 * Shared schema fragment: a post summary.
	 */
	protected static function pageSummarySchema(): array {
		return array(
			'type'       => 'object',
			'properties' => array(
				'id'       => array( 'type' => 'integer' ),
				'type'     => array( 'type' => 'string' ),
				'title'    => array( 'type' => 'string' ),
				'slug'     => array( 'type' => 'string' ),
				'parent'   => array( 'type' => 'integer' ),
				'status'   => array( 'type' => 'string' ),
				'language' => array( 'type' => 'string' ),
				'link'     => array( 'type' => 'string' ),
				'modified' => array( 'type' => 'string' ),
			),
			'required'   => array( 'id', 'type', 'title', 'slug', 'parent', 'status', 'language', 'link', 'modified' ),
		);
	}

	/**
	 * A WP_Error whose message lists every problem (the MCP adapter shows the message only).
	 *
	 * @param string       $code     Error code.
	 * @param string       $headline First line.
	 * @param list<string> $problems Problems.
	 * @param int          $status   HTTP status.
	 */
	protected static function errorList( string $code, string $headline, array $problems, int $status = 422 ): \WP_Error {
		return new \WP_Error(
			$code,
			$headline . "\n- " . implode( "\n- ", $problems ),
			array(
				'status' => $status,
				'errors' => $problems,
			)
		);
	}
}
