<?php
/**
 * KSES suspension for seeder writes.
 *
 * Seeded content is git-authored markup, not user input. Under WP-CLI there is
 * no current user, so kses_init_filters() is active and rewrites what it stores
 * — which both corrupts block-comment JSON and makes stored content differ from
 * what the seeder computed, so the same rows are rewritten on every run.
 *
 * @package Pediment
 */

declare(strict_types=1);

namespace Pediment\Seeder;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Kses {
	/** @return bool Whether the filters were active — pass this back to restore(). */
	public static function suspend(): bool {
		$active = false !== has_filter( 'content_save_pre', 'wp_filter_post_kses' );
		if ( $active ) {
			kses_remove_filters();
		}
		return $active;
	}

	public static function restore( bool $wasActive ): void {
		if ( $wasActive ) {
			kses_init_filters();
		}
	}
}
