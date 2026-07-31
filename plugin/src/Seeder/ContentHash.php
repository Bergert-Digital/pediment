<?php
/**
 * Versioned content hash used to arbitrate between git and the database.
 *
 * @package Pediment
 */

declare(strict_types=1);

namespace Pediment\Seeder;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class ContentHash {
	/**
	 * Bump when the hashed shape changes. A stored hash carrying a different
	 * version never matches, so pages fall back to "treat as edited" —
	 * structure-only, never a content overwrite.
	 */
	public const VERSION = '1';

	public static function compute( string $title, string $content ): string {
		return self::VERSION . ':' . hash( 'sha256', $title . "\x00" . $content );
	}

	/**
	 * Hash the row as WordPress actually stored it. Hashing the intended input
	 * instead makes every page mismatch on the first re-seed and silently
	 * disables content updates site-wide (spec §4.2).
	 */
	public static function forPost( int $postId ): string {
		clean_post_cache( $postId );
		$post = get_post( $postId );
		if ( ! $post instanceof \WP_Post ) {
			return '';
		}
		return self::compute( (string) $post->post_title, (string) $post->post_content );
	}

	public static function matches( string $stored, string $current ): bool {
		if ( '' === $stored || ! str_starts_with( $stored, self::VERSION . ':' ) ) {
			return false;
		}
		return hash_equals( $stored, $current );
	}
}
