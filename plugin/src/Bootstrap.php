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
		add_action( 'init', array( \Pediment\Design\SiteStyles::class, 'registerPostType' ) );
		\Pediment\Design\SiteLayer::register(
			new \Pediment\Design\SiteLayer( new \Pediment\Design\SiteStyles( new \Pediment\Design\StyleValidator() ) )
		);
		\Pediment\Design\AdminTab::register();
		\Pediment\Content\InvalidBlockLog::register();
		\Pediment\Content\LegacyBlocks::register();
		\Pediment\Convert\AdminTab::register();
		\Pediment\Abilities\Registrar::register();
		\Pediment\Mcp\Server::register();
	}
}
