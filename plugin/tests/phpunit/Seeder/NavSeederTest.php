<?php
// plugin/tests/phpunit/Seeder/NavSeederTest.php

use Pediment\Language\NullProvider;
use Pediment\Seeder\Manifest;
use Pediment\Seeder\Meta;
use Pediment\Seeder\NavSeeder;
use Pediment\Seeder\PlanItem;

class NavSeederTest extends WP_UnitTestCase {

	private function manifest( array $items ): Manifest {
		return Manifest::fromArray(
			[
				'pages' => [ 'home' => [ 'title' => 'Home', 'content' => '' ], 'about' => [ 'title' => 'About', 'content' => '' ] ],
				'navs'  => [ 'primary' => [ 'title' => 'Primary', 'items' => $items ] ],
			],
			'/tmp/theme'
		);
	}

	public function test_serializes_entry_links_by_resolved_id() {
		$seeder = new NavSeeder( new NullProvider() );
		$m      = $this->manifest( [ [ 'entry' => 'home' ], [ 'url' => '/contact', 'label' => 'Contact' ] ] );

		$markup = $seeder->serialize( $m->navs()['primary'], '', [ 'home|' => 12 ] );

		$this->assertStringContainsString( '"id":12', $markup );
		$this->assertStringContainsString( '"kind":"post-type"', $markup );
		$this->assertStringContainsString( '"label":"Contact"', $markup );
		$this->assertStringContainsString( 'wp:navigation-link', $markup );
	}

	public function test_creates_one_entity_per_nav_key() {
		$seeder = new NavSeeder( new NullProvider() );
		$m      = $this->manifest( [ [ 'entry' => 'home' ] ] );
		$ids    = [ 'home|' => self::factory()->post->create( [ 'post_type' => 'page' ] ) ];

		$navIds = $seeder->apply( $seeder->plan( $m, $ids ), $m, $ids );

		$nav = get_post( $navIds['primary|'] );
		$this->assertSame( 'wp_navigation', $nav->post_type );
		$this->assertSame( 'publish', $nav->post_status );
		$this->assertSame( 'primary', get_post_meta( $nav->ID, Meta::KEY, true ) );
	}

	public function test_membership_changes_are_planned_and_applied_in_place() {
		$seeder = new NavSeeder( new NullProvider() );
		$ids    = [
			'home|'  => self::factory()->post->create( [ 'post_type' => 'page' ] ),
			'about|' => self::factory()->post->create( [ 'post_type' => 'page' ] ),
		];
		$first  = $this->manifest( [ [ 'entry' => 'home' ] ] );
		$navIds = $seeder->apply( $seeder->plan( $first, $ids ), $first, $ids );

		$second = $this->manifest( [ [ 'entry' => 'home' ], [ 'entry' => 'about' ] ] );
		$plan   = $seeder->plan( $second, $ids );
		$again  = $seeder->apply( $plan, $second, $ids );

		$this->assertSame( PlanItem::UPDATE, $plan->items()[0]->action );
		$this->assertSame( 2, $plan->items()[0]->changes['items']['to'] );
		$this->assertSame( $navIds['primary|'], $again['primary|'], 'update in place, never re-create' );
		$this->assertSame( 2, substr_count( get_post( $again['primary|'] )->post_content, 'wp:navigation-link' ) );
	}

	public function test_an_unchanged_nav_is_not_rewritten() {
		$seeder = new NavSeeder( new NullProvider() );
		$m      = $this->manifest( [ [ 'entry' => 'home' ] ] );
		$ids    = [ 'home|' => self::factory()->post->create( [ 'post_type' => 'page' ] ) ];
		$navIds = $seeder->apply( $seeder->plan( $m, $ids ), $m, $ids );
		$before = get_post( $navIds['primary|'] )->post_modified_gmt;

		$plan = $seeder->plan( $m, $ids );
		$seeder->apply( $plan, $m, $ids );

		$this->assertSame( PlanItem::UNCHANGED, $plan->items()[0]->action );
		$this->assertSame( $before, get_post( $navIds['primary|'] )->post_modified_gmt );
	}

	public function test_a_nav_with_slashes_in_its_urls_is_not_rewritten_forever() {
		// The trap: KSES strips the `\/` escapes out of the stored JSON, so a
		// freshly serialized string never matches what is in the database and the
		// entity is rewritten on every single run.
		$seeder = new NavSeeder( new NullProvider() );
		$m      = $this->manifest( [ [ 'url' => '/contact/us', 'label' => 'Contact' ] ] );
		$seeder->apply( $seeder->plan( $m, [] ), $m, [] );

		$plan = $seeder->plan( $m, [] );

		$this->assertSame( PlanItem::UNCHANGED, $plan->items()[0]->action );
	}

	public function test_an_item_whose_entry_is_not_seeded_is_reported() {
		$seeder = new NavSeeder( new NullProvider() );
		$m      = $this->manifest( [ [ 'entry' => 'home' ] ] );

		$seeder->apply( $seeder->plan( $m, [] ), $m, [] );

		$this->assertNotEmpty( $seeder->errors() );
		$this->assertStringContainsString( 'home', $seeder->errors()[0] );
	}

