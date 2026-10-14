<?php
/**
 * Exports active site-layer styles as client-theme block style partials.
 *
 * @package Pediment
 */

declare(strict_types=1);

namespace Pediment\Design;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Turns site styles into `styles/blocks/<slug>.json` files. Never deletes.
 */
final class Exporter {

	/**
	 * Constructor.
	 *
	 * @param SiteStyles $store Store.
	 */
	public function __construct( private SiteStyles $store ) {}

	/**
	 * File name => JSON for every active site style.
	 *
	 * @return array<string, string>
	 */
	public function files(): array {
		$files = array();
		foreach ( $this->store->all( SiteStyles::STATE_ACTIVE ) as $spec ) {
			$partial = array(
				'$schema'    => 'https://schemas.wp.org/trunk/theme.json',
				'version'    => 3,
				'title'      => $spec['label'],
				'slug'       => $spec['slug'],
				'blockTypes' => $spec['blockTypes'],
				'styles'     => $spec['styles'],
			);
			$files[ $spec['slug'] . '.json' ] = (string) wp_json_encode( $partial, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) . "\n";
		}
		return $files;
	}

	/**
	 * Writes the files into a directory.
	 *
	 * @param string $dir Target directory.
	 * @return list<string> Written paths.
	 */
	public function writeTo( string $dir ): array {
		$files = $this->files();
		if ( array() === $files ) {
			return array();
		}
		wp_mkdir_p( $dir );
		$paths = array();
		foreach ( $files as $name => $json ) {
			$path = rtrim( $dir, '/' ) . '/' . $name;
			file_put_contents( $path, $json ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
			$paths[] = $path;
		}
		return $paths;
	}

	/**
	 * Builds a zip with styles/blocks/*.json.
	 *
	 * @return string|\WP_Error Zip path.
	 */
	public function zip() {
		if ( ! class_exists( '\ZipArchive' ) ) {
			return new \WP_Error( 'pediment_zip_unavailable', __( 'This server has no ZipArchive. Use `wp pediment design export` instead.', 'pediment' ) );
		}
		if ( ! function_exists( 'wp_tempnam' ) ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
		}
		$path    = wp_tempnam( 'pediment-styles.zip' );
		$archive = new \ZipArchive();
		if ( true !== $archive->open( $path, \ZipArchive::CREATE | \ZipArchive::OVERWRITE ) ) {
			return new \WP_Error( 'pediment_zip_failed', __( 'Could not create the zip file.', 'pediment' ) );
		}
		foreach ( $this->files() as $name => $json ) {
			$archive->addFromString( 'styles/blocks/' . $name, $json );
		}
		$archive->close();
		return $path;
	}
}
