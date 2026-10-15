<?php
/**
 * WP-CLI: `wp pediment convert`.
 *
 * @package Pediment
 */

declare(strict_types=1);

namespace Pediment\Cli;

use Pediment\Content\Policy;
use Pediment\Convert\AttachmentMedia;
use Pediment\Convert\Converter;
use Pediment\Convert\PatternFiles;
use Pediment\Convert\PostConverter;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Converts legacy Pediment blocks to core blocks with named styles.
 */
final class ConvertCommand {

	/**
	 * Converts legacy blocks in stored posts, or in a theme's pattern files.
	 *
	 * Without ids, every page, post, synced pattern and template (all
	 * languages) that holds a legacy block is converted. Each converted post
	 * keeps a revision of its old content.
	 *
	 * ## OPTIONS
	 *
	 * [<id>...]
	 * : Only these post IDs.
	 *
	 * [--dry-run]
	 * : Show what would change; write nothing.
	 *
	 * [--patterns=<dir>]
	 * : Rewrite the *.php pattern files in this directory instead of posts
	 * (dev environments). The PHP header, inline PHP and {{media_id:…}} /
	 * {{media_url:…}} placeholders are kept.
	 *
	 * [--format=<format>]
	 * : Output format.
	 * ---
	 * default: text
	 * options:
	 *   - text
	 *   - json
	 * ---
	 *
	 * ## EXAMPLES
	 *
	 *     wp pediment convert --dry-run
	 *     wp pediment convert 12 14
	 *     wp pediment convert --patterns=wp-content/themes/acme/patterns --dry-run
	 *
	 * @param array<int,string>    $args      Post IDs.
	 * @param array<string,string> $assocArgs Options.
	 */
	public function __invoke( array $args, array $assocArgs ): void {
		$dry_run = (bool) \WP_CLI\Utils\get_flag_value( $assocArgs, 'dry-run', false );
		$json    = 'json' === ( $assocArgs['format'] ?? 'text' );

		if ( isset( $assocArgs['patterns'] ) ) {
			$dir = (string) $assocArgs['patterns'];
			if ( ! is_dir( $dir ) ) {
				\WP_CLI::error( "Not a directory: {$dir}" );
			}
			$rows = ( new PatternFiles( Converter::withDefaults(), new Policy(), new AttachmentMedia() ) )->run( $dir, $dry_run );
			\WP_CLI::log( self::renderPatterns( $rows, $json ) );
			if ( array() !== array_filter( $rows, static fn( array $row ): bool => 'failed' === $row['status'] ) ) {
				\WP_CLI::error( 'Some pattern files were not converted; see above.' );
			}
			return;
		}

		// Trusted: WP-CLI has no current user, so kses must not rewrite the backup or the write.
		$service = PostConverter::create( true );
		$ids     = array() === $args ? $service->candidates() : array_map( 'intval', $args );
		$report  = $service->runMany( $ids, $dry_run );
		\WP_CLI::log( self::render( $report, $json ) );
		if ( $report['summary']['failed'] > 0 ) {
			\WP_CLI::error( 'Some posts were not converted; see above.' );
		}
	}

	/**
	 * Text or JSON output for a post run.
	 *
	 * @param array $report PostConverter::runMany() result.
	 * @param bool  $json   JSON output.
	 */
	public static function render( array $report, bool $json ): string {
		if ( $json ) {
			return (string) wp_json_encode( $report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
		}
		$lines = array();
		foreach ( $report['posts'] as $post ) {
			$lines[] = sprintf( '%-9s %s #%d %s%s', $post['status'], $post['type'], $post['id'], $post['title'], '' !== $post['language'] ? " [{$post['language']}]" : '' );
			foreach ( array_merge( $post['converted'], array_map( static fn( string $name ): string => "{$name} (no mapper, left in place)", $post['unmapped'] ) ) as $name ) {
				$lines[] = "  block: {$name}";
			}
			foreach ( $post['errors'] as $error ) {
				$lines[] = "  error: {$error}";
			}
			foreach ( $post['notes'] as $note ) {
				$lines[] = "  note: {$note}";
			}
			if ( $post['restoreRevision'] > 0 ) {
				$lines[] = "  old content: revision {$post['restoreRevision']}";
			}
			if ( '' !== $post['diff'] ) {
				$lines[] = rtrim( $post['diff'] );
			}
		}
		$s       = $report['summary'];
		$lines[] = sprintf( '%d posts, %d legacy blocks: %d converted, %d to convert, %d unchanged, %d failed, %d skipped.', $s['posts'], $s['blocks'], $s['converted'], $s['preview'], $s['unchanged'], $s['failed'], $s['skipped'] );
		return implode( "\n", $lines );
	}

	/**
	 * Text or JSON output for a pattern run.
	 *
	 * @param list<array> $rows PatternFiles::run() result.
	 * @param bool        $json JSON output.
	 */
	public static function renderPatterns( array $rows, bool $json ): string {
		if ( $json ) {
			return (string) wp_json_encode( $rows, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
		}
		$lines = array();
		foreach ( $rows as $row ) {
			$lines[] = sprintf( '%-9s %s', $row['status'], $row['file'] );
			foreach ( $row['errors'] as $error ) {
				$lines[] = "  error: {$error}";
			}
			foreach ( $row['notes'] as $note ) {
				$lines[] = "  note: {$note}";
			}
			if ( '' !== $row['diff'] ) {
				$lines[] = rtrim( $row['diff'] );
			}
		}
		return implode( "\n", $lines );
	}
}
