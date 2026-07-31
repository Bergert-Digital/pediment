<?php
/**
 * Phase 5: post-conditions.
 *
 * The most expensive failure in this project's history was a seeder reporting
 * success while the live header rendered nothing. Everything the seeder claims
 * to own is re-read from the database here and reported if it does not hold.
 *
 * @package Pediment
 */

declare(strict_types=1);

namespace Pediment\Seeder;

use Pediment\Language\LanguageProvider;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Verifier {
	public function __construct( private LanguageProvider $lang ) {}

	/**
	 * @param array<string,DesiredEntry> $desired
	 * @param array<string,int>          $ids
	 * @return string[]
	 */
	public function verify( Manifest $manifest, array $desired, array $ids, MediaMap $media ): array {
		$problems = [];

		foreach ( $desired as $mapKey => $entry ) {
			$postId = (int) ( $ids[ $mapKey ] ?? 0 );
			if ( 0 === $postId ) {
				$problems[] = sprintf( '%s: no post exists for this seed key.', $mapKey );
				continue;
			}

			$post = get_post( $postId );
			if ( ! $post instanceof \WP_Post ) {
				$problems[] = sprintf( '%s: post ID %d does not exist.', $mapKey, $postId );
				continue;
			}
			// Only trash is a problem: `draft` and `pending` are editorial states
			// a client is entitled to set, and the Differ deliberately leaves them.
			if ( 'trash' === $post->post_status ) {
				$problems[] = sprintf( '%s: post %d is in the trash.', $mapKey, $postId );
			}
			if ( $post->post_name !== $entry->slug ) {
				$problems[] = sprintf(
					'%s: slug is "%s" but the manifest says "%s" (WordPress uniquifies colliding slugs).',
					$mapKey,
					$post->post_name,
					$entry->slug
				);
			}
			if ( (string) get_post_meta( $postId, Meta::KEY, true ) !== $entry->key ) {
				$problems[] = sprintf( '%s: post %d is missing its seed key.', $mapKey, $postId );
			}

			$expectedParent = null === $entry->parentKey ? 0 : (int) ( $ids[ $entry->parentKey . '|' . $entry->language ] ?? 0 );
			if ( (int) $post->post_parent !== $expectedParent ) {
				$problems[] = sprintf( '%s: parent is %d, expected %d.', $mapKey, $post->post_parent, $expectedParent );
			}

			if ( $entry->frontPage && $entry->language === $this->lang->defaultLanguage() ) {
				if ( 'page' !== get_option( 'show_on_front' ) || (int) get_option( 'page_on_front' ) !== $postId ) {
					$problems[] = sprintf( '%s: is declared front page but the front page setting points elsewhere.', $mapKey );
				}
			}
			if ( $entry->postsPage && $entry->language === $this->lang->defaultLanguage() && (int) get_option( 'page_for_posts' ) !== $postId ) {
				$problems[] = sprintf( '%s: is declared posts page but page_for_posts points elsewhere.', $mapKey );
			}
		}

		foreach ( $manifest->media() as $key => $spec ) {
			if ( $media->id( $key ) <= 0 ) {
				$problems[] = sprintf( 'media.%s: no attachment was created for %s.', $key, basename( $spec->file ) );
			}
		}

		$registeredByUs = \Pediment\Seeder\PostTypes::registeredSlugs();
		foreach ( $manifest->postTypes() as $spec ) {
			if ( ! post_type_exists( $spec->slug ) ) {
				$problems[] = sprintf( 'post_types.%s: not registered — entries of this type are unreachable.', $spec->slug );
				continue;
			}
			if ( ! in_array( $spec->slug, $registeredByUs, true ) ) {
				$problems[] = sprintf(
					'post_types.%s: already registered by something else — the manifest\'s settings (show_in_rest, supports, rewrite) were not applied.',
					$spec->slug
				);
			}
		}

		return $problems;
	}
}
