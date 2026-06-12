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
