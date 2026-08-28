<?php

class HarnessTest extends WpmlTestCase {

	public function test_wpml_is_loaded() {
		$this->assertTrue( defined( 'ICL_SITEPRESS_VERSION' ) );
	}

	public function test_two_languages_are_active() {
		$active = apply_filters( 'wpml_active_languages', null );
		$this->assertIsArray( $active );
		$this->assertArrayHasKey( 'en', $active );
		$this->assertArrayHasKey( 'de', $active );
	}

	public function test_default_language_is_en() {
		$this->assertSame( 'en', apply_filters( 'wpml_default_language', null ) );
	}
}
