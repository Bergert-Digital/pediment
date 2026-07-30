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
		add_filter( 'theme_file_path', array( self::class, 'shim_is_block_theme_detection' ), 10, 2 );
	}

	/**
	 * WordPress decides whether the active theme is a "block theme" purely by
	 * checking `is_file( $theme_dir . '/templates/index.html' )`
	 * (WP_Theme::is_block_theme()) — a plugin registering block templates via
	 * register_block_template() does not count, and the check has no filter of
	 * its own. Once templates/*.html moved out of the theme entirely (this
	 * task), a theme with none of its own trips WordPress into the classic
	 * (non-block) template hierarchy and the front end renders nothing.
	 *
	 * WP_Theme::get_file_path() does run its resolved path through the
	 * `theme_file_path` filter before the is_file() check, so redirecting
	 * exactly the `templates/index.html` lookup to the plugin's copy — only
	 * when the active theme has no such file of its own — is enough to flip
	 * that detection back on. It doesn't affect any other template's
	 * resolution: those still correctly miss the (removed) theme file and
	 * fall through to Pediment\Templates\Registrar's own
	 * register_block_template() entries via the WP_Block_Templates_Registry.
	 *
	 * @param string $path Resolved absolute path.
	 * @param string $file Relative file requested, e.g. "templates/index.html".
	 * @return string
	 */
	public static function shim_is_block_theme_detection( $path, $file ) {
		if ( 'templates/index.html' !== ltrim( (string) $file, '/' ) ) {
			return $path;
		}
		if ( is_readable( $path ) ) {
			// The active theme really does have its own index.html; leave it alone.
			return $path;
		}
		$plugin_path = PEDIMENT_AI_PLUGIN_DIR . '/templates/index.html';
		return is_readable( $plugin_path ) ? $plugin_path : $path;
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
