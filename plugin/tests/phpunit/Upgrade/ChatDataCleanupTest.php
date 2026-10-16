<?php

use Pediment\Upgrade\ChatDataCleanup;

class ChatDataCleanupTest extends WP_UnitTestCase {

	/**
	 * WP test framework rewrites DROP TABLE to DROP TEMPORARY TABLE, so real
	 * leftover tables from earlier runs would survive. Drop them once, before
	 * the class's tests start: the rewrite filter is not attached yet, and a
	 * real DROP TABLE here cannot commit a test's transaction.
	 */
	public static function set_up_before_class(): void {
		parent::set_up_before_class();
		global $wpdb;
		foreach ( ChatDataCleanup::TABLES as $short ) {
			$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}{$short}" ); // phpcs:ignore WordPress.DB
		}
	}

	public function test_set_up_keeps_the_test_transaction_open(): void {
		global $wpdb;
		// MariaDB: an implicit commit (e.g. a real DROP TABLE) ends the transaction.
		$this->assertSame( '1', $wpdb->get_var( 'SELECT @@in_transaction' ) );
	}

	private function table_exists( string $short ): bool {
		global $wpdb;
		$wpdb->suppress_errors( true );
		$result = $wpdb->query( "SELECT 1 FROM {$wpdb->prefix}{$short} LIMIT 1" ); // phpcs:ignore WordPress.DB
		$wpdb->suppress_errors( false );
		return false !== $result;
	}

	private function seed_legacy_data(): void {
		global $wpdb;
		foreach ( ChatDataCleanup::TABLES as $short ) {
			$wpdb->query( "CREATE TABLE {$wpdb->prefix}{$short} ( id bigint(20) unsigned NOT NULL AUTO_INCREMENT, PRIMARY KEY (id) )" ); // phpcs:ignore WordPress.DB
			$wpdb->query( "INSERT INTO {$wpdb->prefix}{$short} () VALUES ()" ); // phpcs:ignore WordPress.DB
		}
		// The plugin's own plugins_loaded run has marked the test site done.
		delete_option( ChatDataCleanup::DONE_OPTION );
		update_option( 'pediment_ai_settings', array( 'api_key_encrypted' => 'x' ) );
		update_option( 'pediment_ai_rate_limits', array( 'compose' => 30 ) );
		update_option( 'pediment_ai_db_version', '3.10.0' );
		set_transient( 'pediment_ai_schema', 'x' );
		set_transient( 'pediment_ai_rl_1_compose', 3 );
		set_transient( 'pediment_ai_turn_token_42', 'x' );
		set_transient( 'pediment_ai_turn_input_42', 'x' );
		wp_schedule_single_event( time() + 60, 'pediment_ai_job_run' );
	}

	public function test_removes_tables_options_transients_and_cron(): void {
		$this->seed_legacy_data();
		$this->assertTrue( ChatDataCleanup::needed() );

		ChatDataCleanup::run();

		foreach ( ChatDataCleanup::TABLES as $short ) {
			$this->assertFalse( $this->table_exists( $short ), $short );
		}
		foreach ( ChatDataCleanup::OPTIONS as $option ) {
			$this->assertFalse( get_option( $option ), $option );
		}
		$this->assertFalse( get_transient( 'pediment_ai_schema' ) );
		$this->assertFalse( get_transient( 'pediment_ai_rl_1_compose' ) );
		$this->assertFalse( get_transient( 'pediment_ai_turn_token_42' ) );
		$this->assertFalse( get_transient( 'pediment_ai_turn_input_42' ) );
		$this->assertFalse( wp_next_scheduled( 'pediment_ai_job_run' ) );
		$this->assertFalse( ChatDataCleanup::needed() );
		$this->assertSame( '1', get_option( ChatDataCleanup::DONE_OPTION ) );
	}

	public function test_legacy_site_that_never_ran_cleanup_needs_it(): void {
		update_option( 'pediment_ai_db_version', '3.10.0' );
		delete_option( ChatDataCleanup::DONE_OPTION );
		$this->assertTrue( ChatDataCleanup::needed() );

		ChatDataCleanup::maybeRun();

		$this->assertFalse( get_option( 'pediment_ai_db_version' ) );
		$this->assertSame( '1', get_option( ChatDataCleanup::DONE_OPTION ) );
	}

	public function test_finished_cleanup_skips_the_option_lookups(): void {
		update_option( ChatDataCleanup::DONE_OPTION, '1', true );
		update_option( 'pediment_ai_db_version', '3.10.0' );

		$lookups = array();
		$spy     = static function ( $value, $option ) use ( &$lookups ) {
			$lookups[] = $option;
			return $value;
		};
		foreach ( ChatDataCleanup::OPTIONS as $option ) {
			add_filter( "option_{$option}", $spy, 10, 2 );
			add_filter( "default_option_{$option}", $spy, 10, 2 );
		}

		$this->assertFalse( ChatDataCleanup::needed() );
		$this->assertSame( array(), $lookups );
	}

	public function test_fresh_site_records_that_nothing_needs_cleaning(): void {
		delete_option( ChatDataCleanup::DONE_OPTION );
		foreach ( ChatDataCleanup::OPTIONS as $option ) {
			delete_option( $option );
		}

		ChatDataCleanup::maybeRun();

		$this->assertSame( '1', get_option( ChatDataCleanup::DONE_OPTION ) );
	}

	public function test_does_not_touch_non_chat_data(): void {
		update_option( 'pediment_form_retention_days', 30 );
		update_option( 'pediment_ai_db_version', '3.10.0' );
		ChatDataCleanup::run();
		$this->assertSame( 30, (int) get_option( 'pediment_form_retention_days' ) );
	}

	public function test_fresh_site_needs_nothing(): void {
		delete_option( ChatDataCleanup::DONE_OPTION );
		delete_option( 'pediment_ai_db_version' );
		delete_option( 'pediment_ai_settings' );
		delete_option( 'pediment_ai_rate_limits' );
		$this->assertFalse( ChatDataCleanup::needed() );
	}
}
