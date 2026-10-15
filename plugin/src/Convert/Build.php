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
	 * Builds core/image. Reads id, align, sizeSlug, width, height, aspectRatio,
	 * scale, className and a style.border.radius token.
	 *
	 * @param array  $attrs   Attributes.
	 * @param string $url     Image URL (unescaped).
	 * @param string $alt     Alt text (unescaped).
	 * @param string $caption Escaped caption, '' for none.
	 */
	public static function image( array $attrs, string $url, string $alt, string $caption = '' ): array {
		$radius = (string) ( $attrs['style']['border']['radius'] ?? '' );
		$class  = self::classes(
			'wp-block-image',
			self::align( $attrs ),
			isset( $attrs['sizeSlug'] ) ? 'size-' . $attrs['sizeSlug'] : '',
			isset( $attrs['width'] ) || isset( $attrs['height'] ) ? 'is-resized' : '',
			'' !== $radius ? 'has-custom-border' : '',
			(string) ( $attrs['className'] ?? '' )
		);
		$styles = array();
		if ( '' !== $radius ) {
			$styles[] = 'border-radius:' . $radius;
		}
		foreach ( array( 'aspectRatio' => 'aspect-ratio', 'scale' => 'object-fit', 'width' => 'width', 'height' => 'height' ) as $key => $property ) {
			if ( isset( $attrs[ $key ] ) ) {
				$styles[] = $property . ':' . $attrs[ $key ];
			}
		}
		$style = array() === $styles ? '' : ' style="' . esc_attr( implode( ';', $styles ) ) . '"';
		$img   = sprintf( '<img src="%s" alt="%s" class="wp-image-%d"%s/>', esc_url( $url ), esc_attr( $alt ), (int) ( $attrs['id'] ?? 0 ), $style );
		$cap   = '' === $caption ? '' : '<figcaption class="wp-element-caption">' . $caption . '</figcaption>';
		return self::leaf( 'core/image', $attrs, "<figure class=\"{$class}\">{$img}{$cap}</figure>" );
	}

	/**
	 * Builds core/details: a summary line and the inner blocks shown when open.
	 *
	 * @param string $summary Escaped summary.
	 * @param array  $inner   Inner blocks.
	 * @param array  $attrs   Attributes (className).
	 */
	public static function details( string $summary, array $inner, array $attrs ): array {
		$class = self::classes( 'wp-block-details', (string) ( $attrs['className'] ?? '' ) );
		return self::container( 'core/details', $attrs, "<details class=\"{$class}\"><summary>{$summary}</summary>", $inner, '</details>' );
	}

	/**
	 * Builds core/buttons. A justifyContent layout adds no class to the saved HTML.
	 *
	 * @param array $buttons core/button blocks.
	 * @param array $attrs   Attributes (layout).
	 */
	public static function buttons( array $buttons, array $attrs = array() ): array {
		return self::container( 'core/buttons', $attrs, '<div class="wp-block-buttons">', $buttons, '</div>' );
	}

	/**
	 * Builds core/button with a named style.
	 *
	 * @param string $html  Escaped label.
	 * @param string $url   Escaped URL.
	 * @param string $style Style slug, e.g. pill.
	 */
	public static function button( string $html, string $url, string $style ): array {
		$class = 'is-style-' . $style;
		return self::leaf( 'core/button', array( 'className' => $class ), "<div class=\"wp-block-button {$class}\"><a class=\"wp-block-button__link wp-element-button\" href=\"{$url}\">{$html}</a></div>" );
	}

	/**
	 * Builds core/list (unordered) of core/list-item blocks.
	 *
	 * @param list<string> $items Escaped item contents.
	 */
	public static function bulletList( array $items ): array {
		$children = array();
		foreach ( $items as $html ) {
			$children[] = self::leaf( 'core/list-item', array(), "<li>{$html}</li>" );
		}
		return self::container( 'core/list', array(), '<ul class="wp-block-list">', $children, '</ul>' );
	}

	/**
	 * Builds core/columns. Reads align.
	 *
	 * @param array $attrs   Attributes.
	 * @param array $columns core/column blocks.
	 */
	public static function columns( array $attrs, array $columns ): array {
		$class = self::classes( 'wp-block-columns', self::align( $attrs ), (string) ( $attrs['className'] ?? '' ) );
		return self::container( 'core/columns', $attrs, "<div class=\"{$class}\">", $columns, '</div>' );
	}

	/**
	 * Builds core/column with a width such as 52%.
	 *
	 * @param string $width CSS width.
	 * @param array  $inner Inner blocks.
	 */
	public static function column( string $width, array $inner ): array {
		return self::container( 'core/column', array( 'width' => $width ), '<div class="wp-block-column" style="flex-basis:' . esc_attr( $width ) . '">', $inner, '</div>' );
	}

	/**
	 * Builds core/cover. Reads url, id, alt, dimRatio (default 100), overlayColor,
	 * contentPosition, isDark, tagName, align and className.
	 *
	 * @param array $attrs Attributes.
	 * @param array $inner Inner blocks.
	 */
	public static function cover( array $attrs, array $inner ): array {
		$tag      = (string) ( $attrs['tagName'] ?? 'div' );
		$position = (string) ( $attrs['contentPosition'] ?? '' );
		$class    = self::classes(
			'wp-block-cover',
			self::align( $attrs ),
			false === ( $attrs['isDark'] ?? true ) ? 'is-light' : '',
			'' !== $position ? 'has-custom-content-position is-position-' . str_replace( ' ', '-', $position ) : '',
			(string) ( $attrs['className'] ?? '' )
		);
		$image = '';
		if ( isset( $attrs['url'] ) ) {
			$image = sprintf(
				'<img class="wp-block-cover__image-background wp-image-%d" alt="%s" src="%s" data-object-fit="cover"/>',
				(int) ( $attrs['id'] ?? 0 ),
				esc_attr( (string) ( $attrs['alt'] ?? '' ) ),
				esc_url( (string) $attrs['url'] )
			);
		}
		$overlay = self::classes(
			'wp-block-cover__background',
			isset( $attrs['overlayColor'] ) ? 'has-' . $attrs['overlayColor'] . '-background-color' : '',
			'has-background-dim-' . (int) ( $attrs['dimRatio'] ?? 100 ),
			'has-background-dim'
		);
		$open = "<{$tag} class=\"{$class}\">{$image}<span aria-hidden=\"true\" class=\"{$overlay}\"></span><div class=\"wp-block-cover__inner-container\">";
		return self::container( 'core/cover', $attrs, $open, $inner, "</div></{$tag}>" );
	}

	/**
	 * Builds core/media-text. Reads align, mediaPosition, mediaId, mediaSizeSlug and
	 * className; the image URL and alt live only in the HTML.
	 *
	 * @param array  $attrs Attributes.
	 * @param array  $inner Inner blocks of the text side.
	 * @param string $url   Image URL (unescaped), '' for an empty figure.
	 * @param string $alt   Alt text (unescaped).
	 */
	public static function mediaText( array $attrs, array $inner, string $url, string $alt ): array {
		$right  = 'right' === ( $attrs['mediaPosition'] ?? 'left' );
		$class  = self::classes( 'wp-block-media-text', self::align( $attrs ), $right ? 'has-media-on-the-right' : '', 'is-stacked-on-mobile', (string) ( $attrs['className'] ?? '' ) );
		$img    = '' === $url ? '' : sprintf( '<img src="%s" alt="%s" class="wp-image-%d size-%s"/>', esc_url( $url ), esc_attr( $alt ), (int) ( $attrs['mediaId'] ?? 0 ), esc_attr( (string) ( $attrs['mediaSizeSlug'] ?? 'full' ) ) );
		$figure = '<figure class="wp-block-media-text__media">' . $img . '</figure>';
		$open   = "<div class=\"{$class}\">" . ( $right ? '' : $figure ) . '<div class="wp-block-media-text__content">';
		$close  = '</div>' . ( $right ? $figure : '' ) . '</div>';
		return self::container( 'core/media-text', $attrs, $open, $inner, $close );
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
