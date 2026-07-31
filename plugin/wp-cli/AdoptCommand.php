<?php
/**
 * WP-CLI: `wp pediment adopt`.
 *
 * @package Pediment
 */

declare(strict_types=1);

namespace Pediment\Cli;

use Pediment\Language\LanguageRegistry;
use Pediment\Seeder\Adopter;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Exports a live page's block markup back into its pattern file.
 */
final class AdoptCommand {
	/**
	 * ## OPTIONS
	 *
	 * <key>
	 * : The seed key to adopt, as declared in the manifest.
	 *
	 * [--language=<code>]
	 * : Language to adopt. Defaults to the site's default language.
	 *
	 * [--dry-run]
	 * : Print the target file and size without writing.
	 *
	 * ## EXAMPLES
	 *
	 *     wp pediment adopt home --dry-run
	 *     wp pediment adopt guide/faq
	 *
	 * @when after_wp_load
	 *
	 * @param array<int,string>    $args      Positional args.
	 * @param array<string,string> $assocArgs Associative args.
	 */
	public function __invoke( array $args, array $assocArgs ): void {
		$provider = LanguageRegistry::provider();
		$language = (string) ( $assocArgs['language'] ?? $provider->defaultLanguage() );

		$result = ( new Adopter( $provider ) )->adopt( (string) ( $args[0] ?? '' ), $language, isset( $assocArgs['dry-run'] ) );

		foreach ( $result['errors'] as $error ) {
			\WP_CLI::warning( $error );
		}
		if ( [] !== $result['errors'] ) {
			\WP_CLI::error( 'Nothing was adopted.' );
		}

		$backupNote = '' !== $result['backup'] ? sprintf( ' Previous contents backed up to %s.', $result['backup'] ) : '';

		\WP_CLI::success(
			sprintf(
				'%s %s (%d bytes).%s',
				$result['written'] ? 'Wrote' : 'Would write',
				$result['path'],
				$result['bytes'],
				$backupNote
			)
		);
	}
}
