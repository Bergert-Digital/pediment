<?php

use Pediment\Language\PolylangProvider;
use Pediment\Seeder\Applier;
use Pediment\Seeder\ContentHash;
use Pediment\Seeder\ContentResolver;
use Pediment\Seeder\DesiredEntry;
use Pediment\Seeder\DesiredState;
use Pediment\Seeder\Differ;
use Pediment\Seeder\Manifest;
use Pediment\Seeder\MediaMap;
use Pediment\Seeder\Meta;
use Pediment\Seeder\Plan;
use Pediment\Seeder\PlanItem;
use Pediment\Seeder\StateReader;

class ApplierTranslationTest extends PolylangTestCase {

	/** term_id of a language added mid-test, so tear_down() can remove it again. */
	private ?int $addedLanguageId = null;

	/**
	 * seed() sets show_on_front/page_on_front via applyReadingOptions(). Those
	 * options live in the non-persistent object cache, which is not part of
	 * the per-test DB transaction rollback — an option cached here would leak
	 * into whichever test class PHPUnit happens to run next.
	 */
	public function tear_down(): void {
		if ( null !== $this->addedLanguageId ) {
			PLL()->model->delete_language( $this->addedLanguageId );
			$this->addedLanguageId = null;
		}
		update_option( 'show_on_front', 'posts' );
		update_option( 'page_on_front', 0 );
		parent::tear_down();
	}

	private function seed(): array {
		$manifest = Manifest::fromArray(
			[
				'languages' => [ 'en' => [ 'name' => 'English', 'locale' => 'en_US', 'default' => true ], 'de' => [ 'name' => 'Deutsch', 'locale' => 'de_DE' ] ],
				'pages'     => [
					'home'  => [ 'title' => 'Home', 'content' => '<p>home</p>', 'front_page' => true, 'languages' => [ 'de' => [ 'title' => 'Startseite', 'slug' => 'startseite' ] ] ],
					'guide' => [ 'title' => 'Guide', 'content' => '<p>guide</p>', 'languages' => [ 'de' => [ 'title' => 'Anleitung', 'slug' => 'anleitung' ] ] ],
					'faq'   => [ 'title' => 'FAQ', 'content' => '<p>faq</p>', 'parent' => 'guide', 'languages' => [ 'de' => [ 'title' => 'Fragen', 'slug' => 'fragen' ] ] ],
				],
			],
			get_stylesheet_directory()
		);

		$lang    = new PolylangProvider();
		$desired = ( new DesiredState( $lang, new ContentResolver( new MediaMap( [] ) ) ) )->build( $manifest );
		$reader  = new StateReader( $lang );
		$plan    = ( new Differ() )->diff( $desired, $reader->read(), $reader->duplicates() );

		return [ ( new Applier( $lang ) )->apply( $plan, $desired ), $lang ];
	}

	public function test_the_two_languages_are_one_translation_group() {
		[ $applied, $lang ] = $this->seed();

		$en = $applied->ids['home|en'];
		$de = $applied->ids['home|de'];

		$this->assertGreaterThan( 0, $en );
		$this->assertGreaterThan( 0, $de );
		$this->assertSame( $de, $lang->translationOf( $en, 'de' ) );
		$this->assertSame( $en, $lang->translationOf( $de, 'en' ) );
	}

	public function test_a_child_is_parented_within_its_own_language() {
		[ $applied ] = $this->seed();

		$this->assertSame(
			$applied->ids['guide|de'],
			(int) get_post( $applied->ids['faq|de'] )->post_parent,
			'The German FAQ must hang off the German Guide, not the English one — a flat permalink breaks every menu URL in that language.'
		);
	}

	public function test_relinking_on_a_second_run_is_stable() {
		$this->seed();
		[ $applied, $lang ] = $this->seed();

		$this->assertSame( $applied->ids['guide|de'], $lang->translationOf( $applied->ids['guide|en'], 'de' ) );
	}

	public function test_the_front_page_option_holds_the_default_language_page() {
		[ $applied ] = $this->seed();

		$this->assertSame( $applied->ids['home|en'], (int) get_option( 'page_on_front' ) );
	}

	/**
	 * The existing-site migration case, in miniature: a page that predates
	 * this seeder's language tagging (or whose language term was stripped by
	 * hand) diffs as UNCHANGED forever and never goes through create(), so it
	 * never acquires a Polylang language — and linkTranslationGroups() then
	 * hands its ID to Polylang without one, which validate_translations()
	 * drops. `wp_delete_object_term_relationships()`, not simply skipping
	 * setLanguage(), is how a genuinely untagged post is produced: Polylang
	 * auto-tags on save (WORDPRESS_TRAPS.md), so a factory-created post is
	 * never actually untagged unless the term relationship is removed after
	 * the fact.
	 */
	public function test_a_re_seed_repairs_a_post_whose_language_term_was_stripped() {
		[ $applied, $lang ] = $this->seed();
		$en = $applied->ids['home|en'];
		$de = $applied->ids['home|de'];

		wp_delete_object_term_relationships( $en, 'language' );
		$this->assertFalse( $lang->hasLanguage( $en ), 'Precondition: the post must actually be untagged.' );

		$this->seed();

		$this->assertTrue( $lang->hasLanguage( $en ), 'The untagged post must be tagged again by the next seed.' );
		$this->assertSame( 'en', pll_get_post_language( $en ) );
		$this->assertSame( $de, $lang->translationOf( $en, 'de' ), 'The translation group must be intact after the repair.' );
		$this->assertSame( $en, $lang->translationOf( $de, 'en' ) );
	}

