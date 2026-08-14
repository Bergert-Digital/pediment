<?php
// plugin/tests/phpunit/Seeder/MegaBlocksTest.php

use Pediment\Seeder\MegaBlocks;
use Pediment\Seeder\Meta;

class MegaBlocksTest extends WP_UnitTestCase {

	private const BLOCK = '<!-- wp:pediment/mega-menu {"label":"Products","columns":[{"heading":"Banking","links":[{"label":"Features","url":"/features/"}]}]} /-->';

	public function test_extracts_blocks_verbatim_in_document_order() {
		$content = '<!-- wp:navigation-link {"label":"Home","url":"/","kind":"custom"} /-->' . "\n"
			. self::BLOCK . "\n"
			. '<!-- wp:pediment/mega-menu {"label":"More","columns":[{"heading":"B","links":[{"label":"L","url":"/l/"}]}]} /-->';

		$blocks = MegaBlocks::extract( $content );

		$this->assertCount( 2, $blocks );
		$this->assertSame( self::BLOCK, $blocks[0] );
		$this->assertStringContainsString( '"label":"More"', $blocks[1] );
	}

	public function test_extract_finds_nothing_in_mega_free_markup() {
		$this->assertSame( [], MegaBlocks::extract( '<!-- wp:navigation-link {"label":"Home","url":"/","kind":"custom"} /-->' ) );
		$this->assertSame( [], MegaBlocks::extract( '' ) );
	}

	public function test_git_owns_an_empty_position_and_an_unedited_block() {
		$this->assertTrue( MegaBlocks::gitOwns( null, '' ) );
		$this->assertTrue( MegaBlocks::gitOwns( self::BLOCK, MegaBlocks::hash( self::BLOCK ) ) );
	}

	public function test_the_client_owns_an_edited_or_unhashed_block() {
		$this->assertFalse( MegaBlocks::gitOwns( self::BLOCK, '' ), 'a missing hash (claimed nav) means edited' );
		$this->assertFalse( MegaBlocks::gitOwns( self::BLOCK, MegaBlocks::hash( 'something else' ) ) );
		$this->assertFalse( MegaBlocks::gitOwns( self::BLOCK, '0:deadbeef' ), 'a foreign hash version never matches' );
	}

	public function test_write_hashes_freshens_git_owned_positions_and_carries_edited_ones() {
		$navId = self::factory()->post->create( [ 'post_type' => 'wp_navigation' ] );
		update_post_meta( $navId, Meta::MEGA_HASH, wp_json_encode( [ MegaBlocks::hash( self::BLOCK ) ] ) );

		// The client edited the block; a membership rewrite splices it through.
		$edited = str_replace( 'Features', 'Edited', self::BLOCK );
		$new    = '<!-- wp:navigation-link {"label":"About","url":"/about/","kind":"custom"} /-->' . "\n" . $edited;
		// writeHashes() now reads the persisted row, not a string handed in —
		// same rule as ContentHash::forPost() — so the write has to actually
		// land before the call, exactly as apply() does it.
		wp_update_post( [ 'ID' => $navId, 'post_content' => wp_slash( $new ) ] );

		MegaBlocks::writeHashes( $navId, $edited );

		$stored = MegaBlocks::storedHashes( $navId );
		$this->assertSame( MegaBlocks::hash( self::BLOCK ), $stored[0], 'the stale hash is carried forward, so the block stays client-owned' );
		$this->assertFalse( MegaBlocks::gitOwns( $edited, $stored[0] ) );
	}

	public function test_write_hashes_freshens_a_git_owned_position() {
		$navId = self::factory()->post->create( [ 'post_type' => 'wp_navigation' ] );
		update_post_meta( $navId, Meta::MEGA_HASH, wp_json_encode( [ MegaBlocks::hash( self::BLOCK ) ] ) );

		// Git changed the manifest; the old block was untouched, so the new
		// markup was emitted from the manifest and gets a fresh hash.
		$new = str_replace( 'Products', 'Solutions', self::BLOCK );
		wp_update_post( [ 'ID' => $navId, 'post_content' => wp_slash( $new ) ] );

		MegaBlocks::writeHashes( $navId, self::BLOCK );

		$this->assertSame( [ MegaBlocks::hash( $new ) ], MegaBlocks::storedHashes( $navId ) );
	}

	public function test_write_hashes_deletes_the_meta_when_no_mega_remains() {
		$navId = self::factory()->post->create( [ 'post_type' => 'wp_navigation' ] );
		update_post_meta( $navId, Meta::MEGA_HASH, wp_json_encode( [ MegaBlocks::hash( self::BLOCK ) ] ) );

		wp_update_post(
			[
				'ID'           => $navId,
				'post_content' => wp_slash( '<!-- wp:navigation-link {"label":"Home","url":"/","kind":"custom"} /-->' ),
			]
		);

		MegaBlocks::writeHashes( $navId, self::BLOCK );

		$this->assertSame( '', (string) get_post_meta( $navId, Meta::MEGA_HASH, true ) );
	}

	public function test_write_hashes_is_a_no_op_when_the_post_is_gone() {
		// A deleted-mid-request guard, same shape as ContentHash::forPost():
		// writing hashes for content that no longer exists would be nonsense,
		// not a safe default.
		$navId = self::factory()->post->create( [ 'post_type' => 'wp_navigation' ] );
		update_post_meta( $navId, Meta::MEGA_HASH, wp_json_encode( [ MegaBlocks::hash( self::BLOCK ) ] ) );
		wp_delete_post( $navId, true );

		MegaBlocks::writeHashes( $navId, self::BLOCK );

		$this->assertSame( '', (string) get_post_meta( $navId, Meta::MEGA_HASH, true ), 'no meta is written for a post that is gone' );
	}
}
