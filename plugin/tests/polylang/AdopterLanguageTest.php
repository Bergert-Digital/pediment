<?php

use Pediment\Language\PolylangProvider;
use Pediment\Seeder\Adopter;
use Pediment\Seeder\ContentHash;
use Pediment\Seeder\Manifest;
use Pediment\Seeder\Meta;

class AdopterLanguageTest extends PolylangTestCase {

	private string $dir;

	public function set_up(): void {
		parent::set_up();
		$this->dir = get_stylesheet_directory() . '/patterns';
		wp_mkdir_p( $this->dir );
	}

	public function tear_down(): void {
		// The trailing `*` (not `*.php`) also catches the `.bak` sibling adopt()
		// writes when a file with different contents already exists — an
		// unmatched glob here would leak that file into the active theme and
		// distort whatever test class runs next.
		foreach ( glob( $this->dir . '/adoptme*' ) as $file ) {
			wp_delete_file( $file );
		}
		Manifest::resetCache();
		parent::tear_down();
	}

	private function manifest(): void {
		add_filter(
			'pediment_seed_manifest',
			fn() => [
				'languages' => [ 'en' => [ 'name' => 'English', 'locale' => 'en_US', 'default' => true ], 'de' => [ 'name' => 'Deutsch', 'locale' => 'de_DE' ] ],
				'pages'     => [ 'adoptme' => [ 'title' => 'Adopt me', 'pattern' => 'x/adoptme', 'languages' => [ 'de' => [ 'title' => 'Übernimm mich', 'slug' => 'uebernimm-mich' ] ] ] ],
			]
		);
		Manifest::resetCache();
	}

	private function page( string $language, string $content ): int {
		$id = self::factory()->post->create( [ 'post_type' => 'page', 'post_title' => 'x', 'post_content' => $content ] );
		pll_set_post_language( $id, $language );
		update_post_meta( $id, Meta::KEY, 'adoptme' );
		return $id;
	}

	public function test_a_german_adopt_writes_the_german_file() {
		$this->manifest();
		$this->page( 'en', '<p>english</p>' );
		$this->page( 'de', '<p>deutsch</p>' );

		$result = ( new Adopter( new PolylangProvider() ) )->adopt( 'adoptme', 'de' );

		$this->assertSame( [], $result['errors'] );
		$this->assertStringEndsWith( '/patterns/adoptme.de.php', $result['path'] );
		$this->assertFileExists( $this->dir . '/adoptme.de.php' );
		$this->assertStringContainsString( 'deutsch', file_get_contents( $this->dir . '/adoptme.de.php' ) );
	}

	public function test_the_german_file_carries_the_language_slug_header() {
		$this->manifest();
		$this->page( 'en', '<p>english</p>' );
		$this->page( 'de', '<p>deutsch</p>' );

		( new Adopter( new PolylangProvider() ) )->adopt( 'adoptme', 'de' );

		$header = get_file_data( $this->dir . '/adoptme.de.php', [ 'slug' => 'Slug', 'title' => 'Title' ] );

		$this->assertSame( 'x/adoptme-de', $header['slug'], 'The next seed looks the German pattern up by this slug.' );
		$this->assertSame( 'Übernimm mich', $header['title'] );
	}

	public function test_the_default_language_still_writes_the_plain_file() {
		$this->manifest();
		$this->page( 'en', '<p>english</p>' );

		$result = ( new Adopter( new PolylangProvider() ) )->adopt( 'adoptme', 'en' );

		$this->assertStringEndsWith( '/patterns/adoptme.php', $result['path'] );
	}

	public function test_the_german_hashes_are_written_against_the_german_post() {
		$this->manifest();
		$this->page( 'en', '<p>english</p>' );
		$de = $this->page( 'de', '<p>deutsch</p>' );

		( new Adopter( new PolylangProvider() ) )->adopt( 'adoptme', 'de' );

		$this->assertSame( ContentHash::forPost( $de ), get_post_meta( $de, Meta::HASH, true ) );

		// HASH alone is self-referential (it is always the live post's own hash,
		// fix or bug) and does not prove SOURCE was crossed with the GERMAN
		// title. Assert the exact value the next seed will compare against,
		// keyed on "Übernimm mich" — the manifest's declared German title, not
		// the English default — so a regression that hashes the wrong title is
		// caught here rather than three months from now as a silent overwrite.
		$this->assertSame(
			ContentHash::compute( 'Übernimm mich', $this->resolvedPatternContent( $this->dir . '/adoptme.de.php' ) ),
			get_post_meta( $de, Meta::SOURCE, true )
		);
	}

	/** The bytes the pattern registry would read from the written file. */
	private function resolvedPatternContent( string $file ): string {
		ob_start();
		include $file;
		return (string) ob_get_clean();
	}
}
