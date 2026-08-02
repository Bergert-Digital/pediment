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
		register_block_pattern( 'x/sample-post', [ 'title' => 'Sample post', 'content' => '<p>english</p>' ] );
	}

	public function tear_down(): void {
		unregister_block_pattern( 'x/home' );
		unregister_block_pattern( 'x/sample-post' );
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

	public function test_a_missing_translated_pattern_names_the_correct_file_for_a_hyphenated_slug() {
		$manifest = Manifest::fromArray(
			[
				'languages' => [ 'en' => [ 'name' => 'English', 'locale' => 'en_US', 'default' => true ], 'de' => [ 'name' => 'Deutsch', 'locale' => 'de_DE' ] ],
				'pages'     => [ 'sample' => [ 'title' => 'Sample post', 'pattern' => 'x/sample-post' ] ],
			],
			get_stylesheet_directory()
		);

		$state = new DesiredState( new PolylangProvider(), new ContentResolver( new MediaMap( [] ) ) );
		$state->build( $manifest );

		$notices = implode( "\n", $state->missingTranslations() );

		// A hyphen-run regex strips from the FIRST hyphen ('x/sample-post-de'
		// -> 'sample'), naming a file the operator would never create. The
		// correct stem keeps the whole multi-word slug and only drops the
		// known '-de' suffix.
		$this->assertStringContainsString( 'patterns/sample-post.de.php', $notices );
		$this->assertStringNotContainsString( 'patterns/sample.de.php', $notices );
	}
}
