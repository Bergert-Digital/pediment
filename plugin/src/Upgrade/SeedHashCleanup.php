<?php
/**
 * Removes the content-arbitration meta the 3.x seeder left on seeded posts.
 *
 * @package Pediment
 */

declare(strict_types=1);

namespace Pediment\Upgrade;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Pediment 4 seeds bootstrap-only and reads none of these keys. Runs once per
 * site, on the first admin request after the update (production is admin-only
 * hosting, so this is the request that always comes), and records an
 * autoloaded flag so later requests pay nothing. Deleting is the safe
 * direction: a site rolled back to 3.x treats an unhashed row as edited and
 * never overwrites it.
 */
final class SeedHashCleanup {

	public const META_KEYS = array(
		'_pediment_seed_hash',
		'_pediment_seed_source',
		'_pediment_seed_mega_hash',
	);

	public const DONE_OPTION = 'pediment_seed_hash_cleanup_done';

	/**
	 * Runs the cleanup unless it has already finished on this site.
	 */
	public static function maybeRun(): void {
		if ( '1' === get_option( self::DONE_OPTION ) ) {
			return;
		}
		self::run();
	}

	/**
	 * Deletes the three meta keys from every post (one query per key), then
	 * marks the cleanup done. `_pediment_seed_key`, the identity, stays.
	 */
	public static function run(): void {
		foreach ( self::META_KEYS as $key ) {
			delete_post_meta_by_key( $key );
		}
		update_option( self::DONE_OPTION, '1', true );
	}
}
