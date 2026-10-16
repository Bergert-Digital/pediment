<?php

use Pediment\Seeder\Manifest;
use Pediment\Seeder\Meta;
use Pediment\Seeder\PlanItem;
use Pediment\Seeder\Runner;
use Pediment\Upgrade\SeedHashCleanup;

class SeedHashCleanupTest extends WP_UnitTestCase {

	public function set_up(): void {
		parent::set_up();
		delete_option( SeedHashCleanup::DONE_OPTION );
	}

	public function tear_down(): void {
		remove_all_filters( 'pediment_seed_manifest' );
		Manifest::resetCache();
		parent::tear_down();
	}

	/** A post the way a 3.x seed left it: identity plus the two arbitration hashes. */
	private function legacyPage(): int {
		$id = self::factory()->post->create( [ 'post_type' => 'page', 'post_title' => 'About' ] );
		update_post_meta( $id, Meta::KEY, 'about' );
		update_post_meta( $id, '_pediment_seed_hash', '1:' . str_repeat( 'a', 64 ) );
		update_post_meta( $id, '_pediment_seed_source', '1:' . str_repeat( 'b', 64 ) );
		return $id;
	}

	public function test_removes_the_three_keys_and_keeps_the_identity(): void {
		$page = $this->legacyPage();
		$nav  = self::factory()->post->create( [ 'post_type' => 'wp_navigation' ] );
		update_post_meta( $nav, Meta::KEY, 'primary' );
		update_post_meta( $nav, '_pediment_seed_mega_hash', '["1:abc"]' );

		SeedHashCleanup::run();

		foreach ( SeedHashCleanup::META_KEYS as $key ) {
			$this->assertSame( '', get_post_meta( $page, $key, true ), $key );
			$this->assertSame( '', get_post_meta( $nav, $key, true ), $key );
		}
		$this->assertSame( 'about', get_post_meta( $page, Meta::KEY, true ) );
		$this->assertSame( 'primary', get_post_meta( $nav, Meta::KEY, true ) );
		$this->assertSame( '1', get_option( SeedHashCleanup::DONE_OPTION ) );
	}

	public function test_does_not_touch_other_meta(): void {
		$page = $this->legacyPage();
		update_post_meta( $page, '_wp_page_template', 'page-wide' );

		SeedHashCleanup::run();

		$this->assertSame( 'page-wide', get_post_meta( $page, '_wp_page_template', true ) );
	}

	public function test_a_finished_cleanup_does_not_run_again(): void {
		update_option( SeedHashCleanup::DONE_OPTION, '1', true );
		$page = $this->legacyPage();

		SeedHashCleanup::maybeRun();

		$this->assertNotSame( '', get_post_meta( $page, '_pediment_seed_hash', true ), 'the flag short-circuits the routine' );
	}

	public function test_a_fresh_site_records_that_nothing_needs_cleaning(): void {
		SeedHashCleanup::maybeRun();

		$this->assertSame( '1', get_option( SeedHashCleanup::DONE_OPTION ) );
	}

	public function test_it_runs_on_admin_init(): void {
		$this->assertNotFalse( has_action( 'admin_init', array( SeedHashCleanup::class, 'maybeRun' ) ) );
	}

	public function test_a_v3_site_upgrades_without_any_content_change(): void {
		// A 3.x site: seeded, then a person rewrote the page, so its stored hash
		// no longer matches. 4.0 must not read the stale hashes at all — the
		// page stays as the person left it, before and after the cleanup.
		add_filter( 'pediment_seed_manifest', static fn() => [ 'pages' => [ 'about' => [ 'title' => 'About', 'content' => '<p>from git</p>' ] ] ] );
		Manifest::resetCache();
		$page = $this->legacyPage();
		wp_update_post( [ 'ID' => $page, 'post_content' => '<p>written live</p>' ] );
		$before = get_post( $page )->post_content;

		$beforeCleanup = ( new Runner() )->run();
		SeedHashCleanup::maybeRun();
		$afterCleanup = ( new Runner() )->run();

		foreach ( [ $beforeCleanup, $afterCleanup ] as $result ) {
			$this->assertTrue( $result->ok(), implode( "\n", array_merge( $result->errors, $result->problems ) ) );
			$this->assertTrue( $result->plan->isEmpty() );
			$this->assertSame( PlanItem::UNCHANGED, $result->plan->byKind( PlanItem::KIND_ENTRY )[0]->action );
		}
		$this->assertSame( $before, get_post( $page )->post_content );
		$this->assertSame( '', get_post_meta( $page, '_pediment_seed_hash', true ) );
	}
}
