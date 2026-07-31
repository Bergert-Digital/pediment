<?php
/**
 * The inverse of seeding: export a live entry's markup back into the theme's
 * pattern file and clear the "client edited this" state.
 *
 * @package Pediment
 */

declare(strict_types=1);

namespace Pediment\Seeder;

use Pediment\Language\LanguageProvider;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Adopter {
	public function __construct( private LanguageProvider $lang ) {}

	/** @return array{path:string,bytes:int,written:bool,backup:string,errors:string[]} */
	public function adopt( string $seedKey, string $language = '', bool $dryRun = false ): array {
		$empty = [ 'path' => '', 'bytes' => 0, 'written' => false, 'backup' => '', 'errors' => [] ];

		// `init` already populated the per-request memo (PostTypes reads it on
		// every request), and an operator who just edited the manifest expects
		// this run to see the file as it is now.
		Manifest::resetCache();

		try {
			$manifest = Manifest::load();
		} catch ( ManifestError $e ) {
			return array_merge( $empty, [ 'errors' => [ $e->getMessage() ] ] );
		}
		if ( null === $manifest ) {
			return array_merge( $empty, [ 'errors' => [ 'No seed manifest found in the active theme.' ] ] );
		}

		$spec = $manifest->entries()[ $seedKey ] ?? null;
		if ( ! $spec instanceof EntrySpec ) {
			return array_merge( $empty, [ 'errors' => [ sprintf( 'Seed key "%s" is not declared in the manifest.', $seedKey ) ] ] );
		}
		if ( null === $spec->pattern ) {
			return array_merge(
				$empty,
				[ 'errors' => [ sprintf( '"%s" is declared with literal content; give it a `pattern` to adopt into.', $seedKey ) ] ]
			);
		}

		$actual = ( new StateReader( $this->lang ) )->read()[ $seedKey . '|' . $language ] ?? null;
		if ( ! $actual instanceof ActualEntry ) {
			return array_merge( $empty, [ 'errors' => [ sprintf( 'No seeded post carries the key "%s".', $seedKey ) ] ] );
		}

		$post = get_post( $actual->id );
		if ( ! $post instanceof \WP_Post ) {
			return array_merge( $empty, [ 'errors' => [ sprintf( 'Post %d disappeared.', $actual->id ) ] ] );
		}

		$slugParts = explode( '/', $spec->pattern );
		$file      = untrailingslashit( $manifest->baseDir() ) . '/patterns/' . end( $slugParts ) . '.php';
		$markup    = $this->restoreMediaPlaceholders( $manifest, (string) $post->post_content );
		$contents  = $this->render( $spec, $markup, $file );

		if ( $dryRun ) {
			return [ 'path' => $file, 'bytes' => strlen( $contents ), 'written' => false, 'backup' => '', 'errors' => [] ];
		}

		if ( ! wp_mkdir_p( dirname( $file ) ) ) {
			return array_merge( $empty, [ 'errors' => [ sprintf( 'Cannot create %s.', dirname( $file ) ) ] ] );
		}

		// Overwriting a pattern file a developer is mid-edit would destroy work
		// git may not have yet, so keep a sibling copy when the contents differ.
		$backup = '';
		if ( is_readable( $file ) && (string) file_get_contents( $file ) !== $contents ) { // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- developer-side export.
			$backup = $file . '.bak';
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_copy -- developer-side export.
			copy( $file, $backup );
		}

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- developer-side export, runs on a dev machine.
		if ( false === file_put_contents( $file, $contents ) ) {
			return array_merge( $empty, [ 'errors' => [ sprintf( 'Cannot write %s.', $file ) ] ] );
		}

		// A brand-new pattern file is invisible until the theme's pattern header
		// cache expires (30 minutes), which is exactly the first-adopt case.
		wp_get_theme()->delete_pattern_cache();

		// Read the file back the way the pattern registry will, and hash THAT as
		// the source. Hashing the database row instead leaves the two a newline
		// apart, and the very next seed rewrites the page adopt just blessed.
		$resolved = $this->resolveWritten( $file );
		$header   = get_file_data( $file, [ 'slug' => 'Slug' ] );
		if ( $header['slug'] !== $spec->pattern ) {
			$this->rollback( $file, $backup );
			return array_merge(
				$empty,
				[ 'errors' => [ sprintf( '%s: wrote %s but its Slug header reads "%s", not "%s" — the next seed would not find it, so the write was rolled back.', $seedKey, $file, $header['slug'], (string) $spec->pattern ) ] ]
			);
		}

		// The live row is now the source of truth in git too, so it is no longer
		// "edited" — the next seed will treat it as up to date.
		update_post_meta( $actual->id, Meta::HASH, ContentHash::forPost( $actual->id ) );
		update_post_meta( $actual->id, Meta::SOURCE, ContentHash::compute( (string) $post->post_title, $resolved ) );

		return [ 'path' => $file, 'bytes' => strlen( $contents ), 'written' => true, 'backup' => $backup, 'errors' => [] ];
	}

	/** The bytes the pattern registry will see, produced the way it produces them. */
	private function resolveWritten( string $file ): string {
		ob_start();
		include $file;
		return (string) ob_get_clean();
	}

	/** Put the file back the way it was when a post-write check fails. */
	private function rollback( string $file, string $backup ): void {
		if ( '' !== $backup && is_readable( $backup ) ) {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_copy -- developer-side export.
			copy( $backup, $file );
			wp_delete_file( $backup );
			return;
		}
		wp_delete_file( $file );
	}

	/**
	 * Turn concrete media references back into manifest placeholders.
	 *
	 * Without this, adopting a page with images commits environment-specific
	 * URLs to git, and the same pattern seeded onto a fresh install points at
	 * attachments that do not exist there. Sized variants (`-300x200.jpg`) and
	 * srcset entries are NOT mapped back — documented in docs/seeding.md.
	 */
	private function restoreMediaPlaceholders( Manifest $manifest, string $markup ): string {
		$map = ( new MediaSeeder() )->map( $manifest );

		foreach ( array_keys( $manifest->media() ) as $key ) {
			$id = $map->id( $key );
			if ( $id <= 0 ) {
				continue;
			}
			$url = $map->url( $key );
			if ( '' !== $url ) {
				$markup = str_replace( $url, '{{media_url:' . $key . '}}', $markup );
			}
			// Anchored on a non-digit: a bare str_replace of `"id":4` would also
			// hit `"id":41` and corrupt an unrelated block's attributes.
			$markup = (string) preg_replace( '/"id":' . $id . '(?![0-9])/', '"id":{{media_id:' . $key . '}}', $markup );
		}

		return $markup;
	}

	private function render( EntrySpec $spec, string $markup, string $existing = '' ): string {
		// Keep whatever header the file already had — the shipped patterns carry
		// Description, Keywords, Viewport Width and a phpcs:ignoreFile line that
		// a regenerated header would silently drop.
		if ( '' !== $existing && is_readable( $existing ) ) {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- developer-side export.
			$current = (string) file_get_contents( $existing );
			$end     = strpos( $current, '?>' );
			if ( false !== $end ) {
				return substr( $current, 0, $end + 2 ) . "\n" . $markup . "\n";
			}
		}

		return "<?php\n"
			. "/**\n"
			. ' * Title: ' . $spec->title . "\n"
			. ' * Slug: ' . $spec->pattern . "\n"
			. " * Categories: pediment\n"
			. " * Inserter: no\n"
			. " *\n"
			. " * Adopted from the live site by `wp pediment adopt`. Edit here, then re-seed.\n"
			. " *\n"
			. " * @package Pediment\n"
			. " */\n"
			. "\n"
			. "?>\n"
			. $markup . "\n";
	}
}
