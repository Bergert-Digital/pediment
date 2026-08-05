<?php
/**
 * WP-CLI: `wp pediment claim`.
 *
 * @package Pediment
 */

declare(strict_types=1);

namespace Pediment\Cli;

use Pediment\Language\LanguageRegistry;
use Pediment\Seeder\Claimer;
use Pediment\Seeder\Manifest;
use Pediment\Seeder\Plan;
use Pediment\Seeder\Reporter;
use Pediment\Seeder\StateReader;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Gives existing content the seed identity the engine resolves by.
 *
 * Run once, on a site whose content predates Pediment's seeding engine, before
 * the first `wp pediment seed`. Writes `_pediment_seed_key` and nothing else,
 * so every claimed row stays protected from content writes.
 */
final class ClaimCommand {
	/**
	 * ## OPTIONS
	 *
	 * [--dry-run]
	 * : Print the plan and exit without writing anything.
	 *
	 * ## EXAMPLES
	 *
	 *     wp pediment claim --dry-run
	 *     wp pediment claim
	 *
	 * @when after_wp_load
	 *
	 * @param array<int,string>    $args      Positional args (unused).
	 * @param array<string,string> $assocArgs Associative args.
	 */
	public function __invoke( array $args, array $assocArgs ): void {
		$dryRun = isset( $assocArgs['dry-run'] );

		Manifest::resetCache();
		$manifest = Manifest::load();

		if ( null === $manifest ) {
			$output = self::render( new Plan(), false, '', [ sprintf( 'No seed manifest found. Create %s/%s in the active theme.', get_stylesheet(), Manifest::RELATIVE_PATH ) ] );
			if ( class_exists( '\WP_CLI' ) ) {
				\WP_CLI::line( $output );
				\WP_CLI::error( 'Nothing was claimed.' );
			}
			return;
		}

		$provider = LanguageRegistry::provider();
		$claimer  = new Claimer( $provider );
		$plan     = $claimer->plan( $manifest, ( new StateReader( $provider ) )->read() );
		$errors   = [];

		if ( ! $dryRun ) {
			$result = $claimer->apply( $plan );
			$errors = $result['errors'];
		}

		$output = self::render( $plan, ! $dryRun, $manifest->path(), $errors );

		if ( class_exists( '\WP_CLI' ) ) {
			\WP_CLI::line( $output );
			if ( [] !== $errors ) {
				\WP_CLI::error( 'Claiming did not complete cleanly. See the report above.' );
			}
			\WP_CLI::success( $dryRun ? 'Dry run complete — nothing was written.' : 'Claim applied.' );
		}
	}

	/**
	 * The exact bytes the command prints, so the shape can be tested.
	 *
	 * @param string[] $errors
	 */
	public static function render( Plan $plan, bool $applied, string $manifestPath, array $errors ): string {
		return Reporter::claimText( $plan, $applied, $manifestPath, $errors );
	}
}
