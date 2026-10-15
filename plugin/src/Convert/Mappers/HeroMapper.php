<?php
/**
 * Mapper: pediment/hero → group, cover or columns, by variant.
 *
 * @package Pediment
 */

declare(strict_types=1);

namespace Pediment\Convert\Mappers;

use Pediment\Convert\Attrs;
use Pediment\Convert\Build;
use Pediment\Convert\Context;
use Pediment\Convert\Mapper;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Variants: default / centered → section group (h1, lead, pill button);
 * media-bg → cover.is-style-media-card with the image and a primary overlay;
 * stat-card → two columns: copy, buttons and ticks; a media-card cover with
 * a card holding the stat and metrics.
 */
final class HeroMapper implements Mapper {

	private const VARIANTS = array( 'default', 'centered', 'media-bg', 'stat-card' );

	public function names(): array {
		return array( 'pediment/hero' );
	}

	public function map( array $block, Context $context ): ?array {
		$attrs   = (array) ( $block['attrs'] ?? array() );
		$variant = Attrs::string( $attrs, 'variant', 'default' );
		$allowed = function_exists( 'pediment_hero_variants' ) ? (array) pediment_hero_variants() : self::VARIANTS;
		if ( ! in_array( $variant, $allowed, true ) ) {
			$variant = 'default';
		}
		if ( ! in_array( $variant, self::VARIANTS, true ) ) {
			$context->note( "pediment/hero: variant {$variant} has no mapping, converted as default" );
			$variant = 'default';
		}
		if ( 'media-bg' === $variant ) {
			return $this->mediaBackground( $block, $context );
		}
		if ( 'stat-card' === $variant ) {
			return $this->statCard( $block, $context );
		}
		$inner = $this->copy( $attrs, 'centered' === $variant );
		if ( array() === $inner ) {
			return null;
		}
		$group           = array( 'tagName' => 'section' );
		$group           = Attrs::put( $group, 'align', $context->align( $block ) );
		$group           = Attrs::put( $group, 'className', $context->className( $block, 'core/group' ) );
		$group['layout'] = array( 'type' => 'constrained' );
		return Build::group( $group + $context->carry( $block, 'Hero' ), $inner );
	}

	/**
	 * Headline, subheadline and the primary button — the parts that are set.
	 *
	 * @param array $attrs  Legacy attributes.
	 * @param bool  $center Centre the text and the button.
	 * @return list<array>
	 */
	private function copy( array $attrs, bool $center ): array {
		$inner    = array();
		$headline = Attrs::rich( $attrs, 'headline' );
		if ( '' !== $headline ) {
			$inner[] = Build::heading( 1, $headline, $center ? array( 'textAlign' => 'center' ) : array() );
		}
		$sub = Attrs::rich( $attrs, 'subheadline' );
		if ( '' !== $sub ) {
			$inner[] = Build::paragraph( $sub, ( $center ? array( 'align' => 'center' ) : array() ) + array( 'className' => 'is-style-lead' ) );
		}
		$button = $this->button( $attrs, 'ctaText', 'ctaUrl', 'pill' );
		if ( null !== $button ) {
			$layout  = $center ? array(
				'layout' => array(
					'type'           => 'flex',
					'justifyContent' => 'center',
				),
			) : array();
			$inner[] = Build::buttons( array( $button ), $layout );
		}
		return $inner;
	}

	/**
	 * A button when both its text and URL are set (as render.php required).
	 *
	 * @param array  $attrs Legacy attributes.
	 * @param string $text  Text attribute key.
	 * @param string $url   URL attribute key.
	 * @param string $style Style slug.
	 */
	private function button( array $attrs, string $text, string $url, string $style ): ?array {
		$label = Attrs::rich( $attrs, $text );
		$href  = Attrs::url( $attrs, $url );
		return '' === $label || '' === $href ? null : Build::button( $label, $href, $style );
	}

	/**
	 * The media-bg variant.
	 *
	 * @param array   $block   Legacy block.
	 * @param Context $context Run.
	 */
	private function mediaBackground( array $block, Context $context ): ?array {
		$attrs = (array) ( $block['attrs'] ?? array() );
		$inner = $this->copy( $attrs, false );
		$id    = Attrs::int( $attrs, 'mediaId' );
		$url   = $id > 0 ? $context->media()->url( $id, 'full' ) : '';
		if ( $id > 0 && '' === $url ) {
			$context->note( "pediment/hero: image {$id} not found, left out" );
		}
		if ( '' === $url && array() === $inner ) {
			return null;
		}
		$cover = array();
		if ( '' !== $url ) {
			$cover = array(
				'url'      => $url,
				'id'       => $id,
				'dimRatio' => 60,
			);
		}
		$cover['overlayColor']       = 'primary';
		$cover['isUserOverlayColor'] = true;
		$cover['tagName']            = 'section';
		$cover                       = Attrs::put( $cover, 'align', $context->align( $block ) );
		$cover['className']          = $context->className( $block, 'core/cover', 'is-style-media-card' );
		$cover['layout']             = array( 'type' => 'constrained' );
		return Build::cover( $cover + $context->carry( $block, 'Hero' ), $inner );
	}

