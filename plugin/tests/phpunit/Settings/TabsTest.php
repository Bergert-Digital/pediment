<?php

class TabsTest extends WP_UnitTestCase {
	public function set_up(): void {
		parent::set_up();
		$GLOBALS['pediment_settings_tabs'] = array();
	}

	public function tear_down(): void {
		unset( $GLOBALS['pediment_settings_tabs'] );
		parent::tear_down();
	}

	public function test_get_tabs_sorts_by_priority() {
		pediment_settings_register_tab( 'b', 'B', '__return_null', 30 );
		pediment_settings_register_tab( 'a', 'A', '__return_null', 10 );
		pediment_settings_register_tab( 'c', 'C', '__return_null', 20 );
		$this->assertSame( array( 'a', 'c', 'b' ), array_keys( pediment_settings_get_tabs() ) );
	}

	public function test_register_same_id_overwrites() {
		pediment_settings_register_tab( 'x', 'First', '__return_null', 10 );
		pediment_settings_register_tab( 'x', 'Second', '__return_null', 10 );
		$tabs = pediment_settings_get_tabs();
		$this->assertCount( 1, $tabs );
		$this->assertSame( 'Second', $tabs['x']['label'] );
	}

	public function test_resolve_active_tab_prefers_requested_when_known() {
		$tabs = array( 'a' => array(), 'b' => array() );
		$this->assertSame( 'b', pediment_settings_resolve_active_tab( 'b', $tabs ) );
	}

	public function test_resolve_active_tab_falls_back_to_first_when_unknown() {
		$tabs = array( 'a' => array(), 'b' => array() );
		$this->assertSame( 'a', pediment_settings_resolve_active_tab( 'nope', $tabs ) );
		$this->assertSame( 'a', pediment_settings_resolve_active_tab( '', $tabs ) );
	}

	public function test_page_url_includes_page_and_optional_tab() {
		$this->assertStringContainsString( 'page=' . PEDIMENT_SETTINGS_PAGE, pediment_settings_page_url() );
		$url = pediment_settings_page_url( 'secrets' );
		$this->assertStringContainsString( 'page=' . PEDIMENT_SETTINGS_PAGE, $url );
		$this->assertStringContainsString( 'tab=secrets', $url );
	}
}
