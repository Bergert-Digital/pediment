<?php
/**
 * WP-CLI: `wp pediment seed` — populate Brand defaults + sample pages.
 *
 * @package Pediment
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( defined( 'WP_CLI' ) && WP_CLI ) {
	\WP_CLI::add_command( 'pediment seed', 'pediment_seed_cli' );
}

function pediment_seed_cli(): void {
	pediment_seed_run();
	if ( class_exists( '\\WP_CLI' ) ) {
		\WP_CLI::success( 'Pediment seeded.' );
	}
}

function pediment_seed_run(): void {
	$brand_defaults = array(
		'brand_name'    => get_bloginfo( 'name' ) ?: 'Acme',
		'brand_tagline' => 'Short benefit-led promise.',
		'voice_tone'    => 'Confident, plain-spoken, no buzzwords.',
		'contact_email' => get_option( 'admin_email' ),
	);
	foreach ( $brand_defaults as $k => $v ) {
		if ( '' === (string) \Pediment\Brand::get( $k, '' ) ) {
			\Pediment\Brand::set( $k, $v );
		}
	}

	pediment_seed_demo_image();
	pediment_seed_demo_logo();

	$pages = array(
		'home'      => array(
			'title'   => 'Home',
			'content' => pediment_pediment_landing_content(),
		),
		'about'     => array(
			'title'   => 'About',
			'content' =>
				'<!-- wp:pediment/hero {"variant":"default","headline":"About us","subheadline":"Who we are and what we do.","align":"wide"} /-->' .
				'<!-- wp:pediment/prose -->' .
					'<!-- wp:paragraph --><p>Tell your story here. Keep it human and specific.</p><!-- /wp:paragraph -->' .
				'<!-- /wp:pediment/prose -->',
		),
		'contact'   => array(
			'title'   => 'Contact',
			'content' =>
				'<!-- wp:pediment/hero {"variant":"centered","headline":"Contact","subheadline":"Tell us about your project.","align":"wide"} /-->' .
				'<!-- wp:pediment/contact-form {"includePhone":true} /-->',
		),
		'blog'      => array(
			'title'   => 'Blog',
			// home.html renders the listing; the page's own content is unused.
			'content' => '',
		),
		'mega-demo' => array(
			'title'   => 'Mega Menu Demo',
			'content' => pediment_seed_mega_demo_content(),
		),
	);

	$page_ids = array();
	foreach ( $pages as $slug => $page ) {
		$existing = get_page_by_path( $slug, OBJECT, 'page' );
		if ( $existing ) {
			$page_ids[ $slug ] = (int) $existing->ID;
			continue;
		}
		$id = wp_insert_post(
			array(
				'post_type'    => 'page',
				'post_status'  => 'publish',
				'post_title'   => $page['title'],
				'post_name'    => $slug,
				'post_content' => $page['content'],
			),
			true
		);
		if ( ! is_wp_error( $id ) ) {
			$page_ids[ $slug ] = (int) $id;
		}
	}

	if ( isset( $page_ids['home'] ) ) {
		update_option( 'show_on_front', 'page' );
		update_option( 'page_on_front', $page_ids['home'] );
	}
	if ( isset( $page_ids['blog'] ) ) {
		update_option( 'page_for_posts', $page_ids['blog'] );
	}

	pediment_seed_sample_posts();

	if ( function_exists( 'pediment_nav_seed_entity' ) ) {
		pediment_nav_seed_entity();
	}
}

/**
 * The Pediment landing pattern content for the Home page.
 *
 * Reads the registered `pediment/pediment-landing` pattern. Falls back to a
 * minimal valid block composition so seeding never writes an empty Home even
 * if patterns are unavailable.
 *
 * @return string Block markup.
 */
