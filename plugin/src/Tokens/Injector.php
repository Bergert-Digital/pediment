<?php
/**
 * Injects the plugin's design tokens (plugin/tokens/theme.json) as the base
 * theme.json origin, with the active theme's own theme.json merging over it
 * per preset slug (parent/child semantics without a parent theme).
 *
 * Productionized from the spike at
 * .context/spike-plugin-theme/spike-plugin/spike-plugin.php.
 *
 * @package Pediment
 */

declare(strict_types=1);

namespace Pediment\Tokens;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Merges plugin tokens under the active theme's theme.json.
 */
class Injector {
	/**
	 * [settings-group, settings-key] pairs for every preset array that gets
	 * merged per slug instead of wholesale-replaced.
	 */
	private const PRESET_PATHS = array(
		array( 'color', 'palette' ),
		array( 'typography', 'fontFamilies' ),
		array( 'typography', 'fontSizes' ),
		array( 'spacing', 'spacingSizes' ),
		array( 'color', 'gradients' ),
		array( 'color', 'duotone' ),
	);

	/**
	 * Wires the merge onto the theme.json data filter.
	 *
	 * @return void
	 */
	public static function register(): void {
		add_filter( 'wp_theme_json_data_theme', array( self::class, 'inject' ) );
	}

	/**
	 * Merges plugin/tokens/theme.json under the active theme's theme.json.
	 *
	 * @param \WP_Theme_JSON_Data $theme_json The active theme's data, as passed by the filter.
	 * @return \WP_Theme_JSON_Data
	 */
	public static function inject( $theme_json ) {
		$tokens = json_decode( file_get_contents( PEDIMENT_AI_PLUGIN_DIR . '/tokens/theme.json' ), true );
		if ( ! is_array( $tokens ) ) {
			return $theme_json;
		}

		$tokens = self::rewrite_font_face_src( $tokens );

		$base   = new \WP_Theme_JSON_Data( $tokens, 'theme' );
		$client = $theme_json->get_data();
		$client = self::merge_presets_by_slug( $base->get_data(), $client );

		return $base->update_with( $client );
	}

	/**
	 * `file:./assets/...` font-face src references resolve relative to a
	 * theme directory; outside a theme they 404. Rewrite them to the
	 * plugin's own asset URL before the tokens are used to build a
	 * WP_Theme_JSON_Data instance.
	 *
	 * @param array $tokens Decoded plugin/tokens/theme.json.
	 * @return array
	 */
	private static function rewrite_font_face_src( array $tokens ): array {
		$families = $tokens['settings']['typography']['fontFamilies'] ?? null;
		if ( ! is_array( $families ) ) {
			return $tokens;
		}

		foreach ( $families as $family_index => $family ) {
			$faces = $family['fontFace'] ?? null;
			if ( ! is_array( $faces ) ) {
				continue;
			}
			foreach ( $faces as $face_index => $face ) {
				$srcs = $face['src'] ?? null;
				if ( ! is_array( $srcs ) ) {
					continue;
				}
				foreach ( $srcs as $src_index => $src ) {
					if ( is_string( $src ) && str_starts_with( $src, 'file:./assets/' ) ) {
						$srcs[ $src_index ] = str_replace(
							'file:./assets/',
							PEDIMENT_AI_PLUGIN_URL . 'assets/',
							$src
						);
					}
				}
				$tokens['settings']['typography']['fontFamilies'][ $family_index ]['fontFace'][ $face_index ]['src'] = $srcs;
			}
		}

		return $tokens;
	}

	/**
	 * A preset array can be flat (theme.json shape) or origin-keyed
	 * (`['theme' => [...]]`, as WP_Theme_JSON sometimes reports settings
	 * pulled from a single origin). Normalize to a flat, indexed array.
	 *
	 * @param mixed $preset Raw preset value.
	 * @return array
	 */
	private static function normalize( $preset ): array {
		if ( is_array( $preset ) && isset( $preset['theme'] ) ) {
			return $preset['theme'];
		}
		return is_array( $preset ) ? $preset : array();
	}

	/**
	 * For each preset path, merge the client's entries over the base's per
	 * slug: matching slugs are replaced, new slugs are appended, and base
	 * slugs the client doesn't mention survive untouched. The merged array
	 * is written back into $client so update_with()'s wholesale replace of
	 * the client origin carries the union.
	 *
	 * @param array $base   Plugin tokens, as returned by WP_Theme_JSON_Data::get_data().
	 * @param array $client Active theme's data, as returned by WP_Theme_JSON_Data::get_data().
	 * @return array The client array with merged presets written in.
	 */
	private static function merge_presets_by_slug( array $base, array $client ): array {
		foreach ( self::PRESET_PATHS as [ $group, $key ] ) {
			$client_preset = self::normalize( $client['settings'][ $group ][ $key ] ?? array() );
			if ( ! $client_preset ) {
				continue;
			}

			$merged = self::normalize( $base['settings'][ $group ][ $key ] ?? array() );
			foreach ( $client_preset as $entry ) {
				$slug  = is_array( $entry ) ? ( $entry['slug'] ?? null ) : null;
				$found = false;
				if ( null !== $slug ) {
					foreach ( $merged as $existing_index => $existing ) {
						if ( ( $existing['slug'] ?? null ) === $slug ) {
							$merged[ $existing_index ] = $entry;
							$found                     = true;
							break;
						}
					}
				}
				if ( ! $found ) {
					$merged[] = $entry;
				}
			}

			$client['settings'][ $group ][ $key ] = $merged;
		}

		return $client;
	}
}
