<?php
/**
 * Reads legacy block attributes and escapes them the way core stores them.
 *
 * @package Pediment
 */

declare(strict_types=1);

namespace Pediment\Convert;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Attribute readers. parse_blocks() returns only the attributes a block stored, never the
 * block.json defaults, so every reader takes its default explicitly.
 */
final class Attrs {

	/**
	 * A scalar attribute as a string.
	 *
	 * @param array  $attrs   Attributes.
	 * @param string $key     Key.
	 * @param string $fallback Value when the attribute is missing or not scalar.
	 */
	public static function string( array $attrs, string $key, string $fallback = '' ): string {
		$value = $attrs[ $key ] ?? $fallback;
		return is_scalar( $value ) ? (string) $value : $fallback;
	}

	/**
	 * A numeric attribute as an int (0 when missing).
	 *
	 * @param array  $attrs Attributes.
	 * @param string $key   Key.
	 */
	public static function int( array $attrs, string $key ): int {
		$value = $attrs[ $key ] ?? 0;
		return is_numeric( $value ) ? (int) $value : 0;
	}

	/**
	 * A RichText attribute as the HTML a core block stores: post-allowed tags
	 * only (the legacy render.php printed it through wp_kses_post() too),
	 * balanced tags (a stray closing tag would make the core block invalid) and
	 * no block delimiter, so attribute text can never open a block.
	 *
	 * @param array  $attrs Attributes.
	 * @param string $key   Key.
	 */
	public static function rich( array $attrs, string $key ): string {
		return self::html( self::string( $attrs, $key ) );
	}

	/**
	 * Escapes a RichText value (see rich()).
	 *
	 * @param string $value Value.
	 */
	public static function html( string $value ): string {
		return str_replace( '<!--', '&lt;!--', force_balance_tags( wp_kses_post( $value ) ) );
	}

	/**
	 * A URL attribute, escaped for an href or src.
	 *
	 * @param array  $attrs Attributes.
	 * @param string $key   Key.
	 */
	public static function url( array $attrs, string $key ): string {
		return esc_url( self::string( $attrs, $key ) );
	}

	/**
	 * Sets $key when $value is neither null nor ''.
	 *
	 * @param array       $attrs Attributes.
	 * @param string      $key   Key.
	 * @param string|null $value Value.
	 */
	public static function put( array $attrs, string $key, ?string $value ): array {
		if ( null !== $value && '' !== $value ) {
			$attrs[ $key ] = $value;
		}
		return $attrs;
	}
}
