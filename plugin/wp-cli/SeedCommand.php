<?php
/**
 * WP-CLI: `wp pediment seed`.
 *
 * @package Pediment
 */

declare(strict_types=1);

namespace Pediment\Cli;

use Pediment\Seeder\Reporter;
use Pediment\Seeder\Runner;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Applies the active theme's seed manifest to this site.
 */
final class SeedCommand {
	/**
	 * ## OPTIONS
	 *
	 * [--dry-run]
	 * : Print the plan and exit without writing anything.
	 *
	 * [--json]
	 * : Emit the plan as JSON instead of text.
	 *
	 * ## EXAMPLES
	 *
	 *     wp pediment seed --dry-run
	 *     wp pediment seed
	 *
	 * @when after_wp_load
	 *
	 * @param array<int,string>    $args       Positional args (unused).
	 * @param array<string,string> $assocArgs  Associative args.
	 */
	public function __invoke( array $args, array $assocArgs ): void {
		$result = ( new Runner() )->run( [ 'dry_run' => isset( $assocArgs['dry-run'] ) ] );

		if ( isset( $assocArgs['json'] ) ) {
			\WP_CLI::line(
				(string) wp_json_encode(
					[
						'applied'  => $result->applied,
						'ok'       => $result->ok(),
						'manifest' => $result->manifestPath,
						'counts'   => $result->plan->counts(),
						'errors'   => $result->errors,
						'problems' => $result->problems,
					],
					JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES
				)
			);
		} else {
			\WP_CLI::line( Reporter::text( $result ) );
		}

		if ( ! $result->ok() ) {
			\WP_CLI::error( 'Seeding did not complete cleanly. See the report above.' );
		}

		\WP_CLI::success( $result->applied ? 'Seed applied.' : 'Dry run complete — nothing was written.' );
	}
}
