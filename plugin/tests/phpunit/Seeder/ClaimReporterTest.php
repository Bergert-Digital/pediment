<?php
// plugin/tests/phpunit/Seeder/ClaimReporterTest.php

use Pediment\Seeder\ClaimResult;
use Pediment\Seeder\Plan;
use Pediment\Seeder\PlanItem;
use Pediment\Seeder\Reporter;

class ClaimReporterTest extends WP_UnitTestCase {

	public function test_a_dry_run_names_every_outcome_and_says_nothing_was_written() {
		$plan = new Plan(
			[
				new PlanItem( PlanItem::CLAIM, PlanItem::KIND_ENTRY, 'home', '', 12, [ 'seed_key' => [ 'from' => null, 'to' => 'home' ] ], 'page "home" (ID 12)' ),
				new PlanItem( PlanItem::NO_MATCH, PlanItem::KIND_ENTRY, 'about', '', 0, [], 'no unclaimed page with slug "about" — the next seed will create it.' ),
				new PlanItem( PlanItem::AMBIGUOUS, PlanItem::KIND_NAV, 'primary', '', 0, [], '2 unclaimed navigation entities (IDs 7, 9)' ),
			]
		);

		$text = Reporter::claimText( new ClaimResult( $plan, false, '/srv/theme/seed/manifest.php', dryRun: true ) );

		$this->assertStringContainsString( 'Pediment claim — dry run', $text );
		$this->assertStringContainsString( 'manifest: /srv/theme/seed/manifest.php', $text );
		// Both per-kind headings actually rendered, not just implied by the summary line.
		$this->assertStringContainsString( 'PAGES & POSTS', $text );
		$this->assertStringContainsString( 'NAV', $text );
		// The entry item's own line — key and note, not just its action word.
		$this->assertStringContainsString( 'page "home" (ID 12)', $text );
		$this->assertStringContainsString( 'no-match', $text );
		// The nav item's note, distinguishing its line from a garbled or dropped one.
		$this->assertStringContainsString( 'IDs 7, 9', $text );
		$this->assertStringContainsString( '1 to claim, 1 without a match, 1 ambiguous.', $text );
		$this->assertStringContainsString( 'Nothing was written (--dry-run).', $text );
	}

	public function test_a_language_specific_item_shows_its_language_code_after_the_key() {
		$plan = new Plan(
			[
				new PlanItem( PlanItem::CLAIM, PlanItem::KIND_ENTRY, 'home', 'fr', 14, [ 'seed_key' => [ 'from' => null, 'to' => 'home' ] ], 'page "home" (ID 14)' ),
			]
		);

		$text = Reporter::claimText( new ClaimResult( $plan, true, '' ) );

		$this->assertStringContainsString( 'home (fr)', $text );
	}

	public function test_an_applied_run_does_not_claim_to_be_a_dry_run() {
		$text = Reporter::claimText( new ClaimResult( new Plan( [] ), true, '/srv/theme/seed/manifest.php' ) );

		$this->assertStringContainsString( 'Pediment claim', $text );
		$this->assertStringNotContainsString( 'dry run', $text );
		$this->assertStringNotContainsString( '--dry-run', $text );
		$this->assertStringContainsString( '0 to claim, 0 without a match, 0 ambiguous.', $text );
	}

	/**
	 * A real claim that stops before it writes — no manifest, a malformed one,
	 * a language mismatch — applies nothing, but it was not a dry run. The
	 * operator pressed "apply"; the report must not say they did not.
	 */
	public function test_a_real_run_that_wrote_nothing_is_not_called_a_dry_run() {
		$text = Reporter::claimText( new ClaimResult( new Plan( [] ), false, '', [ 'No seed manifest found.' ] ) );

		$this->assertStringStartsWith( "Pediment claim\n", $text );
		$this->assertStringNotContainsString( 'dry run', $text );
		$this->assertStringNotContainsString( '--dry-run', $text );
		$this->assertStringContainsString( '0 to claim, 0 without a match, 0 ambiguous. Nothing was written.', $text );
	}

	public function test_errors_are_printed_under_their_own_heading() {
		$text = Reporter::claimText( new ClaimResult( new Plan( [] ), true, '', [ 'about|: post 12 already carries a seed key' ] ) );

		$this->assertStringContainsString( 'ERRORS', $text );
		$this->assertStringContainsString( 'post 12 already carries a seed key', $text );
	}

	public function test_media_items_are_reported_under_their_own_heading() {
		$plan = new Plan(
			[
				new PlanItem( PlanItem::CLAIM, PlanItem::KIND_MEDIA, 'hero', '', 21, [ 'seed_key' => [ 'from' => null, 'to' => 'hero' ] ], 'attachment "hero.jpg" (ID 21)' ),
				new PlanItem( PlanItem::AMBIGUOUS, PlanItem::KIND_MEDIA, 'logo', '', 0, [], '2 unclaimed attachments share the file name "logo.svg" (IDs 4, 9)' ),
			]
		);

		$text = Reporter::claimText( new ClaimResult( $plan, false, '/srv/theme/seed/manifest.php' ) );

		$this->assertStringContainsString( "MEDIA\n", $text );
		$this->assertMatchesRegularExpression( '/^  claim\s+hero\s+attachment "hero.jpg" \(ID 21\)$/m', $text );
		$this->assertStringContainsString( 'IDs 4, 9', $text );
		$this->assertStringContainsString( '1 to claim, 0 without a match, 1 ambiguous.', $text );
	}
}
