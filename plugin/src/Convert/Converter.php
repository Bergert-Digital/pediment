<?php
/**
 * Rewrites legacy Pediment blocks in block markup to core blocks.
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
 * Walks parsed blocks; every legacy block with a mapper is replaced (at any
 * depth), every other block is kept and walked. Output comes from
 * serialize_blocks(), never from string concatenation of user content.
 */
final class Converter {

	/**
	 * Opening delimiter of a legacy Pediment block, with any whitespace the
	 * block parser accepts after `<!--`.
	 */
	public const LEGACY_DELIMITER = '#<!--\s+wp:pediment/#';

	/**
	 * Legacy blocks whose render.php printed $content: HTML between their
	 * inner blocks was visible, so a mapper that reads innerBlocks only would
	 * drop it.
	 */
	private const CONTAINERS = array(
		'pediment/faq',
		'pediment/feature-grid',
		'pediment/logo-cloud',
		'pediment/media-text',
		'pediment/prose',
		'pediment/stat-grid',
		'pediment/steps',
		'pediment/testimonial-grid',
	);

	/**
	 * Mapper per legacy block name.
	 *
	 * @var array<string,Mapper>
	 */
	private array $mappers = array();

	/**
	 * Constructor.
	 *
	 * @param list<Mapper> $mappers Mappers.
	 */
	public function __construct( array $mappers ) {
		foreach ( $mappers as $mapper ) {
			foreach ( $mapper->names() as $name ) {
				$this->mappers[ $name ] = $mapper;
			}
		}
	}

	/**
	 * The converter with every shipped mapper.
	 */
	public static function withDefaults(): self {
		return new self(
			array(
				new Mappers\SectionHeadMapper(),
				new Mappers\ProseMapper(),
				new Mappers\PullQuoteMapper(),
				new Mappers\FeatureGridMapper(),
				new Mappers\StatGridMapper(),
				new Mappers\StepsMapper(),
				new Mappers\FaqMapper(),
				new Mappers\TestimonialGridMapper(),
				new Mappers\LogoCloudMapper(),
				new Mappers\HeroMapper(),
				new Mappers\CtaMapper(),
				new Mappers\MediaTextMapper(),
				new Mappers\ImageCaptionMapper(),
			)
		);
	}

	/**
	 * Legacy block names that have a mapper, sorted.
	 *
	 * @return list<string>
	 */
	public function mappedNames(): array {
		$names = array_keys( $this->mappers );
		sort( $names );
		return $names;
	}

	/**
	 * Converts markup. Markup without a legacy block comes back unchanged,
	 * byte for byte. So does markup the converter cannot convert safely; the
	 * result's `problems` say why.
	 *
	 * @param string $markup Serialized blocks.
	 * @param Media  $media  Image lookups.
	 */
	public function convert( string $markup, Media $media ): Result {
		$context = new Context( $this, $media );
		if ( 1 !== preg_match( self::LEGACY_DELIMITER, $markup ) ) {
			return $context->result( $markup );
		}
		// Registers theme style-variation names, as Policy::check() does, so
		// className rules see the same styles the Policy accepts.
		\WP_Theme_JSON_Resolver::get_theme_data();
		$blocks = $this->convertList( parse_blocks( $markup ), $context );
		$output = $context->changed() ? serialize_blocks( $blocks ) : $markup;
		if ( ! $context->refused() ) {
			foreach ( $this->leftovers( $output ) as $name ) {
				$context->refuse( "{$name} is still in the markup after conversion (its block comment does not parse); convert it by hand" );
			}
		}
		return $context->result( $context->refused() ? $markup : $output );
	}

	/**
	 * Mapped legacy block names whose opening delimiter is still in the markup
	 * (a block comment the parser read as text, such as attributes written by
	 * inline PHP).
	 *
	 * @param string $markup Converted markup.
	 * @return list<string>
	 */
	private function leftovers( string $markup ): array {
		preg_match_all( '#<!--\s+wp:(pediment/[a-z][a-z0-9_-]*)#', $markup, $matches );
		return array_values( array_unique( array_filter( $matches[1], fn( string $name ): bool => isset( $this->mappers[ $name ] ) ) ) );
	}

	/**
	 * Converts a list of parsed blocks; removed blocks are left out.
	 * Called through Context::inner().
	 *
	 * @param array   $blocks  Parsed blocks.
	 * @param Context $context Run.
	 * @return list<array>
	 */
	public function convertList( array $blocks, Context $context ): array {
		$out = array();
		foreach ( $blocks as $block ) {
			$converted = $this->convertBlock( $block, $context );
			if ( null !== $converted ) {
				$out[] = $converted;
			}
		}
		return $out;
	}

	/**
	 * One block: mapped when it has a mapper, else kept with its inner
	 * blocks walked.
	 *
	 * @param array   $block   Parsed block.
	 * @param Context $context Run.
	 */
	private function convertBlock( array $block, Context $context ): ?array {
		$name = $block['blockName'] ?? null;
		if ( is_string( $name ) && in_array( $name, self::CONTAINERS, true ) ) {
			$raw = self::rawHtml( $block );
			if ( '' !== $raw ) {
				// The block stays; the result keeps the input markup.
				$context->refuse( "{$name} holds HTML outside blocks; edit it into blocks first (\"{$raw}\")" );
				return $this->walk( $block, $context );
			}
		}
		if ( is_string( $name ) && isset( $this->mappers[ $name ] ) ) {
			$context->markConverted( $name );
			$mapped = $this->mappers[ $name ]->map( $block, $context );
			if ( null === $mapped ) {
				$context->note( "{$name}: rendered nothing, removed" );
			}
			return $mapped;
		}
		if ( is_string( $name ) && in_array( $name, Policy::LEGACY, true ) ) {
			$context->markUnmapped( $name );
		}
		return $this->walk( $block, $context );
	}

	/**
	 * Converts a kept block's inner blocks and keeps innerContent aligned:
	 * every null chunk stands for one inner block, so a removed inner block
	 * takes its chunk with it.
	 *
	 * @param array   $block   Parsed block.
	 * @param Context $context Run.
	 */
	private function walk( array $block, Context $context ): array {
		if ( empty( $block['innerBlocks'] ) ) {
			return $block;
		}
		$inner   = array();
		$content = array();
		$next    = 0;
		foreach ( (array) $block['innerContent'] as $chunk ) {
			if ( null !== $chunk ) {
				$content[] = $chunk;
				continue;
			}
			$child = $this->convertBlock( $block['innerBlocks'][ $next++ ], $context );
			if ( null !== $child ) {
				$inner[]   = $child;
				$content[] = null;
			}
		}
		$block['innerBlocks']  = $inner;
		$block['innerContent'] = $content;
		return $block;
	}

	/**
	 * The start of the HTML a block holds between its inner blocks, '' when
	 * there is only whitespace.
	 *
	 * @param array $block Parsed block.
	 */
	private static function rawHtml( array $block ): string {
		$text = '';
		foreach ( (array) ( $block['innerContent'] ?? array() ) as $chunk ) {
			$text .= is_string( $chunk ) ? $chunk . ' ' : ' ';
		}
		$text = trim( (string) preg_replace( '/\s+/', ' ', $text ) );
		return mb_strlen( $text ) > 60 ? mb_substr( $text, 0, 60 ) . '…' : $text;
	}
}
