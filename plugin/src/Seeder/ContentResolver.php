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

	/**
	 * @param string $rawLoader Name of the registry's private raw-content loader
	 *                          (see rawContent()). A parameter only so a test can
	 *                          stand in for a core that no longer has it.
	 */
	public function __construct( private MediaMap $media, private string $rawLoader = 'get_content' ) {}

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

			// Resolve first, then run Block Hooks on valid markup — the order
			// get_registered() cannot give us (see rawContent()). The context is
			// the one core builds: the entry with its raw content, without
			// `filePath`.
			$raw     = $this->rawContent( $registry, $pattern, $entry->key );
			$context = [ 'content' => $raw ] + $pattern;
			unset( $context['filePath'] );

			return apply_block_hooks_to_content(
				$this->rewriteMarkup( $raw ),
				$context,
				'insert_hooked_blocks_and_set_ignored_hooked_blocks_metadata'
			);
		}

		return $this->rewriteMarkup( $content );
	}

	/**
	 * A registered pattern's markup as its author wrote it, before Block Hooks.
	 *
	 * Core's get_registered() and get_all_registered() (WP 6.4+) pass the content
	 * through apply_block_hooks_to_content(), which always re-parses and
	 * re-serializes it. A placeholder inside block JSON —
	 * `{"id":{{media_id:hero}}}`, the form `wp pediment adopt` writes — is not
	 * valid JSON until it is resolved, so the parser drops every attribute of
	 * that block. The registry's own loader, get_content(), returns the raw
	 * markup (it includes a `filePath` pattern file the way core does) but is
	 * private; it has the same signature from WP 6.5 to trunk (7.2), and its
	 * body gained a `filePath` check in 7.0.
	 *
	 * There is no fallback. The hooked content is exactly the damaged markup
	 * this method exists to avoid, and production cores update on their own,
	 * so a core without the loader stops the seed with an error instead of
	 * seeding pages whose blocks lost their attributes.
	 *
	 * @param array<string,mixed> $pattern The get_registered() entry.
	 * @param string              $key     The manifest entry, for the error.
	 *
	 * @throws ManifestError When this WordPress version has no usable raw loader.
	 */
	private function rawContent( \WP_Block_Patterns_Registry $registry, array $pattern, string $key ): string {
		$name = (string) ( $pattern['name'] ?? '' );
		try {
			$raw = method_exists( $registry, $this->rawLoader )
				? ( new \ReflectionMethod( $registry, $this->rawLoader ) )->invoke( $registry, $name )
				: null;
		} catch ( \Throwable $e ) {
			$raw = null; // A changed signature is as unusable as a missing method.
		}

		if ( ! is_string( $raw ) ) {
			throw new ManifestError( "{$key}: pattern '{$name}' — Pediment cannot read the raw pattern markup on this WordPress version (WP_Block_Patterns_Registry::{$this->rawLoader}() is missing or changed). Seeding it would strip block attributes, so nothing was written; update Pediment." ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- exception message for the operator, not echoed output.
		}

		return $raw;
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
