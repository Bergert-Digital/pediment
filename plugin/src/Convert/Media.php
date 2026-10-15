<?php
/**
 * Image lookups the mappers need.
 *
 * @package Pediment
 */

declare(strict_types=1);

namespace Pediment\Convert;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Resolves attachment IDs at conversion time.
 */
interface Media {

	/**
	 * URL of an image size; '' when the attachment does not exist.
	 *
	 * @param int    $id   Attachment ID.
	 * @param string $size Image size slug.
	 */
	public function url( int $id, string $size ): string;

	/**
	 * Alt text of an attachment; '' when it has none.
	 *
	 * @param int $id Attachment ID.
	 */
	public function alt( int $id ): string;
}