	/**
	 * The repair must never move a post that already has a language, even a
	 * "wrong" one — that is an editorial fact, not this engine's to correct.
	 * Exercised directly against Applier::apply() with a hand-built plan
	 * (rather than a second full seed()) because routing the same post
	 * through StateReader a second time, now mistagged 'de', would make it
	 * collide with the real German sibling under the SAME map key — a
	 * different, unrelated failure mode this test is not about.
	 */
	public function test_a_post_already_tagged_with_a_different_language_is_left_alone() {
		$lang   = new PolylangProvider();
		$postId = self::factory()->post->create( [ 'post_type' => 'page', 'post_name' => 'about' ] );
		$lang->setLanguage( $postId, 'de' );
		update_post_meta( $postId, Meta::KEY, 'about' );

		$entry   = new DesiredEntry( 'about', 'en', 'page', 'About', 'about', null, '<p>x</p>', false, false, 0, [], ContentHash::compute( 'About', '<p>x</p>' ) );
		$desired = [ 'about|en' => $entry ];
		// A structure-only UPDATE on the same already-tagged post, planned
		// under the 'en' map key — models a post whose Polylang language
		// disagrees with what the manifest currently declares for it.
		$item = new PlanItem( PlanItem::UPDATE, PlanItem::KIND_ENTRY, 'about', 'en', $postId, [ 'menu_order' => [ 'from' => 1, 'to' => 0 ] ] );
		$plan = new Plan( [ $item ], [] );

		( new Applier( $lang ) )->apply( $plan, $desired );

		$this->assertSame( 'de', pll_get_post_language( $postId ), 'A post that already has a language must never be re-tagged, even if it disagrees with the manifest.' );
	}

	/**
	 * "An ordinary write failure" (a DB hiccup, not a bad manifest) must not
	 * partially link a translation group. With 3 configured languages, a
	 * failed create leaves the map 2-of-3 — before this fix, Polylang's own
	 * count($clean) < 2 guard let a 2-entry map through, silently forming (or
	 * re-forming) a group that looks complete while a language is missing.
	 * Forcing the French create to fail via `wp_insert_post_empty_content` —
	 * matched on the slug EntrySpec::slugFor() derives for it — is a
	 * deterministic stand-in for any ordinary insert failure.
	 */
	public function test_a_failed_create_does_not_partially_link_the_group() {
		$fr                    = PLL()->model->add_language( [ 'slug' => 'fr', 'name' => 'Français', 'locale' => 'fr_FR', 'flag' => 'fr', 'rtl' => 0, 'term_group' => 2 ] );
		$this->addedLanguageId = $fr instanceof WP_Error ? null : (int) $fr->term_id;
		PLL()->model->clean_languages_cache();

		$manifest = Manifest::fromArray(
			[
				'languages' => [
					'en' => [ 'name' => 'English', 'locale' => 'en_US', 'default' => true ],
					'de' => [ 'name' => 'Deutsch', 'locale' => 'de_DE' ],
					'fr' => [ 'name' => 'Français', 'locale' => 'fr_FR' ],
				],
				'pages'     => [ 'home' => [ 'title' => 'Home', 'content' => '<p>home</p>' ] ],
			],
			get_stylesheet_directory()
		);

		$lang    = new PolylangProvider();
		$desired = ( new DesiredState( $lang, new ContentResolver( new MediaMap( [] ) ) ) )->build( $manifest );
		$reader  = new StateReader( $lang );
		$plan    = ( new Differ() )->diff( $desired, $reader->read(), $reader->duplicates() );

		$fail = static function ( $maybe_empty, $postarr ) {
			return ( $postarr['post_name'] ?? '' ) === 'home-fr' ? true : $maybe_empty;
		};
		add_filter( 'wp_insert_post_empty_content', $fail, 10, 2 );
		$applied = ( new Applier( $lang ) )->apply( $plan, $desired );
		remove_filter( 'wp_insert_post_empty_content', $fail, 10 );

		$this->assertArrayNotHasKey( 'home|fr', $applied->ids, 'Precondition: the French create must actually have failed.' );
		$this->assertNotEmpty( $applied->errors );

		$en = $applied->ids['home|en'];
		$de = $applied->ids['home|de'];

		$this->assertSame(
			0,
			$lang->translationOf( $en, 'de' ),
			'A 2-of-3 map must not be linked just because the failure left English and German behind — the group must wait for French to actually exist.'
		);
	}
}
