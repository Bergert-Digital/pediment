<?php
/**
 * WPML implementation of the seeding engine's language seam.
 *
 * Everything WPML-specific in this product lives here, in WpmlSetup, and in
 * inc/wpml-compat.php. Nothing else may call a wpml_* or icl_* function, or
 * read an ICL_* constant.
 *
 * @package Pediment
 */

declare(strict_types=1);

namespace Pediment\Language;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class WpmlProvider implements LanguageProvider {
	/**
	 * "WPML is active" is not enough, mirroring PolylangProvider: an
	 * installed-but-unconfigured WPML returns no active languages, and a seeder
	 * crossed with zero languages writes nothing while reporting success.
	 */
	public static function isActive(): bool {
		if ( ! defined( 'ICL_SITEPRESS_VERSION' ) ) {
			return false;
		}
		$active = apply_filters( 'wpml_active_languages', null );

		return is_array( $active ) && [] !== $active;
	}

	/**
	 * Configured language codes, default first — the order DesiredState and
	 * Applier depend on (a default that is not first writes children before
	 * their parent exists).
	 *
	 * @return string[]
	 */
	public function languages(): array {
		$active = (array) apply_filters( 'wpml_active_languages', null );
		$codes  = array_values( array_map( 'strval', array_keys( $active ) ) );

		$default = $this->defaultLanguage();
		if ( '' === $default ) {
			return $codes;
		}

		$rest = array_values( array_filter( $codes, static fn( string $c ): bool => $c !== $default ) );

		return array_merge( [ $default ], $rest );
	}

	public function defaultLanguage(): string {
		return (string) apply_filters( 'wpml_default_language', null );
	}

	public function currentLanguage(): string {
		return (string) apply_filters( 'wpml_current_language', null );
	}

	/**
	 * Whether a post carries a language assignment at all — the untagged-post
	 * signal Applier's repair relies on. WPML returns null for an element it
	 * has never seen.
	 */
	public function hasLanguage( int $postId ): bool {
		if ( $postId <= 0 ) {
			return false;
		}
		$code = apply_filters(
			'wpml_element_language_code',
			null,
			[ 'element_id' => $postId, 'element_type' => 'post_' . get_post_type( $postId ) ]
		);

		return null !== $code && '' !== (string) $code;
	}

	public function translationOf( int $postId, string $language ): int {
		if ( $postId <= 0 || '' === $language ) {
			return 0;
		}
		$translated = apply_filters(
			'wpml_object_id',
			$postId,
			get_post_type( $postId ),
			false, // return null, not the original, when the translation is absent.
			$language
		);

		return null === $translated ? 0 : (int) $translated;
	}

	/**
	 * @param array<string,mixed> $args
	 * @return array<string,mixed>
	 */
	public function unscopedQuery( array $args ): array {
		// WPML scopes through the posts_* filters, which suppress_filters=true
		// turns off (the reason PolylangProvider::unscopedQuery already sets it).
		$args['suppress_filters'] = true;

		return $args;
	}

	public function setLanguage( int $postId, string $language ): void {
		if ( $postId <= 0 || '' === $language ) {
			return;
		}
		$type = 'post_' . get_post_type( $postId );

		// Reuse an existing trid so re-tagging a post keeps its group; false
		// tells WPML to mint a new translation group.
		$trid = apply_filters( 'wpml_element_trid', null, $postId, $type );

		do_action(
			'wpml_set_element_language_details',
			[
				'element_id'           => $postId,
				'element_type'         => $type,
				'trid'                 => $trid ?: false,
				'language_code'        => $language,
				'source_language_code' => null,
			]
		);
	}

	/**
	 * @param array<string,int> $map language code => post ID
	 */
	public function linkTranslations( array $map ): void {
		$clean = array_filter(
			$map,
			static fn( $postId, $language ): bool => is_int( $postId ) && $postId > 0 && '' !== $language,
			ARRAY_FILTER_USE_BOTH
		);

		if ( count( $clean ) < 2 ) {
			return;
		}

		// Anchor the group on one member's trid (the default language's when
		// present, else the first), then re-register every other member onto
		// that same trid with a source language. This is WPML's equivalent of
		// Polylang's "replace the whole group".
		$default    = $this->defaultLanguage();
		$anchorLang = isset( $clean[ $default ] ) ? $default : (string) array_key_first( $clean );
		$anchorId   = (int) $clean[ $anchorLang ];
		$anchorType = 'post_' . get_post_type( $anchorId );

		$trid = apply_filters( 'wpml_element_trid', null, $anchorId, $anchorType );
		if ( ! $trid ) {
			// Anchor must belong to a group first; assign it, then re-read.
			do_action(
				'wpml_set_element_language_details',
				[
					'element_id'           => $anchorId,
					'element_type'         => $anchorType,
					'trid'                 => false,
					'language_code'        => $anchorLang,
					'source_language_code' => null,
				]
			);
			$trid = apply_filters( 'wpml_element_trid', null, $anchorId, $anchorType );
		}

		foreach ( $clean as $language => $postId ) {
			if ( $language === $anchorLang ) {
				continue;
			}
			do_action(
				'wpml_set_element_language_details',
				[
					'element_id'           => (int) $postId,
					'element_type'         => 'post_' . get_post_type( (int) $postId ),
					'trid'                 => $trid,
					'language_code'        => (string) $language,
					'source_language_code' => $anchorLang,
				]
			);
		}
	}

	/** @param bool|array<string,mixed> $config */
	public function languageSwitcherBlock( $config ): string {
		return '';
	}
}
