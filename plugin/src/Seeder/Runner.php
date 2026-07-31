<?php
/**
 * The seeding engine's entry point. One code path for WP-CLI and wp-admin.
 *
 * Five phases, always in this order (spec §4.2):
 *   1. resolve desired state   2. resolve actual state   3. diff into a plan
 *   4. apply                   5. verify and fail loudly
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

final class Runner {
	private LanguageProvider $lang;

	public function __construct( ?LanguageProvider $lang = null ) {
		$this->lang = $lang ?? LanguageRegistry::provider();
	}

	/** @param array{dry_run?:bool} $options */
	public function run( array $options = [] ): RunResult {
		$dryRun = ! empty( $options['dry_run'] );

		// `init` already populated the per-request memo (PostTypes reads it on
		// every request), and an operator who just edited the manifest expects
		// this run to see the file as it is now.
		Manifest::resetCache();

		try {
			$manifest = Manifest::load();
		} catch ( ManifestError $e ) {
			return new RunResult( new Plan(), false, '', [ $e->getMessage() ] );
		}

		if ( null === $manifest ) {
			return new RunResult(
				new Plan(),
				false,
				'',
				[ sprintf( 'No seed manifest found. Create %s/%s in the active theme.', get_stylesheet(), Manifest::RELATIVE_PATH ) ]
			);
		}

		$mediaSeeder = new MediaSeeder();
		$navSeeder   = new NavSeeder( $this->lang );

		// Media first: page content references attachments by key, so the map
		// has to exist before content is resolved.
		$mediaPlan   = $mediaSeeder->plan( $manifest );
		$mediaMap    = $dryRun ? $mediaSeeder->map( $manifest ) : $mediaSeeder->apply( $mediaPlan, $manifest );
		$mediaErrors = $dryRun ? [] : $mediaSeeder->errors();

		try {
			// Phase 1.
			$desired = ( new DesiredState( $this->lang, new ContentResolver( $mediaMap ) ) )->build( $manifest );
		} catch ( ManifestError $e ) {
			return new RunResult( $mediaPlan, false, $manifest->path(), [ $e->getMessage() ] );
		}

		// Phase 2.
		$reader = new StateReader( $this->lang );

		// Phase 3.
		$entryPlan = ( new Differ() )->diff( $desired, $reader->read(), $reader->duplicates() );

		$entryIds = [];
		foreach ( $entryPlan->byKind( PlanItem::KIND_ENTRY ) as $item ) {
			if ( $item->postId > 0 && PlanItem::ORPHAN !== $item->action ) {
				$entryIds[ $item->mapKey() ] = $item->postId;
			}
		}
		$navPlan = $navSeeder->plan( $manifest, $entryIds );
		$plan    = Plan::merge( $mediaPlan, $entryPlan, $navPlan );

		if ( $dryRun || $plan->hasErrors() ) {
			return new RunResult( $plan, false, $manifest->path(), array_merge( $plan->errors(), $mediaErrors ), [], $entryIds );
		}

		// Phase 4.
		$applied = ( new Applier( $this->lang ) )->apply( $entryPlan, $desired );
		if ( [] !== $applied->errors || [] !== $mediaErrors ) {
			return new RunResult( $plan, true, $manifest->path(), array_merge( $mediaErrors, $applied->errors ), [], $applied->ids );
		}

		// Nav links need the resolved page IDs, so nav is re-planned against them.
		$navSeeder->apply( $navSeeder->plan( $manifest, $applied->ids ), $manifest, $applied->ids );
		$navErrors = $navSeeder->errors();
		if ( [] !== $navErrors ) {
			return new RunResult( $plan, true, $manifest->path(), $navErrors, [], $applied->ids );
		}

		// Phase 5.
		$problems = ( new Verifier( $this->lang ) )->verify( $manifest, $desired, $applied->ids, $mediaMap );

		// Once, at the end, after every post type is registered. Soft flush: a
		// hard flush rewrites .htaccess, and this engine never touches the
		// permalink structure (see plugin/inc/bootstrap.php and pediment#47).
		if ( ! $plan->isEmpty() ) {
			flush_rewrite_rules( false );
		}

		return new RunResult( $plan, true, $manifest->path(), [], $problems, $applied->ids );
	}
}
