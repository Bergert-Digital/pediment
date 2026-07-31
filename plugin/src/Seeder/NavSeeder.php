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
				if ( ! $spec instanceof NavSpec || PlanItem::UNCHANGED === $item->action ) {
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
								'post_name'    => sanitize_title( $spec->key . ( '' !== $item->language ? '-' . $item->language : '' ) ),
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
		} finally {
			Kses::restore( $kses );
		}

		return $ids;
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
					// A menu that quietly comes out short is worse than one that
					// fails: the operator sees a working site missing a link.
					$this->errors[] = sprintf( 'navs.%s: "%s" has no seeded post yet — the link was left out.', $spec->key, $item['entry'] );
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
						'post_status'    => [ 'publish', 'draft' ],
						'posts_per_page' => -1,
						'no_found_rows'  => true,
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
