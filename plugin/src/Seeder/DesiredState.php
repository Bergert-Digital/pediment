<?php
/**
 * Phase 1: the manifest crossed with the configured languages.
 *
 * @package Pediment
 */

declare(strict_types=1);

namespace Pediment\Seeder;

use Pediment\Language\LanguageProvider;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class DesiredState {
	/** @var array<string,string[]> entry map key => media keys the manifest never declares */
	private array $undeclaredMedia = [];

	/** @var string[] Entries whose language has no declared title. */
	private array $missingTitles = [];

	public function __construct(
		private LanguageProvider $lang,
		private ContentResolver $resolver
	) {}

	/**
	 * @return array<string,DesiredEntry>
	 *
	 * @throws ManifestError When an entry's pattern is not registered.
	 */
	public function build( Manifest $manifest ): array {
		$desired               = [];
		$this->undeclaredMedia = [];
		$this->missingTitles   = [];
		$this->resolver->resetMissingPatterns();
		$declared              = array_keys( $manifest->media() );
		$default               = $this->lang->defaultLanguage();

		foreach ( $this->lang->languages() as $language ) {
			foreach ( $manifest->entriesInDependencyOrder() as $spec ) {
				$title   = $spec->titleFor( $language, $default );
				$content = $this->resolver->resolve( $spec, $language, $default );

				// A non-default language rendering the default language's title is
				// a translation nobody wrote yet. Not an error — the page is real
				// and navigable — but silence here is how a five-language site
				// ships three languages of English.
				if ( $language !== $default && '' !== $language && ! isset( $spec->translations[ $language ]['title'] ) ) {
					$this->missingTitles[] = sprintf(
						'%s (%s): no title declared — the page carries the default language title "%s".',
						$spec->key,
						$language,
						$spec->title
					);
				}

				$entry = new DesiredEntry(
					$spec->key,
					$language,
					$spec->postType,
					$title,
					$spec->slugFor( $language, $default ),
					$spec->parent,
					$content,
					$spec->frontPage,
					$spec->postsPage,
					$spec->menuOrder,
					$spec->terms,
					ContentHash::compute( $title, $content )
				);

				$desired[ $entry->id() ] = $entry;

				// The resolver records what it could not resolve per call, so it
				// has to be read here or it is overwritten by the next entry.
				// Keys the manifest DOES declare are the fresh-site case — media
				// simply has not been applied yet, and the MediaSeeder/Verifier
				// own that. A key nobody declares is a typo that can never
				// resolve: the literal sentinel lands in a live page and gets
				// hashed as if it were correct.
				$undeclared = array_values( array_diff( $this->resolver->unresolvedMediaKeys(), $declared ) );
				if ( [] !== $undeclared ) {
					$this->undeclaredMedia[ $entry->id() ] = $undeclared;
				}
			}
		}

		return $desired;
	}

	/**
	 * Media keys the entries reference that the manifest never declares.
	 *
	 * @return array<string,string[]> entry map key => media keys
	 */
	public function undeclaredMediaKeys(): array {
		return $this->undeclaredMedia;
	}

	/**
	 * Translations the manifest and the theme do not have yet.
	 *
	 * Reported as notices, never as problems: RunResult::ok() is false when
	 * problems exist and SeedCommand turns that into a non-zero exit, so a site
	 * that just added a language would fail its very first seed.
	 *
	 * @return string[]
	 */
	public function missingTranslations(): array {
		$lines = $this->missingTitles;

		foreach ( $this->resolver->missingPatterns() as $mapKey => $pattern ) {
			[ $key, $language ] = array_pad( explode( '|', (string) $mapKey ), 2, '' );
			$lines[]            = sprintf(
				'%s (%s): no pattern `%s` is registered — the page carries the default language content. Create patterns/%s.%s.php with `Slug: %s`, or run `wp pediment adopt %s --language=%s` once it is translated in the editor.',
				$key,
				$language,
				$pattern,
				$this->fileStem( $pattern, $language ),
				$language,
				$pattern,
				$key,
				$language
			);
		}

		return $lines;
	}

	/**
	 * `theme/sample-post-de` -> `sample-post` — the stem the file convention
	 * uses. Strips the exact known `-<language>` suffix rather than the last
	 * hyphen run: a hyphen-run regex eats real multi-word stems (`contact-page`,
	 * `hero-cta-faq`) because it is unanchored and matches from the FIRST
	 * hyphen, not the last.
	 */
	private function fileStem( string $pattern, string $language ): string {
		$parts  = explode( '/', $pattern );
		$last   = (string) end( $parts );
		$suffix = '-' . $language;
		if ( '' !== $language && str_ends_with( $last, $suffix ) ) {
			return substr( $last, 0, -strlen( $suffix ) );
		}
		return $last;
	}
}
