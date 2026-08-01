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

	/**
	 * Top-level sections a manifest may declare.
	 *
	 * Silently ignoring the rest is a bad failure mode at migration scale:
	 * `page` instead of `pages` seeds nothing and reports nothing.
	 */
	private const SECTIONS = [ 'version', 'languages', 'pages', 'posts', 'entries', 'media', 'navs', 'post_types', 'site' ];

	/** Keys an entry may declare. Same reasoning: `front-page` must not be a no-op. */
	private const ENTRY_KEYS = [ 'title', 'slug', 'parent', 'pattern', 'content', 'post_type', 'front_page', 'posts_page', 'menu_order', 'terms', 'languages' ];

	/** Keys a language may declare. */
	private const LANGUAGE_KEYS = [ 'name', 'locale', 'flag', 'default' ];

	/** Keys a per-language entry override may declare. */
	private const TRANSLATION_KEYS = [ 'title', 'slug', 'pattern' ];

	/** @var self|null */
	private static ?self $cache = null;

	/** @var bool */
	private static bool $loaded = false;

	/**
	 * @param array<string,EntrySpec>    $entries
	 * @param array<string,MediaSpec>    $media
	 * @param array<string,NavSpec>      $navs
	 * @param array<string,PostTypeSpec> $postTypes
	 * @param array<string,LanguageSpec> $languages
	 */
	private function __construct(
		private string $path,
		private string $baseDir,
		private array $entries,
		private array $media,
		private array $navs,
		private array $postTypes,
		private string $siteLogo,
		private array $languages,
		private string $defaultLanguage
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

		foreach ( array_keys( $raw ) as $section ) {
			if ( ! in_array( (string) $section, self::SECTIONS, true ) ) {
				throw new ManifestError( "Unknown manifest section '{$section}'. Allowed: " . implode( ', ', self::SECTIONS ) . '.' ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- exception message for the operator, not echoed output.
			}
		}

		[ $languages, $defaultLanguage ] = self::parseLanguages( (array) ( $raw['languages'] ?? [] ) );

		$entries = [];
		foreach ( [ 'pages' => 'page', 'posts' => 'post', 'entries' => '' ] as $section => $defaultType ) {
			foreach ( (array) ( $raw[ $section ] ?? [] ) as $key => $declared ) {
				$key = (string) $key;
				if ( isset( $entries[ $key ] ) ) {
					throw new ManifestError( "Duplicate seed key '{$key}' (declared more than once across pages/posts/entries)." ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- exception message for the operator, not echoed output.
				}
				$entries[ $key ] = self::entry( $section, $key, (array) $declared, $defaultType, array_keys( $languages ) );
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

		return new self( $path, $baseDir, $entries, $media, $navs, $postTypes, $siteLogo, $languages, $defaultLanguage );
	}

	/**
	 * @param array<string,mixed> $raw
	 * @return array{0:array<string,LanguageSpec>,1:string}
	 * @throws ManifestError When the languages fail validation.
	 */
	private static function parseLanguages( array $raw ): array {
		if ( [] === $raw ) {
			return [ [], '' ];
		}

		$specs    = [];
		$defaults = [];

		foreach ( $raw as $slug => $declared ) {
			$slug     = (string) $slug;
			$declared = (array) $declared;

			foreach ( array_keys( $declared ) as $key ) {
				if ( ! in_array( (string) $key, self::LANGUAGE_KEYS, true ) ) {
					throw new ManifestError( "languages.{$slug}: unknown key '{$key}'. Allowed: " . implode( ', ', self::LANGUAGE_KEYS ) . '.' ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- operator-facing message.
				}
			}

			// Polylang builds URL prefixes straight from this, and it is also the
			// suffix a derived per-language slug carries. Anything sanitize_title()
			// would rewrite produces URLs nobody declared.
			if ( '' === $slug || sanitize_title( $slug ) !== $slug ) {
				throw new ManifestError( "languages.{$slug}: '{$slug}' is not a valid language code — use a lowercase slug such as 'en' or 'pt-br'." ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- operator-facing message.
			}

			$locale = (string) ( $declared['locale'] ?? '' );
			if ( '' === $locale ) {
				throw new ManifestError( "languages.{$slug}: 'locale' is required (for example 'de_DE')." ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- operator-facing message.
			}

			$isDefault = ! empty( $declared['default'] );
			if ( $isDefault ) {
				$defaults[] = $slug;
			}

			$specs[ $slug ] = new LanguageSpec(
				$slug,
				(string) ( $declared['name'] ?? strtoupper( $slug ) ),
				$locale,
				(string) ( $declared['flag'] ?? '' ),
				$isDefault
			);
		}

		if ( count( $defaults ) > 1 ) {
			throw new ManifestError( 'Only one language may be the default; got: ' . implode( ', ', $defaults ) . '.' ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- operator-facing message.
		}

		$default = $defaults[0] ?? (string) array_key_first( $specs );

		// Default first. Everything downstream depends on this order: the
		// Applier resolves a child's post_parent and the front-page option from
		// the default language's IDs, and translation groups are keyed off it.
		$ordered = [ $default => $specs[ $default ] ];
		foreach ( $specs as $slug => $spec ) {
			if ( $slug !== $default ) {
				$ordered[ $slug ] = $spec;
			}
		}

		// Re-stamp isDefault so a manifest that declared none still has exactly
		// one language reporting itself as the default.
		$ordered[ $default ] = new LanguageSpec(
			$ordered[ $default ]->slug,
			$ordered[ $default ]->name,
			$ordered[ $default ]->locale,
			$ordered[ $default ]->flag,
			true
		);

		return [ $ordered, $default ];
	}

	/**
	 * @param array<string,mixed> $declared
	 * @param string[]            $declaredLanguages
	 * @throws ManifestError When the entry fails validation.
	 */
	private static function entry( string $section, string $key, array $declared, string $defaultType, array $declaredLanguages = [] ): EntrySpec {
		foreach ( array_keys( $declared ) as $declaredKey ) {
			if ( ! in_array( (string) $declaredKey, self::ENTRY_KEYS, true ) ) {
				throw new ManifestError( "{$section}.{$key}: unknown key '{$declaredKey}'. Allowed: " . implode( ', ', self::ENTRY_KEYS ) . '.' ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- exception message for the operator, not echoed output.
			}
		}

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
		if ( '' === $slug ) {
			// sanitize_title('') === '' passes the round-trip below, WordPress
			// then invents a slug from the title, and the Verifier reports a
			// mismatch on every run forever with no fix that converges.
			throw new ManifestError( "{$section}.{$key}: the slug is empty — a key may not end in '/', and 'slug' may not be blank." ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- exception message for the operator, not echoed output.
		}
		if ( sanitize_title( $slug ) !== $slug ) {
			throw new ManifestError( "{$section}.{$key}: slug '{$slug}' is not a valid post slug." ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- exception message for the operator, not echoed output.
		}

		$terms = [];
		foreach ( (array) ( $declared['terms'] ?? [] ) as $taxonomy => $slugs ) {
			$terms[ (string) $taxonomy ] = array_values( array_map( 'strval', (array) $slugs ) );
		}

		$translations = [];
		foreach ( (array) ( $declared['languages'] ?? [] ) as $language => $override ) {
			$language = (string) $language;
			$override = (array) $override;

			if ( ! in_array( $language, $declaredLanguages, true ) ) {
				throw new ManifestError( "{$section}.{$key}.languages.{$language}: '{$language}' is not a declared site language. Add it to the manifest's `languages` section first." ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- exception message for the operator, not echoed output.
			}

			foreach ( array_keys( $override ) as $overrideKey ) {
				if ( ! in_array( (string) $overrideKey, self::TRANSLATION_KEYS, true ) ) {
					throw new ManifestError( "{$section}.{$key}.languages.{$language}: unknown key '{$overrideKey}'. Allowed: " . implode( ', ', self::TRANSLATION_KEYS ) . '.' ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- exception message for the operator, not echoed output.
				}
			}

			if ( isset( $override['slug'] ) ) {
				$overrideSlug = (string) $override['slug'];
				if ( '' === $overrideSlug || sanitize_title( $overrideSlug ) !== $overrideSlug ) {
					throw new ManifestError( "{$section}.{$key}.languages.{$language}: slug '{$overrideSlug}' is not a valid post slug." ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- exception message for the operator, not echoed output.
				}
			}

			$translations[ $language ] = array_intersect_key( $override, array_flip( self::TRANSLATION_KEYS ) );
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
			$terms,
			$translations
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

	/** @return array<string,LanguageSpec> Declared site languages, default first; empty when monolingual. */
	public function languages(): array {
		return $this->languages;
	}

	/** The declared default language code, or '' when the manifest declares none. */
	public function defaultLanguage(): string {
		return $this->defaultLanguage;
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
