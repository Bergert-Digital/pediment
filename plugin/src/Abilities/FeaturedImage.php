<?php
/**
 * Featured images for the write-page ability: import from a URL, reuse, and
 * alt text per post.
 *
 * @package Pediment
 */

declare(strict_types=1);

namespace Pediment\Abilities;

use Pediment\Language\LanguageRegistry;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Imports a remote image once per source URL and keeps the alt text on the
 * post, so translations share one attachment but each carries its own alt.
 * Pediment keeps Polylang's media translation off, so an attachment has no
 * language (see PolylangSetup).
 */
final class FeaturedImage {

	/**
	 * Attachment meta: the URL the image was imported from.
	 */
	public const SOURCE_META = '_pediment_source_url';

	/**
	 * Post meta: alt text of the post's featured image, for this post only.
	 */
	public const ALT_META = '_pediment_featured_image_alt';

	private const MIME_EXTENSIONS = array(
		'image/jpeg' => 'jpg',
		'image/png'  => 'png',
		'image/gif'  => 'gif',
		'image/webp' => 'webp',
		'image/avif' => 'avif',
	);

	/**
	 * Post whose thumbnail is being rendered, 0 outside a render.
	 *
	 * @var int
	 */
	private static int $rendering = 0;

	/**
	 * Hooks the render-time alt text.
	 */
	public static function register(): void {
		add_action( 'begin_fetch_post_thumbnail_html', array( self::class, 'beginRender' ) );
		add_action( 'end_fetch_post_thumbnail_html', array( self::class, 'endRender' ) );
		add_filter( 'wp_get_attachment_image_attributes', array( self::class, 'altAttribute' ) );
	}

	/**
	 * On begin_fetch_post_thumbnail_html.
	 *
	 * @param int $post_id Post whose thumbnail is rendered.
	 */
	public static function beginRender( $post_id ): void {
		self::$rendering = (int) $post_id;
	}

	/**
	 * On end_fetch_post_thumbnail_html.
	 */
	public static function endRender(): void {
		self::$rendering = 0;
	}

	/**
	 * On wp_get_attachment_image_attributes: the post's own alt text wins
	 * over the attachment's while that post's thumbnail is rendered.
	 *
	 * @param array<string,string> $attr Image attributes.
	 * @return array<string,string>
	 */
	public static function altAttribute( $attr ) {
		if ( self::$rendering > 0 ) {
			$alt = get_post_meta( self::$rendering, self::ALT_META, true );
			if ( is_string( $alt ) && '' !== $alt ) {
				$attr['alt'] = $alt;
			}
		}
		return $attr;
	}

	/**
	 * Input problems that need no network or database write.
	 *
	 * @param array  $image featured_image input.
	 * @param string $type  Post type slug.
	 * @return list<string>
	 */
	public static function problems( array $image, string $type ): array {
		$problems = array();
		if ( ! post_type_supports( $type, 'thumbnail' ) ) {
			$problems[] = "featured_image: post_type \"{$type}\" has no featured image";
		}
		if ( isset( $image['url'] ) === isset( $image['id'] ) ) {
			$problems[] = 'featured_image: pass exactly one of url or id';
		}
		if ( isset( $image['url'] ) && ! in_array( wp_parse_url( (string) $image['url'], PHP_URL_SCHEME ), array( 'http', 'https' ), true ) ) {
			$problems[] = 'featured_image.url: use an http or https URL';
		}
		$id = (int) ( $image['id'] ?? 0 );
		if ( $id > 0 && ! wp_attachment_is_image( $id ) ) {
			$problems[] = "featured_image.id: {$id} is not an image in the media library";
		}
		$alt = (string) ( $image['alt'] ?? '' );
		if ( wp_strip_all_tags( $alt ) !== trim( $alt ) ) {
			$problems[] = 'featured_image.alt: markup is not allowed — use plain text';
		}
		return $problems;
	}

	/**
	 * The attachment ID for the input: the given id, or the image imported
	 * from the URL (once per URL; a later call with the same URL reuses it).
	 *
	 * @param array $image featured_image input, already checked by problems().
	 * @return int|\WP_Error Attachment ID, 0 to remove the featured image.
	 */
	public static function resolve( array $image ) {
		if ( ! isset( $image['url'] ) ) {
			return (int) $image['id'];
		}
		$url      = (string) $image['url'];
		$existing = get_posts(
			LanguageRegistry::provider()->unscopedQuery(
				array(
					'post_type'      => 'attachment',
					'post_status'    => 'inherit',
					// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- reuse lookup on one key, once per write.
					'meta_query'     => array( array( 'key' => self::SOURCE_META, 'value' => $url ) ),
					'fields'         => 'ids',
					'posts_per_page' => 1,
					'no_found_rows'  => true,
				)
			)
		);
		if ( array() !== $existing ) {
			return (int) $existing[0];
		}
		return self::import( $url );
	}

