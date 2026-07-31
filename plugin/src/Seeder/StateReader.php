<?php
/**
 * Phase 2: what the database actually holds, looked up by seed key and
 * unscoped by language.
 *
 * Slug lookups are what produced `primary-2` menus and duplicate pages
 * (7d7ca30, 45c9ca5). Nothing in the engine may call get_page_by_path().
 *
 * @package Pediment
 */

declare(strict_types=1);

namespace Pediment\Seeder;

use Pediment\Language\LanguageProvider;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class StateReader {
	/** Post types the engine treats as entries; navs and attachments are handled by their own seeders. */
	private const EXCLUDED_TYPES = [ 'wp_navigation', 'attachment', 'wp_template', 'wp_template_part', 'wp_global_styles' ];

	public function __construct( private LanguageProvider $lang ) {}

	/** @return array<string,ActualEntry> */
	public function read(): array {
		$entries = [];
		foreach ( $this->query() as $post ) {
			$entry                       = $this->toEntry( $post );
			$entries[ $entry->mapKey() ] = $entry;
		}
		return $entries;
	}

	/** @return array<string,int[]> */
	public function duplicates(): array {
		$byKey = [];
		foreach ( $this->query() as $post ) {
			$entry                      = $this->toEntry( $post );
			$byKey[ $entry->mapKey() ][] = $entry->id;
		}
		return array_filter( $byKey, static fn( array $ids ): bool => count( $ids ) > 1 );
	}

	/** @return \WP_Post[] */
	private function query(): array {
		$args = $this->lang->unscopedQuery(
			[
				'post_type'      => 'any',
				'post_status'    => [ 'publish', 'draft', 'pending', 'private', 'future', 'trash' ],
				'posts_per_page' => -1,
				'orderby'        => 'ID',
				'order'          => 'ASC',
				'no_found_rows'  => true,
				// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- seed identity lookup; runs once per seed.
				'meta_key'       => Meta::KEY,
				'meta_compare'   => 'EXISTS',
			]
		);

		return array_values(
			array_filter(
				get_posts( $args ),
				static fn( \WP_Post $post ): bool => ! in_array( $post->post_type, self::EXCLUDED_TYPES, true )
			)
		);
	}

	private function toEntry( \WP_Post $post ): ActualEntry {
		return new ActualEntry(
			(int) $post->ID,
			(string) get_post_meta( $post->ID, Meta::KEY, true ),
			$this->languageOf( (int) $post->ID ),
			(string) $post->post_type,
			(string) $post->post_title,
			(string) $post->post_name,
			(int) $post->post_parent,
			(string) $post->post_status,
			(int) $post->menu_order,
			(string) get_post_meta( $post->ID, Meta::HASH, true ),
			ContentHash::forPost( (int) $post->ID ),
			(string) get_post_meta( $post->ID, Meta::SOURCE, true )
		);
	}

	private function languageOf( int $postId ): string {
		foreach ( $this->lang->languages() as $language ) {
			if ( $this->lang->translationOf( $postId, $language ) === $postId ) {
				return $language;
			}
		}
		return $this->lang->defaultLanguage();
	}
}
