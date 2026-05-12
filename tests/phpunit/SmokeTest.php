<?php

class SmokeTest extends WP_UnitTestCase {
	public function test_wordpress_is_loaded() {
		$this->assertTrue( function_exists( 'wp_get_theme' ) );
	}

	public function test_starter_theme_is_active() {
		$this->assertSame( 'wp-starter-theme', wp_get_theme()->get_stylesheet() );
	}
}
