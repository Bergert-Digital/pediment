<?php
declare(strict_types=1);

namespace Pediment\Seeder;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class EntrySpec {
	/**
	 * @param array<string,string[]>                                          $terms
	 * @param array<string,array{title?:string,slug?:string,pattern?:string}> $translations
	 */
	public function __construct(
		public readonly string $key,
		public readonly string $postType,
		public readonly string $title,
		public readonly string $slug,
		public readonly ?string $parent,
		public readonly ?string $pattern,
		public readonly ?string $content,
		public readonly bool $frontPage,
		public readonly bool $postsPage,
		public readonly int $menuOrder,
		public readonly array $terms,
		public readonly array $translations = []
	) {}

	/**
	 * The declared title for a language, falling back to the default language's.
	 *
	 * $default is accepted but deliberately unread: it exists for signature
	 * symmetry with slugFor()/patternFor() so the three resolvers can be called
	 * as a set without a caller needing to remember which one is asymmetric.
	 * There is only ever one top-level title to fall back to, regardless of
	 * which language is the manifest's default.
	 */
	public function titleFor( string $language, string $default ): string {
		return (string) ( $this->translations[ $language ]['title'] ?? $this->title );
	}

	/**
	 * The slug for a language.
	 *
	 * A non-default language with no declared slug gets `<slug>-<lang>`, not
	 * the default's slug. Polylang does not hook wp_unique_post_slug, so all
	 * top-level pages share one slug namespace regardless of language: two
	 * languages both asking for `about` land as `about` and `about-2`, the
	 * Verifier reports a slug mismatch on every run, and no re-run converges.
	 * NavSeeder::slugFor() uses the same idiom for the same reason.
	 */
	public function slugFor( string $language, string $default ): string {
		$declared = (string) ( $this->translations[ $language ]['slug'] ?? '' );
		if ( '' !== $declared ) {
			return $declared;
		}
		if ( $language === $default || '' === $language ) {
			return $this->slug;
		}
		return $this->slug . '-' . $language;
	}

	/**
	 * The pattern slug for a language.
	 *
	 * Returns null for a `content`-declared entry. A non-default language with
	 * no override gets the `<pattern>-<lang>` convention; the resolver decides
	 * whether that pattern is actually registered and reports the miss.
	 */
	public function patternFor( string $language, string $default ): ?string {
		if ( null === $this->pattern ) {
			return null;
		}
		$declared = (string) ( $this->translations[ $language ]['pattern'] ?? '' );
		if ( '' !== $declared ) {
			return $declared;
		}
		if ( $language === $default || '' === $language ) {
			return $this->pattern;
		}
		return $this->pattern . '-' . $language;
	}
}
