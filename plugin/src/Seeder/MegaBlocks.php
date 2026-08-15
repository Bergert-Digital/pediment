<?php
/**
 * The pediment/mega-menu blocks inside a stored navigation entity, and the
 * per-position hash that arbitrates who owns each one.
 *
 * The nav-side twin of the page contract in docs/seeding.md ("The two
 * hashes"), scoped to one block type: membership stays git-owned, but a mega
 * block's content belongs to git only while its stored markup still hashes to
 * what the seeder last wrote.
 *
 * @package Pediment
 */

declare(strict_types=1);

namespace Pediment\Seeder;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class MegaBlocks {

	/**
	 * The self-closing block comments, verbatim and in document order — never
	 * parsed and re-serialized, which would break the byte stability plan()
	 * compares by.
	 *
	 * @return string[]
	 */
	public static function extract( string $content ): array {
		if ( '' === $content ) {
			return [];
		}
		preg_match_all( '#<!-- wp:pediment/mega-menu\s+\{.*?\}\s+/-->#s', $content, $matches );
		return $matches[0];
	}

	/**
	 * ContentHash's versioned shape so a VERSION bump makes every stored mega
	 * hash foreign, which falls back to "treat as edited" — never a silent
	 * overwrite.
	 */
	public static function hash( string $block ): string {
		return ContentHash::VERSION . ':' . hash( 'sha256', $block );
	}

	/**
	 * Git owns a position when nothing is stored there yet, or the stored
	 * markup is exactly what the seeder last wrote. A missing, foreign-version
	 * or mismatched hash means a human edited the block in the editor, and the
	 * seeder must splice the stored markup through verbatim.
	 */
	public static function gitOwns( ?string $storedBlock, string $storedHash ): bool {
		return null === $storedBlock || ContentHash::matches( $storedHash, self::hash( $storedBlock ) );
	}

	/** @return string[] Hash per mega position, in nav order. */
	public static function storedHashes( int $navId ): array {
		$raw     = get_post_meta( $navId, Meta::MEGA_HASH, true );
		$decoded = json_decode( is_string( $raw ) ? $raw : '', true );
		return is_array( $decoded ) ? array_map( 'strval', array_values( $decoded ) ) : [];
	}

	/**
	 * Record the hashes after a write. Per position: a block the seeder
	 * emitted from the manifest gets a fresh hash; a block spliced through
	 * because the client owns it carries its old entry (or absence) forward,
	 * so it stays client-owned. The twin of Applier's "an update on a
	 * client-edited page must leave the arbitration hash alone" — without the
	 * carry-forward, any membership-driven rewrite would re-hash the client's
	 * edited markup, flip the block back to git-owned, and the next manifest
	 * change would silently overwrite the client's edits.
	 *
	 * Ownership is re-derived from the pre-write content and hashes, so this
	 * needs no side channel from serialize().
	 *
	 * Hashes the row as WordPress actually stored it, not the string handed to
	 * wp_update_post() — same rule as ContentHash::forPost(), same reason: any
	 * content_save_pre filter that touches a byte on the way in would make the
	 * hash computed from the intended write mismatch what's actually in the
	 * database, and the block would be captured as client-owned right after its
	 * first seed. clean_post_cache() + get_post() forces a fresh read past
	 * object-cache staleness; a non-WP_Post result (post deleted mid-request)
	 * is a no-op rather than writing hashes for content that isn't there.
	 */
	public static function writeHashes( int $navId, string $oldContent ): void {
		clean_post_cache( $navId );
		$post = get_post( $navId );
		if ( ! $post instanceof \WP_Post ) {
			return;
		}

		$oldBlocks = self::extract( $oldContent );
		$oldHashes = self::storedHashes( $navId );

		$next = [];
		foreach ( self::extract( (string) $post->post_content ) as $i => $block ) {
			$next[] = self::gitOwns( $oldBlocks[ $i ] ?? null, (string) ( $oldHashes[ $i ] ?? '' ) )
				? self::hash( $block )
				: (string) ( $oldHashes[ $i ] ?? '' );
		}

		if ( [] === $next ) {
			delete_post_meta( $navId, Meta::MEGA_HASH );
			return;
		}
		update_post_meta( $navId, Meta::MEGA_HASH, wp_json_encode( $next ) );
	}
}
