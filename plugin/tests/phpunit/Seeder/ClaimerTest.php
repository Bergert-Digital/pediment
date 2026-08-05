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
}
