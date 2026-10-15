<?php
/**
 * Mapper: pediment/cta → panel group with pill buttons.
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
 * Section group.is-style-panel → h2, paragraph, centred buttons (pill and
 * pill-ghost; each only when its text and URL are set).
 */
final class CtaMapper implements Mapper {

	public function names(): array {
		return array( 'pediment/cta' );
	}

	public function map( array $block, Context $context ): ?array {
		$attrs   = (array) ( $block['attrs'] ?? array() );
		$inner   = array();
		$title   = Attrs::rich( $attrs, 'title' );
		$body    = Attrs::rich( $attrs, 'body' );
		$buttons = array();
		foreach ( array( 'primary' => 'pill', 'secondary' => 'pill-ghost' ) as $prefix => $style ) {
			$label = Attrs::rich( $attrs, "{$prefix}Text" );
			$href  = Attrs::url( $attrs, "{$prefix}Url" );
			if ( '' !== $label && '' !== $href ) {
				$buttons[] = Build::button( $label, $href, $style );
			}
		}
		if ( '' !== $title ) {
			$inner[] = Build::heading( 2, $title );
		}
		if ( '' !== $body ) {
			$inner[] = Build::paragraph( $body );
		}
		if ( array() !== $buttons ) {
			$inner[] = Build::buttons(
				$buttons,
				array(
					'layout' => array(
						'type'           => 'flex',
						'justifyContent' => 'center',
					),
				)
			);
		}
		if ( array() === $inner ) {
			return null;
		}
		$group              = array( 'tagName' => 'section' );
		$group              = Attrs::put( $group, 'align', $context->align( $block ) );
		$group['className'] = $context->className( $block, 'core/group', 'is-style-panel' );
		$group['layout']    = array( 'type' => 'constrained' );
		return Build::group( $group + $context->carry( $block, 'Call to action' ), $inner );
	}
}
