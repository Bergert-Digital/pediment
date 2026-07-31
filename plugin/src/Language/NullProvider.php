<?php
/**
 * Monolingual implementation. Every site runs this code path, which is what
 * makes the multilingual path testable (spec §4.3).
 *
 * @package Pediment
 */

declare(strict_types=1);

namespace Pediment\Language;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class NullProvider implements LanguageProvider {
	public function languages(): array {
		return [ '' ];
	}

	public function defaultLanguage(): string {
		return '';
	}

	public function setLanguage( int $postId, string $language ): void {
		// No language taxonomy on a monolingual site.
	}

	public function linkTranslations( array $map ): void {
		// Nothing to link.
	}

	public function translationOf( int $postId, string $language ): int {
		return '' === $language ? $postId : 0;
	}

	public function unscopedQuery( array $args ): array {
		return $args;
	}
}
