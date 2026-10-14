<?php
/**
 * Runs when the plugin is deleted from wp-admin. Removes the retired AI chat's
 * data. Forms submissions and seeded content belong to the site and stay.
 *
 * @package Pediment
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

require_once __DIR__ . '/vendor/autoload.php';

\Pediment\Upgrade\ChatDataCleanup::run();
