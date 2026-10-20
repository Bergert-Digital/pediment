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
		'pediment/icon',
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
		'No active markup anywhere in the markup, including comments, attribute values and shortcodes: script, style, iframe, object, embed, noscript, svg, math, form, base, meta, link, template, frame, frameset and applet tags and on*= event handlers are rejected. The text "javascript:" is rejected anywhere, even in prose ("Learn JavaScript: …" fails; write "JavaScript –" or rephrase). Use pediment/form for forms.',
		'Give container blocks (group, columns, cover) a readable metadata.name.',
		'Static blocks must match their save() output: run the client-kit validate-blocks tool before writing.',
	);

	private const CONTAINERS = array( 'core/group', 'core/columns', 'core/cover' );

	private const FORBIDDEN_TAGS = array( 'script', 'style', 'iframe', 'object', 'embed', 'noscript', 'svg', 'math', 'form', 'base', 'meta', 'link', 'template', 'frame', 'frameset', 'applet' );

	/**
	 * Inline CSS properties that carry design values and so must hold tokens.
	 */
	private const DESIGN_PROPERTY = '/^(color|background(-color|-image)?|font-size|font-family|padding(-[a-z]+)*|margin(-[a-z]+)*|gap|row-gap|column-gap|border(-[a-z]+)*-radius|border(-(top|right|bottom|left))?-color|box-shadow)$/';

	private const BLOCK_COMMENT = '/<!--\s+(\/)?wp:([a-z][a-z0-9_-]*(?:\/[a-z][a-z0-9_-]*)?)(?:\s+(\{.*?\}))?\s+(\/)?-->/s';

	/**
	 * Blocks a page may use: the (filterable) core list, Pediment's functional
	 * blocks, and every registered block outside the core and pediment
	 * namespaces (client-theme and plugin blocks). core/html is never allowed,
	 * whatever the filter returns.
	 *
	 * @return list<string>
	 */
	public static function allowedBlocks(): array {
		$core  = apply_filters( 'pediment_allowed_blocks', self::ALLOWED_CORE );
		$core  = is_array( $core ) ? array_filter( $core, 'is_string' ) : self::ALLOWED_CORE;
		$other = array();
		foreach ( array_keys( \WP_Block_Type_Registry::get_instance()->get_all_registered() ) as $name ) {
			$namespace = strstr( $name, '/', true );
			if ( 'core' !== $namespace && 'pediment' !== $namespace ) {
				$other[] = $name;
			}
		}
		$list = array_values( array_diff( array_unique( array_merge( $core, self::FUNCTIONAL, $other ) ), array( 'core/html' ) ) );
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

		$errors   = array_merge( $this->comment_json_errors( $markup ), $this->markup_errors( $markup ) );
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

			// Checked before the registry, so the message names the cause. The
			// converter refuses only errors a conversion adds (PostConverter::addedErrors()),
			// so this error never stops a legacy post or pattern from being converted.
			if ( in_array( $name, self::LEGACY, true ) ) {
				$errors[] = "{$here}: {$name} was removed in Pediment 5; convert it with pediment-convert-legacy";
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
		// Every leaf under style.color and style.spacing must be a token (spec §3),
		// whatever its key; the other token paths are checked by name.
		$subtrees = array( 'color', 'spacing' );
		foreach ( TokenValue::PATHS as $token_path ) {
			if ( ! in_array( strstr( $token_path, '.', true ), $subtrees, true ) ) {
				$subtrees[] = $token_path;
			}
		}
		foreach ( $subtrees as $subtree ) {
			$value = _wp_array_get( $style, explode( '.', $subtree ), null );
			if ( null === $value ) {
				continue;
			}
			foreach ( TokenValue::leaves( $value, "{$path}.{$subtree}" ) as list( $leaf_path, $leaf ) ) {
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
	 * Dangerous markup in the submitted content: active tags, event-handler
	 * attributes, javascript: URLs and literal design values in inline styles.
	 * Administrators have unfiltered_html, so core's kses does not catch these.
	 *
	 * Raw-text checks over the static markup core renders (see rendered_text()),
	 * because that is what the browser receives: static blocks render
	 * concatenated, so a payload can start in one block and finish in the next,
	 * and a tag parser reads differently from a browser (unterminated tags,
	 * noscript, foreign content). HTML comments stay in the scanned text: a
	 * browser may end one earlier than expected (`--!>`), so text inside a
	 * comment is checked too.
	 *
	 * @param string $markup Serialized blocks.
	 * @return list<string>
	 */
	private function markup_errors( string $markup ): array {
		$html   = $this->rendered_text( parse_blocks( $markup ) );
		$errors = $this->active_markup_errors( $html, '' );
		foreach ( $this->decoded_shortcode_spans( $html ) as $decoded ) {
			array_push( $errors, ...$this->active_markup_errors( $decoded, ' (in a shortcode, after its escapes are decoded)' ) );
		}
		if ( preg_match_all( '/(?<![\w-])style\s*=\s*("[^"]*(?:"|$)|\'[^\']*(?:\'|$)|[^\s>]+)/i', $html, $found ) ) {
			foreach ( $found[1] as $raw ) {
				$inline = $this->decode_entities( trim( $raw, "\"'" ) );
				foreach ( $this->inline_style_literals( $inline ) as list( $property, $value ) ) {
					$errors[] = "inline style {$property}:{$value} has a literal value — use a preset or a named style";
				}
			}
		}
		return $errors;
	}

	/**
	 * Active tags, event-handler attributes and javascript: URLs in a text.
	 *
	 * @param string $html   Text as the browser would receive it.
	 * @param string $suffix Appended to the rule part of each message.
	 * @return list<string>
	 */
	private function active_markup_errors( string $html, string $suffix ): array {
		$errors = array();
		if ( preg_match_all( '/<\s*(' . implode( '|', self::FORBIDDEN_TAGS ) . ')\b/i', $html, $found, PREG_OFFSET_CAPTURE ) ) {
			foreach ( $found[1] as $i => list( $tag ) ) {
				$errors[] = '<' . strtolower( $tag ) . '> is not allowed in block markup' . $suffix . $this->excerpt( $html, $found[0][ $i ][1] );
			}
		}
		if ( preg_match_all( '/(?:^|[\s\/"\'`])(on[a-z]+)\s*=/i', $html, $found, PREG_OFFSET_CAPTURE ) ) {
			foreach ( $found[1] as $i => list( $name, $offset ) ) {
				$errors[] = strtolower( $name ) . ' attribute is not allowed in block markup' . $suffix . $this->excerpt( $html, $offset );
			}
		}
		// Browsers ignore whitespace and control characters inside a URL scheme.
		$squashed = (string) preg_replace( '/[\x00-\x20]+/', '', $this->decode_entities( $html ) );
		$position = stripos( $squashed, 'javascript:' );
		if ( false !== $position ) {
			$errors[] = 'javascript: URL is not allowed in block markup' . $suffix . $this->excerpt( $squashed, $position );
		}
		return $errors;
	}

	/**
	 * Shortcode spans as their handler receives them. Core decodes shortcode
	 * attributes with stripcslashes() at render time, so `\x3cscript\x3e`
	 * becomes `<script>` only after the raw-text scan. A shortcode attribute
	 * never holds `]`, so every span from the first `[` after one `]` up to the
	 * next `]` covers every shortcode that ends there (one pass, linear).
	 * Only spans that decoding changes are returned.
	 *
	 * @param string $html Rendered text.
	 * @return list<string>
	 */
	private function decoded_shortcode_spans( string $html ): array {
		$spans    = array();
		$segments = explode( ']', $html );
		array_pop( $segments );
		foreach ( $segments as $segment ) {
			$open = strpos( $segment, '[' );
			if ( false === $open ) {
				continue;
			}
			$span    = substr( $segment, $open );
			$decoded = stripcslashes( $span );
			if ( $decoded !== $span ) {
				$spans[] = $decoded;
			}
		}
		return $spans;
	}

	/**
	 * The static markup core renders for parsed blocks, in document order:
	 * each block's innerContent with every null entry replaced by the next
	 * inner block. The block delimiters are whatever core's parser consumed,
	 * so nothing that core outputs is left out.
	 *
	 * @param array $blocks Parsed blocks.
	 */
	private function rendered_text( array $blocks ): string {
		$text = '';
		foreach ( $blocks as $block ) {
			$inner   = $block['innerBlocks'] ?? array();
			$content = $block['innerContent'] ?? array();
			if ( null === $block['blockName'] || ! is_array( $content ) ) {
				$text .= (string) ( $block['innerHTML'] ?? '' );
				continue;
			}
			$next = 0;
			foreach ( $content as $chunk ) {
				$text .= is_string( $chunk )
					? $chunk
					: $this->rendered_text( isset( $inner[ $next ] ) ? array( $inner[ $next++ ] ) : array() );
			}
		}
		return $text;
	}

	/**
	 * A short quoted excerpt of the text at an offset, so a message that lost
	 * its block path still says where to look.
	 *
	 * @param string $text   Text.
	 * @param int    $offset Byte offset.
	 */
	private function excerpt( string $text, int $offset ): string {
		$snippet = mb_substr( (string) preg_replace( '/\s+/', ' ', substr( $text, $offset ) ), 0, 40 );
		return ' — near ' . wp_json_encode( $snippet );
	}

	/**
	 * Decodes named and numeric entities the way a browser does (the trailing
	 * semicolon of a numeric entity is optional).
	 *
	 * @param string $text Text.
	 */
	private function decode_entities( string $text ): string {
		$text = (string) preg_replace_callback(
			'/&#(x[0-9a-f]+|[0-9]+);?/i',
			static function ( array $m ): string {
				$code = 'x' === strtolower( $m[1][0] ) ? hexdec( substr( $m[1], 1 ) ) : (int) $m[1];
				return $code > 0 && $code <= 0x10FFFF ? mb_chr( (int) $code, 'UTF-8' ) : '';
			},
			$text
		);
		return html_entity_decode( $text, ENT_QUOTES | ENT_HTML5, 'UTF-8' );
	}

	/**
	 * Design declarations of an inline style attribute whose value is not made of tokens.
	 *
	 * @param string $style The style attribute.
	 * @return list<array{0:string,1:string}> [property, value] pairs.
	 */
	private function inline_style_literals( string $style ): array {
		$out = array();
		foreach ( $this->split_top_level( $style, ';' ) as $declaration ) {
			$colon = strpos( $declaration, ':' );
			if ( false === $colon ) {
				continue;
			}
			$property = strtolower( trim( substr( $declaration, 0, $colon ) ) );
			$value    = trim( (string) preg_replace( '/\s*!\s*important\s*$/i', '', substr( $declaration, $colon + 1 ) ) );
			if ( 1 !== preg_match( self::DESIGN_PROPERTY, $property ) || '' === $value ) {
				continue;
			}
			// A background image URL is content, not a design value.
			if ( 1 === preg_match( '/^background(-image)?$/', $property ) ) {
				$value = trim( (string) preg_replace( '/\burl\((?:[^()"\']|"[^"]*"|\'[^\']*\')*\)/i', '', $value ) );
				if ( '' === $value ) {
					continue;
				}
			}
			foreach ( $this->split_top_level( $value, ' ,' ) as $part ) {
				if ( ! TokenValue::isToken( $part ) ) {
					$out[] = array( $property, $value );
					break;
				}
			}
		}
		return $out;
	}

	/**
	 * Splits on any of the given characters outside parentheses and quotes.
	 *
	 * @param string $text       Text.
	 * @param string $separators Separator characters (a space also matches tabs and newlines).
	 * @return list<string> Non-empty trimmed parts.
	 */
	private function split_top_level( string $text, string $separators ): array {
		$parts   = array();
		$current = '';
		$depth   = 0;
		$quote   = '';
		$length  = strlen( $text );
		for ( $i = 0; $i < $length; $i++ ) {
			$ch = $text[ $i ];
			if ( '' !== $quote ) {
				$quote = $ch === $quote ? '' : $quote;
			} elseif ( '"' === $ch || "'" === $ch ) {
				$quote = $ch;
			} elseif ( '(' === $ch ) {
				++$depth;
			} elseif ( ')' === $ch ) {
				$depth = max( 0, $depth - 1 );
			} elseif ( 0 === $depth && ( str_contains( $separators, $ch ) || ( 1 === preg_match( '/\s/', $ch ) && str_contains( $separators, ' ' ) ) ) ) {
				$parts[] = trim( $current );
				$current = '';
				continue;
			}
			$current .= $ch;
		}
		$parts[] = trim( $current );
		return array_values( array_filter( $parts, static fn( string $p ): bool => '' !== $p ) );
	}

	/**
	 * Style names registered for a block type: block style variations from the
	 * registry plus the styles the block type declares itself.
	 *
	 * @param string $block Block name.
	 * @return list<string>
	 */
	public static function registeredStyleNames( string $block ): array {
		$known = array_map( 'strval', array_keys( \WP_Block_Styles_Registry::get_instance()->get_registered_styles_for_block( $block ) ) );
		$type  = \WP_Block_Type_Registry::get_instance()->get_registered( $block );
		if ( $type && is_array( $type->styles ) ) {
			foreach ( $type->styles as $style ) {
				if ( isset( $style['name'] ) ) {
					$known[] = (string) $style['name'];
				}
			}
		}
		return array_values( array_unique( $known ) );
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
		$known   = self::registeredStyleNames( $block );
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
