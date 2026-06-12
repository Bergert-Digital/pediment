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
}
