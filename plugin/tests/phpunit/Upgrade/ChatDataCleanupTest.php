<?php

use Pediment\Upgrade\ChatDataCleanup;

class ChatDataCleanupTest extends WP_UnitTestCase {

	public function set_up(): void {
		parent::set_up();
		global $wpdb;
		// WP test framework rewrites DROP TABLE to DROP TEMPORARY TABLE, so real
		// leftover tables from prior test runs survive. Remove them before each test
		// to ensure isolation. Temporarily disable the rewrite filter.
		remove_filter( 'query', array( $this, '_drop_temporary_tables' ) );
		foreach ( ChatDataCleanup::TABLES as $short ) {
			$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}{$short}" ); // phpcs:ignore WordPress.DB
		}
		add_filter( 'query', array( $this, '_drop_temporary_tables' ) );
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
	}

	public function test_does_not_touch_non_chat_data(): void {
		update_option( 'pediment_form_retention_days', 30 );
		update_option( 'pediment_ai_db_version', '3.10.0' );
		ChatDataCleanup::run();
		$this->assertSame( 30, (int) get_option( 'pediment_form_retention_days' ) );
	}

	public function test_fresh_site_needs_nothing(): void {
		delete_option( 'pediment_ai_db_version' );
		delete_option( 'pediment_ai_settings' );
		delete_option( 'pediment_ai_rate_limits' );
		$this->assertFalse( ChatDataCleanup::needed() );
	}
}
