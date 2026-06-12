<?php
/**
 * Manual "Check for theme updates" button on Dashboard → Updates.
 *
 * WordPress offers no per-theme re-check UI on the Updates screen, and Plugin
 * Update Checker's built-in manual-check link exists for plugins only. This
 * file renders a small section via the documented core_upgrade_preamble hook
 * and forces a cache-bypassing PUC check for every registered Pediment theme.
 *
 * @package Pediment
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Update checkers registered by the parent and (when active) child theme.
 *
 * Each entry: array{slug: string, name: string, checker: object}. Empty when
 * the updaters are disabled (local environments) or PUC is not installed.
 *
 * @return array[] Checker entries.
 */
function pediment_update_checkers(): array {
	$checkers = apply_filters( 'pediment_update_checkers', array() );
	if ( ! is_array( $checkers ) ) {
		return array();
	}
	return array_values( array_filter( $checkers, 'is_array' ) );
}

/**
 * Force one cache-bypassing update check and normalise the outcome.
 *
 * @param array $entry Checker entry from pediment_update_checkers().
 * @return array{name: string, status: string, installed: string, new_version: string}
 *               status is one of 'update', 'current', 'error'.
 */
function pediment_run_update_check( array $entry ): array {
	$theme  = wp_get_theme( $entry['slug'] );
	$result = array(
		'name'        => $entry['name'],
		'status'      => 'error',
		'installed'   => $theme->exists() ? (string) $theme->get( 'Version' ) : '',
		'new_version' => '',
	);

	try {
		$update = $entry['checker']->checkForUpdates();
		$errors = $entry['checker']->getLastRequestApiErrors();
	} catch ( \Throwable $e ) {
		return $result;
	}

	if ( null !== $update && isset( $update->version ) ) {
		$result['status']      = 'update';
		$result['new_version'] = (string) $update->version;
	} elseif ( array() === $errors ) {
		$result['status'] = 'current';
	}

	return $result;
}

add_action( 'core_upgrade_preamble', 'pediment_render_update_check_section' );

/**
 * Render the "Theme Updates" section at the bottom of Dashboard → Updates.
 */
function pediment_render_update_check_section(): void {
	$checkers = pediment_update_checkers();
	if ( array() === $checkers || ! current_user_can( 'update_themes' ) ) {
		return;
	}

	echo '<h2>' . esc_html__( 'Theme Updates', 'pediment' ) . '</h2>';
	echo '<p>' . esc_html__( 'Check GitHub for new releases of the Pediment themes. Updates found here appear in the Themes list above.', 'pediment' ) . '</p>';
	echo '<ul>';
	foreach ( $checkers as $entry ) {
		$theme   = wp_get_theme( $entry['slug'] );
		$version = $theme->exists() ? (string) $theme->get( 'Version' ) : '';
		$last    = (int) $entry['checker']->getUpdateState()->getLastCheck();
		if ( $last > 0 ) {
			/* translators: %s: human-readable time difference, e.g. "3 hours". */
			$checked = sprintf( __( 'last checked %s ago', 'pediment' ), human_time_diff( $last ) );
		} else {
			$checked = __( 'not checked yet', 'pediment' );
		}
		echo '<li><strong>' . esc_html( $entry['name'] ) . '</strong> ' . esc_html( $version ) . ' (' . esc_html( $checked ) . ')</li>';
	}
	echo '</ul>';

	echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
	echo '<input type="hidden" name="action" value="pediment_check_theme_updates" />';
	wp_nonce_field( 'pediment_check_theme_updates' );
	submit_button( __( 'Check for theme updates', 'pediment' ), 'secondary', 'pediment-check-updates', false );
	echo '</form>';
}

/**
 * Run every registered checker and stash per-theme results for the notice.
 */
function pediment_store_update_check_results(): void {
	$results = array();
	foreach ( pediment_update_checkers() as $entry ) {
		$results[] = pediment_run_update_check( $entry );
	}
	if ( array() !== $results ) {
		set_transient( 'pediment_update_check_' . get_current_user_id(), $results, MINUTE_IN_SECONDS );
	}
}

add_action( 'admin_post_pediment_check_theme_updates', 'pediment_handle_update_check' );

/**
 * Handle the button submission: verify intent, check, redirect back.
 */
function pediment_handle_update_check(): void {
	check_admin_referer( 'pediment_check_theme_updates' );
	if ( ! current_user_can( 'update_themes' ) ) {
		wp_die( esc_html__( 'Sorry, you are not allowed to update themes for this site.', 'pediment' ) );
	}

	pediment_store_update_check_results();

	wp_safe_redirect( self_admin_url( 'update-core.php' ) );
	exit;
}
