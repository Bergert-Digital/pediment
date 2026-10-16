<?php
/**
 * Mapper: pediment/section-head → group with eyebrow, heading and lead.
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
 * Constrained group (preset bottom margin) → eyebrow paragraph, heading,
 * lead paragraph; centred unless the legacy block was aligned to start.
 */
final class SectionHeadMapper implements Mapper {

	public function names(): array {
		return array( 'pediment/section-head' );
	}

	public function map( array $block, Context $context ): ?array {
		$attrs    = (array) ( $block['attrs'] ?? array() );
		$eyebrow  = Attrs::rich( $attrs, 'eyebrow' );
		$headline = Attrs::rich( $attrs, 'headline' );
		$lead     = Attrs::rich( $attrs, 'lead' );
		if ( '' === $eyebrow && '' === $headline && '' === $lead ) {
			return null;
		}
		if ( '' !== Attrs::string( $attrs, 'maxWidth' ) ) {
			$context->note( 'pediment/section-head: maxWidth dropped, the group uses the content width' );
		}

		// block.json default is center; render.php treats every other value as start.
		$center = 'center' === Attrs::string( $attrs, 'alignment', 'center' );
		$text   = $center ? array( 'align' => 'center' ) : array();
		$inner  = array();
		if ( '' !== $eyebrow ) {
			$inner[] = Build::paragraph( $eyebrow, $text + array( 'className' => 'is-style-eyebrow' ) );
		}
		if ( '' !== $headline ) {
			$inner[] = Build::heading( 3 === Attrs::int( $attrs, 'level' ) ? 3 : 2, $headline, $center ? array( 'textAlign' => 'center' ) : array() );
		}
		if ( '' !== $lead ) {
			$inner[] = Build::paragraph( $lead, $text + array( 'className' => 'is-style-lead' ) );
		}

		$group           = Attrs::put( array(), 'align', $context->align( $block ) );
		$group           = Attrs::put( $group, 'className', $context->className( $block, 'core/group' ) );
		$group['style']  = array( 'spacing' => array( 'margin' => array( 'bottom' => 'var:preset|spacing|60' ) ) );
		$group['layout'] = $center ? array( 'type' => 'constrained' ) : array(
			'type'           => 'constrained',
			'justifyContent' => 'left',
		);
		return Build::group( $group + $context->carry( $block, 'Section head' ), $inner );
	}
}
