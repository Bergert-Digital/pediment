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
	public function __construct(
		private LanguageProvider $lang,
		private ContentResolver $resolver
	) {}

	/** @return array<string,DesiredEntry> */
	public function build( Manifest $manifest ): array {
		$desired = [];

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
			}
		}

		return $desired;
	}
}
