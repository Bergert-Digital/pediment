<?php
/**
 * Plugin Name:       Pediment
 * Plugin URI:        https://github.com/Bergert-Digital/pediment
 * Description:       The Pediment site engine: Gutenberg blocks, templates, design tokens, forms and seeding for client block themes.
 * x-release-please-start-version
 * Version:           4.4.0
 * x-release-please-end
 * Requires at least: 6.9
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

define( 'PEDIMENT_AI_VERSION', '4.4.0' ); // Bumped on release by release-please (x-release-please-version).
define( 'PEDIMENT_AI_PLUGIN_FILE', __FILE__ );
define( 'PEDIMENT_AI_PLUGIN_DIR', __DIR__ );
define( 'PEDIMENT_AI_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

if ( file_exists( __DIR__ . '/vendor/autoload.php' ) ) {
	require __DIR__ . '/vendor/autoload.php';
}

require_once PEDIMENT_AI_PLUGIN_DIR . '/inc/settings-page.php';
require_once PEDIMENT_AI_PLUGIN_DIR . '/inc/seeding-admin.php';
require_once PEDIMENT_AI_PLUGIN_DIR . '/inc/forms.php';
require_once PEDIMENT_AI_PLUGIN_DIR . '/inc/forms-storage.php';
require_once PEDIMENT_AI_PLUGIN_DIR . '/inc/forms-secrets.php';
require_once PEDIMENT_AI_PLUGIN_DIR . '/inc/forms-ssrf.php';
require_once PEDIMENT_AI_PLUGIN_DIR . '/inc/forms-template.php';
require_once PEDIMENT_AI_PLUGIN_DIR . '/inc/forms-presets.php';
require_once PEDIMENT_AI_PLUGIN_DIR . '/inc/forms-destinations.php';
require_once PEDIMENT_AI_PLUGIN_DIR . '/inc/forms-delivery.php';
require_once PEDIMENT_AI_PLUGIN_DIR . '/inc/forms-settings.php';

// Blocks + presentation modules moved from the theme (Task 5 of the
// plugin-absorbs-theme migration): the 7 pediment/* blocks now register from
// PEDIMENT_AI_PLUGIN_DIR . '/build/blocks' regardless of which theme is active.
require_once PEDIMENT_AI_PLUGIN_DIR . '/inc/register-blocks.php';
require_once PEDIMENT_AI_PLUGIN_DIR . '/inc/icons.php';
require_once PEDIMENT_AI_PLUGIN_DIR . '/inc/block-styles.php';
require_once PEDIMENT_AI_PLUGIN_DIR . '/inc/hero-variants.php';
require_once PEDIMENT_AI_PLUGIN_DIR . '/inc/layout-variations.php';
require_once PEDIMENT_AI_PLUGIN_DIR . '/inc/nav-active.php';
require_once PEDIMENT_AI_PLUGIN_DIR . '/inc/nav-language.php';
require_once PEDIMENT_AI_PLUGIN_DIR . '/inc/mega-menu.php';

// Polylang cannot be told wp_navigation is translatable by clicking anything
// in its UI (step 4 of the language-provider migration); this filter is the
// only path, and it is a no-op when Polylang is inactive.
require_once PEDIMENT_AI_PLUGIN_DIR . '/inc/polylang-compat.php';

// The WPML analogue of polylang-compat.php: wp_navigation translatable,
// wp_template_part shared. No-op when WPML is inactive.
require_once PEDIMENT_AI_PLUGIN_DIR . '/inc/wpml-compat.php';

// Templates, patterns (incl. the footer pattern), the header-seeding
// bootstrap, and global CSS/JS enqueues moved from the theme (Task 6 of the
// plugin-absorbs-theme migration): the theme.json tokens (Pediment\Tokens\Injector)
// and the pediment//* templates (Pediment\Templates\Registrar) are wired from
// Bootstrap::register() below since they're autoloaded classes, not procedural
// includes.
require_once PEDIMENT_AI_PLUGIN_DIR . '/inc/patterns.php';
require_once PEDIMENT_AI_PLUGIN_DIR . '/inc/bootstrap.php';
require_once PEDIMENT_AI_PLUGIN_DIR . '/inc/assets.php';

// A client theme can already be active when Pediment is installed. Seed its
// editable header then as well as on later theme switches.
register_activation_hook( PEDIMENT_AI_PLUGIN_FILE, 'pediment_bootstrap' );

// Forms cleanup cron: was wired off theme-switch hooks when the forms engine
// lived in the theme; now that pediment_form_schedule_cleanup() /
// pediment_form_unschedule_cleanup() ship in the plugin (inc/forms-storage.php
// above), the wiring moves here too. Hook names unchanged for now (still
// theme-switch, not plugin activation) to keep this move load-path-only.
add_action( 'after_switch_theme', 'pediment_form_schedule_cleanup' );
add_action( 'switch_theme', 'pediment_form_unschedule_cleanup' );

// One-click updates from GitHub Releases (no manual zip uploads).
if ( class_exists( \Pediment\Updater::class ) ) {
	\Pediment\Updater::register( PEDIMENT_AI_PLUGIN_FILE );
}

// Pediment 4 removed the AI chat; drop its leftover data once per site.
add_action(
	'plugins_loaded',
	array( \Pediment\Upgrade\ChatDataCleanup::class, 'maybeRun' ),
	5
);

// Pediment 4 seeds bootstrap-only; drop the 3.x content-arbitration meta once per site.
add_action( 'admin_init', array( \Pediment\Upgrade\SeedHashCleanup::class, 'maybeRun' ) );

if ( defined( 'WP_CLI' ) && WP_CLI ) {
	require_once __DIR__ . '/wp-cli/SeedCommand.php';
	\WP_CLI::add_command( 'pediment seed', \Pediment\Cli\SeedCommand::class );

	require_once __DIR__ . '/wp-cli/DesignCommand.php';
	\WP_CLI::add_command( 'pediment design', \Pediment\Cli\DesignCommand::class );

	require_once __DIR__ . '/wp-cli/AdoptCommand.php';
	\WP_CLI::add_command( 'pediment adopt', \Pediment\Cli\AdoptCommand::class );

	require_once __DIR__ . '/wp-cli/ClaimCommand.php';
	\WP_CLI::add_command( 'pediment claim', \Pediment\Cli\ClaimCommand::class );

	require_once __DIR__ . '/wp-cli/LanguagesCommand.php';
	\WP_CLI::add_command( 'pediment languages', \Pediment\Cli\LanguagesCommand::class );

	require_once __DIR__ . '/wp-cli/ConvertCommand.php';
	\WP_CLI::add_command( 'pediment convert', \Pediment\Cli\ConvertCommand::class );
}

add_action(
	'plugins_loaded',
	static function () {
		if ( class_exists( '\\Pediment\\Bootstrap' ) ) {
			( new \Pediment\Bootstrap() )->register();
		}
	}
);
