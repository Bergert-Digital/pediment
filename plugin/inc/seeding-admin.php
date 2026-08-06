<?php
/**
 * Settings > Pediment Theme > Seeding.
 *
 * The same Runner the CLI uses, with PHP limits lifted: identical code passing
 * under WP-CLI and dying with a generic critical error in wp-admin is what
 * bfd550f cost a day to.
 *
 * @package Pediment
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action(
	'admin_menu',
	function () {
		if ( function_exists( 'pediment_settings_register_tab' ) ) {
			pediment_settings_register_tab( 'seed', __( 'Seeding', 'pediment' ), 'pediment_seed_admin_render_tab', 20 );
		}
	}
);

/**
 * Read or set the message shown when a submission was rejected.
 *
 * A rejected submission must never look like an untouched page: on admin-only
 * hosting this tab is the only way to seed a site, and an expired nonce is the
 * likeliest thing to go wrong on a tab left open.
 *
 * @param string|null $set Message to store, or null to read the current one.
 * @return string
 */
function pediment_seed_admin_notice( ?string $set = null ): string {
	static $message = '';
	if ( null !== $set ) {
		$message = $set;
	}
	return $message;
}

/**
 * Handle a seed form submission.
 *
 * @return string|null Rendered report, or null when this is not a valid seed submission.
 */
function pediment_seed_admin_handle_post(): ?string {
	pediment_seed_admin_notice( '' );

	$action = isset( $_POST['pediment_seed_action'] ) ? sanitize_key( wp_unslash( $_POST['pediment_seed_action'] ) ) : '';
	if ( ! in_array( $action, array( 'preview', 'apply', 'claim-preview', 'claim-apply' ), true ) ) {
		return null;
	}
	if ( ! current_user_can( 'manage_options' ) ) {
		pediment_seed_admin_notice( __( 'You do not have permission to seed this site.', 'pediment' ) );
		return null;
	}
	$nonce = isset( $_POST['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ) ) : '';
	if ( ! wp_verify_nonce( $nonce, 'pediment_seed' ) ) {
		pediment_seed_admin_notice( __( 'That form had expired, so nothing was run. Please try again.', 'pediment' ) );
		return null;
	}

	// Seeding a large site writes hundreds of rows and generates image sizes.
	if ( function_exists( 'set_time_limit' ) ) {
		// phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- disabled by some hosts.
		@set_time_limit( 0 );
	}
	wp_raise_memory_limit( 'admin' );

	if ( 'claim-preview' === $action || 'claim-apply' === $action ) {
		return pediment_seed_admin_run_claim( 'claim-apply' === $action );
	}

	$result = ( new \Pediment\Seeder\Runner() )->run( array( 'dry_run' => 'preview' === $action ) );

	return \Pediment\Seeder\Reporter::text( $result );
}

/**
 * Run a claim from wp-admin.
 *
 * Admin-only hosting has no WP-CLI, so this is the only path a live site can
 * take to give its existing content seed identity before the first seed.
 * The same ClaimRunner the CLI uses (see the file docblock above for why
 * that matters).
 *
 * @param bool $apply Whether to write, as opposed to previewing.
 * @return string Rendered report.
 */
function pediment_seed_admin_run_claim( bool $apply ): string {
	$result = ( new \Pediment\Seeder\ClaimRunner() )->run( array( 'dry_run' => ! $apply ) );

	return \Pediment\Seeder\Reporter::claimText( $result );
}

/**
 * Render the tab body.
 *
 * @return void
 */
function pediment_seed_admin_render_tab(): void {
	$report = pediment_seed_admin_handle_post();

	$notice = pediment_seed_admin_notice();

	if ( '' !== $notice ) {
		echo '<div class="notice notice-error"><p>' . esc_html( $notice ) . '</p></div>';
	}

	echo '<p>' . esc_html__(
		'Applies the active theme\'s seed manifest. Structure (which pages exist, their slugs, nesting, menus) is owned by the theme; page content you have edited in the editor is never overwritten.',
		'pediment'
	) . '</p>';

	// Two forms rather than two submit buttons in one: each carries its own
	// hidden action, so the POST is unambiguous and no JS is involved.
	echo '<div style="display:flex;gap:8px;align-items:center;">';
	foreach ( array(
		'preview' => array( __( 'Preview plan', 'pediment' ), 'secondary' ),
		'apply'   => array( __( 'Apply plan', 'pediment' ), 'primary' ),
	) as $value => $button ) {
		echo '<form method="post" style="margin:0;">';
		wp_nonce_field( 'pediment_seed' );
		echo '<input type="hidden" name="pediment_seed_action" value="' . esc_attr( $value ) . '" />';
		submit_button( $button[0], $button[1], 'submit', false );
		echo '</form>';
	}
	echo '</div>';

	echo '<hr />';
	echo '<h3>' . esc_html__( 'Claim existing content', 'pediment' ) . '</h3>';
	echo '<p>' . esc_html__(
		'For a site whose pages were built before Pediment. Matches existing pages, posts and menus to the manifest by slug and language and gives them the identity the seeder resolves by. The claim itself writes nothing but that identity, and a claimed page then stays protected from content updates until you adopt it. Run this once, and preview first.',
		'pediment'
	) . '</p>';

	// The claim promise holds for pages, not for menus, and the panel used to
	// imply otherwise — it told the operator to run claim and then Apply plan,
	// while promising menus were untouched. There is no hash arbitration for
	// navs: menu membership is git-owned, so the next Apply plan rewrites a
	// claimed menu's links. Say so where the buttons are, not only in the docs.
	echo '<p><strong>' . esc_html__(
		'Menus are the exception.',
		'pediment'
	) . '</strong> ' . esc_html__(
		'Menu membership is owned by the theme, so the next "Apply plan" rewrites a claimed menu\'s links — a claimed menu is not protected the way a claimed page is. And when the manifest declares one menu and this site has exactly one unclaimed menu, that menu is claimed without any slug or title check. Read every navigation line in the preview and confirm it names the menu you meant.',
		'pediment'
	) . '</p>';

	echo '<div style="display:flex;gap:8px;align-items:center;">';
	foreach ( array(
		'claim-preview' => array( __( 'Preview claim', 'pediment' ), 'secondary' ),
		'claim-apply'   => array( __( 'Claim content', 'pediment' ), 'primary' ),
	) as $value => $button ) {
		echo '<form method="post" style="margin:0;">';
		wp_nonce_field( 'pediment_seed' );
		echo '<input type="hidden" name="pediment_seed_action" value="' . esc_attr( $value ) . '" />';
		submit_button( $button[0], $button[1], 'submit', false );
		echo '</form>';
	}
	echo '</div>';

	if ( null !== $report ) {
		echo '<pre class="pediment-seed-report" style="max-height:60vh;overflow:auto;padding:12px;background:#fff;border:1px solid #c3c4c7;">'
			. esc_html( $report ) . '</pre>';
	}
}
