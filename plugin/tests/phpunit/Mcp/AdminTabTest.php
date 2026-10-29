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

	public function test_shared_config_keeps_the_secret_out(): void {
		$c     = AdminTab::connection( $this->admin, 'secret' );
		$basic = base64_encode( 'agentowner:secret' ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
		$json  = json_decode( $c['mcpJson'], true );

		$this->assertStringNotContainsString( $basic, $c['mcpJson'] );
		$server = $json['mcpServers'][ $c['server'] ];
		$this->assertSame( 'http', $server['type'] );
		$this->assertSame( $c['url'], $server['url'] );
		$this->assertMatchesRegularExpression( '/^Basic \$\{([A-Z0-9_]+)\}$/', $server['headers']['Authorization'] );
		preg_match( '/\$\{([A-Z0-9_]+)\}/', $server['headers']['Authorization'], $m );
		$this->assertSame( sprintf( 'export %s="%s"', $m[1], $basic ), $c['env'] );
	}

	public function test_render_shows_the_setup_once_after_creating(): void {
		$this->post( array( 'pediment_mcp_name' => 'Render test' ) );
		ob_start();
		AdminTab::render();
		$html = ob_get_clean();

		$this->assertStringContainsString( 'claude mcp add', $html );
		$this->assertStringContainsString( esc_html( rest_url( 'pediment/v1/mcp' ) ), $html );

		$_POST = array();
		ob_start();
		AdminTab::render();
		$html = ob_get_clean();
		$this->assertStringNotContainsString( 'claude mcp add', $html );
		$this->assertStringContainsString( 'name="pediment_mcp_action"', $html );
	}
}
