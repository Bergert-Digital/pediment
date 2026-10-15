<?php
/**
 * State of one conversion run, handed to every mapper.
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
 * Collects what was converted and noted, and gives mappers the shared rules
 * for inner blocks, class names and carried-over attributes.
 */
final class Context {

	/**
	 * Converted legacy block names.
	 *
	 * @var list<string>
	 */
	private array $converted = array();

	/**
	 * Legacy block names without a mapper.
	 *
	 * @var list<string>
	 */
	private array $unmapped = array();

	/**
	 * Notes and how often each occurred.
	 *
	 * @var array<string,int>
	 */
	private array $notes = array();

	/**
	 * Problems that stop the conversion, and how often each occurred.
	 *
	 * @var array<string,int>
	 */
	private array $problems = array();

	/**
	 * Constructor.
	 *
	 * @param Converter $converter Converter (for inner blocks).
	 * @param Media     $media     Image lookups.
	 */
	public function __construct( private Converter $converter, private Media $media ) {}

	/**
	 * Image lookups.
	 */
	public function media(): Media {
		return $this->media;
	}

	/**
	 * Inner blocks after conversion: legacy blocks mapped (at any depth),
	 * other blocks kept, removed blocks left out.
	 *
	 * @param array $blocks Parsed blocks.
	 * @return list<array>
	 */
	public function inner( array $blocks ): array {
		return $this->converter->convertList( $blocks, $this );
	}

	/**
	 * Records a note for the report. Repeated notes are counted.
	 *
	 * @param string $message Note.
	 */
	public function note( string $message ): void {
		$this->notes[ $message ] = ( $this->notes[ $message ] ?? 0 ) + 1;
	}

	/**
	 * Records a problem that makes the conversion unsafe: the run's result
	 * keeps the input markup, and callers write nothing. Repeats are counted.
	 *
	 * @param string $message Problem; names the legacy block and what to do.
	 */
	public function refuse( string $message ): void {
		$this->problems[ $message ] = ( $this->problems[ $message ] ?? 0 ) + 1;
	}

	/**
	 * Whether a problem was recorded.
	 */
	public function refused(): bool {
		return array() !== $this->problems;
	}

	/**
	 * Paragraph blocks for a RichText value that the legacy block printed
	 * inside a <div> or <blockquote>: a value of <p> elements becomes one
	 * paragraph per element (text between them becomes its own paragraph), so
	 * a <p> never sits inside a <p>. Other block-level HTML (lists, headings,
	 * …) cannot become paragraphs: the run is refused.
	 *
	 * @param string $html Escaped value (Attrs::html()).
	 * @param string $what Where the value comes from, for the report ("pediment/faq-item answer").
	 * @return list<array>
	 */
	public function paragraphs( string $html, string $what ): array {
		if ( 1 === preg_match( '#<(ul|ol|dl|div|h[1-6]|blockquote|table|figure|pre|hr|section|article|aside|header|footer|nav|address|details|form|fieldset)[\s/>]#i', $html, $match ) ) {
			$this->refuse( sprintf( '%s holds block HTML (<%s>); edit it by hand first', $what, strtolower( $match[1] ) ) );
			return array( Build::paragraph( $html ) );
		}
		if ( 1 !== preg_match( '#<p[\s>]#i', $html ) ) {
			return array( Build::paragraph( $html ) );
		}
		if ( 1 === preg_match( '#<p\s[^>]*[^\s>]#i', $html ) ) {
			$this->note( "{$what}: attributes of a <p> dropped" );
		}
		$out   = array();
		$parts = preg_split( '#<p(?:\s[^>]*)?>(.*?)</p>#is', $html, -1, PREG_SPLIT_DELIM_CAPTURE );
		foreach ( false === $parts ? array() : $parts as $part ) {
			$part = trim( $part );
			if ( '' !== trim( (string) preg_replace( '#<br\s*/?>#i', '', $part ) ) ) {
				$out[] = Build::paragraph( $part );
			}
		}
		return $out;
	}

