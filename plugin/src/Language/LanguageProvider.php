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
}
