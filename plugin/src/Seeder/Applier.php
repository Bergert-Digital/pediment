<?php
/**
 * Phase 4: apply the plan.
 *
 * Rules that are not obvious and cost a lot when missed:
 *   - wp_slash() before every write: wp_insert_post/wp_update_post un-slash
 *     post_content, which corrupts block-attribute JSON (WORDPRESS_TRAPS.md).
 *   - KSES is suspended: under WP-CLI there is no user, so kses_init_filters()
 *     is active and mangles block comments. Seeded content is git-authored, not
 *     user input.
 *   - The language is set in the same write as creation, never after (8593c73).
 *   - _pediment_seed_hash comes from the PERSISTED row, _pediment_seed_source
 *     from the input.
 *
 * @package Pediment
 */

declare(strict_types=1);

namespace Pediment\Seeder;

use Pediment\Language\LanguageProvider;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Applier {
	public function __construct( private LanguageProvider $lang ) {}

	/** @param array<string,DesiredEntry> $desired */
	public function apply( Plan $plan, array $desired ): ApplyResult {
		if ( $plan->hasErrors() ) {
			return new ApplyResult( [], $plan->errors() );
		}

		$ids    = [];
		$errors = [];

		// Seed existing IDs first so parents resolve even when only a child changes.
		foreach ( $plan->byKind( PlanItem::KIND_ENTRY ) as $item ) {
			if ( $item->postId > 0 && PlanItem::ORPHAN !== $item->action ) {
				$ids[ $item->mapKey() ] = $item->postId;
			}
		}

		$kses = Kses::suspend();

		try {
			foreach ( $plan->byKind( PlanItem::KIND_ENTRY ) as $item ) {
				if ( in_array( $item->action, [ PlanItem::ORPHAN, PlanItem::UNCHANGED, PlanItem::PROTECTED ], true ) ) {
					continue;
				}
				$entry = $desired[ $item->mapKey() ] ?? null;
				if ( ! $entry instanceof DesiredEntry ) {
					continue;
				}

				$isCreate = PlanItem::CREATE === $item->action;
				$postId   = $isCreate
					? $this->create( $entry, $ids, $errors )
					: $this->update( $item, $entry, $ids, $errors );

				if ( $postId > 0 ) {
					$ids[ $item->mapKey() ] = $postId;
					// Terms are create-only. Re-applying them on a structure-only
					// write would use wp_set_object_terms(), which REPLACES the
					// taxonomy's assignments — a client who filed a seeded post
					// under an extra category would lose it on an unrelated slug
					// revert. The Differ does not diff terms, so a manifest-side
					// term change is not enforced either; that is documented, not
					// accidental (docs/seeding.md).
					if ( $isCreate ) {
						$this->applyTerms( $postId, $entry, $errors );
					}
					$this->assertSlug( $postId, $entry, $errors );
				}
			}
		} finally {
			Kses::restore( $kses );
		}

		$this->linkTranslationGroups( $ids );

		$this->applyReadingOptions( $desired, $ids );

		return new ApplyResult( $ids, $errors );
	}

	/** @param array<string,int> $ids @param string[] $errors */
	private function create( DesiredEntry $entry, array $ids, array &$errors ): int {
		$postId = wp_insert_post(
			wp_slash(
				[
					'post_type'    => $entry->postType,
					'post_status'  => 'publish',
					'post_title'   => $entry->title,
					'post_name'    => $entry->slug,
					'post_content' => $entry->content,
					'post_parent'  => $this->parentId( $entry, $ids ),
					'menu_order'   => $entry->menuOrder,
				]
			),
			true
		);

		if ( is_wp_error( $postId ) ) {
			$errors[] = sprintf( '%s: %s', $entry->key, $postId->get_error_message() );
			return 0;
		}

		$postId = (int) $postId;

		// Same write path as creation — never a second pass (8593c73).
		$this->lang->setLanguage( $postId, $entry->language );

		update_post_meta( $postId, Meta::KEY, $entry->key );
		$this->recordHashes( $postId, $entry );

		return $postId;
	}

	/** @param array<string,int> $ids @param string[] $errors */
	private function update( PlanItem $item, DesiredEntry $entry, array $ids, array &$errors ): int {
		$postarr = [ 'ID' => $item->postId ];

		foreach ( $item->changes as $field => $change ) {
			switch ( $field ) {
				case 'title':
					$postarr['post_title'] = $entry->title;
					break;
				case 'content':
					$postarr['post_content'] = $entry->content;
					break;
				case 'slug':
					$postarr['post_name'] = $entry->slug;
					break;
				case 'parent_key':
					// The change record carries a seed key for display; the real
					// post_parent is resolved here from the desired entry.
					$postarr['post_parent'] = $this->parentId( $entry, $ids );
					break;
				case 'menu_order':
					$postarr['menu_order'] = $entry->menuOrder;
					break;
				case 'status':
					$postarr['post_status'] = 'publish';
					break;
			}
		}

		if ( isset( $postarr['post_status'] ) && 'trash' === $item->changes['status']['from'] ) {
			Meta::clearTrashBookkeeping( $item->postId );
		}

		if ( 1 === count( $postarr ) ) {
			return $item->postId;
		}

		$result = wp_update_post( wp_slash( $postarr ), true );
		if ( is_wp_error( $result ) ) {
			$errors[] = sprintf( '%s: %s', $entry->key, $result->get_error_message() );
			return $item->postId;
		}

		// Only re-hash when this run actually wrote content; a structure-only
		// update on a client-edited page must leave the arbitration hash alone.
		if ( isset( $item->changes['content'] ) || isset( $item->changes['title'] ) ) {
			$this->recordHashes( $item->postId, $entry );
		}

		return $item->postId;
	}

	private function recordHashes( int $postId, DesiredEntry $entry ): void {
		update_post_meta( $postId, Meta::HASH, ContentHash::forPost( $postId ) );
		update_post_meta( $postId, Meta::SOURCE, $entry->sourceHash );
	}

	/** @param array<string,int> $ids */
	private function parentId( DesiredEntry $entry, array $ids ): int {
		if ( null === $entry->parentKey ) {
			return 0;
		}
		return (int) ( $ids[ $entry->parentKey . '|' . $entry->language ] ?? 0 );
	}

	/** @param string[] $errors */
	private function applyTerms( int $postId, DesiredEntry $entry, array &$errors ): void {
		foreach ( $entry->terms as $taxonomy => $slugs ) {
			if ( ! taxonomy_exists( $taxonomy ) ) {
				// Silence here would be indistinguishable from success: a typo'd
				// taxonomy, or one whose post type has not registered yet, would
				// produce a clean run with no terms assigned.
				$errors[] = sprintf( '%s: taxonomy "%s" is not registered — no terms were assigned.', $entry->key, $taxonomy );
				continue;
			}
			$termIds = [];
			foreach ( $slugs as $slug ) {
				$term = get_term_by( 'slug', $slug, $taxonomy );
				if ( ! $term ) {
					$created = wp_insert_term( ucfirst( str_replace( '-', ' ', $slug ) ), $taxonomy, [ 'slug' => $slug ] );
					if ( is_wp_error( $created ) ) {
						$errors[] = sprintf( '%s: could not create term "%s" in %s — %s', $entry->key, $slug, $taxonomy, $created->get_error_message() );
						continue;
					}
					$termIds[] = (int) $created['term_id'];
					continue;
				}
				$termIds[] = (int) $term->term_id;
			}
			$assigned = wp_set_object_terms( $postId, $termIds, $taxonomy );
			if ( is_wp_error( $assigned ) ) {
				$errors[] = sprintf( '%s: could not assign %s terms — %s', $entry->key, $taxonomy, $assigned->get_error_message() );
			}
		}
	}

	/**
	 * WordPress uniquifies a colliding slug (`contact` -> `contact-2`) and reports
	 * success. Left unreported, the Differ then sees a slug difference on EVERY
	 * later run, rewrites the row, and never converges — silently, forever.
	 *
	 * @param string[] $errors
	 */
	private function assertSlug( int $postId, DesiredEntry $entry, array &$errors ): void {
		$stored = (string) get_post_field( 'post_name', $postId );
		if ( '' === $stored || $stored === $entry->slug ) {
			return;
		}
		$errors[] = sprintf(
			'%s: WordPress stored the slug "%s" instead of "%s" — another post already occupies it. Free that slug or declare a different one in the manifest.',
			$entry->key,
			$stored,
			$entry->slug
		);
	}

	/**
	 * Put every language of a seed key into one translation group.
	 *
	 * Runs after the write loop, not inside it: a group is only meaningful once
	 * every language exists, and pll_save_post_translations() REPLACES the
	 * whole group — calling it per language as each one is written would unlink
	 * the ones written before it. Invisible with two languages, silent data
	 * loss with five.
	 *
	 * Passing the full map every run is also what repairs a group an editor
	 * broke by hand, and what links a language added later to the ones that
	 * were already there.
	 *
	 * Known consequence, not a bug fixed here: $ids excludes ORPHAN items
	 * (mapKeys the current manifest no longer generates) even when their
	 * postId is still valid, and DesiredState crosses every seed key with
	 * every configured language. So dropping a language from both the
	 * manifest and Polylang's own config in the same run — passing the
	 * Runner's gate because the two sets still match — turns every post in
	 * that language into an ORPHAN everywhere at once, drops it out of every
	 * key's map here, and pll_save_post_translations() then unlinks it from
	 * its group site-wide, even though the plan reports those posts as left
	 * in place. See docs/BACKLOG.md (Medium, step-4 Task 10 review).
	 *
	 * @param array<string,int> $ids mapKey => post ID
	 */
	private function linkTranslationGroups( array $ids ): void {
		$languages = $this->lang->languages();
		if ( count( $languages ) < 2 ) {
			return;
		}

		$byKey = [];
		foreach ( $ids as $mapKey => $postId ) {
			// array_pad(explode('|', ...), 2, '') does not truncate if a seed key
			// or language code ever contained '|' itself — not reachable today
			// (both come from manifest identifiers), flagged in case a future
			// caller feeds this the same shape with looser input.
			[ $key, $language ] = array_pad( explode( '|', (string) $mapKey ), 2, '' );
			if ( '' === $language || $postId <= 0 ) {
				continue;
			}
			$byKey[ $key ][ $language ] = $postId;
		}

		foreach ( $byKey as $map ) {
			$this->lang->linkTranslations( $map );
		}
	}

	/**
	 * @param array<string,DesiredEntry> $desired
	 * @param array<string,int>          $ids
	 */
	private function applyReadingOptions( array $desired, array $ids ): void {
		$default = $this->lang->defaultLanguage();

		foreach ( $desired as $mapKey => $entry ) {
			if ( $entry->language !== $default || ! isset( $ids[ $mapKey ] ) ) {
				continue;
			}
			if ( $entry->frontPage ) {
				if ( 'page' !== get_option( 'show_on_front' ) ) {
					update_option( 'show_on_front', 'page' );
				}
				if ( (int) get_option( 'page_on_front' ) !== $ids[ $mapKey ] ) {
					update_option( 'page_on_front', $ids[ $mapKey ] );
				}
			}
			if ( $entry->postsPage && (int) get_option( 'page_for_posts' ) !== $ids[ $mapKey ] ) {
				update_option( 'page_for_posts', $ids[ $mapKey ] );
			}
		}
	}
}
