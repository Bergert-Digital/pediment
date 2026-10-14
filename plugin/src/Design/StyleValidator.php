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

	private const SLUG_PATTERN  = '/^[a-z][a-z0-9-]{0,39}$/D';
	private const BLOCK_PATTERN = '/^[a-z0-9-]+\/[a-z0-9-]+$/D';

	/**
	 * Slugs wp_unique_post_slug() always suffixes for flat post types (feeds
	 * and embed), so a style stored under them could never be found again.
	 */
	private const RESERVED_SLUGS = array( 'feed', 'rss', 'rss2', 'rdf', 'atom', 'embed' );

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
		'outline.color',
		'filter.duotone',
	);

	private const KEYWORDS = array( '0', 'none', 'inherit', 'initial', 'unset', 'transparent', 'currentcolor' );

	/**
	 * CSS Color 4 named colours (148 keywords).
	 */
	private const CSS_NAMED_COLOURS = array(
		'aliceblue', 'antiquewhite', 'aqua', 'aquamarine', 'azure', 'beige', 'bisque', 'black', 'blanchedalmond',
		'blue', 'blueviolet', 'brown', 'burlywood', 'cadetblue', 'chartreuse', 'chocolate', 'coral', 'cornflowerblue',
		'cornsilk', 'crimson', 'cyan', 'darkblue', 'darkcyan', 'darkgoldenrod', 'darkgray', 'darkgrey', 'darkgreen',
		'darkkhaki', 'darkmagenta', 'darkolivegreen', 'darkorange', 'darkorchid', 'darkred', 'darksalmon',
		'darkseagreen', 'darkslateblue', 'darkslategray', 'darkslategrey', 'darkturquoise', 'darkviolet', 'deeppink',
		'deepskyblue', 'dimgray', 'dimgrey', 'dodgerblue', 'firebrick', 'floralwhite', 'forestgreen', 'fuchsia',
		'gainsboro', 'ghostwhite', 'gold', 'goldenrod', 'gray', 'grey', 'green', 'greenyellow', 'honeydew',
		'hotpink', 'indianred', 'indigo', 'ivory', 'khaki', 'lavender', 'lavenderblush', 'lawngreen', 'lemonchiffon',
		'lightblue', 'lightcoral', 'lightcyan', 'lightgoldenrodyellow', 'lightgray', 'lightgrey', 'lightgreen',
		'lightpink', 'lightsalmon', 'lightseagreen', 'lightskyblue', 'lightslategray', 'lightslategrey',
		'lightsteelblue', 'lightyellow', 'lime', 'limegreen', 'linen', 'magenta', 'maroon', 'mediumaquamarine',
		'mediumblue', 'mediumorchid', 'mediumpurple', 'mediumseagreen', 'mediumslateblue', 'mediumspringgreen',
		'mediumturquoise', 'mediumvioletred', 'midnightblue', 'mintcream', 'mistyrose', 'moccasin', 'navajowhite',
		'navy', 'oldlace', 'olive', 'olivedrab', 'orange', 'orangered', 'orchid', 'palegoldenrod', 'palegreen',
		'paleturquoise', 'palevioletred', 'papayawhip', 'peachpuff', 'peru', 'pink', 'plum', 'powderblue',
		'purple', 'rebeccapurple', 'red', 'rosybrown', 'royalblue', 'saddlebrown', 'salmon', 'sandybrown',
		'seagreen', 'seashell', 'sienna', 'silver', 'skyblue', 'slateblue', 'slategray', 'slategrey', 'snow',
		'springgreen', 'steelblue', 'tan', 'teal', 'thistle', 'tomato', 'turquoise', 'violet', 'wheat', 'white',
		'whitesmoke', 'yellow', 'yellowgreen',
	);

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
		} elseif ( in_array( $slug, self::RESERVED_SLUGS, true ) ) {
			$errors[] = "slug: {$slug} is reserved by WordPress — choose another";
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

		foreach ( $this->strings( $styles, 'styles' ) as $leaf_path => $leaf ) {
			if ( str_contains( $leaf, '<' ) ) {
				$errors[] = "{$leaf_path}: \"<\" is not allowed";
			}
		}

		return array_merge( $errors, $this->validate_node( $styles, 'styles' ) );
	}

	/**
	 * Every string leaf of a tree, keyed by dotted path. Core prints style
	 * values into inline `<style>` tags, so no value may open a tag.
	 *
	 * @param array  $tree Tree.
	 * @param string $path Path.
	 * @return array<string, string>
	 */
	private function strings( array $tree, string $path ): array {
		$out = array();
		foreach ( $tree as $key => $value ) {
			if ( is_string( $value ) ) {
				$out[ "{$path}.{$key}" ] = $value;
			} elseif ( is_array( $value ) ) {
				$out += $this->strings( $value, "{$path}.{$key}" );
			}
		}
		return $out;
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

		foreach ( array( 'elements', 'blocks', 'variations' ) as $group ) {
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
		// Pure ref object: skip validation entirely.
		if ( array_keys( $value ) === array( 'ref' ) && is_string( $value['ref'] ) && '' !== trim( $value['ref'] ) ) {
			return array();
		}
		$out = array();
		foreach ( $value as $key => $child ) {
			// Skip the 'ref' key itself if there are other keys (mixed object).
			if ( 'ref' === $key && count( $value ) > 1 ) {
				continue;
			}
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
	 * Validates a `css` string against the grammar core's
	 * WP_Theme_JSON::process_blocks_custom_css() renders correctly: optional
	 * bare declarations first, then rules `&<selector>{declarations}`. Core
	 * splits on every `&` and keeps a rule only when it has exactly one `{`, so
	 * at-rules, nesting, selector lists and stray `&` all render wrong.
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

		if ( 1 === preg_match( '/!\s*important/i', $clean ) ) {
			$errors[] = "{$path}: !important is not allowed";
		}

		// Only strip var() calls with a single --wp--preset or --wp--custom token (no comma inside).
		$without_safe_vars = (string) preg_replace( '/var\(\s*--wp--(preset|custom)--[a-z0-9-]+(--[a-z0-9-]+)*\s*\)/', '', $clean );
		if ( $this->has_literal_colour( $without_safe_vars ) ) {
			$errors[] = "{$path}: literal colour — use var(--wp--preset--color--…)";
		}

		if ( preg_match_all( '/@([a-z][a-z0-9-]*)/i', $clean, $matches ) ) {
			foreach ( array_unique( array_map( 'strtolower', $matches[1] ) ) as $name ) {
				$errors[] = "{$path}: at-rule @{$name} is not allowed";
			}
		}

		// Core does not strip comments, so the grammar applies to the raw string.
		return array_merge( $errors, $this->validate_css_rules( $css, $path ) );
	}

	/**
	 * Checks the rule structure core can render.
	 *
	 * @param string $css  CSS.
	 * @param string $path Path.
	 * @return list<string>
	 */
	private function validate_css_rules( string $css, string $path ): array {
		$rules   = array();
		$prelude = '';
		$buffer  = '';
		$open    = false;
		$length  = strlen( $css );
		for ( $i = 0; $i < $length; $i++ ) {
			$ch = $css[ $i ];
			if ( '{' === $ch ) {
				if ( $open ) {
					return array( "{$path}: nested rules are not allowed" );
				}
				$prelude = $buffer;
				$buffer  = '';
				$open    = true;
			} elseif ( '}' === $ch ) {
				if ( ! $open ) {
					return array( "{$path}: unbalanced }" );
				}
				$rules[] = array( $prelude, $buffer );
				$buffer  = '';
				$open    = false;
			} else {
				$buffer .= $ch;
			}
		}
		if ( $open ) {
			return array( "{$path}: unbalanced {" );
		}

		$errors   = array();
		$stray    = "{$path}: & may only start a rule";
		$trailing = trim( $buffer );
		if ( array() === $rules ) {
			// Bare declarations only: they apply to the style itself.
			return str_contains( $trailing, '&' ) ? array( $stray ) : array();
		}
		if ( '' !== $trailing ) {
			$errors[] = "{$path}: declarations outside a rule must come before the first & rule";
		}

		foreach ( $rules as $index => list( $prelude, $declarations ) ) {
			if ( str_starts_with( trim( $prelude ), '@' ) ) {
				continue; // Reported as an at-rule.
			}
			$amp = strpos( $prelude, '&' );
			if ( false === $amp ) {
				$errors[] = "{$path}: selector " . wp_json_encode( trim( $prelude ) ) . ' must start with &';
				continue;
			}
			$before = trim( substr( $prelude, 0, $amp ) );
			// Only the first rule may follow root declarations, which end with `;`.
			if ( '' !== $before && ( 0 !== $index || ! str_ends_with( $before, ';' ) ) ) {
				$errors[] = "{$path}: selector " . wp_json_encode( trim( $prelude ) ) . ' must start with &';
				continue;
			}
			$selector = substr( $prelude, $amp + 1 );
			if ( str_contains( $selector, '&' ) || str_contains( $declarations, '&' ) ) {
				$errors[] = $stray;
				continue;
			}
			if ( count( $this->split_top_level_commas( $selector ) ) > 1 ) {
				$errors[] = "{$path}: selector list " . wp_json_encode( '&' . trim( $selector ) ) . ' is not allowed — write one & rule per selector';
			}
		}
		return $errors;
	}

	/**
	 * Checks if CSS contains literal colour values.
	 *
	 * @param string $css CSS (with safe vars removed).
	 * @return bool
	 */
	private function has_literal_colour( string $css ): bool {
		// Hex colours.
		if ( 1 === preg_match( '/#[0-9a-f]{3,8}\b/i', $css ) ) {
			return true;
		}
		// rgb/rgba/hsl/hsla/hwb/lab/lch/oklab/oklch functions.
		if ( 1 === preg_match( '/\b(?:rgba?|hsla?|hwb|lab|lch|oklab|oklch)\s*\(/i', $css ) ) {
			return true;
		}
		// color() / color-mix() / light-dark() functions.
		if ( 1 === preg_match( '/\b(?:color|color-mix|light-dark)\s*\(/i', $css ) ) {
			return true;
		}
		// Named colours in declaration values (after : and before ; or }).
		return $this->has_named_colour_in_values( $css );
	}

	/**
	 * Checks if CSS has named colours in declaration values.
	 *
	 * @param string $css CSS.
	 * @return bool
	 */
	private function has_named_colour_in_values( string $css ): bool {
		// Match declarations: prop: value; (or }) at any nesting depth.
		if ( ! preg_match_all( '/:\s*([^:;{}]+?)(?:[;}]|$)/s', $css, $matches ) ) {
			return false;
		}
		foreach ( $matches[1] as $value ) {
			$value = trim( $value );
			// Check if value contains a named colour as a whole word.
			foreach ( self::CSS_NAMED_COLOURS as $colour ) {
				if ( 1 === preg_match( '/\b' . preg_quote( $colour, '/' ) . '\b/i', $value ) ) {
					return true;
				}
			}
		}
		return false;
	}

	/**
	 * Splits a selector list on commas outside parentheses.
	 *
	 * @param string $selector_list Selector list.
	 * @return list<string>
	 */
	private function split_top_level_commas( string $selector_list ): array {
		$parts   = array();
		$depth   = 0;
		$current = '';
		$length  = strlen( $selector_list );
		for ( $i = 0; $i < $length; $i++ ) {
			$ch = $selector_list[ $i ];
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
