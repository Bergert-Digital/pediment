<?php
/**
 * WP-CLI: `wp starter-theme seed` — populate Brand defaults + sample pages.
 *
 * @package Starter
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( defined( 'WP_CLI' ) && WP_CLI ) {
	\WP_CLI::add_command( 'starter-theme seed', 'starter_seed_cli' );
}

function starter_seed_cli(): void {
	starter_seed_run();
	if ( class_exists( '\\WP_CLI' ) ) {
		\WP_CLI::success( 'Starter theme seeded.' );
	}
}

function starter_seed_run(): void {
	$brand_defaults = array(
		'brand_name'    => get_bloginfo( 'name' ) ?: 'Acme',
		'brand_tagline' => 'Short benefit-led promise.',
		'voice_tone'    => 'Confident, plain-spoken, no buzzwords.',
		'contact_email' => get_option( 'admin_email' ),
	);
	foreach ( $brand_defaults as $k => $v ) {
		if ( '' === (string) \Starter\Brand::get( $k, '' ) ) {
			\Starter\Brand::set( $k, $v );
		}
	}

	starter_seed_demo_image();
	starter_seed_demo_logo();

	$pages = array(
		'home'    => array(
			'title'   => 'Home',
			'content' => starter_pediment_landing_content(),
		),
		'about'   => array(
			'title'   => 'About',
			'content' =>
				'<!-- wp:starter/hero {"variant":"default","headline":"About us","subheadline":"Who we are and what we do.","align":"wide"} /-->' .
				'<!-- wp:starter/prose -->' .
					'<!-- wp:paragraph --><p>Tell your story here. Keep it human and specific.</p><!-- /wp:paragraph -->' .
				'<!-- /wp:starter/prose -->',
		),
		'contact' => array(
			'title'   => 'Contact',
			'content' =>
				'<!-- wp:starter/hero {"variant":"centered","headline":"Contact","subheadline":"Tell us about your project.","align":"wide"} /-->' .
				'<!-- wp:starter/contact-form {"includePhone":true} /-->',
		),
		'blog'    => array(
			'title'   => 'Blog',
			// home.html renders the listing; the page's own content is unused.
			'content' => '',
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

	starter_seed_sample_posts();

	if ( function_exists( 'starter_nav_seed_entity' ) ) {
		starter_nav_seed_entity();
	}
}

/**
 * The Pediment landing pattern content for the Home page.
 *
 * Reads the registered `starter/pediment-landing` pattern. Falls back to a
 * minimal valid block composition so seeding never writes an empty Home even
 * if patterns are unavailable.
 *
 * @return string Block markup.
 */
function starter_pediment_landing_content(): string {
	$content = '';
	if ( class_exists( 'WP_Block_Patterns_Registry' ) ) {
		$pattern = WP_Block_Patterns_Registry::get_instance()->get_registered( 'starter/pediment-landing' );
		if ( is_array( $pattern ) && ! empty( $pattern['content'] ) ) {
			$content = (string) $pattern['content'];
		}
	}
	if ( '' === $content ) {
		$content = '<!-- wp:starter/hero {"variant":"centered","headline":"Welcome","subheadline":"A short benefit-led promise.","ctaText":"Get started","ctaUrl":"/contact","align":"wide"} /-->' .
			'<!-- wp:starter/cta {"title":"Ready to start?","body":"Tell us about your project.","primaryText":"Contact us","primaryUrl":"/contact","align":"wide"} /-->' .
			'<!-- wp:starter/blog-index {"count":3,"align":"wide"} /-->';
	}
	return starter_seed_apply_demo_image( $content );
}

/**
 * Idempotently sideload the demo image and tag it for easy cleanup.
 *
 * The marker meta `_starter_seed_demo` makes removal trivial:
 *   wp post list --post_type=attachment --meta_key=_starter_seed_demo --field=ID
 *   | xargs -I{} wp post delete {} --force
 *
 * @return int Attachment ID, or 0 on failure.
 */
function starter_seed_demo_image(): int {
	$existing = get_posts(
		array(
			'post_type'   => 'attachment',
			'post_status' => 'inherit',
			'numberposts' => 1,
			'fields'      => 'ids',
			'meta_key'    => '_starter_seed_demo',
			'meta_value'  => '1',
		)
	);
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
		@unlink( $dest );
		return 0;
	}

	wp_update_attachment_metadata( (int) $attach_id, wp_generate_attachment_metadata( (int) $attach_id, $dest ) );
	update_post_meta( (int) $attach_id, '_starter_seed_demo', '1' );

	return (int) $attach_id;
}