function pediment_pediment_landing_content(): string {
	$content = '';
	if ( class_exists( 'WP_Block_Patterns_Registry' ) ) {
		$pattern = WP_Block_Patterns_Registry::get_instance()->get_registered( 'pediment/pediment-landing' );
		if ( is_array( $pattern ) && ! empty( $pattern['content'] ) ) {
			$content = (string) $pattern['content'];
		}
	}
	if ( '' === $content ) {
		$content = '<!-- wp:pediment/hero {"variant":"centered","headline":"Welcome","subheadline":"A short benefit-led promise.","ctaText":"Get started","ctaUrl":"/contact","align":"wide"} /-->' .
			'<!-- wp:pediment/cta {"title":"Ready to start?","body":"Tell us about your project.","primaryText":"Contact us","primaryUrl":"/contact","align":"wide"} /-->' .
			'<!-- wp:pediment/blog-index {"count":3,"align":"wide"} /-->';
	}
	return pediment_seed_apply_demo_image( $content );
}

/**
 * Mega-menu demo fixture content for the /mega-demo/ page.
 *
 * Reuses the registered `pediment/mega-menu-header` pattern so the e2e suite
 * always asserts against the canonical fixture. Falls back to a minimal
 * inline composition (kept in sync with the pattern) so seeding never writes
 * an empty page if pattern registration is unavailable at seed time.
 *
 * @return string Block markup.
 */
function pediment_seed_mega_demo_content(): string {
	if ( class_exists( 'WP_Block_Patterns_Registry' ) ) {
		$pattern = WP_Block_Patterns_Registry::get_instance()->get_registered( 'pediment/mega-menu-header' );
		if ( is_array( $pattern ) && ! empty( $pattern['content'] ) ) {
			return (string) $pattern['content'];
		}
	}
	return '<!-- wp:group {"className":"mega-demo","layout":{"type":"constrained"}} -->' .
		'<div class="wp-block-group mega-demo">' .
			'<!-- wp:navigation {"overlayMenu":"mobile","layout":{"type":"flex","orientation":"horizontal"}} -->' .
				'<!-- wp:pediment/mega-menu {"label":"Products","columns":[{"heading":"Banking","links":[{"label":"Checking","url":"#checking"}]}]} /-->' .
			'<!-- /wp:navigation -->' .
		'</div>' .
		'<!-- /wp:group -->';
}

/**
 * Idempotently sideload the demo image and tag it for easy cleanup.
 *
 * The marker meta `_pediment_seed_demo` makes removal trivial:
 *   wp post list --post_type=attachment --meta_key=_pediment_seed_demo --field=ID
 *   | xargs -I{} wp post delete {} --force
 *
 * @return int Attachment ID, or 0 on failure.
 */
function pediment_seed_demo_image(): int {
	// phpcs:disable WordPress.DB.SlowDBQuery -- seed lookup runs once per activation; meta lookup acceptable here.
	$existing = get_posts(
		array(
			'post_type'   => 'attachment',
			'post_status' => 'inherit',
			'numberposts' => 1,
			'fields'      => 'ids',
			'meta_key'    => '_pediment_seed_demo',
			'meta_value'  => '1',
		)
	);
	// phpcs:enable WordPress.DB.SlowDBQuery
	if ( ! empty( $existing ) ) {
		return (int) $existing[0];
	}

	$src = get_template_directory() . '/docs/images/dylan-gillis-KdeqA3aTnBY-unsplash.jpg';
	if ( ! file_exists( $src ) ) {
		return 0;
	}

	require_once ABSPATH . 'wp-admin/includes/file.php';
	require_once ABSPATH . 'wp-admin/includes/image.php';

	$uploads = wp_upload_dir();
	if ( ! empty( $uploads['error'] ) ) {
		return 0;
	}
	$filename = wp_unique_filename( $uploads['path'], basename( $src ) );
	$dest     = trailingslashit( $uploads['path'] ) . $filename;
	// phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- copy() can emit non-fatal warnings; return value drives the failure path.
	if ( ! @copy( $src, $dest ) ) {
		return 0;
	}

	$filetype  = wp_check_filetype( $dest, null );
	$attach_id = wp_insert_attachment(
		array(
			'post_mime_type' => $filetype['type'] ?: 'image/jpeg',
			'post_title'     => 'Demo image (Dylan Gillis on Unsplash)',
			'post_content'   => '',
			'post_status'    => 'inherit',
		),
		$dest
	);
	if ( is_wp_error( $attach_id ) || ! $attach_id ) {
		wp_delete_file( $dest );
		return 0;
	}

	wp_update_attachment_metadata( (int) $attach_id, wp_generate_attachment_metadata( (int) $attach_id, $dest ) );
	update_post_meta( (int) $attach_id, '_pediment_seed_demo', '1' );

	return (int) $attach_id;
}

