<?php
/**
 * Removes the data the retired AI chat (Pediment ≤ 3.x) left on a site.
 *
 * @package Pediment
 */

declare(strict_types=1);

namespace Pediment\Upgrade;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Idempotent: `needed()` is true while any chat option remains, so the routine
 * runs once per site and never again.
 */
final class ChatDataCleanup {

	public const TABLES = array(
		'pediment_ai_jobs',
		'pediment_ai_usage',
		'pediment_ai_chat_conversations',
		'pediment_ai_chat_messages',
		'pediment_ai_chat_attachments',
	);

	public const OPTIONS = array(
		'pediment_ai_settings',
		'pediment_ai_rate_limits',
		'pediment_ai_db_version',
	);

	private const TRANSIENT_PREFIXES = array(
		'pediment_ai_schema',
		'pediment_ai_rl_',
		'pediment_ai_turn_token_',
		'pediment_ai_turn_input_',
	);

	/**
	 * Whether chat data is still present.
	 */
	public static function needed(): bool {
		foreach ( self::OPTIONS as $option ) {
			if ( false !== get_option( $option ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Drops chat tables, options, transients and cron events.
	 */
	public static function run(): void {
		global $wpdb;

		foreach ( self::TABLES as $short ) {
			$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}{$short}" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery.SchemaChange
		}

		foreach ( self::TRANSIENT_PREFIXES as $prefix ) {
			$like = $wpdb->esc_like( '_transient_' . $prefix ) . '%';
			$keys = $wpdb->get_col( $wpdb->prepare( "SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE %s", $like ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			foreach ( $keys as $key ) {
				delete_transient( substr( (string) $key, strlen( '_transient_' ) ) );
			}
		}

		wp_clear_scheduled_hook( 'pediment_ai_job_run' );

		foreach ( self::OPTIONS as $option ) {
			delete_option( $option );
		}
	}
}
