<?php
/**
 * Mapper: pediment/logo-cloud → group with the logos style.
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
 * A group.is-style-logos → caption paragraph and a wrapping flex row that
 * holds the legacy block's image blocks unchanged.
 */
final class LogoCloudMapper implements Mapper {

	public function names(): array {
		return array( 'pediment/logo-cloud' );
	}

	public function map( array $block, Context $context ): ?array {
		$caption = Attrs::rich( (array) ( $block['attrs'] ?? array() ), 'caption' );
		$logos   = $context->inner( (array) ( $block['innerBlocks'] ?? array() ) );
		if ( '' === $caption && array() === $logos ) {
			return null;
		}
		$inner = array();
		if ( '' !== $caption ) {
			$inner[] = Build::paragraph( $caption, array( 'align' => 'center' ) );
		}
		if ( array() !== $logos ) {
			$inner[] = Build::group(
				array(
					'layout'   => array(
						'type'           => 'flex',
						'flexWrap'       => 'wrap',
						'justifyContent' => 'space-between',
					),
					'metadata' => array( 'name' => 'Logo row' ),
				),
				$logos
			);
		}
		$group              = array( 'tagName' => 'section' );
		$group              = Attrs::put( $group, 'align', $context->align( $block ) );
		$group['className'] = $context->className( $block, 'core/group', 'is-style-logos' );
		$group['layout']    = array( 'type' => 'constrained' );
		return Build::group( $group + $context->carry( $block, 'Logos' ), $inner );
	}
}
