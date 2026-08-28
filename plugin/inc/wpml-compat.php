<?php
/**
 * What WPML needs to know about this product's built-in post types, and cannot
 * be told by clicking. The runtime analogue of inc/polylang-compat.php.
 *
 * The wp_navigation post type must be translatable so a menu can exist per
 * language; WPML's settings screen does not list built-in post types, so a
 * runtime config injection is the only path. wp_template_part stays shared:
 * one header/footer serves every language, tagged with no language.
 *
 * The array shape below (`wpml-config.custom-types.custom-type[]`, each item
 * `['value' => <post type>, 'attr' => ['translate' => '1'|'0']]`) is confirmed
 * against the installed WPML source (plugin/wpml/sitepress-multilingual-cms):
 * WPML_Config::parse_wpml_config_files() applies the `wpml_config_array`
 * filter to the same array it builds from wpml-config.xml files, then hands
 * `$config['wpml-config']` to WPML_TM_Settings_Update::update_from_config(),
 * which reads exactly `custom-types.custom-type[]` entries shaped
 * `['value' => ..., 'attr' => ['translate' => ...]]`
 * (classes/settings/class-wpml-tm-settings-update.php). This is the same
 * mechanism a wpml-config.xml file feeds through, just injected in PHP instead
 * of parsed from XML.
 *
 * This is separate from the generated plugin/wpml-config.xml, which declares
 * translatable *block attributes*; this file declares *post-type
 * translatability*. Both are no-ops when WPML is inactive (the filter never
 * fires).
 *
 * @package Pediment
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * @param array<string,mixed> $config
 * @return array<string,mixed>
 */
function pediment_wpml_translate_navigation_menus( $config ) {
	$config['wpml-config']['custom-types']['custom-type'][] = [
		'value' => 'wp_navigation',
		'attr'  => [ 'translate' => '1' ],
	];
	return $config;
}
add_filter( 'wpml_config_array', 'pediment_wpml_translate_navigation_menus' );

/**
 * @param array<string,mixed> $config
 * @return array<string,mixed>
 */
function pediment_wpml_share_template_parts( $config ) {
	$config['wpml-config']['custom-types']['custom-type'][] = [
		'value' => 'wp_template_part',
		'attr'  => [ 'translate' => '0' ],
	];
	return $config;
}
add_filter( 'wpml_config_array', 'pediment_wpml_share_template_parts' );
