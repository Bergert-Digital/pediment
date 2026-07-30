<?php
/**
 * Registers the plugin's block templates so they render regardless of which
 * theme is active (Task 6 of the plugin-absorbs-theme migration).
 *
 * @package Pediment
 */

declare(strict_types=1);

namespace Pediment\Templates;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers `pediment//*` block templates from plugin/templates/*.html.
 */
class Registrar {
	private const TEMPLATES = array(
		'404'        => '404 (Pediment)',
		'archive'    => 'Archive (Pediment)',
		'front-page' => 'Front Page (Pediment)',
		'home'       => 'Home (Pediment)',
		'index'      => 'Index (Pediment)',
		'page'       => 'Page (Pediment)',
		'single'     => 'Single (Pediment)',
	);

	/**
	 * Wires the registration hook. Call once, e.g. from Bootstrap::register().
	 *
	 * @return void
	 */
	public static function register(): void {
		add_action( 'init', array( self::class, 'register_templates' ) );
		add_filter( 'get_block_templates', array( self::class, 'normalize_template_query_results' ), PHP_INT_MAX );
	}

	/**
	 * Ensures template queries return a list rather than registry-keyed array.
	 *
	 * WP_Block_Templates_Registry::get_by_query() preserves a registered
	 * template's namespaced key (for example, `pediment//page`). WordPress 6.9
	 * then assumes `get_block_templates( array( 'slug__in' => array( 'page' ) ) )`
	 * has a numeric zero index while building editor settings. Reindexing at the
	 * public query boundary preserves the documented WP_Block_Template[] result
	 * while making that core consumer safe.
	 *
	 * @param WP_Block_Template[] $templates Queried templates.
	 * @return WP_Block_Template[] Numerically indexed templates.
	 */
	public static function normalize_template_query_results( $templates ) {
		return array_values( $templates );
	}

	/**
	 * Registers every known template whose HTML file is readable.
	 *
	 * @return void
	 */
	public static function register_templates(): void {
		$registry = \WP_Block_Templates_Registry::get_instance();
		foreach ( self::TEMPLATES as $slug => $title ) {
			// 'init' can fire more than once per request (several test suites
			// and some plugins re-fire it deliberately); registering the same
			// template name twice trips a _doing_it_wrong() notice.
			if ( $registry->is_registered( 'pediment//' . $slug ) ) {
				continue;
			}
			$file = PEDIMENT_AI_PLUGIN_DIR . '/templates/' . $slug . '.html';
			if ( ! is_readable( $file ) ) {
				continue;
			}
			register_block_template(
				'pediment//' . $slug,
				array(
					'title'   => $title,
					'content' => file_get_contents( $file ),
				)
			);
		}
	}
}
