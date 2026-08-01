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
use Pediment\Seeder\RunResult;

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
		$output = self::render( $result, isset( $assocArgs['json'] ) );

		// Guarded like DumpSchemaCommand so the rendering is unit-testable
		// without WP-CLI loaded.
		if ( class_exists( '\WP_CLI' ) ) {
			\WP_CLI::line( $output );

			if ( ! $result->ok() ) {
				\WP_CLI::error( 'Seeding did not complete cleanly. See the report above.' );
			}

			\WP_CLI::success( $result->applied ? 'Seed applied.' : 'Dry run complete — nothing was written.' );
		}
	}

	/** The exact bytes the command prints, so the shape can be tested. */
	public static function render( RunResult $result, bool $json = false ): string {
		if ( ! $json ) {
			return Reporter::text( $result );
		}

		$items = [];
		foreach ( $result->plan->items() as $item ) {
			$items[] = [
				'kind'      => $item->kind,
				'action'    => $item->action,
				'key'       => $item->key,
				'language'  => $item->language,
				'post_id'   => $item->postId,
				'changes'   => array_keys( $item->changes ),
				'protected' => array_keys( $item->protectedFields ),
				'note'      => $item->note,
			];
		}

		return (string) wp_json_encode(
			[
				'applied'  => $result->applied,
				'ok'       => $result->ok(),
				'manifest' => $result->manifestPath,
				'counts'   => $result->plan->counts(),
				// Counts alone cannot answer "which pages are protected?", which
				// is the question anyone scripting against --json is asking.
				'items'    => $items,
				'errors'   => $result->errors,
				'problems' => $result->problems,
			],
			JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES
		);
	}
}
