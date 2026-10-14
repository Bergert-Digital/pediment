<?php
/**
 * Bootstrap class for the Pediment plugin.
 *
 * @package Pediment
 */

declare(strict_types=1);

namespace Pediment;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Wires the plugin's hooks.
 */
final class Bootstrap {
	/**
	 * Registers hooks. Called on plugins_loaded.
	 *
	 * @return void
	 */
	public function register(): void {
		\Pediment\Templates\Registrar::register();
		\Pediment\Tokens\Injector::register();
		\Pediment\Design\DefaultStyles::register();
		\Pediment\Seeder\PostTypes::register();
	}
}
