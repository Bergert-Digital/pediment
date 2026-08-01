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
		$declared              = array_keys( $manifest->media() );

		foreach ( $this->lang->languages() as $language ) {
			foreach ( $manifest->entriesInDependencyOrder() as $spec ) {
				// Step 4 gives per-language patterns (patterns/<slug>.<lang>.php);
				// with the NullProvider there is exactly one language and one source.
				$content = $this->resolver->resolve( $spec );
				$entry   = new DesiredEntry(
					$spec->key,
					$language,
					$spec->postType,
					$spec->title,
					$spec->slug,
					$spec->parent,
					$content,
					$spec->frontPage,
					$spec->postsPage,
					$spec->menuOrder,
					$spec->terms,
					ContentHash::compute( $spec->title, $content )
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
}
