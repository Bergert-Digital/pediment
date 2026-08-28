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

	/**
	 * Always true: a monolingual site has nothing to tag, so every post is
	 * already "as tagged as it will ever be." Reporting false here would make
	 * Applier's untagged-post repair call setLanguage() every single seed on
	 * every single site — a no-op in NullProvider, but not the no-op the
	 * caller should be able to rely on without inspecting the implementation.
	 */
	public function hasLanguage( int $postId ): bool {
		return true;
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

	public function currentLanguage(): string {
		return '';
	}

	public function permalinkInLanguage( int $postId, string $language ): string {
		// Monolingual: no language context to switch, one permalink.
		return (string) get_permalink( $postId );
	}

	/**
	 * @param bool|array<string,mixed> $config
	 */
	public function languageSwitcherBlock( $config ): string {
		// Monolingual: no switcher.
		return '';
	}
}
