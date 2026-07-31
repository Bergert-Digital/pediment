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

	public function hasUnresolvedMedia( string $content ): bool {
		return str_contains( $content, self::SENTINEL );
	}

	private function rewriteMedia( string $content ): string {
		return (string) preg_replace_callback(
			'/\{\{media_(url|id):([a-z0-9_\-\/]+)\}\}/i',
			function ( array $m ): string {
				$key = $m[2];
				if ( 'id' === strtolower( $m[1] ) ) {
					return (string) $this->media->id( $key );
				}
				return $this->media->has( $key ) ? $this->media->url( $key ) : self::SENTINEL . $key;
			},
			$content
		);
	}
}
