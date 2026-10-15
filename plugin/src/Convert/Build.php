<?php
/**
 * Builders for parsed core blocks whose HTML matches the block's save().
 *
 * @package Pediment
 */

declare(strict_types=1);

namespace Pediment\Convert;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Each method returns one parse_blocks()-shaped array. The HTML is what the
 * block editor's save() stores, in the editor's serializer layout (a newline
 * after the opening comment and before the closing one, a blank line between
 * inner blocks), so serialize_blocks() of the result passes block validation.
 * Text and URLs passed in must already be escaped (see Attrs).
 */
final class Build {

	/**
	 * A block without inner blocks.
	 *
	 * @param string $name  Block name.
	 * @param array  $attrs Comment attributes.
	 * @param string $html  Saved HTML.
	 */
	public static function leaf( string $name, array $attrs, string $html ): array {
		return array(
			'blockName'    => $name,
			'attrs'        => $attrs,
			'innerBlocks'  => array(),
			'innerHTML'    => "\n{$html}\n",
			'innerContent' => array( "\n{$html}\n" ),
		);
	}

	/**
	 * A block with inner blocks: $open, the inner blocks separated by blank
	 * lines, $close.
	 *
	 * @param string $name  Block name.
	 * @param array  $attrs Comment attributes.
	 * @param string $open  HTML before the inner blocks.
	 * @param array  $inner Parsed inner blocks.
	 * @param string $close HTML after the inner blocks.
	 */
	public static function container( string $name, array $attrs, string $open, array $inner, string $close ): array {
		$inner   = array_values( $inner );
		$content = array( "\n{$open}" );
		foreach ( $inner as $i => $unused ) {
			if ( $i > 0 ) {
				$content[] = "\n\n";
			}
			$content[] = null;
		}
		$content[] = "{$close}\n";
		return array(
			'blockName'    => $name,
			'attrs'        => $attrs,
			'innerBlocks'  => $inner,
			'innerHTML'    => implode( '', array_filter( $content, 'is_string' ) ),
			'innerContent' => $content,
		);
	}

	/**
	 * Builds core/group. Reads tagName, align, className and a preset
	 * style.spacing.margin.bottom from $attrs.
	 *
	 * @param array $attrs Attributes.
	 * @param array $inner Inner blocks.
	 */
	public static function group( array $attrs, array $inner ): array {
		$tag    = (string) ( $attrs['tagName'] ?? 'div' );
		$class  = self::classes( 'wp-block-group', self::align( $attrs ), (string) ( $attrs['className'] ?? '' ) );
		$margin = (string) ( $attrs['style']['spacing']['margin']['bottom'] ?? '' );
		$style  = '' === $margin ? '' : ' style="margin-bottom:' . self::preset( $margin ) . '"';
		return self::container( 'core/group', $attrs, "<{$tag} class=\"{$class}\"{$style}>", $inner, "</{$tag}>" );
	}

	/**
	 * Builds core/paragraph. Reads align and className.
	 *
	 * @param string $html  Escaped content.
	 * @param array  $attrs Attributes.
	 */
	public static function paragraph( string $html, array $attrs = array() ): array {
		$class = self::classes( isset( $attrs['align'] ) ? 'has-text-align-' . $attrs['align'] : '', (string) ( $attrs['className'] ?? '' ) );
		return self::leaf( 'core/paragraph', $attrs, '' === $class ? "<p>{$html}</p>" : "<p class=\"{$class}\">{$html}</p>" );
	}

	/**
	 * Builds core/heading. Level 2 is core's default and is not stored.
	 *
	 * @param int    $level Heading level.
	 * @param string $html  Escaped content.
	 * @param array  $attrs Attributes (textAlign, className).
	 */
	public static function heading( int $level, string $html, array $attrs = array() ): array {
		if ( 2 !== $level ) {
			$attrs['level'] = $level;
		}
		$class = self::classes( 'wp-block-heading', isset( $attrs['textAlign'] ) ? 'has-text-align-' . $attrs['textAlign'] : '', (string) ( $attrs['className'] ?? '' ) );
		return self::leaf( 'core/heading', $attrs, "<h{$level} class=\"{$class}\">{$html}</h{$level}>" );
	}

	/**
	 * Builds core/quote with an optional citation.
	 *
	 * @param array  $attrs    Attributes (align, className).
	 * @param array  $inner    Inner blocks.
	 * @param string $citation Escaped citation, '' for none.
	 */
	public static function quote( array $attrs, array $inner, string $citation ): array {
		$class = self::classes( 'wp-block-quote', self::align( $attrs ), (string) ( $attrs['className'] ?? '' ) );
		$cite  = '' === $citation ? '' : "<cite>{$citation}</cite>";
		return self::container( 'core/quote', $attrs, "<blockquote class=\"{$class}\">", $inner, "{$cite}</blockquote>" );
	}

	/**
	 * The class attribute value from class names; empty names are skipped.
	 *
	 * @param string ...$names Class names.
	 */
	private static function classes( string ...$names ): string {
		return esc_attr( implode( ' ', array_filter( $names, static fn( string $name ): bool => '' !== $name ) ) );
	}

	/**
	 * The alignwide / alignfull class, or ''.
	 *
	 * @param array $attrs Attributes.
	 */
	private static function align( array $attrs ): string {
		return isset( $attrs['align'] ) ? 'align' . $attrs['align'] : '';
	}

	/**
	 * Turns var:preset|spacing|60 into var(--wp--preset--spacing--60).
	 *
	 * @param string $value Preset reference.
	 */
	private static function preset( string $value ): string {
		return 'var(--wp--preset--' . str_replace( '|', '--', substr( $value, strlen( 'var:preset|' ) ) ) . ')';
	}
}
