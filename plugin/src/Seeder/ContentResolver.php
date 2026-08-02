<?php
/**
 * Turns an EntrySpec into the block markup the seeder intends to write.
 *
 * Content is sourced from registered patterns rather than hand-copied markup,
 * so seeded pages can never drift from the patterns the product ships.
 *
 * @package Pediment
 */

declare(strict_types=1);

namespace Pediment\Seeder;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class ContentResolver {
	private const SENTINEL = 'PEDIMENT_SEED_MEDIA_URL:';

	/** @var array<string,string> Media keys the last resolve() call could not resolve. */
	private array $unresolved = [];

	/** @var array<string,string> "key|language" => pattern slug that is not registered. */
	private array $missingPatterns = [];

	public function __construct( private MediaMap $media ) {}

	/**
	 * @param string $language The language being resolved ('' when monolingual).
	 * @param string $default  The site's default language code.
	 *
	 * @throws ManifestError When the entry's DEFAULT-language pattern is not registered.
	 */
	public function resolve( EntrySpec $entry, string $language = '', string $default = '' ): string {
		$content = $entry->content;

		if ( null === $content ) {
			$registry = \WP_Block_Patterns_Registry::get_instance();
			$wanted   = (string) $entry->patternFor( $language, $default );
			$pattern  = $registry->get_registered( $wanted );

			// A language with no translated pattern is a normal state on a site
			// that just added one — it renders the default language's content and
			// says so, rather than seeding a blank page or blocking the run. Gated
			// on the LANGUAGE, not on whether $wanted differs from $entry->pattern:
			// patternFor() checks a per-language override before it checks
			// default-ness, so a default-language override that is not registered
			// must still throw below, not soft-fallback into a false negative.
			if ( ( ! is_array( $pattern ) || ! isset( $pattern['content'] ) ) && $language !== $default ) {
				$this->missingPatterns[ $entry->key . '|' . $language ] = $wanted;
				$pattern                                                = $registry->get_registered( (string) $entry->pattern );
			}

			if ( ! is_array( $pattern ) || ! isset( $pattern['content'] ) ) {
				throw new ManifestError( "{$entry->key}: pattern '{$entry->pattern}' is not registered. Patterns register on `init`; check the slug and that the file lives in the theme's patterns/ directory." ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- exception message for the operator, not echoed output.
			}
			$content = (string) $pattern['content'];
		}

		return $this->rewriteMarkup( $content );
	}

	/** @return string[] */
	public function unresolvedMediaKeys(): array {
		return array_values( $this->unresolved );
	}

	/**
	 * Clear the accumulated missing-pattern report.
	 *
	 * The missingPatterns() report accumulates across every resolve() call
	 * because DesiredState reports it once for the whole build() — but that means a
	 * second build() on the SAME DesiredState/ContentResolver pair (not
	 * reachable from Runner today, which always constructs a fresh
	 * ContentResolver) would double-report every language whose pattern was
	 * already missing on the first pass. DesiredState::build() resets
	 * $missingTitles the same way at the start of every call; this keeps
	 * ContentResolver consistent with that.
	 */
	public function resetMissingPatterns(): void {
		$this->missingPatterns = [];
	}

	/**
	 * Patterns a language wanted but does not have.
	 *
	 * Accumulates across resolve() calls (unlike unresolvedMediaKeys(), which is
	 * per-call) because DesiredState reports these once for the whole build.
	 *
	 * @return array<string,string> "key|language" => pattern slug
	 */
	public function missingPatterns(): array {
		return $this->missingPatterns;
	}

	/**
	 * Expand `{{media_url:…}}` / `{{media_id:…}}` in arbitrary markup.
	 *
	 * Public because `Adopter` has to hash the same shape the seeder will: the
	 * bytes it writes back into the pattern file still carry placeholders, and a
	 * source hash taken from those can never match what `DesiredState` computes.
	 *
	 * @throws ManifestError When the PCRE engine itself fails while rewriting placeholders.
	 */
	public function rewriteMarkup( string $content ): string {
		$this->unresolved = [];

		$rewritten = preg_replace_callback(
			'/\{\{media_(url|id):([a-z0-9_\-\/]+)\}\}/i',
			function ( array $m ): string {
				$key = $m[2];
				if ( ! $this->media->has( $key ) ) {
					// An unseeded id resolves to a bare 0, which no amount of
					// scanning the output can tell from a real one — so record it.
					$this->unresolved[ $key ] = $key;
				}
				if ( 'id' === strtolower( $m[1] ) ) {
					return (string) $this->media->id( $key );
				}
				return $this->media->has( $key ) ? $this->media->url( $key ) : self::SENTINEL . $key;
			},
			$content
		);

		if ( null === $rewritten ) {
			// preg_replace_callback returns null on a PCRE failure. Falling back
			// to '' here would seed an empty page — the exact failure this engine exists to prevent.
			throw new ManifestError( 'Media placeholder rewriting failed (PCRE error: ' . preg_last_error_msg() . ').' ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- exception message for the operator, not echoed output.
		}

		return $rewritten;
	}
}
