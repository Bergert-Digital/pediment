<?php
// plugin/tests/phpunit/Seeder/ReporterTest.php

use Pediment\Seeder\Plan;
use Pediment\Seeder\PlanItem;
use Pediment\Seeder\Reporter;
use Pediment\Seeder\RunResult;

class ReporterTest extends WP_UnitTestCase {

	private function result( bool $applied = false ): RunResult {
		$plan = new Plan(
			[
				new PlanItem( PlanItem::CREATE, PlanItem::KIND_MEDIA, 'hero-bg', '', 0, [ 'file' => [ 'from' => null, 'to' => 'hero-bg.jpg' ] ] ),
				new PlanItem( PlanItem::CREATE, PlanItem::KIND_ENTRY, 'home', '', 0, [ 'slug' => [ 'from' => null, 'to' => 'home' ] ] ),
				new PlanItem(
					PlanItem::UNCHANGED,
					PlanItem::KIND_ENTRY,
					'contact',
					'',
					9,
					[],
					'in the trash — left alone; restore it in the editor, or empty the trash and the next seed creates it again'
				),
				new PlanItem( PlanItem::UNCHANGED, PlanItem::KIND_ENTRY, 'guide', '', 11 ),
				new PlanItem( PlanItem::ORPHAN, PlanItem::KIND_ENTRY, 'legacy', '', 42, [], '"Legacy offer" (ID 42) — left in place' ),
			]
		);
		return new RunResult( $plan, $applied, '/themes/acme/seed/manifest.php' );
	}

	public function test_every_action_appears_in_the_report() {
		$text = Reporter::text( $this->result() );

		$this->assertStringContainsString( '/themes/acme/seed/manifest.php', $text );
		$this->assertStringContainsString( 'create', $text );
		$this->assertStringContainsString( 'hero-bg.jpg', $text );
		$this->assertStringContainsString( 'in the trash', $text );
		$this->assertStringContainsString( 'unchanged', $text );
		$this->assertStringContainsString( 'orphan', $text );
	}

	public function test_the_report_has_no_protected_wording_left() {
		$this->assertStringNotContainsString( 'protected', Reporter::text( $this->result() ) );
	}

	public function test_a_dry_run_says_nothing_was_written() {
		$this->assertStringContainsString( 'Nothing was written', Reporter::text( $this->result( false ) ) );
		$this->assertStringNotContainsString( 'Nothing was written', Reporter::text( $this->result( true ) ) );
	}

	public function test_errors_and_problems_are_never_buried() {
		$result = new RunResult( new Plan(), false, '', [ 'duplicate key "home"' ], [ 'home: slug is "home-2"' ] );

		$text = Reporter::text( $result );

		$this->assertStringContainsString( 'ERRORS', $text );
		$this->assertStringContainsString( 'duplicate key "home"', $text );
		$this->assertStringContainsString( 'VERIFICATION', $text );
		$this->assertStringContainsString( 'home-2', $text );
	}

	public function test_summary_line_counts_writes_orphans_and_unchanged() {
		// 2 creates (media hero-bg + page home) = 2 writes.
		$this->assertSame( '2 to write, 1 orphan, 2 unchanged.', Reporter::summaryLine( $this->result( true ) ) );
	}

	public function test_the_summary_keeps_the_seed_check_contract() {
		// .github/actions/seed-check greps for this exact prefix, comma included.
		$this->assertMatchesRegularExpression( '/(^|\s)0 to write,/', Reporter::summaryLine( new RunResult( new Plan(), false ) ) );
	}

	public function test_a_partial_apply_does_not_claim_nothing_was_applied() {
		$result = new RunResult( new Plan(), true, '', [ 'media.logo: could not copy' ] );

		$text = Reporter::text( $result );

		$this->assertStringContainsString( 'the run continued', $text );
		$this->assertStringNotContainsString( 'nothing was applied', $text );
	}

	public function test_notices_print_without_failing_the_run() {
		$result = new RunResult( new Plan(), true, '/x/manifest.php', [], [], [], [ 'home (de): no pattern `x/home-de` is registered — the German page carries the default language content.' ] );

		$text = Reporter::text( $result );

		$this->assertStringContainsString( 'TRANSLATIONS', $text );
		$this->assertStringContainsString( 'x/home-de', $text );
		$this->assertTrue( $result->ok(), 'A missing translation must not fail the run.' );
	}

	public function test_menu_notices_print_in_their_own_section_without_failing_the_run() {
		$result = new RunResult( new Plan(), true, '/x/manifest.php', [], [], [], [], [ 'menu footer|: about is in the trash, left out' ] );

		$text = Reporter::text( $result );

		$this->assertStringContainsString( "MENUS\n  - menu footer|: about is in the trash, left out", $text );
		$this->assertStringNotContainsString( 'TRANSLATIONS', $text );
		$this->assertTrue( $result->ok() );
	}
}
