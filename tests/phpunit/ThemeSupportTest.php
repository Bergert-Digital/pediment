<?php

class ThemeSupportTest extends WP_UnitTestCase {
	public function test_custom_logo_is_registered_with_flex_dimensions() {
		$this->assertTrue(
			current_theme_supports( 'custom-logo' ),
			'Theme must declare support for custom-logo.'
		);

		$args = get_theme_support( 'custom-logo' );
		$this->assertIsArray( $args );
		$this->assertIsArray( $args[0] );
		$this->assertTrue( $args[0]['flex-width'] ?? false, 'custom-logo must allow flex width.' );
		$this->assertTrue( $args[0]['flex-height'] ?? false, 'custom-logo must allow flex height.' );
		$this->assertContains(
			'site-title',
			$args[0]['header-text'] ?? array(),
			'header-text must include site-title.'
		);
		$this->assertContains(
			'site-description',
			$args[0]['header-text'] ?? array(),
			'header-text must include site-description.'
		);
	}
}
