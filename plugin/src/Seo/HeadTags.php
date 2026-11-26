<?php
/**
 * The search and share tags in <head>: meta description, Open Graph and the
 * Twitter card.
 *
 * @package Pediment
 */

declare(strict_types=1);

namespace Pediment\Seo;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Prints `<meta name="description">`, the `og:*` tags and `twitter:card` on
 * every public front-end view. The description is the post excerpt; pages
 * get excerpt support for it. Nothing is printed while an SEO plugin is
 * active, because that plugin prints the same tags.
 */
final class HeadTags {

	/** Option: attachment ID of the site's default share image (0 = none). */
	public const SHARE_IMAGE_OPTION = 'pediment_share_image';

	/** Filter: whether Pediment prints the tags at all. */
	public const FILTER_ENABLED = 'pediment_seo_enabled';

	/** Filter: the meta description of the current view. */
	public const FILTER_DESCRIPTION = 'pediment_meta_description';

	/** Filter: the share image (attachment ID or URL) of the current view. */
	public const FILTER_IMAGE = 'pediment_share_image';

	/** Filter: every og:* and twitter:* tag, as name => content. */
	public const FILTER_TAGS = 'pediment_og_tags';

	/** Length of the og:description taken from the content when the excerpt is empty. */
	private const FALLBACK_LENGTH = 155;

	/**
	 * Constants the SEO plugins define: Yoast, Rank Math, The SEO Framework,
	 * SEOPress, All in One SEO.
	 */
	private const SEO_PLUGINS = array( 'WPSEO_VERSION', 'RANK_MATH_VERSION', 'THE_SEO_FRAMEWORK_VERSION', 'SEOPRESS_VERSION', 'AIOSEO_VERSION' );

	/**
	 * Wires the hooks.
	 */
	public static function register(): void {
		add_action( 'init', array( self::class, 'addPageExcerpts' ) );
		add_action( 'wp_head', array( self::class, 'print' ), 5 );
	}

	/**
	 * Pages carry the description in their excerpt, which core leaves off
	 * for pages. A client theme can take it away again with
	 * remove_post_type_support() on a later `init`.
	 */
	public static function addPageExcerpts(): void {
		add_post_type_support( 'page', 'excerpt' );
	}

	/**
	 * Whether Pediment prints the tags: on, unless an SEO plugin is active.
	 */
	public static function enabled(): bool {
		$active = false;
		foreach ( self::SEO_PLUGINS as $constant ) {
			$active = $active || defined( $constant );
		}
		/**
		 * Filters whether Pediment prints the meta description, Open Graph
		 * and Twitter tags. Default: true, false while Yoast, Rank Math, The
		 * SEO Framework, SEOPress or All in One SEO is active.
		 *
		 * @param bool $enabled Whether to print the tags.
		 */
		return (bool) apply_filters( self::FILTER_ENABLED, ! $active );
	}

