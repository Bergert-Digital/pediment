<?php
/**
 * Meta keys the seeding engine owns.
 *
 * @package Pediment
 */

declare(strict_types=1);

namespace Pediment\Seeder;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Meta {
	/** Stable identity. Never look seeded content up by slug. */
	public const KEY = '_pediment_seed_key';

	/** Hash of the PERSISTED row as last written by the seeder; arbitrates content. */
	public const HASH = '_pediment_seed_hash';

	/** Hash of the git-side INPUT the seeder last wrote; detects content changes. */
	public const SOURCE = '_pediment_seed_source';

	/**
	 * JSON array of per-position hashes over the pediment/mega-menu blocks in
	 * a navigation entity, as last written by the seeder. Arbitrates mega
	 * content the way HASH arbitrates page content: matching = git owns the
	 * block and manifest changes flow through; anything else = the client
	 * edited it in the editor and keeps it. See MegaBlocks.
	 */
	public const MEGA_HASH = '_pediment_seed_mega_hash';

	/**
	 * Drop what wp_trash_post() left behind.
	 *
	 * Every restore in this engine writes post_status directly, which skips the
	 * untrash hooks — so this bookkeeping would stay on the row forever and a
	 * later real untrash would act on stale values.
	 */
	public static function clearTrashBookkeeping( int $postId ): void {
		foreach ( [ '_wp_trash_meta_status', '_wp_trash_meta_time', '_wp_desired_post_slug' ] as $key ) {
			delete_post_meta( $postId, $key );
		}
	}
}
