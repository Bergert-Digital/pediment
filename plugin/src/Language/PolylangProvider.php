<?php
/**
 * Polylang implementation of the seeding engine's language seam.
 *
 * Everything Polylang-specific in this product lives here, in
 * PolylangSetup, and in the two files under inc/ that touch the front end.
 * Nothing else may call a pll_* function.
 *
 * @package Pediment
 */

declare(strict_types=1);

namespace Pediment\Language;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class PolylangProvider implements LanguageProvider {
	/**
	 * Whether this provider can actually do its job.
	 *
	 * "Polylang is active" is not enough: an activated-but-unconfigured
	 * Polylang returns an empty language list, and a seeder crossed with zero
	 * languages writes nothing at all while reporting success.
	 */
	public static function isActive(): bool {
		return function_exists( 'pll_languages_list' )
			&& function_exists( 'pll_default_language' )
			&& [] !== (array) pll_languages_list();
	}

	/**
	 * Configured language slugs, default first.
	 *
	 * The order is load-bearing, not cosmetic: DesiredState crosses the
	 * manifest with this list in order, and Applier resolves a child's
	 * post_parent and the front-page option from the default language's IDs.
	 * A default that is not first means children are written before the
	 * parent they point at exists.
	 *
	 * @return string[]
	 */
	public function languages(): array {
		$all     = array_values( array_map( 'strval', (array) pll_languages_list() ) );
		$default = $this->defaultLanguage();

		$rest = array_values( array_filter( $all, static fn( string $slug ): bool => $slug !== $default ) );

		return '' === $default ? $all : array_merge( [ $default ], $rest );
	}

	public function defaultLanguage(): string {
		return (string) pll_default_language();
	}

	public function setLanguage( int $postId, string $language ): void {
		if ( $postId <= 0 || '' === $language ) {
			return;
		}
		pll_set_post_language( $postId, $language );
	}

	/**
	 * Whether a post carries a language term at all.
	 *
	 * `pll_get_post_language()` returns `false`, not `''`, for a post that
	 * has no language term at all — that is the one signal Polylang gives
	 * for "never tagged" as distinct from "tagged with the default
	 * language," and it is the only thing that makes the untagged-post
	 * repair in Applier safe to run unconditionally on every seed.
	 */
	public function hasLanguage( int $postId ): bool {
		return $postId > 0 && false !== pll_get_post_language( $postId );
	}

	/**
	 * @param array<string,int> $map language code => post ID
	 */
	public function linkTranslations( array $map ): void {
		// pll_save_post_translations() REPLACES the whole group. Handing it a
		// map that files a 0 — "no post" — under a real language key would let
		// Polylang's validate_translations() drop that key, taking whatever
		// post really held it out of the group along with it. Invisible with
		// two languages, silent data loss with five.
		$clean = array_filter(
			$map,
			static fn( $postId, $language ): bool => is_int( $postId ) && $postId > 0 && '' !== $language,
			ARRAY_FILTER_USE_BOTH
		);

		if ( count( $clean ) < 2 ) {
			return;
		}

		pll_save_post_translations( $clean );
	}

	public function translationOf( int $postId, string $language ): int {
		if ( $postId <= 0 || '' === $language ) {
			return 0;
		}

		// Fast path, and the only correct answer for an untranslated post that
		// simply IS the language being asked for.
		if ( (string) pll_get_post_language( $postId ) === $language ) {
			return $postId;
		}

		return (int) pll_get_post( $postId, $language );
	}

	/**
	 * @param array<string,mixed> $args
	 * @return array<string,mixed>
	 */
	public function unscopedQuery( array $args ): array {
		// `suppress_filters` does NOT work here (dd23712). Polylang hooks
		// parse_query and mutates query_vars['tax_query'] directly; WP_Query
		// re-parses that tax query inside get_posts() on a branch gated on
		// `! $this->is_singular`, and nothing on that branch consults
		// suppress_filters, so the language clause survives it.
		//
		// What Polylang does honour is the `lang` query var:
		// PLL_Query::is_already_filtered() treats it as "the caller has
		// decided", and isset() is the whole test — an empty value is enough.
		//
		// suppress_filters is set too, for WPML, which scopes through the
		// posts_* filters this flag turns off. Harmless under Polylang.
		$args['lang']             = '';
		$args['suppress_filters'] = true;

		return $args;
	}

	public function currentLanguage(): string {
		return function_exists( 'pll_current_language' ) ? (string) pll_current_language() : '';
	}

	public function permalinkInLanguage( int $postId, string $language ): string {
		// Polylang's `_get_page_link`/`post_link` filter resolves a permalink
		// from the post's OWN language term, independent of the ambient request
		// language, so no context switch is needed — the raw permalink is
		// already the $language URL for a post tagged $language.
		return (string) get_permalink( $postId );
	}

	/**
	 * @param bool|array<string,mixed> $config
	 */
	public function languageSwitcherBlock( $config ): string {
		// `dropdown` defaults on (the "English ▾" menu-item form); an array
		// value overrides the block attributes. Language-agnostic — every
		// language's menu carries the same block.
		$attrs = array_merge(
			[ 'dropdown' => true ],
			is_array( $config ) ? $config : []
		);

		return '<!-- wp:polylang/navigation-language-switcher ' . wp_json_encode( $attrs, JSON_UNESCAPED_SLASHES ) . ' /-->';
	}
}
