<?php
/**
 * Mapper: pediment/prose → constrained group with the prose style.
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
 * Inner blocks move unchanged (nested legacy blocks are converted) into a
 * constrained group.is-style-prose.
 */
final class ProseMapper implements Mapper {

	public function names(): array {
		return array( 'pediment/prose' );
	}

	public function map( array $block, Context $context ): ?array {
		$inner = $context->inner( (array) ( $block['innerBlocks'] ?? array() ) );
		if ( array() === $inner ) {
			return null;
		}
		$group              = Attrs::put( array(), 'align', $context->align( $block ) );
		$group['className'] = $context->className( $block, 'core/group', 'is-style-prose' );
		$group['layout']    = array( 'type' => 'constrained' );
		return Build::group( $group + $context->carry( $block, 'Prose' ), $inner );
	}
}
