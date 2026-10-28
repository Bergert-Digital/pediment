<?php
/**
 * Ability pediment/check-page.
 *
 * @package Pediment
 */

declare(strict_types=1);

namespace Pediment\Abilities;

use Pediment\Content\InvalidBlockLog;
use Pediment\Content\Policy;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Runs the content rules on a stored page and returns the editor's last validity report.
 */
final class CheckPage extends Ability {

	/**
	 * Constructor.
	 *
	 * @param Policy $policy Policy.
	 */
	public function __construct( private Policy $policy ) {}

	public function name(): string {
		return 'pediment/check-page';
	}

	protected function label(): string {
		return __( 'Check page', 'pediment' );
	}

	protected function description(): string {
		return __( 'Checks a stored page, or a post of the given post_type (default page): content-rule errors and warnings, the blocks the block editor last reported as invalid (null when the page was not opened in the editor since its last write), and legacy Pediment blocks still on the page.', 'pediment' );
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
		return array(
			'type'       => 'object',
			'properties' => array(
				'errors'        => array( 'type' => 'array', 'items' => array( 'type' => 'string' ) ),
				'warnings'      => array( 'type' => 'array', 'items' => array( 'type' => 'string' ) ),
				'invalidBlocks' => array( 'type' => array( 'object', 'null' ) ),
				'legacyBlocks'  => array( 'type' => 'array', 'items' => array( 'type' => 'string' ) ),
			),
			'required'   => array( 'errors', 'warnings', 'invalidBlocks', 'legacyBlocks' ),
		);
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
		if ( ! $page instanceof \WP_Post ) {
			return $page;
		}
		$result = $this->policy->check( $page->post_content );
		return array(
			'errors'        => $result['errors'],
			'warnings'      => $result['warnings'],
			'invalidBlocks' => InvalidBlockLog::get( $page->ID ),
			'legacyBlocks'  => $this->policy->legacyBlocksIn( $page->post_content ),
		);
	}
}
