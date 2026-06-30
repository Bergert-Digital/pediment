<?php
/**
 * Framework bootstrap: make a freshly-activated Pediment site functional
 * (an editable header template part).
 * Runs on theme activation. Carries NO demo content.
 *
 * @package Pediment
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function pediment_bootstrap(): void {
	pediment_bootstrap_header_template_part();

	// Intentionally leave the permalink structure untouched. Forcing pretty
	// permalinks here breaks REST in containerized installs (wp-env, the official
	// WordPress image) where Apache's .htaccess/AllowOverride isn't honored: the
	// flush writes correct rules but Apache never serves the ^wp-json/ rule, so
	// rest_url() resolves to /wp-json/… and 404s, breaking every editor save.
	// On the plain default, rest_url() routes through ?rest_route=… which needs
	// no rewrite and works on every SAPI. Real hosting opts into pretty
	// permalinks via Settings → Permalinks, which flushes correctly there. See
	// Bergert-Digital/pediment#47.
}
add_action( 'after_switch_theme', 'pediment_bootstrap' );

/**
 * Seed an editable, DB-backed `header` wp_template_part so per-site header edits
 * (logo, nav, CTA, spacers, …) persist via the Site Editor instead of requiring
 * theme-file changes. Idempotent: skips when a header part already exists for the
 * active theme.
 */
function pediment_bootstrap_header_template_part(): void {
	$theme = get_stylesheet();

	$existing = get_posts(
		array(
			'name'        => 'header',
			'post_type'   => 'wp_template_part',
			'post_status' => 'publish',
			'numberposts' => 1,
			'fields'      => 'ids',
			// phpcs:ignore WordPress.DB.SlowDBQuery -- seed lookup runs once per activation; tax query acceptable here.
			'tax_query'   => array(
				array(
					'taxonomy' => 'wp_theme',
					'field'    => 'name',
					'terms'    => $theme,
				),
			),
		)
	);
	if ( ! empty( $existing ) ) {
		return;
	}

	$markup = '<!-- wp:group {"tagName":"header","className":"site-header","style":{"spacing":{"padding":{"top":"var:preset|spacing|20","bottom":"var:preset|spacing|20"},"blockGap":"0"},"border":{"bottom":{"color":"var:preset|color|border","width":"1px"}}},"backgroundColor":"surface","layout":{"type":"constrained"}} -->'
		. '<header class="wp-block-group site-header has-border-color has-surface-background-color has-background" style="border-bottom-color:var(--wp--preset--color--border);border-bottom-width:1px;padding-top:var(--wp--preset--spacing--20);padding-bottom:var(--wp--preset--spacing--20)">'
		. '<!-- wp:group {"align":"wide","layout":{"type":"flex","justifyContent":"space-between","flexWrap":"nowrap"},"style":{"spacing":{"blockGap":"0"}}} -->'
		. '<div class="wp-block-group alignwide">'
		. '<!-- wp:group {"className":"brand","layout":{"type":"flex","flexWrap":"nowrap"}} -->'
		. '<div class="wp-block-group brand">'
		. '<!-- wp:site-logo {"width":150} /-->'
		. '</div>'
		. '<!-- /wp:group -->'
		. '<!-- wp:navigation {"overlayMenu":"mobile","layout":{"type":"flex","orientation":"horizontal","flexWrap":"nowrap"},"style":{"spacing":{"blockGap":"var:preset|spacing|30"},"typography":{"fontWeight":"600"}}} /-->'
		. '<!-- wp:buttons -->'
		. '<div class="wp-block-buttons">'
		. '<!-- wp:button {"backgroundColor":"accent","textColor":"surface","style":{"border":{"radius":"999px"}}} -->'
		. '<div class="wp-block-button"><a class="wp-block-button__link has-surface-color has-accent-background-color has-text-color has-background wp-element-button" href="/contact" style="border-radius:999px">Contact</a></div>'
		. '<!-- /wp:button -->'
		. '</div>'
		. '<!-- /wp:buttons -->'
		. '</div>'
		. '<!-- /wp:group -->'
		. '</header>'
		. '<!-- /wp:group -->';

	$id = wp_insert_post(
		array(
			'post_type'    => 'wp_template_part',
			'post_status'  => 'publish',
			'post_name'    => 'header',
			'post_title'   => 'Header',
			'post_content' => $markup,
		),
		true
	);
	if ( is_wp_error( $id ) || ! $id ) {
		return;
	}
	wp_set_object_terms( (int) $id, $theme, 'wp_theme' );
	wp_set_object_terms( (int) $id, 'header', 'wp_template_part_area' );
}
