<?php
/**
 * Bind the header's navigation block to the current language's menu.
 *
 * The seeded header template part (see inc/bootstrap.php) ships
 * `<!-- wp:navigation /-->` with no `ref`, because post IDs differ per
 * environment and a file cannot hardcode one. Core resolves a ref-less
 * navigation block through block_core_navigation_get_fallback_ref(), which
 * returns the MOST RECENTLY CREATED wp_navigation post — so on a multilingual
 * site every language renders whichever menu the seeder happened to write
 * last. On a live site that has previously shown the wrong language's
 * navigation to everyone, and, when the fallback found nothing, removed the
 * header's navigation outright.
 *
 * @package Pediment
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The seed key that binds a ref-less header navigation block to a seeded menu.
 *
 * Nothing in Manifest, NavSpec, or docs/seeding.md#navs requires the header
 * nav be keyed `primary` — nav keys are otherwise free-form. `primary` is a
 * documented CONTRACT for this specific binding (docs/seeding.md#navs), not
 * something the engine derives, because deriving it would mean loading the
 * manifest on every front-end request that renders a ref-less navigation
 * block — Manifest::load() is memoized per request, but "per request" still
 * means every uncached page view, for a value that changes only when a theme
 * is built. A theme whose header nav is keyed differently can opt out via the
 * `pediment_primary_nav_key` filter instead of forking this file.
 *
 * @return string
 */
function pediment_primary_nav_key(): string {
	/**
	 * The seed key `pediment_bind_navigation_ref()` binds a ref-less header
	 * navigation block to.
	 *
	 * @param string $key Defaults to 'primary'.
	 */
	return (string) apply_filters( 'pediment_primary_nav_key', 'primary' );
}

/**
 * The seeded navigation entity for a language, by seed key.
 *
 * Identity is the seed key, never the slug: a stray post holding `primary`
 * pushed every replacement to `primary-2`, where a slug lookup could not find
 * it (7d7ca30).
 *
 * @param string $language Language slug, '' for none.
 * @return int Post ID, 0 when there is none.
 */
function pediment_seeded_nav_id( string $language ): int {
	$args = [
		'post_type'      => 'wp_navigation',
		'post_status'    => 'publish',
		'posts_per_page' => 1,
		'no_found_rows'  => true,
		'fields'         => 'ids',
		// Oldest wins on a tie. NavSeeder::plan() reports a duplicate seed key
		// as an error rather than letting one through, so this ordering exists
		// only to make an already-broken state deterministic instead of
		// leaving it to the database's unspecified default order.
		'orderby'        => 'ID',
		'order'          => 'ASC',
		// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- indexed seed-identity lookup, once per request.
		'meta_key'       => \Pediment\Seeder\Meta::KEY,
		'meta_value'     => pediment_primary_nav_key(), // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value -- see above.
		// Set unconditionally, including '' for the unscoped lookup. Polylang's
		// PLL_Query::is_already_filtered() (src/query.php) treats
		// isset( $qvars['lang'] ) — not its value — as "the caller has already
		// decided the language scope"; omitting the key for '' would leave that
		// check false and hand the query to Polylang's own current-language
		// auto-scoping instead, defeating the point of an unscoped lookup. Same
		// idiom as LanguageProvider::unscopedQuery(). Instrumented verification
		// of this exact call shape did not show the auto-scoping actually
		// altering the returned rows in this Polylang version (see the task-12
		// report), but matching the documented contract and the codebase's own
		// precedent costs nothing and removes the dependency on that detail.
		'lang'           => $language,
	];

	$found = get_posts( $args );

	return $found ? (int) $found[0] : 0;
}

/**
 * Point a ref-less core/navigation block at this language's seeded menu.
 *
 * Falls back to the default language rather than to nothing: showing the wrong
 * language's navigation is bad, showing none at all is what took a live site's
 * header away. An explicitly-set ref is always left alone — that is a Site
 * Editor decision and outranks this.
 *
 * Candidate order is current language, then default, then the unscoped ('')
 * lookup. Building it as `array_filter( [ $current, $default ] )` — rather
 * than folding '' into the filtered set — matters on a monolingual site:
 * there $current and $default are both '', array_filter() drops them both,
 * and unconditionally appending '' afterwards is what keeps the unscoped
 * lookup reachable instead of leaving the candidate list empty and the
 * loop never running.
 *
 * @param array<string,mixed> $parsed_block Parsed block, pre-render.
 * @return array<string,mixed>
 */
function pediment_bind_navigation_ref( $parsed_block ) {
	if ( ! is_array( $parsed_block ) || 'core/navigation' !== ( $parsed_block['blockName'] ?? '' ) ) {
		return $parsed_block;
	}
	if ( ! empty( $parsed_block['attrs']['ref'] ) ) {
		return $parsed_block;
	}
	if ( ! empty( $parsed_block['innerBlocks'] ) ) {
		// Core's own render path (wp-includes/blocks/navigation.php:307-310)
		// does `if ( array_key_exists( 'ref', $attributes ) ) { $inner_blocks =
		// get_inner_blocks_from_navigation_post( $attributes ); }` with NO
		// empty()/isset() guard on $inner_blocks — a ref we inject here would
		// unconditionally override any inline children the block was
		// authored with (patterns/mega-menu-header.php ships a navigation
		// block with an inline pediment/mega-menu child and no ref, for
		// exactly this reason).
		return $parsed_block;
	}

	$provider = \Pediment\Language\LanguageRegistry::provider();
	$current  = $provider->currentLanguage();
	$default  = $provider->defaultLanguage();

	$candidates   = array_values( array_unique( array_filter( [ $current, $default ] ) ) );
	$candidates[] = '';

	foreach ( $candidates as $language ) {
		$ref = pediment_seeded_nav_id( (string) $language );
		if ( $ref > 0 ) {
			$parsed_block['attrs']['ref'] = $ref;
			return $parsed_block;
		}
	}

	// Nothing seeded: leave the block alone and let core's own fallback run.
	// Better a menu chosen badly than an empty header.
	return $parsed_block;
}
add_filter( 'render_block_data', 'pediment_bind_navigation_ref' );
