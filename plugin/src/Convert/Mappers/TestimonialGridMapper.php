<?php
/**
 * Mapper: pediment/testimonial-grid + pediment/testimonial → grid of quote cards.
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
 * Grid (2 columns, min 20rem) of group.is-style-quote-card → core/quote.
 * Without an avatar the byline is the quote's citation; with one, a flex
 * "Byline" group holds the rounded avatar and the byline paragraph.
 */
final class TestimonialGridMapper implements Mapper {

	public function names(): array {
		return array( 'pediment/testimonial-grid', 'pediment/testimonial' );
	}

	public function map( array $block, Context $context ): ?array {
		if ( 'pediment/testimonial' === $block['blockName'] ) {
			return $this->testimonial( $block, $context );
		}
		$cards = $context->inner( (array) ( $block['innerBlocks'] ?? array() ) );
		if ( array() === $cards ) {
			return null;
		}
		$group           = array( 'tagName' => 'section' );
		$group           = Attrs::put( $group, 'align', $context->align( $block ) );
		$group           = Attrs::put( $group, 'className', $context->className( $block, 'core/group' ) );
		$group['layout'] = array(
			'type'               => 'grid',
			'columnCount'        => 2,
			'minimumColumnWidth' => '20rem',
		);
		return Build::group( $group + $context->carry( $block, 'Testimonials' ), $cards );
	}

	/**
	 * One testimonial.
	 *
	 * @param array   $block   Legacy block.
	 * @param Context $context Run.
	 */
	private function testimonial( array $block, Context $context ): ?array {
		$attrs = (array) ( $block['attrs'] ?? array() );
		$quote = Attrs::rich( $attrs, 'quote' );
		if ( '' === $quote ) {
			return null;
		}
		$name   = Attrs::rich( $attrs, 'authorName' );
		$role   = Attrs::rich( $attrs, 'authorRole' );
		$byline = implode( '<br>', array_filter( array( '' === $name ? '' : "<strong>{$name}</strong>", $role ), static fn( string $part ): bool => '' !== $part ) );
		$id     = Attrs::int( $attrs, 'avatarId' );
		$url    = $id > 0 ? $context->media()->url( $id, 'thumbnail' ) : '';
		if ( $id > 0 && '' === $url ) {
			$context->note( "pediment/testimonial: avatar {$id} not found, left out" );
		}

		$inner = array( Build::quote( array(), array( Build::paragraph( $quote ) ), '' === $url ? $byline : '' ) );
		if ( '' !== $url ) {
			$person = array(
				Build::image(
					array(
						'id'              => $id,
						'width'           => '40px',
						'height'          => '40px',
						'aspectRatio'     => '1',
						'scale'           => 'cover',
						'sizeSlug'        => 'thumbnail',
						'linkDestination' => 'none',
						'className'       => 'is-style-rounded',
					),
					$url,
					$context->media()->alt( $id )
				),
			);
			if ( '' !== $byline ) {
				$person[] = Build::paragraph( $byline );
			}
			$inner[] = Build::group(
				array(
					'layout'   => array(
						'type'     => 'flex',
						'flexWrap' => 'nowrap',
					),
					'metadata' => array( 'name' => 'Byline' ),
				),
				$person
			);
		}

		$group = array(
			'className' => $context->className( $block, 'core/group', 'is-style-quote-card' ),
			'layout'    => array( 'type' => 'constrained' ),
		);
		return Build::group( $group + $context->carry( $block, 'Testimonial' ), $inner );
	}
}
