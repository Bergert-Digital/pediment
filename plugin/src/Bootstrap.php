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
 * Wires the plugin's hooks, REST routes, and settings page.
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
		\Pediment\Seeder\PostTypes::register();

		add_filter( 'register_block_type_args', static function ( $args ) {
			\Pediment\Anthropic\SchemaBuilder::invalidate();
			return $args;
		} );

		add_filter( 'pediment_ai_provider', static function ( $default ) {
			$mock_const   = defined( 'PEDIMENT_AI_MOCK' ) && PEDIMENT_AI_MOCK;
			$mock_setting = (bool) ( new \Pediment\Settings\OptionsStore() )->get( 'mock_mode', false );
			if ( $mock_const || $mock_setting ) {
				return new \Pediment\Mock\MockProvider( PEDIMENT_AI_PLUGIN_DIR . '/src/Mock/fixtures' );
			}
			return $default;
		} );

		( new \Pediment\Settings\Page() )->register();

		add_filter( 'pediment_ai_model_compose', static function ( $default ) {
			$val = ( new \Pediment\Settings\OptionsStore() )->get( 'model_compose', '' );
			return '' !== $val ? $val : $default;
		} );
		add_filter( 'pediment_ai_model_edit', static function ( $default ) {
			$val = ( new \Pediment\Settings\OptionsStore() )->get( 'model_edit', '' );
			return '' !== $val ? $val : $default;
		} );
		add_filter( 'pediment_ai_model_refine', static function ( $default ) {
			$val = ( new \Pediment\Settings\OptionsStore() )->get( 'model_refine', '' );
			return '' !== $val ? $val : $default;
		} );

		add_action(
			'rest_api_init',
			static function () {
				( new \Pediment\Rest\ChatController() )->register();
			}
		);
	}
}
