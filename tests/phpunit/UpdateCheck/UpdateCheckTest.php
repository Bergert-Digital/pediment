<?php

class UpdateCheckTest extends WP_UnitTestCase {

	public function tear_down() {
		delete_transient( 'pediment_update_check_' . get_current_user_id() );
		unset( $_REQUEST['_wpnonce'] );
		$GLOBALS['current_screen'] = null;
		parent::tear_down();
	}

	/**
	 * Fake PUC checker exposing exactly the API surface inc/update-check.php uses.
	 */
	private function fake_checker( $update = null, array $errors = array(), int $last_check = 0 ): object {
		return new class( $update, $errors, $last_check ) {
			public function __construct( private $update, private array $errors, private int $last_check ) {}

			public function checkForUpdates() {
				return $this->update;
			}

			public function getLastRequestApiErrors(): array {
				return $this->errors;
			}

			public function getUpdateState(): object {
				return new class( $this->last_check ) {
					public function __construct( private int $last_check ) {}

					public function getLastCheck(): int {
						return $this->last_check;
					}
				};
			}
		};
	}

	private function entry( object $checker ): array {
		return array(
			'slug'    => 'pediment',
			'name'    => 'Pediment',
			'checker' => $checker,
		);
	}

	private function register_fake_checker( $update = null, array $errors = array() ): void {
		$entry = $this->entry( $this->fake_checker( $update, $errors ) );
		add_filter(
			'pediment_update_checkers',
			static function ( array $checkers ) use ( $entry ): array {
				$checkers[] = $entry;
				return $checkers;
			}
		);
	}

	public function test_update_checkers_returns_empty_array_by_default() {
		$this->assertSame( array(), pediment_update_checkers() );
	}

	public function test_update_checkers_returns_registered_entries() {
		$this->register_fake_checker();
		$checkers = pediment_update_checkers();
		$this->assertCount( 1, $checkers );
		$this->assertSame( 'pediment', $checkers[0]['slug'] );
	}

	public function test_run_update_check_reports_available_update() {
		$result = pediment_run_update_check(
			$this->entry( $this->fake_checker( (object) array( 'version' => '9.9.9' ) ) )
		);
		$this->assertSame( 'update', $result['status'] );
		$this->assertSame( '9.9.9', $result['new_version'] );
		$this->assertSame( wp_get_theme( 'pediment' )->get( 'Version' ), $result['installed'] );
	}

	public function test_run_update_check_reports_up_to_date() {
		$result = pediment_run_update_check( $this->entry( $this->fake_checker( null, array() ) ) );
		$this->assertSame( 'current', $result['status'] );
		$this->assertSame( '', $result['new_version'] );
	}

	public function test_run_update_check_reports_error_when_api_failed() {
		$result = pediment_run_update_check(
			$this->entry( $this->fake_checker( null, array( array( 'error' => 'request failed' ) ) ) )
		);
		$this->assertSame( 'error', $result['status'] );
	}

	public function test_run_update_check_reports_error_when_checker_throws() {
		$checker = new class() {
			public function checkForUpdates() {
				throw new RuntimeException( 'network down' );
			}

			public function getLastRequestApiErrors(): array {
				return array();
			}
		};
		$result = pediment_run_update_check( $this->entry( $checker ) );
		$this->assertSame( 'error', $result['status'] );
	}

	public function test_section_renders_nothing_without_checkers() {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		ob_start();
		pediment_render_update_check_section();
		$this->assertSame( '', ob_get_clean() );
	}

	public function test_section_renders_nothing_without_capability() {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'subscriber' ) ) );
		$this->register_fake_checker();
		ob_start();
		pediment_render_update_check_section();
		$this->assertSame( '', ob_get_clean() );
	}

	public function test_section_renders_button_for_admins() {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		$this->register_fake_checker();
		ob_start();
		pediment_render_update_check_section();
		$html = ob_get_clean();
		$this->assertStringContainsString( 'pediment_check_theme_updates', $html );
		$this->assertStringContainsString( 'Pediment', $html );
		$this->assertStringContainsString( 'not checked yet', $html );
		$this->assertStringContainsString( 'admin-post.php', $html );
		$this->assertStringContainsString( '_wpnonce', $html );
	}

	public function test_section_renders_last_checked_time() {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		$entry = $this->entry( $this->fake_checker( null, array(), time() - HOUR_IN_SECONDS ) );
		add_filter(
			'pediment_update_checkers',
			static function ( array $checkers ) use ( $entry ): array {
				$checkers[] = $entry;
				return $checkers;
			}
		);
		ob_start();
		pediment_render_update_check_section();
		$html = ob_get_clean();
		$this->assertStringContainsString( 'last checked', $html );
		$this->assertStringNotContainsString( 'not checked yet', $html );
	}

	public function test_section_is_hooked_to_core_upgrade_preamble() {
		$this->assertNotFalse( has_action( 'core_upgrade_preamble', 'pediment_render_update_check_section' ) );
	}

	public function test_store_results_writes_transient_for_current_user() {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		$this->register_fake_checker( (object) array( 'version' => '9.9.9' ) );
		pediment_store_update_check_results();
		$results = get_transient( 'pediment_update_check_' . get_current_user_id() );
		$this->assertIsArray( $results );
		$this->assertCount( 1, $results );
		$this->assertSame( 'update', $results[0]['status'] );
		$this->assertSame( '9.9.9', $results[0]['new_version'] );
	}

	public function test_store_results_writes_no_transient_without_checkers() {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		pediment_store_update_check_results();
		$this->assertFalse( get_transient( 'pediment_update_check_' . get_current_user_id() ) );
	}

	public function test_handler_dies_on_invalid_nonce() {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		$_REQUEST['_wpnonce'] = 'invalid';
		$this->expectException( 'WPDieException' );
		pediment_handle_update_check();
	}

	public function test_handler_dies_without_capability() {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'subscriber' ) ) );
		$_REQUEST['_wpnonce'] = wp_create_nonce( 'pediment_check_theme_updates' );
		$this->expectException( 'WPDieException' );
		pediment_handle_update_check();
	}

	public function test_handler_is_hooked_to_admin_post() {
		$this->assertNotFalse( has_action( 'admin_post_pediment_check_theme_updates', 'pediment_handle_update_check' ) );
	}
}
