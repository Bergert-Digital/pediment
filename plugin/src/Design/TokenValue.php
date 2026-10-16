<?php
/**
 * The token rule shared by the design layer and the content Policy.
 *
 * @package Pediment
 */

declare(strict_types=1);

namespace Pediment\Design;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Decides whether a style value is a design token.
 */
final class TokenValue {

	/**
	 * Style-node paths whose values must be tokens.
	 */
	public const PATHS = array(
		'color.text',
		'color.background',
		'color.gradient',
		'typography.fontSize',
		'typography.fontFamily',
		'spacing.padding',
		'spacing.margin',
		'spacing.blockGap',
		'border.radius',
		'border.color',
		'border.top.color',
		'border.right.color',
		'border.bottom.color',
		'border.left.color',
		'shadow',
		'outline.color',
		'filter.duotone',
	);

	private const KEYWORDS = array( '0', 'none', 'inherit', 'initial', 'unset', 'transparent', 'currentcolor' );

	/**
	 * Whether a scalar is an allowed token value: a preset/custom variable,
	 * `0`, a CSS-wide keyword, `transparent`, `currentColor` or a percentage.
	 *
	 * @param mixed $value Value.
	 */
	public static function isToken( $value ): bool {
		if ( is_int( $value ) || is_float( $value ) ) {
			return 0.0 === (float) $value;
		}
		if ( ! is_string( $value ) ) {
			return false;
		}
		$v = trim( $value );
		if ( in_array( strtolower( $v ), self::KEYWORDS, true ) ) {
			return true;
		}
		if ( 1 === preg_match( '/^-?\d+(\.\d+)?%$/', $v ) ) {
			return true;
		}
		if ( 1 === preg_match( '/^var:preset\|[a-z-]+\|[a-z0-9-]+$/', $v ) ) {
			return true;
		}
		return 1 === preg_match( '/^var\(--wp--(preset|custom)--[a-z0-9-]+(--[a-z0-9-]+)*\)$/', $v );
	}

	/**
	 * Flattens a value into [path, scalar] pairs (a list: keys may contain
	 * dots). A pure `{ref: "…"}` object is a token; in a mixed object the
	 * `ref` key is skipped.
	 *
	 * @param mixed  $value Value.
	 * @param string $path  Path.
	 * @return list<array{0:string,1:mixed}>
	 */
	public static function leaves( $value, string $path ): array {
		if ( ! is_array( $value ) ) {
			return array( array( $path, $value ) );
		}
		if ( array_keys( $value ) === array( 'ref' ) && is_string( $value['ref'] ) && '' !== trim( $value['ref'] ) ) {
			return array();
		}
		$out = array();
		foreach ( $value as $key => $child ) {
			if ( 'ref' === $key && count( $value ) > 1 ) {
				continue;
			}
			array_push( $out, ...self::leaves( $child, "{$path}.{$key}" ) );
		}
		return $out;
	}
}