	/**
	 * Sets (or with 0 removes) the post's featured image and its alt text.
	 * The alt text goes on the post; the attachment gets it too while it has
	 * none, so the media library shows one.
	 *
	 * @param int         $post_id       Post ID.
	 * @param int         $attachment_id Attachment ID, 0 to remove.
	 * @param string|null $alt           Alt text, null when not given.
	 */
	public static function apply( int $post_id, int $attachment_id, ?string $alt ): void {
		if ( 0 === $attachment_id ) {
			delete_post_thumbnail( $post_id );
			delete_post_meta( $post_id, self::ALT_META );
			return;
		}
		set_post_thumbnail( $post_id, $attachment_id );
		if ( null === $alt || '' === trim( $alt ) ) {
			delete_post_meta( $post_id, self::ALT_META );
			return;
		}
		$alt = trim( $alt );
		update_post_meta( $post_id, self::ALT_META, wp_slash( $alt ) );
		if ( '' === (string) get_post_meta( $attachment_id, '_wp_attachment_image_alt', true ) && current_user_can( 'edit_post', $attachment_id ) ) {
			update_post_meta( $attachment_id, '_wp_attachment_image_alt', wp_slash( $alt ) );
		}
	}

	/**
	 * The post's featured image for read-page, or null.
	 *
	 * @param int $post_id Post ID.
	 * @return array{id:int,url:string,alt:string,source:string}|null
	 */
	public static function describe( int $post_id ): ?array {
		$id = (int) get_post_thumbnail_id( $post_id );
		if ( $id <= 0 ) {
			return null;
		}
		$alt = (string) get_post_meta( $post_id, self::ALT_META, true );
		return array(
			'id'     => $id,
			'url'    => (string) wp_get_attachment_url( $id ),
			'alt'    => '' !== $alt ? $alt : (string) get_post_meta( $id, '_wp_attachment_image_alt', true ),
			'source' => (string) get_post_meta( $id, self::SOURCE_META, true ),
		);
	}

	/**
	 * Downloads an image into the media library.
	 *
	 * @param string $url Source URL.
	 * @return int|\WP_Error Attachment ID.
	 */
	private static function import( string $url ) {
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/media.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';

		// download_url() fetches through wp_safe_remote_get(), which refuses
		// local and private hosts.
		$tmp = download_url( $url, 30 );
		if ( is_wp_error( $tmp ) ) {
			return self::failure( "featured_image.url: could not download {$url} — " . $tmp->get_error_message() );
		}
		$mime      = (string) wp_get_image_mime( $tmp );
		$extension = self::MIME_EXTENSIONS[ $mime ] ?? '';
		if ( '' === $extension ) {
			wp_delete_file( $tmp );
			return self::failure( "featured_image.url: {$url} is not a JPEG, PNG, GIF, WebP or AVIF image" );
		}
		// Image CDNs often serve URLs without a file extension; name the file by its real type.
		$base = sanitize_file_name( (string) pathinfo( (string) wp_parse_url( $url, PHP_URL_PATH ), PATHINFO_FILENAME ) );
		$name = ( '' !== $base ? $base : 'image' ) . '.' . $extension;

		$id = media_handle_sideload(
			array(
				'name'     => $name,
				'tmp_name' => $tmp,
			),
			0
		);
		if ( is_wp_error( $id ) ) {
			wp_delete_file( $tmp );
			return self::failure( "featured_image.url: could not add {$url} to the media library — " . $id->get_error_message() );
		}
		update_post_meta( (int) $id, self::SOURCE_META, wp_slash( $url ) );
		return (int) $id;
	}

	/**
	 * A featured-image error; the message carries the problem (the MCP adapter shows the message only).
	 *
	 * @param string $problem Problem.
	 */
	private static function failure( string $problem ): \WP_Error {
		return new \WP_Error(
			'pediment_featured_image_failed',
			__( 'The featured image could not be imported. Nothing was written.', 'pediment' ) . "\n- " . $problem,
			array(
				'status' => 422,
				'errors' => array( $problem ),
			)
		);
	}
}