	/**
	 * Echoes the tags. Hooked on wp_head.
	 */
	public static function print(): void {
		if ( ! self::enabled() || is_404() || is_feed() || is_embed() ) {
			return;
		}
		echo self::markup(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- markup() escapes every value.
	}

	/**
	 * The tags of the current view, escaped and ready to print.
	 */
	public static function markup(): string {
		$queried     = get_queried_object();
		$post        = self::post( $queried );
		$description = self::description( $queried, $post );

		$out = '';
		if ( '' !== $description ) {
			$out .= sprintf( "<meta name=\"description\" content=\"%s\" />\n", esc_attr( $description ) );
		}
		foreach ( self::tags( $queried, $post, $description ) as $name => $content ) {
			$content = trim( (string) $content );
			if ( '' === $content ) {
				continue;
			}
			$attribute = str_starts_with( (string) $name, 'twitter:' ) ? 'name' : 'property';
			$value     = in_array( $name, array( 'og:url', 'og:image' ), true ) ? esc_url( $content ) : esc_attr( $content );
			$out      .= sprintf( "<meta %s=\"%s\" content=\"%s\" />\n", $attribute, esc_attr( (string) $name ), $value );
		}
		return $out;
	}

	/**
	 * The meta description: the excerpt of the page or post, the tagline on
	 * a blog front page, the term or post type description on an archive.
	 * No text is cut from the content: a search engine writes a better
	 * snippet than a cut sentence.
	 *
	 * @param mixed         $queried Queried object.
	 * @param \WP_Post|null $post    The post the view shows, if any.
	 */
	public static function description( $queried, ?\WP_Post $post ): string {
		$text = '';
		if ( $post instanceof \WP_Post ) {
			$text = post_password_required( $post ) ? '' : $post->post_excerpt;
		} elseif ( is_front_page() && is_home() ) {
			$text = (string) get_bloginfo( 'description' );
		} elseif ( $queried instanceof \WP_Term ) {
			$text = $queried->description;
		} elseif ( $queried instanceof \WP_Post_Type ) {
			$text = $queried->description;
		}
		/**
		 * Filters the meta description of the current view. An empty string
		 * prints no description tag.
		 *
		 * @param string $description Plain-text description.
		 * @param mixed  $queried     The queried object (WP_Post, WP_Term, WP_Post_Type, WP_User or null).
		 */
		$text = (string) apply_filters( self::FILTER_DESCRIPTION, self::plain( $text ), $queried );
		return self::plain( $text );
	}

	/**
	 * The og:* and twitter:* tags, name => content. Empty values are skipped.
	 *
	 * @param mixed         $queried     Queried object.
	 * @param \WP_Post|null $post        The post the view shows, if any.
	 * @param string        $description The meta description.
	 * @return array<string,string>
	 */
	private static function tags( $queried, ?\WP_Post $post, string $description ): array {
		$image = self::image( $queried, $post );
		$tags  = array(
			'og:title'       => self::title( $queried, $post ),
			'og:description' => '' !== $description ? $description : self::fallbackDescription( $post ),
			'og:url'         => self::url( $queried, $post ),
			'og:type'        => $post instanceof \WP_Post && 'post' === $post->post_type && is_singular() ? 'article' : 'website',
			'og:site_name'   => self::plain( (string) get_bloginfo( 'name' ) ),
			'og:locale'      => self::locale(),
		);
		$tags += $image;
		$tags['twitter:card'] = isset( $image['og:image'] ) ? 'summary_large_image' : 'summary';

		/**
		 * Filters the Open Graph and Twitter tags. Keys are tag names
		 * (`og:title`, `twitter:card` …); a `twitter:` key prints as `name=`,
		 * every other key as `property=`. An empty value prints nothing.
		 *
		 * @param array<string,string> $tags    Tag name => content.
		 * @param mixed                $queried The queried object.
		 */
		return (array) apply_filters( self::FILTER_TAGS, $tags, $queried );
	}

	/**
	 * The post a view shows: the singular post, or the posts page.
	 *
	 * @param mixed $queried Queried object.
	 */
	private static function post( $queried ): ?\WP_Post {
		if ( ! $queried instanceof \WP_Post ) {
			return null;
		}
		return is_singular() || is_home() ? $queried : null;
	}

	/**
	 * The og:title: the site name on the front page, the post title, the
	 * archive title on an archive, else the document title.
	 *
	 * @param mixed         $queried Queried object.
	 * @param \WP_Post|null $post    The post the view shows, if any.
	 */
	private static function title( $queried, ?\WP_Post $post ): string {
		// A front page is often titled "Home"; core's <title> shows the site name there too.
		if ( is_front_page() ) {
			return self::plain( (string) get_bloginfo( 'name' ) );
		}
		if ( $post instanceof \WP_Post ) {
			return self::plain( get_the_title( $post ) );
		}
		if ( $queried instanceof \WP_Term ) {
			return self::plain( $queried->name );
		}
		if ( $queried instanceof \WP_Post_Type ) {
			return self::plain( (string) $queried->labels->name );
		}
		return self::plain( wp_get_document_title() );
	}

	/**
	 * The og:url of the view, '' when it has no single address.
	 *
	 * @param mixed         $queried Queried object.
	 * @param \WP_Post|null $post    The post the view shows, if any.
	 */
	private static function url( $queried, ?\WP_Post $post ): string {
		if ( $post instanceof \WP_Post ) {
			$canonical = is_singular() ? wp_get_canonical_url( $post ) : false;
			return (string) ( $canonical ? $canonical : get_permalink( $post ) );
		}
		if ( is_front_page() ) {
			return home_url( '/' );
		}
		if ( $queried instanceof \WP_Term ) {
			$link = get_term_link( $queried );
			return is_wp_error( $link ) ? '' : $link;
		}
		if ( $queried instanceof \WP_Post_Type ) {
			return (string) get_post_type_archive_link( $queried->name );
		}
		return '';
	}

	/**
	 * The og:description when the view has no description: the start of
	 * the post's text, cut at a word to about 155 characters.
	 *
	 * @param \WP_Post|null $post The post the view shows, if any.
	 */
	private static function fallbackDescription( ?\WP_Post $post ): string {
		if ( ! $post instanceof \WP_Post || post_password_required( $post ) ) {
			return '';
		}
		$text = self::plain( strip_shortcodes( excerpt_remove_blocks( $post->post_content ) ) );
		if ( mb_strlen( $text ) <= self::FALLBACK_LENGTH ) {
			return $text;
		}
		$cut   = mb_substr( $text, 0, self::FALLBACK_LENGTH );
		$space = mb_strrpos( $cut, ' ' );
		return rtrim( false === $space ? $cut : mb_substr( $cut, 0, $space ), " \t,.;:-–—" ) . '…';
	}

	/**
	 * The og:image tags: the post's featured image, else the site's default
	 * share image. SVG is skipped, because the share previews do not show it.
	 *
	 * @param mixed         $queried Queried object.
	 * @param \WP_Post|null $post    The post the view shows, if any.
	 * @return array<string,string>
	 */
	private static function image( $queried, ?\WP_Post $post ): array {
		$image = $post instanceof \WP_Post ? (int) get_post_thumbnail_id( $post ) : 0;
		if ( $image <= 0 ) {
			$image = self::defaultImage();
		}
		/**
		 * Filters the share image of the current view: an attachment ID, an
		 * absolute image URL (for example a file in the client theme), or 0
		 * for none. Default: the featured image, else the image in the
		 * `pediment_share_image` option (seeded from the manifest's
		 * `site.share_image`).
		 *
		 * @param int|string $image   Attachment ID or URL.
		 * @param mixed      $queried The queried object.
		 */
		$image = apply_filters( self::FILTER_IMAGE, $image, $queried );

		if ( is_string( $image ) && ! is_numeric( $image ) ) {
			return '' === trim( $image ) ? array() : array( 'og:image' => $image );
		}
		$id = (int) $image;
		if ( $id <= 0 || 'image/svg+xml' === get_post_mime_type( $id ) ) {
			return array();
		}
		$source = wp_get_attachment_image_src( $id, 'full' );
		if ( ! is_array( $source ) || '' === (string) $source[0] ) {
			return array();
		}
		$tags = array( 'og:image' => (string) $source[0] );
		if ( (int) $source[1] > 0 && (int) $source[2] > 0 ) {
			$tags['og:image:width']  = (string) (int) $source[1];
			$tags['og:image:height'] = (string) (int) $source[2];
		}
		$tags['og:image:alt'] = self::plain( (string) get_post_meta( $id, '_wp_attachment_image_alt', true ) );
		return $tags;
	}

	/**
	 * The default share image from the option, 0 when it is unset or the
	 * attachment is gone.
	 */
	public static function defaultImage(): int {
		$id = (int) get_option( self::SHARE_IMAGE_OPTION, 0 );
		if ( $id <= 0 ) {
			return 0;
		}
		$status = get_post_status( $id );
		return false === $status || 'trash' === $status ? 0 : $id;
	}

	/**
	 * The og:locale: the request's locale as language_TERRITORY (`de_DE`
	 * from `de_DE_formal`). The language plugins switch the locale per
	 * request, so a translation gets its own.
	 */
	private static function locale(): string {
		return preg_match( '/^[a-z]{2,3}(?:_[A-Z]{2})?/', get_locale(), $match ) ? $match[0] : '';
	}

	/**
	 * One line of plain text: no tags, no entities, single spaces.
	 *
	 * @param string $text Text.
	 */
	private static function plain( string $text ): string {
		$text = html_entity_decode( wp_strip_all_tags( $text ), ENT_QUOTES | ENT_HTML5, 'UTF-8' );
		return trim( (string) preg_replace( '/\s+/u', ' ', $text ) );
	}
}
