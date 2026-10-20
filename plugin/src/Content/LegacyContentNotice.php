<?php
/**
 * Admin notice: posts that still hold blocks Pediment 5 removed.
 *
 * @package Pediment
 */

declare(strict_types=1);

namespace Pediment\Content;

use Pediment\Convert\PostConverter;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Tells site admins how many posts still hold a legacy block (they render
 * empty since 5.0) and links to the Convert tab. The posts are the converter's
 * candidates (PostConverter::candidates(): one SQL pre-filter, then a parse of
 * the matches). The count is cached for 12 hours and cleared when a
 * convertible post is saved or deleted, and after every conversion write.
 * Nothing runs on front-end page views.
 */
final class LegacyContentNotice {

	public const TRANSIENT = 'pediment_legacy_content_count';

	private const TAB = 'convert';

	/**
	 * Hooks the notice and the cache clearing.
	 */
	public static function register(): void {
		add_action( 'admin_notices', array( self::class, 'render' ) );
		add_action( 'save_post', array( self::class, 'clearFor' ), 10, 2 );
		add_action( 'deleted_post', array( self::class, 'clearFor' ), 10, 2 );
	}

	/**
	 * Posts that hold a legacy block, from the cache or counted now.
	 */
	public static function count(): int {
		$cached = get_transient( self::TRANSIENT );
		if ( false !== $cached ) {
			return (int) $cached;
		}
		$count = count( PostConverter::create()->candidates() );
		set_transient( self::TRANSIENT, $count, 12 * HOUR_IN_SECONDS );
		return $count;
	}

	/**
	 * Drops the cached count.
	 */
	public static function clear(): void {
		delete_transient( self::TRANSIENT );
	}

	/**
	 * Drops the cached count when a post the converter looks at changes
	 * (save_post, deleted_post). Revisions, form entries and other types
	 * leave it alone; a restore saves the parent post, so it clears.
	 *
	 * @param int           $post_id Post ID.
	 * @param \WP_Post|null $post    Post.
	 */
	public static function clearFor( $post_id, $post = null ): void {
		$type = $post instanceof \WP_Post ? $post->post_type : get_post_type( (int) $post_id );
		if ( in_array( $type, PostConverter::TYPES, true ) ) {
			self::clear();
		}
	}

	/**
	 * Prints the notice for users who manage options, except on the Convert tab.
	 */
	public static function render(): void {
		if ( ! current_user_can( 'manage_options' ) || self::onConvertTab() ) {
			return;
		}
		$count = self::count();
		if ( 0 === $count ) {
			return;
		}
		printf(
			'<div class="notice notice-warning"><p>%1$s %2$s %3$s <a href="%4$s">%5$s</a>.</p></div>',
			esc_html(
				sprintf(
					/* translators: %d: number of posts */
					_n( '%d post still contains blocks that Pediment 5 removed.', '%d posts still contain blocks that Pediment 5 removed.', $count, 'pediment' ),
					$count
				)
			),
			esc_html__( 'They no longer render.', 'pediment' ),
			esc_html__( 'Convert them:', 'pediment' ),
			esc_url( pediment_settings_page_url( self::TAB ) ),
			esc_html__( 'Settings → Pediment Theme → Convert legacy blocks', 'pediment' )
		);
	}

	/**
	 * Whether this request shows the Convert tab, which lists the posts itself.
	 */
	private static function onConvertTab(): bool {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- read-only screen check.
		$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '';
		$tab  = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : '';
		// phpcs:enable WordPress.Security.NonceVerification.Recommended
		return PEDIMENT_SETTINGS_PAGE === $page
			&& self::TAB === pediment_settings_resolve_active_tab( $tab, pediment_settings_get_tabs() );
	}
}
