<?php
/**
 * Loads and validates a client theme's seed manifest.
 *
 * The manifest declares STRUCTURE (which entries exist, where they sit, what
 * they are called); pattern files supply CONTENT. Validation is strict and
 * fails before anything is written — a manifest error must never become a
 * half-seeded database.
 *
 * @package Pediment
 */

declare(strict_types=1);

namespace Pediment\Seeder;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Manifest {
	public const RELATIVE_PATH = 'seed/manifest.php';

	/** @var self|null */
	private static ?self $cache = null;

	/** @var bool */
	private static bool $loaded = false;

	/**
	 * @param array<string,EntrySpec>    $entries
	 * @param array<string,MediaSpec>    $media
	 * @param array<string,NavSpec>      $navs
	 * @param array<string,PostTypeSpec> $postTypes
	 */
	private function __construct(
		private string $path,
		private string $baseDir,
		private array $entries,
		private array $media,
		private array $navs,
		private array $postTypes,
		private string $siteLogo
	) {}

	/**
	 * Load and validate the active theme's manifest.
	 *
	 * Memoized per request: `PostTypes` calls this on every `init`, and without
	 * the memo each page load would re-read the file, re-validate every entry,
	 * and stat every declared media file. Seed runs and tests call
	 * `resetCache()` first so they always see the file as it is now.
	 *
	 * @return self|null
	 *
	 * @throws ManifestError When the manifest fails validation.
	 */
	public static function load(): ?self {
		if ( self::$loaded ) {
			return self::$cache;
		}

		$baseDir = untrailingslashit( get_stylesheet_directory() );
		$path    = $baseDir . '/' . self::RELATIVE_PATH;
		$raw     = is_readable( $path ) ? include $path : null;

		/**
		 * Filter the raw seed manifest array.
		 *
		 * @param array|null $raw     Manifest array, or null when the theme ships none.
		 * @param string     $baseDir Stylesheet directory.
		 */
		$raw = apply_filters( 'pediment_seed_manifest', $raw, $baseDir );

		if ( ! is_array( $raw ) ) {
			self::$cache  = null;
			self::$loaded = true;
			return null;
		}

		// fromArray() throws on an invalid manifest, and the memo is only set
		// after it returns — an error is never cached, so fixing the file does
		// not require a new request.
		$manifest = self::fromArray( $raw, $baseDir, is_readable( $path ) ? $path : 'pediment_seed_manifest filter' );

		self::$cache  = $manifest;
		self::$loaded = true;

		return $manifest;
	}

	/** Drop the per-request memo. Call before any read that must see current state. */
	public static function resetCache(): void {
		self::$cache  = null;
		self::$loaded = false;
	}

