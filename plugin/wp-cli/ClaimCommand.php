<?php
/**
 * WP-CLI: `wp pediment claim`.
 *
 * @package Pediment
 */

declare(strict_types=1);

namespace Pediment\Cli;

use Pediment\Seeder\ClaimResult;
use Pediment\Seeder\ClaimRunner;
use Pediment\Seeder\Reporter;

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

		$result = ( new ClaimRunner() )->run( [ 'dry_run' => $dryRun ] );
		$output = self::render( $result );

		if ( ! class_exists( '\WP_CLI' ) ) {
			return;
		}

		\WP_CLI::line( $output );

		// An empty manifest path means ClaimRunner found no manifest at all —
		// the only case where nothing was even planned, as opposed to a plan
		// that ran but reported errors.
		if ( '' === $result->manifestPath ) {
			\WP_CLI::error( 'Nothing was claimed.' );
			return;
		}

		if ( [] !== $result->errors ) {
			\WP_CLI::error( 'Claiming did not complete cleanly. See the report above.' );
		}
		\WP_CLI::success( $dryRun ? 'Dry run complete — nothing was written.' : 'Claim applied.' );
	}

	/**
	 * The exact bytes the command prints, so the shape can be tested.
	 *
	 * Takes the ClaimResult whole. Destructuring it into four arguments here
	 * only to rebuild an identical one on the next line contradicted the
	 * single-claim-path extraction this seam was created by (Task 7b).
	 */
	public static function render( ClaimResult $result ): string {
		return Reporter::claimText( $result );
	}
}
