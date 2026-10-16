<?php
/**
 * Mapper: pediment/faq + pediment/faq-item → stack of core/details accordions.
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
 * Vertical flex group → details.is-style-accordion (summary = question,
 * answer = one paragraph, or one per <p> in the answer). A lone faq-item
 * becomes a lone details block.
 */
final class FaqMapper implements Mapper {

	public function names(): array {
		return array( 'pediment/faq', 'pediment/faq-item' );
	}

	public function map( array $block, Context $context ): ?array {
		if ( 'pediment/faq-item' === $block['blockName'] ) {
			return $this->item( $block, $context );
		}
		$items = $context->inner( (array) ( $block['innerBlocks'] ?? array() ) );
		if ( array() === $items ) {
			return null;
		}
		$group           = array( 'tagName' => 'section' );
		$group           = Attrs::put( $group, 'align', $context->align( $block ) );
		$group           = Attrs::put( $group, 'className', $context->className( $block, 'core/group' ) );
		$group['layout'] = array(
			'type'           => 'flex',
			'orientation'    => 'vertical',
			'justifyContent' => 'stretch',
		);
		return Build::group( $group + $context->carry( $block, 'FAQ' ), $items );
	}

	/**
	 * One question.
	 *
	 * @param array   $block   Legacy block.
	 * @param Context $context Run.
	 */
	private function item( array $block, Context $context ): ?array {
		$attrs    = (array) ( $block['attrs'] ?? array() );
		$question = Attrs::rich( $attrs, 'question' );
		$answer   = Attrs::rich( $attrs, 'answer' );
		if ( '' === $question && '' === $answer ) {
			return null;
		}
		$details = array( 'className' => $context->className( $block, 'core/details', 'is-style-accordion' ) );
		return Build::details( $question, '' === $answer ? array() : $context->paragraphs( $answer, 'pediment/faq-item answer' ), $details + $context->carry( $block, null ) );
	}
}
