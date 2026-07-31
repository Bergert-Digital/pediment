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
					PlanItem::UPDATE,
					PlanItem::KIND_ENTRY,
					'contact',
					'',
					9,
					[ 'slug' => [ 'from' => 'kontakt', 'to' => 'contact' ] ],
					[ 'content' => [ 'from' => '(database)', 'to' => '(manifest)' ] ],
					'edited in the editor — content and title left alone'
				),
				new PlanItem( PlanItem::UNCHANGED, PlanItem::KIND_ENTRY, 'guide', '', 11 ),
				new PlanItem( PlanItem::ORPHAN, PlanItem::KIND_ENTRY, 'legacy', '', 42, [], [], '"Legacy offer" (ID 42) — left in place' ),
			]
		);
		return new RunResult( $plan, $applied, '/themes/acme/seed/manifest.php' );
	}

	public function test_every_action_appears_in_the_report() {
		$text = Reporter::text( $this->result() );

		$this->assertStringContainsString( '/themes/acme/seed/manifest.php', $text );
		$this->assertStringContainsString( 'create', $text );
		$this->assertStringContainsString( 'hero-bg.jpg', $text );
		$this->assertStringContainsString( 'kontakt', $text );
		$this->assertStringContainsString( 'unchanged', $text );
		$this->assertStringContainsString( 'orphan', $text );
	}

	public function test_protected_fields_are_called_out_under_their_entry() {
		$this->assertStringContainsString( 'protected: content', Reporter::text( $this->result() ) );
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

	public function test_summary_line_counts_writes_protections_and_orphans() {
		// 2 creates (media hero-bg + page home) + 1 update (contact) = 3 writes.
		$this->assertSame( '3 to write, 1 protected, 1 orphan, 1 unchanged.', Reporter::summaryLine( $this->result( true ) ) );
	}
}
