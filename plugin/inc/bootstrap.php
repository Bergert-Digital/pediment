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

	if ( is_array( $pattern ) && isset( $pattern['content'] ) && is_string( $pattern['content'] ) && '' !== $pattern['content'] ) {
		return $pattern['content'];
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
// Deliberately NOT hooked here to after_switch_theme -- see
// pediment_bootstrap_defer_header_seed() below for why. plugin.php still wires
// this function directly to register_activation_hook(): plugin activation
// does not change which theme is active, so whatever theme's patterns
// _register_theme_block_patterns() already registered on this request's
// `init` (which always runs before an activation hook fires) are the correct
// ones to read immediately.

const PEDIMENT_HEADER_SEED_PENDING_OPTION = 'pediment_header_seed_pending';

/**
 * Handles `after_switch_theme` for the header part. Does NOT seed immediately.
 *
 * `after_switch_theme` gives no guarantee that the newly active theme's own
 * `patterns/*.php` have been scanned yet. That scan is a wholly separate core
 * mechanism -- `_register_theme_block_patterns()` (`wp-includes/block-
 * patterns.php`), hooked on `init` at the default priority 10 -- with no
 * ordering tie to this hook beyond incidental priority numbers this plugin
 * doesn't control. (In WordPress core today, `after_switch_theme` itself is
 * usually fired by `check_theme_switched()`, hooked on `init` at priority 99,
 * *not* synchronously inside `switch_theme()` -- but relying on that
 * incidental fact would be fragile, and it does not hold for every possible
 * caller of this hook, e.g. code that fires it directly.) Reading the pattern
 * registry here, synchronously, risks silently taking the generic fallback
 * regardless of what the new theme ships -- exactly the bug this function
 * exists to avoid re-introducing.
 *
 * Instead this sets a cheap pending flag and defers the actual seeding to
 * pediment_bootstrap_maybe_seed_header(), which runs on `init` at a priority
 * (100) after both of the above have had a chance to run.
 */
function pediment_bootstrap_defer_header_seed(): void {
	update_option( PEDIMENT_HEADER_SEED_PENDING_OPTION, 1, true );
}
add_action( 'after_switch_theme', 'pediment_bootstrap_defer_header_seed' );

/**
 * Consumes the flag set by pediment_bootstrap_defer_header_seed(), on `init`
 * at priority 100 -- after core's `_register_theme_block_patterns()`
 * (priority 10) has registered the now-active theme's own patterns, and after
 * `check_theme_switched()` (priority 99) has fired `after_switch_theme` for
 * this same request if a theme switch is pending. Reading the flag costs no
 * extra query on the requests where it isn't set: it's a normal autoloaded
 * option, already present in the single `alloptions` read WordPress performs
 * on every request.
 */
function pediment_bootstrap_maybe_seed_header(): void {
	if ( ! get_option( PEDIMENT_HEADER_SEED_PENDING_OPTION ) ) {
		return;
	}

	delete_option( PEDIMENT_HEADER_SEED_PENDING_OPTION );
	pediment_bootstrap_header_template_part();
}
add_action( 'init', 'pediment_bootstrap_maybe_seed_header', 100 );

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
