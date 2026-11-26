<?php
/**
 * The SEO title: the text of <title>, separate from the post title.
 *
 * @package Pediment
 */

declare(strict_types=1);

namespace Pediment\Seo;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Each post may carry an SEO title in the meta field `_pediment_seo_title`.
 * When it is set, it is the whole `<title>` of the post's view, with nothing
 * added. When it is empty, WordPress's title stays ("Post title – Site name").
 * The post title stays short, because it is also the heading and the menu
 * label. Off while an SEO plugin is active, like the other head tags.
 */
final class SeoTitle {

	/** Post meta: the SEO title of the post. Each translation keeps its own. */
	public const META = '_pediment_seo_title';

	/** Filter: the SEO title of the current view. */
	public const FILTER = 'pediment_seo_title';

	/**
	 * Wires the hooks.
	 */
	public static function register(): void {
		add_action( 'init', array( self::class, 'registerMeta' ) );
		add_filter( 'pre_get_document_title', array( self::class, 'documentTitle' ) );
	}

	/**
	 * Registers the meta field on every post type. The block editor reads
	 * and writes it over REST (post types with `custom-fields` support), and
	 * every revision keeps it, so a rollback restores it.
	 */
	public static function registerMeta(): void {
		register_post_meta(
			'',
			self::META,
			array(
				'type'              => 'string',
				'description'       => __( 'SEO title: the whole <title> of the page. Empty: the page title and the site name.', 'pediment' ),
				'single'            => true,
				'default'           => '',
				'show_in_rest'      => true,
				'revisions_enabled' => true,
				'sanitize_callback' => array( self::class, 'sanitize' ),
				'auth_callback'     => static fn( $allowed, $key, $post_id ): bool => current_user_can( 'edit_post', (int) $post_id ),
			)
		);
	}

	/**
	 * One line of plain text: no tags, line breaks become spaces.
	 *
	 * @param mixed $value Raw value.
	 */
	public static function sanitize( $value ): string {
		return is_scalar( $value ) ? wp_strip_all_tags( (string) $value, true ) : '';
	}

	/**
	 * Whether a title carries markup, which the abilities and the manifest
	 * refuse instead of stripping it silently.
	 *
	 * @param string $title Title.
	 */
	public static function hasMarkup( string $title ): bool {
		return wp_strip_all_tags( $title ) !== trim( $title );
	}

	/**
	 * The stored SEO title of a post, '' when it has none.
	 *
	 * @param int $post_id Post ID.
	 */
	public static function of( int $post_id ): string {
		return (string) get_post_meta( $post_id, self::META, true );
	}

	/**
	 * Hooked on pre_get_document_title. Returns the escaped SEO title of the
	 * current view, or the value unchanged so WordPress builds its own title.
	 * A title another plugin already chose stays.
	 *
	 * @param mixed $title The title so far; '' lets WordPress build it.
	 */
	public static function documentTitle( $title ) {
		if ( '' !== (string) $title || ! HeadTags::enabled() || is_404() || is_feed() || is_embed() ) {
			return $title;
		}
		$text = self::current();
		return '' === $text ? $title : esc_html( $text );
	}

	/**
	 * The SEO title of the current view, as plain text: the stored title of
	 * the singular post or the posts page, passed through the filter.
	 */
	public static function current(): string {
		$queried = get_queried_object();
		$post    = HeadTags::post( $queried );
		$text    = $post instanceof \WP_Post ? self::of( $post->ID ) : '';
		/**
		 * Filters the SEO title of the current view: the whole text of
		 * `<title>`. An empty string keeps WordPress's own title.
		 *
		 * @param string $title   Plain-text title. Default: the post's SEO title.
		 * @param mixed  $queried The queried object (WP_Post, WP_Term, WP_Post_Type, WP_User or null).
		 */
		$text = (string) apply_filters( self::FILTER, HeadTags::plain( $text ), $queried );
		return HeadTags::plain( $text );
	}
}
