<?php
/**
 * Mapper: pediment/media-text → core/media-text with the media-row style.
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
 * A media-text.is-style-media-row; inner blocks move to the text side
 * unchanged. Core stacks the image first on mobile, like the legacy block.
 */
final class MediaTextMapper implements Mapper {

	public function names(): array {
		return array( 'pediment/media-text' );
	}

	public function map( array $block, Context $context ): ?array {
		$attrs = (array) ( $block['attrs'] ?? array() );
		$inner = $context->inner( (array) ( $block['innerBlocks'] ?? array() ) );
		$id    = Attrs::int( $attrs, 'mediaId' );
		$url   = $id > 0 ? $context->media()->url( $id, 'large' ) : '';
		if ( $id > 0 && '' === $url ) {
			$context->note( "pediment/media-text: image {$id} not found, left out" );
		}
		if ( '' === $url && array() === $inner ) {
			return null;
		}
		$alt = Attrs::string( $attrs, 'altOverride' );
		if ( '' === $alt && '' !== $url ) {
			$alt = $context->media()->alt( $id );
		}

		$out = Attrs::put( array(), 'align', $context->align( $block ) );
		// Core's default is left, the legacy default is right.
		if ( 'left' !== Attrs::string( $attrs, 'mediaPosition', 'right' ) ) {
			$out['mediaPosition'] = 'right';
		}
		if ( '' !== $url ) {
			$out['mediaId']       = $id;
			$out['mediaType']     = 'image';
			$out['mediaSizeSlug'] = 'large';
		}
		$out['className'] = $context->className( $block, 'core/media-text', 'is-style-media-row' );
		return Build::mediaText( $out + $context->carry( $block, 'Media and text' ), $inner, $url, $alt );
	}
}
