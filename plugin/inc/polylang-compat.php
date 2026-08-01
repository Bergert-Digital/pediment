<?php
/**
 * What Polylang needs to know about this product, and cannot be told by
 * clicking.
 *
 * @package Pediment
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Register wp_navigation as a translatable post type.
 *
 * The header binds its navigation block with a language-scoped lookup (see
 * inc/nav-language.php), which only yields a menu per language if Polylang
 * treats wp_navigation as translatable. This cannot be switched on in the UI:
 * Polylang's settings screen offers only post types registered
 * `public => true` and `_builtin => false`, and wp_navigation is
 * `public => false, _builtin => true`, so it never appears there. Polylang
 * carries no wp_navigation handling of its own — its menu translation UI works
 * on classic nav_menu terms, which a block theme does not use.
 *
 * Filtering only when $is_settings is false uses Polylang's
 * "programmatically active" path: always on, shown as a disabled checkbox
 * rather than one a site owner can untick and silently lose every translated
 * menu to.
 *
 * @param string[] $post_types  Post types Polylang manages.
 * @param bool     $is_settings Whether the list is for the settings screen.
 * @return string[]
 */
function pediment_polylang_translate_navigation_menus( $post_types, $is_settings ) {
	if ( ! $is_settings ) {
		$post_types['wp_navigation'] = 'wp_navigation';
	}
	return $post_types;
}
add_filter( 'pll_get_post_types', 'pediment_polylang_translate_navigation_menus', 10, 2 );
