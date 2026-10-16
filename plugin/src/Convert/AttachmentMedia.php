<?php
/**
 * Media lookups against the media library.
 *
 * @package Pediment
 */

declare(strict_types=1);

namespace Pediment\Convert;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The site's own attachments.
 */
final class AttachmentMedia implements Media {

	public function url( int $id, string $size ): string {
		$url = $id > 0 ? wp_get_attachment_image_url( $id, $size ) : false;
		return is_string( $url ) ? $url : '';
	}

	public function alt( int $id ): string {
		return $id > 0 ? (string) get_post_meta( $id, '_wp_attachment_image_alt', true ) : '';
	}
}
