<?php
/**
 * E2E inline fixtures.
 *
 * Replaces the (removed) `wp pediment seed` command: builds only the minimal
 * demo content the Playwright suite asserts against. Run via
 * `wp eval-file wp-content/themes/pediment/tests/e2e/fixtures.php` from
 * global-setup.ts AFTER the theme is active (so framework bootstrap has created
 * the header part + brand defaults and the registered patterns are available).
 *
 * Canonical block markup for the Home and Mega-menu pages is sourced from the
 * registered patterns rather than hand-copied, so the fixtures never drift from
 * the patterns the theme actually ships.
 *
 * Idempotent: safe to run on every e2e invocation.
 *
 * @package Pediment
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Read the serialized markup of a registered block pattern.
 *
 * @param string $slug Pattern slug, e.g. `pediment/pediment-landing`.
 * @return string Pattern markup, or '' when the pattern is not registered.
 */
function pediment_e2e_pattern_content( string $slug ): string {
	if ( class_exists( 'WP_Block_Patterns_Registry' ) ) {
		$pattern = WP_Block_Patterns_Registry::get_instance()->get_registered( $slug );
		if ( is_array( $pattern ) && ! empty( $pattern['content'] ) ) {
			return (string) $pattern['content'];
		}
	}
	return '';
}

/**
 * Upsert a published page by slug and return its ID.
 *
 * Always rewrites content so re-runs pick up pattern changes (deterministic).
 *
 * @param string $slug    Page slug.
 * @param string $title   Page title.
 * @param string $content Block markup.
 * @return int Page ID (0 on failure).
 */
function pediment_e2e_upsert_page( string $slug, string $title, string $content ): int {
	$existing = get_page_by_path( $slug, OBJECT, 'page' );
	if ( $existing instanceof WP_Post ) {
		wp_update_post(
			array(
				'ID'           => $existing->ID,
				'post_title'   => $title,
				'post_content' => $content,
				'post_status'  => 'publish',
			)
		);
		return (int) $existing->ID;
	}
	$id = wp_insert_post(
		array(
			'post_type'    => 'page',
			'post_status'  => 'publish',
			'post_title'   => $title,
			'post_name'    => $slug,
			'post_content' => $content,
		),
		true
	);
	return is_wp_error( $id ) ? 0 : (int) $id;
}

// ---------------------------------------------------------------------------
// 1. Pages: Home (landing pattern), About, Contact (contact-form block),
// Blog (listing rendered by home.html — own content unused), Mega-menu demo.
// ---------------------------------------------------------------------------

$ids = array();

$ids['home'] = pediment_e2e_upsert_page(
	'home',
	'Home',
	pediment_e2e_pattern_content( 'pediment/pediment-landing' )
);

$ids['about'] = pediment_e2e_upsert_page(
	'about',
	'About',
	'<!-- wp:heading {"level":1} --><h1 class="wp-block-heading">About us</h1><!-- /wp:heading -->' .
		'<!-- wp:paragraph --><p>Who we are and what we do.</p><!-- /wp:paragraph -->'
);

$ids['contact'] = pediment_e2e_upsert_page(
	'contact',
	'Contact',
	'<!-- wp:pediment/contact-form {"includePhone":true} /-->'
);

$ids['blog'] = pediment_e2e_upsert_page( 'blog', 'Blog', '' );

$ids['mega-demo'] = pediment_e2e_upsert_page(
	'mega-demo',
	'Mega Menu Demo',
	pediment_e2e_pattern_content( 'pediment/mega-menu-header' )
);

// ---------------------------------------------------------------------------
// 2. Reading settings: static front page + posts page.
// ---------------------------------------------------------------------------

if ( ! empty( $ids['home'] ) ) {
	update_option( 'show_on_front', 'page' );
	update_option( 'page_on_front', $ids['home'] );
}
if ( ! empty( $ids['blog'] ) ) {
	update_option( 'page_for_posts', $ids['blog'] );
}

// ---------------------------------------------------------------------------
// 3. Sample posts across categories so the blog index (home.html query) renders
// insight cards with category badges.
// ---------------------------------------------------------------------------

$categories = array(
	'insights'  => 'Insights',
	'briefings' => 'Briefings',
	'notes'     => 'Notes',
);
$cat_ids    = array();
foreach ( $categories as $cat_slug => $cat_name ) {
	$cat_term = get_term_by( 'slug', $cat_slug, 'category' );
	if ( $cat_term ) {
		$cat_ids[ $cat_slug ] = (int) $cat_term->term_id;
		continue;
	}
	$created = wp_insert_term( $cat_name, 'category', array( 'slug' => $cat_slug ) );
	if ( ! is_wp_error( $created ) ) {
		$cat_ids[ $cat_slug ] = (int) $created['term_id'];
	}
}

$sample_posts = array(
	array( 'sample-insight-one', 'A practical insight on getting started', 'insights' ),
	array( 'sample-insight-two', 'What good looks like, in plain terms', 'insights' ),
	array( 'sample-briefing-one', 'A short briefing on a common decision', 'briefings' ),
	array( 'sample-briefing-two', 'Trade-offs worth weighing early', 'briefings' ),
	array( 'sample-note-one', 'A quick note on process', 'notes' ),
	array( 'sample-note-two', 'A quick note on outcomes', 'notes' ),
);

