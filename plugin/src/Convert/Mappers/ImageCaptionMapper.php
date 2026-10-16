<?php
/**
 * Mapper: pediment/image-caption → core/image with the framed style.
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
 * An image.is-style-framed (large size) with the caption. Without an existing
 * attachment the legacy block rendered nothing, so it is removed.
 */
final class ImageCaptionMapper implements Mapper {

	public function names(): array {
		return array( 'pediment/image-caption' );
	}

	public function map( array $block, Context $context ): ?array {
		$attrs = (array) ( $block['attrs'] ?? array() );
		$id    = Attrs::int( $attrs, 'mediaId' );
		$url   = $id > 0 ? $context->media()->url( $id, 'large' ) : '';
		if ( '' === $url ) {
			return null;
		}
		$alt = Attrs::string( $attrs, 'altOverride' );
		if ( '' === $alt ) {
			$alt = $context->media()->alt( $id );
		}
		$out              = array(
			'id'              => $id,
			'sizeSlug'        => 'large',
			'linkDestination' => 'none',
		);
		$out              = Attrs::put( $out, 'align', $context->align( $block ) );
		$out['className'] = $context->className( $block, 'core/image', 'is-style-framed' );
		return Build::image( $out + $context->carry( $block, null ), $url, $alt, Attrs::rich( $attrs, 'caption' ) );
	}
}
