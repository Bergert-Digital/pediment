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
 * → image (when set), h3, text, link paragraph. The icon has no core
 * equivalent and is dropped (noted).
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
					'align'           => 'center',
					'style'           => array( 'border' => array( 'radius' => 'var(--wp--custom--radius--md)' ) ),
				),
				$url,
				$context->media()->alt( $id )
			);
		} else {
			if ( $id > 0 ) {
				$context->note( "pediment/feature: image {$id} not found, left out" );
			}
			$context->note( 'pediment/feature: icon dropped, core blocks cannot show the icon set' );
		}
		if ( '' !== $title ) {
			$inner[] = Build::heading( 3, $title, array( 'textAlign' => 'center' ) );
		}
		if ( '' !== $text ) {
			$inner[] = Build::paragraph( $text, array( 'align' => 'center' ) );
		}
		$link_text = Attrs::rich( $attrs, 'linkText' );
		$link_url  = Attrs::url( $attrs, 'linkUrl' );
		if ( '' !== $link_text && '' !== $link_url ) {
			$inner[] = Build::paragraph( "<a href=\"{$link_url}\">{$link_text}</a>", array( 'align' => 'center' ) );
		}

		$group = array(
			'className' => $context->className( $block, 'core/group', 'is-style-card' ),
			'layout'    => array( 'type' => 'constrained' ),
		);
		return Build::group( $group + $context->carry( $block, 'Feature' ), $inner );
	}
}