	/**
	 * The className of a converted root block: the legacy block's plain classes,
	 * then $own (the mapper's style class), then each legacy is-style-* class
	 * that is registered for $target. Unregistered style classes are dropped
	 * and noted.
	 *
	 * @param array  $legacy Parsed legacy block.
	 * @param string $target Block name of the converted root block.
	 * @param string $own    Style class the mapper sets, '' for none.
	 */
	public function className( array $legacy, string $target, string $own = '' ): string {
		$plain  = array();
		$styles = array();
		$known  = Policy::registeredStyleNames( $target );
		$given  = preg_split( '/\s+/', Attrs::string( (array) ( $legacy['attrs'] ?? array() ), 'className' ), -1, PREG_SPLIT_NO_EMPTY );
		foreach ( false === $given ? array() : $given as $class ) {
			$class = sanitize_html_class( $class );
			if ( '' === $class ) {
				continue;
			}
			if ( ! str_starts_with( $class, 'is-style-' ) ) {
				$plain[] = $class;
			} elseif ( in_array( substr( $class, strlen( 'is-style-' ) ), $known, true ) ) {
				$styles[] = $class;
			} else {
				$this->note( "dropped style {$class} on {$legacy['blockName']} → {$target}" );
			}
		}
		$own = '' === $own ? array() : array( $own );
		return implode( ' ', array_values( array_unique( array_merge( $plain, $own, $styles ) ) ) );
	}

	/**
	 * The legacy block's align value when it is wide or full, else null.
	 *
	 * @param array $legacy Parsed legacy block.
	 */
	public function align( array $legacy ): ?string {
		$align = Attrs::string( (array) ( $legacy['attrs'] ?? array() ), 'align' );
		return in_array( $align, array( 'wide', 'full' ), true ) ? $align : null;
	}

	/**
	 * Attributes the converted root block inherits: lock, and the legacy
	 * metadata (list-view name, block visibility, pattern name, …) without
	 * `bindings`, which point to legacy attribute names (dropped and noted).
	 * metadata.name is the legacy block's own name, else $name; none when
	 * both are empty.
	 *
	 * @param array       $legacy Parsed legacy block.
	 * @param string|null $name   Default list-view name, null for none.
	 */
	public function carry( array $legacy, ?string $name ): array {
		$attrs = (array) ( $legacy['attrs'] ?? array() );
		$out   = array();
		if ( isset( $attrs['lock'] ) && is_array( $attrs['lock'] ) ) {
			$out['lock'] = $attrs['lock'];
		}
		$metadata = is_array( $attrs['metadata'] ?? null ) ? $attrs['metadata'] : array();
		if ( array_key_exists( 'bindings', $metadata ) ) {
			unset( $metadata['bindings'] );
			$this->note( "{$legacy['blockName']}: block bindings dropped, they named legacy attributes" );
		}
		$own = Attrs::string( $metadata, 'name' );
		unset( $metadata['name'] );
		$label = '' !== $own ? $own : (string) $name;
		if ( '' !== $label ) {
			$metadata = array( 'name' => $label ) + $metadata;
		}
		if ( array() !== $metadata ) {
			$out['metadata'] = $metadata;
		}
		return $out;
	}

	/**
	 * Records a converted (or removed) legacy block. Called by Converter.
	 *
	 * @param string $name Legacy block name.
	 */
	public function markConverted( string $name ): void {
		$this->converted[] = $name;
	}

	/**
	 * Records a legacy block left in place. Called by Converter.
	 *
	 * @param string $name Legacy block name.
	 */
	public function markUnmapped( string $name ): void {
		$this->unmapped[] = $name;
	}

	/**
	 * Whether anything was converted so far.
	 */
	public function changed(): bool {
		return array() !== $this->converted;
	}

	/**
	 * The run's result.
	 *
	 * @param string $markup Final markup.
	 */
	public function result( string $markup ): Result {
		$notes = self::counted( $this->notes );
		return new Result( $markup, $this->converted, array_values( array_unique( $this->unmapped ) ), $notes, self::counted( $this->problems ) );
	}

	/**
	 * Messages with their count ("… (3 times)") when they occurred more than once.
	 *
	 * @param array<string,int> $messages Message → count.
	 * @return list<string>
	 */
	private static function counted( array $messages ): array {
		$out = array();
		foreach ( $messages as $message => $count ) {
			$out[] = $count > 1 ? sprintf( '%s (%d times)', $message, $count ) : (string) $message;
		}
		return $out;
	}
}
