<?php
/**
 * Registers the client manifest's custom post types.
 *
 * Runs on every request, not just during seeding: a CPT that only exists while
 * seeding produces entries nobody can reach and rewrite rules that never form.
 *
 * @package Pediment
 */

declare(strict_types=1);

namespace Pediment\Seeder;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class PostTypes {
	public static function register(): void {
		add_action( 'init', [ self::class, 'registerFromManifest' ], 5 );
	}

	public static function registerFromManifest(): void {
		try {
			$manifest = Manifest::load();
		} catch ( ManifestError $e ) {
			// A malformed manifest is a seeding-time error, surfaced by
			// `wp pediment seed` and the admin tab. It must never fatal a
			// front-end request.
			return;
		}

		if ( null === $manifest ) {
			return;
		}

		foreach ( $manifest->postTypes() as $spec ) {
			if ( post_type_exists( $spec->slug ) ) {
				continue;
			}
			register_post_type( $spec->slug, $spec->args );
		}
	}
}
