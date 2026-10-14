<?php

class ChatRemovedTest extends WP_UnitTestCase {

	public function test_chat_routes_are_gone_and_forms_route_stays(): void {
		$routes = array_keys( rest_get_server()->get_routes() );
		$this->assertSame( array(), array_values( array_filter( $routes, static fn( string $r ): bool => str_starts_with( $r, '/pediment/v1/chat' ) ) ) );
		$this->assertContains( '/pediment/v1/forms', $routes );
	}

	public function test_ai_settings_tab_is_gone_and_the_rest_remain(): void {
		unset( $GLOBALS['pediment_settings_tabs'] );
		do_action( 'admin_menu' );
		$this->assertSame( array( 'general', 'seed', 'destinations', 'secrets', 'design' ), array_keys( pediment_settings_get_tabs() ) );
	}

	public function test_chat_classes_and_install_function_are_gone(): void {
		$this->assertFalse( class_exists( '\Pediment\Chat\TurnRunner' ) );
		$this->assertFalse( class_exists( '\Pediment\Anthropic\Client' ) );
		$this->assertFalse( class_exists( '\Pediment\Settings\Page' ) );
		$this->assertFalse( function_exists( 'pediment_ai_install_tables' ) );
		$this->assertFalse( has_filter( 'pediment_ai_provider' ) );
	}
}
