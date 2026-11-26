<?php
/**
 * Finds a seeded page by its seed key, in the visitor's language.
 *
 * @package Pediment
 */

declare(strict_types=1);

namespace Pediment\Seeder;

use Pediment\Language\LanguageRegistry;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * A seeded page keeps its seed key on every translation; the language
 * plugin's term tells the translations apart. Shared markup (the footer
 * template part serves every language) names a page by key, and this class
 * turns the key into the post ID for the current request.
 */
final class SeededPage {

	/**
	 * The published page with seed key $key in the current language, else in
	 * the default language, else the oldest one. 0 when there is none.
	 *
	 * @param string $key Seed key, for example `legal-notice`.
	 */
	public static function id( string $key ): int {
		if ( '' === $key ) {
			return 0;
		}
		$provider = LanguageRegistry::provider();
		$ids      = array_map(
			'intval',
			get_posts(
				$provider->unscopedQuery(
					array(
						'post_type'      => 'page',
						'post_status'    => 'publish',
						'posts_per_page' => 20,
						'no_found_rows'  => true,
						'fields'         => 'ids',
						'orderby'        => 'ID',
						'order'          => 'ASC',
						// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- seed identity lookup.
						'meta_key'       => Meta::KEY,
						'meta_value'     => $key, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value -- see above.
					)
				)
			)
		);
		if ( ! $ids ) {
			return 0;
		}

		$languages = array_values( array_unique( array_filter( array( $provider->currentLanguage(), $provider->defaultLanguage() ) ) ) );
		foreach ( $languages as $language ) {
			foreach ( $ids as $id ) {
				if ( $provider->languageOf( $id ) === $language ) {
					return $id;
				}
			}
			$translation = $provider->translationOf( $ids[0], (string) $language );
			if ( $translation > 0 && 'publish' === get_post_status( $translation ) ) {
				return $translation;
			}
		}
		return $ids[0];
	}
}