/**
 * Idempotently sideload the wide demo logo and set it as the site's
 * Custom Logo. Mirrors pediment_seed_demo_image().
 *
 * The marker meta `_pediment_seed_demo_logo` makes removal trivial:
 *   wp post list --post_type=attachment --meta_key=_pediment_seed_demo_logo --field=ID
 *   | xargs -I{} wp post delete {} --force
 *
 * @return int Attachment ID, or 0 on failure.
 */
function pediment_seed_demo_logo(): int {
	// phpcs:disable WordPress.DB.SlowDBQuery -- seed lookup runs once per activation; meta lookup acceptable here.
	$existing = get_posts(
		array(
			'post_type'   => 'attachment',
			'post_status' => 'inherit',
			'numberposts' => 1,
			'fields'      => 'ids',
			'meta_key'    => '_pediment_seed_demo_logo',
			'meta_value'  => '1',
		)
	);
	// phpcs:enable WordPress.DB.SlowDBQuery
	if ( ! empty( $existing ) ) {
		$id = (int) $existing[0];
		if ( (int) get_theme_mod( 'custom_logo', 0 ) !== $id ) {
			set_theme_mod( 'custom_logo', $id );
		}
		return $id;
	}

	$src = get_template_directory() . '/docs/images/logo-demo.svg';
	if ( ! file_exists( $src ) ) {
		return 0;
	}

	require_once ABSPATH . 'wp-admin/includes/file.php';

	$uploads = wp_upload_dir();
	if ( ! empty( $uploads['error'] ) ) {
		return 0;
	}
	$filename = wp_unique_filename( $uploads['path'], basename( $src ) );
	$dest     = trailingslashit( $uploads['path'] ) . $filename;
	// phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- copy() can emit non-fatal warnings; return value drives the failure path.
	if ( ! @copy( $src, $dest ) ) {
		return 0;
	}

	$attach_id = wp_insert_attachment(
		array(
			'post_mime_type' => 'image/svg+xml',
			'post_title'     => 'Demo logo (Pediment)',
			'post_content'   => '',
			'post_status'    => 'inherit',
		),
		$dest
	);
	if ( is_wp_error( $attach_id ) || ! $attach_id ) {
		wp_delete_file( $dest );
		return 0;
	}

	update_post_meta( (int) $attach_id, '_pediment_seed_demo_logo', '1' );
	set_theme_mod( 'custom_logo', (int) $attach_id );

	return (int) $attach_id;
}

/**
 * Bake the seeded demo attachment into the pediment-landing pattern content:
 * adds `mediaId` to the stat-card hero and fills the empty approach-band image.
 *
 * @param string $content Raw pattern markup.
 * @return string Pattern markup with the demo image baked in.
 */
