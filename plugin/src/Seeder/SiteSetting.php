<?php
/**
 * When the seeder may fill a site setting that names a post: the front page,
 * the posts page and the site logo.
 *
 * @package Pediment
 */

declare(strict_types=1);

namespace Pediment\Seeder;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class SiteSetting {
	/**
	 * A setting is unset while it is 0 or names a post that no longer exists
	 * or is in the trash. Any other value is a choice someone made, and the
	 * seeder keeps it — even when the page or file the manifest declares was
	 * created again in this run.
	 */
	public static function isUnset( int $postId ): bool {
		if ( $postId <= 0 ) {
			return true;
		}
		$status = get_post_status( $postId );
		return false === $status || 'trash' === $status;
	}
}
