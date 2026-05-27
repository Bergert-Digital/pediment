<?php

class ListableFilterTest extends WP_UnitTestCase {
	public function test_mega_menu_is_a_listable_navigation_block() {
		$blocks = apply_filters( 'block_core_navigation_listable_blocks', array() );
		$this->assertContains( 'pediment/mega-menu', $blocks );
	}

	public function test_filter_preserves_existing_entries() {
		$blocks = apply_filters( 'block_core_navigation_listable_blocks', array( 'core/site-title' ) );
		$this->assertContains( 'core/site-title', $blocks );
		$this->assertContains( 'pediment/mega-menu', $blocks );
	}
}
