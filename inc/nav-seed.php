<?php
/**
 * Seed and bind the default header navigation entity.
 *
 * WordPress has no file-based mechanism for a specific default menu —
 * navigation content lives in the `wp_navigation` CPT. To ship a curated
 * default menu that is also the editor-managed, front-end-consistent menu,
 * we create (or adopt) a `wp_navigation` entity and point the header's
 * (bare) navigation block at it via `render_block_data`.
 *
 * @package Starter
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const STARTER_NAV_MARKER = '_starter_seeded_nav';

/**
 * Serialized block markup for the default menu.
 *
 * About / Blog / Contact, all plain nav links. The header's pill CTA is a
 * separate wp:button in parts/header.html. Relative custom URLs keep the
 * menu install-independent (active state handled by inc/nav-active.php).
 *
 * @return string
 */
function starter_nav_menu_blocks(): string {
	$mega = wp_json_encode(
		array(
			'label'   => 'Products',
			'columns' => array(
				array(
					'heading' => 'Product',
					'links'   => array(
						array(
							'label'       => 'Pricing',
							'url'         => '/pricing',
							'description' => 'Plans',
							'icon'        => 'tag',
						),
						array(
							'label'       => 'Docs',
							'url'         => '/docs',
							'description' => 'Guides',
							'icon'        => 'book',
						),
					),
				),
			),
		)
	);

	return implode(
		"\n",
		array(
			'<!-- wp:starter/mega-menu ' . $mega . ' /-->',
			'<!-- wp:navigation-link {"label":"About","url":"/about","kind":"custom"} /-->',
			'<!-- wp:navigation-link {"label":"Blog","url":"/blog","kind":"custom"} /-->',
			'<!-- wp:navigation-link {"label":"Contact","url":"/contact","kind":"custom"} /-->',
		)
	);
}

/**
 * Whether a navigation post is a pristine core page-list fallback.
 *
 * Core's WP_Navigation_Fallback persists `<!-- wp:page-list /-->` when no
 * menu exists. Such an untouched post is safe to adopt; anything else is
 * treated as user content and left alone.
 *
 * @param string $content Raw post_content.
 * @return bool
 */
function starter_nav_is_pristine_fallback( string $content ): bool {
	return '<!-- wp:page-list /-->' === trim( $content );
}

/**
 * Find the ID of our seeded navigation entity, if any. Read-only.
 *
 * Called at most once or twice per request (the render_block_data consumer
 * early-returns for non-navigation blocks), so an unmemoized lookup is fine.
 *
 * @return int Post ID, or 0 when none exists yet.
 */
function starter_nav_find_entity_id(): int {
	$ids = get_posts(
		array(
			'post_type'        => 'wp_navigation',
			'post_status'      => 'any',
			'numberposts'      => 1,
			'fields'           => 'ids',
			'meta_key'         => STARTER_NAV_MARKER, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
			'suppress_filters' => false,
		)
	);

	return empty( $ids ) ? 0 : (int) $ids[0];
}

/**
 * Ensure the default navigation entity exists. Idempotent.
 *
 * - Our marked post already exists  → leave it untouched (protects user edits).
 * - A pristine page-list fallback   → adopt it (rewrite content, stamp marker).
 * - Otherwise                       → create a new entity.
 *
 * Never modifies a `wp_navigation` post that is neither our marked post nor a
 * pristine fallback.
 *
 * @return int The navigation post ID (0 on failure).
 */
function starter_nav_seed_entity(): int {
	$existing = starter_nav_find_entity_id();
	if ( $existing > 0 ) {
		return $existing;
	}

	$blocks = starter_nav_menu_blocks();

	$fallback = get_posts(
		array(
			'post_type'        => 'wp_navigation',
			'post_status'      => 'any',
			'numberposts'      => 1,
			'orderby'          => 'date',
			'order'            => 'DESC',
			'suppress_filters' => false,
		)
	);

	if ( ! empty( $fallback ) && starter_nav_is_pristine_fallback( $fallback[0]->post_content ) ) {
		$id = wp_update_post(
			array(
				'ID'           => $fallback[0]->ID,
				'post_title'   => 'Header Navigation',
				'post_content' => $blocks,
			),
			true
		);
	} else {
		$id = wp_insert_post(
			array(
				'post_type'    => 'wp_navigation',
				'post_status'  => 'publish',
				'post_title'   => 'Header Navigation',
				'post_name'    => 'starter-header',
				'post_content' => $blocks,
			),
			true
		);
	}

	if ( is_wp_error( $id ) || ! $id ) {
		return 0;
	}

	update_post_meta( (int) $id, STARTER_NAV_MARKER, '1' );

	return (int) $id;
}
add_action( 'after_switch_theme', 'starter_nav_seed_entity' );

/**
 * Point the header's bare navigation block at the seeded entity.
 *
 * The header ships `<!-- wp:navigation /-->` (no ref) like core themes; this
 * binds it deterministically rather than relying on core's "newest entity"
 * fallback heuristic. Read-only: never creates posts at render time.
 *
 * @param array $parsed_block The parsed block.
 * @return array
 */
function starter_nav_bind_ref( array $parsed_block ): array {
	if ( 'core/navigation' !== ( $parsed_block['blockName'] ?? '' ) ) {
		return $parsed_block;
	}
	if ( ! empty( $parsed_block['attrs']['ref'] ) ) {
		return $parsed_block;
	}

	$ref = starter_nav_find_entity_id();
	if ( $ref > 0 ) {
		$parsed_block['attrs']['ref'] = $ref;
	}

	return $parsed_block;
}
add_filter( 'render_block_data', 'starter_nav_bind_ref' );
