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

		// Unlike Runner, a thrown ManifestError is not caught here — that
		// matches this path's behaviour before the two front doors were
		// unified, and nothing in this refactor's brief asks for it to change.
		$manifest = Manifest::load();

		if ( null === $manifest ) {
			return new ClaimResult(
				new Plan(),
				false,
				'',
				[ sprintf( 'No seed manifest found. Create %s/%s in the active theme.', get_stylesheet(), Manifest::RELATIVE_PATH ) ]
			);
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
