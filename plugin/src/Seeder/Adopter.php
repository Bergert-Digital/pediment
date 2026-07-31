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

	/** @return array{path:string,bytes:int,written:bool,errors:string[]} */
	public function adopt( string $seedKey, string $language = '', bool $dryRun = false ): array {
		$empty = [ 'path' => '', 'bytes' => 0, 'written' => false, 'errors' => [] ];

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
		$contents  = $this->render( $spec, (string) $post->post_content );

		if ( $dryRun ) {
			return [ 'path' => $file, 'bytes' => strlen( $contents ), 'written' => false, 'errors' => [] ];
		}

		if ( ! wp_mkdir_p( dirname( $file ) ) ) {
			return array_merge( $empty, [ 'errors' => [ sprintf( 'Cannot create %s.', dirname( $file ) ) ] ] );
		}
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- developer-side export, runs on a dev machine.
		if ( false === file_put_contents( $file, $contents ) ) {
			return array_merge( $empty, [ 'errors' => [ sprintf( 'Cannot write %s.', $file ) ] ] );
		}

		// The live row is now the source of truth in git too, so it is no longer
		// "edited" — the next seed will treat it as up to date.
		update_post_meta( $actual->id, Meta::HASH, ContentHash::forPost( $actual->id ) );
		update_post_meta( $actual->id, Meta::SOURCE, ContentHash::compute( (string) $post->post_title, (string) $post->post_content ) );

		return [ 'path' => $file, 'bytes' => strlen( $contents ), 'written' => true, 'errors' => [] ];
	}

	private function render( EntrySpec $spec, string $markup ): string {
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