	/**
	 * The stat-card variant; null when it has no copy, no stat and no image.
	 *
	 * @param array   $block   Legacy block.
	 * @param Context $context Run.
	 */
	private function statCard( array $block, Context $context ): ?array {
		$attrs   = (array) ( $block['attrs'] ?? array() );
		$left    = array();
		$eyebrow = Attrs::rich( $attrs, 'eyebrow' );
		if ( '' !== $eyebrow ) {
			$left[] = Build::paragraph( $eyebrow, array( 'className' => 'is-style-eyebrow' ) );
		}
		$headline = Attrs::rich( $attrs, 'headline' );
		if ( '' !== $headline ) {
			$left[] = Build::heading( 1, $headline );
		}
		$sub = Attrs::rich( $attrs, 'subheadline' );
		if ( '' !== $sub ) {
			$left[] = Build::paragraph( $sub, array( 'className' => 'is-style-lead' ) );
		}
		$buttons = array_values( array_filter( array( $this->button( $attrs, 'ctaText', 'ctaUrl', 'pill' ), $this->button( $attrs, 'secondaryText', 'secondaryUrl', 'pill-ghost' ) ) ) );
		if ( array() !== $buttons ) {
			$left[] = Build::buttons( $buttons );
		}
		$ticks = array();
		foreach ( is_array( $attrs['ticks'] ?? null ) ? $attrs['ticks'] : array() as $tick ) {
			$tick = is_scalar( $tick ) ? Attrs::html( (string) $tick ) : '';
			if ( '' !== $tick ) {
				$ticks[] = $tick;
			}
		}
		if ( array() !== $ticks ) {
			$left[] = Build::bulletList( $ticks );
		}

		$figure = $this->figure( $attrs, $context );
		if ( array() === $left && array() === $figure['innerBlocks'] && ! isset( $figure['attrs']['url'] ) ) {
			return null;
		}
		$columns = Attrs::put( array(), 'align', $context->align( $block ) );
		$columns = Attrs::put( $columns, 'className', $context->className( $block, 'core/columns' ) );
		return Build::columns(
			$columns + $context->carry( $block, 'Hero' ),
			array( Build::column( '52%', $left ), Build::column( '48%', array( $figure ) ) )
		);
	}

	/**
	 * The stat-card's image with the stat card on it.
	 *
	 * @param array   $attrs   Legacy attributes.
	 * @param Context $context Run.
	 */
	private function figure( array $attrs, Context $context ): array {
		$value   = Attrs::rich( $attrs, 'statValue' );
		$text    = Attrs::rich( $attrs, 'statText' );
		$metrics = array();
		foreach ( is_array( $attrs['metrics'] ?? null ) ? $attrs['metrics'] : array() as $metric ) {
			$metric = is_array( $metric ) ? $metric : array();
			$parts  = array_filter(
				array(
					'' === Attrs::rich( $metric, 'value' ) ? '' : '<strong>' . Attrs::rich( $metric, 'value' ) . '</strong>',
					Attrs::rich( $metric, 'label' ),
				),
				static fn( string $part ): bool => '' !== $part
			);
			if ( array() !== $parts ) {
				$metrics[] = Build::paragraph( implode( '<br>', $parts ) );
			}
		}

		$card = array();
		if ( '' !== $value || '' !== $text || array() !== $metrics ) {
			$stat = array(
				Build::group(
					array(
						'className' => 'is-style-stat',
						'layout'    => array( 'type' => 'constrained' ),
						'metadata'  => array( 'name' => 'Stat' ),
					),
					Build::statParagraphs( $value, $text )
				),
			);
			if ( array() !== $metrics ) {
				$stat[] = Build::group(
					array(
						'layout'   => array(
							'type'           => 'flex',
							'flexWrap'       => 'wrap',
							'justifyContent' => 'center',
						),
						'metadata' => array( 'name' => 'Metrics' ),
					),
					$metrics
				);
			}
			$card[] = Build::group(
				array(
					'className' => 'is-style-card',
					'layout'    => array( 'type' => 'constrained' ),
					'metadata'  => array( 'name' => 'Hero stat' ),
				),
				$stat
			);
		}

		$id    = Attrs::int( $attrs, 'mediaId' );
		$url   = $id > 0 ? $context->media()->url( $id, 'large' ) : '';
		$cover = array();
		if ( '' !== $url ) {
			$cover             = array(
				'url' => $url,
				'id'  => $id,
			);
			$cover             = Attrs::put( $cover, 'alt', $context->media()->alt( $id ) );
			$cover['dimRatio'] = 0;
		} else {
			if ( $id > 0 ) {
				$context->note( "pediment/hero: image {$id} not found, left out" );
			}
			$cover['overlayColor'] = 'primary';
		}
		$cover['isUserOverlayColor'] = true;
		$cover['contentPosition']    = 'bottom center';
		// Dark text: the stat card is light, on the image and on the primary fill.
		$cover['isDark']    = false;
		$cover['className'] = 'is-style-media-card';
		$cover['style']     = array( 'dimensions' => array( 'aspectRatio' => '5/6' ) );
		$cover['layout']    = array( 'type' => 'constrained' );
		$cover['metadata']  = array( 'name' => 'Hero media' );
		return Build::cover( $cover, $card );
	}
}
