<?php
/**
 * The claim engine's entry point. One code path for WP-CLI and wp-admin.
 *
 * Backfills seed identity onto content that predates the seeding engine
 * (see Claimer's docblock for why that is a separate, one-time step from a
 * seed run). This class is the glue Runner already provides for seeding:
 * reset the manifest cache, load it, plan, apply unless dry-run.
 *
 * @package Pediment
 */

declare(strict_types=1);

namespace Pediment\Seeder;

use Pediment\Language\LanguageProvider;
use Pediment\Language\LanguageRegistry;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class ClaimRunner {
	private LanguageProvider $lang;

	public function __construct( ?LanguageProvider $lang = null ) {
		$this->lang = $lang ?? LanguageRegistry::provider();
	}

	/** @param array{dry_run?:bool} $options */
	public function run( array $options = [] ): ClaimResult {
		$dryRun = ! empty( $options['dry_run'] );

		// Same reasoning as Runner::run(): an operator who just edited the
		// manifest expects this run to see the file as it is now.
		Manifest::resetCache();

		// Mirrors Runner::run(). On admin-only hosting the Seeding tab's claim
		// buttons are the only door there is, and a hand-edited manifest is
		// exactly what a migration produces — an uncaught ManifestError there
		// is "There has been a critical error on this website" instead of a
		// report naming the line that is wrong.
		try {
			$manifest = Manifest::load();
		} catch ( ManifestError $e ) {
			return new ClaimResult( new Plan(), false, '', [ $e->getMessage() ] );
		}

		if ( null === $manifest ) {
			return new ClaimResult(
				new Plan(),
				false,
				'',
				[ sprintf( 'No seed manifest found. Create %s/%s in the active theme.', get_stylesheet(), Manifest::RELATIVE_PATH ) ]
			);
		}

		// The same gate Runner::run() applies, and for a sharper reason than
		// seeding's. A claim run before `wp pediment languages` has configured
		// the manifest's languages sees NullProvider — one empty language — so
		// it keys only the default-slug rows and reports every other row as
		// no-match. The operator then configures languages and seeds, and each
		// unclaimed live page trips Differ rule 1 and is duplicated. Claiming
		// is a one-shot migration step, so getting it wrong is not something a
		// re-run repairs.
		$mismatch = LanguageGate::mismatch( $manifest, $this->lang );
		if ( null !== $mismatch ) {
			return new ClaimResult( new Plan(), false, $manifest->path(), [ $mismatch ] );
		}

		$claimer = new Claimer( $this->lang );
		$plan    = $claimer->plan( $manifest, ( new StateReader( $this->lang ) )->read() );
		$errors  = [];

		if ( ! $dryRun ) {
			$result = $claimer->apply( $plan );
			$errors = $result['errors'];
		}

		return new ClaimResult( $plan, ! $dryRun, $manifest->path(), $errors );
	}
}
