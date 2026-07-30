<?php
/**
 * Plugin Name:       Pediment
 * Plugin URI:        https://github.com/Bergert-Digital/pediment
 * Description:       The Pediment engine + AI: Gutenberg blocks, templates, and tokens, plus an AI composer that edits and refines pages with Claude.
 * x-release-please-start-version
 * Version:           2.4.1
 * x-release-please-end
 * Requires at least: 6.4
 * Requires PHP:      8.1
 * Author:            Jonas Bergert
 * Author URI:        https://bergert.digital
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       pediment
 *
 * @package Pediment
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'PEDIMENT_AI_VERSION', '2.4.1' ); // Bumped on release by release-please (x-release-please-version).
define( 'PEDIMENT_AI_PLUGIN_FILE', __FILE__ );
define( 'PEDIMENT_AI_PLUGIN_DIR', __DIR__ );
define( 'PEDIMENT_AI_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

if ( file_exists( __DIR__ . '/vendor/autoload.php' ) ) {
	require __DIR__ . '/vendor/autoload.php';
}

// One-click updates from GitHub Releases (no manual zip uploads).
if ( class_exists( \Pediment\Updater::class ) ) {
	\Pediment\Updater::register( PEDIMENT_AI_PLUGIN_FILE );
}

require_once __DIR__ . '/src/Schema/tables.php';
register_activation_hook( PEDIMENT_AI_PLUGIN_FILE, 'pediment_ai_install_tables' );
add_action(
	'plugins_loaded',
	static function () {
		if ( get_option( 'pediment_ai_db_version' ) !== PEDIMENT_AI_VERSION ) {
			pediment_ai_install_tables();
		}
	},
	5
);

if ( defined( 'WP_CLI' ) && WP_CLI ) {
	require_once __DIR__ . '/wp-cli/DumpSchemaCommand.php';
	\WP_CLI::add_command( 'pediment dump-schema', \Pediment\Cli\DumpSchemaCommand::class );
}

add_action(
	'plugins_loaded',
	static function () {
		if ( class_exists( '\\Pediment\\Bootstrap' ) ) {
			( new \Pediment\Bootstrap() )->register();
		}
	}
);
