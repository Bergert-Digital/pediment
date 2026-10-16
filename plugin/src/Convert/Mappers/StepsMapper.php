<?php
/**
 * Mapper: pediment/steps + pediment/step → numbered grid of step groups.
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
 * A group.is-style-steps (grid of at most 3 columns, 2 for exactly four steps,
 * one on phones; the style numbers its children)
 * → one group per step with an h3 and a paragraph.
 */
final class StepsMapper implements Mapper {

	public function names(): array {
		return array( 'pediment/steps', 'pediment/step' );
	}

	public function map( array $block, Context $context ): ?array {
		if ( 'pediment/step' === $block['blockName'] ) {
			return $this->step( $block, $context );
		}
		$steps = $context->inner( (array) ( $block['innerBlocks'] ?? array() ) );
		if ( array() === $steps ) {
			return null;
		}
		$group              = Attrs::put( array(), 'align', $context->align( $block ) );
		$group['className'] = $context->className( $block, 'core/group', 'is-style-steps' );
		$group['layout']    = Build::gridLayout( 4 === count( $steps ) ? 2 : min( 3, count( $steps ) ), '12.5rem' );
		return Build::group( $group + $context->carry( $block, 'Steps' ), $steps );
	}

	/**
	 * One step.
	 *
	 * @param array   $block   Legacy block.
	 * @param Context $context Run.
	 */
	private function step( array $block, Context $context ): ?array {
		$attrs = (array) ( $block['attrs'] ?? array() );
		$title = Attrs::rich( $attrs, 'title' );
		$text  = Attrs::rich( $attrs, 'text' );
		if ( '' === $title && '' === $text ) {
			return null;
		}
		$inner = array();
		if ( '' !== $title ) {
			$inner[] = Build::heading( 3, $title );
		}
		if ( '' !== $text ) {
			$inner[] = Build::paragraph( $text );
		}
		$group           = Attrs::put( array(), 'className', $context->className( $block, 'core/group' ) );
		$group['layout'] = array( 'type' => 'constrained' );
		return Build::group( $group + $context->carry( $block, 'Step' ), $inner );
	}
}
