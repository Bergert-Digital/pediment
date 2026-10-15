<?php
/**
 * Converts legacy blocks in a client theme's patterns/*.php files.
 *
 * @package Pediment
 */

declare(strict_types=1);

namespace Pediment\Convert;

use Pediment\Content\Policy;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Rewrites pattern files in place. The leading PHP header is kept byte for
 * byte. Inline PHP (`<?php … ?>`) and seeder media placeholders
 * (`{{media_id:key}}`, `{{media_url:key}}`) are swapped for stand-ins before
 * the block parser sees the file and swapped back afterwards. A file whose
 * inline PHP would not survive the conversion is not written.
 */
final class PatternFiles implements Media {

	private const ID_BASE = 9100000;

	private const URL_BASE = 'https://media.pediment.invalid/';

	/**
	 * Media key per stand-in ID, for the current file.
	 *
	 * @var array<int,string>
	 */
	private array $keys = array();

	/**
	 * Constructor.
	 *
	 * @param Converter $converter Converter.
	 * @param Policy    $policy    Content rules.
	 * @param Media     $media     Lookups for real attachment IDs in a pattern.
	 */
	public function __construct( private Converter $converter, private Policy $policy, private Media $media ) {}

	/**
	 * Converts (or previews) every patterns/*.php file in a directory.
	 *
	 * @param string $dir     Directory that holds the pattern files.
	 * @param bool   $dry_run Preview only.
	 * @return list<array{file:string,status:string,converted:list<string>,blocks:int,unmapped:list<string>,notes:list<string>,errors:list<string>,diff:string}>
	 */
	public function run( string $dir, bool $dry_run ): array {
		$files = glob( rtrim( $dir, '/' ) . '/*.php' );
		$out   = array();
		foreach ( false === $files ? array() : $files as $file ) {
			$text   = (string) file_get_contents( $file ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- local theme file.
			$report = $this->convertText( $text );
			$row    = array( 'file' => $file ) + $report;
			unset( $row['text'] );
			if ( 'preview' === $report['status'] && ! $dry_run ) {
				file_put_contents( $file, $report['text'] ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- dev-only CLI rewrite of a theme file.
				$row['status'] = 'converted';
				$row['diff']   = '';
			}
			$out[] = $row;
		}
		return $out;
	}

	/**
	 * Converts the text of one pattern file.
	 *
	 * Status: preview (would change; `text` holds the new file), unchanged,
	 * failed (`errors` says why; `text` is the old file).
	 *
	 * @param string $text File contents.
	 * @return array{text:string,status:string,converted:list<string>,blocks:int,unmapped:list<string>,notes:list<string>,errors:list<string>,diff:string}
	 */
	public function convertText( string $text ): array {
		$header = '';
		$body   = $text;
		if ( 1 === preg_match( '/^<\?php.*?\?>\s*/s', $text, $match ) ) {
			$header = $match[0];
			$body   = substr( $text, strlen( $header ) );
		}

		$php        = array();
		$this->keys = array();
		$protected  = (string) preg_replace_callback(
			'/<\?(?:php|=).*?\?>/s',
			static function ( array $m ) use ( &$php ): string {
				$token         = '__PEDIMENT_PHP_' . count( $php ) . '__';
				$php[ $token ] = $m[0];
				return $token;
			},
			$body
		);
		$protected = (string) preg_replace_callback(
			'/\{\{media_(id|url):([a-z0-9_\-\/]+)\}\}/i',
			function ( array $m ): string {
				$id = $this->standIn( $m[2] );
				return 'id' === strtolower( $m[1] ) ? (string) $id : self::URL_BASE . $id . '.jpg';
			},
			$protected
		);

		$result = $this->converter->convert( $protected, $this );
		$names  = array_values( array_unique( $result->converted ) );
		sort( $names );
		$report = array(
			'text'      => $text,
			'status'    => 'unchanged',
			'converted' => $names,
			'blocks'    => count( $result->converted ),
			'unmapped'  => $result->unmapped,
			'notes'     => $result->notes,
			'errors'    => array(),
			'diff'      => '',
		);
		if ( ! $result->changed() ) {
			return $report;
		}

		$errors   = PostConverter::addedErrors( $this->policy->check( $protected )['errors'], $this->policy->check( $result->markup )['errors'] );
		$restored = $result->markup;
		foreach ( $php as $token => $code ) {
			// esc_url() prefixes a bare token with http://.
			$restored = str_replace( 'http://' . $token, $token, $restored );
			if ( 1 !== substr_count( $restored, $token ) ) {
				$errors[] = 'inline PHP ' . $code . ' would not survive the conversion — convert this file by hand';
			}
			$restored = str_replace( $token, $code, $restored );
		}
		foreach ( $this->keys as $id => $key ) {
			$restored = str_replace( self::URL_BASE . $id . '.jpg', '{{media_url:' . $key . '}}', $restored );
			$restored = (string) preg_replace( '/(?<![0-9])' . $id . '(?![0-9])/', '{{media_id:' . $key . '}}', $restored );
		}
		if ( array() !== $errors ) {
			$report['status'] = 'failed';
			$report['errors'] = $errors;
			return $report;
		}
		$report['text']   = $header . $restored;
		$report['status'] = 'preview';
		$report['diff']   = Diff::unified( $text, $report['text'] );
		return $report;
	}

	public function url( int $id, string $size ): string {
		return isset( $this->keys[ $id ] ) ? self::URL_BASE . $id . '.jpg' : $this->media->url( $id, $size );
	}

	public function alt( int $id ): string {
		return isset( $this->keys[ $id ] ) ? '' : $this->media->alt( $id );
	}

	/**
	 * The stand-in ID for a media key (one per key and file).
	 *
	 * @param string $key Media key.
	 */
	private function standIn( string $key ): int {
		$id = array_search( $key, $this->keys, true );
		if ( false === $id ) {
			$id                = self::ID_BASE + count( $this->keys ) + 1;
			$this->keys[ $id ] = $key;
		}
		return (int) $id;
	}
}
