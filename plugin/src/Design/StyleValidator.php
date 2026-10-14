<?php
/**
 * Validates design-layer style specs against Pediment's token rules.
 *
 * @package Pediment
 */

declare(strict_types=1);

namespace Pediment\Design;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Checks one style spec: array{slug, label, blockTypes, styles}. `styles` is a
 * theme.json style node and may carry a `css` string.
 */
final class StyleValidator {

	private const SLUG_PATTERN  = '/^[a-z][a-z0-9-]{0,39}$/';
	private const BLOCK_PATTERN = '/^[a-z0-9-]+\/[a-z0-9-]+$/';

	/**
	 * Style-node paths whose values must be tokens.
	 */
	private const TOKEN_PATHS = array(
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
	);

	private const KEYWORDS = array( '0', 'none', 'inherit', 'initial', 'unset', 'transparent', 'currentcolor' );

	/**
	 * Validates a spec.
	 *
	 * @param array $spec Style spec.
	 * @return list<string> Error messages; empty when valid.
	 */
	public function validate( array $spec ): array {
		$errors = array();

		$slug = $spec['slug'] ?? null;
		if ( ! is_string( $slug ) || 1 !== preg_match( self::SLUG_PATTERN, $slug ) ) {
			$errors[] = 'slug: must be lowercase letters, digits and dashes, starting with a letter (max 40)';
		}

		$label = $spec['label'] ?? null;
		if ( ! is_string( $label ) || '' === trim( $label ) ) {
			$errors[] = 'label: must be a non-empty string';
		}

		$types = $spec['blockTypes'] ?? null;
		if ( ! is_array( $types ) || array() === $types ) {
			$errors[] = 'blockTypes: must list at least one block type';
		} else {
			foreach ( $types as $type ) {
				if ( ! is_string( $type ) || 1 !== preg_match( self::BLOCK_PATTERN, $type ) ) {
					$errors[] = 'blockTypes: invalid block type ' . wp_json_encode( $type );
				} elseif ( ! \WP_Block_Type_Registry::get_instance()->is_registered( $type ) ) {
					$errors[] = "blockTypes: block type {$type} is not registered";
				}
			}
		}

		$styles = $spec['styles'] ?? null;
		if ( ! is_array( $styles ) ) {
			$errors[] = 'styles: must be an object';
			return $errors;
		}

		return array_merge( $errors, $this->validate_node( $styles, 'styles' ) );
	}

	/**
	 * Validates one style node and its nested elements, blocks and pseudo states.
	 *
	 * @param array  $node Style node.
	 * @param string $path Dotted path for messages.
	 * @return list<string>
	 */
	private function validate_node( array $node, string $path ): array {
		$errors = array();

		foreach ( self::TOKEN_PATHS as $token_path ) {
			$value = _wp_array_get( $node, explode( '.', $token_path ), null );
			if ( null === $value ) {
				continue;
			}
			foreach ( $this->leaves( $value, "{$path}.{$token_path}" ) as $leaf_path => $leaf ) {
				if ( ! $this->is_token_value( $leaf ) ) {
					$errors[] = "{$leaf_path}: literal value " . wp_json_encode( $leaf ) . ' — use a preset or custom token';
				}
			}
		}

		if ( array_key_exists( 'css', $node ) ) {
			$errors = array_merge( $errors, $this->validate_css( $node['css'], "{$path}.css" ) );
		}

		foreach ( array( 'elements', 'blocks' ) as $group ) {
			$children = $node[ $group ] ?? array();
			if ( ! is_array( $children ) ) {
				continue;
			}
			foreach ( $children as $name => $child ) {
				if ( is_array( $child ) ) {
					$errors = array_merge( $errors, $this->validate_node( $child, "{$path}.{$group}.{$name}" ) );
				}
			}
		}

		foreach ( $node as $key => $child ) {
			if ( is_string( $key ) && str_starts_with( $key, ':' ) && is_array( $child ) ) {
				$errors = array_merge( $errors, $this->validate_node( $child, "{$path}.{$key}" ) );
			}
		}

		return $errors;
	}

	/**
	 * Flattens a value into path => scalar leaves. `{ref: …}` objects count as tokens.
	 *
	 * @param mixed  $value Value.
	 * @param string $path  Path.
	 * @return array<string, mixed>
	 */
	private function leaves( $value, string $path ): array {
		if ( ! is_array( $value ) ) {
			return array( $path => $value );
		}
		if ( array_key_exists( 'ref', $value ) ) {
			return array();
		}
		$out = array();
		foreach ( $value as $key => $child ) {
			$out += $this->leaves( $child, "{$path}.{$key}" );
		}
		return $out;
	}

	/**
	 * Whether a scalar is an allowed token value.
	 *
	 * @param mixed $value Value.
	 */
	private function is_token_value( $value ): bool {
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
	 * Validates a `css` string.
	 *
	 * @param mixed  $css  CSS.
	 * @param string $path Path.
	 * @return list<string>
	 */
	private function validate_css( $css, string $path ): array {
		if ( ! is_string( $css ) ) {
			return array( "{$path}: must be a string" );
		}
		$errors = array();
		$clean  = (string) preg_replace( '#/\*.*?\*/#s', '', $css );

		if ( false !== stripos( $clean, '!important' ) ) {
			$errors[] = "{$path}: !important is not allowed";
		}

		$without_vars = (string) preg_replace( '/var\([^()]*\)/', '', $clean );
		if ( 1 === preg_match( '/#[0-9a-f]{3,8}\b|\b(?:rgba?|hsla?|hwb|lab|lch|oklab|oklch)\s*\(/i', $without_vars ) ) {
			$errors[] = "{$path}: literal colour — use var(--wp--preset--color--…)";
		}

		foreach ( $this->selectors( $clean ) as $selector ) {
			if ( ! str_starts_with( $selector, '&' ) ) {
				$errors[] = "{$path}: selector " . wp_json_encode( $selector ) . ' must start with &';
			}
		}

		return $errors;
	}

	/**
	 * Extracts rule selectors (not at-rule preludes) from CSS, at any nesting depth.
	 *
	 * @param string $css CSS without comments.
	 * @return list<string>
	 */
	private function selectors( string $css ): array {
		$out    = array();
		$buffer = '';
		$length = strlen( $css );
		for ( $i = 0; $i < $length; $i++ ) {
			$ch = $css[ $i ];
			if ( '{' === $ch ) {
				$prelude = trim( $buffer );
				$buffer  = '';
				if ( '' !== $prelude && '@' !== $prelude[0] ) {
					foreach ( $this->split_top_level_commas( $prelude ) as $selector ) {
						$out[] = $selector;
					}
				}
			} elseif ( '}' === $ch || ';' === $ch ) {
				$buffer = '';
			} else {
				$buffer .= $ch;
			}
		}
		return $out;
	}

	/**
	 * Splits a selector list on commas outside parentheses.
	 *
	 * @param string $list Selector list.
	 * @return list<string>
	 */
	private function split_top_level_commas( string $list ): array {
		$parts   = array();
		$depth   = 0;
		$current = '';
		$length  = strlen( $list );
		for ( $i = 0; $i < $length; $i++ ) {
			$ch = $list[ $i ];
			if ( '(' === $ch ) {
				++$depth;
			} elseif ( ')' === $ch ) {
				$depth = max( 0, $depth - 1 );
			}
			if ( ',' === $ch && 0 === $depth ) {
				$parts[] = trim( $current );
				$current = '';
				continue;
			}
			$current .= $ch;
		}
		$parts[] = trim( $current );
		return array_values( array_filter( $parts, static fn( string $p ): bool => '' !== $p ) );
	}
}
