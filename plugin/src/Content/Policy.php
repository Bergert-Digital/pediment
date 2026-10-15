<?php
/**
 * Content rules every page write must pass (spec §3).
 *
 * @package Pediment
 */

declare(strict_types=1);

namespace Pediment\Content;

use Pediment\Design\TokenValue;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Checks serialized block markup. Errors reject a write; warnings are returned.
 */
final class Policy {

	public const ALLOWED_CORE = array(
		'core/group',
		'core/columns',
		'core/column',
		'core/cover',
		'core/image',
		'core/gallery',
		'core/heading',
		'core/paragraph',
		'core/list',
		'core/list-item',
		'core/buttons',
		'core/button',
		'core/details',
		'core/quote',
		'core/separator',
		'core/spacer',
		'core/media-text',
		'core/embed',
	);

	public const FUNCTIONAL = array(
		'pediment/form',
		'pediment/form-field',
		'pediment/slider',
		'pediment/mega-menu',
		'pediment/blog-index',
		'pediment/social-links',
	);

	public const LEGACY = array(
		'pediment/hero',
		'pediment/cta',
		'pediment/feature',
		'pediment/feature-grid',
		'pediment/stat',
		'pediment/stat-grid',
		'pediment/step',
		'pediment/steps',
		'pediment/section-head',
		'pediment/media-text',
		'pediment/testimonial',
		'pediment/testimonial-grid',
		'pediment/logo-cloud',
		'pediment/pull-quote',
		'pediment/image-caption',
		'pediment/prose',
		'pediment/faq',
		'pediment/faq-item',
	);

	public const RULES = array(
		'Use only the blocks in allowedBlocks. core/html and content outside blocks are not allowed.',
		'Never put literal colours, font sizes, font families, spacing, radius or shadows in block attributes. Use presets (var:preset|…) or a named style (className is-style-<slug>).',
		'Every is-style-<slug> class must name a style registered for that block (see styles).',
		'Give container blocks (group, columns, cover) a readable metadata.name.',
		'Static blocks must match their save() output: run the client-kit validate-blocks tool before writing.',
	);

	private const CONTAINERS = array( 'core/group', 'core/columns', 'core/cover' );

	private const BLOCK_COMMENT = '/<!--\s+(\/)?wp:([a-z][a-z0-9_-]*(?:\/[a-z][a-z0-9_-]*)?)(?:\s+(\{.*?\}))?\s+(\/)?-->/s';

	/**
	 * Blocks a page may use: the (filterable) core list, Pediment's functional
	 * blocks, and every registered block outside the core and pediment
	 * namespaces (client-theme and plugin blocks).
	 *
	 * @return list<string>
	 */
	public static function allowedBlocks(): array {
		$core  = (array) apply_filters( 'pediment_allowed_blocks', self::ALLOWED_CORE );
		$other = array();
		foreach ( array_keys( \WP_Block_Type_Registry::get_instance()->get_all_registered() ) as $name ) {
			$namespace = strstr( $name, '/', true );
			if ( 'core' !== $namespace && 'pediment' !== $namespace ) {
				$other[] = $name;
			}
		}
		$list = array_values( array_unique( array_map( 'strval', array_merge( $core, self::FUNCTIONAL, $other ) ) ) );
		sort( $list );
		return $list;
	}

	/**
	 * Checks markup.
	 *
	 * @param string $markup Serialized blocks.
	 * @return array{errors:list<string>,warnings:list<string>}
	 */
	public function check( string $markup ): array {
		// Registers theme style-variation names (core does it while building theme data).
		\WP_Theme_JSON_Resolver::get_theme_data();

		$errors   = $this->comment_json_errors( $markup );
		$warnings = array();
		$this->walk( parse_blocks( $markup ), 'blocks', self::allowedBlocks(), $errors, $warnings );
		foreach ( $this->external_image_hosts( $markup ) as $host ) {
			$warnings[] = "image from external host {$host} — upload it to the media library";
		}

		return array(
			'errors'   => array_values( array_unique( $errors ) ),
			'warnings' => array_values( array_unique( $warnings ) ),
		);
	}

	/**
	 * Legacy Pediment block names used in markup, sorted and unique.
	 *
	 * @param string $markup Serialized blocks.
	 * @return list<string>
	 */
	public function legacyBlocksIn( string $markup ): array {
		$found = array();
		$visit = static function ( array $blocks ) use ( &$visit, &$found ): void {
			foreach ( $blocks as $block ) {
				if ( in_array( $block['blockName'], self::LEGACY, true ) ) {
					$found[] = $block['blockName'];
				}
				$visit( $block['innerBlocks'] ?? array() );
			}
		};
		$visit( parse_blocks( $markup ) );
		$found = array_values( array_unique( $found ) );
		sort( $found );
		return $found;
	}

