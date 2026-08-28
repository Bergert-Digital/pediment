<?php
/**
 * WP-CLI: `wp pediment languages`.
 *
 * @package Pediment
 */

declare(strict_types=1);

namespace Pediment\Cli;

use Pediment\Language\LanguageRegistry;
use Pediment\Seeder\Manifest;
use Pediment\Seeder\ManifestError;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Configures the multilingual plugin from the active theme's seed manifest.
 */
final class LanguagesCommand {
	/**
	 * ## OPTIONS
	 *
	 * [--dry-run]
	 * : Print what would change and exit without writing anything.
	 *
	 * ## EXAMPLES
	 *
	 *     wp pediment languages --dry-run
	 *     wp pediment languages
	 *
	 * @when after_wp_load
	 *
	 * @param array<int,string>    $args      Positional args (unused).
	 * @param array<string,string> $assocArgs Associative args.
	 */
	public function __invoke( array $args, array $assocArgs ): void {
		$dryRun = isset( $assocArgs['dry-run'] );

		Manifest::resetCache();
		try {
			$manifest = Manifest::load();
		} catch ( ManifestError $e ) {
			if ( class_exists( '\WP_CLI' ) ) {
				\WP_CLI::error( $e->getMessage() );
			}
			return;
		}

		if ( null === $manifest ) {
			if ( class_exists( '\WP_CLI' ) ) {
				\WP_CLI::error( sprintf( 'No seed manifest found. Create %s/%s in the active theme.', get_stylesheet(), Manifest::RELATIVE_PATH ) );
			}
			return;
		}

		if ( [] === $manifest->languages() ) {
			if ( class_exists( '\WP_CLI' ) ) {
				\WP_CLI::success( 'The manifest declares no languages — this site is monolingual. Nothing to do.' );
			}
			return;
		}

		$result = LanguageRegistry::setup()->configure( $manifest->languages(), $manifest->defaultLanguage(), $dryRun );

		if ( ! class_exists( '\WP_CLI' ) ) {
			return;
		}

		foreach ( $result['changes'] as $change ) {
			\WP_CLI::line( ( $dryRun ? 'would ' : '' ) . $change );
		}
		if ( [] === $result['changes'] ) {
			\WP_CLI::line( 'Nothing to change.' );
		}
		foreach ( $result['errors'] as $error ) {
			\WP_CLI::warning( $error );
		}

		if ( [] !== $result['errors'] ) {
			\WP_CLI::error( 'Language configuration did not complete cleanly.' );
		}

		\WP_CLI::success( $dryRun ? 'Dry run complete — nothing was written.' : 'Languages configured.' );
	}
}
