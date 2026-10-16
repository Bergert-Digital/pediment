<?php
/**
 * Mapper: pediment/stat-grid + pediment/stat → grid group of stat groups.
 *
 * @package Pediment
 */

declare(strict_types=1);

namespace Pediment\Convert\Mappers;

use Pediment\Convert\Attrs;
use Pediment\Convert\Build;
use Pediment\Convert\Context;
use Pediment\Convert\Mapper;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Grid (one column per stat up to four, fewer on narrow screens) of group.is-style-stat → value, label, context
 * paragraphs, the ones that are set (see Build::statParagraphs()).
 */
final class StatGridMapper implements Mapper {

	public function names(): array {
		return array( 'pediment/stat-grid', 'pediment/stat' );
	}

	public function map( array $block, Context $context ): ?array {
		if ( 'pediment/stat' === $block['blockName'] ) {
			return $this->stat( $block, $context );
		}
		$stats = $context->inner( (array) ( $block['innerBlocks'] ?? array() ) );
		if ( array() === $stats ) {
			return null;
		}
		$group           = Attrs::put( array(), 'align', $context->align( $block ) );
		$group           = Attrs::put( $group, 'className', $context->className( $block, 'core/group' ) );
		$group['layout'] = Build::gridLayout( min( 4, count( $stats ) ), '10rem' );
		return Build::group( $group + $context->carry( $block, 'Stats' ), $stats );
	}

	/**
	 * One stat.
	 *
	 * @param array   $block   Legacy block.
	 * @param Context $context Run.
	 */
	private function stat( array $block, Context $context ): ?array {
		$attrs = (array) ( $block['attrs'] ?? array() );
		$value = Attrs::rich( $attrs, 'value' );
		$label = Attrs::rich( $attrs, 'label' );
		if ( '' === $value && '' === $label ) {
			return null;
		}
		$inner = Build::statParagraphs( $value, $label, Attrs::rich( $attrs, 'context' ) );
		$group = array(
			'className' => $context->className( $block, 'core/group', 'is-style-stat' ),
			'layout'    => array( 'type' => 'constrained' ),
		);
		return Build::group( $group + $context->carry( $block, 'Stat' ), $inner );
	}
}
