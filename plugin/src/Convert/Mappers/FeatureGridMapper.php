<?php
/**
 * Mapper: pediment/feature-grid + pediment/feature → grid group of card groups.
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
 * Grid (at most 3 columns, 2 for exactly four cards, one on phones) of group.is-style-card
 * → image or pediment/icon in the tile style, h3, text, link paragraph. An
 * image wins over the icon, as in the legacy render. The card style centres
 * the content, so a client theme's card style can align it another way.
 */
final class FeatureGridMapper implements Mapper {

	public function names(): array {
		return array( 'pediment/feature-grid', 'pediment/feature' );
	}

	public function map( array $block, Context $context ): ?array {
		return 'pediment/feature' === $block['blockName'] ? $this->feature( $block, $context ) : $this->grid( $block, $context );
	}

	/**
	 * The grid.
	 *
	 * @param array   $block   Legacy block.
	 * @param Context $context Run.
	 */
	private function grid( array $block, Context $context ): ?array {
		$cards = $context->inner( (array) ( $block['innerBlocks'] ?? array() ) );
		if ( array() === $cards ) {
			return null;
		}
		$group           = array( 'tagName' => 'section' );
		$group           = Attrs::put( $group, 'align', $context->align( $block ) );
		$group           = Attrs::put( $group, 'className', $context->className( $block, 'core/group' ) );
		$group['layout'] = Build::gridLayout( 4 === count( $cards ) ? 2 : 3, '16rem' );
		return Build::group( $group + $context->carry( $block, 'Features' ), $cards );
	}

	/**
	 * One card.
	 *
	 * @param array   $block   Legacy block.
	 * @param Context $context Run.
	 */
	private function feature( array $block, Context $context ): ?array {
		$attrs = (array) ( $block['attrs'] ?? array() );
		$title = Attrs::rich( $attrs, 'title' );
		$text  = Attrs::rich( $attrs, 'text' );
		if ( '' === $title && '' === $text ) {
			return null;
		}

		$inner = array();
		$id    = Attrs::int( $attrs, 'imageId' );
		$url   = $id > 0 ? $context->media()->url( $id, 'medium' ) : '';
		if ( '' !== $url ) {
			$inner[] = Build::image(
				array(
					'id'              => $id,
					'width'           => '88px',
					'height'          => '88px',
					'aspectRatio'     => '1',
					'scale'           => 'cover',
					'sizeSlug'        => 'medium',
					'linkDestination' => 'none',
					'style'           => array( 'border' => array( 'radius' => 'var(--wp--custom--radius--md)' ) ),
				),
				$url,
				$context->media()->alt( $id )
			);
		} else {
			if ( $id > 0 ) {
				$context->note( "pediment/feature: image {$id} not found, left out" );
			}
			$icon = $this->icon( $attrs, $context );
			if ( null !== $icon ) {
				$inner[] = $icon;
			}
		}
		if ( '' !== $title ) {
			$inner[] = Build::heading( 3, $title );
		}
		if ( '' !== $text ) {
			$inner[] = Build::paragraph( $text );
		}
		$link_text = Attrs::rich( $attrs, 'linkText' );
		$link_url  = Attrs::url( $attrs, 'linkUrl' );
		if ( '' !== $link_text && '' !== $link_url ) {
			$inner[] = Build::paragraph( "<a href=\"{$link_url}\">{$link_text}</a>" );
		}

		$group = array(
			'className' => $context->className( $block, 'core/group', 'is-style-card' ),
			'layout'    => array( 'type' => 'constrained' ),
		);
		return Build::group( $group + $context->carry( $block, 'Feature' ), $inner );
	}

	/**
	 * The card's icon as a pediment/icon block in the tile style; null when
	 * the feature has an empty icon. The legacy block.json default is
	 * trend-up, so a feature without the attribute rendered trend-up.
	 *
	 * @param array   $attrs   Legacy attributes.
	 * @param Context $context Run.
	 */
	private function icon( array $attrs, Context $context ): ?array {
		// The same normalisation as pediment_icon(), so the stored slug is the one it renders.
		$slug = (string) preg_replace( '/[^a-z0-9-]/', '', strtolower( Attrs::string( $attrs, 'icon', 'trend-up' ) ) );
		if ( '' === $slug ) {
			return null;
		}
		if ( function_exists( 'pediment_icon_map' ) && ! isset( pediment_icon_map()[ $slug ] ) ) {
			$context->note( "pediment/feature: icon {$slug} is not in the icon set, the card shows no icon" );
		}
		return Build::dynamic(
			'pediment/icon',
			array(
				'icon'      => $slug,
				'className' => 'is-style-tile',
			)
		);
	}
}
