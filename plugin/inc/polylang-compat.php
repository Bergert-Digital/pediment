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

/**
 * Keep template parts out of Polylang's translated post types.
 *
 * Polylang Pro's full-site-editing module registers wp_template_part as a
 * translated post type (PLL_FSE_Post_Types::add_post_types(), on
 * `pll_get_post_types` at priority 10), which language-scopes it. A Pediment
 * site seeds ONE header and one footer, shared across every language and tagged
 * with no language — the per-language element is the navigation, the
 * wp_navigation post kept translatable above. Under Pro the language-less parts
 * then match no current language, so the `wp:template-part` block resolves to
 * nothing and the header (with its whole navigation) disappears on every front
 * end. Polylang Free never scoped template parts, so this only surfaces on Pro.
 *
 * Priority 100 so it runs AFTER Pro's own priority-10 filter has added the type.
 * Only wp_template_part is removed; wp_navigation and the translated content
 * pages are untouched.
 *
 * @param string[] $post_types Post types Polylang manages.
 * @return string[]
 */
function pediment_polylang_share_template_parts( $post_types ) {
	unset( $post_types['wp_template_part'] );
	return $post_types;
}
add_filter( 'pll_get_post_types', 'pediment_polylang_share_template_parts', 100, 1 );
