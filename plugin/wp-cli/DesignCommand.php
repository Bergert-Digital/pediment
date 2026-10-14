<?php
/**
 * WP-CLI: `wp pediment design`.
 *
 * @package Pediment
 */

declare(strict_types=1);

namespace Pediment\Cli;

use Pediment\Design\Exporter;
use Pediment\Design\SiteStyles;
use Pediment\Design\StyleValidator;
use Pediment\Design\Vocabulary;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Lists and exports design-layer styles.
 */
final class DesignCommand {

	/**
	 * Lists every style the site can use and its layer.
	 *
	 * ## OPTIONS
	 *
	 * [--format=<format>]
	 * : Output format.
	 * ---
	 * default: table
	 * options:
	 *   - table
	 *   - json
	 * ---
	 *
	 * @subcommand list
	 *
	 * @param array<int,string>    $args      Positional args (unused).
	 * @param array<string,string> $assocArgs Associative args.
	 */
	public function list_( array $args, array $assocArgs ): void {
		$validator = new StyleValidator();
		$rows      = array();
		foreach ( ( new Vocabulary( $validator, new SiteStyles( $validator ) ) )->styles() as $style ) {
			$rows[] = array(
				'slug'       => $style['slug'],
				'label'      => $style['label'],
				'blockTypes' => implode( ',', $style['blockTypes'] ),
				'layer'      => $style['layer'],
				'overrides'  => (string) $style['overrides'],
				'warnings'   => count( $style['warnings'] ),
			);
		}
		\WP_CLI\Utils\format_items( $assocArgs['format'] ?? 'table', $rows, array( 'slug', 'label', 'blockTypes', 'layer', 'overrides', 'warnings' ) );
	}

	/**
	 * Writes active site styles as theme partials.
	 *
	 * ## OPTIONS
	 *
	 * [--dir=<dir>]
	 * : Target directory. Defaults to the active theme's styles/blocks.
	 *
	 * ## EXAMPLES
	 *
	 *     wp pediment design export
	 *     wp pediment design export --dir=/tmp/styles
	 *
	 * @param array<int,string>    $args      Positional args (unused).
	 * @param array<string,string> $assocArgs Associative args.
	 */
	public function export( array $args, array $assocArgs ): void {
		$dir   = $assocArgs['dir'] ?? get_stylesheet_directory() . '/styles/blocks';
		$paths = ( new Exporter( new SiteStyles( new StyleValidator() ) ) )->writeTo( $dir );
		foreach ( $paths as $path ) {
			\WP_CLI::log( $path );
		}
		\WP_CLI::success( sprintf( '%d style file(s) written.', count( $paths ) ) );
	}
}