/**
 * Idempotently sideload the wide demo logo and set it as the site's
 * Custom Logo. Mirrors starter_seed_demo_image().
 *
 * The marker meta `_starter_seed_demo_logo` makes removal trivial:
 *   wp post list --post_type=attachment --meta_key=_starter_seed_demo_logo --field=ID
 *   | xargs -I{} wp post delete {} --force
 *
 * @return int Attachment ID, or 0 on failure.
 */
function starter_seed_demo_logo(): int {
	$existing = get_posts(
		array(
			'post_type'   => 'attachment',
			'post_status' => 'inherit',
			'numberposts' => 1,
			'fields'      => 'ids',
			'meta_key'    => '_starter_seed_demo_logo',
			'meta_value'  => '1',
		)
	);
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
		@unlink( $dest );
		return 0;
	}

	update_post_meta( (int) $attach_id, '_starter_seed_demo_logo', '1' );
	set_theme_mod( 'custom_logo', (int) $attach_id );

	return (int) $attach_id;
}

/**
 * Bake the seeded demo attachment into the pediment-landing pattern content:
 * adds `mediaId` to the stat-card hero and fills the empty approach-band image.
 */
function starter_seed_apply_demo_image( string $content ): string {
	$id = starter_seed_demo_image();
	if ( ! $id ) {
		return $content;
	}

	$content = preg_replace_callback(
		'/<!-- wp:starter\/hero (\{[^}]*"variant":"stat-card"[^}]*\}) \/-->/',
		function ( $m ) use ( $id ) {
			$attrs = json_decode( $m[1], true );
			if ( ! is_array( $attrs ) ) {
				return $m[0];
			}
			if ( empty( $attrs['mediaId'] ) ) {
				$attrs['mediaId'] = $id;
			}
			return '<!-- wp:starter/hero ' . wp_json_encode( $attrs ) . ' /-->';
		},
		$content
	);

	$empty_image = "<!-- wp:image {\"sizeSlug\":\"large\",\"className\":\"starter-approach__image\"} -->\n"
		. "<figure class=\"wp-block-image size-large starter-approach__image\"><img alt=\"\" /></figure>\n"
		. '<!-- /wp:image -->';
	$url = (string) wp_get_attachment_image_url( $id, 'large' );
	$populated_image = sprintf(
		"<!-- wp:image {\"id\":%d,\"sizeSlug\":\"large\",\"className\":\"starter-approach__image\"} -->\n"
		. "<figure class=\"wp-block-image size-large starter-approach__image\"><img src=\"%s\" alt=\"\" class=\"wp-image-%d\" /></figure>\n"
		. '<!-- /wp:image -->',
		$id,
		esc_url( $url ),
		$id
	);
	$content = str_replace( $empty_image, $populated_image, $content );

	return $content;
}

/**
 * Idempotently create sample categories + posts so the Insights band
 * (starter/blog-index) renders fully. Skips anything that already exists.
 *
 * @return void
 */
function starter_seed_sample_posts(): void {
	$categories = array(
		'insights'  => 'Insights',
		'briefings' => 'Briefings',
		'notes'     => 'Notes',
	);
	$cat_ids = array();
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

	$posts = array(
		array( 'slug' => 'sample-insight-one',   'title' => 'A practical insight on getting started', 'cat' => 'insights' ),
		array( 'slug' => 'sample-insight-two',   'title' => 'What good looks like, in plain terms',     'cat' => 'insights' ),
		array( 'slug' => 'sample-briefing-one',  'title' => 'A short briefing on a common decision',    'cat' => 'briefings' ),
		array( 'slug' => 'sample-briefing-two',  'title' => 'Trade-offs worth weighing early',          'cat' => 'briefings' ),
		array( 'slug' => 'sample-note-one',      'title' => 'A quick note on process',                  'cat' => 'notes' ),
		array( 'slug' => 'sample-note-two',      'title' => 'A quick note on outcomes',                 'cat' => 'notes' ),
	);
	$demo_image_id = starter_seed_demo_image();

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
