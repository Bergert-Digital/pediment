<?php
/**
 * WordPress's own draft "Privacy Policy" page, made at install.
 *
 * @package Pediment
 */

declare(strict_types=1);

namespace Pediment\Seeder;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Every new install gets a draft page "Privacy Policy" with the slug
 * `privacy-policy`, named in the `wp_page_for_privacy_policy` setting. A
 * manifest page that wants that slug would get `privacy-policy-2`. The seeder
 * moves the draft to the trash first (that frees the slug), but only while it
 * is exactly what WordPress made: a draft, never edited, without a seed key.
 * Once someone edits or publishes it, it is their page and the seeder leaves
 * it alone, like every other row it did not create.
 */
final class CorePrivacyDraft {

	/** The core setting that names the privacy policy page. */
	public const OPTION = 'wp_page_for_privacy_policy';

	/** The ID of the untouched core draft, 0 when there is none. */
	public static function id(): int {
		$id   = (int) get_option( self::OPTION );
		$post = $id > 0 ? get_post( $id ) : null;
		if ( ! $post instanceof \WP_Post || 'page' !== $post->post_type || 'draft' !== $post->post_status ) {
			return 0;
		}
		if ( '' !== (string) get_post_meta( $id, Meta::KEY, true ) ) {
			return 0;
		}
		// Every save of the page changes post_modified; WordPress made both at once.
		if ( $post->post_modified_gmt !== $post->post_date_gmt ) {
			return 0;
		}
		return $id;
	}

	/** The slug the untouched core draft holds, '' when there is none. */
	public static function slug(): string {
		$id = self::id();
		return $id > 0 ? (string) get_post_field( 'post_name', $id ) : '';
	}
}
