<?php
/**
 * Backfills seed identity onto content that predates the engine.
 *
 * The engine resolves actual state only by `_pediment_seed_key`
 * (StateReader), which is what keeps it off slug lookups — and what makes a
 * site seeded by anything else invisible to it. Running a first seed against
 * such a site plans a CREATE for every entry and duplicates the whole site.
 *
 * A claim is the one-time bridge: it matches by the things a legacy row and a
 * manifest entry can still agree on (slug and language for pages and posts,
 * file name for media), and writes exactly one meta key — the same
 * `_pediment_seed_key` MediaSeeder gives an attachment it uploads. The
 * seed after a claim finds every claimed row and leaves it as it is — the
 * seeder never writes to a row that exists.
 *
 * @package Pediment
 */

declare(strict_types=1);

namespace Pediment\Seeder;

use Pediment\Language\LanguageProvider;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Claimer {
	/** Statuses a claim considers. Trash is deliberately absent. */
	private const STATUSES = [ 'publish', 'draft', 'pending', 'private', 'future' ];

	public function __construct( private LanguageProvider $lang ) {}

	/**
	 * @param array<string,ActualEntry> $actual Keyed "<seedKey>|<language>", from StateReader::read().
	 */
	public function plan( Manifest $manifest, array $actual ): Plan {
		$items    = [];
		$resolved = [];
		foreach ( $actual as $mapKey => $entry ) {
			$resolved[ $mapKey ] = $entry->id;
		}

		$default = $this->lang->defaultLanguage();

		foreach ( $this->lang->languages() as $language ) {
			foreach ( $manifest->entriesInDependencyOrder() as $spec ) {
				$mapKey = $spec->key . '|' . $language;
				if ( isset( $resolved[ $mapKey ] ) ) {
					continue; // Already carries the key: nothing to claim.
				}

				$item = $this->planOne( $spec, $language, $default, $resolved );
				if ( PlanItem::CLAIM === $item->action ) {
					$resolved[ $mapKey ] = $item->postId;
				}
				$items[] = $item;
			}
		}

		// One instance for the whole nav pass, and no query at all when the
		// manifest declares no navs: keyed() runs a get_posts() whose result
		// the loop below would never look at.
		$declaredNavs = count( $manifest->navs() );
		if ( $declaredNavs > 0 ) {
			$navSeeder = new NavSeeder( $this->lang );

			// StateReader::EXCLUDED_TYPES excludes wp_navigation, so $resolved
			// above has no opinion on navs: this is nav claiming's own source of
			// truth for "already carries the key," the same role $actual plays
			// for entries.
			$claimedNavs = $navSeeder->keyed();

			foreach ( $this->lang->languages() as $language ) {
				foreach ( $manifest->navs() as $spec ) {
					$mapKey = $spec->key . '|' . $language;
					if ( isset( $claimedNavs[ $mapKey ] ) ) {
						continue; // Already carries the key: nothing to claim.
					}
					$items[] = $this->planNav( $navSeeder, $spec, $language, $declaredNavs, $default );
				}
			}
		}

		return new Plan( array_merge( $this->planMedia( $manifest ), $items ) );
	}

	/**
	 * Media is matched by file name: the basename of the manifest `file`
	 * against the name an attachment was uploaded under. That is the one thing
	 * a legacy upload of a theme-shipped image and the manifest still agree
	 * on — titles and IDs differ per site.
	 *
	 * Media identity has no language (PolylangSetup keeps Polylang's
	 * media_support off), so each media key is planned once, with ''.
	 *
	 * @return PlanItem[]
	 */
	private function planMedia( Manifest $manifest ): array {
		$media = $manifest->media();
		if ( [] === $media ) {
			return []; // No attachment query for a manifest without media.
		}

		// The MediaSeeder's own lookup, trash included: a key an attachment
		// already carries is resolved, exactly as the next seed will see it.
		$keyed  = ( new MediaSeeder() )->map( $manifest );
		$byName = $this->unclaimedAttachmentsByName();
		$taken  = [];
		$items  = [];

		foreach ( $media as $key => $spec ) {
			if ( $keyed->has( $key ) ) {
				continue; // Already carries the key: nothing to claim.
			}

			$name = self::fileName( $spec->file );
			// An attachment claimed for an earlier media key in this run is
			// not a candidate again: one row never takes two keys.
			$candidates = array_values( array_diff( $byName[ $name ] ?? [], $taken ) );

			if ( [] === $candidates ) {
				$items[] = new PlanItem(
					PlanItem::NO_MATCH,
					PlanItem::KIND_MEDIA,
					$key,
					'',
					0,
					[],
					sprintf( 'no unclaimed attachment with the file name "%s" — the next seed will upload it.', $name )
				);
				continue;
			}

			if ( count( $candidates ) > 1 ) {
				$items[] = new PlanItem(
					PlanItem::AMBIGUOUS,
					PlanItem::KIND_MEDIA,
					$key,
					'',
					0,
					[],
					sprintf(
						'%d unclaimed attachments share the file name "%s" (IDs %s) — claim nothing until one is deleted.',
						count( $candidates ),
						$name,
						implode( ', ', $candidates )
					)
				);
				continue;
			}

			$attachmentId = (int) $candidates[0];
			$taken[]      = $attachmentId;
			$items[]      = new PlanItem(
				PlanItem::CLAIM,
				PlanItem::KIND_MEDIA,
				$key,
				'',
				$attachmentId,
				[ 'seed_key' => [ 'from' => null, 'to' => $key ] ],
				sprintf( 'attachment "%s" (ID %d)', $name, $attachmentId )
			);
		}

		return $items;
	}

	/**
	 * Unkeyed attachments, indexed by every file name they answer to: the
	 * name in `_wp_attached_file` and, when WordPress stored a `-scaled` or
	 * `-rotated` copy, the uploaded original's name from the metadata's
	 * `original_image` (what wp_get_original_image_path() resolves). Trash is
	 * excluded, as for posts.
	 *
	 * @return array<string,int[]> file name => attachment IDs, ID ascending
	 */
	private function unclaimedAttachmentsByName(): array {
		$out = [];
		foreach (
			get_posts(
				[
					'post_type'      => 'attachment',
					'post_status'    => [ 'inherit', 'private' ],
					'posts_per_page' => -1,
					'orderby'        => 'ID',
					'order'          => 'ASC',
					'no_found_rows'  => true,
					// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- one-time claim, not a front-end query.
					'meta_query'     => [
						[
							'key'     => Meta::KEY,
							'compare' => 'NOT EXISTS',
						],
					],
				]
			) as $attachment
		) {
			$id    = (int) $attachment->ID;
			$names = [ self::fileName( (string) get_post_meta( $id, '_wp_attached_file', true ) ) ];

			$metadata = wp_get_attachment_metadata( $id );
			if ( is_array( $metadata ) && ! empty( $metadata['original_image'] ) ) {
				$names[] = self::fileName( (string) $metadata['original_image'] );
			}

			foreach ( array_unique( array_filter( $names ) ) as $name ) {
				$out[ $name ][] = $id;
			}
		}
		return $out;
	}

	/**
	 * The name a file is compared by. sanitize_file_name() is what an upload
	 * applies to the name it stores, so a theme file called "Team Photo.jpg"
	 * still matches its upload "Team-Photo.jpg"; lower case, because an
	 * upload may lower-case the extension.
	 */
	private static function fileName( string $path ): string {
		$base = wp_basename( $path );
		return '' === $base ? '' : strtolower( sanitize_file_name( $base ) );
	}

	/** @param array<string,int> $resolved mapKey => post ID, including rows claimed earlier in this run. */
	private function planOne( EntrySpec $spec, string $language, string $default, array $resolved ): PlanItem {
		$slug = $spec->slugFor( $language, $default );

		$parentId = 0;
		if ( null !== $spec->parent ) {
			$parentMapKey = $spec->parent . '|' . $language;
			if ( ! isset( $resolved[ $parentMapKey ] ) ) {
				return $this->noMatch(
					$spec,
					$language,
					sprintf(
						'parent "%s" is not resolved in this language, so a nested match cannot be verified.',
						$spec->parent
					)
				);
			}
			$parentId = $resolved[ $parentMapKey ];
		}

		$candidates = $this->candidates( $spec->postType, $slug, $parentId, $language, $default );

		if ( [] === $candidates ) {
			return $this->noMatch(
				$spec,
				$language,
				sprintf( 'no unclaimed %s with slug "%s" — the next seed will create it.', $spec->postType, $slug )
			);
		}

		if ( count( $candidates ) > 1 ) {
			return new PlanItem(
				PlanItem::AMBIGUOUS,
				PlanItem::KIND_ENTRY,
				$spec->key,
				$language,
				0,
				[],
				sprintf(
					'%d unclaimed %s posts share the slug "%s" (IDs %s) — claim nothing until one is deleted or re-slugged.',
					count( $candidates ),
					$spec->postType,
					$slug,
					implode( ', ', $candidates )
				)
			);
		}

		$postId = (int) $candidates[0];

		return new PlanItem(
			PlanItem::CLAIM,
			PlanItem::KIND_ENTRY,
			$spec->key,
			$language,
			$postId,
			[ 'seed_key' => [ 'from' => null, 'to' => $spec->key ] ],
			sprintf( '%s "%s" (ID %d)', $spec->postType, $slug, $postId )
		);
	}

	private function noMatch( EntrySpec $spec, string $language, string $note ): PlanItem {
		return new PlanItem( PlanItem::NO_MATCH, PlanItem::KIND_ENTRY, $spec->key, $language, 0, [], $note );
	}

	/**
	 * Unkeyed posts of this type and slug, in this language, under this parent.
	 *
	 * `post_name__in`, never `name`: the `name` query var makes WP_Query
	 * singular, and get_posts() skips tax_query on singular queries — which is
	 * exactly how the header template part matched across themes.
	 *
	 * @return int[]
	 */
	private function candidates( string $postType, string $slug, int $parentId, string $language, string $default ): array {
		$args = $this->lang->unscopedQuery(
			[
				'post_type'      => $postType,
				'post_name__in'  => [ $slug ],
				'post_status'    => self::STATUSES,
				'posts_per_page' => -1,
				'orderby'        => 'ID',
				'order'          => 'ASC',
				'no_found_rows'  => true,
			]
		);

		$out = [];
		foreach ( get_posts( $args ) as $post ) {
			$id = (int) $post->ID;

			if ( '' !== (string) get_post_meta( $id, Meta::KEY, true ) ) {
				continue; // Belongs to another key — never stolen.
			}
			if ( (int) $post->post_parent !== $parentId ) {
				continue; // A same-slug page nested elsewhere is a different page.
			}
			if ( ! $this->languageMatches( $id, $language, $default ) ) {
				continue;
			}

			$out[] = $id;
		}

		return $out;
	}

	/**
	 * A post's language must equal the one being claimed for — except that a
	 * post carrying no language at all is a candidate for the default language
	 * only. That is the monolingual-site-adopting-Polylang case, and claiming
	 * it into a non-default language would silently move a page between
	 * languages.
	 */
	private function languageMatches( int $postId, string $language, string $default ): bool {
		if ( '' === $language ) {
			return true; // Monolingual: NullProvider reports one empty language.
		}
		if ( ! $this->lang->hasLanguage( $postId ) ) {
			return $language === $default;
		}
		return $this->lang->translationOf( $postId, $language ) === $postId;
	}

	/**
	 * A legacy navigation entity's slug is whatever the previous seeder gave
	 * it, so slug matching alone is unreliable. When the manifest declares one
	 * nav and the language holds exactly one unclaimed navigation entity, that
	 * is unambiguous and is claimed. Otherwise fall back to the derived slug,
	 * and report rather than guess.
	 */
	private function planNav( NavSeeder $navSeeder, NavSpec $spec, string $language, int $declaredNavs, string $default ): PlanItem {
		$candidates = $this->navCandidates( $language, $default );

		if ( 1 === $declaredNavs && 1 === count( $candidates ) ) {
			return $this->navItem( PlanItem::CLAIM, $spec, $language, (int) $candidates[0] );
		}

		$slug   = $navSeeder->slugFor( $spec, $language );
		$bySlug = array_values(
			array_filter(
				$candidates,
				static function ( int $id ) use ( $slug ): bool {
					$post = get_post( $id );
					return $post instanceof \WP_Post && $post->post_name === $slug;
				}
			)
		);

		if ( 1 === count( $bySlug ) ) {
			return $this->navItem( PlanItem::CLAIM, $spec, $language, (int) $bySlug[0] );
		}

		if ( [] === $candidates ) {
			return new PlanItem(
				PlanItem::NO_MATCH,
				PlanItem::KIND_NAV,
				$spec->key,
				$language,
				0,
				[],
				'no unclaimed navigation entity — the next seed will create it.'
			);
		}

		return new PlanItem(
			PlanItem::AMBIGUOUS,
			PlanItem::KIND_NAV,
			$spec->key,
			$language,
			0,
			[],
			sprintf(
				'%d unclaimed navigation entities (IDs %s) and none whose slug is "%s" — re-slug the right one, or claim it by hand.',
				count( $candidates ),
				implode( ', ', $candidates ),
				$slug
			)
		);
	}

	private function navItem( string $action, NavSpec $spec, string $language, int $postId ): PlanItem {
		return new PlanItem(
			$action,
			PlanItem::KIND_NAV,
			$spec->key,
			$language,
			$postId,
			[ 'seed_key' => [ 'from' => null, 'to' => $spec->key ] ],
			sprintf( 'navigation "%s" (ID %d)', (string) get_the_title( $postId ), $postId )
		);
	}

	/** @return int[] Unclaimed wp_navigation posts in this language. */
	private function navCandidates( string $language, string $default ): array {
		$args = $this->lang->unscopedQuery(
			[
				'post_type'      => 'wp_navigation',
				'post_status'    => self::STATUSES,
				'posts_per_page' => -1,
				'orderby'        => 'ID',
				'order'          => 'ASC',
				'no_found_rows'  => true,
			]
		);

		$out = [];
		foreach ( get_posts( $args ) as $post ) {
			$id = (int) $post->ID;
			if ( '' !== (string) get_post_meta( $id, Meta::KEY, true ) ) {
				continue;
			}
			if ( ! $this->languageMatches( $id, $language, $default ) ) {
				continue;
			}
			$out[] = $id;
		}

		return $out;
	}

	/** @return array{claimed:int,errors:string[]} */
	public function apply( Plan $plan ): array {
		$claimed = 0;
		$errors  = [];

		foreach ( $plan->items() as $item ) {
			if ( PlanItem::CLAIM !== $item->action || $item->postId <= 0 ) {
				continue;
			}
			if ( '' !== (string) get_post_meta( $item->postId, Meta::KEY, true ) ) {
				// Something claimed it between plan and apply. Refusing is the
				// only safe answer: the alternative is two keys racing for one row.
				$errors[] = sprintf(
					'%s: post %d already carries a seed key — nothing was written for this entry.',
					$item->mapKey(),
					$item->postId
				);
				continue;
			}

			update_post_meta( $item->postId, Meta::KEY, $item->key );
			++$claimed;
		}

		return [
			'claimed' => $claimed,
			'errors'  => $errors,
		];
	}
}
