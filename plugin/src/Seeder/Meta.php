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
}
