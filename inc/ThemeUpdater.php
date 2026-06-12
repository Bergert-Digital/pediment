<?php
/**
 * GitHub-release auto-updates for the Pediment parent theme.
 *
 * Points Plugin Update Checker at the public GitHub repo's releases so theme
 * updates arrive through wp-admin's normal one-click flow (Dashboard → Updates
 * / Appearance → Themes) instead of manual zip uploads.
 *
 * @package Pediment
 */

declare(strict_types=1);

namespace Pediment;

use YahnisElsts\PluginUpdateChecker\v5\PucFactory;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class ThemeUpdater {
	/** Public repo whose GitHub Releases drive theme updates. */
	private const REPO_URL = 'https://github.com/Bergert-Digital/pediment/';

	/**
	 * Wire the update checker to this repo's GitHub releases.
	 */
	public static function register(): void {
		if ( ! class_exists( PucFactory::class ) ) {
			return;
		}

		// Skip update checks in local/dev environments (wp-env, CI). There is no
		// point hitting the GitHub API on every admin load there, and the
		// synchronous check slows the block editor. Real client sites default to
		// the 'production' environment type.
		if ( function_exists( 'wp_get_environment_type' ) && 'local' === wp_get_environment_type() ) {
			return;
		}

		// get_template_directory(): always the parent theme dir, even when a
		// child theme is the active stylesheet.
		$checker = PucFactory::buildUpdateChecker(
			self::REPO_URL,
			get_template_directory() . '/style.css',
			'pediment'
		);

		// Fallback branch for reading the version header if a release is ever absent.
		if ( method_exists( $checker, 'setBranch' ) ) {
			$checker->setBranch( 'main' );
		}

		// Install the built release asset (pediment.zip) rather than GitHub's
		// auto-generated "Source code" zip, which has the wrong folder name and
		// ships no vendor/ autoloader.
		$api = $checker->getVcsApi();
		if ( method_exists( $api, 'enableReleaseAssets' ) ) {
			$api->enableReleaseAssets( '/pediment\.zip$/' );
		}

		// Expose the checker so inc/update-check.php can offer a manual
		// "Check for theme updates" button on Dashboard → Updates.
		add_filter(
			'pediment_update_checkers',
			static function ( array $checkers ) use ( $checker ): array {
				$checkers[] = array(
					'slug'    => 'pediment',
					'name'    => 'Pediment',
					'checker' => $checker,
				);
				return $checkers;
			}
		);
	}
}