	/**
	 * @param array<string,mixed> $raw
	 * @throws ManifestError When the manifest fails validation.
	 */
	public static function fromArray( array $raw, string $baseDir, string $path = '(array)' ): self {
		$baseDir = untrailingslashit( $baseDir );

		$entries = [];
		foreach ( [ 'pages' => 'page', 'posts' => 'post', 'entries' => '' ] as $section => $defaultType ) {
			foreach ( (array) ( $raw[ $section ] ?? [] ) as $key => $declared ) {
				$key = (string) $key;
				if ( isset( $entries[ $key ] ) ) {
					throw new ManifestError( "Duplicate seed key '{$key}' (declared more than once across pages/posts/entries)." ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- exception message for the operator, not echoed output.
				}
				$entries[ $key ] = self::entry( $section, $key, (array) $declared, $defaultType );
			}
		}

		self::validateRelations( $entries );

		$media = [];
		foreach ( (array) ( $raw['media'] ?? [] ) as $key => $declared ) {
			$key      = (string) $key;
			$declared = (array) $declared;
			$file     = (string) ( $declared['file'] ?? '' );
			if ( '' === $file ) {
				throw new ManifestError( "media.{$key}: 'file' is required." ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- exception message for the operator, not echoed output.
			}
			$absolute = path_is_absolute( $file ) ? $file : $baseDir . '/' . ltrim( $file, '/' );
			if ( ! is_readable( $absolute ) ) {
				throw new ManifestError( "media.{$key}: file not found — {$absolute}" ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- exception message for the operator, not echoed output.
			}
			$media[ $key ] = new MediaSpec( $key, $absolute, (string) ( $declared['title'] ?? $key ) );
		}

		$navs = [];
		foreach ( (array) ( $raw['navs'] ?? [] ) as $key => $declared ) {
			$key      = (string) $key;
			$declared = (array) $declared;
			$items    = [];
			foreach ( (array) ( $declared['items'] ?? [] ) as $index => $item ) {
				$item = (array) $item;
				if ( isset( $item['entry'] ) ) {
					$target = (string) $item['entry'];
					if ( ! isset( $entries[ $target ] ) ) {
						throw new ManifestError( "navs.{$key}.items.{$index}: unknown entry '{$target}'." ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- exception message for the operator, not echoed output.
					}
				} elseif ( ! isset( $item['url'], $item['label'] ) ) {
					throw new ManifestError( "navs.{$key}.items.{$index}: needs either 'entry' or both 'url' and 'label'." ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- exception message for the operator, not echoed output.
				}
				$items[] = $item;
			}
			$navs[ $key ] = new NavSpec( $key, (string) ( $declared['title'] ?? ucfirst( $key ) ), $items );
		}

		$postTypes = [];
		foreach ( (array) ( $raw['post_types'] ?? [] ) as $slug => $declared ) {
			$slug               = (string) $slug;
			$postTypes[ $slug ] = new PostTypeSpec(
				$slug,
				array_merge(
					[
						'public'       => true,
						'show_in_rest' => true,
						'has_archive'  => false,
						'supports'     => [ 'title', 'editor', 'excerpt', 'thumbnail', 'custom-fields' ],
						'label'        => ucfirst( $slug ),
					],
					(array) $declared
				)
			);
		}

		$siteLogo = (string) ( $raw['site']['logo'] ?? '' );
		if ( '' !== $siteLogo && ! isset( $media[ $siteLogo ] ) ) {
			throw new ManifestError( "site.logo: '{$siteLogo}' is not a declared media key." ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- exception message for the operator, not echoed output.
		}

		return new self( $path, $baseDir, $entries, $media, $navs, $postTypes, $siteLogo );
	}

	/**
	 * @param array<string,mixed> $declared
	 * @throws ManifestError When the entry fails validation.
	 */
	private static function entry( string $section, string $key, array $declared, string $defaultType ): EntrySpec {
		$title = (string) ( $declared['title'] ?? '' );
		if ( '' === $title ) {
			throw new ManifestError( "{$section}.{$key}: 'title' is required." ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- exception message for the operator, not echoed output.
		}

		$postType = (string) ( $declared['post_type'] ?? $defaultType );
		if ( '' === $postType ) {
			throw new ManifestError( "{$section}.{$key}: 'post_type' is required for entries." ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- exception message for the operator, not echoed output.
		}

		$hasPattern = isset( $declared['pattern'] );
		$hasContent = array_key_exists( 'content', $declared );
		if ( $hasPattern && $hasContent ) {
			throw new ManifestError( "{$section}.{$key}: declare either 'pattern' or 'content', not both." ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- exception message for the operator, not echoed output.
		}
		if ( ! $hasPattern && ! $hasContent ) {
			throw new ManifestError( "{$section}.{$key}: declare either 'pattern' or 'content'." ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- exception message for the operator, not echoed output.
		}

		$segments = explode( '/', $key );
		$slug     = (string) ( $declared['slug'] ?? end( $segments ) );
		if ( sanitize_title( $slug ) !== $slug ) {
			throw new ManifestError( "{$section}.{$key}: slug '{$slug}' is not a valid post slug." ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- exception message for the operator, not echoed output.
		}

		$terms = [];
		foreach ( (array) ( $declared['terms'] ?? [] ) as $taxonomy => $slugs ) {
			$terms[ (string) $taxonomy ] = array_values( array_map( 'strval', (array) $slugs ) );
		}

		return new EntrySpec(
			$key,
			$postType,
			$title,
			$slug,
			isset( $declared['parent'] ) ? (string) $declared['parent'] : null,
			$hasPattern ? (string) $declared['pattern'] : null,
			$hasContent ? (string) $declared['content'] : null,
			! empty( $declared['front_page'] ),
			! empty( $declared['posts_page'] ),
			(int) ( $declared['menu_order'] ?? 0 ),
			$terms
		);
	}

	/**
	 * @param array<string,EntrySpec> $entries
	 * @throws ManifestError When the entries' relations fail validation.
	 */
	private static function validateRelations( array $entries ): void {
		$fronts = [];
		$posts  = [];
		foreach ( $entries as $key => $entry ) {
			if ( $entry->frontPage ) {
				$fronts[] = $key;
			}
			if ( $entry->postsPage ) {
				$posts[] = $key;
			}
			if ( null === $entry->parent ) {
				continue;
			}
			if ( ! isset( $entries[ $entry->parent ] ) ) {
				throw new ManifestError( "{$key}: parent '{$entry->parent}' is not declared." ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- exception message for the operator, not echoed output.
			}

			$seen   = [ $key => true ];
			$cursor = $entry->parent;
			while ( null !== $cursor ) {
				if ( isset( $seen[ $cursor ] ) ) {
					throw new ManifestError( "Parent cycle detected at '{$key}'." ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- exception message for the operator, not echoed output.
				}
				$seen[ $cursor ] = true;
				$cursor          = $entries[ $cursor ]->parent ?? null;
			}
		}

		if ( count( $fronts ) > 1 ) {
			throw new ManifestError( 'Only one entry may set front_page; got: ' . implode( ', ', $fronts ) . '.' ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- exception message for the operator, not echoed output.
		}
		if ( count( $posts ) > 1 ) {
			throw new ManifestError( 'Only one entry may set posts_page; got: ' . implode( ', ', $posts ) . '.' ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- exception message for the operator, not echoed output.
		}
	}

	public function path(): string {
		return $this->path;
	}

	public function baseDir(): string {
		return $this->baseDir;
	}

	/** @return array<string,EntrySpec> */
	public function entries(): array {
		return $this->entries;
	}

	/** @return array<string,MediaSpec> */
	public function media(): array {
		return $this->media;
	}

	/** @return array<string,NavSpec> */
	public function navs(): array {
		return $this->navs;
	}

	/** @return array<string,PostTypeSpec> */
	public function postTypes(): array {
		return $this->postTypes;
	}

	public function siteLogo(): string {
		return $this->siteLogo;
	}

	/**
	 * Parents before children, so the applier always knows a parent's post ID
	 * by the time it writes the child.
	 *
	 * @return EntrySpec[]
	 */
	public function entriesInDependencyOrder(): array {
		$ordered = $this->entries;
		uasort(
			$ordered,
			fn( EntrySpec $a, EntrySpec $b ): int => $this->depth( $a ) <=> $this->depth( $b )
		);
		return array_values( $ordered );
	}

	private function depth( EntrySpec $entry ): int {
		$depth  = 0;
		$cursor = $entry->parent;
		while ( null !== $cursor && isset( $this->entries[ $cursor ] ) ) {
			++$depth;
			$cursor = $this->entries[ $cursor ]->parent;
		}
		return $depth;
	}
}
