<?php
// plugin/tests/phpunit/Seeder/PostTypesTest.php

use Pediment\Seeder\PostTypes;

class PostTypesTest extends WP_UnitTestCase {

	public function tear_down(): void {
		remove_all_filters( 'pediment_seed_manifest' );
		unregister_post_type( 'listing' );
		parent::tear_down();
	}

	public function test_manifest_post_types_are_registered_with_block_editor_support() {
		add_filter(
			'pediment_seed_manifest',
			static fn() => [ 'post_types' => [ 'listing' => [ 'label' => 'Listings', 'has_archive' => true, 'rewrite' => [ 'slug' => 'listings' ] ] ] ]
		);

		PostTypes::registerFromManifest();

		$type = get_post_type_object( 'listing' );
		$this->assertNotNull( $type );
		$this->assertTrue( $type->show_in_rest, 'CPT entries must be editable in Gutenberg' );
		$this->assertSame( 'Listings', $type->label );
	}

	public function test_no_manifest_is_not_an_error() {
		PostTypes::registerFromManifest();

		$this->assertNull( get_post_type_object( 'listing' ) );
	}

	public function test_an_invalid_manifest_never_takes_the_site_down() {
		add_filter( 'pediment_seed_manifest', static fn() => [ 'pages' => [ 'x' => [] ] ] ); // missing title

		PostTypes::registerFromManifest();

		$this->assertTrue( true, 'a broken manifest must not fatal on every request' );
	}
}
