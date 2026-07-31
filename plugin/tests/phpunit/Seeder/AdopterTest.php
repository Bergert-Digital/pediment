<?php
// plugin/tests/phpunit/Seeder/AdopterTest.php

use Pediment\Language\NullProvider;
use Pediment\Seeder\Adopter;
use Pediment\Seeder\ContentHash;
use Pediment\Seeder\Manifest;
use Pediment\Seeder\MediaSeeder;
use Pediment\Seeder\Meta;
use Pediment\Seeder\Runner;

class AdopterTest extends WP_UnitTestCase {

	private string $dir;

	public function set_up(): void {
		parent::set_up();
		$this->dir = get_temp_dir() . 'pediment-adopt-test';
		wp_mkdir_p( $this->dir . '/patterns' );

		// The directory is shared across tests in one process, so a file written
		// by an earlier test would make the dry-run assertion meaningless.
		foreach ( (array) glob( $this->dir . '/patterns/*.php' ) as $stale ) {
			unlink( $stale );
		}
		foreach ( (array) glob( $this->dir . '/patterns/*.php.bak' ) as $stale ) {
			unlink( $stale );
		}

		add_filter(
			'pediment_seed_manifest',
			fn() => [ 'pages' => [ 'home' => [ 'title' => 'Home', 'pattern' => 'acme/home' ] ] ]
		);
		register_block_pattern( 'acme/home', [ 'title' => 'Home', 'content' => '<!-- wp:paragraph --><p>seeded</p><!-- /wp:paragraph -->' ] );
		add_filter( 'stylesheet_directory', fn() => $this->dir );
	}

	public function tear_down(): void {
		remove_all_filters( 'pediment_seed_manifest' );
		remove_all_filters( 'stylesheet_directory' );
		unregister_block_pattern( 'acme/home' );
		Manifest::resetCache();
		parent::tear_down();
	}

	public function test_adopt_writes_the_live_markup_to_the_pattern_file() {
		$ids = ( new Runner() )->run()->ids;
		wp_update_post( [ 'ID' => $ids['home|'], 'post_content' => '<!-- wp:paragraph --><p>client copy</p><!-- /wp:paragraph -->' ] );

		$result = ( new Adopter( new NullProvider() ) )->adopt( 'home' );

		$this->assertTrue( $result['written'] );
		$this->assertSame( $this->dir . '/patterns/home.php', $result['path'] );
		$contents = file_get_contents( $result['path'] );
		$this->assertStringContainsString( 'Slug: acme/home', $contents );
		$this->assertStringContainsString( 'client copy', $contents );
	}

	public function test_adopt_resets_the_hashes_so_the_page_is_no_longer_protected() {
		$ids = ( new Runner() )->run()->ids;
		$id  = $ids['home|'];
		wp_update_post( [ 'ID' => $id, 'post_content' => '<p>client copy</p>' ] );

		( new Adopter( new NullProvider() ) )->adopt( 'home' );

		$this->assertSame( ContentHash::forPost( $id ), get_post_meta( $id, Meta::HASH, true ) );
		// SOURCE is hashed from what the pattern FILE resolves to, not from the
		// row — that is what the next run will compare against.
		$this->assertSame(
			ContentHash::compute( get_post( $id )->post_title, $this->patternFileContent() ),
			get_post_meta( $id, Meta::SOURCE, true )
		);
	}

	public function test_dry_run_writes_no_file() {
		( new Runner() )->run();

		$result = ( new Adopter( new NullProvider() ) )->adopt( 'home', '', true );

		$this->assertFalse( $result['written'] );
		$this->assertFileDoesNotExist( $this->dir . '/patterns/home.php' );
	}

	public function test_an_unknown_key_is_an_error_not_a_silent_no_op() {
		$result = ( new Adopter( new NullProvider() ) )->adopt( 'ghost' );

		$this->assertNotEmpty( $result['errors'] );
		$this->assertStringContainsString( 'ghost', $result['errors'][0] );
	}

	public function test_an_entry_declared_with_literal_content_cannot_be_adopted() {
		remove_all_filters( 'pediment_seed_manifest' );
		add_filter( 'pediment_seed_manifest', static fn() => [ 'pages' => [ 'home' => [ 'title' => 'Home', 'content' => '<p>x</p>' ] ] ] );
		( new Runner() )->run();

		$result = ( new Adopter( new NullProvider() ) )->adopt( 'home' );

		$this->assertStringContainsString( 'pattern', $result['errors'][0] );
	}

	public function test_the_next_seed_sees_an_adopted_page_as_unchanged() {
		// The promise of adopt: the client's version becomes the source of truth,
		// so the run right after it must plan nothing.
		$ids = ( new Runner() )->run()->ids;
		wp_update_post( [ 'ID' => $ids['home|'], 'post_content' => '<!-- wp:paragraph --><p>client copy</p><!-- /wp:paragraph -->' ] );

		( new Adopter( new NullProvider() ) )->adopt( 'home' );
		$this->reregisterAdoptedPattern();
		$result = ( new Runner() )->run();

		$this->assertTrue( $result->plan->isEmpty(), 'adopt must leave nothing for the next run to write' );
		$this->assertSame( '<!-- wp:paragraph --><p>client copy</p><!-- /wp:paragraph -->', get_post( $ids['home|'] )->post_content );
	}

