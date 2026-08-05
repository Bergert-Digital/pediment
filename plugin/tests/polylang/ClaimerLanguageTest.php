<?php
// plugin/tests/polylang/ClaimerLanguageTest.php

use Pediment\Language\PolylangProvider;
use Pediment\Seeder\Claimer;
use Pediment\Seeder\Manifest;
use Pediment\Seeder\PlanItem;

class ClaimerLanguageTest extends PolylangTestCase {

	private function manifest(): Manifest {
		return Manifest::fromArray(
			[
				'languages' => [ 'en' => [ 'name' => 'English', 'locale' => 'en_US', 'default' => true ], 'de' => [ 'name' => 'Deutsch', 'locale' => 'de_DE' ] ],
				'pages'     => [
					'about' => [
						'title'     => 'About',
						'content'   => '<p>a</p>',
						'languages' => [ 'de' => [ 'title' => 'Über uns', 'slug' => 'ueber-uns' ] ],
					],
				],
			],
			'/tmp/theme'
		);
	}

	private function page( string $slug, ?string $language = null ): int {
		$id = self::factory()->post->create(
			[ 'post_type' => 'page', 'post_name' => $slug, 'post_title' => 'Legacy', 'post_status' => 'publish' ]
		);
		if ( null !== $language ) {
			pll_set_post_language( $id, $language );
		}
		return $id;
	}

	/** @return array<string,PlanItem> */
	private function byMapKey( \Pediment\Seeder\Plan $plan ): array {
		$out = [];
		foreach ( $plan->items() as $item ) {
			$out[ $item->mapKey() ] = $item;
		}
		return $out;
	}

	public function test_each_language_claims_its_own_page() {
		$en = $this->page( 'about', 'en' );
		$de = $this->page( 'ueber-uns', 'de' );

		$items = $this->byMapKey( ( new Claimer( new PolylangProvider() ) )->plan( $this->manifest(), [] ) );

		$this->assertSame( $en, $items['about|en']->postId );
		$this->assertSame( $de, $items['about|de']->postId );
	}

	public function test_a_german_page_is_never_claimed_for_english() {
		$this->page( 'about', 'de' );

		$items = $this->byMapKey( ( new Claimer( new PolylangProvider() ) )->plan( $this->manifest(), [] ) );

		$this->assertSame( PlanItem::NO_MATCH, $items['about|en']->action );
	}

	public function test_an_untagged_page_is_claimed_for_the_default_language_only() {
		$untagged = $this->page( 'about' );

		$items = $this->byMapKey( ( new Claimer( new PolylangProvider() ) )->plan( $this->manifest(), [] ) );

		$this->assertSame( PlanItem::CLAIM, $items['about|en']->action );
		$this->assertSame( $untagged, $items['about|en']->postId );
		$this->assertSame( PlanItem::NO_MATCH, $items['about|de']->action );
	}
}
