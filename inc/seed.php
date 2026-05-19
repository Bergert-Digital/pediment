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
			'content' => '<!-- wp:starter/blog-index {"count":10,"align":"wide"} /-->',
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
	if ( class_exists( 'WP_Block_Patterns_Registry' ) ) {
		$pattern = WP_Block_Patterns_Registry::get_instance()->get_registered( 'starter/pediment-landing' );
		if ( is_array( $pattern ) && ! empty( $pattern['content'] ) ) {
			return (string) $pattern['content'];
		}
	}
	return '<!-- wp:starter/hero {"variant":"centered","headline":"Welcome","subheadline":"A short benefit-led promise.","ctaText":"Get started","ctaUrl":"/contact","align":"wide"} /-->' .
		'<!-- wp:starter/cta {"title":"Ready to start?","body":"Tell us about your project.","primaryText":"Contact us","primaryUrl":"/contact","align":"wide"} /-->' .
		'<!-- wp:starter/blog-index {"count":3,"align":"wide"} /-->';
}
