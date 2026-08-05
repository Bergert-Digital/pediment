<?php
// plugin/tests/phpunit/Seeder/ClaimReporterTest.php

use Pediment\Seeder\Plan;
use Pediment\Seeder\PlanItem;
use Pediment\Seeder\Reporter;

class ClaimReporterTest extends WP_UnitTestCase {

	public function test_a_dry_run_names_every_outcome_and_says_nothing_was_written() {
		$plan = new Plan(
			[
				new PlanItem( PlanItem::CLAIM, PlanItem::KIND_ENTRY, 'home', '', 12, [ 'seed_key' => [ 'from' => null, 'to' => 'home' ] ], [], 'page "home" (ID 12)' ),
				new PlanItem( PlanItem::NO_MATCH, PlanItem::KIND_ENTRY, 'about', '', 0, [], [], 'no unclaimed page with slug "about" — the next seed will create it.' ),
				new PlanItem( PlanItem::AMBIGUOUS, PlanItem::KIND_NAV, 'primary', '', 0, [], [], '2 unclaimed navigation entities (IDs 7, 9)' ),
			]
		);

		$text = Reporter::claimText( $plan, false, '/srv/theme/seed/manifest.php' );

		$this->assertStringContainsString( 'Pediment claim — dry run', $text );
		$this->assertStringContainsString( 'manifest: /srv/theme/seed/manifest.php', $text );
		$this->assertStringContainsString( 'claim', $text );
		$this->assertStringContainsString( 'no-match', $text );
		$this->assertStringContainsString( 'ambiguous', $text );
		$this->assertStringContainsString( '1 to claim, 1 without a match, 1 ambiguous.', $text );
		$this->assertStringContainsString( 'Nothing was written (--dry-run).', $text );
	}

	public function test_an_applied_run_does_not_claim_to_be_a_dry_run() {
		$text = Reporter::claimText( new Plan( [] ), true, '/srv/theme/seed/manifest.php' );

		$this->assertStringContainsString( 'Pediment claim', $text );
		$this->assertStringNotContainsString( 'dry run', $text );
		$this->assertStringNotContainsString( '--dry-run', $text );
		$this->assertStringContainsString( '0 to claim, 0 without a match, 0 ambiguous.', $text );
	}

	public function test_errors_are_printed_under_their_own_heading() {
		$text = Reporter::claimText( new Plan( [] ), true, '', [ 'about|: post 12 already carries a seed key' ] );

		$this->assertStringContainsString( 'ERRORS', $text );
		$this->assertStringContainsString( 'post 12 already carries a seed key', $text );
	}
}
