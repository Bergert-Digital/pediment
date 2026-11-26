<?php
/**
 * Turns off WordPress's emoji script, styles and image replacement.
 *
 * @package Pediment
 */

declare(strict_types=1);

namespace Pediment\Privacy;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Core prints an emoji detection script on every page. In a browser that
 * cannot draw an emoji, the script loads the emoji as an image from
 * s.w.org: a request to a third party before the visitor consents (GDPR).
 * Feeds and HTML emails get the same s.w.org images. Every browser in use
 * today draws emoji itself, so Pediment turns all of it off: on the front
 * end, in wp-admin and the block editor, in embeds, feeds and emails. Emoji
 * stay in the content as Unicode characters.
 */
final class Emoji {

	/** Filter: whether WordPress's emoji script, styles and images stay on. */
	public const FILTER_ENABLED = 'pediment_emoji_enabled';

	/**
	 * The core callbacks that load emoji from s.w.org, as
	 * [hook, callback, priority]. The list matches wp-includes/default-filters.php
	 * and wp-admin/includes/admin-filters.php.
	 */
	private const CORE_HOOKS = array(
		array( 'wp_head', 'print_emoji_detection_script', 7 ),
		array( 'wp_enqueue_scripts', 'wp_enqueue_emoji_styles', 10 ),
		array( 'wp_print_styles', 'print_emoji_styles', 10 ),
		array( 'admin_print_scripts', 'print_emoji_detection_script', 10 ),
		array( 'admin_enqueue_scripts', 'wp_enqueue_emoji_styles', 10 ),
		array( 'admin_print_styles', 'print_emoji_styles', 10 ),
		array( 'embed_head', 'print_emoji_detection_script', 10 ),
		array( 'enqueue_embed_scripts', 'wp_enqueue_emoji_styles', 10 ),
		array( 'the_content_feed', 'wp_staticize_emoji', 10 ),
		array( 'comment_text_rss', 'wp_staticize_emoji', 10 ),
		array( 'wp_mail', 'wp_staticize_emoji_for_email', 10 ),
	);

	/**
	 * Wires the hooks. The decision waits for `init`, so a filter in the
	 * client theme's functions.php counts. wp-admin adds its own emoji
	 * callbacks after `init` (admin-filters.php), so it runs again on
	 * `admin_init`.
	 */
	public static function register(): void {
		add_action( 'init', array( self::class, 'apply' ) );
		add_action( 'admin_init', array( self::class, 'apply' ) );
	}

	/**
	 * Whether WordPress's emoji support stays on. Default: false.
	 */
	public static function enabled(): bool {
		/**
		 * Filters whether WordPress's emoji detection script, emoji styles
		 * and emoji images (in feeds and emails) stay on. They load emoji
		 * images from s.w.org, a third party. Default: false.
		 *
		 * @param bool $enabled Whether to keep WordPress's emoji support.
		 */
		return (bool) apply_filters( self::FILTER_ENABLED, false );
	}

	/**
	 * Removes the core emoji callbacks unless the filter keeps them.
	 */
	public static function apply(): void {
		if ( self::enabled() ) {
			return;
		}
		foreach ( self::CORE_HOOKS as list( $hook, $callback, $priority ) ) {
			remove_action( $hook, $callback, $priority );
		}
		// The classic editor (TinyMCE) has its own emoji plugin.
		add_filter( 'tiny_mce_plugins', array( self::class, 'withoutTinyMcePlugin' ) );
	}

	/**
	 * Drops TinyMCE's `wpemoji` plugin.
	 *
	 * @param mixed $plugins TinyMCE plugin names.
	 * @return mixed
	 */
	public static function withoutTinyMcePlugin( $plugins ) {
		return is_array( $plugins ) ? array_values( array_diff( $plugins, array( 'wpemoji' ) ) ) : $plugins;
	}
}
