<?php

use Pediment\Brand;

class StorageTest extends WP_UnitTestCase {
	public function set_up(): void {
		parent::set_up();
		delete_option( Brand::OPTION );
	}

	public function test_get_returns_default_for_missing_key() {
		$this->assertSame( '', Brand::get( 'brand_name', '' ) );
		$this->assertSame( 'fallback', Brand::get( 'no_such_key', 'fallback' ) );
	}

	public function test_set_persists_value() {
		Brand::set( 'brand_name', 'Acme' );
		$this->assertSame( 'Acme', Brand::get( 'brand_name' ) );
	}

	public function test_all_merges_with_defaults() {
		Brand::set( 'brand_name', 'Acme' );
		$all = Brand::all();
		$this->assertSame( 'Acme', $all['brand_name'] );
		$this->assertArrayHasKey( 'contact_email', $all );
		$this->assertArrayHasKey( 'social_links', $all );
		$this->assertIsArray( $all['social_links'] );
	}

	public function test_set_social_links_array() {
		Brand::set(
			'social_links',
			array(
				array( 'platform' => 'twitter', 'url' => 'https://x.com/acme' ),
			)
		);
		$links = Brand::get( 'social_links' );
		$this->assertCount( 1, $links );
		$this->assertSame( 'twitter', $links[0]['platform'] );
	}

	public function test_all_includes_filter_added_defaults() {
		$cb = static function ( $fields ) {
			$fields['legal_page_id'] = array(
				'label'   => 'Legal page',
				'section' => 'identity',
				'type'    => 'integer',
				'default' => 42,
			);
			return $fields;
		};
		add_filter( 'pediment_brand_fields', $cb );

		$all = \Pediment\Brand::all();
		$this->assertArrayHasKey( 'legal_page_id', $all );
		$this->assertSame( 42, $all['legal_page_id'] );

		remove_filter( 'pediment_brand_fields', $cb );
	}
}
