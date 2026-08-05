<?php
// plugin/tests/phpunit/Cli/ClaimCommandTest.php

use Pediment\Cli\ClaimCommand;
use Pediment\Seeder\Plan;
use Pediment\Seeder\PlanItem;

class ClaimCommandTest extends WP_UnitTestCase {

	public function test_render_prints_the_claim_report() {
		$plan = new Plan(
			[ new PlanItem( PlanItem::CLAIM, PlanItem::KIND_ENTRY, 'home', '', 12, [ 'seed_key' => [ 'from' => null, 'to' => 'home' ] ], [], 'page "home" (ID 12)' ) ]
		);

		$out = ClaimCommand::render( $plan, false, '/srv/theme/seed/manifest.php', [] );

		$this->assertStringContainsString( 'Pediment claim — dry run', $out );
		$this->assertStringContainsString( '1 to claim, 0 without a match, 0 ambiguous.', $out );
	}

	/**
	 * The brief's single case always passes an empty $errors array, so a
	 * render() that silently dropped the errors argument (e.g. hardcoding
	 * Reporter::claimText()'s fourth parameter to []) would still pass it.
	 * The __invoke() method decides whether to call WP_CLI::error() based on
	 * this exact array, so its presence in the rendered text is what a human
	 * operator — and this test — relies on to notice a partially-applied claim.
	 */
	public function test_render_includes_errors_when_present() {
		$out = ClaimCommand::render( new Plan(), true, '/srv/theme/seed/manifest.php', [ 'home|: post 12 already carries a seed key — nothing was written for this entry.' ] );

		$this->assertStringContainsString( 'ERRORS', $out );
		$this->assertStringContainsString( 'home|: post 12 already carries a seed key', $out );
	}
}
