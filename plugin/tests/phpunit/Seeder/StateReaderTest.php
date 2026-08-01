<?php
// plugin/tests/phpunit/Seeder/StateReaderTest.php

use Pediment\Language\NullProvider;
use Pediment\Seeder\ContentHash;
use Pediment\Seeder\ContentResolver;
use Pediment\Seeder\DesiredState;
use Pediment\Seeder\Manifest;
use Pediment\Seeder\MediaMap;
use Pediment\Seeder\Meta;
use Pediment\Seeder\StateReader;

class StateReaderTest extends WP_UnitTestCase {

	private function seeded( string $key, array $args = [] ): int {
		$id = self::factory()->post->create(
			array_merge( [ 'post_type' => 'page', 'post_title' => 'T', 'post_content' => 'C' ], $args )
		);
		update_post_meta( $id, Meta::KEY, $key );
		update_post_meta( $id, Meta::HASH, ContentHash::forPost( $id ) );
		update_post_meta( $id, Meta::SOURCE, ContentHash::compute( 'T', 'C' ) );
		return $id;
	}

	public function test_desired_state_crosses_the_manifest_with_languages() {
		$manifest = Manifest::fromArray(
			[ 'pages' => [ 'home' => [ 'title' => 'Home', 'content' => '<p>h</p>', 'front_page' => true ] ] ],
			'/tmp/theme'
		);
		$desired = ( new DesiredState( new NullProvider(), new ContentResolver( new MediaMap( [] ) ) ) )->build( $manifest );

		$this->assertArrayHasKey( 'home|', $desired );
		$entry = $desired['home|'];
		$this->assertSame( 'Home', $entry->title );
		$this->assertSame( '<p>h</p>', $entry->content );
		$this->assertTrue( $entry->frontPage );
		$this->assertSame( ContentHash::compute( 'Home', '<p>h</p>' ), $entry->sourceHash );
	}

	public function test_reads_seeded_entries_by_key_not_slug() {
		$id = $this->seeded( 'home', [ 'post_name' => 'startseite' ] );

		$actual = ( new StateReader( new NullProvider() ) )->read();

		$this->assertArrayHasKey( 'home|', $actual );
		$this->assertSame( $id, $actual['home|']->id );
		$this->assertSame( 'startseite', $actual['home|']->slug );
		$this->assertTrue( ContentHash::matches( $actual['home|']->storedHash, $actual['home|']->currentHash ) );
	}

	public function test_a_client_edit_shows_as_a_hash_mismatch() {
		$id = $this->seeded( 'home' );
		wp_update_post( [ 'ID' => $id, 'post_content' => 'client wrote this' ] );

		$actual = ( new StateReader( new NullProvider() ) )->read()['home|'];

		$this->assertFalse( ContentHash::matches( $actual->storedHash, $actual->currentHash ) );
	}

	public function test_drafts_and_trashed_entries_are_still_found() {
		$this->seeded( 'draft-page', [ 'post_status' => 'draft' ] );
		$this->seeded( 'trashed-page', [ 'post_status' => 'trash' ] );

		$actual = ( new StateReader( new NullProvider() ) )->read();

		$this->assertSame( 'draft', $actual['draft-page|']->status );
		$this->assertSame( 'trash', $actual['trashed-page|']->status );
	}

	public function test_unseeded_posts_are_invisible() {
		self::factory()->post->create( [ 'post_type' => 'page', 'post_title' => 'Client page' ] );

		$this->assertSame( [], ( new StateReader( new NullProvider() ) )->read() );
	}

	public function test_duplicate_keys_are_reported_and_not_silently_picked() {
		$a = $this->seeded( 'home' );
		$b = $this->seeded( 'home' );

		$reader = new StateReader( new NullProvider() );

		$this->assertSame( [ 'home|' => [ $a, $b ] ], $reader->duplicates() );
	}

	public function test_attachments_and_navigation_entities_are_not_entries() {
		$nav = self::factory()->post->create( [ 'post_type' => 'wp_navigation' ] );
		update_post_meta( $nav, Meta::KEY, 'primary' );

		$this->assertSame( [], ( new StateReader( new NullProvider() ) )->read() );
	}
}
