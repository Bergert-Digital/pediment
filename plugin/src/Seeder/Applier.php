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

		$kses = $this->suspendKses();

		try {
			foreach ( $plan->byKind( PlanItem::KIND_ENTRY ) as $item ) {
				if ( in_array( $item->action, [ PlanItem::ORPHAN, PlanItem::UNCHANGED, PlanItem::PROTECTED ], true ) ) {
					continue;
				}
				$entry = $desired[ $item->mapKey() ] ?? null;
				if ( ! $entry instanceof DesiredEntry ) {
					continue;
				}

				$postId = PlanItem::CREATE === $item->action
					? $this->create( $entry, $ids, $errors )
					: $this->update( $item, $entry, $ids, $errors );

				if ( $postId > 0 ) {
					$ids[ $item->mapKey() ] = $postId;
					$this->applyTerms( $postId, $entry );
				}
			}
		} finally {
			$this->restoreKses( $kses );
		}

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

	private function applyTerms( int $postId, DesiredEntry $entry ): void {
		foreach ( $entry->terms as $taxonomy => $slugs ) {
			if ( ! taxonomy_exists( $taxonomy ) ) {
				continue;
			}
			$termIds = [];
			foreach ( $slugs as $slug ) {
				$term = get_term_by( 'slug', $slug, $taxonomy );
				if ( ! $term ) {
					$created = wp_insert_term( ucfirst( str_replace( '-', ' ', $slug ) ), $taxonomy, [ 'slug' => $slug ] );
					if ( is_wp_error( $created ) ) {
						continue;
					}
					$termIds[] = (int) $created['term_id'];
					continue;
				}
				$termIds[] = (int) $term->term_id;
			}
			wp_set_object_terms( $postId, $termIds, $taxonomy );
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

	private function suspendKses(): bool {
		$active = false !== has_filter( 'content_save_pre', 'wp_filter_post_kses' );
		if ( $active ) {
			kses_remove_filters();
		}
		return $active;
	}

	private function restoreKses( bool $wasActive ): void {
		if ( $wasActive ) {
			kses_init_filters();
		}
	}
}