foreach ( $sample_posts as $sample ) {
	list( $post_slug, $post_title, $post_cat ) = $sample;
	if ( get_page_by_path( $post_slug, OBJECT, 'post' ) ) {
		continue;
	}
	$new_post_id = wp_insert_post(
		array(
			'post_type'    => 'post',
			'post_status'  => 'publish',
			'post_title'   => $post_title,
			'post_name'    => $post_slug,
			'post_excerpt' => 'A one-sentence summary of this sample article, ready to be replaced.',
			'post_content' => '<!-- wp:paragraph --><p>Replace this sample article with your own writing.</p><!-- /wp:paragraph -->',
		),
		true
	);
	if ( ! is_wp_error( $new_post_id ) && isset( $cat_ids[ $post_cat ] ) ) {
		wp_set_post_categories( (int) $new_post_id, array( $cat_ids[ $post_cat ] ) );
	}
}

// ---------------------------------------------------------------------------
// 4. Default header navigation entity.
//
// The header template part ships a bare `wp:navigation` (no ref); the parent's
// render-time binder was relocated to the child theme. Core's navigation
// fallback renders the most-recently-published `wp_navigation` entity, so a
// single curated entity binds the header deterministically. Mirrors the removed
// inc/nav-seed.php: adopt a pristine page-list fallback if present, else create.
// ---------------------------------------------------------------------------

$nav_blocks = implode(
	"\n",
	array(
		'<!-- wp:navigation-link {"label":"About","url":"/about","kind":"custom"} /-->',
		'<!-- wp:navigation-link {"label":"Blog","url":"/blog","kind":"custom"} /-->',
		'<!-- wp:navigation-link {"label":"Contact","url":"/contact","kind":"custom"} /-->',
	)
);

$marked = get_posts(
	array(
		'post_type'   => 'wp_navigation',
		'post_status' => 'any',
		'numberposts' => 1,
		'fields'      => 'ids',
		'meta_key'    => '_pediment_e2e_nav', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
	)
);

if ( empty( $marked ) ) {
	$nav_id   = 0;
	$existing = get_posts(
		array(
			'post_type'   => 'wp_navigation',
			'post_status' => 'any',
			'numberposts' => 1,
			'orderby'     => 'date',
			'order'       => 'DESC',
		)
	);
	if ( ! empty( $existing ) && '<!-- wp:page-list /-->' === trim( $existing[0]->post_content ) ) {
		$nav_id = (int) wp_update_post(
			array(
				'ID'           => $existing[0]->ID,
				'post_title'   => 'Header Navigation',
				'post_content' => $nav_blocks,
			),
			true
		);
	} else {
		$nav_id = (int) wp_insert_post(
			array(
				'post_type'    => 'wp_navigation',
				'post_status'  => 'publish',
				'post_title'   => 'Header Navigation',
				'post_name'    => 'pediment-header',
				'post_content' => $nav_blocks,
			),
			true
		);
	}
	if ( $nav_id ) {
		update_post_meta( $nav_id, '_pediment_e2e_nav', '1' );
	}
}

// ---------------------------------------------------------------------------
// 5. Site logo. The header's wp:site-logo only renders an <img> when a custom
// logo is set; bootstrap ships no logo (demo concern), so seed one here.
// Sideloads a generated SVG into the (gitignored) mapped uploads dir.
// ---------------------------------------------------------------------------

$logo_existing = get_posts(
	array(
		'post_type'   => 'attachment',
		'post_status' => 'inherit',
		'numberposts' => 1,
		'fields'      => 'ids',
		'meta_key'    => '_pediment_e2e_logo', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
		'meta_value'  => '1', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
	)
);

if ( ! empty( $logo_existing ) ) {
	$logo_id = (int) $logo_existing[0];
	if ( (int) get_theme_mod( 'custom_logo', 0 ) !== $logo_id ) {
		set_theme_mod( 'custom_logo', $logo_id );
	}
} else {
	$uploads = wp_upload_dir();
	if ( empty( $uploads['error'] ) ) {
		$svg  = '<svg xmlns="http://www.w3.org/2000/svg" width="300" height="80" viewBox="0 0 300 80">'
			. '<rect width="300" height="80" fill="#0E7490"/>'
			. '<text x="150" y="50" font-family="sans-serif" font-size="32" fill="#fff" text-anchor="middle">Pediment</text>'
			. '</svg>';
		$dest = trailingslashit( $uploads['path'] ) . wp_unique_filename( $uploads['path'], 'pediment-e2e-logo.svg' );
		if ( false !== file_put_contents( $dest, $svg ) ) { // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
			$logo_id = wp_insert_attachment(
				array(
					'post_mime_type' => 'image/svg+xml',
					'post_title'     => 'Pediment e2e logo',
					'post_status'    => 'inherit',
				),
				$dest
			);
			if ( ! is_wp_error( $logo_id ) && $logo_id ) {
				update_post_meta( (int) $logo_id, '_pediment_e2e_logo', '1' );
				set_theme_mod( 'custom_logo', (int) $logo_id );
			}
		}
	}
}
