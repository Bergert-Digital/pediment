<?php

class SmokeTest extends WP_UnitTestCase {
	public function test_wordpress_is_loaded() {
		$this->assertTrue( function_exists( 'wp_get_theme' ) );
	}

	public function test_pediment_theme_is_active() {
		$this->assertSame( 'pediment', wp_get_theme()->get_stylesheet() );
	}
}
