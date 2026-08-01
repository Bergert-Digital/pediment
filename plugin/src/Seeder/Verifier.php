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
	public function __construct( private LanguageProvider $lang, private NavSeeder $navSeeder ) {}

	/**
	 * @param array<string,DesiredEntry> $desired
	 * @param array<string,int>          $ids
	 * @param array<string,int>          $navIds navKey|language => post ID
	 * @return string[]
	 */
	public function verify( Manifest $manifest, array $desired, array $ids, MediaMap $media, array $navIds = [] ): array {
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
			if ( null !== $entry->parentKey && 0 === $expectedParent ) {
				// Falling back to 0 made this check unfailable: a child whose
				// parent never got created lands at the site root, which then
				// compares equal to the expectation.
				$problems[] = sprintf( '%s: parent "%s" has no post — this entry landed at the site root.', $mapKey, $entry->parentKey );
			} elseif ( (int) $post->post_parent !== $expectedParent ) {
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
			$attachmentId = $media->id( $key );
			if ( $attachmentId <= 0 ) {
				$problems[] = sprintf( 'media.%s: no attachment was created for %s.', $key, basename( $spec->file ) );
				continue;
			}

			// Re-read rather than trusting the map: the ID could name a row that
			// no longer exists.
			$attachment = get_post( $attachmentId );
			if ( ! $attachment instanceof \WP_Post || 'attachment' !== $attachment->post_type || 'trash' === $attachment->post_status ) {
				$problems[] = sprintf( 'media.%s: attachment %d is missing or trashed.', $key, $attachmentId );
			}
		}

		// Navigation: the header rendering nothing while the seeder reports
		// success is the exact incident this class exists to prevent, so the
		// entities are re-read and their membership re-derived.
		foreach ( $manifest->navs() as $key => $spec ) {
			foreach ( $this->lang->languages() as $language ) {
				// Same key|language form the entry loop reports: without it a
				// five-language site produces five identical lines.
				$mapKey = $key . '|' . $language;
				$navId  = (int) ( $navIds[ $mapKey ] ?? 0 );
				if ( 0 === $navId ) {
					$problems[] = sprintf( 'navs.%s: no navigation entity exists for this seed key.', $mapKey );
					continue;
				}
				$nav = get_post( $navId );
				if ( ! $nav instanceof \WP_Post || 'wp_navigation' !== $nav->post_type ) {
					$problems[] = sprintf( 'navs.%s: post %d is not a navigation entity.', $mapKey, $navId );
					continue;
				}
				if ( 'publish' !== $nav->post_status ) {
					$problems[] = sprintf( 'navs.%s: entity %d is "%s" — the menu will not render.', $mapKey, $navId, $nav->post_status );
				}
				if ( (string) get_post_meta( $navId, Meta::KEY, true ) !== $key ) {
					$problems[] = sprintf( 'navs.%s: entity %d is missing its seed key.', $mapKey, $navId );
				}
				if ( (string) $nav->post_content !== $this->navSeeder->serialize( $spec, $language, $ids ) ) {
					$problems[] = sprintf( 'navs.%s: stored membership does not match the manifest.', $mapKey );
				}
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