	public function test_media_urls_are_written_back_as_placeholders() {
		wp_mkdir_p( $this->dir . '/seed/media' );
		file_put_contents( $this->dir . '/seed/media/hero.svg', '<svg xmlns="http://www.w3.org/2000/svg"/>' );
		remove_all_filters( 'pediment_seed_manifest' );
		add_filter(
			'pediment_seed_manifest',
			fn() => [
				'pages' => [ 'home' => [ 'title' => 'Home', 'pattern' => 'acme/home' ] ],
				'media' => [ 'hero' => [ 'file' => 'seed/media/hero.svg' ] ],
			]
		);
		$ids = ( new Runner() )->run()->ids;
		$url = wp_get_attachment_url( ( new MediaSeeder() )->map( \Pediment\Seeder\Manifest::load() )->id( 'hero' ) );
		wp_update_post( [ 'ID' => $ids['home|'], 'post_content' => '<img src="' . $url . '" />' ] );

		( new Adopter( new NullProvider() ) )->adopt( 'home' );

		$written = (string) file_get_contents( $this->dir . '/patterns/home.php' );
		$this->assertStringContainsString( '{{media_url:hero}}', $written );
		$this->assertStringNotContainsString( $url, $written, 'an environment-specific URL must not land in git' );
	}

	public function test_a_longer_attachment_id_is_not_corrupted_by_a_shorter_one() {
		wp_mkdir_p( $this->dir . '/seed/media' );
		file_put_contents( $this->dir . '/seed/media/hero.svg', '<svg xmlns="http://www.w3.org/2000/svg"/>' );
		remove_all_filters( 'pediment_seed_manifest' );
		add_filter(
			'pediment_seed_manifest',
			fn() => [
				'pages' => [ 'home' => [ 'title' => 'Home', 'pattern' => 'acme/home' ] ],
				'media' => [ 'hero' => [ 'file' => 'seed/media/hero.svg' ] ],
			]
		);
		$ids    = ( new Runner() )->run()->ids;
		$heroId = ( new MediaSeeder() )->map( Manifest::load() )->id( 'hero' );

		// Guarantee the prefix relationship by construction rather than hoping
		// two real attachment IDs happen to collide this way.
		$unrelatedId = $heroId . '9';
		wp_update_post(
			[
				'ID'           => $ids['home|'],
				'post_content' => '<!-- wp:image {"id":' . $heroId . '} /--><!-- wp:image {"id":' . $unrelatedId . '} -->',
			]
		);

		( new Adopter( new NullProvider() ) )->adopt( 'home' );

		$written = (string) file_get_contents( $this->dir . '/patterns/home.php' );
		$this->assertStringContainsString( '"id":{{media_id:hero}}', $written );
		$this->assertStringContainsString( '"id":' . $unrelatedId, $written, 'a longer ID sharing the shorter one\'s digits as a prefix must survive untouched' );
	}

	public function test_an_existing_pattern_header_survives_a_re_adopt() {
		file_put_contents(
			$this->dir . '/patterns/home.php',
			"<?php\n/**\n * Title: Home\n * Slug: acme/home\n * Description: Hand written.\n * Categories: pediment\n */\n\n?>\n<p>old</p>\n"
		);
		( new Runner() )->run();

		( new Adopter( new NullProvider() ) )->adopt( 'home' );

		$written = (string) file_get_contents( $this->dir . '/patterns/home.php' );
		$this->assertStringContainsString( 'Description: Hand written.', $written );
	}

	public function test_an_overwrite_keeps_a_backup() {
		file_put_contents( $this->dir . '/patterns/home.php', "<?php\n/**\n * Title: Home\n * Slug: acme/home\n */\n\n?>\n<p>previous</p>\n" );
		( new Runner() )->run();

		$result = ( new Adopter( new NullProvider() ) )->adopt( 'home' );

		$this->assertNotSame( '', $result['backup'] );
		$this->assertStringContainsString( 'previous', (string) file_get_contents( $result['backup'] ) );
	}

	/** The bytes the pattern registry would read from the written file. */
	private function patternFileContent(): string {
		ob_start();
		include $this->dir . '/patterns/home.php';
		return (string) ob_get_clean();
	}

	/**
	 * Model what a real next request does: the registry re-scans the theme's
	 * patterns directory, so the adopted file becomes the registered pattern.
	 * Inside one PHPUnit process `init` has already fired, so re-register by hand.
	 */
	private function reregisterAdoptedPattern(): void {
		unregister_block_pattern( 'acme/home' );
		register_block_pattern( 'acme/home', [ 'title' => 'Home', 'content' => $this->patternFileContent() ] );
	}
}
