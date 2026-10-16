<?php
/**
 * Page shapes returned by the page abilities, and the page lookup they share.
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
 * Builds page summaries and full page records; resolves and guards page IDs.
 */
final class PageData {

	/**
	 * Short record for lists.
	 *
	 * @param \WP_Post $post Page.
	 */
	public static function summary( \WP_Post $post ): array {
		return array(
			'id'       => $post->ID,
			'title'    => $post->post_title,
			'slug'     => $post->post_name,
			'parent'   => (int) $post->post_parent,
			'status'   => $post->post_status,
			'language' => LanguageRegistry::provider()->languageOf( $post->ID ),
			'link'     => (string) get_permalink( $post ),
			'modified' => (string) mysql_to_rfc3339( $post->post_modified_gmt ),
		);
	}

	/**
	 * Full record including markup and translations. `translations` is an
	 * object so an empty map still encodes as JSON `{}`, as the output schema
	 * declares.
	 *
	 * @param \WP_Post $post Page.
	 */
	public static function full( \WP_Post $post ): array {
		$provider     = LanguageRegistry::provider();
		$translations = array();
		foreach ( $provider->languages() as $code ) {
			if ( '' === $code ) {
				continue;
			}
			$other = $provider->translationOf( $post->ID, $code );
			if ( $other > 0 && $other !== $post->ID ) {
				$translations[ $code ] = $other;
			}
		}
		return self::summary( $post ) + array(
			'translations' => (object) $translations,
			'markup'       => $post->post_content,
			'revisions'    => count( wp_get_post_revisions( $post->ID, array( 'fields' => 'ids' ) ) ),
		);
	}

	/**
	 * ID of the page's newest revision, skipping autosaves: an autosave holds
	 * unsaved editor work, so it is never offered as a rollback target.
	 *
	 * @param int $id Page ID.
	 * @return int Revision ID, 0 when there is none.
	 */
	public static function latestRevision( int $id ): int {
		foreach ( wp_get_post_revisions( $id, array( 'fields' => 'ids' ) ) as $revision ) {
			if ( ! wp_is_post_autosave( $revision ) ) {
				return (int) $revision;
			}
		}
		return 0;
	}

	/**
	 * The page for an ID, or a 404 WP_Error when the ID is not a page.
	 *
	 * @param int $id Post ID.
	 * @return \WP_Post|\WP_Error
	 */
	public static function find( int $id ) {
		$post = get_post( $id );
		if ( $post instanceof \WP_Post && 'page' === $post->post_type ) {
			return $post;
		}
		return new \WP_Error( 'pediment_page_not_found', __( 'No page with that id.', 'pediment' ), array( 'status' => 404 ) );
	}

	/**
	 * Permission check for abilities that take a page `id`: edit_pages, plus
	 * edit_post on the page itself. An unknown id passes so execute() can
	 * answer with a clear "page not found" instead of a bare refusal.
	 *
	 * @param mixed $input Ability input.
	 */
	public static function canEditInput( $input ): bool {
		$id = (int) ( ( (array) $input )['id'] ?? 0 );
		return current_user_can( 'edit_pages' ) && ( ! self::find( $id ) instanceof \WP_Post || current_user_can( 'edit_post', $id ) );
	}
}
