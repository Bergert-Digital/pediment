<?php
// plugin/tests/phpunit/Seeder/ContentHashTest.php

use Pediment\Seeder\ContentHash;

class ContentHashTest extends WP_UnitTestCase {

	public function test_hash_is_version_prefixed_and_stable() {
		$a = ContentHash::compute( 'Home', '<!-- wp:paragraph --><p>Hi</p><!-- /wp:paragraph -->' );
		$b = ContentHash::compute( 'Home', '<!-- wp:paragraph --><p>Hi</p><!-- /wp:paragraph -->' );
		$this->assertSame( $a, $b );
		$this->assertStringStartsWith( '1:', $a );
	}

	public function test_title_and_content_are_both_covered() {
		$base  = ContentHash::compute( 'Home', 'x' );
		$this->assertNotSame( $base, ContentHash::compute( 'Home ', 'x' ) );
		$this->assertNotSame( $base, ContentHash::compute( 'Home', 'y' ) );
		// The separator must not be forgeable by shifting bytes across the boundary.
		$this->assertNotSame( ContentHash::compute( 'ab', 'c' ), ContentHash::compute( 'a', 'bc' ) );
	}

	public function test_for_post_reads_the_persisted_row_not_the_input() {
		// WordPress normalizes `<img alt=""/>` to `<img alt="" />` on write
		// (docs/WORDPRESS_TRAPS.md). Hashing the input would mismatch forever.
		$input = '<!-- wp:image --><figure class="wp-block-image"><img src="http://e.test/a.png" alt=""/></figure><!-- /wp:image -->';
		$id    = self::factory()->post->create( [ 'post_type' => 'page', 'post_title' => 'T', 'post_content' => $input ] );

		$persisted = ContentHash::forPost( $id );

		$this->assertNotSame( ContentHash::compute( 'T', $input ), $persisted );
		$this->assertSame( $persisted, ContentHash::forPost( $id ), 'forPost must be stable across calls' );
	}

	public function test_for_post_ignores_a_stale_object_cache() {
		// The post factory generates a title when none is given, so read the
		// stored title rather than assuming one.
		$id    = self::factory()->post->create( [ 'post_type' => 'page', 'post_content' => 'one' ] );
		$title = get_post( $id )->post_title; // also warms the cache
		$GLOBALS['wpdb']->update( $GLOBALS['wpdb']->posts, [ 'post_content' => 'two' ], [ 'ID' => $id ] );

		$this->assertSame( ContentHash::compute( $title, 'two' ), ContentHash::forPost( $id ) );
	}

	public function test_matches_rejects_empty_and_foreign_versions() {
		$current = ContentHash::compute( 'A', 'B' );
		$this->assertTrue( ContentHash::matches( $current, $current ) );
		$this->assertFalse( ContentHash::matches( '', $current ) );
		$this->assertFalse( ContentHash::matches( '2:' . substr( $current, 2 ), $current ) );
		$this->assertFalse( ContentHash::matches( ContentHash::compute( 'A', 'C' ), $current ) );
	}

	public function test_for_post_returns_empty_string_for_a_missing_post() {
		$this->assertSame( '', ContentHash::forPost( 999999 ) );
	}
}