	public function test_a_trashed_nav_is_restored_not_re_created() {
		// A client deleting the menu used to leave the seed key on the trashed
		// row while a second entity was created beside it — two navs under one
		// identity, which the very next run reports as fatal.
		$seeder = new NavSeeder( new NullProvider() );
		$m      = $this->manifest( [ [ 'entry' => 'home' ] ] );
		$ids    = [ 'home|' => self::factory()->post->create( [ 'post_type' => 'page' ] ) ];
		$navIds = $seeder->apply( $seeder->plan( $m, $ids ), $m, $ids );
		wp_trash_post( $navIds['primary|'] );

		$plan  = $seeder->plan( $m, $ids );
		$again = $seeder->apply( $plan, $m, $ids );

		$this->assertSame( PlanItem::RESTORE, $plan->items()[0]->action );
		$this->assertSame( $navIds['primary|'], $again['primary|'], 'restore in place, never re-create' );
		$nav = get_post( $navIds['primary|'] );
		$this->assertSame( 'publish', $nav->post_status );
		$this->assertSame( 'primary', $nav->post_name, 'the __trashed suffix must not survive' );
		$this->assertSame( '', get_post_meta( $navIds['primary|'], '_wp_trash_meta_status', true ) );
		$this->assertCount( 1, get_posts( [ 'post_type' => 'wp_navigation', 'post_status' => 'any', 'posts_per_page' => -1, 'fields' => 'ids' ] ) );
	}

	public function test_two_entities_under_one_key_are_reported() {
		$seeder = new NavSeeder( new NullProvider() );
		$m      = $this->manifest( [ [ 'entry' => 'home' ] ] );
		$ids    = [ 'home|' => self::factory()->post->create( [ 'post_type' => 'page' ] ) ];
		$seeder->apply( $seeder->plan( $m, $ids ), $m, $ids );
		$impostor = self::factory()->post->create( [ 'post_type' => 'wp_navigation' ] );
		update_post_meta( $impostor, \Pediment\Seeder\Meta::KEY, 'primary' );

		$plan = $seeder->plan( $m, $ids );

		$this->assertTrue( $plan->hasErrors() );
		$this->assertStringContainsString( 'primary', $plan->errors()[0] );
	}

	public function test_an_unresolved_link_is_still_reported_once_the_nav_stops_changing() {
		// A nav that is ALREADY short (seeded before this rule existed, or written
		// by hand) plans as UNCHANGED — the missing link is still missing, and
		// silence would read as success.
		$seeder = new NavSeeder( new NullProvider() );
		$m      = $this->manifest( [ [ 'entry' => 'home' ] ] );
		$navId  = self::factory()->post->create(
			[ 'post_type' => 'wp_navigation', 'post_content' => $seeder->serialize( $m->navs()['primary'], '', [] ) ]
		);
		update_post_meta( $navId, Meta::KEY, 'primary' );

		$plan = $seeder->plan( $m, [] );
		$seeder->apply( $plan, $m, [] );

		$this->assertSame( PlanItem::UNCHANGED, $plan->items()[0]->action );
		$this->assertNotEmpty( $seeder->errors(), 'the problem persists, so the report must too' );
		$this->assertStringContainsString( 'home', $seeder->errors()[0] );
	}

	public function test_a_nav_is_left_untouched_rather_than_written_short() {
		// An entry whose write failed earlier in the same run is absent from the
		// id map. serialize() drops the link, so writing would replace the live
		// menu with a shortened one — the header quietly losing an item.
		$seeder = new NavSeeder( new NullProvider() );
		$ids    = [
			'home|'  => self::factory()->post->create( [ 'post_type' => 'page' ] ),
			'about|' => self::factory()->post->create( [ 'post_type' => 'page' ] ),
		];
		$m      = $this->manifest( [ [ 'entry' => 'home' ], [ 'entry' => 'about' ] ] );
		$navIds = $seeder->apply( $seeder->plan( $m, $ids ), $m, $ids );
		$before = get_post( $navIds['primary|'] )->post_content;

		$partial = [ 'home|' => $ids['home|'] ];
		$plan    = $seeder->plan( $m, $partial );
		$seeder->apply( $plan, $m, $partial );

		$this->assertSame( PlanItem::UPDATE, $plan->items()[0]->action );
		$this->assertSame( $before, get_post( $navIds['primary|'] )->post_content, 'a failed entry write must not delete a link from the live menu' );
		$this->assertStringContainsString( 'about', implode( "\n", $seeder->errors() ) );
	}

	public function test_serialize_has_no_side_effects() {
		$seeder = new NavSeeder( new NullProvider() );
		$m      = $this->manifest( [ [ 'entry' => 'home' ] ] );

		$seeder->serialize( $m->navs()['primary'], '', [] );
		$seeder->serialize( $m->navs()['primary'], '', [] );

		$this->assertSame( [], $seeder->errors(), 'serialize() is a formatter, not a reporter' );
	}
}