function pediment_seed_apply_demo_image( string $content ): string {
	$id = pediment_seed_demo_image();
	if ( ! $id ) {
		return $content;
	}

	$content = preg_replace_callback(
		'/<!-- wp:pediment\/hero (\{[^}]*"variant":"stat-card"[^}]*\}) \/-->/',
		function ( $m ) use ( $id ) {
			$attrs = json_decode( $m[1], true );
			if ( ! is_array( $attrs ) ) {
				return $m[0];
			}
			if ( empty( $attrs['mediaId'] ) ) {
				$attrs['mediaId'] = $id;
			}
			return '<!-- wp:pediment/hero ' . wp_json_encode( $attrs ) . ' /-->';
		},
		$content
	);

	$empty_image     = "<!-- wp:image {\"sizeSlug\":\"large\",\"className\":\"starter-approach__image\"} -->\n"
		. "<figure class=\"wp-block-image size-large starter-approach__image\"><img alt=\"\" /></figure>\n"
		. '<!-- /wp:image -->';
	$url             = (string) wp_get_attachment_image_url( $id, 'large' );
	$populated_image = sprintf(
		"<!-- wp:image {\"id\":%d,\"sizeSlug\":\"large\",\"className\":\"starter-approach__image\"} -->\n"
		. "<figure class=\"wp-block-image size-large starter-approach__image\"><img src=\"%s\" alt=\"\" class=\"wp-image-%d\" /></figure>\n"
		. '<!-- /wp:image -->',
		$id,
		esc_url( $url ),
		$id
	);
	$content         = str_replace( $empty_image, $populated_image, $content );

	return $content;
}

/**
 * Idempotently create sample categories + posts so the Insights band
 * (pediment/blog-index) renders fully. Skips anything that already exists.
 *
 * @return void
 */
function pediment_seed_sample_posts(): void {
	$categories = array(
		'insights'  => 'Insights',
		'briefings' => 'Briefings',
		'notes'     => 'Notes',
	);
	$cat_ids    = array();
	foreach ( $categories as $slug => $name ) {
		$term = get_term_by( 'slug', $slug, 'category' );
		if ( $term ) {
			$cat_ids[ $slug ] = (int) $term->term_id;
			continue;
		}
		$created = wp_insert_term( $name, 'category', array( 'slug' => $slug ) );
		if ( ! is_wp_error( $created ) ) {
			$cat_ids[ $slug ] = (int) $created['term_id'];
		}
	}

	$posts         = array(
		array(
			'slug'  => 'sample-insight-one',
			'title' => 'A practical insight on getting started',
			'cat'   => 'insights',
		),
		array(
			'slug'  => 'sample-insight-two',
			'title' => 'What good looks like, in plain terms',
			'cat'   => 'insights',
		),
		array(
			'slug'  => 'sample-briefing-one',
			'title' => 'A short briefing on a common decision',
			'cat'   => 'briefings',
		),
		array(
			'slug'  => 'sample-briefing-two',
			'title' => 'Trade-offs worth weighing early',
			'cat'   => 'briefings',
		),
		array(
			'slug'  => 'sample-note-one',
			'title' => 'A quick note on process',
			'cat'   => 'notes',
		),
		array(
			'slug'  => 'sample-note-two',
			'title' => 'A quick note on outcomes',
			'cat'   => 'notes',
		),
	);
	$demo_image_id = pediment_seed_demo_image();

	foreach ( $posts as $p ) {
		$existing = get_page_by_path( $p['slug'], OBJECT, 'post' );
		if ( $existing ) {
			if ( $demo_image_id && ! has_post_thumbnail( $existing ) ) {
				set_post_thumbnail( $existing, $demo_image_id );
			}
			continue;
		}
		$post_id = wp_insert_post(
			array(
				'post_type'    => 'post',
				'post_status'  => 'publish',
				'post_title'   => $p['title'],
				'post_name'    => $p['slug'],
				'post_excerpt' => 'A one-sentence summary of this sample article, ready to be replaced.',
				'post_content' => '<!-- wp:paragraph --><p>Replace this sample article with your own writing.</p><!-- /wp:paragraph -->',
			),
			true
		);
		if ( ! is_wp_error( $post_id ) && isset( $cat_ids[ $p['cat'] ] ) ) {
			wp_set_post_categories( (int) $post_id, array( $cat_ids[ $p['cat'] ] ) );
		}
		if ( ! is_wp_error( $post_id ) && $demo_image_id ) {
			set_post_thumbnail( (int) $post_id, $demo_image_id );
		}
	}
}
