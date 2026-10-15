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
	 * How often an image block asked for the alt text of a stand-in, per media key.
	 *
	 * @var array<string,int>
	 */
	private array $alt_misses = array();

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
	 * Only regular, readable files inside the directory are touched (a symlink
	 * that leaves it is skipped). A file that cannot be read or written is
	 * reported as failed; it does not stop the other files. A write goes
	 * through a temporary file and rename(), so an interrupted write cannot
	 * truncate a pattern.
	 *
	 * @param string $dir     Directory that holds the pattern files.
	 * @param bool   $dry_run Preview only.
	 * @return list<array{file:string,status:string,converted:list<string>,blocks:int,unmapped:list<string>,notes:list<string>,errors:list<string>,diff:string}>
	 */
	public function run( string $dir, bool $dry_run ): array {
		$root = realpath( $dir );
		if ( false === $root ) {
			return array();
		}
		$files = glob( $root . '/*.php' );
		$out   = array();
		foreach ( false === $files ? array() : $files as $file ) {
			$real = realpath( $file );
			if ( false === $real || 0 !== strpos( $real, $root . DIRECTORY_SEPARATOR ) ) {
				$out[] = self::row( $file, 'skipped', array( 'outside the pattern directory or a broken link; not touched' ) );
				continue;
			}
			if ( ! is_file( $real ) ) {
				$out[] = self::row( $file, 'skipped', array( 'not a regular file' ) );
				continue;
			}
			$text = is_readable( $real ) ? file_get_contents( $real ) : false; // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- local theme file.
			if ( false === $text ) {
				$out[] = self::row( $file, 'failed', array(), array( 'cannot read the file' ) );
				continue;
			}
			$report = $this->convertText( $text );
			$row    = array( 'file' => $file ) + $report;
			unset( $row['text'] );
			if ( 'preview' === $report['status'] && ! $dry_run ) {
				$problem = self::write( $real, $report['text'] );
				if ( '' === $problem ) {
					$row['status'] = 'converted';
					$row['diff']   = '';
				} else {
					$row['status'] = 'failed';
					$row['errors'] = array( $problem );
					$row['diff']   = '';
				}
			}
			$out[] = $row;
		}
		return $out;
	}

	/**
	 * Converts the text of one pattern file.
	 *
	 * Status: preview (would change; `text` holds the new file), unchanged,
	 * failed (the converter refuses the markup, see Result::$problems, or the
	 * conversion would add a content-rule error or lose inline PHP; `errors`
	 * says why; `text` is the old file), skipped (not a pattern
	 * file: no PHP header with Title/Slug).
	 *
	 * @param string $text File contents.
	 * @return array{text:string,status:string,converted:list<string>,blocks:int,unmapped:list<string>,notes:list<string>,errors:list<string>,diff:string}
	 */
	public function convertText( string $text ): array {
		$report = array(
			'text'      => $text,
			'status'    => 'unchanged',
			'converted' => array(),
			'blocks'    => 0,
			'unmapped'  => array(),
			'notes'     => array(),
			'errors'    => array(),
			'diff'      => '',
		);
		// A pattern file starts with a PHP header that carries Title/Slug; anything else (functions.php, pure PHP) is not a pattern.
		if ( 1 !== preg_match( '/^<\?php.*?\?>\s*/s', $text, $match ) || 1 !== preg_match( '/^\s*\*\s*(?:Title|Slug):/mi', $match[0] ) ) {
			$report['status'] = 'skipped';
			$report['notes']  = array( 'no pattern header' );
			return $report;
		}
		$header = $match[0];
		$body   = substr( $text, strlen( $header ) );

		$php             = array();
		$this->keys      = array();
		$this->alt_misses = array();
		$protected       = (string) preg_replace_callback(
			'/<\?(?:php|=).*?\?>/s',
			static function ( array $m ) use ( &$php ): string {
				$token         = '__PEDIMENT_PHP_' . count( $php ) . '__';
				$php[ $token ] = $m[0];
				return $token;
			},
			$body
		);
		$plain     = $protected;
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
		$notes = $result->notes;
		foreach ( $this->alt_misses as $key => $count ) {
			$notes[] = "alt text not resolvable for {{media_id:{$key}}}; set it by hand" . ( $count > 1 ? " ({$count} times)" : '' );
		}
		$report['converted'] = $names;
		$report['blocks']    = count( $result->converted );
		$report['unmapped']  = $result->unmapped;
		$report['notes']     = $notes;
		if ( array() !== $result->problems ) {
			$report['status'] = 'failed';
			$report['errors'] = array_map( static fn( string $problem ): string => strtr( $problem, $php ), $result->problems );
			return $report;
		}
		if ( ! $result->changed() ) {
			return $report;
		}
		if ( $this->standInClash( $plain ) ) {
			$report['status'] = 'failed';
			$report['errors'] = array( 'the file holds a number or URL that clashes with a media stand-in — convert this file by hand' );
			return $report;
		}

		$errors   = PostConverter::addedErrors( $this->policy->check( $protected )['errors'], $this->policy->check( $result->markup )['errors'] );
		$restored = $result->markup;
		// Media first: inline PHP is still a token here, so a number inside it is never rewritten.
		foreach ( $this->keys as $id => $key ) {
			$restored = str_replace( self::URL_BASE . $id . '.jpg', '{{media_url:' . $key . '}}', $restored );
			$restored = (string) preg_replace( '/(?<![0-9])' . $id . '(?![0-9])/', '{{media_id:' . $key . '}}', $restored );
		}
		foreach ( $php as $token => $code ) {
			// esc_url() prefixes a bare token with http://; a literal http:// the author wrote stays.
			if ( ! str_contains( $protected, 'http://' . $token ) ) {
				$restored = str_replace( 'http://' . $token, $token, $restored );
			}
			if ( 1 !== substr_count( $restored, $token ) ) {
				$errors[] = 'inline PHP ' . $code . ' would not survive the conversion — convert this file by hand';
			}
			$restored = str_replace( $token, $code, $restored );
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
		if ( isset( $this->keys[ $id ] ) ) {
			$key                      = $this->keys[ $id ];
			$this->alt_misses[ $key ] = ( $this->alt_misses[ $key ] ?? 0 ) + 1;
			return '';
		}
		return $this->media->alt( $id );
	}

	/**
	 * Whether the file already holds a stand-in ID or URL (it would be rewritten by mistake).
	 *
	 * @param string $plain File body with inline PHP and placeholders still in place of the stand-ins.
	 */
	private function standInClash( string $plain ): bool {
		if ( array() === $this->keys ) {
			return false;
		}
		if ( str_contains( $plain, self::URL_BASE ) ) {
			return true;
		}
		foreach ( array_keys( $this->keys ) as $id ) {
			if ( 1 === preg_match( '/(?<![0-9])' . $id . '(?![0-9])/', $plain ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Writes a file through a temporary file in the same directory.
	 *
	 * @param string $path Real path of the file.
	 * @param string $text New contents.
	 * @return string The problem, or '' when the file was replaced.
	 */
	private static function write( string $path, string $text ): string {
		$dir = dirname( $path );
		if ( ! is_writable( $path ) ) { // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_is_writable -- local theme file.
			return 'the file is not writable';
		}
		if ( ! is_writable( $dir ) ) { // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_is_writable -- local theme directory.
			return 'the directory is not writable';
		}
		$tmp = tempnam( $dir, '.pediment-' );
		if ( false === $tmp ) {
			return 'cannot create a temporary file next to the pattern';
		}
		$written = file_put_contents( $tmp, $text ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- dev-only CLI rewrite of a theme file.
		if ( strlen( $text ) !== $written ) {
			wp_delete_file( $tmp );
			return 'writing the file failed (disk full?); the pattern is unchanged';
		}
		chmod( $tmp, fileperms( $path ) & 0777 ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_chmod -- keep the pattern file mode.
		if ( ! rename( $tmp, $path ) ) { // phpcs:ignore WordPress.WP.AlternativeFunctions.rename_rename -- atomic replace of a theme file.
			wp_delete_file( $tmp );
			return 'replacing the file failed; the pattern is unchanged';
		}
		return '';
	}

	/**
	 * A report row for a file that was not converted.
	 *
	 * @param string       $file   File path.
	 * @param string       $status skipped or failed.
	 * @param list<string> $notes  Notes.
	 * @param list<string> $errors Errors.
	 */
	private static function row( string $file, string $status, array $notes = array(), array $errors = array() ): array {
		return array(
			'file'      => $file,
			'status'    => $status,
			'converted' => array(),
			'blocks'    => 0,
			'unmapped'  => array(),
			'notes'     => $notes,
			'errors'    => $errors,
			'diff'      => '',
		);
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
