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
		$mediaPlan   = $mediaSeeder->plan( $manifest );
		$reader      = new StateReader( $this->lang );

		// Phases 1-3 are planned against the media that exists NOW, so the whole
		// plan — media, entries and navs — is known before anything is written.
		// Applying media first would mean an errored plan still left attachments
		// and a changed site logo behind while reporting "nothing was applied".
		$previewState = new DesiredState( $this->lang, new ContentResolver( $mediaSeeder->map( $manifest ) ) );
		try {
			$preview = $previewState->build( $manifest );
		} catch ( ManifestError $e ) {
			return new RunResult( $mediaPlan, false, $manifest->path(), [ $e->getMessage() ] );
		}

		$entryPlan = ( new Differ() )->diff( $preview, $reader->read(), $reader->duplicates() );
		$entryIds  = $this->resolvedIds( $entryPlan );
		$plan      = Plan::merge( $mediaPlan, $entryPlan, $navSeeder->plan( $manifest, $entryIds ) );

		if ( $dryRun || $plan->hasErrors() ) {
			return new RunResult( $plan, false, $manifest->path(), $plan->errors(), $this->undeclaredMediaProblems( $previewState ), $entryIds );
		}

		// Phase 4. Media goes first here, because page content references
		// attachments by key and the map has to be real before content is
		// resolved for the write.
		$mediaMap    = $mediaSeeder->apply( $mediaPlan, $manifest );
		$mediaErrors = $mediaSeeder->errors();

		$state = new DesiredState( $this->lang, new ContentResolver( $mediaMap ) );
		try {
			$desired = $state->build( $manifest );
		} catch ( ManifestError $e ) {
			return new RunResult( $plan, true, $manifest->path(), array_merge( $mediaErrors, [ $e->getMessage() ] ) );
		}

		$entryPlan = ( new Differ() )->diff( $desired, $reader->read(), $reader->duplicates() );
		$applied   = ( new Applier( $this->lang ) )->apply( $entryPlan, $desired );

		// Nav links need the resolved page IDs, so nav is planned again against them.
		$navPlan = $navSeeder->plan( $manifest, $applied->ids );
		$navIds  = $navSeeder->apply( $navPlan, $manifest, $applied->ids );

		// Report the plan that actually ran, not the preview.
		$plan = Plan::merge( $mediaPlan, $entryPlan, $navPlan );

		// Once, at the end, after every post type is registered. Soft flush: a
		// hard flush rewrites .htaccess, and this engine never touches the
		// permalink structure (see plugin/inc/bootstrap.php and pediment#47).
		// It runs on a partial failure too — writes that landed still need their
		// rules — and when a manifest post type has no rules yet, which no plan
		// item can express because post types produce none.
		if ( ! $plan->isEmpty() || $this->postTypeRulesMissing( $manifest ) ) {
			flush_rewrite_rules( false );
		}

		// Phase 5 runs even when something failed: an operator debugging a
		// partial apply needs to know what actually landed.
		$problems = array_merge(
			( new Verifier( $this->lang, $navSeeder ) )->verify( $manifest, $desired, $applied->ids, $mediaMap, $navIds ),
			$this->undeclaredMediaProblems( $state )
		);
		$errors   = array_values( array_unique( array_merge( $mediaErrors, $applied->errors, $navSeeder->errors() ) ) );

		return new RunResult( $plan, true, $manifest->path(), $errors, $problems, $applied->ids );
	}

	/**
	 * A pattern referencing a media key nobody declared resolves to a literal
	 * sentinel (or a bare `0`) that gets written into a live page and hashed as
	 * if it were correct. Nothing else in the engine notices: the media plan has
	 * no such key and the Verifier only walks declared media.
	 *
	 * @return string[]
	 */
	private function undeclaredMediaProblems( DesiredState $state ): array {
		$problems = [];
		foreach ( $state->undeclaredMediaKeys() as $mapKey => $keys ) {
			foreach ( $keys as $key ) {
				$problems[] = sprintf(
					'%s: references media key "%s", which the manifest does not declare — the placeholder was written out unresolved.',
					$mapKey,
					$key
				);
			}
		}
		return $problems;
	}

	/** @return array<string,int> mapKey => post ID for entries that already exist */
	private function resolvedIds( Plan $entryPlan ): array {
		$ids = [];
		foreach ( $entryPlan->byKind( PlanItem::KIND_ENTRY ) as $item ) {
			if ( $item->postId > 0 && PlanItem::ORPHAN !== $item->action ) {
				$ids[ $item->mapKey() ] = $item->postId;
			}
		}
		return $ids;
	}

	/**
	 * Whether any manifest post type has no rewrite rules yet.
	 *
	 * Post types produce no plan items, so a manifest that adds a CPT and
	 * nothing else would otherwise never flush, and its permalinks would 404
	 * until someone re-saved Settings > Permalinks.
	 */
	private function postTypeRulesMissing( Manifest $manifest ): bool {
		$postTypes = $manifest->postTypes();
		if ( [] === $postTypes ) {
			return false;
		}

		$rules = implode( "\n", array_values( (array) get_option( 'rewrite_rules', [] ) ) );
		foreach ( $postTypes as $spec ) {
			if ( ! str_contains( $rules, 'post_type=' . $spec->slug ) ) {
				return true;
			}
		}
		return false;
	}
}
