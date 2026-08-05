<?php
// plugin/tests/phpunit/Seeder/ClaimerTest.php

use Pediment\Language\NullProvider;
use Pediment\Seeder\Claimer;
use Pediment\Seeder\Manifest;
use Pediment\Seeder\Meta;
use Pediment\Seeder\PlanItem;

class ClaimerTest extends WP_UnitTestCase {

	private function manifest( array $pages ): Manifest {
		return Manifest::fromArray( [ 'pages' => $pages ], '/tmp/theme' );
	}

	private function page( string $slug, array $args = [] ): int {
		return self::factory()->post->create(
			array_merge(
				[ 'post_type' => 'page', 'post_title' => 'Legacy', 'post_name' => $slug, 'post_status' => 'publish' ],
				$args
			)
		);
	}

	/** @return array<string,PlanItem> mapKey => item */
	private function byMapKey( \Pediment\Seeder\Plan $plan ): array {
		$out = [];
		foreach ( $plan->items() as $item ) {
			$out[ $item->mapKey() ] = $item;
		}
		return $out;
	}

	public function test_an_unkeyed_page_matching_slug_and_type_is_claimed() {
		$id       = $this->page( 'about' );
		$manifest = $this->manifest( [ 'about' => [ 'title' => 'About', 'content' => '<p>a</p>' ] ] );

		$plan = ( new Claimer( new NullProvider() ) )->plan( $manifest, [] );

		$item = $this->byMapKey( $plan )['about|'];
		$this->assertSame( PlanItem::CLAIM, $item->action );
		$this->assertSame( $id, $item->postId );
		$this->assertTrue( $item->writes() );
	}

	public function test_a_page_carrying_another_seed_key_is_never_taken() {
		$id = $this->page( 'about' );
		update_post_meta( $id, Meta::KEY, 'legacy-about' );
		$manifest = $this->manifest( [ 'about' => [ 'title' => 'About', 'content' => '<p>a</p>' ] ] );

		$plan = ( new Claimer( new NullProvider() ) )->plan( $manifest, [] );

		$item = $this->byMapKey( $plan )['about|'];
		$this->assertSame( PlanItem::NO_MATCH, $item->action );
		$this->assertSame( 0, $item->postId );
	}

	public function test_a_trashed_page_is_never_claimed() {
		$this->page( 'about', [ 'post_status' => 'trash' ] );
		$manifest = $this->manifest( [ 'about' => [ 'title' => 'About', 'content' => '<p>a</p>' ] ] );

		$plan = ( new Claimer( new NullProvider() ) )->plan( $manifest, [] );

		$this->assertSame( PlanItem::NO_MATCH, $this->byMapKey( $plan )['about|']->action );
	}

	public function test_two_candidates_are_reported_and_nothing_is_planned() {
		$first  = $this->page( 'about', [ 'post_type' => 'page' ] );
		$second = self::factory()->post->create(
			[ 'post_type' => 'page', 'post_name' => 'about', 'post_status' => 'draft', 'post_title' => 'About draft' ]
		);
		$manifest = $this->manifest( [ 'about' => [ 'title' => 'About', 'content' => '<p>a</p>' ] ] );

		$item = $this->byMapKey( ( new Claimer( new NullProvider() ) )->plan( $manifest, [] ) )['about|'];

		$this->assertSame( PlanItem::AMBIGUOUS, $item->action );
		$this->assertFalse( $item->writes() );
		$this->assertStringContainsString( (string) $first, $item->note );
		$this->assertStringContainsString( (string) $second, $item->note );
	}

	public function test_a_child_is_matched_under_its_claimed_parent() {
		$parent = $this->page( 'guide' );
		$right  = $this->page( 'faq', [ 'post_parent' => $parent ] );
		$wrong  = $this->page( 'faq' ); // Same slug, top level.

		$manifest = $this->manifest(
			[
				'guide'     => [ 'title' => 'Guide', 'content' => '<p>g</p>' ],
				'guide/faq' => [ 'title' => 'FAQ', 'slug' => 'faq', 'parent' => 'guide', 'content' => '<p>f</p>' ],
			]
		);

		$items = $this->byMapKey( ( new Claimer( new NullProvider() ) )->plan( $manifest, [] ) );

		$this->assertSame( $parent, $items['guide|']->postId );
		$this->assertSame( PlanItem::CLAIM, $items['guide/faq|']->action );
		$this->assertSame( $right, $items['guide/faq|']->postId );
		$this->assertNotSame( $wrong, $items['guide/faq|']->postId );
	}

	public function test_a_top_level_entry_does_not_match_a_nested_page() {
		$parent = $this->page( 'guide' );
		$this->page( 'about', [ 'post_parent' => $parent ] );

		$manifest = $this->manifest( [ 'about' => [ 'title' => 'About', 'content' => '<p>a</p>' ] ] );

		$this->assertSame(
			PlanItem::NO_MATCH,
			$this->byMapKey( ( new Claimer( new NullProvider() ) )->plan( $manifest, [] ) )['about|']->action
		);
	}

