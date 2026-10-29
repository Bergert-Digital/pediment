<?php

use Pediment\Mcp\AdminTab;

class AdminTabTest extends WP_UnitTestCase {

	private WP_User $admin;

	public function set_up(): void {
		parent::set_up();
		$this->admin = self::factory()->user->create_and_get(
			array(
				'role'       => 'administrator',
				'user_login' => 'agentowner',
			)
		);
		wp_set_current_user( $this->admin->ID );
		add_filter( 'wp_is_application_passwords_available', '__return_true' );
		$_POST = array();
	}

	public function tear_down(): void {
		$_POST = array();
		remove_filter( 'wp_is_application_passwords_available', '__return_true' );
		parent::tear_down();
	}

	private function post( array $fields ): void {
		$_POST = array_merge(
			array(
				'pediment_mcp_action' => 'connect',
				'_wpnonce'            => wp_create_nonce( AdminTab::ACTION ),
			),
			$fields
		);
	}

	public function test_registers_the_tab_on_admin_menu(): void {
		$GLOBALS['pediment_settings_tabs'] = array();
		do_action( 'admin_menu' );
		$this->assertArrayHasKey( 'mcp', pediment_settings_get_tabs() );
		unset( $GLOBALS['pediment_settings_tabs'] );
	}

	public function test_does_nothing_without_the_form(): void {
		$this->assertNull( AdminTab::handlePost( $this->admin ) );
		$this->assertSame( array(), WP_Application_Passwords::get_user_application_passwords( $this->admin->ID ) );
	}

	public function test_refuses_an_expired_nonce(): void {
		$this->post( array( '_wpnonce' => 'stale' ) );
		$result = AdminTab::handlePost( $this->admin );
		$this->assertWPError( $result );
		$this->assertSame( 'pediment_mcp_nonce', $result->get_error_code() );
		$this->assertSame( array(), WP_Application_Passwords::get_user_application_passwords( $this->admin->ID ) );
	}

	public function test_refuses_when_application_passwords_are_off(): void {
		remove_filter( 'wp_is_application_passwords_available', '__return_true' );
		add_filter( 'wp_is_application_passwords_available', '__return_false' );
		$this->post( array() );
		$result = AdminTab::handlePost( $this->admin );
		remove_filter( 'wp_is_application_passwords_available', '__return_false' );
		$this->assertWPError( $result );
		$this->assertSame( 'pediment_mcp_forbidden', $result->get_error_code() );
	}

	public function test_creates_a_password_that_authenticates(): void {
		$this->post( array( 'pediment_mcp_name' => 'Claude on my laptop' ) );
		$result = AdminTab::handlePost( $this->admin );

		$this->assertIsArray( $result );
		$this->assertSame( 'Claude on my laptop', $result['name'] );
		$stored = WP_Application_Passwords::get_user_application_passwords( $this->admin->ID );
		$this->assertCount( 1, $stored );
		$this->assertSame( 'Claude on my laptop', $stored[0]['name'] );

		add_filter( 'application_password_is_api_request', '__return_true' );
		$authenticated = wp_authenticate_application_password( null, 'agentowner', $result['password'] );
		remove_filter( 'application_password_is_api_request', '__return_true' );
		$this->assertInstanceOf( WP_User::class, $authenticated );
		$this->assertSame( $this->admin->ID, $authenticated->ID );
	}

	public function test_empty_name_gets_a_default(): void {
		$this->post( array( 'pediment_mcp_name' => '  ' ) );
		$result = AdminTab::handlePost( $this->admin );
		$this->assertIsArray( $result );
		$this->assertStringStartsWith( 'Pediment MCP ', $result['name'] );
	}

	public function test_connection_holds_url_and_basic_credentials(): void {
		$c     = AdminTab::connection( $this->admin, 'abcd efgh' );
		$basic = base64_encode( 'agentowner:abcd efgh' ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode

		$this->assertSame( rest_url( 'pediment/v1/mcp' ), $c['url'] );
		$this->assertSame( 'Authorization: Basic ' . $basic, $c['header'] );
		$this->assertStringStartsWith( 'claude mcp add --transport http --scope user ' . $c['server'] . ' ', $c['command'] );
		$this->assertStringContainsString( $basic, $c['command'] );
		$this->assertMatchesRegularExpression( '/^pediment-[a-z0-9-]+$/', $c['server'] );
	}

	public function test_codex_config_sends_basic_auth_as_a_header(): void {
		$c     = AdminTab::connection( $this->admin, 'secret' );
		$basic = base64_encode( 'agentowner:secret' ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode

		$this->assertSame(
			"[mcp_servers.{$c['server']}]\nurl = \"{$c['url']}\"\nhttp_headers = { \"Authorization\" = \"Basic {$basic}\" }",
			$c['codex']
		);
	}

	public function test_render_shows_the_setup_once_after_creating(): void {
		$this->post( array( 'pediment_mcp_name' => 'Render test' ) );
		ob_start();
		AdminTab::render();
		$html = ob_get_clean();

		$this->assertStringContainsString( 'claude mcp add', $html );
		$this->assertStringContainsString( '[mcp_servers.', $html );
		$this->assertStringContainsString( 'href="#pediment-mcp-codex"', $html );
		$this->assertStringNotContainsString( '.mcp.json', $html );
		$this->assertStringContainsString( '<summary', $html );
		$this->assertStringContainsString( 'Connect another agent', $html );
		$this->assertStringNotContainsString( 'Connect an agent<', $html );
		$this->assertStringContainsString( esc_html( rest_url( 'pediment/v1/mcp' ) ), $html );

		$_POST = array();
		ob_start();
		AdminTab::render();
		$html = ob_get_clean();
		$this->assertStringNotContainsString( 'claude mcp add', $html );
		$this->assertStringContainsString( 'Connect an agent<', $html );
		$this->assertStringNotContainsString( '<details', $html );
		$this->assertStringContainsString( 'name="pediment_mcp_action"', $html );
	}
}
