<?php

use Pediment\Language\PolylangProvider;
use Pediment\Seeder\ContentResolver;
use Pediment\Seeder\DesiredState;
use Pediment\Seeder\Manifest;
use Pediment\Seeder\MediaMap;

class DesiredStateLanguageTest extends PolylangTestCase {

	public function set_up(): void {
		parent::set_up();
		register_block_pattern( 'x/home', [ 'title' => 'Home', 'content' => '<p>english</p>' ] );
	}

	public function tear_down(): void {
		unregister_block_pattern( 'x/home' );
		Manifest::resetCache();
		parent::tear_down();
	}

	private function manifest(): Manifest {
		return Manifest::fromArray(
			[
				'languages' => [ 'en' => [ 'name' => 'English', 'locale' => 'en_US', 'default' => true ], 'de' => [ 'name' => 'Deutsch', 'locale' => 'de_DE' ] ],
				'pages'     => [ 'home' => [ 'title' => 'Home', 'pattern' => 'x/home', 'languages' => [ 'de' => [ 'title' => 'Startseite', 'slug' => 'startseite' ] ] ] ],
			],
			get_stylesheet_directory()
		);
	}

	public function test_one_entry_per_language() {
		$desired = ( new DesiredState( new PolylangProvider(), new ContentResolver( new MediaMap( [] ) ) ) )->build( $this->manifest() );

		$this->assertSame( [ 'home|en', 'home|de' ], array_keys( $desired ) );
	}

	public function test_each_language_carries_its_own_title_and_slug() {
		$desired = ( new DesiredState( new PolylangProvider(), new ContentResolver( new MediaMap( [] ) ) ) )->build( $this->manifest() );

		$this->assertSame( 'Startseite', $desired['home|de']->title );
		$this->assertSame( 'startseite', $desired['home|de']->slug );
		$this->assertSame( 'Home', $desired['home|en']->title );
		$this->assertSame( 'home', $desired['home|en']->slug );
	}

	public function test_the_hashes_differ_per_language() {
		$desired = ( new DesiredState( new PolylangProvider(), new ContentResolver( new MediaMap( [] ) ) ) )->build( $this->manifest() );

		$this->assertNotSame( $desired['home|en']->sourceHash, $desired['home|de']->sourceHash );
	}

	public function test_a_missing_translated_pattern_is_reported() {
		$state = new DesiredState( new PolylangProvider(), new ContentResolver( new MediaMap( [] ) ) );
		$state->build( $this->manifest() );

		$notices = implode( "\n", $state->missingTranslations() );

		$this->assertStringContainsString( 'home', $notices );
		$this->assertStringContainsString( 'de', $notices );
		$this->assertStringContainsString( 'x/home-de', $notices );
	}
}
