<?php

use Pediment\Language\PolylangProvider;
use Pediment\Seeder\Applier;
use Pediment\Seeder\ContentResolver;
use Pediment\Seeder\DesiredState;
use Pediment\Seeder\Differ;
use Pediment\Seeder\Manifest;
use Pediment\Seeder\MediaMap;
use Pediment\Seeder\StateReader;

class ApplierTranslationTest extends PolylangTestCase {

	/**
	 * seed() sets show_on_front/page_on_front via applyReadingOptions(). Those
	 * options live in the non-persistent object cache, which is not part of
	 * the per-test DB transaction rollback — an option cached here would leak
	 * into whichever test class PHPUnit happens to run next.
	 */
	public function tear_down(): void {
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
}
