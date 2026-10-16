<?php
/**
 * Hides the 18 legacy blocks from the inserter.
 *
 * @package Pediment
 */

declare(strict_types=1);

namespace Pediment\Content;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Legacy blocks keep rendering and editing; they only leave the inserter, so
 * nobody adds new ones. No block.json is edited.
 */
final class LegacyBlocks {

	/**
	 * Hooks the filter (before blocks register on init).
	 */
	public static function register(): void {
		add_filter( 'register_block_type_args', array( self::class, 'hide' ), 10, 2 );
	}

	/**
	 * Sets supports.inserter = false on legacy blocks.
	 *
	 * @param array  $args Block type args.
	 * @param string $name Block name.
	 */
	public static function hide( array $args, string $name ): array {
		if ( in_array( $name, Policy::LEGACY, true ) ) {
			$args['supports']             = (array) ( $args['supports'] ?? array() );
			$args['supports']['inserter'] = false;
		}
		return $args;
	}
}
