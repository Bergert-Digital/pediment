<?php
// plugin/tests/polylang/ClaimerLanguageTest.php

use Pediment\Language\PolylangProvider;
use Pediment\Seeder\Claimer;
use Pediment\Seeder\Manifest;
use Pediment\Seeder\Meta;
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

	/**
	 * No `de` override, so `EntrySpec::slugFor( 'de', 'en' )` derives its own
	 * `<slug>-<lang>` suffix (docs/seeding.md, "The derived slug rule, and
	 * why") instead of reusing `ueber-uns`. That lets a test hand the
	 * untagged-post candidate query a slug that clears the `de` pass's slug
	 * filter, so only `Claimer::languageMatches()` can still reject it.
	 */
	private function manifestWithDerivedDeSlug(): Manifest {
		return Manifest::fromArray(
			[
				'languages' => [ 'en' => [ 'name' => 'English', 'locale' => 'en_US', 'default' => true ], 'de' => [ 'name' => 'Deutsch', 'locale' => 'de_DE' ] ],
				'pages'     => [
					'about' => [
						'title'   => 'About',
						'content' => '<p>a</p>',
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
		} else {
			// Polylang auto-tags every saved post on `save_post` (see
			// ApplierTranslationTest::test_a_re_seed_repairs_a_post_whose_language_term_was_stripped),
			// so a factory-created post is never actually untagged — confirmed
			// here with pll_get_post_language() returning 'en' after a plain
			// create() — until its language term relationship is explicitly
			// removed. That removal, not simply omitting pll_set_post_language(),
			// is what produces the untagged candidate this suite needs.
			wp_delete_object_term_relationships( $id, 'language' );
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
		// Untagged, and slugged exactly like the derived `de` candidate query
		// expects — so it clears that query's slug filter and reaches
		// languageMatches(), which must still refuse it. Without this second
		// post, "about|de" is NO_MATCH purely because no candidate's slug is
		// "about-de" at all, and the language rule is never consulted.
		$this->page( 'about-de' );

		$items = $this->byMapKey( ( new Claimer( new PolylangProvider() ) )->plan( $this->manifestWithDerivedDeSlug(), [] ) );

		$this->assertSame( PlanItem::CLAIM, $items['about|en']->action );
		$this->assertSame( $untagged, $items['about|en']->postId );
		$this->assertSame( PlanItem::NO_MATCH, $items['about|de']->action );
	}

	private function navManifest(): Manifest {
		return Manifest::fromArray(
			[
				'languages' => [ 'en' => [ 'name' => 'English', 'locale' => 'en_US', 'default' => true ], 'de' => [ 'name' => 'Deutsch', 'locale' => 'de_DE' ] ],
				'pages'     => [ 'home' => [ 'title' => 'Home', 'content' => '<p>h</p>', 'front_page' => true ] ],
				'navs'      => [ 'primary' => [ 'title' => 'Primary', 'items' => [ [ 'entry' => 'home' ] ] ] ],
			],
			'/tmp/theme'
		);
	}

	private function nav( string $slug, string $language ): int {
		$id = self::factory()->post->create(
			[ 'post_type' => 'wp_navigation', 'post_title' => 'Primary', 'post_name' => $slug, 'post_status' => 'publish' ]
		);
		pll_set_post_language( $id, $language );
		return $id;
	}

	/**
	 * NavSeeder::keyed() buckets a claimed nav under "<key>|<language>", not
	 * just "<key>": a `de` nav already carrying the `primary` seed key must
	 * make Claimer skip only `primary|de`, never `primary|en`. If keyed()
	 * mis-attributed language, or Claimer::plan() keyed its skip check on
	 * the nav key alone, the `en` nav below would go unclaimed too.
	 */
	public function test_a_nav_claimed_in_one_language_does_not_block_claiming_the_other() {
		$de = $this->nav( 'primary-de', 'de' );
		update_post_meta( $de, Meta::KEY, 'primary' );
		$en = $this->nav( 'primary', 'en' );

		$navs = ( new Claimer( new PolylangProvider() ) )->plan( $this->navManifest(), [] )->byKind( PlanItem::KIND_NAV );

		$this->assertCount( 1, $navs );
		$this->assertSame( PlanItem::CLAIM, $navs[0]->action );
		$this->assertSame( 'en', $navs[0]->language );
		$this->assertSame( $en, $navs[0]->postId );
	}
}