	/**
	 * Recursive block walk.
	 *
	 * @param array        $blocks   Parsed blocks.
	 * @param string       $path     Path prefix.
	 * @param list<string> $allowed  Allowed block names.
	 * @param list<string> $errors   Errors (by reference).
	 * @param list<string> $warnings Warnings (by reference).
	 */
	private function walk( array $blocks, string $path, array $allowed, array &$errors, array &$warnings ): void {
		foreach ( $blocks as $i => $block ) {
			$name = $block['blockName'];
			if ( null === $name ) {
				if ( '' !== trim( (string) $block['innerHTML'] ) ) {
					$errors[] = "{$path}[{$i}]: content outside a block — wrap it in a block";
				}
				continue;
			}
			$here = "{$path}[{$i}] {$name}";

			if ( in_array( $name, self::LEGACY, true ) ) {
				$warnings[] = "{$here}: legacy block — it will be converted to core blocks";
			} elseif ( ! in_array( $name, $allowed, true ) ) {
				$errors[] = \WP_Block_Type_Registry::get_instance()->is_registered( $name )
					? "{$here}: block is not allowed"
					: "{$here}: block type is not registered";
			}

			$attrs = is_array( $block['attrs'] ?? null ) ? $block['attrs'] : array();
			foreach ( $this->literal_values( $attrs['style'] ?? array(), 'style' ) as list( $attr_path, $value ) ) {
				$errors[] = "{$here}: attrs.{$attr_path} has the literal value " . wp_json_encode( $value ) . ' — use a preset or a named style';
			}
			foreach ( $this->unknown_styles( $name, (string) ( $attrs['className'] ?? '' ) ) as $slug ) {
				$errors[] = "{$here}: is-style-{$slug} is not a registered style for {$name}";
			}
			if ( in_array( $name, self::CONTAINERS, true ) && '' === trim( (string) ( $attrs['metadata']['name'] ?? '' ) ) ) {
				$warnings[] = "{$here}: give it a readable metadata.name";
			}

			$this->walk( $block['innerBlocks'] ?? array(), "{$here} > ", $allowed, $errors, $warnings );
		}
	}

	/**
	 * Literal design values in a block's `style` attribute.
	 *
	 * @param mixed  $style Style attribute (or an element node).
	 * @param string $path  Path.
	 * @return list<array{0:string,1:mixed}>
	 */
	private function literal_values( $style, string $path ): array {
		if ( ! is_array( $style ) ) {
			return array();
		}
		$out = array();
		foreach ( TokenValue::PATHS as $token_path ) {
			$value = _wp_array_get( $style, explode( '.', $token_path ), null );
			if ( null === $value ) {
				continue;
			}
			foreach ( TokenValue::leaves( $value, "{$path}.{$token_path}" ) as list( $leaf_path, $leaf ) ) {
				if ( ! TokenValue::isToken( $leaf ) ) {
					$out[] = array( $leaf_path, $leaf );
				}
			}
		}
		$elements = $style['elements'] ?? array();
		foreach ( is_array( $elements ) ? $elements : array() as $element => $node ) {
			if ( ! is_array( $node ) ) {
				continue;
			}
			array_push( $out, ...$this->literal_values( $node, "{$path}.elements.{$element}" ) );
			foreach ( $node as $key => $state ) {
				if ( is_string( $key ) && str_starts_with( $key, ':' ) ) {
					array_push( $out, ...$this->literal_values( $state, "{$path}.elements.{$element}.{$key}" ) );
				}
			}
		}
		return $out;
	}

	/**
	 * `is-style-*` slugs in a class list that are not registered for the block.
	 *
	 * @param string $block      Block name.
	 * @param string $class_name className attribute.
	 * @return list<string>
	 */
	private function unknown_styles( string $block, string $class_name ): array {
		if ( ! preg_match_all( '/(?:^|\s)is-style-([a-z0-9-]+)/', $class_name, $matches ) ) {
			return array();
		}
		$known = array_keys( \WP_Block_Styles_Registry::get_instance()->get_registered_styles_for_block( $block ) );
		$type  = \WP_Block_Type_Registry::get_instance()->get_registered( $block );
		if ( $type && is_array( $type->styles ) ) {
			foreach ( $type->styles as $style ) {
				if ( isset( $style['name'] ) ) {
					$known[] = (string) $style['name'];
				}
			}
		}
		$known[] = 'default';
		return array_values( array_diff( $matches[1], $known ) );
	}

	/**
	 * Block comments whose attribute JSON does not decode (core's parser drops it silently).
	 *
	 * @param string $markup Markup.
	 * @return list<string>
	 */
	private function comment_json_errors( string $markup ): array {
		$errors = array();
		if ( ! preg_match_all( self::BLOCK_COMMENT, $markup, $matches, PREG_SET_ORDER ) ) {
			return $errors;
		}
		foreach ( $matches as $match ) {
			if ( '' !== $match[1] || empty( $match[3] ) ) {
				continue;
			}
			json_decode( $match[3] );
			if ( JSON_ERROR_NONE !== json_last_error() ) {
				$name     = str_contains( $match[2], '/' ) ? $match[2] : 'core/' . $match[2];
				$errors[] = "{$name}: block comment attributes are not valid JSON";
			}
		}
		return $errors;
	}

	/**
	 * Hosts of `<img src>` URLs that are not this site.
	 *
	 * @param string $markup Markup.
	 * @return list<string>
	 */
	private function external_image_hosts( string $markup ): array {
		$site = wp_parse_url( home_url(), PHP_URL_HOST );
		if ( ! preg_match_all( '/<img\b[^>]*\bsrc="([^"]+)"/i', $markup, $matches ) ) {
			return array();
		}
		$hosts = array();
		foreach ( $matches[1] as $src ) {
			$host = wp_parse_url( html_entity_decode( $src ), PHP_URL_HOST );
			if ( is_string( $host ) && $host !== $site ) {
				$hosts[] = $host;
			}
		}
		return array_values( array_unique( $hosts ) );
	}
}
