<?php
/**
 * Registers the plugin's default design-layer styles from plugin/styles/*.json.
 *
 * @package Pediment
 */

declare(strict_types=1);

namespace Pediment\Design;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Lowest design layer: token-only block style variations shipped by the plugin.
 */
final class DefaultStyles {

	/**
	 * Hooks registration after core blocks exist.
	 */
	public static function register(): void {
		add_action( 'init', array( self::class, 'registerAll' ), 20 );
	}

	/**
	 * Reads every plugin style file as a spec.
	 *
	 * @return list<array{slug:string,label:string,blockTypes:list<string>,styles:array}>
	 */
	public static function specs(): array {
		$files = glob( PEDIMENT_AI_PLUGIN_DIR . '/styles/*.json' );
		$specs = array();
		foreach ( false === $files ? array() : $files as $file ) {
			$data = json_decode( (string) file_get_contents( $file ), true );
			if ( ! is_array( $data ) ) {
				continue;
			}
			$specs[] = array(
				'slug'       => (string) ( $data['slug'] ?? basename( $file, '.json' ) ),
				'label'      => (string) ( $data['title'] ?? '' ),
				'blockTypes' => array_values( (array) ( $data['blockTypes'] ?? array() ) ),
				'styles'     => (array) ( $data['styles'] ?? array() ),
			);
		}
		return $specs;
	}

	/**
	 * Registers every valid spec; skips and reports invalid ones.
	 */
	public static function registerAll(): void {
		$validator = new StyleValidator();
		foreach ( self::specs() as $spec ) {
			$errors = $validator->validate( $spec );
			if ( array() !== $errors ) {
				_doing_it_wrong( __METHOD__, esc_html( "Pediment style {$spec['slug']} skipped: " . implode( '; ', $errors ) ), '4.0.0' );
				continue;
			}
			foreach ( $spec['blockTypes'] as $block_type ) {
				register_block_style(
					$block_type,
					array(
						'name'       => $spec['slug'],
						'label'      => $spec['label'],
						'style_data' => $spec['styles'],
					)
				);
			}
		}
	}
}
