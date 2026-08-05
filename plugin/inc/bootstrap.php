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

/**
 * The generic markup a freshly created `header` part starts life with when no
 * theme has registered a `<stylesheet>/header` pattern. Kept byte-identical to
 * what this project has always shipped.
 */
const PEDIMENT_DEFAULT_HEADER_MARKUP = '<!-- wp:group {"tagName":"header","className":"site-header","style":{"spacing":{"padding":{"top":"var:preset|spacing|20","bottom":"var:preset|spacing|20"},"blockGap":"0"},"border":{"bottom":{"color":"var:preset|color|border","width":"1px"}}},"backgroundColor":"surface","layout":{"type":"constrained"}} -->'
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

/**
 * The markup a freshly created `header` part starts life with.
 *
 * A client theme owns its header by registering a pattern named
 * `<stylesheet>/header`. That keeps the branded markup in git — template parts
 * cannot ship from a plugin, and a theme-file part would not be editable in
 * the Site Editor, which is the property this project chose deliberately.
 * The pattern is read once, at creation; later edits belong to the database.
 */
function pediment_bootstrap_header_markup(): string {
	$registry = \WP_Block_Patterns_Registry::get_instance();
	$pattern  = $registry->get_registered( get_stylesheet() . '/header' );

	if ( is_array( $pattern ) && ! empty( $pattern['content'] ) ) {
		return (string) $pattern['content'];
	}

	return PEDIMENT_DEFAULT_HEADER_MARKUP;
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
			// Deliberately post_name__in, not the singular `name` query var: `name` makes
			// the query singular (WP_Query::parse_query() sets is_single), and
			// WP_Query::get_posts() applies tax_query only when ! $this->is_singular -- so
			// the theme filter below was silently inert, on every site, not just fresh
			// ones. That made this check match ANY existing "header" part regardless of
			// theme and skip seeding for a newly-activated theme. post_name__in keeps the
			// query non-singular, so the tax_query is actually applied.
			'post_name__in' => array( 'header' ),
			'post_type'     => 'wp_template_part',
			'post_status'   => 'publish',
			'numberposts'   => 1,
			'fields'        => 'ids',
			// phpcs:ignore WordPress.DB.SlowDBQuery -- seed lookup runs once per activation; tax query acceptable here.
			'tax_query'     => array(
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

	$markup = pediment_bootstrap_header_markup();

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
