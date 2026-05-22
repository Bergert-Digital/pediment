<?php
/**
 * Mark the navigation link for the current page with aria-current.
 *
 * Core's core/navigation-link only sets aria-current when the link has a
 * matching post `id`. The theme's default menu uses relative custom-URL
 * links, so we derive the active state from the request path instead.
 *
 * @package Pediment
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Whether a navigation link URL points at the current request path.
 *
 * Pure helper: compares the path components only, ignoring host, query
 * string and surrounding slashes. Returns false for empty link URLs and
 * for the bare site root ("/").
 *
 * @param string $link_url     The navigation link href (may be relative).
 * @param string $current_path The current request path (may include query).
 * @return bool
 */
function pediment_nav_path_is_current( string $link_url, string $current_path ): bool {
	$link_path = trim( (string) wp_parse_url( $link_url, PHP_URL_PATH ), '/' );
	$current   = trim( (string) wp_parse_url( $current_path, PHP_URL_PATH ), '/' );

	if ( '' === $link_path ) {
		return false;
	}

	return $link_path === $current;
}

/**
 * Inject aria-current="page" into the active navigation link.
 *
 * @param string $block_content Rendered block HTML.
 * @param array  $block         Parsed block.
 * @return string
 */
function pediment_nav_mark_active_link( string $block_content, array $block ): string {
	if ( '' === $block_content || empty( $block['attrs']['url'] ) ) {
		return $block_content;
	}

	if ( false !== strpos( $block_content, 'aria-current' ) ) {
		return $block_content;
	}

	$request_uri  = isset( $_SERVER['REQUEST_URI'] ) ? wp_unslash( $_SERVER['REQUEST_URI'] ) : '/'; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized,WordPress.Security.ValidatedSanitizedInput.MissingUnslash
	$current_path = (string) wp_parse_url( $request_uri, PHP_URL_PATH );

	if ( ! pediment_nav_path_is_current( (string) $block['attrs']['url'], $current_path ) ) {
		return $block_content;
	}

	return preg_replace( '/<a\b/', '<a aria-current="page"', $block_content, 1 );
}
add_filter( 'render_block_core/navigation-link', 'pediment_nav_mark_active_link', 10, 2 );
