<?php

class PatternsTest extends WP_UnitTestCase {
	public function test_patterns_are_registered() {
		do_action( 'init' );
		$registry = WP_Block_Patterns_Registry::get_instance();
		$this->assertTrue( $registry->is_registered( 'pediment/hero-cta-faq' ) );
		$this->assertTrue( $registry->is_registered( 'pediment/prose-article' ) );
		$this->assertTrue( $registry->is_registered( 'pediment/contact-page' ) );
	}

	public function test_pattern_category_is_registered() {
		do_action( 'init' );
		$cats  = WP_Block_Pattern_Categories_Registry::get_instance()->get_all_registered();
		$slugs = wp_list_pluck( $cats, 'name' );
		$this->assertContains( 'pediment', $slugs );
	}
}
