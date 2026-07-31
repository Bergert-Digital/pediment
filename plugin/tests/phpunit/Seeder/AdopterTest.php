<?php
// plugin/tests/phpunit/Seeder/AdopterTest.php

use Pediment\Language\NullProvider;
use Pediment\Seeder\Adopter;
use Pediment\Seeder\ContentHash;
use Pediment\Seeder\Manifest;
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
		$this->assertSame(
			ContentHash::compute( get_post( $id )->post_title, get_post( $id )->post_content ),
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
}
