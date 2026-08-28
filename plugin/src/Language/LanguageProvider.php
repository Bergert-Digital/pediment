<?php
/**
 * The one seam between the seeding engine and a multilingual plugin.
 *
 * @package Pediment
 */

declare(strict_types=1);

namespace Pediment\Language;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

interface LanguageProvider {
	/** @return string[] Configured language codes; [''] when monolingual. */
	public function languages(): array;

	public function defaultLanguage(): string;

	/** Assign a language. MUST be called in the same write path as creation. */
	public function setLanguage( int $postId, string $language ): void;

	/**
	 * Whether a post already carries a language tag, in any language.
	 *
	 * Exists so a caller can repair a post that predates this seeder's
	 * language-tagging — the migration case: a monolingual site's existing
	 * page never went through create(), so it has no language term at all —
	 * without ever re-tagging or moving a post that already has one. That
	 * "only if untagged" rule is the whole safety property: it is what makes
	 * it safe to run the repair on every seed, forever, including against a
	 * post whose language was deliberately changed by an editor.
	 */
	public function hasLanguage( int $postId ): bool;

	/** @param array<string,int> $map language code => post ID */
	public function linkTranslations( array $map ): void;

	/** @return int Post ID of the translation, 0 when there is none. */
	public function translationOf( int $postId, string $language ): int;

	/**
	 * Return query args that see every language.
	 *
	 * `suppress_filters` does NOT escape a multilingual plugin's parse_query
	 * scoping (dd23712); the idiom that does lives in the adapter, once.
	 *
	 * @param array<string,mixed> $args
	 * @return array<string,mixed>
	 */
	public function unscopedQuery( array $args ): array;

	/** The current request's language code; '' when monolingual. */
	public function currentLanguage(): string;

	/**
	 * Serialized language-switcher block for the seeded header, or '' when the
	 * active plugin has no switcher (monolingual). $config is the manifest's
	 * `language_switcher` value: `true`, or an array of block-attribute overrides.
	 *
	 * @param bool|array<string,mixed> $config
	 */
	public function languageSwitcherBlock( $config ): string;
}
