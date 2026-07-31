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
 * Handle a seed form submission.
 *
 * @return string|null Rendered report, or null when this is not a valid seed submission.
 */
function pediment_seed_admin_handle_post(): ?string {
	$action = isset( $_POST['pediment_seed_action'] ) ? sanitize_key( wp_unslash( $_POST['pediment_seed_action'] ) ) : '';
	if ( ! in_array( $action, array( 'preview', 'apply' ), true ) ) {
		return null;
	}
	if ( ! current_user_can( 'manage_options' ) ) {
		return null;
	}
	$nonce = isset( $_POST['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ) ) : '';
	if ( ! wp_verify_nonce( $nonce, 'pediment_seed' ) ) {
		return null;
	}

	// Seeding a large site writes hundreds of rows and generates image sizes.
	if ( function_exists( 'set_time_limit' ) ) {
		// phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- disabled by some hosts.
		@set_time_limit( 0 );
	}
	wp_raise_memory_limit( 'admin' );

	$result = ( new \Pediment\Seeder\Runner() )->run( array( 'dry_run' => 'preview' === $action ) );

	return \Pediment\Seeder\Reporter::text( $result );
}

/**
 * Render the tab body.
 *
 * @return void
 */
function pediment_seed_admin_render_tab(): void {
	$report = pediment_seed_admin_handle_post();

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

	if ( null !== $report ) {
		echo '<pre class="pediment-seed-report" style="max-height:60vh;overflow:auto;padding:12px;background:#fff;border:1px solid #c3c4c7;">'
			. esc_html( $report ) . '</pre>';
	}
}
