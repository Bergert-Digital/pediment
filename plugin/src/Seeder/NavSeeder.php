<?php
/**
 * Navigation entities, resolved by (seed key, language) like everything else —
 * not by title or slug. A stray post holding the `primary` slug is what turned
 * replacements into `primary-2` (7d7ca30).
 *
 * @package Pediment
 */

declare(strict_types=1);

namespace Pediment\Seeder;

use Pediment\Language\LanguageProvider;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class NavSeeder {
	/** @var string[] Failures from the most recent apply(). */
	private array $errors = [];

	public function __construct( private LanguageProvider $lang ) {}

	/** @param array<string,int> $entryIds */
	public function plan( Manifest $manifest, array $entryIds ): Plan {
		$items    = [];
		$errors   = [];
		$existing = $this->existing();

		foreach ( $this->duplicates() as $mapKey => $duplicateIds ) {
			$errors[] = sprintf(
				'navs.%s is carried by %d navigation entities (IDs %s). Identity must be unique — delete or re-key the extras.',
				explode( '|', (string) $mapKey )[0],
				count( $duplicateIds ),
				implode( ', ', $duplicateIds )
			);
		}

		foreach ( $this->lang->languages() as $language ) {
			foreach ( $manifest->navs() as $key => $spec ) {
				$mapKey  = $key . '|' . $language;
				$desired = $this->serialize( $spec, $language, $entryIds );
				$postId  = (int) ( $existing[ $mapKey ] ?? 0 );

				if ( 0 === $postId ) {
					$items[] = new PlanItem(
						PlanItem::CREATE,
						PlanItem::KIND_NAV,
						$key,
						$language,
						0,
						[ 'items' => [ 'from' => 0, 'to' => count( $spec->items ) ] ]
					);
					continue;
				}

				// A client who deleted the menu still owns the identity: without
				// this the trashed entity keeps its seed key, a second one is
				// created beside it, and the next run reports a duplicate — the
				// `primary-2` shape this class exists to prevent.
				if ( 'trash' === get_post_status( $postId ) ) {
					$items[] = new PlanItem(
						PlanItem::RESTORE,
						PlanItem::KIND_NAV,
						$key,
						$language,
						$postId,
						[ 'status' => [ 'from' => 'trash', 'to' => 'publish' ] ]
					);
					continue;
				}

				$current = (string) get_post( $postId )->post_content;
				$items[] = $current === $desired
					? new PlanItem( PlanItem::UNCHANGED, PlanItem::KIND_NAV, $key, $language, $postId )
					: new PlanItem(
						PlanItem::UPDATE,
						PlanItem::KIND_NAV,
						$key,
						$language,
						$postId,
						[ 'items' => [ 'from' => substr_count( $current, 'wp:navigation-link' ), 'to' => count( $spec->items ) ] ],
						[],
						'membership is git-owned; editor changes to this menu are reverted'
					);
			}
		}

		return new Plan( $items, $errors );
	}

	/**
	 * @param array<string,int> $entryIds
	 * @return array<string,int> navKey|language => post ID
	 */
	public function apply( Plan $plan, Manifest $manifest, array $entryIds ): array {
		$this->errors = [];

		// Same contract as Applier::apply(): an errored plan writes nothing.
		if ( $plan->hasErrors() ) {
			$this->errors = $plan->errors();
			return $this->existing();
		}

		$ids = $this->existing();

		// Without this, wp_filter_post_kses() strips the escapes out of the
		// navigation-link JSON on save, the stored markup never matches a fresh
		// serialize(), and every nav is rewritten on every run.
		$kses = Kses::suspend();

		try {
			foreach ( $plan->byKind( PlanItem::KIND_NAV ) as $item ) {
				$spec = $manifest->navs()[ $item->key ] ?? null;
				if ( ! $spec instanceof NavSpec ) {
					continue;
				}

				// Checked for every item, including UNCHANGED ones: a menu that
				// quietly comes out short is worse than one that fails, and the
				// problem persists across runs even though the nav stops changing.
				$unresolved = $this->unresolvedEntries( $spec, $item->language, $entryIds );
				foreach ( $unresolved as $missing ) {
					$this->errors[] = sprintf( 'navs.%s: "%s" has no seeded post yet — the link is missing from the menu.', $spec->key, $missing );
				}

				// Never write a half-truth. serialize() skips a link it cannot
				// resolve, so writing here would REPLACE the live menu with a
				// shortened one — an entry whose create failed earlier in the same
				// run would silently delete a link from the header. Leave the
				// entity exactly as it is and let phase 5 report the mismatch.
				if ( [] !== $unresolved ) {
					continue;
				}

				if ( PlanItem::UNCHANGED === $item->action ) {
					continue;
				}

				$content = $this->serialize( $spec, $item->language, $entryIds );

				if ( PlanItem::CREATE === $item->action ) {
					$postId = wp_insert_post(
						wp_slash(
							[
								'post_type'    => 'wp_navigation',
								'post_status'  => 'publish',
								'post_title'   => $spec->title,
								'post_name'    => $this->slugFor( $spec, $item->language ),
								'post_content' => $content,
							]
						),
						true
					);
					if ( is_wp_error( $postId ) ) {
						$this->errors[] = sprintf( 'navs.%s: could not create the navigation entity — %s', $spec->key, $postId->get_error_message() );
						continue;
					}
					$postId = (int) $postId;
					$this->lang->setLanguage( $postId, $item->language );
					update_post_meta( $postId, Meta::KEY, $spec->key );
				} elseif ( PlanItem::RESTORE === $item->action ) {
					$postId = $item->postId;
					// The slug is rewritten too: wp_trash_post() renames it to
					// `primary__trashed`, and leaving that behind is what a later
					// create would collide with.
					$restored = wp_update_post(
						wp_slash(
							[
								'ID'           => $postId,
								'post_status'  => 'publish',
								'post_name'    => $this->slugFor( $spec, $item->language ),
								'post_content' => $content,
							]
						),
						true
					);
					if ( is_wp_error( $restored ) ) {
						$this->errors[] = sprintf( 'navs.%s: could not restore the navigation entity %d — %s', $spec->key, $postId, $restored->get_error_message() );
						continue;
					}
					Meta::clearTrashBookkeeping( $postId );
				} else {
					$postId  = $item->postId;
					$updated = wp_update_post( wp_slash( [ 'ID' => $postId, 'post_content' => $content ] ), true );
					if ( is_wp_error( $updated ) ) {
						$this->errors[] = sprintf( 'navs.%s: could not update the navigation entity — %s', $spec->key, $updated->get_error_message() );
						continue;
					}
				}

				$ids[ $item->mapKey() ] = $postId;
			}

			$this->linkTranslationGroups( $ids );

			return $ids;
		} finally {
			Kses::restore( $kses );
		}
	}

	/** @return string[] Failures from the most recent apply(). */
	public function errors(): array {
		return $this->errors;
	}

	/** @param array<string,int> $entryIds */
	public function serialize( NavSpec $spec, string $language, array $entryIds ): string {
		$links = [];

		foreach ( $spec->items as $item ) {
			if ( isset( $item['entry'] ) ) {
				$postId = (int) ( $entryIds[ $item['entry'] . '|' . $language ] ?? 0 );
				if ( 0 === $postId ) {
					// Reported by apply() via unresolvedEntries(), not from here:
					// serialize() must stay pure, and an unresolved link has to be
					// reported on EVERY run, not only the one that rewrites the nav.
					continue;
				}
				$post    = get_post( $postId );
				$links[] = '<!-- wp:navigation-link ' . wp_json_encode(
					[
						'label' => (string) ( $item['label'] ?? ( $post ? $post->post_title : '' ) ),
						'type'  => $post ? $post->post_type : 'page',
						'id'    => $postId,
						'kind'  => 'post-type',
						'url'   => (string) get_permalink( $postId ),
					],
					// JSON_UNESCAPED_SLASHES matches what the block editor writes, and
					// keeps the markup stable under KSES, which strips `\/` on save.
					JSON_UNESCAPED_SLASHES
				) . ' /-->';
				continue;
			}

			$links[] = '<!-- wp:navigation-link ' . wp_json_encode(
				[
					'label' => (string) $item['label'],
					'url'   => (string) $item['url'],
					'kind'  => 'custom',
				],
				// JSON_UNESCAPED_SLASHES matches what the block editor writes, and
				// keeps the markup stable under KSES, which strips `\/` on save.
				JSON_UNESCAPED_SLASHES
			) . ' /-->';
		}

		return implode( "\n", $links );
	}

	private function slugFor( NavSpec $spec, string $language ): string {
		return sanitize_title( $spec->key . ( '' !== $language ? '-' . $language : '' ) );
	}

	/**
	 * Put every language of a nav key into one translation group.
	 *
	 * Same rule as entries: pll_save_post_translations() replaces the whole
	 * group, so this runs once with the full map after every entity is written.
	 *
	 * Without it, a translated menu is invisible to pll_get_post(), the header's
	 * per-language lookup falls back to whichever nav was saved last, and every
	 * language renders one language's navigation — the outage this engine's nav
	 * identity model exists to prevent.
	 *
	 * @param array<string,int> $ids navKey|language => post ID
	 */
	private function linkTranslationGroups( array $ids ): void {
		if ( count( $this->lang->languages() ) < 2 ) {
			return;
		}

		$byKey = [];
		foreach ( $ids as $mapKey => $postId ) {
			// array_pad(explode('|', ...), 2, '') does not truncate if a nav key
			// or language code ever contained '|' itself — not reachable today
			// (both come from manifest identifiers), same caveat as
			// Applier::linkTranslationGroups().
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
	 * Entry keys this nav references that have no seeded post yet.
	 *
	 * @param array<string,int> $entryIds
	 * @return string[]
	 */
	private function unresolvedEntries( NavSpec $spec, string $language, array $entryIds ): array {
		$missing = [];
		foreach ( $spec->items as $item ) {
			if ( isset( $item['entry'] ) && 0 === (int) ( $entryIds[ $item['entry'] . '|' . $language ] ?? 0 ) ) {
				$missing[] = (string) $item['entry'];
			}
		}
		return $missing;
	}

	/** @return array<string,int> navKey|language => post ID */
	private function existing(): array {
		$ids = [];
		foreach ( $this->keyed() as $mapKey => $postIds ) {
			$ids[ $mapKey ] = $postIds[0];
		}
		return $ids;
	}

	/** @return array<string,int[]> navKey|language => post IDs carrying that identity, only where there is more than one. */
	private function duplicates(): array {
		return array_filter( $this->keyed(), static fn( array $postIds ): bool => count( $postIds ) > 1 );
	}

	/** @return array<string,int[]> navKey|language => every post ID carrying that identity. */
	private function keyed(): array {
		$ids = [];
		foreach (
			get_posts(
				$this->lang->unscopedQuery(
					[
						'post_type'      => 'wp_navigation',
						// Trashed entities still hold their seed key: ignoring them
						// would create a second nav under one identity.
						'post_status'    => [ 'publish', 'draft', 'trash' ],
						'posts_per_page' => -1,
						'no_found_rows'  => true,
						'orderby'        => 'ID',
						'order'          => 'ASC',
						// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- seed identity lookup.
						'meta_key'       => Meta::KEY,
						'meta_compare'   => 'EXISTS',
					]
				)
			) as $nav
		) {
			$key = (string) get_post_meta( $nav->ID, Meta::KEY, true );
			if ( '' === $key ) {
				continue;
			}
			$language = '';
			foreach ( $this->lang->languages() as $candidate ) {
				if ( $this->lang->translationOf( (int) $nav->ID, $candidate ) === (int) $nav->ID ) {
					$language = $candidate;
					break;
				}
			}
			$ids[ $key . '|' . $language ][] = (int) $nav->ID;
		}
		return $ids;
	}
}
