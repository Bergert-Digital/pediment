<?php
// plugin/tests/phpunit/Seeder/RunnerTest.php

use Pediment\Seeder\Meta;
use Pediment\Seeder\PlanItem;
use Pediment\Seeder\Runner;

class RunnerTest extends WP_UnitTestCase {

	private array $manifest;

	public function set_up(): void {
		parent::set_up();
		$this->manifest = [
			'pages' => [
				'home'  => [ 'title' => 'Home', 'content' => '<p>one</p>', 'front_page' => true ],
				'about' => [ 'title' => 'About', 'content' => '<p>about</p>' ],
			],
			'navs'  => [ 'primary' => [ 'title' => 'Primary', 'items' => [ [ 'entry' => 'about' ] ] ] ],
		];
		add_filter( 'pediment_seed_manifest', fn() => $this->manifest );
	}

	public function tear_down(): void {
		remove_all_filters( 'pediment_seed_manifest' );
		\Pediment\Seeder\Manifest::resetCache();
		parent::tear_down();
	}

	public function test_dry_run_writes_nothing() {
		$result = ( new Runner() )->run( [ 'dry_run' => true ] );

		$this->assertFalse( $result->applied );
		$this->assertCount( 2, $result->plan->byKind( PlanItem::KIND_ENTRY ) );
		$this->assertSame( [], get_posts( [ 'post_type' => 'page', 'fields' => 'ids' ] ) );
	}

	public function test_a_full_run_creates_verifies_and_reports_ok() {
		$result = ( new Runner() )->run();

		$this->assertTrue( $result->ok(), implode( "\n", array_merge( $result->errors, $result->problems ) ) );
		$this->assertTrue( $result->applied );
		$this->assertSame( 'Home', get_post( $result->ids['home|'] )->post_title );
	}

	public function test_a_second_run_is_a_no_op() {
		( new Runner() )->run();

		$second = ( new Runner() )->run();

		$this->assertTrue( $second->plan->isEmpty(), 'a re-seed with no manifest change must plan no writes' );
		$this->assertTrue( $second->ok() );
	}

	public function test_a_client_edit_survives_a_content_release() {
		$first = ( new Runner() )->run();
		$id    = $first->ids['home|'];
		wp_update_post( [ 'ID' => $id, 'post_content' => '<p>client copy</p>' ] );

		$this->manifest['pages']['home']['content'] = '<p>two</p>';
		$result                                     = ( new Runner() )->run();

		$this->assertSame( '<p>client copy</p>', get_post( $id )->post_content );
		$this->assertNotEmpty( $result->plan->byAction( PlanItem::PROTECTED ) );
		$this->assertTrue( $result->ok(), 'protecting content is a success, not a failure' );
	}

	public function test_a_duplicate_seed_key_aborts_before_any_write() {
		$first = ( new Runner() )->run();
		$clone = self::factory()->post->create( [ 'post_type' => 'page', 'post_title' => 'Impostor' ] );
		update_post_meta( $clone, Meta::KEY, 'home' );

		$this->manifest['pages']['home']['content'] = '<p>two</p>';
		$result                                     = ( new Runner() )->run();

		$this->assertFalse( $result->ok() );
		$this->assertFalse( $result->applied );
		$this->assertStringContainsString( 'home', $result->errors[0] );
		$this->assertSame( '<p>one</p>', get_post( $first->ids['home|'] )->post_content );
	}

	public function test_a_manifest_error_is_reported_not_thrown() {
		$this->manifest['pages']['broken'] = [ 'content' => '' ]; // no title

		$result = ( new Runner() )->run();

		$this->assertFalse( $result->ok() );
		$this->assertStringContainsString( 'title', $result->errors[0] );
	}

	public function test_no_manifest_reports_a_clear_error() {
		remove_all_filters( 'pediment_seed_manifest' );

		$result = ( new Runner() )->run();

		$this->assertFalse( $result->ok() );
		$this->assertStringContainsString( 'seed/manifest.php', $result->errors[0] );
	}

	public function test_navigation_is_seeded_after_pages_so_links_resolve() {
		$result = ( new Runner() )->run();

		$navs = get_posts( [ 'post_type' => 'wp_navigation', 'posts_per_page' => -1 ] );
		$this->assertCount( 1, $navs );
		$this->assertStringContainsString( '"id":' . $result->ids['about|'], $navs[0]->post_content );
	}
}
