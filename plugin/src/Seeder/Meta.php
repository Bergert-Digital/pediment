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
}
