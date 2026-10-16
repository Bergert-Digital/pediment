<?php
/**
 * Phase 4: apply the plan. Creates missing entries; never writes to a row
 * that exists (docs/seeding.md, "Bootstrap only").
 *
 * Rules that are not obvious and cost a lot when missed:
 *   - wp_slash() before every write: wp_insert_post/wp_update_post un-slash
 *     post_content, which corrupts block-attribute JSON (WORDPRESS_TRAPS.md).
 *   - KSES is suspended: under WP-CLI there is no user, so kses_init_filters()
 *     is active and mangles block comments. Seeded content is git-authored, not
 *     user input.
 *   - The language is set in the same write as creation, never after (8593c73).
 *   - A row that exists may still have NO language at all — the migration
 *     case, where a page predates this seeder's language tagging.
 *     repairLanguage() tags it if and only if it is currently untagged; it
 *     never re-tags or moves a post that already carries one. That and the
 *     translation-group link (only for a key where this run created or
 *     tagged a row) are the only things the seeder does to a row it did not
 *     create in this run.
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

		$ids     = [];
		$errors  = [];
		$created = [];

		// Existing rows first, so a new child resolves a parent that already exists.
		foreach ( $plan->byKind( PlanItem::KIND_ENTRY ) as $item ) {
			if ( $item->postId > 0 && PlanItem::ORPHAN !== $item->action ) {
				$ids[ $item->mapKey() ] = $item->postId;
			}
		}

		$kses = Kses::suspend();

		try {
			// Plan order is dependency order per language, so a parent created in
			// this run exists before its children are inserted.
			foreach ( $plan->byAction( PlanItem::CREATE ) as $item ) {
				if ( PlanItem::KIND_ENTRY !== $item->kind ) {
					continue;
				}
				$entry = $desired[ $item->mapKey() ] ?? null;
				if ( ! $entry instanceof DesiredEntry ) {
					continue;
				}

				$postId = $this->create( $entry, $ids, $errors );
				if ( $postId > 0 ) {
					$ids[ $item->mapKey() ] = $postId;
					$created[]              = $item->mapKey();
					$this->applyTerms( $postId, $entry, $errors );
					$this->assertSlug( $postId, $entry, $errors );
				}
			}
		} finally {
			Kses::restore( $kses );
		}

		$repaired = $this->repairLanguage( $plan, $desired, $ids );

		$this->linkTranslationGroups( $ids, array_merge( $created, $repaired ) );

		$this->applyReadingOptions( $desired, $ids );

		return new ApplyResult( $ids, $errors, $created );
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

		// A translation may deliberately share its default-language sibling's
		// slug (/en/home and /de/home both `home`). The insert above ran
		// wp_unique_post_slug BEFORE the post had a Polylang language, so
		// Polylang Pro's share-slug filter could not yet tell the collision
		// was cross-language and WordPress uniquified it to `home-2`. Now that
		// the language is set, re-assert the declared slug: under Pro it lands
		// the shared slug; under Free (no share-slug filter) it re-uniquifies
		// exactly as the bare insert did, so this is a no-op there. Guarded so
		// the overwhelming majority of entries — whose slug never collided —
		// take no second write.
		if ( get_post_field( 'post_name', $postId ) !== $entry->slug ) {
			wp_update_post( wp_slash( [ 'ID' => $postId, 'post_name' => $entry->slug ] ) );
		}

		update_post_meta( $postId, Meta::KEY, $entry->key );

		return $postId;
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
	 * success. The seeder never touches the row again, so this run is the only
	 * chance to say the new page did not get the URL the manifest declares.
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
	 * Put every language of a seed key into one translation group — but only
	 * for a key where this run created or language-tagged at least one row.
	 *
	 * Runs after the write loop, not inside it: a group is only meaningful once
	 * every language exists, and pll_save_post_translations() REPLACES the
	 * whole group — calling it per language as each one is written would unlink
	 * the ones written before it. Invisible with two languages, silent data
	 * loss with five.
	 *
	 * A key whose rows all existed and kept their language is left alone: its
	 * group belongs to the people who edit the site, and a person (or
	 * pediment-write-page with translation_of) may have linked a different
	 * page as a translation. Relinking the seeded rows would undo that. A
	 * language added later still joins its group, because its new row was
	 * created in this run.
	 *
	 * Known consequence, not a bug fixed here: $ids excludes ORPHAN items
	 * (mapKeys the current manifest no longer generates) even when their
	 * postId is still valid, and DesiredState crosses every seed key with
	 * every configured language. So dropping a language from both the
	 * manifest and Polylang's own config in the same run — passing the
	 * Runner's gate because the two sets still match — turns every post in
	 * that language into an ORPHAN everywhere at once and drops it out of
	 * every key's map here. For any key this run then links (it created or
	 * tagged a row), pll_save_post_translations() unlinks the orphan from its
	 * group, even though the plan reports those posts as left in place. See docs/BACKLOG.md (Medium, step-4 Task 10 review).
	 *
	 * @param array<string,int> $ids     mapKey => post ID
	 * @param string[]          $touched mapKeys this run created or tagged.
	 */
	private function linkTranslationGroups( array $ids, array $touched ): void {
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
			// A trashed row stays out of the group: the seeder used to restore it
			// first, and linking a page a person trashed into a group is new
			// behaviour. The map is then short of one language, which the count
			// check below skips whole, so the group is left as it is.
			if ( '' === $language || $postId <= 0 || 'trash' === get_post_status( $postId ) ) {
				continue;
			}
			$byKey[ $key ][ $language ] = $postId;
		}

		$touchedKeys = [];
		foreach ( $touched as $mapKey ) {
			$touchedKeys[ explode( '|', (string) $mapKey )[0] ] = true;
		}

		foreach ( $byKey as $key => $map ) {
			if ( ! isset( $touchedKeys[ $key ] ) ) {
				continue;
			}
			// A map missing even one configured language must never reach
			// linkTranslations(): pll_save_post_translations() REPLACES the
			// whole group, so Polylang's own save_translations() diffs this
			// partial map against the group's CURRENT membership and unlinks
			// whichever language got left out — even though that language's
			// post never changed. count($map) >= 2 let a 2-of-3 map through
			// on an ordinary single-language write failure; only "the map
			// covers every configured language" is safe. This does not
			// change the documented coordinated-removal case just above:
			// there, $languages shrinks along with the map, so the count
			// still matches — that consequence is intentionally left as is.
			if ( count( $map ) !== count( $languages ) ) {
				continue;
			}
			$this->lang->linkTranslations( $map );
		}
	}

	/**
	 * Tag a pre-existing post that has no language at all.
	 *
	 * Only rows that exist reach here (UNCHANGED items) — a CREATE already
	 * tags its post in the same write (see the class docblock). An existing
	 * row may predate this seeder's language tagging entirely: the
	 * existing-site migration case. hasLanguage() is the only check — a post
	 * that already carries a (possibly different) language is left exactly
	 * as it is, because re-tagging or moving it is an editorial decision,
	 * not this engine's to make.
	 *
	 * A no-op on a monolingual site: NullProvider::hasLanguage() always
	 * returns true.
	 *
	 * @param array<string,DesiredEntry> $desired
	 * @param array<string,int>          $ids
	 * @return string[] mapKeys of the rows it tagged.
	 */
	private function repairLanguage( Plan $plan, array $desired, array $ids ): array {
		$repaired = [];
		foreach ( $plan->byKind( PlanItem::KIND_ENTRY ) as $item ) {
			if ( PlanItem::CREATE === $item->action || PlanItem::ORPHAN === $item->action ) {
				continue;
			}
			$postId = $ids[ $item->mapKey() ] ?? 0;
			if ( $postId <= 0 ) {
				continue;
			}
			$entry = $desired[ $item->mapKey() ] ?? null;
			if ( ! $entry instanceof DesiredEntry ) {
				continue;
			}
			if ( ! $this->lang->hasLanguage( $postId ) ) {
				$this->lang->setLanguage( $postId, $entry->language );
				$repaired[] = $item->mapKey();
			}
		}
		return $repaired;
	}

	/**
	 * Point the front page and the posts page at their entries — only while
	 * the setting is unset (SiteSetting::isUnset()). A choice a person made in
	 * Settings → Reading is never reverted, not even when the seeded page was
	 * deleted for good and this run created it again, and a trashed page is
	 * never chosen.
	 *
	 * @param array<string,DesiredEntry> $desired
	 * @param array<string,int>          $ids
	 */
	private function applyReadingOptions( array $desired, array $ids ): void {
		$default = $this->lang->defaultLanguage();

		foreach ( $desired as $mapKey => $entry ) {
			if ( $entry->language !== $default || ! isset( $ids[ $mapKey ] ) ) {
				continue;
			}
			// Core resets both settings when the page is trashed (an empty option
			// would then read as "unset"). A trashed page stays out of the reading
			// settings: that is the person's decision, and visitors would get a
			// trashed page.
			if ( 'trash' === get_post_status( $ids[ $mapKey ] ) ) {
				continue;
			}
			if ( $entry->frontPage && SiteSetting::isUnset( (int) get_option( 'page_on_front' ) ) ) {
				update_option( 'show_on_front', 'page' );
				update_option( 'page_on_front', $ids[ $mapKey ] );
			}
			if ( $entry->postsPage && SiteSetting::isUnset( (int) get_option( 'page_for_posts' ) ) ) {
				update_option( 'page_for_posts', $ids[ $mapKey ] );
			}
		}
	}
}
