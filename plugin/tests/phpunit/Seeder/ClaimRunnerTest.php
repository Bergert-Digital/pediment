<?php
// plugin/tests/phpunit/Seeder/ClaimRunnerTest.php

use Pediment\Seeder\ClaimRunner;
use Pediment\Seeder\Meta;
use Pediment\Seeder\PlanItem;

class ClaimRunnerTest extends WP_UnitTestCase {

	public function set_up(): void {
		parent::set_up();
		add_filter(
			'pediment_seed_manifest',
			static fn() => [ 'pages' => [ 'home' => [ 'title' => 'Home', 'content' => '<p>hi</p>' ] ] ]
		);
	}

	public function tear_down(): void {
		remove_all_filters( 'pediment_seed_manifest' );
		\Pediment\Seeder\Manifest::resetCache();
		parent::tear_down();
	}

	private function legacyHome(): int {
		return self::factory()->post->create(
			[ 'post_type' => 'page', 'post_name' => 'home', 'post_title' => 'Legacy home', 'post_status' => 'publish' ]
		);
	}

	public function test_a_dry_run_plans_but_writes_no_meta() {
		$id = $this->legacyHome();

		$result = ( new ClaimRunner() )->run( [ 'dry_run' => true ] );

		$this->assertFalse( $result->applied );
		$claims = array_filter(
			$result->plan->byKind( PlanItem::KIND_ENTRY ),
			static fn( PlanItem $item ) => PlanItem::CLAIM === $item->action
		);
		$this->assertCount( 1, $claims );
		$this->assertSame( '', get_post_meta( $id, Meta::KEY, true ) );
	}

	public function test_an_applied_run_writes_the_key() {
		$id = $this->legacyHome();

		$result = ( new ClaimRunner() )->run();

		$this->assertTrue( $result->applied );
		$this->assertSame( [], $result->errors );
		$this->assertSame( 'home', get_post_meta( $id, Meta::KEY, true ) );
		$this->assertSame( '', get_post_meta( $id, Meta::HASH, true ) );
	}

	public function test_a_missing_manifest_returns_a_result_carrying_the_error() {
		// Force "no manifest" regardless of whatever the active theme ships,
		// the same way RunnerTest's siblings override the filter rather than
		// relying on there being no manifest file on disk.
		remove_all_filters( 'pediment_seed_manifest' );
		add_filter( 'pediment_seed_manifest', static fn() => null );
		\Pediment\Seeder\Manifest::resetCache();

		$result = ( new ClaimRunner() )->run();

		$this->assertFalse( $result->applied );
		$this->assertSame( '', $result->manifestPath );
		$this->assertNotEmpty( $result->errors );
		$this->assertStringContainsString( 'No seed manifest found', $result->errors[0] );
		$this->assertTrue( $result->plan->isEmpty() );
	}
}
