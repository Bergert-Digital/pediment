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

	public function __construct( private MediaMap $media ) {}

	/**
	 * @throws ManifestError When the entry declares a pattern that is not registered.
	 */
	public function resolve( EntrySpec $entry ): string {
		$content = $entry->content;

		if ( null === $content ) {
			$pattern = \WP_Block_Patterns_Registry::get_instance()->get_registered( (string) $entry->pattern );
			if ( ! is_array( $pattern ) || ! isset( $pattern['content'] ) ) {
				throw new ManifestError( "{$entry->key}: pattern '{$entry->pattern}' is not registered. Patterns register on `init`; check the slug and that the file lives in the theme's patterns/ directory." ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- exception message for the operator, not echoed output.
			}
			$content = (string) $pattern['content'];
		}

		return $this->rewriteMedia( $content );
	}

	/** @return string[] */
	public function unresolvedMediaKeys(): array {
		return array_values( $this->unresolved );
	}

	/**
	 * @throws ManifestError When the PCRE engine itself fails while rewriting placeholders.
	 */
	private function rewriteMedia( string $content ): string {
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