	public function test_apply_writes_only_the_key_and_is_idempotent() {
		$id       = $this->page( 'about', [ 'post_content' => 'live copy', 'post_title' => 'Live title' ] );
		$manifest = $this->manifest( [ 'about' => [ 'title' => 'About', 'content' => '<p>a</p>' ] ] );
		$claimer  = new Claimer( new NullProvider() );

		$first = $claimer->apply( $claimer->plan( $manifest, [] ) );

		$this->assertSame( 1, $first['claimed'] );
		$this->assertSame( [], $first['errors'] );
		$this->assertSame( 'about', get_post_meta( $id, Meta::KEY, true ) );
		$this->assertSame( '', get_post_meta( $id, Meta::HASH, true ) );
		$this->assertSame( '', get_post_meta( $id, Meta::SOURCE, true ) );
		$this->assertSame( 'live copy', get_post( $id )->post_content );
		$this->assertSame( 'Live title', get_post( $id )->post_title );

		// A second run sees the row in actual state and plans nothing.
		$actual  = ( new \Pediment\Seeder\StateReader( new NullProvider() ) )->read();
		$replan  = $claimer->plan( $manifest, $actual );
		$this->assertSame( [], $replan->byAction( PlanItem::CLAIM ) );
	}

	public function test_a_claimed_page_is_protected_by_the_next_seed() {
		$id       = $this->page( 'about', [ 'post_content' => 'live copy' ] );
		$manifest = $this->manifest( [ 'about' => [ 'title' => 'About', 'content' => '<p>a</p>' ] ] );
		$claimer  = new Claimer( new NullProvider() );
		$claimer->apply( $claimer->plan( $manifest, [] ) );

		$desired = ( new \Pediment\Seeder\DesiredState(
			new NullProvider(),
			new \Pediment\Seeder\ContentResolver( new \Pediment\Seeder\MediaMap( [] ) )
		) )->build( $manifest );
		$reader  = new \Pediment\Seeder\StateReader( new NullProvider() );
		$plan    = ( new \Pediment\Seeder\Differ() )->diff( $desired, $reader->read(), $reader->duplicates() );

		$item = $plan->items()[0];
		$this->assertSame( PlanItem::PROTECTED, $item->action );
		$this->assertSame( 'live copy', get_post( $id )->post_content );
	}

	private function nav( string $title, string $slug ): int {
		return self::factory()->post->create(
			[ 'post_type' => 'wp_navigation', 'post_title' => $title, 'post_name' => $slug, 'post_status' => 'publish' ]
		);
	}

	public function test_the_only_unclaimed_navigation_is_claimed_for_a_single_nav_manifest() {
		$id       = $this->nav( 'Primary', 'primary' );
		$manifest = Manifest::fromArray(
			[
				'pages' => [ 'home' => [ 'title' => 'Home', 'content' => '<p>h</p>', 'front_page' => true ] ],
				'navs'  => [ 'primary' => [ 'title' => 'Primary', 'items' => [ [ 'entry' => 'home' ] ] ] ],
			],
			'/tmp/theme'
		);

		$plan  = ( new Claimer( new NullProvider() ) )->plan( $manifest, [] );
		$navs  = $plan->byKind( PlanItem::KIND_NAV );

		$this->assertCount( 1, $navs );
		$this->assertSame( PlanItem::CLAIM, $navs[0]->action );
		$this->assertSame( $id, $navs[0]->postId );
	}

	public function test_the_only_unclaimed_navigation_is_claimed_even_when_its_slug_does_not_match() {
		// 'primary-2' is what a previous seeder run left behind, not the slug
		// NavSeeder::slugFor() would derive today ('primary'). The single
		// unclaimed candidate rule must claim it anyway.
		$id       = $this->nav( 'Primary', 'primary-2' );
		$manifest = Manifest::fromArray(
			[
				'pages' => [ 'home' => [ 'title' => 'Home', 'content' => '<p>h</p>', 'front_page' => true ] ],
				'navs'  => [ 'primary' => [ 'title' => 'Primary', 'items' => [ [ 'entry' => 'home' ] ] ] ],
			],
			'/tmp/theme'
		);

		$navs = ( new Claimer( new NullProvider() ) )->plan( $manifest, [] )->byKind( PlanItem::KIND_NAV );

		$this->assertSame( PlanItem::CLAIM, $navs[0]->action );
		$this->assertSame( $id, $navs[0]->postId );
	}

	public function test_two_unclaimed_navigations_fall_back_to_slug_matching() {
		$this->nav( 'Footer', 'footer-menu' );
		$primary  = $this->nav( 'Primary', 'primary' );
		$manifest = Manifest::fromArray(
			[
				'pages' => [ 'home' => [ 'title' => 'Home', 'content' => '<p>h</p>', 'front_page' => true ] ],
				'navs'  => [ 'primary' => [ 'title' => 'Primary', 'items' => [ [ 'entry' => 'home' ] ] ] ],
			],
			'/tmp/theme'
		);

		$navs = ( new Claimer( new NullProvider() ) )->plan( $manifest, [] )->byKind( PlanItem::KIND_NAV );

		$this->assertSame( PlanItem::CLAIM, $navs[0]->action );
		$this->assertSame( $primary, $navs[0]->postId );
	}
}
